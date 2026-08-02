<?php
/**
 * Resolves a bearer/API-key secret from a stored connection.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper for actions that authenticate with a Connections entry
 * (API Key, Bearer Token, or OAuth 2). Returns either the secret string
 * or a standard action-error array so callers can `return` it directly.
 */
class ConnectionSecretResolver {

	private ConnectionService $connections;

	private ?GoogleOAuthService $google_oauth;

	public function __construct( ConnectionService $connections, ?GoogleOAuthService $google_oauth = null ) {
		$this->connections  = $connections;
		$this->google_oauth = $google_oauth;
	}

	/**
	 * @param int $connection_id Connection id from node config.
	 *
	 * @return string|array{success: bool, error: string} Secret on success, error result on failure.
	 */
	public function resolveBearerSecret( int $connection_id ) {
		if ( $connection_id <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'No connection configured for this action.', 'dragwyb-agentflow' ),
			);
		}

		$connection = $this->connections->find( $connection_id );

		if ( null === $connection ) {
			return array(
				'success' => false,
				'error'   => __( 'The connection configured for this action no longer exists.', 'dragwyb-agentflow' ),
			);
		}

		if ( ConnectionAuthTypes::OAUTH2 === $connection->authType() ) {
			if ( null === $this->google_oauth ) {
				return array(
					'success' => false,
					'error'   => __( 'Google OAuth is not available.', 'dragwyb-agentflow' ),
				);
			}

			$token = $this->google_oauth->getAccessToken( $connection );

			if ( is_array( $token ) ) {
				return $token;
			}

			return (string) $token;
		}

		$credentials = $this->connections->credentials( $connection );

		switch ( $connection->authType() ) {
			case ConnectionAuthTypes::BEARER_TOKEN:
				$token = $credentials['token'] ?? null;

				if ( null === $token || '' === (string) $token ) {
					return array(
						'success' => false,
						'error'   => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'dragwyb-agentflow' ),
					);
				}

				return (string) $token;

			case ConnectionAuthTypes::API_KEY:
			default:
				$api_key = $credentials['api_key'] ?? null;

				if ( null === $api_key || '' === (string) $api_key ) {
					return array(
						'success' => false,
						'error'   => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'dragwyb-agentflow' ),
					);
				}

				return (string) $api_key;
		}
	}
}
