# Snippet Backslash and Editor Submenu Fix

## Problem

The Code Snippets admin controller normalizes `$_POST`, then the repository normalizes the already-normalized result again. Because normalization calls `wp_unslash()`, PHP namespace separators such as `\TUTOR\Icon` are removed on the second pass. The editor routes are also registered as visible SiteIntelix child menus, producing redundant **Add New Code** and **Add New Snippet** sidebar links.

## Approved design

- Treat repository input as canonical, unslashed data and never call `wp_unslash()` inside persistence methods.
- Add a dedicated request normalizer that unslashes raw WordPress form input exactly once.
- Preserve code read from the database during metadata-only updates and duplication.
- Keep JSON import data unslashed and canonical.
- Register both editor routes with a `null` parent so their screens remain reachable from module buttons but are not displayed in the sidebar.
- Repair the existing Tutor snippet by restoring `\TUTOR\Icon::RIGHT_ARROW_UP`, then reactivate it after verification.
- Add a regression test that proves namespace backslashes survive request normalization and repository preparation.

## Verification

Run the focused regression test, all Node tests, PHP syntax checks, WordPress CRUD/runtime smoke checks, a frontend request using the repaired Tutor snippet, and ZIP integrity validation.
