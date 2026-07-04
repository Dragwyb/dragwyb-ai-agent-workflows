<?php
/**
 * Built-in "HTTP Request" action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;

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
 */
class HttpRequestAction implements ActionInterface {

	private const ALLOWED_METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );

	private const DEFAULT_METHOD = 'GET';

	private const TIMEOUT_SECONDS = 15;

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

		$args = array(
			'method' => $method,
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => isset( $config['headers'] ) && is_array( $config['headers'] ) ? $config['headers'] : array(),
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
}
