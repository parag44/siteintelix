=== SiteIntelix Debug Log Viewer ===
Contributors:      parag44
Donate link:       https://parag.bd/donate
Tags:              system info, server info, site health, admin dashboard, environment
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        2.2.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A modern WordPress debug log viewer with structured log tables, pagination, filtering, diagnostics, and export-ready reporting.

== Description ==

**SiteIntelix Debug Log Viewer** gives administrators a clean dashboard to inspect WordPress debug logs, server details, database information, and environment health without SSH access.

The plugin now includes four admin experiences that work together:
* **Overview** - health summary, detailed diagnostics cards, and export tools
* **Debug Log Viewer** - log browsing with severity filters, search, and download tools
* **Debug Settings** - switch between MU-plugin logging and `wp-config.php` logging
* **Security Panel** - enable lightweight hardening features from one place

Information is organised into clear sections with colour-coded health indicators so issues are immediately visible.

= WordPress Information =
* WordPress version (with update check)
* Site URL and Home URL
* Active theme name and version
* Complete list of active plugins with versions
* Permalink structure, timezone, and admin email
* Language, charset, and multisite status

= Server Information =
* PHP version with health indicator
* PHP SAPI interface
* Web server software (Apache, Nginx, etc.)
* MySQL / MariaDB version
* Memory limit with warning threshold
* Maximum upload size
* Maximum execution time and post max size
* Operating system, architecture, and disk free space
* OPcache status, database host/name, uploads directory, and key PHP extensions

= Database Information =
* Database extension, server version, and client version
* Database username, host, name, table prefix, charset, and collation
* Runtime limits including max allowed packet size and max connections

= Environment Information =
* WP_DEBUG mode with production warning
* WP-Cron enabled / disabled state
* HTTPS / SSL status
* WordPress environment type
* Object cache and Script Debug flags
* Debug log state, file editing/modification flags, alternate cron, and cron lock timeout

= Health Checks =
The plugin evaluates eight metrics and assigns a status:

* **Good** - everything is healthy
* **Warning** - attention recommended (for example: low memory, disabled HTTPS)
* **Critical** - urgent issues detected (for example: REST API blocked, WP_DEBUG on in production)

= Export Tools =
* **Copy Report** — copies all info as formatted plain text to the clipboard
* **Export JSON** — downloads a timestamped `.json` file

= Debug Tools =
* Dedicated Debug Log Viewer with severity badges, search, refresh, clear, and download actions
* Paginated log table with administrator-controlled entries per page from Debug Settings
* Supports both MU-plugin mode and `wp-config.php` mode while writing to `wp-content/siteintelix-debug.log`
* Debug method switcher with clear labels for each logging approach

= Security Panel =
* Disable XML-RPC
* Hide WordPress version output
* Disable file editing
* Remove legacy head links
* Basic login attempt protection

= Shortcode =
Use `[siteintelix_panel]` on any page or post to display a compact info table. Visible only to logged-in administrators; all other visitors see nothing.

= Security =
* All outputs escaped with WordPress functions (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
* Every admin page protected with `manage_options` capability check
* Direct file access blocked in every PHP file via `ABSPATH` guard
* No data sent to any external service
* Nonces used for localised JS data

= Design =
* Card-based responsive layout
* CSS custom properties — no external frameworks, no CDN calls
* Loads assets only on its own admin page
* Zero JavaScript dependencies

== Installation ==

= Automatic (Recommended) =
1. Log in to your WordPress admin.
2. Go to **Plugins → Add New**.
3. Search for **SiteIntelix Debug Log Viewer**.
4. Click **Install Now**, then **Activate**.

= Manual Upload =
1. Download the plugin `.zip` file.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Select the `.zip` and click **Install Now**.
4. Click **Activate Plugin**.

= FTP =
1. Unzip the download.
2. Upload the `siteintelix` folder to `/wp-content/plugins/`.
3. Activate from the **Plugins** screen.

After activation, find **SiteIntelix Debug Log Viewer** in the left-hand admin menu.

== Frequently Asked Questions ==

= Who can see SiteIntelix Debug Log Viewer? =
Only users with the `manage_options` capability (Administrators by default).

= Does this plugin slow down my site? =
No. CSS and JavaScript are enqueued only on the plugin's own admin page.

= Does this plugin collect or send data anywhere? =
Never. All information comes from your local server environment and stays within your admin dashboard.

= How do I use the shortcode? =
Add `[siteintelix_panel]` to any page or post. Only administrators see the output; all other visitors see nothing.

= The REST API check shows "Blocked" — what does that mean? =
Your site's REST API is not responding. Common causes: a security plugin blocking it, a firewall rule, or a broken `.htaccess`. Check your security plugin settings.

= How do I increase my memory limit? =
Add the following to `wp-config.php`:

    define( 'WP_MEMORY_LIMIT', '256M' );

= Is the plugin Multisite compatible? =
Yes. It activates per-site and reports information for that individual site.

== Screenshots ==

1. **Overview dashboard** — system overview with health badges and export actions.
2. **Debug Log Viewer** — searchable debug console with filters and downloads.
3. **Debug Settings** — switch between MU Plugin and `wp-config.php` logging.
4. **Security Panel** — manage built-in WordPress hardening features.

== Changelog ==

= 2.2.0 — 2026-04-25 =
* Renamed the plugin to **SiteIntelix Debug Log Viewer** while keeping the existing plugin slug and internal identifiers unchanged.
* Added Debug Log Viewer pagination with a configurable logs-per-page setting in Debug Settings.
* Added a complete log level filter set: Fatal, Warning, Notice, Deprecated, Database, and Info.
* Normalized parsed log levels so PHP errors map into the supported filter groups consistently.
* Removed the extra log path and file-size strip below the debug log table.
* Replaced the overview REST API endpoints card with detailed Database information.
* Removed the custom SiteIntelix REST API endpoints and related REST hardening toggle.

= 2.1.1 — 2026-04-24 =
* Replaced the dark terminal-style Debug Log Viewer with a clean, light-themed table layout.
* Added structured columns for Type, Datetime, Description, File, and Line for easier scanning.
* Enhanced the backend log parser to extract file paths and line numbers automatically from entries.
* Expanded log classification with granular levels including Fatal, Database, Deprecated, Notice, and Warning.
* Standardized both MU Plugin and `wp-config.php` modes to write to `wp-content/siteintelix-debug.log`.
* Replaced the overview REST API endpoints card with a detailed Database information card.
* Removed the custom SiteIntelix REST API endpoints and related REST hardening toggle.
* Added Debug Log Viewer pagination with a configurable logs-per-page setting.
* Updated the Datetime column to show human-readable relative timestamps such as "12 hours ago".
* Refined the visual design with premium badge colours, typography, and spacing for a more polished admin experience.
* Optimized instant search and level-based filtering to work smoothly with the new table structure.

= 1.1.4 — 2026-04-20 =
* Refined top header hierarchy and visual polish for a cleaner, more professional first impression.
* Improved action button emphasis and spacing in the header (`Export JSON` primary, `Copy Report` secondary).
* Softened the header gradient and upgraded radius/shadow styles to better match modern WordPress admin UI expectations.
* Enhanced responsive behavior for the header layout on smaller viewports.
* Replaced a non-prefixed hook usage with a plugin-prefixed filter (`siteintelix_local_ssl_verify`) for stronger coding standards compatibility.
* Updated release metadata for WordPress.org submission.

= 1.1.2 — 2026-04-16 =
* Added a dedicated **Debug Log Viewer** submenu under SiteIntelix Panel.
* Added severity-aware parsing for debug log entries (`FATAL`, `ERROR`, `WARN`, `INFO`, `DEBUG`, `OTHER`).
* Improved Debug Log Viewer UI to match SiteIntelix panel styling.
* Improved third-party admin notice handling so notices render above plugin UI.
* Added live/recorded log status messaging based on `WP_DEBUG_LOG` runtime state.
* Added `wp-config.php` snippet guidance when debug logging is disabled.
* Improved log source path display with compact critical path highlighting.

= 1.1.0 — 2026-04-11 =
* Updated plugin version to 1.1.0.
* Improved output escaping in admin dashboard rendering.
* Hardened inline JSON output encoding for safer script embedding.
* Renamed internal template variables to plugin-prefixed names for better coding standards compliance.
* Reduced readme tags to WordPress.org-supported limits.
* Minor admin label and quality improvements.

= 1.0.0 — 2026-03-27 =
* Initial release.
* WordPress info: version, site/home URL, active theme, active plugins.
* Server info: PHP, MySQL, memory limit, upload size, execution time, OS.
* Environment info: debug mode, cron, HTTPS, environment type.
* Eight health checks with good / warning / critical status indicators.
* Copy Report button (plain-text clipboard export).
* Export JSON download.
* `[siteintelix_panel]` shortcode (admin-only front-end table).
* Fully responsive card-based admin UI.
* Zero external dependencies.

== Upgrade Notice ==

= 2.2.0 =
Plugin rename and Debug Log Viewer refinement release with pagination, full level filters, database overview data, and removed custom REST endpoints. Recommended for all users.

= 2.1.1 =
Structured log table release with smarter parsing, relative timestamps, and faster filtering. Recommended for all users.

= 2.1.0 =
Major UI and diagnostics expansion release with improved reporting and cleaner admin UX. Recommended for all users.

= 1.1.4 =
Header UX and standards polish update. Recommended for all users.

= 1.1.2 =
Debug Log Viewer release with improved notice handling, runtime logging guidance, and enhanced admin UX.

= 1.1.0 =
Security, standards, and readme compliance update. Recommended for all users.

= 1.0.0 =
Initial release — no upgrade steps required.

== Privacy Policy ==

SiteIntelix Debug Log Viewer does not collect, store, or transmit any personal data. All system information is gathered from the local server environment and displayed exclusively in the WordPress admin to authorised administrators. No data is ever sent to any third-party service.
