<?php
/**
 * Resolves a bearer/API-key secret from a stored connection.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Service;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared helper for actions that authenticate with a Connections entry
 * (API Key or Bearer Token). Returns either the secret string or a
 * standard action-error array so callers can `return` it directly.
 */
class ConnectionSecretResolver {

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
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
				'error' => __( 'No connection configured for this action.', 'workflow-automate' ),
			);
		}

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

				if ( null === $token || '' === (string) $token ) {
					return array(
						'success' => false,
						'error' => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' ),
					);
				}

				return (string) $token;

			case ConnectionAuthTypes::API_KEY:
			default:
				$api_key = $credentials['api_key'] ?? null;

				if ( null === $api_key || '' === (string) $api_key ) {
					return array(
						'success' => false,
						'error' => __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' ),
					);
				}

				return (string) $api_key;
		}
	}
}
