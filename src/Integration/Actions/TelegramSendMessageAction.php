<?php
/**
 * Telegram Bot sendMessage action.
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
 * Sends a text message via the Telegram Bot API (`sendMessage`).
 *
 * Bot token comes from a Connections entry (API Key). Chat id and message
 * support `{{trigger.*}}` tokens.
 */
class TelegramSendMessageAction implements ActionInterface {

	private const TIMEOUT_SECONDS = 15;

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections ) {
		$this->secrets = new ConnectionSecretResolver( $connections );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'telegram_send_message_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Telegram Send Message', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Sends a text message with a Telegram bot.', 'ai-agent-workflow-automation' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type'     => 'connection',
				'label'    => __( 'Telegram bot token connection', 'ai-agent-workflow-automation' ),
				'required' => true,
				'default'  => 0,
			),
			'chat_id'       => array(
				'type'     => 'string',
				'label'    => __( 'Chat ID', 'ai-agent-workflow-automation' ),
				'required' => true,
			),
			'message'       => array(
				'type'     => 'string',
				'label'    => __( 'Message (supports {{trigger.fields.*}} tokens)', 'ai-agent-workflow-automation' ),
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

		$chat_id = isset( $config['chat_id'] ) ? trim( (string) $config['chat_id'] ) : '';
		$message = isset( $config['message'] ) ? (string) $config['message'] : '';

		if ( '' === $chat_id ) {
			return array(
				'success' => false,
				'error'   => __( 'No Telegram chat ID configured.', 'ai-agent-workflow-automation' ),
			);
		}

		if ( '' === trim( $message ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No message configured.', 'ai-agent-workflow-automation' ),
			);
		}

		$url = sprintf( 'https://api.telegram.org/bot%s/sendMessage', rawurlencode( $token ) );

		$body = wp_json_encode(
			array(
				'chat_id' => $chat_id,
				'text'    => $message,
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the Telegram payload.', 'ai-agent-workflow-automation' ),
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
			)
		);

		return self::jsonApiResult( $response, 'Telegram' );
	}

	/**
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $service  Service label for errors.
	 *
	 * @return array{success: bool, error?: string, status_code?: int}
	 */
	public static function jsonApiResult( $response, string $service ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error'   => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$detail = is_array( $decoded ) && isset( $decoded['description'] )
				? (string) $decoded['description']
				: ( is_array( $decoded ) && isset( $decoded['error']['message'] )
					? (string) $decoded['error']['message']
					: self::truncate( $raw_body, 200 ) );

			return array(
				'success'     => false,
				'error'       => sprintf(
					/* translators: 1: service name, 2: HTTP status, 3: error detail */
					__( '%1$s returned HTTP %2$d: %3$s', 'ai-agent-workflow-automation' ),
					$service,
					$status_code,
					$detail
				),
				'status_code' => $status_code,
			);
		}

		if ( is_array( $decoded ) && array_key_exists( 'ok', $decoded ) && ! $decoded['ok'] ) {
			return array(
				'success' => false,
				'error'   => isset( $decoded['description'] )
					? (string) $decoded['description']
					: __( 'Telegram reported failure.', 'ai-agent-workflow-automation' ),
			);
		}

		return array(
			'success'     => true,
			'status_code' => $status_code,
			'response'    => is_array( $decoded ) ? $decoded : array(),
		);
	}

	/**
	 * @param string $text   Text.
	 * @param int    $length Max length.
	 *
	 * @return string
	 */
	private static function truncate( string $text, int $length ): string {
		$text = trim( $text );

		return strlen( $text ) <= $length ? $text : substr( $text, 0, $length ) . '…';
	}
}
