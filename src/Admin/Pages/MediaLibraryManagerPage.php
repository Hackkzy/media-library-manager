<?php
/**
 * Settings page for Comments Plus Plus.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Admin\Pages;

use BiliPlugins\MediaLibraryManager\Admin\Tables\DuplicateMediaTable;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Admin Page.
 */
class MediaLibraryManagerPage {

	/**
	 * Constructor for class.
	 */
	public function __construct() {
		// Plugin's setting page.
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		// Late priority so other plugins cannot easily override list layout after us.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 100 );
	}

	/**
	 * Register Admin Menu
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_menu() {
		add_media_page(
			esc_html__( 'Media Library Manager', 'media-library-manager' ),
			esc_html__( 'Media Library Manager', 'media-library-manager' ),
			'upload_files',
			'media-library-manager',
			array( $this, 'render' )
		);
	}

	/**
	 * Render Admin Page
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function render() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'blp_mlm_messages' ); ?>
			<?php

			// Define tabs.
			$tabs = array(
				'blp_mlm_index_media_tab' => esc_html__( 'Index Media', 'media-library-manager' ),
				'blp_mlm_duplicates_tab'  => esc_html__( 'Duplicates', 'media-library-manager' ),
			);

			// Current tab.
			$current_tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : array_key_first( $tabs ); //phpcs:ignore
			?>

			<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key ) ); ?>"
					class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			<?php endforeach; ?>
			</nav>

			<div class="blp-mlm-tab-panel">
			<?php
			switch ( $current_tab ) {
				case 'blp_mlm_duplicates_tab':
					$this->render_duplicates_tab();
					break;

				case 'blp_mlm_index_media_tab':
				default:
					$this->render_index_duplicates_tab();
					break;
			}
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Duplicates Media Tab
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_duplicates_tab() {
		$table = new DuplicateMediaTable();
		$table->prepare_items();
		?>
		<div class="blp-mlm-tab-content">
			<?php $table->views(); ?>
			<form class="blp-mlm-list-form" method="post">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Index Media Media Tab
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function render_index_duplicates_tab() {
		?>
		<div id="blp-mlm-index-duplicates-tab" class="wrap blp-mlm-tab-content">
			<?php esc_html_e( 'Loading…', 'media-library-manager' ); ?>
		</div>
		<?php
	}

	/**
	 * Enqueue Admin Scripts
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 * @since 1.0.0
	 */
	public function enqueue_scripts( $hook_suffix ) {

		// Only enqueue on our plugin's admin page.
		if ( 'media_page_media-library-manager' !== $hook_suffix ) {
			return;
		}

		// Get asset file.
		$asset_file = include trailingslashit( BLP_MLM_PATH ) . 'assets/admin.asset.php';

		// Enqueue admin styles (Tailwind).
		wp_enqueue_style(
			'blp-mlm-admin-ui-style',
			trailingslashit( BLP_MLM_URL ) . 'assets/admin.css',
			array( 'wp-admin' ),
			$asset_file['version']
		);

		// Enqueue admin script.
		wp_enqueue_script(
			'blp-mlm-admin-ui',
			trailingslashit( BLP_MLM_URL ) . 'assets/admin.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		// Beat third-party admin CSS that sets overflow/max-width on generic wrappers.
		wp_add_inline_style(
			'blp-mlm-admin-ui-style',
			'body.media_page_media-library-manager #wpbody-content .wrap .blp-mlm-tab-content{overflow-x:auto!important;overflow-y:visible!important;width:100%!important;max-width:none!important;}'
		);
	}
}
