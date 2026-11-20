<?php
/**
 * Template Name: Buying guide
 * Template Post Type: page
 *
 * Buying-guide page (SEO run of 2026-11-20): the generic page body with the guide hero (role
 * "guide", inc/pages.php), then cards for the other buying guides, so every guide links to the
 * rest. The home page lists every published guide too (templates/home/guides.php).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

while ( have_posts() ) {
	the_post();
	gadgetim_part( 'pages/page-body', [ 'post' => get_post() ] );
	if ( ! post_password_required() ) {
		gadgetim_part(
			'pages/guides',
			[
				'exclude' => get_the_ID(),
				'title'   => __( 'More buying guides', 'gadgetim' ),
				'class'   => 'container guides--related',
			]
		);
	}
}

get_footer();
