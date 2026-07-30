<?php
/**
 * Registers all WordPress workflow actions.
 *
 * @package AIAWAB\Plugin
 */

declare(strict_types=1);

namespace AIAWAB\Plugin\Integration\WordPress;

use AIAWAB\Plugin\Domain\Contracts\ActionInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory for every built-in WordPress action node type, built entirely
 * from `WordPressActionCatalog::definitions()`.
 */
final class WordPressActionRegistrar {

	/**
	 * @return ActionInterface[]
	 */
	public static function all(): array {
		$services = new WordPressServices();
		$actions  = array();

		foreach ( WordPressActionCatalog::definitions() as $definition ) {
			$actions[] = new WordPressCatalogAction( $definition, $services );
		}

		return $actions;
	}
}
