import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const {
	normalizeText,
	filterRows,
	orderRows,
	visibleRange,
	partitionRows,
	sectionMatchSummary,
	formatDiagnosticString,
	resolveDiagnosticTemplate,
	buildDisclosureLabel,
	shouldVirtualize,
	nextAccordionState,
	normalizeDiagnosticRows,
	initDiagnosticsPage,
} = require('../assets/admin/js/siteintelix-server-diagnostics.js');

const rows = [
	{ label: 'PHP', value: '8.3', status: 'pass', detail: 'Modern PHP' },
	{ label: 'Memory limit', value: '128M', status: 'warning', detail: 'Use 256M' },
	{ label: 'File uploads', value: false, status: 'danger' },
	{ label: 'SAPI', value: 'fpm-fcgi', status: 'info' },
	{ label: 'Environment', value: null, status: 'neutral', recommended: 'Production' },
];

test('filterRows combines warning status and label search', () => {
	assert.deepEqual(filterRows(rows, 'warnings', 'memory'), [rows[1]]);
});

test('filterRows searches detail case-insensitively', () => {
	assert.deepEqual(filterRows(rows, 'all', '256m'), [rows[1]]);
});

test('filterRows maps each named filter to its statuses', () => {
	assert.deepEqual(filterRows(rows, 'issues', ''), [rows[2]]);
	assert.deepEqual(filterRows(rows, 'warnings', ''), [rows[1]]);
	assert.deepEqual(filterRows(rows, 'passed', ''), [rows[0]]);
	assert.deepEqual(filterRows(rows, 'information', ''), [rows[3], rows[4]]);
	assert.deepEqual(filterRows(rows, 'unknown', ''), rows);
});

test('filterRows handles nonstring values without mutating rows', () => {
	const snapshot = structuredClone(rows);
	assert.deepEqual(filterRows(rows, 'all', 'false'), [rows[2]]);
	assert.deepEqual(rows, snapshot);
});

test('filterRows skips malformed rows and safely searches circular fields', () => {
	const circular = {};
	circular.self = circular;
	const valid = { label: 'Circular data', value: circular, status: 'info' };

	assert.doesNotThrow(() => filterRows([null, false, 42, 'row', valid], 'all', 'object'));
	assert.deepEqual(filterRows([null, false, 42, 'row', valid], 'all', 'object'), [valid]);
});

test('normalizeText safely normalizes null, arrays, and objects', () => {
	assert.equal(normalizeText(null), '');
	assert.equal(normalizeText(['PHP', 8]), '["php",8]');
	assert.equal(normalizeText({ Mode: 'Production' }), '{"mode":"production"}');
	const circular = {};
	circular.self = circular;
	assert.equal(normalizeText(circular), '[object object]');
});

test('orderRows sorts by severity stably and does not mutate input', () => {
	const unordered = [rows[0], rows[3], rows[1], rows[4], rows[2], { label: 'Cache', status: 'warning' }];
	const snapshot = unordered.slice();
	assert.deepEqual(orderRows(unordered).map((row) => row.label), [
		'File uploads',
		'Memory limit',
		'Cache',
		'SAPI',
		'Environment',
		'PHP',
	]);
	assert.deepEqual(unordered, snapshot);
});

test('orderRows skips malformed rows while preserving valid stable order', () => {
	const warning = { label: 'First warning', status: 'warning' };
	const secondWarning = { label: 'Second warning', status: 'warning' };

	assert.deepEqual(orderRows([null, warning, 12, secondWarning, false]), [warning, secondWarning]);
});

test('visibleRange calculates an overscanned end-exclusive window', () => {
	assert.deepEqual(visibleRange(500, 40, 400, 320, 5), { start: 5, end: 23 });
});

test('visibleRange clamps boundaries and invalid totals', () => {
	assert.deepEqual(visibleRange(10, 40, 0, 80, 5), { start: 0, end: 7 });
	assert.deepEqual(visibleRange(10, 40, 1000, 80, 5), { start: 10, end: 10 });
	assert.deepEqual(visibleRange(0, 40, 0, 80, 5), { start: 0, end: 0 });
	assert.deepEqual(visibleRange(-10, 0, -5, -1, -2), { start: 0, end: 0 });
});

test('visibleRange rejects each invalid total and floors a finite total', () => {
	for (const total of [NaN, Infinity, -Infinity, -1]) {
		assert.deepEqual(visibleRange(total, 10, 0, 10, 0), { start: 0, end: 0 }, String(total));
	}
	assert.deepEqual(visibleRange(10.9, 10, 100, 20, 0), { start: 10, end: 10 });
});

test('visibleRange rejects each invalid row height', () => {
	for (const rowHeight of [0, -1, NaN, Infinity, -Infinity]) {
		assert.deepEqual(visibleRange(10, rowHeight, 0, 10, 0), { start: 0, end: 0 }, String(rowHeight));
	}
});

test('visibleRange normalizes invalid scroll positions to zero', () => {
	for (const scrollTop of [NaN, Infinity, -Infinity, -1]) {
		assert.deepEqual(visibleRange(10, 10, scrollTop, 10, 0), { start: 0, end: 1 }, String(scrollTop));
	}
});

test('visibleRange normalizes invalid viewport heights to zero', () => {
	for (const viewportHeight of [NaN, Infinity, -Infinity, -1]) {
		assert.deepEqual(visibleRange(10, 10, 20, viewportHeight, 0), { start: 2, end: 2 }, String(viewportHeight));
	}
});

test('visibleRange normalizes invalid overscan to zero and floors fractions', () => {
	for (const overscan of [NaN, Infinity, -Infinity, -1]) {
		assert.deepEqual(visibleRange(10, 10, 20, 10, overscan), { start: 2, end: 3 }, String(overscan));
	}
	assert.deepEqual(visibleRange(20, 10, 50, 10, 2.9), { start: 3, end: 8 });
});

test('visibleRange respects exact row boundaries and clamps at the bottom', () => {
	assert.deepEqual(visibleRange(10, 10, 40, 20, 0), { start: 4, end: 6 });
	assert.deepEqual(visibleRange(10, 10, 80, 40, 2), { start: 6, end: 10 });
});

test('partitionRows groups severities in display order and preserves stable row order', () => {
	const firstInfo = { label: 'SAPI', status: 'info' };
	const neutral = { label: 'Environment', status: 'neutral' };
	const danger = { label: 'Uploads', status: 'danger' };
	const warning = { label: 'Memory', status: 'warning' };
	const pass = { label: 'PHP', status: 'pass' };
	const secondInfo = { label: 'Transport', status: 'info' };
	const result = partitionRows([firstInfo, null, pass, danger, warning, neutral, 42, secondInfo]);

	assert.deepEqual(result.problems, [danger, warning]);
	assert.deepEqual(result.information, [firstInfo, neutral, secondInfo]);
	assert.deepEqual(result.passed, [pass]);
	assert.deepEqual(result.ordered, [danger, warning, firstInfo, neutral, secondInfo, pass]);
});

test('partitionRows is malformed-safe and keeps unknown valid statuses with information', () => {
	const unknown = { label: 'Future status', status: 'future' };
	assert.doesNotThrow(() => partitionRows('not rows'));
	assert.deepEqual(partitionRows('not rows'), {
		problems: [],
		information: [],
		passed: [],
		ordered: [],
	});
	assert.deepEqual(partitionRows([false, [], unknown]).information, [unknown]);
});

test('sectionMatchSummary combines status and query and reports exact status counts', () => {
	assert.deepEqual(sectionMatchSummary(rows, 'all', 'm'), {
		total: 4,
		failed: 0,
		warnings: 1,
		passed: 1,
		information: 2,
		rows: [rows[0], rows[1], rows[3], rows[4]],
	});
	assert.deepEqual(sectionMatchSummary(rows, 'information', 'production'), {
		total: 1,
		failed: 0,
		warnings: 0,
		passed: 0,
		information: 1,
		rows: [rows[4]],
	});
});

test('sectionMatchSummary safely ignores malformed and unknown-status rows in counts', () => {
	const unknown = { label: 'Future', status: 'future' };
	assert.deepEqual(sectionMatchSummary([null, unknown], 'all', ''), {
		total: 1,
		failed: 0,
		warnings: 0,
		passed: 0,
		information: 1,
		rows: [unknown],
	});
});

test('buildDisclosureLabel uses singular and plural Show labels', () => {
	assert.equal(buildDisclosureLabel(1, false, 'passed'), 'Show 1 passed check');
	assert.equal(buildDisclosureLabel(2, false, 'passed'), 'Show 2 passed checks');
	assert.equal(buildDisclosureLabel(1, false, 'information'), 'Show 1 informational check');
	assert.equal(buildDisclosureLabel(12, false, 'information'), 'Show 12 informational checks');
});

test('buildDisclosureLabel uses matching Hide labels when expanded', () => {
	assert.equal(buildDisclosureLabel(1, true, 'passed'), 'Hide 1 passed check');
	assert.equal(buildDisclosureLabel(4, true, 'passed'), 'Hide 4 passed checks');
	assert.equal(buildDisclosureLabel(1, true, 'information'), 'Hide 1 informational check');
	assert.equal(buildDisclosureLabel(11, true, 'information'), 'Hide 11 informational checks');
});

test('count-indexed templates select the locale-provided form for 0, 1, 2, and 5', () => {
	const strings = {
		showPassed: [
			'zero-form %d',
			'one-form %d',
			'two-form %d',
			'three-form %d',
			'four-form %d',
			'five-form %d',
		],
	};

	for (const count of [0, 1, 2, 5]) {
		assert.equal(resolveDiagnosticTemplate(strings, 'showPassed', count), `${['zero', 'one', 'two', 'three', 'four', 'five'][count]}-form %d`);
		assert.equal(buildDisclosureLabel(count, false, 'passed', strings), `${['zero', 'one', 'two', 'three', 'four', 'five'][count]}-form ${count}`);
	}
});

test('count-indexed template resolver falls back to the nearest available count', () => {
	assert.equal(resolveDiagnosticTemplate({ checksShown: ['zero %d', 'one %d', 'last %d'] }, 'checksShown', 5), 'last %d');
	assert.equal(resolveDiagnosticTemplate({ checksShown: { 2: 'two %d', 5: 'five %d' } }, 'checksShown', 0), 'two %d');
	assert.equal(resolveDiagnosticTemplate({ checksShown: { 2: 'two %d', 5: 'five %d' } }, 'checksShown', 4), 'five %d');
});

test('diagnostic formatter supports sequential and positional placeholders', () => {
	assert.equal(formatDiagnosticString('%d %s', 5, 'checks'), '5 checks');
	assert.equal(formatDiagnosticString('%2$s: %1$d', 5, 'checks'), 'checks: 5');
});

test('shouldVirtualize starts above the 200-row threshold', () => {
	assert.equal(shouldVirtualize(200), false);
	assert.equal(shouldVirtualize(201), true);
	assert.equal(shouldVirtualize('201'), true);
	assert.equal(shouldVirtualize(-1), false);
	assert.equal(shouldVirtualize(Infinity), false);
});

test('nextAccordionState drives expanded and collapsed ARIA state without coercion surprises', () => {
	assert.deepEqual(nextAccordionState(false), { expanded: true, ariaExpanded: 'true', hidden: false });
	assert.deepEqual(nextAccordionState(true), { expanded: false, ariaExpanded: 'false', hidden: true });
	assert.deepEqual(nextAccordionState('false'), { expanded: true, ariaExpanded: 'true', hidden: false });
});

test('normalizeDiagnosticRows keeps safe display fields and strips unsafe status and actions', () => {
	assert.deepEqual(normalizeDiagnosticRows([
		{
			label: '<img src=x>',
			value: false,
			status: 'danger injected-class',
			detail: null,
			recommended: { minimum: '256M' },
			action: { label: 'Fix', url: 'javascript:alert(1)' },
		},
		null,
	]), [{
		label: '<img src=x>',
		value: 'false',
		status: 'neutral',
		detail: '',
		recommended: '{"minimum":"256M"}',
	}]);
});

class FakeClassList {
	constructor(element) {
		this.element = element;
	}

	values() {
		return this.element.className.split(/\s+/).filter(Boolean);
	}

	add(...names) {
		this.element.className = [...new Set([...this.values(), ...names])].join(' ');
	}

	remove(...names) {
		this.element.className = this.values().filter((name) => !names.includes(name)).join(' ');
	}

	toggle(name, force) {
		const present = this.values().includes(name);
		const enabled = typeof force === 'boolean' ? force : !present;
		if (enabled) this.add(name); else this.remove(name);
		return enabled;
	}

	contains(name) {
		return this.values().includes(name);
	}
}

function matchesSelector(element, selector) {
	if (selector.startsWith('.')) {
		return element.classList.contains(selector.slice(1));
	}
	if (selector.startsWith('[')) {
		const match = selector.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);
		return Boolean(match && element.hasAttribute(match[1]) &&
			(typeof match[2] === 'undefined' || element.getAttribute(match[1]) === match[2]));
	}
	return element.tagName.toLowerCase() === selector.toLowerCase();
}

class FakeElement {
	constructor(tagName, documentObject) {
		this.tagName = tagName.toUpperCase();
		this.ownerDocument = documentObject;
		this.children = [];
		this.parentNode = null;
		this.attributes = new Map();
		this.listeners = new Map();
		this.className = '';
		this.classList = new FakeClassList(this);
		this.style = {};
		this.hidden = false;
		this.textContent = '';
		this.value = '';
		this.scrollTop = 0;
		this.clientHeight = 320;
		this.selected = false;
		this.innerHTMLWrites = 0;
	}

	set innerHTML(value) {
		this.innerHTMLWrites += 1;
		this.textContent = value;
	}

	get innerHTML() {
		return this.textContent;
	}

	get id() {
		return this.getAttribute('id') || '';
	}

	set id(value) {
		this.setAttribute('id', value);
	}

	get firstChild() {
		return this.children[0] || null;
	}

	get offsetHeight() {
		if (!this.classList.contains('sitx-serverdiag-row')) return 0;
		const label = this.querySelector('.sitx-serverdiag-row__label');
		return label && label.textContent === 'Tall row' ? 80 : 40;
	}

	appendChild(child) {
		if (child.parentNode) child.parentNode.removeChild(child);
		this.children.push(child);
		child.parentNode = this;
		return child;
	}

	insertBefore(child, reference) {
		if (child.parentNode) child.parentNode.removeChild(child);
		const index = this.children.indexOf(reference);
		this.children.splice(index < 0 ? this.children.length : index, 0, child);
		child.parentNode = this;
		return child;
	}

	removeChild(child) {
		const index = this.children.indexOf(child);
		if (index >= 0) this.children.splice(index, 1);
		child.parentNode = null;
		return child;
	}

	setAttribute(name, value) {
		this.attributes.set(name, String(value));
	}

	getAttribute(name) {
		return this.attributes.has(name) ? this.attributes.get(name) : null;
	}

	hasAttribute(name) {
		return this.attributes.has(name);
	}

	addEventListener(type, listener) {
		const listeners = this.listeners.get(type) || [];
		listeners.push(listener);
		this.listeners.set(type, listeners);
	}

	removeEventListener(type, listener) {
		const listeners = this.listeners.get(type) || [];
		this.listeners.set(type, listeners.filter((candidate) => candidate !== listener));
	}

	dispatch(type) {
		for (const listener of this.listeners.get(type) || []) listener({ target: this, type });
	}

	querySelectorAll(selector) {
		const matches = [];
		for (const child of this.children) {
			if (matchesSelector(child, selector)) matches.push(child);
			matches.push(...child.querySelectorAll(selector));
		}
		return matches;
	}

	querySelector(selector) {
		return this.querySelectorAll(selector)[0] || null;
	}

	select() {
		this.selected = true;
		if (this.throwOnSelect) throw new Error('selection failed');
	}

	focus() {
		this.ownerDocument.activeElement = this;
	}
}

class FakeDocument {
	constructor() {
		this.body = new FakeElement('body', this);
		this.activeElement = this.body;
		this.execCommand = () => true;
	}

	createElement(tagName) {
		const element = new FakeElement(tagName, this);
		if (tagName === 'textarea' && this.throwOnTextareaSelect) element.throwOnSelect = true;
		return element;
	}

	getElementById(id) {
		return this.body.querySelectorAll('[id="' + id + '"]')[0] || null;
	}
}

function append(documentObject, parent, tag, attributes = {}, text = '') {
	const element = documentObject.createElement(tag);
	for (const [name, value] of Object.entries(attributes)) {
		if (name === 'class') element.className = value;
		else element.setAttribute(name, value);
	}
	element.textContent = text;
	parent.appendChild(element);
	return element;
}

function createDiagnosticsFixture(sectionPayloads = [[
	{ label: 'PHP version', value: '<img src=x onerror=alert(1)>', status: 'danger', detail: 'Runtime' },
	{ label: 'Memory', value: '256M', status: 'pass' },
]]) {
	const documentObject = new FakeDocument();
	const page = append(documentObject, documentObject.body, 'div', { id: 'siteintelix-server-diagnostics-page' });
	const toolbar = append(documentObject, page, 'div');
	const all = append(documentObject, toolbar, 'button', { 'data-sitx-diag-filter': 'all' });
	const issues = append(documentObject, toolbar, 'button', { 'data-sitx-diag-filter': 'issues' });
	const search = append(documentObject, toolbar, 'input', { 'data-sitx-diag-search': '' });
	const expand = append(documentObject, toolbar, 'button', { 'data-sitx-diag-expand-all': '' });
	const collapse = append(documentObject, toolbar, 'button', { 'data-sitx-diag-collapse-all': '' });
	const mobileControls = append(documentObject, toolbar, 'details', { 'data-sitx-diag-section-controls': '' });
	const mobileSummary = append(documentObject, mobileControls, 'summary', {}, 'Section controls');
	const mobileCollapse = append(documentObject, mobileControls, 'button', {
		'type': 'button',
		'data-sitx-diag-mobile-collapse-all': '',
	});
	const mobileExpand = append(documentObject, mobileControls, 'button', {
		'type': 'button',
		'data-sitx-diag-mobile-expand-all': '',
	});
	const live = append(documentObject, page, 'p', { 'data-sitx-diag-live': '' });
	const main = append(documentObject, page, 'main', { class: 'sitx-serverdiag-main' });
	const sections = sectionPayloads.map((payload, index) => {
		const section = append(documentObject, main, 'section', { 'data-sitx-diag-section': '' });
		const toggle = append(documentObject, section, 'button', { 'data-sitx-diag-toggle': '' });
		append(documentObject, section, 'span', { class: 'sitx-serverdiag-section__counts' });
		const panel = append(documentObject, section, 'div', { id: 'panel-' + index, 'data-sitx-diag-panel': '' });
		const script = append(documentObject, section, 'script', { 'data-sitx-diag-payload': '' });
		script.textContent = typeof payload === 'string' ? payload : JSON.stringify(payload);
		return { section, toggle, panel, script };
	});
	const source = append(documentObject, page, 'textarea', { id: 'copy-source' });
	source.value = 'system report';
	const copy = append(documentObject, page, 'button', {
		'data-sitx-diag-copy-system-info': '',
		'aria-controls': 'copy-source',
	});
	return {
		documentObject,
		page,
		all,
		issues,
		search,
		expand,
		collapse,
		mobileControls,
		mobileSummary,
		mobileCollapse,
		mobileExpand,
		live,
		main,
		sections,
		source,
		copy,
	};
}

function immediateWindow(overrides = {}) {
	return {
		navigator: {},
		requestAnimationFrame(callback) { callback(); },
		setTimeout(callback) { callback(); },
		...overrides,
	};
}

test('initDiagnosticsPage is idempotent and accordion controls aria and hidden state', () => {
	const fixture = createDiagnosticsFixture();
	const windowObject = immediateWindow();
	initDiagnosticsPage(fixture.page, fixture.documentObject, windowObject);
	initDiagnosticsPage(fixture.page, fixture.documentObject, windowObject);

	assert.equal(fixture.sections[0].toggle.listeners.get('click').length, 1);
	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'false');
	assert.equal(fixture.sections[0].panel.hidden, true);
	fixture.sections[0].toggle.dispatch('click');
	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'true');
	assert.equal(fixture.sections[0].panel.hidden, false);
});

test('mobile expand all uses shared section behavior, closes its menu, and restores summary focus', () => {
	const fixture = createDiagnosticsFixture();
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.mobileControls.open = true;
	fixture.mobileExpand.focus();
	fixture.mobileExpand.dispatch('click');

	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'true');
	assert.equal(fixture.sections[0].panel.hidden, false);
	assert.equal(fixture.mobileControls.open, false);
	assert.equal(fixture.documentObject.activeElement, fixture.mobileSummary);
});

test('mobile collapse all uses shared section behavior, closes its menu, and restores summary focus', () => {
	const fixture = createDiagnosticsFixture();
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.expand.dispatch('click');
	fixture.mobileControls.open = true;
	fixture.mobileCollapse.focus();
	fixture.mobileCollapse.dispatch('click');

	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'false');
	assert.equal(fixture.sections[0].panel.hidden, true);
	assert.equal(fixture.mobileControls.open, false);
	assert.equal(fixture.documentObject.activeElement, fixture.mobileSummary);
});

test('PHP filterbar provides semantic mobile section controls with button actions', () => {
	const php = readFileSync(new URL(
		'../includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php',
		import.meta.url,
	), 'utf8');

	assert.match(php, /<details class="sitx-serverdiag-section-controls" data-sitx-diag-section-controls>/);
	assert.match(php, /<summary><\?php esc_html_e\( 'Section controls', 'siteintelix' \); \?><\/summary>/);
	assert.match(php, /<button type="button"[^>]*data-sitx-diag-mobile-collapse-all/);
	assert.match(php, /<button type="button"[^>]*data-sitx-diag-mobile-expand-all/);
});

test('DOM filtering trims search, renders no-results, and reset restores sections', () => {
	const fixture = createDiagnosticsFixture();
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.search.value = '  PHP version  ';
	fixture.search.dispatch('input');
	assert.equal(fixture.sections[0].section.hidden, false);
	assert.match(fixture.live.textContent, /^1 check shown/);

	fixture.search.value = 'missing';
	fixture.search.dispatch('input');
	const noResults = fixture.page.querySelector('[data-sitx-diag-no-results]');
	assert.equal(noResults.hidden, false);
	noResults.querySelector('button').dispatch('click');
	assert.equal(fixture.search.value, '');
	assert.equal(fixture.sections[0].section.hidden, false);
});

test('malformed section remains visible without contradictory global no-results', () => {
	const fixture = createDiagnosticsFixture(['{bad json']);
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.issues.dispatch('click');
	assert.equal(fixture.sections[0].section.hidden, false);
	assert.equal(fixture.sections[0].panel.getAttribute('data-render-failed'), 'true');
	assert.equal(fixture.page.querySelector('[data-sitx-diag-no-results]').hidden, true);
	assert.equal(fixture.live.textContent, '0 checks shown. Some sections could not be loaded.');
});

test('hostile values render with textContent and never innerHTML', () => {
	const fixture = createDiagnosticsFixture();
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.sections[0].toggle.dispatch('click');
	const codes = fixture.sections[0].panel.querySelectorAll('code');
	assert.ok(codes.some((code) => code.textContent === '<img src=x onerror=alert(1)>'));
	assert.equal(fixture.sections[0].panel.querySelectorAll('img').length, 0);
	assert.equal(codes.reduce((sum, code) => sum + code.innerHTMLWrites, 0), 0);
});

test('virtualized DOM measures variable rows and exposes full list semantics', () => {
	const rows = Array.from({ length: 201 }, (_, index) => ({
		label: index === 0 ? 'Tall row' : 'Row ' + index,
		value: 'value',
		status: 'pass',
	}));
	const fixture = createDiagnosticsFixture([rows]);
	const observed = [];
	class ResizeObserver {
		constructor(callback) { this.callback = callback; }
		observe(element) { observed.push({ element, observer: this }); }
		disconnect() {}
	}
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow({ ResizeObserver }));
	fixture.sections[0].toggle.dispatch('click');
	const disclosure = fixture.sections[0].panel.querySelector('.sitx-serverdiag-disclosure');
	disclosure.dispatch('click');
	const list = fixture.sections[0].panel.querySelector('.sitx-serverdiag-virtual-list');
	assert.equal(list.getAttribute('role'), 'list');
	assert.equal(list.getAttribute('aria-label'), 'Passed checks (201 total)');
	assert.ok(observed.length > 0, 'rendered rows are observed for their actual height');
	const firstItem = list.querySelector('[role="listitem"]');
	assert.equal(firstItem.getAttribute('aria-setsize'), '201');
	assert.equal(firstItem.getAttribute('aria-posinset'), '1');
	for (const observation of observed.slice()) observation.observer.callback([{ target: observation.element }]);
	list.scrollTop = 400;
	list.dispatch('scroll');
	const renderedPositions = list.querySelectorAll('[role="listitem"]')
		.map((item) => Number(item.getAttribute('aria-posinset')));
	const firstPosition = renderedPositions[0];
	assert.ok(firstPosition > 1);
	assert.deepEqual(renderedPositions, Array.from(
		{ length: renderedPositions.length },
		(_, index) => firstPosition + index,
	), 'scroll rerenders a contiguous range without skipped rows');
	assert.equal(list.firstChild.style.height, 80 + ((firstPosition - 2) * 40) + 'px');
});

test('clipboard modern success announces and fallback always cleans up and restores focus', async () => {
	const modern = createDiagnosticsFixture();
	initDiagnosticsPage(modern.page, modern.documentObject, immediateWindow({
		navigator: { clipboard: { writeText: async (text) => assert.equal(text, 'system report') } },
	}));
	modern.copy.dispatch('click');
	await Promise.resolve();
	assert.equal(modern.live.textContent, 'System information copied.');

	const fallback = createDiagnosticsFixture();
	fallback.source.focus();
	fallback.documentObject.throwOnTextareaSelect = true;
	initDiagnosticsPage(fallback.page, fallback.documentObject, immediateWindow());
	fallback.copy.dispatch('click');
	assert.equal(fallback.documentObject.body.querySelectorAll('textarea').length, 1);
	assert.equal(fallback.documentObject.activeElement, fallback.source);
	assert.equal(fallback.live.textContent, 'System information could not be copied.');
});

test('localized diagnostics data drives generated UI, ARIA, and live-region strings', () => {
	const fixture = createDiagnosticsFixture();
	const windowObject = immediateWindow({
		siteintelixDiagnosticsData: {
			checksShown: ['aucun examen visible', '%d examen visible', '%d examens visibles'],
			noResults: 'Aucun examen ne correspond.',
			resetFilters: 'Effacer les filtres',
			copySuccess: 'Informations copiées.',
			problemsListLabel: 'Examens à corriger',
			listTotal: ['%1$s (aucun)', '%1$s (%2$d examen)', '%1$s (%2$d examens)'],
		},
	});

	initDiagnosticsPage(fixture.page, fixture.documentObject, windowObject);
	fixture.search.value = 'missing';
	fixture.search.dispatch('input');
	const noResults = fixture.page.querySelector('[data-sitx-diag-no-results]');
	assert.equal(noResults.querySelector('p').textContent, 'Aucun examen ne correspond.');
	assert.equal(noResults.querySelector('button').textContent, 'Effacer les filtres');
	assert.equal(fixture.live.textContent, 'aucun examen visible');

	fixture.search.value = 'PHP version';
	fixture.search.dispatch('input');
	fixture.sections[0].toggle.dispatch('click');
	assert.equal(fixture.live.textContent, '1 examen visible');
	assert.equal(
		fixture.sections[0].panel.querySelector('.sitx-serverdiag-problems').getAttribute('aria-label'),
		'Examens à corriger (1 examen)',
	);
	fixture.copy.dispatch('click');
	assert.equal(fixture.live.textContent, 'Informations copiées.');

	const php = readFileSync(new URL('../siteintelix.php', import.meta.url), 'utf8');
	assert.match(php, /wp_localize_script\(\s*'siteintelix-server-diagnostics-script'/);
	assert.match(php, /siteintelixDiagnosticsData/);
	assert.match(php, /\/\* translators: %d: Number of diagnostic checks\. \*\//);
	assert.match(php, /for \( \$siteintelix_count = 0; \$siteintelix_count <= \$siteintelix_diagnostics_count_max; \$siteintelix_count\+\+ \)/);
	assert.match(php, /'checksShown'\]\[ \$siteintelix_count \] = _n\(\s*'%d check shown',\s*'%d checks shown',\s*\$siteintelix_count/);
	assert.match(php, /'listTotal'\]\[ \$siteintelix_count \] = _n\(/);
	assert.doesNotMatch(php, /_n\([^;]+,\s*[12],\s*'siteintelix'\s*\)/);
});

test('repeated filters destroy expanded virtual render resources before replacing lists', () => {
	const rows = Array.from({ length: 201 }, (_, index) => ({
		label: 'Row ' + index,
		value: 'value',
		status: 'pass',
	}));
	const fixture = createDiagnosticsFixture([rows]);
	const observers = [];
	const cancelledFrames = [];
	const frameCallbacks = new Map();
	let nextFrame = 0;
	class ResizeObserver {
		constructor(callback) {
			this.callback = callback;
			this.disconnectCount = 0;
			observers.push(this);
		}
		observe() {}
		disconnect() { this.disconnectCount += 1; }
	}
	const windowObject = {
		navigator: {},
		ResizeObserver,
		requestAnimationFrame(callback) {
			nextFrame += 1;
			frameCallbacks.set(nextFrame, callback);
			return nextFrame;
		},
		cancelAnimationFrame(id) { cancelledFrames.push(id); },
	};

	initDiagnosticsPage(fixture.page, fixture.documentObject, windowObject);
	fixture.sections[0].toggle.dispatch('click');
	fixture.sections[0].panel.querySelector('.sitx-serverdiag-disclosure').dispatch('click');
	let list = fixture.sections[0].panel.querySelector('.sitx-serverdiag-virtual-list');
	const firstObserver = observers[0];
	const firstDisconnectBaseline = firstObserver.disconnectCount;
	list.dispatch('scroll');
	assert.equal(list.listeners.get('scroll').length, 1);

	fixture.issues.dispatch('click');
	assert.equal(firstObserver.disconnectCount, firstDisconnectBaseline + 1);
	assert.equal(list.listeners.get('scroll').length, 0);
	assert.deepEqual(cancelledFrames, [1]);
	const staleChildCount = list.children.length;
	const staleFirstChild = list.firstChild;
	frameCallbacks.get(1)();
	firstObserver.callback([{ target: list.querySelector('[role="listitem"]') }]);
	assert.equal(list.children.length, staleChildCount, 'a stale animation frame cannot rerender a destroyed list');
	assert.equal(list.firstChild, staleFirstChild, 'a stale ResizeObserver callback cannot mutate a destroyed list');

	fixture.all.dispatch('click');
	list = fixture.sections[0].panel.querySelector('.sitx-serverdiag-virtual-list');
	const secondObserver = observers[1];
	const secondDisconnectBaseline = secondObserver.disconnectCount;
	list.dispatch('scroll');
	fixture.issues.dispatch('click');
	assert.equal(secondObserver.disconnectCount, secondDisconnectBaseline + 1);
	assert.equal(list.listeners.get('scroll').length, 0);
	assert.deepEqual(cancelledFrames, [1, 2]);
});
