/* global absDatasheetSMS */
( function () {
	'use strict';

	if ( typeof absDatasheetSMS === 'undefined' ) {
		return;
	}

	var cfg  = absDatasheetSMS;
	var i18n = cfg.i18n || {};

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function showMessage( el, text, isError ) {
		if ( ! el ) {
			return;
		}
		el.textContent = text;
		el.style.display = text ? '' : 'none';
		el.classList.toggle( 'is-error', !! isError );
		el.classList.toggle( 'is-success', ! isError && !! text );
	}

	function initWidget( widget ) {
		var toggle  = widget.querySelector( '.js-datasheet-sms-toggle' );
		var form    = widget.querySelector( '.js-datasheet-sms-form' );
		var phone   = widget.querySelector( '.js-datasheet-sms-phone' );
		var submit  = widget.querySelector( '.js-datasheet-sms-submit' );
		var message = widget.querySelector( '.js-datasheet-sms-message' );

		if ( ! form ) {
			return;
		}

		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				var open = form.style.display !== 'none';
				form.style.display = open ? 'none' : '';
				toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				if ( ! open && phone ) {
					phone.focus();
				}
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var value = phone ? phone.value.trim() : '';
			if ( value.replace( /\D/g, '' ).length < 7 ) {
				showMessage( message, i18n.invalid || 'Please enter a valid mobile number.', true );
				return;
			}

			if ( submit ) {
				submit.disabled = true;
				submit.textContent = i18n.sending || 'Sending…';
			}
			showMessage( message, '', false );

			var hp   = form.querySelector( 'input[name="company"]' );
			var body = new URLSearchParams();
			body.set( 'action', cfg.action );
			body.set( 'nonce', cfg.nonce );
			body.set( 'product_id', widget.getAttribute( 'data-product-id' ) || '' );
			body.set( 'phone', value );
			body.set( 'company', hp ? hp.value : '' );

			fetch( cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} )
				.then( function ( res ) {
					return res.json().catch( function () {
						return { success: false, data: {} };
					} );
				} )
				.then( function ( json ) {
					var ok  = json && json.success;
					var msg = json && json.data && json.data.message
						? json.data.message
						: ( ok ? '' : ( i18n.genericErr || 'Sorry, something went wrong. Please try again.' ) );

					showMessage( message, msg, ! ok );

					if ( ok && phone ) {
						phone.value = '';
					}
				} )
				.catch( function () {
					showMessage( message, i18n.genericErr || 'Sorry, something went wrong. Please try again.', true );
				} )
				.then( function () {
					if ( submit ) {
						submit.disabled = false;
						submit.textContent = i18n.send || 'Send';
					}
				} );
		} );
	}

	ready( function () {
		var widgets = document.querySelectorAll( '.datasheet-sms' );
		Array.prototype.forEach.call( widgets, initWidget );
	} );
} )();
