<?php
/**
 * Footer newsletter: admin-post handler storing subscriber e-mails in the option
 * gadgetim_newsletter (not autoloaded) until a mailing service is chosen.
 *
 * Protection: nonce (gadgetim_newsletter), honeypot field "website" (must stay empty), token-bucket
 * rate limit per hashed IP (Support\RateLimit, 5 per hour) when the plugin is active.
 * Without JS the form posts and redirects back with ?newsletter=ok|invalid|error#newsletter;
 * with JS (app.js) it posts with ajax=1 and gets JSON { ok, message }.
 *
 * Note: page caching keeps a nonce in cached HTML; keep the guest page-cache lifetime under the
 * nonce life (12 h); a stale nonce only shows the generic error.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

/**
 * Whether the footer form is shown (Storefront setting theme.newsletter, default on).
 */
function gadgetim_newsletter_enabled(): bool {
	return false !== gadgetim_setting( 'theme.newsletter', true ) && '0' !== (string) gadgetim_setting( 'theme.newsletter', true );
}

/**
 * admin-post(_nopriv)_gadgetim_newsletter.
 */
function gadgetim_newsletter_handle(): void {
	$ajax   = isset( $_POST['ajax'] ) && '1' === $_POST['ajax']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read before the nonce check only to pick the response format.
	$back   = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$finish = static function ( string $status ) use ( $ajax, $back ): void {
		$messages = [
			'ok'      => __( 'Thank you! You are on the list.', 'gadgetim' ),
			'invalid' => __( 'Please enter a valid email address.', 'gadgetim' ),
			'error'   => __( 'Something went wrong. Please try again.', 'gadgetim' ),
		];
		if ( $ajax ) {
			wp_send_json(
				[
					'ok'      => 'ok' === $status,
					'message' => $messages[ $status ] ?? $messages['error'],
				],
				'ok' === $status ? 200 : 400
			);
		}
		wp_safe_redirect( add_query_arg( 'newsletter', $status, remove_query_arg( 'newsletter', $back ) ) . '#newsletter' );
		exit;
	};

	if ( ! isset( $_POST['_gtm_news'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_gtm_news'] ) ), 'gadgetim_newsletter' ) ) {
		$finish( 'error' );
	}
	// Honeypot: bots fill every field; people never see this one. Pretend success.
	if ( ! empty( $_POST['website'] ) ) {
		$finish( 'ok' );
	}
	if ( class_exists( 'Gadgetim\Core\Support\RateLimit' ) && ! Gadgetim\Core\Support\RateLimit::allow( 'newsletter', 5, HOUR_IN_SECONDS ) ) {
		$finish( 'error' );
	}
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		$finish( 'invalid' );
	}
	$list = get_option( 'gadgetim_newsletter', [] );
	$list = is_array( $list ) ? $list : [];
	$key  = strtolower( $email );
	if ( ! isset( $list[ $key ] ) ) {
		$list[ $key ] = [
			'email' => $email,
			'lang'  => gadgetim_lang(),
			'time'  => time(),
		];
		if ( false === get_option( 'gadgetim_newsletter', false ) ) {
			add_option( 'gadgetim_newsletter', $list, '', false );
		} else {
			update_option( 'gadgetim_newsletter', $list, false );
		}
		/**
		 * Fires after a new newsletter subscription was stored.
		 *
		 * @param string $email E-mail.
		 */
		do_action( 'gadgetim_newsletter_subscribed', $email );
	}
	$finish( 'ok' );
}
add_action( 'admin_post_gadgetim_newsletter', 'gadgetim_newsletter_handle' );
add_action( 'admin_post_nopriv_gadgetim_newsletter', 'gadgetim_newsletter_handle' );
