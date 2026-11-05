<?php
/**
 * Sticky buy bar (phones and tablets < 1100 px). Off-screen and inert until gallery.js sees the main
 * add-to-cart row scroll above the viewport; then it slides up and the bottom nav slides away
 * (the bar's cart button becomes the fly-to-cart target meanwhile). Price + add to cart (quantity
 * synced with the stepper) + cart; out of stock: WhatsApp question; call-for-price: the contact trio.
 *
 * @package Gadgetim
 *
 * @var array $args { product: WC_Product }
 */

declare( strict_types=1 );

$gadgetim_product = $args['product'] ?? null;
if ( ! $gadgetim_product instanceof WC_Product ) {
	return;
}

$gadgetim_id      = (string) $gadgetim_product->get_id();
$gadgetim_title   = gadgetim_short_title( $gadgetim_product );
$gadgetim_contact = gadgetim_contact_mode( $gadgetim_product );
$gadgetim_in      = $gadgetim_product->is_in_stock();
$gadgetim_simple  = $gadgetim_product->is_type( 'simple' ) && $gadgetim_product->is_purchasable();
$gadgetim_cart    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';

$gadgetim_channels = [];
if ( $gadgetim_contact && class_exists( 'Gadgetim\Core\ContactMode\ContactMode' ) && class_exists( 'Gadgetim\Core\ContactMode\Display' ) ) {
	$gadgetim_channels = (array) Gadgetim\Core\ContactMode\ContactMode::channels( Gadgetim\Core\ContactMode\Display::whatsapp_text( $gadgetim_product ), gadgetim_lang() );
}
$gadgetim_wa = $gadgetim_in ? '' : gadgetim_pdp_whatsapp_ask_url( $gadgetim_product );
if ( ! $gadgetim_contact && ! $gadgetim_in && '' === $gadgetim_wa ) {
	return;
}
if ( $gadgetim_contact && ! $gadgetim_channels ) {
	return;
}
?>
<div class="pbar<?php echo $gadgetim_contact ? ' pbar--contact' : ''; ?>" data-pbar data-product-id="<?php echo esc_attr( $gadgetim_id ); ?>" inert>
	<div class="pbar__in">
		<span class="pbar__img">
			<?php
			echo gadgetim_product_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
				$gadgetim_product,
				'gtm-thumb',
				[
					'sizes'   => 'mini',
					'loading' => 'lazy',
					'alt'     => '',
				]
			);
			?>
		</span>
		<span hidden data-title><?php echo esc_html( gadgetim_digits( $gadgetim_title ) ); ?></span>

		<?php if ( $gadgetim_contact ) : ?>
			<p class="pbar__label"><?php echo esc_html( class_exists( 'Gadgetim\Core\ContactMode\ContactMode' ) ? Gadgetim\Core\ContactMode\ContactMode::label( gadgetim_lang() ) : __( 'Ask for price', 'gadgetim' ) ); ?></p>
			<div class="pbar__contact">
				<?php
				$gadgetim_icons  = [
					'call'     => 'phone',
					'whatsapp' => 'chat',
					'telegram' => 'telegram',
				];
				$gadgetim_labels = [
					'call'     => __( 'Call', 'gadgetim' ),
					'whatsapp' => __( 'WhatsApp', 'gadgetim' ),
					'telegram' => __( 'Telegram', 'gadgetim' ),
				];
				foreach ( $gadgetim_channels as $gadgetim_channel => $gadgetim_data ) :
					$gadgetim_href = (string) ( $gadgetim_data['href'] ?? '' );
					if ( '' === $gadgetim_href ) {
						continue;
					}
					?>
					<a class="pbar__ch pbar__ch--<?php echo esc_attr( sanitize_html_class( (string) $gadgetim_channel ) ); ?>" href="<?php echo esc_url( $gadgetim_href ); ?>"<?php echo 'call' !== $gadgetim_channel ? ' target="_blank" rel="noopener nofollow"' : ''; ?>><?php gadgetim_the_icon( $gadgetim_icons[ $gadgetim_channel ] ?? 'chat' ); ?><span><?php echo esc_html( $gadgetim_labels[ $gadgetim_channel ] ?? ucfirst( (string) $gadgetim_channel ) ); ?></span></a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="pbar__price">
				<?php echo gadgetim_price_html( $gadgetim_product, [ 'class' => 'price--bar' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup. ?>
			</div>
			<?php if ( $gadgetim_in && $gadgetim_simple ) : ?>
				<a class="btn pbar__add buy-add" href="<?php echo esc_url( $gadgetim_product->add_to_cart_url() ); ?>" rel="nofollow" role="button" data-add-to-cart="<?php echo esc_attr( $gadgetim_id ); ?>" data-quantity="1">
					<span class="buy__add-in"><?php gadgetim_the_icon( 'bag' ); ?><span><?php esc_html_e( 'Add to cart', 'gadgetim' ); ?></span></span>
					<span class="buy__add-ok" aria-hidden="true"><?php gadgetim_the_icon( 'check' ); ?><span><?php esc_html_e( 'Added', 'gadgetim' ); ?></span></span>
				</a>
			<?php elseif ( $gadgetim_in ) : ?>
				<a class="btn pbar__add" href="#buybox"><?php esc_html_e( 'Choose options', 'gadgetim' ); ?></a>
			<?php else : ?>
				<a class="btn pbar__add buy__wa" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener nofollow"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Ask on WhatsApp', 'gadgetim' ); ?></span></a>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_cart && $gadgetim_in ) : ?>
				<a class="pbar__cart" href="<?php echo esc_url( $gadgetim_cart ); ?>" data-open="cart-drawer" aria-controls="cart-drawer" aria-expanded="false" aria-label="<?php esc_attr_e( 'Cart', 'gadgetim' ); ?>" data-cart-label data-pbar-cart>
					<span class="bnav__icw"><?php gadgetim_the_icon( 'bag' ); ?><b class="badge badge--cart is-empty" data-cart-count aria-hidden="true"></b></span>
				</a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
