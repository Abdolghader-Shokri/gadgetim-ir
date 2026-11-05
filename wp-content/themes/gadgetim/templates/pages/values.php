<?php
/**
 * Promise cards: a gradient icon tile whose strokes draw themselves when the card scrolls into
 * view, a title and a line of explanation (default: the trust.badges setting through
 * gadgetim_pages_promises()).
 *
 * Args: items ([ icon, title, text, c1, c2 ]), title, sub, icon, tone, id, class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_v     = wp_parse_args(
	$args ?? [],
	[
		'items' => null,
		'title' => __( 'Our promise to you', 'gadgetim' ),
		'sub'   => __( 'What every order from Gadgetim comes with', 'gadgetim' ),
		'icon'  => 'shield',
		'tone'  => 'green',
		'id'    => 'pvals-t',
		'class' => '',
	]
);
$gadgetim_items = null === $gadgetim_v['items'] ? gadgetim_pages_promises() : (array) $gadgetim_v['items'];
if ( ! $gadgetim_items ) {
	return;
}
$gadgetim_paths = gadgetim_pages_icon_paths();
?>
<section class="<?php echo esc_attr( trim( 'pvals sect ' . (string) $gadgetim_v['class'] ) ); ?>" aria-labelledby="<?php echo esc_attr( (string) $gadgetim_v['id'] ); ?>">
	<?php
	gadgetim_the_section_head(
		[
			'title' => (string) $gadgetim_v['title'],
			'sub'   => (string) $gadgetim_v['sub'],
			'id'    => (string) $gadgetim_v['id'],
			'icon'  => (string) $gadgetim_v['icon'],
			'tone'  => (string) $gadgetim_v['tone'],
		]
	);
	?>
	<ul class="pvals__list">
		<?php foreach ( array_values( $gadgetim_items ) as $gadgetim_i => $gadgetim_item ) : ?>
			<?php
			$gadgetim_icon = (string) ( $gadgetim_item['icon'] ?? 'sparkle' );
			$gadgetim_svg  = $gadgetim_paths[ $gadgetim_icon ] ?? $gadgetim_paths['sparkle'];
			$gadgetim_css  = '--i:' . min( 7, $gadgetim_i ) . ';--c1:' . (string) ( $gadgetim_item['c1'] ?? '#6366f1' ) . ';--c2:' . (string) ( $gadgetim_item['c2'] ?? '#9333ea' );
			?>
			<li class="pval pval--<?php echo esc_attr( sanitize_html_class( $gadgetim_icon ) ); ?>" data-reveal style="<?php echo esc_attr( $gadgetim_css ); ?>">
				<span class="pval__ic" aria-hidden="true"><svg class="pval__svg" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg></span>
				<h3 class="pval__t"><?php echo gadgetim_bidi( (string) ( $gadgetim_item['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h3>
				<?php if ( ! empty( $gadgetim_item['text'] ) ) : ?>
					<p class="pval__p"><?php echo esc_html( (string) $gadgetim_item['text'] ); ?></p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
