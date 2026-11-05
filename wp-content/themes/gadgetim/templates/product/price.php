<?php
/**
 * Buy box price block (woocommerce_single_product_summary 10): the plugin's price partial in its
 * large form (struck regular price + discount from the thresholds), the saving, the base-currency
 * line with its explanation popover and a countdown for a REAL deadline only. In call-for-price
 * mode: the plugin's full contact slip instead.
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

if ( gadgetim_contact_mode( $gadgetim_product ) ) {
	echo '<div class="buy__slip">' . gadgetim_price_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup, escaped there.
		$gadgetim_product,
		[
			'slip'      => 'full',
			'context'   => 'single',
			'class'     => 'price--pdp',
			'base_hint' => false,
		]
	) . '</div>';
	return;
}

$gadgetim_price = gadgetim_price_html(
	$gadgetim_product,
	[
		'context'   => 'single',
		'class'     => 'price--pdp',
		'base_hint' => false,
	]
);
if ( '' === trim( wp_strip_all_tags( $gadgetim_price ) ) ) {
	return;
}
$gadgetim_off      = gadgetim_product_discount( $gadgetim_product );
$gadgetim_saving   = gadgetim_product_saving( $gadgetim_product );
$gadgetim_show_off = $gadgetim_saving > 0 && $gadgetim_off >= gadgetim_sale_thresholds()['strike'] && $gadgetim_product->is_in_stock();
$gadgetim_base     = gadgetim_pdp_base_note( $gadgetim_product );
$gadgetim_deadline = gadgetim_pdp_deadline( $gadgetim_product );
?>
<div class="buy__price<?php echo $gadgetim_product->is_in_stock() ? '' : ' buy__price--out'; ?>">
	<?php echo $gadgetim_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin price partial, escaped there. ?>

	<?php if ( $gadgetim_show_off ) : ?>
		<p class="buy__save">
			<?php gadgetim_the_icon( 'sparkle' ); ?>
			<?php
			/* translators: %s: amount saved with its unit, e.g. «۳۹۰٬۰۰۰ تومان» */
			$gadgetim_save_text = sprintf( esc_html__( 'You save %s', 'gadgetim' ), '<b><bdi>' . esc_html( gadgetim_number( $gadgetim_saving ) ) . '</bdi> ' . esc_html( gadgetim_price_unit() ) . '</b>' );
			echo wp_kses( $gadgetim_save_text, [ 'b' => [], 'bdi' => [] ] );
			?>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $gadgetim_base['hint'] ) : ?>
		<div class="buy__base">
			<?php echo $gadgetim_base['hint']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin markup (Pricing\Display::base_hint), escaped there. ?>
			<button class="buy__info" type="button" popovertarget="pdp-pop-base" aria-label="<?php esc_attr_e( 'How is this price calculated?', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'info' ); ?><span><?php esc_html_e( 'How is it priced?', 'gadgetim' ); ?></span></button>
		</div>
		<div class="pdp-pop" id="pdp-pop-base" popover aria-labelledby="pdp-pop-base-t">
			<p class="pdp-pop__t" id="pdp-pop-base-t"><?php gadgetim_the_icon( 'info' ); ?><?php esc_html_e( 'How is this price calculated?', 'gadgetim' ); ?></p>
			<p><?php echo wp_kses( $gadgetim_base['text'], [ 'bdi' => [ 'dir' => true ] ] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $gadgetim_deadline > 0 ) : ?>
		<div class="buy__deadline" data-countdown-wrap>
			<span class="buy__deadline-t"><?php gadgetim_the_icon( 'clock' ); ?><?php esc_html_e( 'This price ends in', 'gadgetim' ); ?></span>
			<?php
			echo gadgetim_countdown( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
				$gadgetim_deadline,
				[
					'style' => 'chip',
					'label' => __( 'This price ends in', 'gadgetim' ),
				]
			);
			?>
		</div>
	<?php endif; ?>
</div>
