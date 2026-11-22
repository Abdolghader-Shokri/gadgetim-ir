<?php
/**
 * Buying guides that compare or recommend products of the brand or product category being shown
 * (gadgetim_guides_for_term(), inc/pages.php), above the term description on page 1 (SEO run of
 * 2026-11-22: the rivals' brand pages carry «مطالب مرتبط»; each guide is now also linked from the
 * archive pages whose products it covers). Prints nothing when no guide links one of the products,
 * and on /en/ and /ar/ until a guide is translated.
 *
 * @package Gadgetim
 */

declare( strict_types=1 );

$gadgetim_info = gadgetim_archive_info();
$gadgetim_term = $gadgetim_info['term'] ?? null;
if ( is_paged() || ! $gadgetim_term instanceof WP_Term || ! in_array( (string) ( $gadgetim_info['kind'] ?? '' ), [ 'brand', 'category' ], true ) ) {
	return;
}
gadgetim_part(
	'pages/guides',
	[
		'items' => gadgetim_guides_for_term( $gadgetim_term ),
		'id'    => 'arch-guides-t',
		'title' => __( 'Related buying guides', 'gadgetim' ),
		'sub'   => __( 'Guides that compare or recommend products on this page', 'gadgetim' ),
		'class' => 'guides--arch',
	]
);
