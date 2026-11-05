<?php
/**
 * Storefront enforcement of contact mode: no purchasing, no cart/checkout, no price sorting,
 * no JSON-LD offers unless the price stays visible.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode;

use Gadgetim\Core\Support\Context;
use WC_Product;

/**
 * WooCommerce hooks (attached on woocommerce_loaded). wp-admin, WP-CLI and cron are left
 * alone so shop managers can still create manual (phone) orders for these products.
 */
final class Enforcement {

	/**
	 * Attaches the hooks.
	 */
	public static function register_hooks(): void {
		add_filter( 'woocommerce_is_purchasable', [ self::class, 'is_purchasable' ], 20, 2 );
		add_filter( 'woocommerce_variation_is_purchasable', [ self::class, 'is_purchasable' ], 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', [ self::class, 'add_to_cart_validation' ], 5, 2 );
		add_action( 'template_redirect', [ self::class, 'redirect_cart_and_checkout' ], 5 );
		add_filter( 'woocommerce_available_payment_gateways', [ self::class, 'available_payment_gateways' ], 50 );
		add_filter( 'woocommerce_catalog_orderby', [ self::class, 'catalog_orderby' ], 20 );
		add_filter( 'woocommerce_default_catalog_orderby', [ self::class, 'default_catalog_orderby' ], 20 );
		add_filter( 'woocommerce_structured_data_product', [ self::class, 'structured_data_product' ], 20, 2 );
	}

	/**
	 * Whether enforcement is active for this request (storefront, wc-ajax, Store API; never
	 * wp-admin, admin-ajax, WP-CLI or cron).
	 */
	public static function active(): bool {
		$active = ! is_admin() && ! Context::is_cli() && ! Context::is_cron();

		/**
		 * Filters whether contact-mode enforcement runs for the current request.
		 *
		 * @param bool $active Default: true outside wp-admin, CLI and cron.
		 */
		return (bool) apply_filters( 'gadgetim_contact_mode_enforce', $active );
	}

	/**
	 * woocommerce_is_purchasable / woocommerce_variation_is_purchasable.
	 *
	 * @param bool       $purchasable WooCommerce's answer.
	 * @param WC_Product $product     Product or variation.
	 */
	public static function is_purchasable( $purchasable, $product ): bool {
		if ( ! $purchasable || ! $product instanceof WC_Product || ! self::active() ) {
			return (bool) $purchasable;
		}
		return ! ContactMode::applies( $product );
	}

	/**
	 * woocommerce_add_to_cart_validation: refuses the product with a notice (covers the form
	 * handler, ?add-to-cart= links, ?wc-ajax=add_to_cart and the Store API).
	 *
	 * @param bool $passed     Validation so far.
	 * @param int  $product_id Product ID.
	 */
	public static function add_to_cart_validation( $passed, $product_id ): bool {
		if ( ! $passed || ! self::active() ) {
			return (bool) $passed;
		}
		$product_id = absint( $product_id );
		if ( ! $product_id || ! ContactMode::applies( $product_id ) ) {
			return true;
		}
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( self::notice_text(), 'error' );
		}
		return false;
	}

	/**
	 * The add-to-cart refusal text (also used by the theme for its toast).
	 */
	public static function notice_text(): string {
		/**
		 * Filters the notice shown when a contact-mode product is added to the cart.
		 *
		 * @param string $text Notice text.
		 */
		return (string) apply_filters(
			'gadgetim_contact_mode_notice',
			__( 'Online purchase is not available for this product. Please contact us for a price.', 'gadgetim-core' )
		);
	}

	/**
	 * template_redirect: with the global mode on, the cart and checkout pages redirect to the
	 * shop. The order-received and order-pay endpoints stay reachable for existing orders.
	 */
	public static function redirect_cart_and_checkout(): void {
		if ( ! self::active() || ! ContactMode::enabled() || ! function_exists( 'is_cart' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		$blocked = is_cart();
		if ( ! $blocked && is_checkout() ) {
			$blocked = ! is_wc_endpoint_url( 'order-pay' ) && ! is_wc_endpoint_url( 'order-received' );
		}
		if ( ! $blocked ) {
			return;
		}
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		if ( ! is_string( $shop ) || '' === $shop ) {
			$shop = home_url( '/' );
		}
		wp_safe_redirect( $shop, 302 );
		exit;
	}

	/**
	 * woocommerce_available_payment_gateways: no online payment while the global mode is on.
	 *
	 * @param array $gateways Available gateways.
	 */
	public static function available_payment_gateways( $gateways ): array {
		if ( ! is_array( $gateways ) || ! self::active() || ! ContactMode::enabled() ) {
			return is_array( $gateways ) ? $gateways : [];
		}
		return [];
	}

	/**
	 * woocommerce_catalog_orderby: price sorting is meaningless when prices are hidden.
	 *
	 * @param array $options Sorting options.
	 */
	public static function catalog_orderby( $options ): array {
		if ( ! is_array( $options ) ) {
			return [];
		}
		if ( self::active() && self::prices_hidden() ) {
			unset( $options['price'], $options['price-desc'] );
		}
		return $options;
	}

	/**
	 * woocommerce_default_catalog_orderby: fall back when the default sort is by price.
	 *
	 * @param string $orderby Default sort key.
	 */
	public static function default_catalog_orderby( $orderby ): string {
		$orderby = (string) $orderby;
		if ( self::active() && self::prices_hidden() && in_array( $orderby, [ 'price', 'price-desc' ], true ) ) {
			return 'menu_order';
		}
		return $orderby;
	}

	/**
	 * woocommerce_structured_data_product: drop offers unless the price is public.
	 *
	 * @param array      $markup  Product schema.
	 * @param WC_Product $product Product.
	 */
	public static function structured_data_product( $markup, $product ): array {
		if ( ! is_array( $markup ) ) {
			return [];
		}
		if ( $product instanceof WC_Product && ! ContactMode::offers_enabled( $product ) ) {
			unset( $markup['offers'] );
		}
		return $markup;
	}

	/**
	 * Global mode on and prices hidden.
	 */
	private static function prices_hidden(): bool {
		return ContactMode::enabled() && ! ContactMode::show_price();
	}
}
