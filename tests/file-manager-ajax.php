<?php
/**
 * Structural tests for File Manager request handlers.
 *
 * @package SiteIntelix
 */

$file = dirname( __DIR__ ) . '/includes/modules/file-manager/class-siteintelix-file-manager-ajax.php';
if ( ! file_exists( $file ) ) {
	fwrite( STDERR, "FAIL: File Manager AJAX class exists\n" );
	exit( 1 );
}
$source = file_get_contents( $file );

$expected = array(
	'siteintelix_fm_list_directory',
	'siteintelix_fm_get_file',
	'siteintelix_fm_get_details',
	'siteintelix_fm_save_file',
	'siteintelix_fm_upload_files',
	'siteintelix_fm_create_file',
	'siteintelix_fm_create_directory',
	'siteintelix_fm_rename_item',
	'siteintelix_fm_trash_item',
	'siteintelix_fm_list_trash',
	'siteintelix_fm_restore_item',
	'siteintelix_fm_permanently_delete_item',
	'siteintelix_fm_list_backups',
	'siteintelix_fm_restore_backup',
);

foreach ( $expected as $action ) {
	if ( false === strpos( $source, "wp_ajax_{$action}" ) ) {
		fwrite( STDERR, "FAIL: missing handler {$action}\n" );
		exit( 1 );
	}
	if ( false === strpos( $source, "'{$action}'" ) ) {
		fwrite( STDERR, "FAIL: missing nonce action {$action}\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $source, 'wp_ajax_nopriv_' ) ) {
	fwrite( STDERR, "FAIL: unauthenticated handlers are forbidden\n" );
	exit( 1 );
}
if ( false !== strpos( $source, 'run_file_operation' ) || false !== strpos( $source, 'run_operation' ) ) {
	fwrite( STDERR, "FAIL: generic operation endpoints are forbidden\n" );
	exit( 1 );
}
if ( ! preg_match( '/function authorize\([\s\S]*is_user_logged_in\(\)[\s\S]*current_user_can_manage\(\)[\s\S]*check_ajax_referer/', $source ) ) {
	fwrite( STDERR, "FAIL: request guard order is incomplete\n" );
	exit( 1 );
}
if ( false === strpos( $source, 'admin_post_siteintelix_fm_download_file' ) || false === strpos( $source, 'admin_post_siteintelix_fm_preview_image' ) || false === strpos( $source, 'admin_post_siteintelix_save_file_manager_settings' ) ) {
	fwrite( STDERR, "FAIL: download, image preview, or settings handler is missing\n" );
	exit( 1 );
}

echo "File Manager AJAX tests passed.\n";
