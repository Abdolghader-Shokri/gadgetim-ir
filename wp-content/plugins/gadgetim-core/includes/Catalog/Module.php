<?php
/**
 * Catalog module: product extras (subtitle, specs, FAQ, guarantee, views), popularity
 * ordering, brand helpers and LQIP placeholders (architecture §6).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Catalog;

/**
 * Module entry point (booted by Plugin::boot()). Registers hooks only.
 */
final class Module {

	/**
	 * Registers the catalog hooks. Nothing here touches WooCommerce or translations at load time.
	 */
	public static function register(): void {
		ProductExtras::register();
		Popularity::register();
		Views::register();
		Brands::register();
		Lqip::register();
		// Storefront features of the Aurora theme rebuild (each file owned by its own feature).
		foreach ( [ Filters::class, HomeContent::class ] as $feature ) {
			if ( class_exists( $feature ) ) {
				$feature::register();
			}
		}
	}
}
