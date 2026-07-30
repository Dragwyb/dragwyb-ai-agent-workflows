<?php
/**
 * Google OAuth 2.0 authorization and token lifecycle.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Service;

use RuntimeException;
use AIAWAB\Plugin\Domain\Connection;
use AIAWAB\Plugin\Integration\Actions\TelegramSendMessageAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Google OAuth authorization URLs, code exchange, and access-token
 * refresh for connections stored with auth_type `oauth2`.
 */
class GoogleOAuthService {

	public const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	public const GOOGLE_CREDENTIALS_URL = 'https://console.cloud.google.com/apis/credentials';

	private const TIMEOUT_SECONDS = 20;

	private const STATE_TTL_SECONDS = 600;

	private const EXPIRY_BUFFER_SECONDS = 60;

	/**
	 * Scopes required for Google Sheets and Drive spreadsheet operations.
	 *
	 * @var string[]
	 */
	public const SCOPES = array(
		'https://www.googleapis.com/auth/spreadsheets',
		'https://www.googleapis.com/auth/drive',
	);

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * OAuth redirect URI registered in Google Cloud Console.
	 */
	public function callbackUrl(): string {
		return rest_url( 'wfa/v1/oauth/google/callback' );
	}

	/**
	 * Whether a connection has completed the OAuth consent flow.
	 */
	public function isConnected( Connection $connection ): bool {
		if ( ConnectionAuthTypes::OAUTH2 !== $connection->authType() ) {
			return false;
		}

		$credentials = $this->connections->credentials( $connection );
		$token       = trim( (string) ( $credentials['access_token'] ?? '' ) );

		return '' !== $token;
	}

	/**
	 * Builds the Google authorization URL and stores a short-lived state nonce.
	 *
	 * @param string $return_url Optional admin URL to redirect to after OAuth.
	 * @param string $node_id    Optional builder node id to re-select after OAuth.
	 *
	 * @throws RuntimeException When client credentials are missing.
	 */
	public function buildAuthorizeUrl( Connection $connection, string $return_url = '', string $node_id = '' ): string {
		$credentials = $this->connections->credentials( $connection );
		$client_id   = trim( (string) ( $credentials['client_id'] ?? '' ) );

		if ( '' === $client_id ) {
			throw new RuntimeException(
				esc_html__( 'Client ID is missing. Save your Google OAuth credentials first.', 'workflow-automate' )
			);
		}

		$state = wp_generate_password( 32, false );
		set_transient(
			$this->stateTransientKey( $state ),
			array(
				'connection_id' => $connection->id(),
				'user_id'       => get_current_user_id(),
				'return_url'    => $this->sanitizeReturnUrl( $return_url ),
				'node_id'       => sanitize_text_field( $node_id ),
			),
			self::STATE_TTL_SECONDS
		);

		return add_query_arg(
			array(
				'client_id'     => $client_id,
				'redirect_uri'  => $this->callbackUrl(),
				'response_type' => 'code',
				'scope'         => implode( ' ', self::SCOPES ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => $state,
			),
			self::AUTHORIZE_URL
		);
	}

	/**
	 * Validates OAuth state and returns the stored payload.
	 *
	 * @return array{connection_id: int, user_id: int}|null
	 */
	public function consumeState( string $state ): ?array {
		$state = sanitize_text_field( $state );

		if ( '' === $state ) {
			return null;
		}

		$key     = $this->stateTransientKey( $state );
		$payload = get_transient( $key );
		delete_transient( $key );

		if ( ! is_array( $payload ) ) {
			return null;
		}

		$connection_id = isset( $payload['connection_id'] ) ? (int) $payload['connection_id'] : 0;
		$user_id       = isset( $payload['user_id'] ) ? (int) $payload['user_id'] : 0;

		if ( $connection_id <= 0 || $user_id <= 0 ) {
			return null;
		}

		return array(
			'connection_id' => $connection_id,
			'user_id'       => $user_id,
			'return_url'    => isset( $payload['return_url'] ) ? (string) $payload['return_url'] : '',
			'node_id'       => isset( $payload['node_id'] ) ? (string) $payload['node_id'] : '',
		);
	}

	/**
	 * Exchanges an authorization code for tokens and stores them on the connection.
	 *
	 * @return array{success: bool, error?: string}
	 */
	public function exchangeAuthorizationCode( Connection $connection, string $code ): array {
		$code = trim( $code );

		if ( '' === $code ) {
			return array(
				'success' => false,
				'error'   => __( 'Google did not return an authorization code.', 'workflow-automate' ),
			);
		}

		$credentials   = $this->connections->credentials( $connection );
		$client_id     = trim( (string) ( $credentials['client_id'] ?? '' ) );
		$client_secret = trim( (string) ( $credentials['client_secret'] ?? '' ) );

		if ( '' === $client_id || '' === $client_secret ) {
			return array(
				'success' => false,
				'error'   => __( 'Client ID and Client Secret are required before connecting to Google.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $this->callbackUrl(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		return $this->storeTokenResponse( $connection, $response );
	}

	/**
	 * Returns a valid Google access token, refreshing it when expired.
	 *
	 * @return string|array{success: bool, error: string}
	 */
	public function getAccessToken( Connection $connection ) {
		if ( ConnectionAuthTypes::OAUTH2 !== $connection->authType() ) {
			return array(
				'success' => false,
				'error'   => __( 'This connection is not a Google OAuth connection.', 'workflow-automate' ),
			);
		}

		$credentials = $this->connections->credentials( $connection );
		$access      = trim( (string) ( $credentials['access_token'] ?? '' ) );
		$expires_at  = (int) ( $credentials['expires_at'] ?? 0 );

		if ( '' !== $access && ( 0 === $expires_at || $expires_at > ( time() + self::EXPIRY_BUFFER_SECONDS ) ) ) {
			return $access;
		}

		$refresh = trim( (string) ( $credentials['refresh_token'] ?? '' ) );

		if ( '' === $refresh ) {
			return array(
				'success' => false,
				'error'   => __( 'Google access token expired. Reconnect this connection in Connections.', 'workflow-automate' ),
			);
		}

		$client_id     = trim( (string) ( $credentials['client_id'] ?? '' ) );
		$client_secret = trim( (string) ( $credentials['client_secret'] ?? '' ) );

		if ( '' === $client_id || '' === $client_secret ) {
			return array(
				'success' => false,
				'error'   => __( 'Google OAuth client credentials are missing.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$result = $this->storeTokenResponse( $connection, $response, $refresh );

		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] ) ? (string) $result['error'] : __( 'Failed to refresh the Google access token.', 'workflow-automate' ),
			);
		}

		$updated = $this->connections->find( $connection->id() );

		if ( null === $updated ) {
			return array(
				'success' => false,
				'error'   => __( 'The connection no longer exists.', 'workflow-automate' ),
			);
		}

		$fresh = $this->connections->credentials( $updated );
		$token = trim( (string) ( $fresh['access_token'] ?? '' ) );

		if ( '' === $token ) {
			return array(
				'success' => false,
				'error'   => __( 'Unable to read the refreshed Google access token.', 'workflow-automate' ),
			);
		}

		return $token;
	}

	/**
	 * @param mixed $response Remote response.
	 *
	 * @return array{success: bool, error?: string}
	 */
	private function storeTokenResponse( Connection $connection, $response, string $existing_refresh = '' ): array {
		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Google OAuth' );

		if ( empty( $result['success'] ) || ! is_array( $result['response'] ?? null ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] )
					? (string) $result['error']
					: __( 'Google OAuth token request failed.', 'workflow-automate' ),
			);
		}

		$body          = $result['response'];
		$access_token  = trim( (string) ( $body['access_token'] ?? '' ) );
		$refresh_token = trim( (string) ( $body['refresh_token'] ?? $existing_refresh ) );
		$expires_in    = (int) ( $body['expires_in'] ?? 0 );

		if ( '' === $access_token ) {
			return array(
				'success' => false,
				'error'   => __( 'Google did not return an access token.', 'workflow-automate' ),
			);
		}

		$expires_at = $expires_in > 0 ? ( time() + $expires_in ) : 0;

		try {
			$this->connections->storeOAuthTokens(
				$connection->id(),
				$access_token,
				$refresh_token,
				$expires_at
			);
		} catch ( RuntimeException $exception ) {
			return array(
				'success' => false,
				'error'   => $exception->getMessage(),
			);
		}

		return array( 'success' => true );
	}

	private function stateTransientKey( string $state ): string {
		return 'wfa_google_oauth_' . md5( $state );
	}

	private function sanitizeReturnUrl( string $return_url ): string {
		$return_url = esc_url_raw( trim( $return_url ) );

		if ( '' === $return_url ) {
			return '';
		}

		$validated = wp_validate_redirect( $return_url, false );

		return is_string( $validated ) ? $validated : '';
	}
}
