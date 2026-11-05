<?php
/**
 * I18n module: trilingual routing and translations (architecture §3, D2/D10/D12).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n;

/**
 * Booted by Plugin::boot() at plugin-file load, right after Settings. The request language
 * is detected here, before WooCommerce loads (plugins load alphabetically); everything else
 * only adds hooks.
 */
final class Module {

	/**
	 * Registers hooks only; nothing here translates at load time.
	 */
	public static function register(): void {
		Languages::init();

		add_action( 'gadgetim_core_activate', [ Languages::class, 'seed' ] );
		add_action( 'gadgetim_core_upgrade', [ Languages::class, 'seed' ], 10, 2 );
		add_action( 'plugins_loaded', [ self::class, 'announce' ], 0 );

		Urls::register();
		Locale::register();
		PostTranslations::register();
		TermTranslations::register();
		Slugs::register();
		Visibility::register();
		Hreflang::register();
		Search::register();
		Sitemaps::register();
		Menus::register();
		Strings::register();
		WooCommerce::register();
		Orders::register();
		Emails::register();
		Comments::register();
		SiteIdentity::register();
		Rest\Search::register();

		if ( is_admin() ) {
			Admin\Assets::register();
			Admin\TranslationMetabox::register();
			Admin\TermFields::register();
			Admin\StringsPage::register();
			Admin\LanguagesPage::register();
			Admin\Tools::register();
			Admin\OrderLanguage::register();
		}
	}

	/**
	 * plugins_loaded (0): tells other code which language this request runs in.
	 */
	public static function announce(): void {
		/**
		 * Fires once the request language is known and every plugin is loaded.
		 *
		 * @param string $lang Language code ('fa', 'en', 'ar').
		 */
		do_action( 'gadgetim_language_detected', Languages::current() );
	}
}
