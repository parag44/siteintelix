# Native Mixed Snippet Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Accept valid mixed PHP/HTML snippets while continuing to reject native syntax errors and `__halt_compiler`.

**Architecture:** Treat stored snippets as PHP-mode bodies by prefixing one synthetic opening tag, then rely on `token_get_all(..., TOKEN_PARSE)` as the sole syntax authority. The isolated `eval()` runner already supports valid closing/reopening transitions.

**Tech Stack:** PHP tokenizer, WordPress `WP_Error`, dependency-free PHP regression tests.

---

### Task 1: Capture mixed-template behavior

**Files:**
- Modify: `tests/code-snippets-validator.php`
- Create: `tests/fixtures/tutor-branded-pdf-viewer.php.txt`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add the complete reported snippet fixture**

Copy the approved attachment unchanged to `tests/fixtures/tutor-branded-pdf-viewer.php.txt`, preserving its mid-snippet `?>` and `<?php` transitions.

- [ ] **Step 2: Replace obsolete tag-rejection assertions**

Assert:

```php
true === SITEINTELIX_Snippets_Validator::validate( 'function render_template() { ?>Template<?php }' )
```

and assert the complete fixture validates successfully.

- [ ] **Step 3: Add malformed mixed-template coverage**

Validate `function broken_template() { ?>Template<?php if (` and require a `siteintelix_snippet_parse` error. Keep the existing invalid tagless PHP and `__halt_compiler` assertions.

- [ ] **Step 4: Require the obsolete policy to disappear**

Structurally assert that the validator and editor no longer contain “Enter PHP without opening or closing tags” or “Do not include opening or closing PHP tags.”

- [ ] **Step 5: Run tests and verify RED**

Run:

```bash
php wp-content/plugins/siteintelix/tests/code-snippets-validator.php
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: valid mixed-template assertions fail under the current manual tag policy.

### Task 2: Use native syntax validation

**Files:**
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-validator.php`
- Modify: `includes/modules/code-snippets/views/editor.php`
- Test: `tests/code-snippets-validator.php`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Remove manual tag detection**

Delete `contains_php_tags()` and its early error return. Keep the synthetic source, native `TOKEN_PARSE` call, bounded parse message, and `T_HALT_COMPILER` scan.

- [ ] **Step 2: Correct editor guidance**

Replace the obsolete help text with:

```php
esc_html_e( 'Snippets start in PHP mode. You may close and reopen PHP when outputting template markup. Syntax is checked before saving.', 'siteintelix' );
```

- [ ] **Step 3: Run focused tests and verify GREEN**

Run:

```bash
php wp-content/plugins/siteintelix/tests/code-snippets-validator.php
php wp-content/plugins/siteintelix/tests/code-snippets-normalization.php
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: all commands exit successfully.

### Task 3: Full verification

**Files:**
- Verify all files changed by the three implementation plans.

- [ ] **Step 1: Lint changed PHP and JavaScript**

Run PHP lint on the migration, validator, views, and PHP tests; run `node --check` on the shared script and Node tests.

- [ ] **Step 2: Run every dependency-free PHP test**

Run every `tests/*.php` file and require zero failures.

- [ ] **Step 3: Run every Node test**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/*.test.mjs
```

Expected: zero failures.

- [ ] **Step 4: Exercise the live migration and verify MU ownership**

Run the migration through WordPress, confirm the verified retired safety guard disappears, and compare third-party MU-file checksums before and after.

- [ ] **Step 5: Verify both bulk actions in the browser**

On each management page, select a safe test entry, apply a reversible status action, confirm the dialog, and verify the status changes after redirect.
