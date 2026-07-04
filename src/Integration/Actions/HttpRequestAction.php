<?php
/**
 * Built-in "HTTP Request" action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionAuthTypes;
use WorkflowAutomate\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends an outbound HTTP request to a user-configured URL.
 *
 * Uses `wp_safe_remote_request()` rather than `wp_remote_request()`
 * deliberately: it applies WordPress's own `reject_unsafe_urls` protection
 * against requests targeting internal/private network addresses. This is
 * the concrete implementation of the "no open HTTP proxy endpoint" decision
 * in `docs/internal/architecture.md` (opportunity #1) now that a real
 * outbound-HTTP node type exists.
 *
 * Optionally authenticates the request using a stored `Connection` (item
 * 11) — this is the first real consumer of `ConnectionService::credentials()`.
 * See `docs/integrations.md` for exactly how each auth type is applied.
 */
class HttpRequestAction implements ActionInterface {

	private const ALLOWED_METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	private const DEFAULT_METHOD = 'GET';

	private const TIMEOUT_SECONDS = 15;

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'http_request_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'HTTP Request', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends an outbound HTTP request to a URL you specify.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'url' => array(
				'type' => 'string',
				'label' => __( 'Request URL', 'workflow-automate' ),
				'required' => true,
			),
			'method' => array(
				'type' => 'string',
				'label' => __( 'HTTP method', 'workflow-automate' ),
				'default' => self::DEFAULT_METHOD,
			),
			'headers' => array(
				'type' => 'object',
				'label' => __( 'Headers', 'workflow-automate' ),
				'default' => array(),
			),
			'body' => array(
				'type' => 'string',
				'label' => __( 'Body', 'workflow-automate' ),
				'default' => '',
			),
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'Authenticate with connection (optional)', 'workflow-automate' ),
				'default' => 0,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		$url = isset( $config['url'] ) ? esc_url_raw( trim( (string) $config['url'] ) ) : '';

		if ( '' === $url ) {
			return array(
				'success' => false,
				'error' => __( 'No request URL configured.', 'workflow-automate' ),
			);
		}

		$method = isset( $config['method'] ) ? strtoupper( trim( (string) $config['method'] ) ) : self::DEFAULT_METHOD;

		if ( ! in_array( $method, self::ALLOWED_METHODS, true ) ) {
			$method = self::DEFAULT_METHOD;
		}

		$headers = isset( $config['headers'] ) && is_array( $config['headers'] ) ? $config['headers'] : array();

		$connection_id = isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0;

		if ( $connection_id > 0 ) {
			$auth_error = $this->applyConnectionAuth( $connection_id, $headers );

			if ( null !== $auth_error ) {
				return array(
					'success' => false,
					'error' => $auth_error,
				);
			}
		}

		$args = array(
			'method' => $method,
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => $headers,
		);

		if ( isset( $config['body'] ) && '' !== $config['body'] ) {
			$args['body'] = $config['body'];
		}

		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error' => $response->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'status_code' => wp_remote_retrieve_response_code( $response ),
			'body' => wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Resolves the given connection and, if found and decryptable, adds
	 * the appropriate `Authorization` header for its auth type into
	 * `$headers` by reference. A missing connection or a decryption
	 * failure is reported back as a hard error rather than silently
	 * sending the request unauthenticated — a workflow author who
	 * configured a connection expects the request to fail loudly, not
	 * succeed unauthenticated, if that connection can no longer be used.
	 *
	 * Auth-type-to-header mapping is a deliberate v1 simplification
	 * documented in `docs/integrations.md`: `api_key` and `bearer_token`
	 * connections both send `Authorization: Bearer <value>` (the most
	 * common convention for modern REST APIs), and `basic` sends standard
	 * HTTP Basic auth. An API that expects its key somewhere else (a
	 * custom header, a query parameter) still needs that header added
	 * manually to the Headers field for now.
	 *
	 * @param int                  $connection_id Connection id from config.
	 * @param array<string,string> $headers       Headers array, modified by reference.
	 *
	 * @return string|null An error message, or null on success.
	 */
	private function applyConnectionAuth( int $connection_id, array &$headers ): ?string {
		$connection = $this->connections->find( $connection_id );

		if ( null === $connection ) {
			return __( 'The connection configured for this action no longer exists.', 'workflow-automate' );
		}

		$credentials = $this->connections->credentials( $connection );

		switch ( $connection->authType() ) {
			case ConnectionAuthTypes::BASIC:
				$username = $credentials['username'] ?? null;
				$password = $credentials['password'] ?? null;

				if ( null === $username || null === $password ) {
					return __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' );
				}

				$headers['Authorization'] = 'Basic ' . base64_encode( $username . ':' . $password );

				return null;

			case ConnectionAuthTypes::BEARER_TOKEN:
				$token = $credentials['token'] ?? null;

				if ( null === $token ) {
					return __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' );
				}

				$headers['Authorization'] = 'Bearer ' . $token;

				return null;

			case ConnectionAuthTypes::API_KEY:
			default:
				$api_key = $credentials['api_key'] ?? null;

				if ( null === $api_key ) {
					return __( 'Unable to decrypt this connection\'s credentials. Please re-enter them.', 'workflow-automate' );
				}

				$headers['Authorization'] = 'Bearer ' . $api_key;

				return null;
		}
	}
}
