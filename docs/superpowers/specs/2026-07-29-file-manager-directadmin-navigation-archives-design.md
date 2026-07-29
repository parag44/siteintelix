# File Manager DirectAdmin Navigation and Archives Design

Date: 2026-07-29  
Target release: SiteIntelix 2.7.3  
Status: Approved for specification

## Purpose

Extend the existing Safe Mode File Manager with a DirectAdmin-style navigation model, a compact toolbar, read-only WordPress-root browsing, multi-item selection, and secure ZIP downloads.

The implementation must remain lightweight, dependency-free, and conditionally loaded only when the File Manager module is enabled. This work does not introduce Advanced Mode, archive extraction, persistent archives, background processing, or unrestricted filesystem access.

## User Experience

### Two-pane workspace

The Browser screen uses a two-pane desktop layout:

1. A 260-pixel expandable folder tree.
2. A flexible file-list pane using the remaining width.

The permanent Details pane is removed. Details opens on demand in a slide-over panel from the selection action bar, the row menu, or the context menu.

The tree, list, and Details slide-over have independent scrolling. Large directories must not make the WordPress admin page grow into a long file-list page.

### Folder tree

The tree begins with a single `WordPress` root node.

- Each directory has a folder icon and a disclosure chevron.
- Child directories are visually indented so ancestry is unambiguous.
- The current directory uses the DirectAdmin-style light-blue active state.
- Expanded ancestors remain open while the user navigates their descendants.
- A node loads only its immediate children when first expanded.
- Nodes expose `aria-expanded`, `aria-current`, and correct tree hierarchy semantics.
- A branch-level load failure displays a compact Retry control without replacing the rest of the tree.

Selecting a tree node loads the corresponding file list and synchronizes the breadcrumb, search label, active highlight, and navigation history.

### WordPress root

Selecting `WordPress` loads the installation root instead of returning an invalid-path error.

The empty relative path represents the canonical WordPress root only for approved read operations. The root may show directories such as `wp-admin`, `wp-content`, and `wp-includes`, plus permitted root files.

Protected locations are read-only. The server omits all mutation actions that are not authorized for the specific item. Existing restrictions for WordPress core, `wp-config.php`, active plugins, active themes, must-use plugins, PHP files, and server configuration files remain authoritative.

### Compact toolbar

The old Back, Forward, Up, Refresh, New, Upload, Folders, and Details toolbar is replaced with these compact controls:

1. New Folder
2. New File
3. Upload
4. Sort by
5. Refresh

Breadcrumbs and the current-directory search field sit directly above the file list.

New Folder and New File use the existing secure modal workflow. Upload retains existing size, overwrite, extension, and WordPress-constant restrictions.

`Sort by` offers:

- Name ascending and descending
- Type ascending and descending
- Size ascending and descending
- Modified ascending and descending

Sortable table headers remain available as an equivalent interaction.

### File list and selection

Each row contains:

1. A selection checkbox.
2. File or folder icon and plain-text name.
3. Size.
4. Permissions.
5. Last modified.
6. Writable/read-only status where useful.
7. A narrow three-dot menu button.

The list supports up to 100 selected items from the current directory. Selection does not persist across directory changes.

When at least one item is selected, a compact action bar appears above the list with:

- Download
- Archive ZIP
- Details
- Rename
- Trash

Actions are enabled only when valid for the complete selection:

- Download is direct only for one authorized file.
- Archive ZIP supports one or more files and folders.
- Details and Rename require exactly one authorized item.
- Trash requires every selected item to be writable and trash-authorized.

The right-click context menu and three-dot fallback remain available with the same icon-and-label actions. Row selection, double-click behavior, keyboard operation, focus restoration, and live announcements continue to follow familiar desktop conventions.

## Architecture

### Tree service

Add a focused read-only directory-tree service and an operation-specific `siteintelix_fm_list_tree` AJAX handler.

Input:

- A WordPress-root-relative directory path.

Output:

- Current canonical relative path.
- Immediate child directories only.
- Safe display name.
- Whether a child directory exists.
- Whether the node is read-only.

The service:

- Uses the central File Manager security service.
- Never follows symlinks.
- Never recursively enumerates the tree.
- Applies the existing hidden-file setting.
- Caps each branch scan with the existing bounded directory-scan policy.
- Sorts directory names naturally and case-insensitively.

### Root-path authorization

The central security service treats an empty string as the canonical WordPress root only for explicitly read-only operations such as:

- list
- tree
- details where otherwise permitted

The empty path remains invalid for write operations. Absolute paths, traversal segments, control characters, URL schemes, symlinks, and paths outside `ABSPATH` remain rejected.

Every row action is calculated by asking the security service whether that exact operation is authorized. The client never infers permission from filesystem writability alone.

### Selection model

Selection is client-side state keyed by canonical relative path.

- A checkbox toggles one item.
- The header checkbox selects or clears all visible permitted rows.
- A directory change clears selection.
- Refresh reconciles selection with the refreshed result and drops missing items.
- The action bar derives its state from the intersection of server-provided actions for all selected items.
- The client sends only selected canonical relative paths and never absolute paths.

### Archive service

Add a focused archive service and an authenticated `admin-post.php` download handler.

The handler:

1. Verifies login, the File Manager capability, module enablement, and an operation-specific nonce.
2. Accepts between 1 and 100 canonical relative paths from one directory.
3. Re-authorizes every selected source for the archive operation.
4. Creates an unpredictable temporary ZIP within SiteIntelix-owned private storage.
5. Walks selected directories without following symlinks.
6. Enforces entry-count and uncompressed-size limits while walking.
7. Streams the completed ZIP with download and MIME-sniffing protection headers.
8. Deletes the temporary archive on success, client disconnect, or failure.
9. Records the result and omission counts through the File Manager audit service.

Archive entries preserve paths relative to the selected items' common current directory; absolute server paths never appear in entry names. A single selected item uses its sanitized basename for the download name. Multiple selections use the sanitized current-directory name plus a UTC timestamp. If every selected entry is excluded or unreadable, the request fails without streaming an empty archive.

Required limits:

- Maximum selected sources: 100.
- Maximum archive entries: 5,000.
- Maximum total uncompressed bytes: 250 MB.

The limits are filterable downward or upward by developers but ship with the values above.

Archive generation requires PHP `ZipArchive`. When it is unavailable, archive capability is reported as disabled and the interface explains why.

### Archive exclusions

Archives never contain:

- Symlinks or symlink targets.
- `wp-config.php`.
- Server credential or configuration secrets protected by the central policy.
- SiteIntelix File Manager backup, trash, metadata, lock, or temporary storage.
- Any path outside the canonical WordPress installation.

Excluded descendants are omitted rather than weakening the protection boundary. The archive audit entry records the number of omitted entries without exposing absolute paths or sensitive names in the interface.

Archive extraction is not included.

## Data Flow

### Tree navigation

1. Browser initialization renders the WordPress root.
2. Expanding a node requests `list_tree` for that node.
3. The server authorizes the path and returns only immediate child directories.
4. The client inserts safe text nodes under the expanded parent.
5. Selecting a node requests the existing bounded directory listing.
6. The tree active state, breadcrumb, search label, and list update together.

### Selection and actions

1. The user selects rows through checkboxes, click behavior, or keyboard controls.
2. The client intersects server-provided action arrays.
3. The contextual action bar displays the valid combined actions.
4. Single-item actions reuse existing focused handlers.
5. Archive submits selected relative paths to the dedicated download handler.

### ZIP download

1. The browser submits a nonce-protected archive-download request.
2. The server validates count, common parent, paths, capability, module state, and `ZipArchive`.
3. The archive service creates and populates the private temporary ZIP within fixed limits.
4. The handler streams the ZIP.
5. Cleanup executes regardless of the response outcome.

## Error Handling

- A tree-branch failure remains local to that branch and offers Retry.
- A directory-list failure preserves the last valid directory and active tree state.
- Invalid root requests return a generic bounded error without absolute filesystem details.
- Unsupported archive capability disables Archive ZIP with an explanatory tooltip or notice.
- Selection-count, entry-count, and byte-limit failures identify the applicable limit.
- Temporary storage, ZIP creation, and streaming failures return non-sensitive messages and create failure audit records.
- Cleanup failures are logged without exposing private paths in the UI.
- Existing modal, notice, and live-region announcement systems remain the presentation path for errors and completed operations.

## Responsive and Accessible Behavior

- Above 1100 pixels, the folder tree and file list are visible as two panes.
- At or below 1100 pixels, the tree becomes the existing slide-over drawer and the list uses the available width.
- At or below 782 pixels, WordPress sidebar offsets are removed.
- Auto-folded and manually folded WordPress admin-menu offsets remain supported.
- The Details slide-over is available at every viewport.
- Controls use visible focus states and operation-specific accessible labels.
- Tree nodes expose correct `tree`, `treeitem`, `aria-level`, `aria-expanded`, and `aria-current` semantics.
- Selection changes, menu opening, archive availability, failures, and successful operations use the live announcer.
- Reduced-motion preferences disable drawer and menu transitions.

## Testing

### Automated tests

Add or extend tests for:

- Empty relative path authorization for read operations.
- Empty relative path rejection for write operations.
- WordPress-root listing.
- Protected-root mutation filtering.
- Row action arrays matching server authorization.
- Lazy tree immediate-child results and `has_children`.
- Hidden-directory behavior and branch scan limits.
- Tree rendering, hierarchy, ARIA, retry, and active-state helpers.
- Compact toolbar and selection action-bar markup.
- Selection limits and combined-action intersection.
- Archive capability detection.
- Archive nonce and capability enforcement.
- Common-parent and canonical-path validation.
- Traversal and symlink rejection.
- Protected and private-storage omission.
- Entry-count and byte limits.
- ZIP content naming and collision handling.
- Temporary-file cleanup on success and failure.
- Download response headers and audit recording.
- Responsive two-pane/drawer CSS invariants.

### Live QA

Verify:

1. The WordPress root lists permitted root directories and files.
2. Protected core rows expose no mutation operations.
3. Nested tree expansion clearly communicates ancestry and current location.
4. Current tree state remains synchronized after sorting and refresh.
5. The compact toolbar matches the DirectAdmin-inspired design.
6. Checkbox and keyboard selection update the action bar correctly.
7. Single-file Download works.
8. Multi-file and folder Archive ZIP downloads contain the expected permitted entries.
9. Protected descendants and symlinks are omitted.
10. Tree, list, and Details slide-over scroll independently.
11. Folded WordPress navigation and widths above/below 1100 and 782 pixels remain usable.

## Acceptance Criteria

- The left navigation clearly shows folder ancestry, expanded state, and the current directory.
- Selecting WordPress successfully lists the installation root in read-only mode.
- The default Browser UI is a DirectAdmin-inspired two-pane workspace.
- The toolbar contains only New Folder, New File, Upload, Sort by, and Refresh.
- Multi-selection exposes a compact, permission-aware action bar.
- Files and folders can be securely combined into an immediately downloaded ZIP.
- Archive generation never leaves persistent ZIP files behind.
- Existing Safe Mode protections remain authoritative.
- The module remains lightweight, dependency-free, screen-scoped, and inactive when disabled.
