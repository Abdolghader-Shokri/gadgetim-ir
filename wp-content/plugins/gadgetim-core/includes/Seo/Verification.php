<?php
/**
 * Search engine ownership files served by WordPress.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * The old site proved ownership of https://gadgetim.ir/ in Google Search Console with the file
 * /google12fac158692d4dbf.html in the web root. The move to this site on 2026-11-07 copied only
 * WordPress, so the file has answered 404 since then (found 2026-11-20) and the property loses its
 * verified owner at Google's next check. The plugin now answers that path itself, so the proof
 * cannot be lost again by a move or an upload. Other files can be added with the filter.
 */
final class Verification {

	/**
	 * Google HTML verification file names without ".html" (google + 16 hex characters).
	 */
	public const GOOGLE_FILES = [ 'google12fac158692d4dbf' ];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'serve' ], 0 );
	}

	/**
	 * Allowed Google file names.
	 *
	 * @return string[]
	 */
	public static function google_files(): array {
		/**
		 * Filters the Google Search Console HTML verification files the site answers.
		 *
		 * @param string[] $files Names like "google12fac158692d4dbf" (without ".html").
		 */
		$files = (array) apply_filters( 'gadgetim_seo_google_verification_files', self::GOOGLE_FILES );
		return array_values( array_filter( array_map( 'strval', $files ), static fn( string $f ): bool => 1 === preg_match( '/^google[0-9a-f]{16}$/', $f ) ) );
	}

	/**
	 * init (priority 0): answers /googleXXXXXXXXXXXXXXXX.html with the line Google expects.
	 */
	public static function serve(): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never printed.
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! preg_match( '#/(google[0-9a-f]{16})\.html$#', $path, $m ) ) {
			return;
		}
		$home_path = rtrim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		if ( $path !== $home_path . '/' . $m[1] . '.html' || ! in_array( $m[1], self::google_files(), true ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo 'google-site-verification: ' . $m[1] . '.html'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed text and a validated name.
		exit;
	}
}
