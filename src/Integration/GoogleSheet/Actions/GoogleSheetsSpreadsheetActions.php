<?php
/**
 * Spreadsheet-level Google Sheets workflow actions.
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

final class GoogleSheetsCreateSpreadsheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_create_spreadsheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Create Spreadsheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Creates a new Google spreadsheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'title' => array(
				'type' => 'string',
				'label' => __( 'Spreadsheet title', 'workflow-automate' ),
				'required' => true,
			),
			'sheet_title' => array(
				'type' => 'string',
				'label' => __( 'First sheet tab name', 'workflow-automate' ),
				'default' => 'Sheet1',
			),
		);
	}

	public function execute( array $config, array $context ): array {
		unset( $context );

		$services = $this->resolveServices( $config );

		if ( isset( $services['success'] ) && ! $services['success'] ) {
			return $services;
		}

		$title = $this->configString( $config, 'title' );

		if ( '' === $title ) {
			return array(
				'success' => false,
				'error' => __( 'Spreadsheet title is required.', 'workflow-automate' ),
			);
		}

		return $this->formatResult(
			$services['spreads']->createSpreadsheet(
				$title,
				$this->configString( $config, 'sheet_title', 'Sheet1' )
			)
		);
	}
}

final class GoogleSheetsFindSpreadsheetsAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_find_spreadsheets_action';
	}

	public function label(): string {
		return __( 'Google Sheets Find Spreadsheets', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Searches Google Drive for spreadsheets by name.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'title' => array(
				'type' => 'string',
				'label' => __( 'Search title', 'workflow-automate' ),
				'required' => true,
			),
			'limit' => array(
				'type' => 'string',
				'label' => __( 'Maximum results', 'workflow-automate' ),
				'default' => '10',
			),
		);
	}

	public function execute( array $config, array $context ): array {
		unset( $context );

		$services = $this->resolveServices( $config );

		if ( isset( $services['success'] ) && ! $services['success'] ) {
			return $services;
		}

		$title = $this->configString( $config, 'title' );

		if ( '' === $title ) {
			return array(
				'success' => false,
				'error' => __( 'Search title is required.', 'workflow-automate' ),
			);
		}

		return $this->formatResult(
			$services['spreads']->findSpreadsheets( $title, max( 1, $this->configInt( $config, 'limit', 10 ) ) )
		);
	}
}

final class GoogleSheetsDeleteSpreadsheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_delete_spreadsheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Delete Spreadsheet', 'workflow-automate' );
	}

	public function description(): string {
		return __( 'Permanently deletes a Google spreadsheet.', 'workflow-automate' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'spreadsheet_id' => $this->spreadsheetIdField(),
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

		return $this->formatResult( $services['spreads']->deleteSpreadsheet( $spreadsheet_id ) );
	}
}
