# Debug Log Modern Cleanup and Editor Links Design

## Goal

Simplify the Modern Debug Log interface, make log messages easier to read, expose validated native WordPress editor links in all viewers, and avoid repeating the primary error inside the expanded stack-trace area.

## Modern Filter Cleanup

Remove these controls and their JavaScript behavior:

- More Filters
- Clear Filters
- Saved Filters

Keep the search field, Type, File, Time, and Plugin selectors. Keep Group Similar Logs, Hide Deprecated, and Show Only Critical visible in the checkbox row without a collapsible advanced-filter state.

## Log Message Typography

Modern log messages keep the monospace font and full wrapping behavior but use normal `font-weight: 400`. Severity labels, occurrence counts, headings, and metadata retain their existing emphasis.

## Native Editor Links in Every Viewer

Reuse `SITEINTELIX_Editor_Links::get_link()` everywhere:

- Classic: retain the current linked file column.
- Modern grouped cards: link the dedicated file-path metadata when the resolver returns a valid plugin or theme editor URL.
- Modern individual cards: apply the same behavior.
- Terminal: split the rendered message around the parsed file reference and link only the exact plugin/theme path plus line portion.

Every editor link opens in a new tab with `target="_blank"` and `rel="noopener noreferrer"`. WordPress core, WP-CLI PHAR paths, missing files, and other unsupported paths remain plain text.

## Stack Trace Deduplication

The main Modern card remains the single place that displays the complete error message.

For expanded grouped entries:

1. Split the complete parsed message into physical lines.
2. Remove the first line because it is the primary error already displayed in the card.
3. Retain only genuine trace continuation content:
   - `Stack trace:`
   - numbered frames beginning with `#`
   - `thrown in`
   - multiline continuation lines belonging to a retained trace section.
4. If no genuine trace content remains, omit the Stack Trace panel entirely.
5. If no trace panel exists, let the Occurrence Timeline use the available width.

The disabled placeholder `Open in Editor` button inside the stack panel will be replaced by the same validated native editor link when available; otherwise it will not render.

## Performance and Safety

- Reuse the request-local caches in `SITEINTELIX_Editor_Links`.
- Resolve links only for entries rendered on the current page.
- Add no dependency and no custom editor.
- Keep all existing capability, file-edit, canonical-path, and ownership validation.
- Remove obsolete JavaScript branches for deleted filter controls.

## Testing

Automated tests will verify:

- Removed filter controls and obsolete JavaScript hooks are absent.
- Checkbox filters remain visible.
- Modern messages have normal font weight.
- Classic, Modern grouped, Modern individual, and Terminal render validated editor links.
- All links open in a new tab with safe `rel` attributes.
- Core paths remain plain text through the existing resolver tests.
- The primary error is absent from Stack Trace output.
- Trace panels render only when real trace continuation lines exist.

Live browser verification will cover Modern filter layout, normal message weight, editor-link presence on a real plugin error, absence of links on core errors, and stack-panel suppression for single-line errors.
