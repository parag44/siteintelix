# SiteIntelix Module Roadmap

## Next modules to merge

SiteIntelix should become an all-in-one modular toolbox. The next modules to bring into this plugin are existing plugins inside this WordPress project:

1. **Parag Email Inspector** → SiteIntelix Email Log module
   - Move mail capture, log storage, log viewer, clear/download actions, and settings into a SiteIntelix module.
   - Module should add its submenu only when enabled.
   - Module should load hooks/assets only when enabled.

2. **Custom Error UI** → SiteIntelix Error UI module
   - Move custom error screen/rendering logic and settings into a SiteIntelix module.
   - Module should load runtime hooks only when enabled.
   - Module should expose its own settings section/page.

## Module toggle behavior

The Modules screen uses instant toggles:

- Toggling a module ON should immediately enable it via AJAX/admin-post.
- Toggling a module OFF should immediately disable it via AJAX/admin-post.
- No separate “Save Modules” button should be required.
- After toggle, the SiteIntelix submenu should reflect the new state.
- When disabling a module, cleanup module runtime artifacts if needed, e.g. remove MU bootstrap files or detach scheduled events.

## Performance rule

A module must be truly modular:

- Disabled modules must not load classes, register hooks, enqueue assets, add menus, run queries, register cron, or execute runtime code.
- The core plugin should load only the lightweight module registry and shared helpers.
- Each module should have its own loader that runs only when the module is enabled.

Suggested structure:

```text
includes/modules/
  debug-log/
    class-module.php
    class-settings.php
    class-admin.php
    class-runtime.php
    views/
    assets/
  email-log/
    class-module.php
    class-settings.php
    class-admin.php
    class-runtime.php
    views/
    assets/
  error-ui/
    class-module.php
    class-settings.php
    class-admin.php
    class-runtime.php
    views/
    assets/
```

## Settings architecture recommendation

Use both a central Settings page and optional module settings pages:

1. **Central Settings page**
   - Shows a section/tab/card for each enabled module.
   - Good for simple settings like logs per page, viewer style, retention days, notification email, etc.
   - Disabled modules should not render settings here.

2. **Dedicated module settings page**
   - Use when a module has many settings or needs a specialized UI.
   - Examples:
     - Email Log: retention, capture body, allowed roles, email status filters, storage cleanup.
     - Error UI: templates, branding, support email, preview mode, allowed status codes.

Best UX:

- Keep `SiteIntelix → Settings` as the overview for enabled module settings.
- Add `Settings` links inside each module submenu only when the module needs a richer configuration screen.
- Store options with module prefixes, e.g.:
  - `siteintelix_debug_log_settings`
  - `siteintelix_email_log_settings`
  - `siteintelix_error_ui_settings`

## Tomorrow implementation order

1. Move Debug Log into a dedicated module folder/loader.
2. Import Parag Email Inspector as `email_log` module.
3. Import Custom Error UI as `error_ui` module.
4. Add per-module settings contracts and render enabled module settings only.
5. Confirm disabled modules execute no hooks/classes/assets.
