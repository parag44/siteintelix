(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var page = document.getElementById('siteintelix-safe-mode-page');
		if (!page) { return; }
		function selected(nodes) { var value = ''; nodes.forEach(function (node) { if (node.checked) { value = node.value; } }); return value; }
		var pluginModes = page.querySelectorAll('input[name="siteintelix_plugin_mode"]');
		var themeModes = page.querySelectorAll('input[name="siteintelix_theme_mode"]');
		var picker = page.querySelector('[data-siteintelix-safe-plugin-picker]');
		var theme = page.querySelector('.sitx-safe-theme-select');
		function sync() { if (picker) { picker.classList.toggle('is-visible', 'only' === selected(pluginModes)); } if (theme) { theme.classList.toggle('is-visible', 'switch' === selected(themeModes)); } }
		pluginModes.forEach(function (input) { input.addEventListener('change', sync); });
		themeModes.forEach(function (input) { input.addEventListener('change', sync); });
		var search = page.querySelector('[data-siteintelix-safe-plugin-search]');
		if (search) { search.addEventListener('input', function () { var term = search.value.trim().toLowerCase(); page.querySelectorAll('.sitx-safe-plugin').forEach(function (row) { row.hidden = !!term && (row.getAttribute('data-plugin-name') || '').indexOf(term) === -1; }); }); }
		sync();
	});
}());
