<?php
/**
 * REST API Controller for Duplicate Management.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Rest\V1;

use BiliPlugins\MediaLibraryManager\Core\MediaDataProvider;
use BiliPlugins\MediaLibraryManager\Scheduler\RemoveDuplicatesJob;
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
 * REST API Controller for handling duplicate removal operations.
 *
 * @since 1.0.0
 */
class DuplicatesController extends WP_REST_Controller {

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
	protected $rest_base = 'duplicates';

	/**
	 * Instance of MediaDataProvider.
	 *
	 * @var MediaDataProvider
	 */
	private MediaDataProvider $data_provider;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->data_provider = new MediaDataProvider();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		// GET /blp-mlm/duplicates/progress - Get current duplicate stats and queue status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/progress',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_progress' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// POST /blp-mlm/duplicates/remove-all - Start the background removal process.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/remove-all',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_removal' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to access these endpoints.
	 *
	 * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
	 */
	public function check_permission() {
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
	 * Get current duplicate removal progress.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Progress data including counts and items in queue.
	 */
	public function get_progress( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			RemoveDuplicatesJob::get_progress(),
			200
		);
	}

	/**
	 * Queue duplicate-removal actions in batches (JSON body: last_hash, batch_size).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Batch result plus success flag.
	 */
	public function start_removal( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$last_hash = isset( $params['last_hash'] ) ? (string) $params['last_hash'] : '';
		$last_hash = substr( $last_hash, 0, 128 );

		$batch_size = isset( $params['batch_size'] ) ? (int) $params['batch_size'] : 50;
		$batch_size = max( 1, min( 200, $batch_size ) );

		$result = RemoveDuplicatesJob::dispatch_batch( $batch_size, $last_hash );

		return new WP_REST_Response(
			array_merge(
				array( 'success' => true ),
				$result
			),
			200
		);
	}
}
