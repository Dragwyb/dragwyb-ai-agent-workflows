<?php
/**
 * Google Sheets append row action (legacy slug).
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\Actions;

use AIAWA\Plugin\Integration\GoogleSheet\AbstractGoogleSheetsAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Backward-compatible alias for append-row behavior. Prefer
 * `google_sheets_add_row_action` for new workflows.
 */
class GoogleSheetsAppendRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_append_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Append Row', 'ai-agent-workflow-automation' );
	}

	public function description(): string {
		return __( 'Appends a row of values to a Google Sheet.', 'ai-agent-workflow-automation' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'range'          => array(
				'type'    => 'string',
				'label'   => __( 'Range / tab (e.g. Sheet1!A1)', 'ai-agent-workflow-automation' ),
				'default' => 'Sheet1!A1',
			),
			'values'         => $this->valuesField(),
		);
	}

	public function execute( array $config, array $context ): array {
		unset( $context );

		$services = $this->resolveServices( $config );

		if ( isset( $services['success'] ) && ! $services['success'] ) {
			return $services;
		}

		$spreadsheet_id = $this->requireSpreadsheetId( $config );

		if ( is_array( $spreadsheet_id ) ) {
			return $spreadsheet_id;
		}

		$values_raw = $this->configString( $config, 'values' );

		if ( '' === $values_raw ) {
			return array(
				'success' => false,
				'error'   => __( 'No row values configured.', 'ai-agent-workflow-automation' ),
			);
		}

		$sheet_title = $this->sheetTitleFromRange( $this->configString( $config, 'range', 'Sheet1!A1' ) );
		$result      = $services['rows']->addRow(
			$spreadsheet_id,
			$sheet_title,
			$this->parseIndexedValues( $values_raw )
		);

		$formatted = $this->formatResult( $result );

		if ( ! empty( $formatted['success'] ) && isset( $result['response']['updates']['updatedRange'] ) ) {
			$formatted['updated_range'] = (string) $result['response']['updates']['updatedRange'];
		}

		return $formatted;
	}

	private function sheetTitleFromRange( string $range ): string {
		$range = trim( $range );

		if ( '' === $range ) {
			return 'Sheet1';
		}

		if ( str_contains( $range, '!' ) ) {
			return explode( '!', $range )[0];
		}

		return $range;
	}
}
