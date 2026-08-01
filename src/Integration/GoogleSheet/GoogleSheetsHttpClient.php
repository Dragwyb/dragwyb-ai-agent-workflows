<?php
/**
 * Authenticated HTTP client for Google Sheets and Drive APIs.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\GoogleSheet;

use AIAWA\Plugin\Integration\Actions\TelegramSendMessageAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around wp_safe_remote_* for Google API calls.
 */
final class GoogleSheetsHttpClient {

	public const BASE_URL = 'https://sheets.googleapis.com/v4';

	public const DRIVE_URL = 'https://www.googleapis.com/drive/v3';

	private const TIMEOUT_SECONDS = 20;

	private string $token;

	public function __construct( string $token ) {
		$this->token = $token;
	}

	/**
	 * @param string      $url    Full request URL.
	 * @param string      $method HTTP method.
	 * @param string|null $body   JSON request body.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function request( string $url, string $method = 'GET', ?string $body = null ): array {
		$headers = array(
			'Authorization' => 'Bearer ' . $this->token,
		);

		$args = array(
			'timeout' => self::TIMEOUT_SECONDS,
			'headers' => $headers,
		);

		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
			$args['headers']         = $headers;
			$args['body']            = $body;
		}

		switch ( strtoupper( $method ) ) {
			case 'POST':
				$response = wp_safe_remote_post( $url, $args );
				break;

			case 'PUT':
				$args['method'] = 'PUT';
				$response       = wp_safe_remote_request( $url, $args );
				break;

			case 'DELETE':
				$args['method'] = 'DELETE';
				$response       = wp_safe_remote_request( $url, $args );
				break;

			default:
				unset( $args['headers']['Content-Type'] );
				$response = wp_safe_remote_get( $url, $args );
				break;
		}

		return TelegramSendMessageAction::jsonApiResult( $response, 'Google Sheets' );
	}
}
