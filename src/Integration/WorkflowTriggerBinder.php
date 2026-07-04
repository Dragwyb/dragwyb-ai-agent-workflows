<?php
/**
 * Binds active workflows' triggers to their real-world event sources.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration;

use WorkflowAutomate\Plugin\Domain\Workflow;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes the loop the node type registry (roadmap item 5) deliberately left
 * open: something has to call TriggerInterface::bind() for it to have any
 * effect. This class does so, once per request, for every active
 * workflow's trigger node(s), wiring each one to actually start a run (via
 * WorkflowExecutionService) when its underlying event fires.
 *
 * Reads trigger configuration directly from the workflow's `graph_json`
 * rather than the `wfa_workflow_nodes` table: binding must happen as early
 * as possible in the request (see Core\Plugin::registerExecutionEngine()),
 * before WorkflowExecutionService ever gets a chance to lazily sync that
 * table, so `graph_json` — the builder's own always-current source of
 * truth — is the only data guaranteed fresh at that point.
 *
 * Runs on every request (front-end, admin, REST, cron, AJAX), since a
 * WordPress hook this plugin should react to could fire from any of them.
 * That is an inherent cost of the "trigger via live WP hooks" model chosen
 * in roadmap item 5, not something this class can avoid; the mitigation
 * here is a cheap early exit when there are no active workflows at all, and
 * loading only one page of active workflows (see bindActiveWorkflows()) is
 * flagged below as a known scaling limit rather than solved prematurely.
 */
class WorkflowTriggerBinder {

	/**
	 * Defensive upper bound on how many active workflows are bound per
	 * request. Matches WorkflowRepository::paginate()'s own `per_page`
	 * ceiling — requesting more than that would silently be clamped down
	 * to it anyway, so this is the real limit, not an arbitrary one. Sites
	 * with more than this many simultaneously active workflows are not
	 * expected pre-launch; paginating this loop across multiple pages is
	 * deferred until that becomes a real scenario.
	 */
	private const MAX_ACTIVE_WORKFLOWS = 100;

	private WorkflowService $workflows;

	private NodeTypeRegistry $registry;

	private WorkflowExecutionService $executor;

	public function __construct( WorkflowService $workflows, NodeTypeRegistry $registry, WorkflowExecutionService $executor ) {
		$this->workflows = $workflows;
		$this->registry = $registry;
		$this->executor = $executor;
	}

	/**
	 * Binds every trigger node belonging to every active workflow.
	 *
	 * @return void
	 */
	public function bindActiveWorkflows(): void {
		$active = $this->workflows->list(
			array(
				'status' => Workflow::STATUS_ACTIVE,
				'per_page' => self::MAX_ACTIVE_WORKFLOWS,
			)
		);

		foreach ( $active['items'] as $workflow ) {
			$this->bindWorkflow( $workflow );
		}
	}

	/**
	 * @param Workflow $workflow An active workflow.
	 *
	 * @return void
	 */
	private function bindWorkflow( Workflow $workflow ): void {
		$graph_nodes = $workflow->graph()['nodes'] ?? array();

		if ( ! is_array( $graph_nodes ) ) {
			return;
		}

		$workflow_id = $workflow->id();

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['type'] ) ) {
				continue;
			}

			$trigger = $this->registry->trigger( (string) $graph_node['type'] );

			if ( null === $trigger ) {
				continue;
			}

			$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();

			$trigger->bind(
				$config,
				function ( array $payload, array $bound_config ) use ( $workflow_id ): void {
					unset( $bound_config ); // Required by the TriggerInterface::bind() callback signature; unused here.
					$this->executor->run( $workflow_id, $payload );
				}
			);
		}
	}
}
