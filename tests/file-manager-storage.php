<?php
/**
 * Dependency-free File Manager owned-storage tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-storage-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-content', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/uploads/non-empty', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/trash-me.txt', 'trash' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/non-empty/child.txt', 'child' );
file_put_contents( $siteintelix_test_root . '/wp-content/plugins/siteintelix/siteintelix.php', '<?php' );

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
function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0755, true );
}
function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES );
}
function get_option( $key, $default = false ) {
	global $siteintelix_test_options;
	return array_key_exists( $key, $siteintelix_test_options ) ? $siteintelix_test_options[ $key ] : $default;
}
function get_current_user_id() {
	return 7;
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
function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$siteintelix_storage_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-storage.php';
$siteintelix_redactor_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-redactor.php';
$siteintelix_audit_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-audit.php';
siteintelix_test_assert( file_exists( $siteintelix_storage_class ), 'storage class exists' );
siteintelix_test_assert( file_exists( $siteintelix_redactor_class ), 'redactor class exists' );
siteintelix_test_assert( file_exists( $siteintelix_audit_class ), 'audit class exists' );
require_once $siteintelix_storage_class;
require_once $siteintelix_redactor_class;
require_once $siteintelix_audit_class;

$siteintelix_settings_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-settings.php';
$siteintelix_security_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-security.php';
$siteintelix_trash_class = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-trash.php';
siteintelix_test_assert( file_exists( $siteintelix_settings_class ), 'settings class exists' );
siteintelix_test_assert( file_exists( $siteintelix_security_class ), 'security class exists' );
siteintelix_test_assert( file_exists( $siteintelix_trash_class ), 'trash class exists' );
require_once $siteintelix_settings_class;
require_once $siteintelix_security_class;
require_once $siteintelix_trash_class;

$created = SITEINTELIX_File_Manager_Storage::ensure_directories();
siteintelix_test_assert( true === $created, 'owned directories are initialized' );
foreach ( array( 'backups', 'trash', 'meta', 'audit' ) as $area ) {
	siteintelix_test_assert( is_dir( SITEINTELIX_File_Manager_Storage::path( $area ) ), "{$area} directory exists" );
	siteintelix_test_assert( is_file( SITEINTELIX_File_Manager_Storage::path( $area . '/index.php' ) ), "{$area} has an index guard" );
}

$metadata = array(
	'original_path' => 'wp-content/uploads/note.txt',
	'created_at'    => gmdate( 'c' ),
	'user_id'       => 7,
	'operation'     => 'edit',
	'size'          => 4,
	'sha256'        => hash( 'sha256', 'note' ),
);
$metadata_result = SITEINTELIX_File_Manager_Storage::write_metadata( 'backups', 'safe-id', $metadata );
siteintelix_test_assert( true === $metadata_result, 'valid metadata is written' . ( is_wp_error( $metadata_result ) ? ': ' . $metadata_result->get_error_code() : '' ) );
siteintelix_test_assert( $metadata === SITEINTELIX_File_Manager_Storage::read_metadata( 'backups', 'safe-id' ), 'metadata round trips' );
$bad_metadata = $metadata;
$bad_metadata['original_path'] = '../../wp-config.php';
siteintelix_test_assert( is_wp_error( SITEINTELIX_File_Manager_Storage::write_metadata( 'backups', 'bad-id', $bad_metadata ) ), 'traversal metadata is rejected' );
$bad_metadata['original_path'] = '/etc/passwd';
siteintelix_test_assert( is_wp_error( SITEINTELIX_File_Manager_Storage::write_metadata( 'backups', 'bad-id', $bad_metadata ) ), 'absolute metadata is rejected' );

$owned = SITEINTELIX_File_Manager_Storage::path( 'trash/delete-me' );
mkdir( $owned );
file_put_contents( $owned . '/payload.txt', 'payload' );
siteintelix_test_assert( true === SITEINTELIX_File_Manager_Storage::delete_owned_tree( 'trash', 'delete-me' ), 'owned tree is deleted' );
siteintelix_test_assert( ! file_exists( $owned ), 'owned tree no longer exists' );

$outside = $siteintelix_test_root . '/outside.txt';
file_put_contents( $outside, 'outside' );
if ( function_exists( 'symlink' ) && @symlink( $outside, SITEINTELIX_File_Manager_Storage::path( 'trash/link' ) ) ) {
	siteintelix_test_assert( is_wp_error( SITEINTELIX_File_Manager_Storage::delete_owned_tree( 'trash', 'link' ) ), 'owned deletion refuses symlinks' );
	siteintelix_test_assert( file_exists( $outside ), 'symlink target remains intact' );
}

$source = "define( 'DB_PASSWORD', 'secret' );\ndefine('AUTH_KEY','abc');\ndefine('WP_DEBUG', true);";
$redacted = SITEINTELIX_File_Manager_Redactor::wp_config( $source );
siteintelix_test_assert( false === strpos( $redacted, 'secret' ), 'database password is redacted' );
siteintelix_test_assert( false === strpos( $redacted, "'abc'" ), 'authentication key is redacted' );
siteintelix_test_assert( false !== strpos( $redacted, '********' ), 'redaction marker is present' );
siteintelix_test_assert( false !== strpos( $redacted, 'WP_DEBUG' ), 'non-sensitive constants are preserved' );

siteintelix_test_assert( true === SITEINTELIX_File_Manager_Audit::record( 'edit', 'wp-content/uploads/note.txt', 'success', '' ), 'audit record is appended' );
$audit_lines = file( SITEINTELIX_File_Manager_Audit::log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$audit = json_decode( end( $audit_lines ), true );
siteintelix_test_assert( array( 'user_id', 'timestamp', 'operation', 'path', 'result', 'error_category' ) === array_keys( $audit ), 'audit schema is minimal and stable' );
siteintelix_test_assert( false === strpos( json_encode( $audit ), 'secret' ), 'audit record has no content' );
$audit_count = count( $audit_lines );
$siteintelix_test_options['siteintelix_file_manager_settings'] = array( 'audit_enabled' => 1, 'audit_views' => 0, 'audit_downloads' => 1 );
siteintelix_test_assert( true === SITEINTELIX_File_Manager_Audit::record( 'view', 'wp-content/uploads/note.txt', 'success', '' ), 'disabled view auditing is a no-op' );
siteintelix_test_assert( $audit_count === count( file( SITEINTELIX_File_Manager_Audit::log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ), 'disabled view auditing writes no record' );
siteintelix_test_assert( true === SITEINTELIX_File_Manager_Audit::record( 'download', 'wp-content/uploads/note.txt', 'success', '' ), 'enabled download auditing succeeds' );
siteintelix_test_assert( $audit_count + 1 === count( file( SITEINTELIX_File_Manager_Audit::log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ), 'enabled download auditing writes one record' );
$siteintelix_test_options = array();

$trash = new SITEINTELIX_File_Manager_Trash( new SITEINTELIX_File_Manager_Security() );
$trashed = $trash->trash( 'wp-content/uploads/trash-me.txt', false );
siteintelix_test_assert( ! is_wp_error( $trashed ) && ! file_exists( $siteintelix_test_root . '/wp-content/uploads/trash-me.txt' ), 'file is moved to trash' );
$restored = $trash->restore( $trashed['id'] );
siteintelix_test_assert( ! is_wp_error( $restored ) && is_file( $siteintelix_test_root . '/wp-content/uploads/trash-me.txt' ), 'trashed file is restored' );

$blocked_directory = $trash->trash( 'wp-content/uploads/non-empty', false );
siteintelix_test_assert( is_wp_error( $blocked_directory ), 'non-empty directory requires confirmation' );
$trashed_directory = $trash->trash( 'wp-content/uploads/non-empty', true );
siteintelix_test_assert( ! is_wp_error( $trashed_directory ), 'confirmed non-empty directory is trashed' );
mkdir( $siteintelix_test_root . '/wp-content/uploads/non-empty' );
siteintelix_test_assert( is_wp_error( $trash->restore( $trashed_directory['id'] ) ), 'restore collision never overwrites' );
rmdir( $siteintelix_test_root . '/wp-content/uploads/non-empty' );
siteintelix_test_assert( ! is_wp_error( $trash->restore( $trashed_directory['id'] ) ), 'directory restore succeeds after collision is removed' );

$permanent = $trash->trash( 'wp-content/uploads/trash-me.txt', false );
siteintelix_test_assert( ! is_wp_error( $permanent ), 'file can be trashed again' );
siteintelix_test_assert( is_wp_error( $trash->permanently_delete( $permanent['id'], 'wrong-name.txt' ) ), 'permanent deletion rejects a mismatched confirmation' );
siteintelix_test_assert( true === $trash->permanently_delete( $permanent['id'], 'trash-me.txt' ), 'owned trash item is permanently deleted after server confirmation' );
siteintelix_test_assert( is_wp_error( $trash->trash( 'wp-content/plugins/siteintelix/siteintelix.php', false ) ), 'protected plugin file cannot be trashed' );

echo "File Manager storage tests passed.\n";
