<?php
/**
 * Registers all WordPress workflow actions.
 *
 * @package DragwybVisualAutomation\Plugin
 */

declare(strict_types=1);

namespace DragwybVisualAutomation\Plugin\Integration\WordPress;

use DragwybVisualAutomation\Plugin\Domain\Contracts\ActionInterface;

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
