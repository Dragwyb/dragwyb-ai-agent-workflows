<?php
/**
 * Shared Google Sheet helper utilities.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Integration\GoogleSheet\Helpers;

use DragwybAgentFlow\Plugin\Integration\GoogleSheet\GoogleSheetsHttpClient;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common helpers shared across Google Sheet service classes.
 */
final class GoogleSheetCommons {

	private GoogleSheetsHttpClient $http;

	public function __construct( GoogleSheetsHttpClient $http ) {
		$this->http = $http;
	}

	public function getHttp(): GoogleSheetsHttpClient {
		return $this->http;
	}

	/**
	 * Fetch all values from a worksheet.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function fetchSheetData( string $spreadsheet_id, string $sheet_title ): array {
		$url = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $sheet_title );

		return $this->http->request( $url, 'GET' );
	}

	public function getSheetIdByTitle( string $spreadsheet_id, string $sheet_title ): ?int {
		$properties = $this->getSheetPropertiesByTitle( $spreadsheet_id, $sheet_title );

		if ( null === $properties ) {
			return null;
		}

		return isset( $properties['sheetId'] ) ? (int) $properties['sheetId'] : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function getSheetPropertiesByTitle( string $spreadsheet_id, string $sheet_title ): ?array {
		$url      = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '?fields=sheets.properties';
		$response = $this->http->request( $url, 'GET' );

		if ( empty( $response['success'] ) || ! is_array( $response['response']['sheets'] ?? null ) ) {
			return null;
		}

		foreach ( $response['response']['sheets'] as $sheet ) {
			$title = (string) ( $sheet['properties']['title'] ?? '' );

			if ( $title === $sheet_title ) {
				return is_array( $sheet['properties'] ?? null ) ? $sheet['properties'] : null;
			}
		}

		return null;
	}

	public function excelColumnToIndex( string $column ): int {
		$column = strtoupper( $column );
		$index  = 0;

		for ( $i = 0, $len = strlen( $column ); $i < $len; ++$i ) {
			$index *= 26;
			$index += ord( $column[ $i ] ) - ord( 'A' ) + 1;
		}

		return $index - 1;
	}

	public function columnIndexToLetter( int $column_index ): string {
		++$column_index;
		$letter = '';

		while ( $column_index > 0 ) {
			--$column_index;
			$letter       = chr( $column_index % 26 + 65 ) . $letter;
			$column_index = (int) ( $column_index / 26 );
		}

		return $letter;
	}
}
