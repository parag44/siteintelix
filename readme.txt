=== SiteIntelix ===
Contributors:      parag44
Donate link:       https://parag.bd/donate
Tags:              debug log, email log, site health, admin tools, toolbox
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        2.6.2
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A modular WordPress admin toolbox for diagnostics, debug logs, email logs, SMTP, cron events, database insights, and site utilities.

== Description ==

**SiteIntelix** is a lightweight, modular toolbox for WordPress administrators, developers, and support teams who need practical site diagnostics inside wp-admin.

Instead of installing separate plugins for every troubleshooting task, SiteIntelix gives you one organized admin menu with modules you can turn on only when you need them. It keeps the interface WordPress-native, fast, and focused on day-to-day maintenance work.

= What SiteIntelix Helps You Do =
* Review WordPress, server, PHP, database, and environment details from one Overview screen.
* Copy a readable diagnostics report or export site information as JSON for support and audits.
* Capture and inspect private WordPress debug logs without relying on the default `wp-content/debug.log`.
* Track outgoing WordPress emails, recipients, headers, failures, and message previews.
* Route WordPress email through SMTP when your site needs reliable mail delivery.
* Inspect scheduled WP-Cron events and manually run or remove events when troubleshooting.
* Browse database tables, storage usage, records, and full row data with dedicated edit screens.
* Download installed plugin and theme ZIP packages directly from WordPress admin when needed.
* Configure a custom public error UI for database and critical WordPress errors.
* Enable Maintenance Mode while site work is in progress.

= Current Modules =
* **Overview** — health cards and detailed WordPress, server, environment, and database diagnostics.
* **Modules** — enable or disable SiteIntelix tools from a dedicated module manager.
* **Debug Log** — modern grouped cards, classic table, and terminal-style log viewer modes.
* **Email Log** — captures outgoing email events, failures, recipients, headers, and body previews.
* **SMTP** — sends WordPress email through a configured SMTP provider.
* **Cron Events** — lists scheduled events with search, countdowns, run actions, and delete actions.
* **Database Manager** — shows table statistics, searchable rows, pagination, full row browsing, and dedicated row editing.
* **Download Manager** — adds secure Download links for installed plugins and themes in WordPress admin.
* **Custom Error UI** — replaces plain fatal/database error screens with a configurable visitor-facing page.
* **Maintenance Mode** — simple maintenance content for visitors while administrators keep working.

= Debug Log Module =
* **Modern grouped cards** — grouped errors with occurrence counts, severity styling, timelines, expandable stack traces, and global filters.
* **Classic table** — compact table layout with Type, Datetime, Description, File, and Line columns.
* **Terminal Light** — developer-style log stream with a scrollable terminal-style layout.
* Search and filters for Fatal, Warning, Notice, Deprecated, Database, and Info log types.
* Configurable logs-per-page setting for paginated views.
* Refresh, clear, and download actions protected by WordPress capabilities and nonces.

= Logging Modes =
* **MU Plugin mode** — non-invasive capture layer that writes to `wp-content/siteintelix-debug.log` without editing `wp-config.php`.
* **wp-config.php mode** — uses native WordPress/PHP debug constants and writes the custom `WP_DEBUG_LOG` path into `wp-config.php`.
* Both modes use `wp-content/siteintelix-debug.log` instead of the default `wp-content/debug.log`.

= Security and Privacy =
* All SiteIntelix admin pages require the `manage_options` capability.
* Admin actions use WordPress nonces.
* Output is escaped with WordPress escaping functions.
* No external CDN assets or heavy JavaScript frameworks are loaded.
* Assets are scoped to SiteIntelix admin pages.
* No diagnostics, logs, email data, or database details are sent to third-party services.
* All inspected information stays inside your WordPress installation.

= Shortcode =
Use `[siteintelix_panel]` on any page or post to display a compact system information table. The shortcode output is visible only to logged-in administrators.

== Installation ==

= Automatic =
1. Log in to your WordPress admin.
2. Go to **Plugins → Add New**.
3. Search for **SiteIntelix**.
4. Click **Install Now**, then **Activate**.

= Manual Upload =
1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

= FTP =
1. Unzip the plugin package.
2. Upload the `siteintelix` folder to `/wp-content/plugins/`.
3. Activate **SiteIntelix** from the Plugins screen.

After activation, open **SiteIntelix** in the WordPress admin menu.

== Frequently Asked Questions ==

= Who can access the plugin screens? =
Only users with the `manage_options` capability can access SiteIntelix admin pages.

= Where does SiteIntelix write debug logs? =
Both logging modes write to `wp-content/siteintelix-debug.log`.

= Does SiteIntelix use wp-content/debug.log? =
No. SiteIntelix uses its own `wp-content/siteintelix-debug.log` file so WordPress' default `debug.log` is not used by this plugin.

= What is the difference between MU Plugin mode and wp-config.php mode? =
MU Plugin mode captures common PHP and WordPress runtime messages without editing core configuration. wp-config.php mode uses native WordPress debugging constants and can catch debug output through `WP_DEBUG_LOG`, but it requires editing `wp-config.php`.

= What is Terminal Light mode? =
Terminal Light mode is a developer-style log screen that shows the all parsed log entries in a light terminal layout. It does not use pagination.

= Can the viewer search logs beyond the current page? =
Yes. Search and server-side filters are applied to the parsed log dataset before pagination.

= Does this plugin collect or send data anywhere? =
No. SiteIntelix does not transmit diagnostics, logs, or environment data to any external service.

= How do I use the shortcode? =
Add `[siteintelix_panel]` to a page or post. Only administrators can see the output.

= Is the plugin Multisite compatible? =
Yes. It activates per site and reports information for that individual site.

== Screenshots ==

1. **Overview Dashboard** — minimal health cards, key system details, report actions, and quick module toggles.
2. **Modules** — enable or disable toolbox modules, open available tools, and review planned modules from one screen.
3. **Modern Debug Log Viewer** — grouped cards, severity filters, occurrence counts, stack traces, clear, refresh, and download actions.
4. **Email Log** — captured email events with delivery status, recipients, previews, filters, bulk actions, and row actions.
5. **Cron Events** — scheduled events with search, countdowns, due-now status, run actions, and delete actions.
6. **Settings** — clean module settings for Debug Log, SMTP, Email Log, Custom Error UI, and Maintenance Mode.

== Changelog ==

= 2.6.2 — 2026-05-18 =
* Added a Custom Error UI option to show escaped technical error details on public error pages when explicitly enabled.
* Improved Debug Log and Email Log filter toolbar alignment for a cleaner, more consistent admin layout.
* Refined shared Settings page spacing so module settings rows and form fields use the same visual rhythm.

= 2.6.1 — 2026-05-17 =
* Added Download Manager support for secure plugin and theme ZIP downloads from WordPress admin.
* Refined Database Manager row browsing with full row tables and dedicated row edit screens.
* Renamed Coming Soon to Maintenance Mode while preserving existing settings compatibility.
* Polished the Settings page layout with cleaner tabs, search, spacing, setting rows, and sidebar cards.
* Updated screenshots descriptions for the refreshed 2.6.x admin experience.

= 2.6.0 — 2026-05-16 =
* Refreshed the SiteIntelix admin UI with a shared lightweight design system, common page headers, consistent cards, buttons, badges, forms, and tables.
* Redesigned the Overview screen as a minimal command center with quick health cards, compact system details, report actions, and module enable/disable toggles.
* Added and refined toolbox modules for Email Log, SMTP delivery, Cron Events, Database Manager, Custom Error UI, and Coming Soon.
* Improved Database Manager with summary statistics, searchable table lists, row browsing, pagination, and selected row inspection/editing.
* Improved Settings tabs so direct module links and save redirects return to the correct module settings panel.
* Added a Debug Log conflict repair action for sites where `WP_DEBUG` in `wp-config.php` overrides MU debug capture.
* Reorganized admin assets into scoped `assets/admin/` CSS and JS files and removed duplicated legacy assets.
* Updated WordPress.org description, screenshots, changelog, and upgrade notice for the expanded toolbox release.

= 2.2.0 — 2026-04-25 =
* Renamed the plugin to **SiteIntelix Debug Log Viewer** while keeping the existing plugin slug and internal identifiers unchanged.
* Added Debug Log Viewer pagination with a configurable logs-per-page setting in Debug Settings.
* Added a complete log level filter set: Fatal, Warning, Notice, Deprecated, Database, and Info.
* Normalized parsed log levels so PHP errors map into the supported filter groups consistently.
* Grouped multiline PHP errors, stack traces, and SQL context into a single log table row.
* Prevented MU Plugin mode from writing duplicate native PHP log rows alongside SiteIntelix structured rows.
* Removed the extra log path and file-size strip below the debug log table.
* Replaced the overview REST API endpoints card with detailed Database information.
* Removed the custom SiteIntelix REST API endpoints and related REST hardening toggle.

= 2.1.1 — 2026-04-24 =
* Replaced the light terminal-style Debug Log Viewer with a clean, light-themed table layout.
* Added structured columns for Type, Datetime, Description, File, and Line for easier scanning.
* Enhanced the backend log parser to extract file paths and line numbers automatically from entries.
* Expanded log classification with granular levels including Fatal, Database, Deprecated, Notice, and Warning.
* Standardized both MU Plugin and `wp-config.php` modes to write to `wp-content/siteintelix-debug.log`.
* Added Debug Log Viewer pagination with a configurable logs-per-page setting.
* Updated the Datetime column to show human-readable relative timestamps such as "12 hours ago".
* Refined badge colours, typography, and spacing for a more polished admin experience.
* Optimized search and level-based filtering for the structured table layout.

= 1.1.4 — 2026-04-20 =
* Refined top header hierarchy and visual polish for a cleaner, more professional first impression.
* Improved action button emphasis and spacing in the header.
* Softened the header gradient and upgraded radius/shadow styles.
* Enhanced responsive behavior for smaller viewports.
* Replaced a non-prefixed hook usage with a plugin-prefixed filter.
* Updated release metadata for WordPress.org submission.

= 1.1.2 — 2026-04-16 =
* Added a dedicated Debug Log Viewer submenu.
* Added severity-aware parsing for debug log entries.
* Improved Debug Log Viewer UI to match SiteIntelix panel styling.
* Improved third-party admin notice handling.
* Added debug logging guidance and compact path display.

= 1.1.0 — 2026-04-11 =
* Improved output escaping in admin dashboard rendering.
* Hardened inline JSON output encoding.
* Renamed internal template variables to plugin-prefixed names.
* Reduced readme tags to WordPress.org-supported limits.
* Minor admin label and quality improvements.

= 1.0.0 — 2026-03-27 =
* Initial release.
* WordPress, server, database, and environment information panels.
* Health checks, Copy Report, Export JSON, and admin-only shortcode.
* Responsive card-based admin UI with zero external dependencies.

== Upgrade Notice ==

= 2.6.1 =
Adds Download Manager, improves Database Manager row editing, renames Coming Soon to Maintenance Mode, and refreshes Settings page spacing and screenshot descriptions.

= 2.6.0 =
Adds the refreshed admin UI, expanded toolbox modules, Email Log, SMTP, Cron Events, Database Manager improvements, settings-tab fixes, and scoped admin assets. Recommended for all users.

= 2.2.0 =
Plugin rename and Debug Log Viewer refinement release with pagination, full level filters, multiline log grouping, duplicate MU log prevention, database overview data, and removed custom REST endpoints.

= 2.1.1 =
Structured log table release with smarter parsing, relative timestamps, and faster filtering.

= 1.1.4 =
Header UX and standards polish update.

= 1.1.2 =
Debug Log Viewer release with improved notice handling and runtime logging guidance.

= 1.1.0 =
Security, standards, and readme compliance update.

= 1.0.0 =
Initial release.

== Privacy Policy ==

SiteIntelix does not collect, store, or transmit personal data. All system information and logs are read from the local WordPress installation and displayed only to authorised administrators inside the WordPress admin area. No data is sent to any third-party service.
