# SiteIntelix Admin UI Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename SiteIntelix, apply the approved lightweight WordPress-native visual system across every admin screen, retire Custom Error UI safely, and reduce Server Diagnostics to server/PHP/system/PHP-configuration reporting.

**Architecture:** Keep the existing PHP-rendered WordPress admin architecture and current module workflows. Add a small versioned migration class for one-time retired-module cleanup, use one shared dependency-free admin stylesheet plus the existing Debug Log-only stylesheet, and protect the changes with Node’s built-in structural test runner and full PHP syntax checks.

**Tech Stack:** WordPress PHP 7.4+, vanilla CSS custom properties, vanilla JavaScript, Dashicons, Node.js built-in `node:test`, shell-based PHP linting.

---

## File Structure

### Create

- `tests/structural.test.mjs` — fast source-level regression tests for naming, removed module/runtime code, diagnostics scope, asset scoping, and required visual-system contracts.
- `includes/class-siteintelix-migrations.php` — one-time, versioned cleanup of retired Custom Error UI options, enabled-module references, generated config, and SiteIntelix-owned drop-ins.

### Modify

- `siteintelix.php` — public plugin name, migration loading/execution, retired runtime branch removal, retired activation/deactivation branch removal, and precise asset enqueue conditions.
- `includes/class-siteintelix-modules.php` — remove Custom Error UI registry entry and rewrite the Server Diagnostics description.
- `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php` — remove selected-plugin compatibility behavior, calculations, UI, and export fields; retain system reporting.
- `admin/views/admin-page.php` — polish Overview markup, status semantics, icons, and shared classes without changing controls.
- `admin/views/modules-page.php` — remove retired settings link and normalize shared classes.
- `admin/views/settings-page.php` — remove any retired-module assumptions and normalize tabs/forms/sidebar markup.
- `admin/views/debug-log-page-classic.php` — shared header, controls, table, and responsive polish.
- `admin/views/debug-log-page-modern.php` — shared header, summaries, filters, log cards, and responsive polish.
- `admin/views/debug-log-page-terminal.php` — shared header and Terminal Light polish.
- `admin/views/safe-mode-page.php` — shared status, forms, cards, and actions.
- `includes/modules/email-log/class-siteintelix-email-log-module.php` — shared page/card/table/modal classes.
- `includes/modules/smtp/class-siteintelix-smtp-module.php` — shared form/status classes.
- `includes/modules/cron-events/class-siteintelix-cron-events-module.php` — shared summary/toolbar/table classes.
- `includes/modules/database-manager/class-siteintelix-database-manager-module.php` — shared tabs/sidebar/table/editor classes.
- `includes/modules/download-manager/class-siteintelix-download-manager-module.php` — shared cards/table/action classes.
- `includes/modules/transients-manager/class-siteintelix-transients-manager-module.php` — shared summary/toolbar/table/sidebar classes.
- `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php` — shared settings/preview/action classes.
- `includes/class-siteintelix-admin-ui.php` — shared header/icon/badge/button markup refinements.
- `assets/admin/css/siteintelix-admin.css` — canonical tokens and shared components, followed by compact module-specific layout rules.
- `assets/admin/css/siteintelix-debug-log.css` — only Modern and Terminal Log-specific visuals.
- `assets/admin/js/siteintelix-admin.js` — preserve behavior, consolidate repeated listeners through existing delegated handlers, and avoid work when a page control is absent.
- `readme.txt` — new public name, revised diagnostics scope, removed module, and refreshed changelog text.
- `uninstall.php` — keep backward-compatible safe cleanup for retired options and SiteIntelix-owned files.

### Delete

- `includes/modules/error-ui/class-siteintelix-error-ui-module.php`
- `includes/modules/error-ui/templates/db-error.php`
- `includes/modules/error-ui/templates/fatal-error-handler.php`

The empty `includes/modules/error-ui/` directory can be removed after its files are deleted.

---

### Task 1: Add Structural Regression Harness and Lock the New Public Contract

**Files:**
- Create: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Create the structural test harness with failing naming and removal assertions**

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { constants } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => readFile(path.join(root, relativePath), 'utf8');

test('plugin exposes the approved public name without changing the slug', async () => {
  const main = await read('siteintelix.php');
  assert.match(main, /Plugin Name:\s+SiteIntelix – Debug Logs, Email Logs & Diagnostics/);
  assert.match(main, /Text Domain:\s+siteintelix/);
  assert.match(main, /'siteintelix'/);
});

test('Custom Error UI is absent from runtime and registry code', async () => {
  const [main, modules] = await Promise.all([
    read('siteintelix.php'),
    read('includes/class-siteintelix-modules.php'),
  ]);

  assert.doesNotMatch(main, /SITEINTELIX_Error_UI_Module|modules\/error-ui|error_ui/);
  assert.doesNotMatch(modules, /Custom Error UI|Error UI|error_ui/);
});

test('retired Custom Error UI implementation files are deleted', async () => {
  const retired = [
    'includes/modules/error-ui/class-siteintelix-error-ui-module.php',
    'includes/modules/error-ui/templates/db-error.php',
    'includes/modules/error-ui/templates/fatal-error-handler.php',
  ];

  for (const relativePath of retired) {
    await assert.rejects(access(path.join(root, relativePath), constants.F_OK));
  }
});
```

- [ ] **Step 2: Run the tests and verify the current implementation fails for the intended reasons**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL because the old plugin name and Custom Error UI code/files still exist.

- [ ] **Step 3: Add failing diagnostics-scope assertions**

Append:

```js
test('Server Diagnostics has no plugin compatibility workflow', async () => {
  const diagnostics = await read(
    'includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php'
  );

  for (const forbidden of [
    'selected_plugin',
    'render_plugin_form',
    'render_compatibility_card',
    'get_compatibility_profiles',
    'Compatibility Profiles',
    'Check Plugin',
  ]) {
    assert.doesNotMatch(diagnostics, new RegExp(forbidden));
  }

  for (const retained of [
    'PHP & Server',
    'Extensions',
    'Filesystem',
    'Network',
    'WordPress',
    'Database',
    'format_text_report',
  ]) {
    assert.match(diagnostics, new RegExp(retained));
  }
});
```

- [ ] **Step 4: Re-run and confirm diagnostics assertions also fail**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL listing current compatibility workflow identifiers.

- [ ] **Step 5: Record the red baseline**

Save the failing output in the task notes. No Git commit is possible because this workspace is not a Git repository.

---

### Task 2: Rename the Plugin and Retire Custom Error UI Runtime Code

**Files:**
- Modify: `siteintelix.php`
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `admin/views/modules-page.php`
- Delete: `includes/modules/error-ui/class-siteintelix-error-ui-module.php`
- Delete: `includes/modules/error-ui/templates/db-error.php`
- Delete: `includes/modules/error-ui/templates/fatal-error-handler.php`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Change the plugin header and description**

In `siteintelix.php`, set:

```php
/**
 * Plugin Name:       SiteIntelix – Debug Logs, Email Logs & Diagnostics
 * Plugin URI:        https://wordpress.org/plugins/siteintelix
 * Description:       Lightweight WordPress diagnostics for debug logs, email logs, server health, PHP configuration, cron, database, and troubleshooting.
 */
```

Do not change the directory, slug, text domain, constants, class prefixes, or admin page slugs.

- [ ] **Step 2: Remove Custom Error UI include and boot branches**

Delete these branches from `siteintelix_load_includes()` and `siteintelix_boot_enabled_modules()`:

```php
if ( SITEINTELIX_Modules::is_enabled( 'error_ui' ) ) {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/error-ui/class-siteintelix-error-ui-module.php';
}

if ( SITEINTELIX_Modules::is_enabled( 'error_ui' ) && class_exists( 'SITEINTELIX_Error_UI_Module' ) ) {
	SITEINTELIX_Error_UI_Module::init();
}
```

- [ ] **Step 3: Remove Custom Error UI activation/deactivation registry branches**

Remove the `error_ui` entry from the module lifecycle map near the module activation handlers, and delete special cases equivalent to:

```php
if ( 'error_ui' === $module_id ) {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/error-ui/class-siteintelix-error-ui-module.php';
	return SITEINTELIX_Error_UI_Module::activate();
}
```

and:

```php
if ( 'error_ui' === $module_id ) {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/error-ui/class-siteintelix-error-ui-module.php';
	SITEINTELIX_Error_UI_Module::deactivate();
}
```

- [ ] **Step 4: Remove the module registry entry**

Delete the complete `error_ui` array entry from `SITEINTELIX_Modules::get_all()`.

Change the Server Diagnostics entry to:

```php
'description' => ( did_action( 'init' )
	? __( 'Inspect server health, PHP configuration, extensions, filesystem, network, WordPress, and database details.', 'siteintelix' )
	: 'Inspect server health, PHP configuration, extensions, filesystem, network, WordPress, and database details.'
),
```

- [ ] **Step 5: Remove retired settings-link mapping**

In `admin/views/modules-page.php`, remove:

```php
'error_ui' => 'siteintelix-error-ui-settings',
```

- [ ] **Step 6: Delete the retired module implementation**

Use `apply_patch` to delete:

```text
includes/modules/error-ui/class-siteintelix-error-ui-module.php
includes/modules/error-ui/templates/db-error.php
includes/modules/error-ui/templates/fatal-error-handler.php
```

- [ ] **Step 7: Run the focused structural tests**

Run:

```bash
node --test --test-name-pattern="plugin exposes|Custom Error UI|retired Custom" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS for naming/runtime/file-removal tests; diagnostics test remains red.

- [ ] **Step 8: Run PHP lint for modified PHP**

Run:

```bash
php -l wp-content/plugins/siteintelix/siteintelix.php
php -l wp-content/plugins/siteintelix/includes/class-siteintelix-modules.php
php -l wp-content/plugins/siteintelix/admin/views/modules-page.php
```

Expected: each command reports `No syntax errors detected`.

- [ ] **Step 9: Record checkpoint**

No commit command is included because the workspace is not a Git repository. Record the passing focused test and lint output.

---

### Task 3: Add a Safe One-Time Migration for Retired Module Data and Files

**Files:**
- Create: `includes/class-siteintelix-migrations.php`
- Modify: `siteintelix.php`
- Modify: `uninstall.php`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing migration structure assertions**

Append to `tests/structural.test.mjs`:

```js
test('migration cleans retired Error UI state once and protects foreign drop-ins', async () => {
  const migration = await read('includes/class-siteintelix-migrations.php');

  assert.match(migration, /siteintelix_migration_version/);
  assert.match(migration, /siteintelix_error_ui_settings/);
  assert.match(migration, /siteintelix_error_ui_dropins_version/);
  assert.match(migration, /siteintelix-error-ui-config\.php/);
  assert.match(migration, /SiteIntelix Error UI/);
  assert.match(migration, /array_diff/);
  assert.match(migration, /error_ui/);
  assert.match(migration, /wp_delete_file/);
  assert.doesNotMatch(migration, /unlink\s*\(/);
});

test('main plugin loads and runs the migration after includes are available', async () => {
  const main = await read('siteintelix.php');
  assert.match(main, /class-siteintelix-migrations\.php/);
  assert.match(main, /SITEINTELIX_Migrations::run/);
});
```

- [ ] **Step 2: Run the migration tests and verify they fail because the class does not exist**

Run:

```bash
node --test --test-name-pattern="migration|main plugin loads" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL with `ENOENT` for `includes/class-siteintelix-migrations.php`.

- [ ] **Step 3: Create the migration class**

Create `includes/class-siteintelix-migrations.php`:

```php
<?php
/**
 * Versioned SiteIntelix migrations.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SITEINTELIX_Migrations {

	const VERSION_OPTION = 'siteintelix_migration_version';
	const CURRENT_VERSION = '2.7.0';

	public static function run() {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::CURRENT_VERSION, '>=' ) ) {
			return;
		}

		self::remove_retired_error_ui();
		update_option( self::VERSION_OPTION, self::CURRENT_VERSION, false );
	}

	private static function remove_retired_error_ui() {
		delete_option( 'siteintelix_error_ui_settings' );
		delete_option( 'siteintelix_error_ui_dropins_version' );

		$enabled = get_option( SITEINTELIX_MODULES_OPTION, array() );
		if ( is_array( $enabled ) && in_array( 'error_ui', $enabled, true ) ) {
			update_option(
				SITEINTELIX_MODULES_OPTION,
				array_values( array_diff( $enabled, array( 'error_ui' ) ) )
			);
		}

		$files = array(
			trailingslashit( WP_CONTENT_DIR ) . 'db-error.php',
			trailingslashit( WP_CONTENT_DIR ) . 'fatal-error-handler.php',
			trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-error-ui-config.php',
		);

		foreach ( $files as $file ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}

			$contents = file_get_contents( $file );
			if ( false !== strpos( (string) $contents, 'SiteIntelix Error UI' )
				|| false !== strpos( (string) $contents, 'SiteIntelix Error Handler generated config' )
			) {
				wp_delete_file( $file );
			}
		}
	}
}
```

- [ ] **Step 4: Load and execute the migration once**

In `siteintelix_load_includes()`, add:

```php
require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-migrations.php';
```

After includes are loaded, register:

```php
function siteintelix_run_migrations() {
	if ( class_exists( 'SITEINTELIX_Migrations' ) ) {
		SITEINTELIX_Migrations::run();
	}
}
add_action( 'admin_init', 'siteintelix_run_migrations', 1 );
```

Use `admin_init` so the migration does not add frontend request overhead.

- [ ] **Step 5: Keep uninstall cleanup backward-compatible and ownership-safe**

In `uninstall.php`, include `siteintelix_migration_version` in the option deletion list. Retain the old Error UI option cleanup and ownership marker checks so installations that uninstall without first running the migration are still cleaned safely.

The file deletion condition must accept only:

```php
false !== strpos( (string) $contents, 'SiteIntelix Error UI' )
|| false !== strpos( (string) $contents, 'SiteIntelix Error Handler generated config' )
```

- [ ] **Step 6: Run focused tests and lint**

Run:

```bash
node --test --test-name-pattern="migration|main plugin loads" wp-content/plugins/siteintelix/tests/structural.test.mjs
php -l wp-content/plugins/siteintelix/includes/class-siteintelix-migrations.php
php -l wp-content/plugins/siteintelix/siteintelix.php
php -l wp-content/plugins/siteintelix/uninstall.php
```

Expected: all tests PASS and all PHP files report no syntax errors.

- [ ] **Step 7: Record checkpoint**

Record test and lint output; no Git commit is possible in this workspace.

---

### Task 4: Remove Plugin Compatibility Work from Server Diagnostics

**Files:**
- Modify: `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
- Modify: `readme.txt`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Simplify page request and export flow**

In `render_page()`, replace:

```php
$selected_plugin = self::get_requested_plugin();
$report          = self::get_report( $selected_plugin );
```

with:

```php
$report = self::get_report();
```

Remove `selected_plugin` from JSON and text export URLs.

In `handle_export()`, replace:

```php
$selected_plugin = self::get_requested_plugin();
$report          = self::get_report( $selected_plugin, true );
```

with:

```php
$report = self::get_report( true );
```

- [ ] **Step 2: Update the page description and layout**

Use:

```php
'description' => __( 'Inspect server health, PHP configuration, extensions, filesystem, network, WordPress, and database details.', 'siteintelix' ),
```

Remove:

```php
<?php self::render_plugin_form( $selected_plugin ); ?>
<?php self::render_compatibility_card( $report['compatibility'] ); ?>
```

Keep the report and privacy cards in the sidebar.

- [ ] **Step 3: Remove compatibility rendering and request methods**

Delete complete methods responsible for:

```text
render_plugin_form()
render_compatibility_card()
get_requested_plugin()
get_plugins()
get_selected_plugin_profile()
get_compatibility_profiles()
```

Also delete plugin-header parsing helpers used only by those methods.

- [ ] **Step 4: Simplify report construction**

Change the signature to:

```php
private static function get_report( $redacted = false )
```

Build the summary only from retained rows:

```php
$php_server = self::get_php_server_checks( $redacted );
$extensions = self::get_extension_checks();
$filesystem = self::get_filesystem_checks( $redacted );
$network    = self::get_network_checks();
$wordpress  = self::get_wordpress_checks();
$database   = self::get_database_checks( $redacted );
$all_rows   = array_merge( $php_server, $extensions, $filesystem, $network, $wordpress, $database );

return array(
	'generated_at' => current_time( 'mysql' ),
	'site'         => self::redact_url( home_url( '/' ) ),
	'summary'      => self::summarize( $all_rows ),
	'php_server'   => $php_server,
	'extensions'   => $extensions,
	'filesystem'   => $filesystem,
	'network'      => $network,
	'wordpress'    => $wordpress,
	'database'     => $database,
);
```

- [ ] **Step 5: Remove compatibility output from the text report**

Delete loops over:

```php
$report['compatibility']
```

The report must still include all retained section arrays and summary values.

- [ ] **Step 6: Update documentation language**

In `readme.txt`, replace compatibility-oriented Server Diagnostics descriptions with:

```text
🩺 Server Diagnostics — inspect server health, PHP configuration, extensions, filesystem, network, WordPress, and database details.
```

Remove changelog claims about plugin compatibility checks.

- [ ] **Step 7: Run focused tests and lint**

Run:

```bash
node --test --test-name-pattern="Server Diagnostics" wp-content/plugins/siteintelix/tests/structural.test.mjs
php -l wp-content/plugins/siteintelix/includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php
```

Expected: diagnostics structural test PASS and PHP lint PASS.

- [ ] **Step 8: Record checkpoint**

Record the focused passing output; no Git commit is possible in this workspace.

---

### Task 5: Establish the Canonical Lightweight Design Tokens and Components

**Files:**
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `includes/class-siteintelix-admin-ui.php`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing token and component assertions**

Append:

```js
test('shared admin CSS exposes the approved compact token system', async () => {
  const css = await read('assets/admin/css/siteintelix-admin.css');

  for (const token of [
    '--si-canvas:',
    '--si-surface:',
    '--si-border:',
    '--si-text:',
    '--si-muted:',
    '--si-primary:',
    '--si-success:',
    '--si-warning:',
    '--si-danger:',
    '--si-control-height:',
    '--si-transition:',
  ]) {
    assert.match(css, new RegExp(token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }

  for (const component of [
    '.si-page-header',
    '.si-card',
    '.si-button',
    '.si-badge',
    '.si-toolbar',
    '.si-table',
    '.si-form-row',
    '.si-empty-state',
    '.sitx-toggle',
  ]) {
    assert.match(css, new RegExp(component.replace('.', '\\.')));
  }

  assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
});
```

- [ ] **Step 2: Run and verify failure on missing canonical tokens**

Run:

```bash
node --test --test-name-pattern="shared admin CSS" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL for tokens such as `--si-canvas`, `--si-control-height`, and `--si-transition`.

- [ ] **Step 3: Replace the token block with the approved minimal system**

At the beginning of `siteintelix-admin.css`, define:

```css
.si-admin-wrap,
.siteintelix-wrap {
	--si-canvas: #f6f7f7;
	--si-surface: #ffffff;
	--si-surface-subtle: #f9fafb;
	--si-border: #dcdcde;
	--si-border-strong: #c3c4c7;
	--si-text: #1d2327;
	--si-muted: #646970;
	--si-primary: #2271b1;
	--si-primary-hover: #135e96;
	--si-primary-soft: #eaf3ff;
	--si-success: #008a20;
	--si-success-soft: #edfaef;
	--si-warning: #996800;
	--si-warning-soft: #fcf9e8;
	--si-danger: #b32d2e;
	--si-danger-soft: #fcf0f1;
	--si-info: #2271b1;
	--si-info-soft: #eaf3ff;
	--si-radius-sm: 4px;
	--si-radius-md: 6px;
	--si-radius-lg: 8px;
	--si-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
	--si-space-1: 4px;
	--si-space-2: 8px;
	--si-space-3: 12px;
	--si-space-4: 16px;
	--si-space-5: 24px;
	--si-space-6: 32px;
	--si-control-height: 36px;
	--si-transition: 140ms ease;
	--si-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
	--si-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
}
```

Map required legacy `--siteintelix-*` aliases to these canonical tokens only once.

- [ ] **Step 4: Normalize shared components**

Implement compact common rules using the canonical tokens:

```css
.si-admin-wrap {
	background: var(--si-canvas);
	color: var(--si-text);
	font-family: var(--si-font);
	margin: 0 0 0 -20px;
	min-height: calc(100vh - 32px);
}

.siteintelix-container {
	box-sizing: border-box;
	margin: 0 auto;
	max-width: 1440px;
	padding: var(--si-space-5);
}

.si-card {
	background: var(--si-surface);
	border: 1px solid var(--si-border);
	border-radius: var(--si-radius-lg);
	box-shadow: var(--si-shadow);
	box-sizing: border-box;
}

.si-button {
	align-items: center;
	border: 1px solid transparent;
	border-radius: var(--si-radius-sm);
	box-sizing: border-box;
	cursor: pointer;
	display: inline-flex;
	font-size: 13px;
	font-weight: 600;
	gap: var(--si-space-2);
	justify-content: center;
	min-height: var(--si-control-height);
	padding: 0 var(--si-space-3);
	text-decoration: none;
	transition: background-color var(--si-transition), border-color var(--si-transition), color var(--si-transition);
}

.si-button:focus-visible,
.sitx-toggle input:focus-visible + .sitx-toggle__slider,
.si-admin-wrap :is(a, button, input, select, textarea):focus-visible {
	box-shadow: 0 0 0 1px #fff, 0 0 0 3px var(--si-primary);
	outline: 0;
}

@media (prefers-reduced-motion: reduce) {
	.si-admin-wrap *,
	.si-admin-wrap *::before,
	.si-admin-wrap *::after {
		scroll-behavior: auto !important;
		transition-duration: 0.01ms !important;
	}
}
```

Preserve all required component selectors listed in the test.

- [ ] **Step 5: Refine shared PHP helper markup**

In `SITEINTELIX_Admin_UI::page_header()`, keep existing arguments but ensure:

- The icon wrapper has `aria-hidden="true"`.
- The header uses only shared `si-page-header*` classes plus compatibility classes already consumed by templates.
- Actions remain escaped with `allowed_html()`.

In `button()`, preserve links/buttons and add no JavaScript dependency.

- [ ] **Step 6: Run focused tests and PHP lint**

Run:

```bash
node --test --test-name-pattern="shared admin CSS" wp-content/plugins/siteintelix/tests/structural.test.mjs
php -l wp-content/plugins/siteintelix/includes/class-siteintelix-admin-ui.php
```

Expected: PASS.

- [ ] **Step 7: Record checkpoint**

Record the passing component-contract test and lint output.

---

### Task 6: Refresh Overview, Modules, and Settings with Shared Components

**Files:**
- Modify: `admin/views/admin-page.php`
- Modify: `admin/views/modules-page.php`
- Modify: `admin/views/settings-page.php`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing markup contract assertions**

Append:

```js
test('core admin pages retain controls and use shared visual contracts', async () => {
  const [overview, modules, settings] = await Promise.all([
    read('admin/views/admin-page.php'),
    read('admin/views/modules-page.php'),
    read('admin/views/settings-page.php'),
  ]);

  assert.match(overview, /siteintelix-health-strip/);
  assert.match(overview, /siteintelix-overview-minimal-grid/);
  assert.match(overview, /siteintelix-copy-btn/);
  assert.match(overview, /siteintelix-export-btn/);
  assert.match(overview, /data-siteintelix-module-toggle/);

  assert.match(modules, /data-siteintelix-module-card/);
  assert.match(modules, /data-siteintelix-module-toggle/);
  assert.match(modules, /sitx-module-search/);
  assert.doesNotMatch(modules, /error_ui|siteintelix-error-ui-settings/);

  assert.match(settings, /data-siteintelix-settings-tabs/);
  assert.match(settings, /data-siteintelix-settings-tab/);
  assert.match(settings, /data-siteintelix-settings-panel/);
  assert.doesNotMatch(settings, /error_ui|siteintelix-error-ui-settings/);
});
```

- [ ] **Step 2: Run the focused test**

Run:

```bash
node --test --test-name-pattern="core admin pages" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS for retained behavior and removal; this becomes a guard during markup polishing.

- [ ] **Step 3: Polish Overview markup without changing data flow**

In `admin/views/admin-page.php`:

- Keep the current page header, JSON data script, six featured checks, System Overview rows, report actions, module loop, and AJAX toggle attributes.
- Add semantic status classes to overview row values:

```php
$siteintelix_status_class = 'siteintelix-status-value siteintelix-status-value--' . sanitize_html_class( $siteintelix_checks['https']['status'] );
siteintelix_row(
	__( 'HTTPS', 'siteintelix' ),
	'<span class="' . esc_attr( $siteintelix_status_class ) . '">' . esc_html( $siteintelix_checks['https']['value'] ) . '</span>'
);
```

- Add appropriate Dashicons to the six summary cards based on the check key.
- Keep every user-facing value escaped.

- [ ] **Step 4: Apply compact Overview layout styles**

Use:

```css
.siteintelix-health-strip {
	display: grid;
	gap: var(--si-space-3);
	grid-template-columns: repeat(6, minmax(0, 1fr));
}

.siteintelix-health-pill {
	align-items: center;
	background: var(--si-surface);
	border: 1px solid var(--si-border);
	border-radius: var(--si-radius-lg);
	box-shadow: var(--si-shadow);
	display: grid;
	gap: var(--si-space-3);
	grid-template-columns: 36px minmax(0, 1fr);
	min-width: 0;
	padding: var(--si-space-3);
}

.siteintelix-overview-minimal-grid {
	display: grid;
	gap: var(--si-space-4);
	grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
	margin-top: var(--si-space-4);
}
```

Add responsive breakpoints at 1200px, 960px, and 782px so the strip becomes 3, 2, then 1 column and the main grid becomes one column.

- [ ] **Step 5: Normalize Modules and Settings visuals**

Keep all existing loops, forms, actions, nonces, links, search inputs, tabs, and data attributes. Adjust only shared classes/wrappers needed for:

- Compact module cards.
- Flat status badges.
- Consistent settings tabs.
- Standard form rows.
- Compact sidebar cards.
- Unified notice and action spacing.

Do not change option names or form action names.

- [ ] **Step 6: Run tests and lint core views**

Run:

```bash
node --test --test-name-pattern="core admin pages|shared admin CSS" wp-content/plugins/siteintelix/tests/structural.test.mjs
php -l wp-content/plugins/siteintelix/admin/views/admin-page.php
php -l wp-content/plugins/siteintelix/admin/views/modules-page.php
php -l wp-content/plugins/siteintelix/admin/views/settings-page.php
```

Expected: all PASS.

- [ ] **Step 7: Record checkpoint**

Record tests/lint; no commit is possible in the current workspace.

---

### Task 7: Refresh Every Retained Module Page and Preserve Workflows

**Files:**
- Modify: `admin/views/safe-mode-page.php`
- Modify: `includes/modules/email-log/class-siteintelix-email-log-module.php`
- Modify: `includes/modules/smtp/class-siteintelix-smtp-module.php`
- Modify: `includes/modules/cron-events/class-siteintelix-cron-events-module.php`
- Modify: `includes/modules/database-manager/class-siteintelix-database-manager-module.php`
- Modify: `includes/modules/download-manager/class-siteintelix-download-manager-module.php`
- Modify: `includes/modules/transients-manager/class-siteintelix-transients-manager-module.php`
- Modify: `includes/modules/coming-soon/class-siteintelix-coming-soon-module.php`
- Modify: `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add retained-module behavior assertions**

Append:

```js
test('retained module pages preserve their key workflows', async () => {
  const files = {
    email: await read('includes/modules/email-log/class-siteintelix-email-log-module.php'),
    smtp: await read('includes/modules/smtp/class-siteintelix-smtp-module.php'),
    cron: await read('includes/modules/cron-events/class-siteintelix-cron-events-module.php'),
    database: await read('includes/modules/database-manager/class-siteintelix-database-manager-module.php'),
    download: await read('includes/modules/download-manager/class-siteintelix-download-manager-module.php'),
    transients: await read('includes/modules/transients-manager/class-siteintelix-transients-manager-module.php'),
    maintenance: await read('includes/modules/coming-soon/class-siteintelix-coming-soon-module.php'),
    safeMode: await read('admin/views/safe-mode-page.php'),
  };

  assert.match(files.email, /siteintelix-email-log/);
  assert.match(files.smtp, /siteintelix_save_smtp_settings/);
  assert.match(files.cron, /siteintelix_run_cron_event/);
  assert.match(files.database, /siteintelix-database-manager/);
  assert.match(files.download, /siteintelix-download-manager/);
  assert.match(files.transients, /siteintelix-transients-manager/);
  assert.match(files.maintenance, /siteintelix_save_coming_soon_settings/);
  assert.match(files.safeMode, /siteintelix_start_safe_mode/);
});
```

- [ ] **Step 2: Run and establish a passing workflow guard**

Run:

```bash
node --test --test-name-pattern="retained module pages" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS before visual markup edits.

- [ ] **Step 3: Normalize module page headers and containers**

For every retained module page:

- Render `SITEINTELIX_Admin_UI::page_header()`.
- Use `siteintelix-container`.
- Use shared `si-card`, `si-toolbar`, `si-table`, `si-form-row`, `si-button`, `si-badge`, and `si-empty-state` classes alongside existing module-specific classes.
- Keep all capability checks, nonces, request sanitization, option names, action URLs, and data attributes.

- [ ] **Step 4: Normalize dense data pages**

For Email Log, Cron Events, Database Manager, Download Manager, Transients Manager, and Server Diagnostics:

- Wrap tables in horizontal overflow containers.
- Use sticky table headers only where they do not conflict with WordPress admin offsets.
- Preserve bulk actions, filters, pagination, row actions, and modals.
- Use monospace only for code, paths, hooks, SQL-like values, and identifiers.
- Reduce decorative shadows and oversized padding.

- [ ] **Step 5: Normalize settings and action pages**

For SMTP, Safe Mode, and Maintenance Mode:

- Use the shared form-row pattern.
- Use one primary action per form.
- Keep secondary/destructive actions visually distinct.
- Preserve previews, media selection, toggles, plugin/theme selection, and all submitted fields.

- [ ] **Step 6: Add responsive module rules**

In `siteintelix-admin.css`, ensure:

- Sidebars stack under main content below 1100px.
- Multi-column stat grids reduce progressively.
- Forms and toolbars wrap without clipping.
- Tables use overflow rather than shrinking unreadably.
- WordPress’s 782px mobile admin breakpoint is supported.

- [ ] **Step 7: Run workflow guard and lint all retained module PHP**

Run:

```bash
node --test --test-name-pattern="retained module pages|Server Diagnostics|shared admin CSS" wp-content/plugins/siteintelix/tests/structural.test.mjs
find wp-content/plugins/siteintelix/admin wp-content/plugins/siteintelix/includes -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: all tests PASS; every PHP file reports no syntax errors.

- [ ] **Step 8: Record checkpoint**

Record the passing workflow test and lint output.

---

### Task 8: Refresh All Debug Log Modes and Keep Debug Assets Scoped

**Files:**
- Modify: `admin/views/debug-log-page-classic.php`
- Modify: `admin/views/debug-log-page-modern.php`
- Modify: `admin/views/debug-log-page-terminal.php`
- Modify: `assets/admin/css/siteintelix-debug-log.css`
- Modify: `assets/admin/js/siteintelix-debug-log.js`
- Modify: `siteintelix.php`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing/scoped asset assertions**

Append:

```js
test('Debug Log modes remain available and mode assets stay scoped', async () => {
  const [main, router, classic, modern, terminal] = await Promise.all([
    read('siteintelix.php'),
    read('admin/views/debug-log-page.php'),
    read('admin/views/debug-log-page-classic.php'),
    read('admin/views/debug-log-page-modern.php'),
    read('admin/views/debug-log-page-terminal.php'),
  ]);

  assert.match(router, /classic/);
  assert.match(router, /modern/);
  assert.match(router, /terminal_light/);
  assert.match(main, /siteintelix-debug-log-viewer/);
  assert.match(main, /siteintelix-debug-log/);
  assert.match(main, /in_array\(\s*\$debug_ui_mode,\s*array\(\s*'modern',\s*'terminal_light'/);
  assert.match(classic, /siteintelix-log-table/);
  assert.match(modern, /sitx-log-list/);
  assert.match(terminal, /sitx-terminal-shell/);
});
```

- [ ] **Step 2: Run the Debug Log contract test**

Run:

```bash
node --test --test-name-pattern="Debug Log modes" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS as a behavior guard before visual edits.

- [ ] **Step 3: Normalize Classic mode**

Keep:

- Refresh, settings, clear, and download actions.
- Level filters and search.
- Empty states.
- Table rows, levels, messages, files, line numbers, and pagination.

Apply shared header, button, toolbar, card, table, badge, and responsive overflow classes.

- [ ] **Step 4: Normalize Modern mode**

Keep:

- Summary cards.
- Mode status.
- Search and all filters.
- Grouping/hide/show controls.
- Expandable log groups.
- Copy/details actions.
- Pagination.

Use `siteintelix-debug-log.css` only for the visual structures unique to Modern log cards, sparklines, severity markers, expandable details, and filter panel.

- [ ] **Step 5: Normalize Terminal Light mode**

Keep terminal semantics, timestamp/level/message columns, and mode actions. Use the shared page chrome while retaining a light monospace log shell.

Do not introduce Terminal Dark or another mode.

- [ ] **Step 6: Guard JavaScript work by page controls**

In `siteintelix-debug-log.js`, retain current behavior but ensure every feature initializes only after its root is found:

```js
var logList = document.querySelector('[data-sitx-log-list]');
if (logList) {
	logList.addEventListener('click', handleLogListClick);
}
```

Use one delegated listener for repeated log-card actions rather than one listener per row/card.

- [ ] **Step 7: Preserve precise enqueue rules**

In `siteintelix_enqueue_admin_assets()`, retain:

```php
if (
	false !== strpos( (string) $hook_suffix, 'siteintelix-debug-log' )
	&& in_array( $debug_ui_mode, array( 'modern', 'terminal_light' ), true )
) {
	// Enqueue Debug Log-only CSS and JS.
}
```

Classic mode must use only the shared admin assets.

- [ ] **Step 8: Run tests, JS syntax checks, and lint**

Run:

```bash
node --test --test-name-pattern="Debug Log modes|shared admin CSS" wp-content/plugins/siteintelix/tests/structural.test.mjs
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-debug-log.js
php -l wp-content/plugins/siteintelix/admin/views/debug-log-page-classic.php
php -l wp-content/plugins/siteintelix/admin/views/debug-log-page-modern.php
php -l wp-content/plugins/siteintelix/admin/views/debug-log-page-terminal.php
php -l wp-content/plugins/siteintelix/siteintelix.php
```

Expected: all PASS.

- [ ] **Step 9: Record checkpoint**

Record the passing test, syntax, and lint output.

---

### Task 9: Consolidate Admin JavaScript and Verify Lightweight Asset Behavior

**Files:**
- Modify: `assets/admin/js/siteintelix-admin.js`
- Modify: `siteintelix.php`
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Add asset-scope assertions**

Append:

```js
test('shared assets load only on SiteIntelix screens and contain no external UI dependency', async () => {
  const [main, adminJs, adminCss] = await Promise.all([
    read('siteintelix.php'),
    read('assets/admin/js/siteintelix-admin.js'),
    read('assets/admin/css/siteintelix-admin.css'),
  ]);

  assert.match(main, /strpos\(\s*\(string\)\s*\$hook_suffix,\s*'siteintelix'/);
  assert.doesNotMatch(adminJs, /\b(jQuery|React|Vue|axios)\b/);
  assert.doesNotMatch(adminCss, /@import\s+url|fonts\.googleapis|cdnjs|unpkg|jsdelivr/);
});
```

- [ ] **Step 2: Run the asset test**

Run:

```bash
node --test --test-name-pattern="shared assets load" wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS before refactoring; retain as a guard.

- [ ] **Step 3: Consolidate repeated admin listeners**

In `siteintelix-admin.js`:

- Keep the existing DOM-ready entry point.
- Return early from page-specific initializers when their root element is absent.
- Use delegated handlers for module toggles, confirmation links, settings tabs, and repeated action buttons where behavior is identical.
- Keep current AJAX URL, nonces, localized labels, and payload keys.
- Remove dead handlers tied only to deleted Custom Error UI markup.

Use this initializer pattern:

```js
document.addEventListener('DOMContentLoaded', function () {
	initNoticeSlot();
	initOverviewReportActions();
	initModuleToggles();
	initSettingsTabs();
	initConfirmations();
});

function initModuleToggles() {
	var root = document.querySelector('.si-admin-wrap');
	if (!root) {
		return;
	}

	root.addEventListener('change', function (event) {
		var toggle = event.target.closest('[data-siteintelix-module-toggle]');
		if (!toggle) {
			return;
		}
		handleModuleToggle(toggle);
	});
}
```

Ensure no initializer binds duplicate listeners to the same root.

- [ ] **Step 4: Keep shared enqueue lightweight**

Continue loading `siteintelix-admin.css` and `siteintelix-admin.js` only when the hook suffix contains `siteintelix`. Do not add frontend enqueues or remote dependencies.

- [ ] **Step 5: Run tests and JavaScript syntax checks**

Run:

```bash
node --test --test-name-pattern="shared assets load|core admin pages|retained module pages" wp-content/plugins/siteintelix/tests/structural.test.mjs
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-admin.js
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-debug-log.js
```

Expected: all PASS.

- [ ] **Step 6: Compare asset sizes**

Run:

```bash
wc -c \
  wp-content/plugins/siteintelix/assets/admin/css/siteintelix-admin.css \
  wp-content/plugins/siteintelix/assets/admin/css/siteintelix-debug-log.css \
  wp-content/plugins/siteintelix/assets/admin/js/siteintelix-admin.js \
  wp-content/plugins/siteintelix/assets/admin/js/siteintelix-debug-log.js
```

Record before/after byte counts. If the shared CSS grows, confirm the increase is justified by removing duplicated rules and covering all pages; remove unreachable selectors before proceeding.

- [ ] **Step 7: Record checkpoint**

Record passing tests, syntax checks, and asset sizes.

---

### Task 10: Update Documentation and Complete Automated Verification

**Files:**
- Modify: `readme.txt`
- Modify: `tests/structural.test.mjs`
- Test: all plugin PHP, JS, CSS, and structural contracts.

- [ ] **Step 1: Update readme title and feature language**

Set:

```text
=== SiteIntelix – Debug Logs, Email Logs & Diagnostics ===
```

Update the opening summary to emphasize debug logs, email logs, server/PHP diagnostics, cron, database, and troubleshooting.

Remove Custom Error UI from feature lists and settings documentation.

Describe Server Diagnostics without plugin compatibility claims.

- [ ] **Step 2: Add a release changelog entry**

Add a new top changelog entry containing:

```text
* Renamed the plugin to SiteIntelix – Debug Logs, Email Logs & Diagnostics without changing the plugin slug.
* Refreshed every SiteIntelix admin screen with a lightweight WordPress-native design system.
* Removed the Custom Error UI module and safely cleans up SiteIntelix-owned legacy drop-ins and settings.
* Simplified Server Diagnostics to server, PHP, PHP configuration, filesystem, network, WordPress, and database reporting.
* Reduced duplicated admin styling and kept page-specific assets scoped to the screens that need them.
```

- [ ] **Step 3: Run the complete structural suite**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: all tests PASS with zero failures.

- [ ] **Step 4: Run PHP lint across the complete plugin**

Run:

```bash
find wp-content/plugins/siteintelix -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: every PHP file reports `No syntax errors detected`.

- [ ] **Step 5: Run JavaScript syntax checks**

Run:

```bash
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-admin.js
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-debug-log.js
```

Expected: no output and exit status 0.

- [ ] **Step 6: Run CSS structural validation**

Run:

```bash
node -e "
const fs=require('fs');
for (const file of [
  'wp-content/plugins/siteintelix/assets/admin/css/siteintelix-admin.css',
  'wp-content/plugins/siteintelix/assets/admin/css/siteintelix-debug-log.css'
]) {
  const css=fs.readFileSync(file,'utf8').replace(/\\/\\*[\\s\\S]*?\\*\\//g,'');
  let depth=0;
  for (const ch of css) {
    if (ch === '{') depth++;
    if (ch === '}') depth--;
    if (depth < 0) throw new Error(file + ': unexpected closing brace');
  }
  if (depth !== 0) throw new Error(file + ': unbalanced braces');
  console.log(file + ': balanced');
}"
```

Expected: both stylesheets report `balanced`.

- [ ] **Step 7: Scan for retired code and external assets**

Run:

```bash
rg -n "Custom Error UI|SITEINTELIX_Error_UI|modules/error-ui|selected_plugin|Compatibility Profiles|Check Plugin|fonts\\.googleapis|cdnjs|unpkg|jsdelivr" wp-content/plugins/siteintelix
```

Expected: no production-code matches. Historical changelog wording should also be removed or explicitly marked as historical only if retained.

- [ ] **Step 8: Record automated verification checkpoint**

Save complete test, lint, syntax, CSS, scan, and asset-size results for final handoff.

---

### Task 11: Perform WordPress Functional and Visual Verification

**Files:**
- Modify only if verification reveals defects.
- Test through the local WordPress admin installation.

- [ ] **Step 1: Verify plugin identity and migration**

In WordPress admin:

- Confirm the Plugins screen shows `SiteIntelix – Debug Logs, Email Logs & Diagnostics`.
- Confirm the SiteIntelix slug/menu URL remains unchanged.
- Confirm Custom Error UI is absent from Modules and Settings.
- Confirm `error_ui` is absent from the saved enabled-module list.
- Confirm SiteIntelix-owned Error UI config/drop-ins are removed.
- Confirm any non-SiteIntelix `db-error.php` or `fatal-error-handler.php` fixture remains untouched.

- [ ] **Step 2: Smoke-test Overview and Modules**

Verify:

- Six status cards render and wrap correctly.
- System Overview values and semantic statuses are legible.
- Copy Report and Export JSON still work.
- Module toggles update through AJAX.
- Menu items update after module changes.
- Module search and links work.

- [ ] **Step 3: Smoke-test Settings and retained module pages**

For every enabled module, verify:

- Page header, controls, notices, cards, forms, tables, and actions render consistently.
- Settings save with the existing nonce/action flow.
- Email Log filters, details, modal, and bulk actions work.
- SMTP save/test behavior works.
- Cron search/filter/run/delete behavior works.
- Database tabs, browsing, pagination, edit/delete safeguards work.
- Download Manager actions work.
- Transients search/filter/view/edit/delete actions work.
- Safe Mode start/stop/reset controls work.
- Maintenance Mode settings, preview, media, and status work.

- [ ] **Step 4: Smoke-test Server Diagnostics**

Verify:

- No plugin selector or compatibility card is present.
- PHP & Server, Extensions, Filesystem, Network, WordPress, and Database sections render.
- Summary counts derive from retained checks.
- JSON export contains no `compatibility` or `selected_plugin` keys.
- Text support report contains retained sections only.

- [ ] **Step 5: Smoke-test all Debug Log modes**

For Classic, Modern, and Terminal Light:

- Switch mode through Settings.
- Confirm refresh, clear, download, filtering, search, pagination, and empty states.
- Confirm Modern expand/copy/filter behavior.
- Confirm Terminal Light readability and scrolling.
- Confirm Classic does not load Debug Log-only CSS/JS.

- [ ] **Step 6: Verify responsive layouts**

Inspect at:

```text
1440px desktop
1180px narrow desktop
960px tablet-like admin
782px WordPress mobile breakpoint
600px narrow viewport
```

Confirm:

- No horizontal page overflow except intentional table scrollers.
- Headers and actions wrap.
- Sidebars stack.
- Status grids collapse.
- Forms remain usable.
- Admin menu collapsed/expanded states do not overlap content.

- [ ] **Step 7: Verify accessibility basics**

Keyboard-check:

- Visible focus on links, buttons, tabs, toggles, inputs, and icon controls.
- Settings tabs update `aria-selected`.
- Icon-only buttons have accessible names.
- Status remains understandable without relying only on color.
- Reduced-motion mode suppresses nonessential transitions.

- [ ] **Step 8: Fix defects using red-green verification**

For each defect:

1. Add or tighten a structural assertion when the defect is source-contract testable.
2. Run it and observe failure.
3. Apply the smallest fix.
4. Re-run the focused test and relevant smoke test.

- [ ] **Step 9: Run the full verification suite one final time**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
find wp-content/plugins/siteintelix -name '*.php' -print0 | xargs -0 -n1 php -l
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-admin.js
node --check wp-content/plugins/siteintelix/assets/admin/js/siteintelix-debug-log.js
```

Expected: all automated checks PASS and manual smoke-test checklist is complete.

- [ ] **Step 10: Prepare final handoff**

Report:

- Changed public name.
- Visual system applied across every page.
- Custom Error UI removal and safe migration behavior.
- Server Diagnostics scope reduction.
- Automated test/lint results.
- Manual pages and viewport sizes verified.
- Before/after admin asset byte counts.
- The fact that no commit was created because the workspace is not a Git repository.

