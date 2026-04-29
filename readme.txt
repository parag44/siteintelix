=== SiteIntelix Debug Log Viewer ===
Contributors:      parag44
Donate link:       https://parag.bd/donate
Tags:              debug log, error log, site health, system info, admin tools
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        2.5.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A WordPress debug log viewer with modern grouped cards, classic tables, terminal dark mode, server diagnostics, and local-only admin tools.

== Description ==

**SiteIntelix Debug Log Viewer** helps administrators inspect WordPress debug logs, environment details, database information, and security settings directly from the WordPress admin area.

It is built for site owners, developers, and support teams who need a readable view of `wp-content/siteintelix-debug.log` without SSH access or external services.

= Debug Log Viewer =
* **Modern grouped cards** — grouped errors with occurrence counts, severity styling, timelines, expandable stack traces, and global filters.
* **Classic table** — compact table layout with Type, Datetime, Description, File, and Line columns.
* **Terminal Dark** — developer-style dark terminal stream showing the latest 100 entries without pagination.
* Global search that can find logs across all pages, not only the currently visible page.
* Filters for Fatal, Warning, Notice, Deprecated, Database, and Info log types.
* Configurable logs-per-page setting for paginated views.
* Refresh, clear, and download actions protected by WordPress capabilities and nonces.

= Logging Modes =
* **MU Plugin mode** — non-invasive capture layer that writes to `wp-content/siteintelix-debug.log` without editing `wp-config.php`.
* **wp-config.php mode** — uses native WordPress/PHP debug constants and writes the custom `WP_DEBUG_LOG` path into `wp-config.php`.
* Both modes use `wp-content/siteintelix-debug.log` instead of the default `wp-content/debug.log`.

= Overview Dashboard =
* WordPress version, site URLs, active theme, plugins, timezone, language, charset, and multisite status.
* PHP version, web server, PHP SAPI, memory limit, upload size, execution time, operating system, OPcache, and key PHP extensions.
* Database extension, server/client versions, username, host, database name, table prefix, charset, collation, max packet size, and max connections.
* Environment checks for debug mode, HTTPS, WP-Cron, object cache, script debug, file editing, file modifications, alternate cron, and cron lock timeout.

= Health and Security =
* Health indicators for important WordPress, server, database, and environment settings.
* Security Panel options for XML-RPC, WordPress version output, file editing, legacy head links, and basic login protection.
* REST API availability status is surfaced as a top-level health signal.

= Export Tools =
* **Copy Report** — copies diagnostics as formatted plain text.
* **Export JSON** — downloads a timestamped JSON report.
* **Download Log** — downloads the current SiteIntelix debug log.

= Shortcode =
Use `[siteintelix_panel]` on any page or post to display a compact system information table. The shortcode output is visible only to logged-in administrators.

= Security and Privacy =
* All admin pages require the `manage_options` capability.
* Admin actions use WordPress nonces.
* Output is escaped with WordPress escaping functions.
* No external CDN assets or JavaScript frameworks are used.
* No data is sent to third-party services.
* All inspected information stays inside your WordPress installation.

== Installation ==

= Automatic =
1. Log in to your WordPress admin.
2. Go to **Plugins → Add New**.
3. Search for **SiteIntelix Debug Log Viewer**.
4. Click **Install Now**, then **Activate**.

= Manual Upload =
1. Download the plugin ZIP file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

= FTP =
1. Unzip the plugin package.
2. Upload the `siteintelix` folder to `/wp-content/plugins/`.
3. Activate **SiteIntelix Debug Log Viewer** from the Plugins screen.

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

= What is Terminal Dark mode? =
Terminal Dark mode is a developer-style log screen that shows the latest 100 parsed log entries in a dark terminal layout. It does not use pagination.

= Can the viewer search logs beyond the current page? =
Yes. Search and server-side filters are applied to the parsed log dataset before pagination.

= Does this plugin collect or send data anywhere? =
No. SiteIntelix Debug Log Viewer does not transmit diagnostics, logs, or environment data to any external service.

= How do I use the shortcode? =
Add `[siteintelix_panel]` to a page or post. Only administrators can see the output.

= Is the plugin Multisite compatible? =
Yes. It activates per site and reports information for that individual site.

== Screenshots ==

1. **Overview Dashboard** — WordPress, server, database, environment, health, and export tools.
2. **Modern Debug Log Viewer** — grouped cards, global filters, occurrence counts, and stack traces.
3. **Terminal Dark Viewer** — latest 100 entries in a dark terminal-style stream.
4. **Classic Debug Log Viewer** — structured table with Type, Datetime, Description, File, and Line columns.
5. **Debug Settings** — choose logging method, viewer UI style, and logs-per-page value.
6. **Security Panel** — manage lightweight WordPress hardening options.

== Changelog ==

= 2.5.0 — 2026-04-29 =
* Added a new **Terminal Dark** Debug Log Viewer style that shows the latest 100 entries without pagination.
* Added Settings radio-card selection for Modern grouped cards, Classic table, and Terminal Dark layouts.
* Added global server-side search and filter handling so results are found across the full parsed log dataset, not just the current page.
* Improved Modern grouped cards with compact spacing, better button placement, expandable stack traces, and global pagination-aware filters.
* Added support for grouped similar logs, hide-deprecated, critical-only, type, file, time, and plugin filters before pagination.
* Updated Debug Log Viewer assets to load only for the Modern and Terminal layouts.
* Refreshed WordPress.org metadata, tags, plugin description, readme content, screenshots, changelog, and upgrade notice for the 2.5.0 release.

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
* Replaced the dark terminal-style Debug Log Viewer with a clean, light-themed table layout.
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

= 2.5.0 =
Adds Terminal Dark mode, global log searching/filtering, improved modern grouped cards, and refreshed WordPress.org release metadata. Recommended for all users.

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

SiteIntelix Debug Log Viewer does not collect, store, or transmit personal data. All system information and logs are read from the local WordPress installation and displayed only to authorised administrators inside the WordPress admin area. No data is sent to any third-party service.
