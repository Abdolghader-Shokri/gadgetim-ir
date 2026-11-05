<?php
/**
 * Storefront locale and <html> attributes for the detected language (architecture §3.2).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

/**
 * Filters `locale` and `determine_locale` from plugin load so load_default_textdomain() and
 * every just-in-time textdomain load pick the language's locale. In wp-admin (except AJAX)
 * the filters bail out: WPLANG and per-user user_locale behave normally. Persian requests
 * are never touched (the site locale already is fa_IR).
 */
final class Locale {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'locale', [ self::class, 'filter_locale' ], 10, 1 );
		add_filter( 'determine_locale', [ self::class, 'filter_locale' ], 10, 1 );
		add_filter( 'language_attributes', [ self::class, 'language_attributes' ], 10, 2 );
	}

	/**
	 * locale / determine_locale filter.
	 *
	 * @param mixed $locale Locale determined so far.
	 */
	public static function filter_locale( $locale ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $locale;
		}
		// Background runs (WP-Cron, Action Scheduler) keep the site locale even when an order's
		// language was entered for a moment; only an e-mail being rendered switches it.
		if ( self::in_background() && ! Emails::active() ) {
			return $locale;
		}
		$lang = Languages::current();
		if ( Languages::DEFAULT === $lang ) {
			return $locale;
		}
		return Languages::locale( $lang );
	}

	/**
	 * WP-Cron request or an Action Scheduler queue run (cron or the admin-ajax async runner).
	 */
	public static function in_background(): bool {
		if ( wp_doing_cron() ) {
			return true;
		}
		return did_action( 'action_scheduler_before_process_queue' ) > did_action( 'action_scheduler_after_process_queue' );
	}

	/**
	 * language_attributes filter: lang="en" dir="ltr" etc. for non-Persian pages.
	 *
	 * @param string $output  Attributes string.
	 * @param string $doctype 'html' or 'xhtml'.
	 */
	public static function language_attributes( $output, $doctype = 'html' ) {
		$lang = Languages::current();
		if ( Languages::DEFAULT === $lang || ! is_string( $output ) ) {
			return $output;
		}
		$attributes = [];
		$attributes[] = 'dir="' . esc_attr( Languages::dir( $lang ) ) . '"';
		$tag          = Languages::hreflang( $lang );
		if ( 'xhtml' === $doctype ) {
			$attributes[] = 'xml:lang="' . esc_attr( $tag ) . '"';
		}
		$attributes[] = 'lang="' . esc_attr( $tag ) . '"';

		$stripped = preg_replace( '/\s*(?:xml:)?lang="[^"]*"|\s*dir="[^"]*"/', '', $output ) ?? '';
		$stripped = trim( $stripped );
		return trim( implode( ' ', $attributes ) . ( '' !== $stripped ? ' ' . $stripped : '' ) );
	}
}
