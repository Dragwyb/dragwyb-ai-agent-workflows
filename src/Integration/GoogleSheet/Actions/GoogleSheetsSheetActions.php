<?php
/**
 * Worksheet-level Google Sheets workflow actions.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\GoogleSheet\Actions;

use WorkflowAutomate\Plugin\Integration\GoogleSheet\AbstractGoogleSheetsAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GoogleSheetsCreateSheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_create_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Create Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Adds a new worksheet tab to a spreadsheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title' => array(
				'type' => 'string',
				'label' => __( 'New sheet tab name', 'workflow-automate' ),
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

		$sheet_title = $this->configString( $config, 'sheet_title' );

		if ( '' === $sheet_title ) {
			return array(
				'success' => false,
				'error' => __( 'Sheet tab name is required.', 'workflow-automate' ),
			);
		}

		return $this->formatResult( $services['sheets']->createSheet( $spreadsheet_id, $sheet_title ) );
	}
}

final class GoogleSheetsFindSheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_find_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Find Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Finds worksheet tabs in a spreadsheet by title.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'title' => array(
				'type' => 'string',
				'label' => __( 'Sheet title to find', 'workflow-automate' ),
				'required' => true,
			),
			'exact_match' => array(
				'type' => 'boolean',
				'label' => __( 'Exact match', 'workflow-automate' ),
				'default' => false,
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

		$title = $this->configString( $config, 'title' );

		if ( '' === $title ) {
			return array(
				'success' => false,
				'error' => __( 'Sheet title is required.', 'workflow-automate' ),
			);
		}

		return $this->formatResult(
			$services['sheets']->findWorksheet(
				$spreadsheet_id,
				$title,
				$this->configBool( $config, 'exact_match' )
			)
		);
	}
}

final class GoogleSheetsCopySheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_copy_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Copy Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Copies a worksheet tab to another spreadsheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title' => $this->sheetTitleField(),
			'destination_spreadsheet_id' => array(
				'type' => 'string',
				'label' => __( 'Destination spreadsheet ID', 'workflow-automate' ),
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

		$destination = $this->configString( $config, 'destination_spreadsheet_id' );

		if ( '' === $destination ) {
			return array(
				'success' => false,
				'error' => __( 'Destination spreadsheet ID is required.', 'workflow-automate' ),
			);
		}

		return $this->formatResult(
			$services['sheets']->copySheet(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$destination
			)
		);
	}
}

final class GoogleSheetsDeleteSheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_delete_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Delete Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Deletes a worksheet tab from a spreadsheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title' => $this->sheetTitleField(),
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
			$services['sheets']->deleteSheet( $spreadsheet_id, $this->requireSheetTitle( $config ) )
		);
	}
}

final class GoogleSheetsClearSheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_clear_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Clear Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Clears all cell values from a worksheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title' => $this->sheetTitleField(),
			'is_first_row_headers' => array(
				'type' => 'boolean',
				'label' => __( 'Keep first row as headers', 'workflow-automate' ),
				'default' => false,
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

		return $this->formatResult(
			$services['sheets']->clearSheet(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$this->configBool( $config, 'is_first_row_headers' )
			)
		);
	}
}

final class GoogleSheetsExportSheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_export_sheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Export Sheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Builds an export URL for a worksheet (CSV, PDF, or XLSX).', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
			'sheet_title' => $this->sheetTitleField(),
			'format' => array(
				'type' => 'string',
				'label' => __( 'Export format (csv, pdf, xlsx)', 'workflow-automate' ),
				'default' => 'csv',
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

		return $this->formatResult(
			$services['sheets']->exportSheet(
				$spreadsheet_id,
				$this->requireSheetTitle( $config ),
				$this->configString( $config, 'format', 'csv' )
			)
		);
	}
}
