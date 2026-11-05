<?php
/**
 * Renders settings fields from their schema definition (templates/admin/fields/*.php).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings\Admin;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Options;

/**
 * Field renderer. Input names mirror the option structure:
 * gadgetim_settings[brand][name], gadgetim_settings[brand][name][fa], gadgetim_settings[trust][badges][fa][].
 */
final class Fields {

	/**
	 * Renders a field (all languages for i18n fields).
	 */
	public static function render( string $path, array $field, mixed $value ): void {
		if ( empty( $field['i18n'] ) ) {
			self::control( $path, $field, $value, '' );
			return;
		}
		echo '<div class="gtm-i18n">';
		foreach ( Options::languages() as $code => $language ) {
			$lang_value = is_array( $value ) ? ( $value[ $code ] ?? null ) : ( 'fa' === $code ? $value : null );
			echo '<div class="gtm-i18n__row" data-lang="' . esc_attr( $code ) . '">';
			echo '<label class="gtm-i18n__lang" for="' . esc_attr( self::id( $path, $code ) ) . '"><span class="gtm-i18n__code">' . esc_html( $code ) . '</span> ' . esc_html( self::language_label( $code ) ) . '</label>';
			echo '<div class="gtm-i18n__control">';
			self::control( $path, $field, $lang_value, $code, (string) ( $language['dir'] ?? 'rtl' ) );
			echo '</div></div>';
		}
		echo '</div>';
	}

	/**
	 * Renders one control. A field's own 'render' callable replaces the template.
	 */
	public static function control( string $path, array $field, mixed $value, string $lang = '', string $dir = '' ): void {
		if ( is_callable( $field['render'] ?? null ) ) {
			call_user_func( $field['render'], $path, $field, $value, $lang );
			return;
		}
		$type = in_array( $field['type'], Schema::TYPES, true ) ? $field['type'] : 'text';
		Plugin::view(
			'admin/fields/' . $type,
			[
				'name'    => self::name( $path, $lang ),
				'id'      => self::id( $path, $lang ),
				'value'   => $value ?? ( empty( $field['i18n'] ) ? $field['default'] : ( $field['default'][ $lang ] ?? null ) ),
				'field'   => $field,
				'path'    => $path,
				'lang'    => $lang,
				'dir'     => $dir,
				'options' => 'select' === $type ? Schema::options( $field ) : [],
			]
		);
	}

	/**
	 * Input name for a path (and language).
	 */
	public static function name( string $path, string $lang = '' ): string {
		$name = Settings::OPTION;
		foreach ( explode( '.', Schema::clean_path( $path ) ) as $key ) {
			$name .= '[' . $key . ']';
		}
		if ( '' !== $lang ) {
			$name .= '[' . sanitize_key( $lang ) . ']';
		}
		return $name;
	}

	/**
	 * Element id for a path (and language): lowercase, underscores only (wp_editor-safe).
	 */
	public static function id( string $path, string $lang = '' ): string {
		$id = 'gadgetim_' . str_replace( [ '.', '-' ], '_', Schema::clean_path( $path ) );
		if ( '' !== $lang ) {
			$id .= '_' . sanitize_key( $lang );
		}
		return $id;
	}

	/**
	 * Human label for a language code.
	 */
	public static function language_label( string $code ): string {
		return Options::LANGUAGE_LABELS[ $code ] ?? strtoupper( $code );
	}

	/**
	 * Extra attributes from the field definition as an HTML string (escaped).
	 */
	public static function attrs( array $field, array $extra = [] ): string {
		$attrs = array_merge( $field['attrs'] ?? [], $extra );
		$out   = '';
		foreach ( $attrs as $name => $attr_value ) {
			$name = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $name ) ) ?? '';
			if ( '' === $name || null === $attr_value || false === $attr_value ) {
				continue;
			}
			$out .= true === $attr_value ? ' ' . $name : ' ' . $name . '="' . esc_attr( (string) $attr_value ) . '"';
		}
		return $out;
	}
}
