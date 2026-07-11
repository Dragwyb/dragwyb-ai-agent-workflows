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
			case 'product_stock_status_updated':
				$this->bindProductStatusUpdated( $on_fire, $config );
				break;
			case 'product_delete':
				$this->bindProductDelete( $on_fire, $config );
				break;
			case 'product_restore':
				$this->bindProductRestore( $on_fire, $config );
				break;
			case 'product_status_changed':
				$this->bindProductStatusChanged( $on_fire, $config );
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
	 * @param callable             $on_fire
	 * @param array<string, mixed> $config
	 *
	 * @return void
	 */
	private function bindProductStatusUpdated( callable $on_fire, array $config ): void {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) use ( $on_fire, $config ): void {
				if (
					! self::isWooCommerceActive()
					|| ! self::isProductPost( $post )
					|| (string) $new_status === (string) $old_status
					|| ! is_object( $post )
					|| ! isset( $post->post_status )
					|| (string) $post->post_status !== (string) $new_status
				) {
					return;
				}

				$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

				if ( $product_id <= 0 ) {
					return;
				}

				$payload = WooCommercePayloadBuilder::product( 'product_status_updated', $product_id, null );
				$payload['old_status'] = (string) $old_status;
				$payload['new_status'] = (string) $new_status;

				$on_fire( $payload, $config );
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
	private function bindProductStatusChanged( callable $on_fire, array $config ): void {
		add_action(
			'transition_post_status',
			static function ( $new_status, $old_status, $post ) use ( $on_fire, $config ): void {
				if (
					! self::isWooCommerceActive()
					|| ! self::isProductPost( $post )
					|| (string) $new_status === (string) $old_status
				) {
					return;
				}

				$product_id = isset( $post->ID ) ? (int) $post->ID : 0;

				if ( $product_id <= 0 ) {
					return;
				}

				$payload = WooCommercePayloadBuilder::product( 'product_status_changed', $product_id, null );
				$payload['old_status'] = (string) $old_status;
				$payload['new_status'] = (string) $new_status;

				$on_fire( $payload, $config );
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
		return is_object( $post ) && isset( $post->post_type ) && 'product' === (string) $post->post_type;
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
