(function () {
	'use strict';
	function toast(message, type) {
		var node = document.createElement('div');
		node.className = 'siteintelix-toast siteintelix-toast--' + (type || 'success');
		node.setAttribute('role', 'error' === type ? 'alert' : 'status');
		node.setAttribute('aria-live', 'error' === type ? 'assertive' : 'polite');
		node.textContent = message;
		document.body.appendChild(node);
		requestAnimationFrame(function () { node.classList.add('siteintelix-toast--show'); });
		window.setTimeout(function () { node.remove(); }, 3500);
	}
	window.SiteIntelixAdmin = window.SiteIntelixAdmin || {};
	window.SiteIntelixAdmin.toast = toast;
	function confirmAction(element) {
		var overlay = document.createElement('div');
		var panel = document.createElement('div');
		var title = document.createElement('h2');
		var message = document.createElement('p');
		var cancel = document.createElement('button');
		var confirm = document.createElement('button');
		var previous = document.activeElement;
		overlay.className = 'sitx-core-dialog';
		panel.className = 'sitx-core-dialog__panel';
		panel.setAttribute('role', 'alertdialog');
		panel.setAttribute('aria-modal', 'true');
		title.textContent = 'Confirm action';
		message.textContent = element.getAttribute('data-siteintelix-confirm') || 'Are you sure?';
		cancel.type = 'button'; cancel.className = 'si-button si-button--secondary'; cancel.textContent = 'Cancel';
		confirm.type = 'button'; confirm.className = 'si-button si-button--danger'; confirm.textContent = 'Confirm';
		panel.appendChild(title); panel.appendChild(message); panel.appendChild(cancel); panel.appendChild(confirm); overlay.appendChild(panel); document.body.appendChild(overlay);
		function close() { overlay.remove(); if (previous && previous.focus) { previous.focus(); } }
		cancel.addEventListener('click', close);
		confirm.addEventListener('click', function () {
			close();
			if (element.matches('a[href]')) {
				window.location.assign(element.href);
			} else if (element.form) {
				if ('function' === typeof element.form.requestSubmit) {
					element.form.requestSubmit(element);
				} else {
					element.form.submit();
				}
			}
		});
		overlay.addEventListener('click', function (event) { if (event.target === overlay) { close(); } });
		overlay.addEventListener('keydown', function (event) {
			if ('Escape' === event.key) { close(); }
			if ('Tab' === event.key && event.shiftKey && document.activeElement === cancel) { event.preventDefault(); confirm.focus(); }
			else if ('Tab' === event.key && !event.shiftKey && document.activeElement === confirm) { event.preventDefault(); cancel.focus(); }
		});
		cancel.focus();
	}
	document.addEventListener('DOMContentLoaded', function () {
		var slot = document.getElementById('siteintelix-notices-slot');
		var root = document.querySelector('.siteintelix-wrap');
		if (slot && root) {
			document.querySelectorAll('#wpbody-content > .notice, #wpbody-content > .update-nag').forEach(function (notice) {
				slot.appendChild(notice);
			});
		}
		document.addEventListener('click', function (event) {
			var element = event.target.closest('[data-siteintelix-confirm]');
			if (!element || element.closest('[data-siteintelix-email-modal]') || document.getElementById('siteintelix-email-log-page')) { return; }
			event.preventDefault();
			confirmAction(element);
		});
	});
}());
