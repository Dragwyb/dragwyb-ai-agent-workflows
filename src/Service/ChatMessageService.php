<?php
/**
 * Resolves chat-trigger workflows and extracts chat replies from runs.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Service;

use AIAWA\Plugin\Domain\Workflow;
use AIAWA\Plugin\Domain\WorkflowRun;
use AIAWA\Plugin\Domain\WorkflowRunLog;
use AIAWA\Plugin\Integration\Triggers\ChatMessageReceivedTrigger;
use AIAWA\Plugin\Persistence\WorkflowRunLogRepository;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Looks up active workflows by chat endpoint id and shapes chat API responses.
 */
class ChatMessageService {

	private const MAX_ACTIVE_WORKFLOWS = 100;

	private WorkflowService $workflows;

	private WorkflowRunLogRepository $run_logs;

	public function __construct( WorkflowService $workflows, WorkflowRunLogRepository $run_logs ) {
		$this->workflows = $workflows;
		$this->run_logs  = $run_logs;
	}

	/**
	 * @return array{workflow: Workflow, config: array<string, mixed>}|null
	 */
	public function findByEndpointId( string $endpoint_id ): ?array {
		$endpoint_id = trim( $endpoint_id );

		if ( '' === $endpoint_id ) {
			return null;
		}

		$active = $this->workflows->list(
			array(
				'status'   => Workflow::STATUS_ACTIVE,
				'per_page' => self::MAX_ACTIVE_WORKFLOWS,
			)
		);

		foreach ( $active['items'] as $workflow ) {
			$match = $this->matchWorkflowEndpoint( $workflow, $endpoint_id );

			if ( null !== $match ) {
				return $match;
			}
		}

		return null;
	}

	/**
	 * Includes draft/paused workflows (builder test / preview).
	 *
	 * @return array{workflow: Workflow, config: array<string, mixed>}|null
	 */
	public function findAnyByEndpointId( string $endpoint_id ): ?array {
		$endpoint_id = trim( $endpoint_id );

		if ( '' === $endpoint_id ) {
			return null;
		}

		$match = $this->findByEndpointId( $endpoint_id );

		if ( null !== $match ) {
			return $match;
		}

		$list = $this->workflows->list(
			array(
				'per_page' => self::MAX_ACTIVE_WORKFLOWS,
			)
		);

		foreach ( $list['items'] as $workflow ) {
			$found = $this->matchWorkflowEndpoint( $workflow, $endpoint_id );

			if ( null !== $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * @param Workflow $workflow    Workflow to inspect.
	 * @param string   $endpoint_id Chat endpoint UUID.
	 *
	 * @return array{workflow: Workflow, config: array<string, mixed>}|null
	 */
	private function matchWorkflowEndpoint( Workflow $workflow, string $endpoint_id ): ?array {
		$graph_nodes = $workflow->graph()['nodes'] ?? array();

		if ( ! is_array( $graph_nodes ) ) {
			return null;
		}

		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) ) {
				continue;
			}

			if ( ChatMessageReceivedTrigger::SLUG !== (string) ( $graph_node['type'] ?? '' ) ) {
				continue;
			}

			$config        = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
			$node_endpoint = isset( $config['endpoint_id'] ) ? trim( (string) $config['endpoint_id'] ) : '';

			if ( $node_endpoint === $endpoint_id ) {
				return array(
					'workflow' => $workflow,
					'config'   => $config,
				);
			}
		}

		return null;
	}

	/**
	 * Picks a human chat reply from the finished run's node logs
	 * (prefers AI Agent `response` / `output`, then last successful stringy output).
	 *
	 * @param WorkflowRun $run Completed run.
	 *
	 * @return string
	 */
	public function extractReply( WorkflowRun $run ): string {
		$logs = $this->run_logs->findByRun( $run->id() );

		for ( $i = count( $logs ) - 1; $i >= 0; $i-- ) {
			$log = $logs[ $i ];

			if ( ! $log instanceof WorkflowRunLog ) {
				continue;
			}

			if ( WorkflowRunLog::STATUS_SUCCESS !== $log->status() ) {
				continue;
			}

			$output = $log->output();

			if ( ! is_array( $output ) ) {
				continue;
			}

			foreach ( array( 'response', 'output', 'content', 'message', 'text' ) as $key ) {
				if ( isset( $output[ $key ] ) && is_scalar( $output[ $key ] ) ) {
					$text = trim( (string) $output[ $key ] );

					if ( '' !== $text ) {
						return $text;
					}
				}
			}

			if ( isset( $output['data'] ) && is_scalar( $output['data'] ) ) {
				$text = trim( (string) $output['data'] );

				if ( '' !== $text ) {
					return $text;
				}
			}
		}

		return '';
	}
}
