(function () {
	'use strict';

function normalizeText(value) {
	if (value === null || typeof value === 'undefined') {
		return '';
	}

	if (typeof value === 'object') {
		try {
			return JSON.stringify(value).toLowerCase();
		} catch (error) {
			return String(value).toLowerCase();
		}
	}

	return String(value).toLowerCase();
}

function filterRows(rows, filter, query) {
	var statusGroups = {
		issues: ['danger'],
		warnings: ['warning'],
		passed: ['pass'],
		information: ['info', 'neutral'],
	};
	var statuses = statusGroups[filter];
	var search = normalizeText(query).trim();

	return (Array.isArray(rows) ? rows : []).filter(function (row) {
		if (row === null || typeof row !== 'object' || Array.isArray(row)) {
			return false;
		}

		var statusMatches = !statuses || statuses.indexOf(row.status) !== -1;
		var searchable = [row.label, row.value, row.detail, row.recommended]
			.map(normalizeText)
			.join(' ');

		return statusMatches && (!search || searchable.indexOf(search) !== -1);
	});
}

function orderRows(rows) {
	var ranks = {
		danger: 0,
		warning: 1,
		info: 2,
		neutral: 2,
		pass: 3,
	};

	return (Array.isArray(rows) ? rows : [])
		.filter(function (row) {
			return row !== null && typeof row === 'object' && !Array.isArray(row);
		})
		.map(function (row, index) {
			return { row: row, index: index };
		})
		.sort(function (left, right) {
			var leftRank = Object.prototype.hasOwnProperty.call(ranks, left.row.status) ? ranks[left.row.status] : 4;
			var rightRank = Object.prototype.hasOwnProperty.call(ranks, right.row.status) ? ranks[right.row.status] : 4;

			return leftRank - rightRank || left.index - right.index;
		})
		.map(function (item) {
			return item.row;
		});
}

function sortRows(rows, mode) {
	var ranks = {
		danger: 0,
		warning: 1,
		info: 2,
		neutral: 2,
		pass: 3,
	};

	return (Array.isArray(rows) ? rows : [])
		.filter(function (row) {
			return row !== null && typeof row === 'object' && !Array.isArray(row);
		})
		.map(function (row, index) {
			return { row: row, index: index };
		})
		.sort(function (left, right) {
			var result = 0;
			if ('name' === mode) {
				result = normalizeText(left.row.label).localeCompare(normalizeText(right.row.label));
			} else {
				var leftRank = Object.prototype.hasOwnProperty.call(ranks, left.row.status) ? ranks[left.row.status] : 4;
				var rightRank = Object.prototype.hasOwnProperty.call(ranks, right.row.status) ? ranks[right.row.status] : 4;
				result = leftRank - rightRank;
			}
			return result || left.index - right.index;
		})
		.map(function (item) {
			return item.row;
		});
}

function applyRowView(rows, options) {
	var settings = options || {};
	var matched = filterRows(rows, settings.filter || 'all', settings.query || '');
	var limit = Number(settings.limit) > 0 ? Math.floor(Number(settings.limit)) : 10;

	if (settings.hidePassed && 'passed' !== settings.filter) {
		matched = matched.filter(function (row) {
			return 'pass' !== row.status;
		});
	}
	matched = sortRows(matched, settings.sort || 'severity');

	return {
		total: matched.length,
		visible: settings.expanded ? matched : matched.slice(0, limit),
		hasMore: !settings.expanded && matched.length > limit,
		rows: matched,
	};
}

function visibleRange(total, rowHeight, scrollTop, viewportHeight, overscan) {
	var numericTotal = Number(total);
	var numericRowHeight = Number(rowHeight);

	if (!Number.isFinite(numericTotal) || numericTotal < 0 ||
		!Number.isFinite(numericRowHeight) || numericRowHeight <= 0) {
		return { start: 0, end: 0 };
	}

	var safeTotal = Math.floor(numericTotal);
	var numericScrollTop = Number(scrollTop);
	var numericViewportHeight = Number(viewportHeight);
	var numericOverscan = Number(overscan);
	var safeScrollTop = Number.isFinite(numericScrollTop) && numericScrollTop >= 0 ? numericScrollTop : 0;
	var safeViewportHeight = Number.isFinite(numericViewportHeight) && numericViewportHeight >= 0 ? numericViewportHeight : 0;
	var safeOverscan = Number.isFinite(numericOverscan) && numericOverscan >= 0 ? Math.floor(numericOverscan) : 0;
	var start = Math.max(0, Math.floor(safeScrollTop / numericRowHeight) - safeOverscan);
	var end = Math.ceil((safeScrollTop + safeViewportHeight) / numericRowHeight) + safeOverscan;

	return {
		start: Math.min(safeTotal, start),
		end: Math.min(safeTotal, Math.max(start, end)),
	};
}

function partitionRows(rows) {
	var result = {
		problems: [],
		information: [],
		passed: [],
		ordered: [],
	};

	orderRows(rows).forEach(function (row) {
		if ('danger' === row.status || 'warning' === row.status) {
			result.problems.push(row);
		} else if ('pass' === row.status) {
			result.passed.push(row);
		} else {
			result.information.push(row);
		}
		result.ordered.push(row);
	});

	return result;
}

function sectionMatchSummary(rows, filter, query) {
	var matched = filterRows(rows, filter, query);
	var summary = {
		total: matched.length,
		failed: 0,
		warnings: 0,
		passed: 0,
		information: 0,
		rows: matched,
	};

	matched.forEach(function (row) {
		if ('danger' === row.status) {
			summary.failed += 1;
		} else if ('warning' === row.status) {
			summary.warnings += 1;
		} else if ('pass' === row.status) {
			summary.passed += 1;
		} else {
			summary.information += 1;
		}
	});

	return summary;
}

function diagnosticsStrings(windowObject) {
	var defaults = {
		checksShownSingular: '%d check shown',
		checksShownPlural: '%d checks shown',
		showPassedSingular: 'Show %d passed check',
		showPassedPlural: 'Show %d passed checks',
		hidePassedSingular: 'Hide %d passed check',
		hidePassedPlural: 'Hide %d passed checks',
		showInformationSingular: 'Show %d informational check',
		showInformationPlural: 'Show %d informational checks',
		hideInformationSingular: 'Hide %d informational check',
		hideInformationPlural: 'Hide %d informational checks',
		noResults: 'No diagnostic checks match the current filters.',
		resetFilters: 'Reset filters',
		partialLoad: 'Some sections could not be loaded.',
		sectionLoadError: 'This diagnostics section could not be displayed.',
		copySuccess: 'System information copied.',
		copyFailure: 'System information could not be copied.',
		systemInfoFallback: 'System information is unavailable.',
		expandLabel: 'Expand %s',
		collapseLabel: 'Collapse %s',
		problemsListLabel: 'Checks needing attention',
		informationListLabel: 'Informational checks',
		passedListLabel: 'Passed checks',
		listTotal: '%s (%d total)',
		sectionNoMatches: 'No checks in this section match the current filters.',
		unnamedCheck: 'Unnamed check',
		criticalIssue: 'Critical issue',
		warning: 'Warning',
		information: 'Information',
		passed: 'Passed',
		current: 'Current',
		recommended: 'Recommended',
		details: 'Details',
		showAllChecks: 'Show all checks',
		showFewer: 'Show fewer',
		statusColumn: 'Status',
		checkColumn: 'Check',
		descriptionColumn: 'Description',
		actionColumn: 'Action',
		issueSingular: '%d issue',
		issuePlural: '%d issues',
		warningSingular: '%d warning',
		warningPlural: '%d warnings',
		passedCount: '%d passed',
		informationCount: '%d information',
		refreshing: 'Refreshing diagnostics…',
		refreshComplete: 'Diagnostics refreshed.',
		refreshFailed: 'Refresh failed. The previous results are still available.',
	};
	var localized = windowObject && windowObject.siteintelixDiagnosticsData;
	var key;

	if (localized && typeof localized === 'object') {
		for (key in localized) {
			if (Object.prototype.hasOwnProperty.call(localized, key) &&
				(typeof localized[key] === 'string' || (localized[key] && typeof localized[key] === 'object'))) {
				defaults[key] = localized[key];
			}
		}
	}
	defaults.__i18n = windowObject && windowObject.wp && windowObject.wp.i18n ? windowObject.wp.i18n : null;

	return defaults;
}

function formatDiagnosticString(template) {
	var replacements = Array.prototype.slice.call(arguments, 1);
	var index = 0;

	return String(template).replace(/%(?:(\d+)\$)?[ds]/g, function (placeholder, position) {
		var replacement;
		if (position) {
			replacement = replacements[Number(position) - 1];
		} else {
			replacement = replacements[index];
			index += 1;
		}
		return String(replacement);
	});
}

function resolveDiagnosticTemplate(strings, key, count) {
	var labels = strings || diagnosticsStrings();
	var numericCount = Number(count);
	var safeCount = Number.isFinite(numericCount) && numericCount >= 0 ? Math.floor(numericCount) : 0;
	var templates = labels[key];
	var availableCounts;
	var fallbackKeys = {
		checksShown: ['checksShownSingular', 'checksShownPlural'],
		showPassed: ['showPassedSingular', 'showPassedPlural'],
		hidePassed: ['hidePassedSingular', 'hidePassedPlural'],
		showInformation: ['showInformationSingular', 'showInformationPlural'],
		hideInformation: ['hideInformationSingular', 'hideInformationPlural'],
		issueCount: ['issueSingular', 'issuePlural'],
		warningCount: ['warningSingular', 'warningPlural'],
		passedCount: ['passedCount', 'passedCount'],
		informationCount: ['informationCount', 'informationCount'],
		listTotal: ['listTotal', 'listTotal'],
	};
	var fallback;
	var pluralPairs = {
		checksShown: ['%d check shown', '%d checks shown'],
		showPassed: ['Show %d passed check', 'Show %d passed checks'],
		hidePassed: ['Hide %d passed check', 'Hide %d passed checks'],
		showInformation: ['Show %d informational check', 'Show %d informational checks'],
		hideInformation: ['Hide %d informational check', 'Hide %d informational checks'],
		issueCount: ['%d issue', '%d issues'],
		warningCount: ['%d warning', '%d warnings'],
		passedCount: ['%d passed', '%d passed'],
		informationCount: ['%d information', '%d information'],
		listTotal: ['%1$s (%2$d total check)', '%1$s (%2$d total checks)'],
	};

	if (templates && typeof templates === 'object') {
		if (typeof templates[safeCount] === 'string') {
			return templates[safeCount];
		}
		availableCounts = Object.keys(templates).filter(function (templateCount) {
			return /^\d+$/.test(templateCount) && typeof templates[templateCount] === 'string';
		}).map(Number).sort(function (left, right) {
			return left - right;
		});
		if (availableCounts.length) {
			fallback = availableCounts.reduce(function (nearest, templateCount) {
				return Math.abs(templateCount - safeCount) < Math.abs(nearest - safeCount) ? templateCount : nearest;
			});
			return templates[fallback];
		}
	}
	if (labels.__i18n && typeof labels.__i18n._n === 'function' && pluralPairs[key]) {
		return labels.__i18n._n(pluralPairs[key][0], pluralPairs[key][1], safeCount, 'siteintelix');
	}

	if (typeof templates === 'string') {
		return templates;
	}
	if (fallbackKeys[key]) {
		fallback = labels[fallbackKeys[key][1 === safeCount ? 0 : 1]];
		if (typeof fallback === 'string') {
			return fallback;
		}
	}
	return '';
}

function buildDisclosureLabel(count, expanded, type, strings) {
	var numericCount = Number(count);
	var safeCount = Number.isFinite(numericCount) && numericCount >= 0 ? Math.floor(numericCount) : 0;
	var labels = strings || diagnosticsStrings();
	var prefix = true === expanded ? 'hide' : 'show';
	var kind = 'information' === type ? 'Information' : 'Passed';

	return formatDiagnosticString(resolveDiagnosticTemplate(labels, prefix + kind, safeCount), safeCount);
}

function shouldVirtualize(count) {
	var numericCount = Number(count);
	return Number.isFinite(numericCount) && numericCount > 200;
}

function nextAccordionState(expanded) {
	var nextExpanded = true !== expanded;

	return {
		expanded: nextExpanded,
		ariaExpanded: nextExpanded ? 'true' : 'false',
		hidden: !nextExpanded,
	};
}

function displayText(value) {
	if (value === null || typeof value === 'undefined') {
		return '';
	}

	if (typeof value === 'object') {
		try {
			return JSON.stringify(value);
		} catch (error) {
			return String(value);
		}
	}

	return String(value);
}

function normalizeDiagnosticRows(rows) {
	var allowedStatuses = {
		danger: true,
		warning: true,
		info: true,
		neutral: true,
		pass: true,
	};

	return (Array.isArray(rows) ? rows : []).filter(function (row) {
		return row !== null && typeof row === 'object' && !Array.isArray(row);
	}).map(function (row) {
		var normalized = {
			label: displayText(row.label),
			value: displayText(row.value),
			status: allowedStatuses[row.status] ? row.status : 'neutral',
			detail: displayText(row.detail),
		};

		if (row.recommended !== null && typeof row.recommended !== 'undefined' && '' !== displayText(row.recommended)) {
			normalized.recommended = displayText(row.recommended);
		}

		return normalized;
	});
}

function statusLabel(status, strings) {
	var labels = {
		danger: strings.criticalIssue,
		warning: strings.warning,
		info: strings.information,
		neutral: strings.information,
		pass: strings.passed,
	};

	return labels[status] || strings.information;
}

function removeChildren(element) {
	while (element && element.firstChild) {
		element.removeChild(element.firstChild);
	}
}

function appendLabeledValue(documentObject, parent, label, value, className) {
	var block = documentObject.createElement('div');
	var heading = documentObject.createElement('strong');
	var output = documentObject.createElement('code');

	block.className = className;
	heading.textContent = label + ': ';
	output.textContent = displayText(value);
	block.appendChild(heading);
	block.appendChild(output);
	parent.appendChild(block);
}

function createRowItem(documentObject, row, position, total, strings) {
	var item = documentObject.createElement('div');
	var header = documentObject.createElement('div');
	var label = documentObject.createElement('strong');
	var status = documentObject.createElement('span');
	var detailButton = documentObject.createElement('button');
	var summary;
	var detail;
	var detailId = 'sitx-serverdiag-row-detail-' + String(position || 1) + '-' + normalizeText(row.label).replace(/[^a-z0-9]+/g, '-');

	item.className = 'sitx-serverdiag-row is-' + (row.status || 'neutral');
	item.setAttribute('role', 'listitem');
	item.setAttribute('data-sitx-diag-row', '');
	if (Number.isFinite(position) && Number.isFinite(total)) {
		item.setAttribute('aria-posinset', String(position));
		item.setAttribute('aria-setsize', String(total));
		item.setAttribute('data-sitx-virtual-index', String(position - 1));
	}
	header.className = 'sitx-serverdiag-row__header';
	label.className = 'sitx-serverdiag-row__label';
	label.textContent = displayText(row.label) || strings.unnamedCheck;
	status.className = 'sitx-serverdiag-row__status';
	status.textContent = statusLabel(row.status, strings);
	header.appendChild(status);
	header.appendChild(label);
	item.appendChild(header);
	appendLabeledValue(documentObject, item, strings.current, row.value, 'sitx-serverdiag-row__current');

	summary = documentObject.createElement('p');
	summary.className = 'sitx-serverdiag-row__summary';
	summary.textContent = displayText(row.detail) || '—';
	item.appendChild(summary);
	detail = documentObject.createElement('div');
	detail.className = 'sitx-serverdiag-row__detail';
	detail.id = detailId;
	detail.hidden = true;
	if (row.detail !== null && typeof row.detail !== 'undefined' && '' !== displayText(row.detail)) {
		var detailText = documentObject.createElement('p');
		detailText.textContent = displayText(row.detail);
		detail.appendChild(detailText);
	}
	appendLabeledValue(documentObject, detail, strings.current, row.value, 'sitx-serverdiag-row__detail-value');
	if (row.recommended !== null && typeof row.recommended !== 'undefined' && '' !== displayText(row.recommended)) {
		appendLabeledValue(documentObject, detail, strings.recommended, row.recommended, 'sitx-serverdiag-row__detail-value');
	}
	detailButton.type = 'button';
	detailButton.className = 'sitx-serverdiag-row__action';
	detailButton.setAttribute('data-sitx-diag-row-details', '');
	detailButton.setAttribute('aria-controls', detailId);
	detailButton.setAttribute('aria-expanded', 'false');
	detailButton.textContent = strings.details;
	detailButton.addEventListener('click', function () {
		var expanded = 'true' === detailButton.getAttribute('aria-expanded');
		detailButton.setAttribute('aria-expanded', expanded ? 'false' : 'true');
		if (detail) {
			detail.hidden = expanded;
		}
	});
	item.appendChild(detailButton);
	item.appendChild(detail);

	return item;
}

function appendRows(documentObject, container, rows, strings) {
	rows.forEach(function (row, index) {
		container.appendChild(createRowItem(documentObject, row, index + 1, rows.length, strings));
	});
}

function renderVirtualRows(documentObject, container, rows, label, windowObject, strings) {
	var fallbackHeight = 40;
	var overscan = 5;
	var framePending = false;
	var heights = rows.map(function () { return fallbackHeight; });
	var observer = null;
	var rafId = null;
	var destroyed = false;
	var requestFrame = windowObject && windowObject.requestAnimationFrame ? windowObject.requestAnimationFrame.bind(windowObject) : function (callback) {
		return windowObject && windowObject.setTimeout ? windowObject.setTimeout(callback, 16) : setTimeout(callback, 16);
	};
	var cancelFrame = windowObject && windowObject.cancelAnimationFrame ? windowObject.cancelAnimationFrame.bind(windowObject) : function (id) {
		if (windowObject && windowObject.clearTimeout) {
			windowObject.clearTimeout(id);
		} else {
			clearTimeout(id);
		}
	};
	var scheduleRender = function () {
		if (!destroyed && !framePending) {
			framePending = true;
			var requestedId = requestFrame(function () {
				rafId = null;
				if (!destroyed) {
					renderWindow();
				}
			});
			if (framePending) {
				rafId = requestedId;
			}
		}
	};
	var heightFor = function (element) {
		var measured = Number(element && element.offsetHeight);
		return Number.isFinite(measured) && measured > 0 ? measured : fallbackHeight;
	};
	var measureElement = function (element) {
		var index = Number(element.getAttribute('data-sitx-virtual-index'));
		var measured = heightFor(element);
		if (Number.isFinite(index) && index >= 0 && index < heights.length && heights[index] !== measured) {
			heights[index] = measured;
			return true;
		}
		return false;
	};
	var renderWindow = function () {
		if (destroyed) {
			return;
		}
		var prefix = [0];
		var scrollTop = Math.max(0, Number(container.scrollTop) || 0);
		var viewportBottom = scrollTop + (Number(container.clientHeight) || 320);
		var firstVisible = 0;
		var lastVisible = rows.length;
		var start;
		var end;
		var topSpacer = documentObject.createElement('div');
		var bottomSpacer = documentObject.createElement('div');
		var measuredWithoutObserver = false;

		heights.forEach(function (height) {
			prefix.push(prefix[prefix.length - 1] + height);
		});
		while (firstVisible < rows.length && prefix[firstVisible + 1] <= scrollTop) {
			firstVisible += 1;
		}
		lastVisible = firstVisible;
		while (lastVisible < rows.length && prefix[lastVisible] < viewportBottom) {
			lastVisible += 1;
		}
		start = Math.max(0, firstVisible - overscan);
		end = Math.min(rows.length, lastVisible + overscan);

		if (observer) {
			observer.disconnect();
		}
		removeChildren(container);
		topSpacer.className = 'sitx-serverdiag-virtual-spacer';
		topSpacer.setAttribute('aria-hidden', 'true');
		topSpacer.style.height = prefix[start] + 'px';
		bottomSpacer.className = 'sitx-serverdiag-virtual-spacer';
		bottomSpacer.setAttribute('aria-hidden', 'true');
		bottomSpacer.style.height = (prefix[rows.length] - prefix[end]) + 'px';
		container.appendChild(topSpacer);
		rows.slice(start, end).forEach(function (row, relativeIndex) {
			var index = start + relativeIndex;
			var item = createRowItem(documentObject, row, index + 1, rows.length, strings);
			container.appendChild(item);
			if (observer) {
				observer.observe(item);
			} else if (measureElement(item)) {
				measuredWithoutObserver = true;
			}
		});
		container.appendChild(bottomSpacer);
		framePending = false;
		if (measuredWithoutObserver) {
			scheduleRender();
		}
	};

	if (windowObject && typeof windowObject.ResizeObserver === 'function') {
		observer = new windowObject.ResizeObserver(function (entries) {
			var changed = false;
			if (destroyed) {
				return;
			}
			Array.prototype.forEach.call(entries || [], function (entry) {
				if (entry && entry.target && measureElement(entry.target)) {
					changed = true;
				}
			});
			if (changed) {
				scheduleRender();
			}
		});
	}

	container.classList.add('sitx-serverdiag-virtual-list');
	container.setAttribute('role', 'list');
	container.setAttribute('aria-label', formatDiagnosticString(resolveDiagnosticTemplate(strings, 'listTotal', rows.length), label, rows.length));
	container.style.maxHeight = '320px';
	container.style.overflowY = 'auto';
	var scrollHandler = function () {
		scheduleRender();
	};
	var virtualState = {
		observer: observer,
		scrollHandler: scrollHandler,
		rafId: null,
		destroy: function () {
			if (destroyed) {
				return;
			}
			destroyed = true;
			if (observer) {
				observer.disconnect();
			}
			container.removeEventListener('scroll', scrollHandler);
			if (rafId !== null) {
				cancelFrame(rafId);
			}
			rafId = null;
			virtualState.rafId = null;
		},
	};
	container.addEventListener('scroll', scrollHandler);
	renderWindow();
	Object.defineProperty(virtualState, 'rafId', {
		get: function () { return rafId; },
		set: function (value) { rafId = value; },
		configurable: true,
	});
	return virtualState;
}

function renderRowCollection(documentObject, container, rows, label, windowObject, strings, state) {
	removeChildren(container);
	container.scrollTop = 0;
	if (!state.printRendering && shouldVirtualize(rows.length)) {
		state.virtualRenders.push(renderVirtualRows(documentObject, container, rows, label, windowObject, strings));
		return;
	}
	container.setAttribute('role', 'list');
	container.setAttribute('aria-label', formatDiagnosticString(resolveDiagnosticTemplate(strings, 'listTotal', rows.length), label, rows.length));
	appendRows(documentObject, container, rows, strings);
}

function createDisclosure(documentObject, parent, rows, type, state, windowObject, strings) {
	var button = documentObject.createElement('button');
	var list = documentObject.createElement('div');
	var stateKey = 'information' === type ? 'informationExpanded' : 'passedExpanded';
	var listId = state.panel.id + '-' + type;
	var renderList = function () {
		renderRowCollection(documentObject, list, rows, 'information' === type ? strings.informationListLabel : strings.passedListLabel, windowObject, strings, state);
	};

	button.type = 'button';
	button.className = 'sitx-serverdiag-disclosure';
	button.setAttribute('aria-controls', listId);
	button.setAttribute('aria-expanded', state[stateKey] ? 'true' : 'false');
	button.textContent = buildDisclosureLabel(rows.length, state[stateKey], type, strings);
	list.id = listId;
	list.className = 'sitx-serverdiag-disclosure__content';
	list.hidden = !state[stateKey];
	if (state[stateKey]) {
		renderList();
	}
	button.addEventListener('click', function () {
		state[stateKey] = !state[stateKey];
		button.setAttribute('aria-expanded', state[stateKey] ? 'true' : 'false');
		button.textContent = buildDisclosureLabel(rows.length, state[stateKey], type, strings);
		list.hidden = !state[stateKey];
		if (state[stateKey] && !list.firstChild) {
			renderList();
		}
	});
	parent.appendChild(button);
	parent.appendChild(list);
}

function renderSectionRows(documentObject, state, rows, windowObject, strings) {
	var view = applyRowView(rows, {
		filter: 'all',
		query: '',
		hidePassed: false,
		sort: state.sort || 'severity',
		expanded: state.showAll || state.printRendering,
		limit: 10,
	});
	var tableHeader = documentObject.createElement('div');
	var list = documentObject.createElement('div');
	var showAllButton;

	state.virtualRenders.forEach(function (virtualState) {
		virtualState.destroy();
	});
	state.virtualRenders = [];
	removeChildren(state.panel);
	tableHeader.className = 'sitx-serverdiag-checks-header';
	[
		strings.statusColumn,
		strings.checkColumn,
		strings.current,
		strings.descriptionColumn,
		strings.actionColumn,
	].forEach(function (heading) {
		var cell = documentObject.createElement('span');
		cell.textContent = heading;
		tableHeader.appendChild(cell);
	});
	state.panel.appendChild(tableHeader);
	list.className = 'sitx-serverdiag-checks-table';
	if (state.showAll && shouldVirtualize(view.rows.length) && !state.printRendering) {
		state.virtualRenders.push(renderVirtualRows(documentObject, list, view.rows, strings.checkColumn, windowObject, strings));
	} else {
		renderRowCollection(documentObject, list, view.visible, strings.checkColumn, windowObject, strings, state);
	}
	state.panel.appendChild(list);

	if (view.rows.length > 10) {
		showAllButton = documentObject.createElement('button');
		showAllButton.type = 'button';
		showAllButton.className = 'sitx-serverdiag-show-all';
		showAllButton.setAttribute('data-sitx-diag-show-all', '');
		showAllButton.textContent = state.showAll ? strings.showFewer : strings.showAllChecks + ' (' + view.rows.length + ')';
		showAllButton.addEventListener('click', function () {
			state.showAll = !state.showAll;
			state.renderKey = null;
			renderSectionRows(documentObject, state, rows, windowObject, strings);
		});
		state.panel.appendChild(showAllButton);
	}

	if (!view.rows.length) {
		var empty = documentObject.createElement('p');
		empty.className = 'sitx-serverdiag-section__empty';
		empty.textContent = strings.sectionNoMatches;
		state.panel.appendChild(empty);
	}

	state.panel.setAttribute('data-rendered', 'true');
}

function countSummaryText(summary, strings) {
	return formatDiagnosticString(resolveDiagnosticTemplate(strings, 'issueCount', summary.failed), summary.failed) + ', ' +
		formatDiagnosticString(resolveDiagnosticTemplate(strings, 'warningCount', summary.warnings), summary.warnings) + ', ' +
		formatDiagnosticString(resolveDiagnosticTemplate(strings, 'passedCount', summary.passed), summary.passed) + ', ' +
		formatDiagnosticString(resolveDiagnosticTemplate(strings, 'informationCount', summary.information), summary.information);
}

function initDiagnosticsPage(diagnosticsPage, documentObject, windowObject) {
	if (!diagnosticsPage || 'true' === diagnosticsPage.getAttribute('data-sitx-diag-initialized')) {
		return;
	}
	diagnosticsPage.setAttribute('data-sitx-diag-initialized', 'true');
	var filterButtons = Array.prototype.slice.call(diagnosticsPage.querySelectorAll('[data-sitx-diag-filter]'));
	var searchInput = diagnosticsPage.querySelector('[data-sitx-diag-search]');
	var hidePassedInput = diagnosticsPage.querySelector('[data-sitx-diag-hide-passed]');
	var sortSelect = diagnosticsPage.querySelector('[data-sitx-diag-sort]');
	var expandAll = diagnosticsPage.querySelector('[data-sitx-diag-expand-all]');
	var collapseAll = diagnosticsPage.querySelector('[data-sitx-diag-collapse-all]');
	var sectionControls = diagnosticsPage.querySelector('[data-sitx-diag-section-controls]');
	var mobileExpandAll = diagnosticsPage.querySelector('[data-sitx-diag-mobile-expand-all]');
	var mobileCollapseAll = diagnosticsPage.querySelector('[data-sitx-diag-mobile-collapse-all]');
	var copyButton = diagnosticsPage.querySelector('[data-sitx-diag-copy-system-info]');
	var mobileCopyButton = diagnosticsPage.querySelector('[data-sitx-diag-copy-system-info-mobile]');
	var refreshButton = diagnosticsPage.querySelector('[data-sitx-diag-refresh]');
	var refreshStatus = diagnosticsPage.querySelector('[data-sitx-diag-refresh-status]');
	var liveRegion = diagnosticsPage.querySelector('[data-sitx-diag-live]');
	var main = diagnosticsPage.querySelector('.sitx-serverdiag-main');
	var currentFilter = 'all';
	var currentQuery = '';
	var hidePassed = false;
	var currentSort = 'severity';
	var sections = [];
	var noResults;
	var strings = diagnosticsStrings(windowObject);
	var printSnapshot = null;

	if (refreshButton) {
		refreshButton.addEventListener('click', function () {
			var data = windowObject.siteintelixDiagnosticsData || {};
			var body = new URLSearchParams();
			body.set('action', 'siteintelix_refresh_server_diagnostics');
			body.set('nonce', data.refreshNonce || '');
			refreshButton.disabled = true;
			refreshButton.setAttribute('aria-busy', 'true');
			if (refreshStatus) {
				refreshStatus.classList.remove('has-error');
				refreshStatus.textContent = strings.refreshing;
			}

			windowObject.fetch(data.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
			}).then(function (response) {
				return response.json();
			}).then(function (response) {
				if (!response || !response.success) {
					throw new Error('Diagnostics refresh failed');
				}
				if (refreshStatus) {
					refreshStatus.textContent = strings.refreshComplete;
				}
				windowObject.setTimeout(function () {
					windowObject.location.reload();
				}, 350);
			}).catch(function () {
				refreshButton.disabled = false;
				refreshButton.removeAttribute('aria-busy');
				if (refreshStatus) {
					refreshStatus.classList.add('has-error');
					refreshStatus.textContent = strings.refreshFailed;
				}
			});
		});
	}

	function announce(message) {
		if (liveRegion) {
			liveRegion.textContent = message;
		}
	}

	function parseSection(state) {
		var parsed;
		if (state.rows !== null || state.failed) {
			return !state.failed;
		}
		try {
			if (!state.payload) {
				throw new Error('Missing diagnostics payload');
			}
			parsed = JSON.parse(state.payload.textContent || '');
			if (!Array.isArray(parsed)) {
				throw new Error('Invalid diagnostics payload');
			}
			state.rows = orderRows(normalizeDiagnosticRows(parsed));
			return true;
		} catch (error) {
			state.failed = true;
			state.element.classList.add('has-render-error');
			state.panel.setAttribute('data-render-failed', 'true');
			state.panel.textContent = strings.sectionLoadError;
			announce(strings.sectionLoadError);
			return false;
		}
	}

	function matchingRows(state) {
		var matched;
		if (!parseSection(state)) {
			return null;
		}
		matched = filterRows(state.rows, currentFilter, currentQuery);
		if (hidePassed && 'passed' !== currentFilter) {
			matched = matched.filter(function (row) {
				return 'pass' !== row.status;
			});
		}
		matched = sortRows(matched, currentSort);
		state.sort = currentSort;
		state.match = sectionMatchSummary(matched, 'all', '');
		return state.match;
	}

	function renderState(state) {
		var summary = matchingRows(state);
		var renderKey;
		if (!summary) {
			return;
		}
		renderKey = currentFilter + '\n' + currentQuery + '\n' + String(hidePassed) + '\n' + currentSort + '\n' + String(state.showAll);
		if (state.renderKey === renderKey && 'true' === state.panel.getAttribute('data-rendered')) {
			return;
		}
		renderSectionRows(documentObject, state, summary.rows, windowObject, strings);
		state.renderKey = renderKey;
	}

	function setSectionOpen(state, open) {
		var next = true === open ? { expanded: true, ariaExpanded: 'true', hidden: false } :
			{ expanded: false, ariaExpanded: 'false', hidden: true };
		state.expanded = next.expanded;
		state.toggle.setAttribute('aria-expanded', next.ariaExpanded);
		state.panel.hidden = next.hidden;
		state.element.classList.toggle('is-open', next.expanded);
		if (state.label) {
			state.toggle.setAttribute(
				'aria-label',
				formatDiagnosticString(next.expanded ? strings.collapseLabel : strings.expandLabel, state.label)
			);
		}
		if (next.expanded) {
			renderState(state);
		}
	}

	function expandAllSections() {
		sections.forEach(function (state) {
			if (!state.element.hidden) {
				setSectionOpen(state, true);
			}
		});
	}

	function collapseAllSections() {
		sections.forEach(function (state) {
			setSectionOpen(state, false);
		});
	}

	function prepareForPrint() {
		if (printSnapshot) {
			return;
		}
		printSnapshot = sections.map(function (state) {
			return {
				elementHidden: state.element.hidden,
				expanded: state.expanded,
				informationExpanded: state.informationExpanded,
				passedExpanded: state.passedExpanded,
			};
		});
		sections.forEach(function (state) {
			state.printRendering = true;
			state.informationExpanded = true;
			state.passedExpanded = true;
			state.element.hidden = false;
			setSectionOpen(state, true);
			if (parseSection(state)) {
				renderSectionRows(documentObject, state, state.rows, windowObject, strings);
				state.renderKey = '__print__';
			}
		});
	}

	function restoreAfterPrint() {
		if (!printSnapshot) {
			return;
		}
		sections.forEach(function (state, index) {
			var snapshot = printSnapshot[index];
			state.printRendering = false;
			state.informationExpanded = snapshot.informationExpanded;
			state.passedExpanded = snapshot.passedExpanded;
			state.renderKey = null;
			setSectionOpen(state, snapshot.expanded);
			state.element.hidden = snapshot.elementHidden;
		});
		printSnapshot = null;
	}

	function closeSectionControls(button) {
		var focusTarget = button;
		var summary;
		if (sectionControls) {
			sectionControls.open = false;
			summary = sectionControls.querySelector('summary');
			if (summary) {
				focusTarget = summary;
			}
		}
		if (focusTarget && typeof focusTarget.focus === 'function') {
			focusTarget.focus();
		}
	}

	function ensureNoResults() {
		var reset;
		noResults = diagnosticsPage.querySelector('[data-sitx-diag-no-results]');
		if (noResults || !main) {
			return;
		}
		noResults = documentObject.createElement('div');
		noResults.className = 'si-card sitx-serverdiag-no-results';
		noResults.setAttribute('data-sitx-diag-no-results', '');
		noResults.setAttribute('role', 'status');
		noResults.hidden = true;
		var message = documentObject.createElement('p');
		message.textContent = strings.noResults;
		reset = documentObject.createElement('button');
		reset.type = 'button';
		reset.className = 'si-button';
		reset.textContent = strings.resetFilters;
		reset.addEventListener('click', function () {
			currentFilter = 'all';
			currentQuery = '';
			if (searchInput) {
				searchInput.value = '';
			}
			applyFilters();
		});
		noResults.appendChild(message);
		noResults.appendChild(reset);
		main.parentNode.insertBefore(noResults, main);
	}

	function applyFilters() {
		var total = 0;
		var failedSections = 0;
		filterButtons.forEach(function (button) {
			var active = button.getAttribute('data-sitx-diag-filter') === currentFilter;
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('is-active', active);
		});
		sections.forEach(function (state) {
			var summary = matchingRows(state);
			if (!summary) {
				failedSections += 1;
				state.element.hidden = false;
				return;
			}
			total += summary.total;
			state.element.hidden = 0 === summary.total;
			state.element.setAttribute('data-match-count', String(summary.total));
			if (state.counts) {
				state.counts.textContent = countSummaryText(summary, strings);
			}
			if (state.expanded) {
				state.panel.scrollTop = 0;
				state.renderKey = null;
				renderState(state);
			}
		});
		ensureNoResults();
		if (noResults) {
			noResults.hidden = 0 !== total || 0 !== failedSections;
		}
		announce(formatDiagnosticString(resolveDiagnosticTemplate(strings, 'checksShown', total), total) +
			(failedSections ? '. ' + strings.partialLoad : ''));
	}

	Array.prototype.slice.call(diagnosticsPage.querySelectorAll('[data-sitx-diag-section]')).forEach(function (element) {
		var toggle = element.querySelector('[data-sitx-diag-toggle]');
		var panel = element.querySelector('[data-sitx-diag-panel]');
		var state;
		if (!toggle || !panel) {
			return;
		}
		state = {
			element: element,
			toggle: toggle,
			panel: panel,
			payload: element.querySelector('[data-sitx-diag-payload]'),
			counts: element.querySelector('.sitx-serverdiag-section__counts'),
			rows: null,
			match: null,
			failed: false,
			expanded: false,
			informationExpanded: false,
			passedExpanded: false,
			showAll: false,
			sort: 'severity',
			renderKey: null,
			virtualRenders: [],
			printRendering: false,
			label: (function () {
				var heading = toggle.querySelector('.sitx-serverdiag-section__heading strong');
				return (heading ? heading.textContent : toggle.textContent || '').trim();
			}()),
		};
		sections.push(state);
		toggle.setAttribute('aria-expanded', 'false');
		panel.hidden = true;
		element.classList.remove('is-open');
		if (state.label) {
			toggle.setAttribute('aria-label', formatDiagnosticString(strings.expandLabel, state.label));
		}
		toggle.addEventListener('click', function () {
			var next = nextAccordionState(state.expanded);
			setSectionOpen(state, next.expanded);
		});
	});

	sections.some(function (state) {
		if ('true' !== state.element.getAttribute('data-sitx-diag-default-open')) {
			return false;
		}
		setSectionOpen(state, true);
		return true;
	});

	function resetRowLimits() {
		sections.forEach(function (state) {
			state.showAll = false;
			state.renderKey = null;
		});
	}

	filterButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			currentFilter = button.getAttribute('data-sitx-diag-filter') || 'all';
			if ('passed' === currentFilter && hidePassedInput) {
				hidePassed = false;
				hidePassedInput.checked = false;
			}
			resetRowLimits();
			applyFilters();
		});
	});
	if (searchInput) {
		searchInput.addEventListener('input', function () {
			currentQuery = (searchInput.value || '').trim();
			resetRowLimits();
			applyFilters();
		});
	}
	if (hidePassedInput) {
		hidePassedInput.addEventListener('change', function () {
			hidePassed = true === hidePassedInput.checked;
			resetRowLimits();
			applyFilters();
		});
	}
	if (sortSelect) {
		sortSelect.addEventListener('change', function () {
			currentSort = ['severity', 'name', 'status'].indexOf(sortSelect.value) !== -1 ? sortSelect.value : 'severity';
			resetRowLimits();
			applyFilters();
		});
	}
	if (expandAll) {
		expandAll.addEventListener('click', expandAllSections);
	}
	if (collapseAll) {
		collapseAll.addEventListener('click', collapseAllSections);
	}
	if (mobileExpandAll) {
		mobileExpandAll.addEventListener('click', function () {
			expandAllSections();
			closeSectionControls(mobileExpandAll);
		});
	}
	if (mobileCollapseAll) {
		mobileCollapseAll.addEventListener('click', function () {
			collapseAllSections();
			closeSectionControls(mobileCollapseAll);
		});
	}
	if (windowObject && typeof windowObject.addEventListener === 'function') {
		windowObject.addEventListener('beforeprint', prepareForPrint);
		windowObject.addEventListener('afterprint', restoreAfterPrint);
	}
	function bindCopyButton(button) {
		if (!button) {
			return;
		}
		button.addEventListener('click', function () {
			var sourceId = button.getAttribute('aria-controls');
			var source = sourceId ? documentObject.getElementById(sourceId) : null;
			var report = source ? source.value : '';
			var result;
			var succeeded = function () {
				announce(strings.copySuccess);
			};
			var failed = function () {
				announce(strings.copyFailure);
			};
			if (!source) {
				announce(strings.systemInfoFallback);
				return;
			}
			if (windowObject.navigator && windowObject.navigator.clipboard && windowObject.navigator.clipboard.writeText) {
				try {
					result = windowObject.navigator.clipboard.writeText(report);
					if (result && typeof result.then === 'function') {
						result.then(succeeded, failed);
					} else {
						succeeded();
					}
				} catch (error) {
					failed();
				}
				return;
			}
			var textarea = documentObject.createElement('textarea');
			var previousActiveElement = documentObject.activeElement;
			var appended = false;
			try {
				textarea.value = report;
				textarea.setAttribute('readonly', '');
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				documentObject.body.appendChild(textarea);
				appended = true;
				textarea.select();
				if (documentObject.execCommand('copy')) {
					succeeded();
				} else {
					failed();
				}
			} catch (error) {
				failed();
			} finally {
				if (appended && textarea.parentNode) {
					textarea.parentNode.removeChild(textarea);
				}
				if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
					previousActiveElement.focus();
				}
			}
		});
	}
	bindCopyButton(copyButton);
	bindCopyButton(mobileCopyButton);

	diagnosticsPage.classList.add('is-enhanced');
	ensureNoResults();
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		normalizeText: normalizeText,
		filterRows: filterRows,
		orderRows: orderRows,
		sortRows: sortRows,
		applyRowView: applyRowView,
		visibleRange: visibleRange,
		partitionRows: partitionRows,
		sectionMatchSummary: sectionMatchSummary,
		formatDiagnosticString: formatDiagnosticString,
		resolveDiagnosticTemplate: resolveDiagnosticTemplate,
		buildDisclosureLabel: buildDisclosureLabel,
		shouldVirtualize: shouldVirtualize,
		nextAccordionState: nextAccordionState,
		normalizeDiagnosticRows: normalizeDiagnosticRows,
		initDiagnosticsPage: initDiagnosticsPage,
	};
}

	if (typeof document === 'undefined') {
		return;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var diagnosticsPage = document.getElementById('siteintelix-server-diagnostics-page');

		if (!diagnosticsPage) {
			return;
		}

		initDiagnosticsPage(diagnosticsPage, document, window);
	});
}());
