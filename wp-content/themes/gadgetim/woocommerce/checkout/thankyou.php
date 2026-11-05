<?php
/**
 * Thankyou page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/thankyou.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): a celebration card (animated check, confetti, WooCommerce's received message)
 * with the order facts as chips (number with a copy button, Jalali date, total, payment method),
 * the order status timeline and a «what happens next» card; WooCommerce's order details follow
 * through the woocommerce_thankyou hook. A failed order gets a calm error card with «pay again».
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 11.2.0
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order ty">

	<?php
	if ( $order ) :

		do_action( 'woocommerce_before_thankyou', $order->get_id() );
		?>

		<?php if ( $order->has_status( 'failed' ) ) : ?>

			<?php
			$default_order_failed_text = esc_html__( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' );

			/**
			 * Filters the message shown when an order has failed.
			 *
			 * @param string   $message The failed order message.
			 * @param WC_Order $order   The failed order.
			 *
			 * @since 11.2.0
			 */
			$order_failed_text = apply_filters( 'woocommerce_thankyou_order_failed_text', $default_order_failed_text, $order );
			$order_failed_text = is_string( $order_failed_text ) ? $order_failed_text : $default_order_failed_text;
			?>

			<div class="cm-card ty-fail">
				<span class="ty-fail__ic" aria-hidden="true"><?php gadgetim_the_icon( 'alert' ); ?></span>
				<h2 class="ty-fail__t"><?php esc_html_e( 'The payment did not go through', 'gadgetim' ); ?></h2>
				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed"><?php echo wp_kses_post( $order_failed_text ); ?></p>

				<p class="woocommerce-notice woocommerce-notice--error woocommerce-thankyou-order-failed-actions ty-fail__a">
					<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="button pay btn btn--glow"><?php gadgetim_the_icon( 'lock' ); ?><span><?php esc_html_e( 'Pay', 'woocommerce' ); ?></span></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="button pay btn btn--outline"><?php gadgetim_the_icon( 'user' ); ?><span><?php esc_html_e( 'My account', 'woocommerce' ); ?></span></a>
					<?php endif; ?>
				</p>
			</div>

		<?php else : ?>

			<section class="ty__hero" aria-labelledby="ty-title">
				<span class="ty__fx" aria-hidden="true"><i></i><i></i><i></i></span>
				<span class="ty__confetti" aria-hidden="true"><?php echo str_repeat( '<i></i>', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?></span>
				<span class="ty__badge" aria-hidden="true">
					<svg viewBox="0 0 64 64" focusable="false"><circle class="ty__ring" cx="32" cy="32" r="28"/><path class="ty__tick" d="m19.5 33 8.5 8.5 17-18"/></svg>
				</span>

				<?php wc_get_template( 'checkout/order-received.php', array( 'order' => $order ) ); ?>

				<p class="ty__sub"><?php esc_html_e( 'We will check every item, seal your parcel and send it as soon as possible. If anything needs confirming, we will get in touch.', 'gadgetim' ); ?></p>

				<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details ty__facts">

					<li class="woocommerce-order-overview__order order">
						<span class="ty__k"><?php esc_html_e( 'Order number:', 'woocommerce' ); ?></span>
						<strong class="ty__v">
							<bdi><?php echo esc_html( gadgetim_order_number( $order ) ); ?></bdi>
							<button type="button" class="ty__copy" data-copy="<?php echo esc_attr( (string) $order->get_order_number() ); ?>" aria-label="<?php esc_attr_e( 'Copy the order number', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'copy' ); ?></button>
						</strong>
					</li>

					<li class="woocommerce-order-overview__date date">
						<span class="ty__k"><?php esc_html_e( 'Date:', 'woocommerce' ); ?></span>
						<strong class="ty__v"><?php echo esc_html( gadgetim_order_date( $order, 'medium' ) ); ?></strong>
					</li>

					<?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() && $order->get_billing_email() ) : ?>
						<li class="woocommerce-order-overview__email email">
							<span class="ty__k"><?php esc_html_e( 'Email:', 'woocommerce' ); ?></span>
							<strong class="ty__v"><bdi dir="ltr"><?php echo esc_html( $order->get_billing_email() ); ?></bdi></strong>
						</li>
					<?php endif; ?>

					<li class="woocommerce-order-overview__total total">
						<span class="ty__k"><?php esc_html_e( 'Total:', 'woocommerce' ); ?></span>
						<strong class="ty__v"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
					</li>

					<?php if ( $order->get_payment_method_title() ) : ?>
						<li class="woocommerce-order-overview__payment-method method">
							<span class="ty__k"><?php esc_html_e( 'Payment method:', 'woocommerce' ); ?></span>
							<strong class="ty__v"><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
						</li>
					<?php endif; ?>

				</ul>
			</section>

			<div class="ty__grid">
				<?php echo gadgetim_order_timeline( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template. ?>

				<section class="cm-card ty-next" aria-labelledby="ty-next-t">
					<header class="cm-card__head">
						<span class="cm-card__ic cm-card__ic--cool" aria-hidden="true"><?php gadgetim_the_icon( 'bell' ); ?></span>
						<div class="cm-card__titles"><h2 class="cm-card__t" id="ty-next-t"><?php esc_html_e( 'What happens next?', 'gadgetim' ); ?></h2></div>
					</header>
					<ol class="ty-next__list">
						<li><span class="ty-next__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( 1 ) ); ?></span><?php esc_html_e( 'We confirm your order and check every item for authenticity.', 'gadgetim' ); ?></li>
						<li><span class="ty-next__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( 2 ) ); ?></span><?php esc_html_e( 'Your parcel is sealed and handed to the courier.', 'gadgetim' ); ?></li>
						<li><span class="ty-next__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( 3 ) ); ?></span><?php esc_html_e( 'You can follow it any time with the order number and your mobile number.', 'gadgetim' ); ?></li>
					</ol>
					<div class="ty-next__a">
						<?php
						$gadgetim_track = gadgetim_order_tracking_url();
						if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() ) {
							echo gadgetim_button( __( 'View the order', 'gadgetim' ), [ 'href' => $order->get_view_order_url(), 'icon' => 'orders', 'icon_start' => true, 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						} elseif ( '' !== $gadgetim_track ) {
							echo gadgetim_button( __( 'Track your order', 'gadgetim' ), [ 'href' => $gadgetim_track, 'icon' => 'truck', 'icon_start' => true, 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						}
						$gadgetim_help = gadgetim_order_help_url( $order );
						if ( '' !== $gadgetim_help ) {
							echo gadgetim_button( __( 'Questions? WhatsApp us', 'gadgetim' ), [ 'href' => $gadgetim_help, 'variant' => 'outline', 'icon' => 'chat', 'icon_start' => true, 'size' => 'sm', 'attrs' => [ 'target' => '_blank', 'rel' => 'noopener nofollow' ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						}
						echo gadgetim_button( __( 'Continue shopping', 'gadgetim' ), [ 'href' => gadgetim_shop_url(), 'variant' => 'ghost', 'icon' => 'arrow', 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						?>
					</div>
				</section>
			</div>

		<?php endif; ?>

		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

	<?php else : ?>

		<section class="ty__hero ty__hero--plain" aria-labelledby="ty-title">
			<span class="ty__badge" aria-hidden="true">
				<svg viewBox="0 0 64 64" focusable="false"><circle class="ty__ring" cx="32" cy="32" r="28"/><path class="ty__tick" d="m19.5 33 8.5 8.5 17-18"/></svg>
			</span>
			<?php wc_get_template( 'checkout/order-received.php', array( 'order' => false ) ); ?>
		</section>

	<?php endif; ?>

</div>
