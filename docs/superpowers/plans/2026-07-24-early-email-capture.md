# SiteIntelix Early Email Capture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Capture every standard WordPress `wp_mail()` success or failure event after the SiteIntelix plugin file loads, including Tutor LMS password-reset mail sent on `init` priority 10.

**Architecture:** Add a minimal early bootstrap that checks the saved Email Log module state directly, loads the Email Log class, and attaches capture listeners before `plugins_loaded` or `init` callbacks run. Keep schema checks, retention, and admin hooks in the existing priority-20 module boot, with an idempotent capture-registration method shared by both paths.

**Tech Stack:** PHP 7.4+, WordPress hooks and Options API, Node.js built-in test runner.

---

## File Structure

- Modify `tests/structural.test.mjs`: lock in the required early-bootstrap ordering, success/failure coverage, idempotence, and Tutor-independent implementation.
- Modify `siteintelix.php`: add the lightweight early Email Log bootstrap without initializing the module registry cache before other plugins load.
- Modify `includes/modules/email-log/class-siteintelix-email-log-module.php`: separate idempotent capture-hook registration from the module's normal lifecycle.

The implementation intentionally checks the saved module option directly during
early bootstrap. Calling `SITEINTELIX_Modules::is_enabled()` before all plugins
load would populate its request cache before other plugins can register the
`siteintelix_modules` filter.

### Task 1: Add the Early-Capture Regression Test

**Files:**
- Modify: `tests/structural.test.mjs`
- Test: `tests/structural.test.mjs`

- [x] **Step 1: Append a failing structural test**

Add this test after the existing admin-only module loading test:

```js
test('Email Log capture starts before plugin lifecycle hooks and remains idempotent', async () => {
	const [main, emailLog] = await Promise.all([
		read('siteintelix.php'),
		read('includes/modules/email-log/class-siteintelix-email-log-module.php'),
	]);

	const earlyBootstrapCall = main.indexOf('siteintelix_boot_early_email_capture();');
	const pluginsLoadedHook = main.indexOf("add_action( 'plugins_loaded', 'siteintelix_load_includes' );");
	const initBootHook = main.indexOf("add_action( 'init', 'siteintelix_boot_enabled_modules', 20 );");

	assert.ok(earlyBootstrapCall >= 0, 'missing early Email Log capture bootstrap');
	assert.ok(earlyBootstrapCall < pluginsLoadedHook, 'email capture must start before plugins_loaded callbacks');
	assert.ok(earlyBootstrapCall < initBootHook, 'email capture must start before the normal priority-20 module boot');
	assert.match(main, /get_option\(\s*SITEINTELIX_MODULES_OPTION,\s*null\s*\)/);
	assert.match(main, /in_array\(\s*'email_log'/);
	assert.match(main, /SITEINTELIX_Email_Log_Module::register_capture_hooks\(\)/);

	assert.match(emailLog, /private static \$capture_hooks_registered\s*=\s*false/);
	assert.match(emailLog, /public static function register_capture_hooks\s*\(/);
	assert.match(emailLog, /add_action\(\s*'wp_mail_succeeded',\s*array\(\s*__CLASS__,\s*'log_success'\s*\)/s);
	assert.match(emailLog, /add_action\(\s*'wp_mail_failed',\s*array\(\s*__CLASS__,\s*'log_failure'\s*\)/s);
	assert.match(emailLog, /if\s*\(\s*self::\$capture_hooks_registered\s*\)\s*\{\s*return;/s);
	assert.match(emailLog, /public static function init\s*\(\)\s*\{\s*self::register_capture_hooks\(\);/s);
	assert.doesNotMatch(main + emailLog, /Tutor LMS|tutor_retrieve_password|tutor_action_tutor_retrieve_password/);
});
```

- [x] **Step 2: Run the focused test and verify RED**

Run:

```bash
node --test --test-name-pattern="Email Log capture starts" tests/structural.test.mjs
```

Expected: `FAIL` with `missing early Email Log capture bootstrap`.

### Task 2: Register Mail Capture During Early Bootstrap

**Files:**
- Modify: `siteintelix.php`
- Modify: `includes/modules/email-log/class-siteintelix-email-log-module.php`
- Test: `tests/structural.test.mjs`

- [x] **Step 1: Add idempotent capture registration to the Email Log class**

Immediately after the constants in
`includes/modules/email-log/class-siteintelix-email-log-module.php`, add:

```php
	/**
	 * Whether the request's mail capture hooks are registered.
	 *
	 * @var bool
	 */
	private static $capture_hooks_registered = false;
```

Replace the first two `add_action()` calls in `init()` with:

```php
		self::register_capture_hooks();
```

Immediately after `init()`, add:

```php
	/**
	 * Register outgoing mail capture as early as possible.
	 *
	 * Safe to call from both the plugin bootstrap and the normal module
	 * lifecycle without adding the callbacks more than once.
	 *
	 * @return void
	 */
	public static function register_capture_hooks() {
		if ( self::$capture_hooks_registered ) {
			return;
		}

		add_action( 'wp_mail_succeeded', array( __CLASS__, 'log_success' ), 10, 1 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ), 10, 1 );

		self::$capture_hooks_registered = true;
	}
```

- [x] **Step 2: Add the lightweight early bootstrap to the main plugin file**

Immediately after the plugin constants and before
`siteintelix_should_load_admin_modules()`, add:

```php
/**
 * Register Email Log capture before other plugin lifecycle callbacks can send.
 *
 * The saved option is read directly so the filtered module registry is not
 * cached before every plugin has loaded.
 *
 * @return void
 */
function siteintelix_boot_early_email_capture() {
	$enabled_modules = get_option( SITEINTELIX_MODULES_OPTION, null );

	if ( ! is_array( $enabled_modules ) ) {
		return;
	}

	$enabled_modules = array_map( 'sanitize_key', $enabled_modules );
	if ( ! in_array( 'email_log', $enabled_modules, true ) ) {
		return;
	}

	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/email-log/class-siteintelix-email-log-module.php';
	SITEINTELIX_Email_Log_Module::register_capture_hooks();
}
siteintelix_boot_early_email_capture();
```

- [x] **Step 3: Run the focused test and verify GREEN**

Run:

```bash
node --test --test-name-pattern="Email Log capture starts" tests/structural.test.mjs
```

Expected: the focused test reports `pass 1`, with unrelated tests skipped by
the name filter.

- [x] **Step 4: Run PHP syntax checks**

Run:

```bash
php -l siteintelix.php
php -l includes/modules/email-log/class-siteintelix-email-log-module.php
```

Expected for both files: `No syntax errors detected`.

### Task 3: Verify the Complete Plugin

**Files:**
- Verify: `tests/structural.test.mjs`
- Verify: `tests/debug-log-parser.php`
- Verify: `tests/editor-links.php`
- Verify: `tests/runtime-smoke.php`

- [x] **Step 1: Run all structural tests**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: all tests pass with zero failures.

- [x] **Step 2: Run standalone PHP tests**

Run:

```bash
php tests/debug-log-parser.php
php tests/editor-links.php
```

Expected: both scripts exit with status 0 and report passing assertions.

- [x] **Step 3: Run the WordPress runtime smoke test**

Run:

```bash
wp eval-file tests/runtime-smoke.php
```

Expected: exit status 0 with a final SiteIntelix runtime-smoke success message.

- [x] **Step 4: Inspect the final diff**

Run:

```bash
git diff -- tests/structural.test.mjs siteintelix.php includes/modules/email-log/class-siteintelix-email-log-module.php
```

If the workspace remains outside a Git repository, inspect the three files
directly and confirm only the planned test, bootstrap, and hook-registration
changes are present.

- [x] **Step 5: Perform a Tutor LMS reset smoke check**

Submit a valid username or email address through the Tutor LMS password-reset
page, then open **SiteIntelix → Email Log**.

Expected: exactly one new row appears for the password-reset message, with
`sent` or `failed` status matching the WordPress mail outcome.

## Commit Note

The current WordPress site copy has no `.git` repository, so the usual
test/implementation commits cannot be created. If the plugin is later placed
in a repository, commit the regression test and implementation together with:

```bash
git add tests/structural.test.mjs siteintelix.php includes/modules/email-log/class-siteintelix-email-log-module.php
git commit -m "fix: capture emails sent during early init"
```
