( function () {
	'use strict';

	function ready( callback ) {
		if ( document.readyState !== 'loading' ) {
			callback();
			return;
		}

		document.addEventListener( 'DOMContentLoaded', callback );
	}

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', 'readonly' );
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild( textarea );
		textarea.select();
		document.execCommand( 'copy' );
		document.body.removeChild( textarea );
		return Promise.resolve();
	}

	function initDebugViewer() {
		var root = document.getElementById( 'siteintelix-debug-log-page' );
		if ( ! root ) {
			return;
		}

		var list = root.querySelector( '[data-sitx-log-list]' );
		var cards = list ? Array.prototype.slice.call( list.querySelectorAll( '[data-log-card]' ) ) : [];
		var searchInput = root.querySelector( '#siteintelix-log-search' );
		var typeFilter = root.querySelector( '#siteintelix-type-filter' );
		var fileFilter = root.querySelector( '#siteintelix-file-filter' );
		var timeFilter = root.querySelector( '#siteintelix-time-filter' );
		var pluginFilter = root.querySelector( '#siteintelix-plugin-filter' );
		var groupToggle = root.querySelector( '#siteintelix-group-toggle' );
		var hideDeprecated = root.querySelector( '#siteintelix-hide-deprecated' );
		var onlyCritical = root.querySelector( '#siteintelix-only-critical' );
		var clearFilters = root.querySelector( '#siteintelix-clear-filters' );
		var visibleCount = root.querySelector( '#siteintelix-visible-count' );
		var moreFilters = root.querySelector( '[data-sitx-more-filters]' );
		var advancedFilters = root.querySelector( '[data-sitx-advanced-filters]' );
		var savedFilters = root.querySelector( '#siteintelix-saved-filters' );
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

		function setParam( url, key, value, defaultValue ) {
			if ( ! value || value === defaultValue ) {
				url.searchParams.delete( key );
				return;
			}

			url.searchParams.set( key, value );
		}

		function runGlobalFilters() {
			if ( typeof URL === 'undefined' ) {
				applyFilters();
				return;
			}

			var url = new URL( window.location.href );
			var term = searchInput ? String( searchInput.value || '' ).trim() : '';
			url.searchParams.set( 'page', 'siteintelix-debug-log' );
			url.searchParams.delete( 'siteintelix_log_page' );

			setParam( url, 'siteintelix_log_search', term, '' );
			setParam( url, 'siteintelix_log_type', selectedValue( typeFilter, 'all' ), 'all' );
			setParam( url, 'siteintelix_log_file', fileFilter ? String( fileFilter.value || 'all' ) : 'all', 'all' );
			setParam( url, 'siteintelix_log_time', timeFilter ? String( timeFilter.value || 'all' ) : 'all', 'all' );
			setParam( url, 'siteintelix_log_plugin', pluginFilter ? String( pluginFilter.value || 'all' ) : 'all', 'all' );
			setParam( url, 'siteintelix_group_similar', groupToggle && ! groupToggle.checked ? '0' : '1', '1' );
			setParam( url, 'siteintelix_hide_deprecated', hideDeprecated && hideDeprecated.checked ? '1' : '0', '0' );
			setParam( url, 'siteintelix_only_critical', onlyCritical && onlyCritical.checked ? '1' : '0', '0' );

			if ( url.toString() !== window.location.href ) {
				window.location.href = url.toString();
			} else {
				applyFilters();
			}
		}

		function selectedValue( element, fallback ) {
			return element ? String( element.value || fallback ).toLowerCase() : fallback;
		}

		function cardMatchesSearch( card, term ) {
			if ( ! term ) {
				return true;
			}

			var haystack = String( card.getAttribute( 'data-message' ) || '' ).toLowerCase();
			haystack += ' ' + String( card.getAttribute( 'data-file' ) || '' ).toLowerCase();
			haystack += ' ' + String( card.getAttribute( 'data-plugin' ) || '' ).toLowerCase();
			return haystack.indexOf( term ) !== -1;
		}

		function cardMatchesTime( card, seconds ) {
			if ( ! seconds || seconds === 'all' ) {
				return true;
			}

			var lastSeen = parseInt( card.getAttribute( 'data-last-seen' ) || '0', 10 );
			if ( ! lastSeen ) {
				return false;
			}

			var now = Math.floor( Date.now() / 1000 );
			return now - lastSeen <= parseInt( seconds, 10 );
		}

		function applyFilters() {
			var term = searchInput ? String( searchInput.value || '' ).trim().toLowerCase() : '';
			var type = selectedValue( typeFilter, 'all' );
			var file = fileFilter ? String( fileFilter.value || 'all' ) : 'all';
			var plugin = pluginFilter ? String( pluginFilter.value || 'all' ) : 'all';
			var time = timeFilter ? String( timeFilter.value || 'all' ) : 'all';
			var grouped = ! groupToggle || groupToggle.checked;
			var visible = 0;

			cards.forEach( function ( card ) {
				var level = String( card.getAttribute( 'data-level' ) || '' ).toLowerCase();
				var cardFile = String( card.getAttribute( 'data-file' ) || '' );
				var cardPlugin = String( card.getAttribute( 'data-plugin' ) || '' );
				var isGroupCard = card.hasAttribute( 'data-group-card' );
				var isSingleCard = card.hasAttribute( 'data-single-card' );
				var matches = true;

				if ( ( grouped && isSingleCard ) || ( ! grouped && isGroupCard ) ) {
					matches = false;
				}

				if ( type !== 'all' && level !== type ) {
					matches = false;
				}

				if ( file !== 'all' && cardFile !== file ) {
					matches = false;
				}

				if ( plugin !== 'all' && cardPlugin !== plugin ) {
					matches = false;
				}

				if ( hideDeprecated && hideDeprecated.checked && level === 'deprecated' ) {
					matches = false;
				}

				if ( onlyCritical && onlyCritical.checked && level !== 'fatal' && level !== 'database' ) {
					matches = false;
				}

				if ( ! cardMatchesTime( card, time ) || ! cardMatchesSearch( card, term ) ) {
					matches = false;
				}

				card.classList.toggle( 'is-hidden', ! matches );
				if ( matches ) {
					visible++;
				}
			} );

			if ( visibleCount && ( ! searchInput || ! searchInput.hasAttribute( 'data-siteintelix-global-search' ) ) ) {
				visibleCount.textContent = visible === 1 ? '1 group' : visible + ' groups';
			}
		}

		function resetFilters() {
			if ( searchInput ) { searchInput.value = ''; }
			if ( typeFilter ) { typeFilter.value = 'all'; }
			if ( fileFilter ) { fileFilter.value = 'all'; }
			if ( timeFilter ) { timeFilter.value = 'all'; }
			if ( pluginFilter ) { pluginFilter.value = 'all'; }
			if ( groupToggle ) { groupToggle.checked = true; }
			if ( hideDeprecated ) { hideDeprecated.checked = false; }
			if ( onlyCritical ) { onlyCritical.checked = false; }
			applyFilters();
		}

		function toggleDetails( button ) {
			var card = button.closest( '[data-log-card]' );
			if ( ! card ) {
				return;
			}

			var targetId = button.getAttribute( 'aria-controls' );
			var details = targetId ? document.getElementById( targetId ) : card.querySelector( '.sitx-log-card__details' );
			var expanded = button.getAttribute( 'aria-expanded' ) === 'true';
			var nextState = ! expanded;
			var buttons = card.querySelectorAll( '[data-sitx-toggle-details]' );
			var label = card.querySelector( '[data-sitx-toggle-label]' );

			if ( details ) {
				details.hidden = ! nextState;
			}

			card.classList.toggle( 'is-expanded', nextState );
			buttons.forEach( function ( item ) {
				item.setAttribute( 'aria-expanded', nextState ? 'true' : 'false' );
			} );

			if ( label ) {
				label.textContent = nextState ? 'Hide' : 'View';
			}
		}

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

		[ typeFilter, fileFilter, timeFilter, pluginFilter, hideDeprecated, onlyCritical ].forEach( function ( control ) {
			if ( control ) {
				control.addEventListener( 'change', runGlobalFilters );
			}
		} );

		if ( groupToggle ) {
			groupToggle.addEventListener( 'change', function () {
				root.classList.toggle( 'is-ungrouped-view', ! groupToggle.checked );
				runGlobalFilters();
			} );
		}

		if ( clearFilters ) {
			clearFilters.addEventListener( 'click', function () {
				if ( typeof URL === 'undefined' ) {
					resetFilters();
					return;
				}

				window.location.href = window.location.origin + window.location.pathname + '?page=siteintelix-debug-log';
			} );
		}

		if ( moreFilters && advancedFilters ) {
			moreFilters.addEventListener( 'click', function () {
				var expanded = moreFilters.getAttribute( 'aria-expanded' ) === 'true';
				moreFilters.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
				advancedFilters.classList.toggle( 'is-collapsed', expanded );
			} );
		}

		if ( savedFilters ) {
			savedFilters.addEventListener( 'click', function () {
				window.alert( 'Saved filters are coming soon.' );
			} );
		}

		root.addEventListener( 'click', function ( event ) {
			var toggle = event.target.closest( '[data-sitx-toggle-details]' );
			var copy = event.target.closest( '[data-copy-text]' );

			if ( toggle ) {
				event.preventDefault();
				toggleDetails( toggle );
				return;
			}

			if ( copy ) {
				event.preventDefault();
				copyText( copy.getAttribute( 'data-copy-text' ) || '' ).then( function () {
					var original = copy.innerHTML;
					copy.textContent = 'Copied';
					window.setTimeout( function () {
						copy.innerHTML = original;
					}, 1400 );
				} );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 'k' && searchInput ) {
				event.preventDefault();
				searchInput.focus();
			}
		} );

		applyFilters();
	}

	ready( initDebugViewer );
}() );
