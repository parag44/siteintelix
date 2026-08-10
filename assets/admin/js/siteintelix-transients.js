(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var page = document.getElementById('siteintelix-transients-manager-page');
		var config = window.siteintelixTransients || {};
		if (!page) {
			return;
		}

		var all = page.querySelector('[data-siteintelix-transients-select-all]');
		if (all) {
			all.addEventListener('change', function () {
				page.querySelectorAll('input[name="transients[]"]').forEach(function (box) {
					box.checked = all.checked;
				});
			});
		}

		var modal = page.querySelector('[data-siteintelix-transient-modal]');
		if (!modal) {
			return;
		}
		var surface = modal.querySelector('.sitx-transient-modal__surface');
		var closeButton = modal.querySelector('[data-transient-modal-close]');
		var status = modal.querySelector('[data-transient-modal-status]');
		var content = modal.querySelector('[data-transient-modal-content]');
		var restoreFocus = null;

		function setText(selector, value) {
			var element = modal.querySelector(selector);
			if (element) {
				element.textContent = value || '';
			}
		}

		function closeModal() {
			modal.hidden = true;
			document.body.classList.remove('sitx-modal-open');
			if (restoreFocus) {
				restoreFocus.focus();
			}
		}

		function openModal(button) {
			restoreFocus = button;
			modal.hidden = false;
			document.body.classList.add('sitx-modal-open');
			content.hidden = true;
			status.hidden = false;
			status.textContent = 'Loading transient data…';
			setText('[data-transient-modal-name]', button.dataset.name);
			closeButton.focus();

			var form = new FormData();
			form.set('action', 'siteintelix_tm_preview');
			form.set('nonce', config.nonce || '');
			form.set('type', button.dataset.type || '');
			form.set('name', button.dataset.name || '');
			fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
				.then(function (response) { return response.json(); })
				.then(function (response) {
					if (!response.success) {
						throw new Error(response.data && response.data.message ? response.data.message : config.loadFailed);
					}
					setText('[data-transient-modal-name]', response.data.name);
					setText('[data-transient-modal-type]', response.data.type);
					setText('[data-transient-modal-expiration]', response.data.expiration);
					setText('[data-transient-modal-size]', response.data.size);
					setText('[data-transient-modal-formatted]', response.data.formatted);
					setText('[data-transient-modal-raw]', response.data.raw);
					modal.querySelector('[data-transient-modal-truncated]').hidden = !response.data.truncated;
					status.hidden = true;
					content.hidden = false;
				})
				.catch(function (error) {
					status.textContent = error.message || config.loadFailed || 'The transient data could not be loaded.';
				});
		}

		page.addEventListener('click', function (event) {
			var button = event.target.closest('[data-siteintelix-transient-view]');
			if (button) {
				openModal(button);
			}
		});
		closeButton.addEventListener('click', closeModal);
		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeModal();
			}
		});
		modal.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeModal();
			} else if (event.key === 'Tab') {
				var focusable = surface.querySelectorAll('button, a[href], details > summary');
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault(); last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault(); first.focus();
				}
			}
		});
	});
}());
