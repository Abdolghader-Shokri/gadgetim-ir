<?php
/**
 * Order language and Persian line items with display-time translation (architecture §3.8, D12).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Product;
use WC_Product;
use WP_Term;

/**
 * _gtm_lang on the order is the single source of truth for its language. Line items store
 * the Persian product name (reports, exports, admin screens and admin e-mails read Persian);
 * customer-facing output (storefront, customer e-mails) translates item names and variation
 * attribute values at display time.
 */
final class Orders {

	public const META = '_gtm_lang';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'woocommerce_checkout_create_order', [ self::class, 'on_create_order' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ self::class, 'on_create_order' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ self::class, 'on_store_api_order' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'on_create_line_item' ], 10, 4 );
		add_filter( 'woocommerce_order_item_get_name', [ self::class, 'filter_item_get_name' ], 10, 2 );
		add_filter( 'woocommerce_order_item_name', [ self::class, 'filter_item_name' ], 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', [ self::class, 'filter_item_meta_value' ], 10, 3 );
	}

	/**
	 * Language of an order ('fa' when unknown).
	 *
	 * @param WC_Order|int|mixed $order Order or ID.
	 */
	public static function lang( $order ): string {
		if ( ! $order instanceof WC_Order && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( is_numeric( $order ) ? (int) $order : 0 );
		}
		if ( ! $order instanceof WC_Order ) {
			return Languages::DEFAULT;
		}
		$lang = (string) $order->get_meta( self::META, true );
		return Languages::exists( $lang ) ? $lang : Languages::DEFAULT;
	}

	/**
	 * Enters the order's language context until Languages::restore() (gateway callbacks,
	 * Checkout\ReturnUrls). Returns the language.
	 */
	public static function enter_context( $order ): string {
		$lang = self::lang( $order );
		Languages::force( $lang );
		return $lang;
	}

	/**
	 * woocommerce_checkout_create_order / woocommerce_store_api_checkout_update_order_meta:
	 * stores the shopper's language on the order.
	 *
	 * @param mixed $order Order.
	 */
	public static function on_create_order( $order ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$store_api = 'woocommerce_store_api_checkout_update_order_meta' === current_action();
		// A Store API checkout is a POST to /wp-json/wc/store/v1/checkout, which carries no language
		// context (§3.1), so the language is read from the request itself.
		$lang = $store_api ? self::store_api_lang( null ) : Languages::current();
		$order->update_meta_data( self::META, $lang );
		// The Store API hook fires against an already persisted order; make sure the meta lands
		// even when no later save() follows in that request. The classic checkout saves right after.
		if ( $store_api && $order->get_id() > 0 ) {
			$order->save_meta_data();
		}
	}

	/**
	 * woocommerce_store_api_checkout_update_order_from_request: the shopper's language from the
	 * checkout request (extensions.gadgetim.lang, the X-Gadgetim-Lang header, or the referer prefix).
	 *
	 * @param mixed $order   Order.
	 * @param mixed $request WP_REST_Request.
	 */
	public static function on_store_api_order( $order, $request = null ): void {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order->update_meta_data( self::META, self::store_api_lang( $request instanceof \WP_REST_Request ? $request : null ) );
	}

	/**
	 * Language of a Store API checkout request, validated against the configured languages.
	 */
	public static function store_api_lang( ?\WP_REST_Request $request ): string {
		$candidates = [];
		if ( null !== $request ) {
			$extensions   = $request->get_param( 'extensions' );
			$candidates[] = is_array( $extensions ) ? ( $extensions['gadgetim']['lang'] ?? '' ) : '';
			$candidates[] = (string) $request->get_header( 'x_gadgetim_lang' );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized below.
		$candidates[] = isset( $_SERVER['HTTP_X_GADGETIM_LANG'] ) ? (string) wp_unslash( $_SERVER['HTTP_X_GADGETIM_LANG'] ) : '';
		foreach ( $candidates as $candidate ) {
			$candidate = is_scalar( $candidate ) ? sanitize_key( (string) $candidate ) : '';
			if ( '' !== $candidate && Languages::exists( $candidate ) ) {
				return $candidate;
			}
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched against the home URL pattern.
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : '';
		$lang    = Request::lang_from_referer( $referer );
		return null !== $lang ? $lang : Languages::current();
	}

	/**
	 * woocommerce_checkout_create_order_line_item (classic and Store API): the item name is
	 * the Persian product name, not the view-context (translated) one.
	 *
	 * @param mixed $item          Line item.
	 * @param mixed $cart_item_key Cart key.
	 * @param mixed $values        Cart item values.
	 * @param mixed $order         Order.
	 */
	public static function on_create_line_item( $item, $cart_item_key = '', $values = [], $order = null ): void {
		if ( Languages::is_default() || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$product = is_array( $values ) ? ( $values['data'] ?? null ) : null;
		if ( ! $product instanceof WC_Product ) {
			$product = $item->get_product();
		}
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$name = (string) $product->get_name( 'edit' );
		if ( '' !== $name ) {
			$item->set_name( $name );
		}
	}

	/**
	 * woocommerce_order_item_get_name (view context): translated product title in customer output.
	 *
	 * @param mixed $name Name.
	 * @param mixed $item Order item.
	 */
	public static function filter_item_get_name( $name, $item = null ) {
		if ( ! is_string( $name ) || ! $item instanceof WC_Order_Item_Product ) {
			return $name;
		}
		if ( ! Context::is_customer_output() || ! Context::may_translate() ) {
			return $name;
		}
		$translated = self::translated_item_title( $item );
		return '' !== $translated ? $translated : $name;
	}

	/**
	 * woocommerce_order_item_name (templates): covers names rendered from the edit context.
	 *
	 * @param mixed $name Possibly <a>-wrapped name.
	 * @param mixed $item Order item.
	 */
	public static function filter_item_name( $name, $item = null ) {
		if ( ! is_string( $name ) || ! $item instanceof WC_Order_Item_Product ) {
			return $name;
		}
		if ( ! Context::is_customer_output() || ! Context::may_translate() ) {
			return $name;
		}
		$translated = self::translated_item_title( $item );
		if ( '' === $translated ) {
			return $name;
		}
		$persian = (string) $item->get_name( 'edit' );
		if ( '' !== $persian && str_contains( $name, $persian ) ) {
			return str_replace( $persian, esc_html( $translated ), $name );
		}
		return $name;
	}

	/**
	 * woocommerce_order_item_display_meta_value: attribute term names of variations.
	 *
	 * @param mixed $display_value Display value.
	 * @param mixed $meta          Meta object (key, value).
	 * @param mixed $item          Order item.
	 */
	public static function filter_item_meta_value( $display_value, $meta = null, $item = null ) {
		if ( ! is_string( $display_value ) || ! is_object( $meta ) || empty( $meta->key ) || ! $item instanceof WC_Order_Item ) {
			return $display_value;
		}
		if ( ! Context::is_customer_output() || ! Context::may_translate() ) {
			return $display_value;
		}
		$taxonomy = (string) $meta->key;
		if ( ! taxonomy_exists( $taxonomy ) || ! TermTranslations::supports( $taxonomy ) ) {
			return $display_value;
		}
		$term = get_term_by( 'slug', (string) $meta->value, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return $display_value;
		}
		$name = TermTranslations::name( $term );
		return '' !== $name ? $name : $display_value;
	}

	/**
	 * Translated title of the item's product (variations use the parent), '' when none.
	 */
	private static function translated_item_title( WC_Order_Item_Product $item ): string {
		$product_id = (int) $item->get_product_id();
		if ( $product_id <= 0 ) {
			return '';
		}
		$translated = PostTranslations::get( $product_id, 'title' );
		if ( '' === $translated ) {
			return '';
		}
		$variation_id = (int) $item->get_variation_id();
		if ( $variation_id > 0 && function_exists( 'wc_get_product' ) ) {
			// Keep the attribute summary WooCommerce appends to variation names.
			$persian = (string) $item->get_name( 'edit' );
			$parent  = (string) get_post_field( 'post_title', $product_id, 'raw' );
			if ( '' !== $parent && str_starts_with( $persian, $parent ) ) {
				return $translated . substr( $persian, strlen( $parent ) );
			}
		}
		return $translated;
	}
}
