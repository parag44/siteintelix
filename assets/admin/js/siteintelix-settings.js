(function () {
	'use strict';

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

		var picker = documentObject.querySelector('[data-siteintelix-maintenance-logo-picker]');
		if (picker && windowObject.wp && windowObject.wp.media) {
			var select = picker.querySelector('[data-siteintelix-maintenance-logo-select]');
			var remove = picker.querySelector('[data-siteintelix-maintenance-logo-remove]');
			var preview = picker.querySelector('[data-siteintelix-maintenance-logo-preview]');
			var idInput = picker.querySelector('[data-siteintelix-maintenance-logo-id]');
			var urlInput = picker.querySelector('[data-siteintelix-maintenance-logo-url]');
			var mediaFrame;
			function setPreview(url) {
				preview.textContent = '';
				if (url) { var image = documentObject.createElement('img'); image.src = url; image.alt = ''; preview.appendChild(image); }
				picker.classList.toggle('has-image', !!url);
			}
			select.addEventListener('click', function () {
				if (!mediaFrame) {
					var labels = windowObject.siteintelixSettingsData || {};
					mediaFrame = windowObject.wp.media({ title: labels.chooseLogo || 'Choose Logo', button: { text: labels.useLogo || 'Use this logo' }, multiple: false });
					mediaFrame.on('select', function () { var item = mediaFrame.state().get('selection').first().toJSON(); idInput.value = item.id || ''; urlInput.value = item.url || ''; setPreview(item.url || ''); });
				}
				mediaFrame.open();
			});
			remove.addEventListener('click', function () { idInput.value = ''; urlInput.value = ''; setPreview(''); });
			urlInput.addEventListener('input', function () { setPreview(urlInput.value.trim()); });
		}
	}

	if (typeof module !== 'undefined' && module.exports) { module.exports = { initSettings: initSettings }; }
	if (typeof document !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () { initSettings(document, window); });
	}
}());
