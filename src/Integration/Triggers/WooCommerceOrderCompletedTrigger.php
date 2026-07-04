<?php
/**
 * WooCommerce "Order Completed" trigger.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Triggers;

use WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Starts a workflow when a WooCommerce order reaches the `completed`
 * status (`woocommerce_order_status_completed`).
 *
 * Roadmap item 15's first demand-driven integration: only registered when
 * WooCommerce is active (see `BuiltInNodeTypes`), so sites without
 * WooCommerce never see this node type in the builder palette. No
 * credentials are stored — WooCommerce is a co-installed WordPress plugin,
 * not a remote API.
 *
 * The trigger payload is a structured array (order id, totals, billing
 * fields, line items summary) rather than the raw hook argument list, so
 * later actions can read named keys without knowing WooCommerce's hook
 * signature.
 */
class WooCommerceOrderCompletedTrigger implements TriggerInterface {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'woocommerce_order_completed_trigger';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'WooCommerce Order Completed', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function description(): string {
		return __( 'Starts the workflow when a WooCommerce order is marked completed.', 'workflow-automate' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function configSchema(): array {
		// No configuration yet — "completed" is the event. A future
		// increment can add an order-status picker if demand appears for
		// "any status change" without inventing that surface now.
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function bind( array $config, callable $on_fire ): void {
		add_action(
			'woocommerce_order_status_completed',
			static function ( $order_id, $order = null ) use ( $on_fire, $config ): void {
				$on_fire( self::buildPayload( (int) $order_id, $order ), $config );
			},
			10,
			2
		);
	}

	/**
	 * Builds a JSON-friendly trigger payload from a WooCommerce order.
	 *
	 * Accepts either a `WC_Order` instance (what current WooCommerce passes
	 * as the second hook argument) or null/unknown (older callers, or a
	 * site where the order object could not be loaded) — in the latter
	 * case only `order_id` is guaranteed.
	 *
	 * @param int   $order_id Order id from the hook.
	 * @param mixed $order    Optional `WC_Order` (or compatible) instance.
	 *
	 * @return array<string, mixed>
	 */
	private static function buildPayload( int $order_id, $order ): array {
		$payload = array(
			'source' => 'woocommerce',
			'event' => 'order_completed',
			'order_id' => $order_id,
		);

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			// Prefer the live order object when the hook did not pass one.
			if ( function_exists( 'wc_get_order' ) ) {
				$order = wc_get_order( $order_id );
			}
		}

		if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			return $payload;
		}

		$payload['status'] = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : 'completed';
		$payload['currency'] = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';
		$payload['total'] = method_exists( $order, 'get_total' ) ? (string) $order->get_total() : '';
		$payload['subtotal'] = method_exists( $order, 'get_subtotal' ) ? (string) $order->get_subtotal() : '';
		$payload['customer_id'] = method_exists( $order, 'get_customer_id' ) ? (int) $order->get_customer_id() : 0;
		$payload['billing_email'] = method_exists( $order, 'get_billing_email' ) ? (string) $order->get_billing_email() : '';
		$payload['billing_first_name'] = method_exists( $order, 'get_billing_first_name' ) ? (string) $order->get_billing_first_name() : '';
		$payload['billing_last_name'] = method_exists( $order, 'get_billing_last_name' ) ? (string) $order->get_billing_last_name() : '';
		$payload['billing_phone'] = method_exists( $order, 'get_billing_phone' ) ? (string) $order->get_billing_phone() : '';
		$payload['payment_method'] = method_exists( $order, 'get_payment_method' ) ? (string) $order->get_payment_method() : '';
		$payload['order_key'] = method_exists( $order, 'get_order_key' ) ? (string) $order->get_order_key() : '';
		$payload['items'] = self::lineItems( $order );

		return $payload;
	}

	/**
	 * @param object $order Order-like object with get_items().
	 *
	 * @return array<int, array{product_id: int, variation_id: int, name: string, quantity: float|int|string, total: string}>
	 */
	private static function lineItems( object $order ): array {
		if ( ! method_exists( $order, 'get_items' ) ) {
			return array();
		}

		$items = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) ) {
				continue;
			}

			$items[] = array(
				'product_id' => method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0,
				'variation_id' => method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0,
				'name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
				'quantity' => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : 0,
				'total' => method_exists( $item, 'get_total' ) ? (string) $item->get_total() : '',
			);
		}

		return $items;
	}
}
