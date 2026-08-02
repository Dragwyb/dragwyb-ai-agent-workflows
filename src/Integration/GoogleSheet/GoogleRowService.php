<?php
/**
 * Row and column Google Sheets operations.
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

final class GoogleRowService {

	private GoogleSheetCommons $commons;

	public function __construct( GoogleSheetCommons $commons ) {
		$this->commons = $commons;
	}

	/**
	 * @param array<int, string> $values Indexed column values.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function addRow( string $spreadsheet_id, string $sheet_title, array $values, string $value_input_option = 'USER_ENTERED' ): array {
		if ( empty( $values ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No row values configured.', 'dragwyb-agentflow' ),
			);
		}

		$min_index = min( array_keys( $values ) );
		$max_index = max( array_keys( $values ) );
		$row       = array();

		for ( $i = $min_index; $i <= $max_index; ++$i ) {
			$row[] = $values[ $i ] ?? '';
		}

		$range = $sheet_title . '!' . $this->commons->columnIndexToLetter( $min_index ) . ':' . $this->commons->columnIndexToLetter( $max_index );
		$url   = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $range ) . ':append?valueInputOption=' . rawurlencode( $value_input_option ) . '&insertDataOption=INSERT_ROWS';

		$payload = wp_json_encode(
			array(
				'majorDimension' => 'ROWS',
				'values'         => array( $row ),
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the row payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request( $url, 'POST', $payload );
	}

	/**
	 * @param array<int, string> $values Indexed column values.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function appendOrUpdateRow( string $spreadsheet_id, string $sheet_title, array $values, string $column_to_match ): array {
		$fetched = $this->commons->fetchSheetData( $spreadsheet_id, $sheet_title );

		if ( empty( $fetched['success'] ) ) {
			return $fetched;
		}

		$match_index = $this->resolveColumnIndex( $column_to_match );
		$rows        = $fetched['response']['values'] ?? array();
		$matched_row = -1;
		$old_values  = array();

		if ( $match_index >= 0 && isset( $values[ $match_index ] ) && is_array( $rows ) ) {
			foreach ( $rows as $row_id => $row_values ) {
				if ( ! is_array( $row_values ) ) {
					continue;
				}

				if ( isset( $row_values[ $match_index ] ) && (string) $row_values[ $match_index ] === (string) $values[ $match_index ] ) {
					$matched_row = (int) $row_id;
					$old_values  = $row_values;
					break;
				}
			}
		}

		if ( $matched_row >= 0 ) {
			return $this->updateRowValues( $spreadsheet_id, $sheet_title, $matched_row, $old_values, $values );
		}

		return $this->addRow( $spreadsheet_id, $sheet_title, $values );
	}

	/**
	 * @param array<int, string> $values Indexed column values.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function updateRow( string $spreadsheet_id, string $sheet_title, int $row_number, string $target_range, array $values ): array {
		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-agentflow' ),
			);
		}

		if ( '' === $target_range ) {
			$keys         = array_keys( $values );
			$min_index    = min( $keys );
			$max_index    = max( $keys );
			$target_range = $this->commons->columnIndexToLetter( $min_index ) . $row_number . ':' . $this->commons->columnIndexToLetter( $max_index ) . $row_number;
		}

		$aligned = $this->fillMissingColumns( $values, $target_range );
		$payload = wp_json_encode( array( 'values' => array( $aligned ) ) );

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the update payload.', 'dragwyb-agentflow' ),
			);
		}

		$url = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $sheet_title . '!' . $target_range ) . '?valueInputOption=USER_ENTERED';

		return $this->commons->getHttp()->request( $url, 'PUT', $payload );
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function deleteRow( string $spreadsheet_id, string $sheet_title, int $row_number ): array {
		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-agentflow' ),
			);
		}

		$range = $sheet_title . '!A' . $row_number . ':ZZZ' . $row_number;
		$url   = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $range ) . ':clear';

		return $this->commons->getHttp()->request( $url, 'POST', '{}' );
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>, rows?: array<int, array<int, mixed>>}
	 */
	public function getRowByNumber( string $spreadsheet_id, string $sheet_title, int $row_number ): array {
		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-agentflow' ),
			);
		}

		$url      = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $sheet_title . '!A' . $row_number . ':ZZZ' . $row_number );
		$response = $this->commons->getHttp()->request( $url, 'GET' );

		if ( empty( $response['success'] ) ) {
			return $response;
		}

		$response['response'] = array(
			'row_number' => $row_number,
			'values'     => $response['response']['values'][0] ?? array(),
		);

		return $response;
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>, rows?: array<int, array<int, mixed>>}
	 */
	public function getRows( string $spreadsheet_id, string $sheet_title ): array {
		$response = $this->commons->fetchSheetData( $spreadsheet_id, $sheet_title );

		if ( empty( $response['success'] ) ) {
			return $response;
		}

		$response['response'] = array(
			'values' => $response['response']['values'] ?? array(),
		);

		return $response;
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	public function createColumn( string $spreadsheet_id, string $sheet_title, string $column_name, int $column_index ): array {
		$sheet_id = $this->commons->getSheetIdByTitle( $spreadsheet_id, $sheet_title );

		if ( null === $sheet_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Worksheet was not found.', 'dragwyb-agentflow' ),
			);
		}

		if ( $column_index < 1 ) {
			$fetched    = $this->commons->fetchSheetData( $spreadsheet_id, $sheet_title );
			$total_cols = 0;

			if ( ! empty( $fetched['success'] ) && isset( $fetched['response']['values'][0] ) && is_array( $fetched['response']['values'][0] ) ) {
				$total_cols = count( $fetched['response']['values'][0] );
			}

			$column_index = $total_cols + 1;
		}

		$payload = wp_json_encode(
			array(
				'requests' => array(
					array(
						'insertDimension' => array(
							'range' => array(
								'sheetId'    => $sheet_id,
								'dimension'  => 'COLUMNS',
								'startIndex' => $column_index - 1,
								'endIndex'   => $column_index,
							),
						),
					),
				),
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the column payload.', 'dragwyb-agentflow' ),
			);
		}

		$insert = $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . ':batchUpdate',
			'POST',
			$payload
		);

		if ( empty( $insert['success'] ) ) {
			return $insert;
		}

		$column_label = $this->commons->columnIndexToLetter( $column_index - 1 );
		$header_range = $sheet_title . '!' . $column_label . '1';
		$header_url   = GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values/' . rawurlencode( $header_range ) . '?valueInputOption=USER_ENTERED';
		$header_body  = wp_json_encode( array( 'values' => array( array( $column_name ) ) ) );

		if ( ! is_string( $header_body ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the column header payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request( $header_url, 'PUT', $header_body );
	}

	/**
	 * @param array<int, string> $old_values Existing row values.
	 * @param array<int, string> $new_values New values keyed by column index.
	 *
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}
	 */
	private function updateRowValues( string $spreadsheet_id, string $sheet_title, int $row_id, array $old_values, array $new_values ): array {
		$updates = array();
		$length  = max( count( $old_values ), max( array_keys( $new_values ) ) + 1 );

		for ( $col = 0; $col < $length; ++$col ) {
			$old_val = $old_values[ $col ] ?? '';
			$new_val = $new_values[ $col ] ?? $old_val;

			if ( $old_val !== $new_val ) {
				$updates[] = array(
					'range'  => $sheet_title . '!' . $this->commons->columnIndexToLetter( $col ) . ( $row_id + 1 ),
					'values' => array( array( $new_val ) ),
				);
			}
		}

		if ( empty( $updates ) ) {
			return array(
				'success'     => true,
				'status_code' => 200,
				'response'    => array(
					'updatedCells' => 0,
				),
			);
		}

		$payload = wp_json_encode(
			array(
				'data'             => $updates,
				'valueInputOption' => 'USER_ENTERED',
			)
		);

		if ( ! is_string( $payload ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to encode the batch update payload.', 'dragwyb-agentflow' ),
			);
		}

		return $this->commons->getHttp()->request(
			GoogleSheetsHttpClient::BASE_URL . '/spreadsheets/' . rawurlencode( $spreadsheet_id ) . '/values:batchUpdate',
			'POST',
			$payload
		);
	}

	/**
	 * @param array<int, string> $values Indexed values.
	 *
	 * @return array<int, string|null>
	 */
	private function fillMissingColumns( array $values, string $range ): array {
		if ( function_exists( 'str_contains' ) && str_contains( $range, '!' ) ) {
			$range = explode( '!', $range )[1];
		}

		$parts     = explode( ':', $range );
		$start_col = preg_replace( '/[0-9]/', '', $parts[0] );
		$end_col   = preg_replace( '/[0-9]/', '', $parts[1] ?? $parts[0] );
		$result    = array();
		$start     = $this->commons->excelColumnToIndex( (string) $start_col );
		$end       = $this->commons->excelColumnToIndex( (string) $end_col );

		for ( $i = $start; $i <= $end; ++$i ) {
			$result[] = $values[ $i ] ?? null;
		}

		return $result;
	}

	private function resolveColumnIndex( string $column_to_match ): int {
		$part = explode( ':', $column_to_match )[0];

		return ctype_alpha( $part ) ? $this->commons->excelColumnToIndex( $part ) : (int) $part;
	}
}
