<?php
/**
 * Front page (Aurora «شفق»): category stories, hero slider beside the instant offer, services
 * strip, amazing-offers band, newest rail, promo banners, ranked board, category bento, featured
 * tabs, brand marquee with shop statistics. Everything is rendered on the server from
 * gadgetim-core's Catalog\HomeContent (inc/home.php); home.js only enhances.
 * Sections: templates/home/<name>.php, order filterable through gadgetim_home_sections.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

get_header();
?>
<h1 class="sr-only"><?php echo esc_html( gadgetim_home_heading() ); ?></h1>
<?php
foreach ( gadgetim_home_sections() as $gadgetim_section ) {
	gadgetim_part( 'home/' . $gadgetim_section );
}
get_footer();
