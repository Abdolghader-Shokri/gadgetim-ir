<?php
/**
 * Virtual language sub-directory: two-phase home_url prefixing (architecture §3.2, D2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Cache;
use WP;
use WP_Post;
use WP_Rewrite;
use WP_Term;

/**
 * The home_url filter adds /en or /ar for the current language, but only after
 * do_parse_request regenerated the rewrite rules with the unprefixed home (otherwise the
 * persisted rules would lack robots.txt / favicon.ico / sitemap entries). Inside
 * Languages::with_lang() the prefix is always active.
 */
final class Urls {

	/** Paths that are never prefixed (§3.1). */
	public const EXCLUDED = '#^(wp-json|wp-admin|wp-login\.php|wp-comments-post\.php|wp-content|wp-includes|xmlrpc\.php|wp-cron\.php|wp-signup\.php|wp-activate\.php|wp-trackback\.php)#';

	private static bool $enabled = false;

	private static int $forced = 0;

	private static ?array $home = null;

	/**
	 * Hooks (called once from Module::register()).
	 */
	public static function register(): void {
		add_filter( 'do_parse_request', [ self::class, 'on_do_parse_request' ], 1, 2 );
		add_filter( 'home_url', [ self::class, 'filter_home_url' ], 10, 3 );
		add_filter( 'pre_update_option_rewrite_rules', [ self::class, 'protect_rewrite_rules' ], 10, 1 );
		add_filter( 'gadgetim_cache_purge_urls', [ self::class, 'expand_purge_urls' ], 10, 3 );
	}

	/**
	 * Phase 2 starts: from now on home_url() carries the prefix of the detected language.
	 */
	public static function enable_prefix(): void {
		self::$enabled = true;
	}

	/**
	 * Turns the request-level prefixing off again (tests).
	 */
	public static function disable_prefix(): void {
		self::$enabled = false;
	}

	/**
	 * Whether home_url() is being prefixed right now (request phase 2, or inside with_lang()).
	 */
	public static function prefix_enabled(): bool {
		return self::$enabled || self::$forced > 0;
	}

	/**
	 * Languages::force() entered a context: prefixing is on regardless of the request phase.
	 */
	public static function push_forced(): void {
		self::$forced++;
	}

	/**
	 * Languages::restore() left a context.
	 */
	public static function pop_forced(): void {
		self::$forced = max( 0, self::$forced - 1 );
	}

	/**
	 * do_parse_request (priority 1): lazy rewrite-rule regeneration happens here, with the
	 * UNPREFIXED home, then the prefix is switched on for WP::parse_request().
	 *
	 * @param bool $do Whether to parse the request.
	 * @param WP   $wp The WP object.
	 */
	public static function on_do_parse_request( $do, $wp = null ) {
		global $wp_rewrite;
		if ( $wp_rewrite instanceof WP_Rewrite ) {
			$wp_rewrite->wp_rewrite_rules();
		}
		self::enable_prefix();
		return $do;
	}

	/**
	 * Loads (and, when missing, regenerates) the rewrite rules in Persian context so a later
	 * flush never persists rules built against a prefixed home. Cheap when the option exists.
	 */
	public static function prime_rewrite_rules(): void {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof WP_Rewrite || ! empty( $wp_rewrite->rules ) || ! $wp_rewrite->using_permalinks() ) {
			return;
		}
		if ( Languages::DEFAULT !== Languages::current() ) {
			Languages::with_lang( Languages::DEFAULT, [ self::class, 'prime_rewrite_rules' ] );
			return;
		}
		$wp_rewrite->wp_rewrite_rules();
	}

	/**
	 * pre_update_option_rewrite_rules: a flush that happens while a non-Persian prefix is active
	 * is regenerated in Persian context before it is saved (safety net for §3.2).
	 *
	 * @param mixed $value Rules about to be saved.
	 */
	public static function protect_rewrite_rules( $value ) {
		if ( ! is_array( $value ) || ! $value || ! self::prefix_enabled() || Languages::DEFAULT === Languages::current() ) {
			return $value;
		}
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof WP_Rewrite ) {
			return $value;
		}
		$rules = Languages::with_lang( Languages::DEFAULT, static fn() => $wp_rewrite->rewrite_rules() );
		return is_array( $rules ) && $rules ? $rules : $value;
	}

	/**
	 * home_url filter (§3.2): prefixes the current language's sub-directory.
	 *
	 * @param string      $url    Full URL (or a root-relative path for scheme 'relative').
	 * @param string      $path   Path passed to home_url().
	 * @param string|null $scheme Scheme passed to home_url().
	 */
	public static function filter_home_url( $url, $path = '', $scheme = null ) {
		if ( ! is_string( $url ) || '' === $url || ! self::prefix_enabled() ) {
			return $url;
		}
		$lang = Languages::current();
		if ( Languages::DEFAULT === $lang || in_array( $scheme, [ 'rest', 'json' ], true ) ) {
			return $url;
		}
		$prefix = Languages::prefix( $lang );
		if ( '' === $prefix ) {
			return $url;
		}
		$p = ltrim( (string) $path, '/' );
		if ( '' !== $p && preg_match( self::EXCLUDED, $p ) ) {
			return $url;
		}
		return self::apply_prefix( $url, $prefix, 'relative' === $scheme );
	}

	/**
	 * Inserts /{prefix} after the home base of a URL (absolute, protocol-relative or
	 * root-relative). Already prefixed URLs and URLs on other hosts are returned untouched.
	 */
	public static function apply_prefix( string $url, string $prefix, bool $relative = false ): string {
		if ( '' === $prefix || '' === $url ) {
			return $url;
		}
		$home = self::home();

		if ( $relative || ( '/' === $url[0] && ! str_starts_with( $url, '//' ) ) ) {
			// WC_AJAX::get_endpoint() builds from home_url( '/', 'relative' ): "/" -> "/en/".
			$base = $home['path'];
			$rest = $url;
			if ( '' !== $base && ( $url === $base || str_starts_with( $url, $base . '/' ) || str_starts_with( $url, $base . '?' ) ) ) {
				$rest = substr( $url, strlen( $base ) );
			} else {
				$base = '';
			}
			if ( $rest === "/$prefix" || str_starts_with( $rest, "/$prefix/" ) || str_starts_with( $rest, "/$prefix?" ) ) {
				return $url;
			}
			if ( '' === $rest || '/' !== $rest[0] ) {
				$rest = '/' . $rest;
			}
			return $base . "/$prefix" . $rest;
		}

		$bare = preg_replace( '#^https?:#i', '', $url ) ?? $url;
		if ( ! str_starts_with( $bare, $home['bare'] ) ) {
			return $url;
		}
		$rest = substr( $bare, strlen( $home['bare'] ) );
		if ( '' !== $rest && ! in_array( $rest[0], [ '/', '?', '#' ], true ) ) {
			return $url; // Another host that merely starts with ours (gadgetim.local.example).
		}
		if ( $rest === "/$prefix" || str_starts_with( $rest, "/$prefix/" ) || str_starts_with( $rest, "/$prefix?" ) || str_starts_with( $rest, "/$prefix#" ) ) {
			return $url;
		}
		$scheme_prefix = '';
		if ( str_starts_with( $url, 'https:' ) ) {
			$scheme_prefix = 'https:';
		} elseif ( str_starts_with( $url, 'http:' ) ) {
			$scheme_prefix = 'http:';
		}
		return $scheme_prefix . $home['bare'] . "/$prefix" . $rest;
	}

	/**
	 * The language variant of a Persian URL by prefixing (slugs are NOT translated; for
	 * object URLs use Languages::with_lang( $lang, fn() => get_permalink( $id ) )).
	 */
	public static function localize( string $url, string $lang ): string {
		$prefix = Languages::prefix( $lang );
		if ( '' === $prefix ) {
			return self::strip_prefixes( $url );
		}
		return self::apply_prefix( self::strip_prefixes( $url ), $prefix );
	}

	/**
	 * Removes any language prefix from a URL or path ('/en/shop/' -> '/shop/').
	 */
	public static function strip_prefixes( string $url ): string {
		$prefixes = array_keys( Languages::prefixed() );
		if ( ! $prefixes ) {
			return $url;
		}
		$alt  = implode( '|', array_map( static fn( string $p ): string => preg_quote( $p, '#' ), $prefixes ) );
		$home = self::home();
		if ( '/' === ( $url[0] ?? '' ) && ! str_starts_with( $url, '//' ) ) {
			$base = '' !== $home['path'] && str_starts_with( $url, $home['path'] . '/' ) ? $home['path'] : '';
			return $base . ( preg_replace( '#^/(' . $alt . ')(?=/|$|[?\#])#', '', substr( $url, strlen( $base ) ) ) ?? $url );
		}
		$bare = preg_replace( '#^https?:#i', '', $url ) ?? $url;
		if ( ! str_starts_with( $bare, $home['bare'] ) ) {
			return $url;
		}
		$scheme = substr( $url, 0, strlen( $url ) - strlen( $bare ) );
		$rest   = substr( $bare, strlen( $home['bare'] ) );
		$rest   = preg_replace( '#^/(' . $alt . ')(?=/|$|[?\#])#', '', $rest ) ?? $rest;
		return $scheme . $home['bare'] . ( '' === $rest ? '/' : $rest );
	}

	/**
	 * Path of the current request without the install sub-directory and without the language prefix.
	 */
	public static function request_path_unprefixed(): string {
		$path = \Gadgetim\Core\Support\Context::path_without_home();
		$lang = Request::lang_from_path( $path );
		if ( null !== $lang ) {
			$prefix = Languages::prefix( $lang );
			$path   = (string) substr( $path, strlen( '/' . $prefix ) );
		}
		return '' === $path ? '/' : $path;
	}

	/**
	 * Replaces one run of path segments ("parent/child") with another in a URL, matching
	 * the last occurrence that ends at the path end (trailing slash, query or fragment).
	 */
	public static function replace_segments( string $url, string $native, string $translated ): string {
		$native     = trim( $native, '/' );
		$translated = trim( $translated, '/' );
		if ( '' === $native || '' === $translated || $native === $translated ) {
			return $url;
		}
		$pattern = '#/' . preg_quote( $native, '#' ) . '(?=/?(?:[?\#]|$))#u';
		$result  = preg_replace( $pattern, '/' . str_replace( [ '\\', '$' ], [ '\\\\', '\\$' ], $translated ), $url, 1 );
		return is_string( $result ) ? $result : $url;
	}

	/**
	 * Runs $fn in the language context of an order (Checkout\ReturnUrls uses this for
	 * gateway return/cancel/checkout URLs, §3.8).
	 *
	 * @param \WC_Order|int $order Order or order ID.
	 * @param callable      $fn    Callback building URLs with home_url()-based functions.
	 */
	public static function for_order( $order, callable $fn ): mixed {
		return Languages::with_lang( Orders::lang( $order ), $fn );
	}

	/**
	 * gadgetim_cache_purge_urls: adds the /en/ and /ar/ variants of every URL plus the
	 * language-correct object URLs (translated slugs) for the post/term being purged.
	 *
	 * @param string[] $urls    URLs built in Persian context.
	 * @param int      $post_id Post ID or 0.
	 * @param array    $context 'type', 'term_id', 'taxonomy'.
	 * @return string[]
	 */
	public static function expand_purge_urls( $urls, $post_id = 0, $context = [] ): array {
		$urls    = array_values( array_filter( array_map( 'strval', (array) $urls ) ) );
		$post_id = absint( $post_id );
		$context = is_array( $context ) ? $context : [];
		$out     = $urls;

		foreach ( Languages::secondary_codes() as $lang ) {
			if ( '' === Languages::prefix( $lang ) ) {
				continue;
			}
			$variants = Languages::with_lang(
				$lang,
				static function () use ( $urls, $post_id, $context, $lang ): array {
					$v = [];
					foreach ( $urls as $url ) {
						$v[] = self::localize( $url, $lang );
					}
					$v[] = home_url( '/' );
					if ( function_exists( 'wc_get_page_permalink' ) ) {
						$shop = wc_get_page_permalink( 'shop' );
						if ( is_string( $shop ) && '' !== $shop ) {
							$v[] = $shop;
						}
					}
					if ( $post_id > 0 ) {
						$permalink = get_permalink( $post_id );
						if ( is_string( $permalink ) && '' !== $permalink ) {
							$v[] = $permalink;
						}
						$post_type = (string) get_post_type( $post_id );
						if ( '' !== $post_type ) {
							foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
								if ( ! is_taxonomy_viewable( $taxonomy ) ) {
									continue;
								}
								$terms = get_the_terms( $post_id, $taxonomy );
								if ( is_array( $terms ) ) {
									foreach ( $terms as $term ) {
										$v = array_merge( $v, self::term_urls( $term ) );
									}
								}
							}
						}
					}
					if ( ! empty( $context['term_id'] ) && ! empty( $context['taxonomy'] ) ) {
						$term = get_term( (int) $context['term_id'], (string) $context['taxonomy'] );
						if ( $term instanceof WP_Term ) {
							$v = array_merge( $v, self::term_urls( $term ) );
						}
					}
					return $v;
				}
			);
			$out = array_merge( $out, (array) $variants );
		}

		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Term archive URL plus ancestors (in the current language context).
	 *
	 * @return string[]
	 */
	private static function term_urls( WP_Term $term ): array {
		if ( method_exists( Cache::class, 'term_urls' ) ) {
			return Cache::term_urls( $term );
		}
		$link = get_term_link( $term );
		return is_string( $link ) ? [ $link ] : [];
	}

	/**
	 * Home option parts, computed once: bare (scheme-less) URL and path ('' or '/sub').
	 *
	 * @return array{bare:string,path:string}
	 */
	private static function home(): array {
		if ( null === self::$home ) {
			$home       = untrailingslashit( (string) get_option( 'home' ) );
			$bare       = preg_replace( '#^https?:#i', '', $home ) ?? $home;
			$path       = rtrim( (string) ( wp_parse_url( $home, PHP_URL_PATH ) ?? '' ), '/' );
			self::$home = [
				'bare' => $bare,
				'path' => $path,
			];
		}
		return self::$home;
	}

	/**
	 * Forgets the cached home parts (tests that change the home option).
	 */
	public static function reset(): void {
		self::$home    = null;
		self::$enabled = false;
		self::$forced  = 0;
	}
}
