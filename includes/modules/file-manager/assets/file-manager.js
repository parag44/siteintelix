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

	var fileCategories = {
		php: ['php', 'phtml'],
		javascript: ['js', 'mjs', 'json'],
		css: ['css', 'scss', 'sass', 'less'],
		html: ['html', 'htm', 'xml'],
		text: ['txt', 'md', 'log', 'ini', 'conf', 'config', 'env', 'htaccess'],
		image: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif'],
		archive: ['zip', 'tar', 'gz', 'gzip', 'tgz', 'bz2', '7z'],
		document: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
		media: ['mp3', 'wav', 'ogg', 'mp4', 'webm', 'mov'],
	};

	function itemExtension(item) {
		var supplied = String((item && item.extension) || '').toLowerCase().replace(/^\./, '');
		var name = String((item && item.name) || '');
		if (name.charAt(0) === '.' && name.indexOf('.', 1) === -1) {
			return name.slice(1).toLowerCase();
		}
		var suffix = name.indexOf('.') === -1 ? '' : name.split('.').pop().toLowerCase();
		return supplied || suffix;
	}

	function fileCategory(item) {
		if (item && item.type === 'directory') {
			return 'folder';
		}
		var extension = itemExtension(item);
		var category = Object.keys(fileCategories).find(function (key) {
			return fileCategories[key].indexOf(extension) !== -1;
		});
		return category || 'file';
	}

	var contextActionMap = {
		open: { id: 'open', label: 'Open', icon: 'dashicons-external' },
		view: { id: 'view', label: 'Preview', icon: 'dashicons-visibility' },
		details: { id: 'details', label: 'View details', icon: 'dashicons-info-outline' },
		edit: { id: 'edit', label: 'Edit', icon: 'dashicons-edit-page' },
		download: { id: 'download', label: 'Download', icon: 'dashicons-download' },
		archive: { id: 'archive', label: 'Archive ZIP', icon: 'dashicons-media-archive' },
		rename: { id: 'rename', label: 'Rename', icon: 'dashicons-edit' },
		trash: { id: 'trash', label: 'Move to Trash', icon: 'dashicons-trash', destructive: true },
	};

	function contextActions(item) {
		var available = (item && Array.isArray(item.actions)) ? item.actions : [];
		return ['open', 'view', 'details', 'edit', 'download', 'archive', 'rename', 'trash'].filter(function (id) {
			return available.indexOf(id) !== -1;
		}).map(function (id) {
			return contextActionMap[id];
		});
	}

	function primaryAction(item) {
		var available = contextActions(item).map(function (action) {
			return action.id;
		});
		var order = item && item.type === 'directory'
			? ['open', 'details']
			: ['open', 'view', 'edit', 'details'];
		return order.find(function (id) {
			return available.indexOf(id) !== -1;
		}) || null;
	}

	function clampMenuPosition(point, menu, viewport) {
		var padding = Number(viewport.padding || 0);
		return {
			left: Math.max(padding, Math.min(Number(point.x), Number(viewport.width) - Number(menu.width) - padding)),
			top: Math.max(padding, Math.min(Number(point.y), Number(viewport.height) - Number(menu.height) - padding)),
		};
	}

	function shouldHandleRowAction(target) {
		if (!target || typeof target.closest !== 'function') {
			return true;
		}
		return !target.closest('button, a, input, select, textarea, [role="button"], [role="menuitem"]');
	}

	function createSelection(limit) {
		var maximum = Math.max(1, Number(limit) || 1);
		var selected = new Map();
		return {
			toggle: function (item) {
				var path = String((item && item.path) || '');
				if (!path) {
					return false;
				}
				if (selected.has(path)) {
					selected.delete(path);
					return true;
				}
				if (selected.size >= maximum) {
					return false;
				}
				selected.set(path, item);
				return true;
			},
			clear: function () {
				selected.clear();
			},
			items: function () {
				return Array.from(selected.values());
			},
			paths: function () {
				return Array.from(selected.keys());
			},
			has: function (path) {
				return selected.has(String(path || ''));
			},
			reconcile: function (items) {
				var visible = new Map();
				(items || []).forEach(function (item) {
					var path = String((item && item.path) || '');
					if (path) {
						visible.set(path, item);
					}
				});
				Array.from(selected.keys()).forEach(function (path) {
					if (!visible.has(path)) {
						selected.delete(path);
					} else {
						selected.set(path, visible.get(path));
					}
				});
			},
		};
	}

	function selectionActions(items) {
		if (!items || !items.length) {
			return [];
		}
		var every = function (action) {
			return items.every(function (item) {
				return Array.isArray(item.actions) && item.actions.indexOf(action) !== -1;
			});
		};
		var actions = [];
		if (items.length === 1 && items[0].type === 'file' && every('download')) {
			actions.push('download');
		}
		if (every('archive')) {
			actions.push('archive');
		}
		if (items.length === 1 && every('details')) {
			actions.push('details');
		}
		if (items.length === 1 && every('rename')) {
			actions.push('rename');
		}
		if (every('trash')) {
			actions.push('trash');
		}
		return actions;
	}

	function ancestorPaths(path) {
		var ancestors = [''];
		var parts = String(path || '').split('/').filter(Boolean);
		parts.pop();
		var current = [];
		parts.forEach(function (part) {
			current.push(part);
			ancestors.push(current.join('/'));
		});
		return ancestors;
	}

	function treeLevel(path) {
		return String(path || '').split('/').filter(Boolean).length;
	}

	function createTreeState() {
		var expanded = new Set();
		var active = '';
		return {
			expand: function (path) {
				expanded.add(String(path || ''));
			},
			collapse: function (path) {
				expanded.delete(String(path || ''));
			},
			isExpanded: function (path) {
				return expanded.has(String(path || ''));
			},
			activate: function (path) {
				active = String(path || '');
			},
			isActive: function (path) {
				return active === String(path || '');
			},
		};
	}

	function archivePayload(currentPath, paths, limit) {
		var maximum = Math.max(1, Number(limit) || 1);
		if (!Array.isArray(paths) || !paths.length || paths.length > maximum) {
			return null;
		}
		var current = String(currentPath || '').replace(/\\/g, '/');
		var normalized = paths.map(function (path) {
			return String(path || '').replace(/\\/g, '/');
		});
		var unsafe = function (path, allowEmpty) {
			return (!allowEmpty && !path)
				|| path.charAt(0) === '/'
				|| /(^|\/)\.{1,2}(\/|$)/.test(path)
				|| /^[a-z][a-z0-9+.-]*:\/\//i.test(path)
				|| path.indexOf('//') !== -1
				|| /[\u0000-\u001f\u007f]/.test(path);
		};
		if (unsafe(current, true) || normalized.some(function (path) { return unsafe(path, false); })) {
			return null;
		}
		return { current_path: current, paths: normalized };
	}

	global.siteintelixFileManagerTest = {
		createHistory: createHistory,
		debounce: debounce,
		normalizeSort: normalizeSort,
		normalizeOrder: normalizeOrder,
		fileCategory: fileCategory,
		contextActions: contextActions,
		primaryAction: primaryAction,
		clampMenuPosition: clampMenuPosition,
		shouldHandleRowAction: shouldHandleRowAction,
		createSelection: createSelection,
		selectionActions: selectionActions,
		ancestorPaths: ancestorPaths,
		treeLevel: treeLevel,
		createTreeState: createTreeState,
		archivePayload: archivePayload,
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
			'list_tree',
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
		var selectionLimit = Number((data.limits && data.limits.archiveSelection) || 100);
		var state = {
			path: String(data.startPath || 'wp-content'),
			page: 1,
			perPage: 50,
			sort: 'name',
			order: 'asc',
			search: '',
			selected: null,
			currentItems: [],
			selectionLimit: selectionLimit,
			selection: createSelection(selectionLimit),
			tree: createTreeState(),
			treeChildren: new Map(),
			treeLoading: new Set(),
			treeInitialized: false,
			editorDirty: false,
			editorFile: null,
			editorInstance: null,
			restoreFocus: null,
			pendingNavigation: null,
			contextOrigin: null,
			contextItem: null,
			detailsRequestId: 0,
			responsivePanels: null,
			detailsRestoreFocus: null,
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

		function panelsAreResponsive() {
			return global.matchMedia('(max-width: 1100px)').matches;
		}

		function setTreeOpen(open, restoreFocus) {
			var panel = select('[data-fm-tree-panel]');
			var toggle = select('[data-fm-toggle-tree]');
			var responsive = panelsAreResponsive();
			if (!panel || !toggle) {
				return;
			}
			panel.classList.toggle('is-open', responsive && open);
			toggle.setAttribute('aria-expanded', responsive && open ? 'true' : 'false');
			if (!open && restoreFocus && panel.contains(document.activeElement)) {
				toggle.focus();
			}
		}

		function setDetailsOpen(open, trigger) {
			var panel = select('[data-fm-details-panel]');
			if (!panel) {
				return;
			}
			if (open) {
				if (panelsAreResponsive()) {
					setTreeOpen(false, false);
				}
				state.detailsRestoreFocus = trigger || document.activeElement;
				panel.hidden = false;
				panel.classList.add('is-open');
				var close = select('[data-fm-close-details]', panel);
				if (close) {
					close.focus();
				}
			} else {
				panel.classList.remove('is-open');
				panel.hidden = true;
				if (state.detailsRestoreFocus && typeof state.detailsRestoreFocus.focus === 'function') {
					state.detailsRestoreFocus.focus();
				}
				state.detailsRestoreFocus = null;
			}
		}

		function syncPanelsForViewport(force) {
			var responsive = panelsAreResponsive();
			if (!force && state.responsivePanels === responsive) {
				return;
			}
			state.responsivePanels = responsive;
			setTreeOpen(false, false);
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

		function backupDownloadUrl(id) {
			var params = new URLSearchParams({
				action: 'siteintelix_fm_download_backup',
				id: id,
				_wpnonce: data.backupDownloadNonce || '',
			});
			return String(data.downloadUrl || '') + '?' + params.toString();
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

		function visit(path) {
			closeContextMenu(false);
			guardDirty(function () {
				state.path = String(path || '');
				state.page = 1;
				state.selected = null;
				state.selection.clear();
				updateSelectionUI();
				setDetailsOpen(false);
				loadDirectory();
			});
		}

		function fileIcon(item, large) {
			var category = fileCategory(item);
			var icon = node('span', 'sitx-fm-file-icon sitx-fm-file-icon--' + category + (large ? ' is-large' : ''));
			icon.setAttribute('aria-hidden', 'true');
			icon.appendChild(node('span', 'sitx-fm-file-icon__label', category === 'folder' ? '' : category.slice(0, 3).toUpperCase()));
			return icon;
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
					visit(crumb.path || '');
				}, 'sitx-fm-breadcrumbs__item'));
			});
		}

		function treeItemForPath(path) {
			return selectAll('[data-fm-tree-path]').find(function (item) {
				return item.getAttribute('data-fm-tree-path') === String(path || '');
			}) || null;
		}

		function updateTreeCurrent() {
			selectAll('[data-fm-tree-path]').forEach(function (item) {
				var current = state.tree.isActive(item.getAttribute('data-fm-tree-path'));
				item.classList.toggle('is-current', current);
				if (current) {
					item.setAttribute('aria-current', 'page');
				} else {
					item.removeAttribute('aria-current');
				}
			});
		}

		function renderTreeNode(directory, rootNode) {
			var path = String(directory.path || '');
			var hasChildren = rootNode || Boolean(directory.has_children);
			var item = node('li', 'sitx-fm-tree__item');
			item.setAttribute('role', 'treeitem');
			item.setAttribute('data-fm-tree-path', path);
			item.setAttribute('aria-level', String(rootNode ? 1 : Number(directory.level || treeLevel(path)) + 1));
			item.style.setProperty('--fm-level', String(rootNode ? 1 : Number(directory.level || treeLevel(path)) + 1));
			if (hasChildren) {
				item.setAttribute('aria-expanded', state.tree.isExpanded(path) ? 'true' : 'false');
			}

			var row = node('div', 'sitx-fm-tree__row');
			var toggle;
			if (hasChildren) {
				toggle = button('', function (event) {
					event.stopPropagation();
					var group = select('[data-fm-tree-children]', item);
					if (state.tree.isExpanded(path)) {
						state.tree.collapse(path);
						item.setAttribute('aria-expanded', 'false');
						group.hidden = true;
					} else {
						loadTreeBranch(path, group, item);
					}
				}, 'sitx-fm-tree__toggle');
				toggle.setAttribute('data-fm-tree-toggle', '');
				toggle.setAttribute('aria-label', 'Expand ' + String(directory.name || 'WordPress'));
				toggle.appendChild(node('span', 'dashicons dashicons-arrow-right-alt2'));
			} else {
				toggle = node('span', 'sitx-fm-tree__toggle is-empty');
			}
			row.appendChild(toggle);

			var selectNode = button('', function () {
				visit(path);
				if (panelsAreResponsive()) {
					setTreeOpen(false, true);
				}
			}, 'sitx-fm-tree__select');
			selectNode.setAttribute('data-fm-tree-select', '');
			selectNode.appendChild(fileIcon({ type: 'directory', name: directory.name || 'WordPress' }, false));
			selectNode.appendChild(node('span', 'sitx-fm-tree__label', directory.name || 'WordPress'));
			if (directory.read_only) {
				selectNode.appendChild(node('span', 'sitx-fm-tree__lock dashicons dashicons-lock', ''));
			}
			row.appendChild(selectNode);
			item.appendChild(row);

			var group = node('ul', 'sitx-fm-tree__group');
			group.setAttribute('role', 'group');
			group.setAttribute('data-fm-tree-children', '');
			group.hidden = !state.tree.isExpanded(path);
			item.appendChild(group);
			return item;
		}

		function renderTreeBranch(children, group) {
			clear(group);
			(children || []).forEach(function (directory) {
				group.appendChild(renderTreeNode(directory, false));
			});
		}

		function loadTreeBranch(path, group, item) {
			path = String(path || '');
			if (state.treeLoading.has(path)) {
				return Promise.resolve();
			}
			if (state.treeChildren.has(path)) {
				renderTreeBranch(state.treeChildren.get(path), group);
				state.tree.expand(path);
				item.setAttribute('aria-expanded', 'true');
				group.hidden = false;
				return Promise.resolve();
			}
			state.treeLoading.add(path);
			item.classList.add('is-loading');
			return request('list_tree', { path: path }).then(function (result) {
				var children = Array.isArray(result.children) ? result.children : [];
				state.treeChildren.set(path, children);
				renderTreeBranch(children, group);
				state.tree.expand(path);
				item.setAttribute('aria-expanded', 'true');
				group.hidden = false;
			}).catch(function (error) {
				clear(group);
				var failure = node('li', 'sitx-fm-tree__error', errorMessage(error));
				var retry = button('Retry', function () {
					state.treeChildren.delete(path);
					loadTreeBranch(path, group, item);
				}, 'sitx-fm-tree__retry');
				retry.setAttribute('data-fm-tree-retry', '');
				failure.appendChild(retry);
				group.appendChild(failure);
				group.hidden = false;
			}).finally(function () {
				state.treeLoading.delete(path);
				item.classList.remove('is-loading');
			});
		}

		function expandTreeTo(path) {
			var chain = Promise.resolve();
			ancestorPaths(path).forEach(function (ancestor) {
				chain = chain.then(function () {
					var item = treeItemForPath(ancestor);
					var group = item ? select('[data-fm-tree-children]', item) : null;
					return item && group ? loadTreeBranch(ancestor, group, item) : null;
				});
			});
			return chain.then(updateTreeCurrent);
		}

		function initializeTree() {
			var tree = select('[data-fm-tree-root]');
			if (!tree || state.treeInitialized) {
				return;
			}
			state.treeInitialized = true;
			clear(tree);
			tree.appendChild(renderTreeNode({ name: 'WordPress', path: '', has_children: true, read_only: true, level: 1 }, true));
			expandTreeTo(state.path);
		}

		function updateSelectionUI() {
			var items = state.selection.items();
			var available = selectionActions(items);
			var bar = select('[data-fm-selection-actions]');
			var count = select('[data-fm-selection-count]');
			var selectAllControl = select('[data-fm-select-all]');
			if (bar) {
				bar.hidden = items.length === 0;
			}
			if (count) {
				var singular = (data.i18n && data.i18n.selectedSingular) || '1 item selected';
				var plural = (data.i18n && data.i18n.selectedPlural) || '%d items selected';
				count.textContent = items.length === 1 ? singular : plural.replace('%d', String(items.length));
			}
			['download', 'archive', 'details', 'rename', 'trash'].forEach(function (action) {
				var control = select('[data-fm-selection-' + action + ']');
				if (control) {
					control.disabled = available.indexOf(action) === -1
						|| (action === 'archive' && data.features && data.features.archiveAvailable === false);
				}
			});
			selectAll('[data-fm-select-item]').forEach(function (checkbox) {
				var row = checkbox.closest('tr');
				var path = row ? row.dataset.fmItemPath : '';
				checkbox.checked = state.selection.has(path);
				if (row) {
					row.classList.toggle('is-checked', checkbox.checked);
				}
			});
			if (selectAllControl) {
				var visible = state.currentItems.length;
				var selectedVisible = state.currentItems.filter(function (item) {
					return state.selection.has(item.path);
				}).length;
				selectAllControl.checked = visible > 0 && selectedVisible === visible;
				selectAllControl.indeterminate = selectedVisible > 0 && selectedVisible < visible;
			}
		}

		function downloadArchive(items) {
			var payload = archivePayload(state.path, (items || []).map(function (item) {
				return item.path;
			}), state.selectionLimit);
			if (!payload || (data.features && data.features.archiveAvailable === false)) {
				speak((data.i18n && data.i18n.operationFailed) || 'Archive download is unavailable.');
				return;
			}
			var form = document.createElement('form');
			form.method = 'post';
			form.action = String(data.downloadUrl || '');
			form.target = 'siteintelix-file-manager-download';
			form.hidden = true;
			[
				['action', 'siteintelix_fm_download_archive'],
				['nonce', data.archiveNonce || ''],
				['current_path', payload.current_path],
			].forEach(function (entry) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = entry[0];
				input.value = entry[1];
				form.appendChild(input);
			});
			payload.paths.forEach(function (path) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'paths[]';
				input.value = path;
				form.appendChild(input);
			});
			document.body.appendChild(form);
			form.submit();
			form.remove();
			speak('Preparing ZIP archive download.');
		}

		function renderItems(result) {
			var status = select('[data-fm-state]');
			var table = select('[data-fm-table]');
			var body = table ? table.querySelector('tbody') : null;
			var pagination = select('[data-fm-pagination]');
			if (!table || !body || !status) {
				return;
			}
			state.currentItems = Array.isArray(result.items) ? result.items : [];
			state.selection.reconcile(state.currentItems);
			clear(body);
			if (!state.currentItems.length) {
				setBrowserStatus((data.i18n && data.i18n.empty) || 'This directory is empty.', false);
			} else {
				status.hidden = true;
				table.hidden = false;
				state.currentItems.forEach(function (item) {
					var row = node('tr');
					row.tabIndex = 0;
					row.setAttribute('aria-selected', state.selected && state.selected.path === item.path ? 'true' : 'false');
					row.classList.toggle('is-selected', Boolean(state.selected && state.selected.path === item.path));
					row.classList.toggle('is-checked', state.selection.has(item.path));
					row.dataset.fmItemPath = item.path;
					row.addEventListener('click', function () {
						selectItem(item, row);
					});
					row.addEventListener('dblclick', function (event) {
						if (!shouldHandleRowAction(event.target)) {
							return;
						}
						event.preventDefault();
						runPrimaryAction(item, row);
					});
					row.addEventListener('contextmenu', function (event) {
						event.preventDefault();
						openContextMenu(item, row, row, { x: event.clientX, y: event.clientY });
					});
					row.addEventListener('keydown', function (event) {
						if (!shouldHandleRowAction(event.target)) {
							return;
						}
						if (event.key === 'Enter') {
							event.preventDefault();
							runPrimaryAction(item, row);
						} else if ((event.key === 'F10' && event.shiftKey) || event.key === 'ContextMenu') {
							event.preventDefault();
							openContextMenu(item, row, row);
						}
					});
					var selectCell = node('td', 'sitx-fm-select-column');
					var checkbox = document.createElement('input');
					checkbox.type = 'checkbox';
					checkbox.checked = state.selection.has(item.path);
					checkbox.setAttribute('data-fm-select-item', '');
					checkbox.setAttribute('aria-label', 'Select ' + String(item.name || 'item'));
					checkbox.addEventListener('click', function (event) {
						event.stopPropagation();
						if (!state.selection.toggle(item)) {
							checkbox.checked = false;
							speak('You can select up to ' + state.selectionLimit + ' items.');
						}
						updateSelectionUI();
					});
					selectCell.appendChild(checkbox);
					row.appendChild(selectCell);
					var nameCell = node('td', 'sitx-fm-name-cell');
					var nameWrap = node('span', 'sitx-fm-name');
					nameWrap.appendChild(fileIcon(item, false));
					nameWrap.appendChild(node('span', 'sitx-fm-name__label', item.name));
					nameCell.appendChild(nameWrap);
					row.appendChild(nameCell);
					row.appendChild(node('td', '', item.size_label || '—'));
					row.appendChild(node('td', '', item.permissions || '—'));
					row.appendChild(node('td', '', formatDate(item.modified)));
					row.appendChild(node('td', '', item.read_only || !item.writable ? 'Read-only' : 'Writable'));
					var menuCell = node('td', 'sitx-fm-table__menu-cell');
					var menuButton = button('', function (event) {
						event.stopPropagation();
						openContextMenu(item, row, event.currentTarget);
					}, 'sitx-fm-row-menu');
					menuButton.setAttribute('data-fm-row-menu', '');
					menuButton.setAttribute('aria-label', 'Actions for ' + item.name);
					var menuIcon = node('span', 'dashicons dashicons-ellipsis');
					menuIcon.setAttribute('aria-hidden', 'true');
					menuButton.appendChild(menuIcon);
					menuCell.appendChild(menuButton);
					row.appendChild(menuCell);
					body.appendChild(row);
				});
			}
			updateSelectionUI();
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
			closeContextMenu(false);
			state.detailsRequestId += 1;
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
				renderItems(result);
				state.tree.activate(state.path);
				initializeTree();
				expandTreeTo(state.path);
			}).catch(function (error) {
				setBrowserStatus(errorMessage(error), true);
				speak(errorMessage(error));
			});
		}

		function selectItem(item, row, announce) {
			state.selected = item;
			selectAll('[data-fm-table] tbody tr').forEach(function (entry) {
				entry.classList.toggle('is-selected', entry === row);
				entry.setAttribute('aria-selected', entry === row ? 'true' : 'false');
			});
			if (announce !== false) {
				speak(String(item.name || 'Item') + ' selected.');
			}
		}

		function openDetailsFor(item, trigger, row) {
			var activeRow = row || selectAll('[data-fm-table] tbody tr').find(function (entry) {
				return entry.dataset.fmItemPath === item.path;
			}) || null;
			selectItem(item, activeRow, false);
			showDetails(item);
			setDetailsOpen(true, trigger || activeRow);
		}

		function addMetadata(list, label, value) {
			list.appendChild(node('dt', '', label));
			list.appendChild(node('dd', '', value === null || value === undefined || value === '' ? '—' : value));
		}

		function showDetails(item) {
			var requestId = ++state.detailsRequestId;
			var empty = select('[data-fm-details-empty]');
			var content = select('[data-fm-details-content]');
			var name = select('[data-fm-details-name]');
			var preview = select('[data-fm-preview]');
			var imagePreview = select('[data-fm-image-preview]');
			var metadata = select('[data-fm-metadata]');
			var icon = select('[data-fm-details-icon]');
			if (!content || !metadata || !icon) {
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
			clear(icon);
			icon.appendChild(fileIcon(item, true));
			request('get_details', { path: item.path, hashes: '1' }).then(function (details) {
				if (requestId !== state.detailsRequestId || !state.selected || state.selected.path !== item.path) {
					return;
				}
				addMetadata(metadata, 'Path', details.path);
				if (details.absolute_path) {
					addMetadata(metadata, 'Absolute path', details.absolute_path);
				}
				addMetadata(metadata, 'Type', details.type);
				addMetadata(metadata, 'Size', details.size === null ? '—' : formatBytes(details.size));
				addMetadata(metadata, 'MIME', details.mime);
				addMetadata(metadata, 'Modified', formatDate(details.modified));
				addMetadata(metadata, 'Permissions', details.permissions);
				addMetadata(metadata, 'Writable', details.writable ? 'Yes' : 'No');
				if (details.md5) {
					addMetadata(metadata, 'MD5', details.md5);
				}
				if (details.sha256) {
					addMetadata(metadata, 'SHA-256', details.sha256);
				}
			}).catch(function (error) {
				if (requestId !== state.detailsRequestId || !state.selected || state.selected.path !== item.path) {
					return;
				}
				addMetadata(metadata, 'Error', errorMessage(error));
			});
			if (item.type === 'file') {
				request('get_file', { path: item.path }).then(function (result) {
					if (requestId !== state.detailsRequestId || !state.selected || state.selected.path !== item.path) {
						return;
					}
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
					if (requestId !== state.detailsRequestId || !state.selected || state.selected.path !== item.path) {
						return;
					}
					preview.textContent = errorMessage(error);
				});
			} else {
				preview.textContent = 'Folder';
			}
		}

		function closeContextMenu(restoreFocus) {
			var menu = select('[data-fm-context-menu]');
			if (!menu || menu.hidden) {
				return;
			}
			menu.hidden = true;
			clear(menu);
			if (restoreFocus && state.contextOrigin && typeof state.contextOrigin.focus === 'function') {
				state.contextOrigin.focus();
			}
			state.contextOrigin = null;
			state.contextItem = null;
		}

		function runPrimaryAction(item, row) {
			var action = primaryAction(item);
			if (!action) {
				return;
			}
			if (action === 'view' || action === 'details') {
				openDetailsFor(item, row, row);
				return;
			}
			handleItemAction(action, item, row);
		}

		function contextMenuItems() {
			var menu = select('[data-fm-context-menu]');
			return menu ? selectAll('[role="menuitem"]', menu) : [];
		}

		function moveContextFocus(key) {
			var items = contextMenuItems();
			if (!items.length) {
				return;
			}
			var current = Math.max(0, items.indexOf(document.activeElement));
			var next = current;
			if (key === 'ArrowDown') {
				next = (current + 1) % items.length;
			} else if (key === 'ArrowUp') {
				next = (current - 1 + items.length) % items.length;
			} else if (key === 'Home') {
				next = 0;
			} else if (key === 'End') {
				next = items.length - 1;
			}
			items[next].focus();
		}

		function contextMenuItem(action, item, origin) {
			var element = action.id === 'download'
				? node('a', 'sitx-fm-context-menu__item')
				: node('button', 'sitx-fm-context-menu__item');
			if (action.id === 'download') {
				element.href = downloadUrl(item.path);
				element.addEventListener('click', function () {
					closeContextMenu(false);
				});
			} else {
				element.type = 'button';
				element.addEventListener('click', function () {
					closeContextMenu(false);
					handleItemAction(action.id, item, origin);
				});
			}
			element.setAttribute('role', 'menuitem');
			element.tabIndex = -1;
			if (action.destructive) {
				element.classList.add('is-destructive');
			}
			var actionIcon = node('span', 'sitx-fm-context-menu__icon dashicons ' + action.icon);
			actionIcon.setAttribute('aria-hidden', 'true');
			element.appendChild(actionIcon);
			element.appendChild(node('span', '', action.label));
			return element;
		}

		function openContextMenu(item, row, origin, coordinates) {
			var menu = select('[data-fm-context-menu]');
			var actions = contextActions(item);
			if (!menu || !actions.length) {
				return;
			}
			closeContextMenu(false);
			selectItem(item, row, false);
			state.contextItem = item;
			state.contextOrigin = origin;
			clear(menu);
			actions.forEach(function (action) {
				if (action.destructive && menu.childNodes.length) {
					var separator = node('span', 'sitx-fm-context-menu__separator');
					separator.setAttribute('role', 'separator');
					menu.appendChild(separator);
				}
				menu.appendChild(contextMenuItem(action, item, origin));
			});
			menu.hidden = false;
			var rect = menu.getBoundingClientRect();
			var originRect = origin.getBoundingClientRect();
			var point = coordinates || {
				x: originRect.right,
				y: originRect.bottom,
			};
			var position = clampMenuPosition(
				point,
				{ width: rect.width, height: rect.height },
				{ width: global.innerWidth, height: global.innerHeight, padding: 8 }
			);
			menu.style.left = position.left + 'px';
			menu.style.top = position.top + 'px';
			var first = contextMenuItems()[0];
			if (first) {
				first.focus();
			}
			speak('Actions for ' + String(item.name || 'item') + ' opened.');
		}

		function handleItemAction(action, item, trigger) {
			if (action === 'open') {
				visit(item.path);
			} else if (action === 'view' || action === 'details') {
				openDetailsFor(item, trigger);
			} else if (action === 'edit') {
				openEditor(item.path);
			} else if (action === 'archive') {
				downloadArchive([item]);
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

		function openCreate(kind, trigger) {
			var directory = kind === 'directory';
			openModal({
				title: directory ? 'New Folder' : 'New File',
				description: directory
					? 'Create a folder in the current directory.'
					: 'Create a permitted text file in the current directory.',
				trigger: trigger,
				confirmLabel: 'Create',
				fields: [{ name: 'name', label: 'Name', type: 'text' }],
				onConfirm: function (values) {
					var action = directory ? 'create_directory' : 'create_file';
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

		function openBulkTrash(items, trigger) {
			var queue = (items || []).slice();
			if (queue.length === 1) {
				openTrash(queue[0], trigger);
				return;
			}
			openModal({
				title: 'Move selected items to trash?',
				description: queue.length + ' items will be moved to private File Manager trash. Processing stops if an item fails.',
				trigger: trigger,
				confirmLabel: 'Move to trash',
				onConfirm: function () {
					var chain = Promise.resolve();
					queue.forEach(function (item) {
						chain = chain.then(function () {
							return request('trash_item', { path: item.path, confirmed_non_empty: 1 });
						});
					});
					return chain.then(function () {
						state.selection.clear();
						speak(queue.length + ' items moved to trash.');
						loadDirectory();
					});
				},
			});
		}

		function uploadOne(file, destination, overwrite, onProgress) {
			return new Promise(function (resolve, reject) {
				var form = new FormData();
				form.set('action', 'siteintelix_fm_upload_files');
				form.set('nonce', data.nonces.upload_files);
				form.set('destination', destination);
				form.set('overwrite', overwrite ? '1' : '0');
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

		function uploadFiles(files, overwrite) {
			var list = Array.prototype.slice.call(files || []);
			var chain = Promise.resolve();
			var failures = [];
			list.forEach(function (file) {
				chain = chain.then(function () {
					return uploadOne(file, state.path, overwrite, function (progress) {
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
					var download = node('a', 'si-button si-button--secondary', 'Download');
					download.href = backupDownloadUrl(item.id);
					actions.appendChild(download);
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
								return request('permanently_delete_item', { id: item.id, confirmation: values.confirmation }).then(function () {
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

		function closeSortMenu(restoreFocus) {
			var menu = select('[data-fm-sort-menu]');
			var toggle = select('[data-fm-sort-toggle]');
			if (!menu || menu.hidden) {
				return;
			}
			menu.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			if (restoreFocus) {
				toggle.focus();
			}
		}

		function sortMenuItems() {
			var menu = select('[data-fm-sort-menu]');
			return menu ? selectAll('[role="menuitemradio"]', menu) : [];
		}

		function renderSortMenu() {
			var menu = select('[data-fm-sort-menu]');
			var choices = [
				['name', 'asc', 'Name (A–Z)'],
				['name', 'desc', 'Name (Z–A)'],
				['type', 'asc', 'Type (A–Z)'],
				['type', 'desc', 'Type (Z–A)'],
				['size', 'asc', 'Size (smallest first)'],
				['size', 'desc', 'Size (largest first)'],
				['modified', 'desc', 'Modified (newest first)'],
				['modified', 'asc', 'Modified (oldest first)'],
			];
			clear(menu);
			choices.forEach(function (choice) {
				var control = button(choice[2], function () {
					state.sort = choice[0];
					state.order = choice[1];
					state.page = 1;
					closeSortMenu(true);
					loadDirectory();
				}, 'sitx-fm-sort__item');
				control.setAttribute('role', 'menuitemradio');
				control.setAttribute('aria-checked', state.sort === choice[0] && state.order === choice[1] ? 'true' : 'false');
				control.tabIndex = -1;
				menu.appendChild(control);
			});
		}

		function moveSortFocus(key) {
			var items = sortMenuItems();
			if (!items.length) {
				return;
			}
			var current = Math.max(0, items.indexOf(document.activeElement));
			var next = current;
			if (key === 'ArrowDown') {
				next = (current + 1) % items.length;
			} else if (key === 'ArrowUp') {
				next = (current - 1 + items.length) % items.length;
			} else if (key === 'Home') {
				next = 0;
			} else if (key === 'End') {
				next = items.length - 1;
			}
			items[next].focus();
		}

		function bindBrowser() {
			var menu = select('[data-fm-context-menu]');
			var sortMenu = select('[data-fm-sort-menu]');
			var sortToggle = select('[data-fm-sort-toggle]');
			menu.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					closeContextMenu(true);
				} else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) !== -1) {
					event.preventDefault();
					moveContextFocus(event.key);
				} else if ((event.key === ' ' || event.key === 'Spacebar') && document.activeElement && document.activeElement.tagName === 'A' && menu.contains(document.activeElement)) {
					event.preventDefault();
					document.activeElement.click();
				}
			});
			sortMenu.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					closeSortMenu(true);
				} else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) !== -1) {
					event.preventDefault();
					moveSortFocus(event.key);
				} else if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					document.activeElement.click();
				}
			});
			document.addEventListener('pointerdown', function (event) {
				var target = event.target;
				var rowMenu = target && typeof target.closest === 'function' ? target.closest('[data-fm-row-menu]') : null;
				if (!menu.hidden && !menu.contains(target) && !rowMenu) {
					closeContextMenu(false);
				}
				if (!sortMenu.hidden && !sortMenu.contains(target) && !sortToggle.contains(target)) {
					closeSortMenu(false);
				}
			});
			global.addEventListener('resize', function () {
				closeContextMenu(false);
				syncPanelsForViewport(false);
			});
			global.addEventListener('scroll', function () {
				closeContextMenu(false);
			}, true);
			select('[data-fm-refresh]').addEventListener('click', loadDirectory);
			select('[data-fm-new-folder]').addEventListener('click', function (event) {
				openCreate('directory', event.currentTarget);
			});
			select('[data-fm-new-file]').addEventListener('click', function (event) {
				openCreate('file', event.currentTarget);
			});
			sortToggle.addEventListener('click', function () {
				var opening = sortMenu.hidden;
				closeContextMenu(false);
				if (!opening) {
					closeSortMenu(false);
					return;
				}
				renderSortMenu();
				sortMenu.hidden = false;
				sortToggle.setAttribute('aria-expanded', 'true');
				var current = sortMenuItems().find(function (item) {
					return item.getAttribute('aria-checked') === 'true';
				}) || sortMenuItems()[0];
				if (current) {
					current.focus();
				}
			});
			var uploadInput = select('[data-fm-upload-input]');
			select('[data-fm-upload]').addEventListener('click', function () {
				if (data.features && data.features.overwrite) {
					openModal({
						title: 'Upload files',
						description: 'Files with matching names may replace existing files. A private backup is created first.',
						confirmLabel: 'Choose files',
						onConfirm: function () {
							uploadInput.dataset.overwrite = '1';
							uploadInput.click();
						},
					});
					return;
				}
				uploadInput.dataset.overwrite = '0';
				uploadInput.click();
			});
			uploadInput.addEventListener('change', function () {
				uploadFiles(uploadInput.files, uploadInput.dataset.overwrite === '1');
				uploadInput.value = '';
				uploadInput.dataset.overwrite = '0';
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
				setTreeOpen(event.currentTarget.getAttribute('aria-expanded') !== 'true', false);
			});
			select('[data-fm-close-tree]').addEventListener('click', function () {
				setTreeOpen(false, true);
			});
			select('[data-fm-close-details]').addEventListener('click', function () {
				setDetailsOpen(false);
			});
			select('[data-fm-select-all]').addEventListener('change', function (event) {
				if (!event.currentTarget.checked) {
					state.selection.clear();
					updateSelectionUI();
					return;
				}
				state.selection.clear();
				state.currentItems.slice(0, state.selectionLimit).forEach(function (item) {
					state.selection.toggle(item);
				});
				if (state.currentItems.length > state.selectionLimit) {
					speak('Only the first ' + state.selectionLimit + ' visible items were selected.');
				}
				updateSelectionUI();
			});
			select('[data-fm-selection-clear]').addEventListener('click', function () {
				state.selection.clear();
				updateSelectionUI();
			});
			select('[data-fm-selection-download]').addEventListener('click', function () {
				var item = state.selection.items()[0];
				if (item && item.type === 'file') {
					var link = document.createElement('a');
					link.href = downloadUrl(item.path);
					link.click();
				}
			});
			select('[data-fm-selection-archive]').addEventListener('click', function () {
				downloadArchive(state.selection.items());
			});
			select('[data-fm-selection-details]').addEventListener('click', function (event) {
				var item = state.selection.items()[0];
				if (item) {
					openDetailsFor(item, event.currentTarget);
				}
			});
			select('[data-fm-selection-rename]').addEventListener('click', function (event) {
				var item = state.selection.items()[0];
				if (item) {
					openRename(item, event.currentTarget);
				}
			});
			select('[data-fm-selection-trash]').addEventListener('click', function (event) {
				openBulkTrash(state.selection.items(), event.currentTarget);
			});
			select('[data-fm-editor-save]').addEventListener('click', saveEditor);
			select('[data-fm-editor-cancel]').addEventListener('click', function () {
				guardDirty(closeEditor);
			});
			select('[data-fm-editor-fullscreen]').addEventListener('click', function () {
				select('[data-fm-editor]').classList.toggle('is-fullscreen');
			});
			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && !select('[data-fm-details-panel]').hidden) {
					setDetailsOpen(false);
				}
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
			syncPanelsForViewport(true);
			initializeTree();
			updateSelectionUI();
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
