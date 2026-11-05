<?php
/**
 * Plugin bootstrap: boots the modules in a fixed order, owns activation,
 * the WooCommerce check and the text domain.
 *
 * Module agents never edit this file. A module is a directory includes/<Module>/
 * with a class Gadgetim\Core\<Module>\Module exposing `public static function register(): void`.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core;

/**
 * Boot sequence and lifecycle of gadgetim-core.
 */
final class Plugin {

	/**
	 * Boot order. Support has no Module class and is bootstrapped explicitly.
	 * Missing modules are skipped.
	 */
	public const MODULES = [ 'Support', 'Settings', 'I18n', 'Pricing', 'Catalog', 'ContactMode', 'Checkout', 'Seo', 'Rest', 'Import', 'Cli' ];

	public const OPTION_VERSION = 'gadgetim_core_version';

	private static bool $booted = false;

	/** @var string[] Modules whose register() ran, in boot order. */
	private static array $loaded = [];

	/**
	 * Boots the plugin. Called once at plugin-file load (not on plugins_loaded).
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		register_activation_hook( GADGETIM_CORE_FILE, [ self::class, 'activate' ] );
		register_deactivation_hook( GADGETIM_CORE_FILE, [ self::class, 'deactivate' ] );

		add_action( 'init', [ self::class, 'load_textdomain' ], 0 );
		add_action( 'plugins_loaded', [ self::class, 'check_woocommerce' ], 1 );
		add_action( 'admin_init', [ self::class, 'maybe_upgrade' ], 5 );

		// Support has no Module class: bootstrap its two stateful helpers here.
		Support\Context::register();
		Support\Cache::register_triggers();

		foreach ( self::MODULES as $module ) {
			if ( 'Support' === $module ) {
				continue;
			}
			$class = __NAMESPACE__ . '\\' . $module . '\\Module';
			if ( class_exists( $class ) && method_exists( $class, 'register' ) ) {
				$class::register();
				self::$loaded[] = $module;
			}
		}

		/**
		 * Fires once all shipped modules have registered their hooks (at plugin-file load).
		 *
		 * @param string[] $loaded Names of the booted modules.
		 */
		do_action( 'gadgetim_core_booted', self::$loaded );
	}

	/**
	 * Names of the booted modules.
	 *
	 * @return string[]
	 */
	public static function modules(): array {
		return self::$loaded;
	}

	/**
	 * Whether a module booted (e.g. 'I18n').
	 */
	public static function has_module( string $module ): bool {
		return in_array( $module, self::$loaded, true );
	}

	/**
	 * Whether WooCommerce is loaded (reliable from plugins_loaded on).
	 */
	public static function has_woocommerce(): bool {
		return class_exists( 'WooCommerce', false ) && defined( 'WC_VERSION' );
	}

	/**
	 * Whether WooCommerce is loaded and at least the required version.
	 */
	public static function woocommerce_ok(): bool {
		return self::has_woocommerce() && version_compare( (string) WC_VERSION, GADGETIM_CORE_MIN_WC, '>=' );
	}

	/**
	 * plugins_loaded: WooCommerce absent or too old -> admin notice. The parts of the
	 * plugin that do not need WooCommerce keep running.
	 */
	public static function check_woocommerce(): void {
		if ( self::woocommerce_ok() ) {
			return;
		}
		add_action( 'admin_notices', [ self::class, 'woocommerce_notice' ] );
	}

	/**
	 * Prints the WooCommerce notice.
	 */
	public static function woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		if ( ! self::has_woocommerce() ) {
			$message = sprintf(
				/* translators: %s: minimum WooCommerce version */
				__( 'Gadgetim Core needs WooCommerce %s or newer. WooCommerce is not active, so pricing, catalog and checkout features are disabled until it is.', 'gadgetim-core' ),
				GADGETIM_CORE_MIN_WC
			);
		} else {
			$message = sprintf(
				/* translators: 1: minimum WooCommerce version, 2: installed WooCommerce version */
				__( 'Gadgetim Core needs WooCommerce %1$s or newer; version %2$s is active. Please update WooCommerce.', 'gadgetim-core' ),
				GADGETIM_CORE_MIN_WC,
				(string) WC_VERSION
			);
		}
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * init: loads the plugin text domain from /languages (.mo or .l10n.php).
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'gadgetim-core', false, dirname( plugin_basename( GADGETIM_CORE_FILE ) ) . '/languages' );
	}

	/**
	 * Activation: seeds default options (nothing is scheduled).
	 */
	public static function activate(): void {
		if ( class_exists( Settings\Settings::class ) ) {
			Settings\Settings::seed_defaults();
		}
		update_option( self::OPTION_VERSION, GADGETIM_CORE_VERSION, false );

		/**
		 * Fires on plugin activation, after the core options are seeded.
		 * Modules hook this from register() to seed their own options.
		 */
		do_action( 'gadgetim_core_activate' );
	}

	/**
	 * Deactivation: nothing to unschedule; modules may hook.
	 */
	public static function deactivate(): void {
		/**
		 * Fires on plugin deactivation.
		 */
		do_action( 'gadgetim_core_deactivate' );
	}

	/**
	 * admin_init: runs once per version change (covers junction-linked or FTP-replaced code).
	 */
	public static function maybe_upgrade(): void {
		$stored = (string) get_option( self::OPTION_VERSION, '' );
		if ( GADGETIM_CORE_VERSION === $stored ) {
			return;
		}
		if ( class_exists( Settings\Settings::class ) ) {
			Settings\Settings::seed_defaults();
		}
		update_option( self::OPTION_VERSION, GADGETIM_CORE_VERSION, false );

		/**
		 * Fires when the stored plugin version differs from the running one.
		 *
		 * @param string $from Previously stored version ('' on first run).
		 * @param string $to   Running version.
		 */
		do_action( 'gadgetim_core_upgrade', $stored, GADGETIM_CORE_VERSION );
	}

	/**
	 * Absolute path inside the plugin directory.
	 */
	public static function path( string $relative = '' ): string {
		return GADGETIM_CORE_DIR . ltrim( $relative, '/\\' );
	}

	/**
	 * URL inside the plugin directory.
	 */
	public static function url( string $relative = '' ): string {
		return GADGETIM_CORE_URL . ltrim( $relative, '/\\' );
	}

	/**
	 * Renders a view from templates/ (e.g. 'admin/settings-page'); the view reads `$args`.
	 *
	 * @param string $name Template name relative to templates/, without extension.
	 * @param array  $args Variables for the template.
	 */
	public static function view( string $name, array $args = [] ): void {
		$name = preg_replace( '#[^A-Za-z0-9_/-]#', '', $name );
		$file = GADGETIM_CORE_DIR . 'templates/' . $name . '.php';
		if ( '' === $name || ! is_file( $file ) ) {
			return;
		}
		( static function ( string $file, array $args ): void {
			include $file;
		} )( $file, $args );
	}

	/**
	 * Same as view() but returns the output.
	 *
	 * @param string $name Template name relative to templates/, without extension.
	 * @param array  $args Variables for the template.
	 */
	public static function render( string $name, array $args = [] ): string {
		ob_start();
		self::view( $name, $args );
		return (string) ob_get_clean();
	}
}
