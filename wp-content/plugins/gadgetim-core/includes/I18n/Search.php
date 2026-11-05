<?php
/**
 * Search across languages: per-language normalised blobs (architecture §3.6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Context as SupportContext;
use Gadgetim\Core\Support\Normalize;
use WP_Post;
use WP_Query;

/**
 * On every save the plugin writes _gtm_search_{lang} = Normalize::text( title + subtitle +
 * brand + category names + spec values ) for every language (translated parts included, so
 * "samsung" also finds سامسونگ). posts_search/posts_join match the blob of the current
 * language under /en/ and /ar/ (the visibility gate applies through pre_get_posts), and the
 * native title, excerpt, content OR the blob for Persian product searches.
 */
final class Search {

	public const MAX_BLOB = 4000;

	public const MAX_TERMS = 8;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'save_post', [ self::class, 'on_save_post' ], 30, 2 );
		add_action( 'set_object_terms', [ self::class, 'on_set_object_terms' ], 10, 4 );
		add_filter( 'posts_search', [ self::class, 'posts_search' ], 20, 2 );
		add_filter( 'posts_join', [ self::class, 'posts_join' ], 20, 2 );
	}

	/**
	 * Meta key of the blob.
	 */
	public static function key( string $lang ): string {
		return '_gtm_search_' . $lang;
	}

	/**
	 * save_post (30): index translatable posts.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function on_save_post( $post_id, $post = null ): void {
		if ( ! $post instanceof WP_Post || wp_is_post_autosave( (int) $post_id ) || wp_is_post_revision( (int) $post_id ) ) {
			return;
		}
		if ( ! PostTranslations::supports( $post->post_type ) || in_array( $post->post_status, [ 'auto-draft', 'trash' ], true ) ) {
			return;
		}
		self::index_post( (int) $post_id );
	}

	/**
	 * set_object_terms: category/brand changes alter the blob.
	 *
	 * @param mixed $object_id Object ID.
	 * @param mixed $terms     Terms.
	 * @param mixed $tt_ids    Term taxonomy IDs.
	 * @param mixed $taxonomy  Taxonomy.
	 */
	public static function on_set_object_terms( $object_id, $terms = [], $tt_ids = [], $taxonomy = '' ): void {
		if ( ! is_string( $taxonomy ) || ! TermTranslations::supports( $taxonomy ) ) {
			return;
		}
		$post = get_post( (int) $object_id );
		if ( $post instanceof WP_Post && PostTranslations::supports( $post->post_type ) && 'auto-draft' !== $post->post_status && ! doing_action( 'save_post' ) ) {
			self::index_post( $post->ID );
		}
	}

	/**
	 * Writes the search blobs of one post for every language.
	 */
	public static function index_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || ! PostTranslations::supports( $post->post_type ) ) {
			return;
		}
		$terms     = self::term_names( $post );
		$secondary = Languages::secondary_codes();

		foreach ( Languages::codes() as $lang ) {
			$parts = [ $post->post_title, (string) get_post_meta( $post_id, '_gtm_subtitle', true ) ];
			if ( Languages::DEFAULT === $lang ) {
				foreach ( $secondary as $other ) {
					$parts[] = PostTranslations::raw( $post_id, 'title', $other );
					$parts[] = PostTranslations::raw( $post_id, 'subtitle', $other );
				}
			} else {
				$parts[] = PostTranslations::raw( $post_id, 'title', $lang );
				$parts[] = PostTranslations::raw( $post_id, 'subtitle', $lang );
			}
			if ( Languages::DEFAULT === $lang ) {
				// The Persian blob carries every language's term names (brand and model names cross languages).
				$parts = array_merge( $parts, ...array_values( $terms ) );
			} else {
				$parts = array_merge( $parts, $terms[ Languages::DEFAULT ] ?? [], $terms[ $lang ] ?? [] );
			}
			$parts = array_merge( $parts, self::spec_values( $post_id, Languages::DEFAULT ), Languages::DEFAULT === $lang ? [] : self::spec_values( $post_id, $lang ) );
			if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $post_id );
				if ( $product && method_exists( $product, 'get_sku' ) ) {
					$parts[] = (string) $product->get_sku( 'edit' );
				}
			}

			$blob = Normalize::text( implode( ' ', array_filter( array_map( 'strval', $parts ), 'strlen' ) ) );
			if ( '' !== $blob ) {
				$blob = implode( ' ', array_unique( explode( ' ', $blob ) ) );
				if ( function_exists( 'mb_substr' ) ) {
					$blob = mb_substr( $blob, 0, self::MAX_BLOB, 'UTF-8' );
				}
			}
			$key = self::key( $lang );
			if ( '' === $blob ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $blob );
			}
		}
	}

	/**
	 * Re-indexes every translatable post in batches; returns the number of posts indexed.
	 */
	public static function rebuild_all(): int {
		$count  = 0;
		$offset = 0;
		do {
			$ids = get_posts(
				[
					'post_type'              => PostTranslations::post_types(),
					'post_status'            => [ 'publish', 'private', 'draft', 'pending', 'future' ],
					'fields'                 => 'ids',
					'posts_per_page'         => 100,
					'offset'                 => $offset,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_term_cache' => true,
					'gadgetim_skip_gate'     => true,
					'suppress_filters'       => false,
				]
			);
			foreach ( $ids as $id ) {
				self::index_post( (int) $id );
				$count++;
			}
			$offset += 100;
		} while ( count( $ids ) === 100 );
		return $count;
	}

	/**
	 * posts_search: blob matching for product searches.
	 *
	 * @param mixed    $search WHERE fragment.
	 * @param WP_Query $query  Query.
	 */
	public static function posts_search( $search, $query = null ) {
		if ( ! is_string( $search ) || ! $query instanceof WP_Query || ! self::applies( $query ) ) {
			return $search;
		}
		$lang  = self::lang_for( $query );
		$terms = self::terms_of( (string) $query->get( 's' ) );
		if ( ! $terms ) {
			return $search;
		}
		global $wpdb;
		$clauses = [];
		foreach ( $terms as $term ) {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			if ( Languages::DEFAULT === $lang ) {
				$clauses[] = $wpdb->prepare(
					"({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR gtm_s.meta_value LIKE %s)",
					$like,
					$like,
					$like,
					$like
				);
			} else {
				$clauses[] = $wpdb->prepare( '(gtm_s.meta_value LIKE %s)', $like );
			}
		}
		$sql = ' AND (' . implode( ' AND ', $clauses ) . ') ';
		if ( ! is_user_logged_in() ) {
			$sql .= " AND ({$wpdb->posts}.post_password = '') ";
		}
		return $sql;
	}

	/**
	 * posts_join: the blob of the language.
	 *
	 * @param mixed    $join  JOIN fragment.
	 * @param WP_Query $query Query.
	 */
	public static function posts_join( $join, $query = null ) {
		if ( ! is_string( $join ) || ! $query instanceof WP_Query || ! self::applies( $query ) ) {
			return $join;
		}
		if ( ! self::terms_of( (string) $query->get( 's' ) ) ) {
			return $join;
		}
		global $wpdb;
		return $join . $wpdb->prepare(
			" LEFT JOIN {$wpdb->postmeta} AS gtm_s ON ( gtm_s.post_id = {$wpdb->posts}.ID AND gtm_s.meta_key = %s ) ",
			self::key( self::lang_for( $query ) )
		);
	}

	/**
	 * Whether a query is one of ours: the main front-end search, or any query that sets
	 * 'gadgetim_search' => lang (REST instant search).
	 */
	private static function applies( WP_Query $query ): bool {
		if ( SupportContext::$suspend_translations > 0 || ( is_admin() && ! wp_doing_ajax() ) ) {
			return false;
		}
		$s = $query->get( 's' );
		if ( ! is_string( $s ) || '' === trim( $s ) ) {
			return false;
		}
		if ( $query->get( 'gadgetim_search' ) ) {
			return true;
		}
		return $query->is_search() && $query->is_main_query();
	}

	/**
	 * Language a query searches in.
	 */
	private static function lang_for( WP_Query $query ): string {
		$lang = $query->get( 'gadgetim_search' );
		return is_string( $lang ) && Languages::exists( $lang ) ? $lang : Languages::current();
	}

	/**
	 * Normalised search words (max 8, each at least one character).
	 *
	 * @return string[]
	 */
	public static function terms_of( string $s ): array {
		$normalized = Normalize::text( $s );
		if ( '' === $normalized ) {
			return [];
		}
		if ( function_exists( 'mb_substr' ) ) {
			$normalized = mb_substr( $normalized, 0, 64, 'UTF-8' );
		}
		return array_slice( array_values( array_filter( explode( ' ', $normalized ), 'strlen' ) ), 0, self::MAX_TERMS );
	}

	/**
	 * Term names of a post per language: natives under 'fa', translations under their code.
	 *
	 * @return array<string,string[]>
	 */
	private static function term_names( WP_Post $post ): array {
		$names = [ Languages::DEFAULT => [] ];
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			if ( ! TermTranslations::supports( $taxonomy ) ) {
				continue;
			}
			$terms = get_the_terms( $post->ID, $taxonomy );
			if ( ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$names[ Languages::DEFAULT ][] = (string) $term->name;
				foreach ( Languages::secondary_codes() as $lang ) {
					$translated = TermTranslations::raw( $term->term_id, 'name', $lang );
					if ( '' !== $translated ) {
						$names[ $lang ][] = $translated;
					}
				}
			}
		}
		return $names;
	}

	/**
	 * Values of the spec table (_gtm_specs / _gtm_specs_{lang}, JSON [["label","value"],...]).
	 *
	 * @return string[]
	 */
	private static function spec_values( int $post_id, string $lang ): array {
		$key  = Languages::DEFAULT === $lang ? '_gtm_specs' : '_gtm_specs_' . $lang;
		$json = get_post_meta( $post_id, $key, true );
		if ( ! is_string( $json ) || '' === $json ) {
			return [];
		}
		$rows = json_decode( $json, true );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		$values = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$value = $row[1] ?? ( $row['value'] ?? '' );
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$values[] = (string) $value;
				}
			}
		}
		return $values;
	}
}
