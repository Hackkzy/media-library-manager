<?php
/**
 * Read-only data provider for media queries (duplicates, groups, trashed).
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
 * Fetches media data for duplicates and plugin trash. No mutations, no UI.
 *
 * @since 1.0.0
 */
class MediaDataProvider {

	/**
	 * Meta key for file hash (duplicate detection).
	 *
	 * @var string
	 */
	const META_KEY_HASH = 'blp_mlm_file_hash';

	/**
	 * Meta key for plugin trash.
	 *
	 * @var string
	 */
	const META_KEY_TRASH = 'blp_mlm_trash';

	/**
	 * Duplicate attachments (have hash, in a group with count > 1, not trashed). Paginated, ordered by hash.
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return array<int, object> Rows with ID, post_title, post_author, post_date, file_hash.
	 */
	public function get_duplicate_attachments( int $limit, int $offset ): array {
		global $wpdb;

		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		// Subquery counts only non-trashed items per hash so a lone survivor after trashing doesn't appear as a duplicate.
		$sql = $wpdb->prepare(
			"SELECT
				p.ID,
				p.post_title,
				p.post_author,
				p.post_date,
				pm.meta_value AS file_hash
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			INNER JOIN (
				SELECT pm_i.meta_value
				FROM {$wpdb->postmeta} pm_i
				INNER JOIN {$wpdb->posts} p_i ON p_i.ID = pm_i.post_id
				LEFT JOIN {$wpdb->postmeta} pm_ti ON pm_ti.post_id = pm_i.post_id AND pm_ti.meta_key = %s
				WHERE pm_i.meta_key = %s
				  AND p_i.post_type = 'attachment'
				  AND p_i.post_status = 'inherit'
				  AND pm_ti.meta_id IS NULL
				GROUP BY pm_i.meta_value
				HAVING COUNT(*) > 1
			) AS dup_hashes ON pm.meta_value = dup_hashes.meta_value
			LEFT JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			  AND pm_trash.meta_id IS NULL
			ORDER BY pm.meta_value ASC, p.ID ASC
			LIMIT %d OFFSET %d",
			self::META_KEY_HASH,
			self::META_KEY_TRASH,
			self::META_KEY_HASH,
			self::META_KEY_TRASH,
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		$results = $wpdb->get_results( $sql );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Total count of duplicate attachments (hash present, group size > 1, not trashed).
	 *
	 * @return int
	 */
	public function get_duplicate_attachments_count(): int {
		global $wpdb;

		// Subquery counts only non-trashed items per hash so a lone survivor after trashing isn't counted.
		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			INNER JOIN (
				SELECT pm_i.meta_value
				FROM {$wpdb->postmeta} pm_i
				INNER JOIN {$wpdb->posts} p_i ON p_i.ID = pm_i.post_id
				LEFT JOIN {$wpdb->postmeta} pm_ti ON pm_ti.post_id = pm_i.post_id AND pm_ti.meta_key = %s
				WHERE pm_i.meta_key = %s
				  AND p_i.post_type = 'attachment'
				  AND p_i.post_status = 'inherit'
				  AND pm_ti.meta_id IS NULL
				GROUP BY pm_i.meta_value
				HAVING COUNT(*) > 1
			) AS dup_hashes ON pm.meta_value = dup_hashes.meta_value
			LEFT JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			  AND pm_trash.meta_id IS NULL",
			self::META_KEY_HASH,
			self::META_KEY_TRASH,
			self::META_KEY_HASH,
			self::META_KEY_TRASH
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Duplicate hash values (groups with more than one non-trashed attachment). Paginated.
	 *
	 * @param int    $limit     Max hashes.
	 * @param string $last_hash Cursor: return hashes with meta_value greater than this (empty = first page).
	 * @return array<int, string> List of file hashes.
	 */
	public function get_duplicate_hashes( int $limit, string $last_hash = '' ): array {
		global $wpdb;

		$limit     = max( 1, (int) $limit );
		$last_hash = (string) $last_hash;

		// LEFT JOIN anti-join replaces the correlated NOT EXISTS — single-pass, index-friendly.
		$sql = $wpdb->prepare(
			"SELECT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p
				ON p.ID = pm.post_id
			LEFT JOIN {$wpdb->postmeta} pm_trash
				ON pm_trash.post_id = p.ID AND pm_trash.meta_key = %s
			WHERE pm.meta_key = %s
			AND p.post_type = 'attachment'
			AND p.post_status = 'inherit'
			AND pm.meta_value > %s
			AND pm_trash.meta_id IS NULL
			GROUP BY pm.meta_value
			HAVING COUNT(*) > 1
			ORDER BY pm.meta_value ASC
			LIMIT %d",
			self::META_KEY_TRASH,
			self::META_KEY_HASH,
			$last_hash,
			$limit
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		$results = $wpdb->get_col( $sql );

		return is_array( $results ) ? array_values( $results ) : array();
	}

	/**
	 * One page of attachments for a given hash (excludes trashed). Paginated.
	 *
	 * @param string $hash  File hash (meta_value).
	 * @param int    $limit Max rows.
	 * @param int    $offset Offset.
	 * @return array<int, object> Rows with ID, post_title, post_author, post_date, file_hash.
	 */
	public function get_group_batch( string $hash, int $limit, int $offset = 0 ): array {
		global $wpdb;

		// prevent useless query
		if ( '' === $hash ) {
			return array();
		}

		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_author, p.post_date, pm.meta_value AS file_hash
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			  AND pm_trash.meta_id IS NULL
			ORDER BY p.ID ASC
			LIMIT %d OFFSET %d",
			self::META_KEY_HASH,
			$hash,
			self::META_KEY_TRASH,
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		$results = $wpdb->get_results( $sql );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Total number of attachments for a hash (excluding trashed).
	 *
	 * @param string $hash File hash.
	 * @return int
	 */
	public function get_group_count( string $hash ): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s
			LEFT JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			  AND pm_trash.meta_id IS NULL",
			self::META_KEY_HASH,
			$hash,
			self::META_KEY_TRASH
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Total count of duplicate hashes (groups with more than one non-trashed attachment).
	 *
	 * @return int
	 */
	public function get_duplicate_hashes_count(): int {
		global $wpdb;

		// LEFT JOIN anti-join inside the inner query — avoids correlated subquery per row.
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM (
				SELECT pm.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
				  AND pm_trash.meta_id IS NULL
				GROUP BY pm.meta_value
				HAVING COUNT(*) > 1
			) AS grp",
			self::META_KEY_HASH,
			self::META_KEY_TRASH
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Attachments marked as trashed by the plugin (have blp_mlm_trash meta). Paginated.
	 *
	 * @param int $limit  Max rows.
	 * @param int $offset Offset.
	 * @return array<int, object> Rows with ID, post_title, post_author, post_date, file_hash (if present).
	 */
	public function get_trashed_attachments( int $limit, int $offset ): array {
		global $wpdb;

		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		// ORDER BY p.ID DESC uses the primary key — avoids filesort on the nullable LEFT-joined pm column.
		$sql = $wpdb->prepare(
			"SELECT p.ID, p.post_title, p.post_author, p.post_date, pm.meta_value AS file_hash
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_trash ON p.ID = pm_trash.post_id AND pm_trash.meta_key = %s
			LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'
			ORDER BY p.ID DESC
			LIMIT %d OFFSET %d",
			self::META_KEY_TRASH,
			self::META_KEY_HASH,
			$limit,
			$offset
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		$results = $wpdb->get_results( $sql );
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Total count of plugin-trashed attachments.
	 *
	 * @return int
	 */
	public function get_trashed_attachments_count(): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit'",
			self::META_KEY_TRASH
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Complex query with subquery, no user input, properly prepared.
		return (int) $wpdb->get_var( $sql );
	}
}
