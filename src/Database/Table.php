<?php
/**
 * Table naming helper.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source of truth for the plugin's custom table names.
 */
class Table {

	public const PREFIX = 'dragwyb_af_';

	/**
	 * Builds the fully-qualified, `$wpdb`-prefixed table name.
	 *
	 * @param string $table Unprefixed table name, e.g. "workflows".
	 *
	 * @return string
	 */
	public static function name( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . self::PREFIX . $table;
	}
}
