# User Switcher Toolbar Recovery Design

## Problem

SiteIntelix attempts to expire its switching cookie when no switching cookie exists. The toolbar requests active-session state during `wp_head` or `admin_head`, after WordPress may have printed stylesheet markup. The resulting late `setcookie()` call produces a “Cannot modify header information” warning.

Tutor LMS and WordPress user preferences can also hide the frontend admin bar for an impersonated non-administrator. Without the bar, the switched user cannot see SiteIntelix’s “Return to” control and the original administrator can appear stranded.

Tutor LMS can additionally deny non-administrators access to all wp-admin requests during `admin_init`. WordPress runs `admin_init` before `admin-post.php` dispatches the SiteIntelix restore action, so the visible return link can reach an “Access Denied!” screen without executing the restore handler.

## Scope

The change will:

- avoid sending a cookie header when no SiteIntelix switching cookie exists;
- force the WordPress admin bar to display on the frontend only while the current identity has a fully validated SiteIntelix switching session;
- dispatch the SiteIntelix restore action before ordinary `admin_init` access restrictions can block `admin-post.php`;
- preserve Tutor LMS settings and WordPress toolbar preferences outside a switching session;
- retain the existing SiteIntelix toolbar nodes and wp-admin fallback notice.

It will not add a separate floating return button or change Tutor LMS settings.

## Design

### Session handling

`SITEINTELIX_User_Switcher_Session_Manager::read_session()` will return `false` immediately when `has_cookie()` is false. Cookie expiry will remain limited to requests where a cookie exists but is malformed, expired, or otherwise invalid.

This places the distinction at the shared session-reading boundary, protecting every caller instead of only the toolbar.

### Admin-bar enforcement

`SITEINTELIX_User_Switcher_Toolbar::init()` will register a `show_admin_bar` filter at the highest practical priority. Its callback will:

1. request the fully validated active session from the session manager;
2. return `true` when that session exists;
3. otherwise return the incoming visibility value unchanged.

Because the callback preserves the incoming value outside an active switch, it will not override Tutor LMS or user preferences for ordinary sessions.

WordPress request types that categorically disable the admin bar—AJAX, XML-RPC, embeds, iframes, and JSON requests—will remain governed by WordPress core.

### Early restore dispatch

`SITEINTELIX_User_Switcher_Admin_Actions::init_recovery()` will register an `admin_init` callback at priority `0`, before Tutor LMS’s default-priority admin-area restriction.

The callback will do nothing unless both conditions are true:

1. the current WordPress script is `admin-post.php`;
2. the scalar, sanitized request action exactly equals `siteintelix_user_switcher_restore`.

For a matching request, it will dispatch the existing registered `admin_post_siteintelix_user_switcher_restore` action. The existing restore handler will continue to verify the WordPress nonce and fully validated switching session before restoring the original identity and redirecting.

Unrelated wp-admin and `admin-post.php` requests will remain untouched. The implementation will not inspect, modify, or unhook Tutor LMS callbacks.

## Error Handling and Security

Malformed or expired cookies will continue to be invalidated during the early `init` validation path. The admin-bar filter will not trust cookie presence alone: it will use the existing signed-cookie, transient, target-identity, target-token, expiry, and user-existence validation.

If validation fails, the bar will not be forced and the existing invalidation behavior will apply.

Early dispatch does not bypass authorization. A missing or invalid nonce still fails in `check_admin_referer()`, and a missing, invalid, expired, or identity-mismatched switching session still fails through the existing restore logic.

## Tests

Regression coverage will verify:

- reading session state after output has begun does not attempt `setcookie()` when no switching cookie exists;
- an active validated switch forces the `show_admin_bar` result to `true`;
- no active switch preserves an incoming `false` result;
- the visibility filter is registered with late priority;
- the early restore callback is registered before default-priority access restrictions;
- only the exact SiteIntelix restore action on `admin-post.php` is dispatched early;
- unrelated wp-admin and admin-post actions are ignored;
- existing SiteIntelix structural and User Switcher tests continue to pass;
- edited PHP files pass syntax checks.

The User Switcher documentation will state that SiteIntelix forces the frontend toolbar only for a validated active switch.
