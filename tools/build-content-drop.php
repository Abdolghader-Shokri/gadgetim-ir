<?php
/**
 * Builds a content package for the site's drop folder (gadgetim-core Import\Drop, posts[] and
 * terms[]) from the Local site: pages written or rewritten here, and the introduction texts of
 * product categories and brands, in every language. The live database is a different one, so
 * this is how content reaches the live site (SEO engineer, 2026-11-16).
 *
 * Runs inside WordPress:
 *   "$PHP" -c "$INI" -d display_startup_errors=0 tools/wp-bootstrap.php tools/build-content-drop.php <out.zip> <list.json>
 *
 * list.json:
 *   {
 *     "posts": [ { "id": 7012 }, { "id": 851, "live_id": 851, "replace": true } ],
 *     "terms": [ { "taxonomy": "product_cat", "id": 45, "live_id": 45, "replace": true } ]
 *   }
 * - posts: Local page IDs. Without "live_id" the page is new on the live site and is created only
 *   where no page answers yet. With "live_id" (the ID the live site has at that address) and
 *   "replace", the live page's texts are replaced; WordPress keeps the old ones as a revision.
 *   The featured image travels with a page that is new or that the live site shows without one.
 * - terms: Local term IDs. "replace" swaps a text the live term already has (the old one is kept
 *   in its meta); without it only an empty text is filled. "live_id" guards the address.
 * - "translations": false on a row ships the Persian texts only. The live site writes every en/ar
 *   field a package carries, so a row whose translations were not worked on leaves them out
 *   (SEO run, 2026-11-26: Local holds en/ar category texts that were never checked against live).
 */

declare( strict_types=1 );

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Seo\Meta;

$args = array_values( array_slice( $argv ?? [], 1 ) );
if ( 2 !== count( $args ) ) {
	fwrite( STDERR, "usage: tools/wp-bootstrap.php tools/build-content-drop.php <out.zip> <list.json>\n" );
	exit( 1 );
}
[ $out, $list ] = $args;
$want = json_decode( (string) file_get_contents( $list ), true );
if ( ! is_array( $want ) || ( empty( $want['posts'] ) && empty( $want['terms'] ) ) ) {
	fwrite( STDERR, "bad list: $list\n" );
	exit( 1 );
}

$langs    = Languages::secondary_codes();
$posts    = [];
$terms    = [];
$files    = [];
$problems = [];

foreach ( (array) ( $want['posts'] ?? [] ) as $n => $row ) {
	$id   = (int) ( $row['id'] ?? 0 );
	$post = $id > 0 ? get_post( $id ) : null;
	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
		$problems[] = "posts[$n]: #$id is not a published page";
		continue;
	}
	$live = (int) ( $row['live_id'] ?? 0 );
	if ( ! empty( $row['replace'] ) && $live <= 0 ) {
		$problems[] = "posts[$n]: \"replace\" needs the live_id of the page on the live site";
		continue;
	}
	$entry = [
		'path' => rawurldecode( get_page_uri( $post ) ),
		'fa'   => [
			'title'     => $post->post_title,
			'content'   => $post->post_content,
			'excerpt'   => $post->post_excerpt,
			'seo_title' => (string) get_post_meta( $id, '_gtm_seo_title', true ),
			'seo_desc'  => (string) get_post_meta( $id, '_gtm_seo_desc', true ),
		],
	];
	if ( $live > 0 ) {
		$entry['id'] = $live;
	}
	if ( ! empty( $row['replace'] ) ) {
		$entry['replace'] = true;
	}
	$template = (string) get_post_meta( $id, '_wp_page_template', true );
	if ( '' !== $template ) {
		$entry['template'] = $template;
	}
	if ( $post->menu_order ) {
		$entry['menu_order'] = (int) $post->menu_order;
	}
	foreach ( false === ( $row['translations'] ?? true ) ? [] : $langs as $lang ) {
		$t = PostTranslations::all( $id, $lang );
		if ( '' === ( $t['title'] ?? '' ) ) {
			continue;
		}
		$entry['translations'][ $lang ] = array_filter(
			[
				'title'     => $t['title'] ?? '',
				'content'   => $t['content'] ?? '',
				'excerpt'   => $t['excerpt'] ?? '',
				'seo_title' => $t['seo_title'] ?? '',
				'seo_desc'  => $t['seo_desc'] ?? '',
				'slug'      => rawurldecode( (string) ( $t['slug'] ?? '' ) ),
			],
			static fn( $v ): bool => '' !== (string) $v
		);
	}
	$thumb = (int) get_post_thumbnail_id( $id );
	$file  = $thumb ? (string) get_attached_file( $thumb ) : '';
	if ( '' !== $file && is_file( $file ) ) {
		$name = basename( $file );
		if ( isset( $files[ 'images/' . $name ] ) ) {
			$problems[] = "posts[$n]: two pages use the image file name $name";
			continue;
		}
		$files[ 'images/' . $name ] = $file;
		$entry['image']             = [
			'file'        => $name,
			'title'       => get_the_title( $thumb ),
			'alt'         => (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ),
			'caption'     => (string) wp_get_attachment_caption( $thumb ),
			'description' => (string) get_post_field( 'post_content', $thumb, 'raw' ),
			'text_lang'   => (string) get_post_meta( $thumb, Meta::META_TEXT_LANG, true ),
		];
	}
	$posts[] = $entry;
}

foreach ( (array) ( $want['terms'] ?? [] ) as $n => $row ) {
	$taxonomy = (string) ( $row['taxonomy'] ?? 'product_cat' );
	$term     = get_term( (int) ( $row['id'] ?? 0 ), $taxonomy );
	if ( ! $term instanceof WP_Term ) {
		$problems[] = "terms[$n]: no $taxonomy term #" . (int) ( $row['id'] ?? 0 );
		continue;
	}
	if ( '' === trim( (string) $term->description ) ) {
		$problems[] = "terms[$n]: {$term->name} has no text on this site";
		continue;
	}
	$entry = [
		'taxonomy' => $taxonomy,
		'slug'     => rawurldecode( $term->slug ),
		'fa'       => [ 'desc' => $term->description ],
	];
	if ( ! empty( $row['live_id'] ) ) {
		$entry['id'] = (int) $row['live_id'];
	}
	if ( ! empty( $row['replace'] ) ) {
		$entry['replace'] = true;
	}
	foreach ( false === ( $row['translations'] ?? true ) ? [] : $langs as $lang ) {
		$t = TermTranslations::all( (int) $term->term_id, $lang );
		$t = array_filter(
			[
				'name'   => $t['name'] ?? '',
				'desc'   => $t['desc'] ?? '',
				'status' => $t['status'] ?? '',
			],
			static fn( $v ): bool => '' !== (string) $v
		);
		if ( $t ) {
			$entry['translations'][ $lang ] = $t;
		}
	}
	$terms[] = $entry;
}

if ( $problems ) {
	fwrite( STDERR, implode( "\n", $problems ) . "\n" );
	exit( 1 );
}

@unlink( $out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- may not exist.
$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "cannot create $out\n" );
	exit( 1 );
}
$manifest = array_filter(
	[
		'version' => 1,
		'built'   => gmdate( 'c' ),
		'posts'   => $posts,
		'terms'   => $terms,
	]
);
$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
foreach ( $files as $name => $src ) {
	$zip->addFile( $src, $name );
}
$zip->close();
printf( "%s: %d page(s), %d term text(s), %d image(s)\n", $out, count( $posts ), count( $terms ), count( $files ) );
