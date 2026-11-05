<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): shop, product category, brand, tag and product search archives.
 * Hero (templates/archive/hero.php), filters (sticky sidebar ≥ 1000 px, bottom sheet on phones:
 * templates/archive/filters.php + sheet.php), results (templates/archive/results.php: toolbar,
 * active filters, grid, load more, pagination — the same template answers the plugin's fragment
 * requests), the collapsed term description at the bottom. WooCommerce's hooks are kept; the
 * default parts they print are unhooked in inc/archive.php.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$gadgetim_filters = gadgetim_archive_has_filters();

/**
 * Hook: woocommerce_before_main_content.
 *
 * @hooked gadgetim_wc_wrapper_start - 10 (container + breadcrumbs)
 * @hooked WC_Structured_Data::generate_website_data() - 30
 */
do_action( 'woocommerce_before_main_content' );

gadgetim_part( 'archive/hero' );

/**
 * Hook: woocommerce_shop_loop_header (WooCommerce's taxonomy header is replaced by the hero).
 *
 * @since 8.6.0
 */
do_action( 'woocommerce_shop_loop_header' );
?>
<div class="arch<?php echo $gadgetim_filters ? ' arch--filters' : ''; ?>" id="arch">
	<?php if ( $gadgetim_filters ) : ?>
		<aside class="arch__side" id="arch-side" aria-labelledby="filters-title">
			<?php gadgetim_part( 'archive/filters' ); ?>
		</aside>
	<?php endif; ?>
	<div class="arch__main">
		<?php gadgetim_part( 'archive/results' ); ?>
	</div>
</div>
<?php
gadgetim_part( 'archive/description' );

/**
 * Hook: woocommerce_after_main_content.
 *
 * @hooked gadgetim_wc_wrapper_end - 10
 */
do_action( 'woocommerce_after_main_content' );

/**
 * Hook: woocommerce_sidebar (WooCommerce's sidebar is unhooked by the theme).
 */
do_action( 'woocommerce_sidebar' );

if ( $gadgetim_filters ) {
	gadgetim_part( 'archive/sheet' );
}

get_footer( 'shop' );
