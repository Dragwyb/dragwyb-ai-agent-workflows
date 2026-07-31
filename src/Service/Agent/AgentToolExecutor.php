<?php
/**
 * Executes an AI Agent tool call against a workflow action node.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service\Agent;

use AIAWAB\Plugin\Domain\WorkflowNode;
use AIAWAB\Plugin\Service\ConfigInterpolator;
use AIAWAB\Plugin\Service\NodeExecutionService;

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

		$config = isset( $tool_node['config'] ) && is_array( $tool_node['config'] ) ? $tool_node['config'] : array();
		$config = $this->mergeToolArguments( $config, $arguments, (string) $tool_node['type'] );
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
	 * Config fields that must never be overwritten by LLM tool arguments.
	 *
	 * @var array<string, true>
	 */
	private const RESTRICTED_KEYS = array(
		'connection_id'     => true,
		'allow_unsafe_urls' => true,
		'user_role'         => true,
		'password'          => true,
		'role_capabilities' => true,
		'role_name'         => true,
		'headers'           => true,
		'metadata'          => true,
	);

	/**
	 * Non-empty LLM arguments always win over saved node config defaults.
	 *
	 * @param array<string, mixed> $config    Saved node config.
	 * @param array<string, mixed> $arguments LLM arguments.
	 * @param string               $node_type Action slug (unused reserved for future schema lookup).
	 *
	 * @return array<string, mixed>
	 */
	private function mergeToolArguments( array $config, array $arguments, string $node_type ): array {
		unset( $node_type );

		foreach ( $arguments as $key => $value ) {
			if ( ! is_string( $key ) || isset( self::RESTRICTED_KEYS[ $key ] ) ) {
				continue;
			}

			$value = $this->normalizeArgumentValue( $value );

			if ( $this->isEmptyConfigValue( $value ) ) {
				continue;
			}

			$config[ $key ] = $value;
		}

		return $config;
	}

	/**
	 * Normalize LLM shapes: CSV strings → list, flat objects → key_value rows.
	 *
	 * @param mixed $value Raw LLM argument.
	 *
	 * @return mixed
	 */
	private function normalizeArgumentValue( $value ) {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			// JSON array/object encoded as a string.
			if ( ( str_starts_with( $trimmed, '[' ) && str_ends_with( $trimmed, ']' ) )
				|| ( str_starts_with( $trimmed, '{' ) && str_ends_with( $trimmed, '}' ) ) ) {
				$decoded = json_decode( $trimmed, true );
				if ( is_array( $decoded ) ) {
					return $this->normalizeArgumentValue( $decoded );
				}
			}

			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Flat associative map → key_value rows expected by WordPressServices::keyValue().
		if ( $this->isFlatAssociativeMap( $value ) ) {
			$rows = array();
			foreach ( $value as $map_key => $map_val ) {
				if ( ! is_string( $map_key ) && ! is_int( $map_key ) ) {
					continue;
				}
				$rows[] = array(
					'key'   => (string) $map_key,
					'value' => is_scalar( $map_val ) || null === $map_val ? (string) $map_val : wp_json_encode( $map_val ),
				);
			}

			return $rows;
		}

		return $value;
	}

	/**
	 * @param array<mixed> $value Candidate map.
	 */
	private function isFlatAssociativeMap( array $value ): bool {
		if ( array() === $value ) {
			return false;
		}

		// Already key_value rows.
		$first = reset( $value );
		if ( is_array( $first ) && ( array_key_exists( 'key', $first ) || array_key_exists( 'value', $first ) ) ) {
			return false;
		}

		// List of scalars (array field) — keep as-is.
		if ( $this->isListArray( $value ) ) {
			return false;
		}

		foreach ( $value as $v ) {
			if ( is_array( $v ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<mixed> $value Array to test.
	 */
	private function isListArray( array $value ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $value );
		}

		$expected = 0;
		foreach ( $value as $key => $_ ) {
			if ( $key !== $expected ) {
				return false;
			}
			++$expected;
		}

		return true;
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
