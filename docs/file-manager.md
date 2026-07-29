# SiteIntelix File Manager

File Manager is an optional SiteIntelix 2.7.3 module for inspecting and carefully managing files inside one WordPress installation. It is disabled by default, loads only after an authorized administrator enables it, and operates only in Safe Mode in this release.

## Access and scope

File Manager requires SiteIntelix's effective management capability. On multisite, access is limited to a network super administrator. Every AJAX, download, image-preview, and settings request performs its own capability and action-specific nonce check.

Paths are canonicalized on the server. Null bytes, traversal segments, stream wrappers, Windows drive paths, symbolic links, paths outside `ABSPATH`, and paths outside enabled roots are rejected. The WordPress installation root can be browsed but is read-only. Administrators may enable standard locations such as `wp-content`, plugins, themes, uploads, must-use plugins, and languages, plus existing custom directories inside `ABSPATH`.

## Safe Mode

Advanced Mode is intentionally absent. Safe Mode permanently protects WordPress core, `wp-config.php` writes, SiteIntelix itself, must-use plugins, active plugins, and the active theme. Filters may add protection but cannot remove these immutable rules.

PHP files are view-only. They can be inspected as escaped text and downloaded, but they cannot be edited, created, uploaded, renamed to or from PHP, or otherwise written through File Manager. Executable and server-configuration extensions remain blocked even if submitted in settings.

`DISALLOW_FILE_EDIT` disables browser editing. `DISALLOW_FILE_MODS` disables write operations such as uploads, creation, rename, trash, and restore. File permissions and parent-directory writability are checked again immediately before each operation.

## Browsing, preview, and download

Directory reads are non-recursive, paginated, sortable by a fixed allowlist, searchable within the current directory, and capped to prevent unbounded scans. Text previews have a configurable byte limit and binary detection. An explicitly permitted `wp-config.php` preview is redacted before display. Raster image previews use a separate authenticated, no-store, `nosniff` response. Downloads also use an authenticated attachment response and never expose a direct storage URL.

## Editing and backups

Only configured non-PHP text extensions can be edited. Opening a file records its modification time and SHA-256 hash. Saving fails if either value changed, preventing accidental overwrite of a newer version.

Every edit and backup restore first creates a private, verified backup. Content is written to an exclusive temporary file in the target directory and then atomically renamed over the target. If backup creation or verification fails, the original file is not changed.

Backups are stored under `wp-content/siteintelix/file-manager/backups` with schema-validated metadata stored separately. They are not web-addressable and include no file contents in audit records.

## Uploads and creation

Uploads are off when disabled in settings or by `DISALLOW_FILE_MODS`. Each file is checked independently for upload status, sanitized basename, size, extension, detected MIME, destination, collision, and Safe Mode policy. Overwrite is off by default. Archive storage is opt-in and never extracts an archive.

New files are limited to the configured editable non-PHP text extensions. New folders and renames remain in an authorized parent directory, reject reserved or duplicate names, and never move an item between directories.

## Trash and restore

Delete moves eligible files or folders into private File Manager trash. Non-empty folders require explicit confirmation. Restore returns an entry only to its original still-authorized path and fails on collision. Permanent deletion acts only on an opaque, validated trash identifier and never deletes the `original_path` value from metadata.

## Audit and privacy

The optional audit log records timestamp, user ID, operation, relative path, outcome, and error category. It never records file contents, credentials, salts, request bodies, or upload bodies. View and download logging can be controlled independently.

All backups, trash entries, metadata, and audit records stay within the WordPress installation. File Manager sends no telemetry and makes no external network requests.

## Retention and uninstall

A single daily `siteintelix_file_manager_cleanup` event applies bounded work batches. Backup retention enforces age, maximum backups per original file, and total storage. Trash retention enforces age and total storage when automatic cleanup is enabled. Disabling File Manager or deactivating SiteIntelix clears the schedule without deleting data.

Normal uninstall always removes the File Manager settings option and scheduled hook. File Manager-owned backups, trash, metadata, and audit records are retained unless **Remove owned data on uninstall** was explicitly enabled. Opted-in deletion validates the fixed `WP_CONTENT_DIR/siteintelix/file-manager` root, refuses symbolic links or canonical mismatches, and never follows metadata paths into site files.

## Hooks

- `siteintelix_file_manager_storage_path` changes the runtime private storage root. Uninstall cleanup deliberately accepts only the fixed default root.
- `siteintelix_file_manager_preview_max_bytes` adjusts the bounded preview limit.
- `siteintelix_file_manager_directory_scan_limit` adjusts the non-recursive scan cap.
- `siteintelix_file_manager_hash_max_bytes` adjusts the maximum size eligible for on-demand hashing.
- `siteintelix_file_manager_cleanup_batch_size` adjusts the daily deletion batch between 1 and 500.
- `siteintelix_file_manager_allow_symlinks` can permit reads only when the canonical target remains inside `ABSPATH` and an enabled allowed root; immutable protections still apply.
- `siteintelix_file_manager_allow_wp_config_preview` can enable redacted `wp-config.php` preview.
- `siteintelix_file_manager_protected_paths` can add protected paths.
- `siteintelix_file_manager_after_operation`, `siteintelix_file_manager_file_saved`, and `siteintelix_file_manager_item_trashed` provide post-operation integration points without exposing file contents.

## Troubleshooting permissions

If a path is visible but an action is unavailable, check the selected allowed locations, active plugin/theme protection, WordPress constants, filesystem owner and group, file permissions, parent-directory writability, file size and extension limits, and whether the path contains a symbolic link. File Manager fails closed; it does not offer chmod, ownership changes, shell execution, or a bypass for server policy.
