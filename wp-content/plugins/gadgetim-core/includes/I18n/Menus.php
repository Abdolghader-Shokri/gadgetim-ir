<?php
/**
 * Per-language menu locations (architecture §3.3).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

/**
 * Locations primary_fa, footer_fa, legal_fa and the en/ar variants. The theme asks
 * Menus::location( 'primary' ) for the location of the current language (falls back to
 * the Persian one), or simply passes theme_location => 'primary' to wp_nav_menu(): the
 * wp_nav_menu_args filter maps it.
 */
final class Menus {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'after_setup_theme', [ self::class, 'register_locations' ], 20 );
		add_filter( 'wp_nav_menu_args', [ self::class, 'filter_nav_menu_args' ], 10, 1 );
	}

	/**
	 * Base location names (filter gadgetim_menu_bases).
	 *
	 * @return string[]
	 */
	public static function bases(): array {
		/**
		 * Filters the menu location bases that get one variant per language.
		 *
		 * @param string[] $bases Base names.
		 */
		$bases = (array) apply_filters( 'gadgetim_menu_bases', [ 'primary', 'footer', 'legal' ] );
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $bases ) ) ) );
	}

	/**
	 * after_setup_theme (20): registers {base}_{lang} for every base and language.
	 */
	public static function register_locations(): void {
		$labels = [
			'primary' => __( 'Primary menu', 'gadgetim-core' ),
			'footer'  => __( 'Footer menu', 'gadgetim-core' ),
			'legal'   => __( 'Legal menu', 'gadgetim-core' ),
		];
		$locations = [];
		foreach ( self::bases() as $base ) {
			foreach ( Languages::codes() as $lang ) {
				$label = $labels[ $base ] ?? ucfirst( $base );
				/* translators: 1: menu name, 2: language name */
				$locations[ $base . '_' . $lang ] = sprintf( __( '%1$s (%2$s)', 'gadgetim-core' ), $label, Languages::label( $lang ) );
			}
		}
		if ( $locations ) {
			register_nav_menus( $locations );
		}
	}

	/**
	 * Location to render for a base in a language: {base}_{lang} when a menu is assigned,
	 * else {base}_fa.
	 */
	public static function location( string $base, ?string $lang = null ): string {
		$base = sanitize_key( $base );
		$lang = $lang ?? Languages::current();
		$lang = Languages::exists( $lang ) ? $lang : Languages::DEFAULT;
		$candidate = $base . '_' . $lang;
		if ( has_nav_menu( $candidate ) ) {
			return $candidate;
		}
		return $base . '_' . Languages::DEFAULT;
	}

	/**
	 * Whether any variant of a base has a menu assigned.
	 */
	public static function has( string $base, ?string $lang = null ): bool {
		return has_nav_menu( self::location( $base, $lang ) );
	}

	/**
	 * wp_nav_menu_args: 'primary' or 'primary_fa' become 'primary_en' under /en/ when that
	 * location has a menu; a bare base with no menu falls back to the Persian variant.
	 *
	 * @param mixed $args Arguments.
	 */
	public static function filter_nav_menu_args( $args ) {
		if ( ! is_array( $args ) || empty( $args['theme_location'] ) || ! is_string( $args['theme_location'] ) ) {
			return $args;
		}
		$location = $args['theme_location'];
		$codes    = Languages::codes();
		$base     = preg_replace( '/_(' . implode( '|', array_map( 'preg_quote', $codes ) ) . ')$/', '', $location ) ?? $location;
		if ( ! in_array( $base, self::bases(), true ) ) {
			return $args;
		}
		$lang = Languages::current();
		if ( Languages::DEFAULT !== $lang && has_nav_menu( $base . '_' . $lang ) ) {
			$args['theme_location'] = $base . '_' . $lang;
			return $args;
		}
		if ( ! has_nav_menu( $location ) && has_nav_menu( $base . '_' . Languages::DEFAULT ) ) {
			$args['theme_location'] = $base . '_' . Languages::DEFAULT;
		}
		return $args;
	}
}
