<?php
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim: quantity stepper («+ ۱ −», Persian digits over the real input) beside the gradient
 * add-to-cart button, then «خرید سریع» (same form, posted to «?gtm-buy-now=1» → checkout) and
 * «سفارش از واتساپ». gallery.js upgrades the main button to the theme's AJAX add (fly-to-cart, toast)
 * when the form carries nothing but the quantity; without JS it is WooCommerce's normal POST.
 * The stock line is printed by the theme's buy box (gadgetim_pdp_stock, through woocommerce_get_stock_html).
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.2.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

if ( $product->is_in_stock() ) :
	$gadgetim_min   = $product->get_min_purchase_quantity();
	$gadgetim_max   = $product->get_max_purchase_quantity();
	$gadgetim_value = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $gadgetim_min; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Template repopulates quantity; wc_stock_amount() normalizes it.
	$gadgetim_fixed = (float) $gadgetim_max > 0 && (float) $gadgetim_max <= (float) $gadgetim_min; // One possible quantity: WooCommerce renders a hidden input.
	$gadgetim_title = gadgetim_short_title( $product );
	$gadgetim_wa    = gadgetim_pdp_whatsapp_order_url( $product );
	$gadgetim_wa_1  = gadgetim_pdp_whatsapp_order_url( $product, gadgetim_number( max( 1, (int) $gadgetim_value ) ) );
	$gadgetim_form  = (string) apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() );

	$GLOBALS['gadgetim_pdp_alt_done'] = true; // The WhatsApp link is in this template (see gadgetim_pdp_secondary()).
	?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart buy__form" action="<?php echo esc_url( $gadgetim_form ); ?>" method="post" enctype='multipart/form-data' data-buy-form>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<div class="buy__main" data-buy-anchor>
			<?php do_action( 'woocommerce_before_add_to_cart_quantity' ); ?>

			<?php if ( ! $gadgetim_fixed ) : ?>
				<div class="qstep" data-qstep>
					<?php /* translators: %s: product title */ ?>
					<button class="qstep__btn" type="button" data-step="1" aria-label="<?php echo esc_attr( sprintf( __( 'Increase quantity of %s', 'gadgetim' ), $gadgetim_title ) ); ?>"><?php gadgetim_the_icon( 'plus' ); ?></button>
					<div class="qstep__field">
			<?php endif; ?>

			<?php
			woocommerce_quantity_input(
				[
					'min_value'    => $gadgetim_min,
					'max_value'    => $gadgetim_max,
					'input_value'  => $gadgetim_value,
					'product_name' => $gadgetim_title,
				]
			);
			?>

			<?php if ( ! $gadgetim_fixed ) : ?>
						<span class="qstep__val" aria-hidden="true" data-qstep-val><?php echo esc_html( gadgetim_number( max( 1, (int) $gadgetim_value ) ) ); ?></span>
					</div>
					<?php /* translators: %s: product title */ ?>
					<button class="qstep__btn" type="button" data-step="-1" aria-label="<?php echo esc_attr( sprintf( __( 'Decrease quantity of %s', 'gadgetim' ), $gadgetim_title ) ); ?>"><?php gadgetim_the_icon( 'minus' ); ?></button>
				</div>
			<?php endif; ?>

			<?php do_action( 'woocommerce_after_add_to_cart_quantity' ); ?>

			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" class="single_add_to_cart_button button alt btn btn--lg btn--glow buy__add<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" data-buy-add>
				<span class="buy__add-in"><?php gadgetim_the_icon( 'bag' ); ?><span><?php echo esc_html( $product->single_add_to_cart_text() ); ?></span></span>
				<span class="buy__add-ok" aria-hidden="true"><?php gadgetim_the_icon( 'check' ); ?><span><?php esc_html_e( 'Added to cart', 'gadgetim' ); ?></span></span>
			</button>
		</div>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>

		<div class="buy__alt">
			<button type="submit" name="add-to-cart" value="<?php echo esc_attr( (string) $product->get_id() ); ?>" formaction="<?php echo esc_url( add_query_arg( 'gtm-buy-now', '1', $gadgetim_form ) ); ?>" class="btn btn--dark buy__quick" data-buy-quick><?php gadgetim_the_icon( 'bolt' ); ?><span><?php esc_html_e( 'Quick buy', 'gadgetim' ); ?></span></button>
			<?php if ( '' !== $gadgetim_wa ) : ?>
				<a class="btn buy__wa" href="<?php echo esc_url( $gadgetim_wa_1 ); ?>" data-wa="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener nofollow"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Order on WhatsApp', 'gadgetim' ); ?></span></a>
			<?php endif; ?>
		</div>
	</form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>

<?php endif; ?>
