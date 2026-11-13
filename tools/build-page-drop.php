<?php
/**
 * Builds a page-image package for the site's drop folder (gadgetim-core Import\Drop, pages[]):
 * branded featured images for existing pages, which reach the live site without a database copy.
 *
 * Usage: php tools/build-page-drop.php <out.zip> <pages.json>
 *
 * pages.json is a list of { "id": 851, "path": "درباره-ما", "file": "<image path>", "title": "…",
 * "alt": "…", "text_lang": "fa", "replace": false }. "path" is the page address after the domain
 * (Persian as written); "id" is the page ID the site is expected to have there; "text_lang" is the
 * language of the words drawn on the image, so other languages share their own card instead;
 * "replace" swaps an existing featured image (default: a page that has one keeps it).
 * A relative "file" is read from the folder of pages.json. Needs the zip extension (Local's PHP has it).
 */

declare( strict_types=1 );

if ( 3 !== $argc ) {
	fwrite( STDERR, "usage: php tools/build-page-drop.php <out.zip> <pages.json>\n" );
	exit( 2 );
}
$out  = $argv[1];
$list = $argv[2];
$rows = json_decode( (string) file_get_contents( $list ), true );
if ( ! is_array( $rows ) || ! $rows ) {
	fwrite( STDERR, "bad list: $list\n" );
	exit( 1 );
}

$base  = dirname( str_replace( '\\', '/', $list ) );
$pages = [];
$files = [];
foreach ( $rows as $n => $r ) {
	if ( ! is_array( $r ) || empty( $r['path'] ) || empty( $r['file'] ) || empty( $r['alt'] ) ) {
		fwrite( STDERR, "row $n needs path, file and alt\n" );
		exit( 1 );
	}
	$src = str_replace( '\\', '/', (string) $r['file'] );
	if ( ! is_file( $src ) ) {
		$src = $base . '/' . $src;
	}
	$ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
	if ( ! is_file( $src ) || ! in_array( $ext, [ 'jpg', 'jpeg', 'webp', 'png' ], true ) ) {
		fwrite( STDERR, "missing image: $src\n" );
		exit( 1 );
	}
	$name = basename( $src );
	if ( isset( $files[ 'images/' . $name ] ) ) {
		fwrite( STDERR, "two rows use the file name $name\n" );
		exit( 1 );
	}
	$files[ 'images/' . $name ] = $src;

	$page = [
		'path'  => trim( (string) $r['path'], '/' ),
		'image' => [
			'file'      => $name,
			'title'     => (string) ( $r['title'] ?? '' ),
			'alt'       => (string) $r['alt'],
			'text_lang' => (string) ( $r['text_lang'] ?? '' ),
		],
	];
	if ( ! empty( $r['id'] ) ) {
		$page['id'] = (int) $r['id'];
	}
	if ( ! empty( $r['replace'] ) ) {
		$page['replace'] = true;
	}
	$pages[] = $page;
}

@unlink( $out );
$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "cannot create $out\n" );
	exit( 1 );
}
$zip->addFromString( 'manifest.json', json_encode( [ 'version' => 1, 'built' => gmdate( 'c' ), 'pages' => $pages ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
foreach ( $files as $name => $src ) {
	$zip->addFile( $src, $name );
}
$zip->close();
echo count( $pages ) . ' page(s), ' . count( $files ) . " image(s) -> $out\n";
