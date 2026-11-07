<?php
/**
 * Serves the old-site redirects stored in the gadgetim_redirects option (written by the importer,
 * Import\Redirects::apply(), from data/redirects.csv): removed blog posts, dropped tags, renamed
 * brands and old pages answer with a 301 to their new home instead of a 404.
 *
 * Only requests WordPress could not resolve are looked up, so a rule can never shadow a live page,
 * and the (non-autoloaded) option is read on 404s only.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

use Gadgetim\Core\Support\Normalize;
use Gadgetim\Core\Support\Options;

/**
 * 404 → 301 for known old URLs.
 */
final class Redirects {

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
		$rules = Options::redirects();
		if ( ! $rules ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only used as a lookup key after Normalize::path().
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = Normalize::path( $uri );
		$rule = $rules[ $path ] ?? $rules[ trailingslashit( $path ) ] ?? $rules[ untrailingslashit( $path ) ] ?? null;
		if ( ! is_array( $rule ) ) {
			return;
		}
		$status = (int) ( $rule[1] ?? 301 );
		if ( 410 === $status ) {
			status_header( 410 );
			return;
		}
		$to = (string) ( $rule[0] ?? '' );
		if ( '' === $to || '/' !== $to[0] ) {
			return;
		}
		$query = isset( $_SERVER['QUERY_STRING'] ) ? (string) wp_unslash( $_SERVER['QUERY_STRING'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- re-attached verbatim and sanitised by wp_safe_redirect().
		$url   = home_url( $to ) . ( '' !== $query ? '?' . $query : '' );
		if ( wp_safe_redirect( $url, in_array( $status, [ 301, 302, 307, 308 ], true ) ? $status : 301, 'Gadgetim' ) ) {
			exit;
		}
	}
}
