<?php
/**
 * Basic security headers on storefront responses.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Security;

/**
 * The live store answered without any security header (2026-11-15, checklist TEC.11-03):
 *
 * - X-Content-Type-Options: browsers must not guess a file's type.
 * - Referrer-Policy: other sites see the store's address, never the full path of a page.
 * - X-Frame-Options: no other site can put the shop inside a frame.
 * - Strict-Transport-Security: a browser that has seen the store goes straight to https next
 *   time. One year, without includeSubDomains, so mail and panel subdomains on plain HTTP are
 *   unaffected, and only on https responses of a production copy.
 *
 * WordPress already sends X-Frame-Options on wp-admin and the login page. Define
 * GADGETIM_SECURITY_HEADERS as false in wp-config.php to switch all of them off, or filter
 * gadgetim_security_headers.
 */
final class Headers {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		if ( defined( 'GADGETIM_SECURITY_HEADERS' ) && ! GADGETIM_SECURITY_HEADERS ) {
			return;
		}
		add_action( 'send_headers', [ self::class, 'send' ] );
	}

	/**
	 * send_headers: prints the headers unless output already started.
	 */
	public static function send(): void {
		if ( headers_sent() ) {
			return;
		}
		foreach ( self::headers( is_ssl(), wp_get_environment_type() ) as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}

	/**
	 * The headers for a response.
	 *
	 * @param bool   $secure      The request arrived over https.
	 * @param string $environment wp_get_environment_type().
	 * @return array<string,string>
	 */
	public static function headers( bool $secure, string $environment ): array {
		$headers = [
			'X-Content-Type-Options' => 'nosniff',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'X-Frame-Options'        => 'SAMEORIGIN',
		];
		if ( $secure && 'production' === $environment ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000';
		}

		/**
		 * Filters the security headers of storefront responses ('' removes one).
		 *
		 * @param array<string,string> $headers Header name => value.
		 */
		$headers = (array) apply_filters( 'gadgetim_security_headers', $headers );
		return array_filter( array_map( 'strval', $headers ), static fn( string $v ): bool => '' !== $v && ! preg_match( '/[\r\n]/', $v ) );
	}
}
