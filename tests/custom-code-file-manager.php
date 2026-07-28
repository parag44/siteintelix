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
siteintelix_custom_code_assert(
	method_exists( 'SITEINTELIX_Custom_Code_File_Manager', 'is_managed_file_for_entry' )
	&& SITEINTELIX_Custom_Code_File_Manager::is_managed_file_for_entry( $managed, 25, 'css' ),
	'Managed files are bound to their expected entry ID and type.'
);
siteintelix_custom_code_assert(
	method_exists( 'SITEINTELIX_Custom_Code_File_Manager', 'is_managed_file_for_entry' )
	&& ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file_for_entry( $managed, 26, 'css' )
	&& ! SITEINTELIX_Custom_Code_File_Manager::is_managed_file_for_entry( $managed, 25, 'javascript' ),
	'A managed filename cannot be reused by another entry or code type.'
);

$symlink_target = $siteintelix_uploads . '/' . $foreign;
$symlink_path   = $siteintelix_uploads . '/' . $managed;
$linked         = function_exists( 'symlink' ) && @symlink( $symlink_target, $symlink_path );

if ( $linked ) {
	$target_contents = file_get_contents( $symlink_target );
	siteintelix_custom_code_assert( '' === SITEINTELIX_Custom_Code_File_Manager::path( $managed ), 'Managed-path resolution rejects symlinks.' );
	siteintelix_custom_code_assert( ! SITEINTELIX_Custom_Code_File_Manager::delete( $managed ), 'Managed-file deletion preserves symlinks.' );
	siteintelix_custom_code_assert( is_link( $symlink_path ), 'The managed-name symlink remains in place.' );
	siteintelix_custom_code_assert( $target_contents === file_get_contents( $symlink_target ), 'The symlink target remains unchanged.' );

	$write_result = SITEINTELIX_Custom_Code_File_Manager::write(
		array(
			'id'        => 25,
			'code_type' => 'css',
			'code'      => 'body{color:red}',
		)
	);
	siteintelix_custom_code_assert( $write_result instanceof WP_Error, 'Generated-file writes reject an existing symlink.' );
	siteintelix_custom_code_assert( $target_contents === file_get_contents( $symlink_target ), 'Rejected writes cannot overwrite a symlink target.' );

	unlink( $symlink_path );
	$managed_directory = dirname( $symlink_path );
	rmdir( $managed_directory );
	$linked_directory = @symlink( dirname( $symlink_target ), $managed_directory );

	if ( $linked_directory ) {
		$linked_output = dirname( $symlink_target ) . '/site-1-entry-25.css';
		siteintelix_custom_code_assert( '' === SITEINTELIX_Custom_Code_File_Manager::path( $managed ), 'Managed-path resolution rejects a symlinked directory.' );
		$write_result = SITEINTELIX_Custom_Code_File_Manager::write(
			array(
				'id'        => 25,
				'code_type' => 'css',
				'code'      => 'body{color:blue}',
			)
		);
		siteintelix_custom_code_assert( $write_result instanceof WP_Error, 'Generated-file writes reject a symlinked directory.' );
		siteintelix_custom_code_assert( ! file_exists( $linked_output ), 'Rejected writes cannot create files through a symlinked directory.' );

		unlink( $managed_directory );
		mkdir( $managed_directory, 0777, true );
	}
}

fwrite( STDOUT, "Custom code file manager tests passed.\n" );
