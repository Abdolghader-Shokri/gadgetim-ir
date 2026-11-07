<?php
/**
 * Custom admin login address: the login screen lives at /<slug>/ (default "eesa", set under
 * Settings > General) instead of /wp-login.php. Visitors who are not logged in get "page not
 * found" for wp-login.php and /wp-admin/; admin-ajax.php and admin-post.php stay open for the
 * storefront. Every URL WordPress builds for wp-login.php (logout, lost password, password reset
 * e-mails, redirects) is rewritten to the new address. Logged-in users keep using /wp-admin/.
 *
 * Escape hatch: define( 'GADGETIM_LOGIN_SLUG', false ) in wp-config.php turns the feature off;
 * a string there forces that address and locks the settings field.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Security;

/**
 * Login address handling.
 */
final class LoginSlug {

	public const OPTION = 'gadgetim_login_slug';

	public const DEFAULT = 'eesa';

	/** Addresses that can never be the login slug (WordPress, WooCommerce and storefront routes). */
	private const RESERVED = [ 'wp-admin', 'wp-login', 'wp-login-php', 'wp-content', 'wp-includes', 'wp-json', 'admin', 'login', 'dashboard', 'en', 'ar', 'fa', 'shop', 'cart', 'checkout', 'my-account', 'product', 'product-category', 'product-tag', 'brand', 'feed', 'search', 'page', 'wc-api', 'wc-ajax' ];

	private static bool $is_login = false;

	private static bool $is_blocked = false;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_setting' ] );
		if ( '' === self::slug() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		add_action( 'plugins_loaded', [ self::class, 'detect' ], 1 );
		add_action( 'wp_loaded', [ self::class, 'route' ], 1 );
		add_filter( 'site_url', [ self::class, 'filter_url' ], 100, 1 );
		add_filter( 'network_site_url', [ self::class, 'filter_url' ], 100, 1 );
		add_filter( 'wp_redirect', [ self::class, 'filter_url' ], 100, 1 );
		add_filter( 'login_url', [ self::class, 'filter_url' ], 100, 1 );
	}

	/**
	 * The active slug ('' = feature off).
	 */
	public static function slug(): string {
		if ( defined( 'GADGETIM_LOGIN_SLUG' ) ) {
			return is_string( GADGETIM_LOGIN_SLUG ) ? self::clean( GADGETIM_LOGIN_SLUG ) : '';
		}
		$value = get_option( self::OPTION, self::DEFAULT );
		$value = self::clean( is_string( $value ) ? $value : '' );
		return '' !== $value ? $value : self::DEFAULT;
	}

	/**
	 * Full login URL (no language prefix: built from the raw home option).
	 */
	public static function url(): string {
		return untrailingslashit( (string) get_option( 'home' ) ) . '/' . self::slug() . '/';
	}

	/**
	 * plugins_loaded (1): classify the request before WordPress routes it.
	 */
	public static function detect(): void {
		if ( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$path = self::request_path();
		$base = self::home_path();
		$rel  = '/' . ltrim( (string) substr( $path, strlen( $base ) ), '/' );
		if ( 0 !== strpos( $path, $base ) ) {
			return;
		}
		if ( untrailingslashit( $rel ) === '/' . self::slug() ) {
			self::$is_login     = true;
			$GLOBALS['pagenow'] = 'wp-login.php';
			return;
		}
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( (string) $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		if ( '/wp-login.php' === untrailingslashit( $rel ) || '/wp-login' === untrailingslashit( $rel ) ) {
			self::$is_blocked = 'postpass' !== $action;
			return;
		}
		if ( preg_match( '#^/wp-admin(/|$)#', $rel ) && ! is_user_logged_in() ) {
			$script = basename( (string) wp_parse_url( $rel, PHP_URL_PATH ) );
			self::$is_blocked = ! in_array( $script, [ 'admin-ajax.php', 'admin-post.php' ], true );
		}
	}

	/**
	 * wp_loaded (1): serve the login screen at the slug, "not found" for the old addresses.
	 */
	public static function route(): void {
		if ( self::$is_blocked ) {
			self::not_found();
		}
		if ( ! self::$is_login ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the login screen does its own checks.
		if ( is_user_logged_in() && empty( $_REQUEST['action'] ) && empty( $_REQUEST['interim-login'] ) && empty( $_REQUEST['redirect_to'] ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
		// phpcs:enable
		// wp-login.php expects to run in the global scope with these globals.
		global $error, $interim_login, $action, $user_login, $user, $redirect_to;
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Rewrites any wp-login.php URL to the login slug, keeping the query string.
	 *
	 * @param mixed $url URL.
	 * @return mixed
	 */
	public static function filter_url( $url ) {
		if ( ! is_string( $url ) || ! str_contains( $url, 'wp-login.php' ) ) {
			return $url;
		}
		$parts = explode( '?', $url, 2 );
		if ( ! str_ends_with( $parts[0], 'wp-login.php' ) ) {
			return $url;
		}
		return self::url() . ( isset( $parts[1] ) && '' !== $parts[1] ? '?' . $parts[1] : '' );
	}

	/**
	 * Settings > General: the login address field.
	 */
	public static function register_setting(): void {
		register_setting(
			'general',
			self::OPTION,
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'default'           => self::DEFAULT,
			]
		);
		add_settings_field(
			self::OPTION,
			'<label for="' . esc_attr( self::OPTION ) . '">' . esc_html__( 'Admin login address', 'gadgetim-core' ) . '</label>',
			[ self::class, 'render_field' ],
			'general',
			'default'
		);
	}

	/**
	 * Field markup.
	 */
	public static function render_field(): void {
		$locked = defined( 'GADGETIM_LOGIN_SLUG' );
		printf(
			'<code dir="ltr">%1$s/</code> <input name="%2$s" id="%2$s" type="text" dir="ltr" class="regular-text code" style="width:12em" value="%3$s"%4$s> <code dir="ltr">/</code>',
			esc_html( untrailingslashit( (string) get_option( 'home' ) ) ),
			esc_attr( self::OPTION ),
			esc_attr( '' !== self::slug() ? self::slug() : self::DEFAULT ),
			$locked ? ' readonly' : ''
		);
		echo '<p class="description">';
		if ( $locked ) {
			esc_html_e( 'This address is set in wp-config.php (GADGETIM_LOGIN_SLUG); change it there.', 'gadgetim-core' );
		} else {
			esc_html_e( 'The dashboard login page opens only at this address; for visitors who are not logged in, wp-login.php and /wp-admin show "page not found". Use lowercase English letters, digits and hyphens. Write the new address down before saving.', 'gadgetim-core' );
		}
		echo '</p>';
		if ( '' !== self::slug() ) {
			/* translators: %s: the login URL */
			echo '<p class="description">' . sprintf( esc_html__( 'Current login address: %s', 'gadgetim-core' ), '<a href="' . esc_url( self::url() ) . '" dir="ltr">' . esc_html( self::url() ) . '</a>' ) . '</p>';
		}
	}

	/**
	 * Sanitises the slug; an invalid, reserved or taken address keeps the previous value.
	 *
	 * @param mixed $value Submitted value.
	 */
	public static function sanitize( $value ): string {
		$previous = get_option( self::OPTION, self::DEFAULT );
		$previous = is_string( $previous ) && '' !== self::clean( $previous ) ? self::clean( $previous ) : self::DEFAULT;
		$slug     = self::clean( is_string( $value ) ? $value : '' );
		$taken    = '' !== $slug && ( null !== get_page_by_path( $slug, OBJECT, [ 'page', 'post', 'product' ] ) );
		if ( '' === $slug || in_array( $slug, self::RESERVED, true ) || str_starts_with( $slug, 'wp-' ) || $taken ) {
			add_settings_error( self::OPTION, 'gadgetim_login_slug_invalid', __( 'The admin login address must use lowercase English letters, digits or hyphens and must not be a reserved or existing address. The previous address was kept.', 'gadgetim-core' ) );
			return $previous;
		}
		return $slug;
	}

	/**
	 * Lowercase a-z, 0-9 and single hyphens, no leading/trailing hyphen.
	 */
	private static function clean( string $value ): string {
		$value = strtolower( trim( $value, " \t\n\r\0\x0B/" ) );
		$value = (string) preg_replace( '/[^a-z0-9-]+/', '-', $value );
		return trim( (string) preg_replace( '/-{2,}/', '-', $value ), '-' );
	}

	/**
	 * Decoded request path.
	 */
	private static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only.
		return rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	}

	/**
	 * Path of the site root ('/' or '/sub').
	 */
	private static function home_path(): string {
		$path = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH );
		return '' === $path ? '/' : trailingslashit( $path );
	}

	/**
	 * Theme "page not found" with a 404 status, then stop.
	 */
	private static function not_found(): void {
		global $wp_query;
		status_header( 404 );
		nocache_headers();
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
		}
		$template = get_404_template();
		if ( '' !== $template && is_file( $template ) ) {
			include $template;
		} else {
			echo '<!doctype html><title>404</title><h1>404</h1>';
		}
		exit;
	}
}
