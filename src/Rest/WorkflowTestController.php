<?php
/**
 * REST endpoints for builder test-flow listen / status.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Rest;

use AIAWAB\Plugin\Core\Capabilities;
use AIAWAB\Plugin\Service\WorkflowService;
use AIAWAB\Plugin\Service\WorkflowNodeTestService;
use AIAWAB\Plugin\Service\WorkflowTestListenerService;
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

	private WorkflowNodeTestService $node_tester;

	public function __construct(
		WorkflowService $workflows,
		WorkflowTestListenerService $listener,
		WorkflowNodeTestService $node_tester
	) {
		$this->workflows   = $workflows;
		$this->listener    = $listener;
		$this->node_tester = $node_tester;
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
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start_listen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->idArgs(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'stop_listen' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->idArgs(),
				),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/workflows/(?P<id>[\d]+)/test/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->idArgs(),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/workflows/(?P<id>[\d]+)/test/sample',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'clear_sample' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => $this->idArgs(),
			)
		);

		register_rest_route(
			self::API_NAMESPACE,
			'/workflows/(?P<id>[\d]+)/test/node',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_node' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array_merge(
					$this->idArgs(),
					array(
						'node_id' => array(
							'description' => __( 'Client-side node id from the workflow graph.', 'workflow-automate' ),
							'type'        => 'string',
							'required'    => true,
						),
						'graph'   => array(
							'description' => __( 'Optional unsaved workflow graph (nodes + connections).', 'workflow-automate' ),
							'type'        => 'object',
							'required'    => false,
						),
					)
				),
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
				'type'        => 'integer',
				'required'    => true,
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

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function clear_sample( $request ) {
		$id = (int) $request['id'];

		if ( null === $this->workflows->find( $id ) ) {
			return new WP_Error(
				'wfa_rest_not_found',
				__( 'Workflow not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		$this->listener->clearSample( $id );

		return rest_ensure_response( $this->listener->status( $id ) );
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_node( $request ) {
		$id       = (int) $request['id'];
		$node_id  = sanitize_text_field( (string) $request->get_param( 'node_id' ) );
		$graph    = $request->get_param( 'graph' );
		$workflow = $this->workflows->find( $id );

		if ( null === $workflow ) {
			return new WP_Error(
				'wfa_rest_not_found',
				__( 'Workflow not found.', 'workflow-automate' ),
				array( 'status' => 404 )
			);
		}

		if ( '' === $node_id ) {
			return new WP_Error(
				'wfa_rest_invalid_param',
				__( 'A node id is required.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		$graph_override = is_array( $graph ) ? $graph : array();

		return rest_ensure_response(
			$this->node_tester->testNode( $id, $node_id, $graph_override )
		);
	}
}
