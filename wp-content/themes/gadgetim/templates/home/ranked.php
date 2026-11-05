<?php
/**
 * Ranked board: «پرفروش‌ترین‌ها» by total sales once orders exist, honestly labelled
 * «پربازدیدترین‌ها» (by sampled views) until then. Phones: a three-row swipe board; desktop: a
 * white four-column board with outlined rank numbers (gradient for the top three).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_ranked   = gadgetim_home_ranked();
$gadgetim_products = gadgetim_home_products( (array) ( $gadgetim_ranked['ids'] ?? [] ) );
if ( count( $gadgetim_products ) < 6 ) {
	return;
}
$gadgetim_sales = 'sales' === ( $gadgetim_ranked['basis'] ?? 'views' );
$gadgetim_title = $gadgetim_sales ? __( 'Best sellers', 'gadgetim' ) : __( 'Most viewed', 'gadgetim' );
?>
<section class="sect container sect--glow-pink" id="best" aria-labelledby="best-title">
	<?php
	gadgetim_the_section_head(
		[
			'title'    => $gadgetim_title,
			'sub'      => $gadgetim_sales ? __( 'The favourite picks of Gadgetim shoppers', 'gadgetim' ) : __( 'The gadgets shoppers look at most', 'gadgetim' ),
			'id'       => 'best-title',
			'icon'     => $gadgetim_sales ? 'trend' : 'eye',
			'tone'     => 'warm',
			'more_url' => add_query_arg( 'orderby', 'popularity', gadgetim_shop_url() ),
		]
	);
	?>
	<ol class="rank" id="rank-board" data-rail-target aria-label="<?php echo esc_attr( $gadgetim_title ); ?>">
		<?php
		foreach ( $gadgetim_products as $gadgetim_i => $gadgetim_p ) {
			gadgetim_the_product_card( $gadgetim_p, 'rank', [ 'index' => $gadgetim_i % 6 ] + [ 'rank' => $gadgetim_i + 1 ] );
		}
		?>
	</ol>
</section>
