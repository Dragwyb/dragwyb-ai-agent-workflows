<?php
/**
 * Anthropic Claude Messages API action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionSecretResolver;
use WorkflowAutomate\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls Anthropic's Messages API (`/v1/messages`).
 */
class ClaudeMessagesAction implements ActionInterface {

	private const API_URL = 'https://api.anthropic.com/v1/messages';

	private const DEFAULT_MODEL = 'claude-3-5-haiku-latest';

	private const API_VERSION = '2023-06-01';

	private const TIMEOUT_SECONDS = 60;

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections ) {
		$this->secrets = new ConnectionSecretResolver( $connections );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'claude_messages_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Anthropic Claude', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends a prompt to Anthropic Claude and returns the reply.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'Anthropic API key connection', 'workflow-automate' ),
				'required' => true,
				'default' => 0,
			),
			'model' => array(
				'type' => 'string',
				'label' => __( 'Model', 'workflow-automate' ),
				'default' => self::DEFAULT_MODEL,
			),
			'system_prompt' => array(
				'type' => 'string',
				'label' => __( 'System prompt (optional)', 'workflow-automate' ),
				'default' => '',
			),
			'prompt' => array(
				'type' => 'string',
				'label' => __( 'User prompt (supports {{trigger.fields.*}} tokens)', 'workflow-automate' ),
				'required' => true,
			),
			'max_tokens' => array(
				'type' => 'integer',
				'label' => __( 'Max tokens', 'workflow-automate' ),
				'default' => 1024,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		unset( $context );

		$api_key = $this->secrets->resolveBearerSecret( isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0 );

		if ( is_array( $api_key ) ) {
			return $api_key;
		}

		$prompt = isset( $config['prompt'] ) ? trim( (string) $config['prompt'] ) : '';

		if ( '' === $prompt ) {
			return array(
				'success' => false,
				'error' => __( 'No prompt configured.', 'workflow-automate' ),
			);
		}

		$model = isset( $config['model'] ) ? trim( (string) $config['model'] ) : self::DEFAULT_MODEL;

		if ( '' === $model ) {
			$model = self::DEFAULT_MODEL;
		}

		$max_tokens = isset( $config['max_tokens'] ) ? (int) $config['max_tokens'] : 1024;

		if ( $max_tokens < 1 ) {
			$max_tokens = 1024;
		}

		$payload = array(
			'model' => $model,
			'max_tokens' => $max_tokens,
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
		);

		$system_prompt = isset( $config['system_prompt'] ) ? trim( (string) $config['system_prompt'] ) : '';

		if ( '' !== $system_prompt ) {
			$payload['system'] = $system_prompt;
		}

		$body = wp_json_encode( $payload );

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error' => __( 'Failed to encode the Claude request body.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/json',
					'x-api-key' => $api_key,
					'anthropic-version' => self::API_VERSION,
				),
				'body' => $body,
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Claude' );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		$content = '';
		$decoded = $result['response'] ?? array();

		if ( is_array( $decoded ) && isset( $decoded['content'] ) && is_array( $decoded['content'] ) ) {
			foreach ( $decoded['content'] as $block ) {
				if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$content .= (string) $block['text'];
				}
			}
		}

		return array(
			'success' => true,
			'status_code' => $result['status_code'] ?? 200,
			'model' => $model,
			'content' => $content,
		);
	}
}
