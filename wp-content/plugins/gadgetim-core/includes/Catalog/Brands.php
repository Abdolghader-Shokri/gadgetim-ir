<?php
/**
 * Brand helpers on WooCommerce's native `product_brand` taxonomy (architecture §6):
 * logo term meta, lookups, merge rules for the importer.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Support\Normalize;
use WC_Product;
use WP_Post;
use WP_Term;

/**
 * Read helpers for templates (brand chip, brand wall, brand archive hero) and the merge
 * rules in data/brand-merge.json shared with the importer.
 */
final class Brands {

	public const TAXONOMY = 'product_brand';

	public const META_LOGO = '_gtm_logo_id';

	public const META_OLD_ID = '_gtm_import_old_id';

	public const META_ALIASES = '_gtm_import_aliases';

	public const MERGE_FILE = 'brand-merge.json';

	/** @var array<string,array>|null */
	private static ?array $rules = null;

	/**
	 * Hooks (admin term fields only; everything else is a pure read API).
	 */
	public static function register(): void {
		if ( is_admin() ) {
			Admin\BrandFields::register();
		}
	}

	/**
	 * Whether WooCommerce registered the brands taxonomy (init or later).
	 */
	public static function exists(): bool {
		return taxonomy_exists( self::TAXONOMY );
	}

	/**
	 * Brand terms of a product (empty when none or the taxonomy is missing).
	 *
	 * @return WP_Term[]
	 */
	public static function for_product( WC_Product|WP_Post|int $product ): array {
		$id = ProductExtras::id( $product );
		if ( ! $id || ! self::exists() ) {
			return [];
		}
		$terms = get_the_terms( $id, self::TAXONOMY );
		return is_array( $terms ) ? array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof WP_Term ) ) : [];
	}

	/**
	 * The first brand of a product, or null.
	 */
	public static function primary( WC_Product|WP_Post|int $product ): ?WP_Term {
		$terms = self::for_product( $product );
		return $terms ? $terms[0] : null;
	}

	/**
	 * Display name (translated through gadgetim_term_name(), term object untouched).
	 */
	public static function name( WP_Term $term ): string {
		if ( function_exists( 'gadgetim_term_name' ) ) {
			return gadgetim_term_name( $term );
		}
		return (string) $term->name;
	}

	/**
	 * Archive URL ('' when unavailable).
	 */
	public static function url( WP_Term $term ): string {
		$link = get_term_link( $term );
		return is_string( $link ) ? $link : '';
	}

	/**
	 * Logo attachment ID (0 when none).
	 */
	public static function logo_id( WP_Term|int $term ): int {
		$term_id = $term instanceof WP_Term ? $term->term_id : absint( $term );
		return $term_id ? absint( get_term_meta( $term_id, self::META_LOGO, true ) ) : 0;
	}

	/**
	 * Logo image markup ('' when none). Alt defaults to the brand name.
	 *
	 * @param WP_Term|int $term  Term.
	 * @param string      $size  Image size.
	 * @param array       $attrs Extra attributes.
	 */
	public static function logo( WP_Term|int $term, string $size = 'thumbnail', array $attrs = [] ): string {
		$logo_id = self::logo_id( $term );
		if ( ! $logo_id ) {
			return '';
		}
		if ( ! isset( $attrs['alt'] ) ) {
			$object       = $term instanceof WP_Term ? $term : get_term( $term, self::TAXONOMY );
			$attrs['alt'] = $object instanceof WP_Term ? self::name( $object ) : '';
		}
		$attrs['loading']  = $attrs['loading'] ?? 'lazy';
		$attrs['decoding'] = $attrs['decoding'] ?? 'async';
		return (string) wp_get_attachment_image( $logo_id, $size, false, $attrs );
	}

	/**
	 * Logo URL for a size ('' when none).
	 */
	public static function logo_url( WP_Term|int $term, string $size = 'thumbnail' ): string {
		$logo_id = self::logo_id( $term );
		if ( ! $logo_id ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $logo_id, $size );
		return is_array( $src ) && ! empty( $src[0] ) ? (string) $src[0] : '';
	}

	/**
	 * Stores (or clears, with 0) the logo of a brand.
	 */
	public static function set_logo( int $term_id, int $attachment_id ): bool {
		$term_id       = absint( $term_id );
		$attachment_id = absint( $attachment_id );
		if ( ! $term_id ) {
			return false;
		}
		if ( ! $attachment_id ) {
			return (bool) delete_term_meta( $term_id, self::META_LOGO );
		}
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}
		return false !== update_term_meta( $term_id, self::META_LOGO, $attachment_id );
	}

	/**
	 * Brand terms, non-empty ones by default, ordered by name.
	 *
	 * @param array $args Extra get_terms() arguments.
	 * @return WP_Term[]
	 */
	public static function all( array $args = [] ): array {
		if ( ! self::exists() ) {
			return [];
		}
		$terms = get_terms(
			array_merge(
				[
					'taxonomy'   => self::TAXONOMY,
					'hide_empty' => true,
					'orderby'    => 'name',
					'order'      => 'ASC',
				],
				$args
			)
		);
		return is_array( $terms ) ? array_values( array_filter( $terms, static fn( $t ): bool => $t instanceof WP_Term ) ) : [];
	}

	/**
	 * Brands with at least $min products, most-stocked first (brand wall, §6.1 condition "≥ 6 brands").
	 *
	 * @return WP_Term[]
	 */
	public static function with_products( int $min = 1 ): array {
		$terms = self::all(
			[
				'orderby' => 'count',
				'order'   => 'DESC',
			]
		);
		return array_values( array_filter( $terms, static fn( WP_Term $t ): bool => (int) $t->count >= max( 1, $min ) ) );
	}

	/**
	 * Finds a brand by any spelling: exact slug, exact name, canonical name from the merge
	 * rules, then a normalised (case/Arabic-letter insensitive) name or alias match.
	 */
	public static function find( string $name ): ?WP_Term {
		$name = trim( html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $name || ! self::exists() ) {
			return null;
		}
		$by_slug = get_term_by( 'slug', sanitize_title( $name ), self::TAXONOMY );
		if ( $by_slug instanceof WP_Term ) {
			return $by_slug;
		}
		$by_name = get_term_by( 'name', $name, self::TAXONOMY );
		if ( $by_name instanceof WP_Term ) {
			return $by_name;
		}
		$canonical = self::canonical( $name );
		if ( $canonical ) {
			$term = get_term_by( 'slug', $canonical['slug'], self::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
			$term = get_term_by( 'name', $canonical['name'], self::TAXONOMY );
			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}
		$key = self::key( $name );
		foreach ( self::all( [ 'hide_empty' => false ] ) as $term ) {
			if ( self::key( (string) $term->name ) === $key ) {
				return $term;
			}
			$aliases = get_term_meta( $term->term_id, self::META_ALIASES, true );
			if ( is_array( $aliases ) && in_array( $key, array_map( [ self::class, 'key' ], array_map( 'strval', $aliases ) ), true ) ) {
				return $term;
			}
		}
		return null;
	}

	/**
	 * Canonical brand (name, slug, aliases, old_ids) for a raw name from data/brand-merge.json,
	 * or null when no rule mentions it.
	 *
	 * @return array{name:string,slug:string,aliases:string[],old_ids:int[]}|null
	 */
	public static function canonical( string $raw ): ?array {
		$key = self::key( $raw );
		if ( '' === $key ) {
			return null;
		}
		foreach ( self::merge_rules() as $rule ) {
			if ( self::key( $rule['name'] ) === $key || $rule['slug'] === sanitize_title( $raw ) ) {
				return $rule;
			}
			foreach ( $rule['aliases'] as $alias ) {
				if ( self::key( $alias ) === $key ) {
					return $rule;
				}
			}
		}
		return null;
	}

	/**
	 * Canonical display name for a raw brand name (the raw name, entity-decoded, when no rule applies).
	 */
	public static function canonical_name( string $raw ): string {
		$rule = self::canonical( $raw );
		return $rule ? $rule['name'] : trim( html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Merge rules from data/brand-merge.json, normalised: [ slug => { name, slug, aliases[], old_ids[] } ].
	 * Missing file → []. Filter: gadgetim_brand_merge_rules.
	 *
	 * @return array<string,array{name:string,slug:string,aliases:string[],old_ids:int[]}>
	 */
	public static function merge_rules(): array {
		if ( null !== self::$rules ) {
			return self::$rules;
		}
		$rules = [];
		$file  = self::merge_file();
		if ( '' !== $file && is_readable( $file ) ) {
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$list = is_array( $data ) ? ( $data['brands'] ?? $data ) : [];
			foreach ( (array) $list as $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
					continue;
				}
				$name = trim( (string) $entry['name'] );
				$slug = sanitize_title( (string) ( $entry['slug'] ?? $name ) );
				if ( '' === $slug ) {
					continue;
				}
				$rules[ $slug ] = [
					'name'    => $name,
					'slug'    => $slug,
					'aliases' => array_values( array_unique( array_filter( array_map( 'strval', (array) ( $entry['aliases'] ?? [] ) ), 'strlen' ) ) ),
					'old_ids' => array_values( array_filter( array_map( 'intval', (array) ( $entry['old_ids'] ?? [] ) ) ) ),
				];
			}
		}

		/**
		 * Filters the brand merge rules (importer and Brands::find()).
		 *
		 * @param array  $rules [ slug => { name, slug, aliases[], old_ids[] } ].
		 * @param string $file  Source file path ('' when none).
		 */
		self::$rules = (array) apply_filters( 'gadgetim_brand_merge_rules', $rules, $file );
		return self::$rules;
	}

	/**
	 * Forgets the cached rules (tests, after editing the file).
	 */
	public static function reset_rules(): void {
		self::$rules = null;
	}

	/**
	 * Path of data/brand-merge.json ('' when the data directory cannot be located).
	 */
	public static function merge_file(): string {
		$dir = '';
		if ( class_exists( 'Gadgetim\Core\Import\Paths' ) ) {
			$dir = \Gadgetim\Core\Import\Paths::data_dir();
		} else {
			$real = realpath( GADGETIM_CORE_DIR );
			$dir  = dirname( str_replace( '\\', '/', false === $real ? GADGETIM_CORE_DIR : $real ), 3 ) . '/data';
		}

		/**
		 * Filters the brand merge file path.
		 *
		 * @param string $file Absolute path.
		 */
		return (string) apply_filters( 'gadgetim_brand_merge_file', '' !== $dir ? rtrim( $dir, '/\\' ) . '/' . self::MERGE_FILE : '' );
	}

	/**
	 * Comparison key for brand names: entity-decoded, normalised (Normalize::text), spaces and
	 * punctuation removed ("Green Lion" ≡ "green-lion", "E&amp;Z" ≡ "E&Z").
	 */
	public static function key( string $name ): string {
		$name = html_entity_decode( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$name = Normalize::text( $name );
		return (string) preg_replace( '/[\s\-_.,&+\'"()\/]+/u', '', $name );
	}
}
