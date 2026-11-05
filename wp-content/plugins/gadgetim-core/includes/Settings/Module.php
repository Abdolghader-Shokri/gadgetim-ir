<?php
/**
 * Settings module: the Gadgetim admin menu, the settings option, notices and tools.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings;

/**
 * Module entry point (booted by Plugin::boot()).
 */
final class Module {

	/**
	 * Registers hooks only; nothing here runs translation functions at load time.
	 */
	public static function register(): void {
		// Labels are translated when the schema is built; rebuild it once the text domain is loaded.
		add_action( 'init', [ Schema::class, 'invalidate' ], 1 );

		add_action( 'admin_menu', [ Admin\SettingsPage::class, 'register_menu' ], 9 );
		add_action( 'admin_init', [ Settings::class, 'register_setting' ] );
		add_action( 'admin_init', [ Admin\Tools::class, 'register_core_tools' ] );
		add_action( 'admin_enqueue_scripts', [ Admin\SettingsPage::class, 'enqueue' ] );

		add_filter( 'option_page_capability_' . Settings::GROUP, [ Capabilities::class, 'option_page_capability' ] );
		add_action( 'update_option_' . Settings::OPTION, [ self::class, 'on_updated' ], 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, [ self::class, 'on_added' ], 10, 2 );

		add_action( 'admin_post_' . Admin\Tools::ACTION, [ Admin\Tools::class, 'handle' ] );
		add_action( 'admin_post_' . Admin\Notices::DISMISS_ACTION, [ Admin\Notices::class, 'handle_dismiss' ] );
		add_action( 'admin_notices', [ Admin\Notices::class, 'render' ] );
	}

	/**
	 * update_option_gadgetim_settings.
	 *
	 * @param mixed $old Previous value.
	 * @param mixed $new New value.
	 */
	public static function on_updated( mixed $old, mixed $new ): void {
		Settings::flush_cache();

		/**
		 * Fires after gadgetim_settings changed (form save or programmatic). Support\Cache purges on it.
		 *
		 * @param array $new New settings.
		 * @param array $old Previous settings.
		 */
		do_action( 'gadgetim_settings_saved', is_array( $new ) ? $new : [], is_array( $old ) ? $old : [] );
	}

	/**
	 * add_option_gadgetim_settings (first save).
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value.
	 */
	public static function on_added( string $option, mixed $value ): void {
		self::on_updated( [], $value );
	}
}
