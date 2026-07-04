<?php
/**
 * Workflow application service.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Domain\Workflow;
use WorkflowAutomate\Plugin\Domain\WorkflowNode;
use WorkflowAutomate\Plugin\Persistence\WorkflowNodeRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRepository;

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

	public function __construct( WorkflowRepository $workflows, WorkflowNodeRepository $nodes ) {
		$this->workflows = $workflows;
		$this->nodes = $nodes;
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
			throw new InvalidArgumentException( esc_html__( 'A workflow title is required.', 'workflow-automate' ) );
		}

		$workflow = $this->workflows->insert(
			array(
				'title' => $title,
				'status' => Workflow::STATUS_DRAFT,
				'graph' => $attributes['graph'] ?? array(),
				'settings' => $attributes['settings'] ?? null,
			)
		);

		if ( null === $workflow ) {
			throw new RuntimeException( esc_html__( 'Failed to create the workflow.', 'workflow-automate' ) );
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
	 * @param int                   $id         Workflow id.
	 * @param array<string, mixed>  $attributes Any of: title, graph, settings.
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
				throw new InvalidArgumentException( esc_html__( 'A workflow title cannot be empty.', 'workflow-automate' ) );
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
			throw new InvalidArgumentException( esc_html__( 'Invalid workflow status.', 'workflow-automate' ) );
		}

		return $this->workflows->update( $id, array( 'status' => $status ) );
	}

	/**
	 * Deletes a workflow.
	 *
	 * @param int  $id   Workflow id.
	 * @param bool $hard When true, permanently deletes the workflow and its
	 *                   nodes instead of soft-deleting (trashing) it.
	 *
	 * @return bool
	 */
	public function delete( int $id, bool $hard = false ): bool {
		if ( $hard ) {
			$this->nodes->deleteByWorkflow( $id );

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
	 * @param int                   $workflow_id Workflow id.
	 * @param array<string, mixed>  $attributes  See WorkflowNodeRepository::insert().
	 *
	 * @throws InvalidArgumentException When the workflow does not exist or required fields are missing.
	 * @throws RuntimeException         When the underlying insert fails (e.g. duplicate client_node_id).
	 *
	 * @return WorkflowNode
	 */
	public function addNode( int $workflow_id, array $attributes ): WorkflowNode {
		if ( null === $this->workflows->find( $workflow_id, true ) ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'workflow-automate' ) );
		}

		$client_node_id = isset( $attributes['client_node_id'] ) ? trim( (string) $attributes['client_node_id'] ) : '';
		$node_type = isset( $attributes['node_type'] ) ? trim( (string) $attributes['node_type'] ) : '';

		if ( '' === $client_node_id || '' === $node_type ) {
			throw new InvalidArgumentException( esc_html__( 'A node requires both a client node id and a node type.', 'workflow-automate' ) );
		}

		$node = $this->nodes->insert(
			array(
				'workflow_id' => $workflow_id,
				'client_node_id' => $client_node_id,
				'node_type' => $node_type,
				'label' => $attributes['label'] ?? null,
				'config' => $attributes['config'] ?? null,
			)
		);

		if ( null === $node ) {
			throw new RuntimeException( esc_html__( 'Failed to add the node. Its client node id may already be used in this workflow.', 'workflow-automate' ) );
		}

		return $node;
	}

	/**
	 * Updates an existing node.
	 *
	 * @param int                   $node_id    Node id.
	 * @param array<string, mixed>  $attributes See WorkflowNodeRepository::update().
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
}
