# Server Diagnostics Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the dense Server Diagnostics tables with the approved health-first, accessible, responsive accordion dashboard while preserving every diagnostic value and export workflow.

**Architecture:** Keep `SITEINTELIX_Server_Diagnostics_Module` as the report source and server-rendered shell. Add focused diagnostic CSS and dependency-free JavaScript assets, enqueue them only on the diagnostics screen, encode section rows into per-section JSON, and render issue details on first expansion. A `<noscript>` fallback preserves access to all rows, and lists over 200 items use a windowed renderer.

**Tech Stack:** WordPress/PHP, vanilla JavaScript, CSS custom properties, Node.js structural tests, standalone PHP smoke tests.

---

## File Structure

- Modify `tests/structural.test.mjs` — add structural contracts for markup, scoped assets, accessibility, and dependency restrictions.
- Create `tests/server-diagnostics-ui.test.mjs` — exercise pure filtering, ordering, grouping, and window-range helpers in Node.
- Modify `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php` — derive presentation metadata and render the approved dashboard shell, JSON payloads, fallbacks, utilities, and empty state.
- Modify `siteintelix.php` — enqueue the dedicated diagnostics assets only for the diagnostics submenu.
- Create `assets/admin/js/siteintelix-server-diagnostics.js` — progressive rendering, accordions, combined search/filtering, bulk controls, copy feedback, and list windowing.
- Create `assets/admin/css/siteintelix-server-diagnostics.css` — approved desktop, tablet, mobile, component, focus, reduced-motion, print, and empty-state styling.
- Modify `tests/runtime-smoke.php` — add report metadata and status-count assertions without booting a second diagnostics implementation.

The current working directory is not a Git repository. Replace each requested commit with a local verification checkpoint unless version control is initialized before execution.

### Task 1: Lock the Dashboard Contract with Failing Structural Tests

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add a failing dashboard markup contract**

Append a test that reads the diagnostics module and checks for the approved shell:

```js
test('Server Diagnostics renders the health-first accessible dashboard shell', async () => {
	const diagnostics = await read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php');
	for (const marker of [
		'sitx-serverdiag-health',
		'sitx-serverdiag-filterbar',
		'data-sitx-diag-filter',
		'data-sitx-diag-search',
		'data-sitx-diag-expand-all',
		'data-sitx-diag-collapse-all',
		'data-sitx-diag-section',
		'data-sitx-diag-payload',
		'aria-expanded="false"',
		'aria-live="polite"',
		'Show %d passed checks',
		'Everything looks healthy.',
	]) {
		assert.ok(diagnostics.includes(marker), `missing diagnostics marker ${marker}`);
	}
	assert.doesNotMatch(diagnostics, /sitx-serverdiag-table[^-]/);
});
```

- [ ] **Step 2: Add a failing asset-scoping contract**

Append:

```js
test('Server Diagnostics uses dedicated local dependency-free assets', async () => {
	const [main, js, css] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-server-diagnostics.js'),
		read('assets/admin/css/siteintelix-server-diagnostics.css'),
	]);
	assert.match(main, /siteintelix-server-diagnostics-style/);
	assert.match(main, /siteintelix-server-diagnostics-script/);
	assert.match(main, /siteintelix-server-diagnostics/);
	assert.doesNotMatch(js, /\b(jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(css, /@import\s+url|fonts\.googleapis|cdnjs|unpkg|jsdelivr/);
	assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
	assert.match(css, /:focus-visible/);
});
```

- [ ] **Step 3: Run the structural tests and confirm failure**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: the new tests fail because the dedicated assets and dashboard markers do not exist.

- [ ] **Step 4: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix` and preserve the output in the task notes. If Git is initialized later, commit with `test: define server diagnostics dashboard contract`.

### Task 2: Add Dedicated Asset Loading

**Files:**
- Modify: `wp-content/plugins/siteintelix/siteintelix.php:420-505`
- Create: `wp-content/plugins/siteintelix/assets/admin/js/siteintelix-server-diagnostics.js`
- Create: `wp-content/plugins/siteintelix/assets/admin/css/siteintelix-server-diagnostics.css`

- [ ] **Step 1: Create minimal local assets**

Create the JavaScript file with a safe page guard:

```js
( function () {
	'use strict';
	function init() {
		var page = document.getElementById( 'siteintelix-server-diagnostics-page' );
		if ( ! page ) { return; }
		page.classList.add( 'is-enhanced' );
	}
	document.addEventListener( 'DOMContentLoaded', init );
}() );
```

Create the CSS file with scoped approved tokens:

```css
#siteintelix-server-diagnostics-page {
	--sitx-diag-canvas: #f8fafc;
	--sitx-diag-surface: #fff;
	--sitx-diag-border: #e5e7eb;
	--sitx-diag-primary: #2563eb;
	--sitx-diag-success: #22c55e;
	--sitx-diag-warning: #f59e0b;
	--sitx-diag-danger: #ef4444;
	background: var(--sitx-diag-canvas);
}
```

- [ ] **Step 2: Enqueue the files only on the diagnostics submenu**

After the shared admin enqueue block in `siteintelix_enqueue_admin_assets()`, add:

```php
if ( false !== strpos( (string) $hook_suffix, 'siteintelix-server-diagnostics' ) ) {
	wp_enqueue_style(
		'siteintelix-server-diagnostics-style',
		SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-server-diagnostics.css',
		array( 'siteintelix-admin-style' ),
		SITEINTELIX_VERSION
	);
	wp_enqueue_script(
		'siteintelix-server-diagnostics-script',
		SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-server-diagnostics.js',
		array(),
		SITEINTELIX_VERSION,
		true
	);
}
```

- [ ] **Step 3: Run the asset test**

Run `node --test wp-content/plugins/siteintelix/tests/structural.test.mjs`.

Expected: the asset contract passes; the dashboard markup contract still fails.

- [ ] **Step 4: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix`. If Git is available, commit with `feat: load scoped server diagnostics assets`.

### Task 3: Build Presentation Metadata and the Health-First Shell

**Files:**
- Modify: `wp-content/plugins/siteintelix/includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php:51-303`
- Modify: `wp-content/plugins/siteintelix/tests/runtime-smoke.php`

- [ ] **Step 1: Add failing runtime assertions for status metadata**

In `tests/runtime-smoke.php`, invoke the existing reflection helper used by that file to call `summarize()` and assert:

```php
$summary = $invoke_private(
	'SITEINTELIX_Server_Diagnostics_Module',
	'summarize',
	array(
		array(
			array( 'label' => 'A', 'value' => 'bad', 'status' => 'danger', 'detail' => 'x' ),
			array( 'label' => 'B', 'value' => 'warn', 'status' => 'warning', 'detail' => 'y' ),
			array( 'label' => 'C', 'value' => 'ok', 'status' => 'pass', 'detail' => 'z' ),
			array( 'label' => 'D', 'value' => 'note', 'status' => 'info', 'detail' => 'n' ),
		),
	)
);
$assert( 1 === $summary['failed'], 'summary counts critical failures' );
$assert( 1 === $summary['warnings'], 'summary counts warnings' );
$assert( 1 === $summary['passed'], 'summary counts passed checks' );
$assert( 1 === $summary['information'], 'summary counts information checks' );
```

- [ ] **Step 2: Run the runtime smoke test and confirm failure**

Run `php wp-content/plugins/siteintelix/tests/runtime-smoke.php`.

Expected: failure because `passed` and `information` are absent.

- [ ] **Step 3: Extend `summarize()` and add section metadata**

Track `$passed` and `$information`, treating `neutral` as information, and return both keys. Add a focused helper:

```php
private static function section_summary( $rows ) {
	$summary  = self::summarize( $rows );
	$severity = $summary['failed'] ? 'danger' : ( $summary['warnings'] ? 'warning' : 'pass' );
	return array(
		'total'       => count( $rows ),
		'failed'      => $summary['failed'],
		'warnings'    => $summary['warnings'],
		'passed'      => $summary['passed'],
		'information' => $summary['information'],
		'severity'    => $severity,
	);
}
```

- [ ] **Step 4: Replace overview and layout markup**

Update `render_page()` to render the approved header actions, four KPI cards, filter bar, polite live region, severity-sorted section shells, utility rail, and healthy state. Pass section definitions through one array so rendering and sorting share a single source:

```php
$sections = array(
	array( 'key' => 'php_server', 'title' => __( 'PHP & Server', 'siteintelix' ), 'icon' => 'dashicons-editor-code', 'rows' => $report['php_server'] ),
	array( 'key' => 'extensions', 'title' => __( 'Extensions', 'siteintelix' ), 'icon' => 'dashicons-admin-plugins', 'rows' => $report['extensions'] ),
	array( 'key' => 'filesystem', 'title' => __( 'Filesystem', 'siteintelix' ), 'icon' => 'dashicons-media-default', 'rows' => $report['filesystem'] ),
	array( 'key' => 'network', 'title' => __( 'Network', 'siteintelix' ), 'icon' => 'dashicons-admin-site-alt', 'rows' => $report['network'] ),
	array( 'key' => 'wordpress', 'title' => __( 'WordPress', 'siteintelix' ), 'icon' => 'dashicons-wordpress', 'rows' => $report['wordpress'] ),
	array( 'key' => 'database', 'title' => __( 'Database', 'siteintelix' ), 'icon' => 'dashicons-database', 'rows' => $report['database'] ),
);
foreach ( $sections as &$section ) {
	$section['summary'] = self::section_summary( $section['rows'] );
}
unset( $section );
```

Use a stable severity rank (`danger` 0, `warning` 1, `pass` 2), preserving source order for ties. Render “Everything looks healthy.” only when both failed and warning counts are zero.

- [ ] **Step 5: Render safe progressive payloads and no-script fallback**

For every section, output a `<script type="application/json" data-sitx-diag-payload>` containing `wp_json_encode()` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Add a `<noscript>` copy of the current semantic table so every value remains accessible without JavaScript.

- [ ] **Step 6: Verify PHP and structural behavior**

Run:

```bash
php -l wp-content/plugins/siteintelix/includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php
php wp-content/plugins/siteintelix/tests/runtime-smoke.php
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: no syntax errors; runtime assertions pass; the dashboard contract passes.

- [ ] **Step 7: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix`. If Git is available, commit with `feat: render health-first diagnostics dashboard`.

### Task 4: Implement Pure Filtering, Ordering, and Windowing Helpers with TDD

**Files:**
- Create: `wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs`
- Modify: `wp-content/plugins/siteintelix/assets/admin/js/siteintelix-server-diagnostics.js`

- [ ] **Step 1: Write failing unit tests**

Export helpers for Node only and test combined filtering, severity order, and window ranges:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { filterRows, orderRows, visibleRange } = require('../assets/admin/js/siteintelix-server-diagnostics.js');

const rows = [
	{ label: 'PHP version', value: '8.3', status: 'pass', detail: 'Modern PHP' },
	{ label: 'Memory limit', value: '128M', status: 'warning', detail: 'Use 256M' },
	{ label: 'File uploads', value: 'Off', status: 'danger', detail: 'Required' },
	{ label: 'SAPI', value: 'fpm', status: 'info', detail: 'Runtime' },
];

test('filterRows combines status and normalized text search', () => {
	assert.deepEqual(filterRows(rows, 'warnings', 'memory').map((row) => row.label), ['Memory limit']);
	assert.deepEqual(filterRows(rows, 'all', '256m').map((row) => row.label), ['Memory limit']);
});

test('orderRows puts problems before information and passes', () => {
	assert.deepEqual(orderRows(rows).map((row) => row.status), ['danger', 'warning', 'info', 'pass']);
});

test('visibleRange applies overscan and clamps to list bounds', () => {
	assert.deepEqual(visibleRange(500, 40, 400, 320, 5), { start: 5, end: 23 });
});
```

- [ ] **Step 2: Run and confirm failure**

Run `node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs`.

Expected: import/export failure because the helpers do not exist.

- [ ] **Step 3: Implement the pure helpers**

Define `normalizeText`, `filterRows`, `orderRows`, and `visibleRange` outside the browser initializer. Status mapping is: issues → danger, warnings → warning, passed → pass, information → info and neutral. Search concatenates label, value, detail, and recommended value. Export with:

```js
if ( typeof module !== 'undefined' && module.exports ) {
	module.exports = { filterRows: filterRows, orderRows: orderRows, visibleRange: visibleRange };
}
```

The unit test already uses `createRequire(import.meta.url)`, so the CommonJS conditional export loads from the browser-safe file without changing how WordPress runs it.

- [ ] **Step 4: Run unit and structural tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: all tests pass.

- [ ] **Step 5: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix`. If Git is available, commit with `test: cover diagnostics filtering and windowing`.

### Task 5: Implement Accordion, Search, Filters, Copy, and Progressive Rendering

**Files:**
- Modify: `wp-content/plugins/siteintelix/assets/admin/js/siteintelix-server-diagnostics.js`

- [ ] **Step 1: Add first-expansion rendering**

On accordion activation, parse its adjacent JSON payload once, order rows by severity, render errors and warnings immediately, and place passed rows under a subordinate disclosure. Set a `data-rendered="true"` flag to avoid repeated work. Use only `textContent` for diagnostic values.

- [ ] **Step 2: Add accessible accordion state**

Toggle `aria-expanded`, the panel `hidden` property, and the section’s `is-open` class. Native buttons provide Enter and Space behavior. After Collapse All, leave focus on the initiating control; do not move focus into hidden content.

- [ ] **Step 3: Add combined filtering and instant search**

Listen to filter clicks and search `input`. Re-run `filterRows()` against parsed section data, update section visibility/count summaries, update `aria-pressed` on filter buttons, and announce “N checks shown” in the polite live region. A zero result renders the resettable empty state.

- [ ] **Step 4: Add bulk controls and mobile section menu**

Expand All progressively renders and opens every visible section. Collapse All closes every section. The mobile “Section controls” menu invokes the same functions and closes after selection.

- [ ] **Step 5: Add list windowing above 200 rows**

For filtered lists over 200 rows, render a fixed-height scroll viewport with top and bottom spacers using `visibleRange()`, a 40px estimated row height, and five-row overscan. Recalculate on scroll with `requestAnimationFrame`. Lists of 200 or fewer render normally.

- [ ] **Step 6: Add copy feedback**

Read the redacted report source, use `navigator.clipboard.writeText()`, fall back to a temporary textarea, and announce success/failure in the live region. Never copy the unredacted JSON payload.

- [ ] **Step 7: Run JavaScript tests**

Run:

```bash
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-server-diagnostics.js
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: syntax check and all tests pass.

- [ ] **Step 8: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix`. If Git is available, commit with `feat: add interactive diagnostics workflow`.

### Task 6: Implement the Approved Responsive Visual System

**Files:**
- Modify: `wp-content/plugins/siteintelix/assets/admin/css/siteintelix-server-diagnostics.css`

- [ ] **Step 1: Build the desktop layout**

Implement the approved 1440px arrangement: 1.45fr health card plus three KPI cards, sticky summary/filter layers that respect the WordPress admin bar, `minmax(0, 1fr) 240px` content columns, 12px card radii, restrained one-pixel borders, and hover elevation only on accordion cards.

- [ ] **Step 2: Style states and progressive content**

Use compact bordered badges, current/recommended value blocks, 8px spacing increments, a bordered passed-check disclosure, bounded wrapping code values, the healthy illustration, no-results state, utility widgets, and list-window spacers. Avoid full-card status fills.

- [ ] **Step 3: Add accessibility rules**

Use a visible `:focus-visible` ring (`0 0 0 3px rgba(37, 99, 235, .28)` plus a solid outline), minimum 40px desktop and 44px touch targets, high-contrast badge text, and a visually-hidden helper class. Add reduced-motion rules that set transition and animation duration to near-zero.

- [ ] **Step 4: Add tablet and mobile breakpoints**

At 1024px, change summary to two columns and allow utilities to move below when the main column would fall below 640px. Below 768px, make health full width, keep KPI cards in two columns, stack issue fields, move utilities below, make filters horizontally scrollable, and prevent page-level horizontal overflow. Below 480px, preserve two KPI columns but reduce internal padding.

- [ ] **Step 5: Add print behavior**

Hide filter controls and utility actions, expand readable diagnostic content, remove sticky positioning, and use black text on white backgrounds for printed support copies.

- [ ] **Step 6: Run structural tests**

Run `node --test wp-content/plugins/siteintelix/tests/structural.test.mjs`.

Expected: all CSS token, dependency, reduced-motion, and focus tests pass.

- [ ] **Step 7: Record the checkpoint**

Run `svn status wp-content/plugins/siteintelix`. If Git is available, commit with `style: apply responsive diagnostics design system`.

### Task 7: Full Verification and Visual Acceptance

**Files:**
- Verify all files changed in Tasks 1–6.

- [ ] **Step 1: Run all automated checks**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
node --test wp-content/plugins/siteintelix/tests/server-diagnostics-ui.test.mjs
php -l wp-content/plugins/siteintelix/includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php
php wp-content/plugins/siteintelix/tests/debug-log-parser.php
php wp-content/plugins/siteintelix/tests/editor-links.php
php wp-content/plugins/siteintelix/tests/runtime-smoke.php
```

Expected: every command exits zero with no PHP syntax errors.

- [ ] **Step 2: Verify the live page at four widths**

Open `http://server-info.local/wp-admin/admin.php?page=siteintelix-server-diagnostics` while authenticated and inspect at 1440px, 1024px, 768px, and 390px. Confirm no horizontal page overflow, sticky elements do not cover WordPress chrome, utilities move below content on mobile, and issue counts remain above the fold on desktop.

- [ ] **Step 3: Verify keyboard and screen-reader semantics**

Tab through all controls; activate filters and accordions with Enter and Space; confirm visible focus; inspect `aria-expanded`, `aria-controls`, `aria-pressed`, and the live region in browser accessibility tools; enable reduced motion and confirm accordion transitions are suppressed.

- [ ] **Step 4: Verify diagnostic preservation and performance**

Compare the number and text of checks in the page payloads with `self::format_text_report()`. Confirm every row is reachable, passed checks are initially hidden, no section body is rendered before expansion, search is immediate, and a synthetic section over 200 rows uses the windowed viewport.

- [ ] **Step 5: Verify empty and failure states**

Use temporary local test fixtures to render an all-pass report, a no-search-results state, a missing recommended value, a copy failure, and a very long path. Remove the fixtures after inspection; confirm the healthy message reads “Everything looks healthy.” and long values do not create page overflow.

- [ ] **Step 6: Review the final diff/status**

Run:

```bash
svn status wp-content/plugins/siteintelix
svn diff wp-content/plugins/siteintelix
```

Expected: only planned diagnostics files, tests, and approved documentation are changed; no unrelated user files are touched.

- [ ] **Step 7: Create the final version-control checkpoint if available**

If the directory is placed under Git before execution, commit with `feat: redesign server diagnostics dashboard`. Otherwise, deliver the verified SVN status/diff without committing.
