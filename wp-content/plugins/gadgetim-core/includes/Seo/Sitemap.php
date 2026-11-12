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
		add_filter( 'wp_sitemaps_taxonomies', [ self::class, 'filter_taxonomies' ], 20, 1 );
		foreach ( [ 'wp_sitemaps_posts_entry', 'wp_sitemaps_taxonomies_entry', 'wp_sitemaps_index_entry' ] as $hook ) {
			add_filter( $hook, [ self::class, 'encode_entry' ], 20, 1 );
		}
	}

	/**
	 * wp_sitemaps_taxonomies: no sitemap for taxonomies whose archives are noindex, and none for
	 * the blog's categories and tags while no post is published. Their stored counts can be stale
	 * (the live «Uncategorized» kept count 1 after Hello World was set to draft by SQL), so core
	 * listed an archive that answers with an empty, noindex page (2026-11-12).
	 *
	 * @param mixed $taxonomies [ name => WP_Taxonomy ].
	 * @return mixed
	 */
	public static function filter_taxonomies( $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return $taxonomies;
		}
		foreach ( Robots::NOINDEX_TAXONOMIES as $taxonomy ) {
			unset( $taxonomies[ $taxonomy ] );
		}
		$posts = wp_count_posts( 'post' );
		if ( ! is_object( $posts ) || (int) ( $posts->publish ?? 0 ) <= 0 ) {
			unset( $taxonomies['category'], $taxonomies['post_tag'] );
		}
		return $taxonomies;
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
