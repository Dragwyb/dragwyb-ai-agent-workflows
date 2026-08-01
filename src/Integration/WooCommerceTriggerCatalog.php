<?php
/**
 * WooCommerce trigger catalog — curated hooks for the builder palette.
 *
 * @package AIAWA\Plugin
 */

declare(strict_types=1);

namespace AIAWA\Plugin\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single source for WooCommerce integration triggers. Each entry is
 * instantiated as `WooCommerceCatalogTrigger` when WooCommerce is active.
 */
class WooCommerceTriggerCatalog {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions(): array {
		$order_statuses = array(
			array(
				'slug'        => 'woocommerce_order_status_pending_trigger',
				'label'       => __( 'Order Status Set to Pending', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to pending.', 'workflow-automate' ),
				'event'       => 'order_status_pending',
				'binder'      => 'order_status',
				'status'      => 'pending',
			),
			array(
				'slug'        => 'woocommerce_order_status_failed_trigger',
				'label'       => __( 'Order Status Set to Failed', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to failed.', 'workflow-automate' ),
				'event'       => 'order_status_failed',
				'binder'      => 'order_status',
				'status'      => 'failed',
			),
			array(
				'slug'        => 'woocommerce_order_status_on_hold_trigger',
				'label'       => __( 'Order Status Set to On-hold', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to on-hold.', 'workflow-automate' ),
				'event'       => 'order_status_on_hold',
				'binder'      => 'order_status',
				'status'      => 'on-hold',
			),
			array(
				'slug'        => 'woocommerce_order_status_processing_trigger',
				'label'       => __( 'Order Status Set to Processing', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to processing.', 'workflow-automate' ),
				'event'       => 'order_status_processing',
				'binder'      => 'order_status',
				'status'      => 'processing',
			),
			array(
				'slug'        => 'woocommerce_order_completed_trigger',
				'label'       => __( 'Order Status Set to Completed', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to completed.', 'workflow-automate' ),
				'event'       => 'order_completed',
				'binder'      => 'order_status',
				'status'      => 'completed',
			),
			array(
				'slug'        => 'woocommerce_order_status_refunded_trigger',
				'label'       => __( 'Order Status Set to Refunded', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to refunded.', 'workflow-automate' ),
				'event'       => 'order_status_refunded',
				'binder'      => 'order_status',
				'status'      => 'refunded',
			),
			array(
				'slug'        => 'woocommerce_order_status_cancelled_trigger',
				'label'       => __( 'Order Status Set to Cancelled', 'workflow-automate' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to cancelled.', 'workflow-automate' ),
				'event'       => 'order_status_cancelled',
				'binder'      => 'order_status',
				'status'      => 'cancelled',
			),
		);

		return array_merge(
			array(
				array(
					'slug'        => 'woocommerce_new_order_trigger',
					'label'       => __( 'On New Order Create', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a new WooCommerce order is placed (classic checkout, Checkout Blocks, or admin).', 'workflow-automate' ),
					'event'       => 'order_created',
					'binder'      => 'order_new',
				),
				array(
					'slug'        => 'woocommerce_restore_order_trigger',
					'label'       => __( 'Restore Order', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce order is restored from the trash.', 'workflow-automate' ),
					'event'       => 'order_restored',
					'binder'      => 'order_restore',
				),
				array(
					'slug'        => 'woocommerce_new_coupon_trigger',
					'label'       => __( 'New Coupon Created', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a new WooCommerce coupon is created.', 'workflow-automate' ),
					'event'       => 'coupon_created',
					'binder'      => 'coupon_created',
				),
				array(
					'slug'        => 'woocommerce_create_customer_trigger',
					'label'       => __( 'Create Customer', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a new WooCommerce customer is created.', 'workflow-automate' ),
					'event'       => 'customer_created',
					'binder'      => 'customer_created',
				),
				array(
					'slug'        => 'woocommerce_update_customer_trigger',
					'label'       => __( 'Update Customer', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce customer is updated.', 'workflow-automate' ),
					'event'       => 'customer_updated',
					'binder'      => 'customer_updated',
				),
				array(
					'slug'        => 'woocommerce_delete_customer_trigger',
					'label'       => __( 'Delete Customer', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce customer is deleted.', 'workflow-automate' ),
					'event'       => 'customer_deleted',
					'binder'      => 'customer_deleted',
				),
				array(
					'slug'        => 'woocommerce_create_product_trigger',
					'label'       => __( 'Create Product', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a new WooCommerce product is published for the first time.', 'workflow-automate' ),
					'event'       => 'product_created',
					'binder'      => 'product_created',
				),
				array(
					'slug'        => 'woocommerce_update_product_trigger',
					'label'       => __( 'Update Product', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is updated.', 'workflow-automate' ),
					'event'       => 'product_updated',
					'binder'      => 'product_updated',
				),
				array(
					'slug'        => 'woocommerce_product_status_updated_trigger',
					'label'       => __( 'Product Status Updated', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product post status changes (publish, draft, pending, etc.).', 'workflow-automate' ),
					'event'       => 'product_status_updated',
					'binder'      => 'product_post_status_updated',
				),
				array(
					'slug'        => 'woocommerce_product_stock_status_updated_trigger',
					'label'       => __( 'Product Stock Status Updated', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product stock status changes (in stock, out of stock, on backorder).', 'workflow-automate' ),
					'event'       => 'product_stock_status_updated',
					'binder'      => 'product_stock_status_updated',
				),
				array(
					'slug'        => 'woocommerce_delete_product_trigger',
					'label'       => __( 'Delete Product', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is trashed or permanently deleted.', 'workflow-automate' ),
					'event'       => 'product_deleted',
					'binder'      => 'product_delete',
				),
				array(
					'slug'        => 'woocommerce_restore_product_trigger',
					'label'       => __( 'Restore Product', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is restored from the trash.', 'workflow-automate' ),
					'event'       => 'product_restored',
					'binder'      => 'product_restore',
				),
				array(
					'slug'        => 'woocommerce_product_status_changed_trigger',
					'label'       => __( 'Product Status Changed', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce product post status or stock status changes.', 'workflow-automate' ),
					'event'       => 'product_status_changed',
					'binder'      => 'product_status_changed',
				),
				array(
					'slug'        => 'woocommerce_product_added_to_cart_trigger',
					'label'       => __( 'Product Added to Cart', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a product is added to the WooCommerce cart.', 'workflow-automate' ),
					'event'       => 'product_added_to_cart',
					'binder'      => 'cart_item_added',
				),
				array(
					'slug'        => 'woocommerce_product_removed_from_cart_trigger',
					'label'       => __( 'Product Removed from Cart', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a product is removed from the WooCommerce cart.', 'workflow-automate' ),
					'event'       => 'product_removed_from_cart',
					'binder'      => 'cart_item_removed',
				),
			),
			$order_statuses,
			array(
				array(
					'slug'        => 'woocommerce_order_status_changed_trigger',
					'label'       => __( 'Order Status Changed', 'workflow-automate' ),
					'description' => __( 'Starts the workflow when a WooCommerce order status changes to any value.', 'workflow-automate' ),
					'event'       => 'order_status_changed',
					'binder'      => 'order_status_changed',
				),
			)
		);
	}
}
