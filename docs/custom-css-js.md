# Custom CSS & JS

This disabled-by-default module stores administrator-authored CSS and JavaScript in the per-site `{$wpdb->prefix}siteintelix_custom_code` table. Entries can target the frontend, WordPress admin, or both, and run in header or footer order by priority then ID.

External mode writes deterministic assets under `wp-content/uploads/siteintelix/custom-code/`. If a generated file is missing or cannot be written, runtime output safely falls back to inline delivery. Outer `<style>` and `<script>` wrappers are stripped on save.

Only administrators with `manage_options` can view or change entries. Every mutation uses a WordPress nonce. Disabling the module removes its hooks and menus while preserving data.

By default uninstall preserves the table and generated files. Enable **Settings → Custom Code Data Retention → Delete Custom Code Data on Uninstall** to remove them when SiteIntelix is deleted.
