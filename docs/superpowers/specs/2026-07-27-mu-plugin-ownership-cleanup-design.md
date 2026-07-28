# MU Plugin Ownership and Cleanup Design

## Goal

Ensure SiteIntelix MU bootstraps are clearly identified in WordPress and are removed when their module or the SiteIntelix plugin is deactivated or deleted, without deleting MU files owned by another plugin.

## Root cause

- The full plugin deactivation callback currently unschedules only Email Log retention.
- The uninstall handler removes only `siteintelix-debug-capture.php`.
- Safe Mode module deactivation stops the current session but leaves `siteintelix-safe-mode.php`.
- The retired `siteintelix-plugin-safety-guard.php` is not referenced by current cleanup code.
- This site redirects `WPMU_PLUGIN_DIR` to `wp-content/wp-safe-mode`, while legacy SiteIntelix files also exist in the standard `wp-content/mu-plugins` directory.
- The generated SiteIntelix MU files lack standard WordPress plugin headers, so WordPress shows filenames without useful descriptions.

## Ownership model

Create a focused MU-file manager that is the only cleanup authority for SiteIntelix MU bootstraps.

The allowlist contains:

| Filename | Required ownership markers |
| --- | --- |
| `siteintelix-debug-capture.php` | `SiteIntelix Debug Capture` or the legacy `SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION` marker |
| `siteintelix-safe-mode.php` | `SiteIntelix Safe Mode` and `siteintelix_safe_mode_hash_token` |
| `siteintelix-plugin-safety-guard.php` | `SiteIntelix Plugin Safety Guard` and `siteintelix_psg_filter_active_plugins` |

Deletion is fail-closed:

1. The filename must be in the SiteIntelix allowlist.
2. The file must be a regular readable file inside an approved MU directory.
3. Every required marker for the matching signature must be present in its contents.
4. If any check fails, the file is preserved.
5. Symlinks are not followed or deleted.

This means a third-party file is preserved even if it deliberately or accidentally uses a SiteIntelix filename.

## Directory scope

Cleanup examines only these two explicit directories:

- `WPMU_PLUGIN_DIR`, which is the active MU directory.
- `WP_CONTENT_DIR . '/mu-plugins'`, which is the standard legacy directory.

The paths are normalized and deduplicated. Cleanup does not scan other directories and never recursively deletes directories.

## Lifecycle behavior

### Individual module deactivation

- Disabling Debug Log disables capture and removes verified Debug Capture bootstraps from both approved directories.
- Disabling Safe Mode stops the current user’s session and removes verified Safe Mode bootstraps from both approved directories.
- Other SiteIntelix MU files remain untouched.

### Full SiteIntelix deactivation

- Unschedule existing background work as it does now.
- Disable debug capture.
- Stop the current user’s Safe Mode session where possible.
- Remove every verified SiteIntelix MU bootstrap in the allowlist from both approved directories.
- Preserve options, tables, logs, snippets, and other user data.

### Uninstall

- Run the same ownership-checked full MU cleanup before removing plugin options and tables.
- Remove current and legacy SiteIntelix MU files.
- Never remove third-party MU files, including WP Safe Mode and WPMgr bootstraps.
- Continue to leave `wp-config.php` unchanged.

## WordPress MU plugin metadata

Generated bootstraps receive standard file headers so the Must-Use Plugins screen displays useful information.

### Debug Capture

- Plugin Name: `SiteIntelix Debug Capture`
- Description: `Captures PHP errors before normal plugins load and writes them to the private SiteIntelix debug log.`
- Version: current `SITEINTELIX_VERSION`
- Author: `Parag Das`

### Safe Mode

- Plugin Name: `SiteIntelix Safe Mode`
- Description: `Applies private, session-based plugin and theme isolation before normal plugins load.`
- Version: current `SITEINTELIX_VERSION`
- Author: `Parag Das`

The retired Plugin Safety Guard is deleted when verified and is not regenerated.

## Error handling

- Cleanup returns a result containing removed, preserved, and failed paths.
- Missing files count as successful no-ops.
- Failed deletions are detected by checking whether the file remains after `wp_delete_file()`.
- Module-level cleanup can surface an admin notice when deletion fails.
- Full deactivation cannot reliably display a post-deactivation notice, so failures are preserved for diagnostic logging without deleting unverified files.

## Architecture

- Add `includes/class-siteintelix-mu-files.php` for path resolution, ownership checks, and deletion.
- Load the manager from `siteintelix.php`.
- Reuse it from Debug Log module cleanup, Safe Mode module cleanup, full plugin deactivation, and `uninstall.php`.
- Keep file generation in the existing Debug Log and Safe Mode classes; only add plugin metadata headers there.

## Verification

- Unit-style PHP tests create temporary active and legacy MU directories.
- Tests prove verified SiteIntelix files are deleted from both directories.
- Tests prove foreign content using the same filename is preserved.
- Tests prove symlinks and unrecognized filenames are preserved.
- Structural tests require full deactivation and uninstall to use the centralized manager.
- Structural tests require both generated MU files to contain WordPress plugin metadata and descriptions.
- The complete SiteIntelix structural and PHP runtime suites must pass.
- Live WordPress verification confirms the MU Plugins screen displays both descriptions after the generated files refresh.
