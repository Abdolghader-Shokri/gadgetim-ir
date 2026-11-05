<?php
/**
 * "Need help?" card for asides: WhatsApp and phone buttons (contact.* settings), then links to
 * the other help and policy pages (the current page left out).
 *
 * Args: title, text, links (bool, default true), id (heading id), class.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_a     = wp_parse_args(
	$args ?? [],
	[
		'title' => __( 'Need help?', 'gadgetim' ),
		'text'  => __( 'Ask us about products, orders or authenticity. We are happy to help.', 'gadgetim' ),
		'links' => true,
		'id'    => 'phelp-t',
		'class' => '',
	]
);
$gadgetim_wa    = gadgetim_whatsapp_url( __( 'Hello! I have a question for Gadgetim.', 'gadgetim' ) );
$gadgetim_tel   = gadgetim_tel_href();
$gadgetim_links = $gadgetim_a['links'] ? gadgetim_pages_help_links() : [];
?>
<section class="<?php echo esc_attr( trim( 'phelp ' . (string) $gadgetim_a['class'] ) ); ?>" aria-labelledby="<?php echo esc_attr( (string) $gadgetim_a['id'] ); ?>">
	<div class="phelp__head">
		<span class="phelp__ic" aria-hidden="true"><?php gadgetim_the_icon( 'headphones' ); ?></span>
		<div>
			<h2 class="phelp__t" id="<?php echo esc_attr( (string) $gadgetim_a['id'] ); ?>"><?php echo esc_html( (string) $gadgetim_a['title'] ); ?></h2>
			<p class="phelp__p"><?php echo esc_html( (string) $gadgetim_a['text'] ); ?></p>
		</div>
	</div>
	<?php if ( '' !== $gadgetim_wa || '' !== $gadgetim_tel ) : ?>
		<div class="phelp__acts">
			<?php if ( '' !== $gadgetim_wa ) : ?>
				<a class="btn btn--success btn--sm btn--block" href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><?php gadgetim_the_icon( 'chat' ); ?><span><?php esc_html_e( 'Chat on WhatsApp', 'gadgetim' ); ?></span></a>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_tel ) : ?>
				<a class="btn btn--outline btn--sm btn--block" href="<?php echo esc_url( $gadgetim_tel ); ?>"><?php gadgetim_the_icon( 'phone' ); ?><span><?php echo gadgetim_phone_display(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></span></a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php if ( $gadgetim_links ) : ?>
		<ul class="phelp__links">
			<?php foreach ( $gadgetim_links as $gadgetim_link ) : ?>
				<li><a href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>"><span class="phelp__lic"><?php gadgetim_the_icon( (string) $gadgetim_link['icon'] ); ?></span><span><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></span><?php gadgetim_the_icon( 'chev-l', 'ic--dir phelp__chev' ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
