/*
 * Click to Call (Twilio) — front-end behaviour.
 * Vanilla JS, no dependencies. Builds one shared modal and wires up every
 * [data-ctc-open] button on the page.
 */
( function () {
	'use strict';

	if ( typeof window.CTC_DATA === 'undefined' ) {
		return;
	}

	var DATA = window.CTC_DATA;
	var T = DATA.text || {};
	var modal = null;
	var lastFocused = null;

	function el( tag, attrs, html ) {
		var node = document.createElement( tag );
		if ( attrs ) {
			Object.keys( attrs ).forEach( function ( key ) {
				node.setAttribute( key, attrs[ key ] );
			} );
		}
		if ( typeof html !== 'undefined' ) {
			node.innerHTML = html;
		}
		return node;
	}

	/* Format a US number as the visitor types: (555) 123-4567 */
	function formatUS( value ) {
		var digits = ( value || '' ).replace( /\D/g, '' );
		if ( digits.length === 11 && digits.charAt( 0 ) === '1' ) {
			digits = digits.slice( 1 );
		}
		digits = digits.slice( 0, 10 );
		var a = digits.slice( 0, 3 );
		var b = digits.slice( 3, 6 );
		var c = digits.slice( 6, 10 );
		if ( digits.length > 6 ) {
			return '(' + a + ') ' + b + '-' + c;
		}
		if ( digits.length > 3 ) {
			return '(' + a + ') ' + b;
		}
		if ( digits.length > 0 ) {
			return '(' + a;
		}
		return '';
	}

	function buildModal() {
		var wrap = el( 'div', { class: 'ctc-modal', id: 'ctc-modal', 'aria-hidden': 'true' } );

		var backdrop = el( 'div', { class: 'ctc-modal__backdrop', 'data-ctc-close': '1' } );

		var dialog = el( 'div', {
			class: 'ctc-modal__dialog',
			role: 'dialog',
			'aria-modal': 'true',
			'aria-labelledby': 'ctc-modal-title',
			'aria-describedby': 'ctc-modal-intro'
		} );

		var closeBtn = el( 'button', {
			type: 'button',
			class: 'ctc-modal__close',
			'data-ctc-close': '1',
			'aria-label': T.close || 'Close'
		}, '&times;' );

		var title = el( 'h2', { class: 'ctc-modal__title', id: 'ctc-modal-title' } );
		title.textContent = T.title || 'Request a Call';

		var intro = el( 'p', { class: 'ctc-modal__intro', id: 'ctc-modal-intro' } );
		intro.textContent = T.intro || '';

		var form = el( 'form', { class: 'ctc-form', novalidate: 'novalidate' } );

		var field = el( 'label', { class: 'ctc-field' } );
		var fieldLabel = el( 'span', { class: 'ctc-field__label' } );
		fieldLabel.textContent = T.phoneLabel || 'US phone number';
		var input = el( 'input', {
			type: 'tel',
			class: 'ctc-field__input',
			name: 'phone',
			inputmode: 'tel',
			autocomplete: 'tel',
			placeholder: T.placeholder || '(555) 123-4567',
			'aria-required': 'true'
		} );
		field.appendChild( fieldLabel );
		field.appendChild( input );

		/* Honeypot */
		var honey = el( 'input', {
			type: 'text',
			class: 'ctc-hp',
			name: 'company',
			tabindex: '-1',
			autocomplete: 'off',
			'aria-hidden': 'true'
		} );

		var consent = el( 'label', { class: 'ctc-consent' } );
		var consentBox = el( 'input', { type: 'checkbox', class: 'ctc-consent__box', name: 'consent' } );
		var consentText = el( 'span', { class: 'ctc-consent__text' } );
		consentText.textContent = T.consent || 'I agree to receive a one-time confirmation text.';
		consent.appendChild( consentBox );
		consent.appendChild( consentText );

		var message = el( 'div', { class: 'ctc-message', role: 'alert', 'aria-live': 'assertive' } );

		var submit = el( 'button', { type: 'submit', class: 'ctc-submit' } );
		submit.textContent = T.submit || 'Text me to confirm';

		form.appendChild( field );
		form.appendChild( honey );
		form.appendChild( consent );
		form.appendChild( message );
		form.appendChild( submit );

		var success = el( 'div', { class: 'ctc-success', role: 'status', 'aria-live': 'polite', hidden: 'hidden' } );
		success.innerHTML = '<span class="ctc-success__icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" width="28" height="28"><path fill="currentColor" d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>' +
			'</span><p class="ctc-success__text"></p>';

		dialog.appendChild( closeBtn );
		dialog.appendChild( title );
		dialog.appendChild( intro );
		dialog.appendChild( form );
		dialog.appendChild( success );

		wrap.appendChild( backdrop );
		wrap.appendChild( dialog );
		document.body.appendChild( wrap );

		/* Wiring */
		input.addEventListener( 'input', function () {
			input.value = formatUS( input.value );
			input.classList.remove( 'ctc-invalid' );
		} );

		wrap.addEventListener( 'click', function ( e ) {
			if ( e.target.getAttribute && e.target.getAttribute( 'data-ctc-close' ) ) {
				closeModal();
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && wrap.classList.contains( 'is-open' ) ) {
				closeModal();
			}
			if ( e.key === 'Tab' && wrap.classList.contains( 'is-open' ) ) {
				trapFocus( e, dialog );
			}
		} );

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			onSubmit( form, input, consentBox, honey, message, submit, success );
		} );

		return wrap;
	}

	function trapFocus( e, dialog ) {
		var focusable = dialog.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		var list = [];
		for ( var i = 0; i < focusable.length; i++ ) {
			if ( ! focusable[ i ].disabled && focusable[ i ].offsetParent !== null ) {
				list.push( focusable[ i ] );
			}
		}
		if ( ! list.length ) {
			return;
		}
		var first = list[ 0 ];
		var last = list[ list.length - 1 ];
		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	}

	function openModal() {
		if ( ! modal ) {
			modal = buildModal();
		}
		lastFocused = document.activeElement;
		resetForm();
		modal.classList.add( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'false' );
		document.documentElement.classList.add( 'ctc-noscroll' );
		var input = modal.querySelector( '.ctc-field__input' );
		if ( input ) {
			setTimeout( function () { input.focus(); }, 30 );
		}
	}

	function closeModal() {
		if ( ! modal ) {
			return;
		}
		modal.classList.remove( 'is-open' );
		modal.setAttribute( 'aria-hidden', 'true' );
		document.documentElement.classList.remove( 'ctc-noscroll' );
		if ( lastFocused && typeof lastFocused.focus === 'function' ) {
			lastFocused.focus();
		}
	}

	function resetForm() {
		if ( ! modal ) {
			return;
		}
		var form = modal.querySelector( '.ctc-form' );
		var success = modal.querySelector( '.ctc-success' );
		var message = modal.querySelector( '.ctc-message' );
		if ( form ) {
			form.reset();
			form.style.display = '';
		}
		if ( success ) {
			success.setAttribute( 'hidden', 'hidden' );
		}
		if ( message ) {
			message.classList.remove( 'is-visible' );
			message.textContent = '';
		}
	}

	function showError( message, input, text ) {
		message.textContent = text;
		message.classList.add( 'is-visible' );
		if ( input ) {
			input.classList.add( 'ctc-invalid' );
		}
	}

	function onSubmit( form, input, consentBox, honey, message, submit, success ) {
		message.classList.remove( 'is-visible' );
		input.classList.remove( 'ctc-invalid' );

		var digits = ( input.value || '' ).replace( /\D/g, '' );
		if ( digits.length === 11 && digits.charAt( 0 ) === '1' ) {
			digits = digits.slice( 1 );
		}
		if ( digits.length !== 10 ) {
			showError( message, input, T.invalidPhone || 'Please enter a valid 10-digit US phone number.' );
			input.focus();
			return;
		}
		if ( ! consentBox.checked ) {
			showError( message, null, T.consentReq || 'Please tick the box to agree to receive a text message.' );
			return;
		}

		var original = submit.textContent;
		submit.disabled = true;
		submit.textContent = T.sending || 'Sending…';

		var payload = {
			phone: digits,
			consent: true,
			company: honey.value || ''
		};

		fetch( DATA.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': DATA.nonce || ''
			},
			body: JSON.stringify( payload )
		} )
			.then( function ( res ) {
				return res.json().then( function ( body ) {
					return { ok: res.ok, body: body };
				} );
			} )
			.then( function ( result ) {
				submit.disabled = false;
				submit.textContent = original;
				if ( result.ok && result.body && result.body.success ) {
					form.style.display = 'none';
					var textNode = success.querySelector( '.ctc-success__text' );
					if ( textNode ) {
						textNode.textContent = result.body.message || 'Done!';
					}
					success.removeAttribute( 'hidden' );
				} else {
					var msg = ( result.body && result.body.message ) ? result.body.message : ( T.genericErr || 'Something went wrong.' );
					showError( message, null, msg );
				}
			} )
			.catch( function () {
				submit.disabled = false;
				submit.textContent = original;
				showError( message, null, T.genericErr || 'Something went wrong. Please try again.' );
			} );
	}

	/* Delegate: any current or future [data-ctc-open] opens the modal. */
	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest ? e.target.closest( '[data-ctc-open]' ) : null;
		if ( trigger ) {
			e.preventDefault();
			openModal();
		}
	} );
} )();
