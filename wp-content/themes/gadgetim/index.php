<?php
/**
 * Fallback template and blog index (the posts page, when one is set under Settings → Reading):
 * the article list body (templates/pages/blog.php). Archives use archive.php, the shop uses
 * WooCommerce's templates, the front page front-page.php.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

$gadgetim_args = [];
if ( is_home() && ! is_front_page() ) {
	$gadgetim_blog_page = (int) get_option( 'page_for_posts' );
	if ( $gadgetim_blog_page > 0 ) {
		$gadgetim_args['title'] = get_the_title( $gadgetim_blog_page );
		if ( has_excerpt( $gadgetim_blog_page ) ) {
			$gadgetim_args['lead'] = wp_strip_all_tags( get_the_excerpt( $gadgetim_blog_page ) );
		}
	}
} elseif ( is_archive() ) {
	add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
	$gadgetim_args['title'] = wp_strip_all_tags( get_the_archive_title() );
	remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
}
gadgetim_part( 'pages/blog', $gadgetim_args );

get_footer();
