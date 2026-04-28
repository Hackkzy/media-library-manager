<?php
/**
 * Freemius SDK for Media Library Manager
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Core;

use Freemius;
use function fs_dynamic_init;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Media Library Manager SDK.
 *
 * @since 1.0.0
 */
class MediaLibraryManagerSDK {

	/**
	 * Freemius product ID.
	 */
	private const PRODUCT_ID = 26309;

	/**
	 * Freemius public key.
	 */
	private const PUBLIC_KEY = 'pk_0ab33d1f355a8140d9cf8e1b83740';

	/**
	 * Class instance.
	 *
	 * @var MediaLibraryManagerSDK|null
	 */
	private static ?MediaLibraryManagerSDK $instance = null;

	/**
	 * Freemius SDK instance.
	 *
	 * @var Freemius
	 */
	private Freemius $sdk;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {

		$this->sdk = fs_dynamic_init(
			array(
				'id'               => self::PRODUCT_ID,
				'slug'             => 'media-library-manager',
				'type'             => 'plugin',
				'public_key'       => self::PUBLIC_KEY,
				'is_premium'       => true,
				'is_premium_only'  => true,
				'has_addons'       => false,
				'has_paid_plans'   => true,
				'is_org_compliant' => false,
				'trial'            => array(
					'days'               => 7,
					'is_require_payment' => true,
				),
				'menu'             => array(
					'slug'    => 'media-library-manager',
					'support' => false,
					'parent'  => array(
						'slug' => 'upload.php',
					),
				),
			)
		);
		do_action( 'mlm_sdk_loaded' );
	}

	/**
	 * Initialize and return class instance.
	 *
	 * @return self
	 */
	public static function init(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get Freemius SDK instance.
	 *
	 * @return Freemius|null
	 */
	public static function sdk(): ?Freemius {
		if ( null === self::$instance ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Freemius SDK has not been initialized yet. Call init() first.', 'media-library-manager' ),
				esc_html( BLP_MLM_VERSION )
			);
			return null;
		}

		return self::$instance->sdk;
	}
}
