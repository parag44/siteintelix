import test from 'node:test';
import assert from 'node:assert/strict';
import { access, readFile } from 'node:fs/promises';
import { constants } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => readFile(path.join(root, relativePath), 'utf8');

test('plugin exposes the approved public name without changing internal identity', async () => {
	const main = await read('siteintelix.php');
	assert.match(main, /Plugin Name:\s+SiteIntelix – Debug Logs, Email Logs & Diagnostics/);
	assert.match(main, /Text Domain:\s+siteintelix/);
	assert.match(main, /'siteintelix'/);
});

test('Custom Error UI is absent from production runtime and registry code', async () => {
	const [main, modules, modulePage] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
	]);
	assert.doesNotMatch(main, /SITEINTELIX_Error_UI_Module|modules\/error-ui|['"]error_ui['"]/);
	assert.doesNotMatch(modules, /Custom Error UI|['"]error_ui['"]/);
	assert.doesNotMatch(modulePage, /siteintelix-error-ui-settings|['"]error_ui['"]/);
});

test('retired Custom Error UI implementation files are deleted', async () => {
	for (const relativePath of [
		'includes/modules/error-ui/class-siteintelix-error-ui-module.php',
		'includes/modules/error-ui/templates/db-error.php',
		'includes/modules/error-ui/templates/fatal-error-handler.php',
	]) {
		await assert.rejects(access(path.join(root, relativePath), constants.F_OK));
	}
});

test('migration cleans retired state once and protects foreign drop-ins', async () => {
	const migration = await read('includes/class-siteintelix-migrations.php');
	assert.match(migration, /siteintelix_migration_version/);
	assert.match(migration, /siteintelix_error_ui_settings/);
	assert.match(migration, /siteintelix_error_ui_dropins_version/);
	assert.match(migration, /siteintelix-error-ui-config\.php/);
	assert.match(migration, /SiteIntelix Error UI/);
	assert.match(migration, /array_diff/);
	assert.match(migration, /wp_delete_file/);
	assert.doesNotMatch(migration, /unlink\s*\(/);
});

test('Server Diagnostics has no plugin compatibility workflow', async () => {
	const diagnostics = await read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php');
	for (const forbidden of [
		'selected_plugin',
		'render_plugin_form',
		'render_compatibility_card',
		'get_compatibility_profiles',
		'Compatibility Profiles',
		'Check Plugin',
	]) {
		assert.doesNotMatch(diagnostics, new RegExp(forbidden));
	}
	for (const retained of [
		'PHP & Server',
		'Extensions',
		'Filesystem',
		'Network',
		'WordPress',
		'Database',
		'format_text_report',
	]) {
		assert.match(diagnostics, new RegExp(retained));
	}
});

test('shared admin CSS exposes the compact token and component system', async () => {
	const css = await read('assets/admin/css/siteintelix-admin.css');
	for (const token of [
		'--si-canvas:',
		'--si-surface:',
		'--si-border:',
		'--si-text:',
		'--si-muted:',
		'--si-primary:',
		'--si-success:',
		'--si-warning:',
		'--si-danger:',
		'--si-control-height:',
		'--si-transition:',
	]) {
		assert.ok(css.includes(token), `missing ${token}`);
	}
	for (const component of [
		'.si-page-header',
		'.si-card',
		'.si-button',
		'.si-badge',
		'.si-toolbar',
		'.si-table',
		'.si-form-row',
		'.si-empty-state',
		'.sitx-toggle',
	]) {
		assert.ok(css.includes(component), `missing ${component}`);
	}
	assert.match(css, /@media\s*\(prefers-reduced-motion:\s*reduce\)/);
});

test('final visual-system overrides come after legacy module rules', async () => {
	const css = await read('assets/admin/css/siteintelix-admin.css');
	const finalLayer = css.lastIndexOf('17. Cascade-final visual system');
	const legacyRule = css.lastIndexOf('.sitx-safe-actions .si-button');

	assert.ok(finalLayer > legacyRule, 'final visual overrides must be last in the cascade');
});

test('key page workflows and Debug Log modes remain present', async () => {
	const [overview, modules, settings, router, classic, modern, terminal] = await Promise.all([
		read('admin/views/admin-page.php'),
		read('admin/views/modules-page.php'),
		read('admin/views/settings-page.php'),
		read('admin/views/debug-log-page.php'),
		read('admin/views/debug-log-page-classic.php'),
		read('admin/views/debug-log-page-modern.php'),
		read('admin/views/debug-log-page-terminal.php'),
	]);
	assert.match(overview, /siteintelix-copy-btn/);
	assert.match(overview, /siteintelix-export-btn/);
	assert.match(overview, /data-siteintelix-module-toggle/);
	assert.match(modules, /data-siteintelix-module-toggle/);
	assert.match(settings, /data-siteintelix-settings-tabs/);
	assert.match(router, /classic/);
	assert.match(router, /modern/);
	assert.match(router, /terminal_light/);
	assert.match(classic, /siteintelix-log-table/);
	assert.match(modern, /sitx-log-list/);
	assert.match(terminal, /sitx-terminal-shell/);
});

test('assets stay local, dependency-free, and scoped to SiteIntelix screens', async () => {
	const [main, adminJs, adminCss] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-admin.js'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);
	assert.match(main, /strpos\(\s*\(string\)\s*\$hook_suffix,\s*'siteintelix'/);
	assert.doesNotMatch(adminJs, /\b(jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(adminCss, /@import\s+url|fonts\.googleapis|cdnjs|unpkg|jsdelivr/);
});

test('Terminal Log wraps complete entries without horizontal scrolling', async () => {
	const css = await read('assets/admin/css/siteintelix-debug-log.css');
	const finalTerminalLayer = css.lastIndexOf('Terminal no-horizontal-scroll layout');
	const legacyTerminalRule = css.lastIndexOf('grid-template-columns: 52px max-content 86px minmax(620px, 1fr)');
	const finalCss = css.slice(finalTerminalLayer);

	assert.ok(finalTerminalLayer > legacyTerminalRule, 'terminal wrapping rules must come after legacy fixed-width rules');
	assert.match(finalCss, /\.sitx-terminal-body[\s\S]*overflow-x:\s*hidden/);
	assert.match(finalCss, /\.sitx-terminal-line[\s\S]*grid-template-columns:\s*44px minmax\(150px,\s*auto\) 86px minmax\(0,\s*1fr\)/);
	assert.match(finalCss, /\.sitx-terminal-message[\s\S]*overflow-wrap:\s*anywhere/);
	assert.match(finalCss, /\.sitx-terminal-line pre[\s\S]*white-space:\s*pre-wrap/);
});

test('all Debug Log viewers render complete parsed messages and paths', async () => {
	const [parser, classic, modern, terminal, adminCss, debugCss] = await Promise.all([
		read('includes/class-siteintelix-debug-log.php'),
		read('admin/views/debug-log-page-classic.php'),
		read('admin/views/debug-log-page-modern.php'),
		read('admin/views/debug-log-page-terminal.php'),
		read('assets/admin/css/siteintelix-admin.css'),
		read('assets/admin/css/siteintelix-debug-log.css'),
	]);

	assert.match(parser, /'raw'\s*=>\s*\$line/);
	assert.doesNotMatch(parser, /\$message\s*=\s*trim\(\s*str_replace\(\s*\$file_reference\['raw'\]/);
	assert.doesNotMatch(parser, /\$file\s*=\s*self::relativise_path/);
	assert.match(classic, /siteintelix_entry\['message'\]/);
	assert.match(modern, /sitx-log-full-message/);
	assert.match(terminal, /siteintelix_entry_text/);
	assert.match(adminCss.slice(adminCss.lastIndexOf('Full Debug Log content: Classic viewer')), /-webkit-line-clamp:\s*unset/);
	assert.match(debugCss.slice(debugCss.lastIndexOf('Full Debug Log content: Modern and Terminal viewers')), /white-space:\s*pre-wrap/);
});

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

test('Classic Debug Log links only validated plugin and theme files to native editors', async () => {
	const [main, classic, resolver] = await Promise.all([
		read('siteintelix.php'),
		read('admin/views/debug-log-page-classic.php'),
		read('includes/class-siteintelix-editor-links.php'),
	]);

	assert.match(main, /class-siteintelix-editor-links\.php/);
	assert.match(classic, /SITEINTELIX_Editor_Links::get_link/);
	assert.match(classic, /target="_blank"/);
	assert.match(classic, /rel="noopener noreferrer"/);
	assert.match(resolver, /plugin-editor\.php/);
	assert.match(resolver, /theme-editor\.php/);
	assert.match(resolver, /siteintelix_line/);
	assert.match(resolver, /realpath/);
	assert.match(resolver, /DISALLOW_FILE_EDIT/);
	assert.match(resolver, /DISALLOW_FILE_MODS/);
});

test('native WordPress file editors navigate to and highlight the requested log line', async () => {
	const [main, editorJs] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-editor-line.js'),
	]);

	assert.match(main, /plugin-editor\.php/);
	assert.match(main, /theme-editor\.php/);
	assert.match(main, /siteintelix-editor-line/);
	assert.match(main, /siteintelix_line/);
	assert.match(editorJs, /setCursor/);
	assert.match(editorJs, /scrollIntoView/);
	assert.match(editorJs, /addLineClass/);
	assert.match(editorJs, /removeLineClass/);
	assert.match(editorJs, /selectionStart/);
	assert.match(editorJs, /selectionEnd/);
});

test('Modern Debug Log removes unused filter controls and uses normal message weight', async () => {
	const [modern, debugJs, debugCss] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('assets/admin/js/siteintelix-debug-log.js'),
		read('assets/admin/css/siteintelix-debug-log.css'),
	]);

	for (const removed of ['More Filters', 'Clear Filters', 'Saved Filters', 'data-sitx-more-filters', 'siteintelix-clear-filters', 'siteintelix-saved-filters']) {
		assert.ok(!modern.includes(removed), `Modern view still includes ${removed}`);
		assert.ok(!debugJs.includes(removed), `Debug JS still includes ${removed}`);
	}

	for (const retained of ['Group Similar Logs', 'Hide Deprecated', 'Show Only Critical']) {
		assert.match(modern, new RegExp(retained));
	}

	const finalMessageCss = debugCss.slice(debugCss.lastIndexOf('Modern message typography cleanup'));
	assert.match(finalMessageCss, /\.sitx-log-full-message[\s\S]*font-weight:\s*400/);
});

test('Modern and Terminal viewers expose validated native editor links', async () => {
	const [modern, terminal] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('admin/views/debug-log-page-terminal.php'),
	]);

	assert.match(modern, /SITEINTELIX_Editor_Links::get_link/);
	assert.match(terminal, /SITEINTELIX_Editor_Links::get_link/);
	assert.match(modern, /sitx-log-path--editor/);
	assert.match(terminal, /sitx-terminal-editor-link/);

	for (const viewer of [modern, terminal]) {
		assert.match(viewer, /target="_blank"/);
		assert.match(viewer, /rel="noopener noreferrer"/);
	}
});

test('Modern Stack Trace excludes the primary error and renders only real trace continuations', async () => {
	const [modern, debugCss] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('assets/admin/css/siteintelix-debug-log.css'),
	]);

	assert.match(modern, /\$siteintelix_extract_stack_trace/);
	assert.match(modern, /array_shift\(\s*\$lines\s*\)/);
	assert.match(modern, /Stack trace:/);
	assert.match(modern, /#\\\\d\+/);
	assert.match(modern, /thrown in\\\\b/);
	assert.match(modern, /if \( '' !== \$siteintelix_stack_text \)/);
	assert.doesNotMatch(modern, /sitx-log-details-grid--timeline-only/);
	assert.doesNotMatch(debugCss, /\.sitx-log-details-grid--timeline-only[\s\S]*grid-template-columns:\s*1fr/);
});

test('Modern Debug Log uses wide trace-only details and Settings token polish', async () => {
	const [modern, debugCss, adminCss] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('assets/admin/css/siteintelix-debug-log.css'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);
	const finalDebugCss = debugCss.slice(debugCss.lastIndexOf('Modern wide trace-only layout'));
	const finalAdminCss = adminCss.slice(adminCss.lastIndexOf('Settings token refresh'));

	assert.doesNotMatch(modern, /sitx-timeline|Occurrence Timeline|Copy Details|View Full Log|sitx-log-detail-actions|sitx-log-details-grid--timeline-only/);
	assert.match(modern, /'' !== \$siteintelix_stack_text[\s\S]*data-sitx-toggle-details/);
	assert.match(modern, /'' !== \$siteintelix_stack_text[\s\S]*sitx-log-card__details/);
	assert.match(modern, /sitx-stack--full/);
	assert.match(finalDebugCss, /max-width:\s*1440px/);
	assert.match(finalDebugCss, /\.sitx-stack--full/);
	assert.match(finalAdminCss, /#siteintelix-settings-page[\s\S]*max-width:\s*1440px/);
	assert.match(finalAdminCss, /\.sitx-settings-shell/);
});

test('Server Diagnostics renders the health-first accessible dashboard shell', async () => {
	const diagnostics = await read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php');

	for (const contract of [
		'sitx-serverdiag-health',
		'sitx-serverdiag-filterbar',
		'data-sitx-diag-filter',
		'data-sitx-diag-search',
		'data-sitx-diag-expand-all',
		'data-sitx-diag-collapse-all',
		'data-sitx-diag-section',
		'data-sitx-diag-payload',
		'aria-expanded="false"',
		'aria-live="polite"',
		'Show %d passed checks',
	]) {
		assert.ok(diagnostics.includes(contract), `missing ${contract}`);
	}
	assert.match(
		diagnostics,
		/>\s*(?:Everything looks healthy\.|<\?php\s+(?:echo\s+esc_html__|esc_html_e)\(\s*(["'])Everything looks healthy\.\1\s*,\s*(["'])siteintelix\2\s*\)\s*;?\s*\?>)\s*</,
		'healthy-state copy must be the complete rendered contents of an element'
	);

	assert.doesNotMatch(
		diagnostics,
		/<table\b[^>]*\bclass\s*=\s*(["'])[^"']*\bsitx-serverdiag-table\b[^"']*\1[^>]*>/i,
		'legacy Server Diagnostics table markup must be removed'
	);
});

test('Server Diagnostics uses dedicated local dependency-free assets', async () => {
	const [main, diagnosticsJs, diagnosticsCss] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-server-diagnostics.js'),
		read('assets/admin/css/siteintelix-server-diagnostics.css'),
	]);
	const uncommentedMain = main.replace(/\/\*[\s\S]*?\*\/|^[ \t]*(?:\/\/|#).*$/gm, '');
	const conditionalBlocks = [...uncommentedMain.matchAll(/if\s*\(([^\n]*)\)\s*\{\n([\s\S]*?)\n\t\}/g)];
	const positiveDiagnosticsCondition = /(?:false\s*!==\s*strpos\s*\([^,\n]+,\s*(["'])siteintelix-server-diagnostics\1\s*\)|str_contains\s*\([^,\n]+,\s*(["'])siteintelix-server-diagnostics\2\s*\))/;
	const diagnosticsAssetsBlock = conditionalBlocks.find((match) => positiveDiagnosticsCondition.test(match[1]));

	assert.ok(diagnosticsAssetsBlock, 'missing diagnostics-specific asset conditional');
	assert.match(
		diagnosticsAssetsBlock[2],
		/wp_enqueue_style\(\s*(["'])siteintelix-server-diagnostics-style\1\s*,\s*SITEINTELIX_PLUGIN_URL\s*\.\s*(["'])assets\/admin\/css\/siteintelix-server-diagnostics\.css\2\s*,\s*array\(\s*\)/
	);
	assert.match(
		diagnosticsAssetsBlock[2],
		/wp_enqueue_script\(\s*(["'])siteintelix-server-diagnostics-script\1\s*,\s*SITEINTELIX_PLUGIN_URL\s*\.\s*(["'])assets\/admin\/js\/siteintelix-server-diagnostics\.js\2\s*,\s*array\(\s*\)/
	);
	assert.doesNotMatch(diagnosticsJs, /\b(jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(diagnosticsCss, /@import\s+url|fonts\.googleapis|cdnjs|unpkg|jsdelivr/);
	assert.match(diagnosticsCss, /prefers-reduced-motion/);
	assert.match(diagnosticsCss, /:focus-visible/);
});
