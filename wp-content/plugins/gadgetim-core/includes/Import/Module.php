<?php
/**
 * Import module: migration tooling for the old site (architecture §6.1) — admin batch runner
 * under Tools and the services the WP-CLI / plain-PHP entry points use.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Import;

/**
 * Module entry point (booted by Plugin::boot()).
 */
final class Module {

	/**
	 * Registers the admin tool and its AJAX handler. Nothing runs at load time.
	 */
	public static function register(): void {
		if ( is_admin() ) {
			Admin\ImportTool::register();
		}
	}
}
