# Server Diagnostics Redesign

**Status:** Approved prototype  
**Date:** 2026-07-21  
**Target:** SiteIntelix Server Diagnostics WordPress admin page  
**Theme:** Light, WordPress admin compatible

## Objective

Replace the current dense diagnostic tables with a health-first dashboard that lets an administrator identify critical problems in five seconds while preserving every diagnostic value through progressive disclosure.

The redesign must reduce initial on-screen diagnostic rows by at least 60%, prioritize errors and warnings, remain usable from 390px mobile widths through 1440px desktop widths, and meet WCAG AA interaction and contrast requirements.

## Approved Direction

The approved direction is the **Health-first command center**. It uses a prominent overall health card, three supporting KPI cards, a sticky filter/search bar, issue-first accordion sections, and a compact utility sidebar.

The visual language is intentionally restrained: white cards on `#F8FAFC`, fine `#E5E7EB` borders, small status badges instead of tinted panels, 8px-based spacing, and limited elevation on hover.

## Information Architecture

The page presents information in this order:

1. Page title and primary report actions.
2. Overall health score, critical issue count, warning count, and passed count.
3. Filters, instant search, and expand/collapse controls.
4. Diagnostic category accordions, ordered by severity before healthy sections.
5. Compact support, export, documentation, and privacy utilities.

This order remains consistent on desktop, tablet, and mobile. The sidebar moves below diagnostics on mobile rather than interrupting the issue flow.

## Summary Cards

Four cards display:

- Overall Health Score, formatted as a score out of 100 with a compact progress ring and one-line status summary.
- Critical Issues in red.
- Warnings in amber.
- Passed Checks in green.

The health card is approximately 1.45 times the width of each supporting KPI on desktop. Tablet uses a two-column summary with the health card spanning two rows. Mobile places health full width followed by a two-column KPI grid.

When all checks pass, the standard issue list is replaced by a centered success state with a large green diagnostic illustration, “Everything looks healthy,” and “No action required.” Utilities remain available.

## Filter and Search Bar

The filter bar provides clickable filters for All, Issues, Warnings, Passed, and Information, each with a count. It also includes an instant search input, Collapse All, and Expand All.

The filter bar remains sticky beneath the summary cards on desktop. Below 768px, filter tabs become horizontally scrollable, search occupies a full second row, and Collapse All and Expand All become labeled menu actions.

Filtering and search operate together. Search matches section name, issue/check name, value, recommendation, and description. Empty search results show a concise “No diagnostics match your filters” state with a reset action.

## Diagnostic Accordions

Each category is a single accordion card containing:

- Category icon.
- Category name.
- Status badge.
- Severity-aware summary such as “4 issues · 18 passed checks.”
- Expand/collapse arrow.

Sections are sorted by highest contained severity, then retain their existing report order within equal severity. The available categories are PHP & Server, Extensions, Filesystem, Network, WordPress, and Database.

The accordion header is one semantic button with `aria-expanded` and `aria-controls`. Enter and Space toggle it. The arrow rotates during a 160–200ms height/opacity transition. Reduced-motion preferences disable nonessential animation.

## Expanded Section

An expanded section renders errors first, warnings second, information third, and passed checks last. Each problematic item contains:

- Issue name.
- Status badge.
- Current value.
- Recommended value when available.
- Plain-language description.
- Optional fix or documentation action when a safe destination exists.

Passed checks remain hidden behind a “Show N passed checks” disclosure. Information-only checks use a separate subordinate disclosure whenever a section contains more than ten information rows.

On mobile, item fields stack as labeled cards instead of using horizontal tables. Long values wrap or scroll within their own code-value container and never force page-level horizontal scrolling.

## Utility Sidebar

The right rail contains compact widgets for:

- Need Help / Create Support Report.
- Export Report.
- Copy System Info.
- Documentation.
- Privacy Notice.

The sidebar is sticky on wide desktop layouts. It becomes a two-column utility area on tablet when space permits and moves below the accordions on mobile. Exported and copied data continue to use the existing redacted report source.

## Component Library

### Color Tokens

- Canvas: `#F8FAFC`
- Surface: `#FFFFFF`
- Border: `#E5E7EB`
- Primary: `#2563EB`
- Success: `#22C55E`
- Warning: `#F59E0B`
- Danger: `#EF4444`
- Primary text: `#111827`
- Secondary text: `#64748B`

Status meaning never relies on color alone. Every badge includes a label and compact dot/icon.

### Sizing and Typography

- Base spacing unit: 8px, with 4px reserved for fine internal adjustments.
- Card radius: 12px.
- Control radius: 7px.
- Page heading: 22–24px, semibold/bold.
- Section heading: 14–16px, semibold/bold.
- Body: 14px with approximately 1.5 line height.
- Metadata: 11–12px.
- Interactive targets: minimum 40px desktop and 44px touch where practical.

### Shared Components

- KPI card and health progress ring.
- Status badge for Passed, Warning, Error, and Information.
- Filter tab with count.
- Search field.
- Accordion section header.
- Diagnostic issue item.
- Passed-check disclosure.
- Primary, secondary, and text buttons.
- Utility widget.
- Healthy, no-results, and loading states.

All components use the existing `si-` token system and `sitx-` class namespace when implemented.

## Data and Rendering Model

The existing PHP report remains the source of truth. A presentation layer derives section totals, highest severity, overall counts, and the health score without changing the diagnostic checks themselves.

The server renders summary metadata and collapsed accordion shells. Detailed row markup is deferred until a section is expanded. Each section’s serialized row data is emitted in a safely encoded JSON payload and rendered by dependency-free vanilla JavaScript.

If a section contains more than 200 rows, the expanded list uses windowed rendering with a small overscan buffer. Sections at or below 200 rows render normally. Search indexes normalized text once on page initialization, then filters without network requests.

If JavaScript fails, the page must retain a usable server-rendered fallback, such as native disclosure elements or fully rendered sections without enhanced filtering.

## Accessibility

- All interactive elements are reachable and operable by keyboard.
- Focus uses a visible blue ring with at least 3px visual thickness.
- Accordion relationships use `aria-expanded`, `aria-controls`, and stable IDs.
- Search and icon-only controls have explicit accessible names.
- Status counts and filter changes are announced through a polite live region.
- Text and essential controls meet WCAG AA contrast.
- Color is supplemental to labels and icons.
- Motion respects `prefers-reduced-motion`.
- Heading order and landmarks remain logical.

## Responsive Behavior

### Desktop, 1440px

Four summary cards appear in one row. Diagnostics use a flexible main column with a 228–260px sticky utility rail. Summary and filter controls remain sticky without covering WordPress admin chrome.

### Tablet, approximately 768–1024px

Summary cards use two columns. The diagnostic and utility layout remains two columns only when adequate content width is available; otherwise utilities move below. Filter controls wrap without reducing touch targets.

### Mobile, 390–767px

The health card is full width, supporting KPIs use two columns, accordions remain single-column, issue fields stack, and utilities move below content. Collapse All and Expand All move into a labeled “Section controls” menu; other action labels remain visible.

## Error and Edge States

- Failed diagnostic collection shows a compact error card with the affected section and a retry/reload action.
- Missing recommended values omit that field instead of displaying an empty placeholder.
- Extremely long values wrap within bounded code blocks.
- Zero results provide a reset-filter action.
- A perfect health result uses the dedicated healthy state.
- Copy actions provide nonblocking success or failure feedback in an `aria-live` region.

## Verification and Acceptance Criteria

- Initial collapsed view displays at least 60% fewer diagnostic rows than the current table view.
- Critical and warning counts and their affected sections are visible without scrolling at 1440px.
- All existing diagnostic values remain reachable.
- Filters and search update immediately and work in combination.
- Accordion and disclosure controls work with mouse, touch, Enter, and Space.
- Focus remains visible and follows a logical order.
- Layout is verified at 1440px, 1024px, 768px, and 390px.
- No page-level horizontal overflow occurs.
- The structural Node test suite continues to pass.
- Manual PHP diagnostic scripts continue to run without regressions.
- No external framework, CDN, jQuery, React, Vue, or Axios is introduced.

## Prototype Artifacts

The approved visual companion session contains:

- `layout-directions.html` — three information architecture options.
- `desktop-high-fidelity.html` — approved 1440px desktop direction.
- `responsive-and-components.html` — approved tablet, mobile, and component library views.

These artifacts are visual specifications, not production code.
