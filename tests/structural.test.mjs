import test from 'node:test';
import assert from 'node:assert/strict';
import { access, readFile, readdir } from 'node:fs/promises';
import { constants } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const read = (relativePath) => readFile(path.join(root, relativePath), 'utf8');

const listFiles = async (directory) => {
	const entries = await readdir(directory, { withFileTypes: true });
	const files = [];
	for (const entry of entries) {
		if (['.git', '.superpowers', 'docs', 'tests'].includes(entry.name)) {
			continue;
		}
		const absolute = path.join(directory, entry.name);
		if (entry.isDirectory()) {
			files.push(...await listFiles(absolute));
		} else {
			files.push(absolute);
		}
	}
	return files;
};

test('plugin exposes the approved public name without changing internal identity', async () => {
	const main = await read('siteintelix.php');
	const readme = await read('readme.txt');
	assert.match(main, /Plugin Name:\s+SiteIntelix – WordPress Toolkit/);
	assert.match(main, /Plugin URI:\s+https:\/\/wordpress\.org\/plugins\/siteintelix/);
	assert.match(readme, /^=== SiteIntelix – WordPress Toolkit ===/);
	assert.match(main, /Text Domain:\s+siteintelix/);
	assert.match(main, /'siteintelix'/);
});

test('File Manager is disabled by default and loads only when enabled', async () => {
	const [main, registry, cards, module] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-module.php'),
	]);
	assert.match(registry, /'file_manager'\s*=>\s*array\(/);
	assert.match(registry, /'slug'\s*=>\s*'file-manager'/);
	assert.match(registry, /Safely browse, inspect, edit, upload, download, and manage files inside your WordPress installation\./);
	assert.match(registry, /'default'\s*=>\s*false/);
	assert.match(main, /SITEINTELIX_Modules::is_enabled\(\s*'file_manager'\s*\)[\s\S]*class-siteintelix-file-manager-module\.php/);
	assert.match(main, /SITEINTELIX_File_Manager_Module::init\(\)/);
	assert.match(cards, /'file_manager'\s*=>\s*admin_url\(\s*'admin\.php\?page=siteintelix-file-manager'/);
	assert.match(cards, /siteintelix-file-manager-settings/);
	assert.match(module, /class-siteintelix-file-manager-tree\.php[\s\S]*class-siteintelix-file-manager-ajax\.php/);
});

test('File Manager folder tree stays lazy and non-recursive', async () => {
	const tree = await read('includes/modules/file-manager/class-siteintelix-file-manager-tree.php');
	assert.match(tree, /class SITEINTELIX_File_Manager_Tree/);
	assert.match(tree, /FilesystemIterator/);
	assert.doesNotMatch(tree, /RecursiveDirectoryIterator/);
});

test('File Manager exposes bounded authenticated ZIP downloads', async () => {
	const [module, admin, ajax, archive] = await Promise.all([
		read('includes/modules/file-manager/class-siteintelix-file-manager-module.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-admin.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-ajax.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-archive.php'),
	]);
	assert.match(module, /class-siteintelix-file-manager-archive\.php[\s\S]*class-siteintelix-file-manager-ajax\.php/);
	assert.match(module, /cleanup_temporary_archives\(\)/);
	assert.match(admin, /archiveNonce/);
	assert.match(admin, /archiveAvailable/);
	assert.match(admin, /'archiveSelection'\s*=>\s*100/);
	assert.match(admin, /'archiveEntries'\s*=>\s*5000/);
	assert.match(admin, /'archiveBytes'\s*=>\s*250\s*\*\s*MB_IN_BYTES/);
	assert.match(ajax, /function download_archive\(\)/);
	assert.match(ajax, /finally/);
	assert.match(ajax, /Audit::record\(\s*'archive_download'/);
	assert.match(archive, /Content-Type: application\/zip/);
	assert.match(archive, /Content-Disposition: attachment/);
	assert.match(archive, /X-Content-Type-Options: nosniff/);
	assert.match(archive, /fread\(\s*\$handle,\s*65536\s*\)/);
	assert.doesNotMatch(archive, /file_get_contents\(/);
});

test('File Manager admin UI is accessible and its assets are screen-scoped', async () => {
	const required = [
		'includes/modules/file-manager/class-siteintelix-file-manager-admin.php',
		'includes/modules/file-manager/views/file-manager.php',
		'includes/modules/file-manager/views/settings.php',
		'includes/modules/file-manager/views/partials/toolbar.php',
		'includes/modules/file-manager/views/partials/file-table.php',
		'includes/modules/file-manager/views/partials/folder-tree.php',
		'includes/modules/file-manager/views/partials/details-panel.php',
		'includes/modules/file-manager/views/partials/editor.php',
		'includes/modules/file-manager/views/partials/modals.php',
		'includes/modules/file-manager/assets/file-manager.css',
	];
	for (const file of required) {
		await access(path.join(root, file), constants.F_OK);
	}
	const [admin, page, modals, css] = await Promise.all([
		read(required[0]),
		read(required[1]),
		read(required[8]),
		read(required[9]),
	]);
	assert.match(admin, /siteintelix_page_siteintelix-file-manager/);
	assert.match(admin, /SITEINTELIX_File_Manager_Security::capability\(\)/);
	assert.match(admin, /wp_enqueue_code_editor\(/);
	assert.match(admin, /siteintelix_render_module_settings_sections/);
	assert.match(page, /data-siteintelix-file-manager/);
	assert.match(page, /data-file-manager-tab="browser"/);
	assert.match(page, /data-file-manager-tab="backups"/);
	assert.match(page, /data-file-manager-tab="trash"/);
	assert.match(page, /data-file-manager-tab="settings"/);
	assert.match(page, /<\?php endif; \?>\s*<\?php require __DIR__ \. '\/partials\/modals\.php'; \?>/);
	assert.match(modals, /role="dialog"/);
	assert.match(modals, /aria-modal="true"/);
	assert.match(page, /aria-live="polite"/);
	assert.match(css, /:focus-visible/);
	assert.match(css, /prefers-reduced-motion/);
	assert.match(admin, /'browser'\s*===\s*self::requested_manager_tab\(\)[\s\S]*wp_enqueue_code_editor\(/);
});

test('File Manager uses one safely rendered folder SVG in every admin context', async () => {
	const [ui, registry, modules, settings, page, css] = await Promise.all([
		read('includes/class-siteintelix-admin-ui.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
		read('admin/views/settings-page.php'),
		read('includes/modules/file-manager/views/file-manager.php'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);

	assert.match(registry, /'file_manager'[\s\S]*'icon_svg'\s*=>\s*'<svg/);
	assert.match(ui, /public static function svg_icon\(/);
	assert.match(ui, /'icon_svg'\s*=>\s*''/);
	assert.match(modules, /SITEINTELIX_Admin_UI::svg_icon\(/);
	assert.match(settings, /SITEINTELIX_Admin_UI::svg_icon\(/);
	assert.match(page, /'icon_svg'\s*=>\s*\$siteintelix_fm_module\['icon_svg'\]/);
	assert.match(css, /\.sitx-module-card__icon\s*>\s*svg,[\s\S]*\.sitx-settings-tab__icon\s*>\s*svg/);
	assert.doesNotMatch(registry, /'file_manager'[\s\S]{0,600}'icon'\s*=>\s*'dashicons-open-folder'/);
});

test('File Manager browser exposes the approved desktop workspace shell', async () => {
	const [toolbar, tree, table, details, modals, page] = await Promise.all([
		read('includes/modules/file-manager/views/partials/toolbar.php'),
		read('includes/modules/file-manager/views/partials/folder-tree.php'),
		read('includes/modules/file-manager/views/partials/file-table.php'),
		read('includes/modules/file-manager/views/partials/details-panel.php'),
		read('includes/modules/file-manager/views/partials/modals.php'),
		read('includes/modules/file-manager/views/file-manager.php'),
	]);

	assert.match(toolbar, /data-fm-new-folder/);
	assert.match(toolbar, /data-fm-new-file/);
	assert.match(toolbar, /data-fm-upload/);
	assert.match(toolbar, /data-fm-sort-menu/);
	assert.match(toolbar, /data-fm-refresh/);
	assert.doesNotMatch(toolbar, /data-fm-(?:back|forward|up|toggle-details)(?:\s|>)/);
	assert.match(tree, /id="siteintelix-file-manager-tree"/);
	assert.match(tree, /data-fm-tree-root/);
	assert.match(tree, /role="tree"/);
	assert.match(table, /data-fm-select-all/);
	assert.match(table, /data-fm-selection-actions/);
	assert.match(table, /data-fm-selection-archive/);
	assert.doesNotMatch(table, />Actions</);
	assert.match(table, /sitx-fm-table__menu-column/);
	assert.match(table, /Item menu/);
	assert.match(details, /id="siteintelix-file-manager-details"/);
	assert.match(details, /data-fm-details-icon/);
	assert.match(details, /role="dialog"/);
	assert.match(details, /aria-modal="true"/);
	assert.match(details, /hidden/);
	assert.doesNotMatch(details, /data-fm-details-actions/);
	assert.match(modals, /data-fm-context-menu/);
	assert.match(modals, /role="menu"/);
	assert.match(page, /data-fm-archive-target/);
});

test('File Manager workspace matches the approved DirectAdmin two-pane design', async () => {
	const css = await read('includes/modules/file-manager/assets/file-manager.css');

	assert.match(css, /\.sitx-fm-browser\s*\{[\s\S]*overflow:\s*hidden/);
	assert.match(css, /\.sitx-fm-workspace\s*\{[\s\S]*height:\s*clamp\(/);
	assert.match(css, /grid-template-columns:\s*260px\s+minmax\(0,\s*1fr\)/);
	assert.match(css, /\.sitx-fm-tree__row[\s\S]*padding-left:\s*calc\(/);
	assert.match(css, /\.sitx-fm-tree__item\.is-current/);
	assert.match(css, /\.sitx-fm-selection-actions/);
	assert.match(css, /\.sitx-fm-details\[role="dialog"\]/);
	assert.match(css, /\.sitx-fm-tool/);
	assert.match(css, /\.sitx-fm-table-scroll\s*\{[\s\S]*height:\s*100%[\s\S]*overflow:\s*auto/);
	assert.match(css, /\.sitx-fm-table thead\s*\{[\s\S]*position:\s*sticky/);
	assert.match(css, /\.sitx-fm-file-icon--folder/);
	assert.match(css, /\.sitx-file-manager\s+span\.sitx-fm-file-icon\s*\{[\s\S]*position:\s*relative/);
	assert.match(css, /\.sitx-fm-context-menu\s*\{[\s\S]*position:\s*fixed/);
	assert.match(css, /\.sitx-fm-table tbody tr\.is-selected/);
	assert.match(css, /@media \(max-width:\s*1100px\)/);
	assert.match(css, /body\.auto-fold \.sitx-fm-tree/);
});

test('File Manager retention and uninstall lifecycle preserve site files', async () => {
	const [main, module, backups, trash, storage, uninstall] = await Promise.all([
		read('siteintelix.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-module.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-backups.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-trash.php'),
		read('includes/modules/file-manager/class-siteintelix-file-manager-storage.php'),
		read('uninstall.php'),
	]);
	assert.match(module, /add_action\(\s*'siteintelix_file_manager_cleanup'/);
	assert.match(module, /wp_next_scheduled\(\s*'siteintelix_file_manager_cleanup'\s*\)/);
	assert.match(module, /wp_schedule_event\([\s\S]*'daily'[\s\S]*'siteintelix_file_manager_cleanup'/);
	assert.match(module, /SITEINTELIX_File_Manager_Backups[\s\S]*cleanup\(\)/);
	assert.match(module, /SITEINTELIX_File_Manager_Trash[\s\S]*cleanup\(\)/);
	assert.match(main, /wp_doing_cron\(\)/);
	assert.match(module, /\$siteintelix_file_manager_is_cron[\s\S]*class-siteintelix-file-manager-admin\.php/);
	assert.match(backups, /\$this->cleanup\(\s*\$id\s*,\s*true\s*\)/);
	assert.match(backups, /public function cleanup\([^)]*\)/);
	assert.match(trash, /public function cleanup\(\)/);
	assert.match(main, /SITEINTELIX_File_Manager_Module::deactivate\(\)/);
	assert.match(module, /wp_clear_scheduled_hook\(\s*'siteintelix_file_manager_cleanup'\s*\)/);
	assert.doesNotMatch(module, /function deactivate\(\)[\s\S]*delete_owned_tree/);

	assert.match(uninstall, /get_option\(\s*'siteintelix_file_manager_settings'/);
	assert.match(uninstall, /'siteintelix_file_manager_settings'/);
	assert.match(uninstall, /remove_data_on_uninstall/);
	assert.match(uninstall, /SITEINTELIX_File_Manager_Storage::delete_all_owned_data\(\)/);
	assert.match(storage, /WP_CONTENT_DIR\s*\.\s*'\/siteintelix\/file-manager'/);
	assert.match(storage, /public static function delete_all_owned_data\(\)/);
	assert.match(storage, /is_link\(/);
	assert.match(storage, /is_link\(\s*\$owner_path\s*\)/);
	assert.doesNotMatch(uninstall, /original_path[\s\S]*(?:unlink|rmdir|wp_delete_file)/);
});

test('File Manager release documentation describes Safe Mode boundaries', async () => {
	const [readme, documentation] = await Promise.all([
		read('readme.txt'),
		read('docs/file-manager.md'),
	]);
	for (const phrase of [
		'File Manager',
		'Safe Mode',
		'PHP',
		'DISALLOW_FILE_EDIT',
		'DISALLOW_FILE_MODS',
		'multisite',
		'retention',
		'uninstall',
		'WordPress root',
		'read-only',
		'ZIP',
		'ZipArchive',
		'5,000',
		'250 MB',
		'symlink',
		'temporary',
	]) {
		assert.match(documentation, new RegExp(phrase, 'i'));
	}
	assert.match(readme, /\*\*File Manager\*\*/);
	assert.match(readme, /PHP files remain view-only/i);
	assert.match(readme, /File Manager-owned backups, trash, metadata, and audit records/i);
	assert.match(readme, /expandable folder tree/i);
	assert.match(readme, /private temporary ZIP/i);
});

test('shipped PHP files block direct access and dangerous process execution', async () => {
	const phpFiles = (await listFiles(root)).filter((file) => file.endsWith('.php'));
	let evalCount = 0;

	for (const file of phpFiles) {
		const source = await readFile(file, 'utf8');
		const relative = path.relative(root, file);
		assert.match(
			source,
			/defined\(\s*'ABSPATH'\s*\)|WP_UNINSTALL_PLUGIN/,
			`${relative} must block direct execution`
		);
		assert.doesNotMatch(
			source,
			/\b(?:shell_exec|exec|system|passthru|proc_open)\s*\(/,
			`${relative} must not execute system commands`
		);
		const matches = source.match(/\beval\s*\(/g) ?? [];
		evalCount += matches.length;
		if (matches.length) {
			assert.equal(relative, 'includes/modules/code-snippets/class-siteintelix-snippets-runner.php');
		}
	}

	assert.equal(evalCount, 1, 'only the isolated administrator-authored snippet runner may use eval()');
});

test('download and export responses prevent MIME sniffing', async () => {
	const files = [
		'siteintelix.php',
		'includes/modules/download-manager/class-siteintelix-download-manager-module.php',
		'includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php',
		'includes/modules/transients-manager/class-siteintelix-transients-manager-module.php',
	];

	for (const file of files) {
		assert.match(await read(file), /X-Content-Type-Options:\s*nosniff/, `${file} must send a nosniff header`);
	}
});

test('uninstall removes current settings and only ownership-verified persistent files', async () => {
	const uninstall = await read('uninstall.php');

	for (const option of [
		'siteintelix_smtp_settings',
		'siteintelix_coming_soon_settings',
		'siteintelix_server_diagnostics_cache_filesystem',
		'siteintelix_server_diagnostics_cache_network',
		'siteintelix_server_diagnostics_cache_database',
	]) {
		assert.match(uninstall, new RegExp(`['"]${option}['"]`), `uninstall must remove ${option}`);
	}

	assert.match(uninstall, /delete_metadata\(\s*'user',\s*0,\s*'siteintelix_safe_mode_state'/);
	assert.match(uninstall, /delete_metadata\(\s*'user',\s*0,\s*'siteintelix_safe_mode_log'/);
	assert.match(uninstall, /SITEINTELIX_MU_Files::remove_all\(\)/);
	assert.match(
		uninstall,
		/\$wpdb->esc_like\(\s*\$siteintelix_user_switcher_option_prefix\s*\)\s*\.\s*'%'/,
		'uninstall must escape literal option-name prefixes before adding the SQL LIKE wildcard',
	);
});

test('2.7.3 release metadata, directory description, and privacy disclosure stay aligned', async () => {
	const [main, migrations, readme] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-migrations.php'),
		read('readme.txt'),
	]);
	const shortDescription = readme.split('\n').find((line, index, lines) => index > lines.findIndex((entry) => entry.startsWith('License URI:')) && line.trim())?.trim() ?? '';

	assert.match(main, /Version:\s+2\.7\.3/);
	assert.match(main, /define\(\s*'SITEINTELIX_VERSION',\s*'2\.7\.3'\s*\)/);
	assert.match(migrations, /CURRENT_VERSION\s*=\s*'2\.7\.3\.0'/);
	assert.match(readme, /Stable tag:\s+2\.7\.3/);
	assert.match(readme, /== Changelog ==\s+\n\s*= 2\.7\.3 — 2026-07-28 =/);
	assert.match(readme, /== Upgrade Notice ==\s+\n\s*= 2\.7\.3 =/);
	assert.ok(shortDescription.length > 0 && shortDescription.length <= 150, 'WordPress.org short description must be 1–150 characters');
	assert.match(readme, /^Tags:\s+debug log, email log, diagnostics, code snippets, admin tools$/m);
	assert.match(readme, /Custom CSS & JS/);
	assert.match(readme, /Code Snippets/);
	assert.match(readme, /User Switcher/);
	assert.match(readme, /administrator-authored PHP/i);
	assert.match(readme, /SMTP provider/i);
	assert.match(readme, /no telemetry/i);
	assert.match(readme, /multisite super administrator/i);
	assert.match(readme, /WordPress\.org endpoints/);
	assert.doesNotMatch(readme, /No data is sent to any third-party service/);
	assert.doesNotMatch(main, /load_plugin_textdomain\s*\(/);
});

test('network-sensitive transient operations use the central global-tools policy', async () => {
	const [security, transients] = await Promise.all([
		read('includes/class-siteintelix-security.php'),
		read('includes/modules/transients-manager/class-siteintelix-transients-manager-module.php'),
	]);

	assert.match(security, /GLOBAL_MODULES[\s\S]*'transients_manager'/);
	assert.match(transients, /SITEINTELIX_Security::can_manage_global_tools\(\)/);
	assert.doesNotMatch(
		transients,
		/private static function require_manage_options\(\)[\s\S]*current_user_can\(\s*'manage_options'\s*\)/,
	);
});

test('Maintenance artwork is Media Library-only with responsive SVG fallback', async () => {
	const [module, settingsJs, css, main] = await Promise.all([
		read('includes/modules/coming-soon/class-siteintelix-coming-soon-module.php'),
		read('assets/admin/js/siteintelix-settings.js'),
		read('assets/admin/css/siteintelix-admin.css'),
		read('siteintelix.php'),
	]);

	assert.match(module, /name="artwork_id"/);
	assert.match(module, /wp_attachment_is_image/);
	assert.match(module, /wp_get_attachment_image\([\s\S]*?\$artwork_id[\s\S]*?'large'/);
	assert.match(module, /sitx-maintenance__art--custom/);
	assert.match(module, /<svg class="sitx-maintenance__art"/);
	assert.doesNotMatch(module, /name="artwork_url"/);
	assert.match(settingsJs, /library:\s*\{\s*type:\s*'image'\s*\}/);
	assert.match(settingsJs, /multiple:\s*false/);
	assert.match(css, /\.sitx-maintenance-media-preview--artwork/);
	assert.match(module, /sitx-maintenance__art--custom\{[^}]*max-width:min\(520px,100%\)/);
	assert.match(main, /siteintelix-settings[\s\S]*wp_enqueue_media\(\)/);
});

test('WordPress.org readme documents the nine approved release screenshots in order', async () => {
	const readme = await read('readme.txt');
	const screenshots = readme
		.split('== Screenshots ==')[1]
		?.split('== Changelog ==')[0]
		?.trim() ?? '';
	const expectedTopics = [
		'1. **Overview Dashboard**',
		'2. **Modules**',
		'3. **Modern Debug Log Viewer**',
		'4. **Email Log**',
		'5. **Database Manager**',
		'6. **Server Diagnostics**',
		'7. **User Switcher**',
		'8. **Settings**',
		'9. **Cron Events**',
	];

	for (const topic of expectedTopics) {
		assert.ok(screenshots.includes(topic), `missing screenshot caption: ${topic}`);
	}
	assert.equal(
		screenshots.match(/^\d+\.\s+\*\*/gm)?.length,
		expectedTopics.length,
		'readme must contain exactly nine numbered screenshot captions'
	);
	assert.deepEqual(
		expectedTopics.map((topic) => screenshots.indexOf(topic)),
		expectedTopics.map((_, index, topics) => {
			const topic = topics[index];
			return screenshots.indexOf(topic);
		}).sort((a, b) => a - b),
		'screenshot captions must remain in the approved order'
	);
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

test('MU bootstrap cleanup is centralized across module and plugin lifecycles', async () => {
	const managerPath = path.join(root, 'includes/class-siteintelix-mu-files.php');
	await access(managerPath, constants.F_OK);

	const [main, uninstall, debugModule, safeModeModule] = await Promise.all([
		read('siteintelix.php'),
		read('uninstall.php'),
		read('includes/class-siteintelix-mu-debug.php'),
		read('includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php'),
	]);

	assert.match(main, /require_once\s+SITEINTELIX_PLUGIN_DIR\s*\.\s*'includes\/class-siteintelix-mu-files\.php'/);
	assert.match(main, /function\s+siteintelix_deactivate\s*\(\s*\)[\s\S]*?SITEINTELIX_MU_Files::remove_all\s*\(\s*\)/);
	assert.match(uninstall, /__DIR__\s*\.\s*'\/includes\/class-siteintelix-mu-files\.php'/);
	assert.match(uninstall, /SITEINTELIX_MU_Files::remove_all\s*\(\s*\)/);
	assert.doesNotMatch(uninstall, /WPMU_PLUGIN_DIR[\s\S]{0,160}siteintelix-debug-capture\.php/);
	assert.match(debugModule, /SITEINTELIX_MU_Files::remove_type\s*\(\s*SITEINTELIX_MU_Files::TYPE_DEBUG\s*\)/);
	assert.match(safeModeModule, /function\s+deactivate\s*\(\s*\)[\s\S]*?SITEINTELIX_MU_Files::remove_type\s*\(\s*SITEINTELIX_MU_Files::TYPE_SAFE_MODE\s*\)/);
});

test('generated SiteIntelix MU bootstraps expose useful WordPress metadata', async () => {
	const [debugModule, safeModeModule] = await Promise.all([
		read('includes/class-siteintelix-mu-debug.php'),
		read('includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php'),
	]);

	assert.match(debugModule, /Plugin Name:\s*SiteIntelix Debug Capture/);
	assert.match(debugModule, /Description:\s*Captures PHP errors before normal plugins load and writes them to the private SiteIntelix debug log\./);
	assert.match(debugModule, /Version:[^\n]*SITEINTELIX_VERSION/);
	assert.match(debugModule, /Author:\s*Parag Das/);

	assert.match(safeModeModule, /Plugin Name:\s*SiteIntelix Safe Mode/);
	assert.match(safeModeModule, /Description:\s*Applies private, session-based plugin and theme isolation before normal plugins load\./);
	assert.match(safeModeModule, /Version:[^\n]*SITEINTELIX_VERSION/);
	assert.match(safeModeModule, /Author:\s*Parag Das/);
	assert.doesNotMatch(debugModule + safeModeModule, /siteintelix-plugin-safety-guard\.php/);
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
	assert.match(migration, /SITEINTELIX_MU_Files::remove_type\(\s*SITEINTELIX_MU_Files::TYPE_SAFETY_GUARD\s*\)/);
	assert.match(migration, /empty\(\s*\$cleanup\['failed'\]\s*\)/);
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

test('module toggle hides the native checkbox without hiding its slider focus indicator', async () => {
	const css = await read('assets/admin/css/siteintelix-admin.css');

	assert.match(
		css,
		/\.sitx-toggle input\s*\{[\s\S]*?clip:\s*rect\(0 0 0 0\);[\s\S]*?clip-path:\s*inset\(50%\);[\s\S]*?overflow:\s*hidden;[\s\S]*?\}/
	);
	assert.match(
		css,
		/\.sitx-toggle input:focus-visible\s*\{[\s\S]*?box-shadow:\s*none;[\s\S]*?outline:\s*0;[\s\S]*?\}/
	);
	assert.match(
		css,
		/\.sitx-toggle input:focus-visible\s*\+\s*\.sitx-toggle__slider\s*\{[\s\S]*?box-shadow:/
	);
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

test('SiteIntelix admin-bar parent reuses the sidebar chart-area Dashicon', async () => {
	const main = await read('siteintelix.php');
	const start = main.indexOf('function siteintelix_register_admin_bar_link');
	const end = main.indexOf("add_action( 'admin_bar_menu', 'siteintelix_register_admin_bar_link'", start);
	const adminBarFunction = main.slice(start, end);

	assert.ok(start >= 0 && end > start);
	assert.match(adminBarFunction, /id'\s*=>\s*'siteintelix'/);
	assert.match(adminBarFunction, /class="ab-icon dashicons dashicons-chart-area"/);
	assert.match(adminBarFunction, /aria-hidden="true"/);
	assert.doesNotMatch(adminBarFunction, /<svg|currentColor/);
	assert.match(adminBarFunction, /<span class="ab-label">/);
	assert.match(adminBarFunction, /esc_html__\(\s*'SiteIntelix'/);
	assert.match(adminBarFunction, /'siteintelix-debug-log'/);
	assert.match(adminBarFunction, /'siteintelix-email-log'/);
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

test('module registry memoizes request state and invalidates it after saves', async () => {
	const modules = await read('includes/class-siteintelix-modules.php');

	assert.match(modules, /private static \$all_cache/);
	assert.match(modules, /private static \$enabled_cache/);
	assert.match(modules, /private static \$enabled_lookup_cache/);
	assert.match(modules, /public static function reset_cache\s*\(/);
	assert.match(modules, /self::\$enabled_lookup_cache/);
	assert.match(modules, /update_option[\s\S]*self::reset_cache\s*\(/);
});

test('User Switcher is a complete conditionally loaded SiteIntelix module', async () => {
	const files = [
		'includes/modules/user-switcher/class-siteintelix-user-switcher-module.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-permissions.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-settings.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-logger.php',
		'includes/modules/user-switcher/class-siteintelix-user-switcher-activator.php',
		'includes/modules/user-switcher/views/settings.php',
		'includes/modules/user-switcher/views/logs.php',
		'includes/modules/user-switcher/assets/user-switcher.css',
		'docs/user-switcher.md',
	];

	for (const file of files) {
		await access(path.join(root, file), constants.F_OK);
	}

	const [main, registry, modulesPage, sharedSettingsPage, module, session, permissions, actions, toolbar, settings, logger, activator, settingsView, logsView, uninstall, docs] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
		read('admin/views/settings-page.php'),
		read(files[0]),
		read(files[1]),
		read(files[2]),
		read(files[3]),
		read(files[4]),
		read(files[5]),
		read(files[6]),
		read(files[7]),
		read(files[8]),
		read(files[9]),
		read('uninstall.php'),
		read(files[11]),
	]);

	assert.match(registry, /'user_switcher'\s*=>\s*array/);
	assert.match(registry, /'slug'\s*=>\s*'user-switcher'/);
	assert.match(registry, /'title'[\s\S]*User Switcher/);
	assert.match(registry, /Temporarily access the site as another user/);
	assert.match(registry, /'default'\s*=>\s*false/);
	assert.match(registry, /dashicons-admin-users/);
	assert.match(main, /is_enabled\(\s*'user_switcher'\s*\)[\s\S]*class-siteintelix-user-switcher-module\.php/);
	assert.match(main, /SITEINTELIX_User_Switcher_Module::init\(\)/);
	assert.match(main, /SITEINTELIX_User_Switcher_Activator::activate\(\)/);
	assert.match(main, /SITEINTELIX_User_Switcher_Activator::deactivate\(\)/);
	assert.match(settings, /add_submenu_page\([\s\S]*'siteintelix-user-switcher'[\s\S]*render_page/);
	assert.match(settings, /public static function render_page\s*\(/);
	assert.match(settings, /add_action\(\s*'siteintelix_render_module_settings_sections',\s*array\(\s*__CLASS__,\s*'render_section'\s*\)/s);
	assert.match(settings, /'page'\s*=>\s*'siteintelix-settings'[\s\S]*'tab'\s*=>\s*'user_switcher'/s);
	assert.doesNotMatch(settings, /sitx-user-switcher-tabs|add_query_arg\(\s*'view',\s*'settings'/s);
	assert.match(settings, /require SITEINTELIX_PLUGIN_DIR \. 'includes\/modules\/user-switcher\/views\/logs\.php'/);
	assert.match(modulesPage, /'user_switcher'\s*=>\s*'siteintelix-user-switcher-settings'/);
	assert.match(logger, /'page'\s*=>\s*'siteintelix-user-switcher'/);
	assert.match(settings, /id="siteintelix-user-switcher-settings"/);
	assert.match(module, /siteintelix_page_siteintelix-user-switcher/);
	assert.match(module, /siteintelix_page_siteintelix-user-switcher[\s\S]*siteintelix_page_siteintelix-settings/);
	assert.doesNotMatch(sharedSettingsPage, /if\s*\(\s*'user_switcher'\s*===\s*\$siteintelix_module_id\s*\)\s*\{\s*continue;/s);
	assert.doesNotMatch(logsView + logger, /'page'\s*=>\s*'siteintelix-settings'|name="page"\s+value="siteintelix-settings"/);

	assert.match(module, /class SITEINTELIX_User_Switcher_Module/);
	assert.match(module, /init_recovery/);
	assert.match(actions, /user_row_actions/);
	assert.match(session, /WP_Session_Tokens/);
	assert.match(session, /wp_set_current_user/);
	assert.match(session, /wp_set_auth_cookie/);
	assert.match(session, /wp_clear_auth_cookie/);
	assert.match(session, /wp_get_session_token/);
	assert.match(session, /COOKIE_PREFIX[\s\S]*get_current_blog_id/);
	assert.match(session, /TARGET_INDEX_PREFIX/);
	assert.match(session, /START_LOCK_PREFIX/);
	assert.match(session, /wp_set_auth_cookie\(\s*\$original_user->ID,\s*false,\s*is_ssl\(\),\s*\$session\['restore_token'\]/);
	assert.match(session, /hash_hmac/);
	assert.match(session, /hash_equals/);
	assert.match(session, /wp_salt\(\s*'auth'\s*\)/);
	assert.match(session, /httponly[\s\S]*true/);
	assert.match(session, /samesite[\s\S]*Lax/);
	assert.match(session, /is_ssl\(\)/);
	assert.match(session, /siteintelix_user_switcher_before_switch/);
	assert.match(session, /siteintelix_user_switcher_after_switch/);
	assert.match(session, /siteintelix_user_switcher_before_restore/);
	assert.match(session, /siteintelix_user_switcher_after_restore/);
	assert.match(session, /private static function read_session[\s\S]*if\s*\(\s*!\s*self::has_cookie\(\)\s*\)\s*\{\s*return false;/);
	assert.doesNotMatch(session + actions + permissions, /wp_set_password|user_pass|password_reset|retrieve_password/);

	assert.match(permissions, /siteintelix_switch_users/);
	assert.match(permissions, /siteintelix_user_switcher_can_switch/);
	assert.match(permissions, /siteintelix_user_switcher_protected_roles/);
	assert.match(permissions, /is_super_admin/);
	assert.match(permissions, /is_user_member_of_blog/);
	assert.match(actions, /admin_post_siteintelix_user_switcher_switch/);
	assert.match(actions, /admin_post_siteintelix_user_switcher_restore/);
	assert.match(actions, /add_action\(\s*'admin_init',\s*array\(\s*__CLASS__,\s*'maybe_dispatch_restore'\s*\),\s*0\s*\)/);
	assert.match(actions, /public static function maybe_dispatch_restore/);
	assert.match(actions, /check_admin_referer/);
	assert.match(actions, /edit_user_profile/);
	assert.match(toolbar, /admin_bar_menu/);
	assert.match(toolbar, /add_filter\(\s*'show_admin_bar'[\s\S]*PHP_INT_MAX/);
	assert.match(toolbar, /public static function force_admin_bar/);
	assert.match(toolbar, /Return to/);
	assert.match(toolbar, /admin_notices/);

	assert.match(settings, /siteintelix_user_switcher_settings/);
	assert.match(session, /siteintelix_user_switcher_session_duration/);
	assert.match(settings, /siteintelix_user_switcher_switch_redirect/);
	assert.match(settings, /siteintelix_user_switcher_return_redirect/);
	assert.match(settingsView, /allowed_operator_roles/);
	assert.match(settingsView, /allowed_target_roles/);
	assert.match(settingsView, /allow_administrators/);
	assert.match(settingsView, /session_duration/);
	assert.match(settingsView, /retention_days/);

	assert.match(logger, /siteintelix_user_switch_logs/);
	assert.match(logger, /dbDelta/);
	assert.match(logger, /siteintelix_user_switcher_retention/);
	assert.match(logger, /siteintelix_user_switcher_log_retention/);
	assert.match(logger, /\$wpdb->prepare/);
	assert.match(logsView, /Delete selected/);
	assert.match(logsView, /Clear all logs/);
	assert.match(logsView, /Administrator/);
	assert.match(logsView, /Target user/);
	assert.match(logsView, /Duration/);
	assert.match(permissions, /add_cap\(\s*self::CAPABILITY/);
	assert.match(uninstall, /siteintelix_user_switcher_settings/);
	assert.match(uninstall, /siteintelix_user_switch_logs/);
	assert.match(uninstall, /siteintelix_user_switcher_retention/);
	assert.match(docs, /siteintelix_switch_users/);
	assert.match(docs, /siteintelix_user_switcher_can_switch/);
});

test('admin-only module files are guarded from ordinary frontend requests', async () => {
	const main = await read('siteintelix.php');

	assert.match(main, /function siteintelix_should_load_admin_modules\s*\(/);
	assert.match(main, /if \( siteintelix_should_load_admin_modules\(\) \)[\s\S]*class-siteintelix-cron-events-module\.php/);
	assert.match(main, /if \( siteintelix_should_load_admin_modules\(\) \)[\s\S]*class-siteintelix-server-diagnostics-module\.php/);
	assert.match(main, /class-siteintelix-email-log-module\.php/);
	assert.match(main, /class-siteintelix-smtp-module\.php/);
	assert.match(main, /class-siteintelix-coming-soon-module\.php/);
});

test('Email Log capture starts before plugin lifecycle hooks and remains idempotent', async () => {
	const [main, emailLog] = await Promise.all([
		read('siteintelix.php'),
		read('includes/modules/email-log/class-siteintelix-email-log-module.php'),
	]);

	const earlyBootstrapCall = main.indexOf('siteintelix_boot_early_email_capture();');
	const pluginsLoadedHook = main.indexOf("add_action( 'plugins_loaded', 'siteintelix_load_includes' );");
	const initBootHook = main.indexOf("add_action( 'init', 'siteintelix_boot_enabled_modules', 20 );");

	assert.ok(earlyBootstrapCall >= 0, 'missing early Email Log capture bootstrap');
	assert.ok(earlyBootstrapCall < pluginsLoadedHook, 'email capture must start before plugins_loaded callbacks');
	assert.ok(earlyBootstrapCall < initBootHook, 'email capture must start before the normal priority-20 module boot');
	assert.match(main, /get_option\(\s*SITEINTELIX_MODULES_OPTION,\s*null\s*\)/);
	assert.match(main, /in_array\(\s*'email_log'/);
	assert.match(main, /SITEINTELIX_Email_Log_Module::register_capture_hooks\(\)/);

	assert.match(emailLog, /private static \$capture_hooks_registered\s*=\s*false/);
	assert.match(emailLog, /public static function register_capture_hooks\s*\(/);
	assert.match(emailLog, /add_action\(\s*'wp_mail_succeeded',\s*array\(\s*__CLASS__,\s*'log_success'\s*\)/s);
	assert.match(emailLog, /add_action\(\s*'wp_mail_failed',\s*array\(\s*__CLASS__,\s*'log_failure'\s*\)/s);
	assert.match(emailLog, /if\s*\(\s*self::\$capture_hooks_registered\s*\)\s*\{\s*return;/s);
	assert.match(emailLog, /public static function init\s*\(\)\s*\{\s*self::register_capture_hooks\(\);/s);
	assert.doesNotMatch(main + emailLog, /Tutor LMS|tutor_retrieve_password|tutor_action_tutor_retrieve_password/);
});

test('Overview health is non-blocking and its export payload is redacted', async () => {
	const [systemInfo, overview, main] = await Promise.all([
		read('includes/class-siteintelix-system-info.php'),
		read('admin/views/admin-page.php'),
		read('siteintelix.php'),
	]);

	const environmentMethod = systemInfo.slice(systemInfo.indexOf('public static function get_environment_info'), systemInfo.indexOf('// Helpers'));
	assert.doesNotMatch(environmentMethod, /wp_remote_get\s*\(/);
	assert.match(environmentMethod, /get_cached_rest_api_status/);
	assert.match(systemInfo, /public static function get_redacted_export\s*\(/);
	assert.match(overview, /\$siteintelix_export_info\s*=\s*SITEINTELIX_System_Info::get_redacted_export/);
	assert.match(overview, /wp_json_encode\(\s*\$siteintelix_export_info/);
	assert.doesNotMatch(overview, /class="siteintelix-health-pill[^\"]*"\s+title=/);
	assert.match(overview, /aria-describedby=/);
	assert.match(main, /siteintelix-overview\.js/);
});

test('Server Diagnostics uses cached async refresh and compact JS translations', async () => {
	const [main, diagnostics, diagnosticsJs] = await Promise.all([
		read('siteintelix.php'),
		read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php'),
		read('assets/admin/js/siteintelix-server-diagnostics.js'),
	]);

	assert.doesNotMatch(main, /for \( \$siteintelix_count = 0; \$siteintelix_count <= 200;/);
	assert.match(main, /array\(\s*'wp-i18n'\s*\)/);
	assert.match(main, /wp_set_script_translations\s*\(/);
	assert.match(diagnostics, /siteintelix_server_diagnostics_cache_/);
	assert.match(diagnostics, /wp_ajax_siteintelix_refresh_server_diagnostics/);
	assert.match(diagnostics, /data-sitx-diag-refresh/);
	assert.match(diagnosticsJs, /siteintelix_refresh_server_diagnostics/);
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

test('Modern Debug Log gives source paths the maximum available summary width', async () => {
	const [modern, css] = await Promise.all([
		read('admin/views/debug-log-page-modern.php'),
		read('assets/admin/css/siteintelix-debug-log.css'),
	]);
	const finalPathLayer = css.slice(css.lastIndexOf('Modern Debug Log maximum-width source paths'));

	assert.match(modern, /class="sitx-log-path[^"]*"[^>]*title=/);
	assert.match(finalPathLayer, /\.sitx-log-card__body[\s\S]*min-width:\s*0/);
	assert.match(finalPathLayer, /\.sitx-log-meta[\s\S]*width:\s*100%/);
	assert.match(finalPathLayer, /\.sitx-log-path[\s\S]*flex:\s*1 1 auto/);
	assert.match(finalPathLayer, /\.sitx-log-path[\s\S]*max-width:\s*none/);
	assert.doesNotMatch(finalPathLayer, /max-width:\s*min\(520px/);
});

test('Database Manager dashboard uses bounded server-rendered controls', async () => {
	const php = await read('includes/modules/database-manager/class-siteintelix-database-manager-module.php');

	for (const parameter of ['db_search', 'db_orderby', 'db_order', 'db_per_page', 'db_page']) {
		assert.match(php, new RegExp(parameter));
	}
	assert.match(php, /array\(\s*15,\s*30,\s*50\s*\)/);
	assert.match(php, /array\(\s*'name',\s*'rows',\s*'data',\s*'index'\s*\)/);
	assert.match(php, /array_slice\s*\(/);
	assert.match(php, /min\(\s*50/);
	assert.doesNotMatch(php, /wp_ajax_siteintelix_db_dashboard/);
});

test('Database Manager dashboard renders the approved metrics, toolbar, actions, and pagination', async () => {
	const [php, css] = await Promise.all([
		read('includes/modules/database-manager/class-siteintelix-database-manager-module.php'),
		read('assets/admin/css/siteintelix-admin.css'),
	]);
	const finalVisualLayerIndex = css.lastIndexOf('17. Cascade-final visual system');
	const finalDbLayerIndex = css.lastIndexOf('Database Manager screenshot-matched dashboard');
	const finalDbLayer = css.slice(finalDbLayerIndex);

	assert.ok(finalDbLayerIndex > finalVisualLayerIndex, 'Database Manager dashboard layer must remain cascade-final');

	for (const marker of [
		'sitx-db-stat-icon',
		'sitx-db-dashboard-card',
		'sitx-db-dashboard-toolbar',
		'sitx-db-dashboard-search',
		'sitx-db-dashboard-actions',
		'sitx-db-dashboard-footer',
	]) {
		assert.match(php, new RegExp(marker));
		assert.match(finalDbLayer, new RegExp(`\\.${marker}`));
	}
	assert.match(php, /<th[^>]*>.*Actions/s);
	assert.match(php, /<details class="sitx-db-dashboard-actions"/);
	assert.match(php, /Browse rows/);
	assert.match(php, /paginate_links\s*\(/);
});

test('Database Manager tabs align to the same centered container as dashboard sections', async () => {
	const css = await read('assets/admin/css/siteintelix-admin.css');
	const finalDbLayer = css.slice(css.lastIndexOf('Database Manager screenshot-matched dashboard'));
	const headerRule = finalDbLayer.match(/\.sitx-db-manager \.sitx-db-header\s*\{([^}]*)\}/)?.[1] || '';

	assert.match(headerRule, /box-sizing:\s*border-box/);
	assert.match(headerRule, /margin-inline:\s*auto/);
	assert.match(headerRule, /max-width:\s*1440px/);
	assert.match(headerRule, /width:\s*100%/);
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

test('Server Diagnostics exposes the compact server-rendered dashboard controls', async () => {
	const diagnostics = await read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php');

	for (const contract of [
		'sitx-serverdiag-score-ring',
		'sitx-serverdiag-stat--total',
		'data-sitx-diag-hide-passed',
		'data-sitx-diag-sort',
		'data-sitx-diag-default-open',
		'sitx-serverdiag-category-progress',
		'sitx-serverdiag-actions-menu',
		'<table',
		'<thead>',
		'<th scope="col"',
	]) {
		assert.ok(diagnostics.includes(contract), `missing ${contract}`);
	}

	assert.doesNotMatch(diagnostics, /data-sitx-diag-(?:fix|install)/);
});

test('Server Diagnostics enhancement preserves its lightweight backend boundaries', async () => {
	const [diagnostics, diagnosticsJs] = await Promise.all([
		read('includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php'),
		read('assets/admin/js/siteintelix-server-diagnostics.js'),
	]);

	assert.match(diagnostics, /wp_ajax_siteintelix_refresh_server_diagnostics/);
	assert.match(diagnostics, /check_ajax_referer\(\s*'siteintelix_refresh_server_diagnostics'/);
	assert.match(diagnostics, /check_admin_referer\(\s*'siteintelix_server_diag_export'/);
	assert.match(diagnostics, /self::redact_report/);
	assert.match(diagnostics, /self::get_cached_section/);
	assert.doesNotMatch(diagnosticsJs, /\b(?:jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(diagnosticsJs, /fetch\([^)]*row/i);
});

test('Server Diagnostics uses dedicated local assets and only the WordPress i18n runtime', async () => {
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
		/wp_enqueue_script\(\s*(["'])siteintelix-server-diagnostics-script\1\s*,\s*SITEINTELIX_PLUGIN_URL\s*\.\s*(["'])assets\/admin\/js\/siteintelix-server-diagnostics\.js\2\s*,\s*array\(\s*(["'])wp-i18n\3\s*\)/
	);
	assert.doesNotMatch(diagnosticsJs, /\b(jQuery|React|Vue|axios)\b/);
	assert.doesNotMatch(diagnosticsCss, /@import\s+url|fonts\.googleapis|cdnjs|unpkg|jsdelivr/);
	assert.match(diagnosticsCss, /prefers-reduced-motion/);
	assert.match(diagnosticsCss, /:focus-visible/);
	for (const contract of [
		/\.sitx-serverdiag-score-ring/,
		/\.sitx-serverdiag-checks-table/,
		/\.sitx-serverdiag-actions-menu/,
		/\.sitx-serverdiag-category-progress/,
		/@media\s*\(max-width:\s*782px\)/,
	]) {
		assert.match(diagnosticsCss, contract);
	}
});

test('Server Diagnostics keeps the category chevron centered in its final grid column', async () => {
	const diagnosticsCss = await read('assets/admin/css/siteintelix-server-diagnostics.css');

	assert.match(
		diagnosticsCss,
		/\.sitx-serverdiag-section__toggle\s*\{[\s\S]*?grid-template-columns:\s*40px\s+minmax\(180px,\s*1fr\)\s+auto\s+minmax\(220px,\s*auto\)\s+64px\s+20px;/
	);
	assert.match(
		diagnosticsCss,
		/\.sitx-serverdiag-section__toggle\s*>\s*\.dashicons-arrow-down-alt2\s*\{[\s\S]*?justify-self:\s*center;/
	);
});

test('Custom CSS & JS is an independent conditionally loaded module', async () => {
	const requiredFiles = [
		'includes/modules/custom-code/class-siteintelix-custom-code-module.php',
		'includes/modules/custom-code/class-siteintelix-custom-code-repository.php',
		'includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php',
		'includes/modules/custom-code/class-siteintelix-custom-code-runner.php',
		'includes/modules/custom-code/class-siteintelix-custom-code-admin.php',
		'includes/modules/custom-code/views/list.php',
		'includes/modules/custom-code/views/editor.php',
		'includes/modules/custom-code/assets/custom-code.css',
		'includes/modules/custom-code/assets/custom-code.js',
	];
	const [main, registry, modulesPage] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
	]);

	for (const file of requiredFiles) {
		await access(path.join(root, file), constants.F_OK);
	}

	assert.match(registry, /'custom_code'\s*=>\s*array/);
	assert.match(registry, /Custom CSS & JS/);
	assert.match(registry, /'default'\s*=>\s*false/);
	assert.match(main, /is_enabled\(\s*'custom_code'\s*\)[\s\S]*class-siteintelix-custom-code-module\.php/);
	assert.match(main, /SITEINTELIX_Custom_Code_Module::init\(\)/);
	assert.match(main, /SITEINTELIX_Custom_Code_Module::activate\(\)/);
	assert.match(modulesPage, /'custom_code'\s*=>\s*admin_url\(\s*'admin\.php\?page=siteintelix-custom-code'/);
});

test('Code Snippets is an independent early-runtime module', async () => {
	const requiredFiles = [
		'includes/modules/code-snippets/class-siteintelix-code-snippets-module.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-repository.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-validator.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-context.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-recovery.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-runner.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-admin.php',
		'includes/modules/code-snippets/class-siteintelix-snippets-transfer.php',
		'includes/modules/code-snippets/views/list.php',
		'includes/modules/code-snippets/views/editor.php',
		'includes/modules/code-snippets/views/run-once-confirm.php',
		'includes/modules/code-snippets/views/import.php',
		'includes/modules/code-snippets/assets/code-snippets.css',
		'includes/modules/code-snippets/assets/code-snippets.js',
	];
	const [main, registry, modulesPage] = await Promise.all([
		read('siteintelix.php'),
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/modules-page.php'),
	]);
	await Promise.all(requiredFiles.map((file) => access(path.join(root, file), constants.F_OK)));
	assert.match(registry, /'code_snippets'\s*=>\s*array/);
	assert.match(registry, /'title'\s*=>[\s\S]*Code Snippets/);
	assert.match(registry, /'default'\s*=>\s*false/);
	assert.match(main, /SITEINTELIX_Modules::is_enabled\(\s*'code_snippets'\s*\)/);
	assert.match(main, /class-siteintelix-code-snippets-module\.php/);
	assert.match(main, /SITEINTELIX_Code_Snippets_Module::init\(\)/);
	assert.match(main, /SITEINTELIX_Code_Snippets_Module::activate\(\)/);
	const securityLoad = main.indexOf("require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-security.php';");
	const earlyRuntime = main.indexOf('siteintelix_boot_early_code_snippets();');
	assert.ok(
		securityLoad >= 0 && securityLoad < earlyRuntime,
		'the security policy must load before the early snippets runtime is registered',
	);
	assert.match(modulesPage, /'code_snippets'\s*=>\s*admin_url\(\s*'admin\.php\?page=siteintelix-code-snippets'/);
});

test('Code Snippets uses native PHP syntax validation for mixed templates', async () => {
	const [validator, editor] = await Promise.all([
		read('includes/modules/code-snippets/class-siteintelix-snippets-validator.php'),
		read('includes/modules/code-snippets/views/editor.php'),
	]);

	assert.match(validator, /token_get_all\(\s*\$source,\s*TOKEN_PARSE\s*\)/);
	assert.doesNotMatch(validator, /contains_php_tags|siteintelix_snippet_php_tags|Enter PHP without opening or closing tags/);
	assert.doesNotMatch(editor, /Do not include opening or closing PHP tags/);
	assert.match(editor, /Snippets start in PHP mode[\s\S]*close and reopen PHP[\s\S]*Syntax is checked before saving/);
});

test('custom-code modules keep independent opt-in uninstall cleanup and local accessible assets', async () => {
	const [customModule, snippetsModule, uninstall, customJs, snippetsJs] = await Promise.all([
		read('includes/modules/custom-code/class-siteintelix-custom-code-module.php'),
		read('includes/modules/code-snippets/class-siteintelix-code-snippets-module.php'),
		read('uninstall.php'),
		read('includes/modules/custom-code/assets/custom-code.js'),
		read('includes/modules/code-snippets/assets/code-snippets.js'),
	]);
	assert.match(customModule, /siteintelix_delete_custom_css_js_on_uninstall/);
	assert.match(snippetsModule, /siteintelix_delete_code_snippets_on_uninstall/);
	assert.match(uninstall, /if\s*\(\s*\$siteintelix_delete_custom_css_js\s*\)/);
	assert.match(uninstall, /if\s*\(\s*\$siteintelix_delete_code_snippets\s*\)/);
	assert.match(uninstall, /siteintelix_custom_code/);
	assert.match(uninstall, /siteintelix_snippets/);
	assert.doesNotMatch(customJs + snippetsJs, /window\.(?:confirm|prompt)\s*\(/);
});

test('custom-code editor routes stay out of the SiteIntelix sidebar', async () => {
	const [customAdmin, snippetsAdmin] = await Promise.all([
		read('includes/modules/custom-code/class-siteintelix-custom-code-admin.php'),
		read('includes/modules/code-snippets/class-siteintelix-snippets-admin.php'),
	]);
	assert.match(customAdmin, /add_submenu_page\(\s*null,\s*__\(\s*'Add Custom Code'/);
	assert.match(snippetsAdmin, /add_submenu_page\(\s*null,\s*__\(\s*'Add New Snippet'/);
	assert.match(customAdmin, /'siteintelix-custom-code-new'/);
	assert.match(snippetsAdmin, /'siteintelix-code-snippets-new'/);
});

test('custom-code retention settings belong to their module tabs', async () => {
	const [registry, settingsPage, main, uninstall, customModule, snippetsModule] = await Promise.all([
		read('includes/class-siteintelix-modules.php'),
		read('admin/views/settings-page.php'),
		read('siteintelix.php'),
		read('uninstall.php'),
		read('includes/modules/custom-code/class-siteintelix-custom-code-module.php'),
		read('includes/modules/code-snippets/class-siteintelix-code-snippets-module.php'),
	]);
	assert.match(registry, /'custom_code'[\s\S]*?'settings'\s*=>\s*array\([\s\S]*?siteintelix_save_custom_css_js_settings/);
	assert.match(registry, /'code_snippets'[\s\S]*?'settings'\s*=>\s*array\([\s\S]*?siteintelix_save_code_snippets_settings/);
	assert.match(customModule, /siteintelix_render_module_settings_sections/);
	assert.match(customModule, /siteintelix_save_custom_css_js_settings/);
	assert.match(snippetsModule, /siteintelix_render_module_settings_sections/);
	assert.match(snippetsModule, /siteintelix_save_code_snippets_settings/);
	assert.doesNotMatch(settingsPage, /id="siteintelix-custom-code-retention"/);
	assert.doesNotMatch(main, /function siteintelix_save_custom_code_retention/);
	assert.match(uninstall, /siteintelix_delete_custom_css_js_on_uninstall/);
	assert.match(uninstall, /siteintelix_delete_code_snippets_on_uninstall/);
});

test('custom-code management pages use the SiteIntelix management layout', async () => {
	const [customList, customCss, snippetsList, snippetsCss] = await Promise.all([
		read('includes/modules/custom-code/views/list.php'),
		read('includes/modules/custom-code/assets/custom-code.css'),
		read('includes/modules/code-snippets/views/list.php'),
		read('includes/modules/code-snippets/assets/code-snippets.css'),
	]);
	for (const view of [customList, snippetsList]) {
		assert.match(view, /'actions'\s*=>\s*array/);
		assert.match(view, /sitx-code-summary/);
		assert.match(view, /sitx-code-manager/);
		assert.match(view, /sitx-code-toolbar/);
		assert.match(view, /si-table-wrap/);
		assert.match(view, /si-table/);
		assert.match(view, /si-empty-state/);
		assert.match(view, /sitx-badge/);
		assert.doesNotMatch(view, /\|\s*<\/span>/);
	}
	assert.match(customList, /sitx-code-bulkbar/);
	assert.match(snippetsList, /Recently deactivated/);
	assert.match(customCss + snippetsCss, /@media\s*\(max-width:\s*782px\)/);
	assert.match(customCss + snippetsCss, /\.sitx-code-manager/);
});

test('custom-code bulk Apply controls are explicit confirmed submit buttons', async () => {
	const [snippets, customCode] = await Promise.all([
		read('includes/modules/code-snippets/views/list.php'),
		read('includes/modules/custom-code/views/list.php'),
	]);

	for (const view of [snippets, customCode]) {
		assert.match(view, /<button\b[^>]*type="submit"[^>]*data-siteintelix-confirm=/);
	}
});

test('custom-code editors use the focused SiteIntelix workspace', async () => {
	const [customEditor, customCss, snippetsEditor, snippetsCss] = await Promise.all([
		read('includes/modules/custom-code/views/editor.php'),
		read('includes/modules/custom-code/assets/custom-code.css'),
		read('includes/modules/code-snippets/views/editor.php'),
		read('includes/modules/code-snippets/assets/code-snippets.css'),
	]);

	for (const editor of [customEditor, snippetsEditor]) {
		assert.match(editor, /'actions'\s*=>\s*array/);
		assert.match(editor, /sitx-code-editor-workspace/);
		assert.match(editor, /sitx-code-editor-main\s+si-card/);
		assert.match(editor, /sitx-code-editor-settings\s+si-card/);
		assert.match(editor, /sitx-code-editor-actions\s+si-card/);
		assert.match(editor, /si-button\s+si-button--primary/);
	}

	for (const css of [customCss, snippetsCss]) {
		assert.match(css, /\.sitx-code-editor-workspace/);
		assert.match(css, /\.sitx-code-editor-settings/);
		assert.match(css, /position:\s*sticky/);
		assert.match(css, /@media\s*\(min-width:\s*901px\)\s*and\s*\(min-height:\s*900px\)[\s\S]*?\.sitx-code-editor-sidebar[\s\S]*?position:\s*sticky/);
		assert.match(css, /@media\s*\(max-width:\s*900px\)/);
	}
});

test('snippet import and export stay hidden while transfer support remains dormant', async () => {
	const [snippetsList, snippetsAdmin, snippetsModule] = await Promise.all([
		read('includes/modules/code-snippets/views/list.php'),
		read('includes/modules/code-snippets/class-siteintelix-snippets-admin.php'),
		read('includes/modules/code-snippets/class-siteintelix-code-snippets-module.php'),
	]);

	assert.doesNotMatch(snippetsList, /siteintelix-code-snippets-import|Export selected|sitx-code-export/);
	assert.doesNotMatch(snippetsAdmin, /admin_post_siteintelix_snippet_(?:import|export)|render_import|send_export|invalid_import_upload/);
	assert.match(snippetsModule, /class-siteintelix-snippets-transfer\.php/);
});
