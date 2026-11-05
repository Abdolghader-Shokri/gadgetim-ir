<?php
/**
 * Category stories: rotating gradient rings (the first one, «شگفت‌انگیزها», jumps to the offers
 * band). A swipe rail on phones and tablets, a twelve-column row on desktop. First viewport:
 * no reveal; the rings pop in with a CSS-only entrance and draw themselves one after another
 * (.story__draw, stroke-dashoffset, fx.css) before the turning gradient takes over.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_stories = gadgetim_home_stories();
if ( count( $gadgetim_stories ) < 3 ) {
	return;
}
?>
<section class="stories" aria-label="<?php esc_attr_e( 'Popular categories', 'gadgetim' ); ?>">
	<div class="container stories__in">
		<ul class="rail stories__rail" id="stories-rail" data-stories>
			<?php foreach ( $gadgetim_stories as $gadgetim_i => $gadgetim_story ) : ?>
				<?php
				$gadgetim_attrs = [
					'class'      => 'story' . ( $gadgetim_story['hot'] ? ' story--hot' : '' ),
					'href'       => $gadgetim_story['url'],
					'data-story' => $gadgetim_story['hot'] ? null : (string) $gadgetim_story['id'],
					'aria-label' => $gadgetim_story['label'] !== $gadgetim_story['name'] ? $gadgetim_story['name'] : null,
				];
				?>
				<li style="--i:<?php echo esc_attr( (string) $gadgetim_i ); ?>">
					<a<?php echo gadgetim_attr( $gadgetim_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by gadgetim_attr(). ?>>
						<span class="story__ring"><svg class="story__draw" viewBox="0 0 100 100" aria-hidden="true" focusable="false"><circle cx="50" cy="50" r="47.6" pathLength="1"/></svg><span class="story__img">
							<?php
							if ( $gadgetim_story['hot'] ) {
								gadgetim_the_icon( 'flame' );
							} elseif ( $gadgetim_story['img'] > 0 ) {
								echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
									(int) $gadgetim_story['img'],
									'gtm-thumb',
									[
										'sizes'   => 'story',
										'loading' => $gadgetim_i < 7 ? 'eager' : 'lazy',
									]
								);
							} else {
								gadgetim_the_icon( 'grid' );
							}
							?>
						</span></span>
						<span class="story__name"><?php echo esc_html( $gadgetim_story['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
