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
	 * @param array<int, array<string, mixed>> $tool_nodes    Attached tool graph nodes.
	 * @param array<int, mixed>                $graph_nodes   Full workflow graph nodes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function buildSchemas( array $tool_nodes, array $graph_nodes = array() ): array {
		$schemas = array();

		foreach ( $tool_nodes as $tool_node ) {
			$schema = $this->buildSchemaForNode( $tool_node, $graph_nodes );

			if ( null !== $schema ) {
				$schemas[] = $schema;
			}
		}

		return $schemas;
	}

	/**
	 * @param array<string, mixed> $tool_node   Graph tool node.
	 * @param array<int, mixed>    $graph_nodes Full workflow graph nodes.
	 *
	 * @return array<string, mixed>|null
	 */
	private function buildSchemaForNode( array $tool_node, array $graph_nodes ): ?array {
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

		if ( 'condition_action' === $node_type ) {
			$description = $this->conditionToolDescription( $description, $config, $graph_nodes );
		} elseif ( 'router_action' === $node_type ) {
			$description = $this->routerToolDescription( $description, $config, $graph_nodes );
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
			$description = isset( $field_def['description'] ) && is_string( $field_def['description'] ) && '' !== trim( $field_def['description'] )
				? trim( $field_def['description'] )
				: $label;

			$properties[ $field_key ] = array(
				'type'        => 'object' === $field_type ? 'string' : $field_type,
				'description' => $description,
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
	 * @param string               $base        Action description.
	 * @param array<string, mixed> $config      Saved node config.
	 * @param array<int, mixed>    $graph_nodes Graph nodes.
	 *
	 * @return string
	 */
	private function conditionToolDescription( string $base, array $config, array $graph_nodes ): string {
		$parts   = array( $base );
		$parts[] = __( 'Pre-configured on this workflow node. Call with no arguments.', 'workflow-automate' );

		$field    = isset( $config['field'] ) ? trim( (string) $config['field'] ) : '';
		$operator = isset( $config['operator'] ) ? trim( (string) $config['operator'] ) : 'equals';
		$value    = isset( $config['value'] ) ? trim( (string) $config['value'] ) : '';

		if ( '' !== $field ) {
			$parts[] = sprintf(
				/* translators: 1: field path, 2: operator, 3: compare value */
				__( 'Rule: %1$s %2$s "%3$s".', 'workflow-automate' ),
				$field,
				$operator,
				$value
			);
		}

		$true_id  = isset( $config['true_branch_node_id'] ) ? trim( (string) $config['true_branch_node_id'] ) : '';
		$false_id = isset( $config['false_branch_node_id'] ) ? trim( (string) $config['false_branch_node_id'] ) : '';

		if ( '' !== $true_id ) {
			$parts[] = sprintf(
				/* translators: %s: tool function name and label */
				__( 'If yes, then call: %s.', 'workflow-automate' ),
				$this->branchToolRef( $graph_nodes, $true_id )
			);
		}

		if ( '' !== $false_id ) {
			$parts[] = sprintf(
				/* translators: %s: tool function name and label */
				__( 'If no, then call: %s.', 'workflow-automate' ),
				$this->branchToolRef( $graph_nodes, $false_id )
			);
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param string               $base        Action description.
	 * @param array<string, mixed> $config      Saved node config.
	 * @param array<int, mixed>    $graph_nodes Graph nodes.
	 *
	 * @return string
	 */
	private function routerToolDescription( string $base, array $config, array $graph_nodes ): string {
		$parts   = array( $base );
		$parts[] = __( 'Pre-configured on this workflow node. Call with no arguments.', 'workflow-automate' );

		$field = isset( $config['route_field'] ) ? trim( (string) $config['route_field'] ) : '';

		if ( '' !== $field ) {
			$parts[] = sprintf(
				/* translators: %s: field path */
				__( 'Route field: %s.', 'workflow-automate' ),
				$field
			);
		}

		$routes = isset( $config['routes'] ) && is_array( $config['routes'] ) ? $config['routes'] : array();

		foreach ( $routes as $route ) {
			if ( ! is_array( $route ) ) {
				continue;
			}

			$match   = isset( $route['match'] ) ? trim( (string) $route['match'] ) : '';
			$node_id = isset( $route['node_id'] ) ? trim( (string) $route['node_id'] ) : '';

			if ( '' === $match || '' === $node_id ) {
				continue;
			}

			$parts[] = sprintf(
				/* translators: 1: match value, 2: tool function name and label */
				__( 'When value is "%1$s", call: %2$s.', 'workflow-automate' ),
				$match,
				$this->branchToolRef( $graph_nodes, $node_id )
			);
		}

		$default_id = isset( $config['default_branch_node_id'] ) ? trim( (string) $config['default_branch_node_id'] ) : '';

		if ( '' !== $default_id ) {
			$parts[] = sprintf(
				/* translators: %s: tool function name and label */
				__( 'Otherwise call: %s.', 'workflow-automate' ),
				$this->branchToolRef( $graph_nodes, $default_id )
			);
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param array<int, mixed> $graph_nodes Graph nodes.
	 * @param string            $node_id     Branch target node id.
	 *
	 * @return string
	 */
	private function branchToolRef( array $graph_nodes, string $node_id ): string {
		$node = AgentGraphHelper::findNode( $graph_nodes, $node_id );

		if ( null === $node ) {
			return $node_id;
		}

		$type  = (string) ( $node['type'] ?? '' );
		$label = isset( $node['label'] ) ? (string) $node['label'] : $type;
		$name  = self::toolName( $type, $node_id );

		return $name . ' (' . $label . ')';
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
