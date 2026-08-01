<?php
/**
 * WhatsApp Cloud API send text message action.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;
use AIAWA\Plugin\Service\ConnectionSecretResolver;
use AIAWA\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a text message via Meta's WhatsApp Cloud API.
 *
 * Requires a permanent access token (Connections) and the phone number id
 * from the Meta developer app. Recipient must be in E.164 digits (no +).
 */
class WhatsAppCloudSendMessageAction implements ActionInterface {

	private const TIMEOUT_SECONDS = 20;

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections ) {
		$this->secrets = new ConnectionSecretResolver( $connections );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'whatsapp_cloud_send_message_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'WhatsApp Cloud Send Message', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends a text message via the WhatsApp Cloud API (Meta).', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id'   => array(
				'type'     => 'connection',
				'label'    => __( 'WhatsApp access token connection', 'workflow-automate' ),
				'required' => true,
				'default'  => 0,
			),
			'phone_number_id' => array(
				'type'     => 'string',
				'label'    => __( 'Phone number ID (from Meta)', 'workflow-automate' ),
				'required' => true,
			),
			'to'              => array(
				'type'     => 'string',
				'label'    => __( 'Recipient phone (digits, country code, no +)', 'workflow-automate' ),
				'required' => true,
			),
			'message'         => array(
				'type'     => 'string',
				'label'    => __( 'Message (supports {{trigger.fields.*}} tokens)', 'workflow-automate' ),
				'required' => true,
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function execute( array $config, array $context ): array {
		unset( $context );

		$token = $this->secrets->resolveBearerSecret( isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0 );

		if ( is_array( $token ) ) {
			return $token;
		}

		$phone_number_id = isset( $config['phone_number_id'] ) ? preg_replace( '/\D+/', '', (string) $config['phone_number_id'] ) : '';
		$phone_number_id = is_string( $phone_number_id ) ? $phone_number_id : '';
		$to              = isset( $config['to'] ) ? preg_replace( '/\D+/', '', (string) $config['to'] ) : '';
		$to              = is_string( $to ) ? $to : '';
		$message         = isset( $config['message'] ) ? (string) $config['message'] : '';

		if ( '' === $phone_number_id ) {
			return array(
				'success' => false,
				'error'   => __( 'No WhatsApp phone number ID configured.', 'workflow-automate' ),
			);
		}

		if ( '' === $to ) {
			return array(
				'success' => false,
				'error'   => __( 'No recipient phone number configured.', 'workflow-automate' ),
			);
		}

		if ( '' === trim( $message ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No message configured.', 'workflow-automate' ),
			);
		}

		$url = sprintf( 'https://graph.facebook.com/v19.0/%s/messages', rawurlencode( $phone_number_id ) );

		$body = wp_json_encode(
			array(
				'messaging_product' => 'whatsapp',
				'to'                => $to,
				'type'              => 'text',
				'text'              => array(
					'preview_url' => false,
					'body'        => $message,
				),
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the WhatsApp payload.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body'    => $body,
			)
		);

		return TelegramSendMessageAction::jsonApiResult( $response, 'WhatsApp' );
	}
}
