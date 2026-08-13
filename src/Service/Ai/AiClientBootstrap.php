<?php
/**
 * Boots WordPress AI Client and registers providers.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Service\Ai;

use DragwybVisualAutomation\AiProviders\DeepSeek\DeepSeekProvider;
use DragwybVisualAutomation\AiProviders\Groq\GroqProvider;
use DragwybVisualAutomation\AiProviders\OpenRouter\OpenRouterProvider;
use DragwybVisualAutomation\Plugin\Service\ConnectionService;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AnthropicAiProvider\Provider\AnthropicProvider;
use WordPress\GoogleAiProvider\Provider\GoogleProvider;
use WordPress\OpenAiAiProvider\Provider\OpenAiProvider;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dual-stack bootstrap: vendor SDK below WP 7, core Connectors on WP 7+.
 */
class AiClientBootstrap {

	public const MIGRATION_OPTION = 'daiaw_ai_credentials_migrated_to_wp70';

	/**
	 * Prefixed storage for provider API keys (vendor SDK path, below WP 7).
	 */
	public const CREDENTIALS_OPTION = 'daiaw_ai_provider_credentials';

	/**
	 * Legacy option written before prefix hardening (do not write; migrate only).
	 */
	private const LEGACY_CREDENTIALS_OPTION = 'wp_ai_client_provider_credentials';

	/**
	 * Provider id map: daiaw slug → AiClient / Connectors id.
	 *
	 * @var array<string, string>
	 */
	public const PROVIDER_IDS = array(
		'openai'     => 'openai',
		'claude'     => 'anthropic',
		'anthropic'  => 'anthropic',
		'gemini'     => 'google',
		'google'     => 'google',
		'openrouter' => 'openrouter',
		'groq'       => 'groq',
		'deepseek'   => 'deepseek',
	);

	/**
	 * Integration slugs on daiaw_connections that hold AI API keys.
	 *
	 * @var array<string, string>
	 */
	private const CONNECTION_SLUG_TO_PROVIDER = array(
		'openai'                         => 'openai',
		'openai_chat_action'             => 'openai',
		'anthropic'                      => 'anthropic',
		'claude'                         => 'anthropic',
		'claude_messages_action'         => 'anthropic',
		'google'                         => 'google',
		'gemini'                         => 'google',
		'gemini_generate_content_action' => 'google',
		'openrouter'                     => 'openrouter',
		'openrouter_chat_action'         => 'openrouter',
		'groq'                           => 'groq',
		'groq_chat_action'               => 'groq',
		'deepseek'                       => 'deepseek',
		'deepseek_chat_action'           => 'deepseek',
	);

	private static bool $booted = false;

	/**
	 * Register hooks. Call once from Plugin::load().
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'boot' ), 5 );
	}

	/**
	 * Load SDK (if needed), register providers, migrate credentials.
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		// Composer may load the wp_ai_client_prompt() polyfill on older WP.
		// Use the official version gate, not function_exists().
		$is_wp70 = function_exists( 'wp_has_ai_client' )
			? wp_has_ai_client()
			: version_compare( daiaw_wp_version(), '7.0-alpha', '>=' );

		if ( ! $is_wp70 ) {
			$sdk_autoload = DAIAW_PLUGIN_DIR . 'vendor/wordpress/wp-ai-client/autoload.php';
			if ( file_exists( $sdk_autoload ) ) {
				require_once $sdk_autoload;
			}
			if ( ! class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
				return;
			}
		}

		$providers_autoload = DAIAW_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php';
		if ( file_exists( $providers_autoload ) ) {
			require_once $providers_autoload;
		}

		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		self::registerProviders();

		if ( $is_wp70 ) {
			self::migrateCredentialsToConnectors();
			// After Connectors core pass (init:20), re-apply our stored keys so
			// custom providers (OpenRouter/Groq/DeepSeek) always get Authorization.
			add_action( 'init', array( self::class, 'applyStoredCredentials' ), 25 );
		} else {
			\WordPress\AI_Client\AI_Client::init();
			try {
				$http_transporter = \WordPress\AiClient\Providers\Http\HttpTransporterFactory::createTransporter();
				AiClient::defaultRegistry()->setHttpTransporter( $http_transporter );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Transporter may already be set by AI_Client::init().
			}
			self::migrateCredentialsToLegacyOption();
			self::applyStoredCredentials();
		}
	}

	/**
	 * Whether the AI client stack is available.
	 */
	public static function isAvailable(): bool {
		self::boot();

		return class_exists( AiClient::class ) && function_exists( 'wp_ai_client_prompt' );
	}

	/**
	 * Creates a prompt builder when the WP AI Client API is available.
	 *
	 * @param array<int, mixed> $messages AI Client messages.
	 *
	 * @return object|null
	 */
	public static function createPromptBuilder( array $messages ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return null;
		}

		// phpcs:ignore PluginCheck.WPCompatibility.FunctionAvailability -- only called after isAvailable() succeeds; polyfilled on older core via vendored SDK.
		return function_exists( 'wp_ai_client_prompt' ) ? wp_ai_client_prompt( $messages ) : null;
	}

	/**
	 * Map daiaw provider slug to AiClient provider id.
	 */
	public static function resolveProviderId( string $daiaw_provider ): string {
		$key = strtolower( trim( $daiaw_provider ) );

		return self::PROVIDER_IDS[ $key ] ?? $key;
	}

	/**
	 * Admin URL for managing AI credentials.
	 */
	public static function credentialsUrl(): string {
		if ( function_exists( '_wp_register_default_connector_settings' ) ) {
			return admin_url( 'options-connectors.php' );
		}

		return admin_url( 'options-general.php?page=wp-ai-client-api-credentials' );
	}

	/**
	 * Whether a provider has a configured API key.
	 */
	public static function isProviderConfigured( string $daiaw_provider ): bool {
		if ( ! self::isAvailable() ) {
			return false;
		}

		if ( ! self::hasStoredProviderApiKey( $daiaw_provider ) ) {
			return false;
		}

		self::ensureProviderAuthentication( $daiaw_provider );

		$provider_id = self::resolveProviderId( $daiaw_provider );

		try {
			return AiClient::defaultRegistry()->isProviderConfigured( $provider_id );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Whether the current WordPress environment uses core Connectors storage.
	 */
	public static function usesCoreConnectors(): bool {
		if ( function_exists( 'wp_has_ai_client' ) ) {
			return wp_has_ai_client();
		}

		return version_compare( daiaw_wp_version(), '7.0-alpha', '>=' );
	}

	/**
	 * Option name for a provider API key on WP 7+ Connectors.
	 */
	public static function connectorsOptionName( string $provider_id ): string {
		return 'connectors_ai_' . str_replace( '-', '_', $provider_id ) . '_api_key';
	}

	/**
	 * Prefixed credentials map, migrating once from the legacy unprefixed option.
	 *
	 * @return array<string, mixed>
	 */
	private static function getCredentialsMap(): array {
		$credentials = get_option( self::CREDENTIALS_OPTION, null );

		if ( ! is_array( $credentials ) ) {
			$legacy = get_option( self::LEGACY_CREDENTIALS_OPTION, array() );
			$credentials = is_array( $legacy ) ? $legacy : array();
			if ( array() !== $credentials ) {
				update_option( self::CREDENTIALS_OPTION, $credentials );
			}
		}

		return $credentials;
	}

	/**
	 * Read the stored API key for a provider (never logs or exposes it).
	 */
	public static function getStoredApiKey( string $daiaw_provider ): string {
		$provider_id = self::resolveProviderId( $daiaw_provider );
		if ( '' === $provider_id ) {
			return '';
		}

		if ( self::usesCoreConnectors() ) {
			$key = get_option( self::connectorsOptionName( $provider_id ), '' );
			return is_string( $key ) ? trim( $key ) : '';
		}

		$credentials = self::getCredentialsMap();
		if ( empty( $credentials[ $provider_id ] ) || ! is_string( $credentials[ $provider_id ] ) ) {
			return '';
		}

		return trim( $credentials[ $provider_id ] );
	}

	/**
	 * Attach every stored provider API key to the AiClient registry.
	 *
	 * Safe to call multiple times. Required because OpenRouter/Groq/DeepSeek are
	 * registered by this plugin and may miss core's connectors key-pass.
	 */
	public static function applyStoredCredentials(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		foreach ( array_unique( array_values( self::PROVIDER_IDS ) ) as $provider_id ) {
			if ( ! $registry->hasProvider( $provider_id ) ) {
				continue;
			}

			$api_key = self::getStoredApiKey( $provider_id );
			if ( '' === $api_key ) {
				continue;
			}

			try {
				$registry->setProviderRequestAuthentication(
					$provider_id,
					new ApiKeyRequestAuthentication( $api_key )
				);
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Skip providers that reject the auth type.
			}
		}
	}

	/**
	 * Ensure the registry has request auth for one provider before an API call.
	 *
	 * @return true|WP_Error
	 */
	public static function ensureProviderAuthentication( string $daiaw_provider ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'daiaw_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $daiaw_provider );
		$api_key     = self::getStoredApiKey( $provider_id );

		if ( '' === $api_key ) {
			return new WP_Error(
				'daiaw_ai_missing_key',
				__( 'No API key configured for this provider. Add an API key in this node.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		try {
			AiClient::defaultRegistry()->setProviderRequestAuthentication(
				$provider_id,
				new ApiKeyRequestAuthentication( $api_key )
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'daiaw_ai_auth_failed',
				__( 'Could not attach API credentials for this provider.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Validate and persist a site-wide API key for a provider.
	 *
	 * WP 7+: stores in connectors_ai_{id}_api_key.
	 * Below WP 7: merges into daiaw_ai_provider_credentials.
	 *
	 * @param string $daiaw_provider Provider slug (openai, claude, openrouter, …).
	 * @param string $api_key      Raw API key.
	 *
	 * @return true|WP_Error
	 */
	public static function saveProviderApiKey( string $daiaw_provider, string $api_key ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'daiaw_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $daiaw_provider );
		$api_key     = trim( $api_key );

		if ( '' === $provider_id ) {
			return new WP_Error(
				'daiaw_ai_unknown_provider',
				__( 'Unknown AI provider.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $api_key ) {
			return new WP_Error(
				'daiaw_ai_empty_key',
				__( 'API key is required.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error(
				'daiaw_ai_provider_unregistered',
				sprintf(
					/* translators: %s: provider id */
					__( 'AI provider "%s" is not registered.', 'dragwyb-ai-agent-workflows' ),
					$provider_id
				),
				array( 'status' => 400 )
			);
		}

		$valid = self::validateProviderApiKey( $provider_id, $api_key );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		try {
			$registry->setProviderRequestAuthentication(
				$provider_id,
				new ApiKeyRequestAuthentication( $api_key )
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'daiaw_ai_key_invalid',
				__( 'It was not possible to connect to the provider using this key.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		if ( self::usesCoreConnectors() ) {
			update_option( self::connectorsOptionName( $provider_id ), $api_key );
		} else {
			$credentials = self::getCredentialsMap();
			if ( ! is_array( $credentials ) ) {
				$credentials = array();
			}
			$credentials[ $provider_id ] = $api_key;
			update_option( self::CREDENTIALS_OPTION, $credentials );
		}

		delete_transient( 'daiaw_ai_models_' . $provider_id );

		return true;
	}

	/**
	 * Probe the provider to ensure the API key is accepted.
	 *
	 * OpenRouter's /models endpoint is public, so we hit /auth/key instead.
	 *
	 * @return true|WP_Error
	 */
	private static function validateProviderApiKey( string $provider_id, string $api_key ) {
		if ( 'openrouter' === $provider_id ) {
			$response = wp_remote_get(
				'https://openrouter.ai/api/v1/auth/key',
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'HTTP-Referer'  => home_url( '/' ),
						'X-Title'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'daiaw_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'dragwyb-ai-agent-workflows' ),
					array( 'status' => 400 )
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_Error(
					'daiaw_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'dragwyb-ai-agent-workflows' ),
					array( 'status' => 400 )
				);
			}

			return true;
		}

		$registry = AiClient::defaultRegistry();

		try {
			$registry->setProviderRequestAuthentication(
				$provider_id,
				new ApiKeyRequestAuthentication( $api_key )
			);

			if ( ! $registry->isProviderConfigured( $provider_id ) ) {
				return new WP_Error(
					'daiaw_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'dragwyb-ai-agent-workflows' ),
					array( 'status' => 400 )
				);
			}
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'daiaw_ai_key_invalid',
				__( 'It was not possible to connect to the provider using this key.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Whether a stored API key exists for the provider (does not call the network).
	 */
	public static function hasStoredProviderApiKey( string $daiaw_provider ): bool {
		return '' !== self::getStoredApiKey( $daiaw_provider );
	}

	/**
	 * Remove a site-wide API key for a provider.
	 *
	 * @param string $daiaw_provider Provider slug.
	 *
	 * @return true|WP_Error
	 */
	public static function clearProviderApiKey( string $daiaw_provider ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'daiaw_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $daiaw_provider );
		if ( '' === $provider_id ) {
			return new WP_Error(
				'daiaw_ai_unknown_provider',
				__( 'Unknown AI provider.', 'dragwyb-ai-agent-workflows' ),
				array( 'status' => 400 )
			);
		}

		if ( self::usesCoreConnectors() ) {
			update_option( self::connectorsOptionName( $provider_id ), '' );
		} else {
			$credentials = self::getCredentialsMap();
			if ( is_array( $credentials ) && isset( $credentials[ $provider_id ] ) ) {
				unset( $credentials[ $provider_id ] );
				update_option( self::CREDENTIALS_OPTION, $credentials );
			}
		}

		delete_transient( 'daiaw_ai_models_' . $provider_id );

		return true;
	}

	/**
	 * @return void
	 */
	private static function registerProviders(): void {
		$registry = AiClient::defaultRegistry();

		$providers = array(
			'openai'     => OpenAiProvider::class,
			'anthropic'  => AnthropicProvider::class,
			'google'     => GoogleProvider::class,
			'openrouter' => OpenRouterProvider::class,
			'groq'       => GroqProvider::class,
			'deepseek'   => DeepSeekProvider::class,
		);

		foreach ( $providers as $id => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			if ( $registry->hasProvider( $id ) || $registry->hasProvider( $class ) ) {
				continue;
			}
			$registry->registerProvider( $class );
		}
	}

	/**
	 * One-time migration: daiaw AI connections → connectors_ai_* on WP 7+.
	 */
	private static function migrateCredentialsToConnectors(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$legacy = self::getCredentialsMap();
		if ( is_array( $legacy ) ) {
			foreach ( $legacy as $provider => $key ) {
				$provider_id = self::resolveProviderId( (string) $provider );
				if ( '' === $provider_id || ! is_string( $key ) || '' === $key ) {
					continue;
				}
				$option = 'connectors_ai_' . $provider_id . '_api_key';
				if ( '' === (string) get_option( $option, '' ) ) {
					update_option( $option, $key );
				}
			}
		}

		self::migrateFromConnectionsTable(
			static function ( string $provider_id, string $api_key ): void {
				$option = 'connectors_ai_' . $provider_id . '_api_key';
				if ( '' === (string) get_option( $option, '' ) ) {
					update_option( $option, $api_key );
				}
			}
		);

		update_option( self::MIGRATION_OPTION, true );
	}

	/**
	 * One-time migration: daiaw AI connections → daiaw_ai_provider_credentials below WP 7.
	 */
	private static function migrateCredentialsToLegacyOption(): void {
		if ( get_option( 'daiaw_ai_credentials_migrated_to_sdk' ) ) {
			return;
		}

		$credentials = self::getCredentialsMap();
		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}

		self::migrateFromConnectionsTable(
			static function ( string $provider_id, string $api_key ) use ( &$credentials ): void {
				if ( empty( $credentials[ $provider_id ] ) ) {
					$credentials[ $provider_id ] = $api_key;
				}
			}
		);

		if ( ! empty( $credentials ) ) {
			update_option( self::CREDENTIALS_OPTION, $credentials );
		}

		update_option( 'daiaw_ai_credentials_migrated_to_sdk', true );
	}

	/**
	 * @param callable(string, string): void $writer Receives (provider_id, api_key).
	 */
	private static function migrateFromConnectionsTable( callable $writer ): void {
		if ( ! class_exists( ConnectionService::class ) ) {
			return;
		}

		try {
			$plugin = \DragwybVisualAutomation\Plugin\Core\Plugin::instance();
			/** @var ConnectionService $connections */
			$connections = $plugin->container()->get( ConnectionService::class );
		} catch ( \Throwable $e ) {
			return;
		}

		$list  = $connections->list(
			array(
				'per_page' => 200,
				'page'     => 1,
			)
		);
		$items = isset( $list['items'] ) && is_array( $list['items'] ) ? $list['items'] : array();

		foreach ( $items as $connection ) {
			if ( ! is_object( $connection ) || ! method_exists( $connection, 'integrationSlug' ) ) {
				continue;
			}

			$slug = strtolower( (string) $connection->integrationSlug() );
			if ( ! isset( self::CONNECTION_SLUG_TO_PROVIDER[ $slug ] ) ) {
				continue;
			}

			$provider_id = self::CONNECTION_SLUG_TO_PROVIDER[ $slug ];
			$creds       = $connections->credentials( $connection );
			$api_key     = '';

			if ( ! empty( $creds['api_key'] ) && is_string( $creds['api_key'] ) ) {
				$api_key = $creds['api_key'];
			} elseif ( ! empty( $creds['token'] ) && is_string( $creds['token'] ) ) {
				$api_key = $creds['token'];
			}

			if ( '' === $api_key ) {
				continue;
			}

			$writer( $provider_id, $api_key );
		}
	}
}
