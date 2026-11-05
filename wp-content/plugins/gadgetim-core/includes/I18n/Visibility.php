<?php
/**
 * Visibility gate, fallbacks and the launch gate (architecture §3.5, D10).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Context as SupportContext;
use WP_Post;
use WP_Query;
use WP_Term;

/**
 * Under /en/ and /ar/ a post exists only when _gtm_status_{lang} = published: every listing
 * query (archives, shop, search, related, upsells, cross-sells, home sections, REST search)
 * is gated, untranslated singular pages 404 (or show Persian with noindex when the
 * i18n.fallback setting is 'show'), empty archives are noindex, and a non-public language
 * is Disallow'ed in robots.txt and noindex everywhere.
 */
final class Visibility {

	private static bool $fallback = false;

	private static bool $empty_archive = false;

	/** @var array<string,bool|int> Per-request term content flags and published counts. */
	private static array $term_content = [];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'pre_get_posts', [ self::class, 'gate_query' ], 20 );
		add_filter( 'woocommerce_related_products', [ self::class, 'filter_related' ], 10, 3 );
		add_filter( 'woocommerce_product_get_upsell_ids', [ self::class, 'filter_id_list' ], 10, 2 );
		add_filter( 'woocommerce_product_get_cross_sell_ids', [ self::class, 'filter_id_list' ], 10, 2 );
		add_action( 'template_redirect', [ self::class, 'on_template_redirect' ], 0 );
		add_filter( 'wp_robots', [ self::class, 'filter_robots' ], 20, 1 );
		add_filter( 'robots_txt', [ self::class, 'filter_robots_txt' ], 20, 2 );
		// Theme-owned term markup (mega menu, lane bar, home tiles, brand strip, archive chips).
		add_filter( 'gadgetim_menu_terms', [ self::class, 'filter_theme_terms' ], 10, 2 );
		add_filter( 'gadgetim_brand_terms', [ self::class, 'filter_theme_terms' ], 10, 2 );
		add_filter( 'gadgetim_term_count', [ self::class, 'filter_term_count' ], 10, 3 );
	}

	/**
	 * Post types the gate applies to (the translatable ones).
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		return PostTranslations::post_types();
	}

	/**
	 * pre_get_posts (priority 20, after WC_Query): adds the published clause to listing
	 * queries of translatable types when the language is not Persian.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function gate_query( $query ): void {
		if ( ! $query instanceof WP_Query || ! self::gate_active() ) {
			return;
		}
		if ( $query->get( 'gadgetim_skip_gate' ) || $query->get( 'gadgetim_gated' ) ) {
			return;
		}
		if ( $query->is_main_query() && ( $query->is_singular() || $query->is_404() ) ) {
			return; // Singular items are decided at template_redirect (404 or Persian fallback).
		}
		$types = self::effective_types( $query );
		if ( ! $types ) {
			return;
		}
		$gated = self::post_types();
		foreach ( $types as $type ) {
			if ( ! in_array( $type, $gated, true ) ) {
				return; // Mixed or non-translatable types: untouched.
			}
		}
		$lang = Languages::current();
		$meta = $query->get( 'meta_query' );
		$meta = is_array( $meta ) ? $meta : [];
		$meta[] = [
			'key'     => PostTranslations::key( 'status', $lang ),
			'value'   => 'published',
			'compare' => '=',
		];
		$query->set( 'meta_query', $meta );
		$query->set( 'gadgetim_gated', $lang );
	}

	/**
	 * The post types a query will return, derived like WP_Query::get_posts() does when
	 * post_type is empty. [] means "do not touch".
	 *
	 * @return string[]
	 */
	private static function effective_types( WP_Query $query ): array {
		$types = $query->get( 'post_type' );
		if ( is_string( $types ) && '' !== $types ) {
			$types = [ $types ];
		}
		if ( is_array( $types ) && $types ) {
			$types = array_values( array_map( 'strval', $types ) );
			if ( in_array( 'any', $types, true ) ) {
				if ( $query->is_search() ) {
					$query->set( 'post_type', self::post_types() );
					return self::post_types();
				}
				return [];
			}
			return $types;
		}
		if ( $query->is_attachment() ) {
			return [];
		}
		if ( $query->is_page() ) {
			return [ 'page' ];
		}
		if ( $query->is_search() ) {
			$query->set( 'post_type', self::post_types() );
			return self::post_types();
		}
		if ( $query->is_tax() || $query->is_category() || $query->is_tag() ) {
			return self::post_types(); // Only translatable types carry the meta; others never appear here.
		}
		return [ 'post' ];
	}

	/**
	 * woocommerce_related_products: drops untranslated products.
	 *
	 * @param mixed $ids        Related product IDs.
	 * @param mixed $product_id Product ID.
	 * @param mixed $args       Args.
	 */
	public static function filter_related( $ids, $product_id = 0, $args = [] ) {
		if ( ! is_array( $ids ) || ! self::gate_active() ) {
			return $ids;
		}
		return self::filter_ids( $ids );
	}

	/**
	 * woocommerce_product_get_upsell_ids / cross_sell_ids (view context).
	 *
	 * @param mixed $ids     IDs.
	 * @param mixed $product Product.
	 */
	public static function filter_id_list( $ids, $product = null ) {
		if ( ! is_array( $ids ) || ! $ids || ! self::gate_active() ) {
			return $ids;
		}
		return self::filter_ids( $ids );
	}

	/**
	 * Whether the listing gate applies right now: a non-Persian language context, no explicit
	 * suspension (importer, reprice), not a wp-admin screen. Unlike output translation, the
	 * gate also applies in CLI/cron/REST reads, because it decides what exists in a language.
	 */
	public static function gate_active(): bool {
		if ( Languages::is_default() || SupportContext::$suspend_translations > 0 ) {
			return false;
		}
		return ! ( is_admin() && ! wp_doing_ajax() );
	}

	/**
	 * Keeps only the products published in the language (Persian: unchanged).
	 *
	 * @param int[] $ids Product IDs.
	 * @return int[]
	 */
	public static function filter_ids( array $ids, ?string $lang = null, string $type = 'product' ): array {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return array_values( array_map( 'intval', $ids ) );
		}
		$published = array_fill_keys( Slugs::published_ids( $lang, $type ), true );
		$out       = [];
		foreach ( $ids as $id ) {
			if ( isset( $published[ (int) $id ] ) ) {
				$out[] = (int) $id;
			}
		}
		return $out;
	}

	/**
	 * Published post IDs for post__in loops (theme home sections).
	 *
	 * @return int[]
	 */
	public static function published_ids( ?string $lang = null, string $type = 'product' ): array {
		return Slugs::published_ids( $lang ?? Languages::current(), $type );
	}

	/**
	 * Whether a post is visible in a language (Persian: always).
	 */
	public static function is_visible( int $post_id, ?string $lang = null ): bool {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return true;
		}
		if ( Slugs::is_wc_page( $post_id ) ) {
			return true;
		}
		return PostTranslations::is_published( $post_id, $lang );
	}

	/**
	 * Term-level gate for tiles, mega-menu and home sections: _gtm_status_{lang} = published.
	 */
	public static function term_visible( WP_Term $term, ?string $lang = null ): bool {
		$lang ??= Languages::current();
		return Languages::DEFAULT === $lang || TermTranslations::is_published( $term->term_id, $lang );
	}

	/**
	 * Whether the term's archive would list at least one published item in the language
	 * (children included for hierarchical taxonomies). One query per term and language.
	 */
	public static function term_has_content( WP_Term $term, ?string $lang = null ): bool {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return true;
		}
		$cache_key = $term->term_id . '|' . $lang;
		if ( isset( self::$term_content[ $cache_key ] ) ) {
			return self::$term_content[ $cache_key ];
		}
		$type      = self::type_for_taxonomy( $term->taxonomy );
		$published = Slugs::published_ids( $lang, $type );
		$result    = false;
		if ( $published ) {
			$term_ids = [ $term->term_id ];
			if ( is_taxonomy_hierarchical( $term->taxonomy ) ) {
				$children = get_term_children( $term->term_id, $term->taxonomy );
				if ( is_array( $children ) ) {
					$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
				}
			}
			$objects = get_objects_in_term( $term_ids, $term->taxonomy );
			if ( is_array( $objects ) && $objects ) {
				$result = (bool) array_intersect( array_map( 'intval', $objects ), $published );
			}
		}
		self::$term_content[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Keeps the terms visible in the language (optionally only those with content).
	 *
	 * @param WP_Term[] $terms Terms.
	 * @return WP_Term[]
	 */
	public static function filter_terms( array $terms, ?string $lang = null, bool $require_content = false ): array {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return array_values( $terms );
		}
		$out = [];
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term || ! self::term_visible( $term, $lang ) ) {
				continue;
			}
			if ( $require_content && ! self::term_has_content( $term, $lang ) ) {
				continue;
			}
			$out[] = $term;
		}
		return $out;
	}

	/**
	 * gadgetim_menu_terms / gadgetim_brand_terms (theme): only terms published in the language
	 * that list at least one published item.
	 *
	 * @param mixed $terms Terms.
	 * @param mixed $lang  Language code.
	 * @return mixed
	 */
	public static function filter_theme_terms( $terms, $lang = null ) {
		if ( ! is_array( $terms ) ) {
			return $terms;
		}
		$lang = is_string( $lang ) && Languages::exists( $lang ) ? $lang : Languages::current();
		return self::filter_terms( $terms, $lang, true );
	}

	/**
	 * gadgetim_term_count (theme): the number of the term's own items published in the language
	 * (Persian: the native count).
	 *
	 * @param mixed $count Native count.
	 * @param mixed $term  Term.
	 * @param mixed $lang  Language code.
	 * @return mixed
	 */
	public static function filter_term_count( $count, $term = null, $lang = null ) {
		if ( ! $term instanceof WP_Term ) {
			return $count;
		}
		$lang = is_string( $lang ) && Languages::exists( $lang ) ? $lang : Languages::current();
		return Languages::DEFAULT === $lang ? $count : self::term_count( $term, $lang );
	}

	/**
	 * Items of one term (children not included, like WP_Term::$count) published in a language.
	 */
	public static function term_count( WP_Term $term, ?string $lang = null ): int {
		$lang ??= Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return (int) $term->count;
		}
		$cache_key = 'n|' . $term->term_id . '|' . $lang;
		if ( isset( self::$term_content[ $cache_key ] ) ) {
			return (int) self::$term_content[ $cache_key ];
		}
		$published = Slugs::published_ids( $lang, self::type_for_taxonomy( $term->taxonomy ) );
		$count     = 0;
		if ( $published ) {
			$objects = get_objects_in_term( $term->term_id, $term->taxonomy );
			if ( is_array( $objects ) && $objects ) {
				$count = count( array_intersect( array_map( 'intval', $objects ), array_map( 'intval', $published ) ) );
			}
		}
		self::$term_content[ $cache_key ] = $count;
		return $count;
	}

	/**
	 * Post type whose items a taxonomy classifies ('product' for the WooCommerce taxonomies).
	 */
	public static function type_for_taxonomy( string $taxonomy ): string {
		$tax = get_taxonomy( $taxonomy );
		if ( $tax && ! empty( $tax->object_type ) ) {
			foreach ( (array) $tax->object_type as $type ) {
				if ( in_array( $type, self::post_types(), true ) ) {
					return (string) $type;
				}
			}
		}
		return 'product';
	}

	/**
	 * template_redirect (priority 0): singular fallback (404 or Persian with noindex),
	 * term archives of untranslated terms, and empty-archive detection.
	 */
	public static function on_template_redirect(): void {
		if ( Languages::is_default() || is_admin() || is_feed() || is_robots() || is_favicon() || is_trackback() ) {
			return;
		}
		$lang = Languages::current();

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post || ! PostTranslations::supports( $post->post_type ) || Slugs::is_wc_page( $post->ID ) ) {
				return;
			}
			if ( PostTranslations::is_published( $post->ID, $lang ) ) {
				return;
			}
			if ( '' !== PostTranslations::status( $post->ID, $lang ) && current_user_can( 'edit_post', $post->ID ) ) {
				PostTranslations::allow_preview( $post->ID ); // Translators review drafts under the prefix.
				return;
			}
			self::apply_fallback();
			return;
		}

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term && TermTranslations::supports( $term->taxonomy ) && ! TermTranslations::is_published( $term->term_id, $lang ) ) {
				if ( '' !== TermTranslations::status( $term->term_id, $lang ) && current_user_can( 'edit_term', $term->term_id ) ) {
					return;
				}
				self::apply_fallback();
				return;
			}
		}

		if ( is_archive() || is_search() || is_home() || ( function_exists( 'is_shop' ) && is_shop() ) ) {
			global $wp_query;
			if ( $wp_query instanceof WP_Query && 0 === (int) $wp_query->found_posts && 0 === (int) $wp_query->post_count ) {
				self::$empty_archive = true;
			}
		}
	}

	/**
	 * Untranslated item under a prefix: 404 (default while not public) or Persian content
	 * with lang="fa" on the content blocks and noindex,follow ('show').
	 */
	private static function apply_fallback(): void {
		$mode = '404';
		if ( class_exists( Settings::class ) ) {
			$mode = (string) Settings::get( 'i18n.fallback', '404' );
		}
		if ( 'show' === $mode ) {
			self::$fallback = true;
			return;
		}
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Whether the current page shows Persian content as a fallback ('show' mode).
	 */
	public static function fallback_active(): bool {
		return self::$fallback;
	}

	/**
	 * Whether the current archive lists nothing in this language (theme shows the empty state).
	 */
	public static function is_empty_archive(): bool {
		return self::$empty_archive;
	}

	/**
	 * Language of the content blocks: 'fa' while the Persian fallback renders, else the current one.
	 */
	public static function content_lang(): string {
		return self::$fallback ? Languages::DEFAULT : Languages::current();
	}

	/**
	 * Direction of the content blocks.
	 */
	public static function content_dir(): string {
		return Languages::dir( self::content_lang() );
	}

	/**
	 * wp_robots: noindex,follow for non-public languages, fallback pages, draft previews
	 * and empty archives.
	 *
	 * @param mixed $robots Directives.
	 */
	public static function filter_robots( $robots ) {
		if ( ! is_array( $robots ) || Languages::is_default() ) {
			return $robots;
		}
		$noindex = ! Languages::is_public( Languages::current() ) || self::$fallback || self::$empty_archive;
		if ( ! $noindex && is_singular() ) {
			$noindex = PostTranslations::is_preview( (int) get_queried_object_id() );
		}
		if ( $noindex ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * robots_txt: Disallow non-public prefixes; list the sitemap of each public language.
	 *
	 * @param mixed $output Robots.txt body.
	 * @param mixed $public blog_public.
	 */
	public static function filter_robots_txt( $output, $public = true ) {
		if ( ! is_string( $output ) || ! $public ) {
			return $output;
		}
		$home_path = rtrim( (string) ( wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) ?? '' ), '/' );
		$disallow  = '';
		$sitemaps  = '';
		$enabled   = function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled();
		foreach ( Languages::secondary_codes() as $lang ) {
			$prefix = Languages::prefix( $lang );
			if ( '' === $prefix ) {
				continue;
			}
			if ( ! Languages::is_public( $lang ) ) {
				$disallow .= 'Disallow: ' . $home_path . '/' . $prefix . "/\n";
			} elseif ( $enabled ) {
				$sitemaps .= "\nSitemap: " . Languages::with_lang( $lang, static fn(): string => home_url( '/wp-sitemap.xml' ) ) . "\n";
			}
		}
		if ( '' !== $disallow ) {
			if ( preg_match( '/^User-agent: \*\R/m', $output ) ) {
				$output = preg_replace( '/^(User-agent: \*\R)/m', '$1' . $disallow, $output, 1 ) ?? $output;
			} else {
				$output = "User-agent: *\n" . $disallow . $output;
			}
		}
		return $output . $sitemaps;
	}

	/**
	 * Forgets request state (tests).
	 */
	public static function reset(): void {
		self::$fallback      = false;
		self::$empty_archive = false;
		self::$term_content  = [];
	}
}
