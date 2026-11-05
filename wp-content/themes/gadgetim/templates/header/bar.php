<?php
/**
 * Header bar: menu button (phones/tablets), logo, search, account / wishlist / cart.
 * Phones: two rows (menu · logo · actions, then the full-width search); the top row tucks away
 * when scrolling down (app.js), the search row stays. Badges are filled by app.js from the
 * gtm_cart_count cookie and the localStorage wishlist — the HTML never carries a count.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_kind      = gadgetim_page_kind();
$gadgetim_has_wc    = function_exists( 'wc_get_cart_url' );
$gadgetim_cart_url  = $gadgetim_has_wc ? wc_get_cart_url() : '';
$gadgetim_account   = $gadgetim_has_wc ? (string) wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$gadgetim_logged_in = is_user_logged_in();
$gadgetim_on_cart   = in_array( $gadgetim_kind, [ 'cart', 'checkout' ], true );
?>
<div class="hdr__bar">
	<div class="hdr__bar-in container">
		<button class="icon-btn hdr__menu" type="button" data-open="menu-drawer" aria-controls="menu-drawer" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open the categories menu', 'gadgetim' ); ?>">
			<?php gadgetim_the_icon( 'menu' ); ?>
		</button>

		<?php echo gadgetim_logo( [ 'uid' => 'hl' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>

		<?php gadgetim_part( 'header/search' ); ?>

		<div class="hdr__actions">
			<?php gadgetim_part( 'header/lang' ); ?>
			<a class="act act--account" href="<?php echo esc_url( $gadgetim_account ); ?>"<?php echo 'account' === $gadgetim_kind ? ' aria-current="page"' : ''; ?>>
				<?php gadgetim_the_icon( 'user' ); ?><span><?php echo esc_html( gadgetim_account_label() ); ?></span>
			</a>
			<button class="act act--wish" type="button" data-open="wish-drawer" aria-controls="wish-drawer" aria-expanded="false" aria-label="<?php esc_attr_e( 'Wishlist', 'gadgetim' ); ?>" data-wish-label>
				<?php gadgetim_the_icon( 'heart' ); ?><b class="badge is-empty" data-wish-count aria-hidden="true"></b>
			</button>
			<?php if ( '' !== $gadgetim_cart_url ) : ?>
				<a class="act act--cart" href="<?php echo esc_url( $gadgetim_cart_url ); ?>"<?php echo $gadgetim_on_cart ? '' : ' data-open="cart-drawer" aria-controls="cart-drawer" aria-expanded="false"'; ?> aria-label="<?php esc_attr_e( 'Cart', 'gadgetim' ); ?>" data-cart-label data-cart-target>
					<?php gadgetim_the_icon( 'bag' ); ?><b class="badge badge--cart is-empty" data-cart-count aria-hidden="true"></b>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
