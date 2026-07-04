<?php
/**
 * Google Gemini generateContent action.
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
 * Calls Google Gemini `generateContent` with an API key connection.
 */
class GeminiGenerateContentAction implements ActionInterface {

	private const DEFAULT_MODEL = 'gemini-1.5-flash';

	private const TIMEOUT_SECONDS = 60;

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections ) {
		$this->secrets = new ConnectionSecretResolver( $connections );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'gemini_generate_content_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Google Gemini', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends a prompt to Google Gemini and returns the generated text.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'Gemini API key connection', 'workflow-automate' ),
				'required' => true,
				'default' => 0,
			),
			'model' => array(
				'type' => 'string',
				'label' => __( 'Model', 'workflow-automate' ),
				'default' => self::DEFAULT_MODEL,
			),
			'prompt' => array(
				'type' => 'string',
				'label' => __( 'Prompt (supports {{trigger.fields.*}} tokens)', 'workflow-automate' ),
				'required' => true,
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

		$url = sprintf(
			'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
			rawurlencode( $model ),
			rawurlencode( $api_key )
		);

		$body = wp_json_encode(
			array(
				'contents' => array(
					array(
						'parts' => array(
							array( 'text' => $prompt ),
						),
					),
				),
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error' => __( 'Failed to encode the Gemini request body.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body' => $body,
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Gemini' );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		$content = '';
		$decoded = $result['response'] ?? array();

		if ( is_array( $decoded ) && isset( $decoded['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$content = (string) $decoded['candidates'][0]['content']['parts'][0]['text'];
		}

		return array(
			'success' => true,
			'status_code' => $result['status_code'] ?? 200,
			'model' => $model,
			'content' => $content,
		);
	}
}
