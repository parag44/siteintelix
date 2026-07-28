# Server Diagnostics Lightweight Enhancement

**Status:** Proposed for implementation
**Date:** 2026-07-23
**Target:** SiteIntelix Server Diagnostics WordPress admin page
**Reference:** User-supplied compact Server Diagnostics mockup

## Objective

Refine the existing health-first Server Diagnostics page into a compact, scan-friendly dashboard that closely follows the supplied visual reference while preserving the plugin's current diagnostic logic, cached collection model, permissions, report redaction, exports, and AJAX refresh behavior.

The implementation must reduce vertical scrolling, prioritize actionable results, remain usable from mobile through wide desktop layouts, and avoid adding a frontend framework, new diagnostic queries, or fake remediation actions.

## Functional Boundaries

The following behavior remains unchanged:

- The existing PHP report is the source of truth.
- Existing PHP, extensions, filesystem, network, WordPress, and database collectors remain unchanged.
- Expensive filesystem, network, and database results retain their current cache and explicit refresh behavior.
- The existing `siteintelix_refresh_server_diagnostics` AJAX action, nonce, capability checks, and response remain unchanged.
- JSON and text exports retain their current URLs, capability checks, nonce protection, redaction, and report generation.
- Copy System Info continues to use the current redacted report.
- No saved settings, diagnostic values, hooks, API requests, or permission requirements change.

The UI will not introduce working-looking “Fix” or “Install” buttons without corresponding safe server-side handlers. Rows without a genuine action will expose concise details and guidance through progressive disclosure.

## Chosen Architecture

The redesign is a lightweight enhancement of the current server-rendered implementation.

- PHP renders the page shell, dynamic summary values, toolbar controls, category metadata, utility actions, safe no-JavaScript fallback, and encoded section payloads.
- Section payloads continue to be parsed only when filtering, searching, or opening that section requires them.
- Vanilla JavaScript manages accordion state, filtering, search, Hide Passed, sorting, row limits, row details, responsive action menus, copying, and refresh feedback.
- One delegated page-level interaction handler will be preferred for repeated dynamic row controls.
- The existing large-section virtualization safeguard remains available, but the normal presentation initially renders only a bounded set of relevant rows.
- Existing `si-` design tokens and `sitx-` component naming remain in use.
- No jQuery, React, Vue, Axios, CDN, external font, or new package is introduced.

## Page Header

The header remains aligned to the same centered maximum-width container as the page content.

It contains:

- Existing SiteIntelix diagnostics icon.
- “Server Diagnostics” title.
- Short server/PHP/extensions/filesystem/network/WordPress/database subtitle.
- Version badge.
- Dynamic health badge.
- Refresh action.
- Compact Export menu containing JSON and text report downloads.
- Support Report action.

At wide desktop widths these appear on the right in one compact action group. At narrower widths secondary actions move into a native disclosure/overflow menu so they do not wrap into a tall header. All existing export and support destinations remain real links.

## Health Summary

The summary becomes one compact five-item row:

1. Overall health score with a CSS circular progress indicator.
2. Critical issues.
3. Warnings.
4. Passed checks.
5. Total checks.

All values are derived from the existing dynamic report summary. Cards use equal visual height, 12–16px padding, subtle borders, no decorative shadow, concise uppercase labels, prominent numbers, and minimal supporting copy.

The health card contains the score ring plus a short status label derived from the score and issue counts. Status is communicated with text and iconography as well as color.

On tablet the cards use a compact responsive grid. On mobile they become a two-column grid with the health card spanning the available width; horizontal page overflow is not allowed.

## Toolbar

The toolbar sits directly below the summary and remains compact. It contains:

- Search input.
- All, Issues, Warnings, Passed, and Information filters with live dynamic counts.
- Hide Passed checkbox.
- Sort select with Severity, Check Name, and Status options.
- Collapse All and Expand All controls.

Search and status filters continue to combine. Hide Passed applies in addition to the active status filter, except that explicitly choosing Passed clears or overrides Hide Passed so the requested results remain visible.

Sorting is stable and applies within each category. Severity remains the default, ordering critical, warning, information, and passed rows while preserving report order among equal statuses.

Current search, filter, Hide Passed, and sort state remains active while accordions open or close. The toolbar uses a sticky position only when sufficient viewport height and width make it helpful; it must not cover WordPress admin chrome.

## Category Accordions

The available categories remain the real report categories:

- Extensions
- Filesystem
- Network
- WordPress
- PHP & Server
- Database

Security is not fabricated because the current report has no Security collector.

Categories continue to sort by highest contained severity and retain report order among equal severities. Only the first category in this severity-sorted order opens by default. Other categories remain collapsed.

Each compact accordion header shows:

- Category icon and name.
- Total check count.
- Critical, warning, and passed counts when non-zero.
- A small completion/health progress bar.
- Accessible expand/collapse chevron.

The entire header is one semantic button with stable `aria-expanded` and `aria-controls` relationships. Keyboard behavior remains native.

## Dense Diagnostics Table

Expanded categories use a semantic, compact table on desktop with:

- Status
- Check
- Current
- Recommended
- Description
- Action

Rows target a 44–52px collapsed height. Status uses a small labeled icon/dot rather than a large badge. Long values wrap inside their cells without causing page-level horizontal scrolling.

The Action column contains a compact Details control. Expanding it inserts or reveals a detail row containing the full description, current value, recommended value when available, and relevant guidance already present in the report. No unsafe server mutations or invented documentation URLs are added.

On tablet, the description column becomes visually hidden from the collapsed row and remains available in details. On mobile, each row becomes a compact stacked layout that keeps status, check, current value, and Details visible; recommended values and descriptions move into the expanded detail area.

The no-JavaScript fallback remains a fully readable server-rendered table.

## Row Limits and Progressive Disclosure

Each opened category initially shows up to ten matching rows.

- Critical and warning rows appear before informational and passed rows under default severity sorting.
- A “Show all N checks” control appears when more matching rows exist.
- “Show fewer” restores the initial ten-row view.
- Search, filter, Hide Passed, or sort changes reset the category to the bounded view so the result remains predictable.
- Opening a row detail renders only that detail content.

For exceptionally large categories, the existing virtualization safeguard may be retained when the user explicitly expands the full result set.

## Utility Actions

A narrow right sidebar on wide desktop contains:

- Quick Actions.
- Export Report.
- Copy System Info.
- Create Support Report.
- Documentation.
- Need Help.
- Existing privacy explanation where space permits.

The sidebar uses the real existing URLs and copy source and may remain sticky within the viewport. It must not reduce the main table below a usable width.

At medium and mobile widths, the sidebar becomes a compact native “Page actions” disclosure placed above or below the category list. This uses the same underlying links and button rather than duplicating action logic.

## Interaction and State

- One highest-severity category opens automatically on initialization.
- Filtering and search update category counts and visible results immediately.
- Empty global results show the existing accessible no-results state and Reset Filters control.
- Failed JSON parsing leaves the affected category visible with the existing section error message.
- Refresh disables only the refresh control, announces progress, preserves the previous report on failure, and reloads after success as it does currently.
- Copy actions provide nonblocking `aria-live` feedback.
- Print expands readable report content and excludes interactive controls and the sidebar.

No filter preferences are saved to the WordPress database. Interaction state lasts for the current page session only.

## Visual System

The page uses the established SiteIntelix light admin system:

- White surfaces on a very light gray canvas.
- Subtle 1px borders.
- 8–10px card radii and 6–8px control radii.
- Minimal shadow.
- Blue primary actions.
- Green success, amber warning, red critical, and neutral blue/gray information.
- 16–20px major page gaps.
- 12–16px card and table padding.
- Compact type hierarchy without excessive bold text.

The implementation will align the header, summary, toolbar, diagnostic layout, and sidebar to one centered `1440px` maximum-width container, consistent with the recently optimized Database Manager page.

## Accessibility

- Accordion headers and row Details controls use semantic buttons.
- Desktop diagnostic results use semantic tables and header scopes.
- Focus indicators remain visible and meet the existing high-contrast treatment.
- Status is conveyed by icon/dot and text, not color alone.
- All disclosure controls expose `aria-expanded` and `aria-controls`.
- Search/filter result changes and copy/refresh results use polite live regions.
- Touch targets remain at least 40px desktop and 44px on touch-oriented layouts where practical.
- Motion respects `prefers-reduced-motion`.
- Heading order and main/aside landmarks remain logical.

## Performance Guardrails

- No new diagnostic collection occurs during filtering, sorting, accordion, or row-detail interactions.
- No new AJAX endpoint or per-row request is introduced.
- Section payloads remain encoded once from the report already collected for the page.
- Filtering and sorting operate on normalized in-memory data.
- Rendering is bounded to ten rows per open category until Show All is requested.
- Repeated interactions use event delegation where practical.
- Existing dedicated diagnostics CSS and JavaScript remain scoped to the diagnostics screen.
- No framework, charting library, icon library, or external stylesheet is added.

## Files Expected to Change

- `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`
  - Refine server-rendered header, summary, toolbar, category metadata, and utilities.
  - Preserve collection, cache, refresh, export, and redaction logic.
- `assets/admin/js/siteintelix-server-diagnostics.js`
  - Add lightweight toolbar state, default accordion, sorting, bounded rows, row details, and responsive action behavior.
- `assets/admin/css/siteintelix-server-diagnostics.css`
  - Implement the compact mockup-aligned layout and responsive table/stacked-row styling.
- `siteintelix.php`
  - Add only translations required by new controls and announcements.
- `tests/server-diagnostics-ui.test.mjs`
  - Cover new interaction and rendering behavior.
- `tests/structural.test.mjs`
  - Protect the server-rendered, dependency-free, accessible UI contract.

No new production files are required unless implementation shows a repeated PHP fragment whose extraction materially improves readability without increasing runtime loading.

## Verification and Acceptance Criteria

- Dynamic values and real categories match the existing report; no mock values are introduced.
- Existing refresh, JSON export, text/support export, and copy actions work unchanged.
- Header actions align with content and collapse into an overflow disclosure at narrower widths.
- Five compact summary items include Total Checks and a circular health indicator.
- Toolbar provides search, five status filters, Hide Passed, sort, and accordion controls.
- Only the highest-severity category opens initially.
- Expanded categories use dense desktop tables and compact mobile stacked rows.
- Initial category rendering is limited to ten matching rows with Show All/Show Fewer.
- Row Details disclosure exposes full information without fake remediation actions.
- Layout has no page-level horizontal overflow at 1440px, 1024px, 768px, and 390px.
- Keyboard interaction, visible focus, ARIA relationships, and live feedback remain functional.
- Structural Node tests, diagnostics UI tests, PHP lint, runtime smoke tests, and relevant parser tests pass.
- No Git or SVN commit or push is performed.
