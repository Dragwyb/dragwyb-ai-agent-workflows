<?php
/**
 * Boots WordPress AI Client and registers providers.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service\Ai;

use AIAWAB\AiProviders\DeepSeek\DeepSeekProvider;
use AIAWAB\AiProviders\Groq\GroqProvider;
use AIAWAB\AiProviders\OpenRouter\OpenRouterProvider;
use AIAWAB\Plugin\Service\ConnectionService;
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

	public const MIGRATION_OPTION = 'wfa_ai_credentials_migrated_to_wp70';

	/**
	 * Provider id map: WFA slug → AiClient / Connectors id.
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
	 * Integration slugs on wfa_connections that hold AI API keys.
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
			: version_compare( wfa_wp_version(), '7.0-alpha', '>=' );

		if ( ! $is_wp70 ) {
			$sdk_autoload = WFA_PLUGIN_DIR . 'vendor/wordpress/wp-ai-client/autoload.php';
			if ( file_exists( $sdk_autoload ) ) {
				require_once $sdk_autoload;
			}
			if ( ! class_exists( \WordPress\AI_Client\AI_Client::class ) ) {
				return;
			}
		}

		$providers_autoload = WFA_PLUGIN_DIR . 'includes/ai-providers/vendor/autoload.php';
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
		return wp_ai_client_prompt( $messages );
	}

	/**
	 * Map WFA provider slug to AiClient provider id.
	 */
	public static function resolveProviderId( string $wfa_provider ): string {
		$key = strtolower( trim( $wfa_provider ) );

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
	public static function isProviderConfigured( string $wfa_provider ): bool {
		if ( ! self::isAvailable() ) {
			return false;
		}

		if ( ! self::hasStoredProviderApiKey( $wfa_provider ) ) {
			return false;
		}

		self::ensureProviderAuthentication( $wfa_provider );

		$provider_id = self::resolveProviderId( $wfa_provider );

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

		return version_compare( wfa_wp_version(), '7.0-alpha', '>=' );
	}

	/**
	 * Option name for a provider API key on WP 7+ Connectors.
	 */
	public static function connectorsOptionName( string $provider_id ): string {
		return 'connectors_ai_' . str_replace( '-', '_', $provider_id ) . '_api_key';
	}

	/**
	 * Read the stored API key for a provider (never logs or exposes it).
	 */
	public static function getStoredApiKey( string $wfa_provider ): string {
		$provider_id = self::resolveProviderId( $wfa_provider );
		if ( '' === $provider_id ) {
			return '';
		}

		if ( self::usesCoreConnectors() ) {
			$key = get_option( self::connectorsOptionName( $provider_id ), '' );
			return is_string( $key ) ? trim( $key ) : '';
		}

		$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
		if ( ! is_array( $credentials ) || empty( $credentials[ $provider_id ] ) || ! is_string( $credentials[ $provider_id ] ) ) {
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
	public static function ensureProviderAuthentication( string $wfa_provider ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'wfa_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'workflow-automate' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $wfa_provider );
		$api_key     = self::getStoredApiKey( $provider_id );

		if ( '' === $api_key ) {
			return new WP_Error(
				'wfa_ai_missing_key',
				__( 'No API key configured for this provider. Add an API key in this node.', 'workflow-automate' ),
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
				'wfa_ai_auth_failed',
				__( 'Could not attach API credentials for this provider.', 'workflow-automate' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Validate and persist a site-wide API key for a provider.
	 *
	 * WP 7+: stores in connectors_ai_{id}_api_key.
	 * Below WP 7: merges into wp_ai_client_provider_credentials.
	 *
	 * @param string $wfa_provider Provider slug (openai, claude, openrouter, …).
	 * @param string $api_key      Raw API key.
	 *
	 * @return true|WP_Error
	 */
	public static function saveProviderApiKey( string $wfa_provider, string $api_key ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'wfa_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'workflow-automate' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $wfa_provider );
		$api_key     = trim( $api_key );

		if ( '' === $provider_id ) {
			return new WP_Error(
				'wfa_ai_unknown_provider',
				__( 'Unknown AI provider.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		if ( '' === $api_key ) {
			return new WP_Error(
				'wfa_ai_empty_key',
				__( 'API key is required.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error(
				'wfa_ai_provider_unregistered',
				sprintf(
					/* translators: %s: provider id */
					__( 'AI provider "%s" is not registered.', 'workflow-automate' ),
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
				'wfa_ai_key_invalid',
				__( 'It was not possible to connect to the provider using this key.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		if ( self::usesCoreConnectors() ) {
			update_option( self::connectorsOptionName( $provider_id ), $api_key );
		} else {
			$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
			if ( ! is_array( $credentials ) ) {
				$credentials = array();
			}
			$credentials[ $provider_id ] = $api_key;
			update_option( 'wp_ai_client_provider_credentials', $credentials );
		}

		delete_transient( 'wfa_ai_models_' . $provider_id );

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
					'wfa_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'workflow-automate' ),
					array( 'status' => 400 )
				);
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				return new WP_Error(
					'wfa_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'workflow-automate' ),
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
					'wfa_ai_key_invalid',
					__( 'It was not possible to connect to the provider using this key.', 'workflow-automate' ),
					array( 'status' => 400 )
				);
			}
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'wfa_ai_key_invalid',
				__( 'It was not possible to connect to the provider using this key.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Whether a stored API key exists for the provider (does not call the network).
	 */
	public static function hasStoredProviderApiKey( string $wfa_provider ): bool {
		return '' !== self::getStoredApiKey( $wfa_provider );
	}

	/**
	 * Remove a site-wide API key for a provider.
	 *
	 * @param string $wfa_provider Provider slug.
	 *
	 * @return true|WP_Error
	 */
	public static function clearProviderApiKey( string $wfa_provider ) {
		if ( ! self::isAvailable() ) {
			return new WP_Error(
				'wfa_ai_unavailable',
				__( 'WordPress AI Client is not available.', 'workflow-automate' ),
				array( 'status' => 503 )
			);
		}

		$provider_id = self::resolveProviderId( $wfa_provider );
		if ( '' === $provider_id ) {
			return new WP_Error(
				'wfa_ai_unknown_provider',
				__( 'Unknown AI provider.', 'workflow-automate' ),
				array( 'status' => 400 )
			);
		}

		if ( self::usesCoreConnectors() ) {
			update_option( self::connectorsOptionName( $provider_id ), '' );
		} else {
			$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
			if ( is_array( $credentials ) && isset( $credentials[ $provider_id ] ) ) {
				unset( $credentials[ $provider_id ] );
				update_option( 'wp_ai_client_provider_credentials', $credentials );
			}
		}

		delete_transient( 'wfa_ai_models_' . $provider_id );

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
	 * One-time migration: WFA AI connections → connectors_ai_* on WP 7+.
	 */
	private static function migrateCredentialsToConnectors(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$legacy = get_option( 'wp_ai_client_provider_credentials', array() );
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
	 * One-time migration: WFA AI connections → wp_ai_client_provider_credentials below WP 7.
	 */
	private static function migrateCredentialsToLegacyOption(): void {
		if ( get_option( 'wfa_ai_credentials_migrated_to_sdk' ) ) {
			return;
		}

		$credentials = get_option( 'wp_ai_client_provider_credentials', array() );
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
			update_option( 'wp_ai_client_provider_credentials', $credentials );
		}

		update_option( 'wfa_ai_credentials_migrated_to_sdk', true );
	}

	/**
	 * @param callable(string, string): void $writer Receives (provider_id, api_key).
	 */
	private static function migrateFromConnectionsTable( callable $writer ): void {
		if ( ! class_exists( ConnectionService::class ) ) {
			return;
		}

		try {
			$plugin = \AIAWAB\Plugin\Core\Plugin::instance();
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
