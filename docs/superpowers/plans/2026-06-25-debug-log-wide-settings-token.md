# Debug Log Wide Layout and Settings Token Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Widen the Modern Debug Log layout, remove the occurrence/details expansion section except real stack traces, and refresh Settings with the shared SiteIntelix token polish.

**Architecture:** Keep the change contained to the Modern Debug Log view and admin CSS. Add structural tests that assert the removed UI is gone and final CSS overrides are present after legacy rules.

**Tech Stack:** WordPress PHP admin views, plain CSS, Node structural tests, PHP lint/runtime smoke tests.

---

### Task 1: Add failing structural coverage

**Files:**
- Modify: `/Users/joomshaper/Local Sites/server-info/app/public/wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add a test for trace-only Modern expansion and wide/token CSS**

Add one test that checks:

- Modern Debug Log PHP does not contain `sitx-timeline`, `Occurrence Timeline`, `Copy Details`, or `View Full Log`.
- Modern Debug Log PHP gates expansion controls behind non-empty stack trace text.
- Debug Log CSS contains a final `Modern wide trace-only layout` section with `1440px`.
- Admin CSS contains a final `Settings token refresh` section for the Settings shell.

- [ ] **Step 2: Run the test and verify it fails**

Run: `node --test wp-content/plugins/siteintelix/tests/structural.test.mjs`

Expected: the new test fails because the timeline/details UI and 1200px cap still exist.

### Task 2: Remove Modern Debug Log occurrence details

**Files:**
- Modify: `/Users/joomshaper/Local Sites/server-info/app/public/wp-content/plugins/siteintelix/admin/views/debug-log-page-modern.php`

- [ ] **Step 1: Gate grouped-card controls**

Render `.sitx-log-card__actions` only when `$siteintelix_stack_text` is not empty.

- [ ] **Step 2: Remove occurrence timeline/details footer**

Replace the existing `.sitx-log-card__details` block with a stack-only details block rendered only when `$siteintelix_stack_text` is not empty.

- [ ] **Step 3: Run PHP lint**

Run: `php -l wp-content/plugins/siteintelix/admin/views/debug-log-page-modern.php`

Expected: `No syntax errors detected`.

### Task 3: Add final CSS overrides

**Files:**
- Modify: `/Users/joomshaper/Local Sites/server-info/app/public/wp-content/plugins/siteintelix/assets/admin/css/siteintelix-debug-log.css`
- Modify: `/Users/joomshaper/Local Sites/server-info/app/public/wp-content/plugins/siteintelix/assets/admin/css/siteintelix-admin.css`

- [ ] **Step 1: Add Debug Log final wide layout rules**

Append a final `Modern wide trace-only layout` section that sets the Modern Debug Log content/header sections to `max-width: 1440px` and makes `.sitx-stack--full` fill the card width.

- [ ] **Step 2: Add Settings token refresh rules**

Append a final `Settings token refresh` section that widens `#siteintelix-settings-page .siteintelix-container`, polishes `.sitx-settings-shell`, `.sitx-settings-topbar`, `.sitx-settings-tab`, `.sitx-settings-search`, `.sitx-setting-row`, `.sitx-form-field`, and `.sitx-side-card`.

- [ ] **Step 3: Run structural tests**

Run: `node --test wp-content/plugins/siteintelix/tests/structural.test.mjs`

Expected: all structural tests pass.

### Task 4: Verify runtime and browser behavior

**Files:**
- Test only

- [ ] **Step 1: Run PHP parser/runtime tests**

Run:

```bash
php wp-content/plugins/siteintelix/tests/debug-log-parser.php
php -d 'mysqli.default_socket=/Users/joomshaper/Library/Application Support/Local/run/7QtrlWtxz/mysql/mysqld.sock' /usr/local/bin/wp eval-file wp-content/plugins/siteintelix/tests/runtime-smoke.php --path='/Users/joomshaper/Local Sites/server-info/app/public'
```

Expected: both pass.

- [ ] **Step 2: Browser-check Modern Debug Log**

Open `/wp-admin/admin.php?page=siteintelix-debug-log` and confirm:

- content max width is 1440px
- no occurrence timeline/details footer exists
- no-stack groups do not render expansion controls
- real stack groups still expand to a full-width stack trace

- [ ] **Step 3: Browser-check Settings**

Open `/wp-admin/admin.php?page=siteintelix-settings` and confirm the Settings page uses the wider, polished token styling.
