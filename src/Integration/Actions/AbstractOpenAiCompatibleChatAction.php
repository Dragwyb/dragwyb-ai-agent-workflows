<?php
/**
 * OpenAI-compatible Chat Completions API action base.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionAuthTypes;
use WorkflowAutomate\Plugin\Service\ConnectionService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared executor for providers that expose an OpenAI-style /chat/completions API.
 */
abstract class AbstractOpenAiCompatibleChatAction implements ActionInterface {

	private const TIMEOUT_SECONDS = 60;

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	abstract public function slug(): string;

	abstract public function label(): string;

	abstract public function description(): string;

	abstract protected function apiUrl(): string;

	abstract protected function defaultModel(): string;

	/**
	 * @return string Human-readable provider name for error messages.
	 */
	abstract protected function providerName(): string;

	/**
	 * @return string Connection field label.
	 */
	abstract protected function connectionLabel(): string;

	/**
	 * Optional extra HTTP headers (e.g. OpenRouter site attribution).
	 *
	 * @return array<string, string>
	 */
	protected function extraHeaders(): array {
		return array();
	}

	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type' => 'connection',
				'label' => $this->connectionLabel(),
				'required' => true,
				'default' => 0,
			),
			'model' => array(
				'type' => 'dynamic_select',
				'label' => __( 'Model', 'workflow-automate' ),
				'default' => $this->defaultModel(),
				'options_source' => 'ai_models',
				'connection_field' => 'connection_id',
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

	public function execute( array $config, array $context ): array {
		unset( $context );

		$connection_id = isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0;

		if ( $connection_id <= 0 ) {
			return array(
				'success' => false,
				'error' => sprintf(
					/* translators: %s: provider name */
					__( 'No %s connection configured. Create one under Connections (API Key).', 'workflow-automate' ),
					$this->providerName()
				),
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

		$model = isset( $config['model'] ) ? trim( (string) $config['model'] ) : $this->defaultModel();

		if ( '' === $model ) {
			$model = $this->defaultModel();
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
				'error' => sprintf(
					/* translators: %s: provider name */
					__( 'Failed to encode the %s request body.', 'workflow-automate' ),
					$this->providerName()
				),
			);
		}

		$headers = array_merge(
			array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			),
			$this->extraHeaders()
		);

		$response = wp_safe_remote_post(
			$this->apiUrl(),
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => $headers,
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
					/* translators: 1: provider name, 2: HTTP status code, 3: error message */
					__( '%1$s returned HTTP %2$d: %3$s', 'workflow-automate' ),
					$this->providerName(),
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
			'provider' => $this->slug(),
		);
	}

	/**
	 * @param int $connection_id Connection id.
	 *
	 * @return string|array{success: bool, error: string}
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
