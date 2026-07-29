<?php
/**
 * Dependency-free File Manager editor tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-editor-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-content/uploads', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/note.txt', "original\n" );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/code.php', "<?php echo 'x';" );

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
function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0755, true );
}
function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES );
}
function get_current_user_id() {
	return 9;
}
function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/includes/modules/file-manager/';
foreach ( array( 'settings', 'security', 'storage', 'backups', 'editor' ) as $class ) {
	$file = $base . 'class-siteintelix-file-manager-' . $class . '.php';
	siteintelix_test_assert( file_exists( $file ), "{$class} class exists" );
	require_once $file;
}

$security = new SITEINTELIX_File_Manager_Security();
$backups = new SITEINTELIX_File_Manager_Backups( $security );
$editor = new SITEINTELIX_File_Manager_Editor( $security, $backups );
$opened = $editor->open( 'wp-content/uploads/note.txt' );
siteintelix_test_assert( ! is_wp_error( $opened ), 'approved text file opens' );
$saved = $editor->save( 'wp-content/uploads/note.txt', "replacement\n", $opened['modified'], $opened['sha256'] );
siteintelix_test_assert( ! is_wp_error( $saved ), 'approved text save succeeds' );
siteintelix_test_assert( "replacement\n" === file_get_contents( $siteintelix_test_root . '/wp-content/uploads/note.txt' ), 'replacement is complete' );
siteintelix_test_assert( 1 === count( $backups->for_path( 'wp-content/uploads/note.txt' ) ), 'save creates a backup' );

$stale = $editor->save( 'wp-content/uploads/note.txt', "stale\n", $opened['modified'], $opened['sha256'] );
siteintelix_test_assert( is_wp_error( $stale ) && 'siteintelix_file_manager_stale_file' === $stale->get_error_code(), 'stale save is blocked' );
siteintelix_test_assert( is_wp_error( $editor->open( 'wp-content/uploads/code.php' ) ), 'PHP remains view-only' );

$siteintelix_test_options['siteintelix_file_manager_settings'] = array( 'edit_max_bytes' => 4 );
siteintelix_test_assert( is_wp_error( $editor->open( 'wp-content/uploads/note.txt' ) ), 'oversized edit is blocked' );
$siteintelix_test_options = array();

$failing_backups = new class {
	public function create( $path, $operation ) {
		return new WP_Error( 'backup_failed', 'failed' );
	}
};
$backup_failure_editor = new SITEINTELIX_File_Manager_Editor( $security, $failing_backups );
$current = $editor->open( 'wp-content/uploads/note.txt' );
$backup_failure = $backup_failure_editor->save( 'wp-content/uploads/note.txt', "unchanged\n", $current['modified'], $current['sha256'] );
siteintelix_test_assert( is_wp_error( $backup_failure ), 'backup failure blocks save' );
siteintelix_test_assert( "replacement\n" === file_get_contents( $siteintelix_test_root . '/wp-content/uploads/note.txt' ), 'backup failure preserves original' );

$rename_failure_editor = new SITEINTELIX_File_Manager_Editor(
	$security,
	$backups,
	array(
		'rename' => static function () {
			return false;
		},
	)
);
$current = $editor->open( 'wp-content/uploads/note.txt' );
$rename_failure = $rename_failure_editor->save( 'wp-content/uploads/note.txt', "not-installed\n", $current['modified'], $current['sha256'] );
siteintelix_test_assert( is_wp_error( $rename_failure ), 'atomic rename failure is reported' );
siteintelix_test_assert( "replacement\n" === file_get_contents( $siteintelix_test_root . '/wp-content/uploads/note.txt' ), 'atomic failure preserves original' );

echo "File Manager editor tests passed.\n";
