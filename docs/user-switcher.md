# User Switcher

User Switcher lets an authorised support operator temporarily enter another WordPress account without requesting, viewing, changing, or storing that account's password.

## Enable and configure

Enable **User Switcher** on **SiteIntelix → Modules**. Configure it under **SiteIntelix → Settings → User Switcher**, and review switching records under **SiteIntelix → User Switcher**. The dedicated activity-log submenu appears immediately above the main Settings item. The module is disabled by default and its switching hooks, shared settings tab, and submenu do not load while disabled. An existing signed session retains a minimal recovery path so the operator can return safely if the module is disabled mid-session.

The dedicated capability is `siteintelix_switch_users`. Administrators receive it when the module is activated. The **Allowed operator roles** setting can grant it to other roles. SiteIntelix tracks the role capabilities it added so it does not remove unrelated role customisations.

Settings include:

- allowed operator and target roles;
- administrator-target access, which is off by default;
- switch and return destinations;
- session duration from 5 to 480 minutes;
- activity logging and retention from 1 to 365 days.

Custom redirects are limited to the current site's origin unless a developer explicitly permits an external destination with `siteintelix_user_switcher_allow_external_redirect`.

## Switch and return

Open **Users → All Users** and select **Login as User**, or open another user's edit screen and use the same button. Protected or disallowed accounts do not show the action. Administrator targets require an extra confirmation when administrator switching is enabled.

During a switch, the WordPress toolbar displays **Viewing as {name}** with **Return to {original name}**. wp-admin also provides a fallback notice if the toolbar is unavailable. A switched user cannot switch directly to a third account.

While a switching session is fully validated, SiteIntelix forces the frontend WordPress toolbar to remain visible so LMS settings or the target user's toolbar preference cannot hide the return control. Toolbar preferences remain unchanged outside the switching session.

SiteIntelix processes its nonce-protected return action before ordinary LMS wp-admin access restrictions run. This allows a switched student or instructor to restore the original administrator even when the LMS otherwise blocks that account from wp-admin.

The default switch destination is the user's frontend dashboard or the site homepage. If Tutor LMS is active and exposes its supported dashboard helper, Tutor students and instructors can be sent to that dashboard. Tutor LMS is not required. WooCommerce customers use the standard WordPress Users action; there are no fragile screen overrides.

## Security

Every state-changing request uses a WordPress nonce and the dedicated capability. WordPress core creates all authentication cookies and session tokens. SiteIntelix never constructs an authentication cookie or handles a password.

The temporary switching cookie is blog-scoped for multisite, HTTP-only, SameSite=Lax, secure on HTTPS, short-lived, and HMAC-signed with WordPress authentication salts. It contains a random switch identifier and temporary restoration credentials; user IDs and session metadata stay in a short-lived server-side transient. Cookie signatures, server-side hashes, expiry, the current target identity, and both accounts are validated on requests. A target-session index and short atomic start lock prevent chained or racing switches.

By default, the current account, accounts without roles, administrators, multisite super administrators, invalid/deleted users, and users outside the current multisite site are protected. Administrator access can be enabled in settings, but super-administrator targets require an explicit developer filter.

Logging out, expiry, a corrupted cookie, changed salts, deleted accounts, or an interrupted authentication session invalidates the temporary state. If the original account no longer exists or its restoration credential is invalid, SiteIntelix does not elevate the current browser.

## Activity logging

When enabled, the module uses `{$wpdb->prefix}siteintelix_user_switch_logs` to record:

- original and target user IDs;
- switch identifier and timestamps;
- active, returned, expired, logged-out, or invalidated status;
- request IP address and a bounded user agent;
- the validated redirect destination.

No passwords or authentication tokens are logged. Open **SiteIntelix → User Switcher** to search by username/email, filter by status and date, paginate results, delete selected records, or clear all records. Destructive actions require `manage_options` and a nonce. A daily retention event removes bounded batches of old rows and schedules catch-up batches when needed; the default retention is 30 days.

## Hooks

Actions:

- `siteintelix_user_switcher_before_switch`
- `siteintelix_user_switcher_after_switch`
- `siteintelix_user_switcher_before_restore`
- `siteintelix_user_switcher_after_restore`

Filters:

- `siteintelix_user_switcher_can_switch`
- `siteintelix_user_switcher_switch_redirect`
- `siteintelix_user_switcher_return_redirect`
- `siteintelix_user_switcher_session_duration`
- `siteintelix_user_switcher_protected_roles`
- `siteintelix_user_switcher_log_retention`
- `siteintelix_user_switcher_allow_external_redirect`

The capability and nonce checks occur independently of `siteintelix_user_switcher_can_switch`; the filter cannot grant switching authority to an unauthorised operator.
