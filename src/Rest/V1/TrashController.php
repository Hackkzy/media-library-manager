<?php
/**
 * REST API Controller for plugin trash (permanent delete queue).
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Rest\V1;

use BiliPlugins\MediaLibraryManager\Core\MediaDeduplicator;
use BiliPlugins\MediaLibraryManager\Scheduler\DeleteTrashedMediaJob;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Exit if accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST routes for enqueueing permanent deletes of plugin-trashed media.
 *
 * @since 1.0.0
 */
class TrashController extends WP_REST_Controller {

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = 'blp-mlm';

	/**
	 * Route base for this controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'trash';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/queue',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'queue_single' ),
					'permission_callback' => array( $this, 'permission_queue_single' ),
					'args'                => array(
						'media_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/empty',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'empty_trash' ),
					'permission_callback' => array( $this, 'permission_manage' ),
				),
			)
		);
	}

	/**
	 * User may manage media library.
	 *
	 * @return bool|WP_Error
	 */
	public function permission_manage() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'media-library-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * User may delete the given attachment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function permission_queue_single( WP_REST_Request $request ) {
		$base = $this->permission_manage();
		if ( is_wp_error( $base ) ) {
			return $base;
		}

		$media_id = (int) $request->get_param( 'media_id' );
		if ( ! current_user_can( 'delete_post', $media_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to delete this media item.', 'media-library-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Enqueue one trashed media ID for background permanent delete.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function queue_single( WP_REST_Request $request ) {
		$media_id = (int) $request->get_param( 'media_id' );

		if ( 'attachment' !== get_post_type( $media_id ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid media ID.', 'media-library-manager' ),
				array( 'status' => 400 )
			);
		}

		if ( ! MediaDeduplicator::is_trashed( $media_id ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'This item is not in the plugin trash.', 'media-library-manager' ),
				array( 'status' => 400 )
			);
		}

		DeleteTrashedMediaJob::enqueue_media_id( $media_id );
		DeleteTrashedMediaJob::start_queue();

		return new WP_REST_Response(
			array(
				'success' => true,
			),
			200
		);
	}

	/**
	 * Queue permanent delete for all plugin-trashed attachments.
	 *
	 * @param WP_REST_Request $request Request (passed by REST API).
	 * @return WP_REST_Response
	 */
	public function empty_trash( WP_REST_Request $request ): WP_REST_Response {
		$queued = DeleteTrashedMediaJob::dispatch_all_trashed();

		return new WP_REST_Response(
			array(
				'success' => true,
				'queued'  => $queued,
			),
			200
		);
	}
}
