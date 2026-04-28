<?php
/**
 * Media Hashing Class
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
 * Class for Media Hasher.
 *
 * @since 1.0.0
 */
class MediaHasher {

	/**
	 * Meta key for file hash.
	 *
	 * @var string
	 */
	const META_KEY_HASH = 'blp_mlm_file_hash';

	/**
	 * Generate SHA-256 hash for a file.
	 *
	 * @param string $file_path Full path to the file.
	 * @return string|false File hash on success, false on failure.
	 * @since 1.0.0
	 */
	private static function generate_file_hash( string $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		$hash = hash_file( 'sha256', $file_path );

		return ( false !== $hash ) ? $hash : false;
	}

	/**
	 * Get file size in bytes.
	 *
	 * @param string $file_path Full path to the file.
	 * @return int|false File size in bytes on success, false on failure.
	 * @since 1.0.0
	 */
	private static function get_file_size( string $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return false;
		}

		$size = filesize( $file_path );

		return ( false !== $size ) ? (int) $size : false;
	}

	/**
	 * Hash and store metadata for an attachment.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return array|false Array with 'hash' and 'size' keys on success, false on failure.
	 * @since 1.0.0
	 */
	public static function hash_attachment( int $attachment_id ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		$file_path = get_attached_file( $attachment_id );
		$hash      = self::generate_file_hash( $file_path );

		if ( ! is_file( $file_path ) ) {
			update_post_meta( $attachment_id, self::META_KEY_HASH, $hash );
			return false;
		}

		// Ensure path is absolute.
		if ( ! path_is_absolute( $file_path ) ) {
			$file_path = wp_get_upload_dir()['basedir'] . '/' . ltrim( $file_path, '/' );
		}

		$size = self::get_file_size( $file_path );

		if ( false === $hash || false === $size ) {
			/**
			 * Action fired when attachment hashing fails.
			 *
			 * @param int $attachment_id WordPress attachment post ID.
			 * @since 1.0.0
			 */
			do_action( 'blp_mlm_hash_attachment_failed', $attachment_id );
			return false;
		}

		// Store in post_meta.
		update_post_meta( $attachment_id, self::META_KEY_HASH, $hash );

		$result = array(
			'hash' => $hash,
			'size' => $size,
		);

		/**
		 * Action fired after attachment is successfully hashed.
		 *
		 * @param int   $attachment_id WordPress attachment post ID.
		 * @param array $result Array with 'hash' and 'size' keys.
		 * @since 1.0.0
		 */
		do_action( 'blp_mlm_attachment_hashed', $attachment_id, $result );

		return $result;
	}

	/**
	 * Get stored file hash for an attachment.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return string|false File hash on success, false if not found.
	 * @since 1.0.0
	 */
	public function get_hash( int $attachment_id ) {
		$hash = get_post_meta( $attachment_id, self::META_KEY_HASH, true );

		return ! empty( $hash ) ? $hash : false;
	}

	/**
	 * Check if attachment is already hashed.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return bool True if attachment has hash metadata, false otherwise.
	 * @since 1.0.0
	 */
	public function is_hashed( int $attachment_id ): bool {
		$hash = get_post_meta( $attachment_id, self::META_KEY_HASH, true );

		return ! empty( $hash ) ? true : false;
	}
}
