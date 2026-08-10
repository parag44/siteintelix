( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState !== 'loading' ) {
			callback();
			return;
		}
		document.addEventListener( 'DOMContentLoaded', callback );
	}

	function copyText( value ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( value );
		}
		var area = document.createElement( 'textarea' );
		area.value = value;
		area.setAttribute( 'readonly', 'readonly' );
		area.style.position = 'fixed';
		area.style.left = '-9999px';
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		document.body.removeChild( area );
		return Promise.resolve();
	}

	ready( function () {
		var root = document.getElementById( 'siteintelix-debug-log-page' );
		if ( ! root ) {
			return;
		}

		function closeMenus( exception ) {
			root.querySelectorAll( '.sitx-dlv-menu' ).forEach( function ( menu ) {
				if ( menu !== exception ) {
					menu.hidden = true;
					if ( menu.previousElementSibling ) {
						menu.previousElementSibling.setAttribute( 'aria-expanded', 'false' );
					}
				}
			} );
		}

		root.addEventListener( 'click', function ( event ) {
			var menuButton = event.target.closest( '[data-sitx-menu-button]' );
			var traceButton = event.target.closest( '[data-sitx-toggle-trace]' );
			var copyButton = event.target.closest( '[data-copy-text]' );

			if ( menuButton ) {
				event.preventDefault();
				var menu = menuButton.nextElementSibling;
				var opening = menu && menu.hidden;
				closeMenus( menu );
				if ( menu ) {
					menu.hidden = ! opening;
					menuButton.setAttribute( 'aria-expanded', opening ? 'true' : 'false' );
				}
				return;
			}

			if ( traceButton ) {
				event.preventDefault();
				var trace = document.getElementById( traceButton.getAttribute( 'aria-controls' ) );
				var expanded = traceButton.getAttribute( 'aria-expanded' ) === 'true';
				if ( trace ) {
					trace.hidden = expanded;
				}
				traceButton.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				var label = traceButton.querySelector( 'span:last-child' );
				if ( label ) {
					label.textContent = expanded ? 'Stack Trace' : 'Hide Stack Trace';
				}
				return;
			}

			if ( copyButton ) {
				event.preventDefault();
				copyText( copyButton.getAttribute( 'data-copy-text' ) || '' ).then( function () {
					copyButton.classList.add( 'is-copied' );
					window.setTimeout( function () { copyButton.classList.remove( 'is-copied' ); }, 1200 );
				} );
				return;
			}

			if ( ! event.target.closest( '.sitx-dlv-dropdown' ) ) {
				closeMenus();
			}
		} );

		root.querySelectorAll( '[data-sitx-auto-submit]' ).forEach( function ( control ) {
			control.addEventListener( 'change', function () {
				if ( control.form ) {
					control.form.submit();
				}
			} );
		} );

		root.querySelectorAll( '[data-sitx-column-toggle]' ).forEach( function ( control ) {
			control.addEventListener( 'change', function () {
				var column = control.getAttribute( 'data-sitx-column-toggle' );
				root.querySelectorAll( '[data-column="' + column + '"]' ).forEach( function ( cell ) {
					cell.hidden = ! control.checked;
				} );
			} );
		} );

		root.querySelectorAll( '[data-sitx-debug-setting]' ).forEach( function ( control ) {
			control.addEventListener( 'change', function () {
				var data = window.siteintelixDebugViewer || {};
				var status = root.querySelector( '[data-sitx-debug-status]' );
				var previous = ! control.checked;
				var body = new window.URLSearchParams();
				body.set( 'action', 'siteintelix_update_debug_setting' );
				body.set( 'nonce', data.nonce || '' );
				body.set( 'setting', control.getAttribute( 'data-sitx-debug-setting' ) || '' );
				body.set( 'enabled', control.checked ? '1' : '0' );
				control.disabled = true;
				if ( status ) {
					status.className = 'sitx-dlv__switch-status';
					status.textContent = data.saving || 'Saving…';
				}

				window.fetch( data.ajaxUrl || window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				} ).then( function ( response ) {
					return response.json();
				} ).then( function ( response ) {
					if ( ! response || ! response.success ) {
						throw new Error( response && response.data && response.data.message ? response.data.message : ( data.failed || 'Update failed.' ) );
					}
					if ( status ) {
						status.classList.add( 'is-success' );
						status.textContent = data.saved || 'Saved.';
					}
					window.setTimeout( function () { window.location.reload(); }, 650 );
				} ).catch( function ( error ) {
					control.checked = previous;
					control.disabled = false;
					if ( status ) {
						status.classList.add( 'is-error' );
						status.textContent = error.message || data.failed || 'Update failed.';
					}
				} );
			} );
		} );

		var startButton = root.querySelector( '[data-sitx-start-debugging]' );
		if ( startButton ) {
			startButton.addEventListener( 'click', function () {
				var data = window.siteintelixDebugViewer || {};
				var status = root.querySelector( '[data-sitx-debug-status]' );
				var body = new window.URLSearchParams();
				body.set( 'action', 'siteintelix_start_debugging' );
				body.set( 'nonce', data.nonce || '' );
				startButton.disabled = true;
				if ( status ) {
					status.className = 'sitx-dlv__switch-status';
					status.textContent = data.starting || 'Preparing protected debug logging…';
				}

				window.fetch( data.ajaxUrl || window.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: body.toString()
				} ).then( function ( response ) {
					return response.json();
				} ).then( function ( response ) {
					if ( ! response || ! response.success ) {
						throw new Error( response && response.data && response.data.message ? response.data.message : ( data.failed || 'Setup failed.' ) );
					}
					if ( status ) {
						status.classList.add( 'is-success' );
						status.textContent = data.started || 'Secure logging is ready. Opening the viewer…';
					}
					window.setTimeout( function () { window.location.reload(); }, 650 );
				} ).catch( function ( error ) {
					startButton.disabled = false;
					if ( status ) {
						status.classList.add( 'is-error' );
						status.textContent = error.message || data.failed || 'Setup failed.';
					}
				} );
			} );
		}
	} );
}() );
