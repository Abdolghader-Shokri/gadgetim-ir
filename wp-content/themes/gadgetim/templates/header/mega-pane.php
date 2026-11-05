<?php
/**
 * One mega-menu pane: the group's sub-categories (with images), popular brands, best deals and a
 * featured product. Pane 0 is printed with the page; the others come from the cached ?gtm_mega=1
 * fragment on first hover/focus of the menu (gadgetim_mega_fragment()), which keeps ~80 KB of
 * markup and 50+ image tags out of every page.
 *
 * @var array $args { group: array (gadgetim_category_groups() row), index: int }
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_group = (array) ( $args['group'] ?? [] );
$gadgetim_i     = (int) ( $args['index'] ?? 0 );
if ( ! $gadgetim_group ) {
	return;
}
$gadgetim_feature = (int) $gadgetim_group['feature'] > 0 ? gadgetim_product( (int) $gadgetim_group['feature'] ) : null;
$gadgetim_minis   = count( (array) $gadgetim_group['links'] ) < 6 ? (array) $gadgetim_group['minis'] : [];
?>
<div class="mega__pane<?php echo 0 === $gadgetim_i ? ' is-active' : ''; ?>" data-pane="<?php echo esc_attr( (string) $gadgetim_i ); ?>">
	<div class="mega__main">
		<div class="mega__head">
			<strong class="mega__t"><?php echo esc_html( (string) $gadgetim_group['name'] ); ?></strong>
			<a href="<?php echo esc_url( (string) $gadgetim_group['url'] ); ?>"><?php esc_html_e( 'View all', 'gadgetim' ); ?><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></a>
		</div>
		<?php if ( $gadgetim_group['links'] ) : ?>
			<ul class="mega__links">
				<?php foreach ( (array) $gadgetim_group['links'] as $gadgetim_link ) : ?>
					<li><a class="mega__link" href="<?php echo esc_url( (string) $gadgetim_link['url'] ); ?>">
						<?php
						echo (int) $gadgetim_link['img'] > 0 // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helpers.
							? gadgetim_decor_image(
								(int) $gadgetim_link['img'],
								'gtm-thumb',
								[
									'sizes'   => 'mini',
									'loading' => 'lazy',
								]
							)
							: '<span class="mega__ph">' . gadgetim_icon( 'grid' ) . '</span>';
						?>
						<span><?php echo esc_html( (string) $gadgetim_link['name'] ); ?><small><?php echo esc_html( gadgetim_count_label( (int) $gadgetim_link['count'] ) ); ?></small></span>
					</a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<?php if ( $gadgetim_group['brands'] ) : ?>
			<div class="mega__brands">
				<span class="mega__sub"><?php esc_html_e( 'Popular brands in this category', 'gadgetim' ); ?></span>
				<div>
					<?php foreach ( (array) $gadgetim_group['brands'] as $gadgetim_brand ) : ?>
						<a class="mega__brand" href="<?php echo esc_url( (string) $gadgetim_brand['url'] ); ?>"><span dir="auto"><?php echo esc_html( (string) $gadgetim_brand['name'] ); ?></span><small><?php echo esc_html( gadgetim_number( (int) $gadgetim_brand['count'] ) ); ?></small></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $gadgetim_minis ) : ?>
			<div class="mega__mini">
				<span class="mega__sub"><?php esc_html_e( 'Best deals in this category', 'gadgetim' ); ?></span>
				<ul>
					<?php
					foreach ( $gadgetim_minis as $gadgetim_mini ) {
						gadgetim_the_product_card(
							(int) $gadgetim_mini,
							'compact',
							[
								'reveal'  => false,
								'loading' => 'lazy',
							]
						);
					}
					?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $gadgetim_feature ) : ?>
		<a class="mega__feat" href="<?php echo esc_url( $gadgetim_feature->get_permalink() ); ?>">
			<span class="mega__feat-tag"><?php gadgetim_the_icon( 'flame' ); ?><?php esc_html_e( 'Featured in this category', 'gadgetim' ); ?></span>
			<span class="mega__feat-art" aria-hidden="true">
				<?php
				echo gadgetim_decor_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
					$gadgetim_feature,
					'gtm-card',
					[
						'index'   => gadgetim_product_image_id( $gadgetim_feature, 1 ) ? 1 : 0,
						'fade'    => true,
						'sizes'   => '260px',
						'loading' => 'lazy',
					]
				);
				?>
			</span>
			<b class="mega__feat-t"><?php echo gadgetim_bidi( gadgetim_short_title( $gadgetim_feature ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></b>
			<span class="price-row"><?php echo gadgetim_discount_pill( $gadgetim_feature ) . gadgetim_price_html( $gadgetim_feature, [ 'tag' => 'span' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
		</a>
	<?php endif; ?>
</div>
