<?php
/**
 * Early language detection, run when the plugin file loads (architecture §3.2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Support\Context;

/**
 * Detects the request language by path, then (read-only APIs only) by ?lang= / header, then
 * (storefront AJAX) by the referer prefix. REST and AJAX are recognised by path and query
 * because REST_REQUEST and WC_DOING_AJAX are not defined yet at plugin load.
 */
final class Request {

	/**
	 * Language of the current request.
	 */
	public static function detect(): string {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return Languages::DEFAULT;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			// Context only; the admin locale is NOT filtered (user_locale wins, §3.2).
			return Languages::DEFAULT;
		}
		if ( ! Languages::prefixed() ) {
			return Languages::DEFAULT;
		}

		$path = Context::path_without_home();
		$lang = self::lang_from_path( $path );
		if ( null !== $lang ) {
			// /en/wp-json/… is routable (parse_request strips the prefixed home), but the prefix
			// context must never reach wc/v3, wp/v2 or a write (§3.1): only read-only APIs keep it.
			$route = self::prefixed_rest_route( $path );
			if ( null !== $route && ! self::is_readonly_route( $route ) ) {
				return Languages::DEFAULT;
			}
			return $lang;
		}

		if ( self::is_readonly_api() ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, public endpoints.
			$q = '';
			if ( isset( $_GET['lang'] ) ) {
				$q = sanitize_key( wp_unslash( (string) $_GET['lang'] ) );
			} elseif ( isset( $_SERVER['HTTP_X_GADGETIM_LANG'] ) ) {
				$q = sanitize_key( wp_unslash( (string) $_SERVER['HTTP_X_GADGETIM_LANG'] ) );
			}
			// phpcs:enable
			if ( '' !== $q && Languages::exists( $q ) ) {
				return $q;
			}
		}

		if ( Context::is_wc_ajax() || wp_doing_ajax() ) {
			// Storefront AJAX: the language comes from the page that made the call.
			$referer = isset( $_SERVER['HTTP_REFERER'] ) ? (string) wp_unslash( $_SERVER['HTTP_REFERER'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$lang    = self::lang_from_referer( $referer );
			if ( null !== $lang ) {
				return $lang;
			}
		}

		return Languages::DEFAULT;
	}

	/**
	 * Language whose prefix starts a request path ('/en/shop/' -> 'en'), or null.
	 *
	 * @param string $path Path with the install sub-directory already stripped.
	 */
	public static function lang_from_path( string $path ): ?string {
		$pattern = self::prefix_pattern();
		if ( '' === $pattern ) {
			return null;
		}
		if ( preg_match( '#^/(' . $pattern . ')(?:/|$)#', $path, $m ) ) {
			return Languages::from_prefix( $m[1] );
		}
		return null;
	}

	/**
	 * Language of a referer URL ('http://host/en/shop/' -> 'en'), or null.
	 */
	public static function lang_from_referer( string $referer ): ?string {
		$pattern = self::prefix_pattern();
		if ( '' === $pattern || '' === $referer ) {
			return null;
		}
		$home_path = rtrim( (string) ( wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) ?? '' ), '/' );
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://[^/]+' . preg_quote( $home_path, '#' ) . '/(' . $pattern . ')(?:/|$|[?\#])#i', $referer, $m ) ) {
			return Languages::from_prefix( $m[1] );
		}
		return null;
	}

	/**
	 * GET/HEAD to /wp-json/wc/store/* or /wp-json/gadgetim/v1/* (or ?rest_route= to those).
	 * Only these honour ?lang= and X-Gadgetim-Lang (§3.1): never wc/v3, wp/v2 or any write.
	 */
	public static function is_readonly_api(): bool {
		$route = Context::rest_route();
		if ( '' === $route ) {
			$route = (string) self::prefixed_rest_route( Context::path_without_home() );
		}
		return '' !== $route && self::is_readonly_route( $route );
	}

	/**
	 * Whether a GET/HEAD request to this REST route may carry a language context.
	 */
	private static function is_readonly_route( string $route ): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( (string) $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return false;
		}
		return str_starts_with( $route, '/wc/store/' ) || str_starts_with( $route, '/gadgetim/v1/' );
	}

	/**
	 * REST route of a language-prefixed path ('/en/wp-json/wc/store/v1/cart' -> '/wc/store/v1/cart'),
	 * or of ?rest_route= on a prefixed URL; null when the path is not a REST request.
	 */
	private static function prefixed_rest_route( string $path ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rest_route'] ) ) {
			return Context::rest_route();
		}
		$pattern = self::prefix_pattern();
		if ( '' === $pattern ) {
			return null;
		}
		$rest = '/' . trim( rest_get_url_prefix(), '/' );
		if ( preg_match( '#^/(?:' . $pattern . ')(' . preg_quote( $rest, '#' ) . ')(/.*)?$#', $path, $m ) ) {
			return isset( $m[2] ) && '' !== $m[2] ? $m[2] : '/';
		}
		return null;
	}

	/**
	 * Alternation of the configured prefixes for a regex, or '' when none.
	 */
	private static function prefix_pattern(): string {
		$prefixes = array_keys( Languages::prefixed() );
		if ( ! $prefixes ) {
			return '';
		}
		return implode( '|', array_map( static fn( string $p ): string => preg_quote( $p, '#' ), $prefixes ) );
	}
}
