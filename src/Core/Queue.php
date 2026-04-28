<?php
/**
 * Core logic queueing.
 *
 * @package Media_Library_Manager
 */

namespace BiliPlugins\MediaLibraryManager\Core;

use ActionScheduler;

/**
 * Class for managing task queues using Action Scheduler.
 *
 * @since 1.0.0
 */
class Queue {

	/**
	 * Group name for Action Scheduler.
	 *
	 * @var string
	 */
	private $group_name;

	/**
	 * Callback action for Action Scheduler.
	 *
	 * @var string
	 */
	private $callback_action;

	/**
	 * AJAX action for running the queue.
	 *
	 * @var string
	 */
	private $ajax_action;

	/**
	 * Constructor.
	 *
	 * @param string $group_name      Group name for Action Scheduler.
	 * @param string $callback_action Callback action for Action Scheduler.
	 */
	public function __construct( $group_name, $callback_action ) {
		$this->group_name      = $group_name;
		$this->callback_action = $callback_action;
		$this->ajax_action     = 'blp_mlm_run_queue';

		add_action(
			'wp_ajax_nopriv_' . $this->ajax_action,
			function () {
				ActionScheduler::runner()->run( $this->group_name );
				wp_die( 'OK' );
			}
		);
	}

	/**
	 * Add a task to the queue.
	 *
	 * @param array $task_data Data to pass to the callback action.
	 * @param int   $priority  Priority of the task (lower number = higher priority).
	 * @return int Action ID of the scheduled task.
	 */
	public function add_to_queue( $task_data, $priority = 20 ) {
		global $wpdb;

		$store     = ActionScheduler::store();
		$action_id = $store->query_action(
			array(
				'group'    => $this->group_name,
				'status'   => \ActionScheduler_Store::STATUS_PENDING,
				'args'     => $task_data,
				'priority' => $priority,
			)
		);

		if ( $action_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->actionscheduler_actions,
				array( 'priority' => $priority ),
				array( 'action_id' => $action_id )
			);

			return $action_id;
		}

		return as_enqueue_async_action(
			$this->callback_action,
			$task_data,
			$this->group_name,
			false,
			$priority
		);
	}

	/**
	 * Start processing the queue.
	 *
	 * @return void
	 */
	public function start_queue() {
		$url = add_query_arg( 'action', $this->ajax_action, admin_url( 'admin-ajax.php' ) );
		wp_remote_get(
			$url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
			)
		);
	}

	/**
	 * Get count of pending tasks in the queue.
	 *
	 * @return int Number of pending tasks.
	 */
	public function get_pending_count() {
		$store      = ActionScheduler::store();
		$query_args = array(
			'group'  => $this->group_name,
			'status' => \ActionScheduler_Store::STATUS_PENDING,
		);

		return (int) $store->query_actions( $query_args, 'count' );
	}

	/**
	 * Clear all tasks from the queue.
	 *
	 * @return void
	 */
	public function clear_queue() {
		as_unschedule_all_actions( '', array(), $this->group_name );
	}
}
