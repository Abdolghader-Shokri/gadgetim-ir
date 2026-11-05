<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora) layout:
 *   phone:   delivery details → shipping cards → order summary (items collapsible) → payment
 *   desktop: main column (details, shipping, payment) + a sticky order summary column.
 * The payment section is printed by the gadgetim_checkout_payment action (inc/commerce.php moves
 * woocommerce_checkout_payment there from woocommerce_checkout_order_review). The shipping card and
 * the phone pay bar are refreshed with the order review (woocommerce_update_order_review_fragments).
 * Every WooCommerce hook and id stays in place.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo '<div class="cm-card co-must">' . gadgetim_icon( 'user' ) . '<p>' . esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) ) . '</p>' . gadgetim_button( gadgetim_account_label( true ), [ 'href' => wc_get_page_permalink( 'myaccount' ), 'size' => 'sm' ] ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
	return;
}

$gadgetim_count = gadgetim_cart_count();
?>

<form name="checkout" method="post" class="checkout woocommerce-checkout co" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>" id="gtm-checkout">

	<div class="co__main">
	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set co__details" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>

			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>

		<?php gadgetim_part( 'commerce/checkout-shipping' ); ?>
	</div>

	<aside class="co__aside" aria-labelledby="order_review_heading" data-co-aside>
		<div class="cm-card co-sum">
		<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

		<h3 id="order_review_heading" class="co-sum__head">
			<span class="cm-card__ic cm-card__ic--warm" aria-hidden="true"><?php gadgetim_the_icon( 'receipt' ); ?></span>
			<span class="co-sum__t"><?php esc_html_e( 'Your order', 'woocommerce' ); ?><small><?php echo esc_html( gadgetim_items_label( $gadgetim_count ) ); ?></small></span>
			<button type="button" class="co-sum__toggle" aria-expanded="true" aria-controls="co-items" data-co-items><span data-co-items-label><?php esc_html_e( 'Hide items', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'chev-d' ); ?></button>
		</h3>

		<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

		<div id="order_review" class="woocommerce-checkout-review-order">
			<?php do_action( 'woocommerce_checkout_order_review' ); ?>
		</div>

		<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

		<a class="co-sum__edit" href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php gadgetim_the_icon( 'edit' ); ?><?php esc_html_e( 'Edit cart', 'gadgetim' ); ?></a>
		</div>
	</aside>

	<section class="co__pay" id="co-pay" aria-labelledby="co-pay-t">
		<div class="cm-card co-card co-paycard">
			<header class="co-card__head">
				<span class="co-card__n" aria-hidden="true"><?php gadgetim_the_icon( 'wallet' ); ?></span>
				<div class="co-card__titles">
					<h3 class="co-card__t" id="co-pay-t"><?php esc_html_e( 'Payment', 'gadgetim' ); ?></h3>
					<p class="co-card__sub"><?php esc_html_e( 'Choose how you would like to pay and place your order.', 'gadgetim' ); ?></p>
				</div>
			</header>
			<?php
			/**
			 * Payment methods, terms and the place-order button (woocommerce_checkout_payment, 10).
			 *
			 * @since 1.0.0 (Gadgetim theme)
			 */
			do_action( 'gadgetim_checkout_payment' );
			?>
		</div>
	</section>

	<?php gadgetim_part( 'commerce/checkout-bar' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
