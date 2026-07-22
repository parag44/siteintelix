# Debug Log Modern Cleanup and Editor Links Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Simplify Modern filters, normalize message typography, add validated editor links to Modern and Terminal, and suppress duplicate/nonexistent stack traces.

**Architecture:** Modern and Terminal reuse the existing `SITEINTELIX_Editor_Links` resolver. A focused trace extractor removes the already-rendered primary error and retains only genuine continuation frames; CSS and JavaScript cleanup removes obsolete UI behavior.

**Tech Stack:** WordPress PHP templates, vanilla JavaScript, CSS, Node structural tests, WP-CLI runtime smoke tests.

---

### Task 1: Remove Obsolete Modern Controls and Normalize Message Weight

**Files:**
- Modify: `admin/views/debug-log-page-modern.php`
- Modify: `assets/admin/js/siteintelix-debug-log.js`
- Modify: `assets/admin/css/siteintelix-debug-log.css`
- Test: `tests/structural.test.mjs`

- [ ] Add a structural test that rejects `More Filters`, `Clear Filters`, `Saved Filters`, and their DOM hooks, while requiring the three checkbox filters and `font-weight: 400` for `.sitx-log-full-message`.
- [ ] Run `node --test tests/structural.test.mjs` and confirm the new test fails.
- [ ] Delete the three controls and their unused JavaScript event branches. Keep the checkbox row always visible.
- [ ] Add a final CSS override setting `.sitx-log-full-message { font-weight: 400; }`.
- [ ] Run structural tests, `node --check assets/admin/js/siteintelix-debug-log.js`, and PHP lint.

### Task 2: Add Validated Editor Links to Modern and Terminal

**Files:**
- Modify: `admin/views/debug-log-page-modern.php`
- Modify: `admin/views/debug-log-page-terminal.php`
- Modify: `assets/admin/css/siteintelix-debug-log.css`
- Test: `tests/structural.test.mjs`
- Test: `tests/editor-links.php`

- [ ] Add structural tests requiring `SITEINTELIX_Editor_Links::get_link()` in Modern and Terminal, safe new-tab attributes, and linked path classes.
- [ ] Run tests and confirm failure.
- [ ] Resolve grouped and single Modern links and render anchors only when valid.
- [ ] Resolve Terminal links and render only the dedicated path metadata as an editor anchor after the complete message, leaving unsupported paths plain text.
- [ ] Replace the disabled Modern `Open in Editor` placeholder with the validated native editor link when available.
- [ ] Run structural tests, runtime editor-link tests, and template lint.

### Task 3: Remove Duplicate Primary Errors from Stack Trace Panels

**Files:**
- Modify: `admin/views/debug-log-page-modern.php`
- Modify: `assets/admin/css/siteintelix-debug-log.css`
- Test: `tests/structural.test.mjs`

- [ ] Add tests requiring a trace extractor that removes the first message line and conditionally renders `.sitx-stack`.
- [ ] Run tests and confirm failure.
- [ ] Extract continuation lines beginning with `Stack trace:`, `#`, or `thrown in`, plus lines within an active trace block.
- [ ] Render Stack Trace only when extracted trace text is non-empty.
- [ ] Add a modifier class so timeline-only details use the full width.
- [ ] Run structural tests and PHP lint.

### Task 4: Full Verification

**Files:**
- Verify all modified files.

- [ ] Run `php tests/debug-log-parser.php`.
- [ ] Run `node --test tests/structural.test.mjs`.
- [ ] Run `wp eval-file wp-content/plugins/siteintelix/tests/editor-links.php`.
- [ ] Run `wp eval-file wp-content/plugins/siteintelix/tests/runtime-smoke.php`.
- [ ] Run JavaScript and PHP syntax checks.
- [ ] Verify CSS brace balance.
- [ ] Live-check Modern control removal, normal message weight, core path plain text, and stack suppression; verify Terminal rendering; restore Classic mode.

> The plugin directory is not a Git repository, so commit steps are unavailable.
