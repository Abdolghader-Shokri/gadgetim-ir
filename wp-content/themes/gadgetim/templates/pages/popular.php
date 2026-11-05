<?php
/**
 * Ranked popular products (in stock; best sellers first, then the most viewed — the title says
 * which, honestly: "Best sellers" only once real sales exist).
 *
 * Args: limit (default 6), id, class, title, sub.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_limit = max( 1, (int) ( $args['limit'] ?? 6 ) );
$gadgetim_ids   = gadgetim_pages_popular_ids( $gadgetim_limit );
if ( ! $gadgetim_ids ) {
	return;
}
$gadgetim_sales = function_exists( 'gadgetim_has_bestsellers' ) && gadgetim_has_bestsellers();
$gadgetim_id    = (string) ( $args['id'] ?? 'ppop-t' );
?>
<section class="<?php echo esc_attr( trim( 'ppop sect ' . (string) ( $args['class'] ?? '' ) ) ); ?>" aria-labelledby="<?php echo esc_attr( $gadgetim_id ); ?>">
	<?php
	gadgetim_the_section_head(
		[
			'title'    => (string) ( $args['title'] ?? ( $gadgetim_sales ? __( 'Best sellers', 'gadgetim' ) : __( 'Popular right now', 'gadgetim' ) ) ),
			'sub'      => (string) ( $args['sub'] ?? __( 'The gadgets other shoppers are choosing', 'gadgetim' ) ),
			'id'       => $gadgetim_id,
			'icon'     => 'trend',
			'tone'     => 'warm',
			'more_url' => add_query_arg( 'orderby', 'popularity', gadgetim_shop_url() ),
		]
	);
	?>
	<ol class="ppop__list">
		<?php foreach ( $gadgetim_ids as $gadgetim_i => $gadgetim_pid ) : ?>
			<?php
			gadgetim_the_product_card(
				$gadgetim_pid,
				'rank',
				[
					'index' => $gadgetim_i,
					'tag'   => 'li',
				]
			);
			?>
		<?php endforeach; ?>
	</ol>
</section>
