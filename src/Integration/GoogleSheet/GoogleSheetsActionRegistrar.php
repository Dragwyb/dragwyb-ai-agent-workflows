<?php
/**
 * Registers all Google Sheets workflow actions.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration\GoogleSheet;

use AIAWA\Plugin\Domain\Contracts\ActionInterface;
use AIAWA\Plugin\Integration\Actions\GoogleSheetsAppendRowAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateColumnAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateSheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCreateSpreadsheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteRowAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteSheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsDeleteSpreadsheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsAddRowAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsAppendOrUpdateRowAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsClearSheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsCopySheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsExportSheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsFindSheetAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsFindSpreadsheetsAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsGetAllRowsAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsGetRowAction;
use AIAWA\Plugin\Integration\GoogleSheet\Actions\GoogleSheetsUpdateRowAction;
use AIAWA\Plugin\Service\ConnectionService;
use AIAWA\Plugin\Service\GoogleOAuthService;

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
