<?php
/**
 * Featured products with tabs: «همه» is rendered here; the other tabs (the mega-menu category
 * groups that hold enough stock) load their cards as server-rendered fragments on demand
 * (home.js, ?gtm_home=tab&group=ID; prefetched on hover/focus; a failed fetch opens the category).
 * Products marked "featured" in WooCommerce come first, then the priciest in stock.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_tabs = gadgetim_home_featured_tabs();
if ( ! $gadgetim_tabs ) {
	return;
}
$gadgetim_products = gadgetim_home_products( (array) $gadgetim_tabs[0]['ids'] );
if ( count( $gadgetim_products ) < 4 ) {
	return;
}
$gadgetim_tablist = '';
if ( count( $gadgetim_tabs ) > 1 ) {
	$gadgetim_tablist = '<div class="tabs featured__tabs" role="tablist" aria-label="' . esc_attr__( 'Filter featured products', 'gadgetim' ) . '" data-tabs="shared" data-featured-tabs>';
	foreach ( $gadgetim_tabs as $gadgetim_i => $gadgetim_tab ) {
		$gadgetim_tablist .= '<button' . gadgetim_attr(
			[
				'class'         => 'tab',
				'type'          => 'button',
				'role'          => 'tab',
				'id'            => 'ftab-' . $gadgetim_tab['key'],
				'aria-selected' => 0 === $gadgetim_i ? 'true' : 'false',
				'aria-controls' => 'featured-panel',
				'tabindex'      => 0 === $gadgetim_i ? '0' : '-1',
				'data-group'    => $gadgetim_tab['key'],
				'data-src'      => '' !== $gadgetim_tab['src'] ? esc_url_raw( $gadgetim_tab['src'] ) : null,
				'data-href'     => esc_url_raw( $gadgetim_tab['href'] ),
			]
		) . '>' . esc_html( $gadgetim_tab['label'] ) . '</button>';
	}
	$gadgetim_tablist .= '</div>';
}
?>
<section class="sect container sect--glow-indigo" id="featured" aria-labelledby="featured-title">
	<?php
	gadgetim_the_section_head(
		[
			'title' => __( 'Featured products', 'gadgetim' ),
			'sub'   => __( 'Hand-picked, special gadgets, category by category', 'gadgetim' ),
			'id'    => 'featured-title',
			'icon'  => 'crown',
			'tone'  => 'gold',
			'class' => 'sect__head--tabs',
			'after' => $gadgetim_tablist,
		]
	);
	?>
	<div class="featured__panel" id="featured-panel" role="tabpanel" aria-labelledby="<?php echo esc_attr( count( $gadgetim_tabs ) > 1 ? 'ftab-' . $gadgetim_tabs[0]['key'] : 'featured-title' ); ?>" data-featured>
		<ul class="pgrid featured__grid">
			<?php
			foreach ( $gadgetim_products as $gadgetim_i => $gadgetim_p ) {
				gadgetim_the_product_card( $gadgetim_p, 'default', [ 'index' => $gadgetim_i % 5 ] );
			}
			?>
		</ul>
	</div>
	<?php if ( count( $gadgetim_tabs ) > 1 ) : ?>
		<template id="featured-sk"><?php echo str_repeat( gadgetim_card_skeleton(), 10 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></template>
	<?php endif; ?>
</section>
