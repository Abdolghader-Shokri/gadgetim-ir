<?php
/**
 * Redirect rows for old URLs that do not map 1:1 (architecture §6.1 step 7): dropped pages
 * and posts, unused tags, renamed brand slugs, remapped categories. Keys are normalised
 * like Seo\Redirects does (Support\Normalize::path).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Support\Normalize;
use Gadgetim\Core\Support\Options;

/**
 * CSV columns: old_path,new_path,status,note. Status 410 rows carry an empty new_path.
 */
final class Redirects {

	public const FILE = 'redirects.csv';

	public const OPTION = 'gadgetim_redirects';

	public const HEADER = [ 'old_path', 'new_path', 'status', 'note' ];

	/** Dropped pages and where they go. */
	private const PAGE_TARGETS = [
		'wishlist'       => [ '/shop/', 301, 'wishlist page not migrated' ],
		'مقایسه-محصولات' => [ '/shop/', 301, 'compare page not migrated' ],
		'blog'           => [ '/', 301, 'blog index not migrated (client decision pending)' ],
		'صفحه-اصلی-2'    => [ '/', 301, 'old builder home page' ],
		'order-tracking' => [ '/my-account/orders/', 301, 'order tracking moved into the account' ],
	];

	private const WC_PAGES = [ 'shop', 'cart', 'checkout', 'my-account' ];

	private Importer $importer;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	/**
	 * All redirect rows derived from the export and the mapping files.
	 *
	 * @return array<int,array{from:string,to:string,status:int,note:string}>
	 */
	public function rows(): array {
		$rows = [];
		$add  = static function ( string $from, string $to, int $status, string $note ) use ( &$rows ): void {
			$from = Normalize::path( $from );
			if ( '/' === $from || isset( $rows[ $from ] ) ) {
				return;
			}
			$rows[ $from ] = [
				'from'   => $from,
				'to'     => 410 === $status ? '' : self::target( $to ),
				'status' => $status,
				'note'   => $note,
			];
		};

		$source = $this->importer->source();
		$kept   = array_map( static fn( array $t ): string => $t['slug'], Pages::MAP );

		foreach ( $source->pages() as $page ) {
			$slug = rawurldecode( (string) ( $page['slug'] ?? '' ) );
			$type = (string) ( $page['type'] ?? 'page' );
			if ( '' === $slug ) {
				continue;
			}
			if ( 'post' === $type ) {
				$add( '/' . $slug . '/', '', 410, 'blog post not migrated (client decision pending)' );
				continue;
			}
			if ( in_array( $slug, $kept, true ) || in_array( $slug, self::WC_PAGES, true ) ) {
				continue;
			}
			if ( isset( self::PAGE_TARGETS[ $slug ] ) ) {
				[ $to, $status, $note ] = self::PAGE_TARGETS[ $slug ];
				$add( '/' . $slug . '/', $to, $status, $note );
				continue;
			}
			$add( '/' . $slug . '/', '', 410, 'page not migrated' );
		}

		$map   = $this->importer->category_map();
		$by_id = $source->categories_by_id();
		foreach ( $by_id as $old_id => $category ) {
			$old_path = (string) ( $category['path'] ?? $category['slug'] ?? '' );
			if ( '' === $old_path ) {
				continue;
			}
			$entry = $map->entry( $old_id, $category );
			switch ( $entry['action'] ) {
				case 'skip':
					$add( '/product-category/' . $old_path . '/', '', 410, 'category dropped (mapping)' );
					break;
				case 'default':
					$add( '/product-category/' . $old_path . '/', '/shop/', 301, 'default category' );
					break;
				case 'merge':
					$target = $by_id[ $entry['merge_into'] ] ?? null;
					if ( $target ) {
						$add( '/product-category/' . $old_path . '/', '/product-category/' . $this->new_category_path( (int) $target['id'], $by_id ) . '/', 301, 'category merged' );
					}
					break;
				default:
					$new_path = $this->new_category_path( $old_id, $by_id );
					if ( $new_path !== $old_path ) {
						$add( '/product-category/' . $old_path . '/', '/product-category/' . $new_path . '/', 301, 'category renamed' );
					}
			}
		}

		$groups = $this->importer->terms()->brand_groups();
		foreach ( $source->brands() as $brand ) {
			$old_slug = (string) ( $brand['slug'] ?? '' );
			$raw      = html_entity_decode( (string) ( $brand['name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$new_slug = $old_slug;
			foreach ( $groups as $group ) {
				if ( in_array( (int) ( $brand['id'] ?? 0 ), $group['old_ids'], true ) || in_array( $raw, $group['aliases'], true ) ) {
					$new_slug = (string) $group['slug'];
					break;
				}
			}
			if ( '' !== $old_slug && $old_slug !== $new_slug ) {
				$add( '/brand/' . $old_slug . '/', '/brand/' . $new_slug . '/', 301, 'brand merged or renamed' );
			}
		}

		$used = [];
		foreach ( $this->importer->terms()->used_tags() as $tag ) {
			$used[ (string) ( $tag['slug'] ?? '' ) ] = true;
		}
		foreach ( $source->tags() as $tag ) {
			$slug = (string) ( $tag['slug'] ?? '' );
			if ( '' !== $slug && ! isset( $used[ $slug ] ) ) {
				$add( '/product-tag/' . $slug . '/', '', 410, 'unused tag dropped' );
			}
		}

		/**
		 * Filters the redirect rows the importer writes to data/redirects.csv.
		 *
		 * @param array    $rows   [ from => { from, to, status, note } ].
		 * @param Importer $importer Importer.
		 */
		$rows = (array) apply_filters( 'gadgetim_import_redirect_rows', $rows, $this->importer );
		ksort( $rows );
		return array_values( $rows );
	}

	/**
	 * Writes the CSV (UTF-8, LF). Returns the number of rows written or false.
	 */
	public static function write_csv( array $rows, string $path ): int|false {
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$handle = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		fputcsv( $handle, self::HEADER, ',', '"', '\\', "\n" );
		$count = 0;
		foreach ( $rows as $row ) {
			fputcsv( $handle, [ (string) $row['from'], (string) $row['to'], (string) (int) $row['status'], (string) ( $row['note'] ?? '' ) ], ',', '"', '\\', "\n" );
			++$count;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $count;
	}

	/**
	 * Reads a redirects CSV into rows (header optional).
	 *
	 * @return array<int,array{from:string,to:string,status:int,note:string}>
	 */
	public static function parse_csv( string $path ): array {
		if ( ! is_readable( $path ) ) {
			return [];
		}
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return [];
		}
		$rows = [];
		while ( ( $line = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
			if ( ! is_array( $line ) || count( $line ) < 2 ) {
				continue;
			}
			$from = trim( (string) ( $line[0] ?? '' ) );
			if ( '' === $from || 'old_path' === $from || str_starts_with( $from, '#' ) ) {
				continue;
			}
			$status = (int) ( $line[2] ?? 301 );
			if ( ! in_array( $status, [ 301, 302, 307, 308, 410 ], true ) ) {
				$status = 301;
			}
			$rows[] = [
				'from'   => Normalize::path( $from ),
				'to'     => 410 === $status ? '' : self::target( (string) ( $line[1] ?? '' ) ),
				'status' => $status,
				'note'   => (string) ( $line[3] ?? '' ),
			];
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	/**
	 * Writes rows into the gadgetim_redirects option in the Appendix B shape
	 * { "<normalised old path>": [ "/new/", 301, user, time ] }. Returns the number applied.
	 */
	public static function apply( array $rows ): int {
		$option  = Options::redirects();
		$user    = get_current_user_id();
		$now     = time();
		$applied = 0;
		foreach ( $rows as $row ) {
			$from = Normalize::path( (string) ( $row['from'] ?? '' ) );
			if ( '/' === $from ) {
				continue;
			}
			$status = (int) ( $row['status'] ?? 301 );
			$to     = 410 === $status ? '' : self::target( (string) ( $row['to'] ?? '' ) );
			if ( 410 !== $status && '' === $to ) {
				continue;
			}
			$option[ $from ] = [ $to, $status, $user, $now ];
			++$applied;
		}
		update_option( self::OPTION, $option, false );
		return $applied;
	}

	/**
	 * Redirect targets are same-host paths only (§5.10): absolute old-site URLs are reduced to
	 * their path, anything else that is not a path is dropped, and so is anything a browser could
	 * read as another host (protocol-relative, backslashes, encoded control characters). The
	 * consumer must still redirect with wp_safe_redirect().
	 */
	public static function target( string $to ): string {
		$to = trim( $to );
		if ( '' === $to ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $to ) ) {
			$host = strtolower( (string) wp_parse_url( $to, PHP_URL_HOST ) );
			if ( ! in_array( $host, [ 'gadgetim.ir', 'www.gadgetim.ir', 'gadgetim.local' ], true ) ) {
				return '';
			}
			$path = (string) wp_parse_url( $to, PHP_URL_PATH );
			$to   = '' === $path ? '/' : $path;
		}
		// Decode first, then strip every control character, so %0d%0a cannot come back as CR/LF.
		$to = rawurldecode( $to );
		$to = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $to ) );
		if ( '' === $to ) {
			return '';
		}
		// Backslashes and leading double slashes make protocol-relative targets in browsers
		// ('//evil.com', '/\evil.com', '/%2F%2Fevil.com').
		if ( str_contains( $to, '\\' ) || preg_match( '#^[/\\\\]{2}#', $to ) || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $to ) ) {
			return '';
		}
		if ( '/' !== $to[0] ) {
			$to = '/' . $to;
		}
		if ( preg_match( '#^/{2}#', $to ) ) {
			return '';
		}
		if ( ! preg_match( '/\.[a-z0-9]{1,8}$/i', $to ) && ! str_contains( $to, '?' ) ) {
			$to = trailingslashit( $to );
		}
		return $to;
	}

	/**
	 * New hierarchical path of a category after the mapping (slugs may be renamed, parents reassigned).
	 */
	private function new_category_path( int $old_id, array $by_id ): string {
		$map   = $this->importer->category_map();
		$parts = [];
		$seen  = [];
		while ( $old_id > 0 && isset( $by_id[ $old_id ] ) && ! isset( $seen[ $old_id ] ) ) {
			$seen[ $old_id ] = true;
			$entry           = $map->entry( $old_id, $by_id[ $old_id ] );
			if ( 'merge' === $entry['action'] && $entry['merge_into'] > 0 && isset( $by_id[ $entry['merge_into'] ] ) ) {
				$old_id = $entry['merge_into'];
				continue;
			}
			if ( 'skip' === $entry['action'] || 'default' === $entry['action'] ) {
				break;
			}
			array_unshift( $parts, $entry['slug'] );
			$old_id = $entry['parent'];
		}
		return implode( '/', $parts );
	}
}
