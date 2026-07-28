# User Switcher Toolbar Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent late SiteIntelix cookie headers and guarantee access to the return control during a validated User Switcher session.

**Architecture:** Distinguish “no cookie” from “invalid cookie” at the shared session-reading boundary so ordinary toolbar checks never expire a nonexistent cookie. Add a late `show_admin_bar` filter that forces visibility only when the existing session manager confirms the current target identity and session token.

**Tech Stack:** WordPress PHP hooks and cookies, dependency-free PHP regression scripts, Node.js structural tests.

---

### Task 1: Reproduce the late-header warning

**Files:**
- Create: `tests/user-switcher-session-manager.php`
- Modify: `includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php`

- [ ] **Step 1: Write the failing test**

Create a dependency-free PHP script that defines the WordPress constants and functions reached by an empty-cookie session read, requires the real session manager, emits output, converts the expected header warning into an exception, and calls `get_active_session()`:

```php
<?php
define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );

function get_current_blog_id() {
	return 1;
}

function is_ssl() {
	return false;
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php';

fwrite( STDOUT, "Session manager headers started.\n" );

set_error_handler(
	static function ( $severity, $message ) {
		if ( E_WARNING === $severity && false !== strpos( $message, 'Cannot modify header information' ) ) {
			throw new RuntimeException( $message );
		}
		return false;
	}
);

try {
	$session = SITEINTELIX_User_Switcher_Session_Manager::get_active_session();
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, "FAIL: {$exception->getMessage()}\n" );
	exit( 1 );
} finally {
	restore_error_handler();
}

if ( false !== $session ) {
	fwrite( STDERR, "FAIL: No cookie should produce no active session.\n" );
	exit( 1 );
}

fwrite( STDOUT, "User Switcher session manager tests passed.\n" );
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
php -d display_errors=1 tests/user-switcher-session-manager.php
```

Expected: exit 1 with `FAIL: Cannot modify header information`, proving the existing no-cookie path calls `setcookie()` after output.

- [ ] **Step 3: Implement the minimal session fix**

Add the guard at the start of `read_session()`:

```php
private static function read_session( $require_current_target ) {
	if ( ! self::has_cookie() ) {
		return false;
	}

	$cookie = self::parse_cookie();
```

Keep the existing `clear_cookie()` call after `parse_cookie()` returns false so malformed cookies are still expired.

- [ ] **Step 4: Run the test and verify GREEN**

Run:

```bash
php -d display_errors=1 tests/user-switcher-session-manager.php
```

Expected: exit 0 with `User Switcher session manager tests passed.` and no PHP warning.

### Task 2: Force the toolbar only for active switches

**Files:**
- Create: `tests/user-switcher-toolbar.php`
- Modify: `includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php`

- [ ] **Step 1: Write the failing toolbar test**

Create a dependency-free PHP script with hook recorders and a controllable session-manager test double:

```php
<?php
define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_test_filters = array();

function add_action() {
	return true;
}

function add_filter( $hook, $callback, $priority = 10 ) {
	global $siteintelix_test_filters;
	$siteintelix_test_filters[ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

class SITEINTELIX_User_Switcher_Session_Manager {
	public static $active_session = false;

	public static function get_active_session() {
		return self::$active_session;
	}
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php';

function siteintelix_toolbar_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

SITEINTELIX_User_Switcher_Toolbar::init();

siteintelix_toolbar_test_assert(
	isset( $siteintelix_test_filters['show_admin_bar'] ),
	'The toolbar must register a show_admin_bar filter.'
);
siteintelix_toolbar_test_assert(
	PHP_INT_MAX === $siteintelix_test_filters['show_admin_bar']['priority'],
	'The visibility filter must run at the latest practical priority.'
);

SITEINTELIX_User_Switcher_Session_Manager::$active_session = false;
siteintelix_toolbar_test_assert(
	false === SITEINTELIX_User_Switcher_Toolbar::force_admin_bar( false ),
	'Ordinary hidden-toolbar preferences must remain hidden.'
);

SITEINTELIX_User_Switcher_Session_Manager::$active_session = array( 'switch_id' => 'active' );
siteintelix_toolbar_test_assert(
	true === SITEINTELIX_User_Switcher_Toolbar::force_admin_bar( false ),
	'An active switch must force the toolbar to show.'
);

fwrite( STDOUT, "User Switcher toolbar tests passed.\n" );
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
php tests/user-switcher-toolbar.php
```

Expected: exit 1 because `show_admin_bar` is not registered or a fatal error because `force_admin_bar()` does not exist.

- [ ] **Step 3: Implement the minimal toolbar filter**

Register the filter in `init()`:

```php
add_filter( 'show_admin_bar', array( __CLASS__, 'force_admin_bar' ), PHP_INT_MAX );
```

Add the public callback:

```php
public static function force_admin_bar( $show ) {
	if ( SITEINTELIX_User_Switcher_Session_Manager::get_active_session() ) {
		return true;
	}

	return (bool) $show;
}
```

- [ ] **Step 4: Run the test and verify GREEN**

Run:

```bash
php tests/user-switcher-toolbar.php
```

Expected: exit 0 with `User Switcher toolbar tests passed.`

### Task 3: Document and verify the complete change

**Files:**
- Modify: `docs/user-switcher.md`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Strengthen structural coverage**

Add assertions in the existing User Switcher structural test:

```js
assert.match(session, /private static function read_session[\s\S]*if\s*\(\s*!\s*self::has_cookie\(\)\s*\)\s*\{\s*return false;/);
assert.match(toolbar, /add_filter\(\s*'show_admin_bar'[\s\S]*PHP_INT_MAX/);
assert.match(toolbar, /public static function force_admin_bar/);
```

- [ ] **Step 2: Run structural coverage**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: all structural tests pass.

- [ ] **Step 3: Update User Switcher documentation**

Add this behavior to the “Switch and return” section:

```markdown
While a switching session is fully validated, SiteIntelix forces the frontend WordPress toolbar to remain visible so LMS settings or the target user’s toolbar preference cannot hide the return control. Toolbar preferences remain unchanged outside the switching session.
```

- [ ] **Step 4: Run all focused User Switcher tests**

Run:

```bash
php -d display_errors=1 tests/user-switcher-session-manager.php
php tests/user-switcher-toolbar.php
php tests/user-switcher-permissions.php
```

Expected: all three scripts exit 0 with their corresponding passed messages and no warnings.

- [ ] **Step 5: Run syntax and project regression checks**

Run:

```bash
php -l includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php
php -l includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php
php -l tests/user-switcher-session-manager.php
php -l tests/user-switcher-toolbar.php
node --test tests/structural.test.mjs
```

Expected: every PHP file reports `No syntax errors detected` and every Node test passes.

### Task 4: Restore before LMS admin restrictions

**Files:**
- Create: `tests/user-switcher-admin-actions.php`
- Modify: `includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php`
- Modify: `tests/structural.test.mjs`
- Modify: `docs/user-switcher.md`

- [ ] **Step 1: Write the failing early-dispatch test**

Create a dependency-free PHP script that records action registration and dispatched hooks:

```php
<?php
define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_test_actions    = array();
$siteintelix_dispatched      = array();
$siteintelix_test_logged_in  = true;

function add_action( $hook, $callback, $priority = 10 ) {
	global $siteintelix_test_actions;
	$siteintelix_test_actions[ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

function do_action( $hook ) {
	global $siteintelix_dispatched;
	$siteintelix_dispatched[] = $hook;
}

function is_user_logged_in() {
	global $siteintelix_test_logged_in;
	return $siteintelix_test_logged_in;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php';

function siteintelix_admin_actions_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

SITEINTELIX_User_Switcher_Admin_Actions::init_recovery();

siteintelix_admin_actions_test_assert(
	isset( $siteintelix_test_actions['admin_init'] )
	&& 0 === $siteintelix_test_actions['admin_init']['priority'],
	'Restore recovery must run before default-priority admin restrictions.'
);

$pagenow = 'index.php';
$_REQUEST['action'] = SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Ordinary wp-admin requests must be ignored.' );

$pagenow = 'admin-post.php';
$_REQUEST['action'] = 'unrelated_action';
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Unrelated admin-post actions must be ignored.' );

$siteintelix_test_logged_in = false;
$_REQUEST['action'] = SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Logged-out requests must not dispatch the authenticated restore action.' );

$siteintelix_test_logged_in = true;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert(
	array( 'admin_post_' . SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION ) === $siteintelix_dispatched,
	'The authenticated SiteIntelix restore request must dispatch early.'
);

fwrite( STDOUT, "User Switcher admin action tests passed.\n" );
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```bash
php tests/user-switcher-admin-actions.php
```

Expected: exit 1 because no `admin_init` recovery callback is registered.

- [ ] **Step 3: Implement the minimal early dispatcher**

Register the callback before the existing action handler:

```php
add_action( 'admin_init', array( __CLASS__, 'maybe_dispatch_restore' ), 0 );
```

Add the dispatcher:

```php
public static function maybe_dispatch_restore() {
	global $pagenow;

	if (
		'admin-post.php' !== $pagenow
		|| ! is_user_logged_in()
		|| ! isset( $_REQUEST['action'] )
		|| ! is_scalar( $_REQUEST['action'] )
		|| self::RESTORE_ACTION !== sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
	) {
		return;
	}

	do_action( 'admin_post_' . self::RESTORE_ACTION );
}
```

The dispatched registered handler retains the existing nonce and session validation and exits after restore or failure.

- [ ] **Step 4: Run the test and verify GREEN**

Run:

```bash
php tests/user-switcher-admin-actions.php
```

Expected: exit 0 with `User Switcher admin action tests passed.`

- [ ] **Step 5: Add structural and documentation coverage**

Add structural assertions for the priority-zero `admin_init` hook and `maybe_dispatch_restore()` method. Update the “Switch and return” documentation to state that restore is processed before LMS wp-admin restrictions.

- [ ] **Step 6: Run the complete User Switcher verification**

Run:

```bash
php -d display_errors=1 tests/user-switcher-session-manager.php
php tests/user-switcher-toolbar.php
php tests/user-switcher-admin-actions.php
php tests/user-switcher-permissions.php
php -l includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php
php -l tests/user-switcher-admin-actions.php
node --test tests/structural.test.mjs
```

Expected: every PHP script exits 0, syntax checks report no errors, and all structural tests pass.

### Version-control note

This WordPress site and plugin directory are not Git working trees. Commit steps are intentionally omitted; no safe commit target exists.
