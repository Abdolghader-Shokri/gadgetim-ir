<?php
/**
 * Capability split for the admin (architecture D11, §7.1).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\Settings;

use Gadgetim\Core\Plugin;

/**
 * manage_options for Redirects, Strings, Tools, Languages and Currencies (whatever a
 * module declares); manage_woocommerce for everything else. Shop Managers hold
 * manage_woocommerce, so an open redirect or a language-pack download must never be
 * one compromised shop-manager account away.
 */
final class Capabilities {

	public const MANAGE_OPTIONS_TABS = [ 'redirects', 'strings', 'tools', 'languages', 'currencies' ];

	public const SHOP = 'manage_woocommerce';

	public const ADMIN = 'manage_options';

	/**
	 * Capability for a tab. The D11 list is a floor the gadgetim_tab_capability filter cannot lower.
	 *
	 * @param string     $tab_id Tab id.
	 * @param array|null $tab    Tab definition when already known (avoids a schema lookup).
	 */
	public static function for_tab( string $tab_id, ?array $tab = null ): string {
		$tab_id = sanitize_key( $tab_id );
		$fixed  = in_array( $tab_id, self::MANAGE_OPTIONS_TABS, true );

		if ( $fixed ) {
			$cap = self::ADMIN;
		} else {
			$declared = (string) ( $tab['capability'] ?? '' );
			if ( '' === $declared && null === $tab ) {
				$declared = (string) ( Schema::tab( $tab_id )['capability'] ?? '' );
			}
			$cap = '' === $declared ? self::default_capability() : self::map( $declared );
		}

		/**
		 * Filters the capability required for a settings tab. Cannot lower the D11 tabs below manage_options.
		 *
		 * @param string $cap    Capability.
		 * @param string $tab_id Tab id.
		 */
		$cap = (string) apply_filters( 'gadgetim_tab_capability', $cap, $tab_id );

		if ( $fixed && self::ADMIN !== $cap ) {
			$cap = self::ADMIN;
		}
		return '' === $cap ? self::default_capability() : $cap;
	}

	/**
	 * Whether the current user may use a tab.
	 */
	public static function can( string $tab_id ): bool {
		return current_user_can( self::for_tab( $tab_id ) );
	}

	/**
	 * Tabs the current user may see: [ id => tab ].
	 */
	public static function allowed_tabs(): array {
		$tabs = [];
		foreach ( Schema::tabs() as $id => $tab ) {
			if ( current_user_can( $tab['capability'] ) ) {
				$tabs[ $id ] = $tab;
			}
		}
		return $tabs;
	}

	/**
	 * Capability of the Gadgetim menu entry (the lowest one in use).
	 */
	public static function menu(): string {
		return self::default_capability();
	}

	/**
	 * manage_woocommerce while WooCommerce is active, manage_options otherwise
	 * (the WooCommerce capability may not exist on a site that never had WooCommerce).
	 */
	public static function default_capability(): string {
		return Plugin::has_woocommerce() ? self::SHOP : self::ADMIN;
	}

	/**
	 * option_page_capability_gadgetim_settings filter: lets Shop Managers reach options.php;
	 * the per-tab capability is enforced again inside Settings::sanitize().
	 */
	public static function option_page_capability( string $capability ): string {
		return self::default_capability();
	}

	/**
	 * Replaces manage_woocommerce by manage_options when WooCommerce is not active.
	 */
	private static function map( string $capability ): string {
		if ( self::SHOP === $capability && ! Plugin::has_woocommerce() ) {
			return self::ADMIN;
		}
		return $capability;
	}
}
