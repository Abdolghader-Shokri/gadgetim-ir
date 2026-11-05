<?php
/**
 * Article list body (index.php: blog index; archive.php: categories, tags, authors, dates):
 * breadcrumbs, gradient hero, category chips, a card grid (the newest article featured on the
 * first page), pagination; an empty state with popular categories and products otherwise.
 *
 * Args: title, kicker, lead, icon, tone.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

global $wp_query;
$gadgetim_b     = wp_parse_args(
	$args ?? [],
	[
		'title'  => __( 'Articles and guides', 'gadgetim' ),
		'kicker' => gadgetim_brand_name(),
		'lead'   => __( 'Buying guides, comparisons and tips for getting more out of your gadgets.', 'gadgetim' ),
		'icon'   => 'doc',
		'tone'   => 'warm',
	]
);
$gadgetim_total = $wp_query instanceof WP_Query ? (int) $wp_query->found_posts : 0;
$gadgetim_paged = max( 1, (int) get_query_var( 'paged' ) );
$gadgetim_chips = [];
if ( $gadgetim_total > 0 ) {
	$gadgetim_chips[] = [
		'icon' => 'doc',
		/* translators: %s: number of articles */
		'text' => sprintf( _n( '%s article', '%s articles', $gadgetim_total, 'gadgetim' ), gadgetim_number( $gadgetim_total ) ),
	];
}
if ( $gadgetim_paged > 1 ) {
	$gadgetim_chips[] = [
		'icon' => 'layers',
		/* translators: %s: page number */
		'text' => sprintf( __( 'Page %s', 'gadgetim' ), gadgetim_number( $gadgetim_paged ) ),
	];
}

// Category chips: every category with articles (the default one only when it is the only one).
$gadgetim_default = (int) get_option( 'default_category' );
$gadgetim_cats    = get_categories(
	[
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 12,
	]
);
$gadgetim_cats    = is_array( $gadgetim_cats ) ? $gadgetim_cats : [];
if ( count( $gadgetim_cats ) > 1 ) {
	$gadgetim_cats = array_values( array_filter( $gadgetim_cats, static fn( WP_Term $t ): bool => (int) $t->term_id !== $gadgetim_default ) );
}
$gadgetim_current = is_category() ? (int) get_queried_object_id() : 0;
$gadgetim_blog    = (int) get_option( 'page_for_posts' ) > 0 && 'page' === get_option( 'show_on_front' ) ? (string) get_permalink( (int) get_option( 'page_for_posts' ) ) : '';
?>
<div class="container pg-shell blog-shell">
	<?php gadgetim_breadcrumbs(); ?>
	<?php
	gadgetim_part(
		'pages/hero',
		[
			'title'  => (string) $gadgetim_b['title'],
			'kicker' => (string) $gadgetim_b['kicker'],
			'lead'   => (string) $gadgetim_b['lead'],
			'icon'   => (string) $gadgetim_b['icon'],
			'tone'   => (string) $gadgetim_b['tone'],
			'chips'  => $gadgetim_chips,
		]
	);
	?>
	<?php if ( count( $gadgetim_cats ) > 1 || ( $gadgetim_cats && '' !== $gadgetim_blog ) ) : ?>
		<nav class="bcats" aria-label="<?php esc_attr_e( 'Article categories', 'gadgetim' ); ?>">
			<ul class="bcats__list">
				<?php if ( '' !== $gadgetim_blog ) : ?>
					<li><a class="chip-q<?php echo is_home() ? ' is-active' : ''; ?>" href="<?php echo esc_url( $gadgetim_blog ); ?>"<?php echo is_home() ? ' aria-current="page"' : ''; ?>><?php gadgetim_the_icon( 'grid' ); ?><?php esc_html_e( 'All articles', 'gadgetim' ); ?></a></li>
				<?php endif; ?>
				<?php foreach ( $gadgetim_cats as $gadgetim_cat ) : ?>
					<?php $gadgetim_on = (int) $gadgetim_cat->term_id === $gadgetim_current; ?>
					<li><a class="chip-q<?php echo $gadgetim_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( (string) get_category_link( $gadgetim_cat ) ); ?>"<?php echo $gadgetim_on ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $gadgetim_cat->name ); ?> <small><?php echo esc_html( gadgetim_number( (int) $gadgetim_cat->count ) ); ?></small></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<ul class="bgrid">
			<?php
			$gadgetim_i = 0;
			while ( have_posts() ) {
				the_post();
				$gadgetim_feature = 0 === $gadgetim_i && 1 === $gadgetim_paged && $gadgetim_total >= 3;
				gadgetim_part(
					'pages/post-card',
					[
						'post'    => get_post(),
						'variant' => $gadgetim_feature ? 'feature' : 'default',
						'index'   => $gadgetim_i,
						'reveal'  => $gadgetim_i >= 3,
						'eager'   => 0 === $gadgetim_i,
					]
				);
				++$gadgetim_i;
			}
			?>
		</ul>
		<?php gadgetim_pagination(); ?>
	<?php else : ?>
		<?php
		echo gadgetim_empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper.
			[
				'icon'    => 'layers',
				'title'   => __( 'No articles here yet', 'gadgetim' ),
				'text'    => __( 'New guides are on the way. Meanwhile, have a look at the shop.', 'gadgetim' ),
				'class'   => 'empty--blog',
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
		<?php gadgetim_part( 'pages/stories', [ 'id' => 'blog-cats' ] ); ?>
		<?php gadgetim_part( 'pages/popular', [ 'id' => 'blog-pop' ] ); ?>
	<?php endif; ?>
</div>
