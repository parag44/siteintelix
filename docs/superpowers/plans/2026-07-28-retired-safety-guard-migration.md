# Retired Safety Guard Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove verified retired SiteIntelix Plugin Safety Guard MU files automatically on existing installations without touching foreign MU files.

**Architecture:** Advance the existing internal migration version and delegate cleanup to the fail-closed `SITEINTELIX_MU_Files` manager. Advance the migration version only when no owned-file deletion failed, so filesystem failures retry on later admin requests.

**Tech Stack:** PHP 7.4+, WordPress options API, existing SiteIntelix MU ownership manager, dependency-free PHP tests.

---

### Task 1: Add migration regression coverage

**Files:**
- Modify: `tests/mu-files-cleanup.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add migration test stubs and state**

Add in-memory `get_option()`, `update_option()`, `delete_option()`, and `SITEINTELIX_MODULES_OPTION` support to the standalone MU test.

- [ ] **Step 2: Assert automatic owned-file cleanup**

Create a verified `siteintelix-plugin-safety-guard.php`, set the stored migration version to `2.7.2`, run `SITEINTELIX_Migrations::run()`, and assert:

```php
siteintelix_test_assert( ! file_exists( $guard_path ), 'Migration must remove the verified retired safety guard.' );
siteintelix_test_assert( SITEINTELIX_Migrations::CURRENT_VERSION === get_option( SITEINTELIX_Migrations::VERSION_OPTION ), 'Successful cleanup must advance the migration version.' );
```

- [ ] **Step 3: Assert a foreign same-name file is preserved**

Write a same-name file without the complete SiteIntelix signature, rerun from the old migration version, and assert the file remains while the version advances.

- [ ] **Step 4: Assert deletion failures remain retryable**

Make the standalone `wp_delete_file()` stub leave the verified guard in place, rerun from `2.7.2`, and assert:

```php
siteintelix_test_assert( '2.7.2' === get_option( SITEINTELIX_Migrations::VERSION_OPTION ), 'Failed cleanup must not advance the migration version.' );
```

- [ ] **Step 5: Strengthen structural coverage**

Require `SITEINTELIX_Migrations` to call:

```php
SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_SAFETY_GUARD )
```

and require the internal migration version to be newer than `2.7.2`.

- [ ] **Step 6: Run tests and verify RED**

Run:

```bash
php wp-content/plugins/siteintelix/tests/mu-files-cleanup.php
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: migration-specific assertions fail because the current migration does not clean the retired guard.

### Task 2: Implement retryable versioned cleanup

**Files:**
- Modify: `includes/class-siteintelix-migrations.php`
- Test: `tests/mu-files-cleanup.php`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Advance the internal migration version**

Change `CURRENT_VERSION` from `2.7.2` to `2.7.2.1` without changing the public plugin release version.

- [ ] **Step 2: Delegate retired-file cleanup**

Before advancing the version, call the ownership manager for `TYPE_SAFETY_GUARD`. Return without updating the migration option when its `failed` list is non-empty. Missing and preserved unverified files count as completed migration work.

- [ ] **Step 3: Run focused tests and verify GREEN**

Run:

```bash
php wp-content/plugins/siteintelix/tests/mu-files-cleanup.php
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: both commands exit successfully.
