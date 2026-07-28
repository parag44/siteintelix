import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const pluginRoot = new URL('../', import.meta.url);
const read = (path) => readFile(new URL(path, pluginRoot), 'utf8');

test('Email Log list queries metadata only and previews by numeric ID', async () => {
	const php = await read('includes/modules/email-log/class-siteintelix-email-log-module.php');
	assert.doesNotMatch(php, /SELECT\s+\*\s+FROM\s+\{\$table_name\}/i);
	assert.match(php, /SELECT\s+id\s*,\s*status\s*,\s*sent_at\s*,\s*to_email\s*,\s*subject\s*,\s*error_message\s+FROM/i);
	assert.match(php, /data-siteintelix-email-id/);
	assert.doesNotMatch(php, /data-siteintelix-email-preview=/);
	assert.match(php, /wp_ajax_siteintelix_get_email_preview/);
});

test('Email Log retention is scheduled and bounded away from mail sends', async () => {
	const php = await read('includes/modules/email-log/class-siteintelix-email-log-module.php');
	const insert = php.match(/public static function insert_log\([\s\S]*?\n\t\}/)?.[0] || '';
	assert.doesNotMatch(insert, /retention/i);
	assert.match(php, /siteintelix_email_log_retention/);
	assert.match(php, /RETENTION_BATCH_SIZE/);
	assert.match(php, /wp_schedule_event/);
	assert.match(php, /wp_clear_scheduled_hook/);
	assert.match(php, /KEY status_sent_at_id \(status, sent_at, id\)/);
});

test('Email Log has a screen-specific local asset with compact localization', async () => {
	const [main, js] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-email-log.js'),
	]);
	assert.match(main, /assets\/admin\/js\/siteintelix-email-log\.js/);
	assert.match(main, /siteintelixEmailLogData/);
	assert.match(js, /siteintelix_get_email_preview/);
	assert.match(js, /previewCache/);
	assert.doesNotMatch(js, /innerHTML\s*=/);
});

test('Test-email dialog exposes a visible operation-specific action footer', async () => {
	const [main, js, css] = await Promise.all([
		read('siteintelix.php'),
		read('assets/admin/js/siteintelix-email-log.js'),
		read('assets/admin/css/siteintelix-email-log.css'),
	]);
	assert.match(main, /'sendEmail'\s*=>\s*__\(\s*'Send Email'/);
	assert.match(js, /sitx-action-dialog__actions/);
	assert.match(js, /config\.sendEmail/);
	assert.match(css, /\.sitx-action-dialog__actions/);
	assert.match(css, /var\(--si-primary,\s*#2271b1\)/);
});

test('Bulk actions include nonce-protected delete-all routing', async () => {
	const php = await read('includes/modules/email-log/class-siteintelix-email-log-module.php');
	assert.match(php, /<option value="delete_all">/);
	assert.match(php, /if \( 'delete_all' === \$bulk_action \)/);
	assert.match(php, /private static function clear_logs_table\(\)/);
	const bulkHandler = php.match(/public static function handle_bulk_action\(\)[\s\S]*?\n\t\}/)?.[0] || '';
	assert.ok(bulkHandler.indexOf("check_admin_referer( 'siteintelix_bulk_email_logs' )") < bulkHandler.indexOf("if ( 'delete_all' === $bulk_action )"));
});
