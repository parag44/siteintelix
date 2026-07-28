# Email Log Actions Fix Design

## Goal

Restore a clearly visible Send Email action in the test-email dialog and add a protected Delete all logs choice to the existing bulk-action workflow.

## Root cause

The dialog is appended directly to `document.body`, outside `.siteintelix-wrap`, where the `--si-*` design tokens are defined. The primary button therefore receives unresolved background and border declarations while retaining white text, making it appear absent.

## Design

The dialog will use a dedicated action footer and explicit token fallbacks. Its primary label will describe the operation (`Send Email`) rather than the generic `Confirm`. The existing keyboard trap, validation, cancellation, and focus restoration remain intact.

The bulk-action selector will add `delete_all`. The existing nonce and `manage_options` checks remain the security boundary. The server handler will process `delete_all` without requiring selected IDs, delete all module-owned email rows through a shared helper, and redirect with the existing deletion notice. Both Delete selected and the header Clear Logs shortcut remain available.

## Verification

Automated tests will assert the action footer and Send Email label, the new selector value, capability/nonce-protected server routing, and the distinction between selected and all-row deletion. PHP/JS syntax and the full Node suite will be rerun. Live verification will avoid actually sending mail or deleting data.
