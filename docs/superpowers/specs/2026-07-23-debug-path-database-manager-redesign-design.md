# Debug Path and Database Manager Redesign

## Goal

Improve two SiteIntelix admin interfaces without reversing the plugin's recent performance optimizations:

1. Let the Modern Debug Log source path consume all genuinely available horizontal space before truncating.
2. Make the Database Manager dashboard visually match the approved reference while providing functional, lightweight search, filtering, sorting, page-size, pagination, and row actions.

## Constraints

- Preserve the existing SiteIntelix design tokens and `si-`/`sitx-` component conventions.
- Add no framework, CDN dependency, jQuery dependency, or client-side data-grid library.
- Do not preload all database table metadata into JavaScript.
- Keep database mutations in the existing capability-checked, nonce-protected handlers.
- Preserve the current Dashboard, Tables, and Edit views.
- Keep the interface accessible without JavaScript for core navigation and filtering.
- Do not commit or push changes until the user explicitly requests it.

## Debug Log Path Layout

The Modern Debug Log summary keeps its current four-part structure: severity, message body, time badge, and expand action.

Within the message body:

- The occurrence count remains non-wrapping.
- The source path becomes a flexible element with `flex: 1 1 auto`, `max-width: none`, and `min-width: 0`.
- The metadata row occupies the full message-body width.
- The path truncates with an ellipsis only when the time badge and expand action leave insufficient room.
- The full path remains available through the existing expanded details and a tooltip/title on the compact path.
- Mobile layout retains ellipsis behavior and avoids forcing horizontal page overflow.

## Database Manager Page Structure

### Header and navigation

Keep the existing shared SiteIntelix page header, database icon, description, version badge, table-count badge, and record-count badge.

Render the Dashboard and Tables navigation as a full-width underline tab row matching the reference:

- Active tab uses the SiteIntelix primary color and a two-pixel underline.
- The header and tab row share the same page-width rhythm as the main content.
- Existing URLs and active-view behavior remain unchanged.

### Metric cards

Display four responsive cards:

- Total Tables
- Total Records
- Data Usage
- Index Usage

Each card contains:

- A small colored icon tile.
- A muted label.
- The current computed value.
- A concise static context line such as “Across all tables” or “Database indexes.”

The reference's “this month” deltas will not be copied because the plugin does not store historical snapshots. Showing invented trend values would be misleading and adding a history subsystem would be out of scope and add overhead.

### Dashboard table card

Wrap the dashboard table in a card with:

- Heading: “Database Tables.”
- Accessible information hint explaining that values come from current database metadata.
- Search field.
- Filter/sort control.
- Columns: number, table, records, data usage, index usage, actions.
- Footer controls for page size, result range, and pagination.

The table name links to the existing Tables view. The Actions control exposes a compact menu with a “Browse rows” link to the same view. It does not introduce new mutation handlers.

## Functional Controls

All dashboard controls use GET parameters and ordinary links/forms:

- `db_search`: case-insensitive table-name search.
- `db_orderby`: `name`, `rows`, `data`, or `index`.
- `db_order`: `asc` or `desc`.
- `db_per_page`: restricted to `15`, `30`, or `50`.
- `db_page`: positive page number clamped to the available result range.

Filtering, sorting, and slicing happen in PHP after the existing metadata query. Only the requested slice is rendered into HTML.

The chosen controls persist across pagination and page-size changes. Invalid values fall back to safe defaults:

- Search: empty.
- Sort: records descending.
- Page size: 15.
- Page: 1.

## Performance Design

- Reuse the single existing table-metadata collection as the source for totals and dashboard rows.
- Do not issue a record-count query per visible dashboard row.
- Do not serialize table metadata into JavaScript.
- Render a maximum of 50 dashboard rows per request.
- Use a tiny, page-scoped vanilla JavaScript controller only if needed to open and close action menus; filtering and pagination do not depend on JavaScript.
- Continue loading admin assets only on SiteIntelix screens.

## Accessibility and Responsive Behavior

- Search fields have explicit accessible labels.
- Tabs retain current-page semantics.
- Pagination exposes previous/next labels and the current page.
- Action menus use buttons with `aria-expanded` and close on Escape or outside click if JavaScript is used.
- Keyboard users can reach all actions without relying on hover.
- Metric cards collapse from four columns to two and then one.
- The table scrolls horizontally on narrow screens without widening the WordPress admin page.
- Focus states reuse the SiteIntelix focus-ring system.

## Error and Empty States

- An empty database continues to display the existing empty-state component.
- A search with no matches displays a specific “No tables match your search” message and a clear-filter link.
- Existing row-save, row-delete, and error notices remain unchanged.
- If the requested dashboard page becomes invalid after filtering, it is clamped to the last available page.

## Testing

Add structural tests before production changes to verify:

- The Debug Log path has no fixed 520-pixel cap and uses flexible width.
- The dashboard exposes the approved metric-card structure.
- Search, sort, page-size, and pagination parameters are sanitized and bounded.
- The rendered table includes an Actions column and links to the existing Tables view.
- The implementation adds no framework or external asset dependency.

Run:

- The new focused structural tests through a failing-then-passing TDD cycle.
- The complete SiteIntelix Node structural suite.
- PHP syntax checks for the Database Manager module and Debug Log view.
- JavaScript syntax checks for any modified page-scoped controller.
- ZIP regeneration only after the user requests a new installable archive.

## Out of Scope

- Historical database growth tracking.
- AJAX/live filtering.
- Client-side data grids.
- New row mutation operations.
- Schema changes.
- Git/SVN commits or pushes.
