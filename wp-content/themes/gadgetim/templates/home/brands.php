<?php
/**
 * Brands: two marquee rows of typographic brand chips (logo when the brand has one) running in
 * opposite directions — paused on hover/focus and off-screen; reduced motion: a swipe row on
 * phones and a wrapped grid on desktop — followed by the shop's real statistics (count-up).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_brands = gadgetim_brands( 40 );
$gadgetim_stats  = gadgetim_home_stats();
if ( count( $gadgetim_brands ) < 4 && empty( $gadgetim_stats['products'] ) ) {
	return;
}

/**
 * One brand chip.
 */
$gadgetim_chip = static function ( WP_Term $brand, bool $copy ): string {
	$style = gadgetim_home_brand_style( $brand );
	$count = gadgetim_term_count( $brand );
	$logo  = class_exists( 'Gadgetim\Core\Catalog\Brands' ) ? (int) Gadgetim\Core\Catalog\Brands::logo_id( $brand ) : 0;
	$link  = get_term_link( $brand );
	$img   = $logo > 0 ? wp_get_attachment_image(
		$logo,
		'gtm-thumb',
		false,
		[
			'class'   => 'brand__logo',
			'alt'     => html_entity_decode( gadgetim_term_name( $brand ), ENT_QUOTES, 'UTF-8' ),
			'loading' => 'lazy',
		]
	) : '';
	$name  = '' !== $img ? $img : '<span class="brand__name" dir="ltr">' . esc_html( $style['label'] ) . '</span>';
	/* translators: %s: number of products */
	$label = sprintf( _n( '%s product', '%s products', $count, 'gadgetim' ), gadgetim_number( $count ) );
	return '<a class="brand" href="' . esc_url( is_string( $link ) ? $link : gadgetim_shop_url() ) . '" style="' . esc_attr( $style['style'] ) . '"'
		. ( $copy ? ' aria-hidden="true" tabindex="-1"' : '' ) . '>'
		. $name . '<span class="brand__count">' . esc_html( $label ) . '</span></a>';
};

$gadgetim_rows = [];
if ( count( $gadgetim_brands ) >= 4 ) {
	$gadgetim_half   = (int) ceil( count( $gadgetim_brands ) / 2 );
	$gadgetim_rows[] = array_slice( $gadgetim_brands, 0, $gadgetim_half );
	$gadgetim_rows[] = array_slice( $gadgetim_brands, $gadgetim_half );
}
?>
<section class="brands" id="brands" aria-labelledby="brands-title">
	<div class="container">
		<?php
		gadgetim_the_section_head(
			[
				'title'  => __( 'Original brands at Gadgetim', 'gadgetim' ),
				'sub'    => __( 'The trusted makers and brands behind our shelves', 'gadgetim' ),
				'id'     => 'brands-title',
				'icon'   => 'tag',
				'center' => true,
			]
		);
		?>
	</div>
	<?php foreach ( $gadgetim_rows as $gadgetim_r => $gadgetim_row ) : ?>
		<?php
		if ( ! $gadgetim_row ) {
			continue;
		}
		// Each half of the track is at least twelve chips wide so the -50% loop never shows a gap.
		$gadgetim_reps = max( 1, (int) ceil( 12 / count( $gadgetim_row ) ) );
		$gadgetim_half = '';
		for ( $gadgetim_k = 0; $gadgetim_k < $gadgetim_reps; $gadgetim_k++ ) {
			foreach ( $gadgetim_row as $gadgetim_brand ) {
				$gadgetim_half .= $gadgetim_chip( $gadgetim_brand, $gadgetim_k > 0 );
			}
		}
		$gadgetim_copy = '';
		foreach ( $gadgetim_row as $gadgetim_k => $gadgetim_brand ) {
			$gadgetim_copy .= $gadgetim_chip( $gadgetim_brand, true );
		}
		$gadgetim_copy = str_repeat( $gadgetim_copy, $gadgetim_reps );
		?>
		<div class="marquee<?php echo 1 === $gadgetim_r ? ' marquee--rev' : ''; ?>" data-marquee>
			<div class="marquee__track"><div class="marquee__half"><?php echo $gadgetim_half; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the chip builder. ?></div><div class="marquee__half" aria-hidden="true"><?php echo $gadgetim_copy; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the chip builder. ?></div></div>
		</div>
	<?php endforeach; ?>
	<?php if ( ! empty( $gadgetim_stats['products'] ) ) : ?>
		<div class="container">
			<ul class="stats">
				<?php
				$gadgetim_items = [
					[ 'box', (int) $gadgetim_stats['products'], _n( 'original product', 'original products', (int) $gadgetim_stats['products'], 'gadgetim' ) ],
					[ 'tag', (int) $gadgetim_stats['brands'], _n( 'trusted brand', 'trusted brands', (int) $gadgetim_stats['brands'], 'gadgetim' ) ],
					[ 'layers', (int) $gadgetim_stats['categories'], _n( 'category', 'categories', (int) $gadgetim_stats['categories'], 'gadgetim' ) ],
					[ 'return', (int) $gadgetim_stats['return_days'], _n( 'day to return an item', 'days to return an item', (int) $gadgetim_stats['return_days'], 'gadgetim' ) ],
				];
				$gadgetim_n     = 0;
				foreach ( $gadgetim_items as [ $gadgetim_icon, $gadgetim_value, $gadgetim_label ] ) :
					if ( $gadgetim_value <= 0 ) {
						continue;
					}
					?>
					<li class="stat" data-reveal style="--i:<?php echo esc_attr( (string) $gadgetim_n++ ); ?>">
						<span class="stat__ic" aria-hidden="true"><?php gadgetim_the_icon( $gadgetim_icon ); ?></span>
						<span class="stat__t"><b data-count-to="<?php echo esc_attr( (string) $gadgetim_value ); ?>"><?php echo esc_html( gadgetim_number( $gadgetim_value ) ); ?></b><span><?php echo esc_html( $gadgetim_label ); ?></span></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
