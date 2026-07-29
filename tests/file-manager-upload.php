<?php
/**
 * Dependency-free File Manager upload/create/rename tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-upload-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-content/uploads', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/existing.txt', 'existing' );

define( 'ABSPATH', $siteintelix_test_root . '/' );
define( 'WP_CONTENT_DIR', $siteintelix_test_root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/mu-plugins' );
define( 'SITEINTELIX_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins/siteintelix/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );

$siteintelix_test_options = array();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) {
		$this->code = $code;
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
	return $value;
}
function do_action() {
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_file_name( $value ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '-', basename( (string) $value ) );
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
	return false;
}
function current_user_can( $capability ) {
	return true;
}
function get_current_user_id() {
	return 5;
}
function get_option( $key, $default = false ) {
	global $siteintelix_test_options;
	return array_key_exists( $key, $siteintelix_test_options ) ? $siteintelix_test_options[ $key ] : $default;
}
function add_option( $key, $value ) {
	global $siteintelix_test_options;
	$siteintelix_test_options[ $key ] = $value;
	return true;
}
function update_option( $key, $value ) {
	global $siteintelix_test_options;
	$siteintelix_test_options[ $key ] = $value;
	return true;
}
function wp_check_filetype_and_ext( $tmp, $name ) {
	$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$map = array(
		'jpg' => 'image/jpeg',
		'txt' => 'text/plain',
	);
	return array(
		'ext' => isset( $map[ $extension ] ) ? $extension : false,
		'type' => isset( $map[ $extension ] ) ? $map[ $extension ] : false,
		'proper_filename' => false,
	);
}
function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0755, true );
}
function wp_json_encode( $value ) {
	return json_encode( $value );
}
function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/includes/modules/file-manager/';
foreach ( array( 'settings', 'security', 'storage', 'backups', 'filesystem', 'upload' ) as $class ) {
	$file = $base . 'class-siteintelix-file-manager-' . $class . '.php';
	siteintelix_test_assert( file_exists( $file ), "{$class} class exists" );
	require_once $file;
}

$security = new SITEINTELIX_File_Manager_Security();
$filesystem = new SITEINTELIX_File_Manager_Filesystem( $security );
$upload = new SITEINTELIX_File_Manager_Upload(
	$security,
	array(
		'is_uploaded_file' => static function () {
			return true;
		},
		'move_uploaded_file' => static function ( $source, $destination ) {
			return rename( $source, $destination );
		},
	)
);

$valid_tmp = tempnam( sys_get_temp_dir(), 'sitx-upload-' );
file_put_contents( $valid_tmp, 'jpeg-data' );
$valid = $upload->store(
	array( 'name' => 'photo.jpg', 'tmp_name' => $valid_tmp, 'size' => 9, 'error' => UPLOAD_ERR_OK ),
	'wp-content/uploads'
);
siteintelix_test_assert( ! is_wp_error( $valid ) && is_file( $siteintelix_test_root . '/wp-content/uploads/photo.jpg' ), 'valid image upload succeeds' );

$collision_tmp = tempnam( sys_get_temp_dir(), 'sitx-upload-' );
file_put_contents( $collision_tmp, 'new-photo' );
$collision_file = array( 'name' => 'photo.jpg', 'tmp_name' => $collision_tmp, 'size' => 9, 'error' => UPLOAD_ERR_OK );
siteintelix_test_assert( is_wp_error( $upload->store( $collision_file, 'wp-content/uploads' ) ), 'upload collision is blocked by default' );
$siteintelix_test_options['siteintelix_file_manager_settings'] = array( 'allow_overwrite' => 1 );
siteintelix_test_assert( is_wp_error( $upload->store( $collision_file, 'wp-content/uploads' ) ), 'overwrite setting still requires an explicit request' );
$overwritten = $upload->store( $collision_file, 'wp-content/uploads', true );
siteintelix_test_assert( ! is_wp_error( $overwritten ) && 'new-photo' === file_get_contents( $siteintelix_test_root . '/wp-content/uploads/photo.jpg' ), 'explicit enabled overwrite succeeds' );
siteintelix_test_assert( count( ( new SITEINTELIX_File_Manager_Backups( $security ) )->for_path( 'wp-content/uploads/photo.jpg' ) ) >= 1, 'overwrite creates a verified safety backup' );
$siteintelix_test_options = array();

foreach ( array( 'shell.php', 'shell.php.jpg', '../escape.jpg', 'photo.test.jpg', 'PHOTO.TEST.JPG', 'photo.jpg.', 'photo。jpg' ) as $name ) {
	$tmp = tempnam( sys_get_temp_dir(), 'sitx-upload-' );
	file_put_contents( $tmp, 'payload' );
	$result = $upload->store( array( 'name' => $name, 'tmp_name' => $tmp, 'size' => 7, 'error' => UPLOAD_ERR_OK ), 'wp-content/uploads' );
	siteintelix_test_assert( is_wp_error( $result ), "{$name} upload is blocked" );
}

$spoof_tmp = tempnam( sys_get_temp_dir(), 'sitx-upload-' );
file_put_contents( $spoof_tmp, 'payload' );
$spoof = $upload->store( array( 'name' => 'photo.exe', 'tmp_name' => $spoof_tmp, 'size' => 7, 'error' => UPLOAD_ERR_OK ), 'wp-content/uploads' );
siteintelix_test_assert( is_wp_error( $spoof ), 'unapproved extension is blocked' );

$siteintelix_test_options['siteintelix_file_manager_settings'] = array( 'upload_max_bytes' => 4 );
$large_tmp = tempnam( sys_get_temp_dir(), 'sitx-upload-' );
file_put_contents( $large_tmp, 'large' );
siteintelix_test_assert( is_wp_error( $upload->store( array( 'name' => 'large.jpg', 'tmp_name' => $large_tmp, 'size' => 5, 'error' => UPLOAD_ERR_OK ), 'wp-content/uploads' ) ), 'oversized upload is blocked' );
$siteintelix_test_options = array();

siteintelix_test_assert( ! is_wp_error( $filesystem->create_directory( 'wp-content/uploads', 'new-folder' ) ), 'folder creation succeeds' );
siteintelix_test_assert( ! is_wp_error( $filesystem->create_file( 'wp-content/uploads', 'new.txt', "text\n" ) ), 'approved text creation succeeds' );
siteintelix_test_assert( is_wp_error( $filesystem->create_file( 'wp-content/uploads', 'new.php', '<?php' ) ), 'PHP creation is blocked' );
siteintelix_test_assert( is_wp_error( $filesystem->create_file( 'wp-content/uploads', '../escape.txt', 'x' ) ), 'filename traversal is blocked' );
siteintelix_test_assert( is_wp_error( $filesystem->create_file( 'wp-content/uploads', 'existing.txt', 'x' ) ), 'duplicate creation is blocked' );

siteintelix_test_assert( ! is_wp_error( $filesystem->rename_item( 'wp-content/uploads/new.txt', 'renamed.txt' ) ), 'same-parent rename succeeds' );
siteintelix_test_assert( is_wp_error( $filesystem->rename_item( 'wp-content/uploads/renamed.txt', 'renamed.php' ) ), 'extension-changing rename is blocked' );
siteintelix_test_assert( is_wp_error( $filesystem->rename_item( 'wp-content/uploads/renamed.txt', 'existing.txt' ) ), 'rename overwrite is blocked' );

echo "File Manager upload tests passed.\n";
