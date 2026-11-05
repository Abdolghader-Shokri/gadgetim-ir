<?php
/**
 * Drawers (native <dialog>, drawer.js): the menu drawer (account, category accordion, quick links,
 * WhatsApp, languages), the cart drawer (filled by cart.js from the Store API, only when a cart
 * exists) and the wishlist drawer (localStorage ids → Store API products).
 *
 * Open with any element carrying data-open="<id>" (aria-controls="<id>"); close with [data-close].
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_groups   = gadgetim_category_groups();
$gadgetim_links    = gadgetim_nav_links();
$gadgetim_wa       = gadgetim_whatsapp_url();
$gadgetim_langs    = gadgetim_language_links();
$gadgetim_has_wc   = function_exists( 'wc_get_cart_url' );
$gadgetim_account  = $gadgetim_has_wc ? (string) wc_get_page_permalink( 'myaccount' ) : wp_login_url();
$gadgetim_empty    = '<svg viewBox="0 0 120 120" aria-hidden="true" focusable="false"><defs><linearGradient id="ce-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset=".6" stop-color="#a855f7"/><stop offset="1" stop-color="#ec4899"/></linearGradient></defs><circle cx="60" cy="62" r="46" fill="#f4f1ff"/><path d="M36 46h48l-4 40a6 6 0 0 1-6 5.5H46a6 6 0 0 1-6-5.5L36 46Z" fill="#fff" stroke="url(#ce-g)" stroke-width="3.5" stroke-linejoin="round"/><path d="M48 54V42a12 12 0 0 1 24 0v12" fill="none" stroke="url(#ce-g)" stroke-width="3.5" stroke-linecap="round"/><path d="M92 22c1 6 3.6 8.6 9.6 9.6-6 1-8.6 3.6-9.6 9.6-1-6-3.6-8.6-9.6-9.6 6-1 8.6-3.6 9.6-9.6Z" fill="#fbbf24"/><circle cx="24" cy="34" r="3.4" fill="#f9a8d4"/></svg>';
?>
<dialog class="drawer" id="menu-drawer" aria-labelledby="menu-drawer-title" data-drawer>
	<div class="drawer__head">
		<h2 id="menu-drawer-title"><?php gadgetim_the_icon( 'grid' ); ?><?php esc_html_e( 'Product categories', 'gadgetim' ); ?></h2>
		<button class="icon-btn" type="button" data-close aria-label="<?php esc_attr_e( 'Close the menu', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'close' ); ?></button>
	</div>
	<div class="drawer__body">
		<ul class="drawer__links drawer__links--top">
			<li><a href="<?php echo esc_url( $gadgetim_account ); ?>"><?php gadgetim_the_icon( 'user' ); ?><?php echo esc_html( gadgetim_account_label() ); ?></a></li>
		</ul>
		<?php if ( $gadgetim_groups ) : ?>
			<ul class="acc" data-accordion>
				<?php foreach ( $gadgetim_groups as $gadgetim_i => $gadgetim_group ) : ?>
					<li>
						<button class="acc__btn" type="button" aria-expanded="false" aria-controls="acc-<?php echo esc_attr( (string) $gadgetim_i ); ?>">
							<span class="acc__ic"><?php gadgetim_the_icon( (string) $gadgetim_group['icon'] ); ?></span><span class="acc__label"><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></span><?php gadgetim_the_icon( 'chev-d', 'acc__chev' ); ?>
						</button>
						<div class="acc__panel" id="acc-<?php echo esc_attr( (string) $gadgetim_i ); ?>" inert>
							<div>
								<ul class="acc__grid">
									<?php foreach ( (array) $gadgetim_group['links'] as $gadgetim_link ) : ?>
										<li><a href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>">
											<?php
											if ( (int) $gadgetim_link['img'] > 0 ) {
												echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
													(int) $gadgetim_link['img'],
													'gtm-thumb',
													[
														'sizes'   => 'micro',
														'loading' => 'lazy',
													]
												);
											}
											?>
											<?php echo esc_html( (string) $gadgetim_link['name'] ); ?>
										</a></li>
									<?php endforeach; ?>
									<?php /* translators: %s: category group name */ ?>
									<li class="acc__all-li"><a class="acc__all" href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>"><?php echo esc_html( sprintf( __( 'All of %s', 'gadgetim' ), (string) $gadgetim_group['name'] ) ); ?></a></li>
								</ul>
							</div>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $gadgetim_links ) : ?>
			<ul class="drawer__links">
				<?php foreach ( $gadgetim_links as $gadgetim_link ) : ?>
					<li><a href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>"><?php gadgetim_the_icon( gadgetim_nav_link_icon( $gadgetim_link ) ); ?><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( '' !== $gadgetim_wa ) : ?>
			<a class="drawer__wa" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener">
				<span class="contact__ic contact__ic--wa"><?php gadgetim_the_icon( 'chat' ); ?></span>
				<span><b><?php esc_html_e( 'Free advice on WhatsApp', 'gadgetim' ); ?></b><small><?php echo gadgetim_phone_display( '' !== gadgetim_contact( 'whatsapp' ) ? gadgetim_contact( 'whatsapp' ) : gadgetim_contact( 'phone' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></small></span>
			</a>
		<?php endif; ?>
		<?php if ( $gadgetim_langs ) : ?>
			<nav class="langs drawer__langs" aria-label="<?php esc_attr_e( 'Languages', 'gadgetim' ); ?>">
				<?php foreach ( $gadgetim_langs as $gadgetim_code => $gadgetim_lang_row ) : ?>
					<a href="<?php echo esc_url( $gadgetim_lang_row['url'] ); ?>" hreflang="<?php echo esc_attr( $gadgetim_lang_row['hreflang'] ); ?>" lang="<?php echo esc_attr( (string) $gadgetim_code ); ?>"<?php echo $gadgetim_lang_row['current'] ? ' aria-current="true"' : ''; ?>><?php gadgetim_the_icon( 'globe' ); ?><?php echo esc_html( $gadgetim_lang_row['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</div>
</dialog>

<?php if ( $gadgetim_has_wc && ! gadgetim_contact_mode() ) : ?>
<dialog class="drawer drawer--end" id="cart-drawer" aria-labelledby="cart-drawer-title" data-drawer data-cart-drawer>
	<div class="drawer__head">
		<h2 id="cart-drawer-title"><?php gadgetim_the_icon( 'bag' ); ?><?php esc_html_e( 'Cart', 'gadgetim' ); ?> <span class="drawer__count" data-cart-drawer-count></span></h2>
		<button class="icon-btn" type="button" data-close aria-label="<?php esc_attr_e( 'Close the cart', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'close' ); ?></button>
	</div>
	<div class="drawer__body" data-cart-body aria-live="polite">
		<div class="cart-empty"><?php echo $gadgetim_empty; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><b><?php esc_html_e( 'Your cart is empty', 'gadgetim' ); ?></b><span><?php esc_html_e( 'Add original gadgets with the + button.', 'gadgetim' ); ?></span><?php echo gadgetim_button( __( 'Browse the shop', 'gadgetim' ), [ 'href' => gadgetim_shop_url(), 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></div>
	</div>
	<div class="drawer__foot" data-cart-foot hidden>
		<div class="cart-total"><span><?php esc_html_e( 'Cart total', 'gadgetim' ); ?></span><b data-cart-total></b></div>
		<div class="cart-save" data-cart-save hidden></div>
		<?php echo gadgetim_button( __( 'Continue to checkout', 'gadgetim' ), [ 'href' => wc_get_checkout_url(), 'block' => true, 'icon' => 'arrow' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?>
		<?php echo gadgetim_button( __( 'View cart', 'gadgetim' ), [ 'href' => wc_get_cart_url(), 'block' => true, 'variant' => 'soft', 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?>
	</div>
	<template id="cart-empty-tpl"><div class="cart-empty"><?php echo $gadgetim_empty; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><b><?php esc_html_e( 'Your cart is empty', 'gadgetim' ); ?></b><span><?php esc_html_e( 'Add original gadgets with the + button.', 'gadgetim' ); ?></span><?php echo gadgetim_button( __( 'Browse the shop', 'gadgetim' ), [ 'href' => gadgetim_shop_url(), 'size' => 'sm' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></div></template>
</dialog>
<?php endif; ?>

<dialog class="drawer drawer--end" id="wish-drawer" aria-labelledby="wish-drawer-title" data-drawer data-wish-drawer>
	<div class="drawer__head">
		<h2 id="wish-drawer-title"><?php gadgetim_the_icon( 'heart' ); ?><?php esc_html_e( 'Wishlist', 'gadgetim' ); ?> <span class="drawer__count" data-wish-drawer-count></span></h2>
		<button class="icon-btn" type="button" data-close aria-label="<?php esc_attr_e( 'Close the wishlist', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'close' ); ?></button>
	</div>
	<div class="drawer__body" data-wish-body aria-live="polite">
		<?php
		echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			[
				'icon'  => 'heart',
				'title' => __( 'Your wishlist is empty', 'gadgetim' ),
				'text'    => __( 'Tap the heart on any product to keep it here. The list is saved on this device.', 'gadgetim' ),
				'tag'     => 'h3',
				'class'   => 'empty--wish',
				'actions' => array_values(
					array_filter(
						[
							gadgetim_contact_mode() ? null : [
								'label' => __( 'See amazing offers', 'gadgetim' ),
								'href'  => add_query_arg( 'orderby', 'discount', gadgetim_shop_url() ),
								'icon'  => 'flame',
								'size'  => 'sm',
							],
							[
								'label'   => __( 'Browse categories', 'gadgetim' ),
								'href'    => gadgetim_shop_url(),
								'icon'    => 'grid',
								'variant' => 'soft',
								'size'    => 'sm',
							],
						]
					)
				),
			]
		);
		?>
	</div>
</dialog>
