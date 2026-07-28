# Server Diagnostics Lightweight Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Match the supplied compact Server Diagnostics visual while preserving the existing PHP report, caching, refresh, permissions, redaction, exports, and dependency-free performance model.

**Architecture:** Extend the existing PHP-rendered dashboard shell and encoded section payloads. Add small pure JavaScript helpers for filtering, sorting, bounded rendering, and disclosure state; retain lazy section parsing and the existing large-list virtualization safeguard. Replace the current card-like dynamic rows with a dense semantic table on desktop and CSS-driven stacked rows on mobile.

**Tech Stack:** WordPress PHP, existing SiteIntelix admin UI helpers and CSS tokens, dependency-free ES5-compatible JavaScript, Node.js `node:test`, PHP CLI.

**Execution constraint:** Work inline in the current plugin. Do not create commits, push Git, or use SVN unless the user later requests it.

---

## File Map

- `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
  - Render header actions, five-item summary, toolbar controls, category metadata, safe encoded payloads, no-JavaScript table, and utility actions.
  - Do not modify collectors, cache helpers, refresh handler, redaction, export handler, or report data.
- `assets/admin/js/siteintelix-server-diagnostics.js`
  - Preserve pure filtering and normalization.
  - Add stable sorting, Hide Passed behavior, initial ten-row limits, default highest-severity accordion, row details, action-menu behavior, and delegated dynamic interactions.
- `assets/admin/css/siteintelix-server-diagnostics.css`
  - Implement the compact aligned shell, score ring, five-card summary, toolbar, accordion metadata/progress, dense table, sidebar, overflow menus, mobile stacked rows, print, focus, and reduced-motion behavior.
- `siteintelix.php`
  - Localize only new user-facing strings used by JavaScript.
- `tests/server-diagnostics-ui.test.mjs`
  - Unit-test pure helpers and DOM interactions.
- `tests/structural.test.mjs`
  - Assert the server-rendered visual/accessibility contract and performance boundaries.

---

### Task 1: Protect the New Server-Rendered Contract

**Files:**
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add a failing structural test for the compact dashboard**

Add a test alongside the existing Server Diagnostics structural tests:

```js
test('Server Diagnostics exposes the compact server-rendered dashboard controls', async () => {
	const diagnostics = await read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php');

	for (const contract of [
		'sitx-serverdiag-score-ring',
		'sitx-serverdiag-stat--total',
		'data-sitx-diag-hide-passed',
		'data-sitx-diag-sort',
		'data-sitx-diag-show-all',
		'data-sitx-diag-row-details',
		'sitx-serverdiag-category-progress',
		'sitx-serverdiag-actions-menu',
		'<table',
		'<thead>',
		'<th scope="col"',
	]) {
		assert.ok(diagnostics.includes(contract), `missing ${contract}`);
	}

	assert.doesNotMatch(diagnostics, /data-sitx-diag-(?:fix|install)/);
	assert.doesNotMatch(diagnostics, /Security.+data-sitx-diag-section/s);
});
```

- [ ] **Step 2: Run the targeted test and confirm failure**

Run:

```bash
node --test --test-name-pattern="compact server-rendered dashboard controls" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL because the new summary, toolbar, table, and detail contracts do not exist yet.

- [ ] **Step 3: Add a structural test for dependency and backend preservation**

Add:

```js
test('Server Diagnostics enhancement preserves its lightweight backend boundaries', async () => {
	const [diagnostics, diagnosticsJs] = await Promise.all([
		read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php'),
		read('assets/admin/js/siteintelix-server-diagnostics.js'),
	]);

	assert.match(diagnostics, /wp_ajax_siteintelix_refresh_server_diagnostics/);
	assert.match(diagnostics, /check_ajax_referer\(\s*'siteintelix_refresh_server_diagnostics'/);
	assert.match(diagnostics, /check_admin_referer\(\s*'siteintelix_server_diag_export'/);
	assert.match(diagnostics, /self::redact_report/);
	assert.match(diagnostics, /self::get_cached_section/);
	assert.doesNotMatch(diagnosticsJs, /\b(?:jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(diagnosticsJs, /fetch\([^)]*row/i);
});
```

- [ ] **Step 4: Re-run the two Server Diagnostics structural tests**

Run:

```bash
node --test --test-name-pattern="Server Diagnostics" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: Existing tests pass; the compact dashboard contract remains red until Task 3.

### Task 2: Define Tested Toolbar and Bounded-Rendering State

**Files:**
- Modify: `tests/server-diagnostics-ui.test.mjs`
- Modify: `assets/admin/js/siteintelix-server-diagnostics.js`
- Test: `tests/server-diagnostics-ui.test.mjs`

- [ ] **Step 1: Add failing pure-helper tests**

Import the new helpers from the CommonJS test export and add:

```js
test('sortRows is stable across severity, label, and status modes', () => {
	const rows = [
		{ label: 'Zulu', status: 'pass' },
		{ label: 'Alpha', status: 'warning' },
		{ label: 'Beta', status: 'danger' },
		{ label: 'Alpha', status: 'pass' },
	];

	assert.deepEqual(sortRows(rows, 'severity').map((row) => row.label), ['Beta', 'Alpha', 'Zulu', 'Alpha']);
	assert.deepEqual(sortRows(rows, 'name').map((row) => `${row.label}:${row.status}`), [
		'Alpha:warning',
		'Alpha:pass',
		'Beta:danger',
		'Zulu:pass',
	]);
	assert.deepEqual(sortRows(rows, 'status').map((row) => row.status), ['danger', 'warning', 'pass', 'pass']);
});

test('applyRowView combines filters, hide-passed, sorting, and the initial row limit', () => {
	const rows = [
		{ label: 'Pass B', status: 'pass' },
		{ label: 'Warning', status: 'warning' },
		{ label: 'Pass A', status: 'pass' },
		{ label: 'Issue', status: 'danger' },
	];

	const view = applyRowView(rows, {
		filter: 'all',
		query: '',
		hidePassed: true,
		sort: 'severity',
		expanded: false,
		limit: 10,
	});

	assert.deepEqual(view.visible.map((row) => row.label), ['Issue', 'Warning']);
	assert.equal(view.total, 2);
	assert.equal(view.hasMore, false);
});
```

- [ ] **Step 2: Run the helper tests and confirm failure**

Run:

```bash
node --test --test-name-pattern="sortRows|applyRowView" wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: FAIL because `sortRows` and `applyRowView` are not exported.

- [ ] **Step 3: Implement stable pure helpers**

Add near the existing `orderRows` helper:

```js
function sortRows(rows, mode) {
	var severityRanks = { danger: 0, warning: 1, info: 2, neutral: 2, pass: 3 };
	var statusNames = { danger: 'critical', warning: 'warning', info: 'information', neutral: 'information', pass: 'passed' };

	return (Array.isArray(rows) ? rows : []).map(function (row, index) {
		return { row: row, index: index };
	}).sort(function (left, right) {
		var result = 0;
		if ('name' === mode) {
			result = normalizeText(left.row.label).localeCompare(normalizeText(right.row.label));
		} else if ('status' === mode) {
			result = (statusNames[left.row.status] || '').localeCompare(statusNames[right.row.status] || '');
		} else {
			result = (severityRanks[left.row.status] || 4) - (severityRanks[right.row.status] || 4);
		}
		return result || left.index - right.index;
	}).map(function (item) {
		return item.row;
	});
}

function applyRowView(rows, options) {
	var settings = options || {};
	var matched = filterRows(rows, settings.filter || 'all', settings.query || '');
	if (settings.hidePassed && 'passed' !== settings.filter) {
		matched = matched.filter(function (row) { return 'pass' !== row.status; });
	}
	matched = sortRows(matched, settings.sort || 'severity');
	var limit = Number(settings.limit) > 0 ? Math.floor(Number(settings.limit)) : 10;
	return {
		total: matched.length,
		visible: settings.expanded ? matched : matched.slice(0, limit),
		hasMore: !settings.expanded && matched.length > limit,
		rows: matched,
	};
}
```

Export both helpers from the test-only CommonJS export at the bottom of the file.

- [ ] **Step 4: Run the helper tests**

Run:

```bash
node --test --test-name-pattern="sortRows|applyRowView" wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: PASS.

### Task 3: Render the Compact Header, Summary, Toolbar, and Category Metadata

**Files:**
- Modify: `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`
- Test: `tests/runtime-smoke.php`

- [ ] **Step 1: Refine the header actions without changing destinations**

In `render_page()`, retain `$json_url` and `$text_url`. Render Refresh as the existing `data-sitx-diag-refresh` button, retain the dynamic version and health badges, and group JSON/text export plus support report links inside:

```php
<details class="sitx-serverdiag-actions-menu">
	<summary class="si-button">
		<span class="dashicons dashicons-download" aria-hidden="true"></span>
		<?php esc_html_e( 'Export', 'siteintelix' ); ?>
	</summary>
	<div class="sitx-serverdiag-actions-menu__panel">
		<a href="<?php echo esc_url( $json_url ); ?>"><?php esc_html_e( 'Download JSON', 'siteintelix' ); ?></a>
		<a href="<?php echo esc_url( $text_url ); ?>"><?php esc_html_e( 'Export Report', 'siteintelix' ); ?></a>
	</div>
</details>
```

Keep Support Report as the primary `$text_url` link. Do not add endpoints.

- [ ] **Step 2: Replace the four-card overview with five compact cards**

Change `render_overview()` and `render_stat_card()` so the primary card outputs a score ring:

```php
<span class="sitx-serverdiag-score-ring"
	style="<?php echo esc_attr( '--sitx-diag-score:' . min( 100, max( 0, absint( $summary['score'] ) ) ) ); ?>"
	aria-label="<?php echo esc_attr( sprintf( __( 'Health score: %d percent', 'siteintelix' ), absint( $summary['score'] ) ) ); ?>">
	<strong><?php echo esc_html( absint( $summary['score'] ) . '%' ); ?></strong>
</span>
```

Render Critical, Warnings, Passed, and Total using dynamic `$summary` values. Add the `sitx-serverdiag-stat--total` modifier to Total Checks.

- [ ] **Step 3: Extend the toolbar with Hide Passed and Sort**

Inside `render_filterbar()` add:

```php
<label class="sitx-serverdiag-hide-passed">
	<input type="checkbox" data-sitx-diag-hide-passed>
	<span><?php esc_html_e( 'Hide Passed', 'siteintelix' ); ?></span>
</label>
<label class="screen-reader-text" for="sitx-serverdiag-sort"><?php esc_html_e( 'Sort diagnostics', 'siteintelix' ); ?></label>
<select id="sitx-serverdiag-sort" data-sitx-diag-sort>
	<option value="severity"><?php esc_html_e( 'Sort: Severity', 'siteintelix' ); ?></option>
	<option value="name"><?php esc_html_e( 'Sort: Check name', 'siteintelix' ); ?></option>
	<option value="status"><?php esc_html_e( 'Sort: Status', 'siteintelix' ); ?></option>
</select>
```

Retain search, five filter buttons, live region, and desktop/mobile collapse controls.

- [ ] **Step 4: Add category progress metadata**

In `render_section()`, derive:

```php
$total    = max( 1, absint( $section['summary']['total'] ) );
$progress = (int) round( ( absint( $section['summary']['passed'] ) / $total ) * 100 );
```

Render dynamic count chips only when non-zero and:

```php
<span class="sitx-serverdiag-category-progress" aria-label="<?php echo esc_attr( sprintf( __( '%d percent passed', 'siteintelix' ), $progress ) ); ?>">
	<span style="<?php echo esc_attr( '--sitx-diag-progress:' . $progress . '%' ); ?>"></span>
</span>
```

The first severity-sorted section receives `data-sitx-diag-default-open="true"` from the `render_page()` loop. Do not hardcode a category.

- [ ] **Step 5: Run structural and runtime tests**

Run:

```bash
node --test --test-name-pattern="Server Diagnostics" wp-content/plugins/siteintelix/tests/structural.test.mjs
php wp-content/plugins/siteintelix/tests/runtime-smoke.php
```

Expected: Structural tests pass through the server-rendered contract. Runtime smoke continues to pass with unchanged report logic.

### Task 4: Replace Dynamic Cards with Dense Tables and Details Rows

**Files:**
- Modify: `tests/server-diagnostics-ui.test.mjs`
- Modify: `assets/admin/js/siteintelix-server-diagnostics.js`
- Modify: `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
- Test: `tests/server-diagnostics-ui.test.mjs`

- [ ] **Step 1: Extend the DOM fixture**

Add toolbar controls:

```js
const hidePassed = append(documentObject, toolbar, 'input', {
	type: 'checkbox',
	'data-sitx-diag-hide-passed': '',
});
const sort = append(documentObject, toolbar, 'select', { 'data-sitx-diag-sort': '' });
sort.value = 'severity';
```

Give the first generated section `data-sitx-diag-default-open="true"` and return `hidePassed` and `sort` from the fixture.

- [ ] **Step 2: Add failing interaction tests**

Add:

```js
test('the highest-severity section opens by default', () => {
	const fixture = createDiagnosticsFixture();
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'true');
	assert.equal(fixture.sections[0].panel.hidden, false);
});

test('Hide Passed and sorting rerender without losing accordion state', () => {
	const fixture = createDiagnosticsFixture([[
		{ label: 'Zulu', value: 'ok', status: 'pass', detail: 'Passed row' },
		{ label: 'Alpha', value: 'low', status: 'warning', detail: 'Warning row' },
	]]);
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	fixture.hidePassed.checked = true;
	fixture.hidePassed.dispatch('change');
	assert.equal(fixture.sections[0].panel.querySelectorAll('[data-sitx-diag-row]').length, 1);
	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'true');
	fixture.sort.value = 'name';
	fixture.sort.dispatch('change');
	assert.equal(fixture.sections[0].toggle.getAttribute('aria-expanded'), 'true');
});

test('rows are limited to ten and Details uses an accessible disclosure', () => {
	const rows = Array.from({ length: 12 }, (_, index) => ({
		label: `Check ${index + 1}`,
		value: `Value ${index + 1}`,
		status: index === 0 ? 'warning' : 'pass',
		detail: `Detail ${index + 1}`,
	}));
	const fixture = createDiagnosticsFixture([rows]);
	initDiagnosticsPage(fixture.page, fixture.documentObject, immediateWindow());
	assert.equal(fixture.sections[0].panel.querySelectorAll('[data-sitx-diag-row]').length, 10);
	const showAll = fixture.sections[0].panel.querySelector('[data-sitx-diag-show-all]');
	assert.ok(showAll);
	showAll.dispatch('click');
	assert.equal(fixture.sections[0].panel.querySelectorAll('[data-sitx-diag-row]').length, 12);
	const details = fixture.sections[0].panel.querySelector('[data-sitx-diag-row-details]');
	assert.equal(details.getAttribute('aria-expanded'), 'false');
	details.dispatch('click');
	assert.equal(details.getAttribute('aria-expanded'), 'true');
});
```

- [ ] **Step 3: Run the interaction tests and confirm failure**

Run:

```bash
node --test --test-name-pattern="highest-severity|Hide Passed|limited to ten" wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: FAIL because default open, new controls, bounded rows, and table disclosures are not implemented.

- [ ] **Step 4: Replace `createRowItem()` with table-row builders**

Create safe DOM nodes with `textContent` only:

```js
function createDiagnosticRow(documentObject, row, index, strings) {
	var rowId = 'sitx-serverdiag-row-' + index;
	var tr = documentObject.createElement('tr');
	var detailTr = documentObject.createElement('tr');
	var button = documentObject.createElement('button');

	tr.className = 'sitx-serverdiag-check-row is-' + row.status;
	tr.setAttribute('data-sitx-diag-row', '');
	// Append status, label, current, recommended, concise description, and action cells.
	button.type = 'button';
	button.className = 'sitx-serverdiag-row-details';
	button.setAttribute('data-sitx-diag-row-details', '');
	button.setAttribute('aria-controls', rowId + '-details');
	button.setAttribute('aria-expanded', 'false');
	button.textContent = strings.details;
	// Append full detail content using textContent.
	detailTr.id = rowId + '-details';
	detailTr.className = 'sitx-serverdiag-check-detail';
	detailTr.hidden = true;
	return { row: tr, detail: detailTr };
}
```

Implement the omitted cell appends explicitly using `createElement`, `textContent`, `appendChild`, and the existing `displayText()`/`statusLabel()` helpers. Never use `innerHTML`.

- [ ] **Step 5: Render one semantic table per opened section**

`renderSectionRows()` creates:

```js
var table = documentObject.createElement('table');
var thead = documentObject.createElement('thead');
var tbody = documentObject.createElement('tbody');
table.className = 'sitx-serverdiag-checks-table';
```

Append headers for Status, Check, Current, Recommended, Description, and Action. Use `applyRowView()` to append at most ten row/detail pairs. Append Show All or Show Fewer below the table with `data-sitx-diag-show-all`. Retain the existing no-matches state and virtualization only for explicit full lists over 200 rows.

- [ ] **Step 6: Use delegated interaction handling**

Add one page click listener that resolves:

```js
var detailsButton = event.target.closest && event.target.closest('[data-sitx-diag-row-details]');
var showAllButton = event.target.closest && event.target.closest('[data-sitx-diag-show-all]');
```

Toggle the controlled detail row and `aria-expanded`. For Show All/Fewer, update the owning section state's `showAll` value and rerender it.

- [ ] **Step 7: Wire toolbar state**

Track:

```js
var hidePassed = false;
var currentSort = 'severity';
```

On Hide Passed change, update `hidePassed`; on explicit Passed filter selection, uncheck and clear Hide Passed. On sort change, validate to `severity`, `name`, or `status`. Reset each section's `showAll` to false when search/filter/hide/sort changes, but preserve its accordion expanded state.

- [ ] **Step 8: Open only the server-marked default category**

After section state creation, call the existing shared expansion function for the section whose element has `data-sitx-diag-default-open="true"`. Do not open malformed or hidden sections.

- [ ] **Step 9: Improve the no-JavaScript fallback columns**

Change the PHP `<noscript>` table headers and row cells to Status, Check, Current, Recommended, and Description. Omit Recommended cleanly when unavailable by rendering an em dash.

- [ ] **Step 10: Run the diagnostics UI tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: All diagnostics UI tests pass, including hostile-value safety and clipboard/refresh behavior.

### Task 5: Localize New Interaction Strings

**Files:**
- Modify: `siteintelix.php`
- Modify: `assets/admin/js/siteintelix-server-diagnostics.js`
- Modify: `tests/server-diagnostics-ui.test.mjs`
- Test: `tests/server-diagnostics-ui.test.mjs`

- [ ] **Step 1: Add failing localization assertions**

Extend the localization test to assert:

```js
for (const key of ['details', 'showAllChecks', 'showFewer', 'statusColumn', 'checkColumn', 'descriptionColumn', 'actionColumn']) {
	assert.match(php, new RegExp(`'${key}'\\s*=>`));
}
```

- [ ] **Step 2: Run the localization test and confirm failure**

Run:

```bash
node --test --test-name-pattern="localized diagnostics data" wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: FAIL because the new keys are absent.

- [ ] **Step 3: Add compact localized strings**

Add to `siteintelixDiagnosticsData`:

```php
'details'           => __( 'Details', 'siteintelix' ),
'showAllChecks'     => __( 'Show all checks', 'siteintelix' ),
'showFewer'         => __( 'Show fewer', 'siteintelix' ),
'statusColumn'      => __( 'Status', 'siteintelix' ),
'checkColumn'       => __( 'Check', 'siteintelix' ),
'descriptionColumn' => __( 'Description', 'siteintelix' ),
'actionColumn'      => __( 'Action', 'siteintelix' ),
```

Add matching English defaults in `diagnosticsStrings()`. Keep count-bearing visible button labels assembled from localized label plus numeric count rather than generating 0–200 translation arrays.

- [ ] **Step 4: Run localization and full diagnostics UI tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
```

Expected: PASS.

### Task 6: Implement the Mockup-Aligned Responsive CSS

**Files:**
- Modify: `assets/admin/css/siteintelix-server-diagnostics.css`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add structural CSS assertions**

Extend the dedicated-assets test:

```js
for (const contract of [
	/\.sitx-serverdiag-score-ring/,
	/\.sitx-serverdiag-checks-table/,
	/\.sitx-serverdiag-check-detail/,
	/\.sitx-serverdiag-actions-menu/,
	/\.sitx-serverdiag-category-progress/,
	/@media\s*\(max-width:\s*782px\)/,
	/display:\s*grid/,
]) {
	assert.match(diagnosticsCss, contract);
}
```

- [ ] **Step 2: Run the CSS structural test and confirm failure**

Run:

```bash
node --test --test-name-pattern="dedicated local assets" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL for the new compact component selectors.

- [ ] **Step 3: Refine layout and header styles**

Keep the page and header content at `max-width: 1440px`. Use:

```css
#siteintelix-server-diagnostics-page .sitx-serverdiag-container {
	margin-inline: auto;
	max-width: 1440px;
	padding: 20px 24px 40px;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-layout {
	display: grid;
	gap: 16px;
	grid-template-columns: minmax(0, 1fr) 248px;
}
```

Style the export/actions disclosure as a compact anchored menu with native keyboard behavior.

- [ ] **Step 4: Create the compact five-card summary and score ring**

Use:

```css
#siteintelix-server-diagnostics-page .sitx-serverdiag-stats {
	display: grid;
	gap: 12px;
	grid-template-columns: minmax(220px, 1.3fr) repeat(4, minmax(132px, 1fr));
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-stat {
	min-height: 104px;
	padding: 14px 16px;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-score-ring {
	background: conic-gradient(var(--sitx-diag-primary) calc(var(--sitx-diag-score) * 1%), #dbeafe 0);
	border-radius: 50%;
	display: grid;
	height: 72px;
	place-items: center;
	position: relative;
	width: 72px;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-score-ring::before {
	background: var(--sitx-diag-surface);
	border-radius: inherit;
	content: "";
	inset: 7px;
	position: absolute;
}
```

Place the ring text above the inner circle with `position: relative`.

- [ ] **Step 5: Compact the toolbar and accordion headers**

Use a search-first grid, horizontally scrollable filter group where necessary, compact checkbox/select controls, and sticky behavior below WordPress admin chrome only at wide widths.

Style category headers with a grid that gives the title flexible width and keeps counts/progress/chevron compact. Use a 64px progress track with a child width driven by `--sitx-diag-progress`.

- [ ] **Step 6: Style the dense semantic table**

Use:

```css
#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table {
	border-collapse: collapse;
	table-layout: fixed;
	width: 100%;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table th,
#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table td {
	border-bottom: 1px solid var(--sitx-diag-border);
	padding: 10px 12px;
	text-align: left;
	vertical-align: middle;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-check-row {
	min-height: 48px;
}
```

Give Status and Action narrow fixed widths, Current/Recommended bounded widths, and Description the remaining width. Allow `code` values to wrap with `overflow-wrap: anywhere`.

Style detail rows as one subtle inset block, not a nested card.

- [ ] **Step 7: Implement responsive behavior**

At `max-width: 1180px`, remove the fixed sidebar column and render Page Actions as a full-width disclosure.

At `max-width: 960px`, use a two-column summary grid and hide the collapsed Description column while preserving it in detail rows.

At `max-width: 782px`, use a two-column summary with the health card spanning both columns, stack the toolbar, and transform table markup with CSS:

```css
#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table,
#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table tbody,
#siteintelix-server-diagnostics-page .sitx-serverdiag-check-row {
	display: block;
}

#siteintelix-server-diagnostics-page .sitx-serverdiag-checks-table thead {
	clip: rect(0 0 0 0);
	height: 1px;
	overflow: hidden;
	position: absolute;
	width: 1px;
}
```

Use cell classes rather than positional selectors to keep status, label, current, and action visible. Hide secondary fields until the controlled detail row opens.

At `max-width: 480px`, retain two compact KPI columns and prevent any control or code value from creating page overflow.

- [ ] **Step 8: Preserve accessibility, motion, and print styles**

Keep the existing `:focus-visible`, `[hidden]`, `prefers-reduced-motion`, and print contracts. Print must show readable tables/detail content while hiding filters, menus, sidebar, and buttons.

- [ ] **Step 9: Run CSS and full structural tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS.

### Task 7: Complete Functional and Regression Verification

**Files:**
- Verify only; fix the files above if a test exposes a defect.

- [ ] **Step 1: Lint the modified PHP**

Run:

```bash
php -l wp-content/plugins/siteintelix/includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php
php -l wp-content/plugins/siteintelix/siteintelix.php
```

Expected: `No syntax errors detected` for both files.

- [ ] **Step 2: Run focused diagnostics tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
php wp-content/plugins/siteintelix/tests/runtime-smoke.php
```

Expected: All Node tests pass and runtime smoke reports success.

- [ ] **Step 3: Run the full structural suite**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: All structural tests pass.

- [ ] **Step 4: Run supplementary PHP scripts**

Run:

```bash
php wp-content/plugins/siteintelix/tests/debug-log-parser.php
php wp-content/plugins/siteintelix/tests/editor-links.php
```

Expected: Both scripts complete successfully.

- [ ] **Step 5: Inspect the page at four responsive widths**

Open the SiteIntelix Server Diagnostics admin page and verify:

- 1440px: five compact summary cards, dense main table, sticky 248px sidebar.
- 1024px: readable table, compact actions disclosure, no squeezed sidebar.
- 768px: two-column summary, hidden collapsed descriptions, usable toolbar.
- 390px: stacked diagnostic rows, two-column KPI grid, no horizontal page overflow.

Also verify keyboard interaction for header menus, accordion buttons, Details, Show All/Fewer, filters, copy, refresh, and exports.

- [ ] **Step 6: Confirm repository handling**

Run no Git or SVN mutation command. Report changed files and test evidence only.

---

## Self-Review

- Spec coverage: Every header, summary, toolbar, category, table, progressive-disclosure, sidebar, responsive, accessibility, and performance requirement maps to Tasks 1–7.
- Backend boundary: No task changes collectors, cache keys, API requests, permissions, redaction, export generation, or AJAX action names.
- Placeholder scan: No deferred production behavior remains. The table-row builder step explicitly requires safe DOM construction and names every rendered column.
- Type consistency: JavaScript state consistently uses `currentFilter`, `currentQuery`, `hidePassed`, `currentSort`, per-section `showAll`, normalized row objects, and existing status keys.
- User constraint: Commit and push steps are intentionally omitted because the user explicitly prohibited Git/SVN mutations.
