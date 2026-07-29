import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const scriptUrl = new URL('../includes/modules/file-manager/assets/file-manager.js', import.meta.url);
const stylesheetUrl = new URL('../includes/modules/file-manager/assets/file-manager.css', import.meta.url);

async function loadHelpers() {
	const source = await readFile(scriptUrl, 'utf8');
	const context = {
		clearTimeout,
		setTimeout,
		URLSearchParams,
	};
	context.globalThis = context;
	vm.runInNewContext(source, context);
	return { helpers: context.siteintelixFileManagerTest, source };
}

test('navigation history preserves back and forward paths', async () => {
	const { helpers } = await loadHelpers();
	const history = helpers.createHistory('wp-content');
	history.visit('wp-content/plugins');
	history.visit('wp-content/themes');

	assert.equal(history.back(), 'wp-content/plugins');
	assert.equal(history.back(), 'wp-content');
	assert.equal(history.forward(), 'wp-content/plugins');
	assert.equal(history.forward(), 'wp-content/themes');
});

test('visiting after back clears forward history', async () => {
	const { helpers } = await loadHelpers();
	const history = helpers.createHistory('wp-content');
	history.visit('wp-content/plugins');
	history.visit('wp-content/themes');
	history.back();
	history.visit('wp-content/uploads');

	assert.equal(history.forward(), 'wp-content/uploads');
	assert.deepEqual(Array.from(history.snapshot().forward), []);
});

test('search debounce keeps only the final invocation', async () => {
	const { helpers } = await loadHelpers();
	const values = [];
	const callback = helpers.debounce((value) => values.push(value), 10);
	callback('one');
	callback('two');
	await new Promise((resolve) => setTimeout(resolve, 25));

	assert.deepEqual(values, ['two']);
});

test('sort parameters are restricted to the server allowlist', async () => {
	const { helpers } = await loadHelpers();

	assert.equal(helpers.normalizeSort('modified'), 'modified');
	assert.equal(helpers.normalizeSort('path'), 'name');
	assert.equal(helpers.normalizeOrder('desc'), 'desc');
	assert.equal(helpers.normalizeOrder('sideways'), 'asc');
});

test('file categories are normalized to a fixed visual allowlist', async () => {
	const { helpers } = await loadHelpers();

	assert.equal(helpers.fileCategory({ type: 'directory', name: 'plugins' }), 'folder');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'plugin.php', extension: 'PHP' }), 'php');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'admin.css' }), 'css');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'photo.webp' }), 'image');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'bundle.zip' }), 'archive');
	assert.equal(helpers.fileCategory({ type: 'file', name: '.env' }), 'text');
	assert.equal(helpers.fileCategory({ type: 'file', name: 'unknown.xyz' }), 'file');
});

test('context actions preserve server permissions and stable labels', async () => {
	const { helpers } = await loadHelpers();
	const actions = helpers.contextActions({
		type: 'file',
		actions: ['open', 'details', 'download', 'rename', 'trash', 'arbitrary'],
	});

	assert.deepEqual(
		Array.from(actions, (action) => `${action.id}:${action.label}:${action.icon}`),
		[
			'open:Open:dashicons-external',
			'details:View details:dashicons-info-outline',
			'download:Download:dashicons-download',
			'rename:Rename:dashicons-edit',
			'trash:Move to Trash:dashicons-trash',
		],
	);
	assert.deepEqual(Array.from(helpers.contextActions({ type: 'file', actions: ['details'] }), (action) => action.id), ['details']);
});

test('primary actions and context-menu coordinates follow desktop conventions', async () => {
	const { helpers } = await loadHelpers();

	assert.equal(helpers.primaryAction({ type: 'directory', actions: ['open', 'details'] }), 'open');
	assert.equal(helpers.primaryAction({ type: 'file', actions: ['edit', 'details'] }), 'edit');
	assert.equal(helpers.primaryAction({ type: 'file', actions: ['details'] }), 'details');
	assert.deepEqual(
		{ ...helpers.clampMenuPosition({ x: 790, y: 590 }, { width: 180, height: 220 }, { width: 800, height: 600, padding: 8 }) },
		{ left: 612, top: 372 },
	);
});

test('row primary actions ignore interactive descendants', async () => {
	const { helpers } = await loadHelpers();
	const interactiveTarget = {
		closest: (selector) => selector.includes('button') ? {} : null,
	};
	const plainTarget = {
		closest: () => null,
	};

	assert.equal(helpers.shouldHandleRowAction(interactiveTarget), false);
	assert.equal(helpers.shouldHandleRowAction(plainTarget), true);
	assert.equal(helpers.shouldHandleRowAction(null), true);
});

test('client rendering uses safe DOM assignment and accessible modal behavior', async () => {
	const { source } = await loadHelpers();

	assert.doesNotMatch(source, /\.innerHTML\s*=/);
	assert.match(source, /\.textContent\s*=/);
	assert.doesNotMatch(source, /\b(?:window\.)?(?:prompt|confirm)\s*\(/);
	assert.match(source, /restoreFocus/);
	assert.match(source, /event\.key\s*===\s*'Escape'/);
	assert.match(source, /beforeunload/);
	assert.match(source, /editorDirty/);
	assert.match(source, /siteintelix_fm_preview_image/);
	assert.match(source, /document\.createElement\(\s*'img'\s*\)/);
	assert.match(source, /permanently_delete_item'[\s\S]*confirmation:\s*values\.confirmation/);
	assert.match(source, /preventDefault\(\)/);
	assert.match(source, /data-fm-context-menu/);
	assert.match(source, /event\.key\s*===\s*'F10'\s*&&\s*event\.shiftKey/);
	assert.match(source, /event\.key\s*===\s*'ContextMenu'/);
	assert.match(source, /setAttribute\(\s*'aria-selected'/);
	assert.match(source, /addEventListener\(\s*'dblclick'/);
	assert.match(source, /addEventListener\(\s*'contextmenu'/);
	assert.match(source, /data-fm-row-menu/);
	assert.match(source, /detailsRequestId/);
	assert.match(source, /event\.key\s*===\s*' '\s*\|\|\s*event\.key\s*===\s*'Spacebar'/);
	assert.match(source, /document\.activeElement\.click\(\)/);
	assert.match(source, /setPanelOpen/);
	assert.match(source, /matchMedia\(\s*'\(max-width: 1100px\)'\s*\)/);
	assert.doesNotMatch(source, /data-fm-details-actions/);
});

test('client posts only fixed localized actions with operation-specific nonces', async () => {
	const { source } = await loadHelpers();

	assert.match(source, /form\.set\(\s*'action',\s*'siteintelix_fm_'\s*\+\s*action\s*\)/);
	assert.match(source, /data\.nonces\[action\]/);
	assert.doesNotMatch(source, /eval\s*\(/);
	assert.doesNotMatch(source, /new Function\s*\(/);
});

test('responsive folder drawer clears the WordPress admin menu when closed', async () => {
	const css = await readFile(stylesheetUrl, 'utf8');

	assert.match(css, /\.sitx-fm-tree\s*\{[\s\S]*?left:\s*160px;[\s\S]*?transform:\s*translateX\(calc\(-100%\s*-\s*160px\)\);/);
	assert.match(css, /body\.auto-fold \.sitx-fm-tree,[\s\S]*body\.folded \.sitx-fm-tree\s*\{[\s\S]*?left:\s*36px;[\s\S]*?translateX\(calc\(-100%\s*-\s*36px\)\)/);
	assert.match(css, /body\.auto-fold \.sitx-fm-tree\.is-open,[\s\S]*body\.folded \.sitx-fm-tree\.is-open\s*\{[\s\S]*?transform:\s*translateX\(0\)/);
});
