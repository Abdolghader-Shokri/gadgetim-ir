<?php
/**
 * The "Gadgetim" admin menu and the tabbed settings page.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings\Admin;

use Gadgetim\Core\Plugin;
use Gadgetim\Core\Settings\Capabilities;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Options;

/**
 * Top-level menu "Gadgetim" (dashicons-store). Other modules add sub-pages with
 * add_submenu_page( SettingsPage::SLUG, … ) on admin_menu at priority 10 or later.
 */
final class SettingsPage {

	public const SLUG = 'gadgetim';

	private static string $hook_suffix = '';

	/**
	 * admin_menu (priority 9): menu + "Settings" sub-entry.
	 */
	public static function register_menu(): void {
		$cap  = Capabilities::menu();
		$hook = add_menu_page(
			__( 'Gadgetim', 'gadgetim-core' ),
			__( 'Gadgetim', 'gadgetim-core' ),
			$cap,
			self::SLUG,
			[ self::class, 'render' ],
			'dashicons-store',
			56
		);
		add_submenu_page( self::SLUG, __( 'Gadgetim settings', 'gadgetim-core' ), __( 'Settings', 'gadgetim-core' ), $cap, self::SLUG, [ self::class, 'render' ] );
		self::$hook_suffix = is_string( $hook ) ? $hook : '';
	}

	/**
	 * URL of the settings page, optionally for a tab.
	 */
	public static function url( string $tab = '', array $args = [] ): string {
		$query = [ 'page' => self::SLUG ];
		if ( '' !== $tab ) {
			$query['tab'] = sanitize_key( $tab );
		}
		return add_query_arg( $query + $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Whether an admin page hook belongs to the Gadgetim menu (its own page or a sub-page).
	 */
	public static function is_our_screen( string $hook ): bool {
		return ( '' !== self::$hook_suffix && $hook === self::$hook_suffix ) || str_starts_with( $hook, self::SLUG . '_page_' );
	}

	/**
	 * admin_enqueue_scripts: assets only on Gadgetim pages.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! self::is_our_screen( $hook ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.css' ), [], GADGETIM_CORE_VERSION );
		wp_enqueue_script( 'gadgetim-admin', Plugin::url( 'assets/admin/admin.js' ), [ 'jquery' ], GADGETIM_CORE_VERSION, true );
		wp_localize_script(
			'gadgetim-admin',
			'gadgetimAdmin',
			[
				'i18n' => [
					'selectImage' => __( 'Select image', 'gadgetim-core' ),
					'useImage'    => __( 'Use this image', 'gadgetim-core' ),
					'remove'      => __( 'Remove', 'gadgetim-core' ),
					'moveUp'      => __( 'Move up', 'gadgetim-core' ),
					'moveDown'    => __( 'Move down', 'gadgetim-core' ),
					'confirm'     => __( 'Are you sure?', 'gadgetim-core' ),
				],
			]
		);
	}

	/**
	 * Renders the page.
	 */
	public static function render(): void {
		$tabs = Capabilities::allowed_tabs();
		if ( ! $tabs ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'gadgetim-core' ), 403 );
		}
		$current = self::current_tab( $tabs );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; options.php verified the save.
		if ( isset( $_GET['settings-updated'] ) && ! get_settings_errors( Settings::GROUP ) ) {
			add_settings_error( Settings::GROUP, 'gadgetim_saved', __( 'Settings saved.', 'gadgetim-core' ), 'success' );
		}

		Plugin::view(
			'admin/settings-page',
			[
				'tabs'    => $tabs,
				'current' => $current,
				'tab'     => $tabs[ $current ],
			]
		);
	}

	/**
	 * Renders the body of a tab: custom renderer, or the schema-driven form.
	 */
	public static function render_tab_content( string $id, array $tab ): void {
		if ( is_callable( $tab['render'] ?? null ) ) {
			call_user_func( $tab['render'], $tab );
			return;
		}
		Plugin::view(
			'admin/tab-form',
			[
				'tab_id'    => $id,
				'tab'       => $tab,
				'languages' => Options::languages(),
			]
		);
	}

	/**
	 * The requested tab when it exists and is allowed, else the first allowed in-page tab.
	 */
	public static function current_tab( array $tabs ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';
		if ( '' !== $requested && isset( $tabs[ $requested ] ) && '' === $tabs[ $requested ]['url'] ) {
			return $requested;
		}
		foreach ( $tabs as $id => $tab ) {
			if ( '' === $tab['url'] ) {
				return (string) $id;
			}
		}
		return (string) array_key_first( $tabs );
	}
}
