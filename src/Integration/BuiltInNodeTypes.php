<?php
/**
 * Registers the plugin's own built-in node types.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration;

use WorkflowAutomate\Plugin\Integration\Actions\HttpRequestAction;
use WorkflowAutomate\Plugin\Integration\Actions\SendEmailAction;
use WorkflowAutomate\Plugin\Integration\Triggers\WooCommerceOrderCompletedTrigger;
use WorkflowAutomate\Plugin\Integration\Triggers\WpHookTrigger;
use WorkflowAutomate\Plugin\Service\ConnectionService;
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
 *
 * No longer a purely static helper (see roadmap item 5's original
 * version): `HttpRequestAction` now depends on `ConnectionService` (item
 * 12), so `Plugin::registerNodeTypes()` builds one instance of this class
 * from the container and hooks that instance's register() method instead
 * of the class's own static method. Third-party registrations are
 * unaffected — the `wfa/nodes/register` action's own signature (just
 * `$registry`) has not changed.
 *
 * Optional co-plugin integrations (roadmap item 15) are registered only
 * when that plugin is active — e.g. the WooCommerce order-completed
 * trigger never appears in the palette on a site without WooCommerce.
 */
class BuiltInNodeTypes {

	private ConnectionService $connections;

	public function __construct( ConnectionService $connections ) {
		$this->connections = $connections;
	}

	/**
	 * @param NodeTypeRegistry $registry The registry being populated.
	 *
	 * @return void
	 */
	public function register( NodeTypeRegistry $registry ): void {
		$registry->registerTrigger( new WpHookTrigger() );
		$registry->registerAction( new HttpRequestAction( $this->connections ) );
		$registry->registerAction( new SendEmailAction() );

		if ( self::isWooCommerceActive() ) {
			$registry->registerTrigger( new WooCommerceOrderCompletedTrigger() );
		}
	}

	/**
	 * Whether WooCommerce is loaded and available for integration.
	 *
	 * Checked at `wfa/nodes/register` time (`init`), by which point active
	 * plugins have already been loaded. Uses both the main class and the
	 * `WC()` helper so a partially-broken WooCommerce install does not
	 * register a trigger that cannot resolve orders.
	 *
	 * @return bool
	 */
	private static function isWooCommerceActive(): bool {
		return class_exists( '\WooCommerce', false ) && function_exists( 'WC' );
	}
}
