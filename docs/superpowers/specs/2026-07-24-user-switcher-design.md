# User Switcher Module Design

## Goal

Add a disabled-by-default SiteIntelix module that lets an authorized operator temporarily authenticate as another site user and securely return to the original account without reading or changing passwords.

## Existing Architecture Fit

SiteIntelix registers modules in `SITEINTELIX_Modules`, conditionally requires enabled module classes from `siteintelix.php`, boots them on `init`, and renders configurable module panels through `siteintelix_render_module_settings_sections`. User Switcher will use internal ID `user_switcher`, public slug/folder `user-switcher`, `SITEINTELIX_User_Switcher_*` class prefixes, existing `si-*` and `sitx-*` components, and screen-specific assets.

## Components

- `SITEINTELIX_User_Switcher_Module`: conditionally loads and coordinates the module.
- `SITEINTELIX_User_Switcher_Permissions`: dedicated capability, target-role policy, multisite membership and super-admin protection.
- `SITEINTELIX_User_Switcher_Session_Manager`: signed HTTP-only cookie, short-lived server record, WordPress session-token creation, switching, restoration, expiry, and logout cleanup.
- `SITEINTELIX_User_Switcher_Admin_Actions`: Users row/profile actions, nonce-protected switch/restore handlers, and server-rendered administrator confirmation.
- `SITEINTELIX_User_Switcher_Toolbar`: frontend/admin toolbar state and wp-admin fallback notice.
- `SITEINTELIX_User_Switcher_Settings`: dynamic role settings, redirect settings, duration validation, activity-log routing, and capability synchronization.
- `SITEINTELIX_User_Switcher_Logger`: table schema, audit writes, filters, pagination queries, bulk deletion, clear-all, and bounded retention.
- `SITEINTELIX_User_Switcher_Activator`: default options, schema, administrator capability, cron scheduling, and safe deactivation.

## Security Model

Starting a switch requires `siteintelix_switch_users`, a target-specific WordPress nonce, no active switch, a valid current site member, and an allowed target. Administrator and multisite super-admin targets are denied by default.

Before changing identity, the session manager creates a dedicated, expiring restoration token with `WP_Session_Tokens`, stores only its hash and session metadata server-side, places the raw restoration credential inside a signed HTTP-only SameSite=Lax cookie, destroys the operator's current browser token, then creates an expiring target session with WordPress core functions. The cookie contains no unsigned administrator ID.

Restoration requires the signed cookie, matching server record, matching current target, an unexpired restoration token, and a target-user nonce. It destroys the impersonated token, restores the original user through the dedicated WordPress session token, clears temporary state, completes the audit row, and safely redirects. An operator whose capability is later removed may still return, but cannot start another switch.

Invalid, expired, logged-out, salt-invalidated, or deleted-user sessions are cleared and audited where their server record can be trusted. Multiple browser sessions receive independent switch IDs and restoration tokens. Multiple tabs in one browser share the same switch state.

## Loading and Recovery

No User Switcher actions or settings load while the module is disabled. If a valid switching cookie already exists when another administrator disables the module, only the session/restore/toolbar cleanup path loads until that session returns or expires. It cannot initiate another switch.

## Data

Option `siteintelix_user_switcher_settings` stores operator roles, target roles, administrator policy, redirect modes/URLs, duration, logging, and 30-day retention. Option `siteintelix_user_switcher_managed_roles` records only role capabilities SiteIntelix added, preventing destructive removal of pre-existing custom capabilities.

The table `{$wpdb->prefix}siteintelix_user_switch_logs` stores audit metadata and an `expires_at` value used to classify unattended sessions. Active credentials are never stored in the audit table.

## UI

The module card uses the shared module card and toggle. Its settings panel contains server-rendered Settings and Activity Log views. The log view provides filters, pagination, selected deletion, and clear-all confirmation. Administrator targets use a dedicated confirmation screen. Active switching is highlighted in the toolbar and in a wp-admin fallback notice.

## Integrations

When redirect mode is `user_dashboard`, Tutor LMS is detected defensively through `tutor_utils()` and its dashboard method. Otherwise the homepage is used. No Tutor LMS or WooCommerce class is required. WooCommerce customers are handled through the standard WordPress Users action.

## Extensibility

The module exposes the four requested lifecycle actions plus prefixed filters for target policy, protected roles, switch redirect, return redirect, session duration, retention, and explicit external-redirect permission.
