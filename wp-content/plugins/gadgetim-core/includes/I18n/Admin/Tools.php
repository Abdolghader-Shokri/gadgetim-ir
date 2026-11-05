<?php
/**
 * I18n entries on the Tools tab (architecture §3.2, §3.4, §3.6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Search;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\Settings\Admin\Tools as CoreTools;
use Gadgetim\Core\Support\Cache;
use WP_Error;

/**
 * "Install language packs" (install_languages), "Rebuild slug map", "Rebuild search index"
 * and the "Slug conflicts" report, all behind manage_options + nonce through the core Tools runner.
 */
final class Tools {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'register_tools' ], 20 );
	}

	/**
	 * admin_init (20): registers the tools.
	 */
	public static function register_tools(): void {
		if ( ! class_exists( CoreTools::class ) ) {
			return;
		}
		CoreTools::add(
			'install-language-packs',
			__( 'Install language packs', 'gadgetim-core' ),
			[ self::class, 'tool_install_language_packs' ],
			'install_languages',
			[
				'description' => __( 'Downloads the Arabic WordPress language pack and the WooCommerce (and other plugin/theme) translations for every configured language from translate.wordpress.org.', 'gadgetim-core' ),
				'button'      => __( 'Install packs', 'gadgetim-core' ),
				'order'       => 30,
			]
		);
		CoreTools::add(
			'rebuild-slug-map',
			__( 'Rebuild slug map', 'gadgetim-core' ),
			[ self::class, 'tool_rebuild_slug_map' ],
			'manage_options',
			[
				'description' => __( 'Regenerates the per-language translated-slug maps (transients) and the published-id lists from the stored meta.', 'gadgetim-core' ),
				'button'      => __( 'Rebuild', 'gadgetim-core' ),
				'order'       => 40,
			]
		);
		CoreTools::add(
			'rebuild-search-index',
			__( 'Rebuild search index', 'gadgetim-core' ),
			[ self::class, 'tool_rebuild_search_index' ],
			'manage_options',
			[
				'description' => __( 'Rewrites the normalised search blobs (title, subtitle, brand, categories, specs) of every product, page and post for every language.', 'gadgetim-core' ),
				'button'      => __( 'Rebuild', 'gadgetim-core' ),
				'order'       => 41,
			]
		);
		CoreTools::add(
			'slug-conflicts',
			__( 'Slug conflicts', 'gadgetim-core' ),
			[ self::class, 'tool_slug_conflicts' ],
			'manage_options',
			[
				'description' => __( 'Lists translated slugs that collide with a native slug of the same type, with another translated slug, or with a reserved WooCommerce slug.', 'gadgetim-core' ),
				'button'      => __( 'Check', 'gadgetim-core' ),
				'order'       => 42,
			]
		);
	}

	/**
	 * Tool: language packs for core and plugins/themes.
	 *
	 * @return string|WP_Error|array
	 */
	public static function tool_install_language_packs() {
		if ( ! current_user_can( 'install_languages' ) ) {
			return new WP_Error( 'gadgetim_forbidden', __( 'You are not allowed to install languages.', 'gadgetim-core' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		if ( ! function_exists( 'wp_can_install_language_pack' ) || ! wp_can_install_language_pack() ) {
			return new WP_Error( 'gadgetim_fs', __( 'Language packs cannot be installed: the filesystem is not writable or file modifications are disallowed.', 'gadgetim-core' ) );
		}

		$lines  = [];
		$errors = 0;
		foreach ( Languages::secondary_codes() as $lang ) {
			$locale = Languages::locale( $lang );
			if ( 'en_US' === $locale ) {
				continue;
			}
			if ( in_array( $locale, get_available_languages(), true ) ) {
				/* translators: %s: locale */
				$lines[] = sprintf( __( 'WordPress %s: already installed.', 'gadgetim-core' ), $locale );
				continue;
			}
			$result = wp_download_language_pack( $locale );
			if ( $result ) {
				/* translators: %s: locale */
				$lines[] = sprintf( __( 'WordPress %s: installed.', 'gadgetim-core' ), $locale );
			} else {
				$errors++;
				/* translators: %s: locale */
				$lines[] = sprintf( __( 'WordPress %s: download failed (is translate.wordpress.org reachable from this server?).', 'gadgetim-core' ), $locale );
			}
		}

		// Plugin and theme translations for every installed language (WooCommerce ar among them).
		if ( function_exists( 'wp_clean_update_cache' ) ) {
			wp_clean_update_cache();
		}
		wp_update_plugins();
		wp_update_themes();
		$updates = function_exists( 'wp_get_translation_updates' ) ? wp_get_translation_updates() : [];
		if ( $updates ) {
			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Language_Pack_Upgrader( $skin );
			$results  = $upgrader->bulk_upgrade( $updates );
			$ok       = 0;
			foreach ( (array) $results as $result ) {
				if ( $result && ! is_wp_error( $result ) ) {
					$ok++;
				}
			}
			$names = array_unique( array_map( static fn( $u ): string => (string) ( $u->slug ?? $u->type ?? '' ) . ' (' . (string) ( $u->language ?? '' ) . ')', $updates ) );
			/* translators: 1: installed count, 2: total, 3: list */
			$lines[] = sprintf( __( 'Plugin/theme translations: %1$d of %2$d installed: %3$s', 'gadgetim-core' ), $ok, count( $updates ), implode( ', ', $names ) );
			if ( $ok < count( $updates ) ) {
				$errors++;
			}
		} else {
			$lines[] = __( 'Plugin/theme translations: nothing to install (all up to date, or the update API was unreachable).', 'gadgetim-core' );
		}

		return [
			'type'    => $errors > 0 ? 'warning' : 'success',
			'message' => implode( '<br>', array_map( 'esc_html', $lines ) ),
		];
	}

	/**
	 * Tool: slug map.
	 */
	public static function tool_rebuild_slug_map(): string {
		$report = Slugs::rebuild_all();
		$parts  = [];
		foreach ( $report as $lang => $counts ) {
			/* translators: 1: language code, 2: post slugs, 3: term slugs */
			$parts[] = sprintf( __( '%1$s: %2$d post slugs, %3$d term slugs', 'gadgetim-core' ), strtoupper( (string) $lang ), $counts['posts'], $counts['terms'] );
		}
		/* translators: %s: per-language counts */
		return sprintf( __( 'Slug maps rebuilt (%s).', 'gadgetim-core' ), $parts ? implode( '; ', $parts ) : __( 'no secondary languages', 'gadgetim-core' ) );
	}

	/**
	 * Tool: search blobs.
	 */
	public static function tool_rebuild_search_index(): string {
		Cache::suspend();
		try {
			$count = \Gadgetim\Core\Support\Context::with_suspended( static fn(): int => Search::rebuild_all() );
		} finally {
			Cache::resume();
		}
		/* translators: %d: number of posts */
		return sprintf( _n( 'Search index rebuilt for %d item.', 'Search index rebuilt for %d items.', $count, 'gadgetim-core' ), $count );
	}

	/**
	 * Tool: conflicts report.
	 */
	public static function tool_slug_conflicts(): array {
		$conflicts = Slugs::conflicts_report();
		if ( ! $conflicts ) {
			return [
				'type'    => 'success',
				'message' => __( 'No slug conflicts found.', 'gadgetim-core' ),
			];
		}
		$reasons = [
			'native'    => __( 'equals a native slug of the same type', 'gadgetim-core' ),
			'duplicate' => __( 'used by another translated object', 'gadgetim-core' ),
			'reserved'  => __( 'is a reserved slug', 'gadgetim-core' ),
		];
		$lines = [];
		foreach ( array_slice( $conflicts, 0, 50 ) as $row ) {
			$label = 'term' === $row['kind']
				? ( get_term( $row['id'], $row['type'] )->name ?? '#' . $row['id'] )
				: ( get_the_title( $row['id'] ) ?: '#' . $row['id'] );
			$link  = 'term' === $row['kind'] ? get_edit_term_link( $row['id'], $row['type'] ) : get_edit_post_link( $row['id'], 'raw' );
			$text  = sprintf(
				/* translators: 1: language, 2: object type, 3: object label, 4: slug, 5: reason */
				__( '[%1$s] %2$s "%3$s": slug "%4$s" %5$s', 'gadgetim-core' ),
				strtoupper( $row['lang'] ),
				$row['type'],
				$label,
				$row['slug'],
				$reasons[ $row['reason'] ] ?? $row['reason']
			);
			$lines[] = $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text );
		}
		if ( count( $conflicts ) > 50 ) {
			/* translators: %d: hidden count */
			$lines[] = esc_html( sprintf( __( '... and %d more.', 'gadgetim-core' ), count( $conflicts ) - 50 ) );
		}
		return [
			'type'    => 'warning',
			'message' => implode( '<br>', $lines ),
		];
	}
}
