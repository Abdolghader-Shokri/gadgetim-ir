<?php
/**
 * JSON export/import of the `_gtm_*_{lang}` translation meta on products, pages and terms
 * (architecture §8.1 #5: offline drafting). Delegates to I18n when it offers the same.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Cli;

use Gadgetim\Core\Catalog\ProductExtras;
use Gadgetim\Core\Support\Options;
use WP_Error;
use WP_Term;

/**
 * File shape: { version, exported_at, languages, posts: [ { id, type, slug, source: {…}, translations: { en: {…} } } ],
 * terms: [ { id, taxonomy, slug, source: {…}, translations: {…} } ] }. Import matches by id (type checked), then slug.
 */
final class TranslationsIo {

	public const VERSION = 1;

	/** Post fields: key → [ meta suffix base, sanitizer ]. */
	public const POST_FIELDS = [
		'title'     => [ '_gtm_title', 'text' ],
		'slug'      => [ '_gtm_slug', 'slug' ],
		'excerpt'   => [ '_gtm_excerpt', 'html' ],
		'content'   => [ '_gtm_content', 'html' ],
		'subtitle'  => [ '_gtm_subtitle', 'text' ],
		'specs'     => [ '_gtm_specs', 'specs' ],
		'faq'       => [ '_gtm_faq', 'faq' ],
		'guarantee' => [ '_gtm_guarantee', 'html' ],
		'seo_title' => [ '_gtm_seo_title', 'text' ],
		'seo_desc'  => [ '_gtm_seo_desc', 'text' ],
		'status'    => [ '_gtm_status', 'status' ],
	];

	/** Term fields. */
	public const TERM_FIELDS = [
		'name'   => [ '_gtm_name', 'text' ],
		'slug'   => [ '_gtm_slug', 'slug' ],
		'desc'   => [ '_gtm_desc', 'html' ],
		'status' => [ '_gtm_status', 'status' ],
	];

	public const POST_TYPES = [ 'product', 'page' ];

	public const TAXONOMIES = [ 'product_cat', 'product_brand', 'product_tag' ];

	/** @var string[] */
	private array $languages;

	/** @var string[] */
	private array $types;

	/**
	 * @param string[] $languages Language codes (empty = every non-Persian language).
	 * @param string[] $types     Object types (post types and taxonomies; empty = all).
	 */
	public function __construct( array $languages = [], array $types = [] ) {
		$all             = array_values( array_filter( Options::language_codes(), static fn( string $c ): bool => 'fa' !== $c ) );
		$this->languages = $languages ? array_values( array_intersect( $all, array_map( 'sanitize_key', $languages ) ) ) : $all;
		$this->types     = $types ? array_values( array_intersect( array_merge( self::POST_TYPES, self::TAXONOMIES ), array_map( 'sanitize_key', $types ) ) ) : array_merge( self::POST_TYPES, self::TAXONOMIES );
	}

	/**
	 * Builds the export structure.
	 */
	public function export(): array {
		$out = [
			'version'     => self::VERSION,
			'exported_at' => gmdate( 'c' ),
			'languages'   => $this->languages,
			'posts'       => [],
			'terms'       => [],
		];
		foreach ( array_intersect( self::POST_TYPES, $this->types ) as $type ) {
			$ids = get_posts(
				[
					'post_type'      => $type,
					'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				]
			);
			foreach ( (array) $ids as $id ) {
				$id   = (int) $id;
				$post = get_post( $id );
				if ( ! $post ) {
					continue;
				}
				$entry = [
					'id'           => $id,
					'type'         => $type,
					'slug'         => rawurldecode( (string) $post->post_name ),
					'source'       => [
						'title'    => (string) $post->post_title,
						'excerpt'  => (string) $post->post_excerpt,
						'subtitle' => (string) get_post_meta( $id, ProductExtras::META_SUBTITLE, true ),
						'specs'    => ProductExtras::decode_specs( (string) get_post_meta( $id, ProductExtras::META_SPECS, true ) ),
						'faq'      => ProductExtras::decode_faq( (string) get_post_meta( $id, ProductExtras::META_FAQ, true ) ),
					],
					'translations' => [],
				];
				foreach ( $this->languages as $lang ) {
					$fields = [];
					foreach ( self::POST_FIELDS as $key => [ $base, $kind ] ) {
						$value = get_post_meta( $id, $base . '_' . $lang, true );
						if ( 'specs' === $kind ) {
							$value = ProductExtras::decode_specs( (string) $value );
						} elseif ( 'faq' === $kind ) {
							$value = ProductExtras::decode_faq( (string) $value );
						} else {
							$value = is_scalar( $value ) ? (string) $value : '';
						}
						$fields[ $key ] = $value;
					}
					$entry['translations'][ $lang ] = $fields;
				}
				$out['posts'][] = $entry;
			}
		}
		foreach ( array_intersect( self::TAXONOMIES, $this->types ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'orderby'    => 'id',
				]
			);
			foreach ( is_array( $terms ) ? $terms : [] as $term ) {
				if ( ! $term instanceof WP_Term ) {
					continue;
				}
				$entry = [
					'id'           => (int) $term->term_id,
					'taxonomy'     => $taxonomy,
					'slug'         => rawurldecode( (string) $term->slug ),
					'source'       => [
						'name' => (string) $term->name,
						'desc' => (string) $term->description,
					],
					'translations' => [],
				];
				foreach ( $this->languages as $lang ) {
					$fields = [];
					foreach ( self::TERM_FIELDS as $key => [ $base, $kind ] ) {
						$value          = get_term_meta( $term->term_id, $base . '_' . $lang, true );
						$fields[ $key ] = is_scalar( $value ) ? (string) $value : '';
					}
					$entry['translations'][ $lang ] = $fields;
				}
				$out['terms'][] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Writes the export as pretty JSON. Returns the object count or a WP_Error.
	 */
	public function export_to_file( string $file ): int|WP_Error {
		$data = $this->export();
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return new WP_Error( 'gadgetim_translations_encode', 'The export could not be encoded as JSON.' );
		}
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'gadgetim_translations_dir', sprintf( 'Directory not writable: %s', $dir ) );
		}
		if ( false === file_put_contents( $file, $json . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new WP_Error( 'gadgetim_translations_write', sprintf( 'Could not write %s', $file ) );
		}
		return count( $data['posts'] ) + count( $data['terms'] );
	}

	/**
	 * Imports a file written by export_to_file(). Returns [ objects, fields, log[] ] or a WP_Error.
	 *
	 * @return array{objects:int,fields:int,log:string[]}|WP_Error
	 */
	public function import_from_file( string $file, bool $dry_run = false ): array|WP_Error {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'gadgetim_translations_read', sprintf( 'File not readable: %s', $file ) );
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'gadgetim_translations_json', 'The file is not valid JSON.' );
		}
		return $this->import( $data, $dry_run );
	}

	/**
	 * Imports an export structure (translations only; sources are never written).
	 *
	 * @return array{objects:int,fields:int,log:string[]}
	 */
	public function import( array $data, bool $dry_run = false ): array {
		$objects = 0;
		$fields  = 0;
		$log     = [];

		foreach ( (array) ( $data['posts'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$type = sanitize_key( (string) ( $entry['type'] ?? 'product' ) );
			if ( ! in_array( $type, $this->types, true ) ) {
				continue;
			}
			$id = $this->resolve_post( (int) ( $entry['id'] ?? 0 ), (string) ( $entry['slug'] ?? '' ), $type );
			if ( ! $id ) {
				$log[] = sprintf( 'Post %s "%s" not found; skipped.', $type, (string) ( $entry['slug'] ?? $entry['id'] ?? '?' ) );
				continue;
			}
			$written = 0;
			foreach ( (array) ( $entry['translations'] ?? [] ) as $lang => $values ) {
				$lang = sanitize_key( (string) $lang );
				if ( ! in_array( $lang, $this->languages, true ) || ! is_array( $values ) ) {
					continue;
				}
				foreach ( self::POST_FIELDS as $key => [ $base, $kind ] ) {
					if ( ! array_key_exists( $key, $values ) ) {
						continue;
					}
					$meta_key = $base . '_' . $lang;
					$clean    = self::sanitize( $kind, $values[ $key ] );
					$current  = get_post_meta( $id, $meta_key, true );
					if ( (string) $current === (string) $clean ) {
						continue;
					}
					++$written;
					if ( $dry_run ) {
						continue;
					}
					if ( '' === $clean ) {
						delete_post_meta( $id, $meta_key );
					} else {
						update_post_meta( $id, $meta_key, $clean );
					}
				}
			}
			if ( $written > 0 ) {
				++$objects;
				$fields += $written;
				$log[]   = sprintf( '%s #%d: %d field(s)%s', $type, $id, $written, $dry_run ? ' (dry run)' : '' );
			}
		}

		foreach ( (array) ( $data['terms'] ?? [] ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$taxonomy = sanitize_key( (string) ( $entry['taxonomy'] ?? '' ) );
			if ( ! in_array( $taxonomy, $this->types, true ) || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$id = $this->resolve_term( (int) ( $entry['id'] ?? 0 ), (string) ( $entry['slug'] ?? '' ), $taxonomy );
			if ( ! $id ) {
				$log[] = sprintf( 'Term %s "%s" not found; skipped.', $taxonomy, (string) ( $entry['slug'] ?? $entry['id'] ?? '?' ) );
				continue;
			}
			$written = 0;
			foreach ( (array) ( $entry['translations'] ?? [] ) as $lang => $values ) {
				$lang = sanitize_key( (string) $lang );
				if ( ! in_array( $lang, $this->languages, true ) || ! is_array( $values ) ) {
					continue;
				}
				foreach ( self::TERM_FIELDS as $key => [ $base, $kind ] ) {
					if ( ! array_key_exists( $key, $values ) ) {
						continue;
					}
					$meta_key = $base . '_' . $lang;
					$clean    = self::sanitize( $kind, $values[ $key ] );
					$current  = get_term_meta( $id, $meta_key, true );
					if ( (string) $current === (string) $clean ) {
						continue;
					}
					++$written;
					if ( $dry_run ) {
						continue;
					}
					if ( '' === $clean ) {
						delete_term_meta( $id, $meta_key );
					} else {
						update_term_meta( $id, $meta_key, $clean );
					}
				}
			}
			if ( $written > 0 ) {
				++$objects;
				$fields += $written;
				$log[]   = sprintf( '%s #%d: %d field(s)%s', $taxonomy, $id, $written, $dry_run ? ' (dry run)' : '' );
			}
		}

		if ( ! $dry_run && $objects > 0 ) {
			/**
			 * Fires after a translations JSON import wrote meta (I18n rebuilds its slug maps on it).
			 *
			 * @param int $objects Objects touched.
			 */
			do_action( 'gadgetim_translations_imported', $objects );
		}

		return [
			'objects' => $objects,
			'fields'  => $fields,
			'log'     => $log,
		];
	}

	/**
	 * Sanitises a value per field kind (architecture §5.10).
	 */
	public static function sanitize( string $kind, mixed $value ): string {
		return match ( $kind ) {
			'slug'   => is_scalar( $value ) ? sanitize_title( (string) $value ) : '',
			'html'   => is_scalar( $value ) ? trim( wp_kses_post( (string) $value ) ) : '',
			'specs'  => ProductExtras::sanitize_specs( $value ),
			'faq'    => ProductExtras::sanitize_faq( $value ),
			'status' => in_array( $value, [ 'published', 'draft' ], true ) ? (string) $value : '',
			default  => is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '',
		};
	}

	private function resolve_post( int $id, string $slug, string $type ): int {
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( $post && $post->post_type === $type ) {
				return $id;
			}
		}
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, $type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}

	private function resolve_term( int $id, string $slug, string $taxonomy ): int {
		if ( $id > 0 ) {
			$term = get_term( $id, $taxonomy );
			if ( $term instanceof WP_Term ) {
				return $id;
			}
		}
		if ( '' !== $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term instanceof WP_Term ) {
				return (int) $term->term_id;
			}
		}
		return 0;
	}
}
