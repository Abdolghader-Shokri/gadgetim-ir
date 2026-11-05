<?php
/**
 * Settings -> Languages (manage_options): launch gate, digits, fallback mode (architecture §3.1, §3.5).
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\Slugs;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Settings\Admin\SettingsPage;
use Gadgetim\Core\Settings\Schema;
use Gadgetim\Core\Settings\Settings;
use Gadgetim\Core\Support\Cache;

/**
 * Per language: public flag (the launch gate), digit set, fixed locale/prefix/direction, a
 * readiness checklist (top-level categories and WooCommerce pages translated, language
 * packs installed). Plus the fallback mode for untranslated items and the missing-string
 * collector toggle. Saved through admin-post with a nonce.
 */
final class LanguagesPage {

	public const TAB = 'languages';

	public const ACTION = 'gadgetim_save_languages';

	private const RESULT_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'add_tab' ], 5 );
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	/**
	 * init (5): the tab and the schema fields that back the i18n.* settings (sanitisation + defaults).
	 */
	public static function add_tab(): void {
		if ( ! class_exists( Schema::class ) ) {
			return;
		}
		Schema::add_tab(
			self::TAB,
			__( 'Languages', 'gadgetim-core' ),
			'manage_options',
			[
				'options' => [
					'label'  => __( 'Options', 'gadgetim-core' ),
					'fields' => [
						'i18n.fallback'        => [
							'type'        => 'select',
							'label'       => __( 'Untranslated item under /en/ or /ar/', 'gadgetim-core' ),
							'options'     => [
								'404'  => __( 'Not found (404)', 'gadgetim-core' ),
								'show' => __( 'Show the Persian content with a note (noindex)', 'gadgetim-core' ),
							],
							'default'     => '404',
							'description' => __( 'With "show", the page stays unreachable from any listing in that language; only direct links reach it.', 'gadgetim-core' ),
						],
						'i18n.collect_missing' => [
							'type'        => 'toggle',
							'label'       => __( 'Collect WooCommerce strings missing from the Arabic language pack while an administrator browses /ar/', 'gadgetim-core' ),
							'default'     => false,
							'description' => __( 'The collected strings appear on the Strings tab.', 'gadgetim-core' ),
						],
					],
				],
			],
			[
				'order'       => 70,
				'description' => __( 'Persian lives at the root; English and Arabic under /en/ and /ar/. A language becomes indexable (robots, sitemap, hreflang, switcher for visitors) only when it is public.', 'gadgetim-core' ),
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
		$result = self::take_result();
		echo '<div class="gtm-langs">';
		if ( is_array( $result ) ) {
			echo '<div class="notice notice-' . esc_attr( $result['type'] ) . ' inline"><p>' . esc_html( $result['message'] ) . '</p></div>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		wp_nonce_field( self::ACTION );

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ __( 'Language', 'gadgetim-core' ), __( 'Locale', 'gadgetim-core' ), __( 'URL', 'gadgetim-core' ), __( 'Direction', 'gadgetim-core' ), __( 'Digits', 'gadgetim-core' ), __( 'Public', 'gadgetim-core' ), __( 'Readiness', 'gadgetim-core' ) ] as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( Languages::all() as $code => $def ) {
			$is_default = Languages::DEFAULT === $code;
			$prefix     = Languages::prefix( $code );
			echo '<tr>';
			echo '<td><span class="gtm-langs__code">' . esc_html( $code ) . '</span> ' . esc_html( Languages::label( $code ) ) . '</td>';
			echo '<td><code>' . esc_html( Languages::locale( $code ) ) . '</code></td>';
			echo '<td><code dir="ltr">' . esc_html( '' === $prefix ? '/' : '/' . $prefix . '/' ) . '</code></td>';
			echo '<td>' . esc_html( Languages::dir( $code ) ) . '</td>';
			echo '<td><select name="languages[' . esc_attr( $code ) . '][digits]">';
			foreach ( [ 'fa' => __( 'Persian (۰۱۲)', 'gadgetim-core' ), 'arab' => __( 'Arabic-Indic (٠١٢)', 'gadgetim-core' ), 'latn' => __( 'Latin (012)', 'gadgetim-core' ) ] as $set => $label ) {
				echo '<option value="' . esc_attr( $set ) . '" ' . selected( Languages::digits( $code ), $set, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td>';
			echo '<td>';
			if ( $is_default ) {
				echo '<span class="description">' . esc_html__( 'Always', 'gadgetim-core' ) . '</span>';
			} else {
				echo '<label class="gtm-toggle"><input type="checkbox" class="gtm-toggle__input" name="languages[' . esc_attr( $code ) . '][public]" value="1" role="switch" ' . checked( Languages::is_public( $code ), true, false ) . '><span class="gtm-toggle__track" aria-hidden="true"><span class="gtm-toggle__thumb"></span></span><span class="gtm-toggle__text">' . esc_html__( 'Public', 'gadgetim-core' ) . '</span></label>';
			}
			echo '</td>';
			echo '<td>' . ( $is_default ? '' : self::readiness_html( $code ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Options', 'gadgetim-core' ) . '</h2>';
		echo '<table class="form-table gtm-table" role="presentation"><tbody>';
		$fallback = (string) Settings::get( 'i18n.fallback', '404' );
		echo '<tr><th scope="row"><label for="gtm-i18n-fallback">' . esc_html__( 'Untranslated item under /en/ or /ar/', 'gadgetim-core' ) . '</label></th><td>';
		echo '<select id="gtm-i18n-fallback" name="i18n_fallback">';
		echo '<option value="404" ' . selected( $fallback, '404', false ) . '>' . esc_html__( 'Not found (404)', 'gadgetim-core' ) . '</option>';
		echo '<option value="show" ' . selected( $fallback, 'show', false ) . '>' . esc_html__( 'Show the Persian content with a note (noindex)', 'gadgetim-core' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'With "show", the page stays unreachable from any listing in that language; only direct links reach it.', 'gadgetim-core' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><span class="gtm-field__label">' . esc_html__( 'Missing strings', 'gadgetim-core' ) . '</span></th><td>';
		echo '<label class="gtm-toggle" for="gtm-i18n-collect"><input type="checkbox" class="gtm-toggle__input" id="gtm-i18n-collect" name="collect_missing" value="1" role="switch" ' . checked( (bool) Settings::get( 'i18n.collect_missing', false ), true, false ) . '><span class="gtm-toggle__track" aria-hidden="true"><span class="gtm-toggle__thumb"></span></span><span class="gtm-toggle__text">' . esc_html__( 'Collect WooCommerce strings missing from the Arabic language pack while an administrator browses /ar/', 'gadgetim-core' ) . '</span></label>';
		echo '<p class="description">' . esc_html__( 'The collected strings appear on the Strings tab.', 'gadgetim-core' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<p class="description">' . wp_kses_post(
			sprintf(
				/* translators: %s: link to the Tools tab */
				__( 'Language packs (core and WooCommerce) are installed from the %s tab.', 'gadgetim-core' ),
				'<a href="' . esc_url( SettingsPage::url( 'tools' ) ) . '">' . esc_html__( 'Tools', 'gadgetim-core' ) . '</a>'
			)
		) . '</p>';

		submit_button( __( 'Save languages', 'gadgetim-core' ) );
		echo '</form></div>';
	}

	/**
	 * Readiness checklist of a language (information only; the flip stays a human decision).
	 */
	private static function readiness_html( string $lang ): string {
		$items = [];

		$top = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
				'fields'     => 'ids',
			]
		);
		$top       = is_array( $top ) ? array_map( 'intval', $top ) : [];
		$done      = 0;
		foreach ( $top as $term_id ) {
			if ( TermTranslations::is_published( $term_id, $lang ) ) {
				$done++;
			}
		}
		$items[] = [
			$top && $done === count( $top ),
			/* translators: 1: translated count, 2: total */
			sprintf( __( 'Top-level categories translated: %1$d / %2$d', 'gadgetim-core' ), $done, count( $top ) ),
		];

		$pages = [];
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $page ) {
				$id = (int) wc_get_page_id( $page );
				if ( $id > 0 ) {
					$pages[ $page ] = PostTranslations::is_published( $id, $lang );
				}
			}
		}
		$items[] = [
			$pages && ! in_array( false, $pages, true ),
			/* translators: %d: count */
			sprintf( __( 'Shop, cart, checkout and account pages translated: %1$d / %2$d', 'gadgetim-core' ), count( array_filter( $pages ) ), count( $pages ) ),
		];

		$published = count( Slugs::published_ids( $lang, 'product' ) );
		$items[]   = [
			$published > 0,
			/* translators: %d: count */
			sprintf( _n( '%d published product translation', '%d published product translations', $published, 'gadgetim-core' ), $published ),
		];

		$locale    = Languages::locale( $lang );
		$core_ok   = 'en_US' === $locale || in_array( $locale, get_available_languages(), true );
		$wc_ok     = 'en_US' === $locale || file_exists( WP_LANG_DIR . '/plugins/woocommerce-' . $locale . '.mo' ) || file_exists( WP_LANG_DIR . '/plugins/woocommerce-' . $locale . '.l10n.php' );
		$items[]   = [ $core_ok, sprintf( /* translators: %s: locale */ __( 'WordPress language pack %s', 'gadgetim-core' ), $locale ) ];
		$items[]   = [ $wc_ok, sprintf( /* translators: %s: locale */ __( 'WooCommerce language pack %s', 'gadgetim-core' ), $locale ) ];

		$html = '<ul class="gtm-langs__check">';
		foreach ( $items as [ $ok, $label ] ) {
			$html .= '<li class="' . ( $ok ? 'is-ok' : '' ) . '">' . esc_html( $label ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * admin-post handler.
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'gadgetim-core' ), 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer() above.
		$languages = isset( $_POST['languages'] ) && is_array( $_POST['languages'] ) ? wp_unslash( $_POST['languages'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in Languages::update().
		$fallback  = isset( $_POST['i18n_fallback'] ) ? sanitize_key( wp_unslash( (string) $_POST['i18n_fallback'] ) ) : '404';
		$collect   = ! empty( $_POST['collect_missing'] );
		// phpcs:enable

		$before = Languages::public_codes();
		Languages::update( is_array( $languages ) ? $languages : [] );
		$after = Languages::public_codes();

		if ( class_exists( Settings::class ) ) {
			Settings::update(
				[
					'i18n.fallback'        => in_array( $fallback, [ '404', 'show' ], true ) ? $fallback : '404',
					'i18n.collect_missing' => $collect,
				]
			);
		}
		Slugs::invalidate_all();
		if ( $before !== $after && class_exists( Cache::class ) ) {
			Cache::purge_all(); // robots.txt, hreflang and switcher output changed everywhere.
		}

		set_transient(
			self::result_key(),
			[
				'type'    => 'success',
				'message' => __( 'Languages saved.', 'gadgetim-core' ),
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
		return 'gadgetim_languages_result_' . get_current_user_id();
	}
}
