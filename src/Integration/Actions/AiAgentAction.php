<?php
/**
 * AI Agent — reasoning node with attached chat model, memory, and tools.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\Agent\AgentService;
use WorkflowAutomate\Plugin\Service\ConfigInterpolator;

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
		return __( 'AI Agent', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Reasons over workflow data and calls attached tools (email, HTTP, etc.).', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'provider' => array(
				'type' => 'select',
				'label' => __( 'AI provider (fallback when no Chat Model is attached)', 'workflow-automate' ),
				'default' => 'openai',
				'options' => array(
					array( 'value' => 'openai', 'label' => 'OpenAI' ),
					array( 'value' => 'gemini', 'label' => 'Google Gemini' ),
					array( 'value' => 'claude', 'label' => 'Anthropic Claude' ),
				),
			),
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'API key connection (fallback)', 'workflow-automate' ),
				'default' => 0,
			),
			'model' => array(
				'type' => 'dynamic_select',
				'label' => __( 'AI model (fallback)', 'workflow-automate' ),
				'default' => 'gpt-4o-mini',
				'options_source' => 'ai_models',
				'connection_field' => 'connection_id',
			),
			'system_prompt' => array(
				'type' => 'string',
				'label' => __( 'Instructions for the AI', 'workflow-automate' ),
				'supports_variables' => true,
				'default' => __( 'You are a workflow assistant. Use tools when needed to complete the task.', 'workflow-automate' ),
			),
			'prompt' => array(
				'type' => 'string',
				'label' => __( 'Message to send', 'workflow-automate' ),
				'supports_variables' => true,
				'required' => true,
			),
			'max_iterations' => array(
				'type' => 'integer',
				'label' => __( 'Max tool iterations', 'workflow-automate' ),
				'default' => 5,
			),
			'output_format' => array(
				'type' => 'select',
				'label' => __( 'Reply format', 'workflow-automate' ),
				'default' => 'text',
				'options' => array(
					array( 'value' => 'text', 'label' => __( 'Plain text', 'workflow-automate' ) ),
					array( 'value' => 'json', 'label' => __( 'JSON', 'workflow-automate' ) ),
				),
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$agent_node_id = isset( $context['current_node_id'] ) ? (string) $context['current_node_id'] : '';

		if ( '' === $agent_node_id ) {
			return array(
				'success' => false,
				'error'   => __( 'AI Agent could not resolve its node id.', 'workflow-automate' ),
			);
		}

		$config = ( new ConfigInterpolator() )->interpolateConfig( $config, $context );

		$result = $this->agent->execute( $config, $context, $agent_node_id );

		if ( ! empty( $result['success'] ) ) {
			$result['success'] = true;
		}

		return $result;
	}
}
