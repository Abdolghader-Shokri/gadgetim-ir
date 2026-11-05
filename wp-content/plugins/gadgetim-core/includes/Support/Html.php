<?php
/**
 * Small HTML building and escaping helpers.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Support;

/**
 * Escape-late helpers: every method returns markup whose dynamic parts are escaped.
 */
final class Html {

	private const VOID = [ 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr' ];

	/** JSON flags for data embedded in HTML (architecture §5.10). */
	public const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

	/**
	 * Builds an attribute string (with a leading space when not empty).
	 * true -> bare attribute; false/null -> omitted; arrays -> space-joined (class lists).
	 *
	 * @param array<string,mixed> $attrs Attribute map.
	 */
	public static function attrs( array $attrs ): string {
		$out = '';
		foreach ( $attrs as $name => $value ) {
			$name = strtolower( preg_replace( '/[^A-Za-z0-9_:-]/', '', (string) $name ) ?? '' );
			if ( '' === $name || null === $value || false === $value ) {
				continue;
			}
			if ( true === $value ) {
				$out .= ' ' . $name;
				continue;
			}
			if ( is_array( $value ) ) {
				$value = self::classes( ...$value );
				if ( '' === $value ) {
					continue;
				}
			}
			$value = (string) $value;
			if ( 'href' === $name || 'src' === $name || 'action' === $name || 'poster' === $name ) {
				$out .= ' ' . $name . '="' . esc_url( $value ) . '"';
			} else {
				$out .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
		}
		return $out;
	}

	/**
	 * Builds an element. $inner is trusted markup (escape it before passing).
	 *
	 * @param string              $name  Tag name.
	 * @param array<string,mixed> $attrs Attributes.
	 * @param string|null         $inner Inner HTML, or null for a void element.
	 */
	public static function tag( string $name, array $attrs = [], ?string $inner = null ): string {
		$name = strtolower( preg_replace( '/[^A-Za-z0-9-]/', '', $name ) ?? 'div' );
		if ( '' === $name ) {
			$name = 'div';
		}
		$open = '<' . $name . self::attrs( $attrs ) . '>';
		if ( in_array( $name, self::VOID, true ) ) {
			return $open;
		}
		return $open . ( $inner ?? '' ) . '</' . $name . '>';
	}

	/**
	 * Element with escaped text content.
	 *
	 * @param string              $name  Tag name.
	 * @param string              $text  Plain text (escaped here).
	 * @param array<string,mixed> $attrs Attributes.
	 */
	public static function text( string $name, string $text, array $attrs = [] ): string {
		return self::tag( $name, $attrs, esc_html( $text ) );
	}

	/**
	 * Joins class names, skipping empty/false values.
	 */
	public static function classes( mixed ...$parts ): string {
		$classes = [];
		foreach ( $parts as $part ) {
			if ( is_array( $part ) ) {
				$part = self::classes( ...$part );
			}
			if ( ! is_string( $part ) || '' === trim( $part ) ) {
				continue;
			}
			foreach ( preg_split( '/\s+/', trim( $part ) ) ?: [] as $class ) {
				$classes[ sanitize_html_class( $class ) ] = true;
			}
		}
		unset( $classes[''] );
		return implode( ' ', array_keys( $classes ) );
	}

	/**
	 * <bdi> around a mixed-direction run (numbers, codes).
	 */
	public static function bdi( string $text, array $attrs = [] ): string {
		return self::text( 'bdi', $text, $attrs );
	}

	/**
	 * LTR isolate for phone numbers, SKUs, URLs inside RTL text.
	 */
	public static function ltr( string $text, string $tag = 'span', array $attrs = [] ): string {
		$attrs['dir'] = 'ltr';
		return self::text( $tag, $text, $attrs );
	}

	/**
	 * Allowed inline HTML for short settings texts (badges, hints).
	 */
	public static function kses_inline( string $html ): string {
		return wp_kses(
			$html,
			[
				'a'      => [ 'href' => true, 'title' => true, 'rel' => true, 'target' => true ],
				'strong' => [],
				'b'      => [],
				'em'     => [],
				'i'      => [],
				'span'   => [ 'class' => true, 'dir' => true, 'lang' => true ],
				'bdi'    => [],
				'br'     => [],
			]
		);
	}

	/**
	 * JSON for an attribute value or inline script data (architecture §5.10 flags).
	 */
	public static function json( mixed $data ): string {
		$json = wp_json_encode( $data, self::JSON_FLAGS );
		return false === $json ? 'null' : $json;
	}

	/**
	 * <script type="application/json" id="..."> block (e.g. gadgetim-config).
	 */
	public static function json_script( string $id, mixed $data ): string {
		return '<script type="application/json" id="' . esc_attr( $id ) . '">' . self::json( $data ) . '</script>';
	}

	/**
	 * data-* attribute carrying JSON.
	 */
	public static function data_attr( string $name, mixed $data ): string {
		$name = 'data-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $name ) );
		return ' ' . $name . '="' . esc_attr( self::json( $data ) ) . '"';
	}

	/**
	 * Inline SVG icon reference from the theme sprite: <svg class="icon" aria-hidden="true"><use href="#name"/></svg>.
	 */
	public static function icon( string $name, string $class = 'icon' ): string {
		$name = sanitize_html_class( $name );
		return '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"><use href="#' . esc_attr( $name ) . '"></use></svg>';
	}
}
