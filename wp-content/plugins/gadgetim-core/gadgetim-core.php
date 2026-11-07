<?php
/**
 * Plugin Name:       Gadgetim Core
 * Plugin URI:        https://gadgetim.ir/
 * Description:       Data and behaviour for the Gadgetim storefront: trilingual routing, multi-currency pricing, settings, catalog extras, importer and SEO. The "Gadgetim" theme is presentation only and relies on this plugin.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 * Author:            Gadgetim
 * Author URI:        https://gadgetim.ir/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gadgetim-core
 * Domain Path:       /languages
 * WC requires at least: 9.6
 * WC tested up to:   11.2
 *
 * @package Gadgetim\Core
 */

/*
 * This file is intentionally written in PHP 7.0-compatible syntax: it must parse
 * on an old interpreter so the requirements check below can show a notice instead
 * of a fatal error. Everything under includes/ uses PHP 8.1 syntax.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'GADGETIM_CORE_VERSION' ) ) {
	// Loaded twice (e.g. a second copy of the plugin); the first one wins.
	return;
}

define( 'GADGETIM_CORE_VERSION', '0.1.0' );
define( 'GADGETIM_CORE_API', 1 );
define( 'GADGETIM_CORE_FILE', __FILE__ );
define( 'GADGETIM_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GADGETIM_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'GADGETIM_CORE_MIN_PHP', '8.1' );
define( 'GADGETIM_CORE_MIN_WP', '6.7' );
define( 'GADGETIM_CORE_MIN_WC', '9.6' );

/**
 * Checks the hard floors (PHP and WordPress) that nothing in the plugin can run without.
 *
 * WooCommerce is checked later, on plugins_loaded, because plugins load alphabetically
 * and WooCommerce is not available yet when this file runs; see Gadgetim\Core\Plugin.
 *
 * @return string Empty when the requirements are met, otherwise the English problem description.
 */
function gadgetim_core_requirements_failure() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, GADGETIM_CORE_MIN_PHP, '<' ) ) {
		return sprintf( 'Gadgetim Core requires PHP %1$s or newer; this server runs PHP %2$s. The plugin has not been loaded.', GADGETIM_CORE_MIN_PHP, PHP_VERSION );
	}

	if ( version_compare( (string) $wp_version, GADGETIM_CORE_MIN_WP, '<' ) ) {
		return sprintf( 'Gadgetim Core requires WordPress %1$s or newer; this site runs WordPress %2$s. The plugin has not been loaded.', GADGETIM_CORE_MIN_WP, (string) $wp_version );
	}

	return '';
}

/**
 * Prints the requirements failure as an admin notice.
 */
function gadgetim_core_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$message = gadgetim_core_requirements_failure();
	if ( '' === $message ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
}

if ( '' !== gadgetim_core_requirements_failure() ) {
	add_action( 'admin_notices', 'gadgetim_core_requirements_notice' );
	return;
}

/*
 * WooCommerce feature compatibility. Orders are only read and written through WC_Order
 * (get_meta / update_meta_data) and the admin order column supports both list screens, so the
 * plugin works with High-Performance Order Storage; without this declaration WooCommerce
 * reports it as incompatible. Cart and checkout blocks are not declared: the store uses the
 * classic [woocommerce_cart] / [woocommerce_checkout] pages that Checkout\Fields is built for.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

require GADGETIM_CORE_DIR . 'includes/Autoloader.php';

Gadgetim\Core\Autoloader::register( GADGETIM_CORE_DIR . 'includes/' );

require GADGETIM_CORE_DIR . 'includes/functions.php';

/*
 * Boot immediately at file load, NOT on plugins_loaded: I18n\Request must detect the
 * language of the request before any other code (WooCommerce included) runs.
 */
Gadgetim\Core\Plugin::boot();
