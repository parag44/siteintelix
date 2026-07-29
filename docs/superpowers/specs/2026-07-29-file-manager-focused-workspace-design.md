# File Manager Focused Workspace Design

## Goal

Refine the SiteIntelix 2.7.3 File Manager into the approved focused browser workspace shown in the supplied reference. The release remains Safe Mode-only. Existing file operations retain their current authorization, nonce, canonical-path, and bounded-resource controls.

## Scope

This change combines a presentation refresh with one contained navigation feature:

- Match the reference's search-first toolbar, breadcrumb summary card, separated folder/list cards, blue folder treatment, compact table, and viewport-contained scrolling.
- Remove the visible Browser, Backups, Trash, and Settings tab row.
- Keep Settings accessible through the existing header button.
- Hide the Backups interface and backup settings while preserving automatic private safety backups used by edit and restore operations.
- Expose private trash through a secure virtual `.trash` folder in the normal browser workspace.

Advanced Mode, archive extraction, chmod, ownership changes, shell execution, and physical root trash storage remain out of scope.

## Workspace Structure

The File Manager header continues to show its folder icon, title, description, Safe Mode badge, and Settings button.

The browser content contains three stacked regions:

1. A rounded toolbar card:
   - Search files and folders on the left.
   - New Folder, New File, and Upload in the primary action group.
   - Sort by and Refresh aligned to the right.
2. A rounded breadcrumb and directory-summary card:
   - Home indicator and clickable WordPress-relative breadcrumbs.
   - Item count, total visible file size, directory permissions, and current relative path.
3. A two-pane workspace:
   - An independently scrolling rounded folder-tree card.
   - A wider independently scrolling rounded file-list card with a sticky header.

The existing responsive tree drawer and on-demand Details slide-over remain available. Narrow layouts wrap the toolbar and summary fields without creating page-length file scrolling.

## Visual System

The workspace uses the existing SiteIntelix design tokens and local assets only.

- White cards use subtle borders, 12-pixel radii, and restrained shadows.
- Toolbar controls use bordered 34–38 pixel buttons rather than text separators.
- Folder icons use a blue-to-indigo treatment while preserving distinct file-type colors.
- Tree rows retain 32-pixel height, indentation, chevrons, ellipsis, lock indicators, and a pale blue current-row state.
- File rows remain approximately 44 pixels high with quiet separators, a compact checkbox, readable metadata columns, and a three-dot context trigger.
- Search is the dominant toolbar field.
- Focus rings, reduced-motion rules, live announcements, keyboard menus, and dialog semantics remain intact.

## Virtual `.trash`

`.trash` is a virtual File Manager route displayed directly beneath WordPress in the folder tree. No `ABSPATH/.trash` directory is created.

The route maps to existing private SiteIntelix trash storage and validated metadata. It never exposes private storage paths or creates web-addressable deleted files.

Opening `.trash` replaces the ordinary directory listing with trash entries rendered in the same file table. Each entry exposes only actions valid for trash:

- Restore to the original authorized path.
- Delete permanently after the existing typed-name confirmation.
- View safe metadata when available.

The virtual folder itself cannot be renamed, trashed, archived, downloaded, or modified. Ordinary create, upload, and selection actions are disabled while `.trash` is active. Restore continues to fail closed on collisions, invalid metadata, protected destinations, or unauthorized paths.

## Backups

The visible Backups tab, Backups page navigation, and user-facing backup options are removed for this release.

Automatic private safety backups remain active where the current editor and restore services require them. Their retention and ownership validation are unchanged. This preserves rollback protection without exposing a separate management surface.

## Data Flow

Ordinary directories continue using the existing `list_directory` and lazy `list_tree` handlers.

The root tree response injects one synthetic `.trash` node after WordPress. Selecting it switches the browser into virtual-trash state and calls the existing authenticated trash-list operation. Returning to any ordinary breadcrumb or tree node restores directory mode.

Directory summary values are returned or derived from the bounded current-page response:

- Items: current directory total.
- Size: total size represented by the returned visible file data, clearly formatted.
- Permissions: current directory permissions when available.
- Current Path: WordPress-relative path, with `/` representing the WordPress root.

No recursive size calculation is introduced.

## Error Handling

Tree and directory failures remain contained in their existing panels. Virtual-trash failures use the file-list state area and leave the folder tree usable.

Unsupported or unavailable actions remain absent or disabled based on the complete selected set. Permanent deletion retains typed-name confirmation. All output continues using safe DOM construction and escaped PHP templates.

## Testing

Test-driven changes will cover:

- The visible tab row and public Backups interface are absent.
- Automatic backup services remain loaded and used.
- Toolbar order and search-first structure match the approved layout.
- Breadcrumb summary fields and two separated workspace cards exist.
- Root tree exposes one virtual `.trash` node without a physical root directory.
- Virtual trash lists only private owned entries and exposes only restore, permanent delete, and permitted details.
- Ordinary actions are unavailable in trash mode.
- Returning from `.trash` restores normal browser behavior.
- Existing Safe Mode, archive, selection, context-menu, responsive drawer, syntax, and structural suites remain green.

Authenticated live QA will verify the reference layout, icon alignment, contained scrolling, tree state, `.trash` navigation, restore confirmation without mutating unrelated site files, and responsive behavior.
