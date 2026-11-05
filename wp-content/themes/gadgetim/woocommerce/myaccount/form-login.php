<?php
/**
 * Login Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-login.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a two-panel sign-in card — an aurora panel with the account benefits, and the
 * WooCommerce forms. With registration on, «log in | sign up» tabs (app.js tabs; the inactive panel
 * is hidden only once JavaScript runs, and a failed sign-up reopens on its tab). A guest-order
 * tracking link closes the card. Form fields, names, nonces and hooks are WooCommerce's.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$gadgetim_reg      = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$gadgetim_register = $gadgetim_reg && isset( $_POST['register'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- presence check only: reopen the sign-up tab after a failed attempt.
$gadgetim_track    = gadgetim_order_tracking_url();

do_action( 'woocommerce_before_customer_login_form' ); ?>

<div class="auth<?php echo $gadgetim_reg ? ' auth--tabs u-columns col2-set' : ''; ?>" id="customer_login">

	<div class="auth__art" aria-hidden="true">
		<span class="auth__fx"><i></i><i></i><i></i></span>
		<?php echo gadgetim_monogram( 'auth', 'auth__mark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<p class="auth__hello"><?php esc_html_e( 'Welcome to Gadgetim', 'gadgetim' ); ?></p>
		<ul class="auth__perks">
			<li><?php gadgetim_the_icon( 'truck' ); ?><span><?php esc_html_e( 'Follow every order from checkout to your door', 'gadgetim' ); ?></span></li>
			<li><?php gadgetim_the_icon( 'pin' ); ?><span><?php esc_html_e( 'Save your address for a faster checkout', 'gadgetim' ); ?></span></li>
			<li><?php gadgetim_the_icon( 'shield' ); ?><span><?php esc_html_e( 'Genuine products with an authenticity guarantee', 'gadgetim' ); ?></span></li>
		</ul>
	</div>

	<div class="cm-card auth__card">

		<?php if ( $gadgetim_reg ) : ?>
			<div class="tabs auth__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Log in or sign up', 'gadgetim' ); ?>">
				<button class="tab" type="button" role="tab" id="auth-tab-login" aria-controls="auth-login" aria-selected="<?php echo $gadgetim_register ? 'false' : 'true'; ?>" tabindex="<?php echo $gadgetim_register ? '-1' : '0'; ?>"><?php esc_html_e( 'Login', 'woocommerce' ); ?></button>
				<button class="tab" type="button" role="tab" id="auth-tab-register" aria-controls="auth-register" aria-selected="<?php echo $gadgetim_register ? 'true' : 'false'; ?>" tabindex="<?php echo $gadgetim_register ? '0' : '-1'; ?>"><?php esc_html_e( 'Register', 'woocommerce' ); ?></button>
			</div>

	<div class="u-column1 col-1 auth__panel<?php echo $gadgetim_register ? ' is-inactive' : ''; ?>" id="auth-login" role="tabpanel" aria-labelledby="auth-tab-login">

		<?php endif; ?>

		<h2 class="auth__t"><?php esc_html_e( 'Login', 'woocommerce' ); ?></h2>
		<p class="auth__sub"><?php esc_html_e( 'Use your username or email and your password.', 'gadgetim' ); ?></p>

		<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" dir="auto" /><?php // @codingStandardsIgnoreLine ?>
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>

			<p class="form-row auth__row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
				<span class="woocommerce-LostPassword lost_password">
					<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
				</span>
			</p>
			<p class="form-row">
				<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit btn btn--lg btn--block<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php gadgetim_the_icon( 'user' ); ?><span><?php esc_html_e( 'Log in', 'woocommerce' ); ?></span></button>
			</p>

			<?php do_action( 'woocommerce_login_form_end' ); ?>

		</form>

<?php if ( $gadgetim_reg ) : ?>

	</div>

	<div class="u-column2 col-2 auth__panel<?php echo $gadgetim_register ? '' : ' is-inactive'; ?>" id="auth-register" role="tabpanel" aria-labelledby="auth-tab-register">

		<h2 class="auth__t"><?php esc_html_e( 'Register', 'woocommerce' ); ?></h2>
		<p class="auth__sub"><?php esc_html_e( 'One minute, and your next checkout is faster.', 'gadgetim' ); ?></p>

		<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

			<?php do_action( 'woocommerce_register_form_start' ); ?>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required aria-required="true" dir="auto" /><?php // @codingStandardsIgnoreLine ?>
				</p>

			<?php endif; ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
				<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required aria-required="true" /><?php // @codingStandardsIgnoreLine ?>
			</p>

			<?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
					<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span><span class="screen-reader-text"><?php esc_html_e( 'Required', 'woocommerce' ); ?></span></label>
					<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
				</p>

			<?php else : ?>

				<p class="auth__note"><?php gadgetim_the_icon( 'mail' ); ?><span><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></span></p>

			<?php endif; ?>

			<?php do_action( 'woocommerce_register_form' ); ?>

			<p class="woocommerce-form-row form-row">
				<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
				<button type="submit" class="woocommerce-Button woocommerce-button button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?> woocommerce-form-register__submit btn btn--lg btn--block" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php gadgetim_the_icon( 'sparkle' ); ?><span><?php esc_html_e( 'Register', 'woocommerce' ); ?></span></button>
			</p>

			<?php do_action( 'woocommerce_register_form_end' ); ?>

		</form>

	</div>

<?php endif; ?>

		<?php if ( '' !== $gadgetim_track ) : ?>
			<p class="auth__track"><?php gadgetim_the_icon( 'truck' ); ?><span><?php esc_html_e( 'Ordered without an account?', 'gadgetim' ); ?> <a href="<?php echo esc_url( $gadgetim_track ); ?>"><?php esc_html_e( 'Track your order', 'gadgetim' ); ?></a></span></p>
		<?php endif; ?>
	</div>

</div>

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
