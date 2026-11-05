<?php
/**
 * ContactMode module: "contact instead of price" (architecture §4.6, design spec §5.7).
 *
 * Settings tab, per-product override, storefront enforcement, price-slip markup,
 * JSON-LD offers gate and the migration tool for the old call-for-price plugin.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\ContactMode;

/**
 * Module entry point (booted by Plugin::boot() at plugin-file load).
 */
final class Module {

	/**
	 * Registers hooks only. WooCommerce-dependent hooks attach on woocommerce_loaded.
	 */
	public static function register(): void {
		add_action( 'init', [ ContactMode::class, 'register_settings_tab' ], 5 );
		add_action( 'init', [ Admin\ProductMetabox::class, 'register_meta' ], 6 );
		add_action( 'admin_init', [ Migration::class, 'register_tool' ], 20 );

		add_action( 'add_meta_boxes', [ Admin\ProductMetabox::class, 'add' ], 10, 2 );
		add_action( 'save_post_product', [ Admin\ProductMetabox::class, 'save' ], 10, 3 );

		add_action( 'gadgetim_settings_saved', [ ContactMode::class, 'on_settings_saved' ], 5, 2 );

		if ( did_action( 'woocommerce_loaded' ) ) {
			self::hook_woocommerce();
		} else {
			add_action( 'woocommerce_loaded', [ self::class, 'hook_woocommerce' ] );
		}
	}

	/**
	 * woocommerce_loaded: storefront enforcement and display hooks.
	 */
	public static function hook_woocommerce(): void {
		Enforcement::register_hooks();
		Display::register_hooks();
	}
}
