# User Switcher Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a complete, secure, disabled-by-default User Switcher module inside SiteIntelix.

**Architecture:** Use focused `SITEINTELIX_User_Switcher_*` classes loaded only while enabled, plus a narrowly scoped recovery loader for an existing signed switch session. Authenticate and restore exclusively through WordPress session APIs, keep active credentials out of audit storage, and render all management UI server-side.

**Tech Stack:** WordPress 5.8+, PHP 7.4+, vanilla SiteIntelix admin UI, MySQL/dbDelta, WP-Cron, Node structural tests, WP-CLI runtime tests.

---

### Task 1: Define failing structural and runtime contracts

**Files:**
- Modify: `tests/structural.test.mjs`
- Create: `tests/user-switcher-runtime.php`

- [ ] Add structural assertions for registry metadata, conditional loading, dedicated capability, nonce handlers, signed cookie primitives, schema, settings panel, audit controls, hooks, filters, uninstall cleanup, and absence of password mutation.
- [ ] Add a WP-CLI runtime script that creates temporary operator/target users and checks authorized switching policy, unauthorized policy, self/admin/super-admin protection, chain prevention, redirect validation, cookie corruption/expiry rejection, logging completion, and disabled-module hook absence.
- [ ] Run `node --test --test-name-pattern='User Switcher' wp-content/plugins/siteintelix/tests/structural.test.mjs`.
- [ ] Confirm failure because the module files and registry entry do not exist.

### Task 2: Register the module and lifecycle

**Files:**
- Modify: `includes/class-siteintelix-modules.php`
- Modify: `siteintelix.php`
- Modify: `admin/views/modules-page.php`

- [ ] Register internal ID `user_switcher`, public slug `user-switcher`, icon `dashicons-admin-users`, exact description, disabled default, and settings metadata.
- [ ] Add conditional enabled loading, boot, activation, deactivation, and signed-cookie recovery loading.
- [ ] Add the module settings anchor to the existing module-card settings-link map.
- [ ] Run the targeted structural test and confirm registry/lifecycle assertions pass.

### Task 3: Implement activation, settings, permissions, and schema

**Files:**
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-activator.php`
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php`
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-permissions.php`
- Create: `includes/modules/user-switcher/views/settings.php`

- [ ] Create default settings with administrator operators, registered allowed target roles, administrator targets disabled, user-dashboard switch redirect, Users return redirect, 60-minute duration, logging enabled, and 30-day retention.
- [ ] Clamp session duration to 5–480 minutes and retention to 1–365 days.
- [ ] Add `siteintelix_switch_users` only to selected roles; track and remove only capabilities SiteIntelix itself added.
- [ ] Implement target validation with multisite membership and super-admin protection and the exact `siteintelix_user_switcher_can_switch` filter.
- [ ] Render and save dynamic role, redirect, duration, logging, and retention controls with `manage_options` and nonce checks.
- [ ] Create the audit schema through `dbDelta()` and schedule daily retention.

### Task 4: Implement signed sessions and WordPress authentication

**Files:**
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php`

- [ ] Encode a minimal JSON cookie payload containing switch ID, secret, and restoration token; sign it with `wp_salt( 'auth' )`; set it HTTP-only, SameSite=Lax, SSL-aware, and site-path scoped.
- [ ] Store the trusted session record in a short-lived transient with hashed credentials, original/target IDs, timestamps, previous URL, and redirect destination.
- [ ] Create dedicated restoration and target tokens through `WP_Session_Tokens`, destroy the original browser token, clear core cookies, and authenticate the target through `wp_set_current_user()` and `wp_set_auth_cookie()`.
- [ ] Validate signature, hashes, expiry, target identity, users, and restoration token on each request.
- [ ] Restore by destroying the target token, setting the original user and restoration cookie, clearing transient/cookie state, and invoking the required lifecycle actions.
- [ ] Mark expiry, logout, invalidation, deleted users, and corrupted trusted sessions safely.

### Task 5: Implement actions, notices, redirects, and confirmation

**Files:**
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php`
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php`
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-module.php`

- [ ] Add Users row actions and edit-user profile buttons only when the operator and target pass policy and no switch is active.
- [ ] Add target-specific switch nonces and target-session restore nonces.
- [ ] Add a hidden, server-rendered confirmation screen for administrator targets.
- [ ] Resolve same-origin switch/return redirects with the requested filters; use Tutor LMS dashboard defensively for `user_dashboard`.
- [ ] Add frontend/admin toolbar nodes and a wp-admin fallback notice.
- [ ] Register logout and request-validation cleanup.

### Task 6: Implement audit log operations and UI

**Files:**
- Create: `includes/modules/user-switcher/class-siteintelix-user-switcher-logger.php`
- Create: `includes/modules/user-switcher/views/logs.php`
- Create: `includes/modules/user-switcher/assets/user-switcher.css`

- [ ] Insert active rows and complete them as returned, expired, logged_out, or invalidated.
- [ ] Query logs with prepared pagination, username/email search, status filter, and date range.
- [ ] Render Administrator, Target, Started, Ended, Duration, Status, and IP columns with shared SiteIntelix table components.
- [ ] Add nonce/capability-protected selected deletion and clear-all actions.
- [ ] Delete retention rows in bounded daily batches and mark overdue active rows expired.
- [ ] Load the small CSS asset only on relevant screens or during active impersonation.

### Task 7: Cleanup, documentation, and verification

**Files:**
- Modify: `uninstall.php`
- Modify: `readme.txt`
- Create: `docs/user-switcher.md`

- [ ] Remove module options, tracked capabilities, cron, transients where discoverable, and the module-owned table only during uninstall.
- [ ] Document usage, capability, restrictions, security, logs, retention, hooks, filters, Tutor redirect behavior, and WooCommerce coverage.
- [ ] Run all Node tests, all PHP syntax checks, available runtime scripts, and coding-standard tooling if installed.
- [ ] Fix every failure, rebuild `siteintelix.zip` with production exclusions, and verify archive integrity.
- [ ] Do not commit or push to Git/SVN.
