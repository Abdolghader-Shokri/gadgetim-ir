<?php
/**
 * Settings -> Strings (manage_options): WooCommerce-owned labels and ar-pack gaps.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\Strings;
use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Schema;

/**
 * Three groups: labels WooCommerce builds from its settings (gateways, shipping methods,
 * attributes, account menu), collected missing `woocommerce` strings (Arabic), and custom
 * entries. Saved through admin-post with a nonce; manage_options only (D11).
 */
final class StringsPage {

	public const TAB = 'strings';

	public const ACTION = 'gadgetim_save_strings';

	private const RESULT_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'add_tab' ], 5 );
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * init (5, after the text domain): the tab.
	 */
	public static function add_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_tab(
			self::TAB,
			__( 'Strings', 'gadgetim-core' ),
			'manage_options',
			[],
			[
				'order'       => 80,
				'description' => __( 'Translations for texts WooCommerce builds from its settings (gateway and shipping names, attribute labels, account menu) and for strings the Arabic WooCommerce language pack lacks.', 'gadgetim-core' ),
				'render'      => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * Tab renderer.
	 */
	public static function render( array $tab = [] ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$langs   = Languages::secondary_codes();
		$entries = Strings::all();
		$result  = self::take_result();

		echo '<div class="gtm-strings">';
		if ( is_array( $result ) ) {
			echo '<div class="notice notice-' . esc_attr( $result['type'] ) . ' inline"><p>' . esc_html( $result['message'] ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );

		$suggested = self::suggestions();
		$shown     = [];

		self::group(
			__( 'WooCommerce labels', 'gadgetim-core' ),
			__( 'Texts typed in Persian in the WooCommerce settings. Fill the other languages here; the storefront swaps them through the WooCommerce output filters.', 'gadgetim-core' ),
			$suggested,
			$entries,
			$langs,
			$shown,
			false
		);

		$missing = Strings::missing();
		if ( $missing ) {
			$rows = [];
			foreach ( $missing as $hash => $row ) {
				$rows[ (string) $hash ] = [
					'source'  => (string) ( $row['source'] ?? '' ),
					'domain'  => (string) ( $row['domain'] ?? 'woocommerce' ),
					'context' => (string) ( $row['context'] ?? '' ),
					'where'   => __( 'Seen untranslated on the Arabic storefront', 'gadgetim-core' ),
				];
			}
			self::group(
				__( 'Missing in the WooCommerce language pack', 'gadgetim-core' ),
				__( 'Collected while browsing the Arabic storefront as an administrator (Languages tab). A saved translation is applied through gettext for the woocommerce text domain.', 'gadgetim-core' ),
				$rows,
				$entries,
				$langs,
				$shown,
				true
			);
		}

		$custom = [];
		foreach ( $entries as $hash => $entry ) {
			if ( isset( $shown[ $hash ] ) ) {
				continue;
			}
			$custom[ $hash ] = [
				'source'  => (string) $entry['source'],
				'domain'  => (string) ( $entry['domain'] ?? 'woocommerce' ),
				'context' => (string) ( $entry['context'] ?? '' ),
				'where'   => '',
			];
		}
		self::group( __( 'Custom entries', 'gadgetim-core' ), '', $custom, $entries, $langs, $shown, true );

		echo '<div class="gtm-strings__group"><h2>' . esc_html__( 'Add a string', 'gadgetim-core' ) . '</h2>';
		echo '<table class="widefat striped"><tbody><tr>';
		echo '<td><label class="screen-reader-text" for="gtm-new-source">' . esc_html__( 'Source text', 'gadgetim-core' ) . '</label><input type="text" class="regular-text" id="gtm-new-source" name="new[source]" placeholder="' . esc_attr__( 'Exact source text', 'gadgetim-core' ) . '" dir="auto"></td>';
		echo '<td><input type="text" class="regular-text" name="new[context]" placeholder="' . esc_attr__( 'Context (optional)', 'gadgetim-core' ) . '" dir="ltr"></td>';
		echo '<td><input type="text" class="regular-text" name="new[domain]" value="woocommerce" dir="ltr"></td>';
		foreach ( $langs as $lang ) {
			echo '<td><input type="text" class="regular-text" name="new[' . esc_attr( $lang ) . ']" placeholder="' . esc_attr( Languages::label( $lang ) ) . '" dir="' . esc_attr( Languages::dir( $lang ) ) . '"></td>';
		}
		echo '</tr></tbody></table></div>';

		submit_button( __( 'Save strings', 'gadgetim-core' ) );
		echo '</form></div>';
	}

	/**
	 * One group table.
	 *
	 * @param array<string,array> $rows      [ hash => [ source, domain, context, where ] ].
	 * @param array<string,array> $entries   Stored entries.
	 * @param string[]            $langs     Secondary languages.
	 * @param array<string,true>  $shown     Hashes already rendered (by reference).
	 * @param bool                $deletable Show the delete checkbox.
	 */
	private static function group( string $title, string $description, array $rows, array $entries, array $langs, array &$shown, bool $deletable ): void {
		echo '<div class="gtm-strings__group"><h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		if ( ! $rows ) {
			echo '<p class="description">' . esc_html__( 'Nothing here yet.', 'gadgetim-core' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Source (Persian / English)', 'gadgetim-core' ) . '</th>';
		foreach ( $langs as $lang ) {
			echo '<th>' . esc_html( Languages::label( $lang ) ) . '</th>';
		}
		if ( $deletable ) {
			echo '<th>' . esc_html__( 'Delete', 'gadgetim-core' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $hash => $row ) {
			$hash          = (string) $hash;
			$shown[ $hash ] = true;
			$entry         = $entries[ $hash ] ?? [];
			echo '<tr>';
			echo '<td>';
			echo '<span class="gtm-strings__source" dir="auto">' . esc_html( $row['source'] ) . '</span>';
			if ( '' !== $row['context'] ) {
				echo '<br><small>' . esc_html__( 'Context:', 'gadgetim-core' ) . ' <code>' . esc_html( $row['context'] ) . '</code></small>';
			}
			if ( '' !== $row['where'] ) {
				echo '<br><small class="description">' . esc_html( $row['where'] ) . '</small>';
			}
			echo '<input type="hidden" name="strings[' . esc_attr( $hash ) . '][source]" value="' . esc_attr( $row['source'] ) . '">';
			echo '<input type="hidden" name="strings[' . esc_attr( $hash ) . '][domain]" value="' . esc_attr( $row['domain'] ) . '">';
			echo '<input type="hidden" name="strings[' . esc_attr( $hash ) . '][context]" value="' . esc_attr( $row['context'] ) . '">';
			echo '</td>';
			foreach ( $langs as $lang ) {
				echo '<td><input type="text" class="regular-text" name="strings[' . esc_attr( $hash ) . '][' . esc_attr( $lang ) . ']" value="' . esc_attr( (string) ( $entry[ $lang ] ?? '' ) ) . '" dir="' . esc_attr( Languages::dir( $lang ) ) . '"></td>';
			}
			if ( $deletable ) {
				echo '<td><label><input type="checkbox" name="delete[]" value="' . esc_attr( $hash ) . '"> <span class="screen-reader-text">' . esc_html__( 'Delete', 'gadgetim-core' ) . '</span></label></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Labels WooCommerce builds from its settings: gateway title/description, shipping method
	 * titles, attribute labels, account menu items.
	 *
	 * @return array<string,array{source:string,domain:string,context:string,where:string}>
	 */
	public static function suggestions(): array {
		$rows = [];
		$add  = static function ( string $source, string $where ) use ( &$rows ): void {
			$source = trim( $source );
			if ( '' === $source ) {
				return;
			}
			$hash = Strings::hash( $source );
			if ( ! isset( $rows[ $hash ] ) ) {
				$rows[ $hash ] = [
					'source'  => $source,
					'domain'  => 'woocommerce',
					'context' => '',
					'where'   => $where,
				];
			}
		};
		if ( ! function_exists( 'WC' ) ) {
			return $rows;
		}
		$gateways = WC()->payment_gateways();
		if ( $gateways && method_exists( $gateways, 'payment_gateways' ) ) {
			foreach ( (array) $gateways->payment_gateways() as $gateway ) {
				if ( ! is_object( $gateway ) || 'yes' !== ( $gateway->enabled ?? 'no' ) ) {
					continue;
				}
				$add( (string) ( $gateway->title ?? '' ), __( 'Payment gateway title', 'gadgetim-core' ) );
				$add( wp_strip_all_tags( (string) ( $gateway->description ?? '' ) ), __( 'Payment gateway description', 'gadgetim-core' ) );
			}
		}
		if ( class_exists( 'WC_Shipping_Zones' ) ) {
			$zones   = \WC_Shipping_Zones::get_zones();
			$zones[] = [ 'shipping_methods' => \WC_Shipping_Zones::get_zone( 0 )->get_shipping_methods() ];
			foreach ( $zones as $zone ) {
				foreach ( (array) ( $zone['shipping_methods'] ?? [] ) as $method ) {
					if ( is_object( $method ) && method_exists( $method, 'get_title' ) ) {
						$add( (string) $method->get_title(), __( 'Shipping method', 'gadgetim-core' ) );
					}
				}
			}
		}
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $attribute ) {
				$add( (string) ( $attribute->attribute_label ?? '' ), __( 'Attribute label', 'gadgetim-core' ) );
			}
		}
		if ( function_exists( 'wc_get_account_menu_items' ) ) {
			foreach ( (array) wc_get_account_menu_items() as $label ) {
				if ( is_string( $label ) && preg_match( '/[\x{0600}-\x{06FF}]/u', $label ) ) {
					$add( $label, __( 'Account menu', 'gadgetim-core' ) ); // Only labels that are not already English source strings.
				}
			}
		}
		return $rows;
	}

	/**
	 * admin-post handler.
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'gadgetim-core' ), 403 );
		}
		$langs = Languages::secondary_codes();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() above.
		$strings = isset( $_POST['strings'] ) && is_array( $_POST['strings'] ) ? wp_unslash( $_POST['strings'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by Strings::set().
		$delete  = isset( $_POST['delete'] ) && is_array( $_POST['delete'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['delete'] ) ) : [];
		$new     = isset( $_POST['new'] ) && is_array( $_POST['new'] ) ? wp_unslash( $_POST['new'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable

		$delete  = array_values( array_filter( array_map( static fn( $h ): string => preg_replace( '/[^a-f0-9]/', '', (string) $h ) ?? '', $delete ) ) );
		$deleted = array_fill_keys( $delete, true );
		$saved   = 0;
		$added   = [];

		foreach ( (array) $strings as $hash => $row ) {
			if ( ! is_array( $row ) || isset( $deleted[ (string) $hash ] ) ) {
				continue;
			}
			$source = is_scalar( $row['source'] ?? null ) ? (string) $row['source'] : '';
			if ( '' === trim( $source ) ) {
				continue;
			}
			$translations = [];
			$any          = false;
			foreach ( $langs as $lang ) {
				$translations[ $lang ] = is_scalar( $row[ $lang ] ?? null ) ? (string) $row[ $lang ] : '';
				if ( '' !== trim( $translations[ $lang ] ) ) {
					$any = true;
				}
			}
			$exists = isset( Strings::all()[ Strings::hash( Strings::sanitize_source( $source ) ) ] );
			if ( ! $any && ! $exists ) {
				continue; // Untouched suggestion.
			}
			Strings::set( $source, $translations, (string) ( $row['domain'] ?? 'woocommerce' ), (string) ( $row['context'] ?? '' ) );
			$saved++;
			$added[] = (string) $hash;
		}

		if ( is_array( $new ) && is_scalar( $new['source'] ?? null ) && '' !== trim( (string) $new['source'] ) ) {
			$translations = [];
			foreach ( $langs as $lang ) {
				$translations[ $lang ] = is_scalar( $new[ $lang ] ?? null ) ? (string) $new[ $lang ] : '';
			}
			Strings::set( (string) $new['source'], $translations, (string) ( $new['domain'] ?? 'woocommerce' ), (string) ( $new['context'] ?? '' ) );
			$saved++;
		}

		if ( $delete ) {
			Strings::delete( $delete );
			Strings::forget_missing( $delete );
		}
		if ( $added ) {
			Strings::forget_missing( $added );
		}
		if ( class_exists( \Gadgetim\Core\Support\Cache::class ) ) {
			\Gadgetim\Core\Support\Cache::purge_all();
		}

		set_transient(
			self::result_key(),
			[
				'type'    => 'success',
				/* translators: 1: saved count, 2: deleted count */
				'message' => sprintf( __( '%1$d string(s) saved, %2$d deleted.', 'gadgetim-core' ), $saved, count( $delete ) ),
			],
			self::RESULT_TTL
		);
		wp_safe_redirect( SettingsPage::url( self::TAB ) );
		exit;
	}

	private static function take_result(): ?array {
		$result = get_transient( self::result_key() );
		if ( ! is_array( $result ) ) {
			return null;
		}
		delete_transient( self::result_key() );
		return $result;
	}

	private static function result_key(): string {
		return 'gadgetim_strings_result_' . get_current_user_id();
	}
}
