<?php
/**
 * Admin Hooks Handler
 *
 * Manages all WordPress admin hook integrations for the plugin.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Admin;

use BiliPlugins\MediaLibraryManager\Core\MediaHasher;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Admin Hooks.
 *
 * @since 1.0.0
 */
class AdminHooks {

	/**
	 * Instance of MediaHasher.
	 *
	 * @var MediaHasher
	 */
	private MediaHasher $hasher;

	/**
	 * Constructor for class.
	 */
	public function __construct() {
		$this->hasher = new MediaHasher();
		$this->register_hooks();
	}

	/**
	 * Register all admin hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Attachment hooks.
		add_action( 'add_attachment', array( $this, 'generate_hash' ) );
		add_action( 'edit_attachment', array( $this, 'generate_hash' ) );
	}

	/**
	 * Generate and store hash for an attachment.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 * @return void
	 * @since 1.0.0
	 */
	public function generate_hash( int $attachment_id ) {
		// Generate and store hash when a new attachment is added.
		MediaHasher::hash_attachment( $attachment_id );
	}
}
