<?php
/**
 * Small WooCommerce storefront adjustments per language (architecture §3.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

/**
 * Currency unit label and the no-store cache header for prefixed wc-ajax responses.
 * Order/e-mail behaviour lives in Orders and Emails; labels in Strings; terms in
 * TermTranslations.
 */
final class WooCommerce {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_currency_symbol', [ self::class, 'filter_currency_symbol' ], 10, 2 );
		add_action( 'woocommerce_init', [ self::class, 'on_woocommerce_init' ] );
		add_filter( 'woocommerce_breadcrumb_home_url', [ self::class, 'filter_breadcrumb_home_url' ], 10, 1 );
	}

	/**
	 * woocommerce_init: nothing to do for Persian; wc-ajax under a prefix must never be cached.
	 */
	public static function on_woocommerce_init(): void {
		if ( Languages::is_default() || ! \Gadgetim\Core\Support\Context::is_wc_ajax() ) {
			return;
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * woocommerce_currency_symbol: "Toman" in English (Persian and Arabic keep تومان).
	 *
	 * @param mixed $symbol   Symbol.
	 * @param mixed $currency Currency code.
	 */
	public static function filter_currency_symbol( $symbol, $currency = '' ) {
		if ( ! is_string( $symbol ) || 'IRT' !== $currency || 'en' !== Languages::current() || ! Context::may_translate() ) {
			return $symbol;
		}
		return 'Toman';
	}

	/**
	 * woocommerce_breadcrumb_home_url: home_url() has no trailing slash, which under a language
	 * prefix ("/en") costs every breadcrumb click a 301 to "/en/".
	 *
	 * @param mixed $url Home URL.
	 */
	public static function filter_breadcrumb_home_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || Languages::is_default() ) {
			return $url;
		}
		return trailingslashit( $url );
	}
}
