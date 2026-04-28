<?php
/**
 * Action Scheduler job to permanently delete plugin-trashed attachments.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Scheduler;

use BiliPlugins\MediaLibraryManager\Core\MediaDataProvider;
use BiliPlugins\MediaLibraryManager\Core\MediaDeduplicator;
use BiliPlugins\MediaLibraryManager\Core\Queue;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background job: one attachment ID per action.
 *
 * @since 1.0.0
 */
class DeleteTrashedMediaJob {

	const HOOK = 'blp_mlm_delete_trashed_media';

	/**
	 * Action Scheduler queue wrapper for this job.
	 *
	 * @var Queue|null
	 */
	private static $queue;

	/**
	 * Register the worker hook and queue.
	 */
	public static function register(): void {
		add_action( self::HOOK, array( self::class, 'process_delete_trashed_media' ) );
		self::$queue = new Queue( 'blp-mlm-delete-trashed', self::HOOK );
	}

	/**
	 * Permanently delete one attachment if it is still in plugin trash.
	 *
	 * @param int $media_id Attachment post ID.
	 */
	public static function process_delete_trashed_media( $media_id ): void {
		$media_id = (int) $media_id;
		if ( $media_id <= 0 ) {
			return;
		}

		if ( 'attachment' !== get_post_type( $media_id ) ) {
			return;
		}

		if ( ! MediaDeduplicator::is_trashed( $media_id ) ) {
			return;
		}

		MediaDeduplicator::cleanup_post_backups( $media_id );
		// Run without a current-user context — permission was validated at enqueue time via REST API.
		wp_delete_attachment( $media_id, true );
	}

	/**
	 * Enqueue a single media ID for permanent deletion.
	 *
	 * @param int $media_id  Attachment ID.
	 * @param int $priority  Action Scheduler priority (lower runs first).
	 * @return int|false Action ID from Queue::add_to_queue, or false.
	 */
	public static function enqueue_media_id( int $media_id, int $priority = 20 ) {
		if ( ! self::$queue ) {
			return false;
		}

		return self::$queue->add_to_queue( array( 'media_id' => $media_id ), $priority );
	}

	/**
	 * Kick the async runner (non-blocking HTTP to admin-ajax).
	 */
	public static function start_queue(): void {
		if ( self::$queue ) {
			self::$queue->start_queue();
		}
	}

	/**
	 * Queue all plugin-trashed attachments, then start the runner once.
	 */
	public static function dispatch_all_trashed(): int {
		if ( ! self::$queue ) {
			return 0;
		}

		$provider = new MediaDataProvider();
		$limit    = 300;
		$offset   = 0;
		$queued   = 0;

		while ( true ) {
			$rows = $provider->get_trashed_attachments( $limit, $offset );

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$id = isset( $row->ID ) ? (int) $row->ID : 0;
				if ( $id > 0 ) {
					self::enqueue_media_id( $id, 20 );
					++$queued;
				}
			}

			$offset    += $limit;
			$batch_size = count( $rows );
			if ( $batch_size < $limit ) {
				break;
			}
		}

		if ( $queued > 0 ) {
			self::$queue->start_queue();
		}

		return $queued;
	}
}
