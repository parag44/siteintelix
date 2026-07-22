# Debug Log Wide Layout and Settings Token Refresh Design

## Goal

Make the Modern Debug Log page use the available WordPress admin width, remove the expanded occurrence/details block marked by the user, and bring the Settings page into the same visual token system as the refreshed SiteIntelix screens.

## Debug Log Layout

- The Modern Debug Log shell should use a wider final layout cap of `1440px` instead of the older `1200px` cap.
- Summary/status/filter/log sections should remain centered, but the left and right whitespace shown in the screenshot should be reduced.
- The expanded details section should only exist when a log group has a real stack trace.
- Groups without a stack trace should not render View/Hide or arrow expansion controls.

## Removed Debug Log Detail UI

Remove these elements completely from Modern Debug Log grouped entries:

- Occurrence Timeline
- disabled “Show more” occurrence button
- Copy Details button
- View Full Log button
- the timeline-only details grid

Stack traces should remain available for real stack-trace entries, but should use the available card width directly.

## Settings Page Token Refresh

The Settings page should inherit the current SiteIntelix design token feel:

- wider content shell matching the refreshed pages
- consistent radius, border, shadow, spacing, input height, and button rhythm
- cleaner tab/search/header/card spacing
- responsive layout preserved for smaller screens

## Out of Scope

- No new Settings functionality.
- No new Debug Log filtering behavior.
- No database/schema changes.
- No slug changes.
