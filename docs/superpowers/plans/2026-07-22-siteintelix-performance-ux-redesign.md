# SiteIntelix Performance and UX Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Do not commit or push to Git/SVN.

**Goal:** Reduce SiteIntelix request, database, HTML, and asset costs while delivering the approved evolutionary admin redesign and accessible interactions.

**Architecture:** Memoize module state and load admin-only modules by context; cache and asynchronously refresh expensive diagnostics; query lightweight email metadata and fetch previews on demand; schedule bounded retention cleanup; split the monolithic admin bundle into shared and screen-specific assets. Preserve existing URLs, options, permissions, module IDs, and no-JavaScript fallbacks.

**Tech Stack:** WordPress 5.8+, PHP 7.4+, vanilla JavaScript, WordPress AJAX/admin-post APIs, WordPress Requests and i18n packages, custom CSS design tokens, Node.js built-in test runner.

---

## File structure

**Modify**

- `siteintelix.php` — context-aware class loading, exact screen asset manifest, AJAX registration, diagnostics localization.
- `includes/class-siteintelix-modules.php` — request-local registry/enabled caches and invalidation.
- `includes/class-siteintelix-system-info.php` — cached Overview health input with no synchronous REST request.
- `includes/class-siteintelix-migrations.php` — email schema/index migration and scheduled-event migration.
- `includes/modules/email-log/class-siteintelix-email-log-module.php` — metadata listing, preview endpoint, retention scheduling/batches, accessible modal markup.
- `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php` — section caches, concurrent HTTP checks, refresh endpoint, stale metadata.
- `admin/views/admin-page.php` — redacted data island/export and visible health descriptions.
- `admin/views/settings-page.php` — complete tabs markup and search status.
- `assets/admin/css/siteintelix-admin.css` — retain shared tokens/components; remove moved screen rules.
- `assets/admin/js/siteintelix-admin.js` — retain shared accessible primitives only.
- `assets/admin/js/siteintelix-server-diagnostics.js` — WordPress i18n, refresh/stale states.
- `assets/admin/css/siteintelix-server-diagnostics.css` — refresh/stale/error states.
- `uninstall.php` — clear new scheduled hooks/cache options.
- `tests/structural.test.mjs` — bootstrap, asset, markup, privacy, and query invariants.
- `tests/server-diagnostics-ui.test.mjs` — async diagnostics and i18n behavior.
- `tests/runtime-smoke.php` — PHP cache/normalization/scheduling behavior.

**Create**

- `assets/admin/js/siteintelix-overview.js`
- `assets/admin/js/siteintelix-modules.js`
- `assets/admin/js/siteintelix-settings.js`
- `assets/admin/js/siteintelix-email-log.js`
- `assets/admin/js/siteintelix-cron-events.js`
- `assets/admin/js/siteintelix-transients.js`
- `assets/admin/js/siteintelix-safe-mode.js`
- `assets/admin/js/siteintelix-maintenance.js`
- `assets/admin/css/siteintelix-overview.css`
- `assets/admin/css/siteintelix-modules.css`
- `assets/admin/css/siteintelix-settings.css`
- `assets/admin/css/siteintelix-email-log.css`
- `assets/admin/css/siteintelix-cron-events.css`
- `assets/admin/css/siteintelix-transients.css`
- `assets/admin/css/siteintelix-safe-mode.css`
- `tests/admin-interactions.test.mjs`
- `tests/email-log-performance.test.mjs`

## Task 1: Memoize module state and reduce frontend class loading

**Files:** `tests/structural.test.mjs`, `tests/runtime-smoke.php`, `includes/class-siteintelix-modules.php`, `siteintelix.php`

- [ ] Add a structural test asserting `SITEINTELIX_Modules` declares caches for registry, enabled IDs, and lookup and exposes one cache reset method.
- [ ] Add a runtime test that filters `siteintelix_modules`, calls `get_all()` and `is_enabled()` repeatedly, and asserts the filter runs once until `save_enabled()` invalidates the cache.
- [ ] Add structural assertions that admin-only module class paths are guarded by an admin-request predicate while Email Log, SMTP, Maintenance Mode, and debug runtime remain available outside wp-admin.
- [ ] Run `node --test tests/structural.test.mjs` and `php tests/runtime-smoke.php`; confirm the new assertions fail because memoization/context loading is absent.
- [ ] Add typed-by-docblock static properties `$all_cache`, `$enabled_cache`, and `$enabled_lookup_cache`; centralize invalidation; make `is_enabled()` use the lookup map; refresh caches after saves.
- [ ] Add `siteintelix_should_load_admin_modules()` covering `is_admin()`, AJAX/admin-post, WP-CLI, and cron needs without loading admin-only classes on an ordinary frontend request.
- [ ] Run the two targeted tests and confirm they pass.

## Task 2: Make Overview health collection non-blocking and exports private by default

**Files:** `tests/structural.test.mjs`, `tests/runtime-smoke.php`, `includes/class-siteintelix-system-info.php`, `admin/views/admin-page.php`, `assets/admin/js/siteintelix-overview.js`, `siteintelix.php`

- [ ] Add failing tests asserting `SITEINTELIX_System_Info::get_all()` does not call `wp_remote_get`, health data includes a cached/stale marker, the Overview JSON data island omits database username/host/name and admin email, and health descriptions are linked with `aria-describedby` instead of `title`.
- [ ] Run targeted tests and confirm failures point to synchronous REST and unredacted markup.
- [ ] Split local environment gathering from remote REST health; read a short-lived `siteintelix_overview_remote_health` transient and represent unavailable data as stale/unknown.
- [ ] Build an explicit redacted Overview export payload and place only that payload in the JSON data island.
- [ ] Render visible health descriptions/details with stable IDs and remove hover-only `title` reliance.
- [ ] Move Overview copy/export behavior from shared JS into `siteintelix-overview.js` and enqueue it only on the Overview hook.
- [ ] Run targeted tests and verify green.

## Task 3: Cache and asynchronously refresh Server Diagnostics

**Files:** `tests/server-diagnostics-ui.test.mjs`, `tests/runtime-smoke.php`, `includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php`, `assets/admin/js/siteintelix-server-diagnostics.js`, `assets/admin/css/siteintelix-server-diagnostics.css`, `siteintelix.php`

- [ ] Add PHP tests for cache envelopes containing `schema`, `collected_at`, `stale`, `rows`, and optional `error`; stale fallback; explicit invalidation; and row-level failure isolation.
- [ ] Add JS tests for initial cached rendering, refresh loading/disabled state, successful replacement, stale-on-error behavior, Retry, and live-region announcements.
- [ ] Add structural tests rejecting the 0–200 localization loop and requiring `wp-i18n` plus script translations.
- [ ] Run the diagnostics suites and confirm failures.
- [ ] Split diagnostics into local and expensive collectors. Cache expensive filesystem/network/database sections independently and merge envelopes into the existing report format.
- [ ] Implement concurrent remote requests with the WordPress Requests API, a bounded timeout, and normalized per-endpoint results.
- [ ] Register a nonce/capability-protected diagnostics refresh AJAX action that invalidates and recollects expensive sections and returns normalized JSON.
- [ ] Add refresh/stale timestamps, loading skeletons, per-section errors, Retry, and preserved stale content in PHP/JS/CSS.
- [ ] Replace count arrays with `wp-i18n` `_n`/`sprintf`, declare `wp-i18n` as a core dependency, and call `wp_set_script_translations()`.
- [ ] Run targeted diagnostics tests and verify green.

## Task 4: Make Email Log listings lightweight and previews on demand

**Files:** `tests/email-log-performance.test.mjs`, `tests/runtime-smoke.php`, `includes/modules/email-log/class-siteintelix-email-log-module.php`, `assets/admin/js/siteintelix-email-log.js`, `assets/admin/css/siteintelix-email-log.css`, `siteintelix.php`

- [ ] Add structural tests that the listing query names only metadata columns and that rendered preview buttons contain a numeric `data-siteintelix-email-id` but no serialized message/header/attachment payload.
- [ ] Add runtime tests for preview request validation, capability/nonce enforcement adapters, not-found privacy, and normalized scalar preview fields.
- [ ] Add JS tests for one fetch per ID, in-page caching, loading/error/retry states, sandbox preservation, and source/HTML tab switching.
- [ ] Run tests and confirm the expected failures.
- [ ] Replace `SELECT *` with an explicit metadata projection and pre-read date/time formats outside the table loop.
- [ ] Register `wp_ajax_siteintelix_get_email_preview`; validate nonce/capability/ID and return one normalized row without revealing authorization/not-found distinctions.
- [ ] Replace serialized preview attributes with record IDs and build loading/error/loaded modal states.
- [ ] Cache successful records in a page-local map; keep the iframe sandboxed; clear `srcdoc` on close.
- [ ] Run targeted tests and verify green.

## Task 5: Move email retention to bounded scheduled cleanup

**Files:** `tests/email-log-performance.test.mjs`, `tests/runtime-smoke.php`, `includes/modules/email-log/class-siteintelix-email-log-module.php`, `includes/class-siteintelix-migrations.php`, `siteintelix.php`, `uninstall.php`

- [ ] Add failing tests asserting `insert_log()` does not invoke retention, activation/settings schedule an hourly hook once, cleanup deletes in bounded batches, failures retain data, and deactivation/ uninstall clear the hook.
- [ ] Add a structural test for an email table index supporting `(status, sent_at, id)` and an idempotent schema version migration.
- [ ] Run targeted tests and confirm failures.
- [ ] Remove cleanup from `insert_log()` and register `siteintelix_email_log_retention`.
- [ ] Implement `schedule_retention()`, `unschedule_retention()`, and `run_retention_batch()` with a fixed batch limit, date-policy deletion, overflow cutoff deletion, and follow-up scheduling when more rows remain.
- [ ] Schedule on activation and settings save; migrate existing installs; clear on deactivation/uninstall without dropping data.
- [ ] Add the composite index through `dbDelta()` and version the migration.
- [ ] Run targeted tests and verify green.

## Task 6: Split shared assets and localize only screen requirements

**Files:** `tests/structural.test.mjs`, `siteintelix.php`, all files under `assets/admin/js/` and `assets/admin/css/` listed above

- [ ] Add failing structural tests mapping each admin hook to required handles, rejecting Email/Settings/Cron/Transient/Safe Mode functions from shared JS, and bounding shared CSS/JS size to the new core baseline.
- [ ] Add tests that media scripts load only on Settings when Maintenance Mode settings are visible and that each localized object contains only keys used by its script.
- [ ] Run structural tests and confirm failures.
- [ ] Extract reusable toast, dialog, clipboard, and confirmation primitives into shared admin JS; expose one namespaced `window.SiteIntelixAdmin` API.
- [ ] Move page initializers into screen-specific JS files and module/page rules into focused CSS files, preserving shared tokens and components.
- [ ] Replace substring-only enqueueing with an exact screen manifest plus the existing editor-line special case.
- [ ] Enqueue and localize each bundle independently; retain local, framework-free assets.
- [ ] Run structural tests and verify green.

## Task 7: Implement accessible settings tabs and search

**Files:** `tests/admin-interactions.test.mjs`, `admin/views/settings-page.php`, `assets/admin/js/siteintelix-settings.js`, `assets/admin/css/siteintelix-settings.css`

- [ ] Add DOM interaction tests for stable tab/panel relationships, roving tabindex, ArrowLeft/ArrowRight/Home/End, click/Enter/Space activation, hash/session restoration, hidden inactive panels, result counts, and no-results feedback.
- [ ] Add structural markup assertions for `aria-controls`, `aria-labelledby`, `role="tabpanel"`, and initial `hidden` state.
- [ ] Run the new suite and confirm failures.
- [ ] Render complete tab/panel IDs and semantics in PHP.
- [ ] Implement the keyboard and activation model in screen-specific JS without changing form action URLs.
- [ ] Add an `aria-live="polite"` result summary, clear-search control, and per-panel/no-global-results states.
- [ ] Add focused responsive styles and run targeted tests to green.

## Task 8: Build accessible email and confirmation dialogs

**Files:** `tests/admin-interactions.test.mjs`, `includes/modules/email-log/class-siteintelix-email-log-module.php`, `assets/admin/js/siteintelix-admin.js`, `assets/admin/js/siteintelix-email-log.js`, `assets/admin/css/siteintelix-admin.css`, `assets/admin/css/siteintelix-email-log.css`

- [ ] Add tests for focus entry/trap/restoration, Escape/backdrop closing, semantic preview tabs, Retry, test-email validation, and destructive confirmation without native prompt/confirm.
- [ ] Run tests and confirm failures caused by the native dialogs and incomplete focus behavior.
- [ ] Add a reusable dialog shell/helper in shared assets with labelled title/description, focusable-element cycling, invoker restoration, and alert/status regions.
- [ ] Convert test-email and destructive actions to the shared dialog while retaining server-side nonces and validation.
- [ ] Complete Email preview tab roles, keyboard behavior, focus restoration, and fetch state management.
- [ ] Run targeted tests and verify green.

## Task 9: Preserve WordPress notices and improve feedback/responsiveness

**Files:** `tests/structural.test.mjs`, `tests/admin-interactions.test.mjs`, `siteintelix.php`, admin views, module renderers, shared/screen CSS and JS

- [ ] Add tests rejecting `remove_all_actions()` on WordPress notice hooks and requiring an accessible, collapsible notices region.
- [ ] Add tests for toast roles/live modes, persistent inline errors, manual dismissal, reduced motion, responsive table wrappers, and announced bulk-selection counts.
- [ ] Run targeted tests and confirm failures.
- [ ] Replace global notice suppression with notice relocation/presentation that retains update/security notices in a collapsible region.
- [ ] Give success/error feedback correct roles, live behavior, dismissal controls, and persistent inline fallbacks.
- [ ] Apply the approved workspace hierarchy and responsive table/card rules to Overview, Email Log, Cron, Transients, Database Manager, Safe Mode, and module/settings screens without changing URLs/actions.
- [ ] Add bulk-selection summaries and mobile action access.
- [ ] Run targeted tests and verify green.

## Task 10: Full regression, performance assertions, and cleanup

**Files:** all modified files and all test files

- [ ] Run `node --test tests/structural.test.mjs tests/server-diagnostics-ui.test.mjs tests/admin-interactions.test.mjs tests/email-log-performance.test.mjs` and fix every failure.
- [ ] Run `php tests/debug-log-parser.php`, `php tests/editor-links.php`, and `php tests/runtime-smoke.php` and fix every failure.
- [ ] Run `find . -name '*.php' -print0 | xargs -0 -n1 php -l` from the plugin directory and fix every syntax error.
- [ ] Measure shared and per-screen asset byte counts and confirm no SiteIntelix screen loads the old monolithic payload plus all new bundles.
- [ ] Search production assets for `window.prompt`, `window.confirm`, 0–200 localization loops, serialized email preview bodies, synchronous Overview `wp_remote_get`, and removed-notice callbacks; require zero prohibited matches.
- [ ] Inspect the plugin working tree/filesystem changes and confirm only SiteIntelix code, tests, and approved docs changed.
- [ ] Do not commit, push, deploy, package, or upload. Report modified files, verification evidence, and any residual risks to the user.
