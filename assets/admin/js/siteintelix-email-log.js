(function () {
	'use strict';

	var previewCache = Object.create(null);

	function initEmailLog(documentObject, windowObject) {
		var modal = documentObject.querySelector('[data-siteintelix-email-modal]');
		if (!modal) {
			return;
		}

		var config = windowObject.siteintelixEmailLogData || {};
		var title = modal.querySelector('#siteintelix-email-modal-title');
		var status = modal.querySelector('[data-siteintelix-email-modal-status]');
		var meta = modal.querySelector('[data-siteintelix-email-modal-meta]');
		var headers = modal.querySelector('[data-siteintelix-email-modal-headers]');
		var attachments = modal.querySelector('[data-siteintelix-email-modal-attachments]');
		var frame = modal.querySelector('[data-siteintelix-email-panel="html"]');
		var source = modal.querySelector('[data-siteintelix-email-panel="source"]');
		var retry = modal.querySelector('[data-siteintelix-email-preview-retry]');
		var activeId = 0;
		var invoker = null;

		function renderPreview(preview) {
			title.textContent = preview.subject || config.noSubject || '(No subject)';
			meta.textContent = [preview.status, preview.sentAt, preview.to, preview.error].filter(Boolean).join(' · ');
			headers.textContent = preview.headers || '—';
			attachments.textContent = preview.attachments || '—';
			frame.srcdoc = preview.message || '';
			source.textContent = preview.message || '';
			status.textContent = '';
			retry.hidden = true;
		}

		function requestPreview(id) {
			activeId = id;
			if (previewCache[id]) {
				renderPreview(previewCache[id]);
				return;
			}
			status.textContent = config.loading || 'Loading email preview…';
			retry.hidden = true;
			var body = new URLSearchParams();
			body.set('action', 'siteintelix_get_email_preview');
			body.set('nonce', config.previewNonce || '');
			body.set('log_id', String(id));
			windowObject.fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			}).then(function (response) {
				return response.json();
			}).then(function (response) {
				if (!response || !response.success || !response.data) {
					throw new Error('Preview unavailable');
				}
				previewCache[id] = response.data;
				renderPreview(response.data);
			}).catch(function () {
				status.textContent = config.loadFailed || 'Email preview could not be loaded.';
				retry.hidden = false;
			});
		}

		function open(button) {
			var id = Number(button.getAttribute('data-siteintelix-email-id'));
			if (!Number.isInteger(id) || id < 1) {
				return;
			}
			invoker = button;
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			modal.querySelector('[data-siteintelix-email-modal-close]').focus();
			requestPreview(id);
		}

		function close() {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			frame.srcdoc = '';
			source.textContent = '';
			if (invoker) {
				invoker.focus();
			}
		}

		function openActionDialog(options) {
			var overlay = documentObject.createElement('div');
			var panel = documentObject.createElement('div');
			var heading = documentObject.createElement('h2');
			var description = documentObject.createElement('p');
			var error = documentObject.createElement('p');
			var input = options.input ? documentObject.createElement('input') : null;
			var confirm = documentObject.createElement('button');
			var cancel = documentObject.createElement('button');
			var actions = documentObject.createElement('div');
			var previous = documentObject.activeElement;
			overlay.className = 'sitx-action-dialog';
			overlay.setAttribute('role', 'presentation');
			panel.className = 'sitx-action-dialog__panel';
			panel.setAttribute('role', options.destructive ? 'alertdialog' : 'dialog');
			panel.setAttribute('aria-modal', 'true');
			heading.textContent = options.title;
			description.textContent = options.message || '';
			error.setAttribute('role', 'alert');
			error.setAttribute('aria-live', 'assertive');
			confirm.type = 'button';
			confirm.className = 'si-button ' + (options.destructive ? 'si-button--danger' : 'si-button--primary');
			confirm.textContent = options.confirmLabel || config.confirm || 'Confirm';
			cancel.type = 'button';
			cancel.className = 'si-button si-button--secondary';
			cancel.textContent = config.cancel || 'Cancel';
			panel.appendChild(heading);
			panel.appendChild(description);
			if (input) {
				input.type = 'email';
				input.value = options.value || '';
				input.setAttribute('aria-label', options.message || options.title);
				panel.appendChild(input);
			}
			panel.appendChild(error);
			actions.className = 'sitx-action-dialog__actions';
			actions.appendChild(cancel);
			actions.appendChild(confirm);
			panel.appendChild(actions);
			overlay.appendChild(panel);
			documentObject.body.appendChild(overlay);

			function dismiss() {
				overlay.remove();
				if (previous && previous.focus) { previous.focus(); }
			}
			cancel.addEventListener('click', dismiss);
			overlay.addEventListener('click', function (event) { if (event.target === overlay) { dismiss(); } });
			overlay.addEventListener('keydown', function (event) {
				if ('Escape' === event.key) { dismiss(); }
				if ('Tab' === event.key) {
					var first = input || cancel;
					var last = confirm;
					if (event.shiftKey && documentObject.activeElement === first) { event.preventDefault(); last.focus(); }
					else if (!event.shiftKey && documentObject.activeElement === last) { event.preventDefault(); first.focus(); }
				}
			});
			confirm.addEventListener('click', function () {
				if (input && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value.trim())) {
					error.textContent = config.invalidEmail || 'Enter a valid email address.';
					input.focus();
					return;
				}
				options.onConfirm(input ? input.value.trim() : '');
				dismiss();
			});
			(input || cancel).focus();
		}

		documentObject.querySelectorAll('[data-siteintelix-email-id]').forEach(function (button) {
			button.addEventListener('click', function () { open(button); });
		});

		documentObject.addEventListener('click', function (event) {
			var testButton = event.target.closest('[data-siteintelix-send-test-email]');
			var confirmLink = event.target.closest('[data-siteintelix-confirm]');
			if (testButton) {
				var form = documentObject.querySelector('[data-siteintelix-test-email-form]');
				openActionDialog({
					title: config.sendTestTitle || 'Send test email',
					message: config.sendTestHelp || '',
					input: true,
					value: testButton.getAttribute('data-default-recipient') || '',
					confirmLabel: config.sendEmail || 'Send Email',
					onConfirm: function (recipient) {
						form.querySelector('input[name="recipient"]').value = recipient;
						form.submit();
					},
				});
			}
			if (confirmLink) {
				event.preventDefault();
				openActionDialog({
					title: confirmLink.textContent.trim(),
					message: confirmLink.getAttribute('data-siteintelix-confirm') || '',
					destructive: true,
					onConfirm: function () { windowObject.location.assign(confirmLink.href); },
				});
			}
		});

		var bulkForm = documentObject.querySelector('.sitx-email-log-bulk-form');
		var selectAll = documentObject.querySelector('[data-siteintelix-email-select-all]');
		var selectionStatus = documentObject.querySelector('[data-siteintelix-email-selection-status]');
		function updateSelection() {
			var count = bulkForm ? bulkForm.querySelectorAll('input[name="log_ids[]"]:checked').length : 0;
			selectionStatus.textContent = 1 === count ? config.selectionSingular : String(config.selectionPlural || '%d emails selected').replace('%d', count);
		}
		if (selectAll && bulkForm) {
			selectAll.addEventListener('change', function () {
				bulkForm.querySelectorAll('input[name="log_ids[]"]').forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
				updateSelection();
			});
			bulkForm.addEventListener('change', updateSelection);
			bulkForm.addEventListener('submit', function (event) {
				var action = bulkForm.querySelector('select[name="bulk_action"]');
				var count = bulkForm.querySelectorAll('input[name="log_ids[]"]:checked').length;
				var actionValue = action ? action.value : '';
				if (['delete', 'delete_all'].indexOf(actionValue) === -1) {
					event.preventDefault();
					windowObject.SiteIntelixAdmin.toast('Choose a bulk action first.', 'error');
					return;
				}
				if ('delete' === actionValue && !count) {
					event.preventDefault();
					windowObject.SiteIntelixAdmin.toast('Select at least one email log.', 'error');
					return;
				}
				if ('true' === bulkForm.getAttribute('data-confirmed')) {
					return;
				}
				event.preventDefault();
				openActionDialog({
					title: config.confirm || 'Confirm',
					message: 'delete_all' === actionValue ? (config.deleteAll || 'Delete all email logs? This cannot be undone.') : (config.deleteSelected || 'Delete the selected email logs?'),
					destructive: true,
					onConfirm: function () { bulkForm.setAttribute('data-confirmed', 'true'); bulkForm.submit(); },
				});
			});
			updateSelection();
		}
		modal.querySelectorAll('[data-siteintelix-email-modal-close]').forEach(function (button) {
			button.addEventListener('click', close);
		});
		retry.addEventListener('click', function () { requestPreview(activeId); });

		modal.querySelectorAll('[data-siteintelix-email-tab]').forEach(function (tab) {
			tab.addEventListener('click', function () {
				var selected = tab.getAttribute('data-siteintelix-email-tab');
				modal.querySelectorAll('[data-siteintelix-email-tab]').forEach(function (item) {
					var active = item === tab;
					item.classList.toggle('is-active', active);
					item.setAttribute('aria-selected', active ? 'true' : 'false');
					item.tabIndex = active ? 0 : -1;
				});
				modal.querySelectorAll('[data-siteintelix-email-panel]').forEach(function (panel) {
					var active = panel.getAttribute('data-siteintelix-email-panel') === selected;
					panel.hidden = !active;
					panel.classList.toggle('is-active', active);
				});
			});
		});

		modal.addEventListener('keydown', function (event) {
			if ('Escape' === event.key) {
				close();
			}
			if ('Tab' === event.key) {
				var focusable = Array.prototype.slice.call(modal.querySelectorAll('button:not([hidden]), iframe, [tabindex="0"]'));
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && documentObject.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && documentObject.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			}
		});
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { initEmailLog: initEmailLog, previewCache: previewCache };
	}
	if (typeof document !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () { initEmailLog(document, window); });
	}
}());
