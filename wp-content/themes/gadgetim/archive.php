<?php
/**
 * Blog archives (category, tag, author, date): the article list body (templates/pages/blog.php)
 * with the archive's name as the title, the kind of archive as the kicker and the term
 * description as the lead. Product archives are rendered by WooCommerce (archive-product.php).
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();

add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );
$gadgetim_title = wp_strip_all_tags( get_the_archive_title() );
remove_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

$gadgetim_kicker = __( 'Archive', 'gadgetim' );
$gadgetim_icon   = 'layers';
$gadgetim_tone   = 'warm';
if ( is_category() ) {
	$gadgetim_kicker = __( 'Category', 'gadgetim' );
	$gadgetim_icon   = 'grid';
	$gadgetim_tone   = 'violet';
} elseif ( is_tag() ) {
	$gadgetim_kicker = __( 'Tag', 'gadgetim' );
	$gadgetim_icon   = 'tag';
	$gadgetim_tone   = 'pink';
} elseif ( is_author() ) {
	$gadgetim_kicker = __( 'Author', 'gadgetim' );
	$gadgetim_icon   = 'user';
	$gadgetim_tone   = 'cool';
} elseif ( is_date() ) {
	$gadgetim_icon = 'calendar';
	$gadgetim_tone = 'gold';
}
$gadgetim_lead = trim( wp_strip_all_tags( (string) get_the_archive_description() ) );

gadgetim_part(
	'pages/blog',
	[
		'title'  => '' !== $gadgetim_title ? $gadgetim_title : __( 'Articles and guides', 'gadgetim' ),
		'kicker' => $gadgetim_kicker,
		'lead'   => '' !== $gadgetim_lead ? $gadgetim_lead : __( 'Buying guides, comparisons and tips for getting more out of your gadgets.', 'gadgetim' ),
		'icon'   => $gadgetim_icon,
		'tone'   => $gadgetim_tone,
	]
);

get_footer();
