<?php
/**
 * Hero: the banner slider (carousel.js: progress dots, pause, swipe, keyboard, pointer parallax;
 * home.js adds the scroll depth) beside the instant offer («پیشنهاد لحظه‌ای»). The first slide
 * is in the HTML as the active one, its packshot is the LCP image (preloaded in <head>).
 * .slider__fx holds the drifting light orbs (i) and rising sparkles (b) over every slide (fx.css).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_slides = gadgetim_home_slides();
$gadgetim_count  = count( $gadgetim_slides );
if ( ! $gadgetim_slides ) {
	return;
}
$gadgetim_flash = gadgetim_home_flash_ids();
$gadgetim_aside = $gadgetim_flash || ( gadgetim_contact_mode() && '' !== gadgetim_whatsapp_url() );
?>
<section class="hero container<?php echo $gadgetim_aside ? '' : ' hero--solo'; ?>" aria-label="<?php esc_attr_e( 'Featured offers', 'gadgetim' ); ?>">
	<div class="slider" id="hero-slider" data-hero<?php echo $gadgetim_count > 1 ? ' data-carousel data-autoplay="6500" data-pointer-parallax' : ''; ?> role="region" aria-roledescription="<?php echo esc_attr_x( 'carousel', 'aria-roledescription', 'gadgetim' ); ?>" aria-label="<?php esc_attr_e( 'Featured offers', 'gadgetim' ); ?>">
		<div class="slider__track">
			<?php
			foreach ( $gadgetim_slides as $gadgetim_i => $gadgetim_slide ) {
				gadgetim_part(
					'home/slide',
					[
						'slide' => $gadgetim_slide,
						'index' => $gadgetim_i,
						'total' => $gadgetim_count,
					]
				);
			}
			?>
		</div>
		<span class="slider__fx" aria-hidden="true"><i></i><i></i><i></i><i></i><b></b><b></b><b></b><b></b><b></b><b></b><b></b><b></b><b></b><b></b></span>
		<?php if ( $gadgetim_count > 1 ) : ?>
			<div class="slider__ui">
				<div class="dots slider__dots" data-carousel-dots aria-label="<?php esc_attr_e( 'Choose a slide', 'gadgetim' ); ?>"></div>
				<div class="slider__btns">
					<button class="glass-btn" type="button" data-carousel-pause aria-pressed="false" aria-label="<?php esc_attr_e( 'Pause autoplay', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'pause', 'ic-pause' ); ?><?php gadgetim_the_icon( 'play', 'ic-play' ); ?></button>
					<button class="glass-btn" type="button" data-carousel-prev aria-label="<?php esc_attr_e( 'Previous slide', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chev-r', 'ic--dir' ); ?></button>
					<button class="glass-btn" type="button" data-carousel-next aria-label="<?php esc_attr_e( 'Next slide', 'gadgetim' ); ?>"><?php gadgetim_the_icon( 'chev-l', 'ic--dir' ); ?></button>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
	if ( $gadgetim_aside ) {
		gadgetim_part( 'home/flash', [ 'ids' => $gadgetim_flash ] );
	}
	?>
</section>
