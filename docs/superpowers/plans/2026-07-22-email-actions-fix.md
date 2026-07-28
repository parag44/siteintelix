# Email Actions Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore the visible test-email submit action and add secure delete-all behavior to Email Log bulk actions.

**Architecture:** Keep the existing dialog and admin-post workflows. Correct token scoping at the dialog component boundary, add an explicit action container, and route a new `delete_all` bulk value through the existing capability/nonce-protected handler and a shared table-clear helper.

**Tech Stack:** WordPress PHP, vanilla JavaScript, scoped CSS, Node.js structural tests.

---

### Task 1: Add failing dialog and bulk-delete regression tests

**Files:**
- Modify: `tests/email-log-performance.test.mjs`

- [ ] Assert the JS creates a `sitx-action-dialog__actions` container and uses a localized `sendEmail` label.
- [ ] Assert PHP renders `<option value="delete_all">` and handles `delete_all` before requiring selected IDs.
- [ ] Run `node --test tests/email-log-performance.test.mjs`; expect failures for the missing footer, label, and route.

### Task 2: Repair the dialog action presentation

**Files:**
- Modify: `assets/admin/js/siteintelix-email-log.js`
- Modify: `assets/admin/css/siteintelix-email-log.css`
- Modify: `siteintelix.php`

- [ ] Create a dedicated actions element, append Cancel and Send Email to it, and localize `sendEmail`.
- [ ] Give dialog controls explicit fallbacks for colors, borders, radii, and spacing when rendered outside the SiteIntelix wrapper.
- [ ] Run the targeted Node test and `node --check assets/admin/js/siteintelix-email-log.js`.

### Task 3: Add protected Delete all logs bulk handling

**Files:**
- Modify: `includes/modules/email-log/class-siteintelix-email-log-module.php`

- [ ] Add the `delete_all` selector option.
- [ ] Extract table clearing into one private helper used by the header handler and bulk handler.
- [ ] Route `delete_all` after capability/nonce verification but before selected-ID validation.
- [ ] Update the screen JS so delete-all does not require checkbox selection and uses a specific destructive message.
- [ ] Run targeted tests and PHP/JS syntax checks.

### Task 4: Regression verification

**Files:**
- Test: `tests/email-log-performance.test.mjs`
- Test: `tests/admin-interactions.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] Run all Node test files and require zero failures.
- [ ] Lint every modified PHP file and check the Email Log JavaScript syntax.
- [ ] Reload and inspect the local page without submitting the email form or confirming deletion.
- [ ] Do not commit, push, package, upload, send mail, or delete existing logs.
