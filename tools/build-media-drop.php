<?php
/**
 * Builds a media package for the site's drop folder (gadgetim-core Import\Drop, media[]): the
 * files of existing media-library images, so a host that lost them gets them back under the
 * names its library already has. The move to the live host lost every upload with a Persian
 * file name (2026-11-14: 25 product photos answered 404 there).
 *
 * Runs inside the Local site through WP-CLI, which knows the images' records:
 *   php tools/wp-cli/wp-cli.phar --path=<site> eval-file tools/build-media-drop.php <out.zip> [<id> ...]
 * Without IDs it takes every image whose stored file name is not plain ASCII. Each image's file,
 * its sizes and the original of a scaled image go into media/ under plain names, with their
 * uploads path and sha1 in manifest.json. Needs the zip extension (Local's PHP has it).
 *
 * No strict_types declaration: WP-CLI's eval-file evaluates the file's text, where the
 * declaration would not be the first statement.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	fwrite( STDERR, "run through WP-CLI: wp eval-file tools/build-media-drop.php <out.zip> [<id> ...]\n" );
	exit( 2 );
}

// $args: WP-CLI's positional arguments after the file name.
$gtm_out = (string) ( $args[0] ?? '' );
if ( '' === $gtm_out ) {
	WP_CLI::error( 'usage: wp eval-file tools/build-media-drop.php <out.zip> [<id> ...]' );
}
$gtm_ids = array_values( array_filter( array_map( 'intval', array_slice( $args, 1 ) ) ) );
if ( ! $gtm_ids ) {
	global $wpdb;
	foreach ( $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" ) as $gtm_row ) {
		if ( preg_match( '/[^\x20-\x7E]/', (string) $gtm_row->meta_value ) ) {
			$gtm_ids[] = (int) $gtm_row->post_id;
		}
	}
}

$gtm_base  = trailingslashit( wp_get_upload_dir()['basedir'] );
$gtm_media = [];
$gtm_files = [];
$gtm_bytes = 0;
foreach ( $gtm_ids as $gtm_id ) {
	$gtm_file = (string) get_post_meta( $gtm_id, '_wp_attached_file', true );
	if ( '' === $gtm_file || 'attachment' !== get_post_type( $gtm_id ) ) {
		WP_CLI::warning( "#$gtm_id is not a media-library file" );
		continue;
	}
	$gtm_dir   = dirname( $gtm_file );
	$gtm_dir   = '.' === $gtm_dir ? '' : $gtm_dir . '/';
	$gtm_names = [ wp_basename( $gtm_file ) ];
	$gtm_meta  = wp_get_attachment_metadata( $gtm_id );
	foreach ( (array) ( is_array( $gtm_meta ) ? ( $gtm_meta['sizes'] ?? [] ) : [] ) as $gtm_size ) {
		if ( is_array( $gtm_size ) && ! empty( $gtm_size['file'] ) ) {
			$gtm_names[] = wp_basename( (string) $gtm_size['file'] );
		}
	}
	if ( is_array( $gtm_meta ) && ! empty( $gtm_meta['original_image'] ) ) {
		$gtm_names[] = wp_basename( (string) $gtm_meta['original_image'] );
	}

	$gtm_entry = [
		'id'    => $gtm_id,
		'file'  => $gtm_file,
		'files' => [],
	];
	foreach ( array_values( array_unique( $gtm_names ) ) as $gtm_n => $gtm_name ) {
		$gtm_src = $gtm_base . $gtm_dir . $gtm_name;
		if ( ! is_file( $gtm_src ) ) {
			WP_CLI::warning( "#$gtm_id: $gtm_dir$gtm_name is missing here too" );
			continue;
		}
		$gtm_ext                    = strtolower( (string) substr( (string) strrchr( $gtm_name, '.' ), 1 ) );
		$gtm_zip_name               = 'media/' . $gtm_id . '-' . $gtm_n . '.' . $gtm_ext;
		$gtm_files[ $gtm_zip_name ] = $gtm_src;
		$gtm_entry['files'][]       = [
			'path' => $gtm_dir . $gtm_name,
			'zip'  => $gtm_zip_name,
			'sha1' => sha1_file( $gtm_src ),
		];
		$gtm_bytes += (int) filesize( $gtm_src );
	}
	if ( $gtm_entry['files'] ) {
		$gtm_media[] = $gtm_entry;
	}
}
if ( ! $gtm_media ) {
	WP_CLI::error( 'nothing to pack' );
}

if ( is_file( $gtm_out ) ) {
	unlink( $gtm_out );
}
$gtm_zip = new ZipArchive();
if ( true !== $gtm_zip->open( $gtm_out, ZipArchive::CREATE ) ) {
	WP_CLI::error( "cannot create $gtm_out" );
}
$gtm_zip->addFromString(
	'manifest.json',
	(string) wp_json_encode(
		[
			'version' => 1,
			'built'   => gmdate( 'c' ),
			'media'   => $gtm_media,
		],
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
	)
);
foreach ( $gtm_files as $gtm_zip_name => $gtm_src ) {
	// addFromString: libzip's own file reading does not take UTF-8 paths on Windows.
	$gtm_zip->addFromString( $gtm_zip_name, (string) file_get_contents( $gtm_src ) );
}
$gtm_zip->close();
WP_CLI::success( sprintf( '%d image(s), %d file(s), %s -> %s', count( $gtm_media ), count( $gtm_files ), size_format( $gtm_bytes ), $gtm_out ) );
