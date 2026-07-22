# Debug Log Wide Trace-Only Design

## Goal

Use the available WordPress admin-page width in the Modern Debug Log viewer and remove the low-value expanded occurrence/details interface.

## Layout Width

The Modern viewer body and its main sections will use the shared SiteIntelix content width of `1440px` instead of the current `1200px` cap. The layout remains centered with the existing responsive gutters. Terminal may retain its current terminal-focused width; this change targets Modern view.

## Trace-Only Expansion

Remove the Occurrence Timeline, its occurrence list, and the disabled `Show more` control.

Remove the expanded footer containing:

- Copy Details
- View Full Log

For grouped Modern cards:

- When a genuine extracted stack trace exists, retain View/Hide and arrow controls.
- Expanding the card shows one full-width Stack Trace panel.
- The Stack Trace panel retains Copy and the validated Open in Editor link when available.
- The complete error remains visible only in the main card.

When no genuine stack trace exists:

- Do not render View/Hide or arrow controls.
- Do not render an empty details container.
- The card remains a compact summary.

Individual ungrouped cards remain compact and do not gain expansion controls.

## Performance

Removing timeline rendering avoids slicing and formatting occurrence collections for expanded panels. No new assets or dependencies are introduced.

## Testing

Automated tests will verify:

- Final Modern width rules use `1440px`.
- Occurrence Timeline and Show-more markup are absent.
- Copy Details and expanded View Full Log controls are absent.
- Details and toggle controls are conditional on non-empty stack trace text.
- Stack Trace uses a single-column full-width layout.

Live browser verification will confirm the wider layout, compact no-trace cards, and full-width trace-only expansion.
