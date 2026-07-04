<?php
/**
 * Google Sheets append row action.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Actions;

use WorkflowAutomate\Plugin\Domain\Contracts\ActionInterface;
use WorkflowAutomate\Plugin\Service\ConnectionSecretResolver;
use WorkflowAutomate\Plugin\Service\ConnectionService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends one row to a Google Sheet via the Sheets API v4 `values:append`.
 *
 * Requires an OAuth access token (or service-account access token) stored
 * as a Bearer Token / API Key connection. Spreadsheet must be shared with
 * the Google account that issued the token.
 *
 * `values` is a comma-separated list of cell values (tokens supported),
 * e.g. `{{trigger.fields.email}},{{trigger.fields.name}}`.
 */
class GoogleSheetsAppendRowAction implements ActionInterface {

	private const TIMEOUT_SECONDS = 20;

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections ) {
		$this->secrets = new ConnectionSecretResolver( $connections );
	}

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'google_sheets_append_row_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Google Sheets Append Row', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Appends a row of values to a Google Sheet.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		return array(
			'connection_id' => array(
				'type' => 'connection',
				'label' => __( 'Google access token connection', 'workflow-automate' ),
				'required' => true,
				'default' => 0,
			),
			'spreadsheet_id' => array(
				'type' => 'string',
				'label' => __( 'Spreadsheet ID (from the sheet URL)', 'workflow-automate' ),
				'required' => true,
			),
			'range' => array(
				'type' => 'string',
				'label' => __( 'Range / tab (e.g. Sheet1!A1)', 'workflow-automate' ),
				'default' => 'Sheet1!A1',
			),
			'values' => array(
				'type' => 'string',
				'label' => __( 'Row values, comma-separated (supports {{trigger.fields.*}})', 'workflow-automate' ),
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

		$spreadsheet_id = isset( $config['spreadsheet_id'] ) ? trim( (string) $config['spreadsheet_id'] ) : '';
		$range          = isset( $config['range'] ) ? trim( (string) $config['range'] ) : 'Sheet1!A1';
		$values_raw     = isset( $config['values'] ) ? (string) $config['values'] : '';

		if ( '' === $spreadsheet_id ) {
			return array(
				'success' => false,
				'error' => __( 'No spreadsheet ID configured.', 'workflow-automate' ),
			);
		}

		if ( '' === $range ) {
			$range = 'Sheet1!A1';
		}

		if ( '' === trim( $values_raw ) ) {
			return array(
				'success' => false,
				'error' => __( 'No row values configured.', 'workflow-automate' ),
			);
		}

		$row = array_map( 'trim', explode( ',', $values_raw ) );

		$url = sprintf(
			'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS',
			rawurlencode( $spreadsheet_id ),
			rawurlencode( $range )
		);

		$body = wp_json_encode(
			array(
				'values' => array( $row ),
			)
		);

		if ( ! is_string( $body ) ) {
			return array(
				'success' => false,
				'error' => __( 'Failed to encode the Google Sheets payload.', 'workflow-automate' ),
			);
		}

		$response = wp_safe_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT_SECONDS,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $token,
				),
				'body' => $body,
			)
		);

		$result = TelegramSendMessageAction::jsonApiResult( $response, 'Google Sheets' );

		if ( empty( $result['success'] ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'status_code' => $result['status_code'] ?? 200,
			'updated_range' => is_array( $result['response'] ?? null ) && isset( $result['response']['updates']['updatedRange'] )
				? (string) $result['response']['updates']['updatedRange']
				: '',
		);
	}
}
