<?php
/**
 * Table view for Duplicate Media items.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Admin\Tables;

use BiliPlugins\MediaLibraryManager\Core\MediaDataProvider;
use BiliPlugins\MediaLibraryManager\Core\MediaDeduplicator;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class for Duplicate Media Table.
 */
class DuplicateMediaTable extends \WP_List_Table {

	/**
	 * Data provider for duplicates and trashed media.
	 *
	 * @var MediaDataProvider
	 */
	private MediaDataProvider $data_provider;

	/**
	 * Total count of Duplicate Media.
	 *
	 * @var int
	 */
	private int $duplicate_media_count;

	/**
	 * Total count of Trashed Media.
	 *
	 * @var int
	 */
	private int $trashed_media_count;

	/**
	 * Duplicate media page permalink.
	 *
	 * @var string
	 */
	private string $duplicate_media_link;

	/**
	 * Trash media page permalink.
	 *
	 * @var string
	 */
	private string $trashed_media_link;

	/**
	 * Constructor for class.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'blp_mlm_duplicate_media',
				'plural'   => 'blp_mlm_duplicate_medias',
				'ajax'     => false,
			)
		);

		$this->data_provider         = new MediaDataProvider();
		$this->duplicate_media_count = $this->data_provider->get_duplicate_attachments_count();
		$this->trashed_media_count   = $this->data_provider->get_trashed_attachments_count();

		$this->duplicate_media_link = add_query_arg(
			array(
				'page' => 'media-library-manager',
				'tab'  => 'blp_mlm_duplicates_tab',
			),
			admin_url( 'upload.php' )
		);

		$this->trashed_media_link = add_query_arg(
			array(
				'blp_media_status' => 'trash',
			),
			$this->duplicate_media_link
		);
	}

	/**
	 * Get table columns.
	 *
	 * @return array Columns for the table.
	 * @since 1.0.0
	 */
	public function get_columns() {

		$table_columns = array(
			'cb'          => '<input type="checkbox" />',
			'file'        => esc_html__( 'File', 'media-library-manager' ),
			'author'      => esc_html__( 'Author', 'media-library-manager' ),
			'uploaded_to' => esc_html__( 'Uploaded to', 'media-library-manager' ),
			'date'        => esc_html__( 'Date', 'media-library-manager' ),
		);

		// if ( ! $this->is_trash_view() ) {
		// 	// Remove checkbox column in trash view.
		// 	unset( $table_columns['cb'] );
		// }

		return $table_columns;
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array Sortable columns.
	 * @since 1.0.0
	 */
	public function get_sortable_columns() {
		return array();
	}

	/**
	 * Whether the current view is the trash view.
	 *
	 * @return bool
	 */
	private function is_trash_view() {
		return isset( $_REQUEST['blp_media_status'] ) && 'trash' === $_REQUEST['blp_media_status']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Get table views.
	 *
	 * @return array<string, string>
	 */
	public function get_views() {
		$base      = remove_query_arg( 'paged' );
		$all_url   = remove_query_arg( 'blp_media_status', $base );
		$trash_url = add_query_arg( 'blp_media_status', 'trash', $base );

		$current = $this->is_trash_view() ? 'trash' : 'all';

		$views = array(
			'all'   => sprintf(
				'<a href="%1$s" class="%2$s">%3$s<span>%4$s</span></a>',
				esc_url( $all_url ),
				'all' === $current ? 'current' : '',
				esc_html__( 'All', 'media-library-manager' ),
				$this->duplicate_media_count > 0 ? sprintf( ' (%d)', $this->duplicate_media_count ) : '',
			),
			'trash' => sprintf(
				'<a href="%1$s" class="%2$s">%3$s<span>%4$s</span></a>',
				esc_url( $trash_url ),
				'trash' === $current ? 'current' : '',
				esc_html__( 'Trash', 'media-library-manager' ),
				$this->trashed_media_count > 0 ? sprintf( ' (%d)', $this->trashed_media_count ) : '',
			),
		);

		return $views;
	}

	/**
	 * Extra table nav.
	 *
	 * @param string $which One of 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || ! $this->has_items() ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<?php if ( $this->is_trash_view() ) : ?>
				<div id="blp-mlm-empty-trash-root"></div>
			<?php else : ?>
				<div id="blp-mlm-remove-duplicates-root"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array Bulk actions.
	 * @since 1.0.0
	 */
	public function get_bulk_actions() {
		if ( ! $this->is_trash_view() ) {
			return array(
				'trash' => esc_html__( 'Trash', 'media-library-manager' ),
			);
		}

		return array(
			'restore' => esc_html__( 'Restore', 'media-library-manager' ),
			'delete'  => esc_html__( 'Delete Permanently', 'media-library-manager' ),
		);
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'single_trash' === $action ) {
			// Verify nonce.
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'blp_mlm_trash_single' ) ) {
				return;
			}

			// Get ID.
			$id = isset( $_REQUEST['blp_mlm_trash_id'] ) ? absint( $_REQUEST['blp_mlm_trash_id'] ) : 0;
			MediaDeduplicator::trash_attachment( $id );
			return;
		}

		if ( 'single_restore' === $action ) {

			// Verify nonce.
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'blp_mlm_restore_single' ) ) {
				return;
			}

			// Get ID.
			$id = isset( $_REQUEST['blp_mlm_restore_id'] ) ? absint( $_REQUEST['blp_mlm_restore_id'] ) : 0;
			MediaDeduplicator::restore_attachment( $id );
			return;

		}

		if ( ! in_array( $action, array( 'delete', 'trash', 'restore' ), true ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-blp_mlm_duplicate_medias' ) ) {
			return;
		}

		// Get IDs.
		$ids = isset( $_REQUEST['blp_mlm_duplicate_media'] ) ? array_map( 'absint', (array) $_REQUEST['blp_mlm_duplicate_media'] ) : array();

		if ( empty( $ids ) ) {
			return;
		}

		switch ( $action ) {
			case 'delete':
				MediaDeduplicator::bulk_delete( $ids );
				break;
			case 'trash':
				MediaDeduplicator::bulk_trash( $ids );
				break;
			case 'restore':
				MediaDeduplicator::bulk_restore( $ids );
				break;
			default:
				break;
		}
	}

	/**
	 * Prepare table items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->process_bulk_action();

		$per_page = 20;
		$paged    = $this->get_pagenum();
		$offset   = $per_page * ( $paged - 1 );

		if ( $this->is_trash_view() ) {
			$this->items = $this->data_provider->get_trashed_attachments( $per_page, $offset );
			$total_items = $this->trashed_media_count;
		} else {
			$this->items = $this->data_provider->get_duplicate_attachments( $per_page, $offset );
			$total_items = $this->duplicate_media_count;
		}
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Render file column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_file( $item ) {

		// Get thumbnail.
		$thumb = wp_get_attachment_image( $item->ID, array( 60, 60 ), true );

		// Row actions depend on view.
		if ( $this->is_trash_view() ) {
			$restore_url = add_query_arg(
				array(
					'action'             => 'single_restore',
					'blp_mlm_restore_id' => $item->ID,
					'_wpnonce'           => wp_create_nonce( 'blp_mlm_restore_single' ),
				),
				$this->trashed_media_link
			);

			// Row actions for trashed items.
			$actions = array(
				'restore' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( $restore_url ),
					esc_html__( 'Restore', 'media-library-manager' )
				),
				'delete'  => sprintf(
					'<a href="%s" class="submitdelete">%s</a>',
					esc_url( get_delete_post_link( $item->ID, '', true ) ),
					esc_html__( 'Delete Permanently', 'media-library-manager' )
				),
			);
		} else {

			$trash_url = add_query_arg(
				array(
					'action'           => 'single_trash',
					'blp_mlm_trash_id' => $item->ID,
					'_wpnonce'         => wp_create_nonce( 'blp_mlm_trash_single' ),
				),
				$this->duplicate_media_link
			);

			$actions = array(
				'edit'  => sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $item->ID ) ), esc_html__( 'Edit', 'media-library-manager' ) ),
				'view'  => sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( wp_get_attachment_url( $item->ID ) ), esc_html__( 'View', 'media-library-manager' ) ),
				'trash' => sprintf( '<a href="%s">%s</a>', esc_url( $trash_url ), esc_html__( 'Trash', 'media-library-manager' ) ),
			);
		}

		return sprintf(
			'<div class="media-column">
                <div class="media-icon">
                    %1$s
                </div>
                <div class="media-details">
                    <strong>%2$s</strong>
                    <p class="filename">%3$s</p>
                    <div class="row-actions">%4$s</div>
                </div>
            </div>',
			$thumb,
			esc_html( $item->post_title ),
			esc_html( basename( get_attached_file( $item->ID ) ) ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render author column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_author( $item ) {
		$author = get_userdata( $item->post_author );
		return $author ? esc_html( $author->display_name ) : '—';
	}

	/**
	 * Render Uploaded to column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_uploaded_to( $item ) {

		$post = get_post_parent( $item->ID );

		if ( empty( $post ) ) {
			return esc_html__( '(Unattached)', 'media-library-manager' );
		}

		$link  = get_edit_post_link( $post->ID );
		$title = $post->post_title ? $post->post_title : esc_html__( '(no title)', 'media-library-manager' );

		return sprintf(
			'<strong><a href="%s">%s</a></strong>',
			esc_url( $link ),
			esc_html( $title )
		);
	}

	/**
	 * Render date column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_date( $item ) {
		return esc_html( get_the_date( '', $item->ID ) );
	}

	/**
	 * Render checkbox column.
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="blp_mlm_duplicate_media[]" value="%d" />', $item->ID );
	}

	/**
	 * Render default column.
	 *
	 * @param object $item        Item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return '';
	}
}
