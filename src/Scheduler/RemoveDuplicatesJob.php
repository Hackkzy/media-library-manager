<?php
/**
 * Action Scheduler job to remove media.
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
 * Background job: one duplicate group per action; within a group, duplicates are processed in batches.
 *
 * @since 1.0.0
 */
class RemoveDuplicatesJob {

	/**
	 * Action hook name for scheduling duplicate removal tasks.
	 *
	 * @var string
	 */
	const HOOK = 'blp_mlm_process_deduplicate';

	/**
	 * Default batch size for full dispatch (non-REST).
	 */
	const DEFAULT_DISPATCH_BATCH = 100;

	/**
	 * Queue instance for managing duplicate removal tasks.
	 *
	 * @var Queue
	 */
	private static $queue;

	/**
	 * Register hook EARLY (static callback).
	 */
	public static function register(): void {
		// The worker action.
		add_action( self::HOOK, array( self::class, 'process_deduplicate' ) );
		self::$queue = new Queue( 'blp-mlm-remove-duplicates', self::HOOK );
	}

	/**
	 * Queue one page of duplicate hashes (cursor-based pagination).
	 *
	 * @param int    $batch_size Max hashes to fetch (must be >= 1).
	 * @param string $last_hash  Cursor: fetch hashes with meta_value > this (empty string = start).
	 * @return array{queued: int, complete: bool, next_last_hash: string}
	 */
	public static function dispatch_batch( int $batch_size, string $last_hash = '' ): array {
		if ( ! self::$queue ) {
			return array(
				'queued'         => 0,
				'complete'       => true,
				'next_last_hash' => '',
			);
		}

		$batch_size = max( 1, $batch_size );
		$provider   = new MediaDataProvider();
		$hashes     = $provider->get_duplicate_hashes( $batch_size, $last_hash );

		if ( empty( $hashes ) ) {
			return array(
				'queued'         => 0,
				'complete'       => true,
				'next_last_hash' => '',
			);
		}

		foreach ( $hashes as $hash ) {
			self::$queue->add_to_queue( array( 'hash' => $hash ), 10 );
		}

		self::$queue->start_queue();

		$count    = count( $hashes );
		$complete = ( $count < $batch_size );
		$last     = $hashes[ $count - 1 ];

		return array(
			'queued'         => $count,
			'complete'       => $complete,
			'next_last_hash' => $complete ? '' : (string) $last,
		);
	}

	/**
	 * Dispatcher: enqueue all duplicate-hash groups (uses batched cursor internally).
	 */
	public static function dispatch_all(): void {
		$last_hash = '';

		while ( true ) {
			$result = self::dispatch_batch( self::DEFAULT_DISPATCH_BATCH, $last_hash );

			if ( $result['complete'] ) {
				break;
			}

			$last_hash = $result['next_last_hash'];
		}
	}

	/**
	 * Worker: Process a single hash group.
	 *
	 * @param string $hash The hash representing a group of duplicate media items.
	 * @return void
	 */
	public static function process_deduplicate( $hash ): void {
		if ( empty( $hash ) ) {
			return;
		}

		$provider    = new MediaDataProvider();
		$attachments = $provider->get_group_batch( $hash, 500 );

		if ( count( $attachments ) < 2 ) {
			return;
		}

		$original      = array_shift( $attachments );
		$duplicate_ids = wp_list_pluck( $attachments, 'ID' );

		MediaDeduplicator::replace_and_trash_media( $original->ID, $duplicate_ids, 100 );
	}

	/**
	 * Get current progress stats for duplicate removal.
	 *
	 * @return array Array with 'remaining_hashes', 'remaining_files', and 'in_queue'.
	 */
	public static function get_progress(): array {
		$provider = new MediaDataProvider();

		// Current count of hashes that still have duplicates (not yet processed).
		$duplicate_hashes = $provider->get_duplicate_hashes_count();

		// Total count of duplicate files remaining.
		$duplicate_files = $provider->get_duplicate_attachments_count();

		return array(
			'remaining_hashes' => $duplicate_hashes,
			'remaining_files'  => $duplicate_files,
			'in_queue'         => self::$queue ? self::$queue->get_pending_count() : 0,
		);
	}
}
