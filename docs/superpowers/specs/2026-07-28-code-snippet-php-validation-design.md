# Code Snippet PHP Validation Design

## Problem

The Code Snippets validator searches the raw snippet text for `<?` and `?>`. This rejects valid PHP when those character sequences occur inside a string or comment. The reported Tutor LMS snippet contains an XML declaration string, `<?xml encoding="utf-8" ?>`, and is therefore rejected even though its PHP syntax is valid.

## Design

Keep SiteIntelix snippets tagless, but determine whether PHP tags are real code tokens instead of searching raw text.

1. Prefix the submitted snippet with a synthetic `<?php` opening tag.
2. Tokenize and parse the resulting source with PHP's native `token_get_all(..., TOKEN_PARSE)`.
3. Ignore the one synthetic opening token.
4. Reject actual opening or closing PHP tags found in executable code.
5. Allow tag-like text inside PHP strings and comments.
6. Preserve the existing bounded native parse-error message for invalid syntax.
7. Preserve the existing `__halt_compiler` restriction.

## Error behavior

- Valid tagless PHP saves normally.
- Invalid PHP returns the native parser error.
- Real PHP tags return “Enter PHP without opening or closing tags.”
- XML declarations and similar text inside strings/comments do not trigger the PHP-tag error.

## Regression coverage

Add dependency-free validator tests that verify:

- The reported XML declaration inside a string is accepted.
- PHP-tag examples inside comments are accepted.
- An actual closing tag is rejected.
- An actual opening tag is rejected.
- Invalid tagless PHP is rejected as a parse error.
- `__halt_compiler` remains rejected.
