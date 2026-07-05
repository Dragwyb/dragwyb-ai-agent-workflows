<?php
/**
 * Executes a single builder node for "Test node" previews.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Domain\Workflow;
use WorkflowAutomate\Plugin\Domain\WorkflowNode;
use WorkflowAutomate\Plugin\Service\Agent\AgentGraphHelper;
use WorkflowAutomate\Plugin\Service\ConfigInterpolator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one node with the saved trigger sample and real outputs from prior steps.
 */
class WorkflowNodeTestService {

	private WorkflowService $workflows;

	private NodeExecutionService $executor;

	private NodeTypeRegistry $registry;

	private WorkflowTestListenerService $listener;

	public function __construct(
		WorkflowService $workflows,
		NodeExecutionService $executor,
		NodeTypeRegistry $registry,
		WorkflowTestListenerService $listener
	) {
		$this->workflows = $workflows;
		$this->executor  = $executor;
		$this->registry  = $registry;
		$this->listener  = $listener;
	}

	/**
	 * @param int                  $workflow_id   Workflow id.
	 * @param string               $client_node_id Builder node id from graph JSON.
	 * @param array<string, mixed> $graph         Optional unsaved graph override.
	 *
	 * @return array{success: bool, kind: string, input?: array<string, mixed>, output?: array<string, mixed>, error?: string}
	 */
	public function testNode( int $workflow_id, string $client_node_id, array $graph = array() ): array {
		$workflow = $this->workflows->find( $workflow_id );

		if ( null === $workflow ) {
			return array(
				'success' => false,
				'kind' => 'unknown',
				'error' => __( 'Workflow not found.', 'workflow-automate' ),
			);
		}

		if ( array() === $graph ) {
			$graph = $workflow->graph();
		}

		$graph_nodes = $graph['nodes'] ?? array();

		if ( ! is_array( $graph_nodes ) || array() === $graph_nodes ) {
			return array(
				'success' => false,
				'kind' => 'unknown',
				'error' => __( 'This workflow has no nodes to test.', 'workflow-automate' ),
			);
		}

		$sorted = $this->sortGraphNodes( $graph_nodes );
		$target = null;

		foreach ( $sorted as $graph_node ) {
			if ( is_array( $graph_node ) && isset( $graph_node['id'] ) && (string) $graph_node['id'] === $client_node_id ) {
				$target = $graph_node;
				break;
			}
		}

		if ( null === $target || empty( $target['type'] ) ) {
			return array(
				'success' => false,
				'kind' => 'unknown',
				'error' => __( 'Node not found in this workflow.', 'workflow-automate' ),
			);
		}

		$trigger_type    = $this->triggerTypeFromGraph( $sorted );
		$trigger_payload = $this->listener->samplePayloadForTrigger( $workflow_id, $trigger_type );

		if ( $this->isTriggerGraphNode( $target ) ) {
			if ( array() === $trigger_payload ) {
				return array(
					'success' => false,
					'kind' => 'trigger',
					'error' => __( 'No captured trigger data yet. Use Test Flow → Listen new response first.', 'workflow-automate' ),
				);
			}

			return array(
				'success' => true,
				'kind' => 'trigger',
				'input' => $this->buildTriggerTestInput( $target ),
				'output' => $this->buildTriggerTestOutput( $trigger_payload ),
			);
		}

		if ( array() === $trigger_payload ) {
			return array(
				'success' => false,
				'kind' => 'action',
				'error' => __( 'No captured trigger data yet. Listen for a trigger response before testing action nodes.', 'workflow-automate' ),
			);
		}

		$context = array(
			'trigger' => $trigger_payload,
			'nodes' => array(),
			'workflow_id' => $workflow_id,
			'graph' => $graph,
		);

		foreach ( $sorted as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) || empty( $graph_node['type'] ) ) {
				continue;
			}

			if ( (string) $graph_node['id'] === $client_node_id ) {
				$node_context = array_merge(
					$context,
					array( 'current_node_id' => (string) $graph_node['id'] )
				);
				$raw = $this->runActionNode( $workflow_id, $graph_node, $node_context );

				return $this->formatActionTestResponse( $graph_node, $node_context, $raw );
			}

			if ( $this->isTriggerGraphNode( $graph_node ) ) {
				continue;
			}

			if ( AgentGraphHelper::nodeIsAttachment( $graph_node ) ) {
				continue;
			}

			$prior_raw = $this->runActionNode( $workflow_id, $graph_node, $context );

			if ( empty( $prior_raw['success'] ) ) {
				$label = isset( $graph_node['label'] ) ? (string) $graph_node['label'] : (string) $graph_node['type'];

				return array(
					'success' => false,
					'kind' => 'action',
					'error' => sprintf(
						/* translators: %s: prior node label */
						__( 'A prior step failed (%s), so this node could not be tested.', 'workflow-automate' ),
						$label
					),
					'input' => $this->buildTestInput( $target, $context ),
					'output' => $this->buildTestOutput( $prior_raw ),
				);
			}

			$context['nodes'][ (string) $graph_node['id'] ] = $prior_raw;
		}

		return array(
			'success' => false,
			'kind' => 'unknown',
			'error' => __( 'Node not found in execution order.', 'workflow-automate' ),
		);
	}

	/**
	 * @param int                  $workflow_id
	 * @param array<string, mixed> $graph_node
	 * @param array<string, mixed> $context
	 *
	 * @return array<string, mixed>
	 */
	private function runActionNode( int $workflow_id, array $graph_node, array $context ): array {
		if ( null === $this->registry->action( (string) $graph_node['type'] ) ) {
			return array(
				'success' => false,
				'error' => __( 'This node type is not registered or cannot be executed.', 'workflow-automate' ),
			);
		}

		return $this->executor->execute(
			$this->workflowNodeFromGraph( $workflow_id, $graph_node ),
			$context
		);
	}

	/**
	 * @param array<string, mixed> $graph_node
	 * @param array<string, mixed> $context
	 * @param array<string, mixed> $result
	 *
	 * @return array{success: bool, kind: string, input?: array<string, mixed>, output?: array<string, mixed>, error?: string}
	 */
	private function formatActionTestResponse( array $graph_node, array $context, array $result ): array {
		$response = array(
			'success' => ! empty( $result['success'] ),
			'kind' => 'action',
			'input' => $this->buildTestInput( $graph_node, $context ),
			'output' => $this->buildTestOutput( $result ),
		);

		if ( empty( $result['success'] ) ) {
			$response['error'] = isset( $result['error'] ) && is_string( $result['error'] ) && '' !== $result['error']
				? $result['error']
				: __( 'The node failed without a specific error message.', 'workflow-automate' );
		}

		return $response;
	}

	/**
	 * @param array<int, mixed> $graph_nodes
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sortGraphNodes( array $graph_nodes ): array {
		$nodes = array_values(
			array_filter(
				$graph_nodes,
				static function ( $node ): bool {
					return is_array( $node );
				}
			)
		);

		usort(
			$nodes,
			static function ( array $a, array $b ): int {
				$y_a = isset( $a['y'] ) ? (int) $a['y'] : 0;
				$y_b = isset( $b['y'] ) ? (int) $b['y'] : 0;

				if ( $y_a !== $y_b ) {
					return $y_a <=> $y_b;
				}

				$x_a = isset( $a['x'] ) ? (int) $a['x'] : 0;
				$x_b = isset( $b['x'] ) ? (int) $b['x'] : 0;

				return $x_a <=> $x_b;
			}
		);

		return $nodes;
	}

	/**
	 * @param array<int, array<string, mixed>> $graph_nodes
	 *
	 * @return string|null
	 */
	private function triggerTypeFromGraph( array $graph_nodes ): ?string {
		foreach ( $graph_nodes as $graph_node ) {
			if ( $this->isTriggerGraphNode( $graph_node ) && ! empty( $graph_node['type'] ) ) {
				return (string) $graph_node['type'];
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $graph_node
	 *
	 * @return bool
	 */
	private function isTriggerGraphNode( array $graph_node ): bool {
		if ( isset( $graph_node['category'] ) && 'trigger' === $graph_node['category'] ) {
			return true;
		}

		return null !== $this->registry->trigger( (string) ( $graph_node['type'] ?? '' ) );
	}

	/**
	 * @param int                  $workflow_id
	 * @param array<string, mixed> $graph_node
	 *
	 * @return WorkflowNode
	 */
	private function workflowNodeFromGraph( int $workflow_id, array $graph_node ): WorkflowNode {
		$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
		$now    = gmdate( 'Y-m-d H:i:s' );

		return new WorkflowNode(
			0,
			$workflow_id,
			(string) $graph_node['id'],
			(string) $graph_node['type'],
			isset( $graph_node['label'] ) ? (string) $graph_node['label'] : null,
			$config,
			$now,
			$now
		);
	}

	/**
	 * Resolved node config sent to the action (secrets omitted).
	 *
	 * @param array<string, mixed> $graph_node
	 * @param array<string, mixed> $context
	 *
	 * @return array<string, mixed>
	 */
	private function buildTestInput( array $graph_node, array $context ): array {
		$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
		$resolved = ( new ConfigInterpolator() )->interpolateConfig( $config, $context );
		$hidden   = array( 'connection_id', 'webhook_url' );
		$input    = array();

		foreach ( $resolved as $key => $value ) {
			if ( ! is_string( $key ) || in_array( $key, $hidden, true ) ) {
				continue;
			}

			if ( is_string( $value ) && '' === trim( $value ) ) {
				continue;
			}

			if ( null === $value || '' === $value ) {
				continue;
			}

			$input[ $key ] = $value;
		}

		return $input;
	}

	/**
	 * @param array<string, mixed> $result Raw action result.
	 *
	 * @return array<string, mixed>
	 */
	private function buildTestOutput( array $result ): array {
		$output = array(
			'status' => ! empty( $result['success'] ) ? 'success' : 'failed',
		);

		if ( isset( $result['response'] ) && is_string( $result['response'] ) ) {
			$output['response'] = $result['response'];
		} elseif ( isset( $result['content'] ) && is_string( $result['content'] ) ) {
			$output['response'] = $result['content'];
		} elseif ( isset( $result['body'] ) && is_string( $result['body'] ) ) {
			$output['response'] = $result['body'];
		} elseif ( isset( $result['message'] ) && is_string( $result['message'] ) ) {
			$output['response'] = $result['message'];
		}

		if ( isset( $result['iterations'] ) ) {
			$output['iterations'] = $result['iterations'];
		}

		if ( isset( $result['finish_reason'] ) && is_string( $result['finish_reason'] ) ) {
			$output['finish_reason'] = $result['finish_reason'];
		}

		if ( isset( $result['tool_calls'] ) && is_array( $result['tool_calls'] ) ) {
			$output['tool_calls'] = $result['tool_calls'];
		}

		if ( isset( $result['provider'] ) && is_string( $result['provider'] ) ) {
			$output['provider'] = $result['provider'];
		}

		if ( isset( $result['model'] ) && is_string( $result['model'] ) && '' !== $result['model'] ) {
			$output['model'] = $result['model'];
		}

		if ( isset( $result['status_code'] ) ) {
			$output['status_code'] = $result['status_code'];
		}

		if ( empty( $result['success'] ) && isset( $result['error'] ) && is_string( $result['error'] ) ) {
			$output['error'] = $result['error'];
		}

		return $output;
	}

	/**
	 * @param array<string, mixed> $graph_node
	 *
	 * @return array<string, mixed>
	 */
	private function buildTriggerTestInput( array $graph_node ): array {
		$config = isset( $graph_node['config'] ) && is_array( $graph_node['config'] ) ? $graph_node['config'] : array();
		$input  = array();

		foreach ( $config as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			if ( is_string( $value ) && '' === trim( $value ) ) {
				continue;
			}

			if ( null === $value || '' === $value ) {
				continue;
			}

			$input[ $key ] = $value;
		}

		return $input;
	}

	/**
	 * @param array<string, mixed> $trigger_payload
	 *
	 * @return array<string, mixed>
	 */
	private function buildTriggerTestOutput( array $trigger_payload ): array {
		return array_merge(
			array( 'status' => 'success' ),
			$trigger_payload
		);
	}
}
