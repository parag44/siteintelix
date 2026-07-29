import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const scriptUrl = new URL('../includes/modules/file-manager/assets/file-manager.js', import.meta.url);

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
});

test('client posts only fixed localized actions with operation-specific nonces', async () => {
	const { source } = await loadHelpers();

	assert.match(source, /form\.set\(\s*'action',\s*'siteintelix_fm_'\s*\+\s*action\s*\)/);
	assert.match(source, /data\.nonces\[action\]/);
	assert.doesNotMatch(source, /eval\s*\(/);
	assert.doesNotMatch(source, /new Function\s*\(/);
});
