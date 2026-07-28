# Code Snippets

This disabled-by-default module stores administrator-authored PHP in the per-site `{$wpdb->prefix}siteintelix_snippets` table. Active snippets run by priority then ID, inside an isolated closure, without generated PHP files.

Code must omit PHP opening and closing tags. SiteIntelix validates syntax with PHP token parsing and rejects `__halt_compiler`. Scopes are everywhere, frontend, or admin; ordinary AJAX, REST, and cron requests run only everywhere snippets. CLI, SiteIntelix management actions, uninstall, and Safe Mode skip all snippets.

`SITEINTELIX_SAFE_MODE` can be defined as true to bypass execution. Administrators may also use `?siteintelix_safe_mode=1` for the current admin request.

Caught `Throwable` values and fatal shutdown errors deactivate the current snippet, increment its error counter, store a bounded message and redacted path, and stop the remaining snippets. **Run once** marks the snippet as running before execution and leaves it inactive after success or failure.

JSON imports are limited to 1 MB and 100 records, are syntax checked, and are always inserted inactive. Exports contain only portable snippet fields; no public REST endpoint exposes raw code.

Only users with `manage_options` can manage snippets. By default uninstall preserves snippet data; the shared **Delete Custom Code Data on Uninstall** setting opts into removal.
