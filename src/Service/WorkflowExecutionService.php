<?php
/**
 * Workflow execution service.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use InvalidArgumentException;
use RuntimeException;
use WorkflowAutomate\Plugin\Domain\WorkflowNode;
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Domain\WorkflowRunLog;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;
use WorkflowAutomate\Plugin\Service\Agent\AgentGraphHelper;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs a workflow's nodes start-to-finish. Several ways a run comes to
 * exist:
 *
 * - `run()`: synchronous, within the current request. Used by the "run
 *   now"/test REST action, where a human is waiting for an immediate
 *   result. Never retried automatically — a deliberate, explicit test
 *   should not silently re-fire later without the user asking for that.
 * - `queue()` + `executeClaimedRun()`: background/queued, per roadmap item
 *   8. `queue()` just records a `queued` row and returns immediately;
 *   `BackgroundRunner` claims it later (see
 *   `WorkflowRunRepository::claimBatch()`) and calls `executeClaimedRun()`
 *   from a WP-Cron request instead of the request that triggered it. This
 *   is what `Integration\WorkflowTriggerBinder` uses, since a live
 *   WordPress hook could fire during any front-end/admin/REST request and
 *   must not block it on potentially slow node execution (see
 *   docs/internal/architecture.md §6 performance requirements). Failed or
 *   partial background runs are retried with backoff, up to a limit — see
 *   `maybeScheduleRetry()`.
 * - `rerun()`: synchronous, like run(), but re-executes a specific past
 *   run's trigger payload instead of a caller-supplied one — the "Re-run"
 *   action in the history UI (roadmap item 9). Also never auto-retried,
 *   for the same reason as run().
 *
 * All paths share `executeNodes()` so they can never drift apart in how a
 * run actually executes.
 *
 * Node ordering and failure handling are both intentionally simple for this
 * increment, since the builder (roadmap item 6) does not yet persist
 * connections between nodes:
 *
 * - Trigger nodes are skipped (they start a run; they do not participate in
 *   it — see Domain\Contracts\TriggerInterface).
 * - Every other node executes once, in the order it appears in the graph.
 *   This is a placeholder for real dependency-graph (DAG) execution, which
 *   needs the builder to support drawing connections first.
 * - Execution stops at the first failing node ("fail fast") by default;
 *   the "General" tab of the Settings screen (roadmap item 10) exposes a
 *   global "continue on failure" toggle instead — see
 *   `SettingsService::shouldContinueOnFailure()`. There is no per-workflow
 *   override yet, since the builder (roadmap item 6) has no settings panel
 *   of its own to host one; that remains a possible future refinement, not
 *   a gap in this increment's stated scope.
 */
class WorkflowExecutionService {

	/**
	 * A background run is retried up to this many times in total (i.e. up
	 * to 2 retries after the original attempt) before being left in its
	 * final failed/partial state. Kept low because a retry re-executes every
	 * node from the start, repeating any paid third-party call the earlier
	 * attempt already made.
	 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Backoff base, in seconds: attempt 2 waits ~1 minute, attempt 3 ~2
	 * minutes, attempt 4 ~4 minutes, attempt 5 ~8 minutes (doubling),
	 * capped by MAX_BACKOFF_SECONDS.
	 */
	private const BASE_BACKOFF_SECONDS = 60;

	private const MAX_BACKOFF_SECONDS = HOUR_IN_SECONDS;

	private WorkflowService $workflows;

	private NodeTypeRegistry $registry;

	private NodeExecutionService $nodeExecutor;

	private WorkflowRunRepository $runs;

	private WorkflowRunLogRepository $runLogs;

	private SettingsService $settings;

	private TriggerReentrancyGuard $trigger_guard;

	public function __construct(
		WorkflowService $workflows,
		NodeTypeRegistry $registry,
		NodeExecutionService $nodeExecutor,
		WorkflowRunRepository $runs,
		WorkflowRunLogRepository $runLogs,
		SettingsService $settings,
		TriggerReentrancyGuard $trigger_guard
	) {
		$this->workflows = $workflows;
		$this->registry = $registry;
		$this->nodeExecutor = $nodeExecutor;
		$this->runs = $runs;
		$this->runLogs = $runLogs;
		$this->settings = $settings;
		$this->trigger_guard = $trigger_guard;
	}

	/**
	 * Runs a workflow synchronously and returns once it has finished. Used
	 * for a "run now"/test action.
	 *
	 * Deliberately not restricted to active workflows: a manual "test this
	 * workflow" action is valid for a draft workflow too.
	 *
	 * @param int                   $workflow_id     Workflow id.
	 * @param array<string, mixed>  $trigger_payload Data the triggering event provided; empty for a manual run.
	 *
	 * @throws InvalidArgumentException When the workflow does not exist.
	 * @throws RuntimeException         When the run could not be recorded.
	 *
	 * @return WorkflowRun
	 */
	public function run( int $workflow_id, array $trigger_payload = array() ): WorkflowRun {
		if ( null === $this->workflows->find( $workflow_id ) ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'workflow-automate' ) );
		}

		$run = $this->runs->insert(
			array(
				'workflow_id' => $workflow_id,
				'status' => WorkflowRun::STATUS_RUNNING,
				'trigger_payload' => $trigger_payload,
			)
		);

		if ( null === $run ) {
			throw new RuntimeException( esc_html__( 'Failed to start the workflow run.', 'workflow-automate' ) );
		}

		return $this->executeNodes( $run );
	}

	/**
	 * Re-executes a past run's trigger payload as a brand-new run, linked
	 * back to the original via `parent_run_id`. Used by the "Re-run" action
	 * in the history UI (roadmap item 9).
	 *
	 * Synchronous and never auto-retried, like run() — a human explicitly
	 * asked for this one specific re-execution and is waiting to see its
	 * result, not for it to be silently deferred or retried later.
	 *
	 * @param int $run_id The run to re-execute.
	 *
	 * @throws InvalidArgumentException When the run, or the workflow it belongs to, no longer exists.
	 * @throws RuntimeException         When the new run could not be recorded.
	 *
	 * @return WorkflowRun The new (finished) run.
	 */
	public function rerun( int $run_id ): WorkflowRun {
		$original = $this->runs->find( $run_id );

		if ( null === $original ) {
			throw new InvalidArgumentException( esc_html__( 'The specified run does not exist.', 'workflow-automate' ) );
		}

		if ( null === $this->workflows->find( $original->workflowId() ) ) {
			throw new InvalidArgumentException( esc_html__( 'The workflow this run belongs to no longer exists.', 'workflow-automate' ) );
		}

		$run = $this->runs->insert(
			array(
				'workflow_id' => $original->workflowId(),
				'parent_run_id' => $original->id(),
				'status' => WorkflowRun::STATUS_RUNNING,
				'trigger_payload' => $original->triggerPayload(),
			)
		);

		if ( null === $run ) {
			throw new RuntimeException( esc_html__( 'Failed to start the re-run.', 'workflow-automate' ) );
		}

		return $this->executeNodes( $run );
	}

	/**
	 * Records a workflow to run in the background instead of executing it
	 * now. Returns as soon as the queued row exists; the actual node
	 * execution happens later, out-of-request, when BackgroundRunner claims
	 * it (see WorkflowRunRepository::claimBatch()).
	 *
	 * @param int                  $workflow_id     Workflow id.
	 * @param array<string, mixed> $trigger_payload Data the triggering event provided.
	 *
	 * @throws InvalidArgumentException When the workflow does not exist.
	 * @throws RuntimeException         When the run could not be recorded.
	 *
	 * @return WorkflowRun The queued (not yet executed) run.
	 */
	public function queue( int $workflow_id, array $trigger_payload = array() ): WorkflowRun {
		if ( null === $this->workflows->find( $workflow_id ) ) {
			throw new InvalidArgumentException( esc_html__( 'The specified workflow does not exist.', 'workflow-automate' ) );
		}

		$run = $this->runs->insert(
			array(
				'workflow_id' => $workflow_id,
				'status' => WorkflowRun::STATUS_QUEUED,
				'trigger_payload' => $trigger_payload,
			)
		);

		if ( null === $run ) {
			throw new RuntimeException( esc_html__( 'Failed to queue the workflow run.', 'workflow-automate' ) );
		}

		return $run;
	}

	/**
	 * Queues a run unless an equivalent one is already waiting or in flight.
	 *
	 * A single real-world event can reach the trigger binder several times
	 * (WordPress fires `save_post` more than once per editor "Update"), and
	 * each queued twin would repeat every node — including paid API calls.
	 *
	 * @param int                  $workflow_id     Workflow id.
	 * @param array<string, mixed> $trigger_payload Data the triggering event provided.
	 * @param string               $dedupe_key      JSON fragment identifying the event, e.g. `"post_id":70`.
	 *
	 * @throws InvalidArgumentException When the workflow does not exist.
	 * @throws RuntimeException         When the run could not be recorded.
	 *
	 * @return WorkflowRun|null Null when an equivalent run is already pending.
	 */
	public function queueUnlessPending( int $workflow_id, array $trigger_payload, string $dedupe_key ): ?WorkflowRun {
		if ( '' !== $dedupe_key && $this->runs->hasPendingRunMatchingPayload( $workflow_id, $dedupe_key ) ) {
			return null;
		}

		return $this->queue( $workflow_id, $trigger_payload );
	}

	/**
	 * Executes a run that BackgroundRunner has already claimed (its status
	 * is `running` and it owns a `claim_token` — see
	 * WorkflowRunRepository::claimBatch()). Not meant to be called with any
	 * other kind of run; use run() for a fresh synchronous run instead.
	 *
	 * Unlike run(), a failed or partial outcome here schedules a retry
	 * (see maybeScheduleRetry()) rather than being final immediately.
	 *
	 * @param WorkflowRun $run The claimed run.
	 *
	 * @return WorkflowRun The finished run.
	 */
	public function executeClaimedRun( WorkflowRun $run ): WorkflowRun {
		if ( null === $this->workflows->find( $run->workflowId() ) ) {
			// The workflow was hard-deleted or trashed after this run was
			// queued; nothing to execute against.
			$this->runLogs->insert(
				array(
					'run_id' => $run->id(),
					'node_id' => null,
					'status' => WorkflowRunLog::STATUS_ERROR,
					'message' => __( 'The workflow was deleted or trashed before this queued run could execute.', 'workflow-automate' ),
				)
			);

			return $this->runs->finish( $run->id(), WorkflowRun::STATUS_FAILED ) ?? $run;
		}

		$finished = $this->executeNodes( $run );

		$this->maybeScheduleRetry( $finished );

		return $finished;
	}

	/**
	 * Shared execution loop used by both run() and executeClaimedRun().
	 *
	 * @param WorkflowRun $run A run row that already exists and is ready to execute (status `running`).
	 *
	 * @throws RuntimeException When the run could not be finalized.
	 *
	 * @return WorkflowRun The finished run.
	 */
	private function executeNodes( WorkflowRun $run ): WorkflowRun {
		$workflow_id = $run->workflowId();

		$this->trigger_guard->enter( $workflow_id );

		try {
			return $this->executeNodesGuarded( $run );
		} finally {
			$this->trigger_guard->leave( $workflow_id );
		}
	}

	/**
	 * Executes nodes while the workflow is marked active by the trigger guard.
	 *
	 * @param WorkflowRun $run A running workflow run.
	 *
	 * @throws RuntimeException When the run could not be finalized.
	 *
	 * @return WorkflowRun The finished run.
	 */
	private function executeNodesGuarded( WorkflowRun $run ): WorkflowRun {
		$workflow_id = $run->workflowId();
		$trigger_payload = $run->triggerPayload();

		/**
		 * Fires immediately before a workflow run starts executing nodes.
		 * The `wfa_workflow_runs` row already exists with status `running`.
		 * See docs/hooks-reference.md.
		 *
		 * @since 0.1.0
		 *
		 * @param int                   $workflow_id     The workflow about to run.
		 * @param array<string, mixed>  $trigger_payload Data the triggering event provided; empty for a manual run.
		 */
		do_action( 'wfa/workflow/before_run', $workflow_id, $trigger_payload );

		$nodes = $this->workflows->syncNodesFromGraph( $workflow_id );

		$workflow = $this->workflows->find( $workflow_id );
		$graph    = null !== $workflow ? $workflow->graph() : array();
		$graph_nodes = isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ? $graph['nodes'] : array();
		$graph_connections = isset( $graph['connections'] ) && is_array( $graph['connections'] ) ? $graph['connections'] : array();
		$has_connections_key = array_key_exists( 'connections', $graph ) && is_array( $graph['connections'] );

		$context = array(
			'trigger' => $trigger_payload,
			'nodes' => array(),
			'workflow_id' => $workflow_id,
			'graph' => $graph,
		);

		$executed = 0;
		$succeeded = 0;

		$planner         = new GraphExecutionPlanner();
		$main_path_ids   = $planner->getMainPathNodeIds( $graph_nodes, $graph_connections, $has_connections_key );
		$branch_targets  = $planner->collectBranchTargetIds( $graph_nodes );
		$outgoing_map    = $has_connections_key ? $planner->buildOutgoingMap( $graph_connections ) : array();
		$use_fan_out     = $has_connections_key && array() !== $graph_connections;
		$trigger_id      = $planner->findTriggerId( $graph_nodes );
		$pending_ids     = $use_fan_out && null !== $trigger_id && '' !== $trigger_id
			? array( $trigger_id )
			: $main_path_ids;
		$visited         = array();
		$nodes_by_client = array();

		foreach ( $nodes as $node ) {
			$nodes_by_client[ $node->clientNodeId() ] = $node;
		}

		while ( array() !== $pending_ids ) {
			$client_id = (string) array_shift( $pending_ids );

			if ( '' === $client_id || isset( $visited[ $client_id ] ) ) {
				continue;
			}

			if ( ! isset( $nodes_by_client[ $client_id ] ) ) {
				continue;
			}

			$node = $nodes_by_client[ $client_id ];

			if ( null !== $this->registry->trigger( $node->nodeType() ) ) {
				$visited[ $client_id ] = true;

				if ( $use_fan_out ) {
					foreach ( array_reverse( $planner->getOutgoingTargets( $outgoing_map, $client_id ) ) as $next_id ) {
						if ( ! isset( $visited[ $next_id ] ) ) {
							array_unshift( $pending_ids, $next_id );
						}
					}
				}

				continue;
			}

			if ( AgentGraphHelper::isAgentAttachment( $graph_nodes, $client_id ) ) {
				$visited[ $client_id ] = true;
				continue;
			}

			++$executed;
			$visited[ $client_id ] = true;

			$started_at = microtime( true );
			$node_context = array_merge(
				$context,
				array( 'current_node_id' => $client_id )
			);
			$result = $this->nodeExecutor->execute( $node, $node_context );
			$duration_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );

			$success = ! empty( $result['success'] );

			if ( $success ) {
				++$succeeded;
			}

			$context['nodes'][ $client_id ] = $result;

			$this->runLogs->insert(
				array(
					'run_id' => $run->id(),
					'node_id' => $node->id(),
					'node_type' => $node->nodeType(),
					'node_label' => $node->label(),
					'status' => $success ? WorkflowRunLog::STATUS_SUCCESS : WorkflowRunLog::STATUS_ERROR,
					'input' => $node->config(),
					'output' => $result,
					'message' => $success ? null : ( $result['error'] ?? __( 'The node failed without providing a specific error message.', 'workflow-automate' ) ),
					'duration_ms' => $duration_ms,
				)
			);

			if ( ! $success && ! $this->settings->shouldContinueOnFailure() ) {
				$on_error = $this->resolveAgentOnError( $node, $graph_nodes );

				if ( in_array( $on_error, array( 'continue', 'continue_error_output' ), true ) ) {
					$context['nodes'][ $client_id ] = $this->buildAgentContinueOutput( $result, $on_error );
					++$succeeded;
				} else {
					break;
				}
			}

			$node_type = $node->nodeType();

			if ( in_array( $node_type, GraphExecutionPlanner::BRANCHING_TYPES, true ) ) {
				$branch_ids = $planner->resolveBranchTargets( $result );
				$pending_ids = $planner->stripMainPathAfterBranch( $pending_ids, $client_id, $main_path_ids );

				foreach ( array_reverse( $branch_ids ) as $branch_id ) {
					if ( ! isset( $visited[ $branch_id ] ) ) {
						array_unshift( $pending_ids, $branch_id );
					}
				}

				continue;
			}

			if ( $use_fan_out ) {
				// n8n-style: enqueue every node connected from this node's output.
				foreach ( array_reverse( $planner->getOutgoingTargets( $outgoing_map, $client_id ) ) as $next_id ) {
					if ( ! isset( $visited[ $next_id ] ) ) {
						array_unshift( $pending_ids, $next_id );
					}
				}

				continue;
			}

			if ( isset( $branch_targets[ $client_id ] ) ) {
				$next_branch = $planner->nextInBranchColumn( $client_id, $graph_nodes, $branch_targets );

				if ( null !== $next_branch && ! isset( $visited[ $next_branch ] ) ) {
					array_unshift( $pending_ids, $next_branch );
				}
			}
		}

		$finished = $this->runs->finish( $run->id(), $this->finalStatus( $executed, $succeeded ) );

		$this->workflows->incrementRunCount( $workflow_id );

		if ( null === $finished ) {
			throw new RuntimeException( esc_html__( 'Failed to finalize the workflow run.', 'workflow-automate' ) );
		}

		/**
		 * Fires immediately after a workflow run finishes, whatever its
		 * final status.
		 *
		 * @since 0.1.0
		 *
		 * @param WorkflowRun           $run             The completed run.
		 * @param array<string, mixed>  $trigger_payload Data the triggering event provided; empty for a manual run.
		 */
		do_action( 'wfa/workflow/after_run', $finished, $trigger_payload );

		return $finished;
	}

	/**
	 * Schedules a retry for a background run that ended in `failed` or
	 * `partial`, as a new `queued` row linked via `parent_run_id`, unless
	 * the attempt limit has already been reached. No-op for `success`.
	 *
	 * Retrying re-executes every node from the start; there is no
	 * per-node resume, so a workflow whose first few nodes already
	 * succeeded will run them again on retry. This is the same "no partial
	 * resume" trade-off already documented for fail-fast execution, applied
	 * consistently rather than solved separately here.
	 *
	 * @param WorkflowRun $run The just-finished run.
	 *
	 * @return void
	 */
	private function maybeScheduleRetry( WorkflowRun $run ): void {
		if ( ! in_array( $run->status(), array( WorkflowRun::STATUS_FAILED, WorkflowRun::STATUS_PARTIAL ), true ) ) {
			return;
		}

		if ( $run->attempts() >= self::MAX_ATTEMPTS ) {
			return;
		}

		if ( ! $this->isTransientFailure( $run ) ) {
			return;
		}

		$delay = min( self::MAX_BACKOFF_SECONDS, self::BASE_BACKOFF_SECONDS * ( 2 ** ( $run->attempts() - 1 ) ) );

		$this->runs->insert(
			array(
				'workflow_id' => $run->workflowId(),
				'parent_run_id' => $run->id(),
				'status' => WorkflowRun::STATUS_QUEUED,
				'trigger_payload' => $run->triggerPayload(),
				'attempts' => $run->attempts() + 1,
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
			)
		);
	}

	/**
	 * Decides whether a failed run is worth attempting again.
	 *
	 * Only failures that plausibly resolve on their own — timeouts, rate
	 * limits, upstream 5xx, dropped connections — qualify. A rejected request
	 * or a misconfigured node fails identically every attempt, so retrying it
	 * only multiplies third-party API usage without ever succeeding.
	 *
	 * @param WorkflowRun $run The just-finished run.
	 *
	 * @return bool
	 */
	private function isTransientFailure( WorkflowRun $run ): bool {
		static $signals = array(
			'timed out',
			'timeout',
			'rate limit',
			'too many requests',
			'429',
			'500',
			'502',
			'503',
			'504',
			'temporarily unavailable',
			'try again',
			'overloaded',
			'connection',
			'could not resolve host',
		);

		foreach ( $this->runLogs->findByRun( $run->id() ) as $log ) {
			if ( WorkflowRunLog::STATUS_ERROR !== $log->status() ) {
				continue;
			}

			$message = strtolower( (string) $log->message() );

			if ( '' === $message ) {
				continue;
			}

			foreach ( $signals as $signal ) {
				if ( false !== strpos( $message, $signal ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns every log entry for a run, in execution order.
	 *
	 * @param int $run_id Run id.
	 *
	 * @return WorkflowRunLog[]
	 */
	public function logsFor( int $run_id ): array {
		return $this->runLogs->findByRun( $run_id );
	}

	/**
	 * @param WorkflowNode         $node
	 * @param array<int, mixed>    $graph_nodes
	 *
	 * @return string
	 */
	private function resolveAgentOnError( WorkflowNode $node, array $graph_nodes ): string {
		if ( 'ai_agent_action' !== $node->nodeType() ) {
			return 'stop_workflow';
		}

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || (string) ( $graph_node['id'] ?? '' ) !== $node->clientNodeId() ) {
				continue;
			}

			$config   = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
			$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();

			return (string) ( $settings['on_error'] ?? 'stop_workflow' );
		}

		return 'stop_workflow';
	}

	/**
	 * @param array<string, mixed> $result
	 * @param string               $on_error
	 *
	 * @return array<string, mixed>
	 */
	private function buildAgentContinueOutput( array $result, string $on_error ): array {
		$output = array(
			'success'  => true,
			'response' => '',
			'error'    => (string) ( $result['error'] ?? __( 'AI Agent request failed.', 'workflow-automate' ) ),
		);

		if ( 'continue_error_output' === $on_error ) {
			$output['error_output'] = $output['error'];
		}

		return $output;
	}

	/**
	 * Rolls up per-node outcomes into a single run status.
	 *
	 * @param int $executed  Number of non-trigger nodes that were run.
	 * @param int $succeeded Number of those that succeeded.
	 *
	 * @return string One of WorkflowRun::VALID_STATUSES.
	 */
	private function finalStatus( int $executed, int $succeeded ): string {
		if ( 0 === $executed || $executed === $succeeded ) {
			return WorkflowRun::STATUS_SUCCESS;
		}

		if ( 0 === $succeeded ) {
			return WorkflowRun::STATUS_FAILED;
		}

		return WorkflowRun::STATUS_PARTIAL;
	}
}
