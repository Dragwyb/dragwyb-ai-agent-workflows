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

	private const DEFAULT_MODEL = 'gemini-2.5-flash';

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
				'type' => 'dynamic_select',
				'label' => __( 'Model', 'workflow-automate' ),
				'default' => self::DEFAULT_MODEL,
				'options_source' => 'ai_models',
				'connection_field' => 'connection_id',
			),
			'prompt' => array(
				'type' => 'string',
				'label' => __( 'Prompt (supports {{trigger.fields.*}} tokens)', 'workflow-automate' ),
				'required' => true,
			),
			'system_prompt' => array(
				'type' => 'string',
				'label' => __( 'System prompt (optional)', 'workflow-automate' ),
				'default' => '',
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

		$request = array(
			'contents' => array(
				array(
					'parts' => array(
						array( 'text' => $prompt ),
					),
				),
			),
		);

		$thinking_config = $this->thinkingConfigForModel( $model );

		if ( null !== $thinking_config ) {
			$request['generationConfig'] = array(
				'thinkingConfig' => $thinking_config,
			);
		}

		$system_prompt = isset( $config['system_prompt'] ) ? trim( (string) $config['system_prompt'] ) : '';

		if ( '' !== $system_prompt ) {
			$request['systemInstruction'] = array(
				'parts' => array(
					array( 'text' => $system_prompt ),
				),
			);
		}

		$body = wp_json_encode( $request );

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

		$decoded = $result['response'] ?? array();
		$content = $this->extractGeminiText( is_array( $decoded ) ? $decoded : array() );

		return array(
			'success' => true,
			'status_code' => $result['status_code'] ?? 200,
			'model' => $model,
			'content' => $content,
		);
	}

	/**
	 * Returns user-facing text, skipping Gemini "thought" parts when present.
	 *
	 * @param array<string, mixed> $decoded Gemini API JSON body.
	 *
	 * @return string
	 */
	private function extractGeminiText( array $decoded ): string {
		if ( ! isset( $decoded['candidates'][0]['content']['parts'] ) || ! is_array( $decoded['candidates'][0]['content']['parts'] ) ) {
			return '';
		}

		$parts      = $decoded['candidates'][0]['content']['parts'];
		$text_parts = array();

		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) || ! isset( $part['text'] ) || ! is_string( $part['text'] ) ) {
				continue;
			}

			$text = trim( $part['text'] );

			if ( '' === $text ) {
				continue;
			}

			$text_parts[] = array(
				'text' => $text,
				'thought' => ! empty( $part['thought'] ),
			);
		}

		if ( array() === $text_parts ) {
			return '';
		}

		$non_thought = array_values(
			array_filter(
				$text_parts,
				static function ( array $entry ): bool {
					return ! $entry['thought'];
				}
			)
		);

		if ( 1 === count( $non_thought ) ) {
			return $non_thought[0]['text'];
		}

		if ( count( $non_thought ) > 1 ) {
			return $non_thought[ count( $non_thought ) - 1 ]['text'];
		}

		$thought_tokens = 0;

		if ( isset( $decoded['usageMetadata']['thoughtsTokenCount'] ) ) {
			$thought_tokens = (int) $decoded['usageMetadata']['thoughtsTokenCount'];
		}

		// Thinking models return reasoning first and the answer last.
		if ( count( $text_parts ) > 1 || $thought_tokens > 0 ) {
			return $text_parts[ count( $text_parts ) - 1 ]['text'];
		}

		return $text_parts[0]['text'];
	}

	/**
	 * Minimizes internal reasoning tokens so workflow output stays concise.
	 *
	 * @param string $model Gemini model id.
	 *
	 * @return array<string, int|string>|null
	 */
	private function thinkingConfigForModel( string $model ): ?array {
		if ( preg_match( '/gemini-3/i', $model ) ) {
			return array(
				'thinkingLevel' => 'minimal',
			);
		}

		if ( preg_match( '/gemini-2[.-]5/i', $model ) ) {
			return array(
				'thinkingBudget' => 0,
			);
		}

		return null;
	}
}
