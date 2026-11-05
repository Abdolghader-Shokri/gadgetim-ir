<?php
/**
 * Number tiles that count up from zero when they scroll into view (motion.js [data-count-to]);
 * the server prints the final number, so the tiles read right without JS.
 *
 * Args: items ([ icon, n (int), suffix, label, text ]), class, label (aria-label of the list).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_items = array_values( array_filter( (array) ( $args['items'] ?? [] ), static fn( $i ): bool => is_array( $i ) && (int) ( $i['n'] ?? 0 ) > 0 ) );
if ( ! $gadgetim_items ) {
	return;
}
$gadgetim_tones = [ 'violet', 'pink', 'cool', 'gold', 'green' ];
?>
<ul class="<?php echo esc_attr( trim( 'pstats ' . (string) ( $args['class'] ?? '' ) ) ); ?>"<?php echo ! empty( $args['label'] ) ? ' aria-label="' . esc_attr( (string) $args['label'] ) . '"' : ''; ?>>
	<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
		<li class="pstat pstat--<?php echo esc_attr( $gadgetim_tones[ $gadgetim_i % count( $gadgetim_tones ) ] ); ?>" data-reveal style="--i:<?php echo (int) min( 7, $gadgetim_i ); ?>">
			<span class="pstat__ic" aria-hidden="true"><?php echo gadgetim_pages_icon( (string) ( $gadgetim_item['icon'] ?? 'sparkle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="pstat__n"><span data-count-to="<?php echo esc_attr( (string) (int) $gadgetim_item['n'] ); ?>"><?php echo esc_html( gadgetim_number( (int) $gadgetim_item['n'] ) ); ?></span><?php echo esc_html( (string) ( $gadgetim_item['suffix'] ?? '' ) ); ?></span>
			<span class="pstat__l"><?php echo esc_html( (string) ( $gadgetim_item['label'] ?? '' ) ); ?></span>
			<?php if ( ! empty( $gadgetim_item['text'] ) ) : ?>
				<span class="pstat__t"><?php echo esc_html( (string) $gadgetim_item['text'] ); ?></span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
