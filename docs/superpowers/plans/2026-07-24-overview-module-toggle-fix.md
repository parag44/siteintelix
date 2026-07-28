# Overview Module Toggle Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the quick module enable/disable switches on the SiteIntelix Overview page persist through the existing secured AJAX endpoint.

**Architecture:** Extend the already-loaded Overview JavaScript with a delegated module-toggle handler. Supply the handler through the Overview asset's existing localized data object, preserving the dependency-free, one-asset page architecture.

**Tech Stack:** WordPress PHP, vanilla JavaScript, Node.js built-in test runner.

---

### Task 1: Add a failing Overview toggle regression test

**Files:**
- Modify: `tests/admin-interactions.test.mjs`
- Test: `tests/admin-interactions.test.mjs`

- [ ] Add a minimal DOM/fetch fixture that loads `siteintelix-overview.js`, dispatches a toggle change, and asserts the AJAX payload, pending state, success reload, and error rollback.
- [ ] Run `node --test --test-name-pattern='Overview quick module toggles' wp-content/plugins/siteintelix/tests/admin-interactions.test.mjs`.
- [ ] Confirm the test fails because the current Overview asset registers no module-toggle request.

### Task 2: Implement the lightweight Overview handler

**Files:**
- Modify: `assets/admin/js/siteintelix-overview.js`
- Modify: `siteintelix.php`

- [ ] Add the delegated change handler and use the existing `SiteIntelixAdmin.toast` helper for visible status feedback.
- [ ] Add `ajaxUrl`, `moduleToggleNonce`, and translated updating/success/failure strings to `siteintelixOverviewData`.
- [ ] Re-run the targeted test and confirm it passes.

### Task 3: Verify and package

**Files:**
- Rebuild: `../siteintelix.zip`

- [ ] Run all SiteIntelix Node tests and PHP syntax checks.
- [ ] Rebuild the installable ZIP with the same production exclusions.
- [ ] Check ZIP integrity and confirm no Git/SVN push occurred.
