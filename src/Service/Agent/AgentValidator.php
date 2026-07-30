<?php
/**
 * Validates AI Agent configuration before execution.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service\Agent;

use WorkflowAutomate\Plugin\Service\Ai\AiClientBootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared validation for AI Agent nodes.
 */
class AgentValidator {

	public const PROMPT_SOURCE_DEFINE = 'define_below';

	public const PROMPT_SOURCE_CHAT_TRIGGER = 'connected_chat_trigger';

	/**
	 * @param array<string, mixed> $config        Agent config (interpolated or raw).
	 * @param array<int, mixed>    $graph_nodes   Workflow graph nodes.
	 * @param string               $agent_node_id Agent client node id.
	 *
	 * @return array{success: bool, error?: string}
	 */
	public static function validate( array $config, array $graph_nodes, string $agent_node_id ): array {
		$attachments = AgentGraphHelper::resolveAttachments( $graph_nodes, $agent_node_id );

		if ( null === $attachments['chat_model'] ) {
			return array(
				'success' => false,
				'error'   => __( 'Connect a Chat Model to the AI Agent.', 'workflow-automate' ),
			);
		}

		$chat = AgentGraphHelper::resolveChatModelConfig( $attachments['chat_model'], $config );

		if ( ! AiClientBootstrap::isProviderConfigured( $chat['provider'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No API key configured for the chat model. Add an API key in the Chat Model node.', 'workflow-automate' ),
			);
		}

		$prompt_source = isset( $config['prompt_source'] ) ? (string) $config['prompt_source'] : self::PROMPT_SOURCE_DEFINE;

		if ( self::PROMPT_SOURCE_DEFINE === $prompt_source ) {
			$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

			if ( '' === $prompt ) {
				return array(
					'success' => false,
					'error'   => __( 'No prompt configured for the AI Agent.', 'workflow-automate' ),
				);
			}
		}

		if ( ! empty( $config['require_output_format'] ) && null === $attachments['output_parser'] ) {
			return array(
				'success' => false,
				'error'   => __( 'Connect an Output Parser on the canvas when output format is required.', 'workflow-automate' ),
			);
		}

		if ( ! empty( $config['fallback_enabled'] ) ) {
			if ( null === $attachments['fallback_chat_model'] ) {
				return array(
					'success' => false,
					'error'   => __( 'Connect a Fallback Chat Model when fallback is enabled.', 'workflow-automate' ),
				);
			}

			$fallback = AgentGraphHelper::resolveChatModelConfig( $attachments['fallback_chat_model'], $config );

			if ( ! AiClientBootstrap::isProviderConfigured( $fallback['provider'] ) ) {
				return array(
					'success' => false,
					'error'   => __( 'The fallback chat model needs a configured AI connector.', 'workflow-automate' ),
				);
			}
		}

		return array( 'success' => true );
	}
}
