# SiteIntelix 2.7.2 WordPress.org Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish SiteIntelix 2.7.2 to WordPress.org with verified production code and nine current screenshots.

**Architecture:** Capture screenshots from the authenticated local WordPress admin, validate and optimize them locally, then stage all production code and assets in a fresh WordPress.org SVN checkout. Verification occurs before and after the single release commit; no Git operation is involved.

**Tech Stack:** WordPress, PHP, Node.js tests, Chrome browser capture, PNG assets, rsync, Subversion, WordPress.org Plugin Directory.

**Repository constraint:** Do not commit or push through Git. The only source-control write is the approved WordPress.org SVN release.

---

## File Map

- Modify `wp-content/plugins/siteintelix/readme.txt` — align nine screenshot captions.
- Create temporary `/private/tmp/siteintelix-release-assets/screenshot-1.png` through `screenshot-9.png`.
- Create temporary SVN checkout `/private/tmp/siteintelix-wporg-2.7.2/`.
- Update SVN `/trunk` — verified SiteIntelix 2.7.2 production files only.
- Update SVN `/assets` — nine screenshots while retaining banner and icon files.
- Create SVN `/tags/2.7.2` — immutable release copy of staged trunk.

### Task 1: Verify Release Metadata and Remote State

- [ ] **Step 1: Confirm local version alignment**

Run:

```bash
rg -n "^ \\* Version:|^Stable tag:|= 2\\.7\\.2" \
	wp-content/plugins/siteintelix/siteintelix.php \
	wp-content/plugins/siteintelix/readme.txt
```

Expected: plugin header, stable tag, changelog, and upgrade notice all identify `2.7.2`.

- [ ] **Step 2: Confirm the remote tag does not exist**

Run:

```bash
svn ls https://plugins.svn.wordpress.org/siteintelix/tags/
```

Expected: `2.7.2/` is absent.

- [ ] **Step 3: Verify the existing WordPress.org asset inventory**

Run:

```bash
svn ls https://plugins.svn.wordpress.org/siteintelix/assets/
```

Expected: existing banners, icon, and screenshots 1–6 are listed.

### Task 2: Capture Nine Authentic Screenshots

**Files:**
- Create: `/private/tmp/siteintelix-release-assets/screenshot-1.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-2.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-3.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-4.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-5.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-6.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-7.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-8.png`
- Create: `/private/tmp/siteintelix-release-assets/screenshot-9.png`

- [ ] **Step 1: Confirm authenticated local UI and v2.7.2**

Open:

```text
http://localhost:10106/wp-admin/admin.php?page=siteintelix
```

Expected: SiteIntelix Overview is visible and the header badge says `v2.7.2`.

- [ ] **Step 2: Capture the Overview**

Navigate to:

```text
admin.php?page=siteintelix
```

Capture the normal browser viewport as `screenshot-1.png`. It must show the health summary, System Overview, and Active Modules.

- [ ] **Step 3: Capture Modules**

Navigate to:

```text
admin.php?page=siteintelix-modules
```

Capture `screenshot-2.png` with active module cards, including User Switcher.

- [ ] **Step 4: Capture Modern Debug Log**

Navigate to:

```text
admin.php?page=siteintelix-debug-log
```

Capture `screenshot-3.png` with status, filters, and representative log rows visible. Do not open a row containing credentials or private tokens.

- [ ] **Step 5: Capture Email Log**

Navigate to:

```text
admin.php?page=siteintelix-email-log
```

Capture `screenshot-4.png` with the toolbar, filters, and log table. Ensure no modal is open and no sensitive email body is visible.

- [ ] **Step 6: Capture Database Manager**

Navigate to:

```text
admin.php?page=siteintelix-database-manager
```

Capture `screenshot-5.png` with metrics, filters, and the table list.

- [ ] **Step 7: Capture Server Diagnostics**

Navigate to:

```text
admin.php?page=siteintelix-server-diagnostics
```

Capture `screenshot-6.png` with health metrics, filters, category summaries, and the right-side report panel.

- [ ] **Step 8: Capture User Switcher Activity Log**

Navigate to:

```text
admin.php?page=siteintelix-user-switcher
```

Capture `screenshot-7.png` with filters, table, statuses, and log actions.

- [ ] **Step 9: Capture User Switcher Settings**

Navigate to:

```text
admin.php?page=siteintelix-settings&tab=user_switcher#siteintelix-user-switcher-settings
```

Capture `screenshot-8.png` with the User Switcher tab active and the role/session controls visible.

- [ ] **Step 10: Capture Cron Events**

Navigate to:

```text
admin.php?page=siteintelix-cron-events
```

Capture `screenshot-9.png` with search/filter controls and event rows.

- [ ] **Step 11: Validate image files**

Run:

```bash
for file in /private/tmp/siteintelix-release-assets/screenshot-*.png; do
	sips -g pixelWidth -g pixelHeight "$file"
done
```

Expected: nine readable PNGs with the same browser viewport dimensions.

- [ ] **Step 12: Review all nine screenshots**

Inspect each file visually. Reject and recapture any image with:

- a login screen or error page;
- cropped primary controls;
- an open modal or tooltip;
- stale version text;
- credentials, tokens, private email bodies, or other unnecessary personal data;
- unreadable text.

### Task 3: Align Readme Screenshot Captions

**Files:**
- Modify: `wp-content/plugins/siteintelix/readme.txt`
- Test: `wp-content/plugins/siteintelix/tests/structural.test.mjs`

- [ ] **Step 1: Add the nine-caption structural contract**

Update the release metadata structural test to verify these exact numbered screenshot topics:

```text
1. Overview Dashboard
2. Modules
3. Modern Debug Log Viewer
4. Email Log
5. Database Manager
6. Server Diagnostics
7. User Switcher
8. Settings
9. Cron Events
```

- [ ] **Step 2: Run the structural test and verify it fails**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: FAIL because the readme currently has only seven captions in a different order.

- [ ] **Step 3: Update `readme.txt`**

Replace the Screenshots section with:

```text
1. **Overview Dashboard** — minimal health cards, key system details, report actions, and quick module toggles.
2. **Modules** — enable or disable toolbox modules, open available tools, and review planned modules from one screen.
3. **Modern Debug Log Viewer** — grouped cards, severity filters, occurrence counts, stack traces, clear, refresh, and download actions.
4. **Email Log** — captured email events with delivery status, recipients, previews, filters, bulk actions, and row actions.
5. **Database Manager** — server-rendered database metrics, table search, sorting, pagination, and safe record inspection.
6. **Server Diagnostics** — health score, categorized checks, filters, report actions, and detailed system diagnostics.
7. **User Switcher** — searchable switching activity with session status, duration, and protected log actions.
8. **Settings** — module settings with User Switcher roles, redirects, session duration, logging, and retention.
9. **Cron Events** — scheduled events with search, countdowns, due-now status, run actions, and delete actions.
```

- [ ] **Step 4: Re-run the structural test**

Run:

```bash
node --test wp-content/plugins/siteintelix/tests/structural.test.mjs
```

Expected: all structural tests pass.

### Task 4: Re-run the Full Release Verification

- [ ] **Step 1: Run PHP behavior checks**

Run:

```bash
php wp-content/plugins/siteintelix/tests/user-switcher-permissions.php
php wp-content/plugins/siteintelix/tests/debug-log-parser.php
php wp-content/plugins/siteintelix/tests/editor-links.php
```

Expected: all scripts exit `0`.

- [ ] **Step 2: Lint every PHP file**

Run:

```bash
find wp-content/plugins/siteintelix -path '*/retired' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 3: Run WordPress Coding Standards**

Run the established Plugin Check PHPCS command against all changed production PHP files.

Expected: zero errors and warnings.

- [ ] **Step 4: Validate package exclusions**

Rebuild and inspect the local installable ZIP. Confirm it excludes tests, development plans, retired code, Git metadata, and nested archives.

### Task 5: Stage a Fresh WordPress.org SVN Working Copy

**Files:**
- Create: `/private/tmp/siteintelix-wporg-2.7.2/`

- [ ] **Step 1: Create a fresh checkout**

Run:

```bash
svn checkout https://plugins.svn.wordpress.org/siteintelix /private/tmp/siteintelix-wporg-2.7.2
```

Expected: clean checkout with `/assets`, `/tags`, and `/trunk`.

- [ ] **Step 2: Synchronize production code into trunk**

From `wp-content/plugins`, run:

```bash
rsync -a --delete \
	--exclude '.git/' \
	--exclude '.svn/' \
	--exclude '.DS_Store' \
	--exclude '.superpowers/' \
	--exclude 'docs/superpowers/' \
	--exclude 'tests/' \
	--exclude 'retired/' \
	--exclude 'includes/modules/error-ui/' \
	--exclude '*.zip' \
	--exclude '.distignore' \
	siteintelix/ /private/tmp/siteintelix-wporg-2.7.2/trunk/
```

- [ ] **Step 3: Stage SVN additions and removals**

Run `svn add --force` on trunk, inspect `svn status`, and schedule every versioned path marked missing with `svn rm --force`.

Expected: no `?` or `!` entries remain.

- [ ] **Step 4: Replace screenshots**

Copy `screenshot-1.png` through `screenshot-9.png` into `/assets`, remove obsolete screenshot files not in the approved set, apply `svn:mime-type image/png`, and stage new files.

- [ ] **Step 5: Create the local release tag**

Run:

```bash
svn copy /private/tmp/siteintelix-wporg-2.7.2/trunk \
	/private/tmp/siteintelix-wporg-2.7.2/tags/2.7.2
```

Expected: `tags/2.7.2` is scheduled for addition and mirrors trunk.

### Task 6: Audit the Exact SVN Release

- [ ] **Step 1: Inspect SVN status and diff**

Run:

```bash
svn status /private/tmp/siteintelix-wporg-2.7.2
svn diff /private/tmp/siteintelix-wporg-2.7.2
```

Expected: only intended trunk, asset, and 2.7.2 tag changes.

- [ ] **Step 2: Scan staged files for forbidden artifacts**

Run:

```bash
find /private/tmp/siteintelix-wporg-2.7.2/trunk \
	-name '.git' -o \
	-name '.svn' -o \
	-name '.superpowers' -o \
	-name 'tests' -o \
	-name '*.zip' -o \
	-name '.DS_Store'
```

Expected: only the checkout-managed root `.svn` exists outside trunk; no forbidden result appears inside trunk.

- [ ] **Step 3: Compare trunk and tag**

Run:

```bash
diff -qr \
	/private/tmp/siteintelix-wporg-2.7.2/trunk \
	/private/tmp/siteintelix-wporg-2.7.2/tags/2.7.2
```

Expected: no differences other than SVN administrative metadata.

- [ ] **Step 4: Confirm screenshot and caption counts**

Run:

```bash
find /private/tmp/siteintelix-wporg-2.7.2/assets -maxdepth 1 -name 'screenshot-*.png' | wc -l
```

Expected: `9`.

### Task 7: Publish and Verify WordPress.org

- [ ] **Step 1: Commit the approved SVN release**

Run:

```bash
svn commit /private/tmp/siteintelix-wporg-2.7.2 \
	-m "Release SiteIntelix 2.7.2 with updated plugin assets"
```

Expected: one committed SVN revision.

- [ ] **Step 2: Verify the remote tag**

Run:

```bash
svn ls https://plugins.svn.wordpress.org/siteintelix/tags/2.7.2/
```

Expected: the release files are listed.

- [ ] **Step 3: Verify remote release metadata**

Read:

```text
https://plugins.svn.wordpress.org/siteintelix/trunk/readme.txt
https://plugins.svn.wordpress.org/siteintelix/tags/2.7.2/readme.txt
```

Expected: both identify Stable tag 2.7.2 and the current changelog.

- [ ] **Step 4: Check the public plugin page**

Open:

```text
https://wordpress.org/plugins/siteintelix/
```

Expected after propagation: download version 2.7.2 and updated screenshot captions/assets. Allow for WordPress.org CDN delay.

- [ ] **Step 5: Report release-confirmation state**

If WordPress.org marks the release pending, tell the user to use the emailed Release Management confirmation link. Do not claim the new version is public until the directory reports it.

- [ ] **Step 6: Final handoff**

Report:

- committed SVN revision;
- remote tag verification;
- public plugin page state;
- screenshot files included;
- local installable ZIP path and checksum;
- confirmation that no Git commit or push occurred.
