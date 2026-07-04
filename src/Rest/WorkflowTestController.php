<?php
/**
 * REST endpoints for builder test-flow listen / status.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Service\WorkflowService;
use WorkflowAutomate\Plugin\Service\WorkflowTestListenerService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WorkflowTestController {

	private const API_NAMESPACE = 'wfa/v1';

	private WorkflowService $workflows;

	private WorkflowTestListenerService $listener;

	public function __construct( WorkflowService $workflows, WorkflowTestListenerService $listener ) {
		$this->workflows = $workflows;
		$this->listener  = $listener;
	}

	/**
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/workflows/(?P<id>[\d]+)/test/listen',
			array(
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'start_listen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => $this->idArgs(),
				),
				array(
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => array( $this, 'stop_listen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args' => $this->idArgs(),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/workflows/(?P<id>[\d]+)/test/status',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args' => $this->idArgs(),
			)
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function idArgs(): array {
		return array(
			'id' => array(
				'description' => __( 'Unique identifier for the workflow.', 'workflow-automate' ),
				'type' => 'integer',
				'required' => true,
			),
		);
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check( $request ) {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			return new WP_Error(
				'wfa_rest_forbidden',
				__( 'Sorry, you are not allowed to test workflows.', 'workflow-automate' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_listen( $request ) {
		$id = (int) $request['id'];

		if ( null === $this->workflows->find( $id ) ) {
			return new WP_Error(
				'wfa_rest_not_found',
				__( 'Workflow not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$this->listener->startListening( $id );

		return rest_ensure_response( $this->listener->status( $id ) );
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function stop_listen( $request ) {
		$id = (int) $request['id'];

		if ( null === $this->workflows->find( $id ) ) {
			return new WP_Error(
				'wfa_rest_not_found',
				__( 'Workflow not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$this->listener->stopListening( $id );

		return rest_ensure_response( $this->listener->status( $id ) );
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( $request ) {
		$id = (int) $request['id'];

		if ( null === $this->workflows->find( $id ) ) {
			return new WP_Error(
				'wfa_rest_not_found',
				__( 'Workflow not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $this->listener->status( $id ) );
	}
}
