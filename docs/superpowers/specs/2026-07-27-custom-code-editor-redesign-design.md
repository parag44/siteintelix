# Custom Code Editor Redesign

## Goal

Redesign the Custom CSS & JS and Code Snippets add/edit screens so they use the SiteIntelix visual system, remain easy to scan, and work well at desktop and mobile widths. Remove the currently exposed Code Snippets import/export workflow without deleting its internal transfer implementation.

## Scope

### Custom CSS & JS editor

- Add a header action that returns to the Custom CSS & JS list.
- Place the title and code editor inside one focused primary card.
- Keep the code-only guidance immediately below the editor.
- Place code type, scope, location, loading method, priority, and description inside a clearly titled settings card.
- Place Save and Save & Enable in a distinct action area at the bottom of the sidebar.

### Code Snippets editor

- Add a header action that returns to the Code Snippets list.
- Place the name and PHP editor inside one focused primary card.
- Keep PHP-tag and syntax guidance immediately below the editor.
- Render validation and saved runtime errors within the editor card.
- Place scope, priority, tags, and description inside a clearly titled settings card.
- Place Save and Save & Activate in a distinct action area at the bottom of the sidebar.

### Import/export

- Remove the Import action from the Code Snippets list header.
- Remove export-format and Export Selected controls from the list bulk toolbar.
- Stop registering the import admin page and import/export `admin-post.php` handlers.
- Remove the import rendering and request-handling methods from the admin controller.
- Keep `class-siteintelix-snippets-transfer.php` and the existing import view dormant for possible future reactivation.

## Layout and visual behavior

- Use the existing `si-card`, `si-button`, spacing, border, color, and typography tokens.
- Use a two-column editor workspace: a flexible main editor column and a 320-pixel settings sidebar.
- The sidebar may remain visible with sticky positioning on wide screens.
- Labels sit above controls; every input and select fills its card width.
- The code editor has a defined minimum height, rounded border, and no unstyled page-level fields.
- On screens below 900 pixels, stack the settings sidebar below the editor.
- On WordPress mobile widths, header and action buttons become full width without horizontal overflow.

## Behavior and data

- Existing form action names, nonces, field names, save modes, validation, redirects, and repository behavior remain unchanged.
- Existing saved CSS, JavaScript, and PHP snippets remain unchanged.
- CodeMirror initialization remains unchanged.
- Removing import/export exposure does not affect ordinary snippet creation, editing, activation, deactivation, duplication, deletion, or run-once execution.

## Accessibility and safety

- Every form control has an explicit label.
- Cards and action areas have descriptive headings.
- Back actions are links, while mutations remain buttons.
- Existing capability and nonce checks remain in place.
- Error messages remain escaped and visible near the editor they describe.

## Verification

- Structural tests confirm both editor templates use the focused card/sidebar layout and SiteIntelix buttons.
- Structural tests confirm import/export controls, routes, and handlers are no longer registered.
- PHP syntax checks cover both templates and the snippets admin controller.
- The complete SiteIntelix structural and PHP runtime suites must pass.
- No ZIP archive is created or updated for this change.
