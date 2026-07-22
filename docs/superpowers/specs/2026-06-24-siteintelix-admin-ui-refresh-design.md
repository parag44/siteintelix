# SiteIntelix Admin UI Refresh Design

## Objective

Refresh every SiteIntelix WordPress admin screen with a lightweight, consistent visual system inspired by the supplied dashboard reference, while preserving the existing plugin slug, internal identifiers, information architecture, workflows, and module behavior except for the explicitly approved removals.

The public plugin name will become:

> SiteIntelix – Debug Logs, Email Logs & Diagnostics

The plugin directory, WordPress.org slug, text domain, option prefixes, class names, constants, menu slug, URLs, and other internal `siteintelix` identifiers will remain unchanged.

## Approved Visual Direction

Use the selected **WordPress-native minimal** direction.

The UI will use:

- The native WordPress admin font stack.
- The existing Dashicons bundled with WordPress.
- A light-gray page canvas with flat white content surfaces.
- Thin neutral borders and restrained shadows only where hierarchy requires them.
- Compact spacing and typography suitable for information-dense admin screens.
- Clear blue primary actions and semantic green, amber, red, and informational states.
- Consistent visual hierarchy inspired by the reference: page identity header, health/status summaries, grouped content cards, concise secondary text, and clear actions.
- Responsive grids that collapse cleanly without changing workflows.

No external fonts, icon libraries, UI frameworks, CDNs, or remote design assets will be introduced.

## Shared Design System

The existing admin stylesheet will be reorganized around a small shared token and component layer.

### Tokens

Shared CSS custom properties will define:

- Canvas, surface, muted surface, border, text, and muted text colors.
- Primary, success, warning, danger, and information colors.
- Compact spacing increments.
- Small, medium, and large radii.
- Restrained shadow levels.
- Native sans-serif and monospace font stacks.
- Standard control heights and transition timing.

Legacy aliases may remain temporarily where required to avoid breaking module-specific selectors, but new and refreshed rules will use one canonical token set.

### Shared Components

The common layer will cover:

- Page headers.
- Version and status badges.
- Buttons and icon buttons.
- Cards and section headers.
- Toolbars and filters.
- Form rows, inputs, selects, textareas, radio controls, and checkboxes.
- Toggle switches.
- Tables and responsive table containers.
- Notices and alerts.
- Empty states.
- Summary/stat cards.
- Modal surfaces.
- Pagination.

Components will rely on CSS classes already present in the PHP templates where practical. Template changes will be limited to semantic wrappers, shared classes, accessible labels, icons, and layout markup needed for consistency.

## Page Treatment

### Overview

The Overview page will most closely reflect the supplied reference while following the selected flatter visual direction.

It will contain:

- A compact SiteIntelix identity header with the plugin description, version, and active-module count.
- A responsive status strip for PHP version, memory limit, debug mode, HTTPS, REST API, and WP Cron.
- A System Overview card with readable labels, values, semantic statuses, and report actions.
- An Active Modules card with module icons, descriptions, and existing AJAX toggles.

The information and controls remain unchanged.

### Modules

The Modules screen retains active and available groupings, search, status labels, links, and toggles. Module cards will use the shared compact card, icon, badge, and toggle patterns.

### Settings

The module-tab settings workflow remains unchanged. Tabs, search, setting rows, side cards, forms, notices, and actions will adopt the shared components. Settings for the removed Custom Error UI module will no longer exist.

### Debug Log

Classic, Modern, and Terminal Light modes remain available and distinct:

- Classic remains table-oriented.
- Modern remains card-oriented with summaries and expandable details.
- Terminal Light remains a monospace terminal-style viewer.

All three modes will share the same page header, controls, color semantics, spacing rhythm, buttons, empty states, and responsive behavior.

### Other Module Pages

Email Log, SMTP, Cron Events, Database Manager, Download Manager, Transients Manager, Server Diagnostics, Safe Mode, and Maintenance Mode will retain their current data, actions, forms, filtering, and navigation. Their cards, tables, toolbars, modals, forms, notices, and status displays will be normalized to the shared system.

## Custom Error UI Removal

The Custom Error UI module will be completely retired.

Removal includes:

- Removing the module from the central module registry.
- Removing module loading and boot branches.
- Removing activation and deactivation branches.
- Removing settings tabs, links, actions, and hooks.
- Removing the module class and bundled drop-in templates.
- Removing module-specific styles and scripts that are no longer used.
- Removing saved options:
  - `siteintelix_error_ui_settings`
  - `siteintelix_error_ui_dropins_version`
- Removing generated SiteIntelix Error UI config.
- Removing `db-error.php` and `fatal-error-handler.php` only when file contents identify them as SiteIntelix-owned.

The update must never delete or overwrite a third-party or user-created WordPress drop-in.

Cleanup will run during the plugin update/initialization path once, using a versioned migration option so the filesystem and options are not checked on every request. The uninstall routine will retain safe cleanup support for older installations.

If `error_ui` remains in the saved enabled-module option, registry validation will naturally discard it and the migration will persist a cleaned enabled-module list.

## Server Diagnostics Scope Reduction

Server Diagnostics will become a server and runtime information tool rather than a plugin compatibility checker.

Remove:

- Installed-plugin selector.
- Selected-plugin request handling.
- Plugin header requirement parsing.
- Compatibility profile generation.
- Compatibility score contribution.
- Compatibility UI cards.
- Compatibility fields in JSON and text exports.
- Compatibility-oriented descriptions and documentation.

Retain and visually organize:

- PHP runtime and version.
- Loaded `php.ini` and relevant PHP configuration/directive values.
- PHP extensions.
- Filesystem and temporary-directory checks.
- Network and HTTP environment checks.
- WordPress/system environment information.
- Database environment and limits.
- Redacted JSON and text support reports.
- Health summary derived only from retained checks.

The module description will clearly describe server, PHP, system, and PHP configuration diagnostics.

## Performance Architecture

The implementation will prioritize low overhead:

- Vanilla PHP, CSS, and JavaScript only.
- No new runtime dependencies.
- No remote requests for visual assets.
- Shared core styles for common components.
- Page-specific CSS loaded only where a screen genuinely needs it.
- Existing Debug Log viewer CSS and JavaScript loaded only for the relevant Debug Log mode.
- Event delegation instead of repeated listeners where controls are dynamic or numerous.
- No new polling, background requests, or dashboard autoload calls.
- Removed module classes and diagnostics compatibility calculations will no longer load or execute.
- Repeated or obsolete CSS declarations will be consolidated after visual parity is established.

The asset split will favor fewer bytes and no unused page-specific styles while avoiding an excessive number of tiny HTTP requests.

## Accessibility and Responsive Behavior

The refresh will preserve or improve:

- Keyboard access to links, buttons, toggles, tabs, and filters.
- Visible focus states.
- Semantic headings and table headers.
- Accessible names for icon-only controls.
- Status meaning conveyed by text as well as color.
- Responsive layouts at common WordPress admin widths.
- Horizontal overflow containers for data tables that cannot safely reflow.
- Reduced-motion-safe transitions.

## Data and Compatibility

Existing SiteIntelix data and settings remain untouched except for Custom Error UI data and obsolete enabled-module references.

The following remain stable:

- Plugin slug and directory.
- Text domain.
- Admin page slugs.
- Existing options for retained modules.
- Existing AJAX and form action names.
- Module enable/disable behavior.
- Debug Log modes and stored preferences.
- Export formats for retained report fields.

## Testing Strategy

Because the plugin currently has no automated test harness, add lightweight structural regression tests that can run without booting a full WordPress browser session.

Tests will first assert the desired behavior and fail against the current code:

- Plugin header exposes the new public name.
- The `error_ui` module is absent from the registry and runtime branches.
- The migration removes only SiteIntelix-owned drop-ins and retired options.
- Server Diagnostics contains no plugin selector or compatibility report data.
- Retained diagnostics sections and exports remain present.
- Shared design tokens and required component classes exist.
- Admin asset enqueue conditions do not load unrelated page assets.
- Key page templates retain expected forms, actions, nonces, AJAX hooks, and accessibility attributes.

Verification will include:

- Running the structural tests through red, green, and refactor stages.
- PHP syntax checks for all plugin PHP files.
- JavaScript syntax checks where supported.
- CSS brace/syntax validation.
- Manual WordPress admin smoke tests for every enabled screen.
- Module toggling, settings saves, exports, filtering, pagination, modals, and destructive-action confirmations.
- Responsive visual review at desktop, narrow desktop, tablet-like, and collapsed-menu widths.
- Confirmation that only SiteIntelix-owned legacy drop-ins are removed.

## Success Criteria

The work is complete when:

- WordPress displays the new plugin name.
- Every SiteIntelix admin page uses the approved WordPress-native minimal design system.
- The Overview page reflects the hierarchy and clarity of the supplied reference.
- Existing retained workflows continue to function.
- Custom Error UI code, UI, data, and owned generated files are safely removed.
- Server Diagnostics no longer performs or displays plugin compatibility checks.
- Retained diagnostics focus on server, PHP, system, and PHP configuration.
- Assets remain local, dependency-free, scoped, and lightweight.
- Automated structural checks and syntax checks pass.
- Manual visual and functional verification finds no regressions on SiteIntelix admin screens.
