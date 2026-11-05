<?php
/**
 * Newsletter card: posts to admin-post.php?action=gadgetim_newsletter (inc/newsletter.php) with a
 * nonce and a honeypot; app.js submits it in place and shows the message in the live region.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

if ( ! gadgetim_newsletter_enabled() ) {
	return;
}
$gadgetim_status   = isset( $_GET['newsletter'] ) ? sanitize_key( wp_unslash( (string) $_GET['newsletter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status after the redirect.
$gadgetim_messages = [
	'ok'      => __( 'Thank you! You are on the list.', 'gadgetim' ),
	'invalid' => __( 'Please enter a valid email address.', 'gadgetim' ),
	'error'   => __( 'Something went wrong. Please try again.', 'gadgetim' ),
];
$gadgetim_message  = $gadgetim_messages[ $gadgetim_status ] ?? '';
?>
<section class="news" id="newsletter" aria-labelledby="news-title" data-reveal>
	<div class="news__txt">
		<span class="news__ic" aria-hidden="true"><?php gadgetim_the_icon( 'mail' ); ?></span>
		<div>
			<h2 id="news-title"><?php esc_html_e( 'Never miss a deal', 'gadgetim' ); ?></h2>
			<p><?php esc_html_e( 'Join the newsletter and be the first to hear about amazing offers and new gadgets.', 'gadgetim' ); ?></p>
		</div>
	</div>
	<form class="news__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-newsletter novalidate>
		<input type="hidden" name="action" value="gadgetim_newsletter">
		<?php wp_nonce_field( 'gadgetim_newsletter', '_gtm_news', false ); ?>
		<label class="sr-only" for="news-email"><?php esc_html_e( 'Your email', 'gadgetim' ); ?></label>
		<input id="news-email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="<?php esc_attr_e( 'Enter your email', 'gadgetim' ); ?>" required aria-describedby="news-msg">
		<span class="news__hp" aria-hidden="true"><label for="news-website"><?php esc_html_e( 'Leave this field empty', 'gadgetim' ); ?></label><input id="news-website" name="website" type="text" tabindex="-1" autocomplete="off"></span>
		<button class="btn" type="submit"><span><?php esc_html_e( 'Subscribe', 'gadgetim' ); ?></span></button>
		<p class="news__msg<?php echo in_array( $gadgetim_status, [ 'invalid', 'error' ], true ) ? ' is-err' : ''; ?>" id="news-msg" role="status" aria-live="polite"><?php echo esc_html( $gadgetim_message ); ?></p>
	</form>
</section>
