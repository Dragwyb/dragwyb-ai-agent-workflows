<?php
/**
 * Chat Message Received trigger (n8n-style Chat Trigger).
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Triggers;

use AIAWA\Plugin\Domain\Contracts\TriggerGroupInterface;
use AIAWA\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when a chat message is posted to the workflow's chat
 * ingress URL. Payload mirrors n8n's Chat Trigger (`chatInput`, `sessionId`)
 * so AI Agent "Connected Chat Trigger Node" prompt source works out of the box.
 *
 * Fired via {@see do_action( 'aiawa_chat_message_received', $payload )} from
 * {@see \AIAWA\Plugin\Rest\ChatMessageIngressController}.
 */
class ChatMessageReceivedTrigger implements TriggerInterface, TriggerGroupInterface {

	public const HOOK = 'aiawa_chat_message_received';

	public const SLUG = 'chat_message_received_trigger';

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return self::SLUG;
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'When chat message received', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Runs the workflow when a chat message is submitted to this workflow\'s chat URL (same idea as n8n\'s Chat Trigger).', 'ai-agent-workflow-automation' );
	}

	public function app(): string {
		return 'chat';
	}

	public function group(): string {
		return 'chat';
	}

	public function groupLabel(): string {
		return __( 'Chat', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'endpoint_id'       => array(
				'type'        => 'string',
				'label'       => __( 'Chat endpoint ID', 'ai-agent-workflow-automation' ),
				'description' => __( 'Unguessable ID used in the public chat URL. Generated automatically when you add this trigger.', 'ai-agent-workflow-automation' ),
				'required'    => true,
				'default'     => '',
				'hidden'      => true,
			),
			'public'            => array(
				'type'        => 'boolean',
				'label'       => __( 'Make chat publicly available', 'ai-agent-workflow-automation' ),
				'description' => __( 'When off, only logged-in users with workflow access can post messages. When on, anyone with the URL can post (like n8n public chat).', 'ai-agent-workflow-automation' ),
				'default'     => false,
			),
			'title'             => array(
				'type'    => 'string',
				'label'   => __( 'Title', 'ai-agent-workflow-automation' ),
				'default' => __( 'Hi there! 👋', 'ai-agent-workflow-automation' ),
			),
			'subtitle'          => array(
				'type'    => 'string',
				'label'   => __( 'Subtitle', 'ai-agent-workflow-automation' ),
				'default' => __( 'Start a chat. We\'re here to help you 24/7.', 'ai-agent-workflow-automation' ),
			),
			'input_placeholder' => array(
				'type'    => 'string',
				'label'   => __( 'Input placeholder', 'ai-agent-workflow-automation' ),
				'default' => __( 'Type your question…', 'ai-agent-workflow-automation' ),
			),
			'initial_messages'  => array(
				'type'        => 'string',
				'label'       => __( 'Initial message(s)', 'ai-agent-workflow-automation' ),
				'description' => __( 'Default welcome messages shown at the start of the chat, one per line.', 'ai-agent-workflow-automation' ),
				'multiline'   => true,
				'default'     => __( "Hi there! 👋\nHow can I assist you today?", 'ai-agent-workflow-automation' ),
			),
			'response_mode'     => array(
				'type'    => 'select',
				'label'   => __( 'Response mode', 'ai-agent-workflow-automation' ),
				'default' => 'lastNode',
				'options' => array(
					array(
						'value' => 'lastNode',
						'label' => __( 'When last node finishes', 'ai-agent-workflow-automation' ),
					),
					array(
						'value' => 'immediate',
						'label' => __( 'Acknowledge immediately (queue run)', 'ai-agent-workflow-automation' ),
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		$endpoint_id = isset( $config['endpoint_id'] ) ? trim( (string) $config['endpoint_id'] ) : '';

		if ( '' === $endpoint_id ) {
			return;
		}

		add_action(
			self::HOOK,
			static function ( $payload ) use ( $on_fire, $config, $endpoint_id ): void {
				if ( ! is_array( $payload ) ) {
					return;
				}

				$incoming_id = isset( $payload['endpoint_id'] ) ? trim( (string) $payload['endpoint_id'] ) : '';

				if ( $incoming_id !== $endpoint_id ) {
					return;
				}

				$on_fire( self::normalizePayload( $payload ), $config );
			},
			10,
			1
		);
	}

	/**
	 * @param array<string, mixed> $payload Raw ingress payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function normalizePayload( array $payload ): array {
		$chat_input = '';

		foreach ( array( 'chatInput', 'chat_input', 'message', 'prompt', 'text' ) as $key ) {
			if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ) {
				$chat_input = trim( (string) $payload[ $key ] );

				if ( '' !== $chat_input ) {
					break;
				}
			}
		}

		$session_id = '';

		foreach ( array( 'sessionId', 'session_id' ) as $key ) {
			if ( isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ) {
				$session_id = trim( (string) $payload[ $key ] );

				if ( '' !== $session_id ) {
					break;
				}
			}
		}

		if ( '' === $session_id ) {
			$session_id = wp_generate_uuid4();
		}

		$normalized = array(
			'chatInput'   => $chat_input,
			'sessionId'   => $session_id,
			'endpoint_id' => isset( $payload['endpoint_id'] ) ? (string) $payload['endpoint_id'] : '',
			'action'      => isset( $payload['action'] ) ? (string) $payload['action'] : 'sendMessage',
		);

		if ( isset( $payload['metadata'] ) && is_array( $payload['metadata'] ) ) {
			$normalized['metadata'] = $payload['metadata'];
		}

		if ( isset( $payload['files'] ) && is_array( $payload['files'] ) ) {
			$normalized['files'] = $payload['files'];
		}

		return $normalized;
	}

	/**
	 * Sample payload for the builder / Test Flow field picker.
	 *
	 * @return array<string, mixed>
	 */
	public static function samplePayload(): array {
		return array(
			'chatInput'   => 'Hello! Can you help me?',
			'sessionId'   => 'sample-session-001',
			'endpoint_id' => '00000000-0000-4000-8000-000000000000',
			'action'      => 'sendMessage',
			'metadata'    => array(),
		);
	}
}
