/**
 * SiteIntelix — Admin JavaScript
 *
 * Features:
 *   1. Copy Report — copies system data as formatted plain text.
 *   2. Export JSON — triggers a timestamped .json file download.
 *
 * Vanilla JS ES2015+. Zero external dependencies.
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

/* global siteintelixData */

( function () {
	'use strict';

	// -----------------------------------------------------------------------
	// Toast helper
	// -----------------------------------------------------------------------
	function showToast( message, type, duration ) {
		type     = type     || 'success';
		duration = duration || 2500;

		var existing = document.querySelector( '.siteintelix-toast' );
		if ( existing && existing.parentNode ) {
			existing.parentNode.removeChild( existing );
		}

		var toast       = document.createElement( 'div' );
		toast.className = 'siteintelix-toast siteintelix-toast--' + type;
		toast.textContent = message;
		document.body.appendChild( toast );

		requestAnimationFrame( function () {
			requestAnimationFrame( function () {
				toast.classList.add( 'siteintelix-toast--show' );
			} );
		} );

		setTimeout( function () {
			toast.classList.remove( 'siteintelix-toast--show' );
			setTimeout( function () {
				if ( toast.parentNode ) {
					toast.parentNode.removeChild( toast );
				}
			}, 360 );
		}, duration );
	}

	// -----------------------------------------------------------------------
	// Keep third-party notices above SiteIntelix UI
	// -----------------------------------------------------------------------
	function moveThirdPartyNotices() {
		var dashboard = document.getElementById( 'siteintelix-dashboard' );
		var slot      = document.getElementById( 'siteintelix-notices-slot' );
		if ( ! dashboard || ! slot ) { return; }

		var candidates = dashboard.querySelectorAll( '.notice, .update-nag, .error, .updated' );
		candidates.forEach( function ( el ) {
			if ( el.closest( '#siteintelix-notices-slot' ) ) {
				return;
			}

			slot.appendChild( el );
		} );
	}

	function initNoticeRelocation() {
		var dashboard = document.getElementById( 'siteintelix-dashboard' );
		if ( ! dashboard ) { return; }

		moveThirdPartyNotices();

		var observer = new MutationObserver( function () {
			moveThirdPartyNotices();
		} );

		observer.observe( dashboard, { childList: true, subtree: true } );
	}

	// -----------------------------------------------------------------------
	// Read JSON data island
	// -----------------------------------------------------------------------
	function getSystemData() {
		var el = document.getElementById( 'siteintelix-data-json' );
		if ( ! el ) { return null; }
		try {
			return JSON.parse( el.textContent || el.innerHTML );
		} catch ( e ) {
			console.error( 'SiteIntelix: failed to parse system data.', e ); // eslint-disable-line no-console
			return null;
		}
	}

	// -----------------------------------------------------------------------
	// Build plain-text report
	// -----------------------------------------------------------------------
	function buildReport( data ) {
		var sep   = '='.repeat( 60 );
		var sub   = '-'.repeat( 40 );
		var lines = [];

		lines.push( sep );
		lines.push( '  SITEINTELIX \u2014 SYSTEM REPORT' );
		lines.push( '  Generated: ' + new Date().toLocaleString() );
		lines.push( sep + '\n' );

		if ( data.wordpress ) {
			var wp = data.wordpress;
			lines.push( '[ WORDPRESS ]\n' + sub );
			lines.push( 'WP Version      : ' + wp.wp_version );
			lines.push( 'Site URL        : ' + wp.site_url );
			lines.push( 'Home URL        : ' + wp.home_url );
			lines.push( 'Active Theme    : ' + wp.active_theme );
			lines.push( 'Language        : ' + wp.language );
			lines.push( 'Charset         : ' + wp.charset );
			lines.push( 'Multisite       : ' + ( wp.multisite ? 'Yes' : 'No' ) );
			lines.push( '\nActive Plugins (' + wp.active_plugins.length + '):' );
			wp.active_plugins.forEach( function ( p ) { lines.push( '  \u2022 ' + p ); } );
			lines.push( '' );
		}

		if ( data.server ) {
			var srv = data.server;
			lines.push( '[ SERVER ]\n' + sub );
			lines.push( 'PHP Version     : ' + srv.php_version );
			lines.push( 'PHP SAPI        : ' + srv.php_sapi );
			lines.push( 'Server Software : ' + srv.server_software );
			lines.push( 'MySQL Version   : ' + srv.mysql_version );
			lines.push( 'Memory Limit    : ' + srv.memory_limit );
			lines.push( 'Max Upload Size : ' + srv.max_upload_size );
			lines.push( 'Max Exec Time   : ' + srv.max_exec_time );
			lines.push( 'Post Max Size   : ' + srv.post_max_size );
			lines.push( 'OS              : ' + srv.os );
			lines.push( 'Architecture    : ' + srv.architecture );
			lines.push( '' );
		}

		if ( data.environment ) {
			var env = data.environment;
			lines.push( '[ ENVIRONMENT ]\n' + sub );
			lines.push( 'REST API        : ' + ( env.rest_api     ? 'Accessible' : 'Blocked'  ) );
			lines.push( 'Debug Mode      : ' + ( env.debug_mode   ? 'Enabled'    : 'Disabled' ) );
			lines.push( 'Debug Log       : ' + ( env.debug_log    ? 'Enabled'    : 'Disabled' ) );
			lines.push( 'WP-Cron         : ' + ( env.cron         ? 'Enabled'    : 'Disabled' ) );
			lines.push( 'HTTPS           : ' + ( env.https        ? 'Enabled'    : 'Disabled' ) );
			lines.push( 'Environment     : ' + env.environment );
			lines.push( 'Object Cache    : ' + ( env.cache        ? 'Enabled'    : 'Disabled' ) );
			lines.push( 'Script Debug    : ' + ( env.script_debug ? 'Enabled'    : 'Disabled' ) );
			lines.push( '' );
		}

		lines.push( sep );
		return lines.join( '\n' );
	}

	// -----------------------------------------------------------------------
	// Copy Report button
	// -----------------------------------------------------------------------
	function initCopyButton() {
		var btn = document.getElementById( 'siteintelix-copy-btn' );
		if ( ! btn ) { return; }

		btn.addEventListener( 'click', function () {
			var data = getSystemData();
			if ( ! data ) {
				showToast( ( siteintelixData && siteintelixData.errorLabel ) || 'Failed to read data.', 'error' );
				return;
			}
			var text = buildReport( data );

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					function () { showToast( '\u2713 ' + ( ( siteintelixData && siteintelixData.copiedLabel ) || 'Copied!' ) ); },
					function () { fallbackCopy( text ); }
				);
			} else {
				fallbackCopy( text );
			}
		} );
	}

	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try { ok = document.execCommand( 'copy' ); } catch ( e ) { ok = false; }
		document.body.removeChild( ta );
		if ( ok ) {
			showToast( '\u2713 ' + ( ( siteintelixData && siteintelixData.copiedLabel ) || 'Copied!' ) );
		} else {
			showToast( ( siteintelixData && siteintelixData.errorLabel ) || 'Copy failed.', 'error' );
		}
	}

	// -----------------------------------------------------------------------
	// Export JSON button
	// -----------------------------------------------------------------------
	function pad2( n ) { return ( n < 10 ? '0' : '' ) + n; }

	function initExportButton() {
		var btn = document.getElementById( 'siteintelix-export-btn' );
		if ( ! btn ) { return; }

		btn.addEventListener( 'click', function () {
			var data = getSystemData();
			if ( ! data ) {
				showToast( ( siteintelixData && siteintelixData.errorLabel ) || 'Failed to read data.', 'error' );
				return;
			}

			var payload  = { plugin: 'SiteIntelix', generated: new Date().toISOString(), data: data };
			var json     = JSON.stringify( payload, null, 2 );
			var blob     = new Blob( [ json ], { type: 'application/json' } );
			var url      = URL.createObjectURL( blob );
			var today    = new Date();
			var filename = 'siteintelix-' + today.getFullYear() + '-' + pad2( today.getMonth() + 1 ) + '-' + pad2( today.getDate() ) + '.json';

			var a      = document.createElement( 'a' );
			a.href     = url;
			a.download = filename;
			a.style.display = 'none';
			document.body.appendChild( a );
			a.click();
			setTimeout( function () { document.body.removeChild( a ); URL.revokeObjectURL( url ); }, 150 );

			showToast( '\u2713 Exported as ' + filename );
		} );
	}

	// -----------------------------------------------------------------------
	// Accessibility
	// -----------------------------------------------------------------------
	function initA11y() {
		document.querySelectorAll( '.siteintelix-health-pill' ).forEach( function ( el ) {
			el.setAttribute( 'tabindex', '0' );
		} );
	}

	// -----------------------------------------------------------------------
	// Init
	// -----------------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		initNoticeRelocation();
		initCopyButton();
		initExportButton();
		initA11y();
	} );

}() );
