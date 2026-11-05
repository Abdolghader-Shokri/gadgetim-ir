<?php
/**
 * Term import: categories through data/category-map.json, brands through the merge rules,
 * only the tags that products use (architecture §6.1 step 1). Idempotent via _gtm_import_old_id.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

use Gadgetim\Core\Catalog\Brands;
use Gadgetim\Core\Support\Normalize;
use WP_Error;
use WP_Term;

/**
 * Also the resolver the product step uses: missing terms are created on demand, so
 * `--products` alone still yields a fully categorised catalogue.
 */
final class Terms {

	public const META_OLD_ID = '_gtm_import_old_id';

	public const CAT = 'product_cat';
	public const TAG = 'product_tag';

	private Importer $importer;

	/** @var array<string,array<string,int>> taxonomy → key → term id (negative ids in dry runs). */
	private array $cache = [
		self::CAT       => [],
		Brands::TAXONOMY => [],
		self::TAG       => [],
	];

	/** @var array<string,array>|null */
	private ?array $groups = null;

	/** @var array<string,string>|null alias key → group slug */
	private ?array $alias_index = null;

	/** @var array<int,true> */
	private array $visiting = [];

	private int $dry_counter = 0;

	/** True while terms are resolved on behalf of a product (no counters, quieter log). */
	private bool $quiet = false;

	public function __construct( Importer $importer ) {
		$this->importer = $importer;
	}

	// -------------------------------------------------------------------------------------
	// Categories
	// -------------------------------------------------------------------------------------

	/**
	 * Categories in parent-first order (depth, then export order).
	 */
	public function ordered_categories(): array {
		$categories = $this->importer->source()->categories();
		usort(
			$categories,
			static function ( array $a, array $b ): int {
				$da = substr_count( (string) ( $a['path'] ?? '' ), '/' );
				$db = substr_count( (string) ( $b['path'] ?? '' ), '/' );
				return [ $da, (int) ( $a['id'] ?? 0 ) ] <=> [ $db, (int) ( $b['id'] ?? 0 ) ];
			}
		);
		return $categories;
	}

	/**
	 * Imports one exported category (and its parents first). Returns the term id, 0 when skipped.
	 */
	public function import_category( array $category ): int {
		$old_id = (int) ( $category['id'] ?? 0 );
		if ( $old_id <= 0 ) {
			return 0;
		}
		if ( isset( $this->cache[ self::CAT ][ 'old:' . $old_id ] ) ) {
			return $this->cache[ self::CAT ][ 'old:' . $old_id ];
		}
		if ( isset( $this->visiting[ $old_id ] ) ) {
			$this->log( Log::WARNING, sprintf( 'Category mapping loop at old id %d; attached to the root.', $old_id ) );
			return 0;
		}
		$this->visiting[ $old_id ] = true;

		try {
			$map   = $this->importer->category_map()->entry( $old_id, $category );
			$by_id = $this->importer->source()->categories_by_id();
			$label = (string) ( $category['name'] ?? $map['name'] );

			switch ( $map['action'] ) {
				case 'skip':
					$this->remember( self::CAT, 'old:' . $old_id, 0 );
					++$this->importer->job()->counts['skipped'];
					/* translators: %s: category name */
					$this->log( Log::INFO, sprintf( __( 'Category "%s" skipped (mapping).', 'gadgetim-core' ), $label ) );
					return 0;

				case 'default':
					$default = (int) get_option( 'default_product_cat', 0 );
					$this->remember( self::CAT, 'old:' . $old_id, $default );
					++$this->importer->job()->counts['skipped'];
					/* translators: %s: category name */
					$this->log( Log::INFO, sprintf( __( 'Category "%s" mapped to the default WooCommerce category.', 'gadgetim-core' ), $label ) );
					return $default;

				case 'merge':
					$target = $by_id[ $map['merge_into'] ] ?? null;
					if ( null === $target ) {
						$this->log( Log::WARNING, sprintf( 'Category "%s": merge target %d not found; importing it as is.', $label, $map['merge_into'] ) );
						break;
					}
					$term_id = $this->import_category( $target );
					$this->remember( self::CAT, 'old:' . $old_id, $term_id );
					/* translators: 1: category name, 2: target category name */
					$this->log( Log::INFO, sprintf( __( 'Category "%1$s" merged into "%2$s".', 'gadgetim-core' ), $label, (string) ( $target['name'] ?? '' ) ) );
					return $term_id;
			}

			$parent_id = 0;
			if ( $map['parent'] > 0 && isset( $by_id[ $map['parent'] ] ) ) {
				$parent_id = $this->import_category( $by_id[ $map['parent'] ] );
				$default   = (int) get_option( 'default_product_cat', 0 );
				if ( $parent_id === $default || $parent_id < 0 ) {
					$parent_id = $parent_id < 0 ? $parent_id : 0;
				}
			}

			$slug = (string) $map['slug'];
			$name = '' !== $map['name'] ? $map['name'] : $label;
			$args = [
				'slug'        => $slug,
				'parent'      => max( 0, $parent_id ),
				'description' => self::clean_description( (string) ( $category['description'] ?? '' ) ),
			];

			$existing = $this->find_by_old_id( self::CAT, $old_id );
			if ( ! $existing ) {
				$existing = $this->find_by_slug( self::CAT, $slug );
			}
			if ( $existing > 0 ) {
				$this->update_term( $existing, self::CAT, $name, $args, [ $old_id ] );
				$this->remember( self::CAT, 'old:' . $old_id, $existing );
				return $existing;
			}

			$term_id = $this->create_term( self::CAT, $name, $args, [ $old_id ] );
			$this->remember( self::CAT, 'old:' . $old_id, $term_id );
			return $term_id;
		} finally {
			unset( $this->visiting[ $old_id ] );
		}
	}

	/**
	 * Term id for an exported category path ("a/b"), creating the category on demand.
	 */
	public function category_id_for_path( string $path ): int {
		$by_path = $this->importer->source()->categories_by_path();
		if ( isset( $by_path[ $path ] ) ) {
			return $this->import_category( $by_path[ $path ] );
		}
		$slug = (string) substr( $path, (int) strrpos( '/' . $path, '/' ) );
		foreach ( $by_path as $category ) {
			if ( (string) ( $category['slug'] ?? '' ) === $slug ) {
				return $this->import_category( $category );
			}
		}
		return 0;
	}

	/**
	 * Category ids for a product's `categories` list (ids ≥ 1 only in real runs).
	 *
	 * @return int[]
	 */
	public function category_ids_for_product( array $product ): array {
		$ids = [];
		$this->quiet = true;
		try {
			foreach ( (array) ( $product['categories'] ?? [] ) as $category ) {
				if ( ! is_array( $category ) ) {
					continue;
				}
				$path = (string) ( $category['path'] ?? $category['slug'] ?? '' );
				if ( '' === $path ) {
					continue;
				}
				$id = $this->category_id_for_path( $path );
				if ( 0 !== $id ) {
					$ids[ $id ] = true;
				}
			}
		} finally {
			$this->quiet = false;
		}
		return $this->clean_ids( array_keys( $ids ) );
	}

	// -------------------------------------------------------------------------------------
	// Brands
	// -------------------------------------------------------------------------------------

	/**
	 * Canonical brand groups from brands.json + data/brand-merge.json:
	 * [ slug => { name, slug, aliases[], old_ids[], description, count } ].
	 *
	 * @return array<string,array>
	 */
	public function brand_groups(): array {
		if ( null !== $this->groups ) {
			return $this->groups;
		}
		$groups = [];
		foreach ( $this->importer->source()->brands() as $brand ) {
			$raw = trim( html_entity_decode( (string) ( $brand['name'] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' === $raw ) {
				continue;
			}
			$rule = Brands::canonical( $raw );
			if ( $rule ) {
				$name = $rule['name'];
				$slug = $rule['slug'];
			} else {
				$name = $raw;
				$slug = (string) ( $brand['slug'] ?? '' );
				if ( ! preg_match( '/^[a-z0-9-]+$/', $slug ) && preg_match( '/[A-Za-z]/', $name ) ) {
					$slug = sanitize_title( $name );
				}
				if ( '' === $slug ) {
					$slug = sanitize_title( $name );
				}
			}
			$groups[ $slug ] ??= [
				'name'        => $name,
				'slug'        => $slug,
				'aliases'     => [],
				'old_ids'     => [],
				'description' => '',
				'count'       => 0,
			];
			$group = &$groups[ $slug ];
			if ( ! in_array( $raw, $group['aliases'], true ) ) {
				$group['aliases'][] = $raw;
			}
			$old_id = (int) ( $brand['id'] ?? 0 );
			if ( $old_id > 0 && ! in_array( $old_id, $group['old_ids'], true ) ) {
				$group['old_ids'][] = $old_id;
			}
			$description = trim( (string) ( $brand['description'] ?? '' ) );
			if ( mb_strlen( $description ) > mb_strlen( $group['description'] ) ) {
				$group['description'] = $description;
			}
			$group['count'] += (int) ( $brand['count'] ?? 0 );
			unset( $group );
		}
		// Aliases declared in the merge file but absent from brands.json still resolve.
		foreach ( Brands::merge_rules() as $slug => $rule ) {
			if ( isset( $groups[ $slug ] ) ) {
				foreach ( $rule['aliases'] as $alias ) {
					if ( ! in_array( $alias, $groups[ $slug ]['aliases'], true ) ) {
						$groups[ $slug ]['aliases'][] = $alias;
					}
				}
			}
		}
		ksort( $groups );
		$this->groups = $groups;
		return $groups;
	}

	/**
	 * Imports one brand group. Returns the term id.
	 */
	public function import_brand_group( array $group ): int {
		$slug = (string) $group['slug'];
		if ( isset( $this->cache[ Brands::TAXONOMY ][ 'slug:' . $slug ] ) ) {
			return $this->cache[ Brands::TAXONOMY ][ 'slug:' . $slug ];
		}
		$args = [
			'slug'        => $slug,
			'description' => self::clean_description( (string) ( $group['description'] ?? '' ) ),
		];
		$old_ids = array_map( 'intval', (array) ( $group['old_ids'] ?? [] ) );
		$aliases = array_map( 'strval', (array) ( $group['aliases'] ?? [] ) );

		$existing = 0;
		foreach ( $old_ids as $old_id ) {
			$existing = $this->find_by_old_id( Brands::TAXONOMY, $old_id );
			if ( $existing ) {
				break;
			}
		}
		if ( ! $existing ) {
			$existing = $this->find_by_slug( Brands::TAXONOMY, $slug );
		}
		if ( ! $existing ) {
			$found    = get_term_by( 'name', (string) $group['name'], Brands::TAXONOMY );
			$existing = $found instanceof WP_Term ? (int) $found->term_id : 0;
		}
		if ( $existing > 0 ) {
			$this->update_term( $existing, Brands::TAXONOMY, (string) $group['name'], $args, $old_ids, $aliases );
			$this->remember( Brands::TAXONOMY, 'slug:' . $slug, $existing );
			return $existing;
		}
		$term_id = $this->create_term( Brands::TAXONOMY, (string) $group['name'], $args, $old_ids, $aliases );
		$this->remember( Brands::TAXONOMY, 'slug:' . $slug, $term_id );
		return $term_id;
	}

	/**
	 * Brand term ids for the raw names on a product (groups created on demand).
	 *
	 * @param string[] $names Raw brand names as exported.
	 * @return int[]
	 */
	public function brand_ids_for_names( array $names ): array {
		$ids         = [];
		$this->quiet = true;
		try {
			foreach ( $names as $raw ) {
				$raw = trim( html_entity_decode( (string) $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( '' === $raw ) {
					continue;
				}
				$group = $this->group_for_name( $raw );
				if ( null === $group ) {
					$slug  = sanitize_title( $raw );
					$group = [
						'name'        => $raw,
						'slug'        => '' !== $slug ? $slug : md5( $raw ),
						'aliases'     => [ $raw ],
						'old_ids'     => [],
						'description' => '',
						'count'       => 0,
					];
					$this->groups[ $group['slug'] ] = $group;
					$this->alias_index              = null;
					/* translators: %s: brand name */
					$this->log( Log::WARNING, sprintf( __( 'Brand "%s" is not in brands.json; created from the product.', 'gadgetim-core' ), $raw ) );
				}
				$id = $this->import_brand_group( $group );
				if ( 0 !== $id ) {
					$ids[ $id ] = true;
				}
			}
		} finally {
			$this->quiet = false;
		}
		return $this->clean_ids( array_keys( $ids ) );
	}

	/**
	 * Canonical display name for a raw brand name (for alt texts and logs).
	 */
	public function brand_display_name( string $raw ): string {
		$group = $this->group_for_name( $raw );
		return $group ? (string) $group['name'] : Brands::canonical_name( $raw );
	}

	// -------------------------------------------------------------------------------------
	// Tags
	// -------------------------------------------------------------------------------------

	/**
	 * Exported tags that at least one product uses (name match, normalised).
	 */
	public function used_tags(): array {
		$used = [];
		foreach ( $this->importer->source()->products() as $product ) {
			foreach ( (array) ( $product['tags'] ?? [] ) as $name ) {
				$used[ Normalize::text( (string) $name ) ] = true;
			}
		}
		$tags = [];
		foreach ( $this->importer->source()->tags() as $tag ) {
			$name = (string) ( $tag['name'] ?? '' );
			if ( isset( $used[ Normalize::text( $name ) ] ) || (int) ( $tag['count'] ?? 0 ) > 0 ) {
				$tags[] = $tag;
			}
		}
		return $tags;
	}

	/**
	 * Imports one exported tag. Returns the term id.
	 */
	public function import_tag( array $tag ): int {
		$old_id = (int) ( $tag['id'] ?? 0 );
		$name   = trim( (string) ( $tag['name'] ?? '' ) );
		$slug   = (string) ( $tag['slug'] ?? sanitize_title( $name ) );
		$key    = $old_id > 0 ? 'old:' . $old_id : 'name:' . Normalize::text( $name );
		if ( isset( $this->cache[ self::TAG ][ $key ] ) ) {
			return $this->cache[ self::TAG ][ $key ];
		}
		if ( '' === $name ) {
			return 0;
		}
		$args     = [
			'slug'        => $slug,
			'description' => self::clean_description( (string) ( $tag['description'] ?? '' ) ),
		];
		$existing = $old_id > 0 ? $this->find_by_old_id( self::TAG, $old_id ) : 0;
		if ( ! $existing ) {
			$existing = $this->find_by_slug( self::TAG, $slug );
		}
		if ( ! $existing ) {
			$found    = get_term_by( 'name', $name, self::TAG );
			$existing = $found instanceof WP_Term ? (int) $found->term_id : 0;
		}
		if ( $existing > 0 ) {
			$this->update_term( $existing, self::TAG, $name, $args, $old_id > 0 ? [ $old_id ] : [] );
			$this->remember( self::TAG, $key, $existing );
			return $existing;
		}
		$term_id = $this->create_term( self::TAG, $name, $args, $old_id > 0 ? [ $old_id ] : [] );
		$this->remember( self::TAG, $key, $term_id );
		return $term_id;
	}

	/**
	 * Tag term ids for a product's tag names (exported tags preferred, created on demand).
	 *
	 * @param string[] $names Tag names.
	 * @return int[]
	 */
	public function tag_ids_for_names( array $names ): array {
		$ids   = [];
		$index = [];
		foreach ( $this->importer->source()->tags() as $tag ) {
			$index[ Normalize::text( (string) ( $tag['name'] ?? '' ) ) ] = $tag;
		}
		$this->quiet = true;
		try {
			foreach ( $names as $name ) {
				$name = trim( (string) $name );
				if ( '' === $name ) {
					continue;
				}
				$tag = $index[ Normalize::text( $name ) ] ?? [
					'id'   => 0,
					'name' => $name,
					'slug' => sanitize_title( $name ),
				];
				$id  = $this->import_tag( $tag );
				if ( 0 !== $id ) {
					$ids[ $id ] = true;
				}
			}
		} finally {
			$this->quiet = false;
		}
		return $this->clean_ids( array_keys( $ids ) );
	}

	// -------------------------------------------------------------------------------------
	// Shared
	// -------------------------------------------------------------------------------------

	/**
	 * Term id carrying `_gtm_import_old_id` = $old_id in a taxonomy, or 0.
	 */
	public function find_by_old_id( string $taxonomy, int $old_id ): int {
		if ( $old_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$ids = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
				'number'     => 1,
				'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'   => self::META_OLD_ID,
						'value' => (string) $old_id,
					],
				],
			]
		);
		return is_array( $ids ) && $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Term id by slug (decoded Persian accepted), or 0.
	 */
	public function find_by_slug( string $taxonomy, string $slug ): int {
		if ( '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$term = get_term_by( 'slug', $slug, $taxonomy );
		return $term instanceof WP_Term ? (int) $term->term_id : 0;
	}

	/**
	 * Term description: entities decoded, kses, Windows line endings normalised.
	 */
	public static function clean_description( string $description ): string {
		$description = str_replace( [ "\r\n", "\r" ], "\n", trim( $description ) );
		if ( '' === $description ) {
			return '';
		}
		return trim( wp_kses_post( $description ) );
	}

	/**
	 * Creates a term (dry run: pseudo id < 0). Logs created/failed.
	 *
	 * @param int[]    $old_ids Old ids to attach as _gtm_import_old_id.
	 * @param string[] $aliases Brand aliases (brands only).
	 */
	private function create_term( string $taxonomy, string $name, array $args, array $old_ids, array $aliases = [] ): int {
		$label = self::taxonomy_label( $taxonomy );
		if ( $this->importer->dry() ) {
			if ( ! $this->quiet ) {
				++$this->importer->job()->counts['created'];
			}
			/* translators: 1: taxonomy label, 2: term name, 3: slug */
			$this->log( Log::INFO, sprintf( __( '[dry run] would create %1$s "%2$s" (%3$s).', 'gadgetim-core' ), $label, $name, (string) $args['slug'] ) );
			return -( ++$this->dry_counter );
		}
		$result = wp_insert_term( $name, $taxonomy, $args );
		if ( $result instanceof WP_Error ) {
			$existing = (int) $result->get_error_data( 'term_exists' );
			if ( $existing > 0 ) {
				$this->update_term( $existing, $taxonomy, $name, $args, $old_ids, $aliases );
				return $existing;
			}
			++$this->importer->job()->counts['failed'];
			/* translators: 1: taxonomy label, 2: term name, 3: error message */
			$this->log( Log::ERROR, sprintf( __( '%1$s "%2$s" could not be created: %3$s', 'gadgetim-core' ), $label, $name, $result->get_error_message() ) );
			return 0;
		}
		$term_id = (int) $result['term_id'];
		$this->write_meta( $term_id, $old_ids, $aliases );
		if ( ! $this->quiet ) {
			++$this->importer->job()->counts['created'];
		}
		/* translators: 1: taxonomy label, 2: term name, 3: slug */
		$this->log( Log::SUCCESS, sprintf( __( 'Created %1$s "%2$s" (%3$s).', 'gadgetim-core' ), $label, $name, (string) $args['slug'] ) );
		return $term_id;
	}

	/**
	 * Updates an existing term when name/parent/description changed; always refreshes the import meta.
	 */
	private function update_term( int $term_id, string $taxonomy, string $name, array $args, array $old_ids, array $aliases = [] ): void {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$changes = [];
		if ( $term->name !== $name ) {
			$changes['name'] = $name;
		}
		if ( isset( $args['parent'] ) && (int) $term->parent !== (int) $args['parent'] ) {
			$changes['parent'] = (int) $args['parent'];
		}
		if ( isset( $args['description'] ) && '' !== $args['description'] && trim( (string) $term->description ) !== trim( (string) $args['description'] ) ) {
			$changes['description'] = $args['description'];
		}
		$label = self::taxonomy_label( $taxonomy );
		if ( $this->importer->dry() ) {
			if ( $this->quiet ) {
				return;
			}
			++$this->importer->job()->counts[ $changes ? 'updated' : 'skipped' ];
			$this->log(
				Log::INFO,
				$changes
					/* translators: 1: taxonomy label, 2: term name, 3: changed fields */
					? sprintf( __( '[dry run] would update %1$s "%2$s" (%3$s).', 'gadgetim-core' ), $label, $term->name, implode( ', ', array_keys( $changes ) ) )
					/* translators: 1: taxonomy label, 2: term name */
					: sprintf( __( '[dry run] %1$s "%2$s" already exists.', 'gadgetim-core' ), $label, $term->name )
			);
			return;
		}
		if ( $changes && ! $this->quiet ) {
			$result = wp_update_term( $term_id, $taxonomy, $changes );
			if ( $result instanceof WP_Error ) {
				++$this->importer->job()->counts['failed'];
				/* translators: 1: taxonomy label, 2: term name, 3: error message */
				$this->log( Log::ERROR, sprintf( __( '%1$s "%2$s" could not be updated: %3$s', 'gadgetim-core' ), $label, $term->name, $result->get_error_message() ) );
				return;
			}
			++$this->importer->job()->counts['updated'];
			/* translators: 1: taxonomy label, 2: term name */
			$this->log( Log::SUCCESS, sprintf( __( 'Updated %1$s "%2$s".', 'gadgetim-core' ), $label, $name ) );
		} elseif ( ! $this->quiet ) {
			++$this->importer->job()->counts['skipped'];
		}
		$this->write_meta( $term_id, $old_ids, $aliases );
	}

	/**
	 * Attaches the old ids (one meta row each) and the brand aliases.
	 */
	private function write_meta( int $term_id, array $old_ids, array $aliases ): void {
		$existing = array_map( 'intval', (array) get_term_meta( $term_id, self::META_OLD_ID, false ) );
		foreach ( $old_ids as $old_id ) {
			$old_id = (int) $old_id;
			if ( $old_id > 0 && ! in_array( $old_id, $existing, true ) ) {
				add_term_meta( $term_id, self::META_OLD_ID, $old_id, false );
			}
		}
		if ( $aliases ) {
			$aliases = array_values( array_unique( array_map( 'sanitize_text_field', $aliases ) ) );
			update_term_meta( $term_id, Brands::META_ALIASES, $aliases );
		}
	}

	/**
	 * Group holding a raw brand name (as exported) or null.
	 */
	private function group_for_name( string $raw ): ?array {
		$groups = $this->brand_groups();
		$rule   = Brands::canonical( $raw );
		if ( $rule && isset( $groups[ $rule['slug'] ] ) ) {
			return $groups[ $rule['slug'] ];
		}
		if ( null === $this->alias_index ) {
			$this->alias_index = [];
			foreach ( $groups as $slug => $group ) {
				$this->alias_index[ Brands::key( (string) $group['name'] ) ] = $slug;
				foreach ( $group['aliases'] as $alias ) {
					$this->alias_index[ Brands::key( (string) $alias ) ] = $slug;
				}
			}
		}
		$slug = $this->alias_index[ Brands::key( $raw ) ] ?? null;
		if ( null === $slug && isset( $groups[ sanitize_title( $raw ) ] ) ) {
			$slug = sanitize_title( $raw );
		}
		return null !== $slug ? ( $groups[ $slug ] ?? null ) : null;
	}

	/**
	 * Caches a resolved id.
	 */
	private function remember( string $taxonomy, string $key, int $id ): void {
		$this->cache[ $taxonomy ][ $key ] = $id;
	}

	/**
	 * Real runs keep positive ids only; dry runs keep the pseudo ids for counting.
	 *
	 * @param int[] $ids Ids.
	 * @return int[]
	 */
	private function clean_ids( array $ids ): array {
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		if ( $this->importer->dry() ) {
			return array_values( array_filter( $ids, static fn( int $id ): bool => 0 !== $id ) );
		}
		return array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );
	}

	private function log( string $level, string $message ): void {
		$this->importer->log()->add( $level, $message, 'terms' );
	}

	private static function taxonomy_label( string $taxonomy ): string {
		return match ( $taxonomy ) {
			self::CAT        => __( 'category', 'gadgetim-core' ),
			self::TAG        => __( 'tag', 'gadgetim-core' ),
			Brands::TAXONOMY => __( 'brand', 'gadgetim-core' ),
			default          => $taxonomy,
		};
	}
}
