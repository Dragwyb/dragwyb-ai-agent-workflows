<?php
/**
 * Live credential verification for stored connections.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Integration\Actions\TelegramSendMessageAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls each integration's real API with the submitted credentials before a
 * connection is saved. Unknown or generic integrations (e.g. HTTP Request)
 * are skipped — those stay `pending` because there is no single endpoint to
 * probe without extra configuration.
 */
class ConnectionVerifier {

	private const TIMEOUT_SECONDS = 15;

	private const ANTHROPIC_API_VERSION = '2023-06-01';

	/**
	 * Built-in action slugs that have a lightweight auth-check endpoint.
	 *
	 * @var string[]
	 */
	private const VERIFIABLE = array(
		'openai_chat_action',
		'claude_messages_action',
		'gemini_generate_content_action',
		'openrouter_chat_action',
		'groq_chat_action',
		'deepseek_chat_action',
		'telegram_send_message_action',
		'whatsapp_cloud_send_message_action',
		'google_sheets_append_row_action',
	);

	/**
	 * Common admin-entered aliases mapped to a built-in action slug.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = array(
		'openai' => 'openai_chat_action',
		'openai_chat' => 'openai_chat_action',
		'chatgpt' => 'openai_chat_action',
		'claude' => 'claude_messages_action',
		'anthropic' => 'claude_messages_action',
		'gemini' => 'gemini_generate_content_action',
		'google_gemini' => 'gemini_generate_content_action',
		'openrouter' => 'openrouter_chat_action',
		'open_router' => 'openrouter_chat_action',
		'groq' => 'groq_chat_action',
		'deepseek' => 'deepseek_chat_action',
		'deep_seek' => 'deepseek_chat_action',
		'telegram' => 'telegram_send_message_action',
		'whatsapp' => 'whatsapp_cloud_send_message_action',
		'whatsapp_cloud' => 'whatsapp_cloud_send_message_action',
		'google_sheets' => 'google_sheets_append_row_action',
		'sheets' => 'google_sheets_append_row_action',
	);

	/**
	 * Whether this integration slug can be checked against a real API.
	 *
	 * @param string $integration_slug Integration slug from the create form.
	 *
	 * @return bool
	 */
	public function isVerifiable( string $integration_slug ): bool {
		return null !== $this->resolveTarget( $integration_slug );
	}

	/**
	 * Probes the integration API with the given plaintext credentials.
	 *
	 * @param string               $integration_slug Integration slug.
	 * @param string               $auth_type        One of ConnectionAuthTypes::VALID.
	 * @param array<string,string> $field_values     Plaintext credential fields.
	 *
	 * @return array{success: bool, skipped?: bool, error?: string}
	 */
	public function verify( string $integration_slug, string $auth_type, array $field_values ): array {
		$target = $this->resolveTarget( $integration_slug );

		if ( null === $target ) {
			return array(
				'success' => true,
				'skipped' => true,
			);
		}

		$secret = $this->resolveSecret( $auth_type, $field_values );

		if ( '' === $secret ) {
			if ( ConnectionAuthTypes::OAUTH2 === $auth_type ) {
				return array(
					'success' => true,
					'skipped' => true,
				);
			}

			return array(
				'success' => false,
				'error' => __( 'Credentials are incomplete — cannot verify this connection.', 'workflow-automate' ),
			);
		}

		switch ( $target ) {
			case 'openai_chat_action':
				return $this->verifyOpenAi( $secret );

			case 'claude_messages_action':
				return $this->verifyClaude( $secret );

			case 'gemini_generate_content_action':
				return $this->verifyGemini( $secret );

			case 'openrouter_chat_action':
				return $this->verifyOpenRouter( $secret );

			case 'groq_chat_action':
				return $this->verifyGroq( $secret );

			case 'deepseek_chat_action':
				return $this->verifyDeepSeek( $secret );

			case 'telegram_send_message_action':
				return $this->verifyTelegram( $secret );

			case 'whatsapp_cloud_send_message_action':
				return $this->verifyWhatsApp( $secret );

			case 'google_sheets_append_row_action':
				return $this->verifyGoogleAccessToken( $secret );

			default:
				return array(
					'success' => true,
					'skipped' => true,
				);
		}
	}

	/**
	 * @param string $integration_slug Raw integration slug.
	 *
	 * @return string|null Canonical built-in action slug, or null when unknown.
	 */
	private function resolveTarget( string $integration_slug ): ?string {
		$slug = sanitize_key( $integration_slug );

		if ( isset( self::ALIASES[ $slug ] ) ) {
			$slug = self::ALIASES[ $slug ];
		}

		if ( in_array( $slug, self::VERIFIABLE, true ) ) {
			return $slug;
		}

		return null;
	}

	/**
	 * @param string               $auth_type    Auth type.
	 * @param array<string,string> $field_values Plaintext fields.
	 *
	 * @return string
	 */
	private function resolveSecret( string $auth_type, array $field_values ): string {
		switch ( $auth_type ) {
			case ConnectionAuthTypes::BEARER_TOKEN:
				return trim( (string) ( $field_values['token'] ?? '' ) );

			case ConnectionAuthTypes::OAUTH2:
				return trim( (string) ( $field_values['access_token'] ?? '' ) );

			case ConnectionAuthTypes::BASIC:
				return '';

			case ConnectionAuthTypes::API_KEY:
			default:
				return trim( (string) ( $field_values['api_key'] ?? '' ) );
		}
	}

	/**
	 * @param string $api_key API key or bearer token.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyOpenAi( string $api_key ): array {
		return $this->remoteGet(
			'https://api.openai.com/v1/models',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'OpenAI'
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyOpenRouter( string $api_key ): array {
		return $this->remoteGet(
			'https://openrouter.ai/api/v1/models',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'OpenRouter'
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyGroq( string $api_key ): array {
		return $this->remoteGet(
			'https://api.groq.com/openai/v1/models',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'Groq'
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyDeepSeek( string $api_key ): array {
		return $this->remoteGet(
			'https://api.deepseek.com/models',
			array(
				'Authorization' => 'Bearer ' . $api_key,
			),
			'DeepSeek'
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyClaude( string $api_key ): array {
		return $this->remoteGet(
			'https://api.anthropic.com/v1/models',
			array(
				'x-api-key' => $api_key,
				'anthropic-version' => self::ANTHROPIC_API_VERSION,
			),
			'Claude'
		);
	}

	/**
	 * @param string $api_key Gemini API key.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyGemini( string $api_key ): array {
		return $this->remoteGet(
			sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models?key=%s',
				rawurlencode( $api_key )
			),
			array(),
			'Gemini'
		);
	}

	/**
	 * @param string $token Bot token.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyTelegram( string $token ): array {
		return $this->remoteGet(
			sprintf( 'https://api.telegram.org/bot%s/getMe', rawurlencode( $token ) ),
			array(),
			'Telegram'
		);
	}

	/**
	 * @param string $token Permanent access token.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyWhatsApp( string $token ): array {
		return $this->remoteGet(
			sprintf(
				'https://graph.facebook.com/v19.0/me?fields=id&access_token=%s',
				rawurlencode( $token )
			),
			array(),
			'WhatsApp'
		);
	}

	/**
	 * @param string $token OAuth access token.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function verifyGoogleAccessToken( string $token ): array {
		return $this->remoteGet(
			sprintf(
				'https://oauth2.googleapis.com/tokeninfo?access_token=%s',
				rawurlencode( $token )
			),
			array(),
			'Google'
		);
	}

	/**
	 * @param string               $url     Request URL.
	 * @param array<string,string> $headers Request headers.
	 * @param string               $service Service label for errors.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function remoteGet( string $url, array $headers, string $service ): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => $headers,
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, $service );

		if ( ! empty( $result['success'] ) ) {
			return array( 'success' => true );
		}

		return array(
			'success' => false,
			'error' => isset( $result['error'] )
				? (string) $result['error']
				: sprintf(
					/* translators: %s: third-party service name */
					__( 'Could not verify credentials with %s.', 'workflow-automate' ),
					$service
				),
		);
	}
}
