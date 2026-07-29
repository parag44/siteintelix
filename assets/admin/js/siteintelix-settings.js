(function () {
	'use strict';

	function initMaintenanceMediaPickers(documentObject, windowObject) {
		Array.prototype.slice.call(
			documentObject.querySelectorAll('[data-siteintelix-maintenance-media-picker]')
		).forEach(function (picker) {
			var select = picker.querySelector('[data-siteintelix-media-select]');
			var remove = picker.querySelector('[data-siteintelix-media-remove]');
			var preview = picker.querySelector('[data-siteintelix-media-preview]');
			var idInput = picker.querySelector('[data-siteintelix-media-id]');
			var urlInput = picker.querySelector('[data-siteintelix-media-url]');
			var mediaFrame;

			function preferredUrl(item) {
				var size = picker.getAttribute('data-media-size') || 'medium';
				return item && item.sizes && item.sizes[size] ? item.sizes[size].url : (item.url || '');
			}

			function setPreview(url) {
				preview.textContent = '';
				if (url) {
					var image = documentObject.createElement('img');
					image.src = url;
					image.alt = '';
					preview.appendChild(image);
				}
				picker.classList.toggle('has-image', !!url);
			}

			select.addEventListener('click', function () {
				if (!windowObject.wp || !windowObject.wp.media) { return; }
				if (!mediaFrame) {
					mediaFrame = windowObject.wp.media({
						title: picker.getAttribute('data-media-title') || 'Choose Image',
						button: { text: picker.getAttribute('data-media-button') || 'Use this image' },
						library: { type: 'image' },
						multiple: false
					});
					mediaFrame.on('select', function () {
						var item = mediaFrame.state().get('selection').first().toJSON();
						var url = preferredUrl(item);
						idInput.value = item.id || '';
						if (urlInput) { urlInput.value = url; }
						setPreview(url);
					});
				}
				mediaFrame.open();
			});

			remove.addEventListener('click', function () {
				idInput.value = '';
				if (urlInput) { urlInput.value = ''; }
				setPreview('');
			});

			if (urlInput) {
				urlInput.addEventListener('input', function () {
					setPreview(urlInput.value.trim());
				});
			}
		});
	}

	function initSettings(documentObject, windowObject) {
		var root = documentObject.querySelector('[data-siteintelix-settings-tabs]');
		if (!root) { return; }
		var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-siteintelix-settings-tab]'));
		var panels = Array.prototype.slice.call(root.querySelectorAll('[data-siteintelix-settings-panel]'));
		var search = root.querySelector('[data-siteintelix-settings-search]');
		var clear = root.querySelector('[data-siteintelix-settings-clear]');
		var results = root.querySelector('[data-siteintelix-settings-results]');

		function activate(id, updateHash, focusTab) {
			var activePanel = null;
			tabs.forEach(function (tab) {
				var active = tab.getAttribute('data-siteintelix-settings-tab') === id;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.tabIndex = active ? 0 : -1;
				if (active && focusTab) { tab.focus(); }
			});
			panels.forEach(function (panel) {
				var active = panel.getAttribute('data-siteintelix-settings-panel') === id;
				panel.hidden = !active;
				panel.classList.toggle('is-active', active);
				if (active) { activePanel = panel; }
			});
			if (!activePanel) { return; }
			try { windowObject.sessionStorage.setItem('siteintelixActiveSettingsTab', id); } catch (error) {}
			if (updateHash) { windowObject.history.replaceState(null, '', '#' + activePanel.id); }
		}

		function move(tab, key) {
			var index = tabs.indexOf(tab);
			if ('Home' === key) { index = 0; }
			else if ('End' === key) { index = tabs.length - 1; }
			else if ('ArrowRight' === key) { index = (index + 1) % tabs.length; }
			else if ('ArrowLeft' === key) { index = (index - 1 + tabs.length) % tabs.length; }
			else { return; }
			activate(tabs[index].getAttribute('data-siteintelix-settings-tab'), true, true);
		}

		tabs.forEach(function (tab) {
			tab.addEventListener('click', function () { activate(tab.getAttribute('data-siteintelix-settings-tab'), true, false); });
			tab.addEventListener('keydown', function (event) {
				if (['ArrowRight', 'ArrowLeft', 'Home', 'End'].indexOf(event.key) !== -1) {
					event.preventDefault();
					move(tab, event.key);
				}
			});
		});

		function runSearch() {
			var term = String(search.value || '').trim().toLowerCase();
			var matches = 0;
			root.querySelectorAll('.sitx-setting-row, .sitx-side-card').forEach(function (item) {
				var matched = !term || String(item.textContent || '').toLowerCase().indexOf(term) !== -1;
				item.hidden = !matched;
				if (matched && term) { matches += 1; }
			});
			clear.hidden = !term;
			results.textContent = term ? String(matches) + (1 === matches ? ' setting found.' : ' settings found.') : '';
		}
		search.addEventListener('input', runSearch);
		clear.addEventListener('click', function () { search.value = ''; runSearch(); search.focus(); });

		var hashPanel = windowObject.location.hash ? documentObject.getElementById(windowObject.location.hash.slice(1)) : null;
		var initial = hashPanel && root.contains(hashPanel) ? hashPanel.getAttribute('data-siteintelix-settings-panel') : '';
		if (!initial) {
			try { initial = windowObject.sessionStorage.getItem('siteintelixActiveSettingsTab') || ''; } catch (error) {}
		}
		if (initial) { activate(initial, false, false); }

		initMaintenanceMediaPickers(documentObject, windowObject);
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			initSettings: initSettings,
			initMaintenanceMediaPickers: initMaintenanceMediaPickers
		};
	}
	if (typeof document !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () { initSettings(document, window); });
	}
}());
