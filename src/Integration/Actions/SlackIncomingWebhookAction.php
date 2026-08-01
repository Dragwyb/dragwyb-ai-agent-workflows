<?php
/**
 * Slack Incoming Webhook action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Posts a message to a Slack channel via an Incoming Webhook URL.
 *
 * Uses Slack's documented Incoming Webhooks API (a single HTTPS POST with
 * a JSON `text` body). The webhook URL is stored in the node config (not
 * Connections) because Slack issues one URL per channel integration and
 * it is not a reusable OAuth credential in the same sense as an API key.
 *
 * Supports `{{trigger.fields.*}}` tokens via ConfigInterpolator (applied
 * before execute() by NodeExecutionService).
 */
class SlackIncomingWebhookAction implements ActionInterface {

	private const TIMEOUT_SECONDS = 15;

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'slack_incoming_webhook_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Slack (Incoming Webhook)', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Posts a message to Slack using an Incoming Webhook URL.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'webhook_url' => array(
				'type'     => 'string',
				'label'    => __( 'Slack Incoming Webhook URL', 'workflow-automate' ),
				'required' => true,
			),
			'message'     => array(
				'type'     => 'string',
				'label'    => __( 'Message (supports {{trigger.fields.field_id}} tokens)', 'workflow-automate' ),
				'required' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		unset( $context );

		$url = isset( $config['webhook_url'] ) ? esc_url_raw( trim( (string) $config['webhook_url'] ) ) : '';

		if ( '' === $url ) {
			return array(
				'success' => false,
				'error'   => __( 'No Slack webhook URL configured.', 'workflow-automate' ),
			);
		}

		// Incoming webhooks are always hosted on hooks.slack.com; reject
		// anything else so a mis-pasted URL cannot become an open proxy.
		if ( 0 !== strpos( $url, 'https://hooks.slack.com/' ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook URL must start with https://hooks.slack.com/.', 'workflow-automate' ),
			);
		}

		$message = isset( $config['message'] ) ? (string) $config['message'] : '';

		if ( '' === trim( $message ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No message configured.', 'workflow-automate' ),
			);
		}

		$body = wp_json_encode( array( 'text' => $message ) );

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the Slack payload.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code   = (int) wp_remote_retrieve_response_code( $response );
		$response_body = (string) wp_remote_retrieve_body( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success'     => false,
				'error'       => sprintf(
					/* translators: 1: HTTP status code, 2: response body snippet */
					__( 'Slack returned HTTP %1$d: %2$s', 'workflow-automate' ),
					$status_code,
					self::truncate( $response_body, 200 )
				),
				'status_code' => $status_code,
			);
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
		);
	}

	/**
	 * @param string $text   Raw text.
	 * @param int    $length Max length.
	 *
	 * @return string
	 */
	private static function truncate( string $text, int $length ): string {
		$text = trim( $text );

		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		return substr( $text, 0, $length ) . '…';
	}
}
