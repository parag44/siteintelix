# User Switcher Submenu Design

## Goal

Move User Switcher settings and activity logs out of the combined SiteIntelix Settings page and into a dedicated **User Switcher** submenu under the existing SiteIntelix admin menu.

## Navigation

- Register **User Switcher** immediately above the existing **Settings** submenu.
- Register it only when the `user_switcher` module is enabled.
- Use the page slug `siteintelix-user-switcher`.
- Keep one submenu page with two server-rendered views:
  - **Settings**
  - **Activity Log**
- Select the view with an allow-listed query parameter. Settings is the default.

## Page

The page uses the existing SiteIntelix header, cards, buttons, form fields, table styling, spacing, and responsive behavior. The current settings and log views are reused rather than duplicated.

The combined **All Module Settings** page no longer registers or renders a User Switcher tab. The User Switcher module card links directly to the new submenu page.

## Routing

All User Switcher redirects are updated to the dedicated page:

- settings save;
- log filtering and pagination;
- delete-selected logs;
- clear-all logs;
- Settings and Activity Log navigation.

The existing nonces, capabilities, sanitization, logging, session behavior, and database schema remain unchanged.

## Loading and performance

- The submenu and page callback exist only while the module is enabled.
- User Switcher CSS loads only on the dedicated User Switcher screen.
- No additional JavaScript or frontend asset is introduced.
- The signed-session recovery path remains separate and does not register the disabled module's admin page.

## Verification

- Structural tests assert the dedicated submenu, page slug, new routes, removal from All Module Settings, and scoped stylesheet.
- Run PHP syntax checks and WordPress PHPCS for changed production files.
- Re-run the full SiteIntelix structural suite and User Switcher permission test.
- Rebuild and integrity-check the installable ZIP.
