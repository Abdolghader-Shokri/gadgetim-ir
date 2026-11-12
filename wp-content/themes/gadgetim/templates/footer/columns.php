<?php
/**
 * Footer columns: about + contact cards, quick links, popular categories, customer service
 * (footer menu or the imported pages), trust seals (eNamad / Samandehi from the Storefront
 * settings, placeholders until set) and social links (contact.* settings).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_phone   = gadgetim_contact( 'phone' );
$gadgetim_wa_raw  = gadgetim_contact( 'whatsapp' );
$gadgetim_wa      = gadgetim_whatsapp_url();
$gadgetim_tg      = gadgetim_telegram_url();
$gadgetim_ig      = gadgetim_instagram_url();
$gadgetim_email   = gadgetim_contact( 'email' );
$gadgetim_address = gadgetim_t( 'contact.address' );
$gadgetim_hours   = gadgetim_t( 'contact.hours' );
$gadgetim_groups  = array_slice( gadgetim_category_groups(), 0, 7 );
$gadgetim_service = gadgetim_footer_service_links();
$gadgetim_about   = gadgetim_footer_about_links();
$gadgetim_seals   = [
	'enamad'    => [
		'label' => __( 'eNamad trust seal', 'gadgetim' ),
		'slot'  => __( 'eNamad seal', 'gadgetim' ),
		'icon'  => '<path d="M24 5 9 11v11c0 9.3 6.3 17.3 15 20 8.7-2.7 15-10.7 15-20V11L24 5Z"/><path d="m17 24 5 5 9-10"/>',
	],
	'samandehi' => [
		'label' => __( 'Samandehi registration seal', 'gadgetim' ),
		'slot'  => __( 'Samandehi seal', 'gadgetim' ),
		'icon'  => '<circle cx="24" cy="22" r="13"/><path d="m24 14 2.5 5 5.5.8-4 3.9 1 5.5-5-2.6-5 2.6 1-5.5-4-3.9 5.5-.8L24 14Z"/><path d="M17 33.5 14 43l10-4 10 4-3-9.5"/>',
	],
];
?>
<div class="ftr__grid">
	<div class="ftr__about" data-reveal>
		<?php echo gadgetim_logo( [ 'uid' => 'fl', 'class' => 'logo--ftr' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		<p><?php esc_html_e( 'Gadgetim is an online shop for original, special and useful gadgets: chargers, cables and power banks, headphones and speakers, and gadgets for the home and the car. Every product is genuine and comes with an authenticity guarantee.', 'gadgetim' ); ?></p>
		<ul class="contact">
			<?php if ( '' !== $gadgetim_phone ) : ?>
				<li><a href="<?php echo esc_url( gadgetim_tel_href( $gadgetim_phone ) ); ?>"><span class="contact__ic"><?php gadgetim_the_icon( 'phone' ); ?></span><span><small><?php esc_html_e( 'Support phone', 'gadgetim' ); ?></small><b><?php echo gadgetim_phone_display( $gadgetim_phone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></b></span></a></li>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_wa ) : ?>
				<li><a href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener"><span class="contact__ic contact__ic--wa"><?php gadgetim_the_icon( 'chat' ); ?></span><span><small><?php esc_html_e( 'WhatsApp', 'gadgetim' ); ?></small><b><?php echo gadgetim_phone_display( '' !== $gadgetim_wa_raw ? $gadgetim_wa_raw : $gadgetim_phone ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></b></span></a></li>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_tg ) : ?>
				<li><a href="<?php echo esc_url( $gadgetim_tg ); ?>" target="_blank" rel="noopener"><span class="contact__ic contact__ic--tg"><?php gadgetim_the_icon( 'telegram' ); ?></span><span><small><?php esc_html_e( 'Telegram', 'gadgetim' ); ?></small><b dir="ltr"><?php echo esc_html( '@' . ltrim( (string) wp_parse_url( $gadgetim_tg, PHP_URL_PATH ), '/' ) ); ?></b></span></a></li>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_email && is_email( $gadgetim_email ) ) : ?>
				<li><a href="<?php echo esc_url( 'mailto:' . $gadgetim_email ); ?>"><span class="contact__ic contact__ic--mail"><?php gadgetim_the_icon( 'mail' ); ?></span><span><small><?php esc_html_e( 'Email', 'gadgetim' ); ?></small><b dir="ltr"><?php echo esc_html( $gadgetim_email ); ?></b></span></a></li>
			<?php endif; ?>
			<?php if ( '' !== $gadgetim_address || '' !== $gadgetim_hours ) : ?>
				<li><span class="contact__row"><span class="contact__ic contact__ic--pin"><?php gadgetim_the_icon( '' !== $gadgetim_address ? 'pin' : 'clock' ); ?></span><span><small><?php echo '' !== $gadgetim_address ? esc_html__( 'Address', 'gadgetim' ) : esc_html__( 'Opening hours', 'gadgetim' ); ?></small><b><?php echo esc_html( '' !== $gadgetim_address ? $gadgetim_address : $gadgetim_hours ); ?></b><?php if ( '' !== $gadgetim_address && '' !== $gadgetim_hours ) : ?><small><?php echo esc_html( $gadgetim_hours ); ?></small><?php endif; ?></span></span></li>
			<?php endif; ?>
		</ul>
	</div>

	<nav class="ftr__col" aria-labelledby="fc-quick" data-reveal style="--i:1">
		<h2 id="fc-quick"><?php esc_html_e( 'Quick access', 'gadgetim' ); ?></h2>
		<ul>
			<?php foreach ( $gadgetim_about as $gadgetim_link ) : ?>
				<li><a href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>"><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<?php if ( $gadgetim_groups ) : ?>
		<nav class="ftr__col" aria-labelledby="fc-cats" data-reveal style="--i:2">
			<h2 id="fc-cats"><?php esc_html_e( 'Popular categories', 'gadgetim' ); ?></h2>
			<ul>
				<?php foreach ( $gadgetim_groups as $gadgetim_group ) : ?>
					<li><a href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>"><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php if ( $gadgetim_service ) : ?>
		<nav class="ftr__col" aria-labelledby="fc-service" data-reveal style="--i:3">
			<h2 id="fc-service"><?php esc_html_e( 'Customer service', 'gadgetim' ); ?></h2>
			<ul>
				<?php foreach ( $gadgetim_service as $gadgetim_link ) : ?>
					<li><a href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>"><?php echo esc_html( (string) $gadgetim_link['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<div class="ftr__trust" data-reveal style="--i:4">
		<h2><?php esc_html_e( 'Trust seals', 'gadgetim' ); ?></h2>
		<div class="trust">
			<?php foreach ( $gadgetim_seals as $gadgetim_key => $gadgetim_seal ) : ?>
				<?php
				$gadgetim_seal_url = (string) gadgetim_setting( 'theme.' . $gadgetim_key . '_url', '' );
				$gadgetim_seal_img = (string) gadgetim_setting( 'theme.' . $gadgetim_key . '_img', '' );
				// eNamad: a static copy of the seal ships with the theme, so the badge shows even when
				// trustseal.enamad.ir cannot be reached; the link still goes to the official verification page.
				if ( 'enamad' === $gadgetim_key && '' !== $gadgetim_seal_url && '' === $gadgetim_seal_img ) {
					$gadgetim_seal_img = gadgetim_asset_url( 'assets/img/enamad.png' );
				}
				?>
				<?php if ( '' !== $gadgetim_seal_url && '' !== $gadgetim_seal_img ) : ?>
					<a class="trust__slot" href="<?php echo esc_url( $gadgetim_seal_url ); ?>" target="_blank" rel="noopener" referrerpolicy="origin"><img src="<?php echo esc_url( $gadgetim_seal_img ); ?>" alt="<?php echo esc_attr( $gadgetim_seal['label'] ); ?>" width="88" height="96" loading="lazy" decoding="async" referrerpolicy="origin"></a>
				<?php elseif ( current_user_can( 'manage_options' ) ) : ?>
					<?php // A seal the shop does not hold yet is a placeholder only the admin sees: shoppers would read it as a certification. ?>
					<div class="trust__slot"><svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><?php echo $gadgetim_seal['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg><span><?php echo esc_html( $gadgetim_seal['slot'] ); ?></span></div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php if ( '' !== $gadgetim_ig || '' !== $gadgetim_tg || '' !== $gadgetim_wa ) : ?>
			<div class="social">
				<?php if ( '' !== $gadgetim_ig ) : ?>
					<a href="<?php echo esc_url( $gadgetim_ig ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Gadgetim on Instagram', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'insta' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $gadgetim_tg ) : ?>
					<a href="<?php echo esc_url( $gadgetim_tg ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Gadgetim on Telegram', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'telegram' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $gadgetim_wa ) : ?>
					<a href="<?php echo esc_url( $gadgetim_wa ); ?>" target="_blank" rel="noopener" aria-label="<?php esc_attr_e( 'Gadgetim on WhatsApp', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chat' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
