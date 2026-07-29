# File Manager Safe Mode Design

**Date:** 2026-07-29
**Release:** SiteIntelix 2.7.3
**Status:** Approved design

## Purpose

Add a disabled-by-default File Manager toolbox module that lets trusted WordPress administrators browse, inspect, download, and safely manage files inside the current WordPress installation without becoming an unrestricted server file manager.

The first release is intentionally limited to Safe Mode. It includes the core safe write workflow—approved text editing, uploads, file and directory creation, rename, backups, trash, and restore—but excludes Advanced Mode, ZIP operations, the Large Files scanner, move, chmod, recursive search, content search, and directory ZIP downloads.

Security and filesystem integrity take priority over feature count.

## Existing architecture

File Manager follows the plugin's established module lifecycle:

- `SITEINTELIX_Modules` registers its card metadata and keeps it disabled by default.
- `siteintelix.php` requires File Manager only for enabled admin, AJAX, or CLI requests.
- `siteintelix_boot_enabled_modules()` initializes the module only when enabled.
- The module owns its submenu, settings integration, screen-specific assets, handlers, services, and views.
- Shared `si-` design tokens, `sitx-` components, `SITEINTELIX_Admin_UI`, capability policy, text domain, and asset version constants remain authoritative.

The registry ID is `file_manager`; the public slug is `file-manager`; the submenu slug is `siteintelix-file-manager`. The module card uses the requested description:

> Safely browse, inspect, edit, upload, download, and manage files inside your WordPress installation.

The card provides Open, Settings, and the existing enable/disable toggle. Its registry metadata supplies a fixed, plugin-owned folder/file SVG. The existing card renderer gains narrowly scoped support for trusted registry SVG icons while retaining the current Dashicon path for every existing module.

## Architecture

The module uses focused classes with one-way dependencies:

1. A module bootstrap requires its internal classes and coordinates initialization.
2. An admin controller registers the submenu, page renderers, settings integration, and screen-scoped CSS and JavaScript.
3. Operation-specific AJAX or `admin-post.php` handlers authorize and validate each request.
4. A security gateway applies capability, nonce, path, allowed-root, protected-path, and operation policy checks.
5. Filesystem, editor, upload, backup, trash, redaction, and audit services perform narrowly scoped work.
6. Views escape late and render the Browser, Backups, Trash, and Settings tabs.
7. Vanilla JavaScript manages navigation state, async requests, the preview/editor, and accessible dialogs.

There is no generic "run operation" endpoint. Each handler accepts only the inputs required by its operation.

## Module lifecycle and permissions

File Manager is a Toolbox Module with:

- Name: File Manager
- Registry ID: `file_manager`
- Slug: `file-manager`
- Default state: disabled
- Default capability: `manage_options`
- Multisite default: network administrators only, using a network-level capability
- Text domain: `siteintelix`

The effective capability is filterable:

```php
apply_filters( 'siteintelix_file_manager_capability', 'manage_options' );
```

Every page renderer and server-side action checks the effective capability independently. JavaScript visibility is never treated as authorization.

## Security gateway

Every operation follows this order:

1. Confirm an authenticated WordPress user.
2. Confirm the effective File Manager capability.
3. Verify an action-specific nonce.
4. Reject null bytes and remote or local stream wrappers.
5. Repeatedly decode encoded path input within a small fixed limit and reject encoded, double-encoded, mixed-separator, Unicode-confusable, and Windows traversal forms.
6. Normalize directory separators without accepting an unexpected absolute input.
7. Resolve an existing target with `realpath()`. For a new destination, resolve the nearest existing parent and append only validated path segments.
8. Reject symlinks by default and reject any canonical path outside `ABSPATH`.
9. Confirm the path belongs to an enabled allowed root.
10. Apply centralized protected-path and operation policy rules.
11. Revalidate the canonical path and file state immediately before mutation.

The reusable authorization API exposes an operation-aware method equivalent to:

```php
is_path_allowed( string $path, string $operation ): bool
```

Internally it returns categorized `WP_Error` values so handlers can distinguish invalid input, forbidden roots, protected paths, conflicts, filesystem failures, and resource limits without disclosing sensitive paths.

### Allowed roots

The WordPress installation root is browsable but read-only. Safe Mode writes are permitted only in enabled, canonical subdirectories under:

- `WP_CONTENT_DIR`
- `WP_PLUGIN_DIR`
- `get_theme_root()`
- the uploads base directory from `wp_upload_dir()`
- `WPMU_PLUGIN_DIR`
- the WordPress languages directory

Administrators may enable or disable built-in roots and add custom permitted subdirectories, but custom roots must canonicalize inside `ABSPATH`. Filters receive canonical roots and cannot make server paths visible in the interface.

### Symlinks

Symlink traversal is blocked by default. The `siteintelix_file_manager_allow_symlinks` filter may enable traversal only when the canonical target remains inside both `ABSPATH` and an allowed root. Absolute security restrictions still apply.

### Protected paths

Centralized, filterable protection rules cover:

- `wp-admin`
- `wp-includes`
- `wp-config.php`
- root `.htaccess`, `.user.ini`, `php.ini`, and `index.php`
- the active SiteIntelix plugin directory
- the active theme directory
- must-use plugin files

WordPress core, `wp-config.php`, SiteIntelix, the active theme, and must-use plugins are immutable in this Safe Mode release. PHP files are view-only everywhere. `wp-config.php` preview is disabled by default.

The protected-path filter may add restrictions. It may not remove the absolute restrictions enforced after filters.

### WordPress constants

`DISALLOW_FILE_EDIT` disables editing and explains the reason in the interface.

`DISALLOW_FILE_MODS` disables write operations that conflict with the constant while preserving permitted browsing, preview, metadata, and download behavior.

## Browser

The Browser tab has:

- Back, forward, up, and refresh actions
- Breadcrumbs rooted at the configured starting root
- Current relative path
- Current-directory filename search with debounce
- Directories-first sorting by name, type, size, or modified time
- Bounded pagination for large directories
- Folder tree, file table, and details/preview panel
- Empty, loading, and error states
- Visible actions through labeled controls and a kebab menu

Directory listing never recursively scans. It reads at most the configured page window plus the minimum metadata needed for sorting. Hashes are calculated only on demand.

Each row reports name, type, human-readable size, modified time, permissions, writable state, and allowed actions. Filenames and paths are escaped for their exact HTML or attribute context.

## Preview and details

Text previews support:

- `txt`, `log`, `md`, `php`, `css`, `js`, `json`
- `html`, `htm`, `xml`, `yml`, `yaml`
- `ini`, `conf`, `csv`

HTML, PHP, JavaScript, XML, and SVG are never inserted as executable markup. Text is returned as data and rendered through text-only DOM APIs. HTML is shown as source.

Raster image previews support JPEG, PNG, GIF, and WebP through an authenticated preview endpoint. SVG is shown as source unless a dedicated sanitizer can prove it safe; it is not embedded in the admin DOM.

The default inline preview limit is 2 MiB and is filterable. Oversized or unsupported files show metadata and a download action with an explanation.

Details include relative path, optional absolute path, extension, server-determined MIME type, size, modified time, permissions, readability, writability, and on-demand MD5 and SHA-256 hashes. Absolute path display and copying are disabled by default.

`wp-config.php` is not previewable by default. If a filter explicitly permits preview, a reusable redaction service masks database constants, authentication keys, and salts without modifying the file.

## Text editor

Approved non-PHP text files use WordPress's bundled CodeMirror through `wp_enqueue_code_editor()` where available. The editor provides syntax highlighting, line numbers, search, tab indentation, unsaved-change warning, Save, Cancel, full-screen mode where practical, and the standard save keyboard shortcut.

Before save, the handler:

1. Repeats capability, nonce, path, and operation authorization.
2. Confirms the file still exists and is a regular non-symlink file.
3. Confirms its extension is editable.
4. Confirms the file is inside the editable-size limit.
5. Compares both modification time and SHA-256 hash supplied when the file was opened.
6. Confirms the target and parent are writable.
7. Creates and validates a backup.
8. Writes to a unique temporary file in the same directory.
9. Confirms the complete byte count and flushes the file.
10. Preserves the original permission mode where supported.
11. Atomically renames the temporary file over the original.
12. Removes the temporary file on failure.

The original file is never truncated before a replacement is complete. A backup failure blocks save. PHP editing and PHP creation are absent from this release.

## Upload

Uploads support multiple files with progress feedback. Each file is independently authorized and validated for:

- destination path and Safe Mode write policy
- configured maximum size
- sanitized basename without directory components
- a single approved extension
- dangerous or double extensions
- MIME using WordPress validation and server-side inspection
- filename collisions
- complete upload status and a genuine uploaded temporary file

Overwrite is disabled by default. PHP, executable, server-configuration, and unapproved script extensions are blocked. ZIP files may be stored only if archive uploads are explicitly allowed; they are never extracted. Client-provided MIME is not trusted.

## Create and rename

New folders and approved non-PHP text files validate sanitized basenames, reserved names, separators, traversal, duplicates, allowed roots, and Safe Mode policy.

Rename is limited to the same canonical parent directory. It never overwrites, never changes an extension unless the resulting extension is independently allowed, and never renames protected paths. Move is not included.

## Download

Files are downloaded through an authenticated, nonce-protected `admin-post.php` handler. The handler revalidates the path, confirms a regular readable file, clears output buffers, sends safe content headers including `X-Content-Type-Options: nosniff`, and streams bounded chunks without loading the full file into memory.

No public filesystem URL or absolute server path is exposed. Directory download is not included.

## Backups

Before any existing file is modified, the backup service creates a timestamped copy under:

`wp-content/siteintelix/file-manager/backups/`

Each backup has JSON metadata containing:

- original relative path
- owned backup relative path
- creation time
- user ID
- operation
- original size
- original SHA-256 hash

Metadata is schema-validated and never unserialized. A backup is considered successful only after its copy and metadata are durably written.

The Backups tab lists owned backups, filters by relative path, downloads a backup, and restores it after reauthorizing the current destination. Restore never overwrites without the restore operation's explicit confirmation and creates a backup of the current destination first.

Conservative defaults limit age, backups per file, and total storage. Retention is enforced in bounded batches during new backup creation and through WP-Cron.

## Trash

Delete operations move files or explicitly confirmed non-empty directories to:

`wp-content/siteintelix/file-manager/trash/`

Trash names use collision-resistant owned identifiers. JSON metadata stores the original relative path, deletion time, user ID, type, size where practical, and hash for regular files.

The Trash tab lists owned entries, restores to the validated original location, reports collisions without overwriting, and supports permanent deletion only after stronger accessible confirmation. Permanent deletion recursively removes only a validated entry inside the owned trash directory and never follows symlinks.

Absolute protected paths can never enter trash. Trash age and total storage limits use bounded cleanup.

## Storage protection and audit

Owned storage is created under `wp-content/siteintelix/file-manager/` with:

- `index.php`
- Apache access-denial rules
- IIS access-denial rules

The implementation does not depend solely on web-server rules: all payloads use non-guessable owned names and are accessible only through authorized handlers.

An append-only, bounded internal audit log records:

- user ID
- UTC timestamp
- operation
- relative path
- result
- error category

It never records file content, credentials, salts, request bodies, or upload contents. Views and downloads are logged only when enabled. The interface allows future replacement with an Activity Log adapter.

## Settings

The functional Settings tab includes:

### General

- Safe Mode status, always enabled in this release
- Show hidden files, default off
- Default starting directory
- Maximum inline preview size
- Maximum editable file size
- Maximum upload size
- Allow absolute path display, default off

### Allowed locations

- WordPress root, fixed read-only
- `wp-content`, plugins, themes, uploads, must-use plugins, and languages
- Custom permitted subdirectories inside `ABSPATH`

### Editing

- Enable editing
- Allowed editable non-PHP extensions
- Create backup before save, fixed on in this release
- Block active theme and active plugin editing, fixed on in this release

### Uploads

- Enable uploads
- Allowed upload extensions
- Allow archive storage
- Allow overwrite, default off
- Maximum upload size

### Backups

- Enable automatic backups, fixed on for edits
- Retention days
- Maximum backups per file
- Maximum backup storage

### Trash

- Enable trash, fixed on for delete
- Retention days
- Maximum storage
- Automatic cleanup

### Audit

- Enable activity logging
- Log views
- Log downloads
- Log edits
- Log uploads
- Log trash and restores

### Data retention

- Remove File Manager-owned data on full plugin uninstall, default off

There is no Advanced Mode control or confirmation phrase until the mode has functional, reviewed behavior.

## Request handlers

The first release registers separate handlers for:

- list directory
- get file preview
- get file details and hashes
- save file
- upload files
- download file
- create file
- create directory
- rename item
- trash item
- list trash
- restore trash item
- permanently delete trash item
- list backups
- restore backup
- save settings

State-changing handlers use operation-specific nonces. Read handlers also require a capability and nonce because paths and file contents are sensitive.

Safe before/after hooks receive only operation names, relative paths, user IDs, and result categories. Filter names follow the requested `siteintelix_file_manager_*` prefix.

## Interface

The page uses the existing SiteIntelix header and compact control system. The Browser desktop layout contains:

- title, description, Safe Mode badge, and Settings shortcut
- compact navigation/action toolbar and current-directory search
- breadcrumb row
- left folder tree
- center file table
- right details/preview panel

At narrower widths, the table remains primary and the tree and details become labeled slide-over panels. Toolbars wrap without hiding important text labels.

Dialogs have programmatic labels, focus trapping, Escape handling where safe, focus restoration, and explicit primary/destructive actions. Dynamic results use `wp.a11y.speak()` when available. Important actions do not use native `confirm()`.

JavaScript uses no jQuery or framework. File content, names, paths, metadata, and errors are inserted through text-only APIs rather than `innerHTML`.

## Error handling

Handlers return stable error codes and translated public messages such as:

- permission denied
- path outside permitted directories
- protected file
- stale edit
- file too large
- invalid file type
- backup failed
- destination collision
- storage limit reached

Public responses never include absolute paths, stack traces, PHP warnings, database details, or raw filesystem errors. When `WP_DEBUG` is enabled, technical details may be written to the existing private debug log after secrets and absolute paths are redacted.

The interface preserves the current directory after recoverable errors and shows inline loading, empty, and error states.

## Tests

Implementation follows test-driven development.

Dependency-free PHP test scripts with WordPress stubs cover:

- valid and nested paths
- plain, encoded, double-encoded, mixed-slash, Windows, and null-byte traversal
- external absolute paths and similar-prefix attacks
- missing targets and destination-parent resolution
- symlink escape and filter behavior
- protected paths and immutable absolute restrictions
- single-site and multisite capability policy
- `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`
- preview limits and redaction
- valid save, stale conflict, invalid extension, oversized file, backup failure, and atomic-write failure
- upload extension, MIME, double-extension, size, destination, traversal, and collision validation
- trash, non-empty directory confirmation, restore collision, and permanent deletion
- filenames and errors containing HTML, quotes, and JavaScript-like text

Node structural and interaction tests cover:

- registry, card, submenu, conditional require, and boot integration
- assets restricted to File Manager screens
- one handler per operation
- capability and nonce checks
- direct-access guards and forbidden process execution
- translated strings and safe DOM APIs
- navigation state, sorting, search debounce, modal focus, and unsaved-edit behavior

Verification runs:

- existing Node structural tests
- all relevant existing PHP test scripts
- new File Manager tests
- PHP syntax checks for changed PHP files
- PHPCS with WordPress Coding Standards if configured and available
- JavaScript linting if configured and available

Manual QA covers module enable/disable, submenu visibility, asset scope, Safe Mode defaults, browser navigation, sorting, search, preview, edit/backup, upload, download, trash/restore, protected paths, traversal, capabilities, nonces, errors, large directories, responsive behavior, keyboard/focus behavior, multisite, WordPress constants, writable and non-writable filesystems, debug modes, and regression checks for existing modules.

## Release and documentation

File Manager remains part of SiteIntelix 2.7.3. The existing public version constants and stable tag remain `2.7.3`; the changelog and feature documentation are amended to describe the module.

Documentation covers access boundaries, Safe Mode, protected paths, PHP view-only behavior, backups, trash, uploads, WordPress constants, multisite, security, hooks, retention, and permissions troubleshooting.

The module makes no external requests, includes no telemetry or remote code, modifies no WordPress core file, adds no dependency, and follows the current WordPress.org guideline constraints already applied to SiteIntelix 2.7.3.

Disabling the module keeps settings, backups, trash, and logs. Full uninstall removes only File Manager-owned data when the explicit removal setting is enabled, after canonical path and ownership verification. Original site files are never deleted during uninstall.
