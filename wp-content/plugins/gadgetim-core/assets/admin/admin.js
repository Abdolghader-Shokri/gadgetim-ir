/**
 * Gadgetim Core — admin behaviour: repeater rows, media picker, tool confirmations.
 * Vanilla JS; wp.media is loaded by wp_enqueue_media() on the Gadgetim pages.
 */
( function () {
	'use strict';

	var i18n = ( window.gadgetimAdmin && window.gadgetimAdmin.i18n ) || {};

	/**
	 * Repeater: add / remove / move rows. The empty row lives in a <template>.
	 */
	function handleRepeater( button, action ) {
		var repeater = button.closest( '[data-gtm-repeater]' );
		if ( ! repeater ) {
			return false;
		}
		var list = repeater.querySelector( '.gtm-repeater__list' );
		var row = button.closest( '.gtm-repeater__row' );

		if ( 'add' === action ) {
			var template = repeater.querySelector( 'template' );
			if ( ! template || ! template.content ) {
				return true;
			}
			var node = template.content.firstElementChild.cloneNode( true );
			list.appendChild( node );
			var input = node.querySelector( 'input' );
			if ( input ) {
				input.focus();
			}
		} else if ( 'remove' === action && row ) {
			row.remove();
		} else if ( 'up' === action && row && row.previousElementSibling ) {
			list.insertBefore( row, row.previousElementSibling );
			button.focus();
		} else if ( 'down' === action && row && row.nextElementSibling ) {
			list.insertBefore( row.nextElementSibling, row );
			button.focus();
		}
		return true;
	}

	/**
	 * Image picker: wp.media frame writing the attachment id into the hidden input.
	 */
	function handleImage( button, action ) {
		var wrapper = button.closest( '[data-gtm-image]' );
		if ( ! wrapper ) {
			return false;
		}
		var input = wrapper.querySelector( 'input[type="hidden"]' );
		var preview = wrapper.querySelector( '.gtm-image__preview' );

		if ( 'remove' === action ) {
			input.value = '';
			preview.innerHTML = '';
			wrapper.classList.remove( 'has-image' );
			return true;
		}

		if ( 'select' !== action || ! window.wp || ! window.wp.media ) {
			return true;
		}

		var frame = window.wp.media( {
			title: i18n.selectImage || 'Select image',
			button: { text: i18n.useImage || 'Use this image' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
			input.value = attachment.id;
			preview.innerHTML = '';
			var img = document.createElement( 'img' );
			img.src = url;
			img.alt = '';
			preview.appendChild( img );
			wrapper.classList.add( 'has-image' );
		} );

		frame.open();
		return true;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-gtm-action]' );
		if ( ! button ) {
			return;
		}
		var action = button.getAttribute( 'data-gtm-action' );
		if ( handleRepeater( button, action ) || handleImage( button, action ) ) {
			event.preventDefault();
		}
	} );

	/**
	 * Tool forms may carry a confirmation question.
	 */
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target;
		if ( ! form || ! form.getAttribute ) {
			return;
		}
		var question = form.getAttribute( 'data-gtm-confirm' );
		if ( question && ! window.confirm( question ) ) {
			event.preventDefault();
		}
	} );
} )();
