(function () {
	'use strict';
	document.addEventListener('DOMContentLoaded', function () {
		var page = document.getElementById('siteintelix-modules-page');
		if (!page) { return; }
		var data = window.siteintelixModulesData || {};
		var cards = Array.prototype.slice.call(page.querySelectorAll('[data-siteintelix-module-card]'));
		var search = page.querySelector('[data-siteintelix-module-search]');
		search.addEventListener('input', function () {
			var term = search.value.trim().toLowerCase();
			cards.forEach(function (card) {
				card.hidden = !!term && (card.getAttribute('data-module-title') + ' ' + card.getAttribute('data-module-description')).indexOf(term) === -1;
			});
		});
		page.addEventListener('change', function (event) {
			var toggle = event.target.closest('[data-siteintelix-module-toggle]');
			if (!toggle) { return; }
			var previous = !toggle.checked;
			var body = new URLSearchParams();
			body.set('action', 'siteintelix_toggle_module');
			body.set('nonce', data.nonce || '');
			body.set('module', toggle.value);
			body.set('enabled', toggle.checked ? '1' : '0');
			toggle.disabled = true;
			window.fetch(data.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
				.then(function (response) { return response.json(); })
				.then(function (response) {
					if (!response || !response.success) { throw new Error(response && response.data && response.data.message || data.failed); }
					window.SiteIntelixAdmin.toast(response.data.message || data.updated);
					window.setTimeout(function () { window.location.reload(); }, 300);
				}).catch(function (error) {
					toggle.checked = previous;
					toggle.disabled = false;
					window.SiteIntelixAdmin.toast(error.message || data.failed, 'error');
				});
		});
	});
}());
