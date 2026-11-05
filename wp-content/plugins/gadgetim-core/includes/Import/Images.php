<?php
/**
 * Image sideloading that preserves the original /YYYY/MM/ folders (architecture §6.1 step 4):
 * an upload_dir filter is active for each sideload, so the new URL equals the old path and
 * in-content references stay valid even before rewriting. Idempotent via _gtm_import_src.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use WP_Error;

/**
 * Source files are copied to a temp file first: wp_handle_sideload() deletes its input.
 */
final class Images {

	public const META_SRC = '_gtm_import_src';

	public const META_OLD_ID = '_gtm_import_old_id';

	public const META_OLD_URL = '_gtm_import_old_url';

	private Importer $importer;

	/** @var array<string,int> relative source path → attachment id */
	private array $cache = [];

	private ?string $forced_subdir = null;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * Imports one exported image { id, file, old_url, alt, title }. Returns the attachment id
	 * (0 in a dry run) or a WP_Error.
	 *
	 * @param array  $info        Export entry.
	 * @param int    $parent_id   Product/page to attach to (0 = unattached).
	 * @param string $alt_default Alt text when the export has none.
	 * @param string $date        Post date (site time, 'Y-m-d H:i:s') for the attachment.
	 */
	public function import( array $info, int $parent_id = 0, string $alt_default = '', string $date = '' ): int|WP_Error {
		$relative = self::relative( (string) ( $info['file'] ?? '' ) );
		if ( '' === $relative ) {
			return new WP_Error( 'gadgetim_import_image_path', __( 'Image entry without a file path.', 'gadgetim-core' ) );
		}
		$existing = $this->find_by_src( $relative );
		if ( $existing > 0 ) {
			$this->touch_meta( $existing, $info, $alt_default );
			return $existing;
		}
		if ( ! preg_match( '#^images/(\d{4})/(\d{2})/([^/]+)$#u', $relative, $m ) ) {
			/* translators: %s: relative path */
			return new WP_Error( 'gadgetim_import_image_path', sprintf( __( 'Unexpected image path: %s', 'gadgetim-core' ), $relative ) );
		}
		$subdir   = '/' . $m[1] . '/' . $m[2];
		$filename = $m[3];
		$source   = $this->importer->source()->path( $relative );
		if ( ! is_file( $source ) ) {
			/* translators: %s: file path */
			return new WP_Error( 'gadgetim_import_image_missing', sprintf( __( 'Image file missing: %s', 'gadgetim-core' ), $source ) );
		}

		if ( $this->importer->dry() ) {
			++$this->importer->job()->counts['images'];
			return 0;
		}

		$adopted = $this->adopt_existing( $subdir, $filename );
		if ( $adopted > 0 ) {
			update_post_meta( $adopted, self::META_SRC, $relative );
			$this->touch_meta( $adopted, $info, $alt_default );
			$this->cache[ $relative ] = $adopted;
			/* translators: 1: attachment id, 2: file name */
			$this->log( Log::INFO, sprintf( __( 'Attachment #%1$d already holds %2$s; adopted.', 'gadgetim-core' ), $adopted, $filename ) );
			return $adopted;
		}

		$id = $this->sideload( $source, $filename, $subdir, $parent_id, $info, $date );
		if ( $id instanceof WP_Error ) {
			return $id;
		}
		update_post_meta( $id, self::META_SRC, $relative );
		$this->touch_meta( $id, $info, $alt_default );
		$this->cache[ $relative ]  = $id;
		++$this->importer->job()->counts['images'];
		/* translators: 1: attachment id, 2: relative path */
		$this->log( Log::SUCCESS, sprintf( __( 'Imported image #%1$d (%2$s).', 'gadgetim-core' ), $id, $relative ) );
		return $id;
	}

	/**
	 * Attachment id for a relative source path ('images/2022/08/x.webp'), resized variants
	 * ('-300x300') resolved to the original. 0 when not imported.
	 */
	public function find_by_src( string $relative ): int {
		$relative = self::relative( $relative );
		if ( '' === $relative ) {
			return 0;
		}
		if ( isset( $this->cache[ $relative ] ) ) {
			return $this->cache[ $relative ];
		}
		$id = $this->query_by_src( $relative );
		if ( 0 === $id && preg_match( '/^(.*)-\d{2,4}x\d{2,4}(\.[a-z0-9]+)$/i', $relative, $m ) ) {
			$id = $this->query_by_src( $m[1] . $m[2] );
		}
		$this->cache[ $relative ] = $id;
		return $id;
	}

	/**
	 * New URL for an old upload URL: the imported attachment's root-relative URL; when the
	 * file exists in the export but is not imported yet, the same path under /wp-content/uploads/;
	 * '' when the image is unknown. Other URLs are returned unchanged.
	 */
	public function rewrite_url( string $url, bool $imported_only = false ): string {
		$relative = self::relative_from_url( $url );
		if ( '' === $relative ) {
			return $url;
		}
		$id = $this->find_by_src( $relative );
		if ( $id > 0 ) {
			$new = wp_get_attachment_url( $id );
			return is_string( $new ) && '' !== $new ? wp_make_link_relative( $new ) : $url;
		}
		if ( $imported_only ) {
			return $url;
		}
		$original = (string) preg_replace( '/-\d{2,4}x\d{2,4}(\.[a-z0-9]+)$/i', '$1', $relative );
		if ( $this->importer->source()->has_file( $relative ) || $this->importer->source()->has_file( $original ) ) {
			$uploads = wp_upload_dir( null, false );
			$base    = wp_make_link_relative( (string) ( $uploads['baseurl'] ?? '/wp-content/uploads' ) );
			return rtrim( $base, '/' ) . '/' . substr( $relative, strlen( 'images/' ) );
		}
		return '';
	}

	/**
	 * Rewrites every old upload URL inside HTML (src and href) through rewrite_url();
	 * images that resolve to nothing are left untouched here (the cleaner removed unknown ones).
	 * With $imported_only, only URLs whose attachment already exists (_gtm_import_src) change,
	 * so a products-only re-run never points at files that were not sideloaded.
	 */
	public function rewrite_content( string $html, bool $imported_only = false ): string {
		if ( '' === $html || ! str_contains( $html, 'gadgetim.ir' ) ) {
			return $html;
		}
		return (string) preg_replace_callback(
			'#\b(src|href)=("|\')([^"\']+)\2#i',
			function ( array $m ) use ( $imported_only ): string {
				$new = $this->rewrite_url( $m[3], $imported_only );
				if ( '' === $new || $new === $m[3] ) {
					return $m[0];
				}
				return $m[1] . '=' . $m[2] . esc_url( $new ) . $m[2];
			},
			$html
		);
	}

	/**
	 * 'images/YYYY/MM/file' for an old upload URL or path, '' when it is not one.
	 */
	public static function relative_from_url( string $url ): string {
		$url  = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$path = $url;
		if ( preg_match( '#^https?://#i', $url ) ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( ! in_array( $host, [ 'gadgetim.ir', 'www.gadgetim.ir' ], true ) ) {
				return '';
			}
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		} elseif ( str_starts_with( $url, '//' ) ) {
			return '';
		}
		$path = rawurldecode( $path );
		if ( ! preg_match( '#/wp-content/uploads/(\d{4}/\d{2}/[^/?\#]+)$#u', $path, $m ) ) {
			return '';
		}
		return 'images/' . $m[1];
	}

	/**
	 * Normalises an export file path ('images\2022\08\x.webp' → 'images/2022/08/x.webp').
	 */
	public static function relative( string $path ): string {
		$path = trim( str_replace( '\\', '/', $path ) );
		$path = ltrim( $path, '/' );
		if ( '' !== $path && ! str_starts_with( $path, 'images/' ) && preg_match( '#^\d{4}/\d{2}/#', $path ) ) {
			$path = 'images/' . $path;
		}
		return $path;
	}

	/**
	 * upload_dir filter active during a sideload: forces the source folder.
	 */
	public function upload_dir_filter( array $dirs ): array {
		if ( null === $this->forced_subdir ) {
			return $dirs;
		}
		$dirs['subdir'] = $this->forced_subdir;
		$dirs['path']   = rtrim( (string) $dirs['basedir'], '/\\' ) . $this->forced_subdir;
		$dirs['url']    = rtrim( (string) $dirs['baseurl'], '/' ) . $this->forced_subdir;
		return $dirs;
	}

	/**
	 * media_handle_sideload() through a temp copy with the upload_dir filter armed.
	 */
	private function sideload( string $source, string $filename, string $subdir, int $parent_id, array $info, string $date ): int|WP_Error {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$tmp = wp_tempnam( $filename );
		if ( '' === $tmp || ! copy( $source, $tmp ) ) {
			return new WP_Error( 'gadgetim_import_image_copy', __( 'The image could not be copied to a temporary file.', 'gadgetim-core' ) );
		}
		$type = (string) ( wp_check_filetype( $filename )['type'] ?? '' );
		if ( '' === $type ) {
			$type = (string) ( wp_get_image_mime( $source ) ?: 'application/octet-stream' );
		}
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
			'type'     => $type,
			'size'     => (int) filesize( $source ),
			'error'    => 0,
		];
		$title     = trim( (string) ( $info['title'] ?? '' ) );
		$post_data = [
			'post_title'   => '' !== $title ? sanitize_text_field( $title ) : sanitize_text_field( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_excerpt' => '',
		];
		if ( '' !== $date ) {
			$post_data['post_date'] = $date;
		}

		$this->forced_subdir = $subdir;
		add_filter( 'upload_dir', [ $this, 'upload_dir_filter' ], 20 );
		try {
			$id = media_handle_sideload( $file_array, $parent_id, null, $post_data );
		} finally {
			remove_filter( 'upload_dir', [ $this, 'upload_dir_filter' ], 20 );
			$this->forced_subdir = null;
		}
		if ( is_file( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( $id instanceof WP_Error ) {
			return $id;
		}
		return (int) $id;
	}

	/**
	 * Alt text (export alt, else the default), old id and old URL.
	 */
	private function touch_meta( int $attachment_id, array $info, string $alt_default ): void {
		if ( $this->importer->dry() ) {
			return;
		}
		$alt = trim( (string) ( $info['alt'] ?? '' ) );
		if ( '' === $alt ) {
			$alt = trim( $alt_default );
		}
		if ( '' !== $alt && '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
		$old_id = (int) ( $info['id'] ?? 0 );
		if ( $old_id > 0 ) {
			update_post_meta( $attachment_id, self::META_OLD_ID, $old_id );
		}
		$old_url = (string) ( $info['old_url'] ?? '' );
		if ( '' !== $old_url ) {
			update_post_meta( $attachment_id, self::META_OLD_URL, esc_url_raw( $old_url ) );
		}
	}

	/**
	 * An attachment that already stores this file (same folder and name), or 0.
	 */
	private function adopt_existing( string $subdir, string $filename ): int {
		$candidates = array_unique( [ $filename, sanitize_file_name( $filename ) ] );
		foreach ( $candidates as $candidate ) {
			$attached = ltrim( $subdir, '/' ) . '/' . $candidate;
			$ids      = get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						[
							'key'   => '_wp_attached_file',
							'value' => $attached,
						],
					],
				]
			);
			if ( is_array( $ids ) && $ids ) {
				return (int) $ids[0];
			}
		}
		return 0;
	}

	/**
	 * Attachment id by _gtm_import_src.
	 */
	private function query_by_src( string $relative ): int {
		$ids = get_posts(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => self::META_SRC,
						'value' => $relative,
					],
				],
			]
		);
		return is_array( $ids ) && $ids ? (int) $ids[0] : 0;
	}

	private function log( string $level, string $message ): void {
		$this->importer->log()->add( $level, $message, 'images' );
	}
}
