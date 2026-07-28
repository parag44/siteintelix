<?php
/**
 * Custom code generated-file boundary tests.
 *
 * @package SiteIntelix
 */

$siteintelix_uploads = sys_get_temp_dir() . '/siteintelix-custom-code-' . bin2hex( random_bytes( 6 ) );
mkdir( $siteintelix_uploads . '/siteintelix/custom-code', 0777, true );
mkdir( $siteintelix_uploads . '/2026/07', 0777, true );

define( 'ABSPATH', __DIR__ . '/' );

function wp_upload_dir() {
	global $siteintelix_uploads;
	return array(
		'basedir' => $siteintelix_uploads,
		'baseurl' => 'https://example.test/wp-content/uploads',
	);
}

function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', $path );
}

function trailingslashit( $path ) {
	return rtrim( $path, '/\\' ) . '/';
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_current_blog_id() {
	return 1;
}

function wp_delete_file( $path ) {
	unlink( $path );
}

function wp_mkdir_p( $path ) {
	return is_dir( $path ) || mkdir( $path, 0777, true );
}

function __( $message ) {
	return $message;
}

class WP_Error {
	public function __construct( $code, $message ) {
		unset( $code, $message );
	}
}

function siteintelix_custom_code_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

register_shutdown_function(
	static function () use ( $siteintelix_uploads ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $siteintelix_uploads, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $entry ) {
			$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
		}
		rmdir( $siteintelix_uploads );
	}
);

require_once dirname( __DIR__ ) . '/includes/modules/custom-code/class-siteintelix-custom-code-file-manager.php';

$managed = 'siteintelix/custom-code/site-1-entry-25.css';
$foreign = '2026/07/customer-upload.css';

file_put_contents( $siteintelix_uploads . '/' . $managed, 'body{}' );
file_put_contents( $siteintelix_uploads . '/' . $foreign, 'body{}' );

siteintelix_custom_code_assert( SITEINTELIX_Custom_Code_File_Manager::is_managed_file( $managed ), 'Generated SiteIntelix path is accepted.' );
siteintelix_custom_code_assert( ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file( $foreign ), 'Unrelated upload path is rejected.' );
siteintelix_custom_code_assert( ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file( '../wp-config.php' ), 'Traversal is rejected.' );
siteintelix_custom_code_assert( ! SITEINTELIX_Custom_Code_File_Manager::delete( $foreign ), 'Foreign upload deletion is denied.' );
siteintelix_custom_code_assert( file_exists( $siteintelix_uploads . '/' . $foreign ), 'Foreign upload remains on disk.' );
siteintelix_custom_code_assert( SITEINTELIX_Custom_Code_File_Manager::delete( $managed ), 'Managed file can be deleted.' );
siteintelix_custom_code_assert( ! file_exists( $siteintelix_uploads . '/' . $managed ), 'Managed file is removed.' );
siteintelix_custom_code_assert( '' === SITEINTELIX_Custom_Code_File_Manager::url( $foreign ), 'Foreign path does not receive a public URL.' );
siteintelix_custom_code_assert(
	'https://example.test/wp-content/uploads/siteintelix/custom-code/site-1-entry-25.css'
	=== SITEINTELIX_Custom_Code_File_Manager::url( $managed ),
	'Managed URL is generated from the uploads base URL.'
);

fwrite( STDOUT, "Custom code file manager tests passed.\n" );
