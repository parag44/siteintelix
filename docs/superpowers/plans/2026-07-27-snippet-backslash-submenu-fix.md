# Snippet Backslash and Editor Submenu Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve PHP namespace backslashes across snippet saves and remove redundant editor routes from the SiteIntelix sidebar.

**Architecture:** Raw WordPress request data is unslashed once by an explicit request-normalization boundary. Repository insert/update methods accept canonical data and sanitize without further unslashing; hidden editor screens use parentless submenu registration.

**Tech Stack:** WordPress PHP 7.4+, `$wpdb`, dependency-free PHP tests, Node structural tests, WP-CLI runtime checks.

---

### Task 1: Backslash regression

**Files:**
- Create: `tests/code-snippets-normalization.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-repository.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`

- [ ] Add a dependency-free test for `normalize_request()` using a WordPress-slashed `\TUTOR\Icon::RIGHT_ARROW_UP` body.
- [ ] Run `php tests/code-snippets-normalization.php` and confirm it fails because `normalize_request()` is absent.
- [ ] Implement request normalization and canonical repository preparation, ensuring code from existing rows is never unslashed.
- [ ] Pass raw `$_POST` through `normalize_request()` once in the admin controller and persist canonical data.
- [ ] Run the focused test and confirm it passes.

### Task 2: Hidden editor routes

**Files:**
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-admin.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing assertions that both add/edit routes use `add_submenu_page( null, ...)`.
- [ ] Change only the editor route parents to `null`; retain the visible module list routes.
- [ ] Run the structural test and confirm it passes.

### Task 3: Live repair and package

**Files:**
- Rebuild: `../siteintelix.zip`

- [ ] Update snippet ID 3 from `TUTORIcon` to `\TUTOR\Icon`, validate it, and reactivate it.
- [ ] Verify the database preserves both namespace separators and request the Tutor dashboard without a new `TUTORIcon` fatal.
- [ ] Run all Node tests, existing PHP tests, and full PHP syntax checks.
- [ ] Rebuild the ZIP excluding local brainstorming state and validate it with `unzip -t`.

### Version-control note

The plugin directory is not a Git working tree, so worktree and commit steps do not apply.
