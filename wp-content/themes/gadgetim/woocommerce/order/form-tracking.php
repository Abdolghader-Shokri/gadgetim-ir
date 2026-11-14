<?php
/**
 * Order tracking form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/form-tracking.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a card with the order number and «mobile number or e-mail» (the theme's
 * shortcode handler in inc/commerce.php matches either; the checkout's e-mail is optional), a
 * gradient submit button and a WhatsApp help line. Field names are WooCommerce's.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

global $post;

$gadgetim_help = gadgetim_order_help_url();
// The form posts back to its own page. Rendered outside a page (WP-Cron on Local, 2026-11-14:
// "Attempt to read property ID on null"), the form has no action attribute and posts to the current address.
$gadgetim_action = $post instanceof WP_Post ? (string) get_permalink( $post ) : '';
?>

<form<?php echo '' !== $gadgetim_action ? ' action="' . esc_url( $gadgetim_action ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() inside. ?> method="post" class="woocommerce-form woocommerce-form-track-order track_order cm-card trk">

	<?php
	/**
	 * Action hook fired at the beginning of the form-tracking form.
	 *
	 * @since 6.5.0
	 */
	do_action( 'woocommerce_order_tracking_form_start' );
	?>

	<p class="trk__intro"><?php gadgetim_the_icon( 'info' ); ?><span><?php esc_html_e( 'The order number is in the confirmation message you received after ordering, and on the order received page.', 'gadgetim' ); ?></span></p>

	<div class="trk__fields">
		<p class="form-row form-row-first"><label for="orderid"><?php esc_html_e( 'Order number', 'gadgetim' ); ?></label> <input class="input-text" type="text" name="orderid" id="orderid" value="<?php echo isset( $_REQUEST['orderid'] ) ? esc_attr( wp_unslash( $_REQUEST['orderid'] ) ) : ''; ?>" placeholder="<?php echo esc_attr( gadgetim_digits( '1234' ) ); ?>" inputmode="numeric" autocomplete="off" dir="ltr" required aria-required="true" /></p><?php // @codingStandardsIgnoreLine ?>
		<p class="form-row form-row-last"><label for="order_email"><?php esc_html_e( 'Mobile number or email', 'gadgetim' ); ?></label> <input class="input-text" type="text" name="order_email" id="order_email" value="<?php echo isset( $_REQUEST['order_email'] ) ? esc_attr( wp_unslash( $_REQUEST['order_email'] ) ) : ''; ?>" placeholder="<?php echo esc_attr( gadgetim_digits( '0912 345 6789' ) ); ?>" autocomplete="tel" dir="ltr" required aria-required="true" aria-describedby="order_email_hint" /><span class="trk__hint" id="order_email_hint"><?php esc_html_e( 'The one you entered at checkout.', 'gadgetim' ); ?></span></p><?php // @codingStandardsIgnoreLine ?>
	</div>
	<div class="clear"></div>

	<?php
	/**
	 * Action hook fired in the middle of the form-tracking form (before the submit button).
	 *
	 * @since 6.5.0
	 */
	do_action( 'woocommerce_order_tracking_form' );
	?>

	<p class="form-row trk__go"><button type="submit" class="button btn btn--lg btn--block btn--glow<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="track" value="<?php esc_attr_e( 'Track', 'woocommerce' ); ?>"><?php gadgetim_the_icon( 'search' ); ?><span><?php esc_html_e( 'Track', 'woocommerce' ); ?></span></button></p>
	<?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>

	<?php if ( '' !== $gadgetim_help ) : ?>
		<p class="trk__help"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Cannot find it?', 'gadgetim' ); ?> <a href="<?php echo esc_url( $gadgetim_help ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Ask us on WhatsApp', 'gadgetim' ); ?></a></span></p>
	<?php endif; ?>

	<?php
	/**
	 * Action hook fired at the end of the form-tracking form (after the submit button).
	 *
	 * @since 6.5.0
	 */
	do_action( 'woocommerce_order_tracking_form_end' );
	?>

</form>
