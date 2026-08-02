<?php
/**
 * Migration runner.
 *
 * @package DragwybAgentFlow\Plugin
 */

declare(strict_types=1);

namespace DragwybAgentFlow\Plugin\Database;

use DragwybAgentFlow\Plugin\Core\Options;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs any migration in a given ordered list that has not already been
 * applied on this site, tracking progress in a single option so that
 * calling run() repeatedly (e.g. defensively on every admin page load) is
 * cheap and side-effect free once the site is up to date.
 */
class MigrationRunner {

	private const APPLIED_OPTION = 'applied_migrations';

	/**
	 * Ordered list of migration class names to consider.
	 *
	 * @var array<int, class-string<Migration>>
	 */
	private $migrations;

	/**
	 * @param array<int, class-string<Migration>> $migrations Ordered migration class names.
	 */
	public function __construct( array $migrations ) {
		$this->migrations = $migrations;
	}

	/**
	 * Applies every migration in the list that has not run yet.
	 *
	 * @return void
	 */
	public function run(): void {
		$applied = (array) Options::get( self::APPLIED_OPTION, array() );

		foreach ( $this->migrations as $migration_class ) {
			if ( in_array( $migration_class, $applied, true ) ) {
				continue;
			}

			/** @var Migration $migration */
			$migration = new $migration_class();
			$migration->up();

			$applied[] = $migration_class;
			Options::update( self::APPLIED_OPTION, $applied );
		}
	}
}
