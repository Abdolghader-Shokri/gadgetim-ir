<?php
/**
 * Output a single payment method
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/payment-method.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a selectable card — the native radio (visually hidden, still focusable and
 * used by checkout.js), a radio indicator, the title and the gateway's logo; the description or
 * fields open below the selected card.
 *
 * @see         https://woocommerce.com/document/template-structure/
 * @package     WooCommerce\Templates
 * @version     3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gadgetim_logo = trim( (string) $gateway->get_icon() );
?>
<li class="wc_payment_method payment_method_<?php echo esc_attr( $gateway->id ); ?> pm__i">
	<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" type="radio" class="input-radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>" <?php checked( $gateway->chosen, true ); ?> data-order_button_text="<?php echo esc_attr( $gateway->order_button_text ); ?>" />

	<label for="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="pm__l">
		<span class="pm__radio" aria-hidden="true"></span>
		<span class="pm__t"><?php echo $gateway->get_title(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The base gateway title is sanitized; the public filter intentionally allows HTML. */ ?></span>
		<span class="pm__logo<?php echo '' === $gadgetim_logo ? ' pm__logo--ic' : ''; ?>"><?php echo '' !== $gadgetim_logo ? $gadgetim_logo : gadgetim_icon( 'card' ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The base icon attributes are escaped; the public filter intentionally allows HTML. */ ?></span>
	</label>
	<?php if ( $gateway->has_fields() || $gateway->get_description() ) : ?>
		<div class="payment_box payment_method_<?php echo esc_attr( $gateway->id ); ?>" <?php if ( ! $gateway->chosen ) : /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>style="display:none;"<?php endif; /* phpcs:ignore Squiz.ControlStructures.ControlSignature.NewlineAfterOpenBrace */ ?>>
			<?php $gateway->payment_fields(); ?>
		</div>
	<?php endif; ?>
</li>
