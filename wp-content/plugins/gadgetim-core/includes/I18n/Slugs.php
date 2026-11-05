<?php
/**
 * Translated slugs: per-language slug map, uniqueness, inbound/outbound rewriting, 301s
 * (architecture §3.4).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Context as SupportContext;
use WP_Post;
use WP_Rewrite;
use WP_Term;

/**
 * Storage is meta (_gtm_slug_{lang} on posts and terms, _gtm_slug_{lang}_prev arrays for 301s).
 * Resolution uses one transient per language (gadgetim_slugmap_{lang}) that also carries the
 * published-id lists Visibility and Sitemaps use. Inbound resolution prefers the NATIVE slug
 * when a native object of the same type owns the segment. WooCommerce pages are never
 * slug-translatable.
 */
final class Slugs {

	public const TRANSIENT = 'gadgetim_slugmap_';

	public const VERSION = 2;

	public const MAX_PREV = 10;

	/** @var array<string,array> */
	private static array $maps = [];

	private static ?array $reserved = null;

	private static ?array $wc_pages = null;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'request', [ self::class, 'filter_request' ], 5, 1 );
		add_filter( 'post_type_link', [ self::class, 'filter_post_type_link' ], 10, 4 );
		add_filter( 'post_link', [ self::class, 'filter_post_link' ], 10, 3 );
		add_filter( 'page_link', [ self::class, 'filter_page_link' ], 10, 3 );
		add_filter( 'term_link', [ self::class, 'filter_term_link' ], 10, 3 );
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 0 );
		// Native slugs must not take a translated slug of the same type (inbound is native-first).
		add_filter( 'wp_unique_post_slug', [ self::class, 'unique_post_slug' ], 20, 6 );
		add_filter( 'wp_unique_term_slug', [ self::class, 'unique_term_slug' ], 20, 3 );
		add_filter( 'wp_update_term_data', [ self::class, 'update_term_data' ], 20, 4 );

		// Map invalidation; the map is rebuilt lazily on the next non-Persian request.
		add_action( 'save_post', [ self::class, 'on_post_saved' ], 25, 2 );
		add_action( 'deleted_post', [ self::class, 'invalidate_all' ], 10, 0 );
		add_action( 'trashed_post', [ self::class, 'invalidate_all' ], 10, 0 );
		add_action( 'untrashed_post', [ self::class, 'invalidate_all' ], 10, 0 );
		add_action( 'created_term', [ self::class, 'invalidate_all' ], 10, 0 );
		add_action( 'edited_term', [ self::class, 'invalidate_all' ], 10, 0 );
		add_action( 'delete_term', [ self::class, 'invalidate_all' ], 10, 0 );
		foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta' ] as $hook ) {
			add_action( $hook, [ self::class, 'on_meta_changed' ], 10, 3 );
		}
	}

	/* ------------------------------------------------------------------ map */

	/**
	 * The slug map of a language (built on demand).
	 *
	 * @return array{v:int,post:array,post_prev:array,term:array,term_prev:array,published:array,terms_published:array,built:int}
	 */
	public static function map( string $lang ): array {
		if ( isset( self::$maps[ $lang ] ) ) {
			return self::$maps[ $lang ];
		}
		$map = get_transient( self::TRANSIENT . $lang );
		if ( ! is_array( $map ) || self::VERSION !== (int) ( $map['v'] ?? 0 ) ) {
			$map = self::rebuild( $lang );
		}
		self::$maps[ $lang ] = $map;
		return $map;
	}

	/**
	 * Rebuilds and stores the map of one language (three prepared queries).
	 */
	public static function rebuild( string $lang ): array {
		global $wpdb;

		$lang = sanitize_key( $lang );
		$map  = [
			'v'               => self::VERSION,
			'post'            => [],
			'post_prev'       => [],
			'term'            => [],
			'term_prev'       => [],
			'published'       => [],
			'terms_published' => [],
			'built'           => time(),
		];
		if ( '' === $lang || Languages::DEFAULT === $lang ) {
			return $map;
		}

		$slug_key   = PostTranslations::key( 'slug', $lang );
		$prev_key   = $slug_key . '_prev';
		$status_key = PostTranslations::key( 'status', $lang );
		$types      = PostTranslations::post_types();

		if ( $types ) {
			$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated, values are prepared.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, p.post_type, pm.meta_key, pm.meta_value
					 FROM {$wpdb->postmeta} pm
					 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					 WHERE pm.meta_key IN (%s, %s, %s) AND p.post_status = 'publish' AND p.post_type IN ($placeholders)",
					array_merge( [ $slug_key, $prev_key, $status_key ], $types )
				)
			);
			// phpcs:enable
			foreach ( (array) $rows as $row ) {
				$id   = (int) $row->post_id;
				$type = (string) $row->post_type;
				if ( $row->meta_key === $slug_key ) {
					$key = self::key( (string) $row->meta_value );
					if ( '' !== $key ) {
						$map['post'][ $type ][ $key ] = $id;
					}
				} elseif ( $row->meta_key === $prev_key ) {
					foreach ( self::unserialize_list( $row->meta_value ) as $prev ) {
						$key = self::key( $prev );
						if ( '' !== $key ) {
							$map['post_prev'][ $type ][ $key ] = $id;
						}
					}
				} elseif ( 'published' === $row->meta_value ) {
					$map['published'][ $type ][] = $id;
				}
			}
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tm.term_id, tt.taxonomy, tm.meta_key, tm.meta_value
				 FROM {$wpdb->termmeta} tm
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
				 WHERE tm.meta_key IN (%s, %s, %s)",
				$slug_key,
				$prev_key,
				$status_key
			)
		);
		foreach ( (array) $rows as $row ) {
			$id       = (int) $row->term_id;
			$taxonomy = (string) $row->taxonomy;
			if ( $row->meta_key === $slug_key ) {
				$key = self::key( (string) $row->meta_value );
				if ( '' !== $key ) {
					$map['term'][ $taxonomy ][ $key ] = $id;
				}
			} elseif ( $row->meta_key === $prev_key ) {
				foreach ( self::unserialize_list( $row->meta_value ) as $prev ) {
					$key = self::key( $prev );
					if ( '' !== $key ) {
						$map['term_prev'][ $taxonomy ][ $key ] = $id;
					}
				}
			} elseif ( 'published' === $row->meta_value ) {
				$map['terms_published'][ $taxonomy ][] = $id;
			}
		}

		set_transient( self::TRANSIENT . $lang, $map, 0 );
		self::$maps[ $lang ] = $map;
		return $map;
	}

	/**
	 * Rebuilds every secondary language; returns [ lang => [ 'posts' => n, 'terms' => n ] ].
	 */
	public static function rebuild_all(): array {
		$report = [];
		foreach ( Languages::secondary_codes() as $lang ) {
			$map = SupportContext::with_suspended( static fn() => self::rebuild( $lang ) );
			$report[ $lang ] = [
				'posts' => array_sum( array_map( 'count', $map['post'] ) ),
				'terms' => array_sum( array_map( 'count', $map['term'] ) ),
			];
		}
		return $report;
	}

	/**
	 * Drops the map of one language.
	 */
	public static function invalidate( string $lang ): void {
		unset( self::$maps[ $lang ] );
		delete_transient( self::TRANSIENT . sanitize_key( $lang ) );
	}

	/**
	 * Drops every map.
	 */
	public static function invalidate_all(): void {
		foreach ( Languages::secondary_codes() as $lang ) {
			self::invalidate( $lang );
		}
	}

	/**
	 * Published post IDs of a type in a language (Persian: not tracked, returns []).
	 *
	 * @return int[]
	 */
	public static function published_ids( string $lang, string $type = 'product' ): array {
		if ( Languages::DEFAULT === $lang ) {
			return [];
		}
		return array_map( 'intval', (array) ( self::map( $lang )['published'][ $type ] ?? [] ) );
	}

	/**
	 * Published term IDs of a taxonomy in a language.
	 *
	 * @return int[]
	 */
	public static function published_term_ids( string $lang, string $taxonomy ): array {
		if ( Languages::DEFAULT === $lang ) {
			return [];
		}
		return array_map( 'intval', (array) ( self::map( $lang )['terms_published'][ $taxonomy ] ?? [] ) );
	}

	/**
	 * save_post: translatable types invalidate the maps.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function on_post_saved( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && PostTranslations::supports( $post->post_type ) && ! wp_is_post_revision( (int) $post_id ) ) {
			self::invalidate_all();
		}
	}

	/**
	 * Post/term meta changes on _gtm_slug_* / _gtm_status_* invalidate that language's map.
	 *
	 * @param mixed  $meta_id   Meta ID(s).
	 * @param mixed  $object_id Object ID.
	 * @param string $meta_key  Meta key.
	 */
	public static function on_meta_changed( $meta_id, $object_id, $meta_key = '' ): void {
		if ( is_string( $meta_key ) && preg_match( '/^_gtm_(?:slug|status)_([a-z]{2,5})(?:_prev)?$/', $meta_key, $m ) ) {
			self::invalidate( $m[1] );
		}
	}

	/**
	 * Comparable form of a slug or request segment: lowercase, percent-encoded like post_name.
	 */
	public static function key( string $slug ): string {
		$slug = trim( $slug, '/' );
		return '' === $slug ? '' : sanitize_title_for_query( $slug );
	}

	/* -------------------------------------------------------------- inbound */

	/**
	 * request filter (§3.4): translated segments become native slugs, native-first.
	 *
	 * @param mixed $qv Query vars.
	 */
	public static function filter_request( $qv ) {
		if ( ! is_array( $qv ) || Languages::is_default() ) {
			return $qv;
		}
		$lang = Languages::current();

		if ( ! empty( $qv['product'] ) && is_string( $qv['product'] ) ) {
			$native = self::native_post_path( $qv['product'], $lang, 'product' );
			if ( $native !== $qv['product'] ) {
				$qv['product'] = $native;
				if ( isset( $qv['name'] ) ) {
					$qv['name'] = $native;
				}
			}
		} elseif ( ! empty( $qv['name'] ) && is_string( $qv['name'] ) ) {
			$has_type = isset( $qv['post_type'] ) && is_string( $qv['post_type'] ) && '' !== $qv['post_type'];
			$type     = $has_type ? $qv['post_type'] : 'post';
			$native   = PostTranslations::supports( $type ) ? self::native_post_path( $qv['name'], $lang, $type ) : $qv['name'];
			if ( $native !== $qv['name'] ) {
				$qv['name'] = $native;
			} elseif ( ! $has_type ) {
				// With /%postname%/ a translated page slug misses the verbose page rule (no native
				// page has that path) and lands on the post rule as `name`.
				$qv = self::page_from_request( $qv, $lang );
			}
		} elseif ( empty( $qv['pagename'] ) && ( ! empty( $qv['attachment'] ) || ( isset( $qv['error'] ) && '404' === (string) $qv['error'] ) ) ) {
			// Hierarchical translated page paths (a/b) fall through to the attachment or 404 rules.
			$qv = self::page_from_request( $qv, $lang );
		}

		if ( ! empty( $qv['pagename'] ) && is_string( $qv['pagename'] ) ) {
			$qv['pagename'] = self::native_post_path( $qv['pagename'], $lang, 'page' );
		}

		foreach ( TermTranslations::taxonomies() as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax ) {
				continue;
			}
			$var = is_string( $tax->query_var ) && '' !== $tax->query_var ? $tax->query_var : $taxonomy;
			if ( ! empty( $qv[ $var ] ) && is_string( $qv[ $var ] ) ) {
				$qv[ $var ] = self::native_term_path( $qv[ $var ], $lang, $taxonomy );
			}
		}
		return $qv;
	}

	/**
	 * Rewrites the query vars to a page request when the whole request path is a translated page
	 * path (every segment walked through the page map). Leaves $qv alone otherwise, and when a
	 * native post owns the slug (native-first).
	 *
	 * @param array  $qv   Query vars.
	 * @param string $lang Language.
	 */
	private static function page_from_request( array $qv, string $lang ): array {
		if ( ! PostTranslations::supports( 'page' ) || ! ( $GLOBALS['wp'] ?? null ) instanceof \WP ) {
			return $qv;
		}
		$path = trim( (string) $GLOBALS['wp']->request, '/' );
		if ( '' === $path ) {
			return $qv;
		}
		$page_no = '';
		if ( preg_match( '#^(.+?)/([0-9]+)$#', $path, $m ) ) {
			$path    = $m[1];
			$page_no = $m[2];
		}
		if ( ! empty( $qv['name'] ) && is_string( $qv['name'] ) && self::native_post_exists( $qv['name'], 'post' ) ) {
			return $qv;
		}
		$native = self::native_post_path( $path, $lang, 'page' );
		if ( $native === $path || ! get_page_by_path( $native, OBJECT, 'page' ) instanceof WP_Post ) {
			return $qv;
		}
		foreach ( [ 'name', 'page', 'attachment', 'attachment_id', 'error', 'p', 'subpost', 'subpost_id', 'post_type' ] as $key ) {
			unset( $qv[ $key ] );
		}
		$qv['pagename'] = $native;
		if ( '' !== $page_no ) {
			$qv['page'] = $page_no;
		}
		return $qv;
	}

	/**
	 * Resolves a (possibly hierarchical) path of translated post slugs to native slugs.
	 * Segments that are not translated slugs, or that a native object of the same type owns,
	 * are left alone (native-first precedence).
	 */
	public static function native_post_path( string $path, string $lang, string $type ): string {
		$translated = self::map( $lang )['post'][ $type ] ?? [];
		if ( ! $translated ) {
			return $path;
		}
		$segments = explode( '/', $path );
		$changed  = false;
		foreach ( $segments as $i => $segment ) {
			if ( '' === $segment ) {
				continue;
			}
			$key = self::key( $segment );
			if ( '' === $key || ! isset( $translated[ $key ] ) || self::native_post_exists( $key, $type ) ) {
				continue;
			}
			$native = get_post_field( 'post_name', (int) $translated[ $key ], 'raw' );
			if ( is_string( $native ) && '' !== $native ) {
				$segments[ $i ] = $native;
				$changed        = true;
			}
		}
		return $changed ? implode( '/', $segments ) : $path;
	}

	/**
	 * Same for taxonomy term paths ("parent/child").
	 */
	public static function native_term_path( string $path, string $lang, string $taxonomy ): string {
		$translated = self::map( $lang )['term'][ $taxonomy ] ?? [];
		if ( ! $translated ) {
			return $path;
		}
		$segments = explode( '/', $path );
		$changed  = false;
		foreach ( $segments as $i => $segment ) {
			if ( '' === $segment ) {
				continue;
			}
			$key = self::key( $segment );
			if ( '' === $key || ! isset( $translated[ $key ] ) || self::native_term_exists( $key, $taxonomy ) ) {
				continue;
			}
			$term = get_term( (int) $translated[ $key ], $taxonomy );
			if ( $term instanceof WP_Term && '' !== $term->slug ) {
				$segments[ $i ] = $term->slug;
				$changed        = true;
			}
		}
		return $changed ? implode( '/', $segments ) : $path;
	}

	/**
	 * Whether a native post of the type owns this slug (trash and auto-drafts ignored).
	 */
	public static function native_post_exists( string $slug, string $type, int $exclude_id = 0 ): bool {
		global $wpdb;
		$slug = self::key( $slug );
		if ( '' === $slug ) {
			return false;
		}
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status NOT IN ('trash', 'auto-draft') AND ID <> %d LIMIT 1",
				$slug,
				$type,
				$exclude_id
			)
		);
		return null !== $id;
	}

	/**
	 * Whether a native term of the taxonomy owns this slug.
	 */
	public static function native_term_exists( string $slug, string $taxonomy, int $exclude_id = 0 ): bool {
		global $wpdb;
		$slug = self::key( $slug );
		if ( '' === $slug ) {
			return false;
		}
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.slug = %s AND tt.taxonomy = %s AND t.term_id <> %d LIMIT 1",
				$slug,
				$taxonomy,
				$exclude_id
			)
		);
		return null !== $id;
	}

	/* ------------------------------------------------------------- outbound */

	/**
	 * post_type_link (products and other custom types).
	 *
	 * @param mixed   $link      Permalink.
	 * @param WP_Post $post      Post.
	 * @param bool    $leavename Keep %postname% placeholder.
	 * @param bool    $sample    Sample permalink.
	 */
	public static function filter_post_type_link( $link, $post = null, $leavename = false, $sample = false ) {
		if ( ! is_string( $link ) || $leavename || ! $post instanceof WP_Post || Languages::is_default() ) {
			return $link;
		}
		if ( ! PostTranslations::supports( $post->post_type ) || in_array( $post->post_type, [ 'page', 'post' ], true ) ) {
			return $link;
		}
		$translated = PostTranslations::slug( $post->ID, Languages::current() );
		if ( '' === $translated || $translated === $post->post_name || '' === $post->post_name ) {
			return $link;
		}
		return Urls::replace_segments( $link, $post->post_name, $translated );
	}

	/**
	 * post_link (blog posts).
	 *
	 * @param mixed   $permalink Permalink.
	 * @param WP_Post $post      Post.
	 * @param bool    $leavename Keep %postname%.
	 */
	public static function filter_post_link( $permalink, $post = null, $leavename = false ) {
		if ( ! is_string( $permalink ) || $leavename || ! $post instanceof WP_Post || Languages::is_default() || ! PostTranslations::supports( 'post' ) ) {
			return $permalink;
		}
		$translated = PostTranslations::slug( $post->ID, Languages::current() );
		if ( '' === $translated || $translated === $post->post_name || '' === $post->post_name ) {
			return $permalink;
		}
		return Urls::replace_segments( $permalink, $post->post_name, $translated );
	}

	/**
	 * page_link (non-WooCommerce pages): the hierarchical path is rebuilt with each
	 * ancestor's translated slug.
	 *
	 * @param mixed $link    Permalink.
	 * @param mixed $post_id Page ID.
	 * @param bool  $sample  Sample permalink.
	 */
	public static function filter_page_link( $link, $post_id = 0, $sample = false ) {
		if ( ! is_string( $link ) || $sample || Languages::is_default() || ! PostTranslations::supports( 'page' ) ) {
			return $link;
		}
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || self::is_wc_page( $post_id ) || (int) get_option( 'page_on_front' ) === $post_id ) {
			return $link;
		}
		$lang    = Languages::current();
		$chain   = array_merge( array_reverse( get_post_ancestors( $post_id ) ), [ $post_id ] );
		$native  = [];
		$result  = [];
		$changed = false;
		foreach ( $chain as $id ) {
			$slug = (string) get_post_field( 'post_name', (int) $id, 'raw' );
			if ( '' === $slug ) {
				return $link;
			}
			$translated = PostTranslations::slug( (int) $id, $lang );
			$native[]   = $slug;
			if ( '' !== $translated && $translated !== $slug ) {
				$result[] = $translated;
				$changed  = true;
			} else {
				$result[] = $slug;
			}
		}
		if ( ! $changed ) {
			return $link;
		}
		return Urls::replace_segments( $link, implode( '/', $native ), implode( '/', $result ) );
	}

	/**
	 * term_link: hierarchical paths carry each ancestor's translated slug.
	 *
	 * @param mixed   $termlink Link.
	 * @param WP_Term $term     Term.
	 * @param string  $taxonomy Taxonomy.
	 */
	public static function filter_term_link( $termlink, $term = null, $taxonomy = '' ) {
		if ( ! is_string( $termlink ) || ! $term instanceof WP_Term || Languages::is_default() || ! TermTranslations::supports( (string) $taxonomy ) ) {
			return $termlink;
		}
		$lang    = Languages::current();
		$chain   = [];
		$tax     = get_taxonomy( (string) $taxonomy );
		$nested  = $tax && is_taxonomy_hierarchical( (string) $taxonomy ) && ! empty( $tax->rewrite['hierarchical'] );
		if ( $nested ) {
			$chain = array_reverse( get_ancestors( $term->term_id, (string) $taxonomy, 'taxonomy' ) );
		}
		$chain[] = $term->term_id;

		$native  = [];
		$result  = [];
		$changed = false;
		foreach ( $chain as $id ) {
			$t = (int) $id === $term->term_id ? $term : get_term( (int) $id, (string) $taxonomy );
			if ( ! $t instanceof WP_Term || '' === $t->slug ) {
				return $termlink;
			}
			$translated = TermTranslations::slug( $t->term_id, $lang );
			$native[]   = $t->slug;
			if ( '' !== $translated && $translated !== $t->slug ) {
				$result[] = $translated;
				$changed  = true;
			} else {
				$result[] = $t->slug;
			}
		}
		if ( ! $changed ) {
			return $termlink;
		}
		return Urls::replace_segments( $termlink, implode( '/', $native ), implode( '/', $result ) );
	}

	/* ------------------------------------------------------------ redirects */

	/**
	 * template_redirect (priority 0): under a prefix, a Persian slug 301s to the translated
	 * one when a translation exists, and a previous translated slug 301s to the current one.
	 */
	public static function maybe_redirect(): void {
		if ( Languages::is_default() || is_admin() || is_feed() || is_robots() || is_favicon() || is_trackback() || is_preview() ) {
			return;
		}
		$lang = Languages::current();

		if ( is_404() ) {
			self::redirect_previous_slug( $lang );
			return;
		}

		$pairs = [];
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || ! PostTranslations::supports( $post->post_type ) || self::is_wc_page( $post->ID ) ) {
				return;
			}
			if ( ! PostTranslations::is_published( $post->ID, $lang ) && ! current_user_can( 'edit_post', $post->ID ) ) {
				return; // Visibility decides (404 or Persian fallback).
			}
			$chain = 'page' === $post->post_type ? array_merge( array_reverse( get_post_ancestors( $post ) ), [ $post->ID ] ) : [ $post->ID ];
			foreach ( $chain as $id ) {
				$native     = (string) get_post_field( 'post_name', (int) $id, 'raw' );
				$translated = PostTranslations::slug( (int) $id, $lang );
				if ( '' !== $native && '' !== $translated && $translated !== $native ) {
					$pairs[ self::key( $native ) ] = $translated;
				}
			}
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || ! TermTranslations::supports( $term->taxonomy ) ) {
				return;
			}
			$chain   = is_taxonomy_hierarchical( $term->taxonomy ) ? array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) : [];
			$chain[] = $term->term_id;
			foreach ( $chain as $id ) {
				$t = get_term( (int) $id, $term->taxonomy );
				if ( ! $t instanceof WP_Term ) {
					continue;
				}
				$translated = TermTranslations::slug( $t->term_id, $lang );
				if ( '' !== $t->slug && '' !== $translated && $translated !== $t->slug ) {
					$pairs[ self::key( $t->slug ) ] = $translated;
				}
			}
		} else {
			return;
		}
		if ( ! $pairs ) {
			return;
		}

		$path     = Urls::request_path_unprefixed();
		$segments = explode( '/', $path );
		$changed  = false;
		for ( $i = count( $segments ) - 1; $i >= 0; $i-- ) {
			$segment = $segments[ $i ];
			if ( '' === $segment ) {
				continue;
			}
			$key = self::key( $segment );
			if ( isset( $pairs[ $key ] ) ) {
				$segments[ $i ] = $pairs[ $key ];
				unset( $pairs[ $key ] );
				$changed = true;
			}
		}
		if ( ! $changed ) {
			return;
		}
		self::redirect( home_url( implode( '/', $segments ) ) );
	}

	/**
	 * 404 under a prefix: the last path segment may be a previous translated slug.
	 */
	private static function redirect_previous_slug( string $lang ): void {
		$segments = array_values( array_filter( explode( '/', Urls::request_path_unprefixed() ), 'strlen' ) );
		// Drop pagination ("page/2").
		global $wp_rewrite;
		$pagination = $wp_rewrite instanceof WP_Rewrite ? $wp_rewrite->pagination_base : 'page';
		$count      = count( $segments );
		if ( $count >= 3 && $segments[ $count - 2 ] === $pagination && ctype_digit( $segments[ $count - 1 ] ) ) {
			$segments = array_slice( $segments, 0, $count - 2 );
		}
		if ( ! $segments ) {
			return;
		}
		$last = self::key( (string) end( $segments ) );
		if ( '' === $last ) {
			return;
		}
		$map   = self::map( $lang );
		$kinds = self::prev_kinds( $segments );

		foreach ( $kinds['post'] as $type ) {
			$id = isset( $map['post_prev'][ $type ][ $last ] ) ? (int) $map['post_prev'][ $type ][ $last ] : 0;
			if ( $id <= 0 || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			if ( ! PostTranslations::is_published( $id, $lang ) && ! self::is_wc_page( $id ) && ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$url = get_permalink( $id );
			if ( is_string( $url ) && '' !== $url ) {
				self::redirect( $url );
			}
		}
		foreach ( $kinds['term'] as $taxonomy ) {
			$id = isset( $map['term_prev'][ $taxonomy ][ $last ] ) ? (int) $map['term_prev'][ $taxonomy ][ $last ] : 0;
			if ( $id <= 0 ) {
				continue;
			}
			$term = get_term( $id, $taxonomy );
			if ( $term instanceof WP_Term ) {
				$url = get_term_link( $term );
				if ( is_string( $url ) ) {
					self::redirect( $url );
				}
			}
		}
	}

	/**
	 * Which object kinds a 404 path may belong to, from its first segment and the
	 * WooCommerce permalink bases.
	 *
	 * @param string[] $segments Path segments.
	 * @return array{post:string[],term:string[]}
	 */
	private static function prev_kinds( array $segments ): array {
		$first = (string) $segments[0];
		$types = PostTranslations::post_types();
		$taxes = TermTranslations::taxonomies();
		if ( count( $segments ) < 2 ) {
			return [
				'post' => array_values( array_diff( $types, [ 'product' ] ) ),
				'term' => [],
			];
		}
		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$structure = wc_get_permalink_structure();
			$bases     = [
				'product_base'  => [ 'post', [ 'product' ] ],
				'category_base' => [ 'term', [ 'product_cat' ] ],
				'tag_base'      => [ 'term', [ 'product_tag' ] ],
			];
			foreach ( $bases as $option => [ $kind, $names ] ) {
				$base = trim( (string) ( $structure[ $option ] ?? '' ), '/' );
				$base = '' !== $base ? explode( '/', $base )[0] : '';
				if ( '' !== $base && $base === $first ) {
					return 'post' === $kind ? [ 'post' => $names, 'term' => [] ] : [ 'post' => [], 'term' => $names ];
				}
			}
			if ( 'brand' === $first ) {
				return [ 'post' => [], 'term' => [ 'product_brand' ] ];
			}
		}
		$term_kinds = [];
		foreach ( $taxes as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( $tax && ! empty( $tax->rewrite['slug'] ) && explode( '/', trim( (string) $tax->rewrite['slug'], '/' ) )[0] === $first ) {
				$term_kinds[] = $taxonomy;
			}
		}
		return [
			'post' => array_values( array_diff( $types, [ 'product' ] ) ),
			'term' => $term_kinds,
		];
	}

	/**
	 * 301 with the current query string kept.
	 */
	private static function redirect( string $url ): void {
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- re-attached verbatim and sanitised by wp_safe_redirect().
		if ( '' !== $query && ! str_contains( $url, '?' ) ) {
			$url .= '?' . $query;
		}
		if ( wp_safe_redirect( $url, 301 ) ) {
			exit;
		}
	}

	/* ----------------------------------------------------------- uniqueness */

	/**
	 * Validates a translated slug (§3.4): sanitised, unique across (a) every _gtm_slug_* of the
	 * same type in any language, (b) native slugs of the same type, (c) WooCommerce page and
	 * endpoint slugs plus reserved bases. Collisions get -2, -3 ...; '' when nothing is left.
	 *
	 * @param string $slug      Candidate.
	 * @param string $lang      Language (informational; uniqueness spans languages).
	 * @param string $type      Post type or taxonomy.
	 * @param int    $object_id The object being saved (excluded from the checks).
	 * @param string $kind      'post' or 'term'.
	 */
	public static function validate( string $slug, string $lang, string $type, int $object_id, string $kind = 'post' ): string {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return '';
		}
		$base   = $slug;
		$suffix = 2;
		while ( self::conflicts( $slug, $lang, $type, $object_id, $kind ) ) {
			$slug = $base . '-' . $suffix;
			$suffix++;
			if ( $suffix > 100 ) {
				return '';
			}
		}
		return $slug;
	}

	/**
	 * Whether a slug collides for the given object.
	 */
	public static function conflicts( string $slug, string $lang, string $type, int $object_id, string $kind = 'post' ): bool {
		return 'term' === $kind ? self::term_conflicts( $slug, $type, $object_id ) : self::post_conflicts( $slug, $type, $object_id );
	}

	private static function post_conflicts( string $slug, string $type, int $object_id ): bool {
		if ( in_array( $slug, self::reserved_slugs(), true ) ) {
			return true;
		}
		if ( self::native_post_exists( $slug, $type, $object_id ) ) {
			return true;
		}
		return self::translated_post_taken( $slug, $type, $object_id );
	}

	/**
	 * Whether another post of the type uses the slug as a (current) translated slug.
	 */
	private static function translated_post_taken( string $slug, string $type, int $object_id ): bool {
		global $wpdb;
		$other = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key LIKE %s AND pm.meta_key NOT LIKE %s AND pm.meta_value = %s
				   AND p.post_type = %s AND p.post_status <> 'trash' AND p.ID <> %d
				 LIMIT 1",
				$wpdb->esc_like( '_gtm_slug_' ) . '%',
				'%' . $wpdb->esc_like( '_prev' ),
				$slug,
				$type,
				$object_id
			)
		);
		return null !== $other;
	}

	private static function term_conflicts( string $slug, string $taxonomy, int $object_id ): bool {
		if ( self::native_term_exists( $slug, $taxonomy, $object_id ) ) {
			return true;
		}
		return self::translated_term_taken( $slug, $taxonomy, $object_id );
	}

	/**
	 * Whether another term of the taxonomy uses the slug as a (current) translated slug.
	 */
	private static function translated_term_taken( string $slug, string $taxonomy, int $object_id ): bool {
		global $wpdb;
		$other = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT tm.term_id FROM {$wpdb->termmeta} tm
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
				 WHERE tm.meta_key LIKE %s AND tm.meta_key NOT LIKE %s AND tm.meta_value = %s
				   AND tt.taxonomy = %s AND tm.term_id <> %d
				 LIMIT 1",
				$wpdb->esc_like( '_gtm_slug_' ) . '%',
				'%' . $wpdb->esc_like( '_prev' ),
				$slug,
				$taxonomy,
				$object_id
			)
		);
		return null !== $other;
	}

	/**
	 * wp_unique_post_slug: suffixes a native post_name that equals another post's translated slug.
	 *
	 * @param mixed  $slug          Unique slug WordPress computed.
	 * @param mixed  $post_id       Post ID.
	 * @param mixed  $post_status   Status.
	 * @param mixed  $post_type     Post type.
	 * @param mixed  $post_parent   Parent ID.
	 * @param mixed  $original_slug Requested slug.
	 * @return mixed
	 */
	public static function unique_post_slug( $slug, $post_id = 0, $post_status = '', $post_type = '', $post_parent = 0, $original_slug = '' ) {
		if ( ! is_string( $slug ) || '' === $slug || ! is_string( $post_type ) || ! PostTranslations::supports( $post_type ) ) {
			return $slug;
		}
		$id = (int) $post_id;
		if ( ! self::translated_post_taken( $slug, $post_type, $id ) ) {
			return $slug;
		}
		for ( $suffix = 2; $suffix <= 100; $suffix++ ) {
			$candidate = self::suffixed( $slug, $suffix );
			if ( ! self::native_post_exists( $candidate, $post_type, $id ) && ! self::translated_post_taken( $candidate, $post_type, $id ) ) {
				return $candidate;
			}
		}
		return $slug;
	}

	/**
	 * wp_unique_term_slug (new terms): same rule for term slugs.
	 *
	 * @param mixed $slug          Unique slug.
	 * @param mixed $term          Term object (taxonomy, maybe term_id).
	 * @param mixed $original_slug Requested slug.
	 * @return mixed
	 */
	public static function unique_term_slug( $slug, $term = null, $original_slug = '' ) {
		if ( ! is_string( $slug ) || '' === $slug || ! is_object( $term ) || empty( $term->taxonomy ) ) {
			return $slug;
		}
		return self::free_term_slug( $slug, (string) $term->taxonomy, (int) ( $term->term_id ?? 0 ) );
	}

	/**
	 * wp_update_term_data: wp_update_term() only calls wp_unique_term_slug() for native duplicates,
	 * so a renamed term is checked here.
	 *
	 * @param mixed $data     Term data (name, slug, term_group).
	 * @param mixed $term_id  Term ID.
	 * @param mixed $taxonomy Taxonomy.
	 * @param mixed $args     Arguments.
	 * @return mixed
	 */
	public static function update_term_data( $data, $term_id = 0, $taxonomy = '', $args = [] ) {
		if ( ! is_array( $data ) || empty( $data['slug'] ) || ! is_string( $taxonomy ) ) {
			return $data;
		}
		$data['slug'] = self::free_term_slug( (string) $data['slug'], $taxonomy, (int) $term_id );
		return $data;
	}

	private static function free_term_slug( string $slug, string $taxonomy, int $term_id ): string {
		if ( ! in_array( $taxonomy, TermTranslations::taxonomies(), true ) || ! self::translated_term_taken( $slug, $taxonomy, $term_id ) ) {
			return $slug;
		}
		for ( $suffix = 2; $suffix <= 100; $suffix++ ) {
			$candidate = self::suffixed( $slug, $suffix );
			if ( ! self::native_term_exists( $candidate, $taxonomy, $term_id ) && ! self::translated_term_taken( $candidate, $taxonomy, $term_id ) ) {
				return $candidate;
			}
		}
		return $slug;
	}

	/**
	 * "slug-N", kept within the 200-character post_name / term slug column.
	 */
	private static function suffixed( string $slug, int $suffix ): string {
		$tail = '-' . $suffix;
		return _truncate_post_slug( $slug, 200 - strlen( $tail ) ) . $tail;
	}

	/**
	 * Slugs no translated post may use: language prefixes, WordPress bases, WooCommerce
	 * permalink bases, WooCommerce page slugs and account endpoint slugs.
	 *
	 * @return string[]
	 */
	public static function reserved_slugs(): array {
		if ( null !== self::$reserved ) {
			return self::$reserved;
		}
		global $wp_rewrite;
		$reserved = array_merge(
			array_keys( Languages::prefixed() ),
			[ 'embed', 'feed', 'page', 'comments', 'search', 'author', 'attachment', 'wp-json', 'wp-admin', 'wp-content', 'wp-includes', 'wp-login', 'wc-api', 'wc-ajax', 'wp-sitemap' ]
		);
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$reserved = array_merge( $reserved, (array) $wp_rewrite->feeds, [ $wp_rewrite->pagination_base, $wp_rewrite->comments_pagination_base, $wp_rewrite->search_base, $wp_rewrite->author_base ] );
		}
		if ( function_exists( 'wc_get_permalink_structure' ) ) {
			$structure = wc_get_permalink_structure();
			foreach ( [ 'product_base', 'category_base', 'tag_base', 'attribute_base' ] as $key ) {
				$reserved = array_merge( $reserved, explode( '/', trim( (string) ( $structure[ $key ] ?? '' ), '/' ) ) );
			}
			$reserved[] = 'brand';
		}
		foreach ( self::wc_page_ids() as $page_id ) {
			$name = get_post_field( 'post_name', $page_id, 'raw' );
			if ( is_string( $name ) && '' !== $name ) {
				$reserved[] = $name;
			}
		}
		if ( function_exists( 'WC' ) && isset( WC()->query ) && method_exists( WC()->query, 'get_query_vars' ) ) {
			$reserved = array_merge( $reserved, array_values( array_map( 'strval', (array) WC()->query->get_query_vars() ) ) );
		}
		$reserved = array_values( array_unique( array_filter( array_map( 'strval', $reserved ) ) ) );
		if ( function_exists( 'WC' ) ) {
			self::$reserved = $reserved; // Cache only once WooCommerce contributed its part.
		}
		return $reserved;
	}

	/**
	 * IDs of the pages WooCommerce owns (shop, cart, checkout, my-account, terms, refunds...).
	 * These are never slug-translatable and never gated.
	 *
	 * @return int[]
	 */
	public static function wc_page_ids(): array {
		if ( null !== self::$wc_pages ) {
			return self::$wc_pages;
		}
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return [];
		}
		$ids = [];
		foreach ( [ 'shop', 'cart', 'checkout', 'myaccount', 'terms', 'refund_returns', 'view_order', 'logout' ] as $page ) {
			$id = (int) wc_get_page_id( $page );
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		self::$wc_pages = array_values( $ids );
		return self::$wc_pages;
	}

	/**
	 * Whether a page is one of the WooCommerce pages.
	 */
	public static function is_wc_page( int $post_id ): bool {
		return $post_id > 0 && in_array( $post_id, self::wc_page_ids(), true );
	}

	/**
	 * Records the previous translated slug for 301s (_gtm_slug_{lang}_prev, newest last, capped).
	 *
	 * @param string $kind 'post' or 'term'.
	 */
	public static function remember_previous( string $kind, int $object_id, string $lang, string $old, string $new ): void {
		if ( '' === $old || $old === $new ) {
			return;
		}
		$key  = PostTranslations::key( 'slug', $lang ) . '_prev';
		$prev = 'term' === $kind ? get_term_meta( $object_id, $key, true ) : get_post_meta( $object_id, $key, true );
		$list = array_values( array_filter( array_map( 'strval', is_array( $prev ) ? $prev : [] ) ) );
		$list = array_values( array_diff( $list, [ $new ] ) );
		$list[] = $old;
		$list   = array_slice( array_values( array_unique( $list ) ), -self::MAX_PREV );
		if ( 'term' === $kind ) {
			update_term_meta( $object_id, $key, $list );
		} else {
			update_post_meta( $object_id, $key, $list );
		}
	}

	/**
	 * Slug conflicts report for the Tools tab: translated slugs equal to a native slug of the
	 * same type, shared by two objects, or reserved.
	 *
	 * @return array<int,array{lang:string,kind:string,type:string,slug:string,id:int,reason:string}>
	 */
	public static function conflicts_report(): array {
		global $wpdb;
		$report = [];
		$types  = PostTranslations::post_types();
		$taxes  = TermTranslations::taxonomies();

		foreach ( Languages::secondary_codes() as $lang ) {
			$slug_key = PostTranslations::key( 'slug', $lang );

			if ( $types ) {
				$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, p.post_type, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status <> 'trash' AND p.post_type IN ($placeholders)", array_merge( [ $slug_key ], $types ) ) );
				$seen = [];
				foreach ( (array) $rows as $row ) {
					$id   = (int) $row->post_id;
					$type = (string) $row->post_type;
					$slug = (string) $row->meta_value;
					$k    = $type . '|' . self::key( $slug );
					if ( isset( $seen[ $k ] ) ) {
						$report[] = self::conflict( $lang, 'post', $type, $slug, $id, 'duplicate', $seen[ $k ] );
					}
					$seen[ $k ] = $id;
					if ( in_array( $slug, self::reserved_slugs(), true ) ) {
						$report[] = self::conflict( $lang, 'post', $type, $slug, $id, 'reserved' );
					}
					if ( self::native_post_exists( $slug, $type, $id ) ) {
						$report[] = self::conflict( $lang, 'post', $type, $slug, $id, 'native' );
					}
				}
			}

			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tm.term_id, tt.taxonomy, tm.meta_value FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id WHERE tm.meta_key = %s", $slug_key ) );
			$seen = [];
			foreach ( (array) $rows as $row ) {
				$id       = (int) $row->term_id;
				$taxonomy = (string) $row->taxonomy;
				if ( ! in_array( $taxonomy, $taxes, true ) ) {
					continue;
				}
				$slug = (string) $row->meta_value;
				$k    = $taxonomy . '|' . self::key( $slug );
				if ( isset( $seen[ $k ] ) ) {
					$report[] = self::conflict( $lang, 'term', $taxonomy, $slug, $id, 'duplicate', $seen[ $k ] );
				}
				$seen[ $k ] = $id;
				if ( self::native_term_exists( $slug, $taxonomy, $id ) ) {
					$report[] = self::conflict( $lang, 'term', $taxonomy, $slug, $id, 'native' );
				}
			}
		}
		return $report;
	}

	private static function conflict( string $lang, string $kind, string $type, string $slug, int $id, string $reason, int $other = 0 ): array {
		return [
			'lang'   => $lang,
			'kind'   => $kind,
			'type'   => $type,
			'slug'   => $slug,
			'id'     => $id,
			'reason' => $reason,
			'other'  => $other,
		];
	}

	/**
	 * A stored _prev list (serialized array) as a clean string list.
	 *
	 * @return string[]
	 */
	private static function unserialize_list( mixed $value ): array {
		$value = maybe_unserialize( $value );
		if ( ! is_array( $value ) ) {
			return is_string( $value ) && '' !== $value ? [ $value ] : [];
		}
		return array_values( array_filter( array_map( 'strval', $value ) ) );
	}

	/**
	 * Forgets request caches (tests).
	 */
	public static function reset(): void {
		self::$maps     = [];
		self::$reserved = null;
		self::$wc_pages = null;
	}
}
