# Custom Code Management UI and Settings Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move custom-code retention into two module settings tabs and redesign both code-management list pages with the SiteIntelix design system.

**Architecture:** Each module owns its settings registration, renderer, save handler, and uninstall option. Both list templates share consistent component conventions while retaining separate data repositories and actions.

**Tech Stack:** WordPress PHP 7.4+, SiteIntelix CSS tokens/components, dependency-free JavaScript, Node structural tests.

---

### Task 1: Module-owned settings

**Files:**
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-module.php`
- Create: `includes/modules/custom-code/views/settings.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-code-snippets-module.php`
- Create: `includes/modules/code-snippets/views/settings.php`
- Modify: `admin/views/settings-page.php`
- Modify: `siteintelix.php`
- Modify: `uninstall.php`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing assertions for both registry settings definitions, both module tabs/handlers, absence of the standalone card, and independent uninstall options.
- [ ] Run the focused structural test and confirm failure.
- [ ] Add module settings metadata and module-owned render/save hooks.
- [ ] Render one independent uninstall toggle in each module tab.
- [ ] Remove the standalone shared card/handler and preserve the retired option only as a migration fallback.
- [ ] Split uninstall cleanup and verify the focused test passes.

### Task 2: Custom CSS & JS management page

**Files:**
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-admin.php`
- Modify: `includes/modules/custom-code/views/list.php`
- Modify: `includes/modules/custom-code/assets/custom-code.css`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing assertions for header actions, summary chips, management card, compact filters/bulk controls, badges, empty state, responsive table wrapper, and clean row actions.
- [ ] Add repository counts to the controller.
- [ ] Rebuild the list template using SiteIntelix components without changing action URLs or nonces.
- [ ] Add screen-scoped responsive styles and pass the focused test.

### Task 3: Code Snippets management page

**Files:**
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Modify: `includes/modules/code-snippets/views/list.php`
- Modify: `includes/modules/code-snippets/assets/code-snippets.css`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing assertions for header actions, summary chips, filter/reset controls, combined bulk/export toolbar, status/scope badges, error indicator, empty state, and responsive table wrapper.
- [ ] Add bounded summary counts to the controller.
- [ ] Rebuild the list template while preserving recent-deactivation, run-once, import, export, and confirmations.
- [ ] Add screen-scoped responsive styles and pass the focused test.

### Task 4: Regression and package

**Files:**
- Rebuild: `../siteintelix.zip`

- [ ] Run all Node tests and dependency-free PHP tests.
- [ ] Run syntax checks for every PHP file.
- [ ] Run WordPress settings/runtime checks.
- [ ] Rebuild and validate the ZIP without `.superpowers`, `.git`, or `.DS_Store`.

### Version-control note

The plugin directory is not a Git working tree, so worktree and commit steps do not apply.
