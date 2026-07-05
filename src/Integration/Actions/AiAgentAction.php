<?php
/**
 * AI Agent — reasoning step with optional JSON output for attached tools.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified AI Agent node. Chat model is configured inline on the agent card.
 */
class AiAgentAction implements ActionInterface {

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	public function slug(): string {
		return 'ai_agent_action';
	}

	public function label(): string {
		return __( 'AI Agent', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Analyzes data with an AI. Attach Router or Condition tools below the agent.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'provider' => array(
				'type' => 'select',
				'label' => __( 'AI provider', 'workflow-automate' ),
				'help' => __( 'Choose OpenAI, Google Gemini, or Anthropic Claude — then add the matching API key below.', 'workflow-automate' ),
				'default' => 'openai',
				'options' => array(
					array( 'value' => 'openai', 'label' => 'OpenAI' ),
					array( 'value' => 'gemini', 'label' => 'Google Gemini' ),
					array( 'value' => 'claude', 'label' => 'Anthropic Claude' ),
				),
			),
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'API key connection', 'workflow-automate' ),
				'required' => true,
				'default' => 0,
			),
			'model' => array(
				'type' => 'dynamic_select',
				'label' => __( 'AI model', 'workflow-automate' ),
				'default' => 'gpt-4o-mini',
				'options_source' => 'ai_models',
				'connection_field' => 'connection_id',
			),
			'system_prompt' => array(
				'type' => 'string',
				'label' => __( 'Instructions for the AI', 'workflow-automate' ),
				'supports_variables' => true,
				'default' => __( 'You are a workflow assistant. Return only the requested output.', 'workflow-automate' ),
			),
			'prompt' => array(
				'type' => 'string',
				'label' => __( 'Message to send', 'workflow-automate' ),
				'supports_variables' => true,
				'required' => true,
			),
			'output_format' => array(
				'type' => 'select',
				'label' => __( 'Reply format', 'workflow-automate' ),
				'help' => __( 'Use JSON when a Router tool is attached to the agent.', 'workflow-automate' ),
				'default' => 'json',
				'options' => array(
					array( 'value' => 'text', 'label' => __( 'Plain text', 'workflow-automate' ) ),
					array( 'value' => 'json', 'label' => __( 'JSON (for Router)', 'workflow-automate' ) ),
				),
			),
			'memory_enabled' => array(
				'type' => 'boolean',
				'label' => __( 'Remember earlier messages in this run', 'workflow-automate' ),
				'default' => false,
			),
		);
	}

	public function execute( array $config, array $context ): array {
		$provider = isset( $config['provider'] ) ? strtolower( (string) $config['provider'] ) : 'openai';
		$slug_map = array(
			'openai' => 'openai_chat_action',
			'gemini' => 'gemini_generate_content_action',
			'claude' => 'claude_messages_action',
		);

		$delegate_slug = $slug_map[ $provider ] ?? 'openai_chat_action';
		$delegate      = $this->resolveDelegate( $delegate_slug );

		if ( null === $delegate ) {
			return array(
				'success' => false,
				'error' => __( 'The selected AI provider is not available.', 'workflow-automate' ),
			);
		}

		$delegate_config = array(
			'connection_id' => (int) ( $config['connection_id'] ?? 0 ),
			'model' => (string) ( $config['model'] ?? '' ),
			'system_prompt' => (string) ( $config['system_prompt'] ?? '' ),
			'prompt' => (string) ( $config['prompt'] ?? '' ),
		);

		$result = $delegate->execute( $delegate_config, $context );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		$content = isset( $result['content'] ) ? trim( (string) $result['content'] ) : '';

		if ( isset( $config['output_format'] ) && 'json' === $config['output_format'] ) {
			$parsed = json_decode( $content, true );

			if ( ! is_array( $parsed ) ) {
				return array(
					'success' => false,
					'error' => __( 'The agent did not return valid JSON.', 'workflow-automate' ),
					'content' => $content,
				);
			}

			$result['parsed'] = $parsed;
		}

		$result['response'] = $content;

		return $result;
	}

	/**
	 * @param string $slug Action slug.
	 *
	 * @return ActionInterface|null
	 */
	private function resolveDelegate( string $slug ): ?ActionInterface {
		if ( 'openai_chat_action' === $slug ) {
			return new OpenAiChatAction( $this->connections );
		}

		if ( 'gemini_generate_content_action' === $slug ) {
			return new GeminiGenerateContentAction( $this->connections );
		}

		if ( 'claude_messages_action' === $slug ) {
			return new ClaudeMessagesAction( $this->connections );
		}

		return null;
	}
}
