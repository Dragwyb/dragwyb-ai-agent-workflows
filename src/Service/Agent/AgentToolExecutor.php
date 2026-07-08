<?php
/**
 * Executes an AI Agent tool call against a workflow action node.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

use WorkflowAutomate\Plugin\Domain\WorkflowNode;
use WorkflowAutomate\Plugin\Service\ConfigInterpolator;
use WorkflowAutomate\Plugin\Service\NodeExecutionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Merges LLM tool arguments into a tool node config and runs the action.
 */
class AgentToolExecutor {

	private NodeExecutionService $node_executor;

	public function __construct( NodeExecutionService $node_executor ) {
		$this->node_executor = $node_executor;
	}

	/**
	 * @param string               $tool_name   LLM function name.
	 * @param array<string, mixed> $arguments   LLM-provided arguments.
	 * @param array<int, mixed>    $graph_nodes Workflow graph nodes.
	 * @param int                  $workflow_id Workflow id.
	 * @param array<string, mixed> $context     Execution context.
	 *
	 * @return array<string, mixed>
	 */
	public function execute(
		string $tool_name,
		array $arguments,
		array $graph_nodes,
		int $workflow_id,
		array $context
	): array {
		$parsed = AgentToolSchemaBuilder::parseToolName( $tool_name );

		if ( null === $parsed ) {
			$tool_node = AgentToolSchemaBuilder::findToolNodeByName( $graph_nodes, $tool_name );

			if ( null === $tool_node ) {
				return array(
					'error' => sprintf(
						/* translators: %s: tool function name */
						__( 'Unrecognized tool name "%s".', 'workflow-automate' ),
						$tool_name
					),
				);
			}
		} else {
			$tool_node = AgentGraphHelper::findNode( $graph_nodes, $parsed['id'] );

			if ( null === $tool_node || (string) ( $tool_node['type'] ?? '' ) !== $parsed['type'] ) {
				return array(
					'error' => __( 'Tool node not found for this agent.', 'workflow-automate' ),
				);
			}
		}

		$config = isset( $tool_node['config'] ) && is_array( $tool_node['config'] ) ? $tool_node['config'] : array();
		$config = $this->mergeToolArguments( $config, $arguments );
		$config = ( new ConfigInterpolator() )->interpolateConfig( $config, $context );

		$node = new WorkflowNode(
			0,
			$workflow_id,
			(string) $tool_node['id'],
			(string) $tool_node['type'],
			isset( $tool_node['label'] ) ? (string) $tool_node['label'] : null,
			$config,
			gmdate( 'Y-m-d H:i:s' ),
			gmdate( 'Y-m-d H:i:s' )
		);

		$result = $this->node_executor->execute( $node, $context );

		if ( empty( $result['success'] ) ) {
			return array(
				'error' => isset( $result['error'] ) ? (string) $result['error'] : __( 'The tool action failed.', 'workflow-automate' ),
			);
		}

		unset( $result['success'] );

		return $result;
	}

	/**
	 * @param array<string, mixed> $config    Saved node config.
	 * @param array<string, mixed> $arguments LLM arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function mergeToolArguments( array $config, array $arguments ): array {
		foreach ( $arguments as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( ! array_key_exists( $key, $config ) || $this->isEmptyConfigValue( $config[ $key ] ) ) {
				$config[ $key ] = $value;
			}
		}

		return $config;
	}

	/**
	 * @param mixed $value Config value.
	 *
	 * @return bool
	 */
	private function isEmptyConfigValue( $value ): bool {
		if ( null === $value ) {
			return true;
		}

		if ( is_string( $value ) ) {
			return '' === trim( $value );
		}

		if ( is_array( $value ) ) {
			return array() === $value;
		}

		return false;
	}
}
