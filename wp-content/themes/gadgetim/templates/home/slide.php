<?php
/**
 * One hero slide: aurora blobs, a slowly turning aurora ring (fx.css), dot grid, outline word, sparks;
 * eyebrow (+ brands of the scope), two-line title (the first line split into word spans for the
 * word-by-word reveal), sub-line, CTA with the main packshot's price (phones) and the real "up to x%"
 * deal; the artwork stage (rotating halo + orbit rings) with the main and the back packshot
 * (watermark mask), a feature chip and the price chip.
 *
 * @var array $args { slide: array, index: int, total: int }
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_slide = (array) ( $args['slide'] ?? [] );
$gadgetim_index = (int) ( $args['index'] ?? 0 );
$gadgetim_total = max( 1, (int) ( $args['total'] ?? 1 ) );
$gadgetim_first = 0 === $gadgetim_index;
$gadgetim_art   = array_values( (array) ( $gadgetim_slide['art'] ?? [] ) );
// No products in this language yet: the Persian slide's packshots as pure decoration (no link, no price).
$gadgetim_decor = false;
if ( ! $gadgetim_art ) {
	$gadgetim_art   = gadgetim_home_source_art( 'slides', $gadgetim_index );
	$gadgetim_decor = (bool) $gadgetim_art;
}
$gadgetim_main  = ( $gadgetim_art && ! $gadgetim_decor ) ? gadgetim_product( (int) $gadgetim_art[0][0] ) : null;
$gadgetim_price = ( $gadgetim_main && $gadgetim_main->is_in_stock() && ! gadgetim_contact_mode( $gadgetim_main ) ) ? gadgetim_price_html( $gadgetim_main, [ 'tag' => 'span' ] ) : '';
$gadgetim_title = trim( (string) $gadgetim_slide['title'] . ' ' . (string) $gadgetim_slide['highlight'] );
$gadgetim_off   = (int) ( $gadgetim_slide['max_off'] ?? 0 );
$gadgetim_cta   = (string) ( '' !== $gadgetim_slide['cta'] ? $gadgetim_slide['cta'] : __( 'Shop now', 'gadgetim' ) );
$gadgetim_ctam  = (string) ( '' !== $gadgetim_slide['cta_short'] ? $gadgetim_slide['cta_short'] : $gadgetim_cta );
$gadgetim_sub   = (string) $gadgetim_slide['sub'];
$gadgetim_subm  = (string) ( '' !== $gadgetim_slide['sub_short'] ? $gadgetim_slide['sub_short'] : $gadgetim_sub );

$gadgetim_brands = [];
foreach ( (array) ( $gadgetim_slide['brands'] ?? [] ) as $gadgetim_brand_id ) {
	$gadgetim_brand = get_term( (int) $gadgetim_brand_id, 'product_brand' );
	if ( $gadgetim_brand instanceof WP_Term ) {
		$gadgetim_brands[] = '<bdi>' . esc_html( html_entity_decode( gadgetim_term_name( $gadgetim_brand ), ENT_QUOTES, 'UTF-8' ) ) . '</bdi>';
	}
}

$gadgetim_attrs = [
	'class'                => 'slide slide--' . sanitize_html_class( (string) $gadgetim_slide['tone'] ) . ( $gadgetim_first ? ' is-active' : '' ) . ( $gadgetim_main || $gadgetim_decor ? '' : ' slide--noart' ),
	'data-slide'           => true,
	'role'                 => 'group',
	'aria-roledescription' => _x( 'slide', 'aria-roledescription', 'gadgetim' ),
	/* translators: 1: slide number, 2: number of slides */
	'aria-label'           => sprintf( __( 'Slide %1$s of %2$s', 'gadgetim' ), gadgetim_number( $gadgetim_index + 1 ), gadgetim_number( $gadgetim_total ) ) . ': ' . $gadgetim_title,
	'aria-hidden'          => $gadgetim_first ? null : 'true',
	'inert'                => $gadgetim_first ? null : true,
];
$gadgetim_spark = '<path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/>';
// Without visible products (e.g. a language not launched yet) the stage carries the category's icon.
$gadgetim_icon = (string) $gadgetim_slide['chip_icon'];
if ( ! $gadgetim_main && ! empty( $gadgetim_slide['terms'][0] ) ) {
	$gadgetim_term = get_term( (int) $gadgetim_slide['terms'][0], 'product_cat' );
	if ( $gadgetim_term instanceof WP_Term ) {
		$gadgetim_icon = gadgetim_category_icon( $gadgetim_term );
	}
}
?>
<div<?php echo gadgetim_attr( $gadgetim_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_attr(). ?>>
	<div class="slide__bg" aria-hidden="true">
		<i class="blob b1"></i><i class="blob b2"></i><i class="blob b3"></i><i class="slide__aura"></i><span class="slide__dots"></span>
		<?php if ( '' !== $gadgetim_slide['word'] ) : ?>
			<span class="slide__word"><?php echo esc_html( (string) $gadgetim_slide['word'] ); ?></span>
		<?php endif; ?>
		<?php for ( $gadgetim_k = 1; $gadgetim_k <= 4; $gadgetim_k++ ) : ?>
			<svg class="slide__spark k<?php echo esc_attr( (string) $gadgetim_k ); ?>" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<?php endfor; ?>
	</div>
	<span class="slide__num" aria-hidden="true"><b><?php echo esc_html( gadgetim_digits( sprintf( '%02d', $gadgetim_index + 1 ) ) ); ?></b> / <?php echo esc_html( gadgetim_digits( sprintf( '%02d', $gadgetim_total ) ) ); ?></span>
	<div class="slide__text">
		<?php if ( '' !== $gadgetim_slide['eyebrow'] ) : ?>
			<span class="eyebrow" data-in style="--d:0"><i aria-hidden="true"></i><span class="eyebrow__t"><?php echo esc_html( (string) $gadgetim_slide['eyebrow'] ); ?></span><?php if ( $gadgetim_brands ) : ?><span class="eyebrow__b">&nbsp;· <?php echo implode( ' · ', $gadgetim_brands ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></span><?php endif; ?></span>
		<?php endif; ?>
		<h2 class="slide__title">
			<?php if ( '' !== $gadgetim_slide['title'] ) : ?>
				<span data-in style="--d:1"><?php echo gadgetim_fx_words( gadgetim_bidi( (string) $gadgetim_slide['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(), split into word spans. ?></span>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_slide['highlight'] ) : ?>
				<span data-in style="--d:2"><span class="gt"><?php echo gadgetim_bidi( (string) $gadgetim_slide['highlight'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span></span>
			<?php endif; ?>
		</h2>
		<?php if ( '' !== $gadgetim_sub ) : ?>
			<p class="slide__sub" data-in style="--d:3"><span class="lbl-d"><?php echo gadgetim_bidi( $gadgetim_sub ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span><span class="lbl-m"><?php echo gadgetim_bidi( $gadgetim_subm ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span></p>
		<?php endif; ?>
		<div class="slide__cta" data-in style="--d:4">
			<a class="btn btn--glow" href="<?php echo esc_url( (string) $gadgetim_slide['url'] ); ?>"><span class="lbl-d"><?php echo esc_html( $gadgetim_cta ); ?></span><span class="lbl-m"><?php echo esc_html( $gadgetim_ctam ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
			<?php if ( '' !== $gadgetim_price ) : ?>
				<span class="slide__price" aria-hidden="true"><?php echo $gadgetim_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin price partial. ?></span>
			<?php endif; ?>
			<?php echo gadgetim_home_deal( $gadgetim_off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		</div>
		<?php if ( $gadgetim_main && '' !== $gadgetim_price ) : ?>
			<p class="sr-only"><?php echo esc_html( gadgetim_short_title( $gadgetim_main ) . ': ' . gadgetim_number( (float) $gadgetim_main->get_price() ) . ' ' . gadgetim_price_unit() ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $gadgetim_off >= gadgetim_sale_thresholds()['sticker'] ) : ?>
		<span class="slide__sticker" aria-hidden="true"><?php echo gadgetim_home_sticker( $gadgetim_off ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></span>
	<?php endif; ?>
	<div class="slide__art">
		<div class="stage" aria-hidden="true"><i class="stage__halo"></i><svg viewBox="0 0 200 200" focusable="false"><circle cx="100" cy="100" r="98" stroke-dasharray="2 7"/><circle cx="100" cy="100" r="81"/><circle cx="100" cy="100" r="64" stroke-dasharray="46 12 4 12"/></svg><?php if ( ! $gadgetim_main && ! $gadgetim_decor ) : ?><span class="stage__icon"><?php gadgetim_the_icon( $gadgetim_icon ); ?></span><?php endif; ?></div>
		<?php
		foreach ( array_slice( $gadgetim_art, 0, 2 ) as $gadgetim_k => $gadgetim_entry ) :
			$gadgetim_product = gadgetim_product( (int) $gadgetim_entry[0] );
			if ( ! $gadgetim_product ) {
				continue;
			}
			$gadgetim_is_main = 0 === $gadgetim_k;
			$gadgetim_img     = gadgetim_home_art(
				$gadgetim_entry,
				$gadgetim_is_main ? 'gtm-hero' : 'gtm-card',
				[
					'sizes'         => $gadgetim_is_main ? 'hero' : '(min-width:1100px) 172px, (min-width:768px) 140px, 116px',
					'loading'       => $gadgetim_first ? 'eager' : 'lazy',
					'fetchpriority' => ( $gadgetim_first && $gadgetim_is_main ) ? 'high' : null,
					'alt'           => $gadgetim_is_main && ! $gadgetim_decor ? gadgetim_short_title( $gadgetim_product ) : '',
				]
			);
			?>
			<figure class="hp hp--<?php echo esc_attr( (string) ( $gadgetim_k + 1 ) ); ?>" style="--d:<?php echo esc_attr( (string) $gadgetim_k ); ?>;--z:<?php echo esc_attr( $gadgetim_is_main ? '16' : '34' ); ?>" aria-hidden="true">
				<div class="hp__par"><div class="hp__float">
					<?php if ( $gadgetim_is_main && ! $gadgetim_decor ) : ?>
						<a href="<?php echo esc_url( (string) $gadgetim_product->get_permalink() ); ?>" tabindex="-1"><?php echo $gadgetim_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></a>
					<?php else : ?>
						<?php echo $gadgetim_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					<?php endif; ?>
				</div></div>
				<i class="hp__shadow"></i>
			</figure>
		<?php endforeach; ?>
		<?php if ( '' !== $gadgetim_slide['chip'] ) : ?>
			<span class="chip chip--a" style="--d:0" aria-hidden="true"><span class="chip__ic"><?php gadgetim_the_icon( (string) $gadgetim_slide['chip_icon'] ); ?></span><?php echo gadgetim_bidi( (string) $gadgetim_slide['chip'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
		<?php endif; ?>
		<?php if ( '' !== $gadgetim_price ) : ?>
			<span class="chip chip--b chip--price" style="--d:1" aria-hidden="true"><?php echo $gadgetim_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin price partial. ?></span>
		<?php endif; ?>
	</div>
</div>
