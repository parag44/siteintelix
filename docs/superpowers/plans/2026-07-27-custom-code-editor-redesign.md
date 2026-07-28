# Custom Code Editor Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the raw Custom CSS & JS and Code Snippets editor screens with a consistent responsive SiteIntelix editor workspace and stop exposing snippet import/export.

**Architecture:** Keep the existing controllers, repositories, form fields, nonces, and CodeMirror initialization. Change only editor presentation, module-specific CSS, and the snippet admin routes/controls that expose import/export; retain the dormant transfer implementation.

**Tech Stack:** PHP 7.4+, WordPress Admin APIs, CodeMirror via `wp_enqueue_code_editor()`, SiteIntelix CSS tokens, Node.js structural tests.

---

## File map

- `tests/structural.test.mjs`: structural coverage for the editor layout and hidden snippet transfer workflow.
- `includes/modules/custom-code/views/editor.php`: accessible Custom CSS & JS editor markup.
- `includes/modules/custom-code/assets/custom-code.css`: Custom CSS & JS workspace and responsive styling.
- `includes/modules/code-snippets/views/editor.php`: accessible PHP snippet editor markup.
- `includes/modules/code-snippets/assets/code-snippets.css`: snippet workspace and responsive styling.
- `includes/modules/code-snippets/views/list.php`: remove Import and Export controls.
- `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`: stop registering and handling import/export requests.
- `includes/modules/code-snippets/class-siteintelix-code-snippets-module.php`: retain the dormant transfer include for future reuse.

### Task 1: Add failing structural coverage

**Files:**
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add a failing editor-layout test**

Add assertions requiring both editor views to contain a back-list header action, `sitx-code-editor-workspace`, `sitx-code-editor-main`, `sitx-code-editor-settings`, `sitx-code-editor-actions`, `si-card`, and `si-button`. Require both module stylesheets to define the workspace, settings card, sticky sidebar, and mobile stacking.

- [ ] **Step 2: Add a failing transfer-exposure test**

Assert that the snippet list and controller do not contain:

```js
assert.doesNotMatch(snippetsList, /siteintelix-code-snippets-import|Export selected|sitx-code-export/);
assert.doesNotMatch(snippetsAdmin, /admin_post_siteintelix_snippet_(?:import|export)|render_import|send_export|invalid_import_upload/);
assert.match(snippetsModule, /class-siteintelix-snippets-transfer\.php/);
```

- [ ] **Step 3: Run the focused tests and verify RED**

Run:

```bash
node --test --test-name-pattern='custom-code editors use|snippet import and export stay hidden' tests/structural.test.mjs
```

Expected: both tests fail because the editor workspace classes are absent and import/export is still registered.

### Task 2: Remove snippet import/export exposure

**Files:**
- Modify: `includes/modules/code-snippets/views/list.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Modify: `includes/modules/code-snippets/assets/code-snippets.css`

- [ ] **Step 1: Remove list controls**

Delete the `$import_url`, Import header action, `.sitx-code-export` block, and its unused CSS. Preserve the bulk Activate, Deactivate, and Delete workflow.

- [ ] **Step 2: Remove routes and request handlers**

Delete registration of:

```php
admin_post_siteintelix_snippet_export
admin_post_siteintelix_snippet_import
siteintelix-code-snippets-import
```

Delete `render_import()`, `export()`, `send_export()`, `import()`, and `invalid_import_upload()`. Remove the `export_selected` branch from `bulk()`. Keep the transfer class include in the module bootstrap.

- [ ] **Step 3: Run the transfer-exposure test and verify GREEN**

Run:

```bash
node --test --test-name-pattern='snippet import and export stay hidden' tests/structural.test.mjs
```

Expected: PASS.

### Task 3: Redesign the Custom CSS & JS editor

**Files:**
- Modify: `includes/modules/custom-code/views/editor.php`
- Modify: `includes/modules/custom-code/assets/custom-code.css`

- [ ] **Step 1: Build the focused editor markup**

Use `SITEINTELIX_Admin_UI::page_header()` with a secondary “Back to Custom CSS & JS” action. Inside the existing form, render:

```php
<div class="sitx-code-editor-workspace">
	<main class="sitx-code-editor-main si-card">
		<div class="sitx-code-editor-card__header">...</div>
		<div class="sitx-code-editor-card__body">...</div>
	</main>
	<aside class="sitx-code-editor-sidebar">
		<section class="sitx-code-editor-settings si-card">...</section>
		<section class="sitx-code-editor-actions si-card">...</section>
	</aside>
</div>
```

Keep the existing form action, nonce, `entry_id`, field names, selected values, status hidden field, and submit-mode values.

- [ ] **Step 2: Add module editor styling**

Define a flexible main column plus 320px sidebar, card headers/bodies, vertically stacked labeled fields, full-width controls, sticky sidebar, CodeMirror minimum height, and a 900px stacked breakpoint. Use existing `--si-*` tokens.

- [ ] **Step 3: Run PHP syntax and focused editor test**

Run:

```bash
php -l includes/modules/custom-code/views/editor.php
node --test --test-name-pattern='custom-code editors use' tests/structural.test.mjs
```

Expected: PHP reports no syntax errors; the combined editor test still fails only for the snippet editor.

### Task 4: Redesign the Code Snippets editor

**Files:**
- Modify: `includes/modules/code-snippets/views/editor.php`
- Modify: `includes/modules/code-snippets/assets/code-snippets.css`

- [ ] **Step 1: Build the focused snippet editor markup**

Use the same workspace/card structure and add a “Back to Code Snippets” header action. Preserve the existing nonce, action, `snippet_id`, name, code, scope, priority, tags, description, hidden status, error messages, and `save`/`save_activate` submit modes.

- [ ] **Step 2: Add snippet editor styling**

Mirror the custom-code editor structure with module-local selectors, sticky desktop sidebar, full-width controls and buttons, CodeMirror sizing, inline error presentation, and the 900px stacking breakpoint.

- [ ] **Step 3: Run the focused editor test and verify GREEN**

Run:

```bash
php -l includes/modules/code-snippets/views/editor.php
node --test --test-name-pattern='custom-code editors use' tests/structural.test.mjs
```

Expected: PASS.

### Task 5: Full regression verification

**Files:**
- Verify all modified PHP, CSS, and test files.

- [ ] **Step 1: Run PHP syntax checks**

Run:

```bash
php -l includes/modules/custom-code/views/editor.php
php -l includes/modules/code-snippets/views/editor.php
php -l includes/modules/code-snippets/views/list.php
php -l includes/modules/code-snippets/class-siteintelix-snippets-admin.php
```

Expected: no syntax errors.

- [ ] **Step 2: Run the complete test suite**

Run:

```bash
node --test tests/structural.test.mjs
php tests/debug-log-parser.php
php tests/editor-links.php
php tests/runtime-smoke.php
```

Expected: every test exits successfully with zero failures.

- [ ] **Step 3: Confirm packaging was not changed**

Check the existing ZIP timestamp and do not invoke `zip`, `wp dist-archive`, or any packaging command.
