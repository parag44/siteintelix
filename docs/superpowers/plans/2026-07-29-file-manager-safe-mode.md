# File Manager Safe Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a disabled-by-default, Safe Mode–only File Manager in SiteIntelix 2.7.3 with secure browsing, preview, download, approved text editing, upload, create, rename, backups, trash, restore, settings, and audit logging.

**Architecture:** An enabled-only module bootstrap coordinates focused settings, security, filesystem, storage, editor, upload, audit, admin, and request-handler classes. Every operation-specific handler independently checks capability, nonce, canonical path, allowed root, protected path, Safe Mode policy, and current file state before calling a service.

**Tech Stack:** PHP 7.4+, WordPress 5.8+ admin APIs, WP AJAX/admin-post, WP_Filesystem where suitable, safe native streaming/atomic filesystem primitives where required, WordPress CodeMirror, vanilla JavaScript, existing SiteIntelix CSS tokens, dependency-free PHP and Node tests.

---

## File map

### Existing files to modify

- `siteintelix.php` — enabled-only require/boot and module management loading.
- `includes/class-siteintelix-modules.php` — File Manager registry record.
- `admin/views/modules-page.php` — trusted registry SVG rendering and File Manager Open/Settings links.
- `admin/views/settings-page.php` — no direct File Manager markup; existing module settings action remains the extension point.
- `tests/structural.test.mjs` — module wiring, endpoint, direct-access, process-execution, asset-scope, and DOM-safety invariants.
- `tests/admin-interactions.test.mjs` — File Manager navigation, search, modal, and unsaved-change invariants.
- `uninstall.php` — option cleanup and strictly validated opt-in owned-storage cleanup.
- `readme.txt` — v2.7.3 feature, security, FAQ, and changelog documentation.

### New module files

- `includes/modules/file-manager/class-siteintelix-file-manager-module.php` — module bootstrap and internal requires.
- `includes/modules/file-manager/class-siteintelix-file-manager-settings.php` — defaults, sanitization, settings form, retention options.
- `includes/modules/file-manager/class-siteintelix-file-manager-security.php` — capability, canonicalization, allowed roots, protected paths, operation authorization.
- `includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php` — bounded listing, metadata, previews, details, safe streaming helpers.
- `includes/modules/file-manager/class-siteintelix-file-manager-storage.php` — owned directory initialization, safe JSON metadata, bounded owned-tree deletion.
- `includes/modules/file-manager/class-siteintelix-file-manager-backups.php` — backup creation, listing, retention, restore.
- `includes/modules/file-manager/class-siteintelix-file-manager-trash.php` — trash, listing, restore, permanent deletion, retention.
- `includes/modules/file-manager/class-siteintelix-file-manager-editor.php` — stale checks and atomic text replacement.
- `includes/modules/file-manager/class-siteintelix-file-manager-upload.php` — upload extension/MIME/size/name/collision validation.
- `includes/modules/file-manager/class-siteintelix-file-manager-redactor.php` — sensitive constant redaction.
- `includes/modules/file-manager/class-siteintelix-file-manager-audit.php` — bounded append-only JSON-lines audit.
- `includes/modules/file-manager/class-siteintelix-file-manager-ajax.php` — operation-specific authenticated AJAX handlers.
- `includes/modules/file-manager/class-siteintelix-file-manager-admin.php` — menu, page, settings hooks, CodeMirror and screen-only assets.
- `includes/modules/file-manager/views/file-manager.php` — page header, tabs, and Browser shell.
- `includes/modules/file-manager/views/settings.php` — File Manager settings panel.
- `includes/modules/file-manager/views/partials/toolbar.php` — navigation/action toolbar and breadcrumbs.
- `includes/modules/file-manager/views/partials/file-table.php` — accessible table/loading/empty/error containers.
- `includes/modules/file-manager/views/partials/folder-tree.php` — responsive tree panel.
- `includes/modules/file-manager/views/partials/details-panel.php` — metadata/preview panel.
- `includes/modules/file-manager/views/partials/editor.php` — CodeMirror editor panel.
- `includes/modules/file-manager/views/partials/modals.php` — accessible create/upload/rename/trash/restore dialogs.
- `includes/modules/file-manager/assets/file-manager.css` — module-only responsive styles using shared tokens.
- `includes/modules/file-manager/assets/file-manager.js` — state, API client, safe rendering, navigation, editor, upload, and dialogs.
- `docs/file-manager.md` — user/developer/security/hooks/retention documentation.

### New tests

- `tests/file-manager-security.php` — normalization, traversal, roots, symlinks, protected paths, constants.
- `tests/file-manager-filesystem.php` — bounded listing, search/sort, metadata, preview, redaction.
- `tests/file-manager-storage.php` — storage ownership, metadata schema, backups, trash, restore, cleanup.
- `tests/file-manager-editor.php` — stale writes, size/type policy, backup and atomic-write failures.
- `tests/file-manager-upload.php` — extension, MIME, double-extension, size, path, name, and collision policy.
- `tests/file-manager-ajax.php` — handler registration and independent authorization.
- `tests/file-manager-ui.test.mjs` — safe DOM APIs and state/controller behavior.

## Task 1: Register and conditionally boot the module

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `admin/views/modules-page.php`
- Modify: `siteintelix.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-module.php`

- [ ] **Step 1: Write the failing registry and bootstrap test**

Add a structural test that asserts:

```js
test('File Manager is disabled by default and loads only when enabled', async () => {
	const [main, registry, cards] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
	]);
	assert.match(registry, /'file_manager'\s*=>\s*array\(/);
	assert.match(registry, /'slug'\s*=>\s*'file-manager'/);
	assert.match(registry, /Safely browse, inspect, edit, upload, download, and manage files inside your WordPress installation\./);
	assert.match(registry, /'default'\s*=>\s*false/);
	assert.match(main, /SITEINTELIX_Modules::is_enabled\(\s*'file_manager'\s*\)[\s\S]*class-siteintelix-file-manager-module\.php/);
	assert.match(main, /SITEINTELIX_File_Manager_Module::init\(\)/);
	assert.match(cards, /'file_manager'\s*=>\s*admin_url\(\s*'admin\.php\?page=siteintelix-file-manager'/);
	assert.match(cards, /siteintelix-file-manager-settings/);
});
```

- [ ] **Step 2: Run the test and confirm RED**

Run:

```bash
node --test tests/structural.test.mjs
```

Expected: FAIL because `file_manager` is absent from the registry and bootstrap.

- [ ] **Step 3: Add the registry definition and trusted SVG**

Add a `file_manager` registry entry with:

```php
'file_manager' => array(
	'id'          => 'file_manager',
	'slug'        => 'file-manager',
	'title'       => ( did_action( 'init' ) ? __( 'File Manager', 'siteintelix' ) : 'File Manager' ),
	'menu_title'  => ( did_action( 'init' ) ? __( 'File Manager', 'siteintelix' ) : 'File Manager' ),
	'description' => ( did_action( 'init' ) ? __( 'Safely browse, inspect, edit, upload, download, and manage files inside your WordPress installation.', 'siteintelix' ) : 'Safely browse, inspect, edit, upload, download, and manage files inside your WordPress installation.' ),
	'icon_svg'    => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5Zm2 3v9h14V8H5Z"/></svg>',
	'color'       => 'blue',
	'status'      => 'core',
	'available'   => true,
	'default'     => false,
	'settings'    => array(
		'title'       => ( did_action( 'init' ) ? __( 'File Manager Settings', 'siteintelix' ) : 'File Manager Settings' ),
		'description' => ( did_action( 'init' ) ? __( 'Configure Safe Mode file access, editing, uploads, backups, trash, and auditing.', 'siteintelix' ) : 'Configure Safe Mode file access, editing, uploads, backups, trash, and auditing.' ),
		'action'      => 'siteintelix_save_file_manager_settings',
	),
),
```

Change only the module-card/settings-tab icon branches to render the fixed registry `icon_svg` through `wp_kses()` with an explicit `svg`, `path`, `viewBox`, `fill`, `aria-hidden`, and `focusable` allowlist. Existing module Dashicons remain unchanged.

- [ ] **Step 4: Add enabled-only loading and boot**

Inside `siteintelix_should_load_admin_modules()` branches, require the module only when enabled:

```php
if ( SITEINTELIX_Modules::is_enabled( 'file_manager' ) ) {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/file-manager/class-siteintelix-file-manager-module.php';
}
```

Boot it from `siteintelix_boot_enabled_modules()`:

```php
if ( SITEINTELIX_Modules::is_enabled( 'file_manager' ) && class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
	SITEINTELIX_File_Manager_Module::init();
}
```

Add the management-loader map record for activation-time storage initialization.

Add the activation branch:

```php
if ( 'file_manager' === $module_id ) {
	siteintelix_load_module_class_for_management( $module_id );
	if ( class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
		return SITEINTELIX_File_Manager_Module::activate();
	}
}
```

- [ ] **Step 5: Create the module bootstrap**

The bootstrap directly includes every focused class and exposes:

```php
class SITEINTELIX_File_Manager_Module {
	public static function init() {
		SITEINTELIX_File_Manager_Admin::init();
		SITEINTELIX_File_Manager_Ajax::init();
		SITEINTELIX_File_Manager_Backups::init();
		SITEINTELIX_File_Manager_Trash::init();
	}

	public static function activate() {
		SITEINTELIX_File_Manager_Settings::add_defaults();
		return SITEINTELIX_File_Manager_Storage::ensure_directories();
	}
}
```

Every PHP file begins with the existing `ABSPATH` direct-access guard.

- [ ] **Step 6: Run tests and commit**

Run:

```bash
node --test tests/structural.test.mjs
php -l includes/modules/file-manager/class-siteintelix-file-manager-module.php
```

Expected: PASS.

Commit:

```bash
git add siteintelix.php includes/class-siteintelix-modules.php admin/views/modules-page.php includes/modules/file-manager/class-siteintelix-file-manager-module.php tests/structural.test.mjs
git commit -m "feat: register safe file manager module"
```

## Task 2: Implement settings, capability, roots, and protected paths

**Files:**
- Create: `tests/file-manager-security.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-settings.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-security.php`

- [ ] **Step 1: Write failing security tests**

Build the same lightweight WordPress stubs used by existing PHP scripts, define a temporary `ABSPATH`, `WP_CONTENT_DIR`, `WP_PLUGIN_DIR`, and `WPMU_PLUGIN_DIR`, then assert:

```php
$security = new SITEINTELIX_File_Manager_Security();
siteintelix_test_assert( ! is_wp_error( $security->authorize_path( 'wp-content/uploads/photo.jpg', 'read' ) ), 'nested content path is readable' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '../wp-config.php', 'read' ) ), 'plain traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '..%252fwp-config.php', 'read' ) ), 'double-encoded traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( "wp-content/\0x", 'read' ) ), 'null byte is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'php://filter/resource=index.php', 'read' ) ), 'stream wrappers are blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-admin', 'write' ) ), 'core write is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/plugins/siteintelix/siteintelix.php', 'write' ) ), 'SiteIntelix is immutable' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/themes/active/style.css', 'write' ) ), 'active theme is immutable' );
```

Create a symlink from the fixture root to a temporary external directory and assert that reads are blocked. Assert `/site-old` does not match `/site`.

Stub the current user and multisite functions, then assert an administrator is permitted, editors and subscribers are blocked, a multisite site administrator is blocked, and a network administrator is permitted. Define `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` in isolated test processes and assert their corresponding operations are blocked while reads remain permitted.

- [ ] **Step 2: Run and confirm RED**

Run:

```bash
php tests/file-manager-security.php
```

Expected: FAIL because the security/settings classes do not exist.

- [ ] **Step 3: Implement settings defaults and sanitization**

Use option `siteintelix_file_manager_settings`. Defaults must include numeric byte limits, editable/upload extension arrays, enabled built-in roots, hidden files off, absolute path display off, overwrite off, audit on, and owned-data removal off.

Expose:

```php
public static function defaults();
public static function get();
public static function sanitize( $input );
public static function add_defaults();
public static function save( $input );
```

Clamp preview/edit/upload and retention/storage values to documented minimums and maximums. Canonicalize custom roots through the security class and discard any root outside `ABSPATH`.

- [ ] **Step 4: Implement the security contract**

Expose:

```php
public static function capability();
public static function current_user_can_manage();
public function authorize_path( $path, $operation, $must_exist = true );
public function resolve_destination( $parent, $name, $operation );
public function allowed_roots();
public function protected_paths();
public function relative_path( $absolute );
public function is_path_allowed( $path, $operation );
```

Use a fixed two-pass `rawurldecode()` check, normalize `\` to `/`, reject control characters, schemes, drive letters, leading external absolute paths, and `.`/`..` segments. For existing files use `realpath`; for destinations canonicalize the existing parent and accept only `sanitize_file_name()` basenames without separators.

Prefix containment must be:

```php
private function contains( $root, $path ) {
	$root = untrailingslashit( wp_normalize_path( $root ) );
	$path = wp_normalize_path( $path );
	return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
}
```

Check each path component for symlinks before allowing it. Apply filters, then reapply immutable restrictions.

Expose the detected filesystem method in the operation policy. Browsing remains available for every method. Atomic editing requires the direct method because the original must never be truncated; when direct atomic replacement is unavailable, editing is read-only with a translated explanation. Simple owned-directory, upload, rename, trash, and restore operations use an initialized `WP_Filesystem` implementation when it provides safe local canonical paths; otherwise those operations fail closed instead of accepting an unverifiable remote path mapping.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-security.php
node --test tests/structural.test.mjs
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-security.php includes/modules/file-manager/class-siteintelix-file-manager-settings.php includes/modules/file-manager/class-siteintelix-file-manager-security.php
git commit -m "feat: enforce file manager path policy"
```

## Task 3: Build owned storage, redaction, and audit services

**Files:**
- Create: `tests/file-manager-storage.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-storage.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-redactor.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-audit.php`

- [ ] **Step 1: Write failing storage tests**

Assert storage initializes `backups`, `trash`, `meta`, and `audit`, writes fixed protection files, rejects metadata with absolute or traversal paths, never follows symlinks during owned deletion, and redacts:

```php
$source = "define( 'DB_PASSWORD', 'secret' );\ndefine('AUTH_KEY','abc');";
$redacted = SITEINTELIX_File_Manager_Redactor::wp_config( $source );
siteintelix_test_assert( false === strpos( $redacted, 'secret' ), 'database password is redacted' );
siteintelix_test_assert( false === strpos( $redacted, 'abc' ), 'authentication key is redacted' );
siteintelix_test_assert( false !== strpos( $redacted, '********' ), 'redaction marker is present' );
```

Assert audit JSON lines contain only user, timestamp, operation, relative path, result, and error category.

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-storage.php`.

Expected: FAIL because storage services are missing.

- [ ] **Step 3: Implement owned storage**

Use `WP_CONTENT_DIR . '/siteintelix/file-manager'` through `siteintelix_file_manager_storage_path`. Revalidate that the filtered path is inside `WP_CONTENT_DIR/siteintelix` and is not a symlink.

Protection contents are fixed:

```php
"<?php\n// Silence is golden.\ndefined( 'ABSPATH' ) || exit;\n"
"Deny from all\n"
"<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n"
```

Write JSON atomically with `wp_json_encode()`. Read using `json_decode( $json, true, 32 )`; reject invalid schemas.

Initialize WordPress filesystem APIs through `request_filesystem_credentials()`/`WP_Filesystem()` only from an authorized admin flow. Use the resulting filesystem object for owned directory creation and fixed protection files when its path mapping remains canonical under the owned root. Native functions are reserved for exclusive creation, locking, streaming, same-directory atomic replacement, and other semantics WordPress filesystem abstractions do not provide.

Expose a bounded `delete_owned_tree()` that rejects the root itself, rejects symlinks, and deletes only canonical descendants of the requested `backups` or `trash` entry.

- [ ] **Step 4: Implement redaction and audit**

Redaction uses a constant-name allowlist and replaces only the quoted value token. Audit appends one JSON object per line under an exclusive file lock, rotates at the configured byte ceiling, and accepts relative paths only.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-storage.php
php tests/file-manager-security.php
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-storage.php includes/modules/file-manager/class-siteintelix-file-manager-storage.php includes/modules/file-manager/class-siteintelix-file-manager-redactor.php includes/modules/file-manager/class-siteintelix-file-manager-audit.php
git commit -m "feat: add private file manager storage"
```

## Task 4: Implement bounded browsing, preview, details, and download

**Files:**
- Create: `tests/file-manager-filesystem.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php`

- [ ] **Step 1: Write failing filesystem tests**

Create fixture directories with hidden files, directories, text, binary content, and files containing HTML-like names. Assert:

```php
$result = $filesystem->list_directory( 'wp-content/uploads', array(
	'page' => 1, 'per_page' => 2, 'sort' => 'name', 'order' => 'asc', 'search' => '',
) );
siteintelix_test_assert( 2 === count( $result['items'] ), 'listing is paginated' );
siteintelix_test_assert( 'directory' === $result['items'][0]['type'], 'directories sort first' );
siteintelix_test_assert( false === in_array( '.secret', array_column( $result['items'], 'name' ), true ), 'hidden files stay hidden' );
```

Assert text preview is bounded, HTML is returned as plain source data, binary preview returns `previewable => false`, oversized preview reports `too_large`, and hashes appear only when requested.

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-filesystem.php`.

Expected: FAIL because the filesystem class is missing.

- [ ] **Step 3: Implement bounded listing**

Use `FilesystemIterator` without recursion. Apply search while iterating. Maintain a bounded candidate set for the requested page and sort key rather than building recursive metadata. Return:

```php
array(
	'path'        => $security->relative_path( $directory ),
	'breadcrumbs' => $this->breadcrumbs( $directory ),
	'items'       => $items,
	'page'        => $page,
	'per_page'    => $per_page,
	'total'       => $total,
	'total_pages' => max( 1, (int) ceil( $total / $per_page ) ),
)
```

Each item contains only name, relative path, type, size, modified timestamp, permission string, readable, writable, protected, and allowed actions.

- [ ] **Step 4: Implement preview and details**

Read text in bounded chunks up to the filtered preview maximum. Detect binary NUL bytes and validate extension. Return source as a string for the client to assign through `textContent`.

Raster images use the authenticated preview/download handler and never expose a public URL. SVG returns source only. Details defer hashes unless `include_hashes` is true and size is within the filtered hash ceiling.

- [ ] **Step 5: Implement streaming**

Expose `stream_file( $relative )` that reauthorizes, clears all output buffers, sends `Content-Type`, safe `Content-Disposition`, `Content-Length`, `Cache-Control: no-store`, and `X-Content-Type-Options: nosniff`, then loops with 64 KiB `fread()` chunks.

- [ ] **Step 6: Run tests and commit**

Run:

```bash
php tests/file-manager-filesystem.php
php tests/file-manager-security.php
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-filesystem.php includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php
git commit -m "feat: add safe file browsing services"
```

## Task 5: Implement backups and atomic editing

**Files:**
- Create: `tests/file-manager-editor.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-backups.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-editor.php`

- [ ] **Step 1: Write failing editor tests**

Cover successful save, stale mtime, stale hash, oversized file, PHP/view-only extension, protected path, backup failure, temporary-write failure, rename failure, and permission preservation.

The successful assertion uses:

```php
$opened = $editor->open( 'wp-content/uploads/note.txt' );
$saved = $editor->save(
	'wp-content/uploads/note.txt',
	"replacement\n",
	$opened['modified'],
	$opened['sha256']
);
siteintelix_test_assert( ! is_wp_error( $saved ), 'approved text save succeeds' );
siteintelix_test_assert( "replacement\n" === file_get_contents( $fixture . '/wp-content/uploads/note.txt' ), 'replacement is complete' );
siteintelix_test_assert( 1 === count( SITEINTELIX_File_Manager_Backups::for_path( 'wp-content/uploads/note.txt' ) ), 'save creates a backup' );
```

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-editor.php`.

Expected: FAIL because backups/editor classes are missing.

- [ ] **Step 3: Implement backup creation and restore**

Copy an authorized file to a collision-resistant owned name, verify byte count and SHA-256, atomically write schema-validated JSON metadata, and remove incomplete artifacts on failure. Listing reads metadata in bounded pages. Restore reauthorizes the destination, backs up any current destination, then uses the editor's atomic replace primitive.

Retention removes only owned entries after enforcing age, per-file count, and total-size ceilings in a bounded batch.

- [ ] **Step 4: Implement open and atomic save**

`open()` returns content, relative path, extension, modified timestamp, SHA-256, size, and editor MIME mode.

`save()` rechecks policy and stale values, creates a backup, opens a same-directory temporary file with exclusive creation, writes until every byte is complete, flushes, applies original mode, closes, then renames over the target. Inject a filesystem adapter in tests so backup/write/rename failures are deterministic.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-editor.php
php tests/file-manager-storage.php
php tests/file-manager-security.php
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-editor.php includes/modules/file-manager/class-siteintelix-file-manager-backups.php includes/modules/file-manager/class-siteintelix-file-manager-editor.php
git commit -m "feat: add backed up atomic file editing"
```

## Task 6: Implement upload, create, and rename

**Files:**
- Create: `tests/file-manager-upload.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-upload.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php`

- [ ] **Step 1: Write failing operation tests**

Cover valid image upload, PHP, double extension, spoofed MIME, oversize, invalid destination, filename traversal, collision, valid folder creation, PHP file creation, reserved names, duplicate name, valid same-parent rename, overwrite, extension change, and protected source.

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-upload.php`.

Expected: FAIL because upload/write operations are absent.

- [ ] **Step 3: Implement upload validation**

For each `$_FILES` entry:

```php
$name = sanitize_file_name( wp_unslash( $file['name'] ) );
if ( $name !== wp_basename( $name ) || preg_match( '/\.(?:php\d*|phtml|phar|cgi|pl|sh|exe|dll|ini|htaccess)(?:\.|$)/i', $name ) ) {
	return new WP_Error( 'invalid_upload_name', __( 'This filename is not permitted.', 'siteintelix' ) );
}
```

Require `UPLOAD_ERR_OK`, `is_uploaded_file()` in production, configured size, approved single final extension, `wp_check_filetype_and_ext()`, server-inspected MIME, authorized destination, and no collision. Use `wp_handle_sideload()` only with overrides that do not bypass type checks, or stream-copy to an exclusive destination after all checks.

- [ ] **Step 4: Implement create and rename**

Create directories with `wp_mkdir_p()` only after destination authorization. Create files with exclusive mode (`fopen( $path, 'x+b' )`). Allow only editable non-PHP extensions.

Rename requires identical canonical parents, no destination collision, unchanged approved extension, unprotected source/destination, and immediate revalidation.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-upload.php
php tests/file-manager-filesystem.php
php tests/file-manager-security.php
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-upload.php includes/modules/file-manager/class-siteintelix-file-manager-upload.php includes/modules/file-manager/class-siteintelix-file-manager-filesystem.php
git commit -m "feat: add restricted file manager writes"
```

## Task 7: Implement trash, restore, and permanent deletion

**Files:**
- Modify: `tests/file-manager-storage.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-trash.php`

- [ ] **Step 1: Add failing trash tests**

Test file trash, empty directory trash, non-empty directory without confirmation, confirmed non-empty directory, protected target, restore, restore collision, permanent delete, symlink entry, metadata tampering, and attempt to delete the trash root.

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-storage.php`.

Expected: FAIL because trash operations are missing.

- [ ] **Step 3: Implement trash and restore**

Trash reauthorizes, rejects immutable paths, requires `confirmed_non_empty` for non-empty directories, moves to a random owned ID, and writes metadata only after the move. If metadata fails, move the item back or return a recovery-safe error without deleting it.

Restore validates metadata schema, owned source containment, original relative destination, destination nonexistence, and Safe Mode write policy before moving it back.

- [ ] **Step 4: Implement permanent delete and retention**

Permanent deletion requires a separate operation and nonce. It calls `delete_owned_tree()` on one validated trash entry, never follows symlinks, and removes metadata after payload deletion succeeds. Retention uses the same method in bounded batches.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-storage.php
php tests/file-manager-security.php
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-storage.php includes/modules/file-manager/class-siteintelix-file-manager-trash.php
git commit -m "feat: add file manager trash and restore"
```

## Task 8: Register operation-specific handlers

**Files:**
- Create: `tests/file-manager-ajax.php`
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-ajax.php`

- [ ] **Step 1: Write failing handler tests**

Assert registration of separate named actions and no generic endpoint:

```php
$expected = array(
	'siteintelix_fm_list_directory',
	'siteintelix_fm_get_file',
	'siteintelix_fm_get_details',
	'siteintelix_fm_save_file',
	'siteintelix_fm_upload_files',
	'siteintelix_fm_create_file',
	'siteintelix_fm_create_directory',
	'siteintelix_fm_rename_item',
	'siteintelix_fm_trash_item',
	'siteintelix_fm_list_trash',
	'siteintelix_fm_restore_item',
	'siteintelix_fm_permanently_delete_item',
	'siteintelix_fm_list_backups',
	'siteintelix_fm_restore_backup',
);
```

For representative read and write handlers assert the order capability → nonce → input sanitization → service call. Assert error responses contain no fixture absolute path.

- [ ] **Step 2: Run and confirm RED**

Run `php tests/file-manager-ajax.php`.

Expected: FAIL because handlers are absent.

- [ ] **Step 3: Implement shared authorization without a generic operation**

Use a private helper only for common request guards:

```php
private static function authorize( $nonce_action ) {
	if ( ! is_user_logged_in() || ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
		wp_send_json_error( array( 'code' => 'forbidden', 'message' => __( 'You do not have permission to perform this action.', 'siteintelix' ) ), 403 );
	}
	check_ajax_referer( $nonce_action, 'nonce' );
}
```

Each public handler has a fixed nonce action, fixed accepted inputs, service call, audit call, and safe response mapper. Never accept an operation name from the request.

- [ ] **Step 4: Add authenticated streaming and settings handlers**

Register `admin_post_siteintelix_fm_download_file` and `admin_post_siteintelix_save_file_manager_settings`. Both independently check capability and action-specific nonce. Download delegates to `stream_file()`; settings delegates to sanitized settings save and redirects to the File Manager Settings tab.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
php tests/file-manager-ajax.php
node --test tests/structural.test.mjs
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-ajax.php includes/modules/file-manager/class-siteintelix-file-manager-ajax.php
git commit -m "feat: add scoped file manager handlers"
```

## Task 9: Build the admin page, settings, and screen-only assets

**Files:**
- Create: `includes/modules/file-manager/class-siteintelix-file-manager-admin.php`
- Create: `includes/modules/file-manager/views/file-manager.php`
- Create: `includes/modules/file-manager/views/settings.php`
- Create: `includes/modules/file-manager/views/partials/toolbar.php`
- Create: `includes/modules/file-manager/views/partials/file-table.php`
- Create: `includes/modules/file-manager/views/partials/folder-tree.php`
- Create: `includes/modules/file-manager/views/partials/details-panel.php`
- Create: `includes/modules/file-manager/views/partials/editor.php`
- Create: `includes/modules/file-manager/views/partials/modals.php`
- Create: `includes/modules/file-manager/assets/file-manager.css`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Write failing admin integration tests**

Assert the module registers submenu slug `siteintelix-file-manager`, effective capability, settings section action, and enqueues `file-manager.css`/`.js` only when hook suffix is `siteintelix_page_siteintelix-file-manager` or the File Manager settings tab is active.

Assert view files contain `ABSPATH` guards, translated strings, escaped output, accessible tabs, labeled dialogs, live region, loading/empty/error containers, folder-tree and details-panel controls.

- [ ] **Step 2: Run and confirm RED**

Run `node --test tests/structural.test.mjs`.

Expected: FAIL because admin files are absent.

- [ ] **Step 3: Implement admin hooks and page renderer**

Register:

```php
add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 39 );
add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
```

The page renderer checks capability and module state before requiring the view. Asset loading returns unless the exact File Manager hook or active File Manager settings tab is present.

Enqueue CodeMirror only on the Browser tab. Localize endpoint URL, operation-specific nonces, byte limits, starting path, feature flags, download base URL, and translated messages. Add `wp-a11y` as a dependency.

- [ ] **Step 4: Build escaped, accessible views**

Render functional Browser, Backups, Trash, and Settings tabs. Do not display deferred features. The initial server response contains no filesystem listing; JavaScript requests the configured start path after load.

All path/name values use `esc_html()`, `esc_attr()`, or `esc_url()` at output. Dialogs use `role="dialog"`, `aria-modal="true"`, explicit labels/descriptions, cancel controls, and hidden initial state.

- [ ] **Step 5: Build responsive CSS**

Use `--si-*` variables and existing `.si-button`, `.si-table`, `.si-badge`, `.si-toolbar`, and form styles. At widths below 1100px, tree/details become fixed slide-overs. At smaller widths, controls wrap and table scrolls horizontally. Add `:focus-visible` and `prefers-reduced-motion`.

- [ ] **Step 6: Run tests and commit**

Run:

```bash
node --test tests/structural.test.mjs
php -l includes/modules/file-manager/class-siteintelix-file-manager-admin.php
find includes/modules/file-manager/views -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: PASS.

Commit:

```bash
git add includes/modules/file-manager/class-siteintelix-file-manager-admin.php includes/modules/file-manager/views includes/modules/file-manager/assets/file-manager.css tests/structural.test.mjs
git commit -m "feat: add file manager admin interface"
```

## Task 10: Implement the vanilla JavaScript application

**Files:**
- Create: `tests/file-manager-ui.test.mjs`
- Modify: `tests/admin-interactions.test.mjs`
- Create: `includes/modules/file-manager/assets/file-manager.js`

- [ ] **Step 1: Write failing UI tests**

Export pure helpers under CommonJS/test detection or place them on a test-only object:

```js
test('navigation history preserves back and forward paths', () => {
	const history = createHistory('wp-content');
	history.visit('wp-content/plugins');
	history.visit('wp-content/themes');
	assert.equal(history.back(), 'wp-content/plugins');
	assert.equal(history.forward(), 'wp-content/themes');
});

test('rendering uses textContent and never interpolates filenames as HTML', async () => {
	const source = await read('includes/modules/file-manager/assets/file-manager.js');
	assert.doesNotMatch(source, /\.innerHTML\s*=/);
	assert.match(source, /\.textContent\s*=/);
	assert.doesNotMatch(source, /\bconfirm\s*\(/);
});
```

Add assertions for search debounce, sort parameter allowlist, modal focus restoration, Escape behavior, and unsaved editor guard.

- [ ] **Step 2: Run and confirm RED**

Run:

```bash
node --test tests/file-manager-ui.test.mjs tests/admin-interactions.test.mjs
```

Expected: FAIL because the JavaScript app is missing.

- [ ] **Step 3: Implement state and API client**

Keep one state object:

```js
const state = {
	path: data.startPath,
	back: [],
	forward: [],
	page: 1,
	perPage: 50,
	sort: 'name',
	order: 'asc',
	search: '',
	selected: null,
	editorDirty: false,
};
```

`request(action, payload, nonceKey)` posts `FormData` to the fixed localized action, verifies JSON shape, maps stable error codes, and never renders server HTML.

- [ ] **Step 4: Implement safe renderers and navigation**

Create DOM nodes with `document.createElement()`, assign untrusted values with `textContent`, and set only fixed class/attribute values. Implement folder/table/details rendering, bounded breadcrumbs, pagination, sorting, debounced current-directory search, refresh, back, forward, and up.

- [ ] **Step 5: Implement editor, uploads, and dialogs**

Initialize CodeMirror from localized settings. Track changes, intercept Ctrl/Cmd+S, block navigation with an in-app dialog when dirty, and register `beforeunload`.

Upload files individually for progress and aggregate results. Create/rename/trash/restore actions use labeled focus-trapped dialogs. Permanent delete requires the typed entry name. After async changes, refresh and call `wp.a11y.speak()`.

- [ ] **Step 6: Run tests and commit**

Run:

```bash
node --test tests/file-manager-ui.test.mjs tests/admin-interactions.test.mjs tests/structural.test.mjs
```

Expected: PASS.

Commit:

```bash
git add tests/file-manager-ui.test.mjs tests/admin-interactions.test.mjs includes/modules/file-manager/assets/file-manager.js
git commit -m "feat: add accessible file manager interactions"
```

## Task 11: Add uninstall safety, cron retention, and documentation

**Files:**
- Modify: `tests/structural.test.mjs`
- Modify: `uninstall.php`
- Modify: `readme.txt`
- Create: `docs/file-manager.md`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-module.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-backups.php`
- Modify: `includes/modules/file-manager/class-siteintelix-file-manager-trash.php`

- [ ] **Step 1: Write failing lifecycle tests**

Assert uninstall always deletes the settings option but removes storage only when `remove_data_on_uninstall` is true. Assert it validates `WP_CONTENT_DIR/siteintelix/file-manager`, refuses symlinks, and never deletes original paths from metadata.

Assert activation schedules a daily cleanup event without duplication and deactivation clears only the cron hook—not data.

- [ ] **Step 2: Run and confirm RED**

Run `node --test tests/structural.test.mjs`.

Expected: FAIL because lifecycle cleanup is not integrated.

- [ ] **Step 3: Implement cron and opt-in uninstall**

Register `siteintelix_file_manager_cleanup` and call bounded backup/trash retention. On uninstall, read the removal flag before deleting the settings option, require the storage class, canonicalize the fixed owned root, reject links or path mismatch, and delete only owned storage payloads.

- [ ] **Step 4: Write documentation**

Document access boundaries, Safe Mode, PHP view-only behavior, protected paths, editing/backup/atomic save, uploads, downloads, trash, multisite, `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, hooks/filters, audit/privacy, retention, uninstall, and permission troubleshooting.

Update v2.7.3 readme feature list, FAQ, changelog, privacy description, and screenshot list only if a real File Manager screenshot is added.

- [ ] **Step 5: Run tests and commit**

Run:

```bash
node --test tests/structural.test.mjs
php -l uninstall.php
```

Expected: PASS.

Commit:

```bash
git add uninstall.php readme.txt docs/file-manager.md includes/modules/file-manager/class-siteintelix-file-manager-module.php includes/modules/file-manager/class-siteintelix-file-manager-backups.php includes/modules/file-manager/class-siteintelix-file-manager-trash.php tests/structural.test.mjs
git commit -m "docs: document safe file manager lifecycle"
```

## Task 12: Full security review and release verification

**Files:**
- Modify as defects require: `includes/modules/file-manager/**`
- Modify as defects require: `tests/file-manager-*`
- Modify: `docs/file-manager.md`

- [ ] **Step 1: Run every automated test**

Run:

```bash
node --test tests/structural.test.mjs tests/admin-interactions.test.mjs tests/email-log-performance.test.mjs tests/server-diagnostics-ui.test.mjs tests/file-manager-ui.test.mjs
for test_file in tests/*.php; do php "$test_file" || exit 1; done
find includes/modules/file-manager -name '*.php' -print0 | xargs -0 -n1 php -l
php -l siteintelix.php
php -l uninstall.php
```

Expected: every command exits 0 with no PHP warnings or notices.

- [ ] **Step 2: Run configured static checks**

Detect tools rather than installing new dependencies:

```bash
if command -v phpcs >/dev/null 2>&1; then phpcs --standard=WordPress includes/modules/file-manager siteintelix.php admin/views/modules-page.php uninstall.php; fi
if test -f package.json && npm pkg get scripts.lint | grep -vq null; then npm run lint; fi
git diff --check
```

Expected: no violations or whitespace errors.

- [ ] **Step 3: Perform adversarial source review**

Search for forbidden or high-risk constructs:

```bash
rg -n "shell_exec|exec\\s*\\(|system\\s*\\(|passthru|proc_open|unserialize\\s*\\(|eval\\s*\\(|innerHTML\\s*=|confirm\\s*\\(" includes/modules/file-manager
rg -n "\\$_(?:GET|POST|FILES|REQUEST)" includes/modules/file-manager
rg -n "wp_ajax_nopriv|\\.\\./|php://|data://|file://" includes/modules/file-manager
```

Expected: no process execution, unserialize, eval, unsafe DOM writes, unauthenticated handlers, or unrestricted request input. Every superglobal occurrence is paired with nonce, unslash, and strict validation in its handler.

- [ ] **Step 4: Perform manual WordPress QA**

In the Local site:

1. Confirm disabled module code/classes are not loaded.
2. Enable File Manager and confirm card, submenu, and exact-screen assets.
3. Browse allowed roots; verify root is read-only and PHP is view-only.
4. Exercise breadcrumbs, back/forward/up, sorting, search, pagination, empty/loading/error states.
5. Preview supported text and images; verify HTML/SVG/PHP never execute and binary/oversized files do not render.
6. Edit an approved text file; verify backup, stale conflict, atomic success, and dirty-navigation warning.
7. Upload valid image/text files; reject PHP, double-extension, MIME spoof, oversize, collision, and invalid destination.
8. Create file/folder and rename; reject protected paths, PHP, overwrite, extension change, and traversal.
9. Download through the protected endpoint and inspect `nosniff`/disposition headers.
10. Trash and restore files/directories; verify non-empty and permanent-delete confirmations and collision handling.
11. Verify Backups and Trash tabs, restore flows, and retention limits.
12. Test administrator/editor/subscriber and multisite site-admin/network-admin access.
13. Test with `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, hidden-files off/on, non-writable directories, and `WP_DEBUG` off/on.
14. Test at 1280px and narrower with keyboard-only navigation and modal focus.
15. Confirm existing modules and their settings continue to work.

Record results and any environment limitations in `docs/file-manager.md`.

- [ ] **Step 5: Commit review fixes**

After adding a failing regression test for every defect and making it pass:

```bash
git add includes/modules/file-manager tests docs/file-manager.md
git commit -m "security: harden file manager release"
```

- [ ] **Step 6: Request code review**

Invoke `superpowers:requesting-code-review`, address findings through `superpowers:receiving-code-review`, rerun Step 1 and Step 2, then invoke `superpowers:verification-before-completion` before claiming release readiness.
