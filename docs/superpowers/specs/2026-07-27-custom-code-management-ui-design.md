# Custom Code Management UI and Settings Design

## Scope

Redesign the Custom CSS & JS and Code Snippets list pages using the SiteIntelix component system, and move uninstall preferences from the standalone settings card into module-specific settings tabs.

## Settings architecture

Both enabled modules register a normal SiteIntelix `settings` definition and render through `siteintelix_render_module_settings_sections`.

- Custom CSS & JS stores `siteintelix_delete_custom_css_js_on_uninstall`.
- Code Snippets stores `siteintelix_delete_code_snippets_on_uninstall`.
- Both default to disabled.
- If the retired shared option `siteintelix_delete_custom_code_on_uninstall` is enabled, each new option initially inherits that value so an existing cleanup preference is not silently weakened.
- Each tab has its own `manage_options` handler and nonce.
- The standalone retention card and shared handler are removed.

Uninstall independently removes the custom-code table/files and snippets table/recovery data according to the matching option.

## Management-page layout

Both pages use one consistent structure:

1. SiteIntelix page header with primary creation action; Code Snippets also includes Import.
2. Compact summary chips for total and execution status.
3. One bordered management card.
4. A search/filter toolbar with bounded fields and reset behavior.
5. A compact bulk toolbar; snippets also include export format and export action.
6. A responsive SiteIntelix table with status/type/scope badges, clean row actions, and semantic checkbox labels.
7. Designed empty state and pagination.

Desktop controls remain aligned in compact rows. Tablet and mobile layouts wrap controls and allow the table to scroll without breaking the admin shell.

## Behavior and accessibility

All current CRUD, filtering, seven-day deactivation, import/export, nonces, capabilities, and confirmation behavior remain unchanged. Buttons use the existing `si-button` system, tables use `si-table`, and destructive actions retain the accessible SiteIntelix confirmation dialog.

## Verification

Structural tests cover settings registration, removal of the global card, module-specific uninstall conditions, required page components, no native dialogs, and scoped assets. Runtime checks cover option persistence and uninstall decision inputs; full Node, PHP, syntax, and ZIP checks remain required.
