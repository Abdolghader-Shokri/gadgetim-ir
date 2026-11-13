<?php
/**
 * Hardening that docs/architecture.md (security) asks for: XML-RPC off, no user enumeration,
 * comments only as product reviews, one login error for a wrong username or password.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Security;

use WP_Error;

/**
 * None of it was in place on 2026-11-13: xmlrpc.php accepted logins, which goes around the custom
 * login address (LoginSlug); /?author=1 redirected to the author archive, whose address is the
 * account name; the REST users route listed the accounts; and the login forms said whether a
 * username exists. Define GADGETIM_HARDENING as false in wp-config.php to switch all of it off.
 */
final class Hardening {

	/**
	 * Login error codes that tell whether an account exists.
	 */
	private const LOGIN_CODES = [ 'invalid_username', 'invalid_email', 'incorrect_password' ];

	/**
	 * Hooks.
	 */
	public static function register(): void {
		if ( defined( 'GADGETIM_HARDENING' ) && ! GADGETIM_HARDENING ) {
			return;
		}
		add_action( 'plugins_loaded', [ self::class, 'refuse_xmlrpc' ], 0 );
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'pings_open', '__return_false', 20 ); // Also drops core's X-Pingback header.
		add_filter( 'comments_open', [ self::class, 'reviews_only' ], 20, 2 );
		add_action( 'template_redirect', [ self::class, 'no_author_archives' ], 1 );
		add_filter( 'rest_endpoints', [ self::class, 'hide_users_routes' ] );
		add_filter( 'oembed_response_data', [ self::class, 'oembed_without_author' ], 20 );
		add_filter( 'authenticate', [ self::class, 'one_login_error' ], 99 );
	}

	/**
	 * plugins_loaded (0): a request to xmlrpc.php gets 403 before WordPress does any more work.
	 */
	public static function refuse_xmlrpc(): void {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC is disabled on this site.';
		exit;
	}

	/**
	 * comments_open (20): comments only as product reviews (posts, pages and attachments stay closed).
	 *
	 * @param mixed $open    Whether comments are open.
	 * @param mixed $post_id Post ID.
	 */
	public static function reviews_only( $open, $post_id = 0 ): bool {
		return (bool) $open && 'product' === get_post_type( (int) $post_id );
	}

	/**
	 * template_redirect (1): author archives and ?author=N answer 404 (one shop team, no author pages;
	 * the archive address is the account name, and ?author=N led to it).
	 */
	public static function no_author_archives(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- presence check only.
		if ( ! is_author() && ! isset( $_GET['author'] ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * rest_endpoints: the /wp/v2/users routes only for people who can write posts (the block
	 * editor's author list and /users/me); a visitor's request gets rest_no_route (404).
	 *
	 * @param mixed $endpoints Routes.
	 * @return mixed
	 */
	public static function hide_users_routes( $endpoints ) {
		if ( ! is_array( $endpoints ) || current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( str_starts_with( (string) $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * oembed_response_data (20): the store, not the account, as the author of an embedded page.
	 *
	 * @param mixed $data oEmbed data.
	 * @return mixed
	 */
	public static function oembed_without_author( $data ) {
		if ( is_array( $data ) ) {
			$data['author_name'] = (string) get_bloginfo( 'name' );
			$data['author_url']  = home_url( '/' );
		}
		return $data;
	}

	/**
	 * authenticate (99): one error for a wrong username, e-mail address or password, so wp-login (at
	 * the custom address) and the My account form no longer tell which accounts exist. The message is
	 * core's own for a failed login, so WordPress's language packs already translate it.
	 *
	 * @param mixed $user WP_User, WP_Error or null.
	 * @return mixed
	 */
	public static function one_login_error( $user ) {
		if ( ! $user instanceof WP_Error || ! array_intersect( $user->get_error_codes(), self::LOGIN_CODES ) ) {
			return $user;
		}
		// phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- core's string, translated by core.
		return new WP_Error( 'authentication_failed', __( '<strong>Error:</strong> Invalid username, email address or incorrect password.' ) );
	}
}
