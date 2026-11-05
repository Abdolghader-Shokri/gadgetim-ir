<?php
/**
 * Page hero: a gradient band with drifting light, slow rings and twinkling sparks behind the page
 * title (the page's H1). Decoration is pre-blurred radial gradients, transforms and opacity only;
 * nothing in it starts hidden (it is the first viewport and the title is the LCP).
 *
 * Args:
 *   title    (string, required) plain text, Latin runs isolated with <bdi>
 *   kicker   (string) small line above the title
 *   lead     (string) plain text under the title
 *   icon     (string) sprite icon or gadgetim_pages_icon() name for the kicker and the default art
 *   tone     violet | cool | warm | green | gold | pink | night
 *   chips    [ [ 'icon' => …, 'text' => … ], … ] meta chips (plain text)
 *   actions  [ gadgetim_button() args + 'label', … ]
 *   art      (string) escaped HTML for the visual at the inline end; default: a floating icon stack
 *   after    (string) escaped HTML printed under the actions (e.g. a search form)
 *   size     md | lg
 *   id       heading id
 *   class    extra classes
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_h = wp_parse_args(
	$args ?? [],
	[
		'title'   => '',
		'kicker'  => '',
		'lead'    => '',
		'icon'    => 'sparkle',
		'tone'    => 'violet',
		'chips'   => [],
		'actions' => [],
		'art'     => '',
		'after'   => '',
		'size'    => 'md',
		'id'      => 'page-title',
		'class'   => '',
	]
);
$gadgetim_tones = [ 'violet', 'cool', 'warm', 'green', 'gold', 'pink', 'night' ];
$gadgetim_tone  = in_array( $gadgetim_h['tone'], $gadgetim_tones, true ) ? (string) $gadgetim_h['tone'] : 'violet';
$gadgetim_class = trim( 'phero phero--' . $gadgetim_tone . ( 'lg' === $gadgetim_h['size'] ? ' phero--lg' : '' ) . ( false !== $gadgetim_h['art'] ? ' phero--art' : '' ) . ' ' . (string) $gadgetim_h['class'] );
$gadgetim_spark = '<path d="M12 1c1 7 4 10 11 11-7 1-10 4-11 11-1-7-4-10-11-11 7-1 10-4 11-11Z"/>';
?>
<header class="<?php echo esc_attr( $gadgetim_class ); ?>">
	<div class="phero__fx" aria-hidden="true">
		<i class="phero__orb o1"></i><i class="phero__orb o2"></i><i class="phero__orb o3"></i>
		<svg class="phero__rings" viewBox="0 0 400 400" focusable="false"><circle cx="200" cy="200" r="70"/><circle cx="200" cy="200" r="118"/><circle cx="200" cy="200" r="166"/><circle class="dash" cx="200" cy="200" r="196"/></svg>
		<svg class="phero__spark s1" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="phero__spark s2" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="phero__spark s3" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
		<svg class="phero__spark s4" viewBox="0 0 24 24" focusable="false"><?php echo $gadgetim_spark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></svg>
	</div>
	<div class="phero__in">
		<div class="phero__text">
			<?php if ( '' !== (string) $gadgetim_h['kicker'] ) : ?>
				<p class="phero__kicker"><span class="phero__kic"><?php echo gadgetim_pages_icon( (string) $gadgetim_h['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><?php echo gadgetim_bidi( (string) $gadgetim_h['kicker'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></span></p>
			<?php endif; ?>
			<h1 class="phero__t" id="<?php echo esc_attr( (string) $gadgetim_h['id'] ); ?>"><?php echo gadgetim_bidi( (string) $gadgetim_h['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></h1>
			<?php if ( '' !== (string) $gadgetim_h['lead'] ) : ?>
				<p class="phero__lead"><?php echo gadgetim_bidi( (string) $gadgetim_h['lead'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_bidi(). ?></p>
			<?php endif; ?>
			<?php if ( $gadgetim_h['chips'] ) : ?>
				<ul class="phero__chips">
					<?php foreach ( (array) $gadgetim_h['chips'] as $gadgetim_chip ) : ?>
						<li><?php echo gadgetim_pages_icon( (string) ( $gadgetim_chip['icon'] ?? 'sparkle' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( (string) ( $gadgetim_chip['text'] ?? '' ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $gadgetim_h['actions'] ) : ?>
				<div class="phero__actions">
					<?php foreach ( (array) $gadgetim_h['actions'] as $gadgetim_action ) : ?>
						<?php echo gadgetim_button( (string) ( $gadgetim_action['label'] ?? '' ), (array) $gadgetim_action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php echo (string) $gadgetim_h['after']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?>
		</div>
		<?php if ( false !== $gadgetim_h['art'] ) : ?>
			<div class="phero__art" aria-hidden="true">
				<?php if ( '' !== (string) $gadgetim_h['art'] ) : ?>
					<?php echo (string) $gadgetim_h['art']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller. ?>
				<?php else : ?>
					<span class="phero__stack">
						<span class="phero__card phero__card--a"></span>
						<span class="phero__card phero__card--b"></span>
						<span class="phero__card phero__card--c"><?php echo gadgetim_pages_icon( (string) $gadgetim_h['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
						<i class="phero__bub b1"></i><i class="phero__bub b2"></i><i class="phero__bub b3"></i>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</header>
