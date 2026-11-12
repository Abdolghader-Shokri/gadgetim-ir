<?php
/**
 * Sitemap corrections on top of I18n\Sitemaps.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * <loc> values are percent-encoded (brand links carried the raw Persian base «برند»), and the
 * author sitemap is dropped: author archives are noindex (Seo\Robots) and a sitemap may only
 * list indexable pages.
 */
final class Sitemap {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'wp_sitemaps_add_provider', [ self::class, 'filter_provider' ], 20, 2 );
		foreach ( [ 'wp_sitemaps_posts_entry', 'wp_sitemaps_taxonomies_entry', 'wp_sitemaps_index_entry' ] as $hook ) {
			add_filter( $hook, [ self::class, 'encode_entry' ], 20, 1 );
		}
	}

	/**
	 * wp_sitemaps_add_provider: no users (author) sitemap.
	 *
	 * @param mixed  $provider Provider.
	 * @param string $name     Provider name.
	 * @return mixed
	 */
	public static function filter_provider( $provider, $name = '' ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Percent-encodes the <loc> of a sitemap entry.
	 *
	 * @param mixed $entry Entry.
	 * @return mixed
	 */
	public static function encode_entry( $entry ) {
		if ( is_array( $entry ) && isset( $entry['loc'] ) && is_string( $entry['loc'] ) ) {
			$entry['loc'] = Text::encode_url( $entry['loc'] );
		}
		return $entry;
	}
}
