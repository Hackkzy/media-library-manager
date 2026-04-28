<?php
/**
 * REST API Controller for Indexing.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Rest\V1;

use BiliPlugins\MediaLibraryManager\Core\MediaIndexer;
use BiliPlugins\MediaLibraryManager\Scheduler\IndexMediaJob;
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
 * REST API Controller for media indexing operations.
 *
 * @since 1.0.0
 */
class IndexingController extends WP_REST_Controller {

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
	protected $rest_base = 'indexing';

	/**
	 * Instance of MediaIndexer.
	 *
	 * @var MediaIndexer
	 */
	private MediaIndexer $indexer;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->indexer = new MediaIndexer();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_routes(): void {
		// GET /blp-mlm/indexing/progress - Get current indexing progress.
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

		// POST /blp-mlm/indexing/start - Start or resume indexing.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/start',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_indexing' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to access these endpoints.
	 *
	 * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
	 * @since 1.0.0
	 */
	public function check_permission() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'media-library-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get current indexing progress.
	 *
	 * @return WP_REST_Response Response object containing progress data.
	 * @since 1.0.0
	 */
	public function get_progress() {
		return IndexMediaJob::get_progress();
	}

	/**
	 * Start indexing.
	 *
	 * @since 1.0.0
	 */
	public function start_indexing() {
		IndexMediaJob::index_media();
	}
}
