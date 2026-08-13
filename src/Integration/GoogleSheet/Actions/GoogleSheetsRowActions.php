<?php
/**
 * Row and column Google Sheets workflow actions.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions;

use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\AbstractGoogleSheetsAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GoogleSheetsAddRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_add_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Add Row', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Appends a row of values to a worksheet.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'      => $this->connectionField(),
			'spreadsheet_id'     => $this->spreadsheetIdField(),
			'sheet_title'        => $this->sheetTitleField(),
			'values'             => $this->valuesField(),
			'value_input_option' => array(
				'type'    => 'string',
				'label'   => __( 'Value input option (USER_ENTERED or RAW)', 'dragwyb-ai-agent-workflows' ),
				'default' => 'USER_ENTERED',
			),
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

		$values = $this->parseValuesFromConfig( $config, 'values' );

		if ( array() === $values ) {
			return array(
				'success' => false,
				'error'   => __( 'No row values configured.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		$result = $services['rows']->addRow(
			$spreadsheet_id,
			$this->requireSheetTitle( $config ),
			$values,
			$this->configString( $config, 'value_input_option', 'USER_ENTERED' )
		);

		$formatted = $this->formatResult( $result );

		if ( ! empty( $formatted['success'] ) && isset( $result['response']['updates']['updatedRange'] ) ) {
			$formatted['updated_range'] = (string) $result['response']['updates']['updatedRange'];
		}

		return $formatted;
	}
}

final class GoogleSheetsUpdateRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_update_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Update Row', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Updates an existing row in a worksheet.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title'    => $this->sheetTitleField(),
			'row_number'     => array(
				'type'     => 'string',
				'label'    => __( 'Row number (1-based)', 'dragwyb-ai-agent-workflows' ),
				'required' => true,
			),
			'target_range'   => array(
				'type'    => 'string',
				'label'   => __( 'Target range (optional, e.g. A5:E5)', 'dragwyb-ai-agent-workflows' ),
				'default' => '',
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

		$row_number = $this->configInt( $config, 'row_number' );

		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		$values_raw = $this->configString( $config, 'values' );

		if ( '' === $values_raw ) {
			return array(
				'success' => false,
				'error'   => __( 'No row values configured.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		return $this->formatResult(
			$services['rows']->updateRow(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$row_number,
				$this->configString( $config, 'target_range' ),
				$this->parseIndexedValues( $values_raw )
			)
		);
	}
}

final class GoogleSheetsAppendOrUpdateRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_append_or_update_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Append or Update Row', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Updates a row when a column value matches, otherwise appends a new row.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'   => $this->connectionField(),
			'spreadsheet_id'  => $this->spreadsheetIdField(),
			'sheet_title'     => $this->sheetTitleField(),
			'column_to_match' => array(
				'type'    => 'string',
				'label'   => __( 'Column to match on (letter or index, e.g. A or 0)', 'dragwyb-ai-agent-workflows' ),
				'default' => 'A',
			),
			'values'          => $this->valuesField(),
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
				'error'   => __( 'No row values configured.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		return $this->formatResult(
			$services['rows']->appendOrUpdateRow(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$this->parseIndexedValues( $values_raw ),
				$this->configString( $config, 'column_to_match', 'A' )
			)
		);
	}
}

final class GoogleSheetsGetRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_get_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Get Row', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Retrieves a single row by row number.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title'    => $this->sheetTitleField(),
			'row_number'     => array(
				'type'     => 'string',
				'label'    => __( 'Row number (1-based)', 'dragwyb-ai-agent-workflows' ),
				'required' => true,
			),
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

		$row_number = $this->configInt( $config, 'row_number' );

		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		return $this->formatResult(
			$services['rows']->getRowByNumber(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$row_number
			)
		);
	}
}

final class GoogleSheetsGetAllRowsAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_get_all_rows_action';
	}

	public function label(): string {
		return __( 'Google Sheets Get All Rows', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Retrieves all rows from a worksheet.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title'    => $this->sheetTitleField(),
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

		return $this->formatResult(
			$services['rows']->getRows( $spreadsheet_id, $this->requireSheetTitle( $config ) )
		);
	}
}

final class GoogleSheetsDeleteRowAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_delete_row_action';
	}

	public function label(): string {
		return __( 'Google Sheets Delete Row', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Clears all values in a row.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title'    => $this->sheetTitleField(),
			'row_number'     => array(
				'type'     => 'string',
				'label'    => __( 'Row number (1-based)', 'dragwyb-ai-agent-workflows' ),
				'required' => true,
			),
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

		$row_number = $this->configInt( $config, 'row_number' );

		if ( $row_number <= 0 ) {
			return array(
				'success' => false,
				'error'   => __( 'Row number must be greater than zero.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		return $this->formatResult(
			$services['rows']->deleteRow(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$row_number
			)
		);
	}
}

final class GoogleSheetsCreateColumnAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_create_column_action';
	}

	public function label(): string {
		return __( 'Google Sheets Create Column', 'dragwyb-ai-agent-workflows' );
	}

	public function description(): string {
		return __( 'Inserts a new column and sets its header name.', 'dragwyb-ai-agent-workflows' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title'    => $this->sheetTitleField(),
			'column_name'    => array(
				'type'     => 'string',
				'label'    => __( 'Column header name', 'dragwyb-ai-agent-workflows' ),
				'required' => true,
			),
			'column_index'   => array(
				'type'    => 'string',
				'label'   => __( 'Column index (1-based, leave 0 to append)', 'dragwyb-ai-agent-workflows' ),
				'default' => '0',
			),
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

		$column_name = $this->configString( $config, 'column_name' );

		if ( '' === $column_name ) {
			return array(
				'success' => false,
				'error'   => __( 'Column name is required.', 'dragwyb-ai-agent-workflows' ),
			);
		}

		return $this->formatResult(
			$services['rows']->createColumn(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$column_name,
				$this->configInt( $config, 'column_index', 0 )
			)
		);
	}
}
