<?php
/**
 * OpenAI Chat Completions action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionAuthTypes;
use WorkflowAutomate\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a chat completion request to OpenAI's public API.
 *
 * Credentials come from a Connections entry (API Key or Bearer Token auth
 * type). The prompt fields support `{{trigger.fields.*}}` tokens via
 * ConfigInterpolator.
 *
 * Independently designed against OpenAI's documented Chat Completions
 * HTTP API — not derived from any other plugin's integration code.
 */
class OpenAiChatAction implements ActionInterface {

	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	private const DEFAULT_MODEL = 'gpt-4o-mini';

	private const TIMEOUT_SECONDS = 60;

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'openai_chat_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'OpenAI Chat', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends a prompt to OpenAI Chat Completions and returns the reply.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'OpenAI API connection', 'workflow-automate' ),
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
				'label' => __( 'User prompt (supports {{trigger.fields.field_id}} tokens)', 'workflow-automate' ),
				'required' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		unset( $context );

		$connection_id = isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0;

		if ( $connection_id <= 0 ) {
			return array(
				'success' => false,
				'error' => __( 'No OpenAI connection configured. Create one under Connections (API Key).', 'workflow-automate' ),
			);
		}

		$api_key = $this->resolveApiKey( $connection_id );

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

		$messages = array();
		$system_prompt = isset( $config['system_prompt'] ) ? trim( (string) $config['system_prompt'] ) : '';

		if ( '' !== $system_prompt ) {
			$messages[] = array(
				'role' => 'system',
				'content' => $system_prompt,
			);
		}

		$messages[] = array(
			'role' => 'user',
			'content' => $prompt,
		);

		$body = wp_json_encode(
			array(
				'model' => $model,
				'messages' => $messages,
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error' => __( 'Failed to encode the OpenAI request body.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			self::API_URL,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error' => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = is_array( $decoded ) && isset( $decoded['error']['message'] )
				? (string) $decoded['error']['message']
				: self::truncate( $raw_body, 200 );

			return array(
				'success' => false,
				'error' => sprintf(
					/* translators: 1: HTTP status code, 2: error message */
					__( 'OpenAI returned HTTP %1$d: %2$s', 'workflow-automate' ),
					$status_code,
					$error_message
				),
				'status_code' => $status_code,
			);
		}

		$content = '';

		if ( is_array( $decoded ) && isset( $decoded['choices'][0]['message']['content'] ) ) {
			$content = (string) $decoded['choices'][0]['message']['content'];
		}

		return array(
			'success' => true,
			'status_code' => $status_code,
			'model' => $model,
			'content' => $content,
		);
	}

	/**
	 * @param int $connection_id Connection id.
	 *
	 * @return string|array{success: bool, error: string} API key string, or an error result array.
	 */
	private function resolveApiKey( int $connection_id ) {
		$connection = $this->connections->find( $connection_id );

		if ( null === $connection ) {
			return array(
				'success' => false,
				'error' => __( 'The connection configured for this action no longer exists.', 'workflow-automate' ),
			);
		}

		$credentials = $this->connections->credentials( $connection );

		switch ( $connection->authType() ) {
			case ConnectionAuthTypes::BEARER_TOKEN:
				$token = $credentials['token'] ?? null;

				if ( null === $token || '' === $token ) {
					return array(
						'success' => false,
						'error' => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' ),
					);
				}

				return (string) $token;

			case ConnectionAuthTypes::API_KEY:
			default:
				$api_key = $credentials['api_key'] ?? null;

				if ( null === $api_key || '' === $api_key ) {
					return array(
						'success' => false,
						'error' => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' ),
					);
				}

				return (string) $api_key;
		}
	}

	/**
	 * @param string $text   Raw text.
	 * @param int    $length Max length.
	 *
	 * @return string
	 */
	private static function truncate( string $text, int $length ): string {
		$text = trim( $text );

		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		return substr( $text, 0, $length ) . '…';
	}
}
