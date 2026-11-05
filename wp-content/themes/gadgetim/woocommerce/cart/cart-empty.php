<?php
/**
 * Empty cart page
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-empty.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * Gadgetim (Aurora): an illustrated empty state (WooCommerce's own message through the
 * wc_empty_cart_message filter; the default callback is unhooked in inc/commerce.php so it is not
 * printed twice), shop and wishlist actions, popular categories as story circles and a rail of best
 * sellers. The .wc-empty-cart-message wrapper stays: cart.js looks for it after removing the last item.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked wc_empty_cart_message - 10
 */
do_action( 'woocommerce_cart_is_empty' );

$gadgetim_shop = wc_get_page_id( 'shop' ) > 0 ? (string) apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) : '';
$gadgetim_cats = function_exists( 'gadgetim_story_categories' ) ? gadgetim_story_categories( 10 ) : [];
?>
<div class="wc-empty-cart-message cempty">
	<div class="cempty__card">
		<span class="cempty__art" aria-hidden="true">
			<svg viewBox="0 0 160 140" focusable="false">
				<defs>
					<linearGradient id="cempty-g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset=".55" stop-color="#a855f7"/><stop offset="1" stop-color="#ec4899"/></linearGradient>
					<linearGradient id="cempty-f" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#f4f1ff"/></linearGradient>
				</defs>
				<ellipse class="cempty__shadow" cx="80" cy="128" rx="44" ry="6" fill="#ece7ff"/>
				<g class="cempty__bag">
					<path d="M42 50h76l-6 62a9 9 0 0 1-9 8H57a9 9 0 0 1-9-8L42 50Z" fill="url(#cempty-f)" stroke="url(#cempty-g)" stroke-width="5" stroke-linejoin="round"/>
					<path d="M60 62V44a20 20 0 0 1 40 0v18" fill="none" stroke="url(#cempty-g)" stroke-width="5" stroke-linecap="round"/>
					<circle cx="66" cy="84" r="3.6" fill="#a855f7"/><circle cx="94" cy="84" r="3.6" fill="#a855f7"/>
					<path d="M70 98c6-5 14-5 20 0" fill="none" stroke="#a855f7" stroke-width="4" stroke-linecap="round"/>
				</g>
				<path class="cempty__spark cempty__spark--1" d="M128 20c1.4 8 5 11.6 13 13-8 1.4-11.6 5-13 13-1.4-8-5-11.6-13-13 8-1.4 11.6-5 13-13Z" fill="#fbbf24"/>
				<path class="cempty__spark cempty__spark--2" d="M26 34c.9 5 3.1 7.2 8 8-4.9.9-7.1 3.1-8 8-.9-4.9-3.1-7.1-8-8 4.9-.8 7.1-3 8-8Z" fill="#f9a8d4"/>
				<circle class="cempty__spark cempty__spark--3" cx="140" cy="76" r="4" fill="#a5b4fc"/>
			</svg>
		</span>
		<div class="cempty__t" role="status"><?php echo wp_kses_post( apply_filters( 'wc_empty_cart_message', __( 'Your cart is currently empty.', 'woocommerce' ) ) ); ?></div>
		<p class="cempty__p"><?php esc_html_e( 'Original gadgets with an authenticity guarantee are waiting for you. Start with the best sellers below or browse the categories.', 'gadgetim' ); ?></p>
		<div class="cempty__actions">
			<?php if ( '' !== $gadgetim_shop ) : ?>
				<p class="return-to-shop">
					<a class="button wc-backward btn btn--glow<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" href="<?php echo esc_url( $gadgetim_shop ); ?>">
						<span>
						<?php
							/**
							 * Filter "Return To Shop" text.
							 *
							 * @since 4.6.0
							 * @param string $default_text Default text.
							 */
							echo esc_html( apply_filters( 'woocommerce_return_to_shop_text', __( 'Return to shop', 'woocommerce' ) ) );
						?>
						</span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?>
					</a>
				</p>
			<?php endif; ?>
			<button class="btn btn--outline" type="button" data-open="wish-drawer" aria-controls="wish-drawer" aria-expanded="false"><?php gadgetim_the_icon( 'heart' ); ?><span><?php esc_html_e( 'Your wishlist', 'gadgetim' ); ?></span><b class="cempty__wish badge is-empty" data-wish-count aria-hidden="true"></b></button>
		</div>
	</div>

	<?php if ( $gadgetim_cats ) : ?>
		<nav class="cempty__cats" aria-label="<?php esc_attr_e( 'Popular categories', 'gadgetim' ); ?>">
			<ul class="rail cempty__rail" id="cempty-cats">
				<?php foreach ( $gadgetim_cats as $gadgetim_cat ) : ?>
					<?php $gadgetim_img = (int) ( $gadgetim_cat['img'] ?? 0 ); ?>
					<li>
						<a class="story" href="<?php echo esc_url( (string) $gadgetim_cat['url'] ); ?>">
							<span class="story__ring"><span class="story__img">
								<?php
								if ( $gadgetim_img > 0 ) {
									echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
										$gadgetim_img,
										'gtm-thumb',
										[
											'sizes'   => 'story',
											'loading' => 'lazy',
										]
									);
								} else {
									$gadgetim_term = get_term( (int) $gadgetim_cat['id'], 'product_cat' );
									gadgetim_the_icon( $gadgetim_term instanceof WP_Term ? gadgetim_category_icon( $gadgetim_term ) : 'grid' );
								}
								?>
							</span></span>
							<span class="story__name"><?php echo esc_html( (string) $gadgetim_cat['name'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</div>
<?php
gadgetim_part(
	'commerce/suggestions',
	[
		'ids'    => gadgetim_commerce_suggestion_ids( 10 ),
		'title'  => __( 'Best sellers right now', 'gadgetim' ),
		'sub'    => __( 'What other shoppers are buying', 'gadgetim' ),
		'id'     => 'cempty-sugg',
		'reveal' => false,
	]
);
