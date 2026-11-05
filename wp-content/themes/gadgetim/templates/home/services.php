<?php
/**
 * Services strip: five promises with gradient icon tiles whose strokes draw in once the strip is
 * seen (home.js adds .is-drawn; CSS fallback after 3 s) and a small hover animation per icon.
 * Phones: two columns, the fifth item spans both. Not revealed: it can sit in the first viewport.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_services = gadgetim_home_services();
if ( ! $gadgetim_services ) {
	return;
}
$gadgetim_wa = gadgetim_whatsapp_url();
?>
<section class="services container" aria-label="<?php esc_attr_e( 'Why shop at Gadgetim', 'gadgetim' ); ?>">
	<ul class="services__list" data-services>
		<?php foreach ( $gadgetim_services as $gadgetim_i => $gadgetim_item ) : ?>
			<?php
			$gadgetim_url = (string) $gadgetim_item['url'];
			if ( '' === $gadgetim_url && 'chat' === $gadgetim_item['icon'] ) {
				$gadgetim_url = $gadgetim_wa;
			}
			?>
			<li class="svc svc--<?php echo esc_attr( sanitize_html_class( (string) $gadgetim_item['tone'] ) ); ?>" style="--i:<?php echo esc_attr( (string) $gadgetim_i ); ?>">
				<span class="svc__ic svc__ic--<?php echo esc_attr( sanitize_html_class( (string) $gadgetim_item['icon'] ) ); ?>"><?php echo gadgetim_home_service_icon( (string) $gadgetim_item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="svc__t">
					<?php if ( '' !== $gadgetim_url ) : ?>
						<b><a class="svc__link" href="<?php echo esc_url( $gadgetim_url ); ?>"<?php echo str_starts_with( $gadgetim_url, 'https://wa.me/' ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo gadgetim_bidi( (string) $gadgetim_item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></a></b>
					<?php else : ?>
						<b><?php echo gadgetim_bidi( (string) $gadgetim_item['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></b>
					<?php endif; ?>
					<?php if ( '' !== $gadgetim_item['text'] ) : ?>
						<span><?php echo gadgetim_bidi( (string) $gadgetim_item['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
					<?php endif; ?>
				</span>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
