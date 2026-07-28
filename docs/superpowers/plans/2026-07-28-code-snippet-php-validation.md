# Code Snippet PHP Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow valid snippets containing XML declarations in strings while retaining native syntax validation and rejection of actual PHP tags.

**Architecture:** The validator will parse a synthetic PHP document and inspect its token stream. Literal and comment tokens remain data, while actual PHP-mode transitions are rejected under the existing tagless-snippet contract.

**Tech Stack:** PHP tokenizer, WordPress `WP_Error`, dependency-free PHP regression tests, Node.js structural tests.

---

### Task 1: Add validator regression coverage

**Files:**
- Create: `tests/code-snippets-validator.php`
- Test: `tests/code-snippets-validator.php`

- [x] **Step 1: Create WordPress stubs and load the real validator**

Define minimal `__()`, `WP_Error`, and `is_wp_error()` test support, then require the production validator.

- [x] **Step 2: Add acceptance and rejection assertions**

Test an XML declaration in a quoted string, tag examples in comments, real opening/closing tags, invalid PHP syntax, and `__halt_compiler`.

- [x] **Step 3: Run the regression test and verify the XML case fails**

Run: `php wp-content/plugins/siteintelix/tests/code-snippets-validator.php`

Expected: failure because the existing raw regex rejects the XML declaration.

### Task 2: Implement token-aware validation

**Files:**
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-validator.php`
- Test: `tests/code-snippets-validator.php`

- [x] **Step 1: Replace raw tag matching with token-aware inspection**

Parse the synthetic PHP document once, ignore its first opening tag, allow literals/comments, and reject actual tag tokens or executable tag sequences.

- [x] **Step 2: Run the focused validator test**

Run: `php wp-content/plugins/siteintelix/tests/code-snippets-validator.php`

Expected: all validator assertions pass.

- [x] **Step 3: Run adjacent Code Snippets regression coverage**

Run: `php wp-content/plugins/siteintelix/tests/code-snippets-normalization.php`

Expected: normalization tests pass.

### Task 3: Verify the plugin

**Files:**
- Verify: `includes/modules/code-snippets/class-siteintelix-snippets-validator.php`
- Verify: `tests/code-snippets-validator.php`

- [x] **Step 1: Lint the changed PHP files**

Run PHP lint for the validator and its new test.

- [x] **Step 2: Run the complete structural suite**

Run: `node --test wp-content/plugins/siteintelix/tests/structural.test.mjs`

Expected: zero failures.

- [x] **Step 3: Re-run focused regressions and inspect the final diff**

Confirm the original XML-string symptom passes and only the intended validator, tests, and documentation changed.
