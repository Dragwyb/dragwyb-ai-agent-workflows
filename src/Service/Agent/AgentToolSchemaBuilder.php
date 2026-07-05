<?php
/**
 * Builds LLM tool schemas from attached workflow action nodes.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts action config schemas into OpenAI-style function tool definitions.
 */
class AgentToolSchemaBuilder {

	private NodeTypeRegistry $registry;

	/**
	 * Config field types the agent may fill at runtime.
	 *
	 * @var array<string, true>
	 */
	private const AGENT_FILLABLE_TYPES = array(
		'string' => true,
		'integer' => true,
		'boolean' => true,
		'number' => true,
	);

	/**
	 * Fields never exposed as LLM parameters.
	 *
	 * @var array<string, true>
	 */
	private const EXCLUDED_FIELDS = array(
		'connection_id' => true,
		'model' => true,
		'default_branch_node_id' => true,
		'true_branch_node_id' => true,
		'false_branch_node_id' => true,
		'routes' => true,
	);

	public function __construct( NodeTypeRegistry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * @param array<int, array<string, mixed>> $tool_nodes Attached tool graph nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function buildSchemas( array $tool_nodes ): array {
		$schemas = array();

		foreach ( $tool_nodes as $tool_node ) {
			$schema = $this->buildSchemaForNode( $tool_node );

			if ( null !== $schema ) {
				$schemas[] = $schema;
			}
		}

		return $schemas;
	}

	/**
	 * @param array<string, mixed> $tool_node Graph tool node.
	 *
	 * @return array<string, mixed>|null
	 */
	private function buildSchemaForNode( array $tool_node ): ?array {
		$node_type = (string) ( $tool_node['type'] ?? '' );
		$node_id   = (string) ( $tool_node['id'] ?? '' );

		if ( '' === $node_type || '' === $node_id ) {
			return null;
		}

		$action = $this->registry->action( $node_type );

		if ( ! $action instanceof ActionInterface ) {
			return null;
		}

		$config        = isset( $tool_node['config'] ) && is_array( $tool_node['config'] ) ? $tool_node['config'] : array();
		$parameters    = $this->extractParameters( $action, $config );
		$tool_name     = self::toolName( $node_type, $node_id );
		$description   = trim( $action->description() );

		if ( '' === $description ) {
			$description = $action->label();
		}

		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => $tool_name,
				'description' => $description,
				'parameters'  => $parameters,
			),
		);
	}

	/**
	 * @param ActionInterface      $action Action metadata.
	 * @param array<string, mixed> $config Saved node config.
	 *
	 * @return array<string, mixed>|object
	 */
	private function extractParameters( ActionInterface $action, array $config ): array|object {
		$schema     = $action->configSchema();
		$properties = array();
		$required   = array();

		foreach ( $schema as $field_key => $field_def ) {
			if ( ! is_string( $field_key ) || ! is_array( $field_def ) ) {
				continue;
			}

			if ( isset( self::EXCLUDED_FIELDS[ $field_key ] ) ) {
				continue;
			}

			$field_type = (string) ( $field_def['type'] ?? 'string' );

			if ( ! isset( self::AGENT_FILLABLE_TYPES[ $field_type ] ) && 'object' !== $field_type ) {
				continue;
			}

			if ( ! $this->fieldIsAgentFillable( $field_key, $field_def, $config ) ) {
				continue;
			}

			$label = (string) ( $field_def['label'] ?? $field_key );

			$properties[ $field_key ] = array(
				'type'        => 'object' === $field_type ? 'string' : $field_type,
				'description' => $label,
			);

			if ( ! empty( $field_def['required'] ) ) {
				$required[] = $field_key;
			}
		}

		if ( array() === $properties ) {
			return array(
				'type'       => 'object',
				'properties' => array(),
			);
		}

		return array(
			'type'       => 'object',
			'properties' => $properties,
			'required'   => $required,
		);
	}

	/**
	 * @param string               $field_key Field key.
	 * @param array<string, mixed> $field_def Schema entry.
	 * @param array<string, mixed> $config    Saved config.
	 *
	 * @return bool
	 */
	private function fieldIsAgentFillable( string $field_key, array $field_def, array $config ): bool {
		if ( ! empty( $field_def['agent_fillable'] ) ) {
			return true;
		}

		if ( ! array_key_exists( $field_key, $config ) ) {
			return true;
		}

		$value = $config[ $field_key ];

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

	/**
	 * @param string $node_type Node type slug.
	 * @param string $node_id   Client node id.
	 *
	 * @return string
	 */
	public static function toolName( string $node_type, string $node_id ): string {
		return $node_type . '__' . $node_id;
	}

	/**
	 * @param string $tool_name Tool function name from the LLM.
	 *
	 * @return array{type: string, id: string}|null
	 */
	public static function parseToolName( string $tool_name ): ?array {
		$parts = explode( '__', $tool_name, 2 );

		if ( 2 !== count( $parts ) || '' === $parts[0] || '' === $parts[1] ) {
			return null;
		}

		return array(
			'type' => $parts[0],
			'id'   => $parts[1],
		);
	}

	/**
	 * @param array<int, mixed> $graph_nodes Graph nodes.
	 * @param string            $tool_name   Tool function name from the LLM.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function findToolNodeByName( array $graph_nodes, string $tool_name ): ?array {
		foreach ( $graph_nodes as $graph_node ) {
			if ( ! is_array( $graph_node ) || empty( $graph_node['id'] ) || empty( $graph_node['type'] ) ) {
				continue;
			}

			if ( self::toolName( (string) $graph_node['type'], (string) $graph_node['id'] ) === $tool_name ) {
				return $graph_node;
			}
		}

		return null;
	}
}
