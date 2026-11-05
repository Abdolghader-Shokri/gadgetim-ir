/**
 * Gadgetim Core — Currencies tab: apply confirmation, dirty tracking, progress polling.
 * Vanilla JS; configuration arrives through wp_localize_script as window.gadgetimCurrencies.
 */
( function () {
	'use strict';

	var cfg = window.gadgetimCurrencies || {};
	var i18n = cfg.i18n || {};
	var root = document.querySelector( '.gtm-currencies' );
	if ( ! root ) {
		return;
	}

	function sprintf( template, args ) {
		return String( template ).replace( /%(\d+)\$s/g, function ( match, index ) {
			var value = args[ Number( index ) - 1 ];
			return value === undefined ? '' : String( value );
		} );
	}

	function formatNumber( value ) {
		var number = Number( value );
		return isFinite( number ) ? number.toLocaleString( 'en-US' ) : String( value );
	}

	/**
	 * Charm rounding ignores the step: disable the step input while charm is selected.
	 */
	function syncStep( select ) {
		var row = select.closest( 'tr' );
		var step = row ? row.querySelector( '.gtm-currencies__step' ) : null;
		if ( step ) {
			step.disabled = select.value === 'charm';
		}
	}
	root.querySelectorAll( '.gtm-currencies__mode' ).forEach( syncStep );

	/**
	 * Unsaved edits in the table: applying would use the OLD saved rate, so block it until saved.
	 */
	var form = document.getElementById( 'gtm-currencies-form' );
	var dirty = false;
	function markDirty() {
		dirty = true;
		if ( form ) {
			form.classList.add( 'is-dirty' );
		}
		root.querySelectorAll( '.gtm-currencies__apply' ).forEach( function ( button ) {
			button.setAttribute( 'aria-disabled', 'true' );
			button.title = i18n.saveFirst || '';
		} );
	}
	if ( form ) {
		form.addEventListener( 'input', function ( event ) {
			if ( event.target && event.target.closest( '.gtm-currencies__table' ) ) {
				markDirty();
			}
		} );
		form.addEventListener( 'change', function ( event ) {
			if ( event.target && event.target.classList.contains( 'gtm-currencies__mode' ) ) {
				syncStep( event.target );
			}
			if ( event.target && event.target.closest( '.gtm-currencies__table' ) ) {
				markDirty();
			}
		} );
	}

	/**
	 * Apply / delete confirmations (the buttons submit the hidden per-currency forms).
	 */
	root.addEventListener( 'click', function ( event ) {
		var apply = event.target.closest( '.gtm-currencies__apply' );
		if ( apply ) {
			if ( dirty ) {
				event.preventDefault();
				window.alert( i18n.saveFirst || 'Save the currencies first.' );
				return;
			}
			var message = sprintf( i18n.confirmApply || 'Recompute %1$s products priced in %2$s?', [ formatNumber( apply.getAttribute( 'data-count' ) ), apply.getAttribute( 'data-code' ) ] );
			if ( root.getAttribute( 'data-backup' ) !== 'ok' ) {
				message += '\n\n' + ( i18n.backupStale || '' );
			}
			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
			return;
		}
		var remove = event.target.closest( '.gtm-currencies__delete' );
		if ( remove && ! window.confirm( i18n.confirmDelete || 'Delete this currency?' ) ) {
			event.preventDefault();
		}
	} );

	/**
	 * Progress bar: polls the admin-ajax endpoint (nonce + manage_options) until the job finishes.
	 */
	var panel = root.querySelector( '.gtm-progress[data-job]' );
	if ( ! panel || ! cfg.ajaxUrl ) {
		return;
	}
	var job = panel.getAttribute( 'data-job' );
	var bar = panel.querySelector( '.gtm-progress__bar' );
	var text = panel.querySelector( '.gtm-progress__text' );
	var failures = 0;

	function render( data ) {
		if ( bar ) {
			bar.max = Math.max( 1, data.total );
			bar.value = data.done;
		}
		if ( text ) {
			text.textContent = sprintf( i18n.progress || '%1$s / %2$s', [ formatNumber( data.done ), formatNumber( data.total ) ] );
		}
	}

	function finish( data ) {
		render( data );
		panel.classList.remove( 'notice-info' );
		panel.classList.add( 'notice-success' );
		if ( text ) {
			text.textContent = sprintf( i18n.finished || 'Done: %1$s (%2$s failed).', [ formatNumber( data.done ), formatNumber( data.failed ) ] );
		}
		window.setTimeout( function () {
			window.location.href = cfg.doneUrl || window.location.pathname;
		}, 1500 );
	}

	function poll() {
		var url = cfg.ajaxUrl + ( cfg.ajaxUrl.indexOf( '?' ) === -1 ? '?' : '&' ) +
			'action=' + encodeURIComponent( cfg.action ) +
			'&job=' + encodeURIComponent( job ) +
			'&_ajax_nonce=' + encodeURIComponent( cfg.nonce );
		window.fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					throw new Error( 'bad payload' );
				}
				failures = 0;
				if ( payload.data.finished ) {
					finish( payload.data );
					return;
				}
				render( payload.data );
				window.setTimeout( poll, cfg.pollInterval || 1500 );
			} )
			.catch( function () {
				failures += 1;
				if ( failures >= 5 ) {
					if ( text ) {
						text.textContent = i18n.error || 'Progress unavailable.';
					}
					panel.classList.remove( 'notice-info' );
					panel.classList.add( 'notice-warning' );
					return;
				}
				window.setTimeout( poll, ( cfg.pollInterval || 1500 ) * 2 );
			} );
	}
	window.setTimeout( poll, cfg.pollInterval || 1500 );
} )();
