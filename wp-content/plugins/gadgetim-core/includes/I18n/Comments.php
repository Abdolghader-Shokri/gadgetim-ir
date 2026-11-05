<?php
/**
 * Reviews posted from a prefixed page (architecture §3.8).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use WP_Comment;

/**
 * The comment form posts to /wp-comments-post.php (never prefixed), so the language travels
 * in a hidden gtm_lang field: the redirect is rebuilt inside with_lang( $lang ) and the
 * language is stored as comment meta _gtm_lang for moderation context.
 */
final class Comments {

	public const FIELD = 'gtm_lang';

	public const META = '_gtm_lang';

	private static string $posted_lang = '';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'comment_form_default_fields', [ self::class, 'add_field' ], 10, 1 );
		add_action( 'comment_form_logged_in_after', [ self::class, 'print_field' ], 10, 0 );
		add_filter( 'comment_post_redirect', [ self::class, 'filter_redirect' ], 10, 2 );
		add_filter( 'preprocess_comment', [ self::class, 'preprocess' ], 10, 1 );
		add_action( 'comment_post', [ self::class, 'store_meta' ], 10, 1 );
	}

	/**
	 * The hidden input markup (non-Persian pages only).
	 */
	public static function field_html(): string {
		$lang = Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return '';
		}
		return '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $lang ) . '">';
	}

	/**
	 * comment_form_default_fields: visitors who are not logged in.
	 *
	 * @param mixed $fields Fields.
	 */
	public static function add_field( $fields ) {
		if ( ! is_array( $fields ) ) {
			return $fields;
		}
		$html = self::field_html();
		if ( '' !== $html ) {
			$fields[ self::FIELD ] = $html;
		}
		return $fields;
	}

	/**
	 * comment_form_logged_in_after: logged-in visitors (default fields are not printed for them).
	 */
	public static function print_field(): void {
		echo self::field_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}

	/**
	 * Language posted with the comment, '' when absent or unknown.
	 */
	public static function posted_lang(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the comment form has no nonce; the value is validated against the configured codes.
		$lang = isset( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( (string) $_POST[ self::FIELD ] ) ) : '';
		return '' !== $lang && Languages::exists( $lang ) ? $lang : '';
	}

	/**
	 * comment_post_redirect: back to the page in the shopper's language.
	 *
	 * @param mixed $location Redirect URL.
	 * @param mixed $comment  WP_Comment.
	 */
	public static function filter_redirect( $location, $comment = null ) {
		$lang = self::posted_lang();
		if ( ! is_string( $location ) || '' === $lang || Languages::DEFAULT === $lang || ! $comment instanceof WP_Comment ) {
			return $location;
		}
		$target = Languages::with_lang( $lang, static fn(): string => (string) get_comment_link( $comment ) );
		if ( '' === $target ) {
			return $location;
		}
		$query = (string) ( wp_parse_url( $location, PHP_URL_QUERY ) ?? '' );
		if ( '' !== $query ) {
			wp_parse_str( $query, $args );
			if ( is_array( $args ) && $args ) {
				$fragment = (string) ( wp_parse_url( $target, PHP_URL_FRAGMENT ) ?? '' );
				$base     = '' !== $fragment ? substr( $target, 0, -strlen( $fragment ) - 1 ) : $target;
				$target   = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $args ) ), $base ) . ( '' !== $fragment ? '#' . $fragment : '' );
			}
		}
		return $target;
	}

	/**
	 * preprocess_comment: remembers the posted language for comment_post.
	 *
	 * @param mixed $data Comment data.
	 */
	public static function preprocess( $data ) {
		self::$posted_lang = self::posted_lang();
		return $data;
	}

	/**
	 * comment_post: stores _gtm_lang.
	 *
	 * @param mixed $comment_id Comment ID.
	 */
	public static function store_meta( $comment_id ): void {
		$lang = '' !== self::$posted_lang ? self::$posted_lang : self::posted_lang();
		if ( '' === $lang || (int) $comment_id <= 0 ) {
			return;
		}
		add_comment_meta( (int) $comment_id, self::META, $lang, true );
	}

	/**
	 * Language a comment was posted in ('fa' when unknown).
	 */
	public static function lang( int $comment_id ): string {
		$lang = (string) get_comment_meta( $comment_id, self::META, true );
		return Languages::exists( $lang ) ? $lang : Languages::DEFAULT;
	}
}
