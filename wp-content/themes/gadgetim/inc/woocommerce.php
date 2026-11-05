<?php
/**
 * Core WooCommerce hook wiring. Page layers add their own hooks in inc/archive.php,
 * inc/product.php and inc/commerce.php (loaded after this file, so they may remove or replace
 * anything wired here).
 *
 * What this file does:
 * - replaces WooCommerce's content wrapper with the theme's (<div class="container wc-wrap">;
 *   <main id="main"> is opened in header.php) and prints the theme breadcrumbs inside it;
 * - removes WooCommerce's sidebar, sale flash and default loop item parts, and renders every
 *   loop item with gadgetim_product_card() (the <li> comes from content-product.php);
 * - gives the loop <ul> the theme grid classes; sort options and labels; related products count.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * init 20: rewire the storefront hooks once WooCommerce registered its defaults.
 */
function gadgetim_wc_hooks(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	// Wrapper, breadcrumb, sidebar.
	remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
	remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );
	add_action( 'woocommerce_before_main_content', 'gadgetim_wc_wrapper_start', 10 );
	add_action( 'woocommerce_after_main_content', 'gadgetim_wc_wrapper_end', 10 );

	// Loop item: the theme card replaces every default piece.
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	add_action( 'woocommerce_before_shop_loop_item', 'gadgetim_wc_loop_card', 10 );

	add_filter( 'woocommerce_product_loop_start', 'gadgetim_wc_loop_start' );
	add_filter( 'woocommerce_post_class', 'gadgetim_wc_loop_item_class', 20, 2 );
	add_filter( 'woocommerce_sale_flash', '__return_empty_string' );
	add_filter( 'woocommerce_output_related_products_args', 'gadgetim_wc_related_args' );
	add_filter( 'woocommerce_catalog_orderby', 'gadgetim_wc_orderby_options' );
	add_filter( 'woocommerce_product_description_heading', '__return_empty_string' );
	add_filter( 'woocommerce_product_additional_information_heading', '__return_empty_string' );
	add_filter( 'woocommerce_breadcrumb_defaults', 'gadgetim_wc_breadcrumb_defaults' );
}
add_action( 'init', 'gadgetim_wc_hooks', 20 );

/**
 * Theme wrapper start (inside <main id="main">): container + breadcrumbs.
 */
function gadgetim_wc_wrapper_start(): void {
	echo '<div class="container wc-wrap">';
	gadgetim_breadcrumbs();
}

/**
 * Theme wrapper end.
 */
function gadgetim_wc_wrapper_end(): void {
	echo '</div>';
}

/**
 * Loop item: the default card inside WooCommerce's <li> (content-product.php keeps its hooks).
 */
function gadgetim_wc_loop_card(): void {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	static $index = 0;
	echo gadgetim_product_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
		$product,
		'default',
		[
			'tag'    => 'div',
			'class'  => 'pc--loop',
			'index'  => $index++ % 8,
			'reveal' => false,
		]
	);
}

/**
 * Loop <li> classes: the WooCommerce product classes plus the theme hook class.
 *
 * @param string[]   $classes Classes.
 * @param WC_Product $product Product.
 * @return string[]
 */
function gadgetim_wc_loop_item_class( array $classes, $product ): array {
	if ( ! ( $product instanceof WC_Product && is_product() && get_queried_object_id() === $product->get_id() ) ) {
		$classes[] = 'pgrid__item';
	}
	return $classes;
}

/**
 * The product grid wrapper (the main loop carries the #products skip-link target).
 */
function gadgetim_wc_loop_start( string $html ): string {
	$name    = function_exists( 'wc_get_loop_prop' ) ? (string) wc_get_loop_prop( 'name' ) : '';
	$id      = '' === $name ? ' id="products"' : '';
	$variant = in_array( $name, [ 'related', 'up-sells', 'cross-sells' ], true ) ? ' pgrid--related' : '';
	$open    = '<ul' . $id . ' class="products pgrid' . $variant . '">';
	$swapped = preg_replace( '/^\s*<ul[^>]*>/', $open, $html, 1 );
	return is_string( $swapped ) && '' !== $swapped ? $swapped : $open;
}

/**
 * Related products: eight.
 *
 * @param array $args Args.
 * @return array
 */
function gadgetim_wc_related_args( array $args ): array {
	$args['posts_per_page'] = 8;
	$args['columns']        = 4;
	return $args;
}

/**
 * Whether any product has sales yet («best selling» vs «most viewed»), cached for an hour.
 */
function gadgetim_has_bestsellers(): bool {
	$cached = get_transient( 'gadgetim_has_bestsellers' );
	if ( false !== $cached ) {
		return '1' === $cached;
	}
	global $wpdb;
	$has = (bool) $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} WHERE meta_key = 'total_sales' AND CAST(meta_value AS UNSIGNED) > 0 LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	set_transient( 'gadgetim_has_bestsellers', $has ? '1' : '0', HOUR_IN_SECONDS );
	return $has;
}

/**
 * Sort options: popular · newest · cheapest · most expensive (no price sorts in call-for-price mode).
 *
 * @param array $options Options.
 * @return array
 */
function gadgetim_wc_orderby_options( array $options ): array {
	$sorted = [
		'popularity' => gadgetim_has_bestsellers() ? __( 'Best selling', 'gadgetim' ) : __( 'Most viewed', 'gadgetim' ),
		'date'       => __( 'Newest', 'gadgetim' ),
		'price'      => __( 'Cheapest first', 'gadgetim' ),
		'price-desc' => __( 'Most expensive first', 'gadgetim' ),
	];
	if ( gadgetim_contact_mode() ) {
		unset( $sorted['price'], $sorted['price-desc'] );
	}
	if ( isset( $options['menu_order'] ) ) {
		$sorted = [ 'menu_order' => __( 'Default order', 'gadgetim' ) ] + $sorted;
	}
	return $sorted;
}

/**
 * Breadcrumb defaults (only used where WooCommerce's own breadcrumb() is still called).
 *
 * @param array $defaults Defaults.
 * @return array
 */
function gadgetim_wc_breadcrumb_defaults( array $defaults ): array {
	$defaults['home'] = __( 'Home', 'gadgetim' );
	return $defaults;
}

/**
 * «N products» with locale digits.
 */
function gadgetim_count_label( int $count ): string {
	/* translators: %s: number of products */
	return sprintf( _n( '%s product', '%s products', $count, 'gadgetim' ), gadgetim_number( $count ) );
}
