# Code Snippets Module Design

## Goal

Add an independent, disabled-by-default SiteIntelix module that lets administrators manage small PHP functionality without modifying `functions.php`. The behavior is inspired by Code Snippets, while implementation, branding, storage, and UI remain native to SiteIntelix.

References:

- <https://wordpress.org/plugins/code-snippets/>
- <https://codesnippets.pro/doc/safe-mode/>

## Integration and execution boundary

The module ID is `code_snippets`. It is registered as **Code Snippets** with its own module toggle and dashboard Open link.

When disabled:

- no module menus or assets load;
- no snippet runner or recovery hooks register;
- database rows remain untouched.

Active snippets need to register ordinary WordPress hooks. A small early bootstrap, modeled on SiteIntelix’s early email-capture loader, reads the raw enabled-module option during plugin load, requires only the snippet runtime classes, and registers execution on `plugins_loaded` before the normal SiteIntelix bootstrap. Admin management classes remain conditionally loaded only for admin, management AJAX, CLI, or schema operations.

Only administrators with `manage_options` can create, edit, activate, deactivate, import, export, delete, or explicitly run snippets. Once activated, frontend/everywhere snippets execute on matching public visitor requests like mini-plugins.

## Files and responsibilities

Focused classes under `includes/modules/code-snippets/`:

- module/bootstrap: early runtime and admin integration;
- activator/schema manager;
- repository: prepared storage and list queries;
- admin controller: menus, requests, actions, and notices;
- validator: tags, tokens, and syntax validation;
- runner: scope/context selection and isolated execution;
- recovery service: active-snippet tracking, error capture, deactivation, and notices;
- importer/exporter: bounded JSON/PHP transfer;
- views and local scoped assets.

No single class combines database, validation, execution, recovery, and UI.

## Storage

Create `{$wpdb->prefix}siteintelix_snippets` through `dbDelta()` with a module schema-version option.

Columns:

- `id` bigint unsigned primary key;
- `name` varchar(191);
- `code` longtext;
- `description` text;
- `tags` text;
- `scope` varchar(20): `everywhere`, `frontend`, `admin`, or `run_once`;
- `priority` int;
- `status` varchar(20): `active`, `inactive`, or internal `running_once`;
- `last_error` longtext containing bounded structured error JSON;
- `error_count` int unsigned;
- `deactivated_at` datetime nullable;
- `last_run_at` datetime nullable;
- `created_at` datetime;
- `updated_at` datetime;
- `created_by` bigint unsigned.

Indexes cover status, scope, priority, `deactivated_at`, and updated date. Recently deactivated means inactive with `deactivated_at` within the last seven days.

Tables use the current site prefix. Network-wide execution is not supported.

## Admin navigation and list

When enabled, SiteIntelix receives:

- **Code Snippets**
- **Add New Snippet**

The compact list displays name, description, scope, status, priority, modified date, checkboxes, and row actions.

Row actions:

- Edit
- Activate/Deactivate
- Duplicate
- Export
- Delete

Bulk actions:

- Activate
- Deactivate
- Export
- Delete

Filters:

- All
- Active
- Inactive
- Recently deactivated

Search matches name and description. Prepared queries provide bounded filtering, sorting, and pagination.

## Add/Edit interface

Use the approved focused editor/settings-sidebar layout.

Main area:

- snippet name;
- PHP CodeMirror editor;
- description;
- tags;
- visible high-risk warning.

Sidebar:

- active/inactive status;
- execution scope;
- integer priority;
- Save, Save & Activate, Run Once, Export, and Delete actions as applicable.

Warning copy:

> PHP snippets can change or break your website. Activate only code from a trusted source. SiteIntelix Safe Mode can temporarily disable all snippets.

Use `wp_enqueue_code_editor()` in PHP mode only on snippet editor screens, with a textarea fallback.

## Validation and storage

Users enter PHP without opening or closing tags. Code is stored after `wp_unslash()` and is not passed through text sanitizers.

The validator:

- rejects submitted code containing `<?php`, `<?=`, or `?>` and requires the administrator to remove the tags; it never mutates PHP silently;
- rejects `T_HALT_COMPILER`;
- tokenizes `<?php ` plus the submitted body using `token_get_all(..., TOKEN_PARSE)`;
- catches `ParseError` and reports a bounded administrator-facing line/message;
- permits ordinary functions, classes, closures, WordPress hooks, and statements compatible with PHP 7.4+.

Activation is refused when validation fails. Saving inactive code never executes it. Saving, activation, import, and editor requests skip all snippet execution until management has completed.

## Runner

The selected execution model is an isolated static closure. The runner passes only the code string into the closure and evaluates it there; repository rows, controller variables, and runner internals are not extracted into snippet scope.

The runner:

1. exits if the module is disabled or a safe/excluded context applies;
2. loads active snippets matching the request scope;
3. orders by priority then ID;
4. marks the current snippet in request-local recovery state;
5. executes it inside the isolated closure;
6. clears current state after success.

The runner does not prepend code to plugin files, write executable PHP files, or modify themes, core, `functions.php`, or `wp-config.php`.

### Excluded contexts

Do not execute snippets during:

- plugin activation, deactivation, or uninstall;
- snippet list/editor/save/import/export/activation management requests;
- snippet-management AJAX or REST requests;
- snippet recovery operations;
- run-once confirmation/execution by the ordinary runner;
- SiteIntelix Safe Mode;
- a valid logged-in administrator request with `siteintelix_safe_mode=1`;
- any request when `SITEINTELIX_SAFE_MODE` is true.

On ordinary non-management AJAX, REST, or WP-Cron requests, only `everywhere` snippets execute; frontend-only and admin-only snippets skip these ambiguous machine contexts. WP-CLI skips all snippets. SiteIntelix snippet-management AJAX/REST actions and recovery operations always skip execution.

## Safe mode and recovery

### Safe mode

- `define( 'SITEINTELIX_SAFE_MODE', true );` disables all snippets globally.
- `?siteintelix_safe_mode=1` disables snippets for only that request and only when the current user has `manage_options`.
- Existing SiteIntelix Safe Mode state also suppresses snippets.
- A clear admin notice confirms safe mode and links to the snippets list.

### Runtime errors

Register one shutdown handler before execution and track only the current snippet ID in request-local state.

For caught `Throwable`:

- record the error;
- increment `error_count`;
- set the snippet inactive and `deactivated_at`;
- stop executing remaining snippets for that request;
- allow WordPress to continue when safely possible.

For a detectable fatal shutdown error:

- inspect `error_get_last()` only for fatal types;
- attribute it only when a current snippet is set;
- persist bounded error data and deactivate when database access remains safe;
- leave WordPress Recovery Mode behavior untouched.

Stored error data is limited to:

- error type;
- bounded message;
- normalized relative file/basename;
- line;
- timestamp.

Raw sensitive paths are not shown to unauthorized users. Recovery notices are visible only to `manage_options`.

## Run once

Run-once snippets never participate in the ordinary runner.

Execution requires a dedicated confirmation screen and nonce-protected administrator action. Before execution, the row moves to `running_once`, which is non-runnable. On success it becomes inactive with `last_run_at`. On caught error or fatal recovery it also becomes inactive and records the error. Interrupted requests are therefore not retried automatically.

## Import and export

Exports:

- one snippet as JSON;
- selected snippets as JSON;
- selected snippets as a downloadable PHP reference file.

Portable JSON fields:

- name;
- code;
- description;
- tags;
- scope;
- priority;
- status.

Imports:

- require `manage_options` and a nonce;
- accept JSON only;
- validate extension, WordPress-reported MIME where available, bounded file size, decoding, top-level structure, allowed fields, and each snippet’s syntax;
- ignore unknown fields;
- store every imported snippet inactive regardless of exported status;
- never execute imported code.

No public REST route exposes raw PHP.

## Security

- Every mutation and download requires `manage_options` and a nonce.
- No unauthenticated status or execution endpoint exists.
- REST management is omitted unless a future authenticated use case requires it.
- Metadata uses allowlists and bounded lengths.
- Raw PHP is stored only after authorization and validation.
- Repository SQL is prepared or uses explicit `$wpdb` formats.
- Execution contexts do not expose repository rows as local variables.
- Import size and record counts are bounded to prevent resource exhaustion.

## Shared uninstall setting

Use the SiteIntelix setting **Delete Custom Code Data on Uninstall**, default disabled.

Disabled:

- preserve the snippets table, schema option, and recovery records.

Enabled:

- drop the per-site snippets table;
- remove schema, safe-mode, and recovery options/transients;
- coordinate with Custom CSS & JS file/table cleanup;
- handle each multisite site separately.

Module disabling and normal plugin deactivation preserve snippets.

## Testing

Automated coverage:

- registry, early loader, and disabled-module invariants;
- schema/custom-prefix and multisite isolation;
- PHP tag and `T_HALT_COMPILER` rejection;
- valid token parsing and malformed PHP rejection;
- inactive save and activation refusal;
- priority and scope ordering;
- management, safe-mode, AJAX/REST/cron/CLI exclusions;
- isolated closure scope;
- caught `Throwable`, fatal attribution, deactivation, redaction, and recovery notice;
- seven-day recently deactivated filtering;
- run-once confirmation and non-retry transitions;
- CRUD, duplicate, filters, bulk actions, capability and nonce enforcement;
- JSON/PHP export;
- bounded JSON import and forced inactive status;
- conditional uninstall preservation/deletion;
- PHP 7.4 and PHP 8 compatibility.

Manual checks include active public behavior, admin-only/frontend-only behavior, safe mode by URL/constant/existing SiteIntelix state, recovery after a deliberately failing staging snippet, WordPress Recovery Mode coexistence, multisite per-site execution, plugin/module disable and re-enable, and complete uninstall.
