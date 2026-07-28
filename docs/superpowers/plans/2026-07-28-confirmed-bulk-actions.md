# Confirmed Bulk Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Submit Code Snippets and Custom CSS & JS bulk forms after the user confirms the shared dialog.

**Architecture:** Generalize the shared confirmation helper to perform either anchor navigation or associated form submission. Keep module backends unchanged and make both Apply buttons explicit submit controls.

**Tech Stack:** Vanilla JavaScript, WordPress admin-post forms, Node.js built-in test runner and VM.

---

### Task 1: Add failing shared-confirmation tests

**Files:**
- Modify: `tests/admin-interactions.test.mjs`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Build a minimal confirmation-dialog fixture**

Evaluate `assets/admin/js/siteintelix-core.js` in a VM with fake document elements, dispatch a click on a `data-siteintelix-confirm` submit button, then click the generated Confirm control.

- [ ] **Step 2: Assert confirmed form submission**

Use a fake form with counters:

```js
const form = {
	requestSubmit(submitter) {
		submissions.push(submitter);
	},
};
```

Assert one submission occurs with the original Apply button as submitter.

- [ ] **Step 3: Preserve confirmed link navigation and cancellation**

Assert an anchor still calls `window.location.assign(anchor.href)` and Cancel invokes neither navigation nor submission.

- [ ] **Step 4: Require explicit submit buttons structurally**

Read both list views and assert their bulk Apply buttons contain `type="submit"` and `data-siteintelix-confirm`.

- [ ] **Step 5: Run tests and verify RED**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: form-confirmation assertions fail because the current helper attempts link navigation for the button.

### Task 2: Generalize the shared confirmation helper

**Files:**
- Modify: `assets/admin/js/siteintelix-core.js`
- Modify: `includes/modules/code-snippets/views/list.php`
- Modify: `includes/modules/custom-code/views/list.php`
- Test: `tests/admin-interactions.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Replace link-only confirmation with element actions**

On confirmation:

```js
if (element.matches('a[href]')) {
	window.location.assign(element.href);
} else if (element.form) {
	if ('function' === typeof element.form.requestSubmit) {
		element.form.requestSubmit(element);
	} else {
		element.form.submit();
	}
}
```

Close the dialog before performing the action and preserve all existing accessibility behavior.

- [ ] **Step 2: Mark both Apply controls as submit buttons**

Add `type="submit"` to the Code Snippets and Custom CSS & JS bulk Apply buttons.

- [ ] **Step 3: Run focused tests and verify GREEN**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: both commands exit successfully.
