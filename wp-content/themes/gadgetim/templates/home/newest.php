<?php
/**
 * Newest products: a swipe rail of the latest in-stock arrivals (the "new" tag follows the real
 * publication date, filter gadgetim_new_days). Rail arrows from 1100 px.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_products = gadgetim_home_products( gadgetim_home_newest_ids() );
if ( count( $gadgetim_products ) < 4 ) {
	return;
}
?>
<section class="sect container" id="newest" aria-labelledby="newest-title">
	<?php
	gadgetim_the_section_head(
		[
			'title'    => __( 'Newest products', 'gadgetim' ),
			'sub'      => __( 'The latest gadgets to arrive at Gadgetim', 'gadgetim' ),
			'id'       => 'newest-title',
			'icon'     => 'sparkle',
			'more_url' => add_query_arg( 'orderby', 'date', gadgetim_shop_url() ),
			'rail'     => 'newest-rail',
		]
	);
	?>
	<ul class="rail prail" id="newest-rail" aria-label="<?php esc_attr_e( 'Newest products', 'gadgetim' ); ?>">
		<?php
		foreach ( $gadgetim_products as $gadgetim_i => $gadgetim_p ) {
			gadgetim_the_product_card( $gadgetim_p, 'rail', [ 'index' => $gadgetim_i ] );
		}
		?>
	</ul>
</section>
