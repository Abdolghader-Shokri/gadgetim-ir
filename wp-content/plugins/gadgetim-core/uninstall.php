<?php
/**
 * Uninstall handler.
 *
 * Options and transients are removed ONLY when wp-config.php defines
 * GADGETIM_REMOVE_DATA as true. Post, term, order and comment meta are never
 * touched here: they belong to the catalogue, not to the plugin.
 *
 * @package Gadgetim\Core
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'GADGETIM_REMOVE_DATA' ) || ! GADGETIM_REMOVE_DATA ) {
	return;
}

$gadgetim_options = [
	'gadgetim_settings',
	'gadgetim_languages',
	'gadgetim_currencies',
	'gadgetim_rate_history',
	'gadgetim_home',
	'gadgetim_redirects',
	'gadgetim_strings',
	'gadgetim_prices_version',
	'gadgetim_notices',
	'gadgetim_core_version',
];

foreach ( $gadgetim_options as $gadgetim_option ) {
	delete_option( $gadgetim_option );
}

// Transients: gadgetim_slugmap_*, gadgetim_reprice_*, gadgetim_rl_*, gadgetim_view_*, gadgetim_tool_result_*.
global $wpdb;

$gadgetim_like_value   = $wpdb->esc_like( '_transient_gadgetim_' ) . '%';
$gadgetim_like_timeout = $wpdb->esc_like( '_transient_timeout_gadgetim_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$gadgetim_like_value,
		$gadgetim_like_timeout
	)
);

wp_cache_flush();
