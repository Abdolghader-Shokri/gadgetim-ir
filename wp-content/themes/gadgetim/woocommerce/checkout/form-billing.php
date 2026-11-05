<?php
/**
 * Checkout billing information form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-billing.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a card with a numbered heading; the fields (gadgetim-core's Iranian field
 * set: name, mobile, optional e-mail, province, city, address, postcode) sit in a two-column grid
 * from 768 px. The field loop and every hook are WooCommerce's.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 * @global WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

$gadgetim_ships = WC()->cart && WC()->cart->needs_shipping();
?>
<div class="woocommerce-billing-fields cm-card co-card co-card--billing">
	<header class="co-card__head">
		<span class="co-card__n" aria-hidden="true"><?php gadgetim_the_icon( $gadgetim_ships ? 'pin' : 'user' ); ?></span>
		<div class="co-card__titles">
			<?php if ( $gadgetim_ships ) : ?>
				<h3 class="co-card__t"><?php esc_html_e( 'Delivery details', 'gadgetim' ); ?></h3>
				<p class="co-card__sub"><?php esc_html_e( 'Who receives the parcel, and where should it go?', 'gadgetim' ); ?></p>
			<?php else : ?>
				<h3 class="co-card__t"><?php esc_html_e( 'Billing details', 'woocommerce' ); ?></h3>
			<?php endif; ?>
		</div>
	</header>

	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<div class="woocommerce-billing-fields__field-wrapper co-fields">
		<?php
		$fields = $checkout->get_checkout_fields( 'billing' );

		foreach ( $fields as $key => $field ) {
			woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
		}
		?>
	</div>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
	<div class="woocommerce-account-fields cm-card co-card co-card--account">
		<?php if ( ! $checkout->is_registration_required() ) : ?>

			<p class="form-row form-row-wide create-account co-switch">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></span>
				</label>
			</p>

		<?php endif; ?>

		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>

		<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>

			<div class="create-account co-fields">
				<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
				<div class="clear"></div>
			</div>

		<?php endif; ?>

		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
