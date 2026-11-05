<?php
/**
 * Single Product Up-Sells
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/up-sells.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: «پیشنهاد ما» — the store's up-sells as a rail of the theme's rail cards.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     9.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( $upsells ) :
	// In-stock products first (stable sort keeps the store's order inside each group).
	usort( $upsells, static fn( $a, $b ): int => (int) ( $b instanceof WC_Product && $b->is_in_stock() ) <=> (int) ( $a instanceof WC_Product && $a->is_in_stock() ) );
	$heading = apply_filters( 'woocommerce_product_upsells_products_heading', __( 'We also recommend', 'gadgetim' ) );
	?>

	<section class="up-sells upsells products pdp-rail sect" id="upsells" aria-labelledby="upsells-title">
		<?php
		gadgetim_the_section_head(
			[
				'title' => $heading ? (string) $heading : __( 'We also recommend', 'gadgetim' ),
				'sub'   => __( 'Hand-picked by the Gadgetim team', 'gadgetim' ),
				'id'    => 'upsells-title',
				'icon'  => 'crown',
				'tone'  => 'gold',
				'rail'  => 'upsells-rail',
			]
		);
		?>
		<ul class="rail prail" id="upsells-rail">
			<?php
			foreach ( array_values( $upsells ) as $gadgetim_i => $upsell ) :
				echo gadgetim_product_card( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
					$upsell,
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
