# Module Toggle Focus Glitch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the temporary underline artifact from module toggles without reducing keyboard accessibility or changing toggle behavior.

**Architecture:** Keep the native checkbox and slider markup unchanged. Fully clip the native input, override its own focus shadow with a later, more specific rule, and preserve the visible slider focus indicator.

**Tech Stack:** CSS, Node.js built-in test runner, existing SiteIntelix structural and admin-interaction tests.

---

### Task 1: Fix the hidden checkbox focus rendering

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`
- Modify: `wp-content/plugins/siteintelix/assets/admin/css/siteintelix-admin.css`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`
- Test: `wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs`

- [ ] **Step 1: Write the failing CSS regression test**

Add this test to `tests/structural.test.mjs`:

```js
test('module toggle hides the native checkbox without hiding its slider focus indicator', async () => {
	const css = await read('assets/admin/css/siteintelix-admin.css');

	assert.match(
		css,
		/\.sitx-toggle input\s*\{[\s\S]*?clip:\s*rect\(0 0 0 0\);[\s\S]*?clip-path:\s*inset\(50%\);[\s\S]*?overflow:\s*hidden;[\s\S]*?\}/
	);
	assert.match(
		css,
		/\.sitx-toggle input:focus-visible\s*\{[\s\S]*?box-shadow:\s*none;[\s\S]*?outline:\s*0;[\s\S]*?\}/
	);
	assert.match(
		css,
		/\.sitx-toggle input:focus-visible\s*\+\s*\.sitx-toggle__slider\s*\{[\s\S]*?box-shadow:/
	);
});
```

- [ ] **Step 2: Run the structural suite and confirm the test fails**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: the new test fails because the hidden input does not yet use clipping and has no input-specific focus reset.

- [ ] **Step 3: Implement the minimal CSS fix**

Update the final `.sitx-toggle input` block in `assets/admin/css/siteintelix-admin.css` to include:

```css
.sitx-toggle input {
	border: 0;
	clip: rect(0 0 0 0);
	clip-path: inset(50%);
	height: 1px;
	margin: -1px;
	opacity: 0;
	overflow: hidden;
	padding: 0;
	position: absolute;
	white-space: nowrap;
	width: 1px;
}
```

Immediately before the existing slider focus rule, add:

```css
.sitx-toggle input:focus-visible {
	box-shadow: none;
	outline: 0;
}
```

Do not change `.sitx-toggle input:focus-visible + .sitx-toggle__slider`.

- [ ] **Step 4: Run focused regression tests**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
node --test wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs
```

Expected: both suites pass.

- [ ] **Step 5: Confirm the scope**

Inspect the changed CSS and verify:

- the native checkbox remains in the DOM and focusable;
- the slider retains its keyboard focus box shadow;
- JavaScript and PHP markup are unchanged;
- `wp-content/plugins/siteintelix.zip` was not rebuilt or modified.

This workspace is not a Git repository, so no commit step is available.
