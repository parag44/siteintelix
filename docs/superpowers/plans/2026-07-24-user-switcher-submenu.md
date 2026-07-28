# User Switcher Submenu Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move User Switcher settings and activity logs from All Module Settings to a dedicated conditional SiteIntelix submenu.

**Architecture:** The existing User Switcher settings service will own its submenu registration and page renderer. Existing settings and log partials remain server-rendered and are reused inside the new page shell, while all links and redirects move to `admin.php?page=siteintelix-user-switcher`.

**Tech Stack:** WordPress admin-menu APIs, PHP 7.4, existing SiteIntelix design tokens, WordPress PHPCS, Node structural tests.

---

## File map

- Modify `tests/structural.test.mjs` — define the navigation, routing, removal, and asset-scoping contracts.
- Modify `includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php` — register and render the dedicated submenu page and remove shared-settings rendering.
- Modify `includes/modules/user-switcher/class-siteintelix-user-switcher-module.php` — scope CSS to the dedicated screen.
- Modify `includes/modules/user-switcher/class-siteintelix-user-switcher-logger.php` — redirect log actions to the dedicated page.
- Modify `includes/modules/user-switcher/views/settings.php` — route Activity Log navigation to the dedicated page.
- Modify `includes/modules/user-switcher/views/logs.php` — route Settings, filters, and pagination through the dedicated page.
- Modify `admin/views/modules-page.php` — link the User Switcher module card directly to its submenu.
- Modify `readme.txt` and `docs/user-switcher.md` — document the dedicated submenu.
- Rebuild `../siteintelix.zip` — produce a correct installable archive.

### Task 1: Add the failing navigation contract

- [ ] Update the User Switcher structural test to require:

```js
assert.match(settings, /add_submenu_page\([\s\S]*siteintelix-user-switcher/);
assert.match(settings, /render_page/);
assert.doesNotMatch(settings, /siteintelix_render_module_settings_sections/);
assert.match(modulesPage, /page['"]?\s*=>\s*['"]siteintelix-user-switcher/);
assert.match(logger, /'page'\s*=>\s*'siteintelix-user-switcher'/);
assert.match(settingsView + logsView, /siteintelix-user-switcher/);
```

- [ ] Run:

```bash
node --test --test-name-pattern='User Switcher' tests/structural.test.mjs
```

Expected: failure because the dedicated submenu and routes do not exist yet.

### Task 2: Register and render the submenu page

- [ ] In `SITEINTELIX_User_Switcher_Settings::init()`, replace the shared settings-render hook with:

```php
add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 25 );
```

- [ ] Add `register_submenu()` using:

```php
add_submenu_page(
	'siteintelix',
	__( 'User Switcher', 'siteintelix' ),
	__( 'User Switcher', 'siteintelix' ),
	'manage_options',
	'siteintelix-user-switcher',
	array( __CLASS__, 'render_page' )
);
```

- [ ] Add `render_page()` that:
  - checks `manage_options`;
  - allow-lists `view=settings|logs`;
  - loads the current settings and registered roles;
  - renders the standard SiteIntelix page header and a two-link Settings/Activity Log navigation;
  - requires `views/settings.php` or `views/logs.php`.

- [ ] Remove `render_section()` and its shared-settings panel wrapper.

- [ ] Run PHP syntax check and the focused structural test. Expected: submenu assertions pass; route assertions may still fail.

### Task 3: Move every User Switcher route

- [ ] Change the settings-save redirect to:

```php
add_query_arg(
	array(
		'page'                       => 'siteintelix-user-switcher',
		'view'                       => 'settings',
		'siteintelix_settings_saved' => '1',
	),
	admin_url( 'admin.php' )
);
```

- [ ] Change Logger `redirect_to_logs()` to use:

```php
array(
	'page' => 'siteintelix-user-switcher',
	'view' => 'logs',
)
```

- [ ] Update the settings partial’s Activity Log URL, the logs partial’s Settings URL, and the log filter hidden fields to the same page slug and `view` parameter.

- [ ] Preserve search/status/date query parameters in pagination through the current request URL.

- [ ] Change the module-card settings URL in `admin/views/modules-page.php` to `admin.php?page=siteintelix-user-switcher`.

- [ ] Run the focused structural test. Expected: pass.

### Task 4: Scope styling and remove the old tab

- [ ] In `SITEINTELIX_User_Switcher_Module::enqueue_assets()`, require the exact hook:

```php
if ( 'siteintelix_page_siteintelix-user-switcher' !== $hook_suffix ) {
	return;
}
```

- [ ] Enqueue the existing shared settings stylesheet and User Switcher stylesheet only on that hook.

- [ ] Confirm User Switcher no longer registers `siteintelix_render_module_settings_sections`; this removes it from All Module Settings without changing other modules.

- [ ] Run WordPress PHPCS on the changed production files. Expected: zero errors.

### Task 5: Documentation, regression verification, and package

- [ ] Update `readme.txt` and `docs/user-switcher.md` so navigation reads **SiteIntelix → User Switcher**, with Settings and Activity Log views.

- [ ] Run:

```bash
node --test tests/structural.test.mjs
php tests/user-switcher-permissions.php
find . -type f -name '*.php' -not -path '*/retired/*' -print0 | xargs -0 -n1 php -l
php ../plugin-check/vendor/squizlabs/php_codesniffer/bin/phpcs --standard=WordPress includes/modules/user-switcher admin/views/modules-page.php
```

Expected: all structural and permission tests pass, all PHP files lint, and PHPCS reports no errors.

- [ ] Rebuild `wp-content/plugins/siteintelix.zip` with top-level `siteintelix/`, include `docs/user-switcher.md`, and exclude tests, development plans, Git metadata, and temporary files.

- [ ] Run `unzip -t siteintelix.zip`, confirm the first entry is `siteintelix/`, and calculate SHA-256.

- [ ] Do not commit or push to Git/SVN.
