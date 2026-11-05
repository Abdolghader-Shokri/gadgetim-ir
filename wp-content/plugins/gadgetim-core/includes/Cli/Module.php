<?php
/**
 * WP-CLI module: registers `wp gadgetim …` only when WP-CLI runs the request.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Cli;

/**
 * Module entry point (booted by Plugin::boot()).
 */
final class Module {

	/**
	 * Registers the command namespace with WP-CLI (no-op on web requests).
	 */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}
		\WP_CLI::add_command( 'gadgetim', Commands::class );
	}
}
