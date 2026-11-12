<?php
/**
 * Permanent redirect of plain-HTTP storefront requests to HTTPS.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Security;

/**
 * The live host served the whole shop over http:// with 200 (2026-11-12), so every page had an
 * insecure duplicate. When the site address is https, a GET or HEAD request that arrived over
 * plain HTTP is sent to the same path on https with a 301. wp-admin is already covered by
 * FORCE_SSL_ADMIN, which works on this host, so is_ssl() is reliable there; a request a proxy
 * marks as HTTPS (X-Forwarded-Proto) is never redirected, so a TLS-terminating proxy cannot
 * cause a loop. Define GADGETIM_FORCE_HTTPS as false in wp-config.php to switch it off.
 */
final class HttpsRedirect {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		if ( defined( 'GADGETIM_FORCE_HTTPS' ) && ! GADGETIM_FORCE_HTTPS ) {
			return;
		}
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 0 );
	}

	/**
	 * template_redirect (priority 0).
	 */
	public static function maybe_redirect(): void {
		if ( 'cli' === PHP_SAPI || wp_doing_cron() || wp_doing_ajax() ) {
			return;
		}
		$target = self::target( $_SERVER ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only; wp_safe_redirect() validates the host.
		if ( null === $target ) {
			return;
		}
		wp_safe_redirect( $target, 301, 'Gadgetim' );
		exit;
	}

	/**
	 * The https URL to send this request to, or null when it must be served as it is.
	 *
	 * @param array<string,mixed> $server The $_SERVER values of the request.
	 */
	public static function target( array $server ): ?string {
		$home = wp_parse_url( (string) get_option( 'home' ) );
		if ( ! is_array( $home ) || 'https' !== strtolower( (string) ( $home['scheme'] ?? '' ) ) || empty( $home['host'] ) ) {
			return null;
		}
		if ( self::arrived_secure( $server ) ) {
			return null;
		}
		$method = strtoupper( (string) ( $server['REQUEST_METHOD'] ?? 'GET' ) );
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return null;
		}
		$uri = (string) ( $server['REQUEST_URI'] ?? '/' );
		if ( '' === $uri || '/' !== $uri[0] ) {
			$uri = '/' . ltrim( $uri, '/' );
		}
		$port = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
		return 'https://' . $home['host'] . $port . $uri;
	}

	/**
	 * Whether the request reached PHP over HTTPS, directly or through a proxy that says so.
	 *
	 * @param array<string,mixed> $server The $_SERVER values of the request.
	 */
	private static function arrived_secure( array $server ): bool {
		$https = strtolower( (string) ( $server['HTTPS'] ?? '' ) );
		if ( '' !== $https && 'off' !== $https ) {
			return true;
		}
		if ( '443' === (string) ( $server['SERVER_PORT'] ?? '' ) ) {
			return true;
		}
		$forwarded = strtolower( (string) ( $server['HTTP_X_FORWARDED_PROTO'] ?? '' ) );
		if ( '' !== $forwarded && str_contains( $forwarded, 'https' ) ) {
			return true;
		}
		return 'on' === strtolower( (string) ( $server['HTTP_X_FORWARDED_SSL'] ?? '' ) );
	}
}
