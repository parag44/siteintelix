(function () {
	'use strict';

	function confirmSwitch(form) {
		var config = window.siteintelixVersionSwitcher || {};
		var message = (config.confirmMessage || 'Switching replaces plugin files for the entire website.') + '\n\n' +
			(config.databaseWarning || 'Plugin database changes are not rolled back.');

		if ('production' === config.environment) {
			message += '\n\n' + (config.productionWarning || 'This is a production website.');
		}

		if (!window.confirm(message)) {
			return false;
		}

		var production = form.querySelector('input[name="production_confirm"]');
		if (production) {
			production.value = 'yes';
		}
		return true;
	}

	document.addEventListener('submit', function (event) {
		var form = event.target.closest('.siteintelix-version-switch-form');
		if (!form || 'true' === form.dataset.confirmed) {
			return;
		}
		event.preventDefault();
		if (confirmSwitch(form)) {
			form.dataset.confirmed = 'true';
			form.submit();
		}
	});

	document.addEventListener('click', function (event) {
		var action = event.target.closest('.siteintelix-version-toolbar-action > a, a.siteintelix-version-toolbar-action');
		if (!action) {
			return;
		}
		var hash = action.getAttribute('href') || '';
		if (0 !== hash.indexOf('#siteintelix-version-form-')) {
			return;
		}
		event.preventDefault();
		var form = document.getElementById(hash.substring(1));
		if (form && confirmSwitch(form)) {
			form.dataset.confirmed = 'true';
			form.submit();
		}
	});
}());
