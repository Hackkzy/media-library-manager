<?php
/**
 * Plugin Name:       Media Library Manager
 * Description:       A powerful plugin to manage and organize your WordPress media library with ease.
 * Version:           1.0.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Bili Plugins
 * Author URI:        https://biliplugins.com/
 * Plugin URI:        https://www.medialibrarymanager.com/
 * Text Domain:       media-library-manager
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager;

use BiliPlugins\MediaLibraryManager\Admin\Pages\MediaLibraryManagerPage;
use BiliPlugins\MediaLibraryManager\Admin\AdminHooks;
use BiliPlugins\MediaLibraryManager\Rest\V1\IndexingController;
use BiliPlugins\MediaLibraryManager\Scheduler\IndexMediaJob;
use BiliPlugins\MediaLibraryManager\Core\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Must be registered at file scope (not inside a class) to fire correctly.
register_activation_hook( __FILE__, array( 'BiliPlugins\MediaLibraryManager\Core\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'BiliPlugins\MediaLibraryManager\Core\Installer', 'deactivate' ) );

/**
 * Main Class.
 */
final class MediaLibraryManager {

	/**
	 * Plugin Version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Class instance.
	 *
	 * @var MediaLibraryManager|null
	 */
	private static ?MediaLibraryManager $instance = null;

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->include_autoloader();
		$this->include_action_scheduler();
		$this->init_hooks();
	}

	/**
	 * Singleton instance
	 *
	 * @return MediaLibraryManager
	 */
	public static function instance(): MediaLibraryManager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin Constants.
	 *
	 * @return void
	 */
	private function define_constants(): void {
		define( 'BLP_MLM_VERSION', self::VERSION );
		define( 'BLP_MLM_PATH', plugin_dir_path( __FILE__ ) );
		define( 'BLP_MLM_URL', plugin_dir_url( __FILE__ ) );
		define( 'BLP_MLM_MAIN_FILE', __FILE__ );
		define( 'BLP_MLM_BASE_NAME', plugin_basename( __FILE__ ) );
		define( 'BLP_MLM_ASSETS', BLP_MLM_URL . 'assets/' );
	}

	/**
	 * Load autoloader.
	 *
	 * @return void
	 */
	private function include_autoloader(): void {
		$autoload = trailingslashit( BLP_MLM_PATH ) . 'vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
	}

	/**
	 * Include Action Scheduler.
	 *
	 * @return void
	 */
	public function include_action_scheduler(): void {
		$action_scheduler = trailingslashit( BLP_MLM_PATH ) . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		if ( file_exists( $action_scheduler ) ) {
			require_once $action_scheduler;
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'register_scheduled_jobs' ) );
		add_action( 'init', array( $this, 'plugin_loader' ) );
		add_filter( 'plugin_action_links_' . BLP_MLM_BASE_NAME, array( $this, 'plugin_settings_link' ) );
		// Fallback for installations where the activation hook never fired (e.g. deployed via FTP/git).
		// maybe_upgrade() is idempotent — after first run it short-circuits via the autoloaded options cache.
		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );
	}

	/**
	 * Load language files.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_textdomain( 'media-library-manager', dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Load plugin files.
	 *
	 * @return void
	 */
	public function plugin_loader(): void {
		new IndexingController();

		if ( is_admin() ) {
			new MediaLibraryManagerPage();
			new AdminHooks();
		}
	}

	/**
	 * Register scheduled jobs.
	 *
	 * @return void
	 */
	public function register_scheduled_jobs() {
		IndexMediaJob::register();
	}

	/**
	 * Add Settings link on the Plugins page.
	 *
	 * @param string[] $links An array of plugin action links.
	 * @return string[] Modified array of plugin action links.
	 */
	public function plugin_settings_link( array $links ): array {
		$setting[] = wp_sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( add_query_arg( 'page', 'media-library-manager', admin_url( 'upload.php' ) ) ),
			esc_html__( 'Settings', 'media-library-manager' )
		);
		return array_merge( $setting, $links );
	}
}

// Initialize plugin.
MediaLibraryManager::instance();
