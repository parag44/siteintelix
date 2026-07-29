<?php
/**
 * Dependency-free File Manager folder-tree tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-tree-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/alpha/child/grandchild', 0777, true );
mkdir( $siteintelix_test_root . '/beta', 0777, true );
mkdir( $siteintelix_test_root . '/.hidden', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/uploads', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/alpha/file.txt', 'not a directory' );

define( 'ABSPATH', $siteintelix_test_root . '/' );
define( 'WP_CONTENT_DIR', $siteintelix_test_root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/mu-plugins' );
define( 'SITEINTELIX_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins/siteintelix/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );

$siteintelix_test_filters = array();

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function __( $text ) {
	return $text;
}

function apply_filters( $hook, $value ) {
	global $siteintelix_test_filters;
	return isset( $siteintelix_test_filters[ $hook ] ) ? call_user_func( $siteintelix_test_filters[ $hook ], $value ) : $value;
}

function get_option( $key, $default = false ) {
	return $default;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}

function trailingslashit( $path ) {
	return rtrim( wp_normalize_path( $path ), '/' ) . '/';
}

function untrailingslashit( $path ) {
	return rtrim( wp_normalize_path( $path ), '/' );
}

function wp_upload_dir() {
	return array( 'basedir' => WP_CONTENT_DIR . '/uploads' );
}

function get_theme_root() {
	return WP_CONTENT_DIR . '/themes';
}

function get_stylesheet_directory() {
	return WP_CONTENT_DIR . '/themes/active';
}

function get_template_directory() {
	return WP_CONTENT_DIR . '/themes/active';
}

function is_multisite() {
	return false;
}

function current_user_can( $capability ) {
	return true;
}

function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/includes/modules/file-manager/';
require_once $base . 'class-siteintelix-file-manager-settings.php';
require_once $base . 'class-siteintelix-file-manager-security.php';
$tree_class = $base . 'class-siteintelix-file-manager-tree.php';
siteintelix_test_assert( file_exists( $tree_class ), 'tree class exists' );
require_once $tree_class;

$tree = new SITEINTELIX_File_Manager_Tree( new SITEINTELIX_File_Manager_Security() );
$root = $tree->children( '' );
siteintelix_test_assert( ! is_wp_error( $root ), 'root tree request succeeds' );
siteintelix_test_assert( array( 'alpha', 'beta', 'wp-content' ) === array_column( $root['children'], 'name' ), 'tree returns sorted visible directories only' );
siteintelix_test_assert( true === $root['children'][0]['has_children'], 'tree detects immediate descendants' );
siteintelix_test_assert( false === $root['children'][1]['has_children'], 'tree reports leaf directories' );
siteintelix_test_assert( 1 === $root['children'][0]['level'], 'root children use level one' );

$nested = $tree->children( 'alpha/child' );
siteintelix_test_assert( 'alpha/child' === $nested['path'], 'nested path remains relative' );
siteintelix_test_assert( array( 'grandchild' ) === array_column( $nested['children'], 'name' ), 'tree loads one branch only' );

$siteintelix_test_filters['siteintelix_file_manager_directory_scan_limit'] = static function () {
	return 200;
};
for ( $index = 0; $index < 201; $index++ ) {
	mkdir( $siteintelix_test_root . '/bounded-' . $index );
}
$bounded = $tree->children( '' );
siteintelix_test_assert( true === $bounded['truncated'], 'tree reports a bounded branch scan' );

echo "File Manager tree tests passed.\n";
