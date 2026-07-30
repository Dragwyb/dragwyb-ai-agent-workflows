<?php
/**
 * Plugin uninstall handler.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Core;

use AIAWAB\Plugin\Database\SchemaMigrations;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Removes plugin data when the plugin is deleted, but only when the site
 * owner has explicitly opted in via the "remove data on uninstall" setting.
 *
 * This is a deliberate divergence from the competitor product studied during
 * this project's Phase One analysis (see internal development docs), which
 * removes all plugin data on every uninstall with no opt-out. Defaulting to
 * "keep data" avoids surprise data loss; the setting itself ships with the
 * Settings screen roadmap item.
 */
class Uninstaller {

	/**
	 * Option names (unprefixed) that belong to this plugin.
	 *
	 * Kept as an explicit list rather than a wildcard option lookup so that
	 * uninstall can never remove data belonging to another plugin.
	 *
	 * @var string[]
	 */
	private const OWNED_OPTIONS = array(
		'installed_at',
		'db_version',
		'applied_migrations',
		'remove_data_on_uninstall',
		'global_settings',
		'encryption_key_id',
	);

	/**
	 * Entry point called from the top-level uninstall.php file.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( ! Options::get( 'remove_data_on_uninstall', false ) ) {
			return;
		}

		self::dropTables();

		// Remove plugin caps from every role so a later reinstall starts
		// from a clean role state (activation will re-grant to administrator).
		Capabilities::revokeFromAllRoles();

		foreach ( self::OWNED_OPTIONS as $option ) {
			Options::delete( $option );
		}
	}

	/**
	 * Reverses every migration, in reverse application order, dropping all
	 * plugin-owned tables.
	 *
	 * @return void
	 */
	private static function dropTables(): void {
		foreach ( array_reverse( SchemaMigrations::all() ) as $migration_class ) {
			( new $migration_class() )->down();
		}
	}
}
