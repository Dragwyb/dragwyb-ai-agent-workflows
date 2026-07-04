<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration;

use WorkflowAutomate\Plugin\Integration\Actions\HttpRequestAction;
use WorkflowAutomate\Plugin\Integration\Triggers\WpHookTrigger;
use WorkflowAutomate\Plugin\Service\NodeTypeRegistry;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Listens on the public `wfa/nodes/register` action to add this plugin's
 * own built-in trigger/action node types.
 *
 * Deliberately uses the exact same extension point third-party code uses
 * (see docs/hooks-reference.md) rather than registering directly against
 * the container — this is what proves the extensibility mechanism actually
 * works, instead of only being documented.
 */
class BuiltInNodeTypes {

	/**
	 * @param NodeTypeRegistry $registry The registry being populated.
	 *
	 * @return void
	 */
	public static function register( NodeTypeRegistry $registry ): void {
		$registry->registerTrigger( new WpHookTrigger() );
		$registry->registerAction( new HttpRequestAction() );
	}
}
