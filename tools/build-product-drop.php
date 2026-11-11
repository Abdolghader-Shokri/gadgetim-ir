<?php
/**
 * Builds a product package for the site's drop folder (gadgetim-core Import\Drop) from prepared
 * listings: output/<slug>/listing.json + its webp images -> release/deploy/products-<stamp>.zip.
 *
 * Usage: php tools/build-product-drop.php <out.zip> <listing-dir> [<listing-dir> ...]
 * Needs the zip extension (Local's PHP has it).
 */

declare( strict_types=1 );

if ( $argc < 3 ) {
	fwrite( STDERR, "usage: php tools/build-product-drop.php <out.zip> <listing-dir> [...]\n" );
	exit( 2 );
}
$out      = $argv[1];
$products = [];
$files    = [];

foreach ( array_slice( $argv, 2 ) as $dir ) {
	$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
	$l   = json_decode( (string) file_get_contents( $dir . '/listing.json' ), true );
	if ( ! is_array( $l ) || empty( $l['sku'] ) || empty( $l['fa']['title'] ) ) {
		fwrite( STDERR, "bad listing: $dir\n" );
		exit( 1 );
	}
	$text = static fn( array $x ): array => [
		'title'     => $x['title'],
		'slug'      => $x['slug'] ?? '',
		'subtitle'  => $x['subtitle'] ?? '',
		'excerpt'   => $x['short'] ?? '',
		'content'   => $x['content'] ?? '',
		'seo_title' => $x['seo_title'] ?? '',
		'seo_desc'  => $x['seo_desc'] ?? '',
		'specs'     => $x['specs'] ?? [],
	];
	$images = [];
	foreach ( $l['images'] as $im ) {
		$src = $dir . '/' . $im['file'];
		if ( ! is_file( $src ) || 'webp' !== strtolower( pathinfo( $src, PATHINFO_EXTENSION ) ) ) {
			fwrite( STDERR, "missing webp image: $src\n" );
			exit( 1 );
		}
		$files[ 'images/' . $im['file'] ] = $src;
		$images[]                         = [
			'file'        => $im['file'],
			'title'       => $im['title'],
			'caption'     => $im['caption'],
			'alt'         => $im['alt'],
			'description' => $im['attr'] ?? '',
		];
	}
	$translations = [];
	foreach ( [ 'en', 'ar' ] as $lang ) {
		if ( ! empty( $l[ $lang ]['title'] ) ) {
			$translations[ $lang ] = $text( $l[ $lang ] );
		}
	}
	$products[] = [
		'sku'          => $l['sku'],
		'price'        => [
			'currency' => $l['price']['currency'] ?? '',
			'regular'  => $l['price']['base_regular'] ?? ( $l['price']['regular'] ?? '' ),
			'sale'     => $l['price']['base_sale'] ?? ( $l['price']['sale'] ?? '' ),
		],
		'categories'   => array_values( array_filter( [ (string) ( $l['category'] ?? '' ) ] ) ),
		'brand'        => (string) ( $l['brand'] ?? '' ),
		'stock'        => isset( $l['stock_quantity'] ) ? [ 'quantity' => (int) $l['stock_quantity'] ] : [ 'status' => 'instock' ],
		'images'       => $images,
		'fa'           => $text( $l['fa'] ),
		'translations' => $translations,
	];
}

@unlink( $out );
$zip = new ZipArchive();
if ( true !== $zip->open( $out, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "cannot create $out\n" );
	exit( 1 );
}
$zip->addFromString( 'manifest.json', json_encode( [ 'version' => 1, 'built' => gmdate( 'c' ), 'products' => $products ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
foreach ( $files as $name => $src ) {
	$zip->addFile( $src, $name );
}
$zip->close();
echo count( $products ) . ' product(s), ' . count( $files ) . " image(s) -> $out\n";
