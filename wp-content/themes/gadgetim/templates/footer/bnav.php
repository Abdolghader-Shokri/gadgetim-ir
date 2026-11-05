<?php
/**
 * Mobile bottom navigation (< 768 px): home, categories (menu drawer), search (focuses the header
 * search), cart (cart drawer, badge from the cookie), account. The gradient indicator slides to
 * the active item (--idx set here for the page, updated by app.js when a drawer opens).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_kind     = gadgetim_page_kind();
$gadgetim_active   = [
	'home'     => 0,
	'archive'  => 1,
	'search'   => 2,
	'cart'     => 3,
	'checkout' => 3,
	'account'  => 4,
][ $gadgetim_kind ] ?? -1;
$gadgetim_has_wc   = function_exists( 'wc_get_cart_url' );
$gadgetim_cart_url = $gadgetim_has_wc ? wc_get_cart_url() : '';
$gadgetim_account  = $gadgetim_has_wc ? (string) wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$gadgetim_on_cart  = in_array( $gadgetim_kind, [ 'cart', 'checkout' ], true );
$gadgetim_cls      = static fn( int $i ): string => 'bnav__i' . ( $i === $gadgetim_active ? ' is-active' : '' );
?>
<nav class="bnav" id="bnav" aria-label="<?php esc_attr_e( 'Quick navigation', 'gadgetim' ); ?>" style="--idx:<?php echo esc_attr( (string) max( 0, $gadgetim_active ) ); ?>" data-idx="<?php echo esc_attr( (string) $gadgetim_active ); ?>">
	<span class="bnav__ind<?php echo $gadgetim_active < 0 ? ' is-none' : ''; ?>" aria-hidden="true"></span>
	<a class="<?php echo esc_attr( $gadgetim_cls( 0 ) ); ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" data-idx="0"<?php echo 0 === $gadgetim_active ? ' aria-current="page"' : ''; ?>>
		<span class="bnav__icw"><?php gadgetim_the_icon( 'home' ); ?></span><span><?php esc_html_e( 'Home', 'gadgetim' ); ?></span>
	</a>
	<button class="<?php echo esc_attr( $gadgetim_cls( 1 ) ); ?>" type="button" data-idx="1" data-open="menu-drawer" aria-controls="menu-drawer" aria-expanded="false">
		<span class="bnav__icw"><?php gadgetim_the_icon( 'grid' ); ?></span><span><?php esc_html_e( 'Categories', 'gadgetim' ); ?></span>
	</button>
	<button class="<?php echo esc_attr( $gadgetim_cls( 2 ) ); ?>" type="button" data-idx="2" data-act="search" aria-controls="q">
		<span class="bnav__icw"><?php gadgetim_the_icon( 'search' ); ?></span><span><?php esc_html_e( 'Search', 'gadgetim' ); ?></span>
	</button>
	<?php if ( '' !== $gadgetim_cart_url ) : ?>
		<a class="<?php echo esc_attr( $gadgetim_cls( 3 ) . ' bnav__i--cart' ); ?>" href="<?php echo esc_url( $gadgetim_cart_url ); ?>" data-idx="3"<?php echo $gadgetim_on_cart ? ' aria-current="page"' : ' data-open="cart-drawer" aria-controls="cart-drawer" aria-expanded="false"'; ?> data-cart-label data-cart-target="phone">
			<span class="bnav__icw"><?php gadgetim_the_icon( 'bag' ); ?><b class="badge badge--cart is-empty" data-cart-count aria-hidden="true"></b></span><span aria-hidden="true"><?php esc_html_e( 'Cart', 'gadgetim' ); ?></span><span class="sr-only" data-cart-sr><?php esc_html_e( 'Cart', 'gadgetim' ); ?></span>
		</a>
	<?php endif; ?>
	<a class="<?php echo esc_attr( $gadgetim_cls( 4 ) ); ?>" href="<?php echo esc_url( $gadgetim_account ); ?>" data-idx="4"<?php echo 4 === $gadgetim_active ? ' aria-current="page"' : ''; ?>>
		<span class="bnav__icw"><?php gadgetim_the_icon( 'user' ); ?></span><span><?php esc_html_e( 'Account', 'gadgetim' ); ?></span>
	</a>
</nav>
