<?php
/**
 * Site search (no post_type: /?s=…). Product searches (post_type=product, the header search)
 * are rendered by WooCommerce's product archive. Here: a hero with the query and a search box,
 * the matching products in a rail (with a link to all product results), then the matching
 * articles and pages (inc/pages.php limits the main query to them) with the words highlighted,
 * and a helpful empty state (popular searches, categories, popular products).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

global $wp_query;
$gadgetim_q        = trim( (string) get_search_query( false ) );
$gadgetim_products = '' !== $gadgetim_q ? gadgetim_pages_search_products( $gadgetim_q, 10 ) : [
	'ids'   => [],
	'total' => 0,
];
$gadgetim_found    = '' !== $gadgetim_q && $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
$gadgetim_all_url  = add_query_arg(
	[
		's'         => rawurlencode( $gadgetim_q ),
		'post_type' => 'product',
	],
	home_url( '/' )
);
$gadgetim_chips    = [];
if ( $gadgetim_products['total'] > 0 ) {
	$gadgetim_chips[] = [
		'icon' => 'bag',
		'text' => gadgetim_count_label( $gadgetim_products['total'] ),
	];
}
if ( $gadgetim_found > 0 ) {
	$gadgetim_chips[] = [
		'icon' => 'doc',
		/* translators: %s: number of pages and articles */
		'text' => sprintf( _n( '%s page or article', '%s pages and articles', $gadgetim_found, 'gadgetim' ), gadgetim_number( $gadgetim_found ) ),
	];
}
ob_start();
gadgetim_part(
	'pages/search-form',
	[
		'id'          => 'site-q',
		'scope'       => 'site',
		'value'       => $gadgetim_q,
		'label'       => __( 'Search the site', 'gadgetim' ),
		'placeholder' => __( 'Search products, guides and help pages…', 'gadgetim' ),
		'class'       => 'psearch--hero',
	]
);
$gadgetim_form    = (string) ob_get_clean();
$gadgetim_popular = gadgetim_pages_own_list( 'theme.search_popular' );
$gadgetim_empty   = '' === $gadgetim_q || ( ! $gadgetim_products['ids'] && ! have_posts() );
?>
<div class="container pg-shell sres-shell">
	<?php gadgetim_breadcrumbs(); ?>
	<?php
	gadgetim_part(
		'pages/hero',
		[
			/* translators: %s: search query */
			'title'  => '' !== $gadgetim_q ? gadgetim_isolate_query( sprintf( __( 'Results for “%s”', 'gadgetim' ), $gadgetim_q ), $gadgetim_q ) : __( 'Search the site', 'gadgetim' ),
			'kicker' => __( 'Search results', 'gadgetim' ),
			'icon'   => 'search',
			'tone'   => 'cool',
			'chips'  => $gadgetim_chips,
			'after'  => $gadgetim_form,
			'art'    => false,
			'class'  => 'phero--search',
		]
	);
	?>

	<?php if ( $gadgetim_products['ids'] ) : ?>
		<section class="sect sres__products" aria-labelledby="sres-p-t">
			<?php
			gadgetim_the_section_head(
				[
					'title'      => __( 'Matching products', 'gadgetim' ),
					'sub'        => __( 'Original gadgets that match your search', 'gadgetim' ),
					'id'         => 'sres-p-t',
					'icon'       => 'bag',
					'more_url'   => $gadgetim_all_url,
					/* translators: %s: number of products, e.g. "12 products" */
					'more_label' => sprintf( __( 'See all %s', 'gadgetim' ), gadgetim_count_label( $gadgetim_products['total'] ) ),
					'rail'       => 'sres-rail',
					'reveal'     => false,
				]
			);
			?>
			<ul class="rail prail" id="sres-rail" aria-label="<?php esc_attr_e( 'Matching products', 'gadgetim' ); ?>">
				<?php gadgetim_set_eager_budget( 2 ); ?>
				<?php foreach ( $gadgetim_products['ids'] as $gadgetim_i => $gadgetim_pid ) : ?>
					<?php
					gadgetim_the_product_card(
						$gadgetim_pid,
						'rail',
						[
							'index'  => $gadgetim_i,
							'reveal' => false,
						]
					);
					?>
				<?php endforeach; ?>
				<?php gadgetim_set_eager_budget( 0 ); ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( '' !== $gadgetim_q && have_posts() ) : ?>
		<section class="sect sres__posts" aria-labelledby="sres-a-t">
			<?php
			gadgetim_the_section_head(
				[
					'title'  => __( 'Pages and articles', 'gadgetim' ),
					'id'     => 'sres-a-t',
					'icon'   => 'layers',
					'tone'   => 'warm',
					'reveal' => false,
				]
			);
			?>
			<ol class="sres">
				<?php
				$gadgetim_i = 0;
				while ( have_posts() ) :
					the_post();
					$gadgetim_type = 'page' === get_post_type() ? __( 'Page', 'gadgetim' ) : __( 'Article', 'gadgetim' );
					$gadgetim_path = trim( rawurldecode( (string) wp_parse_url( (string) get_permalink(), PHP_URL_PATH ) ), '/' );
					?>
					<li class="sres__i"<?php echo $gadgetim_i >= 4 ? ' data-reveal style="--i:' . (int) min( 7, $gadgetim_i - 4 ) . '"' : ''; ?>>
						<article class="sres__in">
							<span class="sres__ic" aria-hidden="true"><?php echo gadgetim_pages_icon( 'page' === get_post_type() ? 'doc' : 'sparkle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<div class="sres__body">
								<p class="sres__meta"><span class="sres__type"><?php echo esc_html( $gadgetim_type ); ?></span><?php if ( 'post' === get_post_type() ) : ?><time datetime="<?php echo esc_attr( (string) get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( gadgetim_date( (int) get_post_time( 'U', true ), 'medium' ) ); ?></time><?php endif; ?></p>
								<h3 class="sres__t"><a href="<?php the_permalink(); ?>"><?php echo gadgetim_pages_highlight( get_the_title(), $gadgetim_q ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></a></h3>
								<p class="sres__x"><?php echo gadgetim_pages_highlight( gadgetim_pages_search_excerpt( get_post(), $gadgetim_q ), $gadgetim_q ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?></p>
								<?php if ( '' !== $gadgetim_path ) : ?>
									<p class="sres__url" dir="auto"><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) . ' › ' . str_replace( '/', ' › ', $gadgetim_path ) ); ?></p>
								<?php endif; ?>
							</div>
							<?php gadgetim_the_icon( 'chev-l', 'ic--dir sres__go' ); ?>
						</article>
					</li>
					<?php
					++$gadgetim_i;
				endwhile;
				?>
			</ol>
			<?php gadgetim_pagination(); ?>
		</section>
	<?php endif; ?>

	<?php if ( $gadgetim_empty ) : ?>
		<?php if ( '' !== $gadgetim_q ) : ?>
			<?php
			echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
				[
					'icon'    => 'search-x',
					/* translators: %s: search query */
					'title'   => gadgetim_isolate_query( sprintf( __( 'Nothing found for “%s”', 'gadgetim' ), $gadgetim_q ), $gadgetim_q ),
					'text'    => __( 'Check the spelling, try a shorter or more general word, or pick one of the suggestions below.', 'gadgetim' ),
					'class'   => 'empty--search',
					'actions' => [
						[
							'label' => __( 'Browse the shop', 'gadgetim' ),
							'href'  => gadgetim_shop_url(),
							'icon'  => 'arrow',
						],
					],
				]
			);
			?>
		<?php endif; ?>
		<?php if ( $gadgetim_popular ) : ?>
			<section class="sect sres__popular" aria-labelledby="sres-pop-t">
				<?php
				gadgetim_the_section_head(
					[
						'title'  => __( 'Popular searches', 'gadgetim' ),
						'id'     => 'sres-pop-t',
						'icon'   => 'trend',
						'tone'   => 'pink',
						'reveal' => false,
					]
				);
				?>
				<ul class="sres__chips">
					<?php foreach ( $gadgetim_popular as $gadgetim_term ) : ?>
						<li>
							<a class="chip-q" href="<?php echo esc_url( add_query_arg( [ 's' => rawurlencode( $gadgetim_term ), 'post_type' => 'product' ], home_url( '/' ) ) ); ?>"><?php gadgetim_the_icon( 'search' ); ?><?php echo esc_html( $gadgetim_term ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		<?php gadgetim_part( 'pages/stories', [ 'id' => 'sres-cats' ] ); ?>
		<?php gadgetim_part( 'pages/popular', [ 'id' => 'sres-best' ] ); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
