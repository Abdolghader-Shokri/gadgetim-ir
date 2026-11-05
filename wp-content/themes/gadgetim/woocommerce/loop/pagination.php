<?php
/**
 * Pagination - Show numbered pagination for catalog pages
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/pagination.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): the theme pager (gadgetim_pagination(): real URLs, locale digits, rel
 * prev/next, gradient current page). The catalogue archives print it from
 * templates/archive/more.php; this override serves other callers (e.g. paginated [products]
 * shortcodes, which pass their own $base).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$total   = isset( $total ) ? $total : wc_get_loop_prop( 'total_pages' );
$current = isset( $current ) ? $current : wc_get_loop_prop( 'current_page' );

if ( $total <= 1 ) {
	return;
}

$gadgetim_args = [
	'total'   => (int) $total,
	'current' => max( 1, (int) $current ),
];
if ( isset( $base ) && '' !== (string) $base ) {
	// Shortcode loops: their base carries %#% (e.g. ?product-page=%#%).
	$gadgetim_args['base'] = (string) $base;
}
gadgetim_pagination( $gadgetim_args );
