<?php
/**
 * Workflow application service.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

use InvalidArgumentException;
use RuntimeException;
use DragwybAgentFlow\Plugin\Domain\Workflow;
use DragwybAgentFlow\Plugin\Domain\WorkflowNode;
use DragwybAgentFlow\Plugin\Persistence\WebhookRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowNodeRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRunLogRepository;
use DragwybAgentFlow\Plugin\Persistence\WorkflowRunRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates workflow and workflow-node CRUD on top of the persistence
 * layer. This service knows nothing about HTTP, admin screens, or requests;
 * a future REST controller (roadmap item 3) is expected to call into it
 * after its own input sanitization/validation.
 */
class WorkflowService {

	private WorkflowRepository $workflows;

	private WorkflowNodeRepository $nodes;

	private WorkflowRunRepository $runs;

	private WorkflowRunLogRepository $runLogs;

	private WebhookRepository $webhooks;

	public function __construct(
		WorkflowRepository $workflows,
		WorkflowNodeRepository $nodes,
		WorkflowRunRepository $runs,
		WorkflowRunLogRepository $runLogs,
		WebhookRepository $webhooks
	) {
		$this->workflows = $workflows;
		$this->nodes     = $nodes;
		$this->runs      = $runs;
		$this->runLogs   = $runLogs;
		$this->webhooks  = $webhooks;
	}

	/**
	 * Creates a new workflow.
	 *
	 * @param array<string, mixed> $attributes {
	 *     @type string                    $title    Required, non-empty.
	 *     @type array<string, mixed>      $graph    Optional builder graph. Defaults to empty.
	 *     @type array<string, mixed>|null $settings Optional per-workflow settings.
	 * }
	 *
	 * @throws InvalidArgumentException When the title is missing or empty.
	 * @throws RuntimeException         When the underlying insert fails.
	 *
	 * @return Workflow
	 */
	public function create( array $attributes ): Workflow {
		$title = isset( $attributes['title'] ) ? trim( (string) $attributes['title'] ) : '';

		if ( '' === $title ) {
			throw new InvalidArgumentException( esc_html__( 'A workflow title is required.', 'dragwyb-agentflow' ) );
		}

		$workflow = $this->workflows->insert(
			array(
				'title'    => $title,
				'status'   => Workflow::STATUS_DRAFT,
				'graph'    => $attributes['graph'] ?? array(),
				'settings' => $attributes['settings'] ?? null,
			)
		);

		if ( null === $workflow ) {
			throw new RuntimeException( esc_html__( 'Failed to create the workflow.', 'dragwyb-agentflow' ) );
		}

		return $workflow;
	}

	/**
	 * Finds a workflow by id.
	 *
	 * @param int  $id              Workflow id.
	 * @param bool $include_trashed Whether to also match soft-deleted workflows.
	 *
	 * @return Workflow|null
	 */
	public function find( int $id, bool $include_trashed = false ): ?Workflow {
		return $this->workflows->find( $id, $include_trashed );
	}

	/**
	 * Updates a workflow's title, graph, and/or settings.
	 *
	 * @param int                  $id         Workflow id.
	 * @param array<string, mixed> $attributes Any of: title, graph, settings.
	 *
	 * @return Workflow|null Null if no workflow exists with the given id.
	 */
	public function update( int $id, array $attributes ): ?Workflow {
		if ( null === $this->workflows->find( $id, true ) ) {
			return null;
		}

		if ( array_key_exists( 'title', $attributes ) ) {
			$attributes['title'] = trim( (string) $attributes['title'] );

			if ( '' === $attributes['title'] ) {
				throw new InvalidArgumentException( esc_html__( 'A workflow title cannot be empty.', 'dragwyb-agentflow' ) );
			}
		}

		return $this->workflows->update( $id, $attributes );
	}

	/**
	 * Transitions a workflow to a new status (draft/active/paused).
	 *
	 * @param int $id     Workflow id.
	 * @param int $status One of Workflow::VALID_STATUSES.
	 *
	 * @throws InvalidArgumentException When the status is not recognized.
	 *
	 * @return Workflow|null Null if no workflow exists with the given id.
	 */
	public function changeStatus( int $id, int $status ): ?Workflow {
		if ( ! in_array( $status, Workflow::VALID_STATUSES, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'Invalid workflow status.', 'dragwyb-agentflow' ) );
		}

		return $this->workflows->update( $id, array( 'status' => $status ) );
	}

	/**
	 * Deletes a workflow.
	 *
	 * @param int  $id   Workflow id.
	 * @param bool $hard When true, permanently deletes the workflow and its
	 *                   nodes, runs, and run logs instead of soft-deleting
	 *                   (trashing) it.
	 *
	 * @return bool
	 */
	public function delete( int $id, bool $hard = false ): bool {
		if ( $hard ) {
			// Logs are keyed by run id, not workflow id, so their ids must
			// be resolved before the runs themselves are removed.
			$this->runLogs->deleteByRunIds( $this->runs->idsForWorkflow( $id ) );
			$this->runs->deleteByWorkflow( $id );
			$this->nodes->deleteByWorkflow( $id );
			// Application-level ON DELETE SET NULL for inbound webhooks
			// (roadmap item 13) — the webhook row stays so its public_id
			// is not silently recycled, but ingress returns 409 until it
			// is re-linked or deleted.
			$this->webhooks->nullifyWorkflow( $id );

			return $this->workflows->delete( $id );
		}

		return $this->workflows->softDelete( $id );
	}

	/**
	 * Restores a previously trashed (soft-deleted) workflow.
	 *
	 * @param int $id Workflow id.
	 *
	 * @return bool
	 */
	public function restore( int $id ): bool {
		return $this->workflows->restore( $id );
	}

	/**
	 * Lists workflows with optional filtering and pagination.
	 *
	 * @param array<string, mixed> $args See WorkflowRepository::paginate().
	 *
	 * @return array{items: Workflow[], total: int, page: int, per_page: int}
	 */
	public function list( array $args = array() ): array {
		return $this->workflows->paginate( $args );
	}

	/**
	 * Adds a node to an existing workflow.
	 *
	 * @param int                  $workflow_id Workflow id.
	 * @param array<string, mixed> $attributes  See WorkflowNodeRepository::insert().
	 *
	 * @throws InvalidArgumentException When the workflow does not exist or required fields are missing.
	 * @throws RuntimeException         When the underlying insert fails (e.g. duplicate client_node_id).
	 *
	 * @return WorkflowNode
	 */
	public function addNode( int $workflow_id, array $attributes ): WorkflowNode {
		if ( null === $this->workflows->find( $workflow_id, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'dragwyb-agentflow' ) );
		}

		$client_node_id = isset( $attributes['client_node_id'] ) ? trim( (string) $attributes['client_node_id'] ) : '';
		$node_type      = isset( $attributes['node_type'] ) ? trim( (string) $attributes['node_type'] ) : '';

		if ( '' === $client_node_id || '' === $node_type ) {
			throw new InvalidArgumentException( esc_html__( 'A node requires both a client node id and a node type.', 'dragwyb-agentflow' ) );
		}

		$node = $this->nodes->insert(
			array(
				'workflow_id'    => $workflow_id,
				'client_node_id' => $client_node_id,
				'node_type'      => $node_type,
				'label'          => $attributes['label'] ?? null,
				'config'         => $attributes['config'] ?? null,
			)
		);

		if ( null === $node ) {
			throw new RuntimeException( esc_html__( 'Failed to add the node. Its client node id may already be used in this workflow.', 'dragwyb-agentflow' ) );
		}

		return $node;
	}

	/**
	 * Updates an existing node.
	 *
	 * @param int                  $node_id    Node id.
	 * @param array<string, mixed> $attributes See WorkflowNodeRepository::update().
	 *
	 * @return WorkflowNode|null Null if no node exists with the given id.
	 */
	public function updateNode( int $node_id, array $attributes ): ?WorkflowNode {
		return $this->nodes->update( $node_id, $attributes );
	}

	/**
	 * Permanently removes a node from its workflow.
	 *
	 * @param int $node_id Node id.
	 *
	 * @return bool
	 */
	public function removeNode( int $node_id ): bool {
		return $this->nodes->delete( $node_id );
	}

	/**
	 * Returns every node belonging to a workflow.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return WorkflowNode[]
	 */
	public function nodesFor( int $workflow_id ): array {
		return $this->nodes->findByWorkflow( $workflow_id );
	}

	/**
	 * Reconciles `dragwyb_af_workflow_nodes` rows with a workflow's current
	 * `graph_json` (the builder's source of truth for node identity and
	 * configuration): existing nodes are updated, new ones inserted, and
	 * ones no longer present in the graph are removed.
	 *
	 * The builder (roadmap item 6) only ever writes the whole graph as JSON
	 * via update(); nothing keeps `dragwyb_af_workflow_nodes` in sync with it as
	 * that happens, since nothing read that table until the execution
	 * engine needed real, stable node ids to log run outcomes against.
	 * Rather than pay a sync cost on every autosave, this is called lazily,
	 * right before a run needs it (see WorkflowExecutionService::run()).
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return WorkflowNode[] The workflow's nodes, freshly synced. Empty if the workflow does not exist.
	 */
	public function syncNodesFromGraph( int $workflow_id ): array {
		$workflow = $this->workflows->find( $workflow_id, true );

		if ( null === $workflow ) {
			return array();
		}

		$graph_nodes = $workflow->graph()['nodes'] ?? array();
		$graph_nodes = is_array( $graph_nodes ) ? $graph_nodes : array();

		$existing_by_client_id = array();

		foreach ( $this->nodes->findByWorkflow( $workflow_id ) as $node ) {
			$existing_by_client_id[ $node->clientNodeId() ] = $node;
		}

		$seen   = array();
		$synced = array();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) || empty( $graph_node['type'] ) ) {
				continue; // Defensively skip a malformed graph entry rather than fail the whole sync.
			}

			$client_node_id          = (string) $graph_node['id'];
			$seen[ $client_node_id ] = true;

			$attributes = array(
				'node_type' => (string) $graph_node['type'],
				'label'     => isset( $graph_node['label'] ) ? (string) $graph_node['label'] : null,
				'config'    => isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : null,
			);

			if ( isset( $existing_by_client_id[ $client_node_id ] ) ) {
				$node = $this->nodes->update( $existing_by_client_id[ $client_node_id ]->id(), $attributes );
			} else {
				$node = $this->nodes->insert(
					array_merge(
						array(
							'workflow_id'    => $workflow_id,
							'client_node_id' => $client_node_id,
						),
						$attributes
					)
				);
			}

			if ( null !== $node ) {
				$synced[] = $node;
			}
		}

		foreach ( $existing_by_client_id as $client_node_id => $node ) {
			if ( ! isset( $seen[ $client_node_id ] ) ) {
				$this->nodes->delete( $node->id() );
			}
		}

		return $this->sortNodesByGraphPosition( $synced, $graph_nodes );
	}

	/**
	 * Orders synced nodes top-to-bottom (then left-to-right) to match the builder canvas.
	 *
	 * @param WorkflowNode[]    $nodes       Synced node rows.
	 * @param array<int, mixed> $graph_nodes Raw graph node entries.
	 *
	 * @return WorkflowNode[]
	 */
	private function sortNodesByGraphPosition( array $nodes, array $graph_nodes ): array {
		$positions = array();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) ) {
				continue;
			}

			$positions[ (string) $graph_node['id'] ] = array(
				'y' => isset( $graph_node['y'] ) ? (int) $graph_node['y'] : 0,
				'x' => isset( $graph_node['x'] ) ? (int) $graph_node['x'] : 0,
			);
		}

		usort(
			$nodes,
			static function ( WorkflowNode $a, WorkflowNode $b ) use ( $positions ): int {
				$pos_a = $positions[ $a->clientNodeId() ] ?? array(
					'y' => 0,
					'x' => 0,
				);
				$pos_b = $positions[ $b->clientNodeId() ] ?? array(
					'y' => 0,
					'x' => 0,
				);

				if ( $pos_a['y'] !== $pos_b['y'] ) {
					return $pos_a['y'] <=> $pos_b['y'];
				}

				return $pos_a['x'] <=> $pos_b['x'];
			}
		);

		return $nodes;
	}

	/**
	 * Records that a workflow has just run, for the `run_count` column
	 * shown in the admin list table.
	 *
	 * @param int $workflow_id Workflow id.
	 *
	 * @return void
	 */
	public function incrementRunCount( int $workflow_id ): void {
		$this->workflows->incrementRunCount( $workflow_id );
	}
}
