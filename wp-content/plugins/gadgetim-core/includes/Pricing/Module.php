<?php
/**
 * Pricing module: multi-currency base prices materialised into WooCommerce (architecture §4, D3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Pricing;

/**
 * Module entry point (booted by Plugin::boot() at plugin-file load).
 *
 * Classes: Currencies (option CRUD + rate history), Calculator (conversion + rounding), Meta (keys),
 * Products (queries), ProductFields (admin fields), Materializer (before-save hook), Guard (meta
 * registration, bypass safety net, daily check), Reprice (chained Action Scheduler job), Display
 * (price partial), Rest (wc/v3 field + gadgetim/v1/currencies), Migration (old AED meta),
 * Admin\CurrenciesPage (Gadgetim → Currencies tab).
 */
final class Module {

	/**
	 * Registers hooks only; WooCommerce is not loaded yet and nothing is translated here.
	 */
	public static function register(): void {
		if ( Handover::active() ) {
			self::register_handover();
			return;
		}
		add_action( 'init', [ self::class, 'init' ], 5 );
		add_action( 'gadgetim_core_activate', [ self::class, 'activate' ] );
		add_action( 'gadgetim_core_upgrade', [ self::class, 'activate' ], 10, 0 );
		add_action( 'gadgetim_core_deactivate', [ self::class, 'deactivate' ] );

		// Save pipeline (woocommerce_before_product_object_save covers products, variable parents and variations).
		add_action( 'woocommerce_before_product_object_save', [ Guard::class, 'on_before_save' ], 1, 1 );
		add_action( 'woocommerce_before_product_object_save', [ Materializer::class, 'apply' ], 10, 2 );
		add_action( 'woocommerce_after_product_object_save', [ Guard::class, 'on_after_save' ], 999, 1 );
		add_action( 'updated_post_meta', [ Guard::class, 'on_meta_change' ], 10, 3 );
		add_action( 'added_post_meta', [ Guard::class, 'on_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ Guard::class, 'on_meta_deleted' ], 10, 3 );
		add_action( 'before_delete_post', [ Guard::class, 'on_before_delete_post' ], 1, 1 );
		add_action( 'after_delete_post', [ Guard::class, 'on_after_delete_post' ], 999, 1 );
		add_filter( 'woocommerce_get_variation_prices_hash', [ Materializer::class, 'prices_hash' ], 10, 1 );

		// Admin product fields (read BEFORE the save, in the same request).
		add_action( 'woocommerce_product_options_general_product_data', [ ProductFields::class, 'render_product_fields' ] );
		add_action( 'woocommerce_admin_process_product_object', [ ProductFields::class, 'read_post_fields' ], 10, 1 );
		add_action( 'woocommerce_variation_options_pricing', [ ProductFields::class, 'render_variation_fields' ], 10, 3 );
		add_action( 'woocommerce_admin_process_variation_object', [ ProductFields::class, 'read_variation_fields' ], 10, 2 );
		add_action( 'woocommerce_variable_product_bulk_edit_actions', [ ProductFields::class, 'bulk_edit_option' ] );
		add_action( 'woocommerce_bulk_edit_variations_default', [ ProductFields::class, 'bulk_set_currency' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ ProductFields::class, 'enqueue' ] );

		// Display: the only WooCommerce price filter, guarded by Context::is_storefront_render().
		add_filter( 'woocommerce_get_price_html', [ Display::class, 'filter_price_html' ], 20, 2 );
		// Cart, checkout and order tables print wc_price() through their own filters.
		foreach ( Display::AMOUNT_FILTERS as $amount_filter ) {
			add_filter( $amount_filter, [ Display::class, 'filter_amount_html' ], 20, 1 );
		}

		// REST.
		add_action( 'rest_api_init', [ Rest::class, 'register_routes' ] );
		add_filter( 'woocommerce_rest_prepare_product_object', [ Rest::class, 'prepare_product' ], 10, 3 );
		add_filter( 'woocommerce_rest_prepare_product_variation_object', [ Rest::class, 'prepare_product' ], 10, 3 );
		add_filter( 'woocommerce_rest_pre_insert_product_object', [ Rest::class, 'pre_insert' ], 10, 3 );
		add_filter( 'woocommerce_rest_pre_insert_product_variation_object', [ Rest::class, 'pre_insert' ], 10, 3 );

		// Reprice job (Action Scheduler) and its progress endpoint.
		add_action( Reprice::HOOK_BATCH, [ Reprice::class, 'batch' ], 10, 2 );
		add_action( Reprice::HOOK_FINISH, [ Reprice::class, 'finish' ], 10, 1 );
		add_action( 'wp_ajax_' . Reprice::AJAX_ACTION, [ Reprice::class, 'ajax_progress' ] );

		// Daily mismatch check and its "Fix now" action.
		add_action( Guard::CRON_HOOK, [ Guard::class, 'daily_check' ] );
		add_action( 'admin_post_' . Guard::FIX_ACTION, [ Guard::class, 'handle_fix' ] );
		add_action( 'admin_init', [ Guard::class, 'ensure_cron' ] );

		// Admin: Tools entry and the Currencies tab.
		add_action( 'admin_init', [ Migration::class, 'register_tool' ] );
		add_action( 'admin_enqueue_scripts', [ Admin\CurrenciesPage::class, 'enqueue' ] );
		add_action( 'admin_post_' . Admin\CurrenciesPage::SAVE_ACTION, [ Admin\CurrenciesPage::class, 'handle_save' ] );
		add_action( 'admin_post_' . Admin\CurrenciesPage::APPLY_ACTION, [ Admin\CurrenciesPage::class, 'handle_apply' ] );
		add_action( 'admin_post_' . Admin\CurrenciesPage::DELETE_ACTION, [ Admin\CurrenciesPage::class, 'handle_delete' ] );
	}

	/**
	 * The currency pricing plugin (currency-rates-scraper) is active: Gadgetim keeps only its digit
	 * formatting and copies the currency prices into that plugin (Handover). No rate entry, product
	 * currency fields, materialiser, guard or reprice job run, so the two never fight over prices.
	 */
	private static function register_handover(): void {
		add_filter( 'woocommerce_get_price_html', [ Display::class, 'filter_price_html' ], 20, 2 );
		foreach ( Display::AMOUNT_FILTERS as $amount_filter ) {
			add_filter( $amount_filter, [ Display::class, 'filter_amount_html' ], 20, 1 );
		}
		add_action( 'admin_init', [ Guard::class, 'unschedule_cron' ] );
		add_action( 'gadgetim_core_deactivate', [ self::class, 'deactivate' ] );
		Handover::register();
	}

	/**
	 * init (priority 5, after the text domain): meta registration, settings fields, the Currencies tab.
	 * Tests that load the plugin after init call this directly.
	 */
	public static function init(): void {
		Guard::register_meta();
		Display::register_settings();
		Admin\CurrenciesPage::register_tab();
	}

	/**
	 * gadgetim_core_activate / gadgetim_core_upgrade: seeds the currencies and arms the daily check.
	 */
	public static function activate(): void {
		Currencies::seed();
		Guard::schedule_cron();
	}

	/**
	 * gadgetim_core_deactivate.
	 */
	public static function deactivate(): void {
		Guard::unschedule_cron();
	}
}
