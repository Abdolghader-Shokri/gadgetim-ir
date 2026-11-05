<?php
/**
 * «پیشنهاد شگفت‌انگیز» band: aurora gradient with drifting glows and sparks, the animated gift
 * box (with a shine running down its ribbon), the campaign title with an animated flame, a flip
 * countdown ONLY while an admin-set campaign runs (real end date), otherwise honest facts that
 * count up (how many discounted products, the deepest discount), then a rail of the deepest
 * discounts (or the campaign's own products). Hidden in call-for-price mode.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_ids      = gadgetim_home_offer_ids( gadgetim_home_flash_ids() );
$gadgetim_products = gadgetim_home_products( $gadgetim_ids );
if ( count( $gadgetim_products ) < 2 ) {
	return;
}
$gadgetim_campaign = gadgetim_home_campaign();
$gadgetim_covered  = gadgetim_home_core() ? Gadgetim\Core\Catalog\HomeContent::campaign_covers( $gadgetim_ids ) : $gadgetim_campaign['active'];
$gadgetim_timer    = $gadgetim_covered ? gadgetim_countdown(
	(int) $gadgetim_campaign['end'],
	[
		'style' => 'flip',
		'label' => __( 'Amazing offers end', 'gadgetim' ),
		'class' => 'offers__cd',
	]
) : '';
$gadgetim_title    = trim( (string) $gadgetim_campaign['title'] );
$gadgetim_title    = '' !== $gadgetim_title ? $gadgetim_title : __( 'Amazing offers', 'gadgetim' );
$gadgetim_words    = preg_split( '/\s+/u', $gadgetim_title, 2 ) ?: [ $gadgetim_title ];
$gadgetim_count    = gadgetim_home_core() ? Gadgetim\Core\Catalog\HomeContent::discounted_count() : count( $gadgetim_products );
$gadgetim_best     = gadgetim_home_core() ? Gadgetim\Core\Catalog\HomeContent::max_discount() : 0;
foreach ( $gadgetim_products as $gadgetim_p ) {
	$gadgetim_best = max( $gadgetim_best, gadgetim_product_discount( $gadgetim_p ) );
}
$gadgetim_more  = add_query_arg( 'on_sale', '1', gadgetim_shop_url() );
$gadgetim_spark = '<path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/>';
// Animated flame after the title (fx.css flickers the outer and inner tongues separately).
$gadgetim_flame = '<svg class="offers__flame" viewBox="0 0 24 32" aria-hidden="true" focusable="false"><defs>'
	. '<linearGradient id="fx-fl-o" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#fbbf24"/><stop offset=".55" stop-color="#f97316"/><stop offset="1" stop-color="#f43f5e"/></linearGradient>'
	. '<linearGradient id="fx-fl-i" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#fffbeb"/><stop offset="1" stop-color="#fde047"/></linearGradient></defs>'
	. '<path class="fl-o" d="M12 1.5c1.2 5.2 6.8 8.2 8.6 13.4 2.2 6.4-1.6 15.6-8.6 15.6S1.2 23.6 3.6 17.2c1-2.7 2.8-4.1 3.6-6.6.9 1.9 1.9 3 3.3 3.6C10 9.8 10.6 5.2 12 1.5Z" fill="url(#fx-fl-o)"/>'
	. '<path class="fl-i" d="M12 14c.9 3.2 4.6 4.8 4.6 9.2a4.6 4.6 0 0 1-9.2 0c0-2.4 1.4-3.6 2-5.4.6 1.2 1.3 1.8 2 2.1-.2-1.9.1-4 .6-5.9Z" fill="url(#fx-fl-i)"/></svg>';
?>
<section class="offers" id="offers" aria-labelledby="offers-title">
	<div class="container">
		<div class="offers__band">
			<div class="offers__fx" aria-hidden="true">
				<i class="ob ob1"></i><i class="ob ob2"></i><i class="ob ob3"></i>
				<?php for ( $gadgetim_k = 1; $gadgetim_k <= 4; $gadgetim_k++ ) : ?>
					<svg class="offers__spark s<?php echo esc_attr( (string) $gadgetim_k ); ?>" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
				<?php endfor; ?>
			</div>
			<div class="offers__intro">
				<svg class="offers__art" viewBox="0 0 160 150" aria-hidden="true" focusable="false">
					<defs>
						<linearGradient id="gb-front" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#ffe4f1"/></linearGradient>
						<linearGradient id="gb-rib" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset="1" stop-color="#f59e0b"/></linearGradient>
						<linearGradient id="gb-lid" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fbcfe8"/></linearGradient>
						<clipPath id="gb-rib-clip"><path d="M72 74h16v62H72z"/></clipPath>
					</defs>
					<ellipse cx="80" cy="140" rx="48" ry="7" fill="#2e1065" opacity=".25"/>
					<g class="gift__box">
						<path d="M38 74h84v56a6 6 0 0 1-6 6H44a6 6 0 0 1-6-6V74Z" fill="url(#gb-front)"/>
						<path d="M38 74h84v10H38z" fill="#fbcfe8" opacity=".7"/>
						<path d="M72 74h16v62H72z" fill="url(#gb-rib)"/>
						<g clip-path="url(#gb-rib-clip)"><path class="gift__shine" d="M66 70l28-13v9l-28 13z" fill="#fff"/></g>
					</g>
					<g class="gift__lid">
						<path d="M32 58a5 5 0 0 1 5-5h86a5 5 0 0 1 5 5v14H32V58Z" fill="url(#gb-lid)"/>
						<path d="M71 53h18v19H71z" fill="url(#gb-rib)"/>
						<path d="M80 53c-8-16-30-20-30-8 0 9 18 8 30 8Z" fill="url(#gb-rib)"/>
						<path d="M80 53c8-16 30-20 30-8 0 9-18 8-30 8Z" fill="url(#gb-rib)"/>
						<circle cx="80" cy="53" r="6" fill="#f59e0b"/>
					</g>
					<g class="gift__pop">
						<circle cx="34" cy="30" r="4" fill="#fde68a"/>
						<circle cx="128" cy="24" r="3" fill="#fff"/>
						<path d="M118 40l4 9-9-3z" fill="#fbcfe8"/>
						<path d="M44 14c1.4 6 3.6 8 9 9-5.4 1-7.6 3-9 9-1.4-6-3.6-8-9-9 5.4-1 7.6-3 9-9Z" fill="#fff"/>
						<path d="M140 52c1 4 2.4 5.4 6 6-3.6.6-5 2-6 6-1-4-2.4-5.4-6-6 3.6-.6 5-2 6-6Z" fill="#fde68a"/>
					</g>
				</svg>
				<h2 class="offers__title" id="offers-title"><span><?php echo esc_html( $gadgetim_words[0] ); ?><?php echo isset( $gadgetim_words[1] ) ? '' : $gadgetim_flame; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php if ( isset( $gadgetim_words[1] ) ) : ?> <span><?php echo esc_html( $gadgetim_words[1] ); ?><?php echo $gadgetim_flame; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php endif; ?></h2>
				<?php if ( '' !== trim( (string) $gadgetim_campaign['sub'] ) ) : ?>
					<p class="offers__sub"><?php echo esc_html( (string) $gadgetim_campaign['sub'] ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $gadgetim_timer ) : ?>
					<div class="offers__timer"><?php echo $gadgetim_timer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in gadgetim_countdown(). ?></div>
				<?php else : ?>
					<ul class="offers__facts">
						<li><b data-count-to="<?php echo esc_attr( (string) $gadgetim_count ); ?>"><?php echo esc_html( gadgetim_number( $gadgetim_count ) ); ?></b><span><?php echo esc_html( _n( 'discounted product', 'discounted products', $gadgetim_count, 'gadgetim' ) ); ?></span></li>
						<?php if ( $gadgetim_best >= gadgetim_sale_thresholds()['sticker'] ) : ?>
							<?php
							// The digits count up (motion.js [data-count-to]); the locale's percent sign stays as printed.
							$gadgetim_num = esc_html( gadgetim_number( $gadgetim_best ) );
							$gadgetim_pct = str_replace( $gadgetim_num, '<span data-count-to="' . esc_attr( (string) $gadgetim_best ) . '">' . $gadgetim_num . '</span>', esc_html( gadgetim_percent( $gadgetim_best ) ) );
							?>
							<li><b><?php echo $gadgetim_pct; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></b><span><?php esc_html_e( 'deepest discount', 'gadgetim' ); ?></span></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
				<div class="offers__ctrl">
					<a class="btn btn--white btn--sm offers__more" href="<?php echo esc_url( $gadgetim_more ); ?>"><span><?php esc_html_e( 'View all', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
					<?php echo gadgetim_rail_nav( 'offers-rail', 'glass' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				</div>
			</div>
			<div class="offers__rail-wrap">
				<ul class="rail offers__rail" id="offers-rail" aria-label="<?php echo esc_attr( $gadgetim_title ); ?>">
					<?php
					foreach ( $gadgetim_products as $gadgetim_i => $gadgetim_p ) {
						gadgetim_the_product_card( $gadgetim_p, 'rail', [ 'index' => $gadgetim_i ] );
					}
					?>
				</ul>
			</div>
		</div>
	</div>
</section>
