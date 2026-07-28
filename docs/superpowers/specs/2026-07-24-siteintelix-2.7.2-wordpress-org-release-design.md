# SiteIntelix 2.7.2 WordPress.org Release Design

**Date:** 2026-07-24

## Goal

Publish SiteIntelix 2.7.2 to the existing WordPress.org plugin repository with current production code and a complete set of authentic screenshots from the running local WordPress installation.

## Release Identity

- WordPress.org slug: `siteintelix`
- Public plugin name: `SiteIntelix – WordPress Toolkit`
- Release version and SVN tag: `2.7.2`
- Stable tag: `2.7.2`
- Existing banner and icon assets remain unchanged.

## Screenshot Set

Capture these nine screens from the running `server-info` Local site:

1. Overview Dashboard
2. Modules
3. Modern Debug Log Viewer
4. Email Log
5. Database Manager
6. Server Diagnostics
7. User Switcher Activity Log
8. Main Settings with the User Switcher tab active
9. Cron Events

Screenshots must:

- show the current v2.7.2 interface;
- come from the real plugin UI rather than generated mockups;
- include the WordPress admin navigation for context;
- avoid open modals, transient notices, personal email content, credentials, secrets, or unnecessary diagnostic details;
- use lowercase filenames `screenshot-1.png` through `screenshot-9.png`;
- be optimized without making interface text unreadable.

The `readme.txt` screenshot captions must have exactly nine numbered entries matching this order.

## SVN Staging

Use a new temporary checkout of:

`https://plugins.svn.wordpress.org/siteintelix`

Do not stage the release in the existing checkout because it contains unversioned development artifacts.

Synchronize the verified plugin source into SVN `/trunk` while excluding:

- `.git`, `.svn`, `.DS_Store`;
- `.superpowers`, `docs/superpowers`;
- tests and development-only documentation;
- retired files and the removed Error UI directory;
- ZIP archives and local build artifacts;
- `.distignore`.

Copy the nine optimized screenshots into SVN `/assets`. Preserve existing banner and icon files.

Create `/tags/2.7.2` from the fully staged `/trunk` using `svn copy`.

## Verification Gates

Before committing:

1. Re-run SiteIntelix structural, permission, PHP syntax, regression, and WordPress Coding Standards checks.
2. Confirm the plugin header version, readme stable tag, changelog heading, and tag directory all say `2.7.2`.
3. Confirm `/trunk` contains no nested `siteintelix` directory, ZIP archive, tests, development plans, Git metadata, or SVN metadata copied from the source.
4. Confirm `/assets` contains nine screenshots plus the existing banner and icon assets.
5. Confirm all screenshot dimensions, filenames, and PNG MIME properties.
6. Review `svn status`, `svn diff`, and the proposed tag contents before the commit.

## Publication

Commit trunk, assets, and `tags/2.7.2` together with a release-specific SVN message.

After committing:

- verify the SVN revision and remote tag;
- verify the public WordPress.org plugin page and version metadata after propagation;
- note that WordPress.org asset caching can delay screenshot updates;
- if Release Confirmation is enabled, report the pending confirmation clearly so the user can approve it through the emailed WordPress.org dashboard link.

No Git commit or Git push is part of this release.
