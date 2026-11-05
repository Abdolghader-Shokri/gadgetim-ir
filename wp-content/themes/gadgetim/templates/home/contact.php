<?php
/**
 * Contact strip: only when the footer newsletter is switched off (the footer then carries no
 * call to action above its columns) — WhatsApp advice and the support phone.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

if ( gadgetim_newsletter_enabled() ) {
	return;
}
$gadgetim_wa  = gadgetim_whatsapp_url( __( 'Hello, I would like advice on a product.', 'gadgetim' ) );
$gadgetim_tel = gadgetim_tel_href();
if ( '' === $gadgetim_wa && '' === $gadgetim_tel ) {
	return;
}
?>
<section class="container home-contact" aria-labelledby="home-contact-title">
	<div class="home-contact__in" data-reveal>
		<span class="home-contact__ic" aria-hidden="true"><?php gadgetim_the_icon( 'chat' ); ?></span>
		<div class="home-contact__t">
			<h2 id="home-contact-title"><?php esc_html_e( 'Not sure which one to choose?', 'gadgetim' ); ?></h2>
			<p><?php esc_html_e( 'Tell us what you need on WhatsApp and get honest advice before you buy.', 'gadgetim' ); ?></p>
		</div>
		<div class="home-contact__actions">
			<?php if ( '' !== $gadgetim_wa ) : ?>
				<a class="btn btn--success" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Chat on WhatsApp', 'gadgetim' ); ?></span></a>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_tel ) : ?>
				<a class="btn btn--outline" href="<?php echo esc_url( $gadgetim_tel ); ?>"><?php gadgetim_the_icon( 'phone' ); ?><span class="sr-only"><?php esc_html_e( 'Call us:', 'gadgetim' ); ?> </span><?php echo gadgetim_phone_display(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></a>
			<?php endif; ?>
		</div>
	</div>
</section>
