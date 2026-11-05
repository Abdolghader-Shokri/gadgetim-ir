<?php
/**
 * Contact form → admin-post.php?action=gadgetim_contact (inc/pages.php gadgetim_contact_handle()):
 * name, phone and e-mail (one of the two required), topic (?topic=<key> preselects it), order
 * number, message; nonce, honeypot and time trap. Works as a plain POST that redirects back with
 * ?contact=<status>&cf=<field keys> (never values); pages.js validates on the fly, sends it in place
 * (ajax=1) and shows the success card.
 *
 * Args: id (section id, default contact-form), class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

gadgetim_pages_use_script();

// Display-only status after the no-JS redirect.
$gadgetim_status   = isset( $_GET['contact'] ) ? sanitize_key( wp_unslash( (string) $_GET['contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gadgetim_flagged  = isset( $_GET['cf'] ) ? array_values( array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( (string) $_GET['cf'] ) ) ) ) ) ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gadgetim_topic    = isset( $_GET['topic'] ) ? sanitize_key( wp_unslash( (string) $_GET['topic'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$gadgetim_messages = gadgetim_contact_messages();
$gadgetim_errors   = gadgetim_contact_field_errors();
$gadgetim_subjects = gadgetim_contact_subjects();
$gadgetim_sent     = 'sent' === $gadgetim_status;
$gadgetim_alert    = ! $gadgetim_sent && isset( $gadgetim_messages[ $gadgetim_status ] ) ? $gadgetim_messages[ $gadgetim_status ] : '';
$gadgetim_privacy  = get_privacy_policy_url();
$gadgetim_privacy  = '' !== $gadgetim_privacy ? $gadgetim_privacy : gadgetim_pages_role_url( 'privacy' );
$gadgetim_again    = remove_query_arg( [ 'contact', 'cf' ] ) . '#contact-form';
$gadgetim_id       = (string) ( $args['id'] ?? 'contact-form' );

// The no-JS redirect only names the fields; both reach fields flagged means "neither given".
$gadgetim_reach = in_array( 'phone', $gadgetim_flagged, true ) && in_array( 'email', $gadgetim_flagged, true );
$gadgetim_err   = static function ( string $key ) use ( $gadgetim_flagged, $gadgetim_errors, $gadgetim_reach ): string {
	if ( ! in_array( $key, $gadgetim_flagged, true ) ) {
		return '';
	}
	if ( $gadgetim_reach && in_array( $key, [ 'phone', 'email' ], true ) ) {
		return $gadgetim_errors['reach'];
	}
	return $gadgetim_errors[ $key ] ?? '';
};
$gadgetim_field = static function ( string $key ) use ( $gadgetim_err ): string {
	$error = $gadgetim_err( $key );
	$attrs = ' aria-describedby="' . ( in_array( $key, [ 'phone', 'email' ], true ) ? 'cf-reach ' : '' ) . 'cf-' . $key . '-err"';
	if ( '' !== $error ) {
		$attrs .= ' aria-invalid="true"';
	}
	return $attrs;
};
$gadgetim_js = [
	'errors'  => $gadgetim_errors,
	'error'   => $gadgetim_messages['error'],
	'invalid' => $gadgetim_messages['invalid'],
	'sent'    => $gadgetim_messages['sent'],
	/* translators: 1: characters typed, 2: maximum characters */
	'count'   => __( '%1$s of %2$s characters', 'gadgetim' ),
];
?>
<section class="<?php echo esc_attr( trim( 'cform ' . (string) ( $args['class'] ?? '' ) ) ); ?>" id="<?php echo esc_attr( $gadgetim_id ); ?>" aria-labelledby="cform-t" tabindex="-1">
	<div class="cform__head">
		<span class="cform__ic" aria-hidden="true"><?php echo gadgetim_pages_icon( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<div>
			<h2 class="cform__t" id="cform-t"><?php esc_html_e( 'Send us a message', 'gadgetim' ); ?></h2>
			<p class="cform__sub"><?php esc_html_e( 'Fill in the form and we will reply by phone or email.', 'gadgetim' ); ?></p>
		</div>
	</div>

	<div class="cform__done" data-cform-done tabindex="-1"<?php echo $gadgetim_sent ? '' : ' hidden'; ?>>
		<span class="cform__check" aria-hidden="true"><svg viewBox="0 0 52 52" focusable="false"><circle cx="26" cy="26" r="23"/><path d="m15.5 27 7.2 7.2 14-15"/></svg></span>
		<h3 class="cform__dt"><?php esc_html_e( 'Message sent!', 'gadgetim' ); ?></h3>
		<p class="cform__dp" role="status"><?php echo esc_html( $gadgetim_messages['sent'] ); ?></p>
		<a class="btn btn--soft btn--sm" href="<?php echo esc_url( $gadgetim_again ); ?>" data-cform-again><?php echo gadgetim_pages_icon( 'send' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php esc_html_e( 'Send another message', 'gadgetim' ); ?></span></a>
	</div>

	<form class="cform__form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" data-contact-form data-msg="<?php echo esc_attr( (string) wp_json_encode( $gadgetim_js, JSON_UNESCAPED_UNICODE ) ); ?>"<?php echo $gadgetim_sent ? ' hidden' : ''; ?>>
		<input type="hidden" name="action" value="gadgetim_contact">
		<input type="hidden" name="_gtm_t" value="<?php echo esc_attr( (string) time() ); ?>">
		<?php wp_nonce_field( 'gadgetim_contact', '_gtm_contact' ); ?>

		<p class="cform__alert" role="alert" data-cform-alert<?php echo '' !== $gadgetim_alert ? '' : ' hidden'; ?>><?php gadgetim_the_icon( 'alert' ); ?><span><?php echo esc_html( $gadgetim_alert ); ?></span></p>

		<div class="cform__grid">
			<p class="fld fld--name">
				<label for="cf-name"><?php esc_html_e( 'Your name', 'gadgetim' ); ?> <span class="fld__req" aria-hidden="true">*</span></label>
				<input id="cf-name" name="name" type="text" autocomplete="name" required minlength="2" maxlength="80"<?php echo $gadgetim_field( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attributes. ?>>
				<span class="fld__err" id="cf-name-err" data-err-for="name"><?php echo esc_html( $gadgetim_err( 'name' ) ); ?></span>
			</p>
			<p class="fld fld--phone">
				<label for="cf-phone"><?php esc_html_e( 'Mobile or phone', 'gadgetim' ); ?></label>
				<input id="cf-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" maxlength="24" placeholder="<?php echo esc_attr( gadgetim_digits( '0912 345 6789' ) ); ?>"<?php echo $gadgetim_field( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attributes. ?>>
				<span class="fld__err" id="cf-phone-err" data-err-for="phone"><?php echo esc_html( $gadgetim_err( 'phone' ) ); ?></span>
			</p>
			<p class="fld fld--email">
				<label for="cf-email"><?php esc_html_e( 'Email', 'gadgetim' ); ?></label>
				<input id="cf-email" name="email" type="email" inputmode="email" autocomplete="email" dir="ltr" maxlength="120" placeholder="name@example.com"<?php echo $gadgetim_field( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attributes. ?>>
				<span class="fld__err" id="cf-email-err" data-err-for="email"><?php echo esc_html( $gadgetim_err( 'email' ) ); ?></span>
			</p>
			<p class="fld__hint cform__reach" id="cf-reach"><?php gadgetim_the_icon( 'info' ); ?><span><?php esc_html_e( 'Enter at least one of the two so we can reply.', 'gadgetim' ); ?></span></p>
			<p class="fld fld--subject">
				<label for="cf-subject"><?php esc_html_e( 'Topic', 'gadgetim' ); ?></label>
				<select id="cf-subject" name="subject">
					<?php foreach ( $gadgetim_subjects as $gadgetim_key => $gadgetim_label ) : ?>
						<option value="<?php echo esc_attr( (string) $gadgetim_key ); ?>"<?php selected( $gadgetim_topic, (string) $gadgetim_key ); ?>><?php echo esc_html( (string) $gadgetim_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="fld fld--order">
				<label for="cf-order"><?php esc_html_e( 'Order number', 'gadgetim' ); ?> <small><?php esc_html_e( '(optional)', 'gadgetim' ); ?></small></label>
				<input id="cf-order" name="order" type="text" inputmode="numeric" dir="ltr" maxlength="32" autocomplete="off">
			</p>
			<p class="fld fld--message">
				<label for="cf-message"><?php esc_html_e( 'Your message', 'gadgetim' ); ?> <span class="fld__req" aria-hidden="true">*</span></label>
				<textarea id="cf-message" name="message" rows="6" required minlength="10" maxlength="3000"<?php echo $gadgetim_field( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attributes. ?>></textarea>
				<span class="fld__meta"><span class="fld__err" id="cf-message-err" data-err-for="message"><?php echo esc_html( $gadgetim_err( 'message' ) ); ?></span><span class="fld__count" data-cform-count aria-hidden="true"></span></span>
			</p>
			<span class="cform__hp" aria-hidden="true"><label for="cf-website"><?php esc_html_e( 'Leave this field empty', 'gadgetim' ); ?></label><input id="cf-website" name="website" type="text" tabindex="-1" autocomplete="off"></span>
		</div>

		<div class="cform__foot">
			<button class="btn btn--lg cform__send" type="submit"><span><?php esc_html_e( 'Send message', 'gadgetim' ); ?></span><?php echo gadgetim_pages_icon( 'send', 'cform__plane' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			<p class="cform__note">
				<?php gadgetim_the_icon( 'lock' ); ?>
				<span>
					<?php if ( '' !== $gadgetim_privacy ) : ?>
						<?php
						printf(
							/* translators: %s: link to the privacy policy */
							esc_html__( 'We use your details only to answer you. Read our %s.', 'gadgetim' ),
							'<a href="' . esc_url( $gadgetim_privacy ) . '">' . esc_html__( 'privacy policy', 'gadgetim' ) . '</a>'
						);
						?>
					<?php else : ?>
						<?php esc_html_e( 'We use your details only to answer you.', 'gadgetim' ); ?>
					<?php endif; ?>
				</span>
			</p>
		</div>
	</form>
</section>
