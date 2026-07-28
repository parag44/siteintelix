/**
 * SiteIntelix Overview copy and redacted export actions.
 */
/* global siteintelixOverviewData */
( function () {
	'use strict';

	function readData() {
		var node = document.getElementById( 'siteintelix-data-json' );
		if ( ! node ) { return null; }
		try { return JSON.parse( node.textContent || '{}' ); } catch ( error ) { return null; }
	}

	function announce( message, isError ) {
		var status = document.getElementById( 'siteintelix-overview-status' );
		if ( ! status ) {
			status = document.createElement( 'p' );
			status.id = 'siteintelix-overview-status';
			status.className = 'screen-reader-text';
			status.setAttribute( 'aria-live', isError ? 'assertive' : 'polite' );
			document.body.appendChild( status );
		}
		status.textContent = '';
		window.setTimeout( function () { status.textContent = message; }, 20 );
	}

	function toast( message, type ) {
		if ( window.SiteIntelixAdmin && 'function' === typeof window.SiteIntelixAdmin.toast ) {
			window.SiteIntelixAdmin.toast( message, type );
			return;
		}
		announce( message, 'error' === type );
	}

	function initModuleToggles() {
		var dashboard = document.getElementById( 'siteintelix-dashboard' );
		if ( ! dashboard ) { return; }

		dashboard.addEventListener( 'change', function ( event ) {
			var toggle = event.target.closest( '[data-siteintelix-module-toggle]' );
			if ( ! toggle ) { return; }

			var card = toggle.closest( '[data-siteintelix-module-card]' );
			var moduleId = toggle.value || ( card ? card.getAttribute( 'data-module-id' ) : '' );
			var enabled = toggle.checked;
			var previousState = ! enabled;

			if ( ! moduleId || ! siteintelixOverviewData.ajaxUrl || ! siteintelixOverviewData.moduleToggleNonce ) {
				toggle.checked = previousState;
				toast( siteintelixOverviewData.moduleUpdateFailed, 'error' );
				return;
			}

			var body = new URLSearchParams();
			body.set( 'action', 'siteintelix_toggle_module' );
			body.set( 'nonce', siteintelixOverviewData.moduleToggleNonce );
			body.set( 'module', moduleId );
			body.set( 'enabled', enabled ? '1' : '0' );

			toggle.disabled = true;
			if ( card ) { card.classList.add( 'is-updating' ); }
			toast( siteintelixOverviewData.moduleUpdating );

			window.fetch( siteintelixOverviewData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						if ( ! response.ok || ! data || ! data.success ) {
							throw new Error( data && data.data && data.data.message ? data.data.message : siteintelixOverviewData.moduleUpdateFailed );
						}
						return data;
					} );
				} )
				.then( function ( data ) {
					toast( ( data.data && data.data.message ) || siteintelixOverviewData.moduleUpdated );
					window.setTimeout( function () { window.location.reload(); }, 300 );
				} )
				.catch( function ( error ) {
					toggle.checked = previousState;
					toggle.disabled = false;
					if ( card ) { card.classList.remove( 'is-updating' ); }
					toast( error.message || siteintelixOverviewData.moduleUpdateFailed, 'error' );
				} );
		} );
	}

	function fallbackCopy( text ) {
		var field = document.createElement( 'textarea' );
		field.value = text;
		field.style.cssText = 'position:fixed;left:-9999px;top:0';
		document.body.appendChild( field );
		field.select();
		var copied = false;
		try { copied = document.execCommand( 'copy' ); } catch ( error ) { copied = false; }
		document.body.removeChild( field );
		announce( copied ? siteintelixOverviewData.copied : siteintelixOverviewData.copyFailed, ! copied );
	}

	function copyReport( data ) {
		var text = JSON.stringify( data, null, 2 );
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then(
				function () { announce( siteintelixOverviewData.copied, false ); },
				function () { fallbackCopy( text ); }
			);
			return;
		}
		fallbackCopy( text );
	}

	function exportReport( data ) {
		var blob = new Blob( [ JSON.stringify( { plugin: 'SiteIntelix', generated: new Date().toISOString(), data: data }, null, 2 ) ], { type: 'application/json' } );
		var url = URL.createObjectURL( blob );
		var link = document.createElement( 'a' );
		link.href = url;
		link.download = 'siteintelix-redacted-' + new Date().toISOString().slice( 0, 10 ) + '.json';
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		URL.revokeObjectURL( url );
		announce( siteintelixOverviewData.exported, false );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var copy = document.getElementById( 'siteintelix-copy-btn' );
		var download = document.getElementById( 'siteintelix-export-btn' );
		if ( copy ) { copy.addEventListener( 'click', function () { var data = readData(); if ( data ) { copyReport( data ); } } ); }
		if ( download ) { download.addEventListener( 'click', function () { var data = readData(); if ( data ) { exportReport( data ); } } ); }
		initModuleToggles();
	} );
}() );
