<?php
/**
 * SEO module: meta description, canonical, social cards, titles of archives, JSON-LD and
 * robots.txt rules (architecture §5.7, D9: no third-party SEO plugin).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Seo;

/**
 * Module entry point (booted by Plugin::boot()). Registers hooks only.
 *
 * Classes: Text (clean/shorten/encode helpers), SettingsTab (Gadgetim → Settings → SEO),
 * Meta (<head> tags and archive titles), JsonLd (Organization/WebSite, BreadcrumbList,
 * WooCommerce product markup), Robots (robots.txt rules for parameter URLs, low-value
 * archives), Sitemap (encoded <loc>, no author sitemap), ShareJpeg (JPEG copies of WebP
 * share images for link previews).
 */
final class Module {

	/**
	 * Registers the SEO hooks. Nothing here reads options or translates at load time.
	 */
	public static function register(): void {
		add_action( 'init', [ SettingsTab::class, 'register_tab' ], 5 );
		Meta::register();
		JsonLd::register();
		Robots::register();
		Sitemap::register();
		ShareJpeg::register();
	}
}
