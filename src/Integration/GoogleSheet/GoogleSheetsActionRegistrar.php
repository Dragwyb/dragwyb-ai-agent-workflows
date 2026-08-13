<?php
/**
 * Registers all Google Sheets workflow actions.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\GoogleSheet;

use DragwybVisualAutomation\Plugin\Domain\Contracts\ActionInterface;
use DragwybVisualAutomation\Plugin\Integration\Actions\GoogleSheetsAppendRowAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateColumnAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateSheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateSpreadsheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteRowAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteSheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteSpreadsheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsAddRowAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsAppendOrUpdateRowAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsClearSheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCopySheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsExportSheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsFindSheetAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsFindSpreadsheetsAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsGetAllRowsAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsGetRowAction;
use DragwybVisualAutomation\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsUpdateRowAction;
use DragwybVisualAutomation\Plugin\Service\ConnectionService;
use DragwybVisualAutomation\Plugin\Service\GoogleOAuthService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Multiple action classes live in each file below; PSR-4 autoloading expects
// one class per filename, so load these bundles explicitly.
require_once __DIR__ . '/Actions/GoogleSheetsSpreadsheetActions.php';
require_once __DIR__ . '/Actions/GoogleSheetsSheetActions.php';
require_once __DIR__ . '/Actions/GoogleSheetsRowActions.php';

/**
 * Factory for every built-in Google Sheets action node type.
 */
final class GoogleSheetsActionRegistrar {

	/**
	 * @return ActionInterface[]
	 */
	public static function all( ConnectionService $connections, GoogleOAuthService $google_oauth ): array {
		return array(
			new GoogleSheetsCreateSpreadsheetAction( $connections, $google_oauth ),
			new GoogleSheetsFindSpreadsheetsAction( $connections, $google_oauth ),
			new GoogleSheetsDeleteSpreadsheetAction( $connections, $google_oauth ),
			new GoogleSheetsCreateSheetAction( $connections, $google_oauth ),
			new GoogleSheetsFindSheetAction( $connections, $google_oauth ),
			new GoogleSheetsCopySheetAction( $connections, $google_oauth ),
			new GoogleSheetsDeleteSheetAction( $connections, $google_oauth ),
			new GoogleSheetsClearSheetAction( $connections, $google_oauth ),
			new GoogleSheetsExportSheetAction( $connections, $google_oauth ),
			new GoogleSheetsAddRowAction( $connections, $google_oauth ),
			new GoogleSheetsAppendRowAction( $connections, $google_oauth ),
			new GoogleSheetsUpdateRowAction( $connections, $google_oauth ),
			new GoogleSheetsAppendOrUpdateRowAction( $connections, $google_oauth ),
			new GoogleSheetsGetRowAction( $connections, $google_oauth ),
			new GoogleSheetsGetAllRowsAction( $connections, $google_oauth ),
			new GoogleSheetsDeleteRowAction( $connections, $google_oauth ),
			new GoogleSheetsCreateColumnAction( $connections, $google_oauth ),
		);
	}
}
