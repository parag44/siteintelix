# SiteIntelix 2.7.3 Release Security Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the current SiteIntelix code as a security-reviewed `2.7.3` branch with accurate WordPress.org metadata, regression coverage, a clean release package, and no merge or publication side effects.

**Architecture:** Add one small authorization policy class for high-risk tools, then harden the existing MU-file, generated-file, transient-preview, and SMTP boundaries in their current owners. Preserve the modular runtime and the intentionally administrator-authored Code Snippets executor, while documenting its `eval()` boundary and restricting its controls to trusted administrators or multisite super administrators.

**Tech Stack:** WordPress 5.8+ APIs, PHP 7.4+, native PHP token parsing, `$wpdb`, WP Filesystem, Node.js built-in test runner, standalone PHP regression scripts, PHPCS/WPCS, WordPress Plugin Check.

---

## File Map

- Create `includes/class-siteintelix-security.php`: central least-privilege policy for ordinary, code-execution, and network/global tools.
- Create `tests/security-capabilities.php`: isolated capability-policy regressions.
- Create `tests/custom-code-file-manager.php`: managed generated-file path regressions.
- Create `tests/transients-safe-unserialize.php`: object-instantiation regression.
- Create `tests/smtp-settings-security.php`: SMTP secret handling and autoload regression.
- Create `docs/security-audit-2.7.3.md`: durable audit and release verification record, excluded from the distribution by `.distignore`.
- Modify `siteintelix.php`: version, security bootstrap, sensitive module-toggle policy, debug configuration authorization, and response headers.
- Modify sensitive module admin/controller files: enforce the central policy at menus, renderers, settings, actions, and destructive operations.
- Modify `includes/class-siteintelix-mu-files.php` and `includes/class-siteintelix-mu-debug.php`: ownership-aware legacy cleanup only.
- Modify `includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php`: exact managed-path validation and canonical containment.
- Modify `includes/modules/transients-manager/class-siteintelix-transients-manager-module.php`: safe serialized preview decoding.
- Modify `includes/modules/smtp/class-siteintelix-smtp-module.php`: non-autoloaded credentials and password-preserving validation.
- Modify `includes/class-siteintelix-migrations.php`: run the one-time SMTP autoload migration as `2.7.3.0`.
- Modify repository SQL call sites and `uninstall.php`: document fixed/allow-listed identifiers for Plugin Check without changing query behavior.
- Modify `readme.txt`, `languages/index.php`, `.distignore`, and tests: release metadata, directory disclosure, direct-access guard, packaging, and verification assertions.

### Task 1: Lock the 2.7.3 Release Contract

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `siteintelix.php`
- Modify: `readme.txt`

- [ ] **Step 1: Add failing release metadata assertions**

Add this test to `tests/structural.test.mjs`:

```js
test('release metadata is aligned to 2.7.3', () => {
	assert.match(main, /^ \* Version:\s+2\.7\.3$/m);
	assert.match(main, /define\(\s*'SITEINTELIX_VERSION',\s*'2\.7\.3'\s*\)/);
	assert.match(readme, /^Stable tag:\s+2\.7\.3$/m);
	assert.match(readme, /^Tested up to:\s+7\.0$/m);
	assert.match(readme, /^Requires PHP:\s+7\.4$/m);
	assert.match(readme, /^= 2\.7\.3 =$/m);
	assert.match(readme, /^= 2\.7\.3 =\n/m);
});
```

- [ ] **Step 2: Verify the contract fails on 2.7.2**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: the new test fails because the plugin header, constant, and stable tag still contain `2.7.2`.

- [ ] **Step 3: Update active version sources**

Change the main header and constant in `siteintelix.php` to:

```php
 * Version:     2.7.3
define( 'SITEINTELIX_VERSION', '2.7.3' );
```

Change the active metadata in `readme.txt` to:

```text
Stable tag: 2.7.3
Tested up to: 7.0
Requires PHP: 7.4
```

Add a dated `2.7.3` changelog heading and matching upgrade-notice heading. Do not rewrite historical version references.

- [ ] **Step 4: Verify the release contract passes**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: all structural tests pass.

- [ ] **Step 5: Commit the release contract**

```bash
git add siteintelix.php readme.txt tests/structural.test.mjs
git commit -m "chore: begin SiteIntelix 2.7.3 release"
```

### Task 2: Enforce Least Privilege for Sensitive Tools

**Files:**
- Create: `includes/class-siteintelix-security.php`
- Create: `tests/security-capabilities.php`
- Modify: `siteintelix.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-code-snippets-module.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-admin.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-actions.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-module.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-admin.php`
- Modify: `includes/modules/database-manager/class-siteintelix-database-manager-module.php`
- Modify: `includes/modules/download-manager/class-siteintelix-download-manager-module.php`
- Modify: `includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php`

- [ ] **Step 1: Write the failing policy test**

Create `tests/security-capabilities.php` with WordPress stubs and assertions for this matrix:

```php
$cases = array(
	array( false, false, array(), false, false, false ),
	array( false, false, array( 'manage_options' ), true, false, true ),
	array( false, false, array( 'manage_options', 'unfiltered_html' ), true, true, true ),
	array( true, false, array( 'manage_options', 'unfiltered_html' ), true, false, false ),
	array( true, true, array( 'manage_options', 'unfiltered_html' ), true, true, true ),
);

foreach ( $cases as $case ) {
	list( $multisite, $super_admin, $capabilities, $manage, $code, $global ) = $case;
	// Assign stub globals, then assert can_manage(), can_manage_code(), and can_manage_global_tools().
}

siteintelix_test_assert( SITEINTELIX_Security::can_manage_module( 'smtp' ), 'SMTP uses ordinary site administration.' );
siteintelix_test_assert( ! SITEINTELIX_Security::can_manage_module( 'code-snippets' ), 'Code Snippets requires the code policy.' );
siteintelix_test_assert( ! SITEINTELIX_Security::can_manage_module( 'database-manager' ), 'Database Manager requires the global-tools policy.' );
```

The complete test must define `ABSPATH`, `is_multisite()`, `is_super_admin()`, and `current_user_can()` stubs before requiring the new class.

- [ ] **Step 2: Verify the policy test fails**

Run:

```bash
php tests/security-capabilities.php
```

Expected: failure because `includes/class-siteintelix-security.php` does not exist.

- [ ] **Step 3: Implement the central policy**

Create `includes/class-siteintelix-security.php`:

```php
<?php
/**
 * SiteIntelix authorization policy.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

final class SITEINTELIX_Security {
	const CODE_MODULES = array( 'code-snippets', 'custom-code' );
	const GLOBAL_MODULES = array( 'database-manager', 'download-manager', 'safe-mode-debugger' );

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function can_manage_code() {
		return self::can_manage()
			&& current_user_can( 'unfiltered_html' )
			&& ( ! is_multisite() || is_super_admin() );
	}

	public static function can_manage_global_tools() {
		return self::can_manage() && ( ! is_multisite() || is_super_admin() );
	}

	public static function can_manage_module( $module_id ) {
		$module_id = sanitize_key( $module_id );
		if ( in_array( $module_id, self::CODE_MODULES, true ) ) {
			return self::can_manage_code();
		}
		if ( in_array( $module_id, self::GLOBAL_MODULES, true ) ) {
			return self::can_manage_global_tools();
		}
		return self::can_manage();
	}
}
```

Format the constants and DocBlocks to WPCS while preserving the exact behavior.

- [ ] **Step 4: Load and apply the policy**

Require the class from `siteintelix_load_includes()`. Replace sensitive `manage_options` checks with:

```php
if ( ! SITEINTELIX_Security::can_manage_code() ) {
	wp_die( esc_html__( 'You do not have permission to manage executable code.', 'siteintelix' ), 403 );
}
```

for Custom CSS & JS and Code Snippets controls, and:

```php
if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
	wp_die( esc_html__( 'You do not have permission to manage this network-sensitive tool.', 'siteintelix' ), 403 );
}
```

for Database Manager, Download Manager, Safe Mode, MU debug toggles, and wp-config debug writes.

Before changing a module in `siteintelix_ajax_toggle_module()`, require:

```php
if ( ! SITEINTELIX_Security::can_manage_module( $module_id ) ) {
	wp_send_json_error( array( 'message' => __( 'You do not have permission to manage this module.', 'siteintelix' ) ), 403 );
}
```

Keep ordinary tools on `manage_options`. Keep all existing nonces and method checks.

- [ ] **Step 5: Run policy and existing module tests**

Run:

```bash
php tests/security-capabilities.php
php tests/code-snippets-validator.php
php tests/code-snippets-normalization.php
node --test tests/structural.test.mjs tests/admin-interactions.test.mjs
```

Expected: every command passes.

- [ ] **Step 6: Commit the authorization boundary**

```bash
git add includes/class-siteintelix-security.php siteintelix.php includes/modules/code-snippets includes/modules/custom-code includes/modules/database-manager/class-siteintelix-database-manager-module.php includes/modules/download-manager/class-siteintelix-download-manager-module.php includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php tests/security-capabilities.php
git commit -m "security: restrict sensitive tools to trusted administrators"
```

### Task 3: Preserve Foreign MU Files During Legacy Cleanup

**Files:**
- Modify: `tests/mu-files-cleanup.php`
- Modify: `includes/class-siteintelix-mu-files.php`
- Modify: `includes/class-siteintelix-mu-debug.php`

- [ ] **Step 1: Add failing foreign-file and verified-legacy assertions**

Extend `tests/mu-files-cleanup.php` with:

```php
$foreign_legacy = "<?php\n/** Plugin Name: Another Debug Tool */\nfunction another_debug_capture() {}\n";
$owned_legacy   = "<?php\n/** Plugin Name: SiteIntelix Debug Capture */\ndefine( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_enable_debug_capture' );\nfunction siteintelix_debug_capture_enabled() {}\n";

siteintelix_test_fixture( $active_directory, 'my-debug-capture.php', $foreign_legacy );
siteintelix_test_fixture( $standard_directory, 'siteintelix-debug.php', $owned_legacy );

$result = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_LEGACY_DEBUG );

siteintelix_test_assert( file_exists( $active_directory . '/my-debug-capture.php' ), 'A foreign same-name MU file is preserved.' );
siteintelix_test_assert( ! file_exists( $standard_directory . '/siteintelix-debug.php' ), 'A signature-verified legacy SiteIntelix file is removed.' );
siteintelix_test_assert( in_array( wp_normalize_path( $active_directory . '/my-debug-capture.php' ), $result['preserved'], true ), 'Foreign file is reported as preserved.' );
```

- [ ] **Step 2: Verify the ownership test fails**

Run:

```bash
php tests/mu-files-cleanup.php
```

Expected: failure because `TYPE_LEGACY_DEBUG` and its fixed candidate definitions do not exist.

- [ ] **Step 3: Add fixed legacy candidates and remove blind deletion**

Add a `TYPE_LEGACY_DEBUG` definition whose candidates are exactly `my-debug-capture.php` and `siteintelix-debug.php`, with complete SiteIntelix ownership signatures. Update `remove_type()` to iterate each fixed filename in the definition. Do not accept wildcard paths, partial product-name matches, symlinks, unreadable files, or files missing a full signature.

Replace the deletion loop in `SITEINTELIX_MU_Debug::ensure_mu_plugin_file()` with:

```php
if ( class_exists( 'SITEINTELIX_MU_Files' ) ) {
	SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_LEGACY_DEBUG );
}
```

The result is intentionally non-fatal: inability to remove a retired verified file must not prevent creation of the current bootstrap.

- [ ] **Step 4: Verify MU ownership behavior**

Run:

```bash
php tests/mu-files-cleanup.php
```

Expected: all current, legacy, safe-mode, safety-guard, symlink, unreadable, and foreign-file assertions pass.

- [ ] **Step 5: Commit MU cleanup hardening**

```bash
git add includes/class-siteintelix-mu-files.php includes/class-siteintelix-mu-debug.php tests/mu-files-cleanup.php
git commit -m "security: verify ownership before removing MU files"
```

### Task 4: Confine Custom Code Files to the Managed Namespace

**Files:**
- Create: `tests/custom-code-file-manager.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-runner.php`

- [ ] **Step 1: Write the failing managed-path test**

Create a standalone test with a temporary uploads directory and stubs, then assert:

```php
$managed = 'siteintelix/custom-code/site-1-entry-25.css';
$foreign = '2026/07/customer-upload.css';

siteintelix_test_assert( SITEINTELIX_Custom_Code_File_Manager::is_managed_file( $managed ), 'Generated SiteIntelix path is accepted.' );
siteintelix_test_assert( ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file( $foreign ), 'Unrelated upload path is rejected.' );
siteintelix_test_assert( ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file( '../wp-config.php' ), 'Traversal is rejected.' );
siteintelix_test_assert( false === SITEINTELIX_Custom_Code_File_Manager::delete( $foreign ), 'Foreign upload deletion is denied.' );
siteintelix_test_assert( file_exists( $uploads . '/' . $foreign ), 'Foreign upload remains on disk.' );
siteintelix_test_assert( SITEINTELIX_Custom_Code_File_Manager::delete( $managed ), 'Managed file can be deleted.' );
siteintelix_test_assert( '' === SITEINTELIX_Custom_Code_File_Manager::url( $foreign ), 'Foreign path does not receive a public URL.' );
```

- [ ] **Step 2: Verify the managed-path test fails**

Run:

```bash
php tests/custom-code-file-manager.php
```

Expected: failure because `is_managed_file()` is undefined and foreign paths are currently accepted.

- [ ] **Step 3: Implement exact path validation and canonical containment**

Add:

```php
public static function is_managed_file( $relative_file ) {
	if ( ! is_string( $relative_file ) ) {
		return false;
	}

	$relative_file = wp_normalize_path( ltrim( $relative_file, '/' ) );
	return 1 === preg_match(
		'#^siteintelix/custom-code/site-[1-9][0-9]*-entry-[1-9][0-9]*\.(?:css|js)$#',
		$relative_file
	);
}
```

Create one private resolver that combines the validated relative path with the normalized uploads base directory and verifies the resolved directory starts with `trailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . 'siteintelix/custom-code/'`. Use it from `delete()` and `url()`. Return `false` from `delete()` and an empty string from `url()` for any invalid path. Update the runner to fall back to inline code when a stored generated path is rejected.

- [ ] **Step 4: Verify generated-file confinement**

Run:

```bash
php tests/custom-code-file-manager.php
php tests/code-snippets-normalization.php
```

Expected: both scripts pass.

- [ ] **Step 5: Commit file-boundary hardening**

```bash
git add includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php includes/modules/custom-code/class-siteintelix-custom-code-runner.php tests/custom-code-file-manager.php
git commit -m "security: confine generated custom code files"
```

### Task 5: Prevent Object Instantiation in Transient Previews

**Files:**
- Create: `tests/transients-safe-unserialize.php`
- Modify: `includes/modules/transients-manager/class-siteintelix-transients-manager-module.php`

- [ ] **Step 1: Write the failing deserialization test**

Create a class whose `__wakeup()` increments a counter, serialize an instance, and call the module's safe decoder through reflection:

```php
class SITEINTELIX_Wakeup_Probe {
	public static $wakeups = 0;
	public function __wakeup() {
		++self::$wakeups;
	}
}

$serialized = serialize( new SITEINTELIX_Wakeup_Probe() );
$decoded    = $method->invoke( null, $serialized );

siteintelix_test_assert( 0 === SITEINTELIX_Wakeup_Probe::$wakeups, 'Preview decoding never instantiates serialized objects.' );
siteintelix_test_assert( is_object( $decoded ) && '__PHP_Incomplete_Class' === get_class( $decoded ), 'Object payload is represented without loading its class.' );
siteintelix_test_assert( false === $method->invoke( null, 'b:0;' ), 'Serialized false is decoded correctly.' );
siteintelix_test_assert( 'plain text' === $method->invoke( null, 'plain text' ), 'Plain values are unchanged.' );
```

- [ ] **Step 2: Verify the safe-decoder test fails**

Run:

```bash
php tests/transients-safe-unserialize.php
```

Expected: failure because the private safe decoder is absent or `maybe_unserialize()` invokes `__wakeup()`.

- [ ] **Step 3: Add a PHP 7.4-compatible safe decoder**

Add:

```php
private static function safe_unserialize_for_preview( $value ) {
	if ( ! is_serialized( $value ) ) {
		return $value;
	}

	return unserialize( trim( $value ), array( 'allowed_classes' => false ) );
}
```

Use this method instead of `maybe_unserialize()` only in `fetch_item()`. This preserves scalar and array previews while preventing class loading.

- [ ] **Step 4: Verify transient preview safety**

Run:

```bash
php tests/transients-safe-unserialize.php
```

Expected: every assertion passes and the wakeup counter remains zero.

- [ ] **Step 5: Commit safe preview decoding**

```bash
git add includes/modules/transients-manager/class-siteintelix-transients-manager-module.php tests/transients-safe-unserialize.php
git commit -m "security: disable object loading in transient previews"
```

### Task 6: Keep SMTP Credentials Out of Autoloaded Options

**Files:**
- Create: `tests/smtp-settings-security.php`
- Modify: `includes/modules/smtp/class-siteintelix-smtp-module.php`
- Modify: `includes/class-siteintelix-migrations.php`

- [ ] **Step 1: Write failing SMTP security assertions**

Create stubs that record `add_option()` and `update_option()` arguments, then assert:

```php
SITEINTELIX_SMTP_Module::activate();
siteintelix_test_assert( false === $recorded_add_option['autoload'], 'New SMTP credentials are not autoloaded.' );

$password = " leading space !@#$%^&*() trailing space ";
$clean    = SITEINTELIX_SMTP_Module::sanitize_password( $password );
siteintelix_test_assert( $password === $clean, 'Valid password characters and spaces are preserved.' );
siteintelix_test_assert( 'nulremoved' === SITEINTELIX_SMTP_Module::sanitize_password( "nul\0removed" ), 'NUL bytes are removed.' );
siteintelix_test_assert( 1024 === strlen( SITEINTELIX_SMTP_Module::sanitize_password( str_repeat( 'a', 2048 ) ) ), 'Passwords are bounded.' );
```

Also assert the save path calls `update_option( SITEINTELIX_SMTP_Module::SETTINGS_OPTION, $settings, false )`.

- [ ] **Step 2: Verify the SMTP security test fails**

Run:

```bash
php tests/smtp-settings-security.php
```

Expected: failure because the option currently uses default autoloading and the password uses `sanitize_text_field()`.

- [ ] **Step 3: Implement secret-preserving validation and non-autoload storage**

Use:

```php
add_option( self::SETTINGS_OPTION, self::get_default_settings(), '', false );
update_option( self::SETTINGS_OPTION, $settings, false );
```

Add:

```php
public static function sanitize_password( $password ) {
	if ( ! is_scalar( $password ) ) {
		return '';
	}

	return substr( str_replace( "\0", '', (string) $password ), 0, 1024 );
}
```

Call it only when a non-empty replacement password is submitted; otherwise preserve the saved password.

- [ ] **Step 4: Add a one-time autoload migration**

Bump `SITEINTELIX_DB_VERSION` to `2.7.3.0`. Add a migration that reads the existing SMTP array, deletes only `siteintelix_smtp_settings`, and immediately recreates it with:

```php
add_option( SITEINTELIX_SMTP_Module::SETTINGS_OPTION, $settings, '', false );
```

Run only when the option exists and is an array. Never log or include the credential value in a notice.

- [ ] **Step 5: Verify SMTP and migration behavior**

Run:

```bash
php tests/smtp-settings-security.php
php tests/runtime-smoke.php
```

Expected: all assertions and smoke checks pass without printing credential values.

- [ ] **Step 6: Commit SMTP storage hardening**

```bash
git add includes/modules/smtp/class-siteintelix-smtp-module.php includes/class-siteintelix-migrations.php tests/smtp-settings-security.php
git commit -m "security: stop autoloading SMTP credentials"
```

### Task 7: Close Static-Analysis and Direct-Access Findings

**Files:**
- Modify: `languages/index.php`
- Modify: `siteintelix.php`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-repository.php`
- Modify: `includes/modules/code-snippets/class-siteintelix-snippets-repository.php`
- Modify: `includes/modules/email-log/class-siteintelix-email-log-module.php`
- Modify: `uninstall.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing structural security assertions**

Add tests that require every shipped PHP file to contain an `ABSPATH` guard, require `X-Content-Type-Options: nosniff` on download/export handlers, and reject production `var_dump`, `print_r`, `error_log`, `shell_exec`, `exec`, `system`, `passthru`, and `proc_open`. Allow the single, documented Code Snippets `eval()` call by exact file and exact count.

- [ ] **Step 2: Run the structural suite and capture failures**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: failure for `languages/index.php` and missing download hardening headers.

- [ ] **Step 3: Apply narrow fixes**

Change `languages/index.php` to:

```php
<?php
/**
 * Prevent direct access.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;
```

Before all attachment bodies, send:

```php
header( 'X-Content-Type-Options: nosniff' );
```

Escape generated numeric data attributes with `esc_attr( (string) $id )`.

For fixed plugin-owned table names and allow-listed query fragments that Plugin Check cannot infer, retain prepared values and strict allow-lists, then add a precise inline `PluginCheck.Security.DirectDB.UnescapedDBParameter` suppression explaining the fixed identifier source. Do not suppress any request-derived identifier.

- [ ] **Step 4: Run security-focused PHPCS**

Run:

```bash
php ../plugin-check/vendor/bin/phpcs --standard=PluginCheck --extensions=php --ignore=docs,tests,.git .
```

Expected: zero Plugin Check standard errors and warnings.

- [ ] **Step 5: Run lint and structural tests**

Run:

```bash
find . -type f -name '*.php' -not -path './.git/*' -not -path './docs/*' -print0 | xargs -0 -n1 php -l
node --test tests/structural.test.mjs
```

Expected: every PHP file reports no syntax errors and every structural test passes.

- [ ] **Step 6: Commit static hardening**

```bash
git add languages/index.php siteintelix.php includes/modules/custom-code/class-siteintelix-custom-code-repository.php includes/modules/code-snippets/class-siteintelix-snippets-repository.php includes/modules/email-log/class-siteintelix-email-log-module.php uninstall.php tests/structural.test.mjs
git commit -m "security: harden direct access and download responses"
```

### Task 8: Rewrite WordPress.org Metadata for the Current Product

**Files:**
- Modify: `readme.txt`
- Modify: `siteintelix.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Add failing directory-metadata assertions**

Assert:

```js
assert.match(readme, /^Tags: debug log, email log, diagnostics, code snippets, admin tools$/m);
assert.ok(shortDescription.length <= 150);
assert.match(readme, /Custom CSS & JS/);
assert.match(readme, /Code Snippets/);
assert.match(readme, /User Switcher/);
assert.match(readme, /administrator-authored PHP/i);
assert.match(readme, /SMTP provider/i);
assert.match(readme, /no telemetry/i);
assert.match(readme, /multisite super administrator/i);
assert.match(readme, /== Upgrade Notice ==[\s\S]*= 2\.7\.3 =/);
```

- [ ] **Step 2: Verify the metadata assertions fail**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: failures for stale tags or missing current-module/security disclosures.

- [ ] **Step 3: Update the directory listing**

Use exactly five tags:

```text
Tags: debug log, email log, diagnostics, code snippets, admin tools
```

Keep the short description under 150 plain-text characters. Update the full description, feature list, installation, FAQ, privacy, screenshots, changelog, and upgrade notice so they accurately state:

- Custom CSS & JS can inject administrator-authored CSS or JavaScript.
- Code Snippets executes enabled administrator-authored PHP and automatically deactivates snippets after captured failures.
- User Switcher temporarily impersonates eligible users and keeps a local audit log.
- SMTP sends mail through the administrator-configured external provider and stores its credential locally without autoloading.
- diagnostics may contact the local site and WordPress.org endpoints on administrator request.
- debug, email, database, and switching data may contain sensitive information and is stored locally.
- SiteIntelix sends no telemetry.
- on multisite, code-execution and network/global tools require a super administrator.
- uninstall retention behavior matches `uninstall.php`.
- `eval()` is intentional only for locally authored Code Snippets; no remote code is fetched.

Do not add testimonials, guarantees, keyword stuffing, remote service promotions, or unsupported compatibility claims.

- [ ] **Step 4: Verify metadata and header alignment**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: all directory-metadata and structural tests pass.

- [ ] **Step 5: Commit the WordPress.org listing**

```bash
git add readme.txt siteintelix.php tests/structural.test.mjs
git commit -m "docs: prepare WordPress.org listing for 2.7.3"
```

### Task 9: Run the Complete Release Verification

**Files:**
- Create: `docs/security-audit-2.7.3.md`
- Modify: `.distignore`

- [ ] **Step 1: Run every Node test**

```bash
node --test tests/*.test.mjs
```

Expected: all tests pass with zero failures.

- [ ] **Step 2: Run every standalone PHP test**

```bash
for test_file in tests/*.php; do php "$test_file"; done
```

Expected: every script exits `0`; fixture files that are not standalone tests must be excluded if the shell glob includes them.

- [ ] **Step 3: Lint all shipped PHP and JavaScript**

```bash
find . -type f -name '*.php' -not -path './.git/*' -not -path './docs/*' -print0 | xargs -0 -n1 php -l
for script_file in assets/admin/js/*.js; do node --check "$script_file"; done
```

Expected: zero syntax errors.

- [ ] **Step 4: Run PHPCS and Plugin Check**

```bash
php ../plugin-check/vendor/bin/phpcs --standard=PluginCheck --extensions=php --ignore=docs,tests,.git .
php ../plugin-check/vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=docs,tests,.git .
wp plugin check siteintelix
```

Expected: the Plugin Check security standard is clean. Record the full WPCS style-debt count rather than applying broad formatting churn. If Local's database is unavailable and blocks `wp plugin check`, record that exact environmental blocker instead of reporting success.

- [ ] **Step 5: Repeat targeted static searches**

```bash
rg -n "eval\\(|unserialize\\(|maybe_unserialize|shell_exec|exec\\(|system\\(|passthru|proc_open|include\\s*\\(|require\\s*\\(|\\$_(GET|POST|REQUEST|FILES|COOKIE)|\\$wpdb->(query|get_|insert|update|delete)" --glob '*.php' .
rg -n "password|secret|token|api[_-]?key|BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|var_dump|print_r|error_log" --glob '!docs/**' --glob '!tests/**' .
```

Expected: every result is either removed, authorized and validated, a fixed include, a prepared/allow-listed query, the deliberate snippet executor, or a non-secret field label. Record the disposition in the audit report.

- [ ] **Step 6: Build and inspect a temporary distribution**

Ensure `.distignore` excludes `.git`, `.superpowers`, `docs`, `tests`, development files, and archives. Build into a temporary directory with the existing ignore rules, then verify:

```bash
test -f "$release_dir/siteintelix/siteintelix.php"
test -f "$release_dir/siteintelix/readme.txt"
test ! -e "$release_dir/siteintelix/.git"
test ! -e "$release_dir/siteintelix/tests"
test ! -e "$release_dir/siteintelix/docs"
test ! -e "$release_dir/siteintelix/.superpowers"
```

Run PHP lint against the temporary release tree. Remove the temporary tree after inspection; do not create a deliverable ZIP.

- [ ] **Step 7: Write the durable audit report**

Create `docs/security-audit-2.7.3.md` with:

- release scope, branch, base, and date;
- every changed production and test file;
- severity, attack precondition, affected component, and fix for each confirmed issue;
- WordPress.org guideline findings and dispositions;
- exact verification commands, totals, and exit results;
- explicit residual risks: intentional local `eval()`, plaintext-at-rest SMTP credential, administrator access to sensitive logs/database data, unavailable runtime checks, and pre-existing non-security WPCS style debt;
- a statement that no secrets, telemetry, unauthenticated AJAX, REST endpoints, remote executable code, shell commands, or uncontrolled dynamic includes were found.

- [ ] **Step 8: Commit verification records**

```bash
git add .distignore docs/security-audit-2.7.3.md
git commit -m "docs: record SiteIntelix 2.7.3 security audit"
```

### Task 10: Final Review, Commit, and Push

**Files:**
- Review: all tracked release files

- [ ] **Step 1: Confirm branch and repository state**

```bash
git branch --show-current
git status --short
git diff --check
git log --oneline --decorate -12
```

Expected: branch is exactly `2.7.3`; no whitespace errors; all intended plugin changes are tracked; no unrelated site files are present.

- [ ] **Step 2: Re-run the release gate**

Run the Node tests, all standalone PHP tests, PHP lint, JavaScript syntax checks, Plugin Check PHPCS standard, metadata assertions, and temporary package inspection again from the final commit candidate.

Expected: all available release gates pass; only explicitly documented environmental or style-debt items remain.

- [ ] **Step 3: Commit any remaining current plugin work**

Stage the complete plugin source while respecting `.distignore` and excluding generated archives:

```bash
git add -A
git status --short
git commit -m "release: prepare SiteIntelix 2.7.3"
```

If there is nothing new to commit, preserve the prior task commits and continue.

- [ ] **Step 4: Push only the release branch**

```bash
git push --set-upstream origin 2.7.3
```

Expected: GitHub reports the new remote branch `2.7.3`. Do not push a tag, merge another branch, create a GitHub release, or touch WordPress.org SVN.

- [ ] **Step 5: Record final identifiers**

```bash
git rev-parse HEAD
git status --short --branch
git ls-remote --heads origin 2.7.3
```

Expected: local `HEAD` and `refs/heads/2.7.3` resolve to the same commit, and the worktree is clean.

