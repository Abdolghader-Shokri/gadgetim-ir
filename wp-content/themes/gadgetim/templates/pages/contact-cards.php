<?php
/**
 * Contact channel cards from the settings (WhatsApp, phone, Telegram, Instagram, e-mail,
 * address, hours): colourful icon tile, label, value, a hint, the action (the whole card is the
 * link) and a copy button for numbers and addresses (pages.js).
 *
 * Args: class, label (aria-label of the list).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_items = gadgetim_pages_contact_channels();
if ( ! $gadgetim_items ) {
	return;
}
gadgetim_pages_use_script();
?>
<ul class="<?php echo esc_attr( trim( 'ccards ccards--n' . min( 4, count( $gadgetim_items ) ) . ' ' . (string) ( $args['class'] ?? '' ) ) ); ?>" aria-label="<?php echo esc_attr( (string) ( $args['label'] ?? __( 'Ways to reach us', 'gadgetim' ) ) ); ?>">
	<?php foreach ( $gadgetim_items as $gadgetim_i => $gadgetim_item ) : ?>
		<li class="ccard ccard--<?php echo esc_attr( sanitize_html_class( (string) $gadgetim_item['key'] ) ); ?>" style="--i:<?php echo (int) $gadgetim_i; ?>">
			<span class="ccard__ic" aria-hidden="true"><?php gadgetim_the_icon( (string) $gadgetim_item['icon'] ); ?></span>
			<span class="ccard__label"><?php echo esc_html( (string) $gadgetim_item['label'] ); ?></span>
			<b class="ccard__value"><?php echo wp_kses( (string) $gadgetim_item['value'], [ 'bdi' => [ 'dir' => true, 'class' => true ] ] ); ?></b>
			<?php if ( '' !== (string) $gadgetim_item['hint'] ) : ?>
				<span class="ccard__hint"><?php echo esc_html( (string) $gadgetim_item['hint'] ); ?></span>
			<?php endif; ?>
			<span class="ccard__row">
				<?php if ( '' !== (string) $gadgetim_item['href'] ) : ?>
					<a class="ccard__go" href="<?php echo esc_url( (string) $gadgetim_item['href'], [ 'http', 'https', 'tel', 'mailto' ] ); ?>"<?php echo $gadgetim_item['external'] ? ' target="_blank" rel="noopener"' : ''; ?>><span class="sr-only"><?php echo esc_html( (string) $gadgetim_item['label'] ); ?>: </span><span><?php echo esc_html( (string) $gadgetim_item['action'] ); ?></span><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== (string) $gadgetim_item['copy'] ) : ?>
					<?php /* translators: %s: what is copied (phone, email, address) */ ?>
					<button class="ccard__copy" type="button" data-copy="<?php echo esc_attr( (string) $gadgetim_item['copy'] ); ?>" data-copied="<?php esc_attr_e( 'Copied', 'gadgetim' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Copy: %s', 'gadgetim' ), (string) $gadgetim_item['label'] ) ); ?>"><?php echo gadgetim_pages_icon( 'copy' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="ccard__copied" aria-hidden="true"><?php esc_html_e( 'Copied', 'gadgetim' ); ?></span></button>
				<?php endif; ?>
			</span>
		</li>
	<?php endforeach; ?>
</ul>
