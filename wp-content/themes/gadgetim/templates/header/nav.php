<?php
/**
 * Desktop nav row (≥ 1000 px): «دسته‌بندی کالاها» mega-menu button, main links (primary menu or
 * built-in shop links), authenticity note and WhatsApp support. Plain anchors, no ARIA menu roles.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_links = gadgetim_nav_links();
$gadgetim_wa    = gadgetim_whatsapp_url();
$gadgetim_here  = gadgetim_current_url();
?>
<nav class="hdr__nav" aria-label="<?php esc_attr_e( 'Main menu', 'gadgetim' ); ?>">
	<div class="hdr__nav-in container">
		<div class="mega-wrap" data-mega>
			<button class="mega-btn" type="button" aria-expanded="false" aria-controls="mega" data-mega-btn>
				<?php gadgetim_the_icon( 'grid' ); ?><span><?php esc_html_e( 'Product categories', 'gadgetim' ); ?></span><?php gadgetim_the_icon( 'chev-d', 'mega-btn__chev' ); ?>
			</button>
			<?php gadgetim_part( 'header/mega' ); ?>
		</div>
		<ul class="nav-links">
			<?php foreach ( $gadgetim_links as $gadgetim_link ) : ?>
				<?php $gadgetim_current = ! empty( $gadgetim_link['current'] ) || ( '' !== $gadgetim_here && untrailingslashit( (string) $gadgetim_link['url'] ) === untrailingslashit( $gadgetim_here ) && ! str_contains( (string) $gadgetim_link['url'], '?' ) ); ?>
				<li><a<?php echo ! empty( $gadgetim_link['hot'] ) ? ' class="nav-hot"' : ''; ?> href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>"<?php echo $gadgetim_current ? ' aria-current="page"' : ''; ?>><?php echo ! empty( $gadgetim_link['icon'] ) ? gadgetim_icon( (string) $gadgetim_link['icon'] ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
		<div class="nav-extra">
			<span><?php gadgetim_the_icon( 'shield' ); ?><?php esc_html_e( 'Authenticity guarantee', 'gadgetim' ); ?></span>
			<?php if ( '' !== $gadgetim_wa ) : ?>
				<a href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><?php gadgetim_the_icon( 'chat' ); ?><?php esc_html_e( 'WhatsApp support', 'gadgetim' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</nav>
