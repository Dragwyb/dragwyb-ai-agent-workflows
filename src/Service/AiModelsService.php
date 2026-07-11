<?php
/**
 * Fetches available AI model lists from provider APIs.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

use WorkflowAutomate\Plugin\Integration\Actions\TelegramSendMessageAction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads chat-model options for OpenAI, Anthropic, and Gemini connections.
 */
class AiModelsService {

	private const TIMEOUT_SECONDS = 20;

	private const CACHE_TTL = 900;

	private const ANTHROPIC_API_VERSION = '2023-06-01';

	/**
	 * Node types that support dynamic model listing.
	 *
	 * @var array<string, true>
	 */
	private const SUPPORTED_NODE_TYPES = array(
		'openai_chat_action' => true,
		'claude_messages_action' => true,
		'gemini_generate_content_action' => true,
		'openrouter_chat_action' => true,
		'groq_chat_action' => true,
		'deepseek_chat_action' => true,
	);

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * @param int    $connection_id Connection id.
	 * @param string $node_type     Action node type slug.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	public function listForConnection( int $connection_id, string $node_type ): array {
		$node_type = sanitize_key( $node_type );

		if ( ! isset( self::SUPPORTED_NODE_TYPES[ $node_type ] ) ) {
			return array(
				'options' => array(),
				'error' => __( 'This node type does not support dynamic model listing.', 'workflow-automate' ),
			);
		}

		if ( $connection_id <= 0 ) {
			return array(
				'options' => array(),
				'error' => __( 'Select a connection first.', 'workflow-automate' ),
			);
		}

		$cache_key = 'wfa_ai_models_' . $connection_id . '_' . $node_type;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['options'] ) ) {
			return array(
				'options' => $cached['options'],
				'error' => $cached['error'] ?? null,
			);
		}

		$connection = $this->connections->find( $connection_id );

		if ( null === $connection ) {
			return array(
				'options' => array(),
				'error' => __( 'Connection not found.', 'workflow-automate' ),
			);
		}

		if ( ! $this->connectionMatchesNodeType( $connection->integrationSlug(), $node_type ) ) {
			return array(
				'options' => array(),
				'error' => __( 'This connection does not match the selected AI provider.', 'workflow-automate' ),
			);
		}

		$secret = $this->resolveSecret( $connection );

		if ( '' === $secret ) {
			return array(
				'options' => array(),
				'error' => __( 'Unable to read credentials for this connection.', 'workflow-automate' ),
			);
		}

		$result = $this->fetchModels( $node_type, $secret );

		set_transient(
			$cache_key,
			array(
				'options' => $result['options'],
				'error' => $result['error'],
			),
			self::CACHE_TTL
		);

		return $result;
	}

	/**
	 * @param string $integration_slug Connection integration slug.
	 * @param string $node_type        Node type slug.
	 *
	 * @return bool
	 */
	private function connectionMatchesNodeType( string $integration_slug, string $node_type ): bool {
		$slug = sanitize_key( $integration_slug );

		if ( $slug === $node_type ) {
			return true;
		}

		$aliases = array(
			'openai_chat_action' => array( 'openai', 'open_ai', 'chatgpt' ),
			'claude_messages_action' => array( 'claude', 'anthropic' ),
			'gemini_generate_content_action' => array( 'gemini', 'google_gemini', 'google_gemini_api', 'google_ai' ),
			'openrouter_chat_action' => array( 'openrouter', 'open_router' ),
			'groq_chat_action' => array( 'groq' ),
			'deepseek_chat_action' => array( 'deepseek', 'deep_seek' ),
		);

		if ( ! isset( $aliases[ $node_type ] ) ) {
			return false;
		}

		return in_array( $slug, $aliases[ $node_type ], true );
	}

	/**
	 * @param object $connection Connection entity.
	 *
	 * @return string
	 */
	private function resolveSecret( object $connection ): string {
		$credentials = $this->connections->credentials( $connection );

		if ( ConnectionAuthTypes::BEARER_TOKEN === $connection->authType() ) {
			return trim( (string) ( $credentials['token'] ?? '' ) );
		}

		return trim( (string) ( $credentials['api_key'] ?? '' ) );
	}

	/**
	 * @param string $node_type Node type slug.
	 * @param string $secret    API key or bearer token.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchModels( string $node_type, string $secret ): array {
		switch ( $node_type ) {
			case 'openai_chat_action':
				return $this->fetchOpenAiModels( $secret );

			case 'claude_messages_action':
				return $this->fetchClaudeModels( $secret );

			case 'gemini_generate_content_action':
				return $this->fetchGeminiModels( $secret );

			case 'openrouter_chat_action':
				return $this->fetchOpenRouterModels( $secret );

			case 'groq_chat_action':
				return $this->fetchGroqModels( $secret );

			case 'deepseek_chat_action':
				return $this->fetchDeepSeekModels( $secret );

			default:
				return array(
					'options' => array(),
					'error' => null,
				);
		}
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchOpenAiModels( string $api_key ): array {
		$response = wp_safe_remote_get(
			'https://api.openai.com/v1/models',
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'OpenAI' );

		if ( empty( $result['success'] ) ) {
			return array(
				'options' => array(),
				'error' => isset( $result['error'] ) ? (string) $result['error'] : __( 'Could not load OpenAI models.', 'workflow-automate' ),
			);
		}

		$decoded = $result['response'] ?? array();
		$options = array();

		if ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			foreach ( $decoded['data'] as $model ) {
				if ( ! is_array( $model ) || empty( $model['id'] ) ) {
					continue;
				}

				$id = (string) $model['id'];

				if ( ! $this->isOpenAiChatModel( $id ) ) {
					continue;
				}

				$options[] = array(
					'value' => $id,
					'label' => $id,
				);
			}
		}

		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return array(
			'options' => $options,
			'error' => array() === $options ? __( 'No chat models returned by OpenAI for this API key.', 'workflow-automate' ) : null,
		);
	}

	/**
	 * @param string $model_id OpenAI model id.
	 *
	 * @return bool
	 */
	private function isOpenAiChatModel( string $model_id ): bool {
		$lower = strtolower( $model_id );

		if ( preg_match( '/(embed|whisper|tts|dall-e|davinci|babbage|moderation|audio|transcribe|realtime|search|image)/', $lower ) ) {
			return false;
		}

		return (bool) preg_match( '/^(gpt-|o\d|chatgpt)/', $lower );
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchClaudeModels( string $api_key ): array {
		$response = wp_safe_remote_get(
			'https://api.anthropic.com/v1/models',
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'x-api-key' => $api_key,
					'anthropic-version' => self::ANTHROPIC_API_VERSION,
				),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Claude' );

		if ( empty( $result['success'] ) ) {
			return array(
				'options' => array(),
				'error' => isset( $result['error'] ) ? (string) $result['error'] : __( 'Could not load Claude models.', 'workflow-automate' ),
			);
		}

		$decoded = $result['response'] ?? array();
		$options = array();

		if ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			foreach ( $decoded['data'] as $model ) {
				if ( ! is_array( $model ) || empty( $model['id'] ) ) {
					continue;
				}

				$id    = (string) $model['id'];
				$label = isset( $model['display_name'] ) && '' !== trim( (string) $model['display_name'] )
					? (string) $model['display_name']
					: $id;

				$options[] = array(
					'value' => $id,
					'label' => $label,
				);
			}
		}

		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return array(
			'options' => $options,
			'error' => array() === $options ? __( 'No models returned by Anthropic for this API key.', 'workflow-automate' ) : null,
		);
	}

	/**
	 * @param string $api_key Gemini API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchGeminiModels( string $api_key ): array {
		$response = wp_safe_remote_get(
			sprintf(
				'https://generativelanguage.googleapis.com/v1beta/models?key=%s',
				rawurlencode( $api_key )
			),
			array(
				'timeout' => self::TIMEOUT_SECONDS,
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Gemini' );

		if ( empty( $result['success'] ) ) {
			return array(
				'options' => array(),
				'error' => isset( $result['error'] ) ? (string) $result['error'] : __( 'Could not load Gemini models.', 'workflow-automate' ),
			);
		}

		$decoded = $result['response'] ?? array();
		$options = array();

		if ( is_array( $decoded ) && isset( $decoded['models'] ) && is_array( $decoded['models'] ) ) {
			foreach ( $decoded['models'] as $model ) {
				if ( ! is_array( $model ) || empty( $model['name'] ) ) {
					continue;
				}

				$methods = $model['supportedGenerationMethods'] ?? array();

				if ( ! is_array( $methods ) || ! in_array( 'generateContent', $methods, true ) ) {
					continue;
				}

				$name  = (string) $model['name'];
				$value = preg_replace( '#^models/#', '', $name );
				$label = isset( $model['displayName'] ) && '' !== trim( (string) $model['displayName'] )
					? (string) $model['displayName']
					: (string) $value;

				$options[] = array(
					'value' => (string) $value,
					'label' => $label,
				);
			}
		}

		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return array(
			'options' => $options,
			'error' => array() === $options ? __( 'No generateContent models returned by Gemini for this API key.', 'workflow-automate' ) : null,
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchOpenRouterModels( string $api_key ): array {
		return $this->fetchOpenAiCompatibleModels(
			'https://openrouter.ai/api/v1/models',
			$api_key,
			'OpenRouter',
			__( 'Could not load OpenRouter models.', 'workflow-automate' ),
			__( 'No models returned by OpenRouter for this API key.', 'workflow-automate' ),
			static function ( string $id ): bool {
				return '' !== trim( $id );
			}
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchGroqModels( string $api_key ): array {
		return $this->fetchOpenAiCompatibleModels(
			'https://api.groq.com/openai/v1/models',
			$api_key,
			'Groq',
			__( 'Could not load Groq models.', 'workflow-automate' ),
			__( 'No models returned by Groq for this API key.', 'workflow-automate' ),
			static function ( string $id ): bool {
				$lower = strtolower( $id );

				return ! preg_match( '/(whisper|embed|tts|guard|vision-preview)/', $lower );
			}
		);
	}

	/**
	 * @param string $api_key API key.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchDeepSeekModels( string $api_key ): array {
		return $this->fetchOpenAiCompatibleModels(
			'https://api.deepseek.com/models',
			$api_key,
			'DeepSeek',
			__( 'Could not load DeepSeek models.', 'workflow-automate' ),
			__( 'No models returned by DeepSeek for this API key.', 'workflow-automate' ),
			static function ( string $id ): bool {
				return (bool) preg_match( '/^deepseek/i', $id );
			}
		);
	}

	/**
	 * @param string               $url             Models list URL.
	 * @param string               $api_key         API key.
	 * @param string               $provider_label  Provider name for errors.
	 * @param string               $fetch_error     Error when request fails.
	 * @param string               $empty_error     Error when no models match.
	 * @param callable(string):bool $include_model  Whether to include a model id.
	 *
	 * @return array{options: array<int, array{value: string, label: string}>, error: string|null}
	 */
	private function fetchOpenAiCompatibleModels(
		string $url,
		string $api_key,
		string $provider_label,
		string $fetch_error,
		string $empty_error,
		callable $include_model
	): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, $provider_label );

		if ( empty( $result['success'] ) ) {
			return array(
				'options' => array(),
				'error' => isset( $result['error'] ) ? (string) $result['error'] : $fetch_error,
			);
		}

		$decoded = $result['response'] ?? array();
		$options = array();

		if ( is_array( $decoded ) && isset( $decoded['data'] ) && is_array( $decoded['data'] ) ) {
			foreach ( $decoded['data'] as $model ) {
				if ( ! is_array( $model ) || empty( $model['id'] ) ) {
					continue;
				}

				$id = (string) $model['id'];

				if ( ! $include_model( $id ) ) {
					continue;
				}

				$label = isset( $model['name'] ) && '' !== trim( (string) $model['name'] )
					? (string) $model['name']
					: $id;

				$options[] = array(
					'value' => $id,
					'label' => $label,
				);
			}
		}

		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return array(
			'options' => $options,
			'error' => array() === $options ? $empty_error : null,
		);
	}
}
