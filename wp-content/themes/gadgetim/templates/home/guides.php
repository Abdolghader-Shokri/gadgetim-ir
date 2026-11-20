<?php
/**
 * Buying guides on the home page (SEO run of 2026-11-20): the guide cards of templates/pages/guides.php,
 * so the strongest page of the site links to every published guide. Guides are Persian until one is
 * translated, so /en/ and /ar/ print nothing here.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

gadgetim_part(
	'pages/guides',
	[
		'id'    => 'home-guides-t',
		'class' => 'container home-guides',
	]
);
