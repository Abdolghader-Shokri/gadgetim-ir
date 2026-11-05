<?php
/**
 * The extracted old-site export: products.json, categories.json, brands.json,
 * product-tags.json, pages-posts.json, menus.json, old-urls.txt, images/YYYY/MM/*.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use WP_Error;

/**
 * Lazy, validated access to the export files. Nothing is written here.
 */
final class Source {

	public const FILES = [
		'products'   => 'products.json',
		'categories' => 'categories.json',
		'brands'     => 'brands.json',
		'tags'       => 'product-tags.json',
		'pages'      => 'pages-posts.json',
		'menus'      => 'menus.json',
	];

	public const OLD_URLS = 'old-urls.txt';

	private string $dir;

	/** @var array<string,array> */
	private array $cache = [];

	/** @var array<int,array>|null */
	private ?array $page_contents = null;

	public function __construct( string $dir ) {
		$this->dir = Paths::normalize( $dir );
	}

	/**
	 * Source directory (normalised).
	 */
	public function dir(): string {
		return $this->dir;
	}

	/**
	 * Absolute path of a file inside the export.
	 */
	public function path( string $relative ): string {
		return $this->dir . '/' . ltrim( str_replace( '\\', '/', $relative ), '/' );
	}

	/**
	 * Checks that the directory exists and products.json parses.
	 */
	public function validate(): bool|WP_Error {
		if ( '' !== $this->dir && ! Paths::is_local_path( $this->dir ) ) {
			return new WP_Error( 'gadgetim_import_bad_dir', __( 'The source must be a local directory; URLs and stream wrappers are not accepted.', 'gadgetim-core' ) );
		}
		if ( '' === $this->dir || ! is_dir( $this->dir ) ) {
			/* translators: %s: directory path */
			return new WP_Error( 'gadgetim_import_no_dir', sprintf( __( 'Source directory not found: %s', 'gadgetim-core' ), $this->dir ) );
		}
		foreach ( [ 'products', 'categories', 'brands', 'tags' ] as $key ) {
			$file = $this->path( self::FILES[ $key ] );
			if ( ! is_readable( $file ) ) {
				/* translators: %s: file path */
				return new WP_Error( 'gadgetim_import_missing_file', sprintf( __( 'Export file missing: %s', 'gadgetim-core' ), $file ) );
			}
		}
		$products = $this->json( self::FILES['products'] );
		if ( null === $products ) {
			return new WP_Error( 'gadgetim_import_bad_json', __( 'products.json is not valid JSON.', 'gadgetim-core' ) );
		}
		if ( ! is_dir( $this->path( 'images' ) ) ) {
			return new WP_Error( 'gadgetim_import_no_images', __( 'The images/ directory is missing from the export.', 'gadgetim-core' ) );
		}
		return true;
	}

	/**
	 * Products in export order.
	 */
	public function products(): array {
		return $this->list( 'products' );
	}

	/**
	 * One product by old ID, or null.
	 */
	public function product( int $old_id ): ?array {
		foreach ( $this->products() as $product ) {
			if ( (int) ( $product['id'] ?? 0 ) === $old_id ) {
				return $product;
			}
		}
		return null;
	}

	/**
	 * Categories (unsorted, as exported).
	 */
	public function categories(): array {
		return $this->list( 'categories' );
	}

	/**
	 * Categories indexed by old ID.
	 *
	 * @return array<int,array>
	 */
	public function categories_by_id(): array {
		$out = [];
		foreach ( $this->categories() as $category ) {
			$out[ (int) ( $category['id'] ?? 0 ) ] = $category;
		}
		unset( $out[0] );
		return $out;
	}

	/**
	 * Categories indexed by their hierarchical path ("لوازم-جانبی-موبایل/گجت-های-موبایل").
	 *
	 * @return array<string,array>
	 */
	public function categories_by_path(): array {
		$out = [];
		foreach ( $this->categories() as $category ) {
			$path = (string) ( $category['path'] ?? $category['slug'] ?? '' );
			if ( '' !== $path ) {
				$out[ $path ] = $category;
			}
		}
		return $out;
	}

	/**
	 * Brands (both old taxonomies, as exported).
	 */
	public function brands(): array {
		return $this->list( 'brands' );
	}

	/**
	 * Product tags.
	 */
	public function tags(): array {
		return $this->list( 'tags' );
	}

	/**
	 * Pages and posts (title, slug, old_url; content only when the export carries it).
	 */
	public function pages(): array {
		return $this->list( 'pages' );
	}

	/**
	 * Menus.
	 */
	public function menus(): array {
		return $this->list( 'menus' );
	}

	/**
	 * Old URLs (one per line, de-duplicated, order kept).
	 *
	 * @return string[]
	 */
	public function old_urls(): array {
		$file = $this->path( self::OLD_URLS );
		if ( ! is_readable( $file ) ) {
			return [];
		}
		$lines = preg_split( '/\R+/', (string) file_get_contents( $file ) ) ?: []; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$urls  = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' !== $line && ! isset( $urls[ $line ] ) ) {
				$urls[ $line ] = true;
			}
		}
		return array_keys( $urls );
	}

	/**
	 * Raw content of a page: the export entry's own `content`, else <dir>/pages/<slug>.html,
	 * else data/pages.json (extracted from the database dump). Null when none is available.
	 *
	 * @param array $page Export entry (id, slug, …).
	 */
	public function page_content( array $page ): ?string {
		if ( isset( $page['content'] ) && is_string( $page['content'] ) ) {
			return $page['content'];
		}
		$slug = (string) ( $page['slug'] ?? '' );
		if ( '' !== $slug ) {
			$file = $this->path( 'pages/' . $slug . '.html' );
			if ( is_readable( $file ) ) {
				return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		$contents = $this->data_page_contents();
		$old_id   = (int) ( $page['id'] ?? 0 );
		if ( $old_id && isset( $contents[ $old_id ] ) ) {
			return (string) ( $contents[ $old_id ]['content'] ?? '' );
		}
		foreach ( $contents as $entry ) {
			if ( '' !== $slug && ( $entry['slug'] ?? '' ) === $slug ) {
				return (string) ( $entry['content'] ?? '' );
			}
		}
		return null;
	}

	/**
	 * Extra page attributes from data/pages.json (date) for an export entry.
	 */
	public function page_extra( array $page ): array {
		$contents = $this->data_page_contents();
		$old_id   = (int) ( $page['id'] ?? 0 );
		return $old_id && isset( $contents[ $old_id ] ) ? $contents[ $old_id ] : [];
	}

	/**
	 * Whether a relative image path exists in the export.
	 */
	public function has_file( string $relative ): bool {
		return is_file( $this->path( $relative ) );
	}

	/**
	 * data/pages.json indexed by old ID.
	 *
	 * @return array<int,array>
	 */
	private function data_page_contents(): array {
		if ( null !== $this->page_contents ) {
			return $this->page_contents;
		}
		$this->page_contents = [];
		$file                = Paths::data_file( 'pages.json' );
		if ( is_readable( $file ) ) {
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$list = is_array( $data ) ? ( $data['pages'] ?? $data ) : [];
			foreach ( (array) $list as $entry ) {
				if ( is_array( $entry ) && isset( $entry['id'] ) ) {
					$this->page_contents[ (int) $entry['id'] ] = $entry;
				}
			}
		}
		return $this->page_contents;
	}

	/**
	 * A list file as an array (empty when missing/invalid).
	 */
	private function list( string $key ): array {
		$data = $this->json( self::FILES[ $key ] );
		return is_array( $data ) ? array_values( array_filter( $data, 'is_array' ) ) : [];
	}

	/**
	 * Decoded JSON file (cached), null when unreadable or invalid.
	 */
	private function json( string $file ): ?array {
		if ( array_key_exists( $file, $this->cache ) ) {
			return $this->cache[ $file ];
		}
		$path = $this->path( $file );
		$data = null;
		if ( is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data    = is_array( $decoded ) ? $decoded : null;
		}
		$this->cache[ $file ] = $data;
		return $data;
	}
}
