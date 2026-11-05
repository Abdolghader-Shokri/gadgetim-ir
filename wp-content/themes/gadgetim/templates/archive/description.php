<?php
/**
 * The full term description (legacy category and brand texts) at the bottom of page 1, clamped
 * behind a fade with «ادامه مطلب» (filters.js; without JS the whole text shows). It stays in the
 * HTML for search engines and screen readers.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_info = gadgetim_archive_info();
$gadgetim_html = trim( (string) $gadgetim_info['description'] );
if ( '' === $gadgetim_html ) {
	return;
}
// Legacy texts: no inline styles or scripts, paragraphs from line breaks.
$gadgetim_html = (string) preg_replace( '/\s(style|class|id)=("[^"]*"|\'[^\']*\')/i', '', $gadgetim_html );
$gadgetim_html = wpautop( wp_kses_post( $gadgetim_html ) );
?>
<section class="adesc" aria-labelledby="adesc-title">
	<div class="adesc__head">
		<span class="sect__ic sect__ic--cool" aria-hidden="true"><?php gadgetim_the_icon( 'info' ); ?></span>
		<h2 class="adesc__t" id="adesc-title">
			<?php
			/* translators: %s: category or brand name */
			echo gadgetim_bidi( sprintf( __( 'About %s', 'gadgetim' ), (string) $gadgetim_info['name'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi().
			?>
		</h2>
	</div>
	<div class="adesc__body prose" id="adesc-body" data-desc>
		<?php echo $gadgetim_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() above. ?>
	</div>
	<button class="adesc__more" type="button" aria-expanded="false" aria-controls="adesc-body" data-desc-toggle hidden>
		<span class="adesc__more-on"><?php esc_html_e( 'Read more', 'gadgetim' ); ?></span>
		<span class="adesc__more-off"><?php esc_html_e( 'Show less', 'gadgetim' ); ?></span>
		<?php gadgetim_the_icon( 'chev-d' ); ?>
	</button>
</section>
