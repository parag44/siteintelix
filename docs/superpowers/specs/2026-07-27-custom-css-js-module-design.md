# Custom CSS & JS Module Design

## Goal

Add an independent, disabled-by-default SiteIntelix module for managing multiple CSS and JavaScript entries without editing themes or plugins. The behavior is inspired by Simple Custom CSS and JS, but the implementation and interface are native to SiteIntelix.

Reference: <https://wordpress.org/plugins/custom-css-js/>

## Integration

The module ID is `custom_code`. It is registered as **Custom CSS & JS** in `SITEINTELIX_Modules` with its own enable/disable toggle, conditional class loading, runtime boot, activation schema installer, and module-dashboard Open link.

When disabled:

- its classes are not loaded on ordinary requests;
- its submenu pages and admin assets are absent;
- no CSS or JavaScript execution hooks are registered;
- database rows and generated files remain unchanged.

The runtime must load on frontend and admin requests when enabled. Management remains restricted to `manage_options`.

## Files and responsibilities

The module will use focused classes under `includes/modules/custom-code/`:

- module/bootstrap: registers runtime, admin, activation, and assets;
- repository: schema name, prepared CRUD, search, filters, pagination, duplication, and bulk status changes;
- admin controller: menus, request validation, actions, redirects, and notices;
- runner: context matching and ordered CSS/JavaScript output;
- file manager: deterministic uploads paths, file generation, regeneration, and deletion;
- views: list and Add/Edit screens;
- local CSS/JavaScript assets: editor initialization and module-specific compact layout.

No unrelated module is restructured.

## Storage

Create `{$wpdb->prefix}siteintelix_custom_code` with `dbDelta()` and a module schema-version option.

Columns:

- `id` bigint unsigned primary key;
- `title` varchar(191);
- `code` longtext;
- `code_type` varchar(12): `css` or `javascript`;
- `scope` varchar(12): `frontend`, `admin`, or `both`;
- `location` varchar(10): `header` or `footer`;
- `loading_method` varchar(10): `inline` or `external`;
- `priority` int;
- `status` varchar(10): `enabled` or `disabled`;
- `description` text;
- `generated_file` varchar(255);
- `created_by` bigint unsigned;
- `created_at` datetime;
- `updated_at` datetime.

Indexes cover status, type, scope, priority, and modified date. Tables use the current site prefix; multisite entries remain per-site.

## Admin navigation and list

When enabled, SiteIntelix receives two submenu items:

- **Custom CSS & JS**
- **Add New Code**

The main page uses a compact WordPress-style table with title, type, location, scope, status, priority, modified date, selection checkboxes, and row actions.

Row actions:

- Edit
- Enable/Disable
- Duplicate
- Delete

Bulk actions:

- Enable
- Disable
- Delete

Filters:

- All
- CSS
- JavaScript
- Enabled
- Disabled

Search matches title. Filtering, sorting, and pagination are bounded and implemented through prepared repository queries. Destructive actions require explicit confirmation.

## Add/Edit interface

The approved layout is a focused CodeMirror editor with a compact settings sidebar, collapsing below the editor at smaller widths.

Main area:

- title;
- CSS or JavaScript editor;
- description/internal notes;
- visible invalid-code warning.

Sidebar:

- enabled/disabled status;
- frontend/admin/both scope;
- header/footer location;
- inline/external loading;
- integer priority;
- Save, Save & Enable, and Delete actions as appropriate.

Use `wp_enqueue_code_editor()` with WordPress’s bundled CSS or JavaScript mode. If WordPress reports that the code editor is unavailable, retain an accessible textarea. Assets load only on the two Custom CSS & JS screens.

## Code preservation and validation

CSS and JavaScript code is stored as submitted after `wp_unslash()`; it must not pass through `sanitize_text_field()` or KSES.

The save handler:

- requires `manage_options` and a valid nonce;
- sanitizes metadata fields against explicit allowlists;
- validates title length and bounded priority;
- detects surrounding `<style>` or `<script>` tags;
- removes only those matching outer tags and returns a visible warning;
- preserves all remaining code bytes.

The interface warns that invalid code can affect the site.

## Execution

The runner retrieves only enabled entries matching the current frontend/admin context, location, and code type. Entries execute in ascending priority and ID order.

### Inline CSS

Render through `wp_head`, `wp_footer`, `admin_head`, or `admin_footer`. Each entry uses:

```html
<style id="siteintelix-custom-css-{id}">
```

The ID is escaped; code is emitted raw because access, nonce, tag handling, and database storage protect the surrounding markup.

### External CSS

Generate a deterministic `.css` file in:

`wp-content/uploads/siteintelix/custom-code/`

Use the current site’s `wp_upload_dir()` result, WordPress filesystem APIs where practical, and an entry/site-specific filename. Header files are enqueued normally. Footer files are enqueued and printed through the corresponding footer path without moving unrelated styles.

### Inline JavaScript

Users enter JavaScript without `<script>` tags. The runner emits a unique script element through the requested head/footer hook. It does not use JavaScript `eval()`.

### External JavaScript

Generate a deterministic `.js` file in the custom-code upload directory and enqueue it with a unique handle. Header/footer placement and priority ordering are retained. The updated timestamp is used as the asset version.

### File fallback

Saving or updating an external entry regenerates its file. If directory creation or writing fails:

- retain the entry and requested loading method;
- execute inline for that request;
- store no false success state;
- show an administrator warning explaining the fallback.

Permanent deletion removes the generated file through `wp_delete_file()`. Disabling an entry or module does not delete data or files.

## Security

- Every mutation requires `manage_options` and a nonce.
- IDs, pagination, filters, metadata, and priority are sanitized and validated.
- Database values use prepared queries or `$wpdb` CRUD helpers with explicit formats.
- Raw code is never exposed through public REST routes.
- Output attributes and notices are escaped.
- Only code content inside controlled style/script elements remains intentionally raw.
- Importing remote libraries, CDNs, or third-party editor bundles is prohibited.

## Uninstall

Add a shared SiteIntelix setting named **Delete Custom Code Data on Uninstall**, default disabled.

When disabled, uninstall preserves this table and generated CSS/JavaScript files.

When enabled, uninstall:

- drops the per-site custom-code table;
- removes generated files and empty SiteIntelix custom-code directories;
- removes schema/version and recovery options;
- handles each site separately in multisite.

Normal deactivation and module disabling never remove saved code.

## Testing

Automated coverage will include:

- registry and conditional-loader invariants;
- schema and custom-prefix construction;
- metadata normalization without destructive code sanitization;
- tag detection/removal;
- CRUD, duplicate, filters, search, pagination, and bulk status changes;
- scope/location/type/priority selection;
- inline markup and unique IDs;
- external filenames, regeneration, deletion, and write-failure fallback;
- capability and nonce enforcement;
- disabled-module hook absence;
- per-site multisite behavior;
- conditional uninstall preservation/deletion;
- PHP 7.4 and PHP 8 syntax compatibility.

Manual runtime checks will cover the four frontend/admin header/footer combinations, inline/external loading, CodeMirror modes, responsive layout, caching compatibility, and filesystem-unavailable behavior.
