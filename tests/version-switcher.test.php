<?php
/** Dependency-free behavioral tests for the Version Switcher vault and ZIP inspector. */

$test_root = realpath( sys_get_temp_dir() ) . '/siteintelix-version-test-' . bin2hex( random_bytes( 6 ) );
mkdir( $test_root, 0770, true );

define( 'ABSPATH', $test_root . '/wordpress/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'SITEINTELIX_VERSION_VAULT_DIR', $test_root . '/vault' );
mkdir( WP_PLUGIN_DIR, 0770, true );

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function add( $code, $message ) { $this->code = $code; $this->message .= ' ' . $message; }
}

$test_options = array();
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function __( $text ) { return $text; }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function untrailingslashit( $path ) { return rtrim( $path, '/\\' ); }
function trailingslashit( $path ) { return untrailingslashit( $path ) . '/'; }
function apply_filters( $hook, $value ) { return $value; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0770, true ); }
function get_option( $key, $default = false ) { global $test_options; return array_key_exists( $key, $test_options ) ? $test_options[ $key ] : $default; }
function update_option( $key, $value ) { global $test_options; $test_options[ $key ] = $value; return true; }
function add_option( $key, $value ) { global $test_options; if ( array_key_exists( $key, $test_options ) ) { return false; } $test_options[ $key ] = $value; return true; }
function delete_option( $key ) { global $test_options; unset( $test_options[ $key ] ); return true; }
function is_multisite() { return false; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_file_name( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) ); }
function wp_basename( $value ) { return basename( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_generate_uuid4() { return sprintf( '%08x-%04x-4%03x-a%03x-%012x', random_int( 0, 0xffffffff ), random_int( 0, 0xffff ), random_int( 0, 0xfff ), random_int( 0, 0xfff ), random_int( 0, 0xffffffffffff ) ); }
function wp_generate_password( $length ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_max_upload_size() { return 50 * 1024 * 1024; }
function get_current_user_id() { return 7; }
function get_bloginfo() { return '7.0.2'; }
function admin_url( $path = '' ) { return 'http://example.test/wp-admin/' . ltrim( $path, '/' ); }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_SANITIZE_URL ); }
function wp_validate_redirect( $url, $fallback ) { $host = parse_url( $url, PHP_URL_HOST ); return $host && 'example.test' !== $host ? $fallback : $url; }
$test_capabilities = array( 'update_plugins' => true );
function current_user_can( $capability ) { global $test_capabilities; return ! empty( $test_capabilities[ $capability ] ); }
class SITEINTELIX_Security { public static $allowed = true; public static function can_manage_global_tools() { return self::$allowed; } }
function do_action() {}
function wp_clean_plugins_cache() {}
function wp_tempnam() { return tempnam( sys_get_temp_dir(), 'sitx-version-' ); }
function get_temp_dir() { return trailingslashit( sys_get_temp_dir() ); }
function get_plugin_data( $path ) {
	$contents = file_get_contents( $path );
	preg_match( '/^\s*Version:\s*(.+)$/mi', $contents, $match );
	return array( 'Version' => isset( $match[1] ) ? trim( $match[1] ) : '' );
}
function get_plugins() {
	$plugins = array();
	if ( ! is_dir( WP_PLUGIN_DIR ) ) { return $plugins; }
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WP_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$data = get_plugin_data( $file->getPathname() );
			$contents = file_get_contents( $file->getPathname() );
			if ( preg_match( '/^\s*Plugin Name:\s*(.+)$/mi', $contents, $name ) ) {
				$relative = ltrim( str_replace( '\\', '/', substr( $file->getPathname(), strlen( WP_PLUGIN_DIR ) ) ), '/' );
				$plugins[ $relative ] = array( 'Name' => trim( $name[1] ), 'Version' => $data['Version'] );
			}
		}
	}
	return $plugins;
}
function copy_dir( $source, $destination ) {
	if ( ! is_dir( $destination ) && ! mkdir( $destination, 0770, true ) ) { return new WP_Error( 'copy_failed', 'Copy failed' ); }
	foreach ( scandir( $source ) as $name ) {
		if ( '.' === $name || '..' === $name ) { continue; }
		$from = $source . '/' . $name; $to = $destination . '/' . $name;
		if ( is_dir( $from ) ) { $result = copy_dir( $from, $to ); if ( is_wp_error( $result ) ) { return $result; } }
		elseif ( ! copy( $from, $to ) ) { return new WP_Error( 'copy_failed', 'Copy failed' ); }
	}
	return true;
}

class Test_Filesystem {
	public function exists( $path ) { return file_exists( $path ); }
	public function is_dir( $path ) { return is_dir( $path ); }
	public function delete( $path, $recursive = false ) {
		if ( ! file_exists( $path ) ) { return true; }
		if ( is_dir( $path ) && $recursive ) { remove_test_tree( $path ); return ! file_exists( $path ); }
		return is_dir( $path ) ? rmdir( $path ) : unlink( $path );
	}
}
$wp_filesystem = new Test_Filesystem();
function WP_Filesystem() { return true; }

class Automatic_Upgrader_Skin {
	public function get_errors() { return new class { public function has_errors() { return false; } public function get_error_message() { return ''; } }; }
}
class Plugin_Upgrader {
	public static $fail = false;
	public function __construct( $skin ) {}
	public function install( $package ) {
		if ( self::$fail ) { remove_test_tree( WP_PLUGIN_DIR . '/demo-plugin' ); return new WP_Error( 'simulated_upgrade_failure', 'Simulated installer failure.' ); }
		$zip = new ZipArchive(); $zip->open( $package ); $zip->extractTo( WP_PLUGIN_DIR ); $zip->close(); return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-storage.php';
require_once dirname( __DIR__ ) . '/includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-package.php';

$passed = 0;
$failed = 0;
function assert_test( $condition, $message ) {
	global $passed, $failed;
	if ( $condition ) { $passed++; echo "PASS: {$message}\n"; } else { $failed++; echo "FAIL: {$message}\n"; }
}
function make_plugin_zip( $path, $version, $extra = array() ) {
	$zip = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$zip->addFromString( 'demo-plugin/demo-plugin.php', "<?php\n/*\nPlugin Name: Demo Plugin\nVersion: {$version}\nRequires PHP: 7.4\nRequires at least: 5.8\n*/\n" );
	foreach ( $extra as $name => $contents ) { $zip->addFromString( $name, $contents ); }
	$zip->close();
}

if ( ! class_exists( 'ZipArchive' ) ) {
	echo "SKIP: ZipArchive is not available.\n";
	exit( 0 );
}

$zip_one = $test_root . '/demo-1.zip';
$zip_two = $test_root . '/demo-2.zip';
make_plugin_zip( $zip_one, '1.0.0' );
make_plugin_zip( $zip_two, '2.0.0' );

$one = SITEINTELIX_Version_Switcher_Package::import( $zip_one, 'Demo 1.zip', 7, false );
$two = SITEINTELIX_Version_Switcher_Package::import( $zip_two, 'Demo 2.zip', 7, false );
assert_test( ! is_wp_error( $one ) && ! is_wp_error( $two ), 'two valid versions import successfully' );
assert_test( 2 === count( SITEINTELIX_Version_Switcher_Storage::manifest() ), 'versions are grouped by shared plugin slug metadata' );
assert_test( ! is_dir( WP_PLUGIN_DIR . '/demo-plugin' ), 'uploading builds does not install or activate the plugin' );
assert_test( 64 === strlen( $one['checksum'] ) && SITEINTELIX_Version_Switcher_Storage::valid_filename( $one['stored_filename'] ), 'manifest stores SHA-256 and randomized filenames' );
assert_test( false === strpos( SITEINTELIX_Version_Switcher_Storage::safe_message( WP_CONTENT_DIR . '/siteintelix/version-vault/' . $one['stored_filename'] ), $one['stored_filename'] ), 'user-visible errors redact physical paths and randomized filenames' );

$malformed = $test_root . '/malformed.zip';
file_put_contents( $malformed, 'not a zip' );
assert_test( 'siteintelix_version_malformed_zip' === SITEINTELIX_Version_Switcher_Package::inspect( $malformed )->get_error_code(), 'malformed ZIP is rejected' );

$headerless = $test_root . '/headerless.zip';
$zip = new ZipArchive(); $zip->open( $headerless, ZipArchive::CREATE ); $zip->addFromString( 'demo/readme.txt', 'No plugin header' ); $zip->close();
assert_test( 'siteintelix_version_no_plugin_header' === SITEINTELIX_Version_Switcher_Package::inspect( $headerless )->get_error_code(), 'ZIP without plugin header is rejected' );

$traversal = $test_root . '/traversal.zip';
$zip = new ZipArchive(); $zip->open( $traversal, ZipArchive::CREATE ); $zip->addFromString( '../escape.php', '<?php' ); $zip->addFromString( 'demo/demo.php', "<?php\n/* Plugin Name: Demo\nVersion: 1.0 */" ); $zip->close();
assert_test( 'siteintelix_version_unsafe_archive' === SITEINTELIX_Version_Switcher_Package::inspect( $traversal )->get_error_code(), 'archive traversal path is rejected' );

$duplicate = SITEINTELIX_Version_Switcher_Package::import( $zip_one, 'Again.zip', 7, false );
assert_test( is_wp_error( $duplicate ) && 'siteintelix_version_duplicate_package' === $duplicate->get_error_code(), 'exact duplicate version and checksum is rejected' );

$same_version = $test_root . '/demo-1-build-b.zip';
make_plugin_zip( $same_version, '1.0.0', array( 'demo-plugin/build.txt' => 'different build' ) );
$different_build = SITEINTELIX_Version_Switcher_Package::import( $same_version, 'Demo 1 build B.zip', 7, false );
assert_test( ! is_wp_error( $different_build ) && $different_build['checksum'] !== $one['checksum'], 'same version with different checksum is stored as a distinct build' );

mkdir( ABSPATH . 'wp-admin/includes', 0770, true );
file_put_contents( ABSPATH . 'wp-admin/includes/plugin.php', "<?php\n" );
file_put_contents( ABSPATH . 'wp-admin/includes/file.php', "<?php\n" );
file_put_contents( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php', "<?php\n" );
mkdir( WP_PLUGIN_DIR . '/demo-plugin', 0770, true );
file_put_contents( WP_PLUGIN_DIR . '/demo-plugin/demo-plugin.php', "<?php\n/*\nPlugin Name: Demo Plugin\nVersion: 1.0.0\n*/\n" );
update_option( 'active_plugins', array( 'demo-plugin/demo-plugin.php' ) );
require_once dirname( __DIR__ ) . '/includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-switcher.php';
require_once dirname( __DIR__ ) . '/includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-module.php';
$switched = SITEINTELIX_Version_Switcher_Switcher::switch_to( $two['package_id'], 'http://example.test/course' );
assert_test( ! is_wp_error( $switched ) && '2.0.0' === get_plugin_data( WP_PLUGIN_DIR . '/demo-plugin/demo-plugin.php' )['Version'], 'requested version is installed and verified' );
assert_test( in_array( 'demo-plugin/demo-plugin.php', get_option( 'active_plugins' ), true ), 'switching an active plugin preserves active state' );

update_option( 'active_plugins', array() );
$switched_back = SITEINTELIX_Version_Switcher_Switcher::switch_to( $one['package_id'], 'http://example.test/course' );
assert_test( ! is_wp_error( $switched_back ) && empty( get_option( 'active_plugins' ) ), 'switching an inactive plugin keeps it inactive' );

Plugin_Upgrader::$fail = true;
$failed_switch = SITEINTELIX_Version_Switcher_Switcher::switch_to( $two['package_id'], 'http://example.test/course' );
assert_test( is_wp_error( $failed_switch ) && '1.0.0' === get_plugin_data( WP_PLUGIN_DIR . '/demo-plugin/demo-plugin.php' )['Version'], 'failed switch restores the previous plugin files' );
Plugin_Upgrader::$fail = false;
assert_test( SITEINTELIX_Version_Switcher_Switcher::is_self_package( array( 'plugin_directory' => 'siteintelix' ) ), 'SiteIntelix cannot switch itself' );

assert_test( SITEINTELIX_Version_Switcher_Module::can_switch(), 'authorized administrator passes module authorization' );
$test_capabilities['update_plugins'] = false;
assert_test( ! SITEINTELIX_Version_Switcher_Module::can_switch(), 'user without update_plugins cannot upload, switch, delete, or see toolbar controls' );
$test_capabilities['update_plugins'] = true;
assert_test( 'http://example.test/wp-admin/admin.php?page=siteintelix-version-switcher' === SITEINTELIX_Version_Switcher_Module::validated_return_url( 'https://attacker.example/steal' ), 'external return URL is rejected' );
assert_test( 'http://example.test/course/42' === SITEINTELIX_Version_Switcher_Module::validated_return_url( 'http://example.test/course/42' ), 'same-site return URL is preserved' );

$lock = SITEINTELIX_Version_Switcher_Storage::acquire_lock();
$second_lock = SITEINTELIX_Version_Switcher_Storage::acquire_lock();
assert_test( is_string( $lock ) && is_wp_error( $second_lock ), 'switch lock prevents concurrent operations' );
SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );

for ( $index = 0; $index < 110; $index++ ) { SITEINTELIX_Version_Switcher_Storage::add_history( array( 'plugin_name' => 'Demo', 'plugin_slug' => 'demo', 'success' => true ) ); }
assert_test( SITEINTELIX_Version_Switcher_Storage::HISTORY_LIMIT === count( SITEINTELIX_Version_Switcher_Storage::history() ), 'history retention is bounded' );

$manifest = SITEINTELIX_Version_Switcher_Storage::manifest();
$unsafe_id = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';
$manifest[ $unsafe_id ] = array( 'package_id' => $unsafe_id, 'stored_filename' => '../../outside.zip' );
update_option( SITEINTELIX_Version_Switcher_Storage::MANIFEST_OPTION, $manifest );
$unsafe_delete = SITEINTELIX_Version_Switcher_Storage::delete_package( $unsafe_id );
assert_test( is_wp_error( $unsafe_delete ) && 'siteintelix_version_unsafe_package' === $unsafe_delete->get_error_code(), 'package deletion cannot escape the vault' );

function remove_test_tree( $directory ) {
	if ( ! is_dir( $directory ) || is_link( $directory ) ) { return; }
	foreach ( scandir( $directory ) as $name ) {
		if ( '.' === $name || '..' === $name ) { continue; }
		$path = $directory . '/' . $name;
		if ( is_dir( $path ) && ! is_link( $path ) ) { remove_test_tree( $path ); } else { unlink( $path ); }
	}
	rmdir( $directory );
}
remove_test_tree( $test_root );
echo "\n{$passed} passed, {$failed} failed\n";
exit( $failed ? 1 : 0 );
