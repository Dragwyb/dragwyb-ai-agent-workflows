<?php
/**
 * AI Agent — reasoning node with attached chat model, memory, and tools.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;
use AIAWA\Plugin\Service\Agent\AgentService;
use AIAWA\Plugin\Service\ConfigInterpolator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the agent tool-calling loop via {@see AgentService}.
 */
class AiAgentAction implements ActionInterface {

	private AgentService $agent;

	public function __construct( AgentService $agent ) {
		$this->agent = $agent;
	}

	public function slug(): string {
		return 'ai_agent_action';
	}

	public function label(): string {
		return __( 'AI Agent', 'dragwyb-agentflow' );
	}

	public function description(): string {
		return __( 'Reasons over workflow data and calls attached tools (email, HTTP, etc.).', 'dragwyb-agentflow' );
	}

	public function configSchema(): array {
		return array(
			'prompt_source'         => array(
				'type'    => 'select',
				'label'   => __( 'Source for Prompt (User Message)', 'dragwyb-agentflow' ),
				'default' => 'define_below',
				'options' => array(
					array(
						'value' => 'connected_chat_trigger',
						'label' => __( 'Connected Chat Trigger Node', 'dragwyb-agentflow' ),
					),
					array(
						'value' => 'define_below',
						'label' => __( 'Define below', 'dragwyb-agentflow' ),
					),
				),
			),
			'provider'              => array(
				'type'    => 'select',
				'label'   => __( 'AI provider (fallback when no Chat Model is attached)', 'dragwyb-agentflow' ),
				'default' => 'openai',
				'options' => array(
					array(
						'value' => 'openai',
						'label' => 'OpenAI',
					),
					array(
						'value' => 'gemini',
						'label' => 'Google Gemini',
					),
					array(
						'value' => 'claude',
						'label' => 'Anthropic Claude',
					),
					array(
						'value' => 'openrouter',
						'label' => 'OpenRouter',
					),
					array(
						'value' => 'groq',
						'label' => 'Groq',
					),
					array(
						'value' => 'deepseek',
						'label' => 'DeepSeek',
					),
				),
			),
			'api_credentials'       => array(
				'type'           => 'ai_credentials',
				'label'          => __( 'API key (fallback provider)', 'dragwyb-agentflow' ),
				'provider_field' => 'provider',
			),
			'model'                 => array(
				'type'           => 'dynamic_select',
				'label'          => __( 'AI model (fallback)', 'dragwyb-agentflow' ),
				'default'        => 'gpt-4o-mini',
				'options_source' => 'ai_models',
				'provider_field' => 'provider',
			),
			'system_prompt'         => array(
				'type'               => 'string',
				'label'              => __( 'Instructions for the AI', 'dragwyb-agentflow' ),
				'supports_variables' => true,
				'default'            => __( 'You are a workflow assistant. Use tools when needed to complete the task.', 'dragwyb-agentflow' ),
			),
			'prompt'                => array(
				'type'               => 'string',
				'label'              => __( 'Prompt (User Message)', 'dragwyb-agentflow' ),
				'supports_variables' => true,
				'required'           => true,
			),
			'require_output_format' => array(
				'type'    => 'boolean',
				'label'   => __( 'Require Specific Output Format', 'dragwyb-agentflow' ),
				'default' => false,
			),
			'clean_output'          => array(
				'type'    => 'boolean',
				'label'   => __( 'Clean output (strip markdown code fences)', 'dragwyb-agentflow' ),
				'default' => true,
				'help'    => __( 'When enabled, {{output}} is cleaned for HTTP Request and other nodes. Raw model text stays in {{response}}.', 'dragwyb-agentflow' ),
			),
			'fallback_enabled'      => array(
				'type'    => 'boolean',
				'label'   => __( 'Enable Fallback Model', 'dragwyb-agentflow' ),
				'default' => false,
			),
			'max_iterations'        => array(
				'type'    => 'integer',
				'label'   => __( 'Max tool iterations', 'dragwyb-agentflow' ),
				'default' => 5,
			),
			'output_format'         => array(
				'type'    => 'select',
				'label'   => __( 'Reply format', 'dragwyb-agentflow' ),
				'default' => 'text',
				'options' => array(
					array(
						'value' => 'text',
						'label' => __( 'Plain text', 'dragwyb-agentflow' ),
					),
					array(
						'value' => 'json',
						'label' => __( 'JSON', 'dragwyb-agentflow' ),
					),
				),
			),
			'options'               => array(
				'type'    => 'array',
				'label'   => __( 'Options', 'dragwyb-agentflow' ),
				'default' => array(),
			),
			'settings'              => array(
				'type'    => 'object',
				'label'   => __( 'Settings', 'dragwyb-agentflow' ),
				'default' => array(
					'always_output_data'    => false,
					'execute_once'          => false,
					'retry_on_fail'         => false,
					'max_tries'             => 3,
					'wait_between_tries_ms' => 1000,
					'on_error'              => 'stop_workflow',
					'notes'                 => '',
					'display_note_in_flow'  => false,
				),
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$agent_node_id = isset( $context['current_node_id'] ) ? (string) $context['current_node_id'] : '';

		if ( '' === $agent_node_id ) {
			return array(
				'success' => false,
				'error'   => __( 'AI Agent could not resolve its node id.', 'dragwyb-agentflow' ),
			);
		}

		$config = ( new ConfigInterpolator() )->interpolateConfig( $config, $context );

		$result = $this->agent->execute( $config, $context, $agent_node_id );

		return $result;
	}
}
