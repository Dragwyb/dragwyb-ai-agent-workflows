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
use WorkflowAutomate\Plugin\Service\SettingsService;
use WorkflowAutomate\Plugin\Service\WorkflowExecutionService;
use WorkflowAutomate\Plugin\Service\WorkflowService;
use WorkflowAutomate\Plugin\Service\WorkflowTestListenerService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes the loop the node type registry (roadmap item 5) deliberately left
 * open: something has to call TriggerInterface::bind() for it to have any
 * effect. This class does so, once per request, for every active
 * workflow's trigger node(s), wiring each one to queue a run (via
 * WorkflowExecutionService::queue()) when its underlying event fires.
 *
 * Queues rather than runs synchronously by default: the WordPress hook a
 * trigger binds to could be firing in the middle of an arbitrary
 * front-end/admin/REST request that has nothing to do with this plugin, so
 * blocking it on potentially slow node execution (outbound HTTP calls,
 * etc.) is not acceptable — see docs/internal/architecture.md §6
 * performance requirements. A WP-Cron-driven BackgroundRunner (roadmap item
 * 8) executes the queued run out-of-request shortly after. The Settings
 * screen's "Advanced" tab (roadmap item 10) can disable background
 * execution entirely — see SettingsService::backgroundExecutionEnabled()
 * — in which case triggers fall back to running synchronously instead;
 * this exists for hosts where WP-Cron is unreliable or disabled outright,
 * not recommended otherwise.
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

	private SettingsService $settings;

	private WorkflowTestListenerService $test_listener;

	public function __construct( WorkflowService $workflows, NodeTypeRegistry $registry, WorkflowExecutionService $executor, SettingsService $settings, WorkflowTestListenerService $test_listener ) {
		$this->workflows = $workflows;
		$this->registry = $registry;
		$this->executor = $executor;
		$this->settings = $settings;
		$this->test_listener = $test_listener;
	}

	/**
	 * Binds every trigger node belonging to every active workflow.
	 *
	 * @return void
	 */
	public function bindActiveWorkflows(): void {
		$listening_ids = array_fill_keys( $this->test_listener->listeningWorkflowIds(), true );

		$active = $this->workflows->list(
			array(
				'status' => Workflow::STATUS_ACTIVE,
				'per_page' => self::MAX_ACTIVE_WORKFLOWS,
			)
		);

		$bound_ids = array();

		foreach ( $active['items'] as $workflow ) {
			$workflow_id = $workflow->id();
			$test_listen = isset( $listening_ids[ $workflow_id ] );
			$this->bindWorkflow( $workflow, $test_listen );
			$bound_ids[ $workflow_id ] = true;
		}

		foreach ( array_keys( $listening_ids ) as $workflow_id ) {
			if ( isset( $bound_ids[ $workflow_id ] ) ) {
				continue;
			}

			$workflow = $this->workflows->find( $workflow_id );

			if ( null !== $workflow ) {
				$this->bindWorkflow( $workflow, true );
			}
		}
	}

	/**
	 * @param Workflow $workflow      Workflow to bind.
	 * @param bool     $test_listen   True when binding for builder test-listen capture.
	 *
	 * @return void
	 */
	private function bindWorkflow( Workflow $workflow, bool $test_listen ): void {
		$graph_nodes = $workflow->graph()['nodes'] ?? array();

		if ( ! is_array( $graph_nodes ) ) {
			return;
		}

		$workflow_id = $workflow->id();
		$trigger_bound = false;

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['type'] ) ) {
				continue;
			}

			$trigger = $this->registry->trigger( (string) $graph_node['type'] );

			if ( null === $trigger ) {
				continue;
			}

			// One trigger per workflow — ignore extra trigger nodes in legacy graphs.
			if ( $trigger_bound ) {
				continue;
			}

			$trigger_bound = true;

			$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();

			$trigger->bind(
				$config,
				function ( array $payload, array $bound_config ) use ( $workflow_id, $test_listen, $graph_node ): void {
					unset( $bound_config );

					if ( $test_listen ) {
						$trigger_type = isset( $graph_node['type'] ) ? (string) $graph_node['type'] : null;
						$this->test_listener->capturePayload( $workflow_id, $payload, $trigger_type );
						return;
					}

					if ( $this->settings->backgroundExecutionEnabled() ) {
						$this->executor->queue( $workflow_id, $payload );
					} else {
						$this->executor->run( $workflow_id, $payload );
					}
				}
			);
		}
	}
}
