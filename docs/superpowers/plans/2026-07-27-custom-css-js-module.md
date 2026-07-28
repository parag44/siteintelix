# Custom CSS & JS Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a disabled-by-default SiteIntelix module that securely manages and executes multiple CSS and JavaScript entries using inline output or generated upload files.

**Architecture:** A conditionally loaded module coordinates a prepared-query repository, upload file manager, ordered runner, admin controller, and compact views. Data lives in a dedicated per-site table; generated files are optional caches with inline fallback.

**Tech Stack:** WordPress 5.8+, PHP 7.4+, `$wpdb`, `dbDelta()`, Filesystem API, CodeMirror via `wp_enqueue_code_editor()`, SiteIntelix UI tokens, dependency-free PHP/Node tests.

---

### Task 1: Registry, loader, and schema

**Files:**
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `siteintelix.php`
- Modify: `admin/views/modules-page.php`
- Create: `includes/modules/custom-code/class-siteintelix-custom-code-module.php`
- Create: `includes/modules/custom-code/class-siteintelix-custom-code-repository.php`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing structural assertions for module ID `custom_code`, conditional require/init, dashboard Open link, and required class files.
- [ ] Run `node --test tests/structural.test.mjs`; expect the Custom CSS & JS assertions to fail.
- [ ] Register the module with `available => true`, `default => false`, `dashicons-editor-code`, and a concise description.
- [ ] Add conditional runtime loading outside the admin-only loader because enabled entries execute on frontend requests.
- [ ] Implement `SITEINTELIX_Custom_Code_Repository::install_schema()` using:

```php
$table = $wpdb->prefix . 'siteintelix_custom_code';
$sql = "CREATE TABLE {$table} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	title varchar(191) NOT NULL,
	code longtext NOT NULL,
	code_type varchar(12) NOT NULL,
	scope varchar(12) NOT NULL,
	location varchar(10) NOT NULL,
	loading_method varchar(10) NOT NULL,
	priority int(11) NOT NULL DEFAULT 10,
	status varchar(10) NOT NULL DEFAULT 'disabled',
	description text NOT NULL,
	generated_file varchar(255) NOT NULL DEFAULT '',
	created_by bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY status_type (status, code_type),
	KEY scope_location (scope, location),
	KEY priority (priority),
	KEY updated_at (updated_at)
) {$charset_collate};";
```

- [ ] Call schema installation on module activation and on enabled admin requests when the schema version is stale.
- [ ] Run structural tests; expect Task 1 assertions to pass.

### Task 2: Repository behavior

**Files:**
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-repository.php`
- Create: `tests/custom-code-repository.php`

- [ ] Write a dependency-free repository test with a fake `$wpdb` covering table naming, allowlisted normalization, bounded priority, prepared search/filter fragments, and execution ordering.
- [ ] Run `php tests/custom-code-repository.php`; expect failure because repository methods are absent.
- [ ] Implement:

```php
public static function normalize_entry( $input, $existing = array() );
public static function get( $id );
public static function insert( $entry );
public static function update( $id, $entry );
public static function delete( $id );
public static function duplicate( $id, $user_id );
public static function set_status( $ids, $status );
public static function list_entries( $args );
public static function count_entries( $args );
public static function get_enabled_for( $scope, $location, $type );
```

- [ ] Preserve `code` with `wp_unslash()` only; sanitize all metadata through explicit allowlists.
- [ ] Ensure all raw SQL values use `$wpdb->prepare()` and all CRUD calls include explicit formats.
- [ ] Run the repository test; expect all assertions to pass.

### Task 3: Tag handling and file cache

**Files:**
- Create: `includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php`
- Create: `tests/custom-code-file-manager.php`

- [ ] Write failing tests for outer `<style>`/`<script>` removal, deterministic filenames, extension allowlisting, and file-write failure.
- [ ] Run `php tests/custom-code-file-manager.php`; expect failure because the class does not exist.
- [ ] Implement:

```php
public static function strip_outer_tags( $code, $type );
public static function relative_filename( $entry_id, $type, $blog_id );
public static function write( $entry );
public static function delete( $relative_file );
public static function url( $relative_file );
```

- [ ] Use `wp_upload_dir()`, `wp_mkdir_p()`, `WP_Filesystem()` where practical, atomic temporary writes when available, and `wp_delete_file()` for deletion.
- [ ] Return `WP_Error` on write failure without changing the entry’s requested loading method.
- [ ] Run file-manager tests; expect all assertions to pass.

### Task 4: Ordered execution runner

**Files:**
- Create: `includes/modules/custom-code/class-siteintelix-custom-code-runner.php`
- Create: `tests/custom-code-runner.php`

- [ ] Write failing tests for context/scope matching, priority order, unique handles/IDs, external fallback, and header/footer hook registration.
- [ ] Run `php tests/custom-code-runner.php`; expect failure.
- [ ] Implement a runner that registers `wp_head`, `wp_footer`, `admin_head`, and `admin_footer`, retrieves matching entries, and emits:

```php
<style id="siteintelix-custom-css-<?php echo esc_attr( $id ); ?>">
<script id="siteintelix-custom-js-<?php echo esc_attr( $id ); ?>">
```

- [ ] Enqueue valid external assets with deterministic handles and updated timestamps; use inline output when the generated file is missing or unwritable.
- [ ] Never call JavaScript `eval()` and never run disabled entries.
- [ ] Run runner tests; expect all assertions to pass.

### Task 5: Admin list and actions

**Files:**
- Create: `includes/modules/custom-code/class-siteintelix-custom-code-admin.php`
- Create: `includes/modules/custom-code/views/list.php`
- Create: `tests/custom-code-admin.php`

- [ ] Write failing action-policy tests for capability checks, nonces, allowed single/bulk actions, duplicate status, and delete-file cleanup.
- [ ] Run `php tests/custom-code-admin.php`; expect failure.
- [ ] Register `siteintelix-custom-code` and `siteintelix-custom-code-new` submenus with `manage_options`.
- [ ] Implement nonce-protected admin-post handlers for save, toggle, duplicate, delete, and bulk enable/disable/delete.
- [ ] Render a bounded compact table with required columns, filters, search, pagination, row actions, checkboxes, and confirmation attributes using existing `si-*`/`sitx-*` components.
- [ ] Run admin policy tests; expect all assertions to pass.

### Task 6: Editor UI and scoped assets

**Files:**
- Create: `includes/modules/custom-code/views/editor.php`
- Create: `includes/modules/custom-code/assets/custom-code.css`
- Create: `includes/modules/custom-code/assets/custom-code.js`
- Modify: `includes/modules/custom-code/class-siteintelix-custom-code-module.php`
- Modify: `tests/admin-interactions.test.mjs`

- [ ] Add failing structural/UI assertions for every field, warning, Save/Save & Enable actions, sidebar layout, and CodeMirror initialization.
- [ ] Run Node tests; expect the new assertions to fail.
- [ ] Implement the approved editor + sidebar layout and responsive collapse.
- [ ] Call `wp_enqueue_code_editor( array( 'type' => 'text/css' ) )` or `application/javascript` based on the current entry, enqueue only on module screens, and initialize with `wp.codeEditor.initialize`.
- [ ] Provide an accessible textarea fallback and client-side type switching without loading external libraries.
- [ ] Run Node tests; expect all assertions to pass.

### Task 7: Shared uninstall policy and documentation

**Files:**
- Modify: `admin/views/settings-page.php`
- Modify: `siteintelix.php`
- Modify: `uninstall.php`
- Create: `docs/custom-css-js.md`
- Modify: `tests/structural.test.mjs`

- [ ] Add failing assertions for `siteintelix_delete_custom_code_on_uninstall`, default-preserve logic, table cleanup, and upload-file cleanup.
- [ ] Add a nonce-protected SiteIntelix data-retention setting labeled **Delete Custom Code Data on Uninstall**.
- [ ] Preserve rows/files by default; only delete custom-code data during uninstall when the option is enabled, including per-site multisite cleanup.
- [ ] Document hooks, schema, fallback behavior, permissions, and manual checks.
- [ ] Run all custom-code PHP tests, all existing Node tests, PHP syntax checks, and `wp eval` schema/runtime smoke checks.

### Version-control note

The plugin directory is not a Git working tree. Commit steps are omitted because no safe commit target exists.
