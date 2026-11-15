<?php
/**
 * Promo strip: animated gradient band with rotating messages (Storefront setting
 * theme.promo_messages, else the trust badges, else built-in copy) and, from 768 px, help links
 * and the support phone. app.js rotates the messages (pauses on hover/focus/hidden tab).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_messages = gadgetim_list_setting( 'theme.promo_messages' );
if ( ! $gadgetim_messages ) {
	$gadgetim_messages = gadgetim_list_setting( 'trust.badges' );
}
if ( ! $gadgetim_messages ) {
	$gadgetim_messages = [
		__( 'Every product is genuine, with an authenticity guarantee', 'gadgetim' ),
		__( 'Fast delivery across Iran', 'gadgetim' ),
		__( '7-day return guarantee', 'gadgetim' ),
		__( 'Advice and support on WhatsApp', 'gadgetim' ),
	];
}
$gadgetim_messages = array_slice( $gadgetim_messages, 0, 6 );
$gadgetim_icons    = [ 'shield', 'truck', 'return', 'chat', 'gift', 'bolt' ];

$gadgetim_links   = [];
$gadgetim_account = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'myaccount' ) : '';
if ( ! gadgetim_contact_mode() ) {
	// The public tracking page (order number + mobile or e-mail) serves guests as well; the
	// account's order list needs a login and is noindex. Until 2026-11-15 this link went to the
	// account, so the tracking page had no inbound link in any language.
	$gadgetim_tracking = gadgetim_order_tracking_page_id();
	$gadgetim_track    = $gadgetim_tracking > 0 ? (string) get_permalink( $gadgetim_tracking ) : '';
	if ( '' === $gadgetim_track && '' !== $gadgetim_account ) {
		$gadgetim_track = wc_get_endpoint_url( 'orders', '', $gadgetim_account );
	}
	if ( '' !== $gadgetim_track ) {
		$gadgetim_links[] = [ __( 'Track your order', 'gadgetim' ), $gadgetim_track ];
	}
}
$gadgetim_faq = gadgetim_page_url( [ 'سوالات-متداول', 'faq' ] );
if ( '' !== $gadgetim_faq ) {
	$gadgetim_links[] = [ __( 'FAQ', 'gadgetim' ), $gadgetim_faq ];
}
$gadgetim_contact_page = gadgetim_page_url( [ 'ارتباط-با-ما', 'contact', 'contact-us' ] );
if ( '' !== $gadgetim_contact_page ) {
	$gadgetim_links[] = [ __( 'Contact us', 'gadgetim' ), $gadgetim_contact_page ];
}
$gadgetim_tel = gadgetim_tel_href();
?>
<div class="promo" role="region" aria-label="<?php esc_attr_e( 'Shop announcements', 'gadgetim' ); ?>">
	<div class="promo__in container">
		<ul class="promo__ticker" data-ticker>
			<?php foreach ( $gadgetim_messages as $gadgetim_i => $gadgetim_message ) : ?>
				<li<?php echo 0 === $gadgetim_i ? ' class="is-on"' : ' aria-hidden="true"'; ?>><?php gadgetim_the_icon( $gadgetim_icons[ $gadgetim_i % count( $gadgetim_icons ) ] ); ?><span><?php echo esc_html( $gadgetim_message ); ?></span></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $gadgetim_links || '' !== $gadgetim_tel ) : ?>
			<nav class="promo__links" aria-label="<?php esc_attr_e( 'Help links', 'gadgetim' ); ?>">
				<?php foreach ( $gadgetim_links as [ $gadgetim_label, $gadgetim_url ] ) : ?>
					<a href="<?php echo esc_url( $gadgetim_url ); ?>"><?php echo esc_html( $gadgetim_label ); ?></a>
				<?php endforeach; ?>
				<?php if ( '' !== $gadgetim_tel ) : ?>
					<a class="promo__tel" href="<?php echo esc_url( $gadgetim_tel ); ?>"><?php gadgetim_the_icon( 'phone' ); ?><span class="sr-only"><?php esc_html_e( 'Call us:', 'gadgetim' ); ?> </span><?php echo gadgetim_phone_display(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</div>
