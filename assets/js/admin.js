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
		var root = document.querySelector( '#siteintelix-dashboard, #siteintelix-debug-log-page' );
		var slot = document.getElementById( 'siteintelix-notices-slot' );
		if ( ! root || ! slot ) { return; }

		var candidates = root.querySelectorAll( '.notice, .update-nag, .error, .updated, .tutor-admin-notice, .tutor-notice, [class*="notice-"]' );
		candidates.forEach( function ( el ) {
			if ( el.closest( '#siteintelix-notices-slot' ) ) {
				return;
			}

			slot.appendChild( el );
		} );
	}

	function initNoticeRelocation() {
		var root = document.querySelector( '#siteintelix-dashboard, #siteintelix-debug-log-page' );
		if ( ! root ) { return; }

		moveThirdPartyNotices();

		var observer = new MutationObserver( function () {
			moveThirdPartyNotices();
		} );

		observer.observe( root, { childList: true, subtree: true } );
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
	function formatToggle( value, enabledLabel, disabledLabel ) {
		return value ? ( enabledLabel || 'Enabled' ) : ( disabledLabel || 'Disabled' );
	}

	function formatValue( value, fallback ) {
		if ( value === null || value === undefined || value === '' ) {
			return fallback || 'N/A';
		}

		return String( value );
	}

	function buildReport( data ) {
		var sep   = '='.repeat( 60 );
		var sub   = '-'.repeat( 40 );
		var lines = [];
		var extensions;

		lines.push( sep );
		lines.push( '  SITEINTELIX \u2014 SYSTEM REPORT' );
		lines.push( '  Generated: ' + new Date().toLocaleString() );
		lines.push( sep + '\n' );

		if ( data.wordpress ) {
			var wp = data.wordpress;
			lines.push( '[ WORDPRESS ]\n' + sub );
			lines.push( 'Site Title      : ' + formatValue( wp.site_title ) );
			lines.push( 'WP Version      : ' + wp.wp_version );
			lines.push( 'Site URL        : ' + wp.site_url );
			lines.push( 'Home URL        : ' + wp.home_url );
			lines.push( 'Permalinks      : ' + formatValue( wp.permalink, 'Default' ) );
			lines.push( 'Timezone        : ' + formatValue( wp.timezone ) );
			lines.push( 'Admin Email     : ' + formatValue( wp.admin_email ) );
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
			extensions = [];
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
			lines.push( 'OPcache         : ' + formatToggle( srv.opcache ) );
			lines.push( 'Uploads Dir     : ' + formatValue( srv.uploads_dir && srv.uploads_dir.basedir ) );
			lines.push( 'Disk Free       : ' + formatValue( srv.disk_free ) );
			lines.push( 'Database Host   : ' + formatValue( srv.db_host ) );
			lines.push( 'Database Name   : ' + formatValue( srv.db_name ) );

			if ( srv.php_extensions ) {
				Object.keys( srv.php_extensions ).forEach( function ( key ) {
					if ( srv.php_extensions[ key ] ) {
						extensions.push( key );
					}
				} );
			}

			lines.push( 'PHP Extensions  : ' + formatValue( extensions.join( ', ' ) ) );
			lines.push( '' );
		}

		if ( data.environment ) {
			var env = data.environment;
			lines.push( '[ ENVIRONMENT ]\n' + sub );
			lines.push( 'REST API        : ' + formatToggle( env.rest_api, 'Accessible', 'Blocked' ) );
			lines.push( 'Debug Mode      : ' + formatToggle( env.debug_mode ) );
			lines.push( 'Debug Log       : ' + formatToggle( env.debug_log ) );
			lines.push( 'WP-Cron         : ' + formatToggle( env.cron ) );
			lines.push( 'HTTPS           : ' + formatToggle( env.https ) );
			lines.push( 'Environment     : ' + env.environment );
			lines.push( 'Object Cache    : ' + formatToggle( env.cache ) );
			lines.push( 'Script Debug    : ' + formatToggle( env.script_debug ) );
			lines.push( 'File Editor     : ' + formatToggle( ! env.file_edit ) );
			lines.push( 'File Mods       : ' + formatToggle( ! env.file_mods ) );
			lines.push( 'Core Updates    : ' + formatValue( env.auto_update ) );
			lines.push( 'Alternate Cron  : ' + formatToggle( env.alt_cron ) );
			lines.push( 'Cron Lock       : ' + formatValue( env.cron_lock ? env.cron_lock + 's' : '', 'Default' ) );
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
	// Debug log page filters
	// -----------------------------------------------------------------------
	function initDebugLogFilters() {
		var root = document.getElementById( 'siteintelix-debug-log-page' );
		if ( ! root ) { return; }

		var filterButtons = root.querySelectorAll( '.siteintelix-debug-filter, .sitx-filter' );
		var searchInput = root.querySelector( '#siteintelix-log-search' );
		var rows = root.querySelectorAll( '.siteintelix-log-line' );
		if ( ! filterButtons.length || ! rows.length ) { return; }

		var activeLevel = 'all';

		function rowMatchesLevel( row, level ) {
			var rowLevel = ( row.getAttribute( 'data-level' ) || '' ).toLowerCase();
			if ( level === 'all' ) { return true; }
			if ( level === 'fatal' ) { return rowLevel === 'fatal' || rowLevel === 'error'; }
			return rowLevel === level;
		}

		function rowMatchesSearch( row, term ) {
			if ( ! term ) { return true; }
			var msg = ( row.getAttribute( 'data-message' ) || '' ).toLowerCase();
			var text = ( row.textContent || '' ).toLowerCase();
			return msg.indexOf( term ) !== -1 || text.indexOf( term ) !== -1;
		}

		function applyFilters() {
			var term = searchInput ? String( searchInput.value || '' ).trim().toLowerCase() : '';
			rows.forEach( function ( row ) {
				var ok = rowMatchesLevel( row, activeLevel ) && rowMatchesSearch( row, term );
				row.style.display = ok ? '' : 'none';
			} );
		}

		filterButtons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				activeLevel = ( btn.getAttribute( 'data-level' ) || 'all' ).toLowerCase();
				filterButtons.forEach( function (b) { b.classList.remove( 'is-active' ); } );
				btn.classList.add( 'is-active' );
				applyFilters();
			} );
		} );

		if ( searchInput ) {
			searchInput.addEventListener( 'input', applyFilters );
		}
	}

	// -----------------------------------------------------------------------
	// Settings page — method card selection
	// -----------------------------------------------------------------------
	function initSettingsPage() {
		var page = document.getElementById( 'siteintelix-settings-page' );
		if ( ! page ) { return; }

		var cards  = page.querySelectorAll( '.siteintelix-method-card' );
		var radios = page.querySelectorAll( '.siteintelix-method-card__radio' );

		// Highlight the currently selected card.
		function updateCardHighlight() {
			cards.forEach( function ( card ) {
				var radio = card.querySelector( '.siteintelix-method-card__radio' );
				if ( radio && radio.checked ) {
					card.classList.add( 'is-selected' );
				} else {
					card.classList.remove( 'is-selected' );
				}
			} );
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', function () {
				// Warn when selecting wp-config method.
				if ( radio.value === 'wp_config' ) {
					var confirmed = window.confirm(
						'This will allow SiteIntelix to modify wp-config.php to enable WP_DEBUG constants.\n' +
						'A backup (wp-config.php.bak) will be created before any change.\n\n' +
						'Continue?'
					);
					if ( ! confirmed ) {
						// Revert selection.
						radios.forEach( function ( r ) {
							if ( r.value === 'mu' ) { r.checked = true; }
						} );
						updateCardHighlight();
						return;
					}
				}
				updateCardHighlight();
			} );
		} );

		// Also trigger on clicking anywhere within the card label.
		cards.forEach( function ( card ) {
			card.addEventListener( 'click', function () {
				updateCardHighlight();
			} );
		} );

		updateCardHighlight();
	}

	// -----------------------------------------------------------------------
	// Security page — live toggle state updates
	// -----------------------------------------------------------------------
	function initSecurityToggles() {
		var page = document.getElementById( 'siteintelix-security-page' );
		if ( ! page ) { return; }

		var toggleInputs = page.querySelectorAll( '.siteintelix-security-card .siteintelix-toggle input' );

		toggleInputs.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				var card        = input.closest( '.siteintelix-security-card' );
				var statusLabel = card && card.querySelector( '.siteintelix-security-card__status-label' );
				if ( ! card ) { return; }

				if ( input.checked ) {
					card.classList.add( 'is-active' );
					if ( statusLabel ) { statusLabel.textContent = 'Active'; }
				} else {
					card.classList.remove( 'is-active' );
					if ( statusLabel ) { statusLabel.textContent = 'Inactive'; }
				}
			} );
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
		initDebugLogFilters();
		initSettingsPage();
		initSecurityToggles();
	} );

}() );
