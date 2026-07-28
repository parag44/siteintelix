# SiteIntelix Early Email Capture Design

**Date:** 2026-07-24

## Problem

SiteIntelix currently registers its `wp_mail_succeeded` and `wp_mail_failed`
listeners from `siteintelix_boot_enabled_modules()`, which runs on WordPress
`init` at priority 20.

Tutor LMS processes its password-retrieval form on `init` at the default
priority 10. That handler calls WordPress `retrieve_password()`, which sends
the reset message through `wp_mail()` before SiteIntelix has registered its
listeners. The email may be sent successfully, but no SiteIntelix log row is
created.

The issue is architectural rather than Tutor-specific: any plugin that calls
`wp_mail()` before SiteIntelix's priority-20 `init` callback can evade capture.

## Goal

Capture every standard WordPress `wp_mail()` success or failure event that
occurs after the SiteIntelix plugin file has loaded, including Tutor LMS
password-reset emails sent during early `init`.

SiteIntelix cannot observe mail sent before WordPress loads SiteIntelix itself.
That is the explicit boundary of this guarantee.

## Chosen Approach

Register only the lightweight Email Log capture listeners during SiteIntelix's
early plugin bootstrap. Keep the rest of the module lifecycle—schema checks,
retention scheduling, admin menus, actions, and UI registration—on the existing
normal boot path.

This approach provides wider capture coverage without moving database migration
or admin setup work into plugin-file loading.

## Architecture

### Early bootstrap

The main SiteIntelix plugin file will load the module registry and, when the
Email Log module is enabled, load the Email Log class and register its mail
capture hooks before WordPress fires `init`.

The early path will not create tables, run retention, or register admin UI.

### Idempotent capture registration

The Email Log module will expose a focused method that registers:

- `wp_mail_succeeded` → `SITEINTELIX_Email_Log_Module::log_success`
- `wp_mail_failed` → `SITEINTELIX_Email_Log_Module::log_failure`

Registration will be guarded so repeated calls in one request do not attach
duplicate callbacks. The existing full `init()` method will call this method
as a safe fallback and then register the module's remaining hooks.

### Existing data path

Captured events will continue through the existing `log_success()`,
`log_failure()`, and `insert_log()` methods. Existing settings, sanitization,
database schema, status values, retention behavior, and error-alert exclusions
will remain unchanged.

## Data Flow

1. WordPress loads the SiteIntelix plugin file.
2. SiteIntelix checks whether the Email Log module is enabled.
3. SiteIntelix attaches the success and failure listeners.
4. Tutor LMS or another plugin calls `wp_mail()`.
5. WordPress fires `wp_mail_succeeded` or `wp_mail_failed`.
6. SiteIntelix inserts one email-log row through the existing persistence path.
7. The normal SiteIntelix `init` callback completes module setup without
   duplicating the capture listeners.

## Error Handling

- If Email Log is disabled, no early listeners will be registered.
- If logging is disabled in Email Log settings, the existing `insert_log()`
  guard will continue to skip persistence.
- A failed `wp_mail()` call will continue to create a `failed` log row using
  the WordPress error payload.
- Duplicate listener registration must be prevented so a single email never
  produces duplicate SiteIntelix rows.
- No Tutor LMS classes or hooks will be referenced, preserving compatibility
  when Tutor LMS is absent or updated.

## Testing

Add a regression test before production changes that fails against the current
bootstrap order and proves:

1. Email capture is registered before the priority-20 module boot.
2. Both `wp_mail_succeeded` and `wp_mail_failed` are covered.
3. Capture registration is idempotent.
4. The solution contains no Tutor LMS-specific integration.

After the minimal implementation, run:

- `node --test tests/structural.test.mjs`
- `php tests/debug-log-parser.php`
- `php tests/editor-links.php`
- `php tests/runtime-smoke.php`
- PHP syntax checks for every changed PHP file

## Out of Scope

- Capturing mail sent without WordPress `wp_mail()`.
- Replacing or changing Tutor LMS password-reset behavior.
- Changing SMTP delivery settings.
- Refactoring Email Log storage, retention, or UI.
- Guaranteeing capture before the SiteIntelix plugin file itself is loaded.
