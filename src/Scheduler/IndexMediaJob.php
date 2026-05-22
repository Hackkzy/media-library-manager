<?php
/**
 * Action Scheduler job to index media in batches.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Scheduler;

use BiliPlugins\MediaLibraryManager\Core\MediaIndexer;
use BiliPlugins\MediaLibraryManager\Core\Queue;
use BiliPlugins\MediaLibraryManager\Core\MediaHasher;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Action Scheduler job class for indexing media in batches.
 *
 * @since 1.0.0
 */
class IndexMediaJob {

	/**
	 * Action hook name for scheduling batches.
	 *
	 * @var string
	 */
	const HOOK = 'blp_mlm_index_media';

	/**
	 * Queue instance for managing media indexing tasks.
	 *
	 * @var Queue
	 */
	private static $idex_queue;

	/**
	 * Register hook EARLY (static callback).
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'index_single_media' ) );
		self::$idex_queue = new Queue( 'blp-mlm-index-media', self::HOOK );
	}

	/**
	 * Process a single media item for indexing.
	 *
	 * @param int $media_id The ID of the media item to index.
	 * @return void
	 * @throws \Exception If hashing fails for the media item.
	 */
	public static function index_single_media( $media_id ): void {
		$result = MediaHasher::hash_attachment( $media_id );
		if ( ! $result ) {
			throw new \Exception(
				sprintf(
					'Something went wrong hashing media ID: %s',
					esc_html( $media_id )
				)
			);
		}

		usleep( 100000 );
	}

	/**
	 * Add media to action scedular.
	 */
	public static function index_media() {

		self::$idex_queue->clear_queue();
		wp_suspend_cache_addition( true );

		$paged          = 1;
		$added_to_queue = false;

		do {
			$query = new \WP_Query(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'posts_per_page'         => 2000, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
					'paged'                  => $paged++,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'             => array(
						array(
							'key'     => MediaHasher::META_KEY_HASH,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			);

			if ( empty( $query->posts ) ) {
				break;
			}

			$added_to_queue = self::queue_media( $query->posts, 20 );
		} while ( $query->have_posts() );

		wp_suspend_cache_addition( false );

		if ( ! $added_to_queue ) {
			wp_send_json_error();
		}

		self::$idex_queue->start_queue();
		wp_send_json_success();
	}

	/**
	 * Add media to the queue
	 *
	 * @param array   $media_ids Array of media IDs to add to the queue.
	 * @param integer $priority Priority for the queue items (default: 25).
	 * @return void
	 */
	private static function queue_media( $media_ids, $priority = 25 ) {

		if ( empty( $media_ids ) ) {
			return false;
		}

		$queue     = self::$idex_queue;
		$media_ids = apply_filters( 'blp_mlm_index_media_ids', $media_ids );

		foreach ( $media_ids ?? array() as $media_id ) {
			$queue->add_to_queue( array( 'media_id' => $media_id ), $priority );
		}

		return count( $media_ids );
	}

	/**
	 * Get current progress stats.
	 *
	 * @return array Array with 'total', and 'indexed' keys.
	 * @since 1.0.0
	 */
	public static function get_progress(): array {
		return array(
			'total'          => self::get_total_attachments(),
			'media_in_queue' => self::$idex_queue->get_pending_count(),
			'indexed'        => MediaIndexer::get_indexed_attachments(),
		);
	}

	/**
	 * Get total attachment count.
	 *
	 * @return int Total number of attachments in the media library.
	 * @since 1.0.0
	 */
	private static function get_total_attachments(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		return (int) $count;
	}
}
