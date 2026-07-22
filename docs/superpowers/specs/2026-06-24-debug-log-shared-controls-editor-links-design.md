# Debug Log Shared Controls and Editor Links Design

## Goal

Make the Debug Log viewer controls visually identical in Classic, Modern, and Terminal modes. In Classic mode, make plugin and theme file paths open the matching native WordPress file editor in a new tab at the reported error line.

## Shared Status and Mode Component

All three viewers will render the same shared PHP partial for the status area. The component contains:

- The active debug method icon.
- The active method title: `wp-config.php Mode Active` or `MU Plugin Mode Active`.
- The current logging destination description.
- A `Switch Mode` button linking to SiteIntelix settings.
- A `Recent Entries` card displaying the unfiltered parsed entry count.

The shared component will use one set of CSS classes and responsive rules. Viewer-specific copies of this markup and their conflicting layout rules will be removed.

The existing header actions remain unchanged: version, refresh, switch mode, clear logs, and download log.

## Classic Editor Links

Classic rows will ask a dedicated helper to classify each parsed file path and produce editor-link metadata.

### Plugin files

For a path inside `WP_PLUGIN_DIR`:

- Confirm the current user can `edit_plugins`.
- Confirm file modifications and the built-in file editor are enabled.
- Resolve the path relative to `WP_PLUGIN_DIR`.
- Resolve the owning plugin's main file from WordPress's registered plugins.
- Confirm the target is included in `get_plugin_files()` for that plugin.
- Build a native `plugin-editor.php` URL with `plugin`, `file`, and the SiteIntelix line parameter.

### Theme files

For a path inside the active WordPress themes directory:

- Confirm the current user can `edit_themes`.
- Confirm file modifications and the built-in file editor are enabled.
- Resolve the owning stylesheet directory and the path relative to it.
- Confirm the theme exists and the file is one of the files WordPress exposes as editable.
- Build a native `theme-editor.php` URL with `theme`, `file`, and the SiteIntelix line parameter.

### Non-editable files

WordPress core files, `wp-admin`, `wp-includes`, uploads, vendor files outside a registered plugin or theme, missing files, disallowed extensions, and failed validation cases remain plain text.

Editor links open in a new tab with `target="_blank"` and `rel="noopener noreferrer"`. The visible path remains the complete absolute path.

## Exact-Line Navigation

SiteIntelix will conditionally enqueue a small editor-line script only on `plugin-editor.php` and `theme-editor.php` when a valid positive SiteIntelix line parameter is present.

After WordPress initializes its CodeMirror editor, the script will:

1. Clamp the requested 1-based line to the available document range.
2. Convert it to CodeMirror's 0-based line index.
3. Move the cursor to that line.
4. Scroll the line into the center of the editor viewport.
5. Add a temporary line background class.
6. Focus the editor.

If syntax highlighting is disabled and WordPress renders a plain textarea, the script will calculate the character offset for the requested line, focus the textarea, place the caret at that line, and scroll the textarea proportionally. The URL still opens the correct file if either navigation method cannot initialize.

## Security and Validation

- Never use a raw log path directly as a URL parameter without canonicalization and ownership validation.
- Require the same WordPress capabilities used by the native editors.
- Respect `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS`.
- Reject traversal, symlink escape, missing files, and files outside the resolved plugin or theme root.
- Do not add editor links for WordPress core files.
- Escape all link attributes and visible paths.

## Performance

- Keep editor-link classification in a focused helper.
- Cache plugin and theme file maps for the duration of the request.
- Generate links only for Classic rows currently rendered on the page.
- Load the line-navigation script only on native editor pages with a valid line request.
- Add no third-party dependency or custom code editor.

## Testing

Automated tests will verify:

- All three viewers use the shared status partial.
- Viewer-specific duplicate switch/status markup is absent.
- Plugin paths generate native Plugin File Editor metadata.
- Theme paths generate native Theme File Editor metadata.
- Core and invalid paths generate no editor link.
- Capability and file-edit restrictions suppress links.
- Editor URLs include the requested line.
- The navigation script supports CodeMirror and textarea fallback behavior.

Live browser verification will cover:

- Matching shared panel layout in Classic, Modern, and Terminal.
- A real plugin file link opens the native Plugin File Editor in a new tab.
- The correct file is selected.
- The reported line is centered and highlighted.
- Core paths remain plain text.

