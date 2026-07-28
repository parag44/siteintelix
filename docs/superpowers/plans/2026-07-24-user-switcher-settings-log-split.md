# User Switcher Settings and Activity Log Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Return User Switcher configuration to the shared SiteIntelix Settings page while keeping the dedicated User Switcher submenu as an activity-log-only screen.

**Architecture:** Reconnect the existing User Switcher settings renderer to the shared module-settings hook and route module-card/save actions back to the main settings page. Keep the existing dedicated submenu registration, but simplify its renderer so it includes only the existing server-rendered log view. No switching, session, permission, database, or retention behavior changes.

**Tech Stack:** WordPress PHP APIs, SiteIntelix server-rendered admin UI, existing CSS tokens, Node.js structural tests, PHP CLI, WordPress Coding Standards.

**Repository constraint:** Work directly in the existing plugin directory. Do not create commits, branches, worktrees, Git pushes, or SVN pushes.

---

## File Map

- Modify `tests/structural.test.mjs` — enforce the shared settings tab and log-only submenu contract.
- Modify `includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php` — render settings in the shared page, route settings saves there, and render logs only in the submenu.
- Modify `admin/views/settings-page.php` — include User Switcher in the shared settings-tab registry.
- Modify `admin/views/modules-page.php` — route the module card's Settings button to the shared User Switcher tab.
- Modify `includes/modules/user-switcher/assets/user-switcher.css` — remove obsolete dedicated-page tab styling while retaining the log layout.
- Modify `docs/user-switcher.md` — document the final navigation.
- Modify `readme.txt` — align the screenshot description and 2.7.2 changelog.
- Rebuild `wp-content/plugins/siteintelix.zip` — produce the final installable archive.

### Task 1: Lock the Navigation Contract with a Failing Structural Test

**Files:**
- Modify: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Replace the dedicated-settings assertions with the split contract**

In the existing User Switcher structural test, require all of these strings or patterns:

```js
assert.match(
	userSwitcherSettings,
	/add_action\(\s*'siteintelix_render_module_settings_sections',\s*array\(\s*__CLASS__,\s*'render_section'\s*\)/s
);
assert.match(
	userSwitcherSettings,
	/'page'\s*=>\s*'siteintelix-settings'[\s\S]*'tab'\s*=>\s*'user_switcher'/s
);
assert.match(
	userSwitcherSettings,
	/add_submenu_page\([\s\S]*'siteintelix-user-switcher'[\s\S]*'render_page'/s
);
assert.doesNotMatch(
	userSwitcherSettings,
	/sitx-user-switcher-tabs|add_query_arg\(\s*'view',\s*'settings'/s
);
assert.match(
	userSwitcherSettings,
	/require SITEINTELIX_PLUGIN_DIR \. 'includes\/modules\/user-switcher\/views\/logs\.php'/
);
assert.doesNotMatch(
	settingsView,
	/if\s*\(\s*'user_switcher'\s*===\s*\$siteintelix_module_id\s*\)\s*\{\s*continue;/s
);
assert.match(
	modulesView,
	/'user_switcher'\s*=>\s*'siteintelix-user-switcher-settings'/
);
assert.match(
	userSwitcherSettings,
	/id="siteintelix-user-switcher-settings"/
);
```

Keep the existing assertions that the submenu is conditionally registered, its exact screen hook scopes assets, and log mutations redirect to `siteintelix-user-switcher`.

- [ ] **Step 2: Run the focused test and verify red**

Run:

```bash
node --test --test-name-pattern='User Switcher' wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL because `render_section` is not registered, the shared settings page skips User Switcher, and the submenu still contains two tabs.

### Task 2: Restore User Switcher to the Shared Settings Page

**Files:**
- Modify: `wp-content/plugins/siteintelix/includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php`
- Modify: `wp-content/plugins/siteintelix/admin/views/settings-page.php`
- Modify: `wp-content/plugins/siteintelix/admin/views/modules-page.php`
- Modify: `wp-content/plugins/siteintelix/includes/modules/user-switcher/views/settings.php`

- [ ] **Step 1: Register the existing settings section**

In `SITEINTELIX_User_Switcher_Settings::init()`, keep the submenu hook and add:

```php
add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_section' ), 10, 2 );
```

- [ ] **Step 2: Add the shared-page settings renderer**

Add this public method to `SITEINTELIX_User_Switcher_Settings`:

```php
/**
 * Render User Switcher settings inside the shared settings page.
 *
 * @param string[] $enabled_modules Enabled module IDs.
 * @param string   $active_tab      Active shared settings tab.
 * @return void
 */
public static function render_section( $enabled_modules, $active_tab = '' ) {
	if ( ! in_array( 'user_switcher', (array) $enabled_modules, true ) ) {
		return;
	}

	$settings = self::get_settings();
	$roles    = self::get_registered_roles();
	?>
	<section class="sitx-settings-panel-tab <?php echo 'user_switcher' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-user-switcher-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-user_switcher" data-siteintelix-settings-panel="user_switcher" <?php echo 'user_switcher' === $active_tab ? '' : 'hidden'; ?>>
		<?php require SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/views/settings.php'; ?>
	</section>
	<?php
}
```

- [ ] **Step 3: Redirect saved settings back to the shared tab**

Change `handle_save()` to redirect using:

```php
add_query_arg(
	array(
		'page'                       => 'siteintelix-settings',
		'tab'                        => 'user_switcher',
		'siteintelix_settings_saved' => '1',
	),
	admin_url( 'admin.php' )
) . '#siteintelix-user-switcher-settings'
```

- [ ] **Step 4: Re-enable the shared settings tab**

Delete this block from `admin/views/settings-page.php`:

```php
if ( 'user_switcher' === $siteintelix_module_id ) {
	continue;
}
```

The existing module registry metadata will then populate the User Switcher tab without duplicating configuration.

- [ ] **Step 5: Route the module card to the shared settings tab**

Set:

```php
'user_switcher' => 'siteintelix-user-switcher-settings',
```

Remove the User Switcher URL special case and use the existing shared settings URL for every entry:

```php
$module_settings_url = add_query_arg(
	array(
		'page' => 'siteintelix-settings',
		'tab'  => $module_id,
	),
	admin_url( 'admin.php' )
) . '#' . $settings_links[ $module_id ];
```

- [ ] **Step 6: Remove the standalone heading from the reusable settings partial**

Keep the form and success notice in `views/settings.php`, but remove the `Access Settings` heading block so the partial sits naturally inside the shared settings panel and does not repeat the page context.

- [ ] **Step 7: Run the focused test**

Run:

```bash
node --test --test-name-pattern='User Switcher' wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: the shared settings assertions pass; the test may remain red until the submenu renderer is simplified in Task 3.

### Task 3: Make the Dedicated Submenu Activity-Log Only

**Files:**
- Modify: `wp-content/plugins/siteintelix/includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php`
- Modify: `wp-content/plugins/siteintelix/includes/modules/user-switcher/assets/user-switcher.css`

- [ ] **Step 1: Simplify `render_page()`**

Keep the `manage_options` capability check and the standard page header. Remove the `view` query parsing, Settings/Activity Log navigation, settings variables, and conditional partial loading. The page body must render:

```php
<div class="siteintelix-container">
	<div class="sitx-settings-shell si-card sitx-user-switcher-shell">
		<?php require SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/views/logs.php'; ?>
	</div>
</div>
```

Use this page description:

```php
__( 'Review temporary account access and how each switching session ended.', 'siteintelix' )
```

Keep the **All Users** header action.

- [ ] **Step 2: Remove obsolete tab CSS**

Delete selectors for:

```css
.sitx-user-switcher-tabs
.sitx-user-switcher-tabs .sitx-settings-tab
.sitx-user-switcher-tabs .sitx-settings-tab:hover
.sitx-user-switcher-tabs .sitx-settings-tab.is-active
.sitx-user-switcher-tabs .dashicons
```

Retain `.sitx-user-switcher-shell`, filters, table, status badges, pagination, destructive-action spacing, and responsive rules.

- [ ] **Step 3: Run the focused test and verify green**

Run:

```bash
node --test --test-name-pattern='User Switcher' wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: PASS.

### Task 4: Align Documentation

**Files:**
- Modify: `wp-content/plugins/siteintelix/docs/user-switcher.md`
- Modify: `wp-content/plugins/siteintelix/readme.txt`

- [ ] **Step 1: Update module documentation**

State explicitly:

```markdown
Enable **User Switcher** on **SiteIntelix → Modules**. Configure it under
**SiteIntelix → Settings → User Switcher**. Review switching records under
**SiteIntelix → User Switcher**.
```

Remove statements that the submenu contains Settings and Activity Log views.

- [ ] **Step 2: Update public readme wording**

Describe the relevant screens as:

```text
6. **Settings** — clean module settings for Debug Log, SMTP, Email Log, User Switcher, and Maintenance Mode.
7. **User Switcher** — a dedicated activity log for secure temporary account access.
```

Change the 2.7.2 changelog line to say the module includes shared settings plus a dedicated activity-log submenu.

### Task 5: Run Final Verification and Rebuild the Installable ZIP

**Files:**
- Rebuild: `wp-content/plugins/siteintelix.zip`

- [ ] **Step 1: Run the full structural suite**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: 33 tests pass with zero failures.

- [ ] **Step 2: Run focused PHP behavior checks**

Run:

```bash
php wp-content/plugins/siteintelix/tests/user-switcher-permissions.php
php wp-content/plugins/siteintelix/tests/debug-log-parser.php
php wp-content/plugins/siteintelix/tests/editor-links.php
```

Expected: all commands exit `0`; the User Switcher permission script reports success.

- [ ] **Step 3: Lint all production PHP**

Run:

```bash
find wp-content/plugins/siteintelix -path '*/retired' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: every file reports no syntax errors.

- [ ] **Step 4: Run WordPress Coding Standards on changed production files**

Run:

```bash
php wp-content/plugins/plugin-check/vendor/squizlabs/php_codesniffer/bin/phpcs \
	--standard=WordPress \
	wp-content/plugins/siteintelix/includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php \
	wp-content/plugins/siteintelix/includes/modules/user-switcher/assets/user-switcher.css \
	wp-content/plugins/siteintelix/includes/modules/user-switcher/views/settings.php \
	wp-content/plugins/siteintelix/admin/views/settings-page.php \
	wp-content/plugins/siteintelix/admin/views/modules-page.php
```

If PHPCS rejects the CSS path because the configured standard has no CSS tokenizer, rerun the same command without the CSS file. Expected: zero PHP errors or warnings.

- [ ] **Step 5: Rebuild the ZIP without development-only files**

Remove the existing archive, then from `wp-content/plugins` run:

```bash
zip -qr siteintelix.zip siteintelix \
	-x 'siteintelix/tests/*' \
	'siteintelix/docs/superpowers/*' \
	'siteintelix/.superpowers/*' \
	'siteintelix/retired/*' \
	'siteintelix/includes/modules/error-ui/*' \
	'siteintelix/.git/*' \
	'siteintelix/.DS_Store' \
	'siteintelix/.distignore'
```

- [ ] **Step 6: Verify archive integrity and checksum**

Run:

```bash
unzip -t wp-content/plugins/siteintelix.zip
shasum -a 256 wp-content/plugins/siteintelix.zip
```

Expected: no compressed-data errors and one SHA-256 checksum.

- [ ] **Step 7: Report without source-control actions**

Provide the clickable ZIP path, checksum, verification results, and any Local database/runtime limitation. Confirm explicitly that no Git or SVN commit/push occurred.
