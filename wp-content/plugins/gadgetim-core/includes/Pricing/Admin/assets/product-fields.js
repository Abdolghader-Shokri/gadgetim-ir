/**
 * Gadgetim Core — product edit screen: live Toman preview for base-currency pricing.
 *
 * Mirrors Gadgetim\Core\Pricing\Calculator exactly (nearest | up | down | charm; charm = nearest
 * 10,000 minus 1,000, from 10,000,000 nearest 100,000 minus 1,000, midpoints rounded down).
 * With a currency chosen the WooCommerce Toman inputs become read-only and hold the computed
 * values; the server recomputes them anyway, so a tampered value never sticks.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.gadgetimPricing || {};
	var currencies = cfg.currencies || {};
	var i18n = cfg.i18n || {};
	var unit = cfg.unit || 'Toman';

	var PERSIAN = '۰۱۲۳۴۵۶۷۸۹';
	var ARABIC = '٠١٢٣٤٥٦٧٨٩';

	function toLatinDigits( value ) {
		return String( value )
			.replace( /[۰-۹]/g, function ( d ) {
				return String( PERSIAN.indexOf( d ) );
			} )
			.replace( /[٠-٩]/g, function ( d ) {
				return String( ARABIC.indexOf( d ) );
			} );
	}

	function parseDecimal( raw ) {
		var text = toLatinDigits( raw === null || raw === undefined ? '' : raw )
			.trim()
			.replace( /٫/g, '.' )
			.replace( /[٬،,\s  ‎‏]/g, '' );
		if ( text === '' || /^-/.test( text ) || ! /^\d*\.?\d*$/.test( text ) || text.replace( /\./g, '' ) === '' ) {
			return null;
		}
		var number = parseFloat( text );
		return isFinite( number ) && number >= 0 ? number : null;
	}

	function roundHalfDown( q ) {
		var floor = Math.floor( q );
		var rest = q - floor;
		return rest > 0.5 + 1e-9 ? floor + 1 : floor;
	}

	function charm( raw ) {
		if ( raw <= 0 ) {
			return 0;
		}
		var step = raw >= 10000000 ? 100000 : 10000;
		var rounded = roundHalfDown( raw / step ) * step;
		if ( rounded <= 0 ) {
			return Math.round( raw );
		}
		var price = rounded - 1000;
		return price > 0 ? price : rounded;
	}

	function toToman( amount, currency ) {
		if ( amount === null || ! currency || ! ( currency.rate > 0 ) ) {
			return 0;
		}
		var raw = amount * currency.rate;
		if ( raw <= 0 ) {
			return 0;
		}
		if ( currency.rounding_mode === 'charm' ) {
			return charm( raw );
		}
		var step = Math.max( 1, parseInt( currency.rounding_step, 10 ) || 1 );
		// Snap float noise before ceil/floor (2.2 × 85000 / 1000 = 187.00000000000003), as Calculator.php does.
		var q = Math.round( ( raw / step ) * 1e6 ) / 1e6;
		if ( currency.rounding_mode === 'up' ) {
			q = Math.ceil( q );
		} else if ( currency.rounding_mode === 'down' ) {
			q = Math.floor( q );
		} else {
			q = Math.round( q );
		}
		return Math.round( q * step );
	}

	function formatNumber( value ) {
		return Number( value ).toLocaleString( 'en-US' );
	}

	function formatAmount( amount, currency ) {
		var text = String( amount );
		var fraction = text.indexOf( '.' ) === -1 ? '' : text.split( '.' )[ 1 ].replace( /0+$/, '' );
		var decimals = Math.min( currency.decimals === undefined ? 2 : currency.decimals, fraction.length );
		return currency.symbol + ' ' + Number( amount ).toLocaleString( 'en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals } );
	}

	function sprintf( template, args ) {
		return String( template ).replace( /%(\d+)\$s/g, function ( match, index ) {
			var value = args[ Number( index ) - 1 ];
			return value === undefined ? '' : String( value );
		} );
	}

	/**
	 * A scope is the document (simple/external product) or one .woocommerce_variation row.
	 */
	function scopeOf( element ) {
		return element.closest( '.woocommerce_variation' ) || document;
	}

	function fieldsOf( scope ) {
		if ( scope === document ) {
			return {
				currency: document.getElementById( '_gtm_price_currency' ),
				regular: document.getElementById( '_gtm_base_regular_price' ),
				sale: document.getElementById( '_gtm_base_sale_price' ),
				tomanRegular: document.getElementById( '_regular_price' ),
				tomanSale: document.getElementById( '_sale_price' ),
				note: document.querySelector( '[data-gtm-pricing="base"] .gtm-pricing__note-text' )
			};
		}
		return {
			currency: scope.querySelector( '.gtm-pricing__currency' ),
			regular: scope.querySelector( '.gtm-pricing__base-regular' ),
			sale: scope.querySelector( '.gtm-pricing__base-sale' ),
			tomanRegular: scope.querySelector( 'input[name^="variable_regular_price["]' ),
			tomanSale: scope.querySelector( 'input[name^="variable_sale_price["]' ),
			note: scope.querySelector( '.gtm-pricing__note-text' )
		};
	}

	function setReadonly( input, on ) {
		if ( ! input ) {
			return;
		}
		input.readOnly = on;
		input.classList.toggle( 'gtm-readonly', on );
		if ( on ) {
			input.setAttribute( 'title', i18n.readonly || '' );
		} else {
			input.removeAttribute( 'title' );
		}
	}

	function setNote( fields, text ) {
		if ( fields.note ) {
			fields.note.textContent = text;
		}
	}

	function update( scope ) {
		var fields = fieldsOf( scope );
		if ( ! fields.currency ) {
			return;
		}
		var code = fields.currency.value;
		var currency = currencies[ code ];
		var hasCurrency = code !== '';
		var container = scope === document ? document.body : scope;

		setReadonly( fields.tomanRegular, hasCurrency );
		setReadonly( fields.tomanSale, hasCurrency );
		container.classList.toggle( 'gtm-has-currency', hasCurrency );

		if ( ! hasCurrency ) {
			setNote( fields, i18n.fixed || '' );
			return;
		}
		if ( ! currency ) {
			// Disabled currency: the server-rendered note explains that the last prices are kept.
			return;
		}
		var regular = parseDecimal( fields.regular ? fields.regular.value : '' );
		var sale = parseDecimal( fields.sale ? fields.sale.value : '' );
		if ( regular === null ) {
			setNote( fields, i18n.noBase || '' );
			if ( fields.tomanRegular ) {
				fields.tomanRegular.value = '';
			}
			if ( fields.tomanSale ) {
				fields.tomanSale.value = '';
			}
			return;
		}
		var tomanRegular = toToman( regular, currency );
		var tomanSale = sale === null ? 0 : toToman( sale, currency );
		var warning = '';
		if ( sale !== null && tomanSale >= tomanRegular ) {
			tomanSale = 0;
			warning = ' ' + ( i18n.saleTooBig || '' );
		}
		if ( fields.tomanRegular ) {
			fields.tomanRegular.value = tomanRegular > 0 ? String( tomanRegular ) : '';
		}
		if ( fields.tomanSale ) {
			fields.tomanSale.value = tomanSale > 0 ? String( tomanSale ) : '';
		}
		var modeLabel = ( i18n.modes && i18n.modes[ currency.rounding_mode ] ) || currency.rounding_mode;
		setNote( fields, sprintf( i18n.computed || '%1$s %2$s = %3$s × %4$s (%5$s)', [
			formatNumber( tomanRegular ),
			unit,
			formatAmount( regular, currency ),
			formatNumber( currency.rate ),
			modeLabel
		] ) + warning );
	}

	function updateAll() {
		update( document );
		document.querySelectorAll( '.woocommerce_variation' ).forEach( update );
	}

	document.addEventListener( 'input', function ( event ) {
		var target = event.target;
		if ( target && target.matches && target.matches( '.gtm-pricing__base-regular, .gtm-pricing__base-sale' ) ) {
			update( scopeOf( target ) );
		}
	} );
	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( target && target.matches && target.matches( '.gtm-pricing__currency, .gtm-pricing__base-regular, .gtm-pricing__base-sale' ) ) {
			update( scopeOf( target ) );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', updateAll );
	} else {
		updateAll();
	}

	if ( $ ) {
		// Variation rows are loaded by AJAX; re-run for the new rows.
		$( '#variable_product_options' ).on( 'woocommerce_variations_loaded woocommerce_variations_added', updateAll );

		// Bulk action "Set pricing currency for all variations": WooCommerce asks the select for extra data
		// through triggerHandler( action + '_ajax_data' ), which does not bubble, so bind directly.
		var bulkAction = cfg.bulkAction || 'gtm_set_currency';
		$( 'select.variation_actions' ).on( bulkAction + '_ajax_data', function ( event, data ) {
			data = data || {};
			var codes = Object.keys( currencies );
			var answer = window.prompt( ( i18n.bulkPrompt || 'Currency code:' ) + ' ' + codes.join( ', ' ), codes[ 0 ] || '' );
			if ( answer === null ) {
				data.gtm_cancel = 1;
				return data;
			}
			data.value = toLatinDigits( answer ).trim().toUpperCase();
			return data;
		} );
	}
} )( window.jQuery );
