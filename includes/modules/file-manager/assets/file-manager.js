(function (global) {
	'use strict';

	function createHistory(initialPath) {
		var current = String(initialPath || '');
		var back = [];
		var forward = [];

		return {
			visit: function (path) {
				path = String(path || '');
				if (path !== current) {
					back.push(current);
					current = path;
					forward = [];
				}
				return current;
			},
			back: function () {
				if (back.length) {
					forward.push(current);
					current = back.pop();
				}
				return current;
			},
			forward: function () {
				if (forward.length) {
					back.push(current);
					current = forward.pop();
				}
				return current;
			},
			current: function () {
				return current;
			},
			snapshot: function () {
				return { current: current, back: back.slice(), forward: forward.slice() };
			},
		};
	}

	function debounce(callback, delay) {
		var timer = null;
		return function () {
			var args = arguments;
			clearTimeout(timer);
			timer = setTimeout(function () {
				callback.apply(null, args);
			}, delay);
		};
	}

	function normalizeSort(value) {
		return ['name', 'type', 'size', 'modified'].indexOf(value) === -1 ? 'name' : value;
	}

	function normalizeOrder(value) {
		return value === 'desc' ? 'desc' : 'asc';
	}

	global.siteintelixFileManagerTest = {
		createHistory: createHistory,
		debounce: debounce,
		normalizeSort: normalizeSort,
		normalizeOrder: normalizeOrder,
	};

	if (typeof document === 'undefined') {
		return;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('[data-siteintelix-file-manager]');
		var data = global.siteintelixFileManager || {};
		if (!root || !data.ajaxUrl || !data.nonces) {
			return;
		}

		var fixedActions = [
			'list_directory',
			'get_file',
			'get_details',
			'save_file',
			'upload_files',
			'create_file',
			'create_directory',
			'rename_item',
			'trash_item',
			'list_trash',
			'restore_item',
			'permanently_delete_item',
			'list_backups',
			'restore_backup',
		];
		var state = {
			path: String(data.startPath || 'wp-content'),
			history: createHistory(String(data.startPath || 'wp-content')),
			page: 1,
			perPage: 50,
			sort: 'name',
			order: 'asc',
			search: '',
			selected: null,
			editorDirty: false,
			editorFile: null,
			editorInstance: null,
			restoreFocus: null,
			pendingNavigation: null,
		};

		function select(selector, scope) {
			return (scope || root).querySelector(selector);
		}

		function selectAll(selector, scope) {
			return Array.prototype.slice.call((scope || root).querySelectorAll(selector));
		}

		function clear(element) {
			if (element) {
				element.textContent = '';
			}
		}

		function node(tag, className, textValue) {
			var element = document.createElement(tag);
			if (className) {
				element.className = className;
			}
			if (textValue !== undefined) {
				element.textContent = String(textValue);
			}
			return element;
		}

		function button(label, callback, className) {
			var element = node('button', className || 'si-button si-button--secondary', label);
			element.type = 'button';
			element.addEventListener('click', callback);
			return element;
		}

		function speak(message) {
			var announcer = select('[data-fm-announcer]');
			if (announcer) {
				announcer.textContent = '';
				global.setTimeout(function () {
					announcer.textContent = String(message || '');
				}, 20);
			}
			if (global.wp && global.wp.a11y && typeof global.wp.a11y.speak === 'function') {
				global.wp.a11y.speak(String(message || ''));
			}
		}

		function errorMessage(error) {
			return error && error.message ? error.message : (data.i18n && data.i18n.operationFailed) || 'The operation could not be completed.';
		}

		function request(action, payload) {
			if (fixedActions.indexOf(action) === -1 || !data.nonces[action]) {
				return Promise.reject(new Error('Unsupported File Manager operation.'));
			}
			var form = payload instanceof FormData ? payload : new FormData();
			if (!(payload instanceof FormData)) {
				Object.keys(payload || {}).forEach(function (key) {
					form.set(key, String(payload[key]));
				});
			}
			form.set('action', 'siteintelix_fm_' + action);
			form.set('nonce', data.nonces[action]);
			return global.fetch(data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: form,
			}).then(function (response) {
				return response.json().catch(function () {
					throw new Error((data.i18n && data.i18n.operationFailed) || 'Invalid server response.');
				}).then(function (json) {
					if (!response.ok || !json || json.success !== true) {
						var failure = json && json.data ? json.data : {};
						var message = failure.message || (data.i18n && data.i18n.operationFailed) || 'The operation could not be completed.';
						var error = new Error(message);
						error.code = failure.code || 'request_failed';
						throw error;
					}
					return json.data;
				});
			});
		}

		function formatDate(value) {
			var date = typeof value === 'number' ? new Date(value * 1000) : new Date(value);
			return Number.isNaN(date.getTime()) ? '—' : date.toLocaleString();
		}

		function formatBytes(value) {
			var bytes = Number(value || 0);
			var units = ['B', 'KB', 'MB', 'GB'];
			var index = 0;
			while (bytes >= 1024 && index < units.length - 1) {
				bytes /= 1024;
				index += 1;
			}
			return bytes.toFixed(index ? 1 : 0) + ' ' + units[index];
		}

		function downloadUrl(path) {
			var params = new URLSearchParams({
				action: 'siteintelix_fm_download_file',
				path: path,
				_wpnonce: data.downloadNonce || '',
			});
			return String(data.downloadUrl || '') + '?' + params.toString();
		}

		function previewUrl(path) {
			var params = new URLSearchParams({
				action: 'siteintelix_fm_preview_image',
				path: path,
				_wpnonce: data.previewNonce || '',
			});
			return String(data.downloadUrl || '') + '?' + params.toString();
		}

		function updateHistoryButtons() {
			var snapshot = state.history.snapshot();
			var backButton = select('[data-fm-back]');
			var forwardButton = select('[data-fm-forward]');
			if (backButton) {
				backButton.disabled = snapshot.back.length === 0;
			}
			if (forwardButton) {
				forwardButton.disabled = snapshot.forward.length === 0;
			}
		}

		function setBrowserStatus(message, isError) {
			var status = select('[data-fm-state]');
			var table = select('[data-fm-table]');
			if (status) {
				status.hidden = false;
				status.textContent = String(message || '');
				status.classList.toggle('is-error', Boolean(isError));
			}
			if (table) {
				table.hidden = true;
			}
		}

		function guardDirty(continuation) {
			if (!state.editorDirty) {
				continuation();
				return;
			}
			state.pendingNavigation = continuation;
			openModal({
				title: 'Discard unsaved changes?',
				description: (data.i18n && data.i18n.unsavedChanges) || 'You have unsaved changes. Discard them?',
				confirmLabel: 'Discard changes',
				onConfirm: function () {
					state.editorDirty = false;
					closeEditor();
					var pending = state.pendingNavigation;
					state.pendingNavigation = null;
					if (pending) {
						pending();
					}
				},
			});
		}

		function visit(path, addHistory) {
			guardDirty(function () {
				state.path = String(path || '');
				state.page = 1;
				state.selected = null;
				if (addHistory !== false) {
					state.history.visit(state.path);
				}
				loadDirectory();
			});
		}

		function renderBreadcrumbs(breadcrumbs) {
			var container = select('[data-fm-breadcrumbs]');
			if (!container) {
				return;
			}
			clear(container);
			(breadcrumbs || []).slice(0, 30).forEach(function (crumb, index) {
				if (index) {
					container.appendChild(node('span', 'sitx-fm-breadcrumbs__separator', '/'));
				}
				container.appendChild(button(crumb.label || '/', function () {
					visit(crumb.path || '', true);
				}, 'sitx-fm-breadcrumbs__item'));
			});
		}

		function renderTree(breadcrumbs) {
			var tree = select('[data-fm-tree]');
			if (!tree) {
				return;
			}
			clear(tree);
			(breadcrumbs || []).slice(0, 30).forEach(function (crumb) {
				var item = button(crumb.label || '/', function () {
					visit(crumb.path || '', true);
				}, 'sitx-fm-tree__item');
				item.setAttribute('role', 'treeitem');
				item.setAttribute('aria-current', crumb.path === state.path ? 'true' : 'false');
				tree.appendChild(item);
			});
		}

		function actionButton(action, item) {
			var labels = {
				open: 'Open',
				view: 'Preview',
				details: 'Details',
				edit: 'Edit',
				download: 'Download',
				rename: 'Rename',
				trash: 'Trash',
			};
			if (action === 'download') {
				var link = node('a', 'si-button si-button--secondary', labels[action]);
				link.href = downloadUrl(item.path);
				return link;
			}
			return button(labels[action] || action, function (event) {
				event.stopPropagation();
				handleItemAction(action, item, event.currentTarget);
			});
		}

		function renderItems(result) {
			var status = select('[data-fm-state]');
			var table = select('[data-fm-table]');
			var body = table ? table.querySelector('tbody') : null;
			var pagination = select('[data-fm-pagination]');
			if (!table || !body || !status) {
				return;
			}
			clear(body);
			if (!result.items || result.items.length === 0) {
				setBrowserStatus((data.i18n && data.i18n.empty) || 'This directory is empty.', false);
			} else {
				status.hidden = true;
				table.hidden = false;
				result.items.forEach(function (item) {
					var row = node('tr');
					row.tabIndex = 0;
					row.addEventListener('click', function () {
						selectItem(item, row);
					});
					row.addEventListener('keydown', function (event) {
						if (event.key === 'Enter') {
							selectItem(item, row);
						}
					});
					var nameCell = node('td');
					var nameButton = button(item.name, function (event) {
						event.stopPropagation();
						if (item.type === 'directory') {
							visit(item.path, true);
						} else {
							showDetails(item);
						}
					}, 'sitx-fm-name');
					nameCell.appendChild(nameButton);
					row.appendChild(nameCell);
					row.appendChild(node('td', '', item.type === 'directory' ? 'Folder' : (item.extension || 'File')));
					row.appendChild(node('td', '', item.size_label || '—'));
					row.appendChild(node('td', '', formatDate(item.modified)));
					row.appendChild(node('td', '', item.permissions || '—'));
					row.appendChild(node('td', '', item.writable ? 'Yes' : 'No'));
					var actionsCell = node('td', 'sitx-fm-row-actions');
					(item.actions || []).forEach(function (action) {
						actionsCell.appendChild(actionButton(action, item));
					});
					row.appendChild(actionsCell);
					body.appendChild(row);
				});
			}
			if (pagination) {
				pagination.hidden = Number(result.total_pages || 1) <= 1;
				var label = select('[data-fm-page-label]');
				if (label) {
					label.textContent = 'Page ' + result.page + ' of ' + result.total_pages;
				}
				var previous = select('[data-fm-prev]');
				var next = select('[data-fm-next]');
				if (previous) {
					previous.disabled = result.page <= 1;
				}
				if (next) {
					next.disabled = result.page >= result.total_pages;
				}
			}
		}

		function loadDirectory() {
			setBrowserStatus((data.i18n && data.i18n.loading) || 'Loading files…', false);
			request('list_directory', {
				path: state.path,
				page: state.page,
				per_page: state.perPage,
				sort: normalizeSort(state.sort),
				order: normalizeOrder(state.order),
				search: state.search,
			}).then(function (result) {
				state.path = String(result.path || '');
				renderBreadcrumbs(result.breadcrumbs);
				renderTree(result.breadcrumbs);
				renderItems(result);
				updateHistoryButtons();
			}).catch(function (error) {
				setBrowserStatus(errorMessage(error), true);
				speak(errorMessage(error));
			});
		}

		function selectItem(item, row) {
			state.selected = item;
			selectAll('[data-fm-table] tbody tr').forEach(function (entry) {
				entry.classList.toggle('is-selected', entry === row);
			});
			showDetails(item);
		}

		function addMetadata(list, label, value) {
			list.appendChild(node('dt', '', label));
			list.appendChild(node('dd', '', value === null || value === undefined || value === '' ? '—' : value));
		}

		function showDetails(item) {
			var empty = select('[data-fm-details-empty]');
			var content = select('[data-fm-details-content]');
			var name = select('[data-fm-details-name]');
			var preview = select('[data-fm-preview]');
			var imagePreview = select('[data-fm-image-preview]');
			var metadata = select('[data-fm-metadata]');
			var actions = select('[data-fm-details-actions]');
			if (!content || !metadata || !actions) {
				return;
			}
			if (empty) {
				empty.hidden = true;
			}
			content.hidden = false;
			name.textContent = item.name || '';
			preview.textContent = 'Loading preview…';
			preview.hidden = false;
			imagePreview.hidden = true;
			clear(imagePreview);
			clear(metadata);
			clear(actions);
			request('get_details', { path: item.path }).then(function (details) {
				addMetadata(metadata, 'Path', details.path);
				addMetadata(metadata, 'Type', details.type);
				addMetadata(metadata, 'Size', details.size === null ? '—' : formatBytes(details.size));
				addMetadata(metadata, 'MIME', details.mime);
				addMetadata(metadata, 'Modified', formatDate(details.modified));
				addMetadata(metadata, 'Permissions', details.permissions);
				addMetadata(metadata, 'Writable', details.writable ? 'Yes' : 'No');
			}).catch(function (error) {
				addMetadata(metadata, 'Error', errorMessage(error));
			});
			if (item.type === 'file') {
				request('get_file', { path: item.path }).then(function (result) {
					if (!result.previewable) {
						preview.textContent = 'Preview unavailable: ' + String(result.reason || 'unsupported') + '.';
					} else if (result.kind === 'text') {
						preview.textContent = result.content || '';
					} else {
						preview.hidden = true;
						imagePreview.hidden = false;
						var image = document.createElement('img');
						image.src = previewUrl(item.path);
						image.alt = item.name || 'Image preview';
						image.loading = 'lazy';
						imagePreview.appendChild(image);
					}
				}).catch(function (error) {
					preview.textContent = errorMessage(error);
				});
			} else {
				preview.textContent = 'Folder';
			}
			(item.actions || []).forEach(function (action) {
				if (action !== 'details' && action !== 'open' && action !== 'view') {
					actions.appendChild(actionButton(action, item));
				}
			});
		}

		function handleItemAction(action, item, trigger) {
			if (action === 'open') {
				visit(item.path, true);
			} else if (action === 'view' || action === 'details') {
				showDetails(item);
			} else if (action === 'edit') {
				openEditor(item.path);
			} else if (action === 'rename') {
				openRename(item, trigger);
			} else if (action === 'trash') {
				openTrash(item, trigger);
			}
		}

		function editorValue() {
			return state.editorInstance && typeof state.editorInstance.getValue === 'function'
				? state.editorInstance.getValue()
				: select('[data-fm-editor-textarea]').value;
		}

		function openEditor(path) {
			request('get_file', { path: path, edit: 1 }).then(function (file) {
				var panel = select('[data-fm-editor]');
				var textarea = select('[data-fm-editor-textarea]');
				state.editorFile = file;
				state.editorDirty = false;
				select('[data-fm-editor-path]').textContent = file.path;
				textarea.value = file.content;
				panel.hidden = false;
				if (!state.editorInstance && global.wp && global.wp.codeEditor && data.editorSettings) {
					var initialized = global.wp.codeEditor.initialize(textarea, data.editorSettings);
					state.editorInstance = initialized.codemirror;
					state.editorInstance.on('change', function () {
						state.editorDirty = true;
					});
				} else if (state.editorInstance) {
					state.editorInstance.setValue(file.content);
				}
				textarea.addEventListener('input', function () {
					state.editorDirty = true;
				}, { once: true });
				state.editorDirty = false;
				(state.editorInstance || textarea).focus();
			}).catch(function (error) {
				speak(errorMessage(error));
			});
		}

		function saveEditor() {
			if (!state.editorFile) {
				return;
			}
			request('save_file', {
				path: state.editorFile.path,
				content: editorValue(),
				modified: state.editorFile.modified,
				sha256: state.editorFile.sha256,
			}).then(function (result) {
				state.editorFile.modified = result.modified;
				state.editorFile.sha256 = result.sha256;
				state.editorDirty = false;
				speak((data.i18n && data.i18n.saved) || 'File saved.');
				loadDirectory();
			}).catch(function (error) {
				speak(errorMessage(error));
			});
		}

		function closeEditor() {
			var panel = select('[data-fm-editor]');
			if (panel) {
				panel.hidden = true;
			}
			state.editorFile = null;
			state.editorDirty = false;
		}

		function modalElements() {
			var modal = select('[data-fm-modal]');
			return {
				modal: modal,
				title: select('[data-fm-modal-title]', modal),
				description: select('[data-fm-modal-description]', modal),
				fields: select('[data-fm-modal-fields]', modal),
				cancel: select('[data-fm-modal-cancel]', modal),
				confirm: select('[data-fm-modal-confirm]', modal),
			};
		}

		function closeModal() {
			var parts = modalElements();
			parts.modal.hidden = true;
			parts.modal.removeEventListener('keydown', trapModalKeys);
			if (state.restoreFocus && typeof state.restoreFocus.focus === 'function') {
				state.restoreFocus.focus();
			}
			state.restoreFocus = null;
			parts.confirm.onclick = null;
		}

		function trapModalKeys(event) {
			var parts = modalElements();
			if (event.key === 'Escape') {
				event.preventDefault();
				closeModal();
				return;
			}
			if (event.key !== 'Tab') {
				return;
			}
			var focusable = selectAll('button, input, select, textarea, a[href]', parts.modal).filter(function (element) {
				return !element.disabled && !element.hidden;
			});
			if (!focusable.length) {
				return;
			}
			var first = focusable[0];
			var last = focusable[focusable.length - 1];
			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		}

		function openModal(options) {
			var parts = modalElements();
			state.restoreFocus = options.trigger || document.activeElement;
			parts.title.textContent = options.title || 'Confirm action';
			parts.description.textContent = options.description || '';
			parts.confirm.textContent = options.confirmLabel || 'Confirm';
			clear(parts.fields);
			(options.fields || []).forEach(function (field) {
				var label = node('label', 'sitx-fm-modal__field');
				label.appendChild(node('span', '', field.label));
				var input = document.createElement(field.type === 'select' ? 'select' : 'input');
				input.name = field.name;
				if (field.type !== 'select') {
					input.type = field.type || 'text';
					input.value = field.value || '';
				} else {
					(field.options || []).forEach(function (option) {
						var optionNode = node('option', '', option.label);
						optionNode.value = option.value;
						input.appendChild(optionNode);
					});
				}
				input.required = field.required !== false;
				label.appendChild(input);
				parts.fields.appendChild(label);
			});
			parts.modal.hidden = false;
			parts.modal.addEventListener('keydown', trapModalKeys);
			parts.cancel.onclick = closeModal;
			parts.confirm.onclick = function () {
				var values = {};
				var valid = true;
				selectAll('input, select, textarea', parts.fields).forEach(function (input) {
					if (!input.checkValidity()) {
						valid = false;
						input.reportValidity();
					}
					values[input.name] = input.value;
				});
				if (!valid) {
					return;
				}
				parts.confirm.disabled = true;
				Promise.resolve().then(function () {
					return options.onConfirm ? options.onConfirm(values) : null;
				}).then(function () {
					parts.confirm.disabled = false;
					closeModal();
				}).catch(function (error) {
					parts.confirm.disabled = false;
					parts.description.textContent = errorMessage(error);
					speak(errorMessage(error));
				});
			};
			var first = select('input, select, textarea, button', parts.fields) || parts.confirm;
			first.focus();
		}

		function openCreate(trigger) {
			openModal({
				title: 'Create an item',
				description: 'Create a permitted text file or folder in the current directory.',
				trigger: trigger,
				confirmLabel: 'Create',
				fields: [
					{ name: 'kind', label: 'Item type', type: 'select', options: [{ value: 'file', label: 'File' }, { value: 'directory', label: 'Folder' }] },
					{ name: 'name', label: 'Name', type: 'text' },
				],
				onConfirm: function (values) {
					var action = values.kind === 'directory' ? 'create_directory' : 'create_file';
					return request(action, { parent: state.path, name: values.name }).then(function () {
						speak('Item created.');
						loadDirectory();
					});
				},
			});
		}

		function openRename(item, trigger) {
			openModal({
				title: 'Rename item',
				description: 'Renaming stays inside the current folder and never overwrites another item.',
				trigger: trigger,
				confirmLabel: 'Rename',
				fields: [{ name: 'name', label: 'New name', type: 'text', value: item.name }],
				onConfirm: function (values) {
					return request('rename_item', { path: item.path, name: values.name }).then(function () {
						speak('Item renamed.');
						loadDirectory();
					});
				},
			});
		}

		function openTrash(item, trigger) {
			openModal({
				title: 'Move to trash?',
				description: 'The item will be moved to private File Manager trash and can be restored later.',
				trigger: trigger,
				confirmLabel: 'Move to trash',
				onConfirm: function () {
					return request('trash_item', { path: item.path, confirmed_non_empty: 1 }).then(function () {
						speak('Item moved to trash.');
						loadDirectory();
					});
				},
			});
		}

		function uploadOne(file, destination, onProgress) {
			return new Promise(function (resolve, reject) {
				var form = new FormData();
				form.set('action', 'siteintelix_fm_upload_files');
				form.set('nonce', data.nonces.upload_files);
				form.set('destination', destination);
				form.append('files[]', file, file.name);
				var xhr = new XMLHttpRequest();
				xhr.open('POST', data.ajaxUrl);
				xhr.withCredentials = true;
				xhr.upload.addEventListener('progress', function (event) {
					if (event.lengthComputable) {
						onProgress(Math.round((event.loaded / event.total) * 100));
					}
				});
				xhr.addEventListener('load', function () {
					try {
						var response = JSON.parse(xhr.responseText);
						if (xhr.status < 200 || xhr.status >= 300 || !response.success) {
							reject(new Error(response.data && response.data.message ? response.data.message : 'Upload failed.'));
							return;
						}
						if (response.data.errors && response.data.errors.length) {
							reject(new Error(response.data.errors[0].message || 'Upload failed.'));
							return;
						}
						resolve(response.data);
					} catch (error) {
						reject(new Error('Upload returned an invalid response.'));
					}
				});
				xhr.addEventListener('error', function () {
					reject(new Error('Upload failed.'));
				});
				xhr.send(form);
			});
		}

		function uploadFiles(files) {
			var list = Array.prototype.slice.call(files || []);
			var chain = Promise.resolve();
			var failures = [];
			list.forEach(function (file) {
				chain = chain.then(function () {
					return uploadOne(file, state.path, function (progress) {
						speak('Uploading ' + file.name + ': ' + progress + '%');
					}).catch(function (error) {
						failures.push(file.name + ': ' + errorMessage(error));
					});
				});
			});
			chain.then(function () {
				speak(failures.length ? failures.join(' ') : ((data.i18n && data.i18n.uploaded) || 'Upload complete.'));
				loadDirectory();
			});
		}

		function renderUtility(type, result) {
			var table = select('[data-fm-' + type + '-table]');
			var status = select('[data-fm-' + type + '-state]');
			var body = table ? table.querySelector('tbody') : null;
			var items = result.items || [];
			if (!table || !body || !status) {
				return;
			}
			clear(body);
			status.hidden = items.length > 0;
			status.textContent = items.length ? '' : (type === 'trash' ? 'Trash is empty.' : 'No backups are available.');
			table.hidden = items.length === 0;
			items.forEach(function (item) {
				var row = node('tr');
				row.appendChild(node('td', '', item.original_path || '—'));
				row.appendChild(node('td', '', formatDate(item.created_at)));
				row.appendChild(node('td', '', type === 'trash' ? (item.type || '—') : formatBytes(item.size)));
				var actions = node('td', 'sitx-fm-row-actions');
				if (type === 'backups') {
					actions.appendChild(button('Restore', function (event) {
						openModal({
							title: 'Restore backup?',
							description: 'The current file will be backed up before this version is restored.',
							trigger: event.currentTarget,
							confirmLabel: 'Restore',
							onConfirm: function () {
								return request('restore_backup', { id: item.id }).then(function () {
									speak('Backup restored.');
									loadUtility('backups');
								});
							},
						});
					}));
				} else {
					actions.appendChild(button('Restore', function (event) {
						openModal({
							title: 'Restore item?',
							description: 'The item will return to its original path if that path is available.',
							trigger: event.currentTarget,
							confirmLabel: 'Restore',
							onConfirm: function () {
								return request('restore_item', { id: item.id }).then(function () {
									speak('Trash item restored.');
									loadUtility('trash');
								});
							},
						});
					}));
					actions.appendChild(button('Delete permanently', function (event) {
						var expectedName = String(item.original_path || '').split('/').pop();
						openModal({
							title: 'Permanently delete item?',
							description: (data.i18n && data.i18n.confirmPermanent) || 'Type the item name to permanently delete it.',
							trigger: event.currentTarget,
							confirmLabel: 'Delete permanently',
							fields: [{ name: 'confirmation', label: 'Type “' + expectedName + '”', type: 'text' }],
							onConfirm: function (values) {
								if (values.confirmation !== expectedName) {
									throw new Error('The item name did not match.');
								}
								return request('permanently_delete_item', { id: item.id }).then(function () {
									speak('Trash item permanently deleted.');
									loadUtility('trash');
								});
							},
						});
					}, 'si-button si-button--danger'));
				}
				row.appendChild(actions);
				body.appendChild(row);
			});
		}

		function loadUtility(type) {
			var action = type === 'trash' ? 'list_trash' : 'list_backups';
			var status = select('[data-fm-' + type + '-state]');
			if (status) {
				status.hidden = false;
				status.textContent = 'Loading…';
			}
			request(action, { limit: 100 }).then(function (result) {
				renderUtility(type, result);
			}).catch(function (error) {
				if (status) {
					status.textContent = errorMessage(error);
					status.classList.add('is-error');
				}
			});
		}

		function bindBrowser() {
			select('[data-fm-back]').addEventListener('click', function () {
				guardDirty(function () {
					state.path = state.history.back();
					loadDirectory();
				});
			});
			select('[data-fm-forward]').addEventListener('click', function () {
				guardDirty(function () {
					state.path = state.history.forward();
					loadDirectory();
				});
			});
			select('[data-fm-up]').addEventListener('click', function () {
				var segments = state.path.split('/').filter(Boolean);
				segments.pop();
				visit(segments.join('/'), true);
			});
			select('[data-fm-refresh]').addEventListener('click', loadDirectory);
			select('[data-fm-new]').addEventListener('click', function (event) {
				openCreate(event.currentTarget);
			});
			var uploadInput = select('[data-fm-upload-input]');
			select('[data-fm-upload]').addEventListener('click', function () {
				uploadInput.click();
			});
			uploadInput.addEventListener('change', function () {
				uploadFiles(uploadInput.files);
				uploadInput.value = '';
			});
			select('[data-fm-prev]').addEventListener('click', function () {
				state.page = Math.max(1, state.page - 1);
				loadDirectory();
			});
			select('[data-fm-next]').addEventListener('click', function () {
				state.page += 1;
				loadDirectory();
			});
			selectAll('[data-fm-sort]').forEach(function (sortButton) {
				sortButton.addEventListener('click', function () {
					var nextSort = normalizeSort(sortButton.getAttribute('data-fm-sort'));
					state.order = state.sort === nextSort && state.order === 'asc' ? 'desc' : 'asc';
					state.sort = nextSort;
					state.page = 1;
					loadDirectory();
				});
			});
			select('[data-fm-search]').addEventListener('input', debounce(function (event) {
				state.search = event.target.value;
				state.page = 1;
				loadDirectory();
			}, 250));
			select('[data-fm-toggle-tree]').addEventListener('click', function (event) {
				var panel = select('[data-fm-tree-panel]');
				panel.classList.toggle('is-open');
				event.currentTarget.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
			});
			select('[data-fm-toggle-details]').addEventListener('click', function (event) {
				var panel = select('[data-fm-details-panel]');
				panel.classList.toggle('is-open');
				event.currentTarget.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
			});
			select('[data-fm-close-tree]').addEventListener('click', function () {
				select('[data-fm-tree-panel]').classList.remove('is-open');
			});
			select('[data-fm-close-details]').addEventListener('click', function () {
				select('[data-fm-details-panel]').classList.remove('is-open');
			});
			select('[data-fm-editor-save]').addEventListener('click', saveEditor);
			select('[data-fm-editor-cancel]').addEventListener('click', function () {
				guardDirty(closeEditor);
			});
			select('[data-fm-editor-fullscreen]').addEventListener('click', function () {
				select('[data-fm-editor]').classList.toggle('is-fullscreen');
			});
			document.addEventListener('keydown', function (event) {
				if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's' && !select('[data-fm-editor]').hidden) {
					event.preventDefault();
					saveEditor();
				}
			});
			global.addEventListener('beforeunload', function (event) {
				if (state.editorDirty) {
					event.preventDefault();
					event.returnValue = '';
				}
			});
			loadDirectory();
		}

		var activeTab = root.getAttribute('data-active-tab') || 'browser';
		if (activeTab === 'browser' && select('[data-fm-table]')) {
			bindBrowser();
		} else if (activeTab === 'backups') {
			select('[data-fm-refresh-backups]').addEventListener('click', function () {
				loadUtility('backups');
			});
			loadUtility('backups');
		} else if (activeTab === 'trash') {
			select('[data-fm-refresh-trash]').addEventListener('click', function () {
				loadUtility('trash');
			});
			loadUtility('trash');
		}
	});
}(typeof globalThis !== 'undefined' ? globalThis : this));
