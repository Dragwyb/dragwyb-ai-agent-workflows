<?php
/**
 * Built-in "HTTP Request" action.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\Actions;

use AIAWAB\Plugin\Domain\Contracts\ActionInterface;
use AIAWAB\Plugin\Service\ConnectionAuthTypes;
use AIAWAB\Plugin\Service\ConnectionService;

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

	private const ALLOWED_METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS' );

	private const DEFAULT_METHOD = 'GET';

	private const DEFAULT_BODY_CONTENT_TYPE = 'json';

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
			'method'            => array(
				'type'    => 'select',
				'label'   => __( 'Method', 'workflow-automate' ),
				'default' => self::DEFAULT_METHOD,
				'options' => array(
					array(
						'value' => 'GET',
						'label' => 'GET',
					),
					array(
						'value' => 'POST',
						'label' => 'POST',
					),
					array(
						'value' => 'PUT',
						'label' => 'PUT',
					),
					array(
						'value' => 'PATCH',
						'label' => 'PATCH',
					),
					array(
						'value' => 'DELETE',
						'label' => 'DELETE',
					),
					array(
						'value' => 'HEAD',
						'label' => 'HEAD',
					),
					array(
						'value' => 'OPTIONS',
						'label' => 'OPTIONS',
					),
				),
			),
			'url'               => array(
				'type'               => 'string',
				'label'              => __( 'URL', 'workflow-automate' ),
				'required'           => true,
				'supports_variables' => true,
			),
			'connection_id'     => array(
				'type'    => 'connection',
				'label'   => __( 'Authentication (optional)', 'workflow-automate' ),
				'default' => 0,
			),
			'headers'           => array(
				'type'    => 'object',
				'label'   => __( 'Headers', 'workflow-automate' ),
				'default' => array(),
			),
			'allow_unsafe_urls' => array(
				'type'    => 'boolean',
				'label'   => __( 'Allow local/unsafe URLs', 'workflow-automate' ),
				'default' => false,
				'help'    => __( 'Enable to reach localhost or private/internal addresses (e.g. a local dev server). Leave off in production — this bypasses protection against requests to internal network hosts.', 'workflow-automate' ),
			),
			'send_body'         => array(
				'type'    => 'boolean',
				'label'   => __( 'Send Body', 'workflow-automate' ),
				'default' => false,
			),
			'body_content_type' => array(
				'type'      => 'select',
				'label'     => __( 'Body Content Type', 'workflow-automate' ),
				'default'   => self::DEFAULT_BODY_CONTENT_TYPE,
				'show_when' => array(
					array(
						'field'  => 'send_body',
						'equals' => true,
					),
				),
				'options'   => array(
					array(
						'value' => 'json',
						'label' => __( 'JSON', 'workflow-automate' ),
					),
					array(
						'value' => 'form_urlencoded',
						'label' => __( 'Form URL Encoded', 'workflow-automate' ),
					),
					array(
						'value' => 'raw',
						'label' => __( 'Raw', 'workflow-automate' ),
					),
				),
			),
			'body_specify'      => array(
				'type'      => 'select',
				'label'     => __( 'Specify Body', 'workflow-automate' ),
				'default'   => 'json',
				'show_when' => array(
					array(
						'field'  => 'send_body',
						'equals' => true,
					),
				),
				'options'   => array(
					array(
						'value' => 'json',
						'label' => __( 'Using JSON', 'workflow-automate' ),
					),
					array(
						'value' => 'fields',
						'label' => __( 'Using Fields Below', 'workflow-automate' ),
					),
				),
			),
			'body'              => array(
				'type'               => 'string',
				'label'              => __( 'Body', 'workflow-automate' ),
				'default'            => '',
				'supports_variables' => true,
				'help'               => __( 'For JSON, enter an object such as {"name":"Ravi"}. Supports {{tokens}} from earlier steps.', 'workflow-automate' ),
				'show_when'          => array(
					array(
						'field'  => 'send_body',
						'equals' => true,
					),
					array(
						'field'  => 'body_specify',
						'equals' => 'json',
					),
				),
			),
			'body_parameters'   => array(
				'type'               => 'key_value',
				'label'              => __( 'Body Parameters', 'workflow-automate' ),
				'default'            => array(),
				'button_label'       => __( 'Add Body Field', 'workflow-automate' ),
				'supports_variables' => true,
				'show_when'          => array(
					array(
						'field'  => 'send_body',
						'equals' => true,
					),
					array(
						'field'  => 'body_specify',
						'equals' => 'fields',
					),
				),
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
				'error'   => __( 'No request URL configured.', 'workflow-automate' ),
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
					'error'   => $auth_error,
				);
			}
		}

		$args = array(
			'method'  => $method,
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => $headers,
		);

		$this->applyBody( $config, $method, $args );

		$allow_unsafe_urls = ! empty( $config['allow_unsafe_urls'] );

		// By default use `wp_safe_remote_request()` so WordPress's
		// `reject_unsafe_urls` protection blocks requests to loopback and
		// private/internal hosts (anti-SSRF). When the author explicitly
		// opts in — typically to reach a local dev server such as
		// `http://localhost:8002` — fall back to the regular client, which
		// does not apply that restriction.
		$response = $allow_unsafe_urls
			? wp_remote_request( $url, $args )
			: wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		return array(
			'success'     => true,
			'status_code' => wp_remote_retrieve_response_code( $response ),
			'body'        => wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * Builds the request body onto `$args` based on the configured
	 * "Send Body" toggle, "Body Content Type" and "Specify Body" mode,
	 * mirroring n8n's HTTP Request node.
	 *
	 * Two ways to specify the body:
	 *  - "Using JSON" (`body_specify = json`): the author types a raw
	 *    JSON/form/raw string in the Body field, sent verbatim.
	 *  - "Using Fields Below" (`body_specify = fields`): the author adds
	 *    Name/Value pairs in `body_parameters`, which are assembled into a
	 *    JSON object or a URL-encoded query string per the content type.
	 *
	 * Values are already interpolated for `{{tokens}}` upstream.
	 *
	 * Backwards compatibility: older configs stored a plain `body` string
	 * without a `send_body` toggle. When `send_body` is absent but a body
	 * is present, we still send it so existing workflows keep working.
	 *
	 * @param array<string, mixed> $config Node configuration.
	 * @param string               $method Resolved HTTP method.
	 * @param array<string, mixed> $args   Request args, modified by reference.
	 *
	 * @return void
	 */
	private function applyBody( array $config, string $method, array &$args ): void {
		$body = isset( $config['body'] ) ? (string) $config['body'] : '';

		$send_body = array_key_exists( 'send_body', $config )
			? (bool) $config['send_body']
			: ( '' !== $body );

		if ( ! $send_body || in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return;
		}

		$content_type = isset( $config['body_content_type'] )
			? (string) $config['body_content_type']
			: self::DEFAULT_BODY_CONTENT_TYPE;

		$specify = isset( $config['body_specify'] ) ? (string) $config['body_specify'] : 'json';

		if ( 'fields' === $specify ) {
			$this->applyBodyFields( $config, $content_type, $args );
			return;
		}

		if ( '' === $body ) {
			return;
		}

		switch ( $content_type ) {
			case 'form_urlencoded':
				$decoded = json_decode( $body, true );

				if ( is_array( $decoded ) ) {
					$args['body'] = http_build_query( $decoded );
				} else {
					$args['body'] = $body;
				}

				$this->ensureContentType( $args, 'application/x-www-form-urlencoded' );
				break;

			case 'raw':
				$args['body'] = $body;
				break;

			case 'json':
			default:
				$args['body'] = $this->sanitizeJsonBody( $body );
				$this->ensureContentType( $args, 'application/json' );
				break;
		}
	}

	/**
	 * Ensures the JSON body is valid. If interpolation left raw control
	 * characters inside strings, re-encode via json_decode/encode when possible.
	 *
	 * @param string $body Request body.
	 *
	 * @return string
	 */
	private function sanitizeJsonBody( string $body ): string {
		$trimmed = trim( $body );

		if ( '' === $trimmed ) {
			return $body;
		}

		$decoded = json_decode( $trimmed, true );

		if ( JSON_ERROR_NONE === json_last_error() && ( is_array( $decoded ) || is_object( $decoded ) ) ) {
			$encoded = wp_json_encode( $decoded );

			return is_string( $encoded ) ? $encoded : $trimmed;
		}

		return $body;
	}

	/**
	 * Assembles the request body from the "Using Fields Below" Name/Value
	 * pairs. Rows with an empty name are skipped. The pairs become a JSON
	 * object (for `json`/`raw` content types) or a URL-encoded query string
	 * (for `form_urlencoded`), with the matching default `Content-Type`.
	 *
	 * @param array<string, mixed> $config       Node configuration.
	 * @param string               $content_type Resolved body content type.
	 * @param array<string, mixed> $args         Request args, modified by reference.
	 *
	 * @return void
	 */
	private function applyBodyFields( array $config, string $content_type, array &$args ): void {
		$rows = isset( $config['body_parameters'] ) && is_array( $config['body_parameters'] )
			? $config['body_parameters']
			: array();

		$data = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? trim( (string) $row['name'] ) : '';

			if ( '' === $name ) {
				continue;
			}

			$data[ $name ] = isset( $row['value'] ) ? (string) $row['value'] : '';
		}

		if ( array() === $data ) {
			return;
		}

		if ( 'form_urlencoded' === $content_type ) {
			$args['body'] = http_build_query( $data );
			$this->ensureContentType( $args, 'application/x-www-form-urlencoded' );
			return;
		}

		$encoded      = wp_json_encode( $data );
		$args['body'] = is_string( $encoded ) ? $encoded : '';
		$this->ensureContentType( $args, 'application/json' );
	}

	/**
	 * Adds a `Content-Type` header only when the author hasn't already
	 * provided one (any capitalization), so an explicit header in the
	 * Headers field always wins.
	 *
	 * @param array<string, mixed> $args         Request args, modified by reference.
	 * @param string               $content_type Content type to default to.
	 *
	 * @return void
	 */
	private function ensureContentType( array &$args, string $content_type ): void {
		$headers = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();

		foreach ( array_keys( $headers ) as $name ) {
			if ( 0 === strcasecmp( (string) $name, 'Content-Type' ) ) {
				return;
			}
		}

		$headers['Content-Type'] = $content_type;
		$args['headers']         = $headers;
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
