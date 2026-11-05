<?php
/**
 * The gadgetim_settings option: typed reads with defaults, schema-driven sanitisation.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings;

use Gadgetim\Core\Support\Digits;
use Gadgetim\Core\Support\Phone;

/**
 * Settings storage (architecture §5.10, §6, Appendix B).
 */
final class Settings {

	public const OPTION = 'gadgetim_settings';

	/** Settings API group / option page, also the settings_errors() setting name. */
	public const GROUP = 'gadgetim_settings';

	public const TAB_FIELD = 'gadgetim_settings_tab';

	private static ?array $cache = null;

	private static bool $bypass = false;

	/**
	 * admin_init: registers the option with the Settings API (saving goes through options.php).
	 */
	public static function register_setting(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ self::class, 'sanitize' ],
				'show_in_rest'      => false,
			]
		);
	}

	/**
	 * Stored values merged over the schema defaults (lists replace, maps merge).
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			self::$cache = self::merge( self::defaults(), self::raw() );
		}
		return self::$cache;
	}

	/**
	 * One setting by dotted path, with the schema default as fallback, then $default.
	 */
	public static function get( string $path, mixed $default = null ): mixed {
		$value = Schema::path_get( self::all(), $path, null );
		return null === $value ? $default : $value;
	}

	/**
	 * Per-language value of an i18n setting: requested language, else Persian, else $default.
	 */
	public static function get_lang( string $path, ?string $lang = null, string $default = '' ): string {
		$value = self::get( $path );
		$lang  = $lang ?? ( function_exists( 'gadgetim_lang' ) ? gadgetim_lang() : 'fa' );
		if ( is_array( $value ) ) {
			$candidate = $value[ $lang ] ?? '';
			if ( ! is_scalar( $candidate ) || '' === trim( (string) $candidate ) ) {
				$candidate = $value['fa'] ?? '';
			}
			$value = $candidate;
		}
		return is_scalar( $value ) && '' !== trim( (string) $value ) ? (string) $value : $default;
	}

	/**
	 * Writes one setting (sanitized by its schema field when one exists).
	 */
	public static function set( string $path, mixed $value ): bool {
		$field = Schema::field( $path );
		if ( null !== $field ) {
			$value = self::sanitize_value( $field, $value );
		}
		$data = self::raw();
		Schema::path_set( $data, $path, $value );
		return self::write( $data );
	}

	/**
	 * Writes several settings at once: [ 'contact.phone' => '…', 'contact.enabled' => true ].
	 */
	public static function update( array $values ): bool {
		$data = self::raw();
		foreach ( $values as $path => $value ) {
			$field = Schema::field( (string) $path );
			if ( null !== $field ) {
				$value = self::sanitize_value( $field, $value );
			}
			Schema::path_set( $data, (string) $path, $value );
		}
		return self::write( $data );
	}

	/**
	 * The stored option as is (no defaults).
	 */
	public static function raw(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/**
	 * Schema defaults.
	 */
	public static function defaults(): array {
		return Schema::defaults();
	}

	/**
	 * Activation/upgrade: creates the option with the defaults when it does not exist.
	 */
	public static function seed_defaults(): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		self::$bypass = true;
		try {
			add_option( self::OPTION, self::defaults(), '', true );
		} finally {
			self::$bypass = false;
		}
		self::flush_cache();
	}

	/**
	 * Drops the in-request cache (after writes).
	 */
	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * register_setting sanitizer.
	 *
	 * Form saves (options.php) post one tab: only that tab's fields are read, everything else
	 * keeps its stored value, and the tab's capability is enforced here as well. An options.php
	 * submission without a tab field is refused. Programmatic writes (update_option() outside
	 * Settings::write(), which bypasses this) require manage_options, cron or WP-CLI; they
	 * sanitize every known field present and keep unknown keys.
	 */
	public static function sanitize( mixed $input ): array {
		$current = self::raw();
		if ( self::$bypass ) {
			return is_array( $input ) ? $input : $current;
		}
		if ( ! is_array( $input ) ) {
			return $current;
		}

		$tab       = '';
		$form_save = false;
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- options.php verified the Settings API nonce before calling this sanitizer.
		if ( isset( $_POST['option_page'] ) && is_scalar( $_POST['option_page'] ) && self::GROUP === sanitize_key( wp_unslash( (string) $_POST['option_page'] ) ) ) {
			$form_save = true;
			if ( isset( $_POST[ self::TAB_FIELD ] ) && is_scalar( $_POST[ self::TAB_FIELD ] ) ) {
				$tab = sanitize_key( wp_unslash( (string) $_POST[ self::TAB_FIELD ] ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $form_save && '' === $tab ) {
			// The tab decides the capability, so a tab-less form save is never trusted.
			add_settings_error( self::GROUP, 'gadgetim_missing_tab', __( 'The settings form was incomplete; nothing was saved.', 'gadgetim-core' ) );
			return $current;
		}

		if ( '' === $tab ) {
			$trusted = current_user_can( Capabilities::ADMIN ) || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
			if ( ! $trusted ) {
				return $current;
			}
			$out = $input;
			foreach ( Schema::fields() as $path => $field ) {
				if ( ! Schema::path_exists( $input, $path ) ) {
					continue;
				}
				Schema::path_set( $out, $path, self::sanitize_value( $field, Schema::path_get( $input, $path ) ) );
			}
			return $out;
		}

		$tab_def = Schema::tab( $tab );
		if ( null === $tab_def ) {
			add_settings_error( self::GROUP, 'gadgetim_unknown_tab', __( 'Unknown settings tab; nothing was saved.', 'gadgetim-core' ) );
			return $current;
		}
		if ( ! Capabilities::can( $tab ) ) {
			add_settings_error( self::GROUP, 'gadgetim_forbidden', __( 'You are not allowed to change these settings.', 'gadgetim-core' ) );
			return $current;
		}

		$out = $current;
		foreach ( Schema::fields( $tab ) as $path => $field ) {
			if ( '' !== $field['capability'] && ! current_user_can( $field['capability'] ) ) {
				continue;
			}
			Schema::path_set( $out, $path, self::sanitize_value( $field, Schema::path_get( $input, $path ) ) );
		}
		return $out;
	}

	/**
	 * Sanitizes a value for a field, handling per-language arrays.
	 */
	public static function sanitize_value( array $field, mixed $value ): mixed {
		if ( empty( $field['i18n'] ) ) {
			return self::sanitize_field( $field, $value );
		}
		$out = [];
		foreach ( Schema::languages() as $code ) {
			$out[ $code ] = self::sanitize_field( $field, is_array( $value ) ? ( $value[ $code ] ?? null ) : ( 'fa' === $code ? $value : null ) );
		}
		return $out;
	}

	/**
	 * Sanitizes a single (non-i18n) value by field type; a field's own 'sanitize' callable wins.
	 */
	public static function sanitize_field( array $field, mixed $value ): mixed {
		if ( is_callable( $field['sanitize'] ?? null ) ) {
			return call_user_func( $field['sanitize'], $value, $field );
		}

		$type    = (string) ( $field['type'] ?? 'text' );
		$default = $field['default'] ?? null;

		switch ( $type ) {
			case 'toggle':
				return null !== $value && rest_sanitize_boolean( is_scalar( $value ) ? $value : false );

			case 'number':
				$raw = is_scalar( $value ) ? trim( Digits::to_latin( (string) $value ) ) : '';
				if ( '' === $raw || ! is_numeric( $raw ) ) {
					return is_numeric( $default ) ? $default : 0;
				}
				$number = (float) $raw;
				if ( is_numeric( $field['min'] ?? null ) ) {
					$number = max( (float) $field['min'], $number );
				}
				if ( is_numeric( $field['max'] ?? null ) ) {
					$number = min( (float) $field['max'], $number );
				}
				$step = $field['step'] ?? 1;
				return ( is_numeric( $step ) && floor( (float) $step ) === (float) $step ) ? (int) round( $number ) : $number;

			case 'select':
				$options = Schema::options( $field );
				$key     = is_scalar( $value ) ? (string) $value : '';
				if ( array_key_exists( $key, $options ) ) {
					return $key;
				}
				return is_scalar( $default ) ? (string) $default : '';

			case 'textarea':
				return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';

			case 'richtext':
				return is_scalar( $value ) ? wp_kses_post( (string) $value ) : '';

			case 'phone':
				return is_scalar( $value ) ? Phone::normalize( (string) $value ) : '';

			case 'email':
				return is_scalar( $value ) ? sanitize_email( (string) $value ) : '';

			case 'url':
				return is_scalar( $value ) ? esc_url_raw( trim( (string) $value ) ) : '';

			case 'image':
				return is_scalar( $value ) ? absint( $value ) : 0;

			case 'repeater':
				if ( ! is_array( $value ) ) {
					return [];
				}
				$items = [];
				foreach ( array_slice( $value, 0, 50 ) as $item ) {
					if ( ! is_scalar( $item ) ) {
						continue;
					}
					$item = sanitize_text_field( (string) $item );
					if ( '' !== $item ) {
						$items[] = $item;
					}
				}
				return $items;

			default:
				return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}
	}

	/**
	 * Deep merge: associative arrays merge key by key, lists (repeaters) are replaced wholesale.
	 */
	private static function merge( array $defaults, array $stored ): array {
		foreach ( $stored as $key => $value ) {
			if ( is_array( $value ) && isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && ! array_is_list( $value ) && ! array_is_list( $defaults[ $key ] ) ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	private static function write( array $data ): bool {
		self::$bypass = true;
		try {
			$ok = update_option( self::OPTION, $data, true );
		} finally {
			self::$bypass = false;
		}
		self::flush_cache();
		return $ok;
	}
}
