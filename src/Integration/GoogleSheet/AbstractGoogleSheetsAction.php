<?php
/**
 * Base class for Google Sheets workflow actions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\GoogleSheet;

use AIAWAB\Plugin\Domain\Contracts\ActionInterface;
use AIAWAB\Plugin\Service\ConnectionService;
use AIAWAB\Plugin\Service\GoogleOAuthService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractGoogleSheetsAction implements ActionInterface {

	protected GoogleSheetsServices $services;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->services = new GoogleSheetsServices( $connections, $google_oauth );
	}

	/**
	 * @return array{type: string, label: string, required?: bool, default?: mixed}
	 */
	protected function connectionField(): array {
		return array(
			'type'     => 'connection',
			'label'    => __( 'Google connection', 'workflow-automate' ),
			'required' => true,
			'default'  => 0,
		);
	}

	/**
	 * @return array{type: string, label: string, required?: bool, default?: mixed}
	 */
	protected function spreadsheetIdField(): array {
		return array(
			'type'     => 'string',
			'label'    => __( 'Spreadsheet ID (from the sheet URL)', 'workflow-automate' ),
			'required' => true,
		);
	}

	/**
	 * @return array{type: string, label: string, required?: bool, default?: mixed}
	 */
	protected function sheetTitleField(): array {
		return array(
			'type'     => 'string',
			'label'    => __( 'Sheet tab name', 'workflow-automate' ),
			'default'  => 'Sheet1',
			'required' => true,
		);
	}

	/**
	 * @return array{type: string, label: string, required?: bool, default?: mixed}
	 */
	protected function valuesField(): array {
		return array(
			'type'     => 'string',
			'label'    => __( 'Row values, comma-separated (supports {{trigger.fields.*}})', 'workflow-automate' ),
			'required' => true,
		);
	}

	/**
	 * Optional row values for agent-driven tools (e.g. create spreadsheet + seed row).
	 *
	 * @return array{type: string, label: string, description?: string, agent_fillable?: bool}
	 */
	protected function optionalValuesField(): array {
		return array(
			'type'           => 'string',
			'label'          => __( 'Row values', 'workflow-automate' ),
			'description'    => __( 'Comma-separated cell values for a data row (map fields from trigger/post data). Optional.', 'workflow-automate' ),
			'agent_fillable' => true,
		);
	}

	/**
	 * @param array<string, mixed> $config Node config.
	 * @param string               $key    Config key holding values.
	 *
	 * @return array<int, string>
	 */
	protected function parseValuesFromConfig( array $config, string $key = 'values' ): array {
		if ( ! array_key_exists( $key, $config ) ) {
			return array();
		}

		$raw = $config[ $key ];

		if ( is_array( $raw ) ) {
			$values = array();

			foreach ( $raw as $index => $value ) {
				if ( ! is_int( $index ) && ! is_string( $index ) ) {
					continue;
				}

				$values[ (int) $index ] = is_scalar( $value ) ? (string) $value : ( wp_json_encode( $value ) ?: '' );
			}

			if ( array() !== $values ) {
				ksort( $values );

				return $values;
			}

			return array();
		}

		$string = trim( (string) $raw );

		if ( '' === $string ) {
			return array();
		}

		if ( str_starts_with( $string, '[' ) ) {
			$decoded = json_decode( $string, true );

			if ( is_array( $decoded ) ) {
				return $this->parseValuesFromConfig( array( $key => $decoded ), $key );
			}
		}

		return $this->parseIndexedValues( $string );
	}

	/**
	 * @param array<string, mixed> $config Node config.
	 *
	 * @return array{success: bool, error?: string}|array{commons: GoogleSheetCommons, spreadsheets: GoogleSpreadsheetService, sheets: GoogleSheetService, rows: GoogleRowService}
	 */
	protected function resolveServices( array $config ) {
		return $this->services->forConnection( isset( $config['connection_id'] ) ? (int) $config['connection_id'] : 0 );
	}

	/**
	 * @return array{success: bool, error?: string, status_code?: int, response?: array<string, mixed>}&array<string, mixed>
	 */
	protected function formatResult( array $result ): array {
		if ( empty( $result['success'] ) ) {
			return array(
				'success' => false,
				'error'   => isset( $result['error'] ) ? (string) $result['error'] : __( 'Google Sheets request failed.', 'workflow-automate' ),
			);
		}

		return array(
			'success'     => true,
			'status_code' => $result['status_code'] ?? 200,
			'response'    => $result['response'] ?? array(),
		);
	}

	protected function configString( array $config, string $key, string $default = '' ): string {
		return isset( $config[ $key ] ) ? trim( (string) $config[ $key ] ) : $default;
	}

	protected function configInt( array $config, string $key, int $default = 0 ): int {
		return isset( $config[ $key ] ) ? (int) $config[ $key ] : $default;
	}

	protected function configBool( array $config, string $key, bool $default = false ): bool {
		if ( ! isset( $config[ $key ] ) ) {
			return $default;
		}

		return filter_var( $config[ $key ], FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @return array<int, string>
	 */
	protected function parseIndexedValues( string $values_raw ): array {
		$parts  = array_map( 'trim', explode( ',', $values_raw ) );
		$values = array();

		foreach ( $parts as $index => $value ) {
			$values[ $index ] = $value;
		}

		return $values;
	}

	protected function requireSpreadsheetId( array $config ): string|array {
		$spreadsheet_id = $this->configString( $config, 'spreadsheet_id' );

		if ( '' === $spreadsheet_id ) {
			return array(
				'success' => false,
				'error'   => __( 'No spreadsheet ID configured.', 'workflow-automate' ),
			);
		}

		return $spreadsheet_id;
	}

	protected function requireSheetTitle( array $config ): string {
		$title = $this->configString( $config, 'sheet_title', 'Sheet1' );

		return '' === $title ? 'Sheet1' : $title;
	}
}
