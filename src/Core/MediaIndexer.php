<?php
/**
 * Media Indexing Class
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Core;

use BiliPlugins\MediaLibraryManager\Core\MediaHasher;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Media Indexing.
 *
 * @since 1.0.0
 */
class MediaIndexer {

	/**
	 * Default batch size for processing attachments.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Instance of MediaHasher.
	 *
	 * @var MediaHasher
	 */
	private MediaHasher $hasher;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->hasher = new MediaHasher();
	}

	/**
	 * Process one batch of unindexed attachments.
	 *
	 * @param int|null $batch_size Number of attachments to process in this batch. Defaults to DEFAULT_BATCH_SIZE.
	 * @return array Array with 'processed', 'remaining', and 'complete' keys.
	 * @since 1.0.0
	 */
	public function process_batch( int $batch_size = self::DEFAULT_BATCH_SIZE ): array {

		// Get batch of unindexed attachments.
		$attachments = $this->get_unindexed_attachments( $batch_size );

		if ( empty( $attachments ) ) {
			// No more attachments to process.
			return array(
				'processed' => 0,
				'remaining' => 0,
				'complete'  => true,
			);
		}

		$processed = 0;

		// Process each attachment in the batch.
		foreach ( $attachments as $attachment_id ) {
			$result = $this->hasher->hash_attachment( $attachment_id );

			if ( false !== $result ) {
				++$processed;
			}
		}

		// Check completion status.
		$progress    = $this->get_progress();
		$is_complete = $progress['indexed'] >= $progress['total'];
		$remaining   = max( 0, $progress['total'] - $progress['indexed'] );

		/**
		 * Action fired after a batch is processed.
		 *
		 * @param int   $processed Number of successfully processed attachments.
		 * @param int   $remaining Number of remaining attachments.
		 * @param bool  $is_complete Whether indexing is complete.
		 * @since 1.0.0
		 */
		do_action( 'blp_mlm_batch_processed', $processed, $remaining, $is_complete );

		return array(
			'processed' => $processed,
			'remaining' => $remaining,
			'complete'  => $is_complete,
		);
	}

	/**
	 * Get current progress stats.
	 *
	 * @return array Array with 'total', and 'indexed' keys.
	 * @since 1.0.0
	 */
	public function get_progress(): array {
		return array(
			'total'   => $this->get_total_attachments(),
			'indexed' => $this->get_indexed_attachments(),
		);
	}

	/**
	 * Process all batches in one go (use cautiously).
	 *
	 * @param int|null $batch_size Number of attachments to process per batch. Defaults to DEFAULT_BATCH_SIZE.
	 * @return array Array with summary information about the indexing process.
	 * @since 1.0.0
	 */
	public function index_all( int $batch_size = self::DEFAULT_BATCH_SIZE ): array {

		$total_processed = 0;
		$batches_run     = 0;

		// Process batches until complete.
		while ( ! $this->is_complete() ) {
			$result           = $this->process_batch( $batch_size );
			$total_processed += $result['processed'];
			++$batches_run;

			// Safety check: prevent infinite loops.
			if ( $batches_run > 1000 ) {
				break;
			}
		}

		return array(
			'total_processed' => $total_processed,
			'batches_run'     => $batches_run,
			'complete'        => $this->is_complete(),
		);
	}


	/**
	 * Check if indexing is complete.
	 *
	 * @return bool True if all attachments have been processed, false otherwise.
	 * @since 1.0.0
	 */
	public function is_complete(): bool {
		$progress = $this->get_progress();
		return $progress['indexed'] >= $progress['total'];
	}

	/**
	 * Get total attachment count.
	 *
	 * @return int Total number of attachments in the media library.
	 * @since 1.0.0
	 */
	public function get_total_attachments(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		return (int) $count;
	}

	/**
	 * Get count of indexed attachments.
	 *
	 * @return int Number of attachments that have been indexed.
	 * @since 1.0.0
	 */
	public static function get_indexed_attachments(): int {
		global $wpdb;

		// COUNT(*) is safe here: each attachment has at most one blp_mlm_file_hash meta row,
		// so DISTINCT is redundant and adds unnecessary overhead.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                 WHERE p.post_type = 'attachment'
                 AND p.post_status = 'inherit'",
				MediaHasher::META_KEY_HASH
			)
		);

		return (int) $count;
	}

	/**
	 * Get a batch of unindexed attachments.
	 *
	 * @param int $batch_size Number of attachments to retrieve.
	 * @return array Array of attachment IDs.
	 * @since 1.0.0
	 */
	private function get_unindexed_attachments( int $batch_size ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
				WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND pm.post_id IS NULL
				ORDER BY p.ID ASC
				LIMIT %d",
				MediaHasher::META_KEY_HASH,
				$batch_size
			)
		);

		return $results ? array_map( 'intval', $results ) : array();
	}
}
