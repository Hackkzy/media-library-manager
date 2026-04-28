<?php
/**
 * Database installer and upgrade routines.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Core;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles DB index creation and version-gated migrations.
 *
 * @since 1.0.0
 */
class Installer {

	/**
	 * Current DB schema version. Bump this integer whenever a new migration is added.
	 *
	 * @var int
	 */
	const DB_VERSION = 1;

	/**
	 * WordPress option key used to track the installed DB schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'blp_mlm_db_version';

	/**
	 * Name of the composite postmeta index created by this plugin.
	 *
	 * @var string
	 */
	const INDEX_NAME = 'blp_mlm_meta_key_value';

	/**
	 * Called from register_activation_hook. Delegates to maybe_upgrade().
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::maybe_upgrade();
	}

	/**
	 * Called from register_deactivation_hook.
	 *
	 * Intentionally a no-op. Dropping the index on deactivation would hurt performance
	 * on re-activation and is unnecessary for correctness. Index removal is handled in
	 * uninstall.php (permanent removal only).
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// No-op. See docblock.
	}

	/**
	 * Version-gated upgrade runner. Safe to call on every admin_init — after the first
	 * successful run the get_option() call short-circuits via the autoloaded options cache
	 * (memory only, no DB query).
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed_version = (int) get_option( self::DB_VERSION_OPTION, 0 );

		if ( $installed_version < self::DB_VERSION ) {
			self::create_indexes();
			// Autoloaded (true) so subsequent checks are served from the options cache.
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	/**
	 * Create the composite postmeta index used by duplicate-detection queries.
	 *
	 * Uses information_schema to check existence first — MySQL does not support
	 * CREATE INDEX IF NOT EXISTS, so the check-then-create pattern is required.
	 *
	 * Index columns:
	 *   meta_key(32)   — varchar(255); prefix covers all plugin keys (longest: 18 chars)
	 *   meta_value(64) — longtext; prefix covers full SHA-256 hashes (64 chars)
	 *
	 * @return void
	 */
	private static function create_indexes(): void {
		global $wpdb;

		// Check whether the index already exists before attempting to create it.
		// Table name comes from $wpdb->postmeta (trusted WP property), not user input.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS
				WHERE table_schema = DATABASE()
				  AND table_name = %s
				  AND index_name = %s',
				$wpdb->postmeta,
				self::INDEX_NAME
			)
		);

		if ( 0 === $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				// Cannot use $wpdb->prepare() for table/index names (not value placeholders).
				// $wpdb->postmeta is a trusted WP core property.
				'CREATE INDEX `' . self::INDEX_NAME . "` ON `{$wpdb->postmeta}` (meta_key(32), meta_value(64))" // phpcs:ignore
			);
		}
	}

	/**
	 * Drop the composite postmeta index. Called from uninstall.php only.
	 *
	 * @return void
	 */
	public static function drop_indexes(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.STATISTICS
				WHERE table_schema = DATABASE()
				  AND table_name = %s
				  AND index_name = %s',
				$wpdb->postmeta,
				self::INDEX_NAME
			)
		);

		if ( $exists > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				'DROP INDEX `' . self::INDEX_NAME . "` ON `{$wpdb->postmeta}`" // phpcs:ignore
			);
		}

		delete_option( self::DB_VERSION_OPTION );
	}
}
