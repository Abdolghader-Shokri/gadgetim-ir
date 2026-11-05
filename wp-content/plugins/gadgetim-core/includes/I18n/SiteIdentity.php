<?php
/**
 * Site identity per language: on /en/ and /ar/ the site title and tagline (document <title>,
 * Open Graph site name, feeds…) come from the brand settings of that language instead of the
 * Persian blogname/blogdescription options.
 *
 * Output-only (architecture §3.3): the options are filtered on storefront renders in a
 * non-default language and never while saving, in wp-admin, REST writes, CLI or cron.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

use Gadgetim\Core\Settings\Settings;

/**
 * Translates blogname / blogdescription for the current language.
 */
final class SiteIdentity {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'option_blogname', [ self::class, 'filter_name' ], 20 );
		add_filter( 'option_blogdescription', [ self::class, 'filter_tagline' ], 20 );
	}

	/**
	 * option_blogname.
	 *
	 * @param mixed $value Stored (Persian) site title.
	 * @return mixed
	 */
	public static function filter_name( $value ) {
		return self::translate( 'brand.name', $value );
	}

	/**
	 * option_blogdescription.
	 *
	 * @param mixed $value Stored (Persian) tagline.
	 * @return mixed
	 */
	public static function filter_tagline( $value ) {
		return self::translate( 'brand.tagline', $value );
	}

	/**
	 * The setting's value for the current language, or the stored option when not applicable.
	 *
	 * @param string $path  Settings path holding [ fa, en, ar ].
	 * @param mixed  $value Stored option value.
	 * @return mixed
	 */
	private static function translate( string $path, $value ) {
		if ( ! self::applies() ) {
			return $value;
		}
		$lang = Languages::current();
		if ( Languages::is_default( $lang ) ) {
			return $value;
		}
		$set = class_exists( Settings::class ) ? Settings::get( $path, [] ) : [];
		$out = is_array( $set ) ? trim( (string) ( $set[ $lang ] ?? '' ) ) : '';
		return '' !== $out ? $out : $value;
	}

	/**
	 * Only for reading on the storefront (and read-only API) — never in admin, CLI, cron or writes.
	 */
	private static function applies(): bool {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return false;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		return in_array( $method, [ 'GET', 'HEAD' ], true );
	}
}
