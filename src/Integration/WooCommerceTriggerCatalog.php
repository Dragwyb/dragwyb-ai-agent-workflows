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
				'label'       => __( 'Order Status Set to Pending', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to pending.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_pending',
				'binder'      => 'order_status',
				'status'      => 'pending',
			),
			array(
				'slug'        => 'woocommerce_order_status_failed_trigger',
				'label'       => __( 'Order Status Set to Failed', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to failed.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_failed',
				'binder'      => 'order_status',
				'status'      => 'failed',
			),
			array(
				'slug'        => 'woocommerce_order_status_on_hold_trigger',
				'label'       => __( 'Order Status Set to On-hold', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to on-hold.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_on_hold',
				'binder'      => 'order_status',
				'status'      => 'on-hold',
			),
			array(
				'slug'        => 'woocommerce_order_status_processing_trigger',
				'label'       => __( 'Order Status Set to Processing', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to processing.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_processing',
				'binder'      => 'order_status',
				'status'      => 'processing',
			),
			array(
				'slug'        => 'woocommerce_order_completed_trigger',
				'label'       => __( 'Order Status Set to Completed', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to completed.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_completed',
				'binder'      => 'order_status',
				'status'      => 'completed',
			),
			array(
				'slug'        => 'woocommerce_order_status_refunded_trigger',
				'label'       => __( 'Order Status Set to Refunded', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to refunded.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_refunded',
				'binder'      => 'order_status',
				'status'      => 'refunded',
			),
			array(
				'slug'        => 'woocommerce_order_status_cancelled_trigger',
				'label'       => __( 'Order Status Set to Cancelled', 'ai-agent-workflow-automation' ),
				'description' => __( 'Starts the workflow when a WooCommerce order is set to cancelled.', 'ai-agent-workflow-automation' ),
				'event'       => 'order_status_cancelled',
				'binder'      => 'order_status',
				'status'      => 'cancelled',
			),
		);

		return array_merge(
			array(
				array(
					'slug'        => 'woocommerce_new_order_trigger',
					'label'       => __( 'On New Order Create', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a new WooCommerce order is placed (classic checkout, Checkout Blocks, or admin).', 'ai-agent-workflow-automation' ),
					'event'       => 'order_created',
					'binder'      => 'order_new',
				),
				array(
					'slug'        => 'woocommerce_restore_order_trigger',
					'label'       => __( 'Restore Order', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce order is restored from the trash.', 'ai-agent-workflow-automation' ),
					'event'       => 'order_restored',
					'binder'      => 'order_restore',
				),
				array(
					'slug'        => 'woocommerce_new_coupon_trigger',
					'label'       => __( 'New Coupon Created', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a new WooCommerce coupon is created.', 'ai-agent-workflow-automation' ),
					'event'       => 'coupon_created',
					'binder'      => 'coupon_created',
				),
				array(
					'slug'        => 'woocommerce_create_customer_trigger',
					'label'       => __( 'Create Customer', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a new WooCommerce customer is created.', 'ai-agent-workflow-automation' ),
					'event'       => 'customer_created',
					'binder'      => 'customer_created',
				),
				array(
					'slug'        => 'woocommerce_update_customer_trigger',
					'label'       => __( 'Update Customer', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce customer is updated.', 'ai-agent-workflow-automation' ),
					'event'       => 'customer_updated',
					'binder'      => 'customer_updated',
				),
				array(
					'slug'        => 'woocommerce_delete_customer_trigger',
					'label'       => __( 'Delete Customer', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce customer is deleted.', 'ai-agent-workflow-automation' ),
					'event'       => 'customer_deleted',
					'binder'      => 'customer_deleted',
				),
				array(
					'slug'        => 'woocommerce_create_product_trigger',
					'label'       => __( 'Create Product', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a new WooCommerce product is published for the first time.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_created',
					'binder'      => 'product_created',
				),
				array(
					'slug'        => 'woocommerce_update_product_trigger',
					'label'       => __( 'Update Product', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is updated.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_updated',
					'binder'      => 'product_updated',
				),
				array(
					'slug'        => 'woocommerce_product_status_updated_trigger',
					'label'       => __( 'Product Status Updated', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product post status changes (publish, draft, pending, etc.).', 'ai-agent-workflow-automation' ),
					'event'       => 'product_status_updated',
					'binder'      => 'product_post_status_updated',
				),
				array(
					'slug'        => 'woocommerce_product_stock_status_updated_trigger',
					'label'       => __( 'Product Stock Status Updated', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product stock status changes (in stock, out of stock, on backorder).', 'ai-agent-workflow-automation' ),
					'event'       => 'product_stock_status_updated',
					'binder'      => 'product_stock_status_updated',
				),
				array(
					'slug'        => 'woocommerce_delete_product_trigger',
					'label'       => __( 'Delete Product', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is trashed or permanently deleted.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_deleted',
					'binder'      => 'product_delete',
				),
				array(
					'slug'        => 'woocommerce_restore_product_trigger',
					'label'       => __( 'Restore Product', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product is restored from the trash.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_restored',
					'binder'      => 'product_restore',
				),
				array(
					'slug'        => 'woocommerce_product_status_changed_trigger',
					'label'       => __( 'Product Status Changed', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce product post status or stock status changes.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_status_changed',
					'binder'      => 'product_status_changed',
				),
				array(
					'slug'        => 'woocommerce_product_added_to_cart_trigger',
					'label'       => __( 'Product Added to Cart', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a product is added to the WooCommerce cart.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_added_to_cart',
					'binder'      => 'cart_item_added',
				),
				array(
					'slug'        => 'woocommerce_product_removed_from_cart_trigger',
					'label'       => __( 'Product Removed from Cart', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a product is removed from the WooCommerce cart.', 'ai-agent-workflow-automation' ),
					'event'       => 'product_removed_from_cart',
					'binder'      => 'cart_item_removed',
				),
			),
			$order_statuses,
			array(
				array(
					'slug'        => 'woocommerce_order_status_changed_trigger',
					'label'       => __( 'Order Status Changed', 'ai-agent-workflow-automation' ),
					'description' => __( 'Starts the workflow when a WooCommerce order status changes to any value.', 'ai-agent-workflow-automation' ),
					'event'       => 'order_status_changed',
					'binder'      => 'order_status_changed',
				),
			)
		);
	}
}
