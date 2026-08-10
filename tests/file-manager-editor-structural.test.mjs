import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const root = new URL('../', import.meta.url);
const editorView = readFileSync(new URL('includes/modules/file-manager/views/partials/editor.php', root), 'utf8');
const editorScript = readFileSync(new URL('includes/modules/file-manager/assets/file-manager.js', root), 'utf8');
const editorStyles = readFileSync(new URL('includes/modules/file-manager/assets/file-manager.css', root), 'utf8');
const adminClass = readFileSync(new URL('includes/modules/file-manager/class-siteintelix-file-manager-admin.php', root), 'utf8');

test('fullscreen control has styled accessible state', () => {
	assert.match(editorView, /si-button si-button--secondary sitx-fm-editor__fullscreen/);
	assert.match(editorView, /data-fm-editor-fullscreen-label/);
	assert.match(editorView, /aria-pressed="false"/);
	assert.match(editorStyles, /\.sitx-fm-editor\.is-fullscreen\s*\{[^}]*inset:\s*0;/s);
	assert.match(editorScript, /setEditorFullscreen/);
	assert.match(editorScript, /editorInstance\.refresh\(\)/);
	assert.match(editorScript, /sitx-fm-editor-fullscreen-open/);
});

test('editor reports save progress success and failure visibly', () => {
	assert.match(editorView, /data-fm-editor-status/);
	assert.match(editorView, /aria-live="polite"/);
	assert.match(editorStyles, /\.sitx-fm-editor__status\.is-success/);
	assert.match(editorStyles, /\.sitx-fm-editor__status\.is-error/);
	assert.match(editorScript, /setEditorStatus\('saving'/);
	assert.match(editorScript, /setEditorStatus\('success'/);
	assert.match(editorScript, /setEditorStatus\('error'/);
	assert.match(adminClass, /'saved'\s*=> __\( 'File saved successfully\.'/);
});

test('save button prevents duplicate submissions while saving', () => {
	assert.match(editorScript, /state\.editorSaving/);
	assert.match(editorScript, /saveButton\.disabled = true/);
	assert.match(editorScript, /aria-busy/);
});
