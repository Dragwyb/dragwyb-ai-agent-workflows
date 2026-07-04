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
use WorkflowAutomate\Plugin\Domain\WorkflowRun;
use WorkflowAutomate\Plugin\Domain\WorkflowRunLog;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunLogRepository;
use WorkflowAutomate\Plugin\Persistence\WorkflowRunRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs a workflow start-to-finish, synchronously, within the current
 * request. Background/queued execution (retries, webhook batching) is a
 * separate later roadmap item; this class is the engine both that future
 * item and any "run now" entry point build on.
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
 * - Execution stops at the first failing node ("fail fast"). This is the
 *   sensible default until a per-workflow "continue on failure" setting
 *   (see docs/internal/architecture.md §2.4 Settings) exists to make it
 *   configurable.
 */
class WorkflowExecutionService {

	private WorkflowService $workflows;

	private NodeTypeRegistry $registry;

	private NodeExecutionService $nodeExecutor;

	private WorkflowRunRepository $runs;

	private WorkflowRunLogRepository $runLogs;

	public function __construct(
		WorkflowService $workflows,
		NodeTypeRegistry $registry,
		NodeExecutionService $nodeExecutor,
		WorkflowRunRepository $runs,
		WorkflowRunLogRepository $runLogs
	) {
		$this->workflows = $workflows;
		$this->registry = $registry;
		$this->nodeExecutor = $nodeExecutor;
		$this->runs = $runs;
		$this->runLogs = $runLogs;
	}

	/**
	 * Runs a workflow. Usable both for a "run now"/test action and as the
	 * callback a live trigger binds to (see Integration\WorkflowTriggerBinder).
	 *
	 * Deliberately not restricted to active workflows: a manual "test this
	 * workflow" action is valid for a draft workflow too. It is
	 * WorkflowTriggerBinder's job to only bind triggers for active
	 * workflows, which is what gates the *automatic* path.
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

		/**
		 * Fires immediately before a workflow run starts.
		 *
		 * @since 0.1.0
		 *
		 * @param int                   $workflow_id     The workflow about to run.
		 * @param array<string, mixed>  $trigger_payload Data the triggering event provided; empty for a manual run.
		 */
		do_action( 'wfa/workflow/before_run', $workflow_id, $trigger_payload );

		$nodes = $this->workflows->syncNodesFromGraph( $workflow_id );

		$run = $this->runs->insert(
			array(
				'workflow_id' => $workflow_id,
				'status' => WorkflowRun::STATUS_RUNNING,
			)
		);

		if ( null === $run ) {
			throw new RuntimeException( esc_html__( 'Failed to start the workflow run.', 'workflow-automate' ) );
		}

		$context = array(
			'trigger' => $trigger_payload,
			'nodes' => array(),
		);

		$executed = 0;
		$succeeded = 0;

		foreach ( $nodes as $node ) {
			if ( null !== $this->registry->trigger( $node->nodeType() ) ) {
				continue;
			}

			++$executed;

			$started_at = microtime( true );
			$result = $this->nodeExecutor->execute( $node, $context );
			$duration_ms = (int) round( ( microtime( true ) - $started_at ) * 1000 );

			$success = ! empty( $result['success'] );

			if ( $success ) {
				++$succeeded;
			}

			$context['nodes'][ $node->clientNodeId() ] = $result;

			$this->runLogs->insert(
				array(
					'run_id' => $run->id(),
					'node_id' => $node->id(),
					'status' => $success ? WorkflowRunLog::STATUS_SUCCESS : WorkflowRunLog::STATUS_ERROR,
					'input' => $node->config(),
					'output' => $result,
					'message' => $success ? null : ( $result['error'] ?? __( 'The node failed without providing a specific error message.', 'workflow-automate' ) ),
					'duration_ms' => $duration_ms,
				)
			);

			if ( ! $success ) {
				break;
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
