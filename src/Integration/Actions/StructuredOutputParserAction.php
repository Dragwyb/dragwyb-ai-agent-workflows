<?php
/**
 * Structured Output Parser — n8n-style JSON schema for AI Agent replies.
 *
 * @package DRAGAIW\Plugin
 */

declare(strict_types=1);

namespace DRAGAIW\Plugin\Integration\Actions;

use DRAGAIW\Plugin\Domain\Contracts\ActionInterface;
use DRAGAIW\Plugin\Service\Agent\AgentStructuredOutputParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attachment node used by AI Agent when "Require Specific Output Format" is on.
 * Optional chat model attachment powers Auto-Fix (same as n8n Model* port).
 */
class StructuredOutputParserAction implements ActionInterface {

	public function slug(): string {
		return 'agent_output_parser';
	}

	public function label(): string {
		return __( 'Structured Output Parser', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Define a JSON structure the AI Agent must return (from example or JSON Schema).', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'schema_type'            => array(
				'type'    => 'select',
				'label'   => __( 'Schema Type', 'dragwyb-ai-agent-workflows' ),
				'default' => 'from_json',
				'options' => array(
					array(
						'value' => 'from_json',
						'label' => __( 'Generate From JSON Example', 'dragwyb-ai-agent-workflows' ),
					),
					array(
						'value' => 'manual',
						'label' => __( 'Define using JSON Schema', 'dragwyb-ai-agent-workflows' ),
					),
				),
			),
			'json_example'           => array(
				'type'      => 'string',
				'label'     => __( 'JSON Example', 'dragwyb-ai-agent-workflows' ),
				'default'   => "{\n  \"state\": \"California\",\n  \"cities\": [\"Los Angeles\", \"San Francisco\", \"San Diego\"]\n}",
				'multiline' => true,
				'rows'      => 10,
				'help'      => __( 'All properties will be required. To make them optional, use the JSON Schema schema type instead.', 'dragwyb-ai-agent-workflows' ),
				'show_when' => array(
					array(
						'field'  => 'schema_type',
						'equals' => 'from_json',
					),
				),
			),
			'json_schema'            => array(
				'type'      => 'string',
				'label'     => __( 'Input Schema', 'dragwyb-ai-agent-workflows' ),
				'default'   => "{\n  \"type\": \"object\",\n  \"properties\": {\n    \"state\": {\n      \"type\": \"string\"\n    },\n    \"cities\": {\n      \"type\": \"array\",\n      \"items\": {\n        \"type\": \"string\"\n      }\n    }\n  }\n}",
				'multiline' => true,
				'rows'      => 12,
				'help'      => __( 'Use JSON Schema format. $refs syntax is not supported.', 'dragwyb-ai-agent-workflows' ),
				'show_when' => array(
					array(
						'field'  => 'schema_type',
						'equals' => 'manual',
					),
				),
			),
			'auto_fix'               => array(
				'type'    => 'boolean',
				'label'   => __( 'Auto-Fix Format', 'dragwyb-ai-agent-workflows' ),
				'default' => true,
				'help'    => __( 'If the reply does not match the schema, ask the connected Model to fix it once.', 'dragwyb-ai-agent-workflows' ),
			),
			'customize_retry_prompt' => array(
				'type'      => 'boolean',
				'label'     => __( 'Customize Retry Prompt', 'dragwyb-ai-agent-workflows' ),
				'default'   => false,
				'show_when' => array(
					array(
						'field'  => 'auto_fix',
						'equals' => true,
					),
				),
			),
			'retry_prompt'           => array(
				'type'      => 'string',
				'label'     => __( 'Retry Prompt', 'dragwyb-ai-agent-workflows' ),
				'default'   => '',
				'multiline' => true,
				'rows'      => 8,
				'help'      => __( 'Placeholders: {instructions}, {completion}, {error}', 'dragwyb-ai-agent-workflows' ),
				'show_when' => array(
					array(
						'field'  => 'auto_fix',
						'equals' => true,
					),
					array(
						'field'  => 'customize_retry_prompt',
						'equals' => true,
					),
				),
			),
		);
	}

	/**
	 * Test / dry-run: validates the configured schema. Runtime parsing happens
	 * inside the AI Agent; connect a Model below this node for Auto-Fix.
	 *
	 * @param array<string, mixed> $config  Parser config.
	 * @param array<string, mixed> $context Execution context.
	 *
	 * @return array<string, mixed>
	 */
	public function execute( array $config, array $context ): array {
		$parser   = new AgentStructuredOutputParser();
		$resolved = $parser->resolve(
			array(
				'config' => $config,
			)
		);

		if ( isset( $resolved['success'] ) && false === $resolved['success'] ) {
			return $resolved;
		}

		$graph_nodes = array();

		if ( isset( $context['graph']['nodes'] ) && is_array( $context['graph']['nodes'] ) ) {
			$graph_nodes = $context['graph']['nodes'];
		}

		$parser_id = isset( $context['current_node_id'] ) ? (string) $context['current_node_id'] : '';
		$has_model = false;

		if ( '' !== $parser_id ) {
			foreach ( $graph_nodes as $graph_node ) {
				if ( ! is_array( $graph_node ) ) {
					continue;
				}

				if ( (string) ( $graph_node['parent_agent_id'] ?? '' ) !== $parser_id ) {
					continue;
				}

				if ( 'parser_chat_model' === (string) ( $graph_node['attachment_type'] ?? '' ) ) {
					$has_model = true;
					break;
				}
			}
		}

		$auto_fix = ! empty( $resolved['auto_fix'] );

		return array(
			'success'        => true,
			'message'        => __( 'Structured Output Parser schema is valid. It runs with the AI Agent; Auto-Fix uses the Model connected under this node.', 'dragwyb-ai-agent-workflows' ),
			'schema'         => $resolved['schema'],
			'auto_fix'       => $auto_fix,
			'auto_fix_model' => $has_model,
			'needs_model'    => $auto_fix && ! $has_model,
			'output'         => $resolved['schema'],
			'json'           => is_array( $resolved['schema'] ) ? $resolved['schema'] : array( 'schema' => $resolved['schema'] ),
		);
	}
}
