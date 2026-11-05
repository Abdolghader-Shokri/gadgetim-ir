<?php
/**
 * Inline admin CSS/JS for the translation metabox, term fields and the I18n settings tabs.
 *
 * @package Gadgetim\Core
 */

declare( strict_types=1 );

namespace Gadgetim\Core\I18n\Admin;

use Gadgetim\Core\I18n\Languages;
use Gadgetim\Core\I18n\PostTranslations;
use Gadgetim\Core\I18n\TermTranslations;
use Gadgetim\Core\Settings\Admin\SettingsPage;

/**
 * The module owns no files under assets/, so its small styles and behaviour ship inline on
 * the screens that need them (vanilla JS, logical CSS properties for RTL/LTR dashboards).
 */
final class Assets {

	public const HANDLE = 'gadgetim-i18n-admin';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ], 20, 1 );
	}

	/**
	 * admin_enqueue_scripts: post edit screens of translatable types, term screens of
	 * translatable taxonomies and the Gadgetim settings pages.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function enqueue( $hook ): void {
		$hook   = (string) $hook;
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$load   = false;
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && $screen && PostTranslations::supports( (string) $screen->post_type ) ) {
			$load = true;
		} elseif ( in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) && $screen && TermTranslations::supports( (string) $screen->taxonomy ) ) {
			$load = true;
		} elseif ( class_exists( SettingsPage::class ) && SettingsPage::is_our_screen( $hook ) ) {
			$load = true;
		}
		if ( ! $load ) {
			return;
		}
		wp_register_style( self::HANDLE, false, [], GADGETIM_CORE_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, self::css() );

		wp_register_script( self::HANDLE, false, [], GADGETIM_CORE_VERSION, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script( self::HANDLE );
		wp_add_inline_script(
			self::HANDLE,
			'window.gadgetimI18n = ' . wp_json_encode(
				[
					'langs' => Languages::secondary_codes(),
					'i18n'  => [
						'copied' => __( 'Copied from the Persian field.', 'gadgetim-core' ),
					],
				],
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
			) . ';',
			'before'
		);
		wp_add_inline_script( self::HANDLE, self::js() );
	}

	/**
	 * Styles.
	 */
	private static function css(): string {
		return <<<'CSS'
.gtm-tr{--gtm-line:#c3c4c7;--gtm-smoke:#646970;--gtm-plaster:#f6f7f7;--gtm-accent:#b5331d;--gtm-ok:#1f7a4d}
.gtm-tr__tabs{display:flex;flex-wrap:wrap;gap:.25rem;margin:0 0 1rem;border-block-end:1px solid var(--gtm-line)}
.gtm-tr__tab{appearance:none;background:transparent;border:1px solid transparent;border-block-end:0;padding:.5rem .9rem;cursor:pointer;font:inherit;color:var(--gtm-smoke);border-start-start-radius:4px;border-start-end-radius:4px;display:inline-flex;align-items:center;gap:.4rem}
.gtm-tr__tab.is-active{background:#fff;border-color:var(--gtm-line);color:#1d2327;margin-block-end:-1px;font-weight:600}
.gtm-tr__code{font:600 11px/1.6 ui-monospace,Menlo,Consolas,monospace;text-transform:uppercase;background:var(--gtm-plaster);border:1px solid var(--gtm-line);border-radius:4px;padding:0 4px}
.gtm-tr__badge{font-size:11px;border-radius:999px;padding:1px 7px;background:var(--gtm-plaster);color:var(--gtm-smoke)}
.gtm-tr__badge--published{background:#e6f4ec;color:var(--gtm-ok)}
.gtm-tr__badge--draft{background:#fbeae6;color:var(--gtm-accent)}
.gtm-tr__panel[hidden]{display:none}
.gtm-tr__row{display:grid;grid-template-columns:160px minmax(0,1fr);gap:.5rem 1rem;align-items:start;margin:0 0 .9rem}
.gtm-tr__row>label,.gtm-tr__row>.gtm-tr__label{font-weight:600;padding-block-start:5px}
.gtm-tr__control{min-width:0}
.gtm-tr__control .regular-text,.gtm-tr__control textarea{width:100%;max-width:720px}
.gtm-tr__control textarea{min-height:5em}
.gtm-tr__control .wp-editor-wrap{max-width:720px}
.gtm-tr__copy{margin-inline-start:.5rem;font-size:12px;vertical-align:middle;color:var(--gtm-smoke)}
.gtm-tr__copy:hover{color:#1d2327}
.gtm-tr__hint{color:var(--gtm-smoke);font-size:12px;margin:.25rem 0 0}
.gtm-tr__slugbase{color:var(--gtm-smoke);font-size:12px;direction:ltr;unicode-bidi:isolate}
.gtm-tr input[dir="ltr"],.gtm-tr textarea[dir="ltr"]{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.gtm-tr__status{max-width:220px}
@media (max-width:782px){.gtm-tr__row{grid-template-columns:1fr}}
.gtm-term-tr{border:1px solid var(--gtm-line,#c3c4c7);border-radius:6px;padding:.75rem 1rem;margin-block:1rem}
.gtm-term-tr h3{margin:.25rem 0 .75rem;font-size:13px}
.gtm-term-tr .gtm-tr__row{grid-template-columns:120px minmax(0,1fr)}
.form-table .gtm-term-tr{margin-block:0}
.gtm-strings table.widefat td input.regular-text,.gtm-strings table.widefat td textarea{width:100%}
.gtm-strings table.widefat td{vertical-align:top}
.gtm-strings__source{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;direction:ltr;unicode-bidi:isolate;max-width:28ch;overflow-wrap:anywhere}
.gtm-strings__group{margin-block:1.5rem 0}
.gtm-strings__group h2{font-size:1.1rem;margin-block:0 .5rem}
.gtm-langs table.widefat td,.gtm-langs table.widefat th{vertical-align:middle}
.gtm-langs__code{font:600 12px/1.6 ui-monospace,Menlo,Consolas,monospace;text-transform:uppercase}
.gtm-langs__check{display:grid;gap:.35rem;margin:.5rem 0 0;padding:0;list-style:none}
.gtm-langs__check li::before{content:"\25CB";margin-inline-end:.4rem;color:var(--gtm-smoke,#646970)}
.gtm-langs__check li.is-ok::before{content:"\25CF";color:var(--gtm-ok,#1f7a4d)}
.gtm-langs__actions{margin-block-start:1rem}
CSS;
	}

	/**
	 * Behaviour: tabs, "copy from Persian" buttons.
	 */
	private static function js(): string {
		return <<<'JS'
( function () {
	'use strict';
	function activate( root, lang ) {
		root.querySelectorAll( '[data-gtm-tab]' ).forEach( function ( tab ) {
			var on = tab.getAttribute( 'data-gtm-tab' ) === lang;
			tab.classList.toggle( 'is-active', on );
			tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
		} );
		root.querySelectorAll( '[data-gtm-panel]' ).forEach( function ( panel ) {
			var on = panel.getAttribute( 'data-gtm-panel' ) === lang;
			panel.hidden = ! on;
			if ( on && window.tinymce ) {
				panel.querySelectorAll( 'textarea.wp-editor-area' ).forEach( function ( area ) {
					var editor = window.tinymce.get( area.id );
					if ( editor && editor.iframeElement ) {
						editor.iframeElement.style.height = Math.max( 200, editor.iframeElement.offsetHeight ) + 'px';
					}
				} );
			}
		} );
		try { window.localStorage.setItem( 'gadgetim-tr-tab', lang ); } catch ( e ) {}
	}
	function source( root ) {
		var node = root.querySelector( 'script[data-gtm-source]' );
		if ( ! node ) { return {}; }
		try { return JSON.parse( node.textContent || '{}' ); } catch ( e ) { return {}; }
	}
	function setValue( root, lang, field, value ) {
		var id = 'gtm_tr_' + lang + '_' + field;
		var el = document.getElementById( id ) || root.querySelector( '[name="gtm_tr[' + lang + '][' + field + ']"], [name="gtm_term_tr[' + lang + '][' + field + ']"]' );
		if ( ! el ) { return; }
		if ( window.tinymce && window.tinymce.get( id ) && ! window.tinymce.get( id ).isHidden() ) {
			window.tinymce.get( id ).setContent( value || '' );
		}
		el.value = value || '';
		el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	}
	document.querySelectorAll( '[data-gtm-tr]' ).forEach( function ( root ) {
		var remembered = null;
		try { remembered = window.localStorage.getItem( 'gadgetim-tr-tab' ); } catch ( e ) {}
		var first = root.querySelector( '[data-gtm-tab]' );
		if ( remembered && root.querySelector( '[data-gtm-tab="' + remembered + '"]' ) ) {
			activate( root, remembered );
		} else if ( first ) {
			activate( root, first.getAttribute( 'data-gtm-tab' ) );
		}
	} );
	document.addEventListener( 'click', function ( event ) {
		var tab = event.target.closest( '[data-gtm-tab]' );
		if ( tab ) {
			event.preventDefault();
			activate( tab.closest( '[data-gtm-tr]' ), tab.getAttribute( 'data-gtm-tab' ) );
			return;
		}
		var copy = event.target.closest( '[data-gtm-copy]' );
		if ( copy ) {
			event.preventDefault();
			var root = copy.closest( '[data-gtm-tr]' ) || document;
			var data = source( root );
			var field = copy.getAttribute( 'data-gtm-copy' );
			var lang = copy.getAttribute( 'data-gtm-lang' );
			if ( field === '*' ) {
				Object.keys( data ).forEach( function ( key ) { setValue( root, lang, key, data[ key ] ); } );
			} else {
				setValue( root, lang, field, data[ field ] );
			}
			var live = root.querySelector( '[data-gtm-live]' );
			if ( live && window.gadgetimI18n ) { live.textContent = window.gadgetimI18n.i18n.copied; }
		}
	} );
} )();
JS;
	}
}
