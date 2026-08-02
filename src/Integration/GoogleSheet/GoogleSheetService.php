<?php
/**
 * Worksheet/tab-level Google Sheets operations.
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

final class GoogleSheetService {

	private GoogleSheetCommons $commons;

	public function __construct( GoogleSheetCommons $commons ) {
		$this->commons = $commons;
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function createSheet( string $spreadsheet_id, string $sheet_title ): array {
		$payload = wp_json_encode(
			array(
				'requests' => array(
					array(
						'addSheet' => array(
							'properties' => array(
								'title' => $sheet_title,
							),
						),
					),
				),
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the sheet payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . ':batchUpdate',
			'POST',
			$payload
		);
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>, worksheets?: array<int, array<string, mixed>>}
	 */
	public function findWorksheet( string $spreadsheet_id, string $title, bool $exact_match ): array {
		$url      = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '?fields=sheets.properties';
		$response = $this->commons->getHttp()->request( $url, 'GET' );

		if ( empty( $response['success'] ) ) {
			return $response;
		}

		$matched = array();

		foreach ( $response['response']['sheets'] ?? array() as $sheet ) {
			$sheet_title = (string) ( $sheet['properties']['title'] ?? '' );

			if ( $exact_match ? ( $sheet_title === $title ) : ( stripos( $sheet_title, $title ) !== false ) ) {
				$matched[] = $sheet['properties'];
			}
		}

		$response['response'] = array(
			'found'      => count( $matched ) > 0,
			'worksheets' => $matched,
		);

		return $response;
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function copySheet( string $spreadsheet_id, string $sheet_title, string $destination_spreadsheet_id ): array {
		$sheet_id = $this->commons->getSheetIdByTitle( $spreadsheet_id, $sheet_title );

		if ( null === $sheet_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Source worksheet was not found.', 'dragwyb-agentflow' ),
			);
		}

		$payload = wp_json_encode(
			array(
				'destinationSpreadsheetId' => $destination_spreadsheet_id,
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the copy payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/sheets/' . $sheet_id . ':copyTo',
			'POST',
			$payload
		);
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function deleteSheet( string $spreadsheet_id, string $sheet_title ): array {
		$sheet_id = $this->commons->getSheetIdByTitle( $spreadsheet_id, $sheet_title );

		if ( null === $sheet_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Worksheet was not found.', 'dragwyb-agentflow' ),
			);
		}

		$payload = wp_json_encode(
			array(
				'requests' => array(
					array(
						'deleteSheet' => array(
							'sheetId' => $sheet_id,
						),
					),
				),
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the delete payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . ':batchUpdate',
			'POST',
			$payload
		);
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function clearSheet( string $spreadsheet_id, string $sheet_title, bool $is_first_row_headers ): array {
		$start_index = $is_first_row_headers ? 2 : 1;
		$range       = $sheet_title . '!A' . $start_index . ':ZZZ';
		$url         = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $range ) . ':clear';

		return $this->commons->getHttp()->request( $url, 'POST', '{}' );
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function exportSheet( string $spreadsheet_id, string $sheet_title, string $format ): array {
		$sheet_id = $this->commons->getSheetIdByTitle( $spreadsheet_id, $sheet_title );

		if ( null === $sheet_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Worksheet was not found.', 'dragwyb-agentflow' ),
			);
		}

		$format = in_array( $format, array( 'csv', 'pdf', 'xlsx' ), true ) ? $format : 'csv';

		return array(
			'success'     => true,
			'status_code' => 200,
			'response'    => array(
				'file_url' => sprintf(
					'https://docs.google.com/spreadsheets/d/%s/export?format=%s&id=%s&gid=%d',
					rawurlencode( $spreadsheet_id ),
					rawurlencode( $format ),
					rawurlencode( $spreadsheet_id ),
					$sheet_id
				),
			),
		);
	}
}
