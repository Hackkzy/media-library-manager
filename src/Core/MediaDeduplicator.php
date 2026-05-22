<?php
/**
 * Core logic for removing media.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Core;

use BiliPlugins\MediaLibraryManager\Compatibility\Woo;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles replacing references to an attachment ID with another, and moving attachments to trash.
 *
 * @since 1.0.0
 */
class MediaDeduplicator {

	/**
	 * Meta key for plugin trash.
	 *
	 * @var string
	 */
	const TRASH_META_KEY = 'blp_mlm_trash';

	/**
	 * Max postmeta rows processed per replace_post_media_in_meta() call.
	 *
	 * @var int
	 */
	const META_REPLACE_ROW_LIMIT = 200;

	/**
	 * Meta key on posts tracking which trashed attachments changed their content or thumbnail.
	 *
	 * @var string
	 */
	const CONTENT_BACKUP_META_KEY = 'blp_mlm_content_backup';

	/**
	 * Max distinct attachment URL patterns used in LIKE discovery (post content + postmeta).
	 *
	 * Kept at 20 to limit the OR clause size in LIKE '%...%' full-table scans.
	 * These scans cannot use indexes due to the leading wildcard; this method runs only
	 * as a background job (never on page load) so latency is acceptable, but a wide OR
	 * clause makes the scan disproportionately slower with no extra benefit.
	 *
	 * @var int
	 */
	const VARIANT_URL_PATTERN_LIMIT = 20;

	/**
	 * Trash a single attachment.
	 *
	 * @param int   $attachment_id    Attachment post ID.
	 * @param int   $canonical_id     Attachment that replaced this one (0 when not from dedup).
	 * @param int[] $affected_post_ids Posts whose content or thumbnail was changed.
	 * @return bool True on success, false if not an attachment or already trashed.
	 */
	public static function trash_attachment( int $attachment_id, int $canonical_id = 0, array $affected_post_ids = array() ): bool {
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		if ( self::is_trashed( $attachment_id ) ) {
			return true;
		}

		$auto_resolved_canonical = false;

		// For single-item trash from UI, resolve canonical duplicate and update references first.
		if ( $canonical_id <= 0 ) {
			$data_provider           = new MediaDataProvider();
			$canonical_id            = (int) $data_provider->get_original_attachment_id( $attachment_id );
			$auto_resolved_canonical = true;
		}

		if ( $auto_resolved_canonical && $canonical_id > 0 && $canonical_id !== $attachment_id ) {
			$content_post_ids   = self::replace_media_in_post_content( $canonical_id, array( $attachment_id ) );
			$thumbnail_post_ids = self::replace_post_media_in_meta( $attachment_id, $canonical_id );
			$affected_post_ids  = array_values( array_unique( array_merge( $affected_post_ids, $content_post_ids, $thumbnail_post_ids ) ) );
		}

		$payload = array(
			'trashed_at'        => time(),
			'trashed_by'        => get_current_user_id(),
			'canonical_id'      => $canonical_id,
			'affected_post_ids' => array_values( array_filter( array_map( 'absint', $affected_post_ids ) ) ),
		);
		return (bool) update_post_meta( $attachment_id, self::TRASH_META_KEY, $payload );
	}

	/**
	 * Trash multiple attachments (plugin trash).
	 *
	 * @param array<int> $attachment_ids   Attachment post IDs.
	 * @param int        $canonical_id     Attachment that replaced these (0 when not from dedup).
	 * @param int[]      $affected_post_ids Posts whose content or thumbnail was changed.
	 * @return void
	 */
	public static function bulk_trash( array $attachment_ids, int $canonical_id = 0, array $affected_post_ids = array() ): void {
		$ids = array_filter( array_map( 'absint', $attachment_ids ) );
		foreach ( $ids as $id ) {
			self::trash_attachment( $id, $canonical_id, $affected_post_ids );
		}
	}

	/**
	 * Restore multiple attachments (plugin trash).
	 *
	 * @param array<int> $attachment_ids Attachment post IDs.
	 * @return void
	 */
	public static function bulk_restore( array $attachment_ids ): void {
		$ids = array_filter( array_map( 'absint', $attachment_ids ) );
		foreach ( $ids as $id ) {
			self::restore_attachment( $id );
		}
	}

	/**
	 * Record that a post's content or thumbnail was changed during deduplication of $attachment_id.
	 * First-write wins: if an entry already exists for this attachment, it is not overwritten.
	 *
	 * @param int  $post_id       Post that was modified.
	 * @param int  $attachment_id Trashed attachment whose replacement caused the change.
	 * @param bool $content       Whether post_content was changed.
	 * @param bool $thumbnail     Whether _thumbnail_id was changed.
	 * @param bool $gallery       Whether WooCommerce product gallery meta was changed.
	 */
	private static function record_post_backup( int $post_id, int $attachment_id, bool $content, bool $thumbnail, bool $gallery = false ): void {
		$existing = get_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, true );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		if ( isset( $existing[ $attachment_id ] ) ) {
			return;
		}
		$existing[ $attachment_id ] = array(
			'content'   => $content,
			'thumbnail' => $thumbnail,
			'gallery'   => $gallery,
		);
		update_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, $existing );
	}

	/**
	 * Permanently delete multiple attachments. Skips IDs the current user cannot delete.
	 *
	 * @param array<int> $attachment_ids Attachment post IDs.
	 * @return void
	 */
	public static function bulk_delete( array $attachment_ids ): void {
		$ids = array_filter( array_map( 'absint', $attachment_ids ) );
		foreach ( $ids as $id ) {
			if ( current_user_can( 'upload_files', $id ) ) {
				self::cleanup_post_backups( $id );
				wp_delete_attachment( $id, true );
			}
		}
	}

	/**
	 * Restore a single attachment from plugin trash by removing trash meta.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True on success, false if not trashed or not an attachment.
	 */
	public static function restore_attachment( int $attachment_id ): bool {
		if ( ! self::is_trashed( $attachment_id ) ) {
			return true;
		}
		$info = self::get_trash_info( $attachment_id );
		if ( $info && $info['canonical_id'] > 0 && ! empty( $info['affected_post_ids'] ) ) {
			self::restore_post_backups( $attachment_id, $info['canonical_id'], $info['affected_post_ids'] );
		}
		return (bool) delete_post_meta( $attachment_id, self::TRASH_META_KEY );
	}

	/**
	 * Check if an attachment is in the plugin's trash (has blp_mlm_trash meta with valid JSON).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool
	 */
	public static function is_trashed( int $attachment_id ): bool {
		$raw = get_post_meta( $attachment_id, self::TRASH_META_KEY, true );
		if ( '' === $raw || null === $raw ) {
			return false;
		}
		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return is_array( $decoded ) && isset( $decoded['trashed_at'] );
	}

	/**
	 * Get trash info for an attachment. Returns null if not trashed.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array{trashed_at: int, trashed_by: int, canonical_id: int, affected_post_ids: int[]}|null
	 */
	public static function get_trash_info( int $attachment_id ): ?array {
		$raw = get_post_meta( $attachment_id, self::TRASH_META_KEY, true );
		if ( '' === $raw || null === $raw ) {
			return null;
		}
		$decoded = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['trashed_at'] ) ) {
			return null;
		}
		return array(
			'trashed_at'        => (int) $decoded['trashed_at'],
			'trashed_by'        => isset( $decoded['trashed_by'] ) ? (int) $decoded['trashed_by'] : 0,
			'canonical_id'      => isset( $decoded['canonical_id'] ) ? (int) $decoded['canonical_id'] : 0,
			'affected_post_ids' => isset( $decoded['affected_post_ids'] ) && is_array( $decoded['affected_post_ids'] )
				? array_map( 'absint', $decoded['affected_post_ids'] )
				: array(),
		);
	}

	/**
	 * Replace every reference to given attachment ID(s) with $to_id, then trash the from attachments (plugin trash).
	 * Use for a single duplicate (e.g. from UI) or a batch (e.g. from Remove All Duplicates job).
	 *
	 * @param int   $to_id           Attachment to keep (all references point here).
	 * @param int[] $from_ids        Attachment ID(s) to replace and trash. Excludes $to_id; empty array skips.
	 * @param int   $post_batch_size If > 0, scan post_content in batches of this size; if 0, fetch all matching posts at once. Use 0 for single-id, or e.g. 50 for batch.
	 * @return bool True on success. False only when a single $from_id was given and validation failed (not an attachment or already trashed).
	 */
	public static function replace_and_trash_media( int $to_id, array $from_ids, int $post_batch_size = 0 ): bool {
		$from_ids = array_filter( array_map( 'absint', $from_ids ) );
		$from_ids = array_values( array_diff( $from_ids, array( $to_id ) ) );

		if ( empty( $from_ids ) ) {
			return true;
		}

		$post_batch_size = $post_batch_size > 0 ? max( 1, min( 100, (int) $post_batch_size ) ) : 0;

		$content_post_ids   = self::replace_media_in_post_content( $to_id, $from_ids, $post_batch_size );
		$thumbnail_post_ids = array();
		foreach ( $from_ids as $from_id ) {
			$thumbnail_post_ids = array_merge( $thumbnail_post_ids, self::replace_post_media_in_meta( $from_id, $to_id ) );
		}
		$affected_post_ids = array_values( array_unique( array_merge( $content_post_ids, $thumbnail_post_ids ) ) );

		self::bulk_trash( $from_ids, $to_id, $affected_post_ids );
		return true;
	}

	/**
	 * Build absolute URLs for an attachment's full file and each intermediate size (metadata), keyed by size name.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array<string, string> Size name (e.g. full, medium) => URL.
	 */
	private static function get_attachment_variant_urls_by_size( int $attachment_id ): array {
		$result   = array();
		$full     = wp_get_attachment_image_src( $attachment_id, 'full' );
		$full_url = ( is_array( $full ) && ! empty( $full[0] ) ) ? $full[0] : wp_get_attachment_url( $attachment_id );
		if ( ! is_string( $full_url ) || '' === $full_url ) {
			return $result;
		}
		$result['full'] = $full_url;

		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
			return $result;
		}

		$base = trailingslashit( dirname( $full_url ) );
		foreach ( $meta['sizes'] as $size_name => $size_data ) {
			if ( empty( $size_data['file'] ) || ! is_string( $size_data['file'] ) ) {
				continue;
			}
			$result[ (string) $size_name ] = $base . ltrim( $size_data['file'], '/' );
		}

		return $result;
	}

	/**
	 * Replace all known file URLs for $from_id with matching (by size) or fallback URLs for $to_id.
	 *
	 * @param string $content HTML or post content.
	 * @param int    $from_id Source attachment ID.
	 * @param int    $to_id   Target attachment ID.
	 * @return string Content after replacements.
	 */
	private static function replace_attachment_variant_urls_in_string( string $content, int $from_id, int $to_id ): string {
		$from_by_size = self::get_attachment_variant_urls_by_size( $from_id );
		$to_by_size   = self::get_attachment_variant_urls_by_size( $to_id );
		$to_fallback  = $to_by_size['full'] ?? wp_get_attachment_url( $to_id );
		if ( ! is_string( $to_fallback ) || '' === $to_fallback || empty( $from_by_size ) ) {
			return $content;
		}

		$pairs = array();
		foreach ( $from_by_size as $size => $from_url ) {
			if ( ! is_string( $from_url ) || '' === $from_url ) {
				continue;
			}
			$to_url = isset( $to_by_size[ $size ] ) ? $to_by_size[ $size ] : $to_fallback;
			if ( ! is_string( $to_url ) || '' === $to_url || $from_url === $to_url ) {
				continue;
			}
			$pairs[ $from_url ] = $to_url;
		}

		if ( empty( $pairs ) ) {
			return $content;
		}

		uksort(
			$pairs,
			static function ( $a, $b ) {
				return strlen( (string) $b ) <=> strlen( (string) $a );
			}
		);

		$new = $content;
		foreach ( $pairs as $from_url => $to_url ) {
			$new = str_replace( $from_url, $to_url, $new );
		}

		return $new;
	}

	/**
	 * Replace attachment ID(s) and URL(s) in post_content for posts that reference any from-attachment URL variant or ID.
	 * Loads at most 100 matching post IDs, then replaces IDs and maps each URL variant to the related variant on $to_id.
	 * Records a backup flag on each changed post so the change can be reversed on restore.
	 *
	 * @param int   $to_id           Attachment ID to use.
	 * @param int[] $from_ids        Attachment ID(s) to replace. Empty array skips.
	 * @param int   $post_batch_size Unused; reserved for callers. Discovery is capped at 100 posts.
	 * @return int[] Post IDs whose post_content was actually changed.
	 */
	private static function replace_media_in_post_content( int $to_id, array $from_ids, int $post_batch_size = 0 ): array {
		global $wpdb;

		$from_ids = array_filter( array_map( 'absint', $from_ids ) );
		$from_ids = array_unique( array_slice( $from_ids, 0, 100 ) );
		if ( empty( $from_ids ) ) {
			return array();
		}
		$changed_post_ids = array();

		$to_value  = (string) $to_id;
		$id_alt    = implode(
			'|',
			array_map(
				static function ( $id ) {
					return preg_quote( (string) $id, '/' );
				},
				$from_ids
			)
		);
		$ref_regex = '/(ids=["\']|id=["\']attachment_|wp-image-)(' . $id_alt . ')(["\'\s>]|$)/';

		$url_patterns = array();
		foreach ( $from_ids as $from_id ) {
			foreach ( self::get_attachment_variant_urls_by_size( $from_id ) as $url ) {
				if ( is_string( $url ) && '' !== $url ) {
					$url_patterns[ $url ] = true;
				}
			}
		}
		$url_patterns = array_keys( $url_patterns );
		usort(
			$url_patterns,
			static function ( $a, $b ) {
				return strlen( (string) $b ) <=> strlen( (string) $a );
			}
		);
		$url_patterns = array_slice( $url_patterns, 0, self::VARIANT_URL_PATTERN_LIMIT );

		$like_parts = array();
		foreach ( $url_patterns as $url ) {
			$like_parts[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( $url ) . '%' );
		}
		foreach ( $from_ids as $fid ) {
			$like_parts[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( 'wp-image-' . $fid ) . '%' );
			$like_parts[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( 'attachment_' . $fid ) . '%' );
		}

		if ( empty( $like_parts ) ) {
			return array();
		}

		$post_types = array_values( get_post_types( array( 'public' => true ) ) );
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}
		$types_sql = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
		$sql       = "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$types_sql}) AND (" . implode( ' OR ', $like_parts ) . ') ORDER BY post_date DESC LIMIT 100';

		$post_ids = $wpdb->get_col( $wpdb->prepare( $sql, ...$post_types ) ); // phpcs:ignore

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return array();
		}

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				continue;
			}
			$content = get_post_field( 'post_content', $post_id );
			if ( ! is_string( $content ) || '' === $content ) {
				continue;
			}

			$updated = false;

			if ( has_blocks( $content ) ) {
				// Block Editor
				$blocks = parse_blocks( $content );
				foreach ( $from_ids as $from_id ) {
					$blocks = self::recursive_block_replacement( $blocks, $from_id, $to_id, $updated );
				}

				if ( $updated ) {
					$new = serialize_blocks( $blocks );
				}
			} else {
				// Classic Editor fallback
				$new = preg_replace( $ref_regex, '${1}' . $to_value . '${3}', $content );
				foreach ( $from_ids as $from_id ) {
					$new = self::replace_attachment_variant_urls_in_string( $new, $from_id, $to_id );
				}
				if ( $new !== $content ) {
					$updated = true;
				}
			}

			if ( $updated && isset( $new ) ) {
				foreach ( $from_ids as $fid ) {
					self::record_post_backup( $post_id, $fid, true, false );
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $post_id ) );
				$changed_post_ids[] = $post_id;
				clean_post_cache( $post_id );
			}
		}

		return $changed_post_ids;
	}

	/**
	 * Recursively walk through blocks to update attributes and HTML strings for all media types.
	 *
	 * @param array $blocks    Array of blocks from parse_blocks().
	 * @param int   $from_id   ID to replace.
	 * @param int   $to_id     New ID.
	 * @param bool  $updated   Flag to track if changes were made.
	 * @return array Processed blocks.
	 */
	private static function recursive_block_replacement( array $blocks, int $from_id, int $to_id, bool &$updated ): array {
		// Catch common HTML ID patterns: wp-image-123, attachment_123, id="123", id:123
		$id_pattern = '/(wp-image-|attachment_|id[s]?=["\']|id[s]?[:=])(' . $from_id . ')(["\'\s,}]|$)/';

		foreach ( $blocks as &$block ) {
			// 1. Update Attributes (The JSON metadata)
			if ( ! empty( $block['attrs'] ) ) {
				if ( self::replace_attachment_refs_in_meta_value( $block['attrs'], $from_id, $to_id ) ) {
					$updated = true;
				}
			}

			// 2. Update Inner HTML & Inner Content chunks
			$fields = array( 'innerHTML', 'innerContent' );
			foreach ( $fields as $field ) {
				if ( empty( $block[ $field ] ) ) {
					continue;
				}

				$is_array = is_array( $block[ $field ] );
				$chunks   = $is_array ? $block[ $field ] : array( &$block[ $field ] );

				foreach ( $chunks as &$chunk ) {
					if ( ! is_string( $chunk ) ) {
						continue;
					}

					$old_chunk = $chunk;
					$chunk     = self::replace_attachment_variant_urls_in_string( $chunk, $from_id, $to_id );

					// Universal replacement for IDs in HTML (Images, Video, Audio, Files)
					$chunk = preg_replace( $id_pattern, '${1}' . $to_id . '${3}', $chunk );

					if ( $old_chunk !== $chunk ) {
						$updated = true;
					}
				}

				if ( $is_array ) {
					$block[ $field ] = $chunks;
				}
			}

			// 3. Recurse into Inner Blocks (Columns, Groups, etc.)
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::recursive_block_replacement( $block['innerBlocks'], $from_id, $to_id, $updated );
			}
		}
		return $blocks;
	}

	/**
	 * Replace attachment ID and image variant URLs in postmeta.
	 * Discovers rows by scalar ID, serialized ID, variant URLs, or wp-image / attachment_ substrings.
	 * Processes at most META_REPLACE_ROW_LIMIT rows per call (ORDER BY meta_id DESC).
	 * Records a backup flag when _thumbnail_id is changed so it can be reversed on restore.
	 *
	 * @param int $from_id Attachment ID to replace.
	 * @param int $to_id   Attachment ID to use.
	 * @return int[] Post IDs whose _thumbnail_id was changed.
	 */
	private static function replace_post_media_in_meta( int $from_id, int $to_id ): array {
		global $wpdb;

		$serialized_like = '%' . $wpdb->esc_like( 'i:' . $from_id . ';' ) . '%';
		$where_parts     = array(
			$wpdb->prepare( 'meta_value = %s', (string) $from_id ),
			$wpdb->prepare( 'meta_value LIKE %s', $serialized_like ),
		);

		$url_patterns = array();
		foreach ( self::get_attachment_variant_urls_by_size( $from_id ) as $url ) {
			if ( is_string( $url ) && '' !== $url ) {
				$url_patterns[ $url ] = true;
			}
		}
		$url_patterns = array_keys( $url_patterns );
		usort(
			$url_patterns,
			static function ( $a, $b ) {
				return strlen( (string) $b ) <=> strlen( (string) $a );
			}
		);
		$url_patterns = array_slice( $url_patterns, 0, self::VARIANT_URL_PATTERN_LIMIT );
		foreach ( $url_patterns as $url ) {
			$where_parts[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( $url ) . '%' );
		}
		$where_parts[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( 'wp-image-' . $from_id ) . '%' );
		$where_parts[] = $wpdb->prepare( 'meta_value LIKE %s', '%' . $wpdb->esc_like( 'attachment_' . $from_id ) . '%' );
		$where_parts[] = $wpdb->prepare(
			'meta_key = %s AND meta_value LIKE %s',
			Woo::PRODUCT_IMAGE_GALLERY_META_KEY,
			'%' . $wpdb->esc_like( (string) $from_id ) . '%'
		);

		$limit = (int) self::META_REPLACE_ROW_LIMIT;
		$sql   = "SELECT meta_id, meta_key, post_id, meta_value FROM {$wpdb->postmeta} WHERE (" . implode( ' OR ', $where_parts ) . ") ORDER BY meta_id DESC LIMIT {$limit}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WHERE fragments are built with $wpdb->prepare().
		$meta_rows = $wpdb->get_results( $sql );

		if ( empty( $meta_rows ) || ! is_array( $meta_rows ) ) {
			return array();
		}

		$thumbnail_post_ids = array();

		foreach ( $meta_rows as $meta ) {
			$raw = $meta->meta_value;
			if ( ! is_string( $raw ) ) {
				continue;
			}
			$is_numeric = is_numeric( $raw ) && (int) $raw === $from_id;
			if ( $is_numeric ) {
				if ( '_thumbnail_id' === $meta->meta_key ) {
					self::record_post_backup( (int) $meta->post_id, $from_id, false, true );
					$thumbnail_post_ids[] = (int) $meta->post_id;
				}
				update_post_meta( $meta->post_id, $meta->meta_key, (string) $to_id );
				continue;
			}
			$unserialized = @unserialize( $raw ); // phpcs:ignore
			if ( false !== $unserialized ) {
				$replaced = self::replace_attachment_refs_in_meta_value( $unserialized, $from_id, $to_id );
				if ( $replaced ) {
					update_post_meta( $meta->post_id, $meta->meta_key, $unserialized );
				}
				continue;
			}

			if ( Woo::is_product_gallery_meta_key( $meta->meta_key ) ) {
				$new = Woo::replace_product_gallery_reference( $raw, $from_id, $to_id );
				if ( $new !== $raw ) {
					self::record_post_backup( (int) $meta->post_id, $from_id, false, false, true );
					update_post_meta( $meta->post_id, $meta->meta_key, $new );
				}
				continue;
			}

			$new = self::replace_attachment_variant_urls_in_string( $raw, $from_id, $to_id );
			if ( $new !== $raw ) {
				update_post_meta( $meta->post_id, $meta->meta_key, $new );
			}
		}

		return array_values( array_unique( $thumbnail_post_ids ) );
	}

	/**
	 * Recursively replace attachment ID (int or numeric string) and variant URLs in meta structures.
	 *
	 * @param mixed $value   Value (by reference).
	 * @param int   $from_id Source attachment ID.
	 * @param int   $to_id   Target attachment ID.
	 * @return bool Whether any change was made.
	 */
	private static function replace_attachment_refs_in_meta_value( &$value, int $from_id, int $to_id ): bool {
		if ( is_int( $value ) && $value === $from_id ) {
			$value = $to_id;
			return true;
		}
		if ( is_string( $value ) ) {
			if ( is_numeric( $value ) && (int) $value === $from_id ) {
				$value = (string) $to_id;
				return true;
			}
			$new = self::replace_attachment_variant_urls_in_string( $value, $from_id, $to_id );
			if ( $new !== $value ) {
				$value = $new;
				return true;
			}
			return false;
		}
		if ( is_array( $value ) ) {
			$changed = false;
			foreach ( $value as $k => $v ) {
				if ( self::replace_attachment_refs_in_meta_value( $value[ $k ], $from_id, $to_id ) ) {
					$changed = true;
				}
			}
			return $changed;
		}
		if ( is_object( $value ) ) {
			$changed = false;
			foreach ( (array) $value as $k => $v ) {
				if ( self::replace_attachment_refs_in_meta_value( $value->{$k}, $from_id, $to_id ) ) {
					$changed = true;
				}
			}
			return $changed;
		}
		return false;
	}

	/**
	 * Reverse the dedup replacement in a single content string: swap canonical → original attachment.
	 * Mirrors the forward replacement in replace_media_in_post_content() but with from/to inverted.
	 *
	 * @param string $content       Current post_content.
	 * @param int    $canonical_id  Attachment that was used as the replacement (the "from" for reversal).
	 * @param int    $attachment_id Original trashed attachment (the "to" for reversal).
	 * @return string Content after reverse replacements.
	 */
	private static function reverse_replace_in_post_content( string $content, int $canonical_id, int $attachment_id ): string {
		$ref_regex = '/(ids=["\']|id=["\']attachment_|wp-image-)(' . preg_quote( (string) $canonical_id, '/' ) . ')(["\'\s>]|$)/';
		$new       = preg_replace( $ref_regex, '${1}' . $attachment_id . '${3}', $content );
		if ( ! is_string( $new ) ) {
			$new = $content;
		}
		return self::replace_attachment_variant_urls_in_string( $new, $canonical_id, $attachment_id );
	}

	/**
	 * Reverse content and thumbnail changes for $attachment_id on all affected posts.
	 * Only reverts changes that are still present (canonical references); skips if already changed.
	 *
	 * @param int   $attachment_id Trashed attachment being restored.
	 * @param int   $canonical_id  Attachment that replaced it.
	 * @param int[] $post_ids      Posts to check (from trash meta affected_post_ids).
	 */
	public static function restore_post_backups( int $attachment_id, int $canonical_id, array $post_ids ): void {
		global $wpdb;

		foreach ( $post_ids as $post_id ) {
			$post_id  = absint( $post_id );
			$existing = get_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, true );
			if ( ! is_array( $existing ) || ! isset( $existing[ $attachment_id ] ) ) {
				continue;
			}

			$entry = $existing[ $attachment_id ];

			if ( ! empty( $entry['content'] ) ) {
				$content = get_post_field( 'post_content', $post_id );
				if ( is_string( $content ) && '' !== $content ) {
					$updated = false;
					if ( has_blocks( $content ) ) {
						// Block Editor
						$blocks = parse_blocks( $content );
						$blocks = self::recursive_block_replacement( $blocks, $canonical_id, $attachment_id, $updated );

						if ( $updated ) {
							$new = serialize_blocks( $blocks );
						} else {
							$new = $content;
						}
					} else {
						// Classic Editor fallback
						$new = self::reverse_replace_in_post_content( $content, $canonical_id, $attachment_id );

						if ( $new !== $content ) {
							$updated = true;
						}
					}

					if ( $updated && isset( $new ) ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $post_id ) );
						clean_post_cache( $post_id );
					}
				}
			}

			if ( ! empty( $entry['thumbnail'] ) ) {
				$current_thumb = (int) get_post_meta( $post_id, '_thumbnail_id', true );
				if ( $current_thumb === $canonical_id ) {
					update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
				}
			}

			if ( ! empty( $entry['gallery'] ) ) {
				$current_gallery = get_post_meta( $post_id, Woo::PRODUCT_IMAGE_GALLERY_META_KEY, true );
				if ( is_string( $current_gallery ) && '' !== $current_gallery ) {
					$updated_gallery = Woo::restore_product_gallery_reference( $current_gallery, $canonical_id, $attachment_id );
					if ( $updated_gallery !== $current_gallery ) {
						update_post_meta( $post_id, Woo::PRODUCT_IMAGE_GALLERY_META_KEY, $updated_gallery );
					}
				}
			}

			unset( $existing[ $attachment_id ] );
			if ( empty( $existing ) ) {
				delete_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY );
			} else {
				update_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, $existing );
			}
		}
	}

	/**
	 * Remove backup flags for $attachment_id from all affected posts without restoring content.
	 * Called before permanent deletion so orphaned meta does not accumulate.
	 *
	 * @param int $attachment_id Trashed attachment being permanently deleted.
	 */
	public static function cleanup_post_backups( int $attachment_id ): void {
		$info = self::get_trash_info( $attachment_id );
		if ( ! $info || empty( $info['affected_post_ids'] ) ) {
			return;
		}
		foreach ( $info['affected_post_ids'] as $post_id ) {
			$post_id  = absint( $post_id );
			$existing = get_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, true );
			if ( ! is_array( $existing ) || ! isset( $existing[ $attachment_id ] ) ) {
				continue;
			}
			unset( $existing[ $attachment_id ] );
			if ( empty( $existing ) ) {
				delete_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY );
			} else {
				update_post_meta( $post_id, self::CONTENT_BACKUP_META_KEY, $existing );
			}
		}
	}

	/**
	 * Recursively replace exact integer $from with $to in arrays/objects; only scalar ints.
	 *
	 * @param mixed $value Value (passed by reference for arrays/objects).
	 * @param int   $from  Integer to replace.
	 * @param int   $to    Replacement integer.
	 * @return bool Whether any replacement was made.
	 */
	private static function replace_int_in_value( &$value, int $from, int $to ): bool {
		if ( is_int( $value ) && $value === $from ) {
			$value = $to;
			return true;
		}
		if ( is_array( $value ) ) {
			$changed = false;
			foreach ( $value as $k => $v ) {
				if ( self::replace_int_in_value( $value[ $k ], $from, $to ) ) {
					$changed = true;
				}
			}
			return $changed;
		}
		if ( is_object( $value ) ) {
			$changed = false;
			foreach ( (array) $value as $k => $v ) {
				if ( self::replace_int_in_value( $value->{$k}, $from, $to ) ) {
					$changed = true;
				}
			}
			return $changed;
		}
		return false;
	}
}
