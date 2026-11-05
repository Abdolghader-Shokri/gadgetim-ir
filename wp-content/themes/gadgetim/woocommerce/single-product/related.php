<?php
/**
 * Related Products
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/related.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: «از همین دسته» — a scroll-snap rail of the theme's rail cards (gadgetim_product_card),
 * section head with arrows and a «view all» link to the product's category.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     10.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $related_products ) :
	/**
	 * Ensure all images of related products are lazy loaded by increasing the
	 * current media count to WordPress's lazy loading threshold if needed.
	 * Because wp_increase_content_media_count() is a private function, we
	 * check for its existence before use.
	 */
	if ( function_exists( 'wp_increase_content_media_count' ) ) {
		$content_media_count = wp_increase_content_media_count( 0 );
		if ( $content_media_count < wp_omit_loading_attr_threshold() ) {
			wp_increase_content_media_count( wp_omit_loading_attr_threshold() - $content_media_count );
		}
	}

	// In-stock products first (PHP's sort is stable: WooCommerce's order is kept inside each group).
	usort( $related_products, static fn( $a, $b ): int => (int) ( $b instanceof WC_Product && $b->is_in_stock() ) <=> (int) ( $a instanceof WC_Product && $a->is_in_stock() ) );

	global $product;
	$gadgetim_lane = $product instanceof WC_Product ? gadgetim_product_lane( $product ) : null;
	$gadgetim_more = $gadgetim_lane ? get_term_link( $gadgetim_lane ) : '';
	$heading       = apply_filters( 'woocommerce_product_related_products_heading', __( 'From the same category', 'gadgetim' ) );
	?>

	<section class="related products pdp-rail sect" id="related" aria-labelledby="related-title" tabindex="-1">
		<?php
		gadgetim_the_section_head(
			[
				'title'      => $heading ? (string) $heading : __( 'From the same category', 'gadgetim' ),
				'sub'        => __( 'Other picks shoppers compare with this one', 'gadgetim' ),
				'id'         => 'related-title',
				'icon'       => 'grid',
				'tone'       => 'cool',
				'more_url'   => is_string( $gadgetim_more ) ? $gadgetim_more : '',
				'more_label' => __( 'View all', 'gadgetim' ),
				'rail'       => 'related-rail',
			]
		);
		?>
		<ul class="rail prail" id="related-rail">
			<?php
			foreach ( array_values( $related_products ) as $gadgetim_i => $related_product ) :
				echo gadgetim_product_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
					$related_product,
					'rail',
					[
						'index' => $gadgetim_i,
						'tag'   => 'li',
					]
				);
			endforeach;
			?>
		</ul>
	</section>
	<?php
endif;

wp_reset_postdata();
