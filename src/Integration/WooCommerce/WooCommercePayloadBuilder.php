<?php
/**
 * Structured WooCommerce trigger payloads for the builder variable picker.
 *
 * @package WorkflowAutomate\Plugin
 */

declare(strict_types=1);

namespace WorkflowAutomate\Plugin\Integration\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes WooCommerce objects into JSON-friendly arrays with rich fields
 * (aligned with common automation integrations such as Bit Flows).
 */
class WooCommercePayloadBuilder {

	/**
	 * @param string $event
	 * @param int    $order_id
	 * @param mixed  $order
	 *
	 * @return array<string, mixed>
	 */
	public static function order( string $event, int $order_id, $order = null ): array {
		$payload = array(
			'source' => 'woocommerce',
			'event' => $event,
			'order_id' => $order_id,
			'id' => $order_id,
		);

		$order = self::resolveOrder( $order_id, $order );

		if ( null === $order ) {
			return $payload;
		}

		$data = array_merge(
			$payload,
			array(
				'order_key' => self::callString( $order, 'get_order_key' ),
				'cart_tax' => self::callString( $order, 'get_cart_tax' ),
				'currency' => self::callString( $order, 'get_currency' ),
				'discount_tax' => self::callString( $order, 'get_discount_tax' ),
				'discount_total' => self::callString( $order, 'get_discount_total' ),
				'shipping_tax' => self::callString( $order, 'get_shipping_tax' ),
				'shipping_total' => self::callString( $order, 'get_shipping_total' ),
				'total' => self::callString( $order, 'get_total' ),
				'subtotal' => self::callString( $order, 'get_subtotal' ),
				'total_refunded' => self::callString( $order, 'get_total_refunded' ),
				'remaining_refund_amount' => self::callString( $order, 'get_remaining_refund_amount' ),
				'shipping_method' => self::callString( $order, 'get_shipping_method' ),
				'date_created' => self::callDate( $order, 'get_date_created' ),
				'date_modified' => self::callDate( $order, 'get_date_modified' ),
				'date_completed' => self::callDate( $order, 'get_date_completed' ),
				'date_paid' => self::callDate( $order, 'get_date_paid' ),
				'customer_id' => self::callInt( $order, 'get_customer_id' ),
				'created_via' => self::callString( $order, 'get_created_via' ),
				'status' => self::callString( $order, 'get_status' ),
				'billing_first_name' => self::callString( $order, 'get_billing_first_name' ),
				'billing_last_name' => self::callString( $order, 'get_billing_last_name' ),
				'billing_company' => self::callString( $order, 'get_billing_company' ),
				'billing_address_1' => self::callString( $order, 'get_billing_address_1' ),
				'billing_address_2' => self::callString( $order, 'get_billing_address_2' ),
				'billing_city' => self::callString( $order, 'get_billing_city' ),
				'billing_state' => self::callString( $order, 'get_billing_state' ),
				'billing_postcode' => self::callString( $order, 'get_billing_postcode' ),
				'billing_country' => self::callString( $order, 'get_billing_country' ),
				'billing_email' => self::callString( $order, 'get_billing_email' ),
				'billing_phone' => self::callString( $order, 'get_billing_phone' ),
				'shipping_first_name' => self::callString( $order, 'get_shipping_first_name' ),
				'shipping_last_name' => self::callString( $order, 'get_shipping_last_name' ),
				'shipping_company' => self::callString( $order, 'get_shipping_company' ),
				'shipping_address_1' => self::callString( $order, 'get_shipping_address_1' ),
				'shipping_address_2' => self::callString( $order, 'get_shipping_address_2' ),
				'shipping_city' => self::callString( $order, 'get_shipping_city' ),
				'shipping_state' => self::callString( $order, 'get_shipping_state' ),
				'shipping_postcode' => self::callString( $order, 'get_shipping_postcode' ),
				'shipping_country' => self::callString( $order, 'get_shipping_country' ),
				'payment_method' => self::callString( $order, 'get_payment_method' ),
				'payment_method_title' => self::callString( $order, 'get_payment_method_title' ),
				'customer_note' => self::callString( $order, 'get_customer_note' ),
				'checkout_order_received_url' => self::callString( $order, 'get_checkout_order_received_url' ),
				'coupon_codes' => self::orderCouponCodes( $order ),
				'line_items' => self::lineItems( $order ),
				'items' => self::lineItems( $order ),
			)
		);

		if ( defined( 'WC_VERSION' ) && version_compare( (string) WC_VERSION, '8.5.1', '>=' ) && method_exists( $order, 'get_meta' ) ) {
			$data['_wc_order_attribution_referrer'] = (string) $order->get_meta( '_wc_order_attribution_referrer' );
			$data['_wc_order_attribution_user_agent'] = (string) $order->get_meta( '_wc_order_attribution_user_agent' );
			$data['_wc_order_attribution_utm_source'] = (string) $order->get_meta( '_wc_order_attribution_utm_source' );
			$data['_wc_order_attribution_device_type'] = (string) $order->get_meta( '_wc_order_attribution_device_type' );
			$data['_wc_order_attribution_source_type'] = (string) $order->get_meta( '_wc_order_attribution_source_type' );
		}

		return $data;
	}

	/**
	 * @param string $event
	 * @param int    $product_id
	 * @param mixed  $product
	 *
	 * @return array<string, mixed>
	 */
	public static function product( string $event, int $product_id, $product = null ): array {
		$payload = array(
			'source' => 'woocommerce',
			'event' => $event,
			'product_id' => $product_id,
		);

		$product = self::resolveProduct( $product_id, $product );

		if ( null === $product ) {
			return $payload;
		}

		$image_id = method_exists( $product, 'get_image_id' ) ? (int) $product->get_image_id() : 0;
		$gallery  = array();

		if ( method_exists( $product, 'get_gallery_image_ids' ) ) {
			foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
				$url = wp_get_attachment_image_url( (int) $gallery_id, 'full' );

				if ( is_string( $url ) && '' !== $url ) {
					$gallery[] = $url;
				}
			}
		}

		$categories = function_exists( 'wc_get_product_category_list' )
			? wp_strip_all_tags( (string) wc_get_product_category_list( $product_id ) )
			: '';

		return array_merge(
			$payload,
			array(
				'name' => self::callString( $product, 'get_name' ),
				'product_title' => self::callString( $product, 'get_name' ),
				'product_content' => self::callString( $product, 'get_description' ),
				'product_excerpt' => self::callString( $product, 'get_short_description' ),
				'status' => self::callString( $product, 'get_status' ),
				'product_status' => self::callString( $product, 'get_status' ),
				'type' => self::callString( $product, 'get_type' ),
				'product_type' => self::callString( $product, 'get_type' ),
				'sku' => self::callString( $product, 'get_sku' ),
				'price' => self::callString( $product, 'get_price' ),
				'regular_price' => self::callString( $product, 'get_regular_price' ),
				'sale_price' => self::callString( $product, 'get_sale_price' ),
				'stock_status' => self::callString( $product, 'get_stock_status' ),
				'stock_quantity' => method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null,
				'manage_stock' => self::callBool( $product, 'get_manage_stock' ),
				'backorders' => self::callString( $product, 'get_backorders' ),
				'sold_individually' => self::callBool( $product, 'get_sold_individually' ),
				'featured' => self::callBool( $product, 'get_featured' ),
				'catalog_visibility' => self::callString( $product, 'get_catalog_visibility' ),
				'weight' => self::callString( $product, 'get_weight' ),
				'length' => self::callString( $product, 'get_length' ),
				'width' => self::callString( $product, 'get_width' ),
				'height' => self::callString( $product, 'get_height' ),
				'virtual' => self::callBool( $product, 'get_virtual' ),
				'downloadable' => self::callBool( $product, 'get_downloadable' ),
				'tax_status' => self::callString( $product, 'get_tax_status' ),
				'tax_class' => self::callString( $product, 'get_tax_class' ),
				'purchase_note' => self::callString( $product, 'get_purchase_note' ),
				'menu_order' => self::callInt( $product, 'get_menu_order' ),
				'date_created' => self::callDate( $product, 'get_date_created' ),
				'date_modified' => self::callDate( $product, 'get_date_modified' ),
				'date_on_sale_from' => self::callDate( $product, 'get_date_on_sale_from' ),
				'date_on_sale_to' => self::callDate( $product, 'get_date_on_sale_to' ),
				'product_url' => function_exists( 'get_permalink' ) ? (string) get_permalink( $product_id ) : '',
				'product_image' => $image_id > 0 ? (string) wp_get_attachment_image_url( $image_id, 'full' ) : '',
				'product_gallery' => $gallery,
				'product_category' => $categories,
				'tag_ids' => method_exists( $product, 'get_tag_ids' ) ? array_map( 'intval', (array) $product->get_tag_ids() ) : array(),
			)
		);
	}

	/**
	 * @param string $event
	 * @param int    $coupon_id
	 * @param mixed  $coupon
	 *
	 * @return array<string, mixed>
	 */
	public static function coupon( string $event, int $coupon_id, $coupon = null ): array {
		$payload = array(
			'source' => 'woocommerce',
			'event' => $event,
			'coupon_id' => $coupon_id,
		);

		$coupon = self::resolveCoupon( $coupon_id, $coupon );

		if ( null === $coupon ) {
			return $payload;
		}

		$data = method_exists( $coupon, 'get_data' ) ? (array) $coupon->get_data() : array();

		return array_merge(
			$payload,
			array(
				'coupon_code' => self::callString( $coupon, 'get_code' ),
				'code' => self::callString( $coupon, 'get_code' ),
				'coupon_amount' => self::callString( $coupon, 'get_amount' ),
				'amount' => self::callString( $coupon, 'get_amount' ),
				'coupon_status' => isset( $data['status'] ) ? (string) $data['status'] : '',
				'discount_type' => self::callString( $coupon, 'get_discount_type' ),
				'description' => self::callString( $coupon, 'get_description' ),
				'date_created' => self::callDate( $coupon, 'get_date_created' ),
				'date_modified' => self::callDate( $coupon, 'get_date_modified' ),
				'date_expires' => self::callDate( $coupon, 'get_date_expires' ),
				'usage_count' => self::callInt( $coupon, 'get_usage_count' ),
				'usage_limit' => self::callInt( $coupon, 'get_usage_limit' ),
				'usage_limit_per_user' => self::callInt( $coupon, 'get_usage_limit_per_user' ),
				'limit_usage_to_x_items' => self::callInt( $coupon, 'get_limit_usage_to_x_items' ),
				'free_shipping' => self::callBool( $coupon, 'get_free_shipping' ),
				'exclude_sale_items' => self::callBool( $coupon, 'get_exclude_sale_items' ),
				'minimum_amount' => self::callString( $coupon, 'get_minimum_amount' ),
				'maximum_amount' => self::callString( $coupon, 'get_maximum_amount' ),
				'virtual' => self::callBool( $coupon, 'get_virtual' ),
				'individual_use' => self::callBool( $coupon, 'get_individual_use' ),
				'product_ids' => method_exists( $coupon, 'get_product_ids' ) ? array_map( 'intval', (array) $coupon->get_product_ids() ) : array(),
				'excluded_product_ids' => method_exists( $coupon, 'get_excluded_product_ids' ) ? array_map( 'intval', (array) $coupon->get_excluded_product_ids() ) : array(),
				'product_categories' => method_exists( $coupon, 'get_product_categories' ) ? array_map( 'intval', (array) $coupon->get_product_categories() ) : array(),
				'excluded_product_categories' => method_exists( $coupon, 'get_excluded_product_categories' ) ? array_map( 'intval', (array) $coupon->get_excluded_product_categories() ) : array(),
				'email_restrictions' => method_exists( $coupon, 'get_email_restrictions' ) ? (array) $coupon->get_email_restrictions() : array(),
			)
		);
	}

	/**
	 * @param string               $event
	 * @param int                  $customer_id
	 * @param array<string, mixed> $extra
	 *
	 * @return array<string, mixed>
	 */
	public static function customer( string $event, int $customer_id, array $extra = array() ): array {
		$payload = array_merge(
			array(
				'source' => 'woocommerce',
				'event' => $event,
				'customer_id' => $customer_id,
			),
			$extra
		);

		$user = get_userdata( $customer_id );

		if ( is_object( $user ) ) {
			$payload['email'] = (string) $user->user_email;
			$payload['username'] = (string) $user->user_login;
			$payload['first_name'] = (string) $user->first_name;
			$payload['last_name'] = (string) $user->last_name;
			$payload['display_name'] = (string) $user->display_name;
			$payload['roles'] = array_values( (array) $user->roles );
			$payload['date_registered'] = (string) $user->user_registered;
		}

		if ( function_exists( 'wc_get_customer' ) ) {
			$customer = wc_get_customer( $customer_id );

			if ( is_object( $customer ) ) {
				$payload['billing_first_name'] = self::callString( $customer, 'get_billing_first_name' );
				$payload['billing_last_name'] = self::callString( $customer, 'get_billing_last_name' );
				$payload['billing_company'] = self::callString( $customer, 'get_billing_company' );
				$payload['billing_address_1'] = self::callString( $customer, 'get_billing_address_1' );
				$payload['billing_address_2'] = self::callString( $customer, 'get_billing_address_2' );
				$payload['billing_city'] = self::callString( $customer, 'get_billing_city' );
				$payload['billing_state'] = self::callString( $customer, 'get_billing_state' );
				$payload['billing_postcode'] = self::callString( $customer, 'get_billing_postcode' );
				$payload['billing_country'] = self::callString( $customer, 'get_billing_country' );
				$payload['billing_email'] = self::callString( $customer, 'get_billing_email' );
				$payload['billing_phone'] = self::callString( $customer, 'get_billing_phone' );
				$payload['shipping_first_name'] = self::callString( $customer, 'get_shipping_first_name' );
				$payload['shipping_last_name'] = self::callString( $customer, 'get_shipping_last_name' );
				$payload['shipping_company'] = self::callString( $customer, 'get_shipping_company' );
				$payload['shipping_address_1'] = self::callString( $customer, 'get_shipping_address_1' );
				$payload['shipping_address_2'] = self::callString( $customer, 'get_shipping_address_2' );
				$payload['shipping_city'] = self::callString( $customer, 'get_shipping_city' );
				$payload['shipping_state'] = self::callString( $customer, 'get_shipping_state' );
				$payload['shipping_postcode'] = self::callString( $customer, 'get_shipping_postcode' );
				$payload['shipping_country'] = self::callString( $customer, 'get_shipping_country' );
				$payload['is_paying_customer'] = self::callBool( $customer, 'get_is_paying_customer' );
				$payload['order_count'] = self::callInt( $customer, 'get_order_count' );
				$payload['total_spent'] = self::callString( $customer, 'get_total_spent' );
			}
		}

		return $payload;
	}

	/**
	 * @param array<string, mixed> $context
	 *
	 * @return array<string, mixed>
	 */
	public static function cartItemAdded( array $context ): array {
		$product_id   = (int) ( $context['product_id'] ?? 0 );
		$variation_id = (int) ( $context['variation_id'] ?? 0 );
		$product      = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		$payload = array_merge(
			array(
				'source' => 'woocommerce',
				'event' => 'product_added_to_cart',
			),
			$context
		);

		if ( is_object( $product ) ) {
			$payload['product_name'] = self::callString( $product, 'get_name' );
			$payload['product_sku'] = self::callString( $product, 'get_sku' );
			$payload['product_price'] = self::callString( $product, 'get_price' );
			$payload['product_type'] = self::callString( $product, 'get_type' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$payload = self::enrichCartSnapshot( $payload );
		}

		unset( $variation_id );

		return $payload;
	}

	/**
	 * @param array<string, mixed> $context
	 *
	 * @return array<string, mixed>
	 */
	public static function cartItemRemoved( array $context ): array {
		$payload = array_merge(
			array(
				'source' => 'woocommerce',
				'event' => 'product_removed_from_cart',
			),
			$context
		);

		$product_id = (int) ( $context['product_id'] ?? 0 );

		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( is_object( $product ) ) {
				$payload['product_name'] = self::callString( $product, 'get_name' );
				$payload['product_sku'] = self::callString( $product, 'get_sku' );
				$payload['product_price'] = self::callString( $product, 'get_price' );
			}
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$payload = self::enrichCartSnapshot( $payload );
		}

		return $payload;
	}

	/**
	 * Recalculates cart totals and attaches current cart summary fields.
	 *
	 * `woocommerce_add_to_cart` fires before WooCommerce runs calculate_totals(),
	 * so subtotal/total would otherwise exclude the item just added.
	 *
	 * @param array<string, mixed> $payload
	 *
	 * @return array<string, mixed>
	 */
	private static function enrichCartSnapshot( array $payload ): array {
		$cart = WC()->cart;

		if ( ! is_object( $cart ) ) {
			return $payload;
		}

		$cart->calculate_totals();

		$payload['cart_total'] = (string) $cart->get_cart_contents_total();
		$payload['cart_subtotal'] = (string) $cart->get_subtotal();
		$payload['cart_tax'] = (string) $cart->get_cart_contents_tax();
		$payload['cart_grand_total'] = (string) $cart->get_total( 'edit' );
		$payload['cart_item_count'] = (int) $cart->get_cart_contents_count();
		$payload['cart_line_count'] = count( $cart->get_cart() );

		$line_items = array();

		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$data = isset( $item['data'] ) && is_object( $item['data'] ) ? $item['data'] : null;

			$line_items[] = array(
				'cart_item_key' => (string) $cart_item_key,
				'product_id' => isset( $item['product_id'] ) ? (int) $item['product_id'] : 0,
				'variation_id' => isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
				'quantity' => isset( $item['quantity'] ) ? $item['quantity'] : 0,
				'product_name' => is_object( $data ) && method_exists( $data, 'get_name' ) ? (string) $data->get_name() : '',
				'product_unit_price' => is_object( $data ) && method_exists( $data, 'get_price' ) ? (string) $data->get_price() : '',
				'line_subtotal' => isset( $item['line_subtotal'] ) ? (string) $item['line_subtotal'] : '',
				'line_total' => isset( $item['line_total'] ) ? (string) $item['line_total'] : '',
			);
		}

		$payload['cart_line_items'] = $line_items;

		$added_key = isset( $payload['cart_item_key'] ) ? (string) $payload['cart_item_key'] : '';

		if ( '' !== $added_key ) {
			foreach ( $line_items as $line_item ) {
				if ( $line_item['cart_item_key'] === $added_key ) {
					$payload['added_line_subtotal'] = $line_item['line_subtotal'];
					$payload['added_line_total'] = $line_item['line_total'];
					break;
				}
			}
		}

		return $payload;
	}

	/**
	 * @param int   $order_id
	 * @param mixed $order
	 *
	 * @return object|null
	 */
	private static function resolveOrder( int $order_id, $order ): ?object {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return $order;
		}

		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$loaded = wc_get_order( $order_id );

			return is_object( $loaded ) ? $loaded : null;
		}

		return null;
	}

	/**
	 * @param int   $product_id
	 * @param mixed $product
	 *
	 * @return object|null
	 */
	private static function resolveProduct( int $product_id, $product ) {
		if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return $product;
		}

		if ( $product_id > 0 && function_exists( 'wc_get_product' ) ) {
			$loaded = wc_get_product( $product_id );

			return is_object( $loaded ) ? $loaded : null;
		}

		return null;
	}

	/**
	 * @param int   $coupon_id
	 * @param mixed $coupon
	 *
	 * @return object|null
	 */
	private static function resolveCoupon( int $coupon_id, $coupon ) {
		if ( is_object( $coupon ) && method_exists( $coupon, 'get_id' ) ) {
			return $coupon;
		}

		if ( $coupon_id > 0 && class_exists( '\WC_Coupon', false ) ) {
			return new \WC_Coupon( $coupon_id );
		}

		return null;
	}

	/**
	 * @param object $order
	 *
	 * @return array<int, array<string, mixed>>
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

			$product_id = method_exists( $item, 'get_product_id' ) ? (int) $item->get_product_id() : 0;
			$product    = ( $product_id > 0 && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;

			$row = array(
				'product_id' => $product_id,
				'variation_id' => method_exists( $item, 'get_variation_id' ) ? (int) $item->get_variation_id() : 0,
				'name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
				'product_name' => method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '',
				'quantity' => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : 0,
				'subtotal' => method_exists( $item, 'get_subtotal' ) ? (string) $item->get_subtotal() : '',
				'total' => method_exists( $item, 'get_total' ) ? (string) $item->get_total() : '',
				'subtotal_tax' => method_exists( $item, 'get_subtotal_tax' ) ? (string) $item->get_subtotal_tax() : '',
				'tax_class' => method_exists( $item, 'get_tax_class' ) ? (string) $item->get_tax_class() : '',
			);

			if ( is_object( $product ) ) {
				$row['product_sku'] = self::callString( $product, 'get_sku' );
				$row['product_unit_price'] = self::callString( $product, 'get_price' );
				$row['tax_status'] = self::callString( $product, 'get_tax_status' );
			}

			$items[] = $row;
		}

		return $items;
	}

	/**
	 * @param object $order
	 *
	 * @return array<int, string>
	 */
	private static function orderCouponCodes( object $order ): array {
		if ( ! method_exists( $order, 'get_coupon_codes' ) ) {
			return array();
		}

		$codes = $order->get_coupon_codes();

		return is_array( $codes ) ? array_values( array_map( 'strval', $codes ) ) : array();
	}

	/**
	 * @param object $object
	 * @param string $method
	 *
	 * @return string
	 */
	private static function callString( object $object, string $method ): string {
		return method_exists( $object, $method ) ? (string) $object->{$method}() : '';
	}

	/**
	 * @param object $object
	 * @param string $method
	 *
	 * @return int
	 */
	private static function callInt( object $object, string $method ): int {
		return method_exists( $object, $method ) ? (int) $object->{$method}() : 0;
	}

	/**
	 * @param object $object
	 * @param string $method
	 *
	 * @return bool
	 */
	private static function callBool( object $object, string $method ): bool {
		return method_exists( $object, $method ) ? (bool) $object->{$method}() : false;
	}

	/**
	 * @param object $object
	 * @param string $method
	 *
	 * @return string
	 */
	private static function callDate( object $object, string $method ): string {
		if ( ! method_exists( $object, $method ) ) {
			return '';
		}

		$date = $object->{$method}();

		if ( ! is_object( $date ) || ! method_exists( $date, 'date' ) ) {
			return '';
		}

		return (string) $date->date( 'Y-m-d H:i:s' );
	}
}
