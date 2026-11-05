<?php
/**
 * Request context: storefront render window, translation suspension, REST/CLI/cron detection.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Answers "may output filters translate / convert digits right now?" (architecture §3.3, §4.5).
 */
final class Context {

	/**
	 * Translation suspension counter. Non-zero means every output filter must return
	 * the source untouched. Prefer with_suspended(); set around every write the plugin
	 * performs (importer, slug-map rebuild, reprice).
	 */
	public static int $suspend_translations = 0;

	private static bool $rendering = false;

	private static int $forced_render = 0;

	private static ?string $path = null;

	/**
	 * Hooks the render window. Called once from Plugin::boot().
	 */
	public static function register(): void {
		// Before WC_AJAX (priority 0) and Seo\Redirects (priority 1) so ?wc-ajax= fragments count as storefront output.
		add_action( 'template_redirect', [ self::class, 'begin_render' ], -1000 );
		add_action( 'shutdown', [ self::class, 'end_render' ], -1000 );
	}

	/**
	 * template_redirect: output starts.
	 */
	public static function begin_render(): void {
		self::$rendering = true;
	}

	/**
	 * shutdown: output ended.
	 */
	public static function end_render(): void {
		self::$rendering = false;
	}

	/**
	 * True between template_redirect and shutdown on a non-admin, non-REST, non-CLI,
	 * non-cron request, and inside with_render() (email rendering in a customer context).
	 */
	public static function is_storefront_render(): bool {
		if ( self::$forced_render > 0 ) {
			return true;
		}
		if ( ! self::$rendering ) {
			return false;
		}
		return ! is_admin() && ! self::is_rest_request() && ! self::is_cli() && ! self::is_cron();
	}

	/**
	 * Runs $fn with is_storefront_render() forced to true (I18n\Emails uses this).
	 */
	public static function with_render( callable $fn ): mixed {
		self::$forced_render++;
		try {
			return $fn();
		} finally {
			self::$forced_render--;
		}
	}

	/**
	 * Runs $fn with translations suspended (nestable, exception-safe).
	 */
	public static function with_suspended( callable $fn ): mixed {
		self::$suspend_translations++;
		try {
			return $fn();
		} finally {
			self::$suspend_translations = max( 0, self::$suspend_translations - 1 );
		}
	}

	/**
	 * Whether output filters must stay inert: explicit suspension, WP-CLI, cron,
	 * wp-admin (except AJAX), or a REST request with a writing method.
	 */
	public static function translations_suspended(): bool {
		if ( self::$suspend_translations > 0 || self::is_cli() || self::is_cron() ) {
			return true;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return true;
		}
		return self::is_rest_request() && ! self::is_read_method();
	}

	/**
	 * WP-CLI or any PHP CLI run (plain-PHP importer entry points included).
	 */
	public static function is_cli(): bool {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || PHP_SAPI === 'cli';
	}

	/**
	 * WP-Cron request.
	 */
	public static function is_cron(): bool {
		return wp_doing_cron() || ( defined( 'DOING_CRON' ) && DOING_CRON );
	}

	/**
	 * admin-ajax.php request.
	 */
	public static function is_ajax(): bool {
		return wp_doing_ajax();
	}

	/**
	 * Storefront ?wc-ajax= request (detected by query, works before WooCommerce defines WC_DOING_AJAX).
	 */
	public static function is_wc_ajax(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['wc-ajax'] );
	}

	/**
	 * REST request, detected by path or ?rest_route= (works at plugin load, before REST_REQUEST exists).
	 */
	public static function is_rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}
		$prefix = '/' . trim( rest_get_url_prefix(), '/' );
		$path   = self::path_without_home();
		return $path === $prefix || str_starts_with( $path, $prefix . '/' );
	}

	/**
	 * REST route of the current request ('/wc/store/v1/cart'), '' when not a REST request.
	 */
	public static function rest_route(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rest_route'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$route = sanitize_text_field( wp_unslash( (string) $_GET['rest_route'] ) );
			return '/' . ltrim( $route, '/' );
		}
		$prefix = '/' . trim( rest_get_url_prefix(), '/' );
		$path   = self::path_without_home();
		if ( $path === $prefix ) {
			return '/';
		}
		if ( str_starts_with( $path, $prefix . '/' ) ) {
			return substr( $path, strlen( $prefix ) );
		}
		return '';
	}

	/**
	 * GET, HEAD or OPTIONS.
	 */
	public static function is_read_method(): bool {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( (string) $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		return in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true );
	}

	/**
	 * Request path (no query string, not decoded), always starting with '/'.
	 */
	public static function request_path(): string {
		if ( null !== self::$path ) {
			return self::$path;
		}
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = (string) ( wp_parse_url( $uri, PHP_URL_PATH ) ?? '/' );
		$path = preg_replace( '#/{2,}#', '/', $path ) ?? $path;
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		self::$path = $path;
		return $path;
	}

	/**
	 * Request path with the install sub-directory (path part of the `home` option) stripped.
	 */
	public static function path_without_home(): string {
		$path = self::request_path();
		$home = (string) ( wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) ?? '' );
		$home = rtrim( $home, '/' );
		if ( '' !== $home && ( $path === $home || str_starts_with( $path, $home . '/' ) ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return '' === $path ? '/' : $path;
	}

	/**
	 * Forgets the cached request path (tests).
	 */
	public static function reset(): void {
		self::$path      = null;
		self::$rendering = false;
	}
}
