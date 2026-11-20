<?php
/**
 * Serves the old-site redirects stored in the gadgetim_redirects option (written by the importer,
 * Import\Redirects::apply(), from data/redirects.csv): removed blog posts, dropped tags, renamed
 * brands and old pages answer with a 301 to their new home instead of a 404.
 *
 * Old brand archives are mapped on their own, rule or not. The base moved from /brand/ to
 * Brands::base(), and the importer only wrote rows for renamed slugs, with targets still under
 * /brand/. So the old site's six /brand/ links were 404s (found 2026-11-17). So were the English and
 * Arabic brand links this site printed from 2026-11-07 to 11-12, before the base was pinned.
 *
 * Only requests WordPress could not resolve are looked up, so a rule can never shadow a live page,
 * and the (non-autoloaded) option is read on 404s only.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\Support\Normalize;
use Gadgetim\Core\Support\Options;
use WP_Term;

/**
 * 404 → 301 for known old URLs.
 */
final class Redirects {

	/**
	 * Brand archive bases this site no longer serves: the old site's, and the per-language ones
	 * WooCommerce printed before Brands pinned a single base («العلامة التجارية» with a %20 space).
	 */
	private const OLD_BRAND_BASES = [ 'brand', 'العلامة التجارية', 'العلامة-التجارية' ];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 0 );
	}

	/**
	 * template_redirect (0): redirect a 404 whose path is a known old URL.
	 */
	public static function maybe_redirect(): void {
		if ( ! is_404() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used as a lookup key after Normalize::path().
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path   = Normalize::path( $uri );
		$rules  = Options::redirects();
		$rule   = $rules[ $path ] ?? $rules[ trailingslashit( $path ) ] ?? $rules[ untrailingslashit( $path ) ] ?? null;
		$rule   = is_array( $rule ) ? $rule : null;
		$status = (int) ( $rule[1] ?? 301 );

		// An explicit 410 stays gone; any other path under an old brand base goes to the live term.
		if ( 410 !== $status ) {
			$brand = self::old_brand_url( $path, (string) ( $rule[0] ?? '' ) );
			if ( '' !== $brand ) {
				self::go( $brand, 301 );
				return;
			}
		}
		if ( '/order-tracking/' === trailingslashit( $path ) ) {
			$tracking = self::tracking_page_url();
			if ( '' !== $tracking ) {
				self::go( $tracking, 301 );
				return;
			}
		}
		if ( null === $rule ) {
			return;
		}
		if ( 410 === $status ) {
			status_header( 410 );
			return;
		}
		$to = (string) ( $rule[0] ?? '' );
		if ( '' === $to || '/' !== $to[0] ) {
			return;
		}
		self::go( home_url( $to ), in_array( $status, [ 301, 302, 307, 308 ], true ) ? $status : 301 );
	}

	/**
	 * The public order-tracking form (the page that holds [woocommerce_order_tracking], found by the
	 * theme's gadgetim_order_tracking_page_id()), or ''. The old site's /order-tracking/ was stored
	 * as a redirect to the account's order list, which asks guests to log in and is noindex; the form
	 * answers the same question without an account (deep check TEC.6-03, 2026-11-20).
	 */
	private static function tracking_page_url(): string {
		$id  = function_exists( 'gadgetim_order_tracking_page_id' ) ? (int) gadgetim_order_tracking_page_id() : 0;
		$url = $id > 0 ? get_permalink( $id ) : false;
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Archive URL of the brand that an old brand path names, or ''. A stored rule (a brand the
	 * importer renamed) still picks the slug, though its target has the old base too. A rule that
	 * points anywhere else wins. The link comes from the live term, so it has the current base and,
	 * through the I18n link filters, the language of the request.
	 *
	 * An old page number is dropped: page 2 of a brand with five products is a 404 here, so every old
	 * page lands on the first one. A brand without products lands on its archive too, which says so
	 * and links on (it is noindex, so it passes nothing in search; better for a visitor than a 404).
	 * A target equal to the request (a base set back to "brand" under Settings → Permalinks) is
	 * skipped, never redirected to itself.
	 *
	 * @param string $path    Normalised request path.
	 * @param string $rule_to Target of the stored rule for the path, '' when none.
	 */
	private static function old_brand_url( string $path, string $rule_to ): string {
		$parts = self::old_brand_parts( $path );
		if ( null === $parts ) {
			return '';
		}
		$slug = $parts[0];
		if ( '' !== $rule_to ) {
			$renamed = self::old_brand_parts( Normalize::path( $rule_to ) );
			if ( null === $renamed ) {
				return '';
			}
			$slug = $renamed[0];
		}
		$term = get_term_by( 'slug', $slug, Brands::TAXONOMY );
		if ( ! $term instanceof WP_Term ) {
			return '';
		}
		$link = get_term_link( $term );
		if ( ! is_string( $link ) || '' === $link ) {
			return '';
		}
		if ( Normalize::path( (string) wp_parse_url( $link, PHP_URL_PATH ) ) === Normalize::path( $path ) ) {
			return '';
		}
		return $link;
	}

	/**
	 * [ slug, page ] of a path under an old brand base, language prefix allowed
	 * ('/en/brand/anker/page/2/' → [ 'anker', 2 ]), or null.
	 *
	 * @return array{0:string,1:int}|null
	 */
	private static function old_brand_parts( string $path ): ?array {
		$segments = explode( '/', trim( $path, '/' ) );
		if ( count( $segments ) > 2 && null !== Languages::from_prefix( $segments[0] ) ) {
			array_shift( $segments );
		}
		$count = count( $segments );
		if ( 2 !== $count && 4 !== $count ) {
			return null;
		}
		if ( '' === $segments[1] || ! in_array( $segments[0], self::old_brand_bases(), true ) ) {
			return null;
		}
		if ( 4 === $count && ( 'page' !== $segments[2] || ! ctype_digit( $segments[3] ) ) ) {
			return null;
		}
		return [ $segments[1], 4 === $count ? (int) $segments[3] : 1 ];
	}

	/**
	 * OLD_BRAND_BASES as Normalize::path() leaves them: decoded, with Persian letters («العلامه التجاریه»).
	 *
	 * @return string[]
	 */
	private static function old_brand_bases(): array {
		static $bases = null;
		$bases ??= array_map( static fn( string $base ): string => trim( Normalize::path( '/' . $base . '/' ), '/' ), self::OLD_BRAND_BASES );
		return $bases;
	}

	/**
	 * Redirects with the request's query string re-attached; returns only when the redirect was refused.
	 */
	private static function go( string $url, int $status ): void {
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- re-attached verbatim and sanitised by wp_safe_redirect().
		if ( wp_safe_redirect( $url . ( '' !== $query ? '?' . $query : '' ), $status, 'Gadgetim' ) ) {
			exit;
		}
	}
}
