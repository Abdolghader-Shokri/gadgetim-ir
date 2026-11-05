<?php
/**
 * Commerce heading: the steps indicator (cart → delivery details → payment) on the cart, checkout,
 * order-pay and order-received screens, breadcrumbs on account and tracking pages, and the page
 * title (endpoint-aware: it runs inside the loop).
 *
 * @package Gadgetim
 *
 * @var array $args { view: string }
 */

declare( strict_types=1 );

$gadgetim_view  = (string) ( $args['view'] ?? '' );
$gadgetim_title = gadgetim_digits( wp_strip_all_tags( get_the_title() ) );
$gadgetim_steps = [
	'cart'     => 'cart',
	'checkout' => 'details',
	'pay'      => 'payment',
	'received' => 'done',
][ $gadgetim_view ] ?? '';
if ( 'received' === $gadgetim_view ) {
	// A declined payment is not a finished checkout: the payment step stays current.
	$gadgetim_order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
	if ( $gadgetim_order instanceof WC_Order && $gadgetim_order->has_status( 'failed' ) ) {
		$gadgetim_steps = 'payment';
	}
}

if ( '' !== $gadgetim_steps ) {
	echo gadgetim_commerce_steps( $gadgetim_steps ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
}
if ( in_array( $gadgetim_view, [ 'account', 'tracking' ], true ) ) {
	gadgetim_breadcrumbs();
}
?>
<?php if ( 'received' === $gadgetim_view ) : ?>
	<h1 class="sr-only"><?php echo esc_html( $gadgetim_title ); ?></h1>
<?php elseif ( 'auth' === $gadgetim_view ) : ?>
	<h1 class="<?php echo is_wc_endpoint_url( 'lost-password' ) ? 'cm-head__t cm-head__t--center' : 'sr-only'; ?>"><?php echo esc_html( $gadgetim_title ); ?></h1>
<?php elseif ( 'tracking' === $gadgetim_view ) : ?>
	<header class="cm-hero">
		<span class="cm-hero__ic" aria-hidden="true"><?php gadgetim_the_icon( 'truck' ); ?></span>
		<div class="cm-hero__txt">
			<h1 class="cm-head__t"><?php echo esc_html( $gadgetim_title ); ?></h1>
			<p class="cm-hero__sub"><?php esc_html_e( 'See where your parcel is: enter the order number and the mobile number or email you used at checkout.', 'gadgetim' ); ?></p>
		</div>
	</header>
<?php else : ?>
	<header class="cm-head">
		<h1 class="cm-head__t"><?php echo esc_html( $gadgetim_title ); ?></h1>
		<?php if ( in_array( $gadgetim_view, [ 'checkout', 'pay' ], true ) ) : ?>
			<span class="cm-safe"><?php gadgetim_the_icon( 'lock' ); ?><?php esc_html_e( 'Secure checkout', 'gadgetim' ); ?></span>
		<?php elseif ( 'cart' === $gadgetim_view && gadgetim_cart_count() > 0 ) : ?>
			<span class="cm-safe cm-safe--soft"><?php gadgetim_the_icon( 'shield' ); ?><?php esc_html_e( 'Every product genuine, with an authenticity guarantee', 'gadgetim' ); ?></span>
		<?php endif; ?>
	</header>
<?php endif; ?>
