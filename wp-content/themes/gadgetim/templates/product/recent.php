<?php
/**
 * «بازدیدهای اخیر شما» rail (woocommerce_after_single_product_summary 25). Hidden until gallery.js
 * finds other product ids in localStorage (gtm_recent) and fills the rail from
 * GET gadgetim/v1/cards (server-rendered cards); skeletons hold the space meanwhile.
 *
 * @package Gadgetim
 *
 * @var array $args { product: WC_Product }
 */

declare( strict_types=1 );

$gadgetim_product = $args['product'] ?? null;
if ( ! $gadgetim_product instanceof WC_Product ) {
	return;
}
?>
<section class="pdp-rail pdp-recent sect" id="recent" aria-labelledby="recent-title" data-pdp-recent hidden>
	<?php
	gadgetim_the_section_head(
		[
			'title' => __( 'Recently viewed', 'gadgetim' ),
			'sub'   => __( 'Pick up where you left off', 'gadgetim' ),
			'id'    => 'recent-title',
			'icon'  => 'clock',
			'tone'  => 'pink',
			'rail'  => 'recent-rail',
		]
	);
	?>
	<ul class="rail prail" id="recent-rail" aria-busy="true" data-recent-rail>
		<?php
		for ( $gadgetim_i = 0; $gadgetim_i < 5; $gadgetim_i++ ) {
			echo gadgetim_card_skeleton(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
		}
		?>
	</ul>
</section>
