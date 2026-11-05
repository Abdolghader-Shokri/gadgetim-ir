<?php
/**
 * Archive hero: an aurora panel (pre-blurred blobs, dot grid, outlined background word, sparks)
 * with the eyebrow (kind · count), the title (gradient last word, the search words or the brand),
 * a short line, highlights, the brand logo or monogram, two floating packshots behind the
 * watermark mask with the biggest real discount as a sticker, and the category chips rail.
 * Nothing here starts hidden: it is the first viewport.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_info  = gadgetim_archive_info();
$gadgetim_kind  = (string) $gadgetim_info['kind'];
$gadgetim_chips = (array) $gadgetim_info['chips']['items'];
$gadgetim_count = (int) $gadgetim_info['count'];
$gadgetim_art   = (array) $gadgetim_info['art'];
$gadgetim_spark = '<path d="M12 2.5c.9 5 2.6 6.7 7.6 7.6-5 .9-6.7 2.6-7.6 7.6-.9-5-2.6-6.7-7.6-7.6 5-.9 6.7-2.6 7.6-7.6Z"/>';
?>
<section class="ahero ahero--<?php echo esc_attr( $gadgetim_kind ); ?><?php echo $gadgetim_art ? ' has-art' : ''; ?>" data-tone="<?php echo esc_attr( (string) $gadgetim_info['tone'] ); ?>" aria-labelledby="ahero-title">
	<div class="ahero__bg" aria-hidden="true">
		<i class="ahero__blob ahero__blob--1"></i><i class="ahero__blob ahero__blob--2"></i><i class="ahero__blob ahero__blob--3"></i>
		<span class="ahero__dots"></span>
		<span class="ahero__word"><span dir="ltr"><?php echo esc_html( (string) $gadgetim_info['word'] ); ?></span></span>
		<svg class="ahero__spark ahero__spark--1" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="ahero__spark ahero__spark--2" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="ahero__spark ahero__spark--3" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
	</div>

	<div class="ahero__in">
		<div class="ahero__text">
			<?php if ( 'brand' === $gadgetim_kind ) : ?>
				<span class="ahero__mark" aria-hidden="true">
					<?php if ( '' !== (string) $gadgetim_info['logo'] ) : ?>
						<?php echo $gadgetim_info['logo']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
					<?php else : ?>
						<b dir="ltr"><?php echo esc_html( gadgetim_archive_initials( (string) $gadgetim_info['name'] ) ); ?></b>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<p class="ahero__eyebrow">
				<i aria-hidden="true"></i>
				<span><?php echo esc_html( (string) $gadgetim_info['eyebrow'] ); ?></span>
				<span class="ahero__count"><?php echo esc_html( gadgetim_count_label( $gadgetim_count ) ); ?></span>
			</p>
			<h1 class="ahero__title" id="ahero-title">
				<?php if ( '' !== trim( (string) $gadgetim_info['lead'] ) ) : ?>
					<span class="ahero__lead"><?php echo gadgetim_bidi( trim( (string) $gadgetim_info['lead'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span>
				<?php endif; ?>
				<span class="ahero__grad"><?php echo ! empty( $gadgetim_info['isolate'] ) ? '<bdi>' . esc_html( (string) $gadgetim_info['grad'] ) . '</bdi>' : gadgetim_bidi( (string) $gadgetim_info['grad'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span><?php echo esc_html( (string) $gadgetim_info['after'] ); ?>
			</h1>
			<?php if ( '' !== (string) $gadgetim_info['sub'] ) : ?>
				<p class="ahero__sub"><?php echo gadgetim_bidi( (string) $gadgetim_info['sub'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></p>
			<?php endif; ?>
			<ul class="ahero__hl">
				<li><?php gadgetim_the_icon( 'shield' ); ?><span><?php esc_html_e( 'Authenticity guarantee', 'gadgetim' ); ?></span></li>
				<?php if ( (int) $gadgetim_info['from'] > 0 ) : ?>
					<li class="ahero__hl-price"><?php gadgetim_the_icon( 'tag' ); ?><span>
						<?php
						/* translators: %s: lowest price with the currency unit */
						echo esc_html( sprintf( __( 'From %s', 'gadgetim' ), gadgetim_number( (int) $gadgetim_info['from'] ) . ' ' . gadgetim_price_unit() ) );
						?>
					</span></li>
				<?php endif; ?>
				<li class="ahero__hl-more"><?php gadgetim_the_icon( 'truck' ); ?><span><?php esc_html_e( 'Fast delivery across Iran', 'gadgetim' ); ?></span></li>
			</ul>
		</div>

		<?php if ( $gadgetim_art ) : ?>
			<div class="ahero__art" aria-hidden="true">
				<span class="ahero__halo"></span>
				<?php foreach ( array_slice( $gadgetim_art, 0, 2 ) as $gadgetim_i => $gadgetim_product ) : ?>
					<span class="ahero__card ahero__card--<?php echo esc_attr( (string) ( $gadgetim_i + 1 ) ); ?>">
						<?php
						echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
							gadgetim_decor_pick( $gadgetim_product, [ 1, 0, 2 ] ),
							'gtm-card',
							[
								'crop'          => 'auto',
								'sizes'   => 0 === $gadgetim_i ? '(min-width:1100px) 230px, (min-width:768px) 180px, 118px' : '(min-width:1100px) 150px, (min-width:768px) 120px, 78px',
								'loading'       => 'eager',
								'fetchpriority' => 'auto',
								'class'         => 'ahero__img',
							]
						);
						?>
					</span>
				<?php endforeach; ?>
				<?php if ( (int) $gadgetim_info['max_off'] > 0 ) : ?>
					<?php echo gadgetim_sticker( gadgetim_percent( (int) $gadgetim_info['max_off'] ), __( 'up to', 'gadgetim' ), __( 'off', 'gadgetim' ), 'ahero__sticker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( $gadgetim_chips ) : ?>
		<nav class="achips" aria-label="<?php echo esc_attr( (string) $gadgetim_info['chips']['label'] ); ?>">
			<ul class="rail achips__list" id="achips">
				<?php foreach ( $gadgetim_chips as $gadgetim_chip ) : ?>
					<li>
						<a class="achip<?php echo ! empty( $gadgetim_chip['current'] ) ? ' is-current' : ''; ?><?php echo ! empty( $gadgetim_chip['all'] ) ? ' achip--all' : ''; ?>" href="<?php echo esc_url( (string) $gadgetim_chip['url'] ); ?>"<?php echo ! empty( $gadgetim_chip['current'] ) ? ' aria-current="page"' : ''; ?>>
							<span class="achip__img" aria-hidden="true">
								<?php
								if ( (int) $gadgetim_chip['img'] > 0 && empty( $gadgetim_chip['all'] ) ) {
									echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
										(int) $gadgetim_chip['img'],
										'gtm-thumb',
										[
											'sizes'   => '40px',
											'loading' => 'lazy',
											'crop'    => 'auto',
										]
									);
								} else {
									gadgetim_the_icon( ! empty( $gadgetim_chip['all'] ) ? 'layers' : 'grid' );
								}
								?>
							</span>
							<span class="achip__t"><?php echo gadgetim_bidi( (string) $gadgetim_chip['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span>
							<span class="achip__n" aria-hidden="true"><?php echo esc_html( gadgetim_number( (int) $gadgetim_chip['count'] ) ); ?></span>
							<span class="sr-only">, <?php echo esc_html( gadgetim_count_label( (int) $gadgetim_chip['count'] ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</section>
