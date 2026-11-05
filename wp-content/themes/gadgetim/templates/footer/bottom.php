<?php
/**
 * Footer bottom row: copyright with the Jalali year (gadgetim_date), language links
 * (I18n\Hreflang::switcher) and the authenticity line.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_langs = gadgetim_language_links();
/* translators: 1: year (Jalali in Persian), 2: shop name */
$gadgetim_copy = sprintf( __( '© %1$s %2$s — all rights reserved.', 'gadgetim' ), gadgetim_date( time(), 'Y' ), gadgetim_brand_name() );
?>
<div class="ftr__bottom">
	<p><?php echo esc_html( $gadgetim_copy ); ?></p>
	<?php if ( $gadgetim_langs ) : ?>
		<nav class="langs" aria-label="<?php esc_attr_e( 'Languages', 'gadgetim' ); ?>">
			<?php foreach ( $gadgetim_langs as $gadgetim_code => $gadgetim_lang_row ) : ?>
				<a href="<?php echo esc_url( $gadgetim_lang_row['url'] ); ?>" hreflang="<?php echo esc_attr( $gadgetim_lang_row['hreflang'] ); ?>" lang="<?php echo esc_attr( (string) $gadgetim_code ); ?>"<?php echo $gadgetim_lang_row['current'] ? ' aria-current="true"' : ''; ?>><?php gadgetim_the_icon( 'globe' ); ?><?php echo esc_html( $gadgetim_lang_row['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
	<p class="ftr__made"><?php gadgetim_the_icon( 'shield' ); ?><?php esc_html_e( 'Every product genuine, with an authenticity guarantee', 'gadgetim' ); ?></p>
</div>
