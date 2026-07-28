# SiteIntelix 2.7.3 Release and Security Audit Design

## Objective

Prepare the current SiteIntelix plugin for a public `2.7.3` major release on a
dedicated Git branch, remediate confirmed security and WordPress.org compliance
issues without unnecessary functional changes, verify the release thoroughly,
and push the completed branch to `parag44/siteintelix` without merging,
tagging, publishing, or creating a release.

## Source Control Strategy

- Use GitHub branch `2.7.1-stable` as the historical base because it contains
  the modern SiteIntelix architecture.
- Preserve the current local plugin as the functional source of truth. It
  contains the subsequent `2.7.2` work and unreleased fixes not present on
  GitHub.
- Develop on a new branch named exactly `2.7.3`.
- Commit the approved design and implementation plan separately from the final
  release implementation where practical.
- Push only `2.7.3`. Do not modify `main`, `2.7.1-stable`, tags, GitHub
  releases, or the WordPress.org SVN repository.

## Release Metadata

The public release version will be `2.7.3`. Update every active version source,
including:

- the main plugin `Version` header;
- `SITEINTELIX_VERSION`;
- the WordPress.org `Stable tag`;
- the changelog and upgrade notice;
- structural tests and release assertions;
- any active build, package, blueprint, or distribution metadata that exists
  in the release branch.

Historical design documents, plans, and changelog entries will retain the
versions they document. The internal migration version will change only if a
new data or filesystem migration is required by a confirmed audit fix.

Compatibility metadata will state:

- Requires WordPress: `5.8`, unless code analysis proves a newer minimum;
- Tested up to WordPress: `7.0`, matching the current stable WordPress 7.0.2
  line and the local test installation;
- Requires PHP: `7.4`, unless syntax analysis proves a newer minimum.

## WordPress.org Listing

Review and revise `readme.txt` so the directory listing accurately represents
the current plugin:

- use no more than five relevant, non-spammy tags;
- keep the short description plain text and within 150 characters;
- describe all currently shipped modules, including Custom CSS & JS, Code
  Snippets, and User Switcher;
- accurately disclose local data storage, network requests, debug logging,
  generated files, privileged code execution, and uninstall behavior;
- keep installation, FAQ, screenshots, changelog, and upgrade notice aligned
  with the implementation;
- avoid unsupported claims, keyword stuffing, promotional language, and stale
  feature descriptions;
- ensure the readme stable tag and main plugin version agree.

The review will use the current official WordPress.org Detailed Plugin
Guidelines, Plugin Readme standard, Plugin Developer FAQ, Plugin Check
guidance, and WordPress Coding Standards.

## Security Audit Method

Inventory every PHP file and map all externally reachable or privileged
surfaces:

- admin menu pages and form handlers;
- `admin_post_*`, AJAX, REST, shortcode, cron, activation, deactivation,
  uninstall, and shutdown hooks;
- module toggles and settings writes;
- database reads, writes, table/column identifiers, sorting, searching, and
  pagination;
- debug and email logs, exports, downloads, generated ZIP files, MU files,
  configuration edits, and deletion paths;
- user switching, authentication restoration, signed state, redirect targets,
  role restrictions, and audit logs;
- Custom CSS & JS storage and output;
- Code Snippets validation, activation, execution, recovery, and `eval`
  isolation;
- SMTP credentials and other sensitive settings;
- diagnostic HTTP requests and report generation.

For each surface, verify:

1. authentication and least-privilege capability checks;
2. action-specific nonce or REST permission checks where state or sensitive
   data is involved;
3. request method enforcement and rejection of missing or malformed values;
4. early validation, context-appropriate sanitization, and allow-lists;
5. late, context-appropriate escaping;
6. prepared SQL values and allow-listed or `%i`-prepared identifiers;
7. bounded queries, pagination, and denial of unauthorized record access;
8. safe upload, archive, path canonicalization, file type, ownership, download,
   deletion, and direct-access handling;
9. safe redirects and URLs;
10. absence of secrets, development diagnostics, unsafe unserialization,
    shell execution, remote executable code, or uncontrolled dynamic includes.

Every PHP entry file must prevent direct execution unless it is intentionally
loaded by WordPress through a defined, authenticated interface.

## Intentional Code Execution Boundary

The Code Snippets module intentionally executes administrator-authored PHP.
Removing it would be an unnecessary functional regression. The audit will
instead require:

- a high-trust capability and action-specific nonce for creation, editing,
  activation, deactivation, deletion, and one-time execution;
- native PHP syntax validation before persistence or activation;
- isolated closure execution so snippet variables do not leak into plugin
  scope;
- automatic deactivation and bounded error recording on failure;
- no unauthenticated, subscriber, author, or editor execution controls;
- no remote retrieval or execution of snippet code;
- clear readme disclosure that enabled snippets execute privileged PHP.

Because `eval()` remains intrinsically high risk and conflicts with general
WordPress Coding Standards guidance, the final report will identify it as a
deliberate residual risk even if its authorization boundary passes review.

## Remediation Rules

- Confirm root cause before changing production code.
- Add or extend a regression test before each behavioral security fix and
  observe the test fail for the expected reason.
- Prefer WordPress APIs and existing SiteIntelix patterns.
- Preserve public behavior unless it is insecure, misleading, or incompatible
  with directory requirements.
- Avoid broad refactors and style-only churn.
- Never delete or modify files owned by another plugin.
- Do not expose secrets or sensitive test fixtures in commits or reports.

## Verification

Run the strongest locally available checks:

- PHP syntax lint across every shipped PHP file;
- all standalone PHP test scripts;
- all Node test files, including the structural suite;
- JavaScript syntax checks;
- PHPCS with WordPress Coding Standards when installed or installable without
  modifying release dependencies;
- WordPress Plugin Check when available in the local installation;
- targeted static searches for unsafe APIs, direct request access, SQL,
  missing escaping, missing nonces/capabilities, secrets, debug statements,
  dynamic includes, filesystem access, and executable-code paths;
- readme format and metadata consistency checks;
- distribution allow-list or `.distignore` inspection;
- clean archive build and inspection if a build mechanism exists;
- runtime smoke checks against the local WordPress site when its services are
  available.

Unavailable tools or environmental blockers will be reported explicitly rather
than treated as passing checks.

## Deliverables

The final `2.7.3` branch will contain:

- the current plugin functionality;
- release and WordPress.org metadata aligned to `2.7.3`;
- confirmed security and guideline fixes;
- regression tests for behavioral fixes;
- a release-quality changelog and upgrade notice;
- development design and implementation records excluded from distribution
  when specified by `.distignore`.

The final report will list:

- every changed file;
- security issues found and fixed, including severity and affected surface;
- WordPress.org guideline issues found and their disposition;
- every verification command and result;
- residual risks and manual-review items;
- the final commit identifier and pushed GitHub branch.

## Out of Scope

- merging into another branch;
- creating or pushing a Git tag;
- publishing a GitHub release;
- committing to WordPress.org SVN;
- deploying to a production WordPress site;
- redesigning the admin UI or adding unrelated functionality.
