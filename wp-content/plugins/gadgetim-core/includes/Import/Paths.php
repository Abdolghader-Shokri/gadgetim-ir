<?php
/**
 * Locations the importer works with: repository root (through the Local junction), data/
 * directory, default source directory, writable output directory.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * On Local the plugin directory is a junction into the git repository; realpath() resolves
 * it, so the repository root is three levels above the real plugin directory.
 */
final class Paths {

	public const OPTION_SOURCE = 'gadgetim_import_source';

	/**
	 * Plugin directory with the junction resolved, forward slashes, no trailing slash.
	 */
	public static function plugin_real_dir(): string {
		$dir  = rtrim( GADGETIM_CORE_DIR, '/\\' );
		$real = realpath( $dir );
		return self::normalize( false === $real ? $dir : $real );
	}

	/**
	 * Repository root: GADGETIM_REPO_ROOT when defined, else the first ancestor (3 levels
	 * above the real or the linked plugin directory) that holds data/ or .gitattributes.
	 */
	public static function repo_root(): string {
		if ( defined( 'GADGETIM_REPO_ROOT' ) && is_string( GADGETIM_REPO_ROOT ) && is_dir( GADGETIM_REPO_ROOT ) ) {
			return self::normalize( GADGETIM_REPO_ROOT );
		}
		$candidates = [
			dirname( self::plugin_real_dir(), 3 ),
			dirname( self::normalize( GADGETIM_CORE_DIR ), 3 ),
		];
		foreach ( $candidates as $candidate ) {
			if ( is_dir( $candidate . '/data' ) || is_file( $candidate . '/.gitattributes' ) ) {
				return $candidate;
			}
		}
		return $candidates[0];
	}

	/**
	 * The data/ directory (brand-merge.json, category-map.json, redirects.csv, pages.json).
	 */
	public static function data_dir(): string {
		/**
		 * Filters the data directory the importer and the catalog read from.
		 *
		 * @param string $dir Absolute path without trailing slash.
		 */
		return self::normalize( (string) apply_filters( 'gadgetim_data_dir', self::repo_root() . '/data' ) );
	}

	/**
	 * Path of a file inside data/.
	 */
	public static function data_file( string $name ): string {
		return self::data_dir() . '/' . ltrim( $name, '/\\' );
	}

	/**
	 * Default source directory: GADGETIM_IMPORT_SOURCE, the saved admin choice, then
	 * <repo>/first-backup/extracted.
	 */
	public static function default_source_dir(): string {
		if ( defined( 'GADGETIM_IMPORT_SOURCE' ) && is_string( GADGETIM_IMPORT_SOURCE ) && '' !== GADGETIM_IMPORT_SOURCE ) {
			return self::normalize( GADGETIM_IMPORT_SOURCE );
		}
		$saved = get_option( self::OPTION_SOURCE, '' );
		if ( is_string( $saved ) && '' !== $saved && self::is_local_path( $saved ) && is_dir( $saved ) ) {
			return self::normalize( $saved );
		}
		return self::repo_root() . '/first-backup/extracted';
	}

	/**
	 * Where generated files (redirects.csv) go: data/ when writable, else uploads/gadgetim-import.
	 */
	public static function output_dir(): string {
		$data = self::data_dir();
		if ( is_dir( $data ) && wp_is_writable( $data ) ) {
			return $data;
		}
		$uploads = wp_upload_dir( null, false );
		$dir     = self::normalize( (string) ( $uploads['basedir'] ?? sys_get_temp_dir() ) ) . '/gadgetim-import';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Forward slashes, no trailing slash, "\" → "/" (Windows paths come in both spellings).
	 */
	public static function normalize( string $path ): string {
		$path = str_replace( '\\', '/', trim( $path ) );
		$path = (string) preg_replace( '#(?<!:)/{2,}#', '/', $path );
		return '/' === $path ? $path : rtrim( $path, '/' );
	}

	/**
	 * Sanitises a directory path typed by an administrator: no control characters, no stream
	 * wrapper (phar://, ftp://, compress.zlib://…; Windows drive letters are fine), resolved with
	 * realpath() and required to be an existing directory. Returns '' when the path is refused.
	 */
	public static function sanitize_dir( string $path ): string {
		$path = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', wp_unslash( $path ) ) );
		if ( '' === $path || ! self::is_local_path( $path ) ) {
			return '';
		}
		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) ) {
			return '';
		}
		return self::normalize( $real );
	}

	/**
	 * Whether a path is a plain filesystem path (not a URL or stream wrapper).
	 */
	public static function is_local_path( string $path ): bool {
		return 1 !== preg_match( '#^[a-z][a-z0-9+.-]*://#i', trim( $path ) )
			&& ! str_starts_with( strtolower( trim( $path ) ), 'data:' );
	}
}
