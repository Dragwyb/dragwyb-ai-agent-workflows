<?php
/**
 * Builds LLM tool schemas from attached workflow action nodes.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service\Agent;

use AIAWAB\Plugin\Domain\Contracts\ActionInterface;
use AIAWAB\Plugin\Service\NodeTypeRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts action config schemas into OpenAI-style function tool definitions.
 */
class AgentToolSchemaBuilder {

	private NodeTypeRegistry $registry;

	/**
	 * OpenAI/Anthropic/Gemini function-name max length.
	 */
	private const MAX_TOOL_NAME_LENGTH = 64;

	/**
	 * Config field types the agent may fill at runtime.
	 *
	 * @var array<string, true>
	 */
	private const AGENT_FILLABLE_TYPES = array(
		'string'    => true,
		'integer'   => true,
		'boolean'   => true,
		'number'    => true,
		'select'    => true,
		'object'    => true,
		'array'     => true,
		'key_value' => true,
	);

	/**
	 * Fields never exposed as LLM parameters.
	 *
	 * @var array<string, true>
	 */
	private const EXCLUDED_FIELDS = array(
		'connection_id'          => true,
		'model'                  => true,
		'true_branch_node_id'    => true,
		'false_branch_node_id'   => true,
		'conditions'             => true,
		'default_branch_node_id' => true,
		'routes'                 => true,
		'allow_unsafe_urls'      => true,
		'user_role'              => true,
		'password'               => true,
		'role_capabilities'      => true,
		'role_name'              => true,
		'headers'                => true,
		'metadata'               => true,
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

		$config      = isset( $tool_node['config'] ) && is_array( $tool_node['config'] ) ? $tool_node['config'] : array();
		$parameters  = $this->extractParameters( $action, $config );
		$tool_name   = self::toolName( $node_type, $node_id );
		$description = trim( $action->description() );

		if ( '' === $description ) {
			$description = $action->label();
		}

		$label = isset( $tool_node['label'] ) ? trim( (string) $tool_node['label'] ) : '';
		if ( '' !== $label && $label !== $action->label() ) {
			$description = $label . ' — ' . $description;
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
	 * @return array<string, mixed>
	 */
	private function extractParameters( ActionInterface $action, array $config ): array {
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

			if ( ! isset( self::AGENT_FILLABLE_TYPES[ $field_type ] ) ) {
				continue;
			}

			$label       = (string) ( $field_def['label'] ?? $field_key );
			$description = isset( $field_def['description'] ) && is_string( $field_def['description'] ) && '' !== trim( $field_def['description'] )
				? trim( $field_def['description'] )
				: $label;

			$description = $this->parameterDescription( $field_key, $field_type, $description, $config );

			$property = $this->buildPropertySchema( $field_type, $field_def, $description );

			$properties[ $field_key ] = $property;

			if ( ! empty( $field_def['required'] ) && $this->configValueIsEmpty( $config[ $field_key ] ?? null ) ) {
				$required[] = $field_key;
			}
		}

		if ( array() === $properties ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			);
		}

		$result = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);

		if ( array() !== $required ) {
			$result['required'] = $required;
		}

		return $result;
	}

	/**
	 * @param string               $field_type  Schema type.
	 * @param array<string, mixed> $field_def   Field definition.
	 * @param string               $description Parameter description.
	 *
	 * @return array<string, mixed>
	 */
	private function buildPropertySchema( string $field_type, array $field_def, string $description ): array {
		if ( 'array' === $field_type ) {
			return array(
				'type'        => 'array',
				'items'       => array( 'type' => 'string' ),
				'description' => $description,
			);
		}

		if ( 'key_value' === $field_type ) {
			return array(
				'type'                 => 'object',
				'additionalProperties' => array(
					'type' => array( 'string', 'number', 'boolean' ),
				),
				'description'          => $description,
			);
		}

		$property = array(
			'type'        => in_array( $field_type, array( 'object', 'select' ), true ) ? 'string' : $field_type,
			'description' => $description,
		);

		if ( 'select' === $field_type && ! empty( $field_def['options'] ) && is_array( $field_def['options'] ) ) {
			$enum = array();
			foreach ( $field_def['options'] as $option ) {
				if ( is_array( $option ) && array_key_exists( 'value', $option ) ) {
					$enum[] = (string) $option['value'];
				} elseif ( is_scalar( $option ) ) {
					$enum[] = (string) $option;
				}
			}
			$enum = array_values( array_filter( $enum, static fn( string $v ): bool => '' !== $v ) );
			if ( array() !== $enum ) {
				$property['enum'] = $enum;
			}
		}

		return $property;
	}

	/**
	 * @param string               $field_key   Config field key.
	 * @param string               $field_type  Field type.
	 * @param string               $description Base field description.
	 * @param array<string, mixed> $config      Saved node config.
	 *
	 * @return string
	 */
	private function parameterDescription( string $field_key, string $field_type, string $description, array $config ): string {
		$parts = array( $description );

		if ( in_array( $field_key, array( 'message', 'prompt', 'text', 'body', 'content' ), true ) ) {
			$parts[] = __( 'Provide the complete final text with actual values from the workflow data. Do not use {{placeholder}} templates.', 'workflow-automate' );
		}

		if ( 'post_type' === $field_key ) {
			$parts[] = __( 'Prefer the trigger post_type from workflow data (page vs post vs CPT) unless the user explicitly asks for a different type.', 'workflow-automate' );
		}

		if ( 'array' === $field_type ) {
			$parts[] = __( 'Pass a JSON array of strings (or a comma-separated string).', 'workflow-automate' );
		}

		if ( 'key_value' === $field_type ) {
			$parts[] = __( 'Pass a flat JSON object of key → value pairs, e.g. {"seo_title":"…","_custom":"…"}.', 'workflow-automate' );
		}

		if ( array_key_exists( $field_key, $config ) && ! $this->configValueIsEmpty( $config[ $field_key ] ) ) {
			$default = $config[ $field_key ];
			if ( is_scalar( $default ) ) {
				$parts[] = sprintf(
					/* translators: %s: current default value */
					__( 'Current node default: %s. You may override this value.', 'workflow-automate' ),
					(string) $default
				);
			}
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param mixed $value Config value.
	 */
	private function configValueIsEmpty( $value ): bool {
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
	 * Stable tool function name ≤ 64 chars (provider limit).
	 *
	 * @param string $node_type Node type slug.
	 * @param string $node_id   Client node id.
	 *
	 * @return string
	 */
	public static function toolName( string $node_type, string $node_id ): string {
		$hash      = substr( md5( $node_id ), 0, 8 );
		$separator = '__';
		$max_type  = self::MAX_TOOL_NAME_LENGTH - strlen( $separator ) - strlen( $hash );

		if ( $max_type < 8 ) {
			$max_type = 8;
		}

		$type = $node_type;
		if ( strlen( $type ) > $max_type ) {
			$type = substr( $type, 0, $max_type );
		}

		return $type . $separator . $hash;
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

			// Legacy long names (pre-hash) for in-flight runs.
			$legacy = (string) $graph_node['type'] . '__' . (string) $graph_node['id'];
			if ( $legacy === $tool_name ) {
				return $graph_node;
			}
		}

		return null;
	}
}
