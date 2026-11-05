<?php
/**
 * The Template for displaying all single products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: the theme wrapper (inc/woocommerce.php, woocommerce_before_main_content 10) opens the
 * container and prints the breadcrumbs; the page itself is content-single-product.php.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header( 'shop' );

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked gadgetim_wc_wrapper_start - 10 (container + breadcrumbs)
 */
do_action( 'woocommerce_before_main_content' );

while ( have_posts() ) :
	the_post();

	wc_get_template_part( 'content', 'single-product' );

endwhile; // End of the loop.

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked gadgetim_wc_wrapper_end - 10
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar (the theme has no shop sidebar; the hook stays for plugins).
 */
do_action( 'woocommerce_sidebar' );

get_footer( 'shop' );
