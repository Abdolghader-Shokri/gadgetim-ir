<?php
/**
 * Popular categories as story circles in a scroll rail (gadgetim_story_categories(); images through
 * the watermark mask, the category icon when a category has no image).
 *
 * Args: limit (default 12), id, class, title, sub, reveal (bool, default true).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_cats = function_exists( 'gadgetim_story_categories' ) ? gadgetim_story_categories( max( 4, (int) ( $args['limit'] ?? 12 ) ) ) : [];
if ( ! $gadgetim_cats ) {
	return;
}
$gadgetim_id = (string) ( $args['id'] ?? 'pcats-t' );
?>
<section class="<?php echo esc_attr( trim( 'pcats sect ' . (string) ( $args['class'] ?? '' ) ) ); ?>" aria-labelledby="<?php echo esc_attr( $gadgetim_id ); ?>">
	<?php
	gadgetim_the_section_head(
		[
			'title'    => (string) ( $args['title'] ?? __( 'Popular categories', 'gadgetim' ) ),
			'sub'      => (string) ( $args['sub'] ?? __( 'Jump straight to what you are looking for', 'gadgetim' ) ),
			'id'       => $gadgetim_id,
			'icon'     => 'grid',
			'tone'     => 'cool',
			'more_url' => gadgetim_shop_url(),
			'rail'     => $gadgetim_id . '-rail',
			'reveal'   => false !== ( $args['reveal'] ?? true ),
		]
	);
	?>
	<ul class="rail pcats__rail" id="<?php echo esc_attr( $gadgetim_id . '-rail' ); ?>">
		<?php foreach ( $gadgetim_cats as $gadgetim_cat ) : ?>
			<?php
			$gadgetim_img  = (int) ( $gadgetim_cat['img'] ?? 0 );
			$gadgetim_term = $gadgetim_img > 0 ? null : get_term( (int) $gadgetim_cat['id'], 'product_cat' );
			?>
			<li>
				<a class="story" href="<?php echo esc_url( (string) $gadgetim_cat['url'] ); ?>">
					<span class="story__ring"><span class="story__img">
						<?php if ( $gadgetim_img > 0 ) : ?>
							<?php
							echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								$gadgetim_img,
								'gtm-thumb',
								[
									'sizes'   => 'story',
									'loading' => 'lazy',
								]
							);
							?>
						<?php else : ?>
							<?php gadgetim_the_icon( $gadgetim_term instanceof WP_Term ? gadgetim_category_icon( $gadgetim_term ) : 'grid' ); ?>
						<?php endif; ?>
					</span></span>
					<span class="story__name"><?php echo esc_html( (string) $gadgetim_cat['name'] ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
