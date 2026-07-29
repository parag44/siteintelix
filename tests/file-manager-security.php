<?php
/**
 * Dependency-free File Manager security tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-security-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-admin', 0777, true );
mkdir( $siteintelix_test_root . '/wp-includes', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/uploads/nested', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/photo.jpg', 'image' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/nested/note.txt', 'note' );
file_put_contents( $siteintelix_test_root . '/wp-content/plugins/siteintelix/siteintelix.php', '<?php' );
file_put_contents( $siteintelix_test_root . '/wp-content/themes/active/style.css', 'body{}' );
file_put_contents( $siteintelix_test_root . '/wp-config.php', '<?php' );

define( 'ABSPATH', $siteintelix_test_root . '/' );
define( 'WP_CONTENT_DIR', $siteintelix_test_root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/mu-plugins' );
define( 'SITEINTELIX_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins/siteintelix/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );

$siteintelix_test_options   = array();
$siteintelix_test_multisite = false;
$siteintelix_test_caps      = array( 'manage_options' => true );
$siteintelix_test_filters   = array();

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
	if ( isset( $siteintelix_test_filters[ $hook ] ) ) {
		return call_user_func( $siteintelix_test_filters[ $hook ], $value );
	}
	return $value;
}

function get_option( $key, $default = false ) {
	global $siteintelix_test_options;
	return array_key_exists( $key, $siteintelix_test_options ) ? $siteintelix_test_options[ $key ] : $default;
}

function add_option( $key, $value ) {
	global $siteintelix_test_options;
	if ( array_key_exists( $key, $siteintelix_test_options ) ) {
		return false;
	}
	$siteintelix_test_options[ $key ] = $value;
	return true;
}

function update_option( $key, $value ) {
	global $siteintelix_test_options;
	$siteintelix_test_options[ $key ] = $value;
	return true;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
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

function is_multisite() {
	global $siteintelix_test_multisite;
	return $siteintelix_test_multisite;
}

function current_user_can( $capability ) {
	global $siteintelix_test_caps;
	return ! empty( $siteintelix_test_caps[ $capability ] );
}

function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$siteintelix_settings_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-settings.php';
$siteintelix_security_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-security.php';
siteintelix_test_assert( file_exists( $siteintelix_settings_class ), 'File Manager settings class exists' );
siteintelix_test_assert( file_exists( $siteintelix_security_class ), 'File Manager security class exists' );
require_once $siteintelix_settings_class;
require_once $siteintelix_security_class;

$security = new SITEINTELIX_File_Manager_Security();
$valid    = $security->authorize_path( 'wp-content/uploads/photo.jpg', 'read' );

siteintelix_test_assert( ! is_wp_error( $valid ), 'valid content file is readable' . ( is_wp_error( $valid ) ? ': ' . $valid->get_error_code() : '' ) );
siteintelix_test_assert( ! is_wp_error( $security->authorize_path( 'wp-content/uploads/nested/note.txt', 'read' ) ), 'valid nested file is readable' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '../wp-config.php', 'read' ) ), 'plain traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '..%2fwp-config.php', 'read' ) ), 'encoded traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '..%252fwp-config.php', 'read' ) ), 'double-encoded traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '..\\wp-config.php', 'read' ) ), 'Windows traversal is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( "wp-content/\0x", 'read' ) ), 'null byte is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'php://filter/resource=index.php', 'read' ) ), 'stream wrappers are blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( '/var/www/external.txt', 'read' ) ), 'external absolute path is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-admin', 'write' ) ), 'core write is blocked' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/plugins/siteintelix/siteintelix.php', 'write' ) ), 'SiteIntelix is immutable' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/themes/active/style.css', 'write' ) ), 'active theme is immutable' );
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-config.php', 'read' ) ), 'wp-config preview is disabled' );
siteintelix_test_assert( ! is_wp_error( $security->resolve_destination( 'wp-content/uploads', 'safe.txt', 'create' ) ), 'safe destination is accepted' );
siteintelix_test_assert( is_wp_error( $security->resolve_destination( 'wp-content/uploads', '../bad.txt', 'create' ) ), 'destination traversal is blocked' );

$siteintelix_external = sys_get_temp_dir() . '/siteintelix-fm-external-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_external );
file_put_contents( $siteintelix_external . '/secret.txt', 'secret' );
if ( function_exists( 'symlink' ) && @symlink( $siteintelix_external, WP_CONTENT_DIR . '/uploads/outside-link' ) ) {
	siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/uploads/outside-link/secret.txt', 'read' ) ), 'symlink escape is blocked' );
}

$siteintelix_test_filters['siteintelix_file_manager_protected_paths'] = static function () {
	return array();
};
siteintelix_test_assert( is_wp_error( $security->authorize_path( 'wp-content/plugins/siteintelix/siteintelix.php', 'write' ) ), 'filters cannot remove immutable protection' );
$siteintelix_test_filters = array();

$siteintelix_test_caps = array( 'manage_options' => true );
siteintelix_test_assert( SITEINTELIX_File_Manager_Security::current_user_can_manage(), 'administrator is permitted' );
$siteintelix_test_caps = array( 'edit_posts' => true );
siteintelix_test_assert( ! SITEINTELIX_File_Manager_Security::current_user_can_manage(), 'editor is blocked' );
$siteintelix_test_multisite = true;
$siteintelix_test_caps      = array( 'manage_options' => true );
siteintelix_test_assert( ! SITEINTELIX_File_Manager_Security::current_user_can_manage(), 'multisite site administrator is blocked' );
$siteintelix_test_caps = array( 'manage_network_options' => true );
siteintelix_test_assert( SITEINTELIX_File_Manager_Security::current_user_can_manage(), 'network administrator is permitted' );

echo "File Manager security tests passed.\n";
