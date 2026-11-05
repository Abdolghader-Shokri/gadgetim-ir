<?php
/**
 * «پیشنهاد لحظه‌ای»: the deepest discounts in stock, one at a time (carousel.js autoplay with a
 * progress bar; home.js keeps the top bar in step). A countdown appears only for a real deadline:
 * the running campaign's end in the head (when it covers these products) or a product's own sale
 * end date. In call-for-price mode the card becomes a WhatsApp consultation card.
 *
 * @var array $args { ids: int[] }
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_ids      = array_map( 'intval', (array) ( $args['ids'] ?? [] ) );
$gadgetim_products = gadgetim_home_products( $gadgetim_ids );

if ( ! $gadgetim_products ) {
	$gadgetim_wa = gadgetim_whatsapp_url( __( 'Hello, I would like advice on a product.', 'gadgetim' ) );
	if ( '' === $gadgetim_wa ) {
		return;
	}
	$gadgetim_tel = gadgetim_tel_href();
	?>
	<aside class="flash flash--contact" aria-labelledby="flash-title">
		<div class="flash__head">
			<h2 class="flash__title" id="flash-title"><span class="flash__bolt flash__bolt--green" aria-hidden="true"><?php gadgetim_the_icon( 'chat' ); ?></span><?php esc_html_e( 'Ask before you buy', 'gadgetim' ); ?></h2>
		</div>
		<p class="flash__note"><?php esc_html_e( 'Prices follow the exchange rate, so we confirm them for you. Message us for a quote and honest advice.', 'gadgetim' ); ?></p>
		<div class="flash__actions">
			<a class="btn btn--success btn--block" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Chat on WhatsApp', 'gadgetim' ); ?></span></a>
			<?php if ( '' !== $gadgetim_tel ) : ?>
				<a class="btn btn--outline btn--block" href="<?php echo esc_url( $gadgetim_tel ); ?>"><?php gadgetim_the_icon( 'phone' ); ?><span class="sr-only"><?php esc_html_e( 'Call us:', 'gadgetim' ); ?> </span><?php echo gadgetim_phone_display(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the helper. ?></a>
			<?php endif; ?>
		</div>
	</aside>
	<?php
	return;
}

$gadgetim_count    = count( $gadgetim_products );
$gadgetim_campaign = gadgetim_home_campaign();
$gadgetim_covered  = gadgetim_home_core() ? Gadgetim\Core\Catalog\HomeContent::campaign_covers( $gadgetim_ids ) : $gadgetim_campaign['active'];
$gadgetim_timer    = $gadgetim_covered ? gadgetim_countdown(
	(int) $gadgetim_campaign['end'],
	[
		'style' => 'chip',
		'label' => __( 'Amazing offers end', 'gadgetim' ),
		'class' => 'flash__timer',
	]
) : '';
$gadgetim_best     = 0;
foreach ( $gadgetim_products as $gadgetim_p ) {
	$gadgetim_best = max( $gadgetim_best, gadgetim_product_discount( $gadgetim_p ) );
}
?>
<aside class="flash" id="flash" data-flash<?php echo $gadgetim_count > 1 ? ' data-carousel data-autoplay="7000"' : ''; ?> aria-labelledby="flash-title">
	<div class="flash__head">
		<h2 class="flash__title" id="flash-title"><span class="flash__bolt" aria-hidden="true"><?php gadgetim_the_icon( 'bolt' ); ?></span><?php esc_html_e( 'Instant offer', 'gadgetim' ); ?></h2>
		<?php
		if ( '' !== $gadgetim_timer ) {
			echo $gadgetim_timer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in gadgetim_countdown().
		} else {
			echo gadgetim_home_deal( $gadgetim_best, 'deal flash__deal' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
		}
		?>
	</div>
	<div class="flash__bar" aria-hidden="true"><i></i></div>
	<div class="flash__stage">
		<?php foreach ( $gadgetim_products as $gadgetim_i => $gadgetim_p ) : ?>
			<?php
			$gadgetim_first  = 0 === $gadgetim_i;
			$gadgetim_saving = gadgetim_product_saving( $gadgetim_p );
			$gadgetim_low    = gadgetim_low_stock( $gadgetim_p );
			$gadgetim_end    = $gadgetim_timer ? 0 : gadgetim_sale_end( $gadgetim_p );
			$gadgetim_attrs  = [
				'class'                => 'fitem' . ( $gadgetim_first ? ' is-active' : '' ),
				'data-slide'           => true,
				'role'                 => 'group',
				'aria-roledescription' => _x( 'offer', 'aria-roledescription', 'gadgetim' ),
				/* translators: 1: offer number, 2: number of offers */
				'aria-label'           => sprintf( __( '%1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_i + 1 ), gadgetim_number( $gadgetim_count ) ),
				'aria-hidden'          => $gadgetim_first ? null : 'true',
				'inert'                => $gadgetim_first ? null : true,
			];
			?>
			<article<?php echo gadgetim_attr( $gadgetim_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_attr(). ?>>
				<div class="fitem__media">
					<?php echo gadgetim_discount_pill( $gadgetim_p, 'fitem__off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					<div class="fitem__img">
						<?php
						echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
							$gadgetim_p,
							'gtm-card',
							[
								'index'   => gadgetim_product_image_id( $gadgetim_p, 1 ) ? 1 : 0,
								'fade'    => true,
								'sizes'   => '(min-width:1100px) 300px, (min-width:768px) 240px, 128px',
								'loading' => $gadgetim_first ? 'eager' : 'lazy',
								'alt'     => gadgetim_short_title( $gadgetim_p ),
							]
						);
						?>
					</div>
				</div>
				<div class="fitem__body">
					<h3 class="fitem__name"><a href="<?php echo esc_url( (string) $gadgetim_p->get_permalink() ); ?>"><?php echo gadgetim_bidi( gadgetim_short_title( $gadgetim_p ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></a></h3>
					<?php if ( $gadgetim_saving > 0 ) : ?>
						<p class="fitem__save"><?php gadgetim_the_icon( 'sparkle' ); ?><span><?php
							/* translators: %s: amount saved with the currency, e.g. 1,240,000 Toman */
							echo wp_kses( sprintf( __( 'You save %s', 'gadgetim' ), '<b>' . esc_html( gadgetim_number( $gadgetim_saving ) ) . '</b> ' . esc_html( gadgetim_price_unit() ) ), [ 'b' => [] ] );
						?></span></p>
					<?php endif; ?>
					<?php if ( $gadgetim_end > 0 ) : ?>
						<div class="fitem__ends"><span><?php esc_html_e( 'Sale ends in', 'gadgetim' ); ?></span><?php echo gadgetim_countdown( $gadgetim_end, [ 'style' => 'chip', 'label' => __( 'Sale ends in', 'gadgetim' ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in gadgetim_countdown(). ?></div>
					<?php elseif ( $gadgetim_low > 0 ) : ?>
						<p class="fitem__low"><i class="dotty" aria-hidden="true"></i><?php
							/* translators: %s: number of items left */
							echo esc_html( sprintf( __( 'Only %s left', 'gadgetim' ), gadgetim_number( $gadgetim_low ) ) );
						?></p>
					<?php endif; ?>
					<div class="fitem__cta"><?php echo gadgetim_price_html( $gadgetim_p ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin price partial. ?><?php echo gadgetim_add_button( $gadgetim_p, [ 'wide' => true ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<?php if ( $gadgetim_count > 1 ) : ?>
		<div class="dots flash__dots" data-carousel-dots aria-label="<?php esc_attr_e( 'Choose an offer', 'gadgetim' ); ?>"></div>
	<?php endif; ?>
</aside>
