<?php
/**
 * Dependency-free File Manager archive tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-archive-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-content/uploads/folder/nested', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/one.txt', 'one' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/two.txt', 'two' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/nested/three.txt', 'three' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/folder/php.ini', 'secret' );
file_put_contents( $siteintelix_test_root . '/wp-content/plugins/siteintelix/siteintelix.php', '<?php' );
file_put_contents( $siteintelix_test_root . '/wp-config.php', '<?php' );

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

function sanitize_file_name( $value ) {
	$value = preg_replace( '/[^A-Za-z0-9._-]+/', '-', (string) $value );
	return trim( $value, '-.' );
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

function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0755, true );
}

function wp_delete_file( $path ) {
	return unlink( $path );
}

function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/includes/modules/file-manager/';
foreach ( array( 'settings', 'security', 'storage' ) as $class ) {
	require_once $base . 'class-siteintelix-file-manager-' . $class . '.php';
}
$archive_class = $base . 'class-siteintelix-file-manager-archive.php';
siteintelix_test_assert( file_exists( $archive_class ), 'archive class exists' );
require_once $archive_class;

siteintelix_test_assert( class_exists( 'ZipArchive' ), 'ZipArchive is available in the test runtime' );
$archive = new SITEINTELIX_File_Manager_Archive( new SITEINTELIX_File_Manager_Security() );
$result  = $archive->create(
	'wp-content/uploads',
	array( 'wp-content/uploads/one.txt', 'wp-content/uploads/folder' )
);
siteintelix_test_assert( ! is_wp_error( $result ), 'archive creation succeeds' . ( is_wp_error( $result ) ? ': ' . $result->get_error_code() : '' ) );
siteintelix_test_assert( file_exists( $result['path'] ), 'archive exists before streaming cleanup' );
siteintelix_test_assert( 5 === $result['entries'], 'archive counts permitted files and directory records' );
siteintelix_test_assert( 1 === $result['omitted'], 'archive reports protected omission' );

$zip = new ZipArchive();
siteintelix_test_assert( true === $zip->open( $result['path'] ), 'created ZIP opens' );
$names = array();
for ( $index = 0; $index < $zip->numFiles; $index++ ) {
	$names[] = $zip->getNameIndex( $index );
}
$zip->close();
sort( $names );
siteintelix_test_assert(
	array( 'folder/', 'folder/nested/', 'folder/nested/three.txt', 'folder/two.txt', 'one.txt' ) === $names,
	'archive paths are relative and protected files are omitted'
);
siteintelix_test_assert( false === strpos( implode( "\n", $names ), $siteintelix_test_root ), 'archive never contains absolute paths' );

$outside_parent = $archive->create( 'wp-content/uploads', array( 'wp-content/plugins' ) );
siteintelix_test_assert( is_wp_error( $outside_parent ), 'selected sources must share the current parent' );
$too_many = $archive->create( 'wp-content/uploads', array_fill( 0, 101, 'wp-content/uploads/one.txt' ) );
siteintelix_test_assert( is_wp_error( $too_many ) && 'siteintelix_file_manager_archive_selection_limit' === $too_many->get_error_code(), 'archive rejects more than 100 selected sources' );

if ( function_exists( 'symlink' ) && @symlink( $siteintelix_test_root . '/wp-content/uploads/one.txt', $siteintelix_test_root . '/wp-content/uploads/folder/link.txt' ) ) {
	$with_link = $archive->create( 'wp-content/uploads', array( 'wp-content/uploads/folder' ) );
	siteintelix_test_assert( ! is_wp_error( $with_link ), 'archive with a symlink descendant succeeds by omission' );
	$linked_zip = new ZipArchive();
	siteintelix_test_assert( true === $linked_zip->open( $with_link['path'] ), 'symlink test ZIP opens' );
	$linked_names = array();
	for ( $index = 0; $index < $linked_zip->numFiles; $index++ ) {
		$linked_names[] = $linked_zip->getNameIndex( $index );
	}
	$linked_zip->close();
	siteintelix_test_assert( ! in_array( 'folder/link.txt', $linked_names, true ), 'symlink and target are absent from ZIP' );
	siteintelix_test_assert( 2 === $with_link['omitted'], 'symlink and protected file omissions are counted' );
	$archive->delete( $with_link['path'] );
}

$siteintelix_test_filters['siteintelix_file_manager_archive_entry_limit'] = static function () {
	return 2;
};
$entry_limited = $archive->create( 'wp-content/uploads', array( 'wp-content/uploads/folder' ) );
siteintelix_test_assert( is_wp_error( $entry_limited ) && 'siteintelix_file_manager_archive_entry_limit' === $entry_limited->get_error_code(), 'archive enforces entry limit' );
unset( $siteintelix_test_filters['siteintelix_file_manager_archive_entry_limit'] );

$siteintelix_test_filters['siteintelix_file_manager_archive_byte_limit'] = static function () {
	return 2;
};
$size_limited = $archive->create( 'wp-content/uploads', array( 'wp-content/uploads/one.txt' ) );
siteintelix_test_assert( is_wp_error( $size_limited ) && 'siteintelix_file_manager_archive_size_limit' === $size_limited->get_error_code(), 'archive enforces uncompressed byte limit' );
unset( $siteintelix_test_filters['siteintelix_file_manager_archive_byte_limit'] );

$remaining_archives = glob( SITEINTELIX_File_Manager_Storage::path( 'tmp/archive-*.zip' ) );
siteintelix_test_assert( array( $result['path'] ) === $remaining_archives, 'failed archives leave no partial ZIP files' );
$guard_file = SITEINTELIX_File_Manager_Storage::path( 'tmp/index.php' );
$guard_delete = $archive->delete( $guard_file );
siteintelix_test_assert( is_wp_error( $guard_delete ), 'cleanup rejects non-archive private files' );
siteintelix_test_assert( is_file( $guard_file ), 'cleanup preserves private protection files' );
$archive->delete( $result['path'] );
siteintelix_test_assert( ! file_exists( $result['path'] ), 'archive cleanup removes the private ZIP' );
siteintelix_test_assert( array() === glob( SITEINTELIX_File_Manager_Storage::path( 'tmp/archive-*.zip' ) ), 'temporary ZIP storage is empty after cleanup' );

echo "File Manager archive tests passed.\n";
