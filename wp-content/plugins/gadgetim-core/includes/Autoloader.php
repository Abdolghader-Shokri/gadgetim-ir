<?php
/**
 * PSR-4-like autoloader: Gadgetim\Core\X\Y -> includes/X/Y.php
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core;

/**
 * Maps the Gadgetim\Core namespace onto the includes/ directory.
 */
final class Autoloader {

	public const PREFIX = 'Gadgetim\\Core\\';

	private static string $base_dir = '';

	private static bool $registered = false;

	/**
	 * Registers the autoloader once.
	 *
	 * @param string $base_dir Absolute path of the includes/ directory.
	 */
	public static function register( string $base_dir ): void {
		if ( self::$registered ) {
			return;
		}
		self::$base_dir   = rtrim( $base_dir, '/\\' ) . '/';
		self::$registered = spl_autoload_register( [ self::class, 'load' ], true, false );
	}

	/**
	 * Loads one class if it belongs to the plugin namespace and its file exists.
	 *
	 * Missing files are silently ignored so that `class_exists( Module::class )`
	 * for a module that is not shipped simply returns false.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( string $class ): void {
		$file = self::file_for( $class );
		if ( '' !== $file && is_file( $file ) ) {
			require $file;
		}
	}

	/**
	 * Resolves the file a class would live in, or '' when the class is not ours.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function file_for( string $class ): string {
		if ( 0 !== strncmp( $class, self::PREFIX, strlen( self::PREFIX ) ) ) {
			return '';
		}
		$relative = substr( $class, strlen( self::PREFIX ) );
		if ( '' === $relative || ! preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return '';
		}
		return self::$base_dir . str_replace( '\\', '/', $relative ) . '.php';
	}

	/**
	 * Absolute path of the includes/ directory (with trailing slash).
	 */
	public static function base_dir(): string {
		return self::$base_dir;
	}
}
