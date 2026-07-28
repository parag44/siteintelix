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
		toast.setAttribute( 'role', 'error' === type ? 'alert' : 'status' );
		toast.setAttribute( 'aria-live', 'error' === type ? 'assertive' : 'polite' );
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
	// Auto-dismiss SiteIntelix success notifications
	// -----------------------------------------------------------------------
	function initAutoDismissAlerts() {
		document.querySelectorAll( '.siteintelix-wrap .sitx-alert--success' ).forEach( function ( alert ) {
			window.setTimeout( function () {
				alert.classList.add( 'is-dismissing' );
				window.setTimeout( function () {
					if ( alert.parentNode ) {
						alert.parentNode.removeChild( alert );
					}
				}, 520 );
			}, 5000 );
		} );
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

		if ( data.database ) {
			var db = data.database;
			lines.push( '[ DATABASE ]\n' + sub );
			lines.push( 'Database Extension : ' + formatValue( db.extension ) );
			lines.push( 'Server Version     : ' + formatValue( db.server_version ) );
			lines.push( 'Client Version     : ' + formatValue( db.client_version ) );
			lines.push( 'Database Username  : ' + formatValue( db.username ) );
			lines.push( 'Database Host      : ' + formatValue( db.host ) );
			lines.push( 'Database Name      : ' + formatValue( db.name ) );
			lines.push( 'Table Prefix       : ' + formatValue( db.table_prefix ) );
			lines.push( 'Database Charset   : ' + formatValue( db.charset ) );
			lines.push( 'Database Collation : ' + formatValue( db.collation ) );
			lines.push( 'Max Allowed Packet : ' + formatValue( db.max_allowed_packet ) );
			lines.push( 'Max Connections    : ' + formatValue( db.max_connections ) );
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

	function initTextCopyButtons() {
		document.addEventListener( 'click', function ( event ) {
			var copyButton = event.target.closest( '[data-siteintelix-copy-text]' );
			var text;

			if ( ! copyButton ) {
				return;
			}

			text = copyButton.getAttribute( 'data-siteintelix-copy-text' ) || '';
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then(
					function () { showToast( 'Copied to clipboard.' ); },
					function () { fallbackCopy( text ); }
				);
			} else {
				fallbackCopy( text );
			}
		} );
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
		var rows = root.querySelectorAll( '.siteintelix-log-row' );
		if ( ! filterButtons.length && ! searchInput ) { return; }

		var activeLevel = 'all';
		var searchTimer = null;

		function runGlobalSearch() {
			if ( ! searchInput || ! searchInput.hasAttribute( 'data-siteintelix-global-search' ) || typeof URL === 'undefined' ) {
				return;
			}

			var term = String( searchInput.value || '' ).trim();
			var url = new URL( window.location.href );
			url.searchParams.set( 'page', 'siteintelix-debug-log' );
			url.searchParams.delete( 'siteintelix_log_page' );

			if ( term ) {
				url.searchParams.set( 'siteintelix_log_search', term );
			} else {
				url.searchParams.delete( 'siteintelix_log_search' );
			}

			if ( url.toString() !== window.location.href ) {
				window.location.href = url.toString();
			}
		}

		function rowMatchesLevel( row, level ) {
			var rowLevel = ( row.getAttribute( 'data-level' ) || '' ).toLowerCase();
			if ( level === 'all' ) { return true; }
			if ( level === 'fatal' ) { return rowLevel === 'fatal' || rowLevel === 'error' || rowLevel === 'parse'; }
			if ( level === 'warning' ) { return rowLevel === 'warning' || rowLevel === 'warn'; }
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
			searchInput.addEventListener( 'input', function () {
				var term = String( searchInput.value || '' ).trim();
				applyFilters();

				if ( ! searchInput.hasAttribute( 'data-siteintelix-global-search' ) || ( term.length > 0 && term.length < 3 ) ) {
					return;
				}

				window.clearTimeout( searchTimer );
				searchTimer = window.setTimeout( runGlobalSearch, 650 );
			} );
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
	// Modules page search
	// -----------------------------------------------------------------------
	function initModuleSearch() {
		var page = document.getElementById( 'siteintelix-modules-page' );
		if ( ! page ) { return; }

		var input = page.querySelector( '[data-siteintelix-module-search]' );
		var cards = page.querySelectorAll( '[data-siteintelix-module-card]' );
		if ( ! input || ! cards.length ) { return; }

		input.addEventListener( 'input', function () {
			var term = String( input.value || '' ).trim().toLowerCase();

			cards.forEach( function ( card ) {
				var title = card.getAttribute( 'data-module-title' ) || '';
				var desc = card.getAttribute( 'data-module-description' ) || '';
				card.style.display = ! term || title.indexOf( term ) !== -1 || desc.indexOf( term ) !== -1 ? '' : 'none';
			} );
		} );
	}

	// -----------------------------------------------------------------------
	// Modules page instant toggles
	// -----------------------------------------------------------------------
	function initModuleToggles() {
		var page = document.getElementById( 'siteintelix-modules-page' ) || document.getElementById( 'siteintelix-dashboard' );
		if ( ! page ) { return; }

		page.addEventListener( 'change', function ( event ) {
			var toggle = event.target.closest( '[data-siteintelix-module-toggle]' );
			if ( ! toggle ) { return; }

			var card = toggle.closest( '[data-siteintelix-module-card]' );
			var moduleId = toggle.value || ( card ? card.getAttribute( 'data-module-id' ) : '' );
			var enabled = toggle.checked;
			var previousState = ! enabled;

			if ( ! moduleId || ! siteintelixData || ! siteintelixData.ajaxUrl || ! siteintelixData.moduleToggleNonce ) {
				toggle.checked = previousState;
				showToast( 'Module toggle is unavailable.', 'error' );
				return;
			}

			toggle.disabled = true;
			if ( card ) {
				card.classList.add( 'is-updating' );
			}

			var body = new URLSearchParams();
			body.append( 'action', 'siteintelix_toggle_module' );
			body.append( 'nonce', siteintelixData.moduleToggleNonce );
			body.append( 'module', moduleId );
			body.append( 'enabled', enabled ? '1' : '0' );

			showToast( siteintelixData.moduleUpdating || 'Updating module…' );

			fetch( siteintelixData.ajaxUrl, {
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
							throw new Error( data && data.data && data.data.message ? data.data.message : 'Module update failed.' );
						}

						return data;
					} );
				} )
				.then( function ( data ) {
					showToast( ( data.data && data.data.message ) || siteintelixData.moduleUpdated || 'Module updated.' );
					window.setTimeout( function () {
						window.location.reload();
					}, 350 );
				} )
				.catch( function ( error ) {
					toggle.checked = previousState;
					toggle.disabled = false;
					if ( card ) {
						card.classList.remove( 'is-updating' );
					}
					showToast( error.message || 'Module update failed.', 'error' );
				} );
		} );
	}

	// -----------------------------------------------------------------------
	// Tabbed module settings
	// -----------------------------------------------------------------------
	function initModuleSettingsTabs() {
		var root = document.querySelector( '[data-siteintelix-settings-tabs]' );
		if ( ! root ) { return; }

		var moduleTabs = root.querySelectorAll( '[data-siteintelix-settings-tab]' );
		var panels = root.querySelectorAll( '[data-siteintelix-settings-panel]' );
		var search = root.querySelector( '[data-siteintelix-settings-search]' );

		function getPanelIdFromHash() {
			var hash = window.location.hash ? window.location.hash.replace( '#', '' ) : '';
			var panel;

			if ( ! hash ) { return ''; }

			panel = document.getElementById( hash );
			if ( ! panel || ! root.contains( panel ) || ! panel.hasAttribute( 'data-siteintelix-settings-panel' ) ) {
				return '';
			}
			return panel ? panel.getAttribute( 'data-siteintelix-settings-panel' ) : '';
		}

		function activatePanel( id, updateHash ) {
			var activePanel = null;

			if ( ! id ) { return; }

			panels.forEach( function ( panel ) {
				if ( panel.getAttribute( 'data-siteintelix-settings-panel' ) === id ) {
					activePanel = panel;
				}
			} );

			if ( ! activePanel ) { return; }

			moduleTabs.forEach( function ( tab ) {
				var isActive = tab.getAttribute( 'data-siteintelix-settings-tab' ) === id;
				tab.classList.toggle( 'is-active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			panels.forEach( function ( panel ) {
				var isActive = panel.getAttribute( 'data-siteintelix-settings-panel' ) === id;
				panel.classList.toggle( 'is-active', isActive );
			} );

			try {
				window.sessionStorage.setItem( 'siteintelixActiveSettingsTab', id );
			} catch ( error ) {}

			if ( updateHash && activePanel && activePanel.id ) {
				window.history.replaceState( null, '', '#' + activePanel.id );
			}
		}

		function getStoredPanelId() {
			try {
				return window.sessionStorage.getItem( 'siteintelixActiveSettingsTab' ) || '';
			} catch ( error ) {
				return '';
			}
		}

		root.addEventListener( 'click', function ( event ) {
			var moduleTab = event.target.closest( '[data-siteintelix-settings-tab]' );
			var subtab = event.target.closest( '[data-siteintelix-subtab]' );
			var panel;
			var subtabId;

			if ( moduleTab ) {
				activatePanel( moduleTab.getAttribute( 'data-siteintelix-settings-tab' ), true );
				return;
			}

			if ( subtab ) {
				panel = subtab.closest( '[data-siteintelix-settings-panel]' );
				if ( ! panel ) { return; }

				subtabId = subtab.getAttribute( 'data-siteintelix-subtab' );
				panel.querySelectorAll( '[data-siteintelix-subtab]' ).forEach( function ( button ) {
					button.classList.toggle( 'is-active', button === subtab );
				} );
				panel.querySelectorAll( '[data-siteintelix-subpanel]' ).forEach( function ( subpanel ) {
					subpanel.classList.toggle( 'is-active', subpanel.getAttribute( 'data-siteintelix-subpanel' ) === subtabId );
				} );
			}
		} );

		if ( search ) {
			search.addEventListener( 'input', function () {
				var term = String( search.value || '' ).trim().toLowerCase();
				root.querySelectorAll( '.sitx-setting-row, .sitx-side-card' ).forEach( function ( item ) {
					var text = String( item.textContent || '' ).toLowerCase();
					item.style.display = ! term || text.indexOf( term ) !== -1 ? '' : 'none';
				} );
			} );
		}

		root.addEventListener( 'submit', function () {
			var activeTab = root.querySelector( '[data-siteintelix-settings-tab].is-active' );

			if ( activeTab ) {
				try {
					window.sessionStorage.setItem( 'siteintelixActiveSettingsTab', activeTab.getAttribute( 'data-siteintelix-settings-tab' ) );
				} catch ( error ) {}
			}
		} );

		activatePanel( getPanelIdFromHash() || ( window.location.search.indexOf( 'siteintelix_settings_saved=1' ) !== -1 ? getStoredPanelId() : '' ), false );
	}

	// -----------------------------------------------------------------------
	// Email Log preview modal
	// -----------------------------------------------------------------------
	function initEmailPreviewModal() {
		var modal = document.querySelector( '[data-siteintelix-email-modal]' );
		if ( ! modal ) { return; }

		var title = modal.querySelector( '#siteintelix-email-modal-title' );
		var meta = modal.querySelector( '[data-siteintelix-email-modal-meta]' );
		var headers = modal.querySelector( '[data-siteintelix-email-modal-headers]' );
		var attachments = modal.querySelector( '[data-siteintelix-email-modal-attachments]' );
		var frame = modal.querySelector( '[data-siteintelix-email-panel="html"]' );
		var source = modal.querySelector( '[data-siteintelix-email-panel="source"]' );
		var closeButtons = modal.querySelectorAll( '[data-siteintelix-email-modal-close]' );

		function openModal( payload ) {
			var message = payload.message || '';
			var status = String( payload.status || 'Sent' );
			var statusBadge = document.createElement( 'span' );
			var sentLabel = document.createElement( 'strong' );
			var toLabel = document.createElement( 'strong' );

			title.textContent = payload.subject || '(No subject)';
			meta.textContent = '';
			statusBadge.className = 'sitx-email-status sitx-email-status--' + status.toLowerCase();
			statusBadge.textContent = status;
			sentLabel.textContent = 'Sent at:';
			toLabel.textContent = 'To:';
			meta.appendChild( statusBadge );
			meta.appendChild( sentLabel );
			meta.appendChild( document.createTextNode( ' ' + ( payload.sentAt || '-' ) ) );
			meta.appendChild( toLabel );
			meta.appendChild( document.createTextNode( ' ' + ( payload.to || '-' ) ) );
			headers.textContent = payload.headers || 'No headers logged for this email.';
			attachments.textContent = payload.attachments || 'No attachments for this email.';
			source.textContent = message || payload.error || '';

			if ( frame ) {
				frame.srcdoc = message || '<!doctype html><html><body><p>No HTML body logged for this email.</p></body></html>';
			}

			modal.classList.add( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			var close = modal.querySelector( '.sitx-email-modal__close' );
			if ( close ) {
				close.focus();
			}
		}

		function closeModal() {
			modal.classList.remove( 'is-open' );
			modal.setAttribute( 'aria-hidden', 'true' );
			if ( frame ) {
				frame.srcdoc = '';
			}
		}

		document.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-siteintelix-email-preview]' );
			var tab = event.target.closest( '[data-siteintelix-email-tab]' );
			var payload;
			var activeTab;

			if ( button ) {
				try {
					payload = JSON.parse( button.getAttribute( 'data-siteintelix-email-preview' ) || '{}' );
					openModal( payload );
				} catch ( e ) {
					showToast( 'Could not open email preview.', 'error' );
				}
				return;
			}

			if ( tab && modal.contains( tab ) ) {
				activeTab = tab.getAttribute( 'data-siteintelix-email-tab' );
				modal.querySelectorAll( '[data-siteintelix-email-tab]' ).forEach( function ( item ) {
					item.classList.toggle( 'is-active', item === tab );
				} );
				modal.querySelectorAll( '[data-siteintelix-email-panel]' ).forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.getAttribute( 'data-siteintelix-email-panel' ) === activeTab );
				} );
			}
		} );

		closeButtons.forEach( function ( button ) {
			button.addEventListener( 'click', closeModal );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && modal.classList.contains( 'is-open' ) ) {
				closeModal();
			}
		} );
	}

	// -----------------------------------------------------------------------
	// Email Log actions
	// -----------------------------------------------------------------------
	function initEmailLogActions() {
		var testForm = document.querySelector( '[data-siteintelix-test-email-form]' );
		var selectAll = document.querySelector( '[data-siteintelix-email-select-all]' );
		var bulkForm = document.querySelector( '.sitx-email-log-bulk-form' );

		document.addEventListener( 'click', function ( event ) {
			var testButton = event.target.closest( '[data-siteintelix-send-test-email]' );
			var confirmLink = event.target.closest( '[data-siteintelix-confirm]' );
			var defaultRecipient;
			var recipient;

			if ( testButton && testForm ) {
				defaultRecipient = testButton.getAttribute( 'data-default-recipient' ) || '';
				recipient = defaultRecipient;

				if ( null === recipient ) {
					return;
				}

				recipient = String( recipient || '' ).trim();
				if ( ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( recipient ) ) {
					showToast( 'Please enter a valid email address.', 'error' );
					return;
				}

				testForm.querySelector( 'input[name="recipient"]' ).value = recipient;
				testForm.submit();
				return;
			}

			if ( confirmLink && false ) {
				event.preventDefault();
			}
		} );

		if ( selectAll && bulkForm ) {
			selectAll.addEventListener( 'change', function () {
				bulkForm.querySelectorAll( 'input[name="log_ids[]"]' ).forEach( function ( checkbox ) {
					checkbox.checked = selectAll.checked;
				} );
			} );
		}

		if ( bulkForm ) {
			bulkForm.addEventListener( 'submit', function ( event ) {
				var action = bulkForm.querySelector( 'select[name="bulk_action"]' );
				var selected = bulkForm.querySelectorAll( 'input[name="log_ids[]"]:checked' );

				if ( ! action || 'delete' !== action.value ) {
					event.preventDefault();
					showToast( 'Choose a bulk action first.', 'error' );
					return;
				}

				if ( ! selected.length ) {
					event.preventDefault();
					showToast( 'Select at least one email log.', 'error' );
					return;
				}

				if ( false ) {
					event.preventDefault();
				}
			} );
		}
	}

	// -----------------------------------------------------------------------
	// Safe Mode Debugger controls
	// -----------------------------------------------------------------------
	function initSafeModePage() {
		var page = document.getElementById( 'siteintelix-safe-mode-page' );
		if ( ! page ) { return; }

		var pluginModes = page.querySelectorAll( 'input[name="siteintelix_plugin_mode"]' );
		var pluginPicker = page.querySelector( '[data-siteintelix-safe-plugin-picker]' );
		var pluginSearch = page.querySelector( '[data-siteintelix-safe-plugin-search]' );
		var themeModes = page.querySelectorAll( 'input[name="siteintelix_theme_mode"]' );
		var themeSelect = page.querySelector( '.sitx-safe-theme-select' );

		function selectedValue( nodes ) {
			var value = '';
			nodes.forEach( function ( node ) {
				if ( node.checked ) {
					value = node.value;
				}
			} );
			return value;
		}

		function syncVisibility() {
			if ( pluginPicker ) {
				pluginPicker.classList.toggle( 'is-visible', selectedValue( pluginModes ) === 'only' );
			}

			if ( themeSelect ) {
				themeSelect.classList.toggle( 'is-visible', selectedValue( themeModes ) === 'switch' );
			}
		}

		pluginModes.forEach( function ( input ) {
			input.addEventListener( 'change', syncVisibility );
		} );

		themeModes.forEach( function ( input ) {
			input.addEventListener( 'change', syncVisibility );
		} );

		if ( pluginSearch ) {
			pluginSearch.addEventListener( 'input', function () {
				var query = pluginSearch.value.trim().toLowerCase();
				page.querySelectorAll( '.sitx-safe-plugin' ).forEach( function ( row ) {
					var haystack = row.getAttribute( 'data-plugin-name' ) || '';
					row.hidden = query && haystack.indexOf( query ) === -1;
				} );
			} );
		}

		syncVisibility();
	}

	// -----------------------------------------------------------------------
	// Transients Manager controls
	// -----------------------------------------------------------------------
	function initTransientsManagerPage() {
		var page = document.getElementById( 'siteintelix-transients-manager-page' );
		if ( ! page ) { return; }

		var selectAll = page.querySelector( '[data-siteintelix-transients-select-all]' );
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				page.querySelectorAll( 'input[name="transients[]"]' ).forEach( function ( checkbox ) {
					checkbox.checked = selectAll.checked;
				} );
			} );
		}
	}

	// -----------------------------------------------------------------------
	// Cron Events controls
	// -----------------------------------------------------------------------
	function initCronEventsPage() {
		var page = document.getElementById( 'siteintelix-cron-events-page' );
		if ( ! page ) { return; }

		var selectAll = page.querySelector( '[data-siteintelix-cron-select-all]' );
		if ( selectAll ) {
			selectAll.addEventListener( 'change', function () {
				page.querySelectorAll( 'input[name="cron_events[]"]' ).forEach( function ( checkbox ) {
					checkbox.checked = selectAll.checked;
				} );
			} );
		}
	}

	// -----------------------------------------------------------------------
	// Maintenance Mode media picker
	// -----------------------------------------------------------------------
	function initMaintenanceLogoPicker() {
		var picker = document.querySelector( '[data-siteintelix-maintenance-logo-picker]' );
		if ( ! picker ) { return; }

		var selectButton = picker.querySelector( '[data-siteintelix-maintenance-logo-select]' );
		var removeButton = picker.querySelector( '[data-siteintelix-maintenance-logo-remove]' );
		var preview = picker.querySelector( '[data-siteintelix-maintenance-logo-preview]' );
		var idInput = picker.querySelector( '[data-siteintelix-maintenance-logo-id]' );
		var urlInput = picker.querySelector( '[data-siteintelix-maintenance-logo-url]' );
		var mediaFrame;

		function setPreview( url ) {
			if ( ! preview ) { return; }

			preview.textContent = '';
			if ( url ) {
				var image = document.createElement( 'img' );
				image.src = url;
				image.alt = '';
				preview.appendChild( image );
			} else {
				preview.innerHTML = '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
			}
		}

		if ( selectButton ) {
			selectButton.addEventListener( 'click', function () {
				if ( ! window.wp || ! window.wp.media ) {
					showToast( 'WordPress media library is not available.', 'error' );
					return;
				}

				if ( mediaFrame ) {
					mediaFrame.open();
					return;
				}

				mediaFrame = window.wp.media( {
					title: window.siteintelixData && siteintelixData.chooseLogoLabel ? siteintelixData.chooseLogoLabel : 'Choose Logo',
					button: {
						text: window.siteintelixData && siteintelixData.useLogoLabel ? siteintelixData.useLogoLabel : 'Use this logo'
					},
					library: {
						type: 'image'
					},
					multiple: false
				} );

				mediaFrame.on( 'select', function () {
					var attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
					var url = attachment && attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

					if ( idInput ) {
						idInput.value = attachment.id || '';
					}
					if ( urlInput ) {
						urlInput.value = url || '';
					}
					setPreview( url || '' );
				} );

				mediaFrame.open();
			} );
		}

		if ( removeButton ) {
			removeButton.addEventListener( 'click', function () {
				if ( idInput ) {
					idInput.value = '';
				}
				if ( urlInput ) {
					urlInput.value = '';
				}
				setPreview( '' );
			} );
		}

		if ( urlInput ) {
			urlInput.addEventListener( 'input', function () {
				setPreview( urlInput.value.trim() );
			} );
		}
	}

	// -----------------------------------------------------------------------
	// Init
	// -----------------------------------------------------------------------
	document.addEventListener( 'DOMContentLoaded', function () {
		initNoticeRelocation();
		initAutoDismissAlerts();
		initA11y();
		initDebugLogFilters();
		initSettingsPage();
		initModuleSearch();
		initModuleToggles();
		initSafeModePage();
		initTextCopyButtons();
		initCronEventsPage();
		initTransientsManagerPage();
		initMaintenanceLogoPicker();
	} );

}() );
