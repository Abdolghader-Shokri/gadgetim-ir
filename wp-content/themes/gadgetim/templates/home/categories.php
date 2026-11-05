<?php
/**
 * Shop by category: a pastel bento of category tiles with packshots (watermark mask) and live
 * product counts (count-up on reveal). Desktop: six columns, the first two tiles large (2×2) with
 * a hero packshot, a companion and a round arrow.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_tiles = gadgetim_home_tiles();
if ( count( $gadgetim_tiles ) < 4 ) {
	return;
}
?>
<section class="sect container sect--glow-sky" id="cats" aria-labelledby="cats-title">
	<?php
	gadgetim_the_section_head(
		[
			'title'      => __( 'Shop by category', 'gadgetim' ),
			'sub'        => __( 'Every gadget you need, in its own category', 'gadgetim' ),
			'id'         => 'cats-title',
			'icon'       => 'grid',
			'tone'       => 'cool',
			'more_url'   => gadgetim_shop_url(),
			'more_label' => __( 'All categories', 'gadgetim' ),
		]
	);
	?>
	<ul class="cgrid">
		<?php foreach ( $gadgetim_tiles as $gadgetim_i => $gadgetim_tile ) : ?>
			<?php
			$gadgetim_term = get_term( (int) $gadgetim_tile['id'], 'product_cat' );
			if ( ! $gadgetim_term instanceof WP_Term ) {
				continue;
			}
			$gadgetim_link  = get_term_link( $gadgetim_term );
			$gadgetim_big   = ! empty( $gadgetim_tile['big'] );
			$gadgetim_count = (int) $gadgetim_tile['count'];
			$gadgetim_art   = array_slice( (array) $gadgetim_tile['art'], 0, $gadgetim_big ? 2 : 1 );
			?>
			<li class="ctile-li<?php echo $gadgetim_big ? ' ctile-li--big' : ''; ?>" data-reveal style="--i:<?php echo esc_attr( (string) ( $gadgetim_i % 6 ) ); ?>">
				<a class="ctile ctile--p<?php echo esc_attr( (string) ( $gadgetim_i % 8 ) ); ?><?php echo $gadgetim_big ? ' ctile--big' : ''; ?>" href="<?php echo esc_url( is_string( $gadgetim_link ) ? $gadgetim_link : gadgetim_shop_url() ); ?>">
					<span class="ctile__art" aria-hidden="true">
						<?php
						foreach ( $gadgetim_art as $gadgetim_entry ) {
							echo gadgetim_home_art( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
								(array) $gadgetim_entry,
								$gadgetim_big ? 'gtm-hero' : 'gtm-card',
								[
									'sizes'   => $gadgetim_big ? '(min-width:1100px) 300px, 104px' : 'tile',
									'loading' => 'lazy',
								]
							);
						}
						if ( ! $gadgetim_art && ! empty( $gadgetim_tile['img'] ) ) {
							echo gadgetim_decor_image( (int) $gadgetim_tile['img'], 'gtm-card', [ 'sizes' => 'tile', 'loading' => 'lazy' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
						}
						?>
					</span>
					<span class="ctile__name"><?php echo gadgetim_bidi( gadgetim_home_label( gadgetim_term_name( $gadgetim_term ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
					<span class="ctile__count">
						<?php
						/* translators: %s: number of products */
						echo wp_kses( sprintf( _n( '%s product', '%s products', $gadgetim_count, 'gadgetim' ), '<b data-count-to="' . esc_attr( (string) $gadgetim_count ) . '">' . esc_html( gadgetim_number( $gadgetim_count ) ) . '</b>' ), [ 'b' => [ 'data-count-to' => true ] ] );
						?>
					</span>
					<span class="ctile__go" aria-hidden="true"><?php gadgetim_the_icon( 'arrow', 'ic--dir' ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
