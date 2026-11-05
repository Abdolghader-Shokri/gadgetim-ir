<?php
/**
 * Cart count cookie for the header badge (design-spec §5.10, decisions.md "Cart badge").
 *
 * Cached HTML never contains the cart count. WooCommerce's own cookies only say "there is a cart",
 * so this writes a small, non-HttpOnly `gtm_cart_count` cookie that the theme's cart.js reads.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

/**
 * Writes and clears the gtm_cart_count cookie.
 */
final class CartCookie {

	public const NAME = 'gtm_cart_count';

	/** Last value written in this request, to avoid duplicate Set-Cookie headers. */
	private static ?int $written = null;

	/**
	 * Hooks (called on woocommerce_loaded).
	 */
	public static function register_hooks(): void {
		// Fired by WC_Cart_Session::set_cart_cookies() whenever WooCommerce sets or clears its cart cookies
		// (page loads, wc-ajax add_to_cart, cart page updates, checkout).
		add_action( 'woocommerce_set_cart_cookies', [ self::class, 'on_set_cart_cookies' ], 20 );
		// Store API (cart drawer) writes finish inside the REST server before WooCommerce's shutdown hook,
		// so headers would already be sent; update the cookie before the response is served.
		add_filter( 'rest_post_dispatch', [ self::class, 'on_rest_post_dispatch' ], 20, 3 );
		// Cart mutations that answer before shutdown (wc-ajax=remove_from_cart sends its JSON
		// first): write the cookie at the moment the cart changes, while headers are still open.
		foreach ( [ 'woocommerce_cart_item_removed', 'woocommerce_cart_emptied', 'woocommerce_add_to_cart', 'woocommerce_after_cart_item_quantity_update', 'woocommerce_cart_item_restored' ] as $hook ) {
			add_action( $hook, [ self::class, 'sync' ], 999, 0 );
		}
		// Reconcile a stale cookie (it outlives WooCommerce's browser-session cookies) on page loads.
		add_action( 'wp', [ self::class, 'reconcile' ], 100 );
	}

	/**
	 * Writes the current count (cart mutation hooks).
	 */
	public static function sync(): void {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			self::write( self::current_count() );
		}
	}

	/**
	 * wp (priority 100): when the browser still carries our cookie, make it match the cart that
	 * WooCommerce actually loaded (expired or emptied server carts clear it).
	 */
	public static function reconcile(): void {
		if ( is_admin() || ! isset( $_COOKIE[ self::NAME ] ) || ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
			return;
		}
		self::sync();
	}

	/**
	 * woocommerce_set_cart_cookies.
	 *
	 * @param bool $set True when the cart has items.
	 */
	public static function on_set_cart_cookies( $set ): void {
		self::write( $set ? self::current_count() : 0 );
	}

	/**
	 * rest_post_dispatch: refresh the cookie after Store API cart routes.
	 *
	 * @param mixed            $response Response.
	 * @param \WP_REST_Server  $server   Server.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function on_rest_post_dispatch( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$route = (string) $request->get_route();
		// Every Store API route: /cart/*, /checkout (empties the cart) and /batch.
		if ( ! str_starts_with( $route, '/wc/store/' ) ) {
			return $response;
		}
		if ( function_exists( 'WC' ) && WC()->cart ) {
			self::write( self::current_count() );
		}
		return $response;
	}

	/**
	 * Number of items in the current cart (quantities summed), 0 when no cart is loaded.
	 */
	public static function current_count(): int {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}
		return max( 0, (int) WC()->cart->get_cart_contents_count() );
	}

	/**
	 * Sets (count > 0) or expires (count = 0) the cookie.
	 *
	 * @param int $count Item count.
	 */
	public static function write( int $count ): void {
		if ( headers_sent() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$count = max( 0, min( 999, $count ) );
		if ( self::$written === $count ) {
			return;
		}
		$present = isset( $_COOKIE[ self::NAME ] ) ? (int) $_COOKIE[ self::NAME ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( null === self::$written && $present === $count ) {
			self::$written = $count;
			return;
		}
		if ( 0 === $count && null === $present ) {
			self::$written = 0;
			return;
		}
		$options = [
			'expires'  => $count > 0 ? time() + 2 * DAY_IN_SECONDS : time() - YEAR_IN_SECONDS,
			'path'     => ( defined( 'COOKIEPATH' ) && COOKIEPATH ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => false, // Read by the theme's cart.js.
			'samesite' => 'Lax',
		];
		setcookie( self::NAME, $count > 0 ? (string) $count : '', $options ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		if ( $count > 0 ) {
			$_COOKIE[ self::NAME ] = (string) $count;
		} else {
			unset( $_COOKIE[ self::NAME ] );
		}
		self::$written = $count;
	}
}
