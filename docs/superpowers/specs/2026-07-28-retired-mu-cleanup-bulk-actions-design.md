# Retired MU Cleanup and Bulk Actions Design

## Goals

1. Automatically remove the retired SiteIntelix Plugin Safety Guard MU file from existing installations.
2. Make bulk actions work on the Code Snippets and Custom CSS & JS management pages.
3. Allow Code Snippets to use valid mixed PHP/HTML templates.
4. Preserve unrelated MU files and existing confirmation behavior for ordinary links.

## Root causes

### Retired MU file

`siteintelix-plugin-safety-guard.php` is a retired SiteIntelix bootstrap. Current runtime code does not generate or use it. The ownership manager can already identify and remove it safely, but that removal currently runs only during full plugin deactivation or uninstall. Existing active installations therefore retain stale copies.

### Bulk actions

Both management pages attach `data-siteintelix-confirm` to a submit button. The shared confirmation code treats every confirmed element as a link and calls `window.location.assign(element.href)`. A button has no destination URL, so confirming never submits the bulk-action form.

### Mixed PHP/HTML snippets

The snippet validator manually rejects every real PHP closing or reopening tag even when PHP's native parser accepts the source. The reported PDF viewer starts in PHP mode, temporarily closes PHP to print a large CSS/JavaScript template, and reopens PHP before the callback ends. The isolated `eval()` runner supports this native PHP pattern, but the manual tag policy prevents the snippet from being saved.

## Retired safety-guard migration

Extend the existing versioned admin migration:

1. Advance the internal migration version so installations already migrated at `2.7.2` run the new cleanup once.
2. Call `SITEINTELIX_MU_Files::remove_type(SITEINTELIX_MU_Files::TYPE_SAFETY_GUARD)`.
3. Rely on the existing fail-closed checks:
   - Exact allowlisted filename.
   - Approved active or standard MU directory.
   - Regular, readable, non-symlink file.
   - Complete SiteIntelix ownership signature in its contents.
4. If deletion fails, do not advance the migration version, allowing a later admin request to retry.
5. Treat missing or unverified files as safe completion. Unverified files remain untouched.

The migration must not scan directories, use wildcards, or alter WP Safe Mode, WPMgr, or other third-party files.

## Shared confirmation behavior

Generalize the shared confirmation helper from link-only navigation to confirmed actions:

- For an anchor with an `href`, preserve the current navigation behavior.
- For a submit button associated with a form, submit that form after confirmation.
- Prefer `form.requestSubmit(button)` when available so native form validation and submit events remain active.
- Use `form.submit()` only as a compatibility fallback.
- Close the dialog before performing the confirmed action.
- Preserve cancellation, Escape handling, focus restoration, and keyboard focus containment.

The Code Snippets and Custom CSS & JS Apply controls will explicitly declare `type="submit"`.

## Native snippet syntax validation

Use PHP's parser as the sole authority for snippet syntax:

1. Continue prefixing the stored snippet with a synthetic `<?php` tag for validation because stored snippets start in PHP mode.
2. Parse with `token_get_all($source, TOKEN_PARSE)`.
3. Remove the manual opening/closing-tag rejection.
4. Accept native transitions such as `?> ... <?php` when the complete source parses.
5. Continue rejecting malformed mixed templates through the bounded native parse error.
6. Continue rejecting `__halt_compiler`.
7. Leave the isolated closure runner and automatic runtime-error deactivation unchanged.

An initial opening tag remains unnecessary because SiteIntelix already places the snippet in PHP mode. The editor help will state that snippets start in PHP mode and that closing/reopening PHP is allowed when emitting template markup.

## Backend behavior

The existing authenticated handlers remain unchanged:

- Code Snippets receives `bulk_action` and `snippet_ids[]`.
- Custom CSS & JS receives `bulk_action` and `entry_ids[]`.
- Existing nonces and `manage_options` checks remain authoritative.

## Testing

### Migration regression

Extend the dependency-free MU test harness to prove:

- A verified retired safety guard is removed by the migration.
- A same-name file without the full SiteIntelix signature is preserved.
- A deletion failure prevents the migration version from advancing.

### Confirmation regression

Add a dependency-free JavaScript test for the shared confirmation helper:

- Confirming an anchor navigates to its URL.
- Confirming a submit button invokes its associated form submission.
- Cancelling performs neither action.

Structural coverage will require:

- Both bulk Apply controls are explicit submit buttons.
- Both retain confirmation messages.
- The shared confirmation script supports forms rather than assuming every target has an `href`.

### Snippet syntax regression

Extend the dependency-free validator tests to prove:

- The complete attached Tutor LMS PDF viewer is accepted unchanged.
- A minimal valid `?> HTML <?php` template is accepted.
- Invalid tagless PHP still returns a native parse error.
- A malformed mixed PHP/HTML transition returns a native parse error.
- `__halt_compiler` remains prohibited.
- The obsolete “Enter PHP without opening or closing tags” policy is absent from runtime validation and editor help.

### Full verification

- PHP lint for changed PHP files.
- JavaScript syntax check for the shared script and its test.
- All dependency-free PHP tests.
- Complete Node structural suite.
- Live browser verification that both bulk pages submit selected rows and the retired MU entry disappears.
