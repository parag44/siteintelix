=== SiteIntelix – WordPress Toolkit ===
Contributors:      parag44
Donate link:       https://parag.bd
Tags:              debug log, email log, site health, admin tools, toolbox
Requires at least: 5.8
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        2.7.3
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Modular WordPress support tools for logs, email, cron, database, server health, user switching, SMTP, Safe Mode, and maintenance.

== Description ==

**SiteIntelix** is a modular diagnostics and troubleshooting toolkit for WordPress administrators, developers, and support teams.

It brings debug logs, outgoing email history, server checks, cron inspection, database browsing, Safe Mode, SMTP, and maintenance controls into one organized admin menu. Modules load only when needed, and screen-specific assets stay scoped to SiteIntelix pages.

= Key Features =
* 🩺 **Server Diagnostics** — inspect server health, PHP configuration, extensions, filesystem permissions, network access, WordPress, and database limits.
* 🧾 **Debug Log Viewer** — capture, search, group, filter, clear, and download private WordPress debug logs.
* ✉️ **Email Log** — record outgoing WordPress emails with recipients, headers, status, failures, and message previews.
* 📮 **SMTP Mailer** — route WordPress emails through your own SMTP provider for more reliable delivery.
* ⏱️ **Cron Events** — inspect scheduled WP-Cron events, run due events manually, and remove selected events safely.
* 🗄️ **Database Manager** — browse tables, inspect rows, search records, review sizes, and edit selected rows from wp-admin.
* 📦 **Download Manager** — add secure download links for installed plugin and theme ZIP packages.
* 🛡️ **Safe Mode Debugger** — test plugin and theme conflict scenarios privately without affecting normal visitors.
* 👥 **User Switcher** — temporarily enter an allowed user account for support without requesting or changing its password, then return securely.
* 🚧 **Maintenance Mode** — show a polished public maintenance page while administrators continue working.
* 🧰 **Modular Toolbox** — enable or disable each tool from the SiteIntelix Modules screen.

= Why Use SiteIntelix? =
* Keep troubleshooting tools in one place instead of installing many small utilities.
* Share clean diagnostic reports with support teams.
* See the active PHP configuration and server limits that often explain plugin/import failures.
* Review debug logs, email delivery, cron jobs, database rows, and maintenance mode from wp-admin.
* Keep sensitive data local: SiteIntelix does not upload logs, email contents, database contents, or generated reports.

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
* SiteIntelix does not upload diagnostics, logs, email contents, database contents, or generated reports.
* Server reachability checks may contact WordPress.org endpoints and the site's own loopback or REST URL. They send normal HTTP request metadata, not report contents.
* User Switcher uses WordPress-native authentication, signed short-lived state, dedicated capabilities, protected roles, and optional local audit logs.

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
SiteIntelix does not collect telemetry or upload diagnostics, logs, email contents, database contents, or generated reports. When an administrator refreshes Server Diagnostics, reachability checks may contact WordPress.org endpoints and the site's own loopback or REST URL. Those checks send normal HTTP request metadata, not SiteIntelix report contents.

= How do I use the shortcode? =
Add `[siteintelix_panel]` to a page or post. Only administrators can see the output.

= Is the plugin Multisite compatible? =
Yes. It activates per site and reports information for that individual site.

== Screenshots ==

1. **Overview Dashboard** — minimal health cards, key system details, report actions, and quick module toggles.
2. **Modules** — enable or disable toolbox modules, open available tools, and review planned modules from one screen.
3. **Modern Debug Log Viewer** — grouped cards, severity filters, occurrence counts, stack traces, clear, refresh, and download actions.
4. **Email Log** — captured email events with delivery status, recipients, previews, filters, bulk actions, and row actions.
5. **Database Manager** — server-rendered database metrics, table search, sorting, pagination, and safe record inspection.
6. **Server Diagnostics** — health score, categorized checks, filters, report actions, and detailed system diagnostics.
7. **User Switcher** — searchable switching activity with session status, duration, and protected log actions.
8. **Settings** — module settings with User Switcher roles, redirects, session duration, logging, and retention.
9. **Cron Events** — scheduled events with search, countdowns, due-now status, run actions, and delete actions.

== Changelog ==

= 2.7.3 — 2026-07-28 =
* Added Custom CSS & JS and isolated Code Snippets modules with focused management screens and module-specific settings.
* Improved User Switcher recovery, admin-bar visibility, activity logging, and secure return-to-administrator handling.
* Hardened sensitive tools, generated files, MU bootstrap ownership checks, transient previews, SMTP credential storage, and download responses.
* Updated WordPress.org metadata, privacy disclosures, multisite permission boundaries, and release verification coverage.

= 2.7.2 — 2026-07-24 =
* Added the optional User Switcher module with shared SiteIntelix settings, a dedicated activity-log submenu, protected-role rules, secure one-click return, dedicated capabilities, short-lived signed sessions, configurable redirects, and retained audit logs.
* Renamed the public plugin title to SiteIntelix – WordPress Toolkit while preserving the existing `siteintelix` slug and internal identifiers.
* Fixed the Overview quick module switches so enable and disable changes persist through the secured module endpoint and roll back cleanly on failure.
* Redesigned Database Manager and Server Diagnostics with lightweight, server-rendered dashboards, responsive tables, filtering, sorting, pagination, and accessible details.
* Improved Email Log with a complete test-email action, on-demand previews, single and bulk deletion, and a delete-all workflow.
* Improved Debug Log layouts so messages, stack traces, and source paths use the available width without hiding useful context.
* Reduced request overhead with context-aware module loading, memoized module state, screen-specific assets, cached diagnostics, and bounded email-log retention.
* Hardened capabilities, nonces, request validation, output escaping, redacted exports, SQL allow-lists, and package-path validation.
* Updated WordPress.org metadata, privacy disclosures, translations guidance, and Plugin Check compatibility.

= 2.7.1 — 2026-07-21 =
* Refined the Modern Debug Log Viewer with compact, full-width WordPress admin layouts for the mode status, statistics, filters, and log rows.
* Improved grouped log scanning with collapsed rows, structured expanded details, copy actions, and clearer severity indicators.
* Preserved existing debug logging, filtering, pagination, clear, download, mode switching, and native editor-link workflows.

= 2.7.0 — 2026-06-24 =
* Refreshed every SiteIntelix admin screen with a lightweight WordPress-native design system.
* Removed the Custom Error UI module and added safe cleanup for SiteIntelix-owned legacy drop-ins and settings.
* Simplified Server Diagnostics to server, PHP, PHP configuration, filesystem, network, WordPress, and database reporting.
* Reduced decorative styling and kept page-specific assets scoped to the screens that need them.

Earlier release history remains available in previous WordPress.org tags.

== Upgrade Notice ==

= 2.7.3 =
Security-focused major update with Custom CSS & JS, isolated Code Snippets, safer multisite permissions, and improved User Switcher recovery.

= 2.7.2 =
Recommended performance, security, accessibility, and admin workflow update for Debug Log, Email Log, Database Manager, and Server Diagnostics.

== Privacy Policy ==

SiteIntelix does not collect telemetry or upload logs, email contents, database contents, or generated diagnostic reports. Stored logs and settings remain in the WordPress installation and are visible only to authorised administrators.

When an administrator runs or refreshes Server Diagnostics, reachability checks may make HTTP requests to WordPress.org endpoints and to the site's own loopback or REST URL. These requests include normal network metadata such as the site's public IP address and a SiteIntelix version user agent, but they do not include the contents of SiteIntelix reports.
