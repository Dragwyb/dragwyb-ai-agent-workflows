<?php
/**
 * Public inbound-webhook ingress REST controller.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Rest;

use AIAWA\Plugin\Service\WebhookService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public (no cookie/nonce/capability) POST endpoint that receives inbound
 * webhook calls and hands them to `WebhookService::ingest()`. Security is
 * deliberately *not* WordPress authentication — callers are third-party
 * services that cannot hold a WP cookie — and is instead:
 *
 * - an unguessable `public_id` UUID in the URL,
 * - an optional per-webhook HMAC signing secret (`X-aiawa-Signature`),
 * - an optional per-webhook IP allow-list,
 * - a site-wide "require signing" setting (see SettingsService).
 *
 * This is the one intentional `__return_true`-style permission callback
 * in the plugin; every other REST route still requires a `aiawa_*` capability
 * (with `manage_options` as a fallback — see `Core\Capabilities`).
 * Documented in `docs/rest-api.md` and `docs/integrations.md`.
 */
class WebhookIngressController {

	private const API_NAMESPACE = 'aiawa/v1';

	private const ROUTE = '/webhooks/(?P<public_id>[0-9a-fA-F-]{36})';

	private WebhookService $webhooks;

	public function __construct( WebhookService $webhooks ) {
		$this->webhooks = $webhooks;
	}

	/**
	 * Registers the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'public_id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value ) && (bool) preg_match( '/^[0-9a-fA-F-]{36}$/', $value );
						},
					),
				),
			)
		);
	}

	/**
	 * @param WP_REST_Request $request Full request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive( $request ) {
		$public_id = (string) $request->get_param( 'public_id' );
		$client_ip = $this->clientIp( $request );

		if ( ! $this->checkRateLimit( $public_id, $client_ip ) ) {
			return new WP_Error(
				'aiawa_webhook_rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'ai-agent-workflow-automation' ),
				array( 'status' => 429 )
			);
		}

		$raw_body  = (string) $request->get_body();
		$signature = (string) $request->get_header( 'x-aiawa-signature' );

		$result = $this->webhooks->ingest( $public_id, $raw_body, $client_ip, $signature );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$status = ! empty( $result['queued'] ) ? 202 : 200;

		return new WP_REST_Response( $result, $status );
	}

	/**
	 * Rate limiting helper for webhooks using transients.
	 *
	 * @param string $public_id Webhook public ID.
	 * @param string $client_ip Client IP.
	 *
	 * @return bool True if allowed, false if exceeded.
	 */
	private function checkRateLimit( string $public_id, string $client_ip ): bool {
		$transient_key = 'aiawa_wh_rl_' . md5( $client_ip . '|' . $public_id );
		$count         = (int) get_transient( $transient_key );

		if ( $count >= 60 ) {
			return false;
		}

		set_transient( $transient_key, $count + 1, 60 );
		return true;
	}

	/**
	 * Best-effort client IP. Uses REMOTE_ADDR only — deliberately does
	 * not trust `X-Forwarded-For` / similar headers, which any caller can
	 * forge. Sites behind a trusted reverse proxy that need the real
	 * client IP should configure PHP/the web server to set REMOTE_ADDR
	 * correctly (e.g. via the proxy's real-IP module), not rely on this
	 * plugin reading forgeable headers.
	 *
	 * @param WP_REST_Request $request Full request (unused; kept for symmetry with other controllers).
	 *
	 * @return string
	 */
	private function clientIp( $request ): string {
		unset( $request );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as an IP below; not echoed.
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		if ( ! is_string( $remote ) || '' === $remote ) {
			return '';
		}

		return false !== filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	}
}
