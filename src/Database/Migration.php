<?php
/**
 * Base migration contract.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Database;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One migration equals one schema change, applied via `up()` and reversible
 * via `down()`. Table creation should use `dbDelta()` (the WordPress-native
 * mechanism for safely creating/updating custom tables) rather than a custom
 * fluent schema-builder DSL.
 */
abstract class Migration {

	/**
	 * Applies the migration.
	 *
	 * @return void
	 */
	abstract public function up(): void;

	/**
	 * Reverses the migration. Only invoked when the site owner has opted in
	 * to full data removal on uninstall (see Uninstaller).
	 *
	 * @return void
	 */
	abstract public function down(): void;
}
