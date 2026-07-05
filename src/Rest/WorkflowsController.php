<?php
/**
 * Workflows REST controller.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Rest;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Core\Capabilities;
use WorkflowAutomate\Plugin\Domain\Workflow;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Domain\WorkflowRunLog;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowService;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes `wfa/v1/workflows` for workflow CRUD, plus a `restore` action for
 * un-trashing a soft-deleted workflow. Every route has an explicit
 * permission callback and a JSON-Schema argument definition; no route ever
 * relies on `__return_true` or skips input validation.
 *
 * Node-level endpoints (`workflow_nodes`) are intentionally out of scope
 * for this increment and will be added when the visual builder needs them.
 *
 * A dedicated, paginated `wfa/v1/runs` resource (for the run history UI) is
 * deferred to that later roadmap item; `run_item()` here only exists so the
 * synchronous execution engine (roadmap item 7) is testable/usable before
 * that UI exists, and returns a single run's outcome plus its logs inline
 * rather than a browsable collection.
 */
class WorkflowsController extends WP_REST_Controller {

	private WorkflowService $workflows;

	private WorkflowExecutionService $executor;

	/**
	 * Cached item schema. See get_item_schema().
	 *
	 * Must be protected (or public): WP_REST_Controller declares $schema as
	 * protected, and PHP forbids a child from narrowing that visibility.
	 *
	 * @var array<string, mixed>|null
	 */
	protected $schema = null;

	public function __construct( WorkflowService $workflows, WorkflowExecutionService $executor ) {
		$this->namespace = 'wfa/v1';
		$this->rest_base = 'workflows';
		$this->workflows = $workflows;
		$this->executor = $executor;
	}

	/**
	 * Registers all routes for this controller.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args' => $this->get_collection_params(),
				),
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args' => $this->getCreateArgs(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the workflow.', 'workflow-automate' ),
						'type' => 'integer',
					),
				),
				array(
					'methods' => WP_REST_Server::READABLE,
					'callback' => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args' => array(
						'include_trashed' => array(
							'description' => __( 'Whether to also match a trashed workflow.', 'workflow-automate' ),
							'type' => 'boolean',
							'default' => false,
						),
					),
				),
				array(
					'methods' => WP_REST_Server::EDITABLE,
					'callback' => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args' => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods' => WP_REST_Server::DELETABLE,
					'callback' => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
					'args' => array(
						'force' => array(
							'description' => __( 'Whether to permanently delete the workflow (and its nodes) instead of moving it to the trash.', 'workflow-automate' ),
							'type' => 'boolean',
							'default' => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/restore',
			array(
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'restore_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args' => array(
						'id' => array(
							'description' => __( 'Unique identifier for the workflow.', 'workflow-automate' ),
							'type' => 'integer',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/run',
			array(
				array(
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => array( $this, 'run_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args' => array(
						'id' => array(
							'description' => __( 'Unique identifier for the workflow.', 'workflow-automate' ),
							'type' => 'integer',
						),
					),
				),
			)
		);
	}

	/**
	 * Schema-derived args for POST, minus `status`: new workflows always
	 * start as drafts (see WorkflowService::create()), so advertising an
	 * accepted `status` field on create would be misleading.
	 *
	 * @return array<string, mixed>
	 */
	private function getCreateArgs(): array {
		$args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE );
		unset( $args['status'] );

		return $args;
	}

	/**
	 * All routes on this controller require `wfa_manage_workflows`
	 * (administrators and anyone with `manage_options` receive it via
	 * `Core\Capabilities::filterUserHasCap()`).
	 *
	 * @return true|WP_Error
	 */
	private function checkPermission() {
		if ( ! current_user_can( Capabilities::MANAGE_WORKFLOWS ) ) {
			return new WP_Error(
				'wfa_rest_forbidden',
				__( 'Sorry, you are not allowed to manage workflows.', 'workflow-automate' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->checkPermission();
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->checkPermission();
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->checkPermission();
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->checkPermission();
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->checkPermission();
	}

	/**
	 * Lists workflows.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'page' => (int) $request->get_param( 'page' ),
			'per_page' => (int) $request->get_param( 'per_page' ),
			'include_trashed' => (bool) $request->get_param( 'include_trashed' ),
		);

		if ( null !== $request->get_param( 'status' ) ) {
			$args['status'] = (int) $request->get_param( 'status' );
		}

		$result = $this->workflows->list( $args );

		$items = array();

		foreach ( $result['items'] as $workflow ) {
			$data = $this->prepare_item_for_response( $workflow, $request );
			$items[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) (int) ceil( $result['total'] / max( 1, $result['per_page'] ) ) );

		return $response;
	}

	/**
	 * Retrieves a single workflow.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$workflow = $this->workflows->find( (int) $request['id'], (bool) $request->get_param( 'include_trashed' ) );

		if ( null === $workflow ) {
			return $this->notFoundError();
		}

		return rest_ensure_response( $this->prepare_item_for_response( $workflow, $request ) );
	}

	/**
	 * Creates a new workflow.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		try {
			$workflow = $this->workflows->create(
				array(
					'title' => $request->get_param( 'title' ),
					'graph' => $request->get_param( 'graph' ) ?? array(),
					'settings' => $request->get_param( 'settings' ),
				)
			);
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wfa_rest_invalid', $exception->getMessage(), array( 'status' => 400 ) );
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'wfa_rest_server_error', $exception->getMessage(), array( 'status' => 500 ) );
		}

		$response = rest_ensure_response( $this->prepare_item_for_response( $workflow, $request ) );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $workflow->id() ) ) );

		return $response;
	}

	/**
	 * Updates an existing workflow's title, graph, settings, and/or status.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id = (int) $request['id'];

		$attributes = array();

		foreach ( array( 'title', 'graph', 'settings' ) as $field ) {
			if ( $request->has_param( $field ) ) {
				$attributes[ $field ] = $request->get_param( $field );
			}
		}

		try {
			$workflow = $this->workflows->update( $id, $attributes );

			if ( null !== $workflow && $request->has_param( 'status' ) ) {
				$workflow = $this->workflows->changeStatus( $id, (int) $request->get_param( 'status' ) );
			}
		} catch ( InvalidArgumentException $exception ) {
			return new WP_Error( 'wfa_rest_invalid', $exception->getMessage(), array( 'status' => 400 ) );
		}

		if ( null === $workflow ) {
			return $this->notFoundError();
		}

		return rest_ensure_response( $this->prepare_item_for_response( $workflow, $request ) );
	}

	/**
	 * Deletes (or, with `force`, permanently removes) a workflow.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$id = (int) $request['id'];
		$force = (bool) $request->get_param( 'force' );

		$workflow = $this->workflows->find( $id, true );

		if ( null === $workflow ) {
			return $this->notFoundError();
		}

		$previous = $this->prepare_item_for_response( $workflow, $request );

		if ( ! $this->workflows->delete( $id, $force ) ) {
			return new WP_Error( 'wfa_rest_cannot_delete', __( 'Failed to delete the workflow.', 'workflow-automate' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
				'previous' => $previous->get_data(),
			)
		);
	}

	/**
	 * Restores a previously trashed workflow.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_item( $request ) {
		$id = (int) $request['id'];

		if ( null === $this->workflows->find( $id, true ) ) {
			return $this->notFoundError();
		}

		if ( ! $this->workflows->restore( $id ) ) {
			return new WP_Error( 'wfa_rest_cannot_restore', __( 'Failed to restore the workflow.', 'workflow-automate' ), array( 'status' => 500 ) );
		}

		$workflow = $this->workflows->find( $id, true );

		return rest_ensure_response( $this->prepare_item_for_response( $workflow, $request ) );
	}

	/**
	 * Runs a workflow synchronously ("run now"/test action) and returns its
	 * outcome, including per-node logs so the result is inspectable without
	 * a run history UI. Blocks for as long as the workflow takes to finish
	 * executing every node — see WorkflowExecutionService for why that is
	 * an accepted characteristic of this increment, not an oversight.
	 *
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_item( $request ) {
		$id = (int) $request['id'];

		try {
			$run = $this->executor->run( $id );
		} catch ( InvalidArgumentException $exception ) {
			return $this->notFoundError();
		} catch ( RuntimeException $exception ) {
			return new WP_Error( 'wfa_rest_run_failed', $exception->getMessage(), array( 'status' => 500 ) );
		}

		return rest_ensure_response( $this->serializeRun( $run ) );
	}

	/**
	 * @param WorkflowRun $run The completed run.
	 *
	 * @return array<string, mixed>
	 */
	private function serializeRun( WorkflowRun $run ): array {
		return array(
			'id' => $run->id(),
			'workflow_id' => $run->workflowId(),
			'status' => $run->status(),
			'started_at' => null === $run->startedAt() ? null : mysql_to_rfc3339( $run->startedAt() ),
			'finished_at' => null === $run->finishedAt() ? null : mysql_to_rfc3339( $run->finishedAt() ),
			'logs' => array_map( array( $this, 'serializeRunLog' ), $this->executor->logsFor( $run->id() ) ),
		);
	}

	/**
	 * @param WorkflowRunLog $log A single node's outcome within the run.
	 *
	 * @return array<string, mixed>
	 */
	private function serializeRunLog( WorkflowRunLog $log ): array {
		return array(
			'node_id' => $log->nodeId(),
			'status' => $log->status(),
			'message' => $log->message(),
			'output' => $log->output(),
			'duration_ms' => $log->durationMs(),
		);
	}

	/**
	 * @param Workflow         $item    Domain object to serialize.
	 * @param WP_REST_Request  $request Full request.
	 *
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = array(
			'id' => $item->id(),
			'title' => $item->title(),
			'status' => $item->status(),
			'definition_version' => $item->definitionVersion(),
			'graph' => (object) $item->graph(),
			'settings' => null === $item->settings() ? null : (object) $item->settings(),
			'run_count' => $item->runCount(),
			'is_trashed' => $item->isTrashed(),
			'created_at' => mysql_to_rfc3339( $item->createdAt() ),
			'updated_at' => mysql_to_rfc3339( $item->updatedAt() ),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data = $this->add_additional_fields_to_object( $data, $request );
		$data = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepareLinks( $item ) );

		return $response;
	}

	/**
	 * @param Workflow $item Workflow to link.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function prepareLinks( Workflow $item ): array {
		return array(
			'self' => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);
	}

	/**
	 * @return WP_Error
	 */
	private function notFoundError(): WP_Error {
		return new WP_Error( 'wfa_rest_not_found', __( 'Workflow not found.', 'workflow-automate' ), array( 'status' => 404 ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_item_schema() {
		if ( null !== $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title' => 'workflow',
			'type' => 'object',
			'properties' => array(
				'id' => array(
					'description' => __( 'Unique identifier for the workflow.', 'workflow-automate' ),
					'type' => 'integer',
					'context' => array( 'view', 'edit' ),
					'readonly' => true,
				),
				'title' => array(
					'description' => __( 'The workflow title.', 'workflow-automate' ),
					'type' => 'string',
					'context' => array( 'view', 'edit' ),
					'required' => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'status' => array(
					'description' => __( 'The workflow status (0 = draft, 1 = active, 2 = paused).', 'workflow-automate' ),
					'type' => 'integer',
					'enum' => Workflow::VALID_STATUSES,
					'context' => array( 'view', 'edit' ),
				),
				'definition_version' => array(
					'description' => __( 'Schema version of the stored graph.', 'workflow-automate' ),
					'type' => 'integer',
					'context' => array( 'view' ),
					'readonly' => true,
				),
				'graph' => array(
					'description' => __( 'The builder graph (nodes and connections) as a JSON object.', 'workflow-automate' ),
					'type' => 'object',
					'context' => array( 'view', 'edit' ),
				),
				'settings' => array(
					'description' => __( 'Per-workflow settings.', 'workflow-automate' ),
					'type' => array( 'object', 'null' ),
					'context' => array( 'view', 'edit' ),
				),
				'run_count' => array(
					'description' => __( 'Number of times this workflow has run.', 'workflow-automate' ),
					'type' => 'integer',
					'context' => array( 'view' ),
					'readonly' => true,
				),
				'is_trashed' => array(
					'description' => __( 'Whether the workflow is in the trash.', 'workflow-automate' ),
					'type' => 'boolean',
					'context' => array( 'view' ),
					'readonly' => true,
				),
				'created_at' => array(
					'description' => __( "The workflow's creation date, in the site's timezone.", 'workflow-automate' ),
					'type' => 'string',
					'format' => 'date-time',
					'context' => array( 'view' ),
					'readonly' => true,
				),
				'updated_at' => array(
					'description' => __( "The workflow's last modification date, in the site's timezone.", 'workflow-automate' ),
					'type' => 'string',
					'format' => 'date-time',
					'context' => array( 'view' ),
					'readonly' => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_collection_params() {
		return array(
			'context' => $this->get_context_param( array( 'default' => 'view' ) ),
			'page' => array(
				'description' => __( 'Current page of the collection.', 'workflow-automate' ),
				'type' => 'integer',
				'default' => 1,
				'minimum' => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items to be returned in the result set.', 'workflow-automate' ),
				'type' => 'integer',
				'default' => 20,
				'minimum' => 1,
				'maximum' => 100,
				'sanitize_callback' => 'absint',
			),
			'status' => array(
				'description' => __( 'Limit results to workflows with a specific status.', 'workflow-automate' ),
				'type' => 'integer',
				'enum' => Workflow::VALID_STATUSES,
			),
			'include_trashed' => array(
				'description' => __( 'Whether to include trashed workflows in the results.', 'workflow-automate' ),
				'type' => 'boolean',
				'default' => false,
			),
		);
	}
}
