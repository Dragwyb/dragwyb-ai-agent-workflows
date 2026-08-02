<?php
/**
 * Spreadsheet-level Google Sheets workflow actions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\GoogleSheet\Actions;

use AIAWA\Plugin\Integration\GoogleSheet\AbstractGoogleSheetsAction;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GoogleSheetsCreateSpreadsheetAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_create_spreadsheet_action';
	}

	public function label(): string {
		return __( 'Google Sheets Create Spreadsheet', 'ai-agent-workflow-automation' );
	}

	public function description(): string {
		return __( 'Creates a new Google spreadsheet. Pass values (and optional header_row) to write post/trigger data into the first sheet tab.', 'ai-agent-workflow-automation' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'title'         => array(
				'type'           => 'string',
				'label'          => __( 'Spreadsheet title', 'ai-agent-workflow-automation' ),
				'description'    => __( 'Name for the new spreadsheet file.', 'ai-agent-workflow-automation' ),
				'required'       => true,
				'agent_fillable' => true,
			),
			'sheet_title'   => array(
				'type'           => 'string',
				'label'          => __( 'First sheet tab name', 'ai-agent-workflow-automation' ),
				'default'        => 'Sheet1',
				'agent_fillable' => true,
			),
			'header_row'    => array(
				'type'           => 'string',
				'label'          => __( 'Header row', 'ai-agent-workflow-automation' ),
				'description'    => __( 'Optional comma-separated column headers (e.g. post_title,post_content,post_date).', 'ai-agent-workflow-automation' ),
				'agent_fillable' => true,
			),
			'values'        => $this->optionalValuesField(),
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
				'error'   => __( 'Spreadsheet title is required.', 'ai-agent-workflow-automation' ),
			);
		}

		$sheet_title = $this->requireSheetTitle( $config );
		$result      = $services['spreads']->createSpreadsheet( $title, $sheet_title );
		$formatted   = $this->formatResult( $result );

		if ( empty( $formatted['success'] ) ) {
			return $formatted;
		}

		$response       = is_array( $result['response'] ?? null ) ? $result['response'] : array();
		$spreadsheet_id = (string) ( $response['spreadsheetId'] ?? '' );

		if ( '' !== $spreadsheet_id ) {
			$formatted['spreadsheet_id'] = $spreadsheet_id;
		}

		if ( ! empty( $response['spreadsheetUrl'] ) ) {
			$formatted['spreadsheet_url'] = (string) $response['spreadsheetUrl'];
		}

		if ( '' === $spreadsheet_id ) {
			return $formatted;
		}

		$header_row = $this->parseValuesFromConfig( $config, 'header_row' );

		if ( array() !== $header_row ) {
			$header_result = $services['rows']->addRow( $spreadsheet_id, $sheet_title, $header_row );

			if ( empty( $header_result['success'] ) ) {
				$formatted['header_row_error'] = isset( $header_result['error'] )
					? (string) $header_result['error']
					: __( 'Failed to write the header row.', 'ai-agent-workflow-automation' );
			}
		}

		$values = $this->parseValuesFromConfig( $config, 'values' );

		if ( array() === $values ) {
			return $formatted;
		}

		$row_result = $services['rows']->addRow( $spreadsheet_id, $sheet_title, $values );

		if ( empty( $row_result['success'] ) ) {
			$formatted['row_error'] = isset( $row_result['error'] )
				? (string) $row_result['error']
				: __( 'Spreadsheet was created but the data row could not be written.', 'ai-agent-workflow-automation' );

			return $formatted;
		}

		if ( isset( $row_result['response']['updates']['updatedRange'] ) ) {
			$formatted['updated_range'] = (string) $row_result['response']['updates']['updatedRange'];
		}

		return $formatted;
	}
}

final class GoogleSheetsFindSpreadsheetsAction extends AbstractGoogleSheetsAction {

	public function slug(): string {
		return 'google_sheets_find_spreadsheets_action';
	}

	public function label(): string {
		return __( 'Google Sheets Find Spreadsheets', 'ai-agent-workflow-automation' );
	}

	public function description(): string {
		return __( 'Searches Google Drive for spreadsheets by name.', 'ai-agent-workflow-automation' );
	}

	public function configSchema(): array {
		return array(
			'connection_id' => $this->connectionField(),
			'title'         => array(
				'type'     => 'string',
				'label'    => __( 'Search title', 'ai-agent-workflow-automation' ),
				'required' => true,
			),
			'limit'         => array(
				'type'    => 'string',
				'label'   => __( 'Maximum results', 'ai-agent-workflow-automation' ),
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
				'error'   => __( 'Search title is required.', 'ai-agent-workflow-automation' ),
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
		return __( 'Google Sheets Delete Spreadsheet', 'ai-agent-workflow-automation' );
	}

	public function description(): string {
		return __( 'Permanently deletes a Google spreadsheet.', 'ai-agent-workflow-automation' );
	}

	public function configSchema(): array {
		return array(
			'connection_id'  => $this->connectionField(),
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
