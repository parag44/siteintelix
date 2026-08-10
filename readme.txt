=== SiteIntelix – Developer & Admin Toolkit ===
Contributors:      parag44
Donate link:       https://parag.bd
Tags:              file manager, debug log, diagnostics, code snippets, admin tools
Requires at least: 5.8
Tested up to:      7.0
Requires PHP:      7.4
Stable tag:        2.8.2
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Developer tools for diagnostics, debugging, maintenance, and site administration.

== Description ==

**SiteIntelix** is a modular diagnostics and troubleshooting toolkit for WordPress administrators, developers, and support teams.

It brings a DirectAdmin-inspired File Manager, private debug logs, outgoing email history, server checks, cron inspection, database browsing, Safe Mode, SMTP, user switching, custom CSS/JavaScript, PHP snippets, and maintenance controls into one organized admin menu. Modules load only when needed, screen-specific assets stay scoped to SiteIntelix pages, and third-party admin banners stay out of the SiteIntelix workspace.

= Key Features =
* 🩺 **Server Diagnostics** — inspect server health, PHP configuration, extensions, filesystem permissions, network access, WordPress, and database limits.
* 🧾 **Debug Log Viewer** — capture, search, group, filter, clear, and download private WordPress debug logs.
* ✉️ **Email Log** — record outgoing WordPress emails with recipients, headers, status, failures, and message previews.
* 📮 **SMTP Mailer** — route WordPress emails through your own SMTP provider for more reliable delivery.
* ⏱️ **Cron Events** — inspect scheduled WP-Cron events, run due events manually, and remove selected events safely.
* 🗄️ **Database Manager** — browse tables, inspect rows, search records, review sizes, and edit selected rows from wp-admin.
* 📦 **Download Manager** — add secure download links for installed plugin and theme ZIP packages.
* 🔁 **Plugin Version Switcher** — keep private plugin ZIP builds in a protected Version Vault and switch the installed files with WordPress' native upgrader while preserving activation state.
* 📁 **File Manager** — use a focused DirectAdmin-inspired browser to navigate the WordPress installation, inspect approved files, edit safe text formats only inside configured writable locations, upload allowed files, create ZIP exports, and manage private backups and trash under enforced Safe Mode rules.
* 🛡️ **Safe Mode Debugger** — test plugin and theme conflict scenarios privately without affecting normal visitors.
* 👥 **User Switcher** — temporarily enter an allowed user account for support without requesting or changing its password, then return securely.
* 🎨 **Custom CSS & JS** — manage reusable administrator-authored CSS and JavaScript with frontend/admin scope, placement, priority, and inline or generated-file loading.
* 💻 **Code Snippets** — validate, organize, and run administrator-authored PHP in isolated closures with automatic error deactivation and recovery controls.
* 🚧 **Maintenance Mode** — show a polished public maintenance page while administrators continue working.
* 🧰 **Modular Toolbox** — enable or disable each tool from the SiteIntelix Modules screen.

= Why Use SiteIntelix? =
* Keep troubleshooting tools in one place instead of installing many small utilities.
* Share clean diagnostic reports with support teams.
* See the active PHP configuration and server limits that often explain plugin/import failures.
* Review debug logs, email delivery, cron jobs, database rows, and maintenance mode from wp-admin.
* Keep sensitive data local: SiteIntelix does not upload logs, email contents, database contents, or generated reports.

= Debug Log Module =
* **One focused log viewer** — a compact table with Type, Last seen, Count, Description, File, and Line columns.
* Search, error-type filters, time ranges, adjustable pagination, optional columns, and grouped duplicate entries.
* Expandable stack traces plus protected refresh, clear, and download actions.
* First-run automatic setup plus dedicated settings for WP_DEBUG, SCRIPT_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, and SAVEQUERIES.

= Logging =
* SiteIntelix uses the audited WP-CLI `WPConfigTransformer` library to update WordPress debug constants in `wp-config.php`.
* A restricted `wp-config.php.siteintelix.bak` backup is created before changes.
* WP_DEBUG_LOG points to a cryptographically randomized `.log` filename in protected `wp-content/siteintelix/` storage instead of the default `wp-content/debug.log`.
* Error display remains disabled by default and can be enabled explicitly from Debug Log settings when local troubleshooting requires it.

= Security and Privacy =
* SiteIntelix admin pages require the `manage_options` capability. On Multisite, executable-code and network-sensitive tools require a Multisite super administrator.
* Admin actions use WordPress nonces.
* Output is escaped with WordPress escaping functions.
* No external CDN assets or heavy JavaScript frameworks are loaded.
* Assets are scoped to SiteIntelix admin pages.
* SiteIntelix sends no telemetry and does not upload diagnostics, logs, email contents, database contents, snippets, custom code, or generated reports.
* Server reachability checks may contact WordPress.org endpoints and the site's own loopback or REST URL. They send normal HTTP request metadata, not report contents.
* SMTP sends outgoing mail through the administrator-configured SMTP provider. Its credential is stored locally in a non-autoloaded WordPress option.
* Enabled Code Snippets execute locally authored PHP through the module's isolated runner. SiteIntelix does not retrieve or execute remote snippet code.
* Custom CSS & JS can add administrator-authored executable JavaScript to configured frontend or admin pages.
* User Switcher uses WordPress-native authentication, signed short-lived state, dedicated capabilities, protected roles, and optional local audit logs.
* File Manager canonicalizes every path and provides read-only navigation across the WordPress installation. Writes remain confined to configured safe locations; symbolic-link traversal, private storage access, protected locations, sensitive-file content access, and PHP modification are blocked.
* Plugin Version Switcher requires `update_plugins`, validates package identity and SHA-256 checksums, rejects traversal and symbolic-link archive entries, keeps recovery copies until verification succeeds, and never exposes vault paths or stored filenames to the browser.
* Debug logs, email logs, database values, diagnostic reports, and switching records can contain sensitive information and should be shared only with trusted people.

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
Users need the `manage_options` capability to access SiteIntelix. On Multisite, Code Snippets, Custom CSS & JS, Debug Log configuration, Database Manager, Download Manager, Plugin Version Switcher, and Safe Mode require a Multisite super administrator. Code modules also require WordPress' `unfiltered_html` capability.

= Does Code Snippets execute PHP? =
Yes. Enabled administrator-authored PHP runs locally in an isolated closure. Snippets are syntax-checked before saving or activation, controls require capabilities and nonces, and a captured runtime failure deactivates the affected snippet. The executor intentionally uses PHP `eval()` for this local feature and never downloads remote code.

= Where are Custom CSS & JS files stored? =
Entries are stored in a plugin-owned database table. When external-file loading is selected, generated files use the restricted `wp-content/uploads/siteintelix/custom-code/` directory. SiteIntelix only deletes files that match its managed filename pattern.

= Where does SiteIntelix write debug logs? =
WordPress writes to a randomized file in the protected SiteIntelix content directory. The viewer shows only its WordPress-relative path, not the complete server path.

= Does SiteIntelix use wp-content/debug.log? =
No. SiteIntelix uses its own randomized private log file so WordPress' default `debug.log` is not used by this plugin.

= Where are Plugin Version Switcher builds stored? =
By default, randomized ZIP filenames are stored beneath the protected SiteIntelix content directory. Define `SITEINTELIX_VERSION_VAULT_DIR` with an absolute directory path, or use the `siteintelix_version_vault_directory` filter, to place licensed builds outside the public web root. The admin interface never publishes package URLs, physical paths, or randomized filenames.

= Does switching a plugin version roll back its database changes? =
No. Version Switcher replaces plugin files only. It cannot reverse database migrations, changed options, custom tables, post metadata, or scheduled events. Use a staging site and create a database backup before comparing versions that may change stored data.

= Can caches react after a successful version switch? =
Yes. SiteIntelix clears WordPress' plugin metadata cache but intentionally does not call a global cache flush or `opcache_reset()`. Integrations can use `siteintelix_before_plugin_version_switch`, `siteintelix_after_plugin_version_switch`, and `siteintelix_plugin_version_switch_failed` for targeted cache handling or audit integrations.

= Nginx private storage rule =

Nginx does not read `.htaccess`. Administrators using the default WordPress content URL should add this rule inside the relevant `server` block:

```
location ^~ /wp-content/siteintelix/ {
	deny all;
	return 403;
}
```

If `WP_CONTENT_URL` is customized, replace `/wp-content/` with that installation's actual content URL path. Keep the rule scoped to the complete SiteIntelix directory, reload Nginx after validating the configuration, and use SiteIntelix's authenticated admin interfaces to access private data. This rule also protects the default Version Vault; placing the vault outside the public web root is stronger.

= Does SiteIntelix edit wp-config.php? =
Yes, only when an authorized administrator starts debugging or changes a Debug Log setting. SiteIntelix uses WPConfigTransformer, validates an allowlist of debug constants, and writes a restricted adjacent backup first. If `wp-config.php` is read-only, the log viewer remains available while configuration changes report the write failure.

= Can the viewer search logs beyond the current page? =
Yes. Search and server-side filters are applied to the parsed log dataset before pagination.

= Does this plugin collect or send data anywhere? =
SiteIntelix does not collect telemetry or upload diagnostics, logs, email contents, database contents, or generated reports. When an administrator refreshes Server Diagnostics, reachability checks may contact WordPress.org endpoints and the site's own loopback or REST URL. Those checks send normal HTTP request metadata, not SiteIntelix report contents.

= How do I use the shortcode? =
Add `[siteintelix_panel]` to a page or post. Only administrators can see the output.

= Is the plugin Multisite compatible? =
Yes. Site-scoped tools can be activated per site. Tools that can execute code, inspect network-wide database data, read installed source packages, write safety MU files, or change debug configuration require a Multisite super administrator.

= What happens when I delete the plugin? =
SiteIntelix removes its settings, scheduled events, logs, and only signature-verified SiteIntelix safety MU bootstrap files. Custom CSS & JS entries, generated assets, Code Snippets, and File Manager-owned backups, trash, metadata, and audit records are removed only when their independent uninstall-retention settings are enabled. Version Vault builds and switching history remain until they are deleted from Version Switcher. Debug constants are not silently rewritten during deletion; the viewer should be used to disable them first.

= Can File Manager edit PHP or WordPress core files? =
No. File Manager is Safe Mode-only in this release. PHP files remain view-only, while WordPress core, `wp-config.php` writes, SiteIntelix, must-use plugins, active plugins, and the active theme are protected. WordPress' `DISALLOW_FILE_EDIT` and `DISALLOW_FILE_MODS` constants are also enforced.

== Screenshots ==

1. **Overview Dashboard** — minimal health cards, key system details, report actions, and quick module toggles.
2. **Modules** — enable or disable installed toolbox modules and open available tools from one screen.
3. **Debug Log Viewer** — first-run secure setup, grouped table rows, time and severity filters, stack traces, clear, refresh, and download actions.
4. **Email Log** — captured email events with delivery status, recipients, previews, filters, bulk actions, and row actions.
5. **Database Manager** — server-rendered database metrics, table search, sorting, pagination, and safe record inspection.
6. **Server Diagnostics** — health score, categorized checks, filters, report actions, and detailed system diagnostics.
7. **User Switcher** — searchable switching activity with session status, duration, and protected log actions.
8. **Settings** — module settings with User Switcher roles, redirects, session duration, logging, and retention.
9. **Cron Events** — scheduled events with search, countdowns, due-now status, run actions, and delete actions.

== Changelog ==

= 2.8.2 — 2026-08-10 =
* Added: Plugin Version Switcher with protected local build storage, package validation, integrity checks, recovery copies, and switching history.
* Changed: Renamed the plugin to SiteIntelix – Developer & Admin Toolkit and simplified its directory description.
* Fixed: Prevented a frontend admin-bar fatal by loading Version Switcher runtime classes only after administrator authorization is available.
* Fixed: Restored File Manager editor fullscreen behavior, styled its fullscreen control, and added visible save progress, success, and error feedback.
* Fixed: Removed incomplete module teasers and third-party-specific notice selectors for WordPress.org production readiness.

= 2.8.1 — 2026-08-01 =
* Added: WPConfigTransformer-based debugging, protected randomized log storage, first-run automatic setup, grouped logs, filters, stack traces, pagination, clear, and download actions.
* Fixed: Removed Debug Log MU-mode runtime files, separated capture settings from the log viewer, and prevented transformer conflicts with other plugins.

= 2.8.0 — 2026-08-01 =
* Added: DirectAdmin-inspired File Manager, lazy WordPress-root folder tree, read-only protected locations, private ZIP downloads, and randomized protected debug storage.
* Fixed: Blocked traversal, symlink escapes, sensitive-file access, and unsafe writes.

= 2.7.3 — 2026-07-28 =
* Added: Safe Mode File Manager, Custom CSS & JS, Code Snippets, Maintenance Mode artwork selection, and improved User Switcher recovery and activity logging.
* Fixed: Code Snippets bootstrap order, generated-file ownership checks, multisite permissions, SMTP storage, transient previews, and uninstall cleanup matching.

= 2.7.2 — 2026-07-24 =
* Added: User Switcher, Database Manager and Server Diagnostics dashboards, Email Log actions, and responsive Debug Log details.
* Fixed: Overview module toggles, request overhead, capability checks, nonce validation, output escaping, SQL allow-lists, and package-path validation.

= 2.7.1 — 2026-07-21 =
* Added: Grouped Debug Log rows, expanded details, copy actions, severity indicators, filters, pagination, and native editor links.
* Fixed: Debug Log layout spacing and full-width log-row presentation.

= 2.7.0 — 2026-06-24 =
* Added: Shared WordPress-native admin design system and focused Server Diagnostics reporting.
* Fixed: Removed the retired Custom Error UI and safely cleaned its SiteIntelix-owned files and settings.

Earlier release history remains available in previous WordPress.org tags.

== Upgrade Notice ==

= 2.8.2 =
Adds protected local plugin-version switching and aligns plugin metadata and module presentation with WordPress.org release requirements.

= 2.8.1 =
Adds one WPConfigTransformer-based debug mode, a SiteIntelix-native log workspace, secure first-run setup, and protected clear/download controls.

= 2.8.0 =
Major File Manager workspace update with read-only WordPress-root browsing, protected randomized debug storage, safer temporary diagnostics, and cleaner SiteIntelix admin screens.

= 2.7.3 =
Security-focused major update with Safe Mode File Manager, Custom CSS & JS, isolated Code Snippets, safer multisite permissions, and improved User Switcher recovery.

= 2.7.2 =
Recommended performance, security, accessibility, and admin workflow update for Debug Log, Email Log, Database Manager, and Server Diagnostics.

== Privacy Policy ==

SiteIntelix sends no telemetry and does not upload logs, email contents, database contents, snippets, custom code, File Manager data, or generated diagnostic reports. Stored logs, SMTP credentials, settings, snippets, custom code, and File Manager backups, trash, metadata, and audit records remain in the WordPress installation and are available only through privileged administration interfaces. Enabled custom JavaScript and PHP snippets execute on the site according to their configured scope.

When an administrator runs or refreshes Server Diagnostics, reachability checks may make HTTP requests to WordPress.org endpoints and to the site's own loopback or REST URL. These requests include normal network metadata such as the site's public IP address and a SiteIntelix version user agent, but they do not include the contents of SiteIntelix reports.

When SMTP is enabled, WordPress sends outgoing email and authentication data to the administrator-selected SMTP provider under that provider's privacy terms. SiteIntelix stores the SMTP credential locally in a non-autoloaded option; it does not transmit the credential anywhere else.
