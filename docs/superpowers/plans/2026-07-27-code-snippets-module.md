# Code Snippets Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a disabled-by-default SiteIntelix PHP snippets module with isolated execution, safe mode, fatal recovery, run-once, and secure import/export.

**Architecture:** An early conditional runtime loader executes validated active snippets before normal WordPress hooks, while focused repository, validator, runner, recovery, admin, and transfer classes remain isolated. Snippets use a dedicated per-site table and never generate executable PHP files.

**Tech Stack:** WordPress 5.8+, PHP 7.4+, `$wpdb`, `dbDelta()`, `token_get_all(TOKEN_PARSE)`, isolated closure execution, CodeMirror, SiteIntelix UI, dependency-free PHP/Node tests.

---

### Task 1: Registry, early loader, and schema

**Files:**
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `siteintelix.php`
- Modify: `admin/views/modules-page.php`
- Create: `includes/modules/code-snippets/class-siteintelix-code-snippets-module.php`
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-repository.php`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing structural assertions for module ID `code_snippets`, early enabled-option loader, admin loader, runtime registration, dashboard link, and focused class files.
- [ ] Run structural tests; expect failure.
- [ ] Register the disabled-by-default module and add an early bootstrap that reads `SITEINTELIX_MODULES_OPTION`, requires runtime classes only when enabled, and registers execution on `plugins_loaded`.
- [ ] Implement the dedicated snippets table exactly as approved, including `deactivated_at`, `last_run_at`, recovery fields, and indexes.
- [ ] Install schema on module activation and stale enabled-admin schema checks.
- [ ] Run structural tests; expect Task 1 assertions to pass.

### Task 2: PHP validator

**Files:**
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-validator.php`
- Create: `tests/code-snippets-validator.php`

- [ ] Write failing tests for valid hooks/functions/classes, opening/closing tags, short echo tags, `__halt_compiler`, malformed syntax, and bounded error messages.
- [ ] Run `php tests/code-snippets-validator.php`; expect failure.
- [ ] Implement:

```php
public static function validate( $code ) {
	if ( preg_match( '/<\\?(?:php|=)?|\\?>/i', $code ) ) {
		return new WP_Error( 'siteintelix_snippet_php_tags', __( 'Enter PHP without opening or closing tags.', 'siteintelix' ) );
	}
	try {
		$tokens = token_get_all( "<?php\n" . $code, TOKEN_PARSE );
	} catch ( ParseError $error ) {
		return new WP_Error( 'siteintelix_snippet_parse', self::bounded_parse_message( $error ) );
	}
	foreach ( $tokens as $token ) {
		if ( is_array( $token ) && T_HALT_COMPILER === $token[0] ) {
			return new WP_Error( 'siteintelix_snippet_halt', __( '__halt_compiler is not allowed.', 'siteintelix' ) );
		}
	}
	return true;
}
```

- [ ] Run validator tests on the current PHP runtime; expect all assertions to pass.

### Task 3: Repository and seven-day filtering

**Files:**
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-repository.php`
- Create: `tests/code-snippets-repository.php`

- [ ] Write failing tests for table prefixing, metadata allowlists, prepared search, active ordering, status transitions, error counters, and seven-day deactivation cutoff.
- [ ] Implement prepared CRUD, duplication, bulk status, active-by-scope query, `running_once` transitions, bounded structured error recording, and import insertion.
- [ ] Preserve raw PHP using `wp_unslash()` only; sanitize metadata and tags separately.
- [ ] Run repository tests; expect all assertions to pass.

### Task 4: Safe-mode/context policy

**Files:**
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-context.php`
- Create: `tests/code-snippets-context.php`

- [ ] Write failing matrix tests for frontend, admin, ordinary AJAX/REST/cron, management AJAX/REST, CLI, constant safe mode, administrator URL safe mode, existing SiteIntelix safe mode, activation/deactivation/uninstall, and module-disabled state.
- [ ] Implement:

```php
public static function should_skip_all();
public static function request_scope();
public static function snippet_matches_scope( $snippet_scope, $request_scope );
public static function is_safe_mode();
public static function is_management_request();
```

- [ ] Ensure only `everywhere` runs in ordinary AJAX/REST/cron; all snippets skip in CLI and management/recovery contexts.
- [ ] Run context tests; expect all assertions to pass.

### Task 5: Isolated runner and recovery

**Files:**
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-recovery.php`
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-runner.php`
- Create: `tests/code-snippets-runner.php`
- Create: `tests/code-snippets-recovery.php`

- [ ] Write failing tests for priority order, isolated local scope, stop-after-error, current-snippet tracking, caught `Throwable`, fatal-type detection, path redaction, deactivation, and error count.
- [ ] Implement one shutdown handler and request-local current snippet ID.
- [ ] Execute through:

```php
private static function execute_code( $code ) {
	$executor = static function ( $snippet_code ) {
		eval( $snippet_code ); // phpcs:ignore -- administrator-authored PHP is the module's explicit purpose.
	};
	$executor( $code );
}
```

- [ ] Catch `Throwable`, record/deactivate through recovery, stop remaining snippets, and leave WordPress Recovery Mode handlers intact.
- [ ] Normalize stored file paths relative to `ABSPATH` or basename and bound error messages.
- [ ] Run runner/recovery tests; expect all assertions to pass.

### Task 6: Admin list/editor and CRUD

**Files:**
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Create: `includes/modules/code-snippets/views/list.php`
- Create: `includes/modules/code-snippets/views/editor.php`
- Create: `includes/modules/code-snippets/assets/code-snippets.css`
- Create: `includes/modules/code-snippets/assets/code-snippets.js`
- Create: `tests/code-snippets-admin.php`

- [ ] Write failing policy/UI tests for menus, fields, warnings, filters, row/bulk actions, capability/nonces, validation refusal, and forced inactive imports.
- [ ] Register **Code Snippets** and **Add New Snippet** submenus.
- [ ] Implement compact list/search/filter/pagination and the approved editor/sidebar UI.
- [ ] Use WordPress PHP CodeMirror only on snippet editor screens.
- [ ] Implement save, activate/deactivate, duplicate, delete, and bulk actions; never run ordinary snippets on these requests.
- [ ] Run admin and UI tests; expect all assertions to pass.

### Task 7: Run once

**Files:**
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-runner.php`
- Create: `includes/modules/code-snippets/views/run-once-confirm.php`
- Create: `tests/code-snippets-run-once.php`

- [ ] Write failing tests for explicit confirmation, nonce/capability, `running_once` before execution, inactive-after-success, inactive-after-error, and no automatic retry.
- [ ] Implement the dedicated confirmation page and action.
- [ ] Transition to `running_once` before calling the isolated executor, then persist inactive/last-run or inactive/error.
- [ ] Run run-once tests; expect all assertions to pass.

### Task 8: Import/export

**Files:**
- Create: `includes/modules/code-snippets/class-siteintelix-snippets-transfer.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Create: `includes/modules/code-snippets/views/import.php`
- Create: `tests/code-snippets-transfer.php`

- [ ] Write failing tests for one/selected JSON, PHP reference export, MIME/extension/size/record bounds, unknown-field removal, malformed JSON, syntax rejection, and forced inactive import.
- [ ] Implement nonce/capability-protected downloads with no raw public REST route.
- [ ] Import only approved fields, validate every code body, cap upload bytes and record count, and insert inactive.
- [ ] Run transfer tests; expect all assertions to pass.

### Task 9: Shared uninstall setting, notices, and documentation

**Files:**
- Modify: `admin/views/settings-page.php`
- Modify: `siteintelix.php`
- Modify: `uninstall.php`
- Create: `docs/code-snippets.md`
- Modify: `tests/structural.test.mjs`

- [ ] Extend the shared **Delete Custom Code Data on Uninstall** setting to snippets.
- [ ] Preserve data by default; opt-in cleanup drops per-site tables and removes schema/recovery state across multisite.
- [ ] Show safe-mode and redacted recovery notices only to `manage_options`.
- [ ] Document schema, early timing, scopes, safe modes, recovery limitations, run-once, transfer format, hooks/constants, and staging test procedure.
- [ ] Run every new PHP test, all existing Node tests, syntax checks, and WordPress CLI schema/runtime smoke tests.

### Version-control note

The plugin directory is not a Git working tree. Commit steps are omitted because no safe commit target exists.
