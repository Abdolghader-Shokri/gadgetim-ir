<?php
/**
 * Checkout module: Iranian checkout fields, storefront gateway visibility and
 * language-correct gateway return URLs (architecture §3.8, §5.6, §6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Checkout;

/**
 * Module entry point (booted by Plugin::boot() at plugin-file load).
 */
final class Module {

	/**
	 * Registers hooks only. WooCommerce-dependent hooks attach on woocommerce_loaded.
	 */
	public static function register(): void {
		add_action( 'init', [ SettingsTab::class, 'register_tab' ], 5 );
		add_action( 'admin_init', [ Gateways::class, 'register_fields' ], 20 );

		if ( did_action( 'woocommerce_loaded' ) ) {
			self::hook_woocommerce();
		} else {
			add_action( 'woocommerce_loaded', [ self::class, 'hook_woocommerce' ] );
		}
	}

	/**
	 * woocommerce_loaded: everything that filters WooCommerce.
	 */
	public static function hook_woocommerce(): void {
		Gateways::register_hooks();
		OrderStatuses::register_hooks();
		Fields::register_hooks();
		CityScript::register_hooks();
		ReturnUrls::register_hooks();
		CartCookie::register_hooks();
	}
}
