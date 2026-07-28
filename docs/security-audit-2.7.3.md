# SiteIntelix 2.7.3 Release and Security Audit

Audit date: 2026-07-28
Release branch: `2.7.3`
Audit baseline: `origin/2.7.1-stable`, with the current 2.7.2 development work included in the release candidate

## Scope

The complete distributable plugin was reviewed, including bootstrap and uninstall
logic, module registration, admin pages, AJAX and `admin-post.php` handlers,
database access, generated and downloaded files, MU-file lifecycle, custom CSS/JS,
PHP snippet execution, frontend output, assets, WordPress.org metadata, and the
release package allowlist.

The audit specifically covered nonce verification, capabilities, multisite
permissions, validation and sanitization, escaping, SQL construction, AJAX and
REST exposure, file upload/access behavior, CSRF, XSS, privilege escalation,
unauthorized data access, dynamic execution and inclusion, deserialization,
shell execution, direct PHP access, secrets, debug information, Plugin Directory
guidelines, and WordPress Coding Standards.

## Confirmed Security Issues Fixed

### High: sensitive modules relied on a broad administrator capability

Code execution, database management, log access, downloads, and safe-mode tools
used `manage_options` inconsistently. On multisite installations this could give a
site administrator access to operations that affect executable code or global
state.

Fixed by introducing `SITEINTELIX_Security` as the central policy:

- Code modules require `manage_options` and `unfiltered_html`; multisite also
  requires a super administrator.
- Global diagnostic/database/file tools require `manage_options`; multisite also
  requires a super administrator.
- Menu visibility, page access, mutations, settings, and module toggles now use
  the same policy.

### High: MU cleanup could remove files not owned by SiteIntelix

Legacy cleanup was based too heavily on file names. A foreign plugin file with a
matching name could be removed.

Fixed by requiring both an exact retired filename and a complete SiteIntelix
signature. Symlinks and files whose contents do not match the owned signature are
preserved. Deactivation/uninstall cleanup remains limited to the explicit
SiteIntelix MU-file registry.

### Medium: generated custom-code paths trusted stored metadata

A tampered `generated_file` value could point outside SiteIntelix's generated
asset directory and be used by cleanup or URL generation.

Fixed with an exact managed-path format:
`siteintelix/custom-code/site-<site-id>-entry-<entry-id>.(css|js)`. File deletion,
URL creation, and runner loading reject all other paths and do not follow
symlinks.

### Medium: transient previews could instantiate serialized objects

Previewing arbitrary transient values used deserialization without disabling PHP
class instantiation.

Fixed with a preview-only deserializer using
`unserialize( ..., array( 'allowed_classes' => false ) )`. The stored value is not
executed or mutated.

### Medium: SMTP credentials were autoloaded

The SMTP option, including its password, could be loaded into memory on every
request.

Fixed by creating and updating the option with autoload disabled and migrating
existing installs to `autoload = no`. Password sanitization now preserves valid
special characters while removing NUL bytes and enforcing a defensive length
limit.

### Low: response and direct-access hardening gaps

Some exports/downloads did not send `X-Content-Type-Options: nosniff`, one shipped
PHP placeholder did not have a direct-access guard, and one numeric runner value
was not escaped at its output boundary.

Fixed by adding the header to download/export responses, adding the missing
`ABSPATH` guard, and escaping the runner value.

### Low: uninstall cleanup and ownership checks were incomplete

Some plugin options/user metadata/transients were left behind, while custom-code
cleanup needed a stricter ownership boundary.

Fixed by removing the documented SiteIntelix options, user metadata, and
transients. Generated asset cleanup only removes exact SiteIntelix-managed files
and preserves foreign files and symlinks.

## Other Security Review Results

- Nonces and capability checks were reviewed for authenticated AJAX and
  `admin-post.php` handlers; targeted custom-sanitizer and authorization comments
  were added where static analysis could not infer the validation.
- No unauthenticated AJAX endpoints were found.
- No REST routes were found.
- No active file-upload handler was found. Dormant snippet transfer UI code is
  not registered or exposed in the current release.
- Database identifiers are selected from fixed or validated allowlists. Dynamic
  SQL values use `$wpdb->prepare()` where applicable; narrowly scoped
  PluginCheck annotations document fixed-identifier queries.
- No shell command execution, remote code loading, uncontrolled dynamic include,
  exposed private key, API secret, password, or telemetry endpoint was found.
- Exactly one `eval` remains. It is the intentional isolated-closure PHP snippet
  runner and is protected by administrator authorization, nonce checks, native
  PHP syntax validation, automatic runtime-error deactivation, and recovery
  handling.
- The only direct `unserialize()` use now disables allowed classes.
- Dynamic includes use plugin-owned fixed paths or allowlisted view mappings.

## WordPress.org Metadata and Guideline Changes

- Version and stable tag updated to 2.7.3.
- Compatibility states WordPress 7.0 and PHP 7.4 or later.
- Tags reduced to five focused directory tags:
  `debug log`, `email log`, `diagnostics`, `code snippets`, `admin tools`.
- Short description was rewritten to remain within the directory limit.
- Full description now covers Custom CSS/JS, Code Snippets, User Switcher, SMTP,
  multisite authorization, external SMTP-provider communication, data retention,
  uninstall behavior, lack of telemetry, and the intentional PHP execution model.
- Changelog and 2.7.3 upgrade notice were added.
- Distribution exclusions remove Git data, local planning artifacts, tests,
  development-only files, and nested ZIP files.

No confirmed Plugin Directory guideline violation remains in the distributable
release candidate. The PHP snippet feature remains security-sensitive by design
and is disclosed prominently.

## Files Changed

The release changes are grouped as follows:

- Bootstrap, versioning, migration, uninstall, security policy, module registry,
  health/system information, and WordPress.org metadata.
- Admin views and the scoped SiteIntelix design-system CSS/JavaScript.
- Custom CSS/JS, Code Snippets, User Switcher, Debug Log, Database Manager,
  Download Manager, Safe Mode, SMTP, Transients, Cron Events, Coming Soon, and
  Email Log modules.
- MU-file ownership and cleanup components.
- Structural, security, parser, editor, runtime-smoke, module, and UI tests.
- Release documentation, module documentation, `.distignore`, and `.gitignore`.

The authoritative file-by-file list is the Git diff for the `2.7.3` branch
against `origin/2.7.1-stable`.

## Verification Results

- Node structural/UI tests: 108 passed, 0 failed.
- Standalone PHP test scripts: all passed.
- PHP syntax lint across all shipped and test PHP files: passed.
- JavaScript syntax checks across admin and module assets: passed.
- PluginCheck PHPCS standard: passed with 0 findings.
- WordPress Security sniffs: 0 errors; 47 warnings were manually reviewed as
  read-only query-string, nonce, dispatcher, or fixed/allowlisted SQL patterns.
- Full WordPress Coding Standards scan: 992 errors and 410 warnings across 46
  files, primarily pre-existing whitespace, naming, documentation, and formatting
  debt; 1,081 findings are mechanically fixable. A broad automated rewrite was
  intentionally not applied during the security release because it would create
  high-risk unrelated churn.
- Distribution package inspection: passed. Required plugin files are present;
  `.git`, `.superpowers`, docs, tests, and development artifacts are absent; all
  packaged PHP files lint clean.
- WordPress CLI reports WordPress 7.0.2.
- A live non-WP-CLI WordPress bootstrap using Local PHP 8.5.3 and the Local
  database passed. The security policy, snippets context, and snippets runner
  loaded successfully, and the context method involved in the reported early
  bootstrap fatal completed without error.
- A browser smoke test could not complete because the Local web server was
  stopped. `wp plugin check siteintelix` was unavailable because this WP-CLI
  installation does not register the `plugin check` command; the standalone
  PluginCheck PHPCS ruleset completed successfully instead.

## Remaining Risks and Manual Review

1. PHP snippets necessarily execute administrator-authored PHP through `eval`.
   The authorization, validation, recovery, and disclosure controls reduce the
   risk but cannot make arbitrary PHP intrinsically safe.
2. SMTP passwords are stored as WordPress option data in plaintext at rest,
   matching common WordPress plugin behavior. They are no longer autoloaded.
   Database and backup access must remain protected.
3. Logs and database tools expose sensitive operational data to the trusted roles
   described above; administrators should use least-privilege accounts.
4. Multisite behavior should receive a final manual smoke test on a real
   multisite installation, especially super-admin module toggles.
5. Run WordPress Plugin Checker after its WP-CLI command is registered, and run
   the browser smoke tests after the Local web server is started.
6. Address the remaining WordPress Coding Standards formatting debt in a separate
   no-functional-change cleanup release.
