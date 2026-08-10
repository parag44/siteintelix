import fs from 'node:fs';
import path from 'node:path';

const plugin = path.resolve(import.meta.dirname, '..');
const read = (file) => fs.readFileSync(path.join(plugin, file), 'utf8');
const main = read('siteintelix.php');
const registry = read('includes/class-siteintelix-modules.php');
const security = read('includes/class-siteintelix-security.php');
const moduleFile = read('includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-module.php');
const packageFile = read('includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-package.php');
const switcher = read('includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-switcher.php');
const storage = read('includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-storage.php');
const js = read('includes/modules/plugin-version-switcher/assets/version-switcher.js');

let passed = 0;
let failed = 0;
function test(name, condition) {
	if (condition) { passed++; console.log(`PASS: ${name}`); }
	else { failed++; console.error(`FAIL: ${name}`); }
}

test('module is optional and disabled by default', registry.includes("'plugin_version_switcher'") && registry.includes("'default'     => false"));
test('module classes load only when enabled', main.includes("is_enabled( 'plugin_version_switcher' )") && main.includes('class-siteintelix-version-switcher-module.php'));
test('heavy package and upgrader classes load lazily for authorized users', !main.includes('class-siteintelix-version-switcher-package.php') && moduleFile.includes('private static function load_runtime()') && moduleFile.includes("if ( ! self::can_switch() )") && moduleFile.includes('class-siteintelix-version-switcher-package.php'));
test('frontend toolbar loads runtime before using switcher helpers', moduleFile.indexOf('private static function load_runtime()') < moduleFile.indexOf('private static function toolbar_groups()') && moduleFile.match(/private static function toolbar_groups\(\)[\s\S]{0,180}self::load_runtime\(\)/));
test('module is governed by global authorization', security.includes("'plugin_version_switcher'"));
test('actions require update_plugins and global-tool policy', moduleFile.includes("current_user_can( 'update_plugins' )") && moduleFile.includes('can_manage_global_tools'));
test('upload switch and delete use dedicated nonces', moduleFile.includes("authorize( 'siteintelix_version_upload' )") && moduleFile.includes("check_admin_referer( 'siteintelix_version_switch_'") && moduleFile.includes("check_admin_referer( 'siteintelix_version_delete_'"));
test('archive traversal absolute paths and symlinks are rejected', packageFile.includes("'..' === $segment") && packageFile.includes("'/' === substr( $name, 0, 1 )") && packageFile.includes('is_symlink_entry'));
test('archive inspection requires plugin name and version', packageFile.includes("'' !== $headers['name']") && packageFile.includes("'' !== $headers['version']"));
test('same slug cannot silently change primary plugin identity', packageFile.includes('siteintelix_version_plugin_identity_conflict') && packageFile.includes("$stored['primary_plugin_file'] !== $inspection['primary_plugin_file']"));
test('vault uses random names and SHA-256', packageFile.includes("bin2hex( random_bytes( 20 ) )") && packageFile.includes("hash_file( 'sha256'"));
test('vault supports constant and filter configuration', storage.includes('SITEINTELIX_VERSION_VAULT_DIR') && storage.includes('siteintelix_version_vault_directory'));
test('vault creates Apache IIS and index protections', storage.includes("'.htaccess'") && storage.includes("'web.config'") && storage.includes("'index.php'"));
test('manifest never exposes a public download route', !moduleFile.includes('download_package') && !moduleFile.includes('wp_ajax_nopriv'));
test('switch recalculates checksum and archive identity', switcher.includes("hash_file( 'sha256'") && switcher.includes('identity_key'));
test('switch uses Plugin_Upgrader overwrite flow', switcher.includes('new Plugin_Upgrader') && switcher.includes("'overwrite_package'  => true"));
test('switch records and restores site and network activation', switcher.includes("'site_active'") && switcher.includes("'network_active'") && switcher.includes("update_site_option( 'active_sitewide_plugins'"));
test('switch creates backup before upgrader and verifies after install', switcher.indexOf('create_backup') < switcher.indexOf('new Plugin_Upgrader') && switcher.includes('get_plugin_data') && switcher.includes('version_verification_failed'));
test('failed switch invokes recovery and releases lock', switcher.includes('recover_failure') && switcher.includes('release_lock') && switcher.includes('siteintelix_plugin_version_switch_failed'));
test('success clears plugin cache without global cache or opcache flush', switcher.includes('wp_clean_plugins_cache( true )') && !switcher.includes('wp_cache_flush') && !switcher.includes('opcache_reset'));
test('redirects are validated and safe', moduleFile.includes('wp_validate_redirect') && moduleFile.includes('wp_safe_redirect'));
test('admin bar is capability gated and uses POST forms', moduleFile.includes('register_admin_bar') && moduleFile.includes('siteintelix-version-toolbar-form') && moduleFile.includes('method="post"'));
test('production requires explicit confirmation', moduleFile.includes("'production' === wp_get_environment_type()") && moduleFile.includes("'yes' !== $confirmed") && js.includes("production.value = 'yes'"));
test('SiteIntelix self-switching is blocked', switcher.includes("'siteintelix' === strtolower") && switcher.includes('siteintelix_version_self_switch'));
test('history is bounded and excludes path fields', storage.includes('HISTORY_LIMIT') && storage.includes('array_slice') && !storage.match(/add_history[\s\S]{0,2500}stored_filename/));
test('package deletion validates manifest ID and vault containment', storage.includes('delete_package') && storage.includes('valid_package_id') && storage.includes('package_path'));
test('switch lifecycle hooks are present', switcher.includes('siteintelix_before_plugin_version_switch') && switcher.includes('siteintelix_after_plugin_version_switch') && switcher.includes('siteintelix_plugin_version_switch_failed'));

console.log(`\n${passed} passed, ${failed} failed`);
process.exitCode = failed ? 1 : 0;
