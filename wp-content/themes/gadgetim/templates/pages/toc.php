<?php
/**
 * Table of contents of a long page or post (items from gadgetim_pages_prepare()).
 * mode "inline": a <details> card at the top of the content (phones and tablets);
 * mode "aside": a sticky list in the desktop aside. pages.js marks the section being read
 * (aria-current) in both.
 *
 * Args: items ([ [ id, text, level ], … ]), mode (inline|aside).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_items = (array) ( $args['items'] ?? [] );
$gadgetim_mode  = 'aside' === ( $args['mode'] ?? 'inline' ) ? 'aside' : 'inline';
if ( count( $gadgetim_items ) < 3 ) {
	return;
}
gadgetim_pages_use_script();
/* translators: %s: number of sections */
$gadgetim_count = sprintf( _n( '%s section', '%s sections', count( $gadgetim_items ), 'gadgetim' ), gadgetim_number( count( $gadgetim_items ) ) );
?>
<?php if ( 'inline' === $gadgetim_mode ) : ?>
	<details class="toc toc--inline">
		<summary class="toc__sum">
			<span class="toc__ic"><?php echo gadgetim_pages_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="toc__st"><b><?php esc_html_e( 'On this page', 'gadgetim' ); ?></b><small><?php echo esc_html( $gadgetim_count ); ?></small></span>
			<?php gadgetim_the_icon( 'chev-d', 'toc__chev' ); ?>
		</summary>
		<ol class="toc__list" data-toc>
			<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
				<li><a href="#<?php echo esc_attr( (string) $gadgetim_item['id'] ); ?>"><span class="toc__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_i + 1 ) ); ?></span><span><?php echo gadgetim_bidi( (string) $gadgetim_item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span></a></li>
			<?php endforeach; ?>
		</ol>
	</details>
<?php else : ?>
	<nav class="toc toc--aside" aria-labelledby="toc-aside-t">
		<h2 class="toc__t" id="toc-aside-t"><span class="toc__ic"><?php echo gadgetim_pages_icon( 'list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'On this page', 'gadgetim' ); ?></h2>
		<ol class="toc__list" data-toc>
			<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
				<li><a href="#<?php echo esc_attr( (string) $gadgetim_item['id'] ); ?>"><span class="toc__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( $gadgetim_i + 1 ) ); ?></span><span><?php echo gadgetim_bidi( (string) $gadgetim_item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span></a></li>
			<?php endforeach; ?>
		</ol>
	</nav>
<?php endif; ?>
