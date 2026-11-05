<?php
/**
 * Page template.
 *
 * Content pages (privacy, terms and any page without a special template) render the generic body
 * (templates/pages/page-body.php): gradient hero, prose, table of contents, help aside. Pages
 * with a role use page-templates/<role>.php instead (inc/pages.php, page_template_hierarchy).
 *
 * Cart, checkout and account render their WooCommerce shortcodes in the plain shell below: the
 * commerce builder styles it (commerce.css) or takes the page over through template_include.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

if ( in_array( gadgetim_page_kind(), [ 'cart', 'checkout', 'account' ], true ) ) :
	?>
	<div class="container page-shell">
		<?php gadgetim_breadcrumbs(); ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'entry entry--wc' ); ?>>
				<header class="page-head">
					<h1 class="page-head__t"><?php echo gadgetim_bidi( get_the_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></h1>
				</header>
				<div class="panel entry__body">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</div>
	<?php
else :
	while ( have_posts() ) {
		the_post();
		gadgetim_part( 'pages/page-body', [ 'post' => get_post() ] );
	}
endif;

get_footer();
