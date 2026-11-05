<?php
/**
 * Brand tiles: typographic brand names in their own colour with the number of products, linked to
 * the brand archive (native product_brand taxonomy, brands with products only).
 *
 * Args: limit (default 18), title, sub, id, class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_b      = wp_parse_args(
	$args ?? [],
	[
		'limit' => 18,
		'title' => __( 'Brands we carry', 'gadgetim' ),
		'sub'   => __( 'Original products from brands you can trust', 'gadgetim' ),
		'id'    => 'pbrands-t',
		'class' => '',
	]
);
$gadgetim_brands = function_exists( 'gadgetim_brands' ) ? gadgetim_brands( (int) $gadgetim_b['limit'] ) : [];
if ( ! $gadgetim_brands ) {
	return;
}
// Text colours with at least 4.5:1 contrast on white.
$gadgetim_colors = [ '#e11d48', '#2563eb', '#047857', '#7c3aed', '#c2410c', '#0e7490', '#c026d3', '#4f46e5', '#0f766e', '#be123c', '#9333ea', '#0369a1' ];
?>
<section class="<?php echo esc_attr( trim( 'pbrands sect ' . (string) $gadgetim_b['class'] ) ); ?>" aria-labelledby="<?php echo esc_attr( (string) $gadgetim_b['id'] ); ?>">
	<?php
	gadgetim_the_section_head(
		[
			'title'  => (string) $gadgetim_b['title'],
			'sub'    => (string) $gadgetim_b['sub'],
			'id'     => (string) $gadgetim_b['id'],
			'icon'   => 'tag',
			'center' => true,
		]
	);
	?>
	<ul class="pbrands__list">
		<?php foreach ( $gadgetim_brands as $gadgetim_i => $gadgetim_brand ) : ?>
			<?php
			$gadgetim_link = get_term_link( $gadgetim_brand );
			if ( is_wp_error( $gadgetim_link ) ) {
				continue;
			}
			$gadgetim_count = function_exists( 'gadgetim_term_count' ) ? gadgetim_term_count( $gadgetim_brand ) : (int) $gadgetim_brand->count;
			?>
			<li data-reveal style="--i:<?php echo (int) min( 7, $gadgetim_i % 8 ); ?>">
				<a class="pbrand" href="<?php echo esc_url( (string) $gadgetim_link ); ?>" style="--bc:<?php echo esc_attr( $gadgetim_colors[ $gadgetim_i % count( $gadgetim_colors ) ] ); ?>">
					<b class="pbrand__n" dir="auto"><?php echo esc_html( gadgetim_term_name( $gadgetim_brand ) ); ?></b>
					<small class="pbrand__c"><?php echo esc_html( gadgetim_count_label( $gadgetim_count ) ); ?></small>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
