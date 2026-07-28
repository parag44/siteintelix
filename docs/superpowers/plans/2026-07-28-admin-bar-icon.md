# SiteIntelix Admin-Bar Icon Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Use the exact WordPress `dashicons-chart-area` sidebar glyph immediately before the SiteIntelix label in the WordPress admin bar.

**Architecture:** Build the parent node title from a decorative native Dashicons element and an escaped text label inside the existing admin-bar registration function. WordPress’s admin-bar stylesheet already depends on Dashicons, so no extra asset or stylesheet is required; keep the node hierarchy, URLs, permissions, tooltip, and child shortcuts unchanged.

**Tech Stack:** WordPress PHP, Dashicons, Node.js structural tests.

---

### Task 1: Add the SiteIntelix admin-bar icon

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`
- Modify: `wp-content/plugins/siteintelix/siteintelix.php:345-394`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add a failing structural test**

Add:

```js
test('SiteIntelix admin-bar parent reuses the sidebar chart-area Dashicon', async () => {
	const main = await read('siteintelix.php');
	const start = main.indexOf('function siteintelix_register_admin_bar_link');
	const end = main.indexOf("add_action( 'admin_bar_menu', 'siteintelix_register_admin_bar_link'", start);
	const adminBarFunction = main.slice(start, end);

	assert.ok(start >= 0 && end > start);
	assert.match(adminBarFunction, /id'\s*=>\s*'siteintelix'/);
	assert.match(adminBarFunction, /class="ab-icon dashicons dashicons-chart-area"/);
	assert.match(adminBarFunction, /aria-hidden="true"/);
	assert.doesNotMatch(adminBarFunction, /<svg|currentColor/);
	assert.match(adminBarFunction, /<span class="ab-label">/);
	assert.match(adminBarFunction, /esc_html__\(\s*'SiteIntelix'/);
	assert.match(adminBarFunction, /'siteintelix-debug-log'/);
	assert.match(adminBarFunction, /'siteintelix-email-log'/);
});
```

- [ ] **Step 2: Run the structural test and confirm the red phase**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: the revised test fails because the parent title still contains the custom inline SVG instead of the exact `dashicons-chart-area` class.

- [ ] **Step 3: Replace the custom SVG with the native Dashicon**

Inside `siteintelix_register_admin_bar_link()`, replace the existing custom SVG assignment with:

```php
$siteintelix_admin_bar_icon  = '<span class="ab-icon dashicons dashicons-chart-area" aria-hidden="true"></span>';
$siteintelix_admin_bar_label = '<span class="ab-label">' . esc_html__( 'SiteIntelix', 'siteintelix' ) . '</span>';
```

Change only the parent node title:

```php
'title' => $siteintelix_admin_bar_icon . $siteintelix_admin_bar_label,
```

Do not change the parent ID, URL, tooltip, permissions, hook priority, or child nodes.

- [ ] **Step 4: Run the structural and PHP syntax checks**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
php -l wp-content/plugins/siteintelix/siteintelix.php
```

Expected: all structural tests pass and PHP reports no syntax errors.

- [ ] **Step 5: Verify scope**

Confirm:

- the icon uses the exact `dashicons dashicons-chart-area` classes used for the sidebar glyph;
- the decorative icon is hidden from assistive technology;
- no custom SVG remains in the admin-bar function;
- the visible escaped label remains present;
- Debug Log and Email Log remain children of the `siteintelix` node;
- no CSS, JavaScript, external asset, or ZIP file changed.

This workspace is not a Git repository, so no commit step is available.
