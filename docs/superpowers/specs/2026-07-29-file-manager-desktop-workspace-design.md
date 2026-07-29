# File Manager Desktop Workspace Design

## Summary

Redesign the SiteIntelix File Manager browser as a polished three-pane desktop workspace while preserving the Safe Mode security architecture and the existing operation-specific AJAX services.

The implementation target is the approved Visual Companion mockup:

- a persistent folder tree, sortable file list, and details panel on desktop;
- file and folder icons in the Name column;
- selection and opening behavior familiar from desktop file managers;
- an icon-and-label context menu instead of a crowded Actions column;
- a three-dot menu button as an accessible touch and keyboard fallback;
- independent scrolling inside a workspace constrained to the available WordPress admin viewport;
- slide-over Folder Tree and Details panels on narrower screens.

This is a UI and interaction redesign. It does not expand File Manager permissions, protected-path rules, supported operations, or Safe Mode boundaries.

## Goals

1. Repair the missing File Manager icon on the Modules screen, All Module Settings screen, and File Manager page header.
2. Eliminate the malformed or overlapping module-tab icon presentation shown in the supplied screenshots.
3. Replace native-looking file-name and action buttons with a clean, consistent file list.
4. Remove the visible Actions column and expose the same permitted operations through a right-click context menu and three-dot fallback.
5. Keep long directories inside the current browser viewport instead of extending the complete WordPress admin page.
6. Match the approved mockup's hierarchy, density, spacing, icon treatment, selected state, and context-menu presentation.
7. Preserve dependency-free JavaScript, local assets, accessibility, WordPress conventions, and current security behavior.

## Non-Goals

- Advanced Mode or any change to the Safe Mode-only release boundary.
- New filesystem operations or expanded access to protected paths.
- A grid/gallery view or a list/grid switcher.
- Drag and drop, cut/copy/paste, multi-select, or bulk operations.
- Replacing the existing PHP services, storage model, AJAX contracts, editor, Backups, Trash, or Settings flows.
- Copying another File Manager product or importing an external icon/font library.

## Approved Visual Direction

The browser uses a calm SiteIntelix visual language rather than default browser controls:

- white workspace surface on the existing SiteIntelix canvas;
- restrained blue accents based on the existing `--si-primary` tokens;
- compact toolbar and approximately 42–44 pixel file rows;
- subtle borders and one-pixel separators;
- a pale-blue selected-row background with a blue leading edge;
- visible but quiet hover and keyboard-focus states;
- colored file-type icons used as recognition aids;
- a compact elevated context menu with icon-and-label actions and a separated destructive action;
- a light details preview block and aligned metadata rows.

The approved mockup is the source of truth for proportions and hierarchy. Responsive adaptations may change pane visibility, but not the component styling or interaction model.

## Shared Module Icon Repair

The File Manager module already declares an inline folder SVG, but the supplied screenshots show blank output in module contexts and an unreliable Dashicon in the page header.

The redesign will:

1. Use one reusable, sanitized File Manager folder SVG source for module cards, settings tabs, and the File Manager page header.
2. Give inline module SVGs explicit width, height, display, and flex behavior so they cannot collapse or overlap adjacent labels.
3. Scope SVG sizing to the module icon wrappers rather than applying generic rules that could affect Custom CSS & JS or Code Snippets.
4. Retain a valid Dashicon fallback for contexts that cannot render the shared SVG.
5. Keep the icon decorative with `aria-hidden="true"` and prevent it from receiving focus.

## Desktop Workspace

### Layout

The browser retains the existing high-level page header, section tabs, toolbar, breadcrumbs, and three-pane workspace.

On desktop:

- Folder Tree has a fixed, compact width.
- File List consumes remaining width and is the primary pane.
- Details has a fixed width sufficient for previews and metadata.
- The workspace fills the remaining visible WordPress admin viewport beneath the File Manager header, tabs, toolbar, and breadcrumbs.
- The workspace has a minimum height for smaller desktop windows and a capped calculation that prevents normal directory contents from lengthening the whole page.
- Folder Tree, File List, and Details scroll independently.
- The File List header remains sticky within its scroll container.
- Pagination remains anchored within the File List pane and does not extend the page.

On narrower screens:

- File List becomes the only persistent pane.
- Folder Tree and Details become existing-pattern slide-over drawers.
- The toolbar wraps without causing horizontal page scrolling.
- The file table may scroll horizontally inside the pane when required.

### Columns

Remove the Actions column.

The list displays:

1. Name, containing the file/folder icon and plain-text name.
2. Type.
3. Size.
4. Modified.
5. Permissions.
6. Writable/access state.
7. A narrow, unlabeled menu-button column containing the row's three-dot control.

At constrained widths, secondary metadata columns may be hidden progressively in CSS while Name and the menu control remain available.

## File and Folder Icons

Icons are local, dependency-free, and generated from a fixed trusted category map.

Required categories include:

- folder;
- PHP/code;
- JavaScript;
- CSS;
- HTML;
- text/config/log;
- image;
- archive;
- document;
- audio/video;
- generic file.

The client classifies an item from the server-provided item type and a normalized extension. Filenames are never interpreted as markup. Labels and names continue to be assigned with `textContent`.

Icons must have:

- consistent geometry and alignment;
- readable color contrast;
- restrained category colors;
- decorative semantics (`aria-hidden="true"`);
- a generic fallback for unknown or extensionless files.

## Selection and Opening

One selected-item state drives row styling, details, and actions.

- Single-click selects a row and requests or displays its details.
- Double-click opens a folder or invokes the file's supported primary open/preview/edit behavior.
- Enter on a focused row performs its primary Open action.
- Selection persists while the related asynchronous details request completes.
- Selecting a new item invalidates stale detail responses so older network responses cannot overwrite the current selection.
- Refreshing or navigating clears selection and closes any open context menu.

## Context Menu

### Invocation

The context menu opens from:

- right-click on a valid file row;
- the row's three-dot button;
- Shift+F10 on a focused row;
- the keyboard Menu/ContextMenu key where exposed by the browser.

The native browser context menu is suppressed only for a valid File Manager item row. Right-click elsewhere retains normal browser behavior.

### Contents

The menu shows an icon and human-readable name for each action available to that item. It reuses the same permission-aware action model as the current row controls.

Possible entries include:

- Open;
- View details;
- Edit, when editing is supported and enabled;
- Download, for downloadable files;
- Rename, when modification is allowed;
- Move to Trash, when deletion is allowed.

Unavailable operations are omitted. Protected items do not present Rename or Move to Trash. The destructive operation is visually separated and styled as destructive.

### Behavior and Positioning

- Only one context menu exists in the DOM and is reused for every row.
- It is positioned next to the pointer or originating three-dot button.
- Its position is clamped to the File Manager/viewport edges so it remains fully visible.
- Opening it selects the originating row.
- Outside click, Escape, navigation, refresh, resize, or a completed action closes it.
- Focus moves to the first menu item on keyboard invocation.
- Arrow Up/Down, Home/End, Enter/Space, and Escape are supported.
- Closing restores focus to the originating row or button when appropriate.

## Details Panel

The details panel follows the approved visual treatment:

- a clear Details heading;
- a file-type hero icon or existing secure image preview;
- the selected item name;
- aligned metadata rows for type, size, modified time, permissions, and writable/protected status;
- no duplicate inline action buttons; operations remain in the shared context menu.

The panel has its own scroll container. Empty, loading, and error states remain inside the panel.

## Toolbar and Breadcrumb Polish

The existing controls remain functionally unchanged but receive consistent SiteIntelix styling:

- compact icon-and-label buttons;
- clear primary treatment for New;
- locally rendered icons;
- aligned search field with a rounded, compact appearance;
- clean breadcrumbs using separators rather than native-looking boxed buttons;
- disabled states for unavailable Back, Forward, or Up operations.

No toolbar function is removed.

## Data Flow and Architecture

The existing secure PHP layer remains authoritative:

1. The current directory request returns escaped/serialized item data.
2. The JavaScript renderer classifies each item, creates the row with DOM APIs, and attaches trusted item data through in-memory state or data attributes.
3. Selection updates the details request and selected-row presentation.
4. Menu invocation resolves permitted actions from the selected item's existing flags and global localized settings.
5. Choosing an action calls the existing operation-specific request path or dialog.
6. Successful mutations refresh the affected view using current behavior.
7. Failures pass through the existing dialog and live-region announcement paths.

No request accepts an arbitrary operation name. Nonces, capability checks, path canonicalization, protected-path checks, and per-operation handlers remain unchanged.

## Failure Handling

- A details failure leaves the item selected and renders an in-panel error without breaking the workspace.
- An action failure closes the menu safely, reports the server-provided safe message, and avoids a false success refresh.
- Empty directories render a contained empty state inside the File List.
- Loading states occupy the relevant pane, not the complete page.
- Unknown file types use the generic icon and remain operable.
- If viewport calculations are unavailable, CSS minimum and maximum heights provide a usable fallback.
- Context-menu positioning must tolerate narrow windows and browser zoom.

## Accessibility

- File rows and menu controls are keyboard reachable.
- Selected rows expose selection state using suitable ARIA state.
- The context menu uses `role="menu"` and items use `role="menuitem"`.
- The three-dot control has an action-specific accessible label that includes the item name.
- Focus rings remain clearly visible.
- Menu opening, selection, errors, and completed operations continue to use the existing live announcer.
- Touch targets remain usable even with compact visual density.
- Reduced-motion preferences disable drawer/menu transitions where applicable.
- Icon color is never the only indicator of type or status.

## Security and Performance

- No external dependencies, CDN imports, image hotlinks, jQuery, React, Vue, or Axios.
- No filename, path, server message, or metadata is inserted through `innerHTML`.
- Inline SVG output uses a fixed plugin-owned source and a narrow `wp_kses` allowlist.
- The context menu is built only from fixed action definitions.
- One reusable context menu replaces dozens of per-row button handlers and DOM nodes.
- Event delegation is preferred for row selection, double-click, context menu, and menu-button activation.
- Resize work is debounced or handled with CSS whenever possible.
- The module continues to load only when enabled.

## Testing

### Structural Tests

Add assertions for:

- the shared File Manager SVG renderer/source;
- explicit inline-SVG sizing and wrapper scoping;
- File Manager icon use in Modules, All Module Settings, and the page header;
- absence of a visible Actions table header;
- presence of context-menu markup and three-dot menu hooks;
- file-icon hooks and trusted category mapping;
- viewport-constrained workspace and independent pane overflow;
- no new dependency or unsafe HTML use.

### JavaScript Unit Tests

Extend the dependency-free File Manager UI tests for:

- file-type category normalization and generic fallback;
- permission-aware context action resolution;
- selected-item transitions;
- double-click/Enter primary-action resolution;
- right-click suppression only for valid rows;
- context-menu edge clamping;
- outside-click and Escape dismissal;
- keyboard menu navigation and focus restoration;
- stale details response protection;
- history, debounce, sort, and order regressions.

### Existing Regression Tests

Run:

- `node --test tests/structural.test.mjs`
- `node --test tests/admin-interactions.test.mjs`
- `node --test tests/file-manager-ui.test.mjs`
- all existing dependency-free PHP File Manager scripts
- the complete current plugin test set used by the branch

### Live WordPress QA

Verify at desktop and narrow widths:

1. File Manager icon appearance on Modules.
2. File Manager and neighboring icon alignment on All Module Settings.
3. File Manager page-header icon appearance.
4. Exact visual comparison with the approved mockup.
5. Long directory behavior without whole-page growth.
6. Independent tree, list, and details scrolling.
7. Single-click selection and detail updates.
8. Double-click and Enter opening.
9. Right-click context menu contents and position.
10. Three-dot, Shift+F10, Menu key, arrow keys, Enter, and Escape behavior.
11. Protected-item action omission.
12. New, Upload, Rename, Trash, Download, editor, dialogs, Backups, Trash, and Settings regressions.
13. Drawer behavior and toolbar wrapping on narrow screens.
14. Browser zoom, focus visibility, reduced motion, loading, empty, and error states.

## Acceptance Criteria

The work is complete when:

- the File Manager folder icon renders consistently in every supplied problem location;
- adjacent settings-tab icons no longer overlap labels;
- the browser visually matches the approved three-pane mockup;
- no row displays Open, Details, Rename, Trash, or similar inline action buttons;
- right-click and the three-dot fallback expose icon-and-name actions appropriate to the item;
- familiar desktop selection, double-click, and keyboard behavior works;
- long file lists scroll inside the available viewport instead of lengthening the WordPress page;
- all three panes scroll independently on desktop;
- narrow-screen drawers remain usable;
- the server security model and operation-specific AJAX handlers are unchanged;
- automated tests and live WordPress QA pass.
