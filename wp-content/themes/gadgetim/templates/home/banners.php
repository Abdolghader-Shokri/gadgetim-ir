<?php
/**
 * Promo banners: two or three gradient cards (brand pill, two-line title, sub-line, CTA) with two
 * tilted white tiles carrying packshots (watermark mask), slowly turning orbit rings, a light sweep
 * on hover and the real «تا x٪ تخفیف» sticker of the banner's scope. Desktop: a bento with the
 * first banner spanning two rows.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_banners = gadgetim_home_banners();
if ( ! $gadgetim_banners ) {
	return;
}
?>
<section class="banners container banners--<?php echo esc_attr( (string) count( $gadgetim_banners ) ); ?>" aria-label="<?php esc_attr_e( 'Brand and category picks', 'gadgetim' ); ?>">
	<?php foreach ( $gadgetim_banners as $gadgetim_i => $gadgetim_b ) : ?>
		<?php
		$gadgetim_cta  = '' !== $gadgetim_b['cta'] ? (string) $gadgetim_b['cta'] : __( 'Shop now', 'gadgetim' );
		$gadgetim_art  = array_values( (array) $gadgetim_b['art'] );
		if ( ! $gadgetim_art ) {
			$gadgetim_art = gadgetim_home_source_art( 'banners', (int) $gadgetim_i ); // Untranslated language: Persian packshots as decoration.
		}
		$gadgetim_tile = static function ( ?array $entry, string $class ): string {
			if ( ! $entry ) {
				return '';
			}
			$img = gadgetim_home_art(
				$entry,
				'gtm-card',
				[
					'sizes'   => 'banner',
					'loading' => 'lazy',
				]
			);
			return '' !== $img ? '<span class="bnr__tile ' . esc_attr( $class ) . '">' . $img . '</span>' : '';
		};
		?>
		<a class="bnr bnr--<?php echo esc_attr( sanitize_html_class( (string) $gadgetim_b['tone'] ) ); ?>" href="<?php echo esc_url( (string) $gadgetim_b['url'] ); ?>" data-reveal style="--i:<?php echo esc_attr( (string) $gadgetim_i ); ?>">
			<span class="bnr__fx" aria-hidden="true"><i></i><svg viewBox="0 0 200 200" focusable="false"><circle cx="100" cy="100" r="98"/><circle cx="100" cy="100" r="76" stroke-dasharray="3 7"/><circle cx="100" cy="100" r="54"/></svg></span>
			<span class="bnr__text">
				<?php if ( '' !== $gadgetim_b['label'] ) : ?>
					<span class="bnr__brand" dir="ltr"><?php echo esc_html( (string) $gadgetim_b['label'] ); ?></span>
				<?php endif; ?>
				<span class="bnr__title"><?php echo gadgetim_bidi( (string) $gadgetim_b['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?><?php if ( '' !== $gadgetim_b['highlight'] ) : ?> <span class="bnr__hl"><?php echo gadgetim_bidi( (string) $gadgetim_b['highlight'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span><?php endif; ?></span>
				<?php if ( '' !== $gadgetim_b['sub'] ) : ?>
					<span class="bnr__sub"><?php echo gadgetim_bidi( (string) $gadgetim_b['sub'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
				<?php endif; ?>
				<span class="bnr__cta"><?php echo esc_html( $gadgetim_cta ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></span>
			</span>
			<span class="bnr__art" aria-hidden="true">
				<?php echo $gadgetim_tile( $gadgetim_art[1] ?? null, 'bnr__tile--b' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?>
				<?php echo $gadgetim_tile( $gadgetim_art[0] ?? null, 'bnr__tile--a' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?>
				<?php echo gadgetim_home_sticker( (int) $gadgetim_b['max_off'], 'bnr__badge' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
			</span>
		</a>
	<?php endforeach; ?>
</section>
