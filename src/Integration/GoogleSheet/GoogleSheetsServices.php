<?php
/**
 * Google Sheets service factory.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\GoogleSheet;

use WorkflowAutomate\Plugin\Integration\GoogleSheet\Helpers\GoogleSheetCommons;
use WorkflowAutomate\Plugin\Service\ConnectionSecretResolver;
use WorkflowAutomate\Plugin\Service\ConnectionService;
use WorkflowAutomate\Plugin\Service\GoogleOAuthService;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds authenticated Google Sheets service instances for an action run.
 */
final class GoogleSheetsServices {

	private ConnectionSecretResolver $secrets;

	public function __construct( ConnectionService $connections, GoogleOAuthService $google_oauth ) {
		$this->secrets = new ConnectionSecretResolver( $connections, $google_oauth );
	}

	/**
	 * @return array{success: bool, error?: string}|array{commons: GoogleSheetCommons, spreadsheets: GoogleSpreadsheetService, sheets: GoogleSheetService, rows: GoogleRowService}
	 */
	public function forConnection( int $connection_id ) {
		$token = $this->secrets->resolveBearerSecret( $connection_id );

		if ( is_array( $token ) ) {
			return $token;
		}

		$http     = new GoogleSheetsHttpClient( (string) $token );
		$commons  = new GoogleSheetCommons( $http );
		$rows     = new GoogleRowService( $commons );
		$sheets   = new GoogleSheetService( $commons );
		$spreads  = new GoogleSpreadsheetService( $commons );

		return compact( 'commons', 'rows', 'sheets', 'spreads' );
	}
}
