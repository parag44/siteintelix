# User Switcher Settings and Activity Log Split

**Date:** 2026-07-24

## Goal

Keep User Switcher configuration with the plugin's other module settings while retaining a dedicated SiteIntelix submenu for switching activity.

## Navigation

- **SiteIntelix → Settings** includes the User Switcher settings tab.
- **SiteIntelix → User Switcher** opens the User Switcher Activity Log directly.
- The dedicated submenu remains immediately above the main SiteIntelix Settings item.
- The User Switcher module card's **Settings** button opens the User Switcher tab on the main Settings page.

## Main Settings Page

The shared settings registry and page include User Switcher again. Its existing server-rendered settings form remains unchanged, including:

- operator and target role selection;
- administrator-target protection;
- switch and return redirect settings;
- session duration;
- activity logging and retention.

After saving, the form redirects back to the User Switcher tab on **SiteIntelix → Settings** and displays the existing success notice.

## Dedicated Submenu

The dedicated **User Switcher** submenu renders only the Activity Log. It has no Settings/Activity Log tab switcher.

The page keeps:

- the standard SiteIntelix page header;
- search, status, and date filters;
- pagination;
- selected-log deletion;
- clear-all with confirmation;
- capability and nonce protection.

Log filters, pagination, and destructive-action redirects remain on `admin.php?page=siteintelix-user-switcher`.

## Loading and Performance

- The submenu and User Switcher runtime continue to register only while the module is enabled.
- User Switcher settings markup loads only on the shared SiteIntelix Settings screen.
- Activity-log markup and module-specific log styles load only on the dedicated submenu.
- No JavaScript framework or new dependency is added.

## Compatibility

- Existing secure switching sessions, cookies, permissions, audit records, hooks, and retention jobs are unchanged.
- Disabling the module removes both its shared settings tab and dedicated submenu while preserving the existing recovery path for an active impersonation session.

## Verification

- Structural tests confirm the shared User Switcher settings tab and dedicated log-only submenu.
- PHP syntax checks cover all changed PHP files.
- WordPress Coding Standards checks cover changed production files.
- Existing User Switcher permission tests and the full SiteIntelix structural suite must pass.
- The installable ZIP is rebuilt only after all checks pass.
