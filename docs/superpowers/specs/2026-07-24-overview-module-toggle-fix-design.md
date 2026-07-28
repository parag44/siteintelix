# Overview Module Toggle Fix Design

## Problem

The Overview page renders module toggle controls but only loads `siteintelix-overview.js`. That asset currently handles report copy/export actions only. The working toggle handler and nonce are limited to the separate Modules page, so changing a switch on Overview does not send a request or persist the new state.

## Design

Keep the Overview page lightweight by extending its existing JavaScript asset instead of loading the legacy admin bundle or creating another request. A delegated `change` handler will:

1. Detect `data-siteintelix-module-toggle` controls.
2. POST the module ID, intended state, and localized nonce to the existing `siteintelix_toggle_module` AJAX action.
3. Disable the switch and mark its card as updating while the request is pending.
4. Reload shortly after success so menus, counts, and runtime state reflect the saved configuration.
5. Restore the previous state and announce an accessible error if the request fails.

The PHP enqueue code will add the AJAX URL, nonce, and translated status strings to the existing `siteintelixOverviewData` object. No new asset, dependency, database option, endpoint, or module API will be introduced.

## Testing

Add a Node regression test that executes the Overview asset with a minimal DOM/fetch fixture and verifies request payload, pending state, successful reload, and failure rollback. Run that test red before implementation, then run it and the complete plugin test suite after the fix.
