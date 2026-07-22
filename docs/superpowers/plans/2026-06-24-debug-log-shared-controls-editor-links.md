# Debug Log Shared Controls and Editor Links Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give all Debug Log viewer modes one identical status/switch panel and let Classic plugin/theme paths open the native WordPress editor at the reported line.

**Architecture:** A shared view partial owns the status panel markup, while a focused `SITEINTELIX_Editor_Links` helper validates log paths and creates native editor URLs. A tiny editor-only JavaScript file performs line navigation in WordPress CodeMirror or its textarea fallback.

**Tech Stack:** WordPress PHP admin APIs, native Plugin/Theme File Editors, vanilla JavaScript, CodeMirror supplied by WordPress, Node structural tests, PHP smoke tests.

---

### Task 1: Shared Debug Status Component

**Files:**
- Create: `admin/views/partials/debug-log-status.php`
- Modify: `admin/views/debug-log-page-classic.php`
- Modify: `admin/views/debug-log-page-modern.php`
- Modify: `admin/views/debug-log-page-terminal.php`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `assets/admin/css/siteintelix-debug-log.css`
- Test: `tests/structural.test.mjs`

- [ ] **Step 1: Write the failing structural test**

Add a test that requires every viewer to include the shared partial and rejects the old viewer-specific status classes:

```js
test('all Debug Log modes use one shared status and switch component', async () => {
	const [classic, modern, terminal, partial] = await Promise.all([
		read('admin/views/debug-log-page-classic.php'),
		read('admin/views/debug-log-page-modern.php'),
		read('admin/views/debug-log-page-terminal.php'),
		read('admin/views/partials/debug-log-status.php'),
	]);
	for (const viewer of [classic, modern, terminal]) {
		assert.match(viewer, /partials\/debug-log-status\.php/);
		assert.doesNotMatch(viewer, /sitx-terminal-status|sitx-debug-status-grid|siteintelix-debug-top-grid/);
	}
	assert.match(partial, /siteintelix-debug-shared-status/);
	assert.match(partial, /Recent Entries/);
	assert.match(partial, /Switch Mode/);
});
```

- [ ] **Step 2: Run the structural test and verify RED**

Run: `node --test tests/structural.test.mjs`

Expected: FAIL because the partial does not exist and the viewers still contain separate status markup.

- [ ] **Step 3: Create the shared partial**

Create a partial that expects `$siteintelix_active_method`, `$siteintelix_mode_label`, `$siteintelix_settings_url`, and `$siteintelix_total_entries`:

```php
<section class="siteintelix-debug-shared-status" aria-label="<?php esc_attr_e( 'Debug log status', 'siteintelix' ); ?>">
	<div class="siteintelix-debug-shared-status__mode si-card">
		<span class="siteintelix-debug-shared-status__icon dashicons <?php echo 'wp_config' === $siteintelix_active_method ? 'dashicons-editor-code' : 'dashicons-shield'; ?>" aria-hidden="true"></span>
		<div class="siteintelix-debug-shared-status__copy">
			<strong><?php echo 'wp_config' === $siteintelix_active_method ? esc_html__( 'wp-config.php Mode Active', 'siteintelix' ) : esc_html__( 'MU Plugin Mode Active', 'siteintelix' ); ?></strong>
			<p><?php echo esc_html( $siteintelix_mode_label ); ?></p>
		</div>
		<a class="sitx-btn sitx-btn--outline si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
			<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
			<?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
		</a>
	</div>
	<div class="siteintelix-debug-shared-status__count si-card">
		<span><?php esc_html_e( 'Recent Entries', 'siteintelix' ); ?></span>
		<strong><?php echo esc_html( number_format_i18n( (int) $siteintelix_total_entries ) ); ?></strong>
	</div>
</section>
```

- [ ] **Step 4: Replace duplicate markup and add final shared CSS**

Require the partial from each viewer after its page header and remove the old status cards. Add one responsive grid and component style at the final cascade layer:

```css
.siteintelix-debug-shared-status {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 240px;
	gap: var(--si-space-4);
	margin-bottom: var(--si-space-5);
}

.siteintelix-debug-shared-status__mode {
	align-items: center;
	display: flex;
	gap: var(--si-space-4);
	padding: 20px;
}

.siteintelix-debug-shared-status__copy {
	flex: 1;
	min-width: 0;
}

.siteintelix-debug-shared-status__count {
	align-content: center;
	display: grid;
	justify-items: center;
	padding: 20px;
	text-align: center;
}

@media (max-width: 782px) {
	.siteintelix-debug-shared-status {
		grid-template-columns: 1fr;
	}
}
```

- [ ] **Step 5: Run tests and lint**

Run:

```bash
node --test tests/structural.test.mjs
php -l admin/views/partials/debug-log-status.php
php -l admin/views/debug-log-page-classic.php
php -l admin/views/debug-log-page-modern.php
php -l admin/views/debug-log-page-terminal.php
```

Expected: PASS.

### Task 2: Secure Native Editor Link Resolver

**Files:**
- Create: `includes/class-siteintelix-editor-links.php`
- Modify: `siteintelix.php`
- Modify: `admin/views/debug-log-page-classic.php`
- Create: `tests/editor-links.php`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Write failing resolver and rendering tests**

Create a WordPress-loaded smoke test that checks:

```php
$plugin = SITEINTELIX_Editor_Links::get_link(
	WP_PLUGIN_DIR . '/siteintelix/siteintelix.php',
	42
);
siteintelix_assert( 'plugin' === $plugin['type'], 'Plugin file classified as plugin.' );
siteintelix_assert( false !== strpos( $plugin['url'], 'plugin-editor.php' ), 'Plugin editor URL used.' );
siteintelix_assert( false !== strpos( $plugin['url'], 'siteintelix_line=42' ), 'Line included.' );

$core = SITEINTELIX_Editor_Links::get_link( ABSPATH . WPINC . '/functions.php', 6170 );
siteintelix_assert( empty( $core ), 'Core files remain unlinked.' );
```

Add structural assertions that Classic calls `SITEINTELIX_Editor_Links::get_link()` and renders `target="_blank"` with `noopener noreferrer`.

- [ ] **Step 2: Run tests and verify RED**

Run:

```bash
php tests/editor-links.php
node --test tests/structural.test.mjs
```

Expected: FAIL because the resolver class and Classic link rendering do not exist.

- [ ] **Step 3: Implement the resolver**

Create `SITEINTELIX_Editor_Links::get_link( $file, $line )`, returning either an empty array or:

```php
array(
	'type' => 'plugin',
	'url'  => admin_url(
		add_query_arg(
			array(
				'plugin'          => $plugin_main_file,
				'file'            => $relative_file,
				'siteintelix_line' => max( 1, (int) $line ),
			),
			'plugin-editor.php'
		)
	),
);
```

The helper must:

- Return empty when `DISALLOW_FILE_EDIT` or `DISALLOW_FILE_MODS` is true.
- Require `edit_plugins` or `edit_themes`.
- Compare canonical `realpath()` values against canonical plugin/theme roots.
- Reject missing files, symlink escapes, traversal, core paths, and files absent from WordPress's editable file lists.
- Cache plugin and theme maps in static request-local properties.

- [ ] **Step 4: Load the helper and render Classic links**

Require the helper whenever the Debug Log module is active. In each Classic row:

```php
$siteintelix_editor_link = SITEINTELIX_Editor_Links::get_link(
	$siteintelix_entry['file'],
	$siteintelix_entry['line_number']
);
```

Render an anchor only when metadata is returned:

```php
<?php if ( $siteintelix_editor_link ) : ?>
	<a class="siteintelix-log-path siteintelix-log-path--editor" href="<?php echo esc_url( $siteintelix_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
		<?php echo esc_html( $siteintelix_entry['file'] ); ?>
	</a>
<?php else : ?>
	<span class="siteintelix-log-path"><?php echo esc_html( $siteintelix_entry['file'] ? $siteintelix_entry['file'] : '-' ); ?></span>
<?php endif; ?>
```

- [ ] **Step 5: Run resolver tests and lint**

Run:

```bash
php tests/editor-links.php
node --test tests/structural.test.mjs
php -l includes/class-siteintelix-editor-links.php
php -l admin/views/debug-log-page-classic.php
php -l siteintelix.php
```

Expected: PASS.

### Task 3: Exact-Line Navigation in WordPress Editors

**Files:**
- Create: `assets/admin/js/siteintelix-editor-line.js`
- Modify: `siteintelix.php`
- Modify: `assets/admin/css/siteintelix-admin.css`
- Modify: `tests/structural.test.mjs`

- [ ] **Step 1: Write the failing asset tests**

Require conditional editor-page enqueueing, a localized positive line number, CodeMirror navigation APIs, and textarea fallback:

```js
assert.match(main, /plugin-editor\.php|theme-editor\.php/);
assert.match(main, /siteintelix-editor-line/);
assert.match(editorJs, /setCursor/);
assert.match(editorJs, /scrollIntoView/);
assert.match(editorJs, /addLineClass/);
assert.match(editorJs, /selectionStart/);
```

- [ ] **Step 2: Run structural tests and verify RED**

Run: `node --test tests/structural.test.mjs`

Expected: FAIL because the editor-line asset is missing.

- [ ] **Step 3: Conditionally enqueue the editor helper**

Before the existing SiteIntelix-screen early return, detect `plugin-editor.php` and `theme-editor.php`. Read `siteintelix_line` as an absolute integer and enqueue only for a positive value:

```php
$siteintelix_editor_page = in_array( (string) $hook_suffix, array( 'plugin-editor.php', 'theme-editor.php' ), true );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation parameter.
$siteintelix_line = isset( $_GET['siteintelix_line'] ) ? absint( wp_unslash( $_GET['siteintelix_line'] ) ) : 0;
```

Enqueue with dependency `wp-theme-plugin-editor`, localize `line`, and return so normal SiteIntelix assets are not loaded into the editor.

- [ ] **Step 4: Implement CodeMirror and textarea navigation**

Use a bounded retry loop because WordPress initializes CodeMirror asynchronously:

```js
( function() {
	'use strict';
	var targetLine = Math.max( 1, parseInt( window.siteintelixEditorLine.line, 10 ) || 1 );
	var attempts = 0;

	function focusLine() {
		var component = window.wp && window.wp.themePluginEditor;
		var cm = component && component.instance && component.instance.codemirror;
		if ( cm ) {
			var line = Math.min( targetLine - 1, Math.max( 0, cm.lineCount() - 1 ) );
			cm.setCursor( { line: line, ch: 0 } );
			cm.scrollIntoView( { line: line, ch: 0 }, 140 );
			cm.addLineClass( line, 'background', 'siteintelix-editor-target-line' );
			cm.focus();
			window.setTimeout( function() {
				cm.removeLineClass( line, 'background', 'siteintelix-editor-target-line' );
			}, 4000 );
			return;
		}

		var textarea = document.getElementById( 'newcontent' );
		if ( textarea && attempts >= 20 ) {
			var lines = textarea.value.split( '\n' );
			var offset = lines.slice( 0, targetLine - 1 ).join( '\n' ).length + ( targetLine > 1 ? 1 : 0 );
			textarea.focus();
			textarea.selectionStart = textarea.selectionEnd = Math.min( offset, textarea.value.length );
			textarea.scrollTop = textarea.scrollHeight * ( targetLine / Math.max( 1, lines.length ) );
			return;
		}

		attempts++;
		if ( attempts <= 40 ) {
			window.setTimeout( focusLine, 100 );
		}
	}

	window.addEventListener( 'load', focusLine );
}() );
```

Add a lightweight highlight rule loaded inline on editor pages.

- [ ] **Step 5: Run tests and lint**

Run:

```bash
node --test tests/structural.test.mjs
php -l siteintelix.php
```

Expected: PASS.

### Task 4: Full Verification and Live Browser Checks

**Files:**
- Verify all files changed in Tasks 1-3.

- [ ] **Step 1: Run the complete automated suite**

Run:

```bash
php tests/debug-log-parser.php
php tests/editor-links.php
php tests/runtime-smoke.php
node --test tests/structural.test.mjs
```

Expected: all tests pass with zero failures.

- [ ] **Step 2: Run PHP syntax validation**

Run:

```bash
find admin/views includes -name '*.php' -print0 | xargs -0 -n1 php -l
php -l siteintelix.php
```

Expected: no syntax errors.

- [ ] **Step 3: Verify CSS brace balance**

Run a brace count for both admin stylesheets and require equal opening/closing counts.

- [ ] **Step 4: Verify all three viewer modes**

Switch the option through `classic`, `modern`, and `terminal_light`, reload the existing Debug Log tab, and verify `.siteintelix-debug-shared-status` exists with the same two-card structure each time. Restore the original option.

- [ ] **Step 5: Verify native editor navigation**

Use a real SiteIntelix plugin path and line to verify:

- The Classic file path is an anchor.
- It targets `plugin-editor.php`.
- It opens in a new tab.
- The correct file is selected.
- CodeMirror cursor line matches the requested line.
- The target line has `siteintelix-editor-target-line`.
- A core `wp-includes/functions.php` row remains a non-link.

- [ ] **Step 6: Report completion**

Report exact automated test counts, the live modes checked, and whether the original viewer option was restored.

> Note: this plugin directory is not a Git repository, so commit steps are intentionally omitted rather than claiming unavailable commits.
