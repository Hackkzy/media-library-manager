<?php
/**
 * Compatibility logic for WooCommerce.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Compatibility;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles compatibility logic for WooCommerce.
 *
 * @since 1.0.0
 */
class Woo {

	/**
	 * Product gallery meta key.
	 *
	 * @var string
	 */
	public const PRODUCT_IMAGE_GALLERY_META_KEY = '_product_image_gallery';

	/**
	 * Determine whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
	}

	/**
	 * Determine whether a meta key stores WooCommerce product gallery IDs.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	public static function is_product_gallery_meta_key( string $meta_key ): bool {
		return self::PRODUCT_IMAGE_GALLERY_META_KEY === $meta_key;
	}

	/**
	 * Replace one attachment ID with another inside a product gallery meta string.
	 *
	 * @param string $value   Meta value.
	 * @param int    $from_id Source attachment ID.
	 * @param int    $to_id   Replacement attachment ID.
	 * @return string Updated meta value.
	 */
	public static function replace_product_gallery_reference( string $value, int $from_id, int $to_id ): string {
		return self::replace_comma_separated_attachment_ids( $value, $from_id, $to_id );
	}

	/**
	 * Restore one attachment ID inside a product gallery meta string.
	 *
	 * @param string $value         Meta value.
	 * @param int    $canonical_id  Canonical attachment ID currently stored.
	 * @param int    $attachment_id Original attachment ID to restore.
	 * @return string Updated meta value.
	 */
	public static function restore_product_gallery_reference( string $value, int $canonical_id, int $attachment_id ): string {
		return self::replace_comma_separated_attachment_ids( $value, $canonical_id, $attachment_id );
	}

	/**
	 * Replace attachment IDs within a comma-separated ID list.
	 *
	 * @param string $value   Comma-separated IDs.
	 * @param int    $from_id Source attachment ID.
	 * @param int    $to_id   Replacement attachment ID.
	 * @return string Updated value.
	 */
	private static function replace_comma_separated_attachment_ids( string $value, int $from_id, int $to_id ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return $value;
		}

		if ( false === strpos( $value, (string) $from_id ) ) {
			return $value;
		}

		if ( ! preg_match( '/^\s*\d+(?:\s*,\s*\d+)*\s*$/', $value ) ) {
			return $value;
		}

		$ids = array_filter(
			array_map(
				'trim',
				explode( ',', $value )
			),
			static function ( $item ) {
				return '' !== $item;
			}
		);

		if ( empty( $ids ) ) {
			return $value;
		}

		$updated = false;
		foreach ( $ids as &$id ) {
			if ( (string) $from_id === $id ) {
				$id      = (string) $to_id;
				$updated = true;
			}
		}
		unset( $id );

		if ( ! $updated ) {
			return $value;
		}

		$ids = array_values( array_unique( $ids ) );
		return implode( ',', $ids );
	}
}
