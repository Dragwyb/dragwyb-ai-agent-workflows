<?php
/**
 * Spreadsheet-level Google Sheets operations.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\GoogleSheet;

use DragwybAgentFlow\Plugin\Integration\GoogleSheet\Helpers\GoogleSheetCommons;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GoogleSpreadsheetService {

	private GoogleSheetCommons $commons;

	public function __construct( GoogleSheetCommons $commons ) {
		$this->commons = $commons;
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function createSpreadsheet( string $title, string $sheet_title ): array {
		$payload = wp_json_encode(
			array(
				'properties' => array(
					'title' => $title,
				),
				'sheets'     => array(
					array(
						'properties' => array(
							'title' => $sheet_title,
						),
					),
				),
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the spreadsheet payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets',
			'POST',
			$payload
		);
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function findSpreadsheets( string $title, int $limit ): array {
		$query = sprintf(
			"name contains '%s' and mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
			str_replace( "'", "\\'", $title )
		);

		$url = GoogleSheetsHttpClient::DRIVE_URL . '/files?q=' . rawurlencode( $query ) . '&pageSize=' . max( 1, min( 100, $limit ) ) . '&fields=files(id,name,createdTime,webViewLink)';

		return $this->commons->getHttp()->request( $url, 'GET' );
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function deleteSpreadsheet( string $spreadsheet_id ): array {
		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::DRIVE_URL . '/files/' . rawurlencode( $spreadsheet_id ),
			'DELETE'
		);
	}
}
