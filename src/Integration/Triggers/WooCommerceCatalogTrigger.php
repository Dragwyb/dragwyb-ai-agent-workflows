<?php
/**
 * Catalog-defined WooCommerce hook trigger.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\Triggers;

use WorkflowAutomate\Plugin\Domain\Contracts\TriggerInterface;
use WorkflowAutomate\Plugin\Integration\WooCommerce\WooCommercePayloadBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds a curated WooCommerce event and emits a structured payload for
 * downstream actions (order id, product id, cart fields, etc.).
 */
class WooCommerceCatalogTrigger implements TriggerInterface {

	/** @var array<string, mixed> */
	private array $definition;

	/**
	 * Previous stock status loaded from the database before a product save.
	 *
	 * @var array<int, string>
	 */
	private static array $stock_status_before = array();

	/** @param array<string, mixed> $definition */
	public function __construct( array $definition ) {
		$this->definition = $definition;
	}

	public function slug(): string {
		return (string) $this->definition['slug'];
	}

	public function label(): string {
		return (string) $this->definition['label'];
	}

	public function description(): string {
		return (string) $this->definition['description'];
	}

	public function configSchema(): array {
		return array();
	}

	public function bind( array $config, callable $on_fire ): void {
		$binder = (string) ( $this->definition['binder'] ?? '' );

		switch ( $binder ) {
			case 'order_new':
				$this->bindOrderNew( $on_fire, $config );
				break;
			case 'order_restore':
				$this->bindOrderRestore( $on_fire, $config );
				break;
			case 'coupon_created':
				$this->bindCouponCreated( $on_fire, $config );
				break;
			case 'customer_created':
				$this->bindCustomerCreated( $on_fire, $config );
				break;
			case 'customer_updated':
				$this->bindCustomerUpdated( $on_fire, $config );
				break;
			case 'customer_deleted':
				$this->bindCustomerDeleted( $on_fire, $config );
				break;
			case 'product_created':
				$this->bindProductCreated( $on_fire, $config );
				break;
			case 'product_updated':
				$this->bindProductUpdated( $on_fire, $config );
				break;
			case 'product_post_status_updated':
				$this->bindProductPostStatusTransition( $on_fire, $config, 'product_status_updated' );
				break;
			case 'product_stock_status_updated':
				$this->bindProductStockStatusTransition( $on_fire, $config, 'product_stock_status_updated' );
				break;
			case 'product_delete':
				$this->bindProductDelete( $on_fire, $config );
				break;
			case 'product_restore':
				$this->bindProductRestore( $on_fire, $config );
				break;
			case 'product_status_changed':
				$this->bindProductPostStatusTransition( $on_fire, $config, 'product_status_changed' );
				$this->bindProductStockStatusTransition( $on_fire, $config, 'product_status_changed' );
				break;
			case 'cart_item_added':
				$this->bindCartItemAdded( $on_fire, $config );
				break;
			case 'cart_item_removed':
				$this->bindCartItemRemoved( $on_fire, $config );
				break;
			case 'order_status':
				$this->bindOrderStatus( $on_fire, $config );
				break;
			case 'order_status_changed':
				$this->bindOrderStatusChanged( $on_fire, $config );
				break;
		}
	}

	/**
	 * Storefront checkout (especially Checkout Blocks) often does not fire
	 * `woocommerce_new_order`. Listen on all reliable order-created hooks and
	 * dedupe by order id so one placement only triggers once.
	 *
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindOrderNew( callable $on_fire, array $config ): void {
		$seen = array();

		$fire = static function ( $order_id, $order = null ) use ( $on_fire, $config, &$seen ): void {
			if ( ! self::isWooCommerceActive() ) {
				return;
			}

			$order_id = (int) $order_id;

			if ( $order_id <= 0 || isset( $seen[ $order_id ] ) ) {
				return;
			}

			$seen[ $order_id ] = true;

			$on_fire( WooCommercePayloadBuilder::order( 'order_created', $order_id, $order ), $config );
		};

		add_action( 'woocommerce_new_order', $fire, 10, 2 );

		add_action(
			'woocommerce_checkout_order_processed',
			static function ( $order_id, $posted_data = null, $order = null ) use ( $fire ): void {
				unset( $posted_data );
				$fire( $order_id, $order );
			},
			10,
			3
		);

		add_action(
			'woocommerce_store_api_checkout_order_processed',
			static function ( $order ) use ( $fire ): void {
				if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
					$fire( (int) $order->get_id(), $order );
				}
			},
			10,
			1
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindOrderRestore( callable $on_fire, array $config ): void {
		add_action(
			'woocommerce_order_status_changed',
			static function ( $order_id, $old_status, $new_status, $order = null ) use ( $on_fire, $config ): void {
				if ( 'trash' !== (string) $old_status ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::order( 'order_restored', (int) $order_id, $order ), $config );
			},
			10,
			4
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCouponCreated( callable $on_fire, array $config ): void {
		add_action(
			'woocommerce_update_coupon',
			static function ( $coupon_id, $coupon = null ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::coupon( 'coupon_created', (int) $coupon_id, $coupon ), $config );
			},
			10,
			2
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCustomerCreated( callable $on_fire, array $config ): void {
		add_action(
			'user_register',
			static function ( $customer_id, $userdata = array() ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() ) {
					return;
				}

				$customer_id = (int) $customer_id;

				if (
					! self::isCustomerUserdata( $userdata )
					&& ! self::userIsCustomer( $customer_id )
				) {
					return;
				}

				$on_fire(
					WooCommercePayloadBuilder::customer(
						'customer_created',
						(int) $customer_id,
						array(
							'customer_data' => is_array( $userdata ) ? $userdata : array(),
						)
					),
					$config
				);
			},
			10,
			2
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCustomerUpdated( callable $on_fire, array $config ): void {
		add_action(
			'profile_update',
			static function ( $customer_id, $old_user_data = array() ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() || ! self::userIsCustomer( (int) $customer_id ) ) {
					return;
				}

				$user = get_userdata( (int) $customer_id );
				$userdata = array();

				if ( is_object( $user ) ) {
					$userdata = array(
						'ID' => (int) $user->ID,
						'user_email' => (string) $user->user_email,
						'user_login' => (string) $user->user_login,
						'first_name' => (string) $user->first_name,
						'last_name' => (string) $user->last_name,
						'role' => in_array( 'customer', (array) $user->roles, true ) ? 'customer' : '',
					);
				}

				$payload = WooCommercePayloadBuilder::customer(
					'customer_updated',
					(int) $customer_id,
					array( 'customer_data' => $userdata )
				);

				if ( is_array( $old_user_data ) && array() !== $old_user_data ) {
					$payload['old_data'] = $old_user_data;
				}

				$on_fire( $payload, $config );
			},
			10,
			2
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCustomerDeleted( callable $on_fire, array $config ): void {
		add_action(
			'delete_user',
			static function ( $customer_id ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() || ! self::userIsCustomer( (int) $customer_id ) ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::customer( 'customer_deleted', (int) $customer_id ), $config );
			},
			10,
			1
		);
	}

	/**
	 * Fires when a product is published for the first time — not when the
	 * auto-draft is created by clicking "Add new product" in wp-admin.
	 *
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindProductCreated( callable $on_fire, array $config ): void {
		add_action(
			'wp_after_insert_post',
			static function ( $post_id, $post, $update, $post_before = null ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() || ! self::isProductPost( $post ) ) {
					return;
				}

				if ( ! is_object( $post ) || ! isset( $post->post_status ) || 'publish' !== (string) $post->post_status ) {
					return;
				}

				$prior_status = '';

				if ( is_object( $post_before ) && isset( $post_before->post_status ) ) {
					$prior_status = (string) $post_before->post_status;
				}

				// Already published — subsequent saves are "Update Product", not create.
				if ( 'publish' === $prior_status ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::product( 'product_created', (int) $post_id, null ), $config );
			},
			10,
			4
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindProductUpdated( callable $on_fire, array $config ): void {
		add_action(
			'wp_after_insert_post',
			static function ( $post_id, $post, $update, $post_before = null ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() || ! self::isProductPost( $post ) ) {
					return;
				}

				if ( empty( $post_before ) || 'auto-draft' === (string) $post_before->post_status ) {
					return;
				}

				$current_status = is_object( $post ) && isset( $post->post_status ) ? (string) $post->post_status : '';
				$prior_status   = (string) $post_before->post_status;

				// First publish is handled by the Create Product trigger.
				if ( 'publish' === $current_status && 'publish' !== $prior_status ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::product( 'product_updated', (int) $post_id, null ), $config );
			},
			10,
			4
		);
	}

	/**
	 * WordPress post status transition (publish, draft, pending, trash, etc.).
	 *
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 * @param string               $event
	 *
	 * @return void
	 */
	private function bindProductPostStatusTransition( callable $on_fire, array $config, string $event ): void {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) use ( $on_fire, $config, $event ): void {
				if (
					! self::isWooCommerceActive()
					|| ! self::isProductPost( $post )
					|| (string) $new_status === (string) $old_status
				) {
					return;
				}

				$product_id = self::productIdFromPost( $post );

				if ( $product_id <= 0 ) {
					return;
				}

				$payload = WooCommercePayloadBuilder::product( $event, $product_id, null );
				$payload['status_kind'] = 'post';
				$payload['old_status'] = (string) $old_status;
				$payload['new_status'] = (string) $new_status;

				$on_fire( $payload, $config );
			},
			10,
			3
		);
	}

	/**
	 * WooCommerce inventory stock status (instock, outofstock, onbackorder).
	 *
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 * @param string               $event
	 *
	 * @return void
	 */
	private function bindProductStockStatusTransition( callable $on_fire, array $config, string $event ): void {
		add_action(
			'woocommerce_before_product_object_save',
			static function ( $product ): void {
				if ( ! self::isWooCommerceActive() || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
					return;
				}

				$product_id = (int) $product->get_id();

				if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
					return;
				}

				$existing = wc_get_product( $product_id );

				if ( is_object( $existing ) && method_exists( $existing, 'get_stock_status' ) ) {
					self::$stock_status_before[ $product_id ] = (string) $existing->get_stock_status();
				}
			},
			5,
			1
		);

		$fire = static function ( $product_id, $new_stock_status, $product = null ) use ( $on_fire, $config, $event ): void {
			if ( ! self::isWooCommerceActive() ) {
				return;
			}

			$product_id = (int) $product_id;

			if ( $product_id <= 0 ) {
				return;
			}

			$new_stock_status = (string) $new_stock_status;
			$old_stock_status = self::$stock_status_before[ $product_id ] ?? '';

			unset( self::$stock_status_before[ $product_id ] );

			if ( '' !== $old_stock_status && $old_stock_status === $new_stock_status ) {
				return;
			}

			$payload = WooCommercePayloadBuilder::product( $event, $product_id, $product );
			$payload['status_kind'] = 'stock';
			$payload['old_stock_status'] = $old_stock_status;
			$payload['new_stock_status'] = $new_stock_status;
			$payload['old_status'] = $old_stock_status;
			$payload['new_status'] = $new_stock_status;

			$on_fire( $payload, $config );
		};

		add_action( 'woocommerce_product_set_stock_status', $fire, 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', $fire, 10, 3 );
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindProductDelete( callable $on_fire, array $config ): void {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) use ( $on_fire, $config ): void {
				if (
					! self::isWooCommerceActive()
					|| ! self::isProductPost( $post )
					|| 'trash' !== (string) $new_status
					|| 'new' === (string) $old_status
				) {
					return;
				}

				$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

				if ( $product_id <= 0 ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::product( 'product_deleted', $product_id, null ), $config );
			},
			10,
			3
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindProductRestore( callable $on_fire, array $config ): void {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) use ( $on_fire, $config ): void {
				if (
					! self::isWooCommerceActive()
					|| ! self::isProductPost( $post )
					|| 'trash' !== (string) $old_status
				) {
					return;
				}

				$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

				if ( $product_id <= 0 ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::product( 'product_restored', $product_id, null ), $config );
			},
			10,
			3
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCartItemAdded( callable $on_fire, array $config ): void {
		add_action(
			'woocommerce_add_to_cart',
			static function (
				$cart_item_key,
				$product_id,
				$quantity,
				$variation_id = 0,
				$variation = array(),
				$cart_item_data = array()
			) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() ) {
					return;
				}

				$on_fire(
					WooCommercePayloadBuilder::cartItemAdded(
						array(
							'cart_item_key' => (string) $cart_item_key,
							'product_id' => (int) $product_id,
							'variation_id' => (int) $variation_id,
							'quantity' => $quantity,
							'variation' => is_array( $variation ) ? $variation : array(),
							'cart_item_data' => is_array( $cart_item_data ) ? $cart_item_data : array(),
						)
					),
					$config
				);
			},
			10,
			6
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindCartItemRemoved( callable $on_fire, array $config ): void {
		add_action(
			'woocommerce_cart_item_removed',
			static function ( $cart_item_key, $cart ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() ) {
					return;
				}

				$context = array(
					'cart_item_key' => (string) $cart_item_key,
					'product_id' => 0,
					'variation_id' => 0,
					'quantity' => 0,
				);

				if ( is_object( $cart ) && method_exists( $cart, 'removed_cart_contents' ) ) {
					$removed = $cart->removed_cart_contents;

					if ( is_array( $removed ) && isset( $removed[ $cart_item_key ] ) && is_array( $removed[ $cart_item_key ] ) ) {
						$item = $removed[ $cart_item_key ];
						$context['product_id'] = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
						$context['variation_id'] = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
						$context['quantity'] = isset( $item['quantity'] ) ? $item['quantity'] : 0;
					}
				}

				$on_fire( WooCommercePayloadBuilder::cartItemRemoved( $context ), $config );
			},
			10,
			2
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindOrderStatus( callable $on_fire, array $config ): void {
		$status = (string) ( $this->definition['status'] ?? '' );
		$event  = (string) ( $this->definition['event'] ?? 'order_status_' . $status );
		$hook   = 'woocommerce_order_status_' . $status;

		add_action(
			$hook,
			static function ( $order_id, $order = null ) use ( $on_fire, $config, $event ): void {
				if ( ! self::isWooCommerceActive() || (int) $order_id <= 0 ) {
					return;
				}

				$on_fire( WooCommercePayloadBuilder::order( $event, (int) $order_id, $order ), $config );
			},
			10,
			2
		);
	}

	/**
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindOrderStatusChanged( callable $on_fire, array $config ): void {
		add_action(
			'woocommerce_order_status_changed',
			static function ( $order_id, $old_status, $new_status, $order = null ) use ( $on_fire, $config ): void {
				if ( ! self::isWooCommerceActive() || (int) $order_id <= 0 ) {
					return;
				}

				$payload = WooCommercePayloadBuilder::order( 'order_status_changed', (int) $order_id, $order );
				$payload['old_status'] = (string) $old_status;
				$payload['new_status'] = (string) $new_status;

				$on_fire( $payload, $config );
			},
			10,
			4
		);
	}

	/**
	 * @return bool
	 */
	private static function isWooCommerceActive(): bool {
		return class_exists( '\WooCommerce', false ) && function_exists( 'WC' );
	}

	/**
	 * @param mixed $post
	 *
	 * @return bool
	 */
	private static function isProductPost( $post ): bool {
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return false;
		}

		return in_array( (string) $post->post_type, array( 'product', 'product_variation' ), true );
	}

	/**
	 * @param mixed $post
	 *
	 * @return int
	 */
	private static function productIdFromPost( $post ): int {
		if ( ! is_object( $post ) || ! isset( $post->ID ) ) {
			return 0;
		}

		return (int) $post->ID;
	}

	/**
	 * @param mixed $userdata
	 *
	 * @return bool
	 */
	private static function isCustomerUserdata( $userdata ): bool {
		return is_array( $userdata ) && isset( $userdata['role'] ) && 'customer' === (string) $userdata['role'];
	}

	/**
	 * @param int $user_id
	 *
	 * @return bool
	 */
	private static function userIsCustomer( int $user_id ): bool {
		$user = get_userdata( $user_id );

		return is_object( $user ) && in_array( 'customer', (array) $user->roles, true );
	}
}
