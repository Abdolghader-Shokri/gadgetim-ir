<?php
/**
 * Security module: the custom admin login address, the HTTPS redirect of the storefront and the
 * hardening of docs/architecture.md (XML-RPC, user enumeration, comments, login errors).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Security;

/**
 * Module entry point (booted by Plugin::boot()). Registers hooks only.
 */
final class Module {

	/**
	 * Registers the security hooks.
	 */
	public static function register(): void {
		LoginSlug::register();
		HttpsRedirect::register();
		Hardening::register();
	}
}
