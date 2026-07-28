# SiteIntelix Performance and UX Redesign

## Status

Approved in conversation on 2026-07-22. The selected visual direction is an evolutionary redesign: preserve the SiteIntelix identity, URLs, module concepts, and WordPress permissions while modernizing hierarchy, asynchronous behavior, accessibility, and responsive layouts.

No Git or SVN commit, push, or deployment is authorized as part of this work.

## Goals

1. Remove avoidable work from normal frontend and WordPress admin requests.
2. Prevent remote diagnostics and large email bodies from blocking initial page rendering.
3. Reduce CSS, JavaScript, localized-data, HTML, and database-query costs per SiteIntelix screen.
4. Make settings, dialogs, notices, filters, bulk actions, loading states, and responsive layouts accessible and predictable.
5. Preserve existing plugin options, admin URLs, action names, permissions, debug-log parsing behavior, and module semantics.

## Non-goals

- Replacing WordPress admin with a single-page application.
- Changing the SiteIntelix public name or module IDs.
- Requiring third-party JavaScript or CSS frameworks.
- Deleting existing email logs, settings, debug logs, or diagnostic data during migration.
- Changing SMTP, debug-capture, maintenance-mode, or Safe Mode behavior except where loading and UI wiring are optimized.

## Architecture

### Module registry and runtime loading

`SITEINTELIX_Modules` will memoize the filtered module registry, the validated enabled-module list, and an associative enabled lookup for the lifetime of one PHP request. Saving or toggling modules will refresh those request-local caches immediately.

Plugin bootstrap will distinguish runtime modules from admin-only modules. Email capture, SMTP, Maintenance Mode, and debug capture continue loading when their frontend/runtime hooks are required. Cron Events, Database Manager, Download Manager, Transients Manager, Server Diagnostics, and their admin interfaces load only for relevant admin requests. Safe Mode's public isolation remains in its MU bootstrap; its management class is admin-only.

### Diagnostics collection

Local, inexpensive diagnostics render immediately. Expensive sections—remote HTTP checks, loopback/REST checks, temporary filesystem mutation, and database-size/autoload aggregation—are cached independently with short expirations. The diagnostics screen exposes a nonce-protected refresh action that invalidates/refreshes these caches.

Independent HTTP checks run concurrently through the WordPress Requests layer with bounded timeouts. A failed check returns a structured row-level error while other sections remain usable. When refresh fails, stale cached data remains visible with its collection timestamp and a retry control.

The Overview consumes cached health data and never performs a synchronous loopback request during normal page rendering.

### Email Log

The listing query selects only metadata required by the table: ID, status, sent date, recipient, subject, and a bounded error summary. Full message, headers, attachments, and error content are fetched only after an administrator opens a preview.

A nonce-protected AJAX endpoint accepts one positive integer log ID, requires `manage_options`, and returns one normalized preview record. The browser caches successful preview payloads for the current page session. The existing sandboxed iframe remains sandboxed and does not receive script privileges.

Retention cleanup is removed from the `wp_mail()` success/failure request path. Activation and settings changes schedule a SiteIntelix retention event. Cleanup is throttled to at most hourly, deletes expired/overflow rows in bounded batches, and safely reschedules until the table is within policy. Deactivation unschedules the event without deleting logs.

The owned email table gains an index suited to status-filtered chronological listings. Schema changes run through the existing activation/migration pattern.

### Assets and localization

The current shared admin bundle becomes a small core containing common buttons, clipboard helpers, accessible notices/toasts, confirmation dialog infrastructure, and shared design tokens/components. Screen-specific behavior and styles move to assets for Overview/Modules, Settings, Email Log, Cron Events, Transients Manager, Safe Mode, Database Manager, Debug Log, and Server Diagnostics as appropriate.

Asset enqueueing uses exact SiteIntelix screen IDs/hooks and passes each bundle only the nonces and translations it consumes. Core WordPress JavaScript internationalization replaces the diagnostics arrays that currently generate count strings for every number from 0 through 200.

No CDN or third-party runtime dependency is introduced.

## UI and interaction design

### Visual hierarchy

The current brand colors and component vocabulary remain. Page headers become more compact and task-focused. Primary content occupies the main workspace; health/status summaries and secondary actions use compact supporting regions. Existing admin URLs and WordPress menu placement remain familiar.

### Settings

Module settings use a complete tabs pattern:

- Every tab has a stable ID, `aria-controls`, `aria-selected`, and roving `tabindex`.
- Every panel has `role="tabpanel"`, `aria-labelledby`, and the `hidden` attribute when inactive.
- Left/Right, Home/End, Enter/Space, click, URL hash, and saved-session restoration are supported.
- Search results expose a visible count and a no-results state and never leave an empty active panel without explanation.

### Email preview and confirmation dialogs

Email preview becomes a reusable accessible modal dialog with:

- A loading state, error message, retry button, and loaded state.
- Focus moved into the dialog on open, trapped while open, and restored to the invoking button on close.
- Escape and backdrop close behavior.
- Semantic HTML/source tabs with keyboard navigation and correct panel visibility.
- The existing sandboxed HTML preview and a plain-text/source view.

Test-email input and destructive confirmations use accessible in-page dialogs instead of `window.prompt()` and `window.confirm()`.

### Notices and feedback

SiteIntelix no longer removes all WordPress notice callbacks. Notices are collected or presented in a collapsible "WordPress notices" region so critical update and security information remains available without dominating the workspace.

Success toasts use `role="status"` and polite announcements. Error feedback uses `role="alert"` and persists inline when an action cannot be completed. Automatically dismissed success messages can be manually dismissed and honor reduced-motion preferences.

### Overview, privacy, and responsive behavior

Health explanations are visible in expandable details or linked descriptions rather than hover-only `title` attributes. Overview exports are redacted by default and clearly label what is omitted. An explicit administrator-only reveal/export path may expose full local values only after confirmation.

Large tables retain semantic table markup and deliberate horizontal overflow on medium screens. On narrow screens, nonessential columns collapse while row identity, status, and actions remain reachable. Bulk selection and action status are announced to assistive technology.

## Data contracts

Diagnostics cache entries contain a schema version, collection timestamp, stale flag, rows, and optional section error. Email preview responses contain only `id`, `subject`, `status`, `sentAt`, `to`, `headers`, `attachments`, `message`, and `error`, with all fields normalized to scalar display strings.

AJAX errors use WordPress JSON error responses with a stable machine code and localized user-facing message. Unauthorized and invalid requests do not reveal whether a log record exists.

## Error handling

- Diagnostics refresh never blanks previously rendered results.
- One failed remote check does not suppress successful diagnostic sections.
- Email preview failures keep the dialog open and offer Retry and Close.
- Clipboard failures expose selectable fallback content.
- Module-toggle failures restore the previous control state and show persistent error feedback.
- Retention failures leave records intact and retry on the next scheduled run.

## Compatibility and migration

Existing option names, module IDs, menu slugs, admin-post action names, and debug-log paths remain unchanged. New cache keys and schema-version options use the `siteintelix_` prefix. Email table migrations are idempotent. Old pages remain functional with JavaScript disabled where practical, including forms, exports, logs, and diagnostic fallback tables.

The minimum supported versions remain WordPress 5.8 and PHP 7.4 unless an existing project requirement changes separately.

## Testing strategy

Every behavioral change follows red-green-refactor:

1. Add failing structural or focused PHP/JavaScript tests.
2. Run the targeted test and confirm the expected failure.
3. Implement the smallest production change.
4. Run the targeted test and the full existing suite.

Coverage includes module memoization/invalidation, request-context loading, diagnostics cache/stale/error behavior, parallel-request normalization, compact localization data, email metadata queries, preview authorization/normalization, retention throttling/batching/scheduling, exact asset scoping, accessible settings tabs, modal focus and retry behavior, accessible notices/toasts, redacted exports, responsive markup invariants, PHP syntax, parser behavior, editor links, and runtime smoke behavior.

## Acceptance criteria

- Overview renders without a synchronous HTTP loopback.
- Server Diagnostics can render cached/local results before remote checks finish and can be refreshed explicitly.
- Email Log list HTML contains no full message bodies, headers, or attachments.
- Email logging no longer performs retention `COUNT`/delete queries for every sent message.
- Module registry/filter evaluation occurs once per request unless invalidated by a save.
- Admin-only module classes are not included on ordinary frontend requests.
- Each SiteIntelix screen loads only shared core assets plus its required screen assets.
- Diagnostics no longer localizes 0–200 count arrays.
- Settings and email-preview tabs satisfy keyboard and ARIA behavior described above.
- Email preview and confirmation dialogs trap/restore focus and expose loading/error states.
- WordPress notices are available rather than globally removed.
- Overview exports are redacted by default.
- Existing automated tests and new regression tests pass, and every plugin PHP file passes `php -l`.
- No Git/SVN commit or push is performed.
