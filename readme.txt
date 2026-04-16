=== SiteIntelix ===
Contributors:      parag44
Donate link:       https://parag.bd/donate
Tags:              system info, server info, site health, admin dashboard, environment
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      7.4
Stable tag:        1.1.2
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

A clean, modern admin dashboard showing comprehensive WordPress, server, and environment info with colour-coded health checks and export tools.

== Description ==

**SiteIntelix** gives administrators a single, beautiful dashboard to monitor their entire hosting environment — no SSH access or technical knowledge required.

Information is organised into three sections with colour-coded health indicators (green / amber / red) so issues are immediately visible.

= WordPress Information =
* WordPress version (with update check)
* Site URL and Home URL
* Active theme name and version
* Complete list of active plugins with versions
* Language, charset, and multisite status

= Server Information =
* PHP version with health indicator
* PHP SAPI interface
* Web server software (Apache, Nginx, etc.)
* MySQL / MariaDB version
* Memory limit with warning threshold
* Maximum upload size
* Maximum execution time and post max size
* Operating system and architecture

= Environment Information =
* REST API reachability status
* WP_DEBUG mode with production warning
* WP-Cron enabled / disabled state
* HTTPS / SSL status
* WordPress environment type
* Object cache and Script Debug flags

= Health Checks =
The plugin evaluates eight metrics and assigns a status:

* 🟢 **Good** — everything is healthy
* 🟡 **Warning** — PHP < 8.0, memory < 256 MB, WP-Cron disabled, update available
* 🔴 **Critical** — PHP < 7.4, REST API blocked, WP_DEBUG on in production

= Export Tools =
* **Copy Report** — copies all info as formatted plain text to the clipboard
* **Export JSON** — downloads a timestamped `.json` file

= Shortcode =
Use `[siteintelix_panel]` on any page or post to display a compact info table. Visible only to logged-in administrators; all other visitors see nothing.

= REST API Endpoints =
Requires Administrator authentication:

    GET /wp-json/siteintelix/v1/info
    GET /wp-json/siteintelix/v1/info?section=server
    GET /wp-json/siteintelix/v1/health

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
3. Search for **SiteIntelix**.
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

After activation, find **SiteIntelix Panel** in the left-hand admin menu.

== Frequently Asked Questions ==

= Who can see the SiteIntelix Panel? =
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

1. **Dashboard overview** — header with overall health status and action buttons.
2. **Health check strip** — colour-coded pills for each metric.
3. **WordPress card** — WP version, URLs, theme, and active plugin list.
4. **Server card** — PHP, MySQL, memory limit and server details with status badges.
5. **Environment card** — REST API, debug mode, cron, HTTPS, and health warnings.

== Changelog ==

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
* Environment info: REST API, debug mode, cron, HTTPS, environment type.
* Eight health checks with good / warning / critical status indicators.
* Copy Report button (plain-text clipboard export).
* Export JSON download.
* `[siteintelix_panel]` shortcode (admin-only front-end table).
* REST API endpoints: `/info` and `/health`.
* Fully responsive card-based admin UI.
* Zero external dependencies.

== Upgrade Notice ==

= 1.1.2 =
Debug Log Viewer release with improved notice handling, runtime logging guidance, and enhanced admin UX.

= 1.1.0 =
Security, standards, and readme compliance update. Recommended for all users.

= 1.0.0 =
Initial release — no upgrade steps required.

== Privacy Policy ==

SiteIntelix does not collect, store, or transmit any personal data. All system information is gathered from the local server environment and displayed exclusively in the WordPress admin to authorised administrators. No data is ever sent to any third-party service.
