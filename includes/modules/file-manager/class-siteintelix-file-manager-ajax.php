<?php
/**
 * File Manager operation-specific request handlers.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers narrowly scoped authenticated handlers.
 */
class SITEINTELIX_File_Manager_Ajax {

	/**
	 * Register request handlers.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_siteintelix_fm_list_directory', array( __CLASS__, 'list_directory' ) );
		add_action( 'wp_ajax_siteintelix_fm_get_file', array( __CLASS__, 'get_file' ) );
		add_action( 'wp_ajax_siteintelix_fm_get_details', array( __CLASS__, 'get_details' ) );
		add_action( 'wp_ajax_siteintelix_fm_save_file', array( __CLASS__, 'save_file' ) );
		add_action( 'wp_ajax_siteintelix_fm_upload_files', array( __CLASS__, 'upload_files' ) );
		add_action( 'wp_ajax_siteintelix_fm_create_file', array( __CLASS__, 'create_file' ) );
		add_action( 'wp_ajax_siteintelix_fm_create_directory', array( __CLASS__, 'create_directory' ) );
		add_action( 'wp_ajax_siteintelix_fm_rename_item', array( __CLASS__, 'rename_item' ) );
		add_action( 'wp_ajax_siteintelix_fm_trash_item', array( __CLASS__, 'trash_item' ) );
		add_action( 'wp_ajax_siteintelix_fm_list_trash', array( __CLASS__, 'list_trash' ) );
		add_action( 'wp_ajax_siteintelix_fm_restore_item', array( __CLASS__, 'restore_item' ) );
		add_action( 'wp_ajax_siteintelix_fm_permanently_delete_item', array( __CLASS__, 'permanently_delete_item' ) );
		add_action( 'wp_ajax_siteintelix_fm_list_backups', array( __CLASS__, 'list_backups' ) );
		add_action( 'wp_ajax_siteintelix_fm_restore_backup', array( __CLASS__, 'restore_backup' ) );
		add_action( 'admin_post_siteintelix_fm_download_file', array( __CLASS__, 'download_file' ) );
		add_action( 'admin_post_siteintelix_save_file_manager_settings', array( __CLASS__, 'save_settings' ) );
	}

	/**
	 * List one directory.
	 *
	 * @return void
	 */
	public static function list_directory() {
		self::authorize( 'siteintelix_fm_list_directory' );
		$args = array(
			'page'     => self::post_int( 'page', 1 ),
			'per_page' => self::post_int( 'per_page', 50 ),
			'sort'     => self::post_key( 'sort', 'name' ),
			'order'    => self::post_key( 'order', 'asc' ),
			'search'   => self::post_text( 'search' ),
		);
		self::respond( ( new SITEINTELIX_File_Manager_Filesystem() )->list_directory( self::post_path(), $args ) );
	}

	/**
	 * Preview or open one approved file.
	 *
	 * @return void
	 */
	public static function get_file() {
		self::authorize( 'siteintelix_fm_get_file' );
		$path = self::post_path();
		if ( '1' === self::post_text( 'edit' ) ) {
			self::respond( ( new SITEINTELIX_File_Manager_Editor() )->open( $path ) );
		}
		self::respond( ( new SITEINTELIX_File_Manager_Filesystem() )->preview( $path ) );
	}

	/**
	 * Return metadata and optional hashes.
	 *
	 * @return void
	 */
	public static function get_details() {
		self::authorize( 'siteintelix_fm_get_details' );
		self::respond( ( new SITEINTELIX_File_Manager_Filesystem() )->details( self::post_path(), '1' === self::post_text( 'hashes' ) ) );
	}

	/**
	 * Save an approved text file.
	 *
	 * @return void
	 */
	public static function save_file() {
		self::authorize( 'siteintelix_fm_save_file' );
		$path   = self::post_path();
		$result = ( new SITEINTELIX_File_Manager_Editor() )->save(
			$path,
			self::post_raw( 'content' ),
			self::post_int( 'modified', 0 ),
			self::post_text( 'sha256' )
		);
		self::audit_result( 'edit', $path, $result );
		self::respond( $result );
	}

	/**
	 * Upload independently validated files.
	 *
	 * @return void
	 */
	public static function upload_files() {
		self::authorize( 'siteintelix_fm_upload_files' );
		$destination = self::post_path( 'destination' );
		$files       = self::normalize_uploads( isset( $_FILES['files'] ) ? $_FILES['files'] : array() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() verified the nonce.
		$uploader    = new SITEINTELIX_File_Manager_Upload();
		$stored      = array();
		$errors      = array();
		foreach ( $files as $file ) {
			$result = $uploader->store( $file, $destination );
			if ( is_wp_error( $result ) ) {
				$errors[] = array( 'name' => sanitize_file_name( isset( $file['name'] ) ? $file['name'] : '' ), 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
			} else {
				$stored[] = $result;
				SITEINTELIX_File_Manager_Audit::record( 'upload', $result['path'], 'success', '' );
			}
		}
		wp_send_json_success( array( 'files' => $stored, 'errors' => $errors ) );
	}

	/**
	 * Create an approved text file.
	 *
	 * @return void
	 */
	public static function create_file() {
		self::authorize( 'siteintelix_fm_create_file' );
		$parent = self::post_path( 'parent' );
		$result = ( new SITEINTELIX_File_Manager_Filesystem() )->create_file( $parent, self::post_text( 'name' ), self::post_raw( 'content' ) );
		self::audit_result( 'create_file', $parent, $result );
		self::respond( $result );
	}

	/**
	 * Create an approved directory.
	 *
	 * @return void
	 */
	public static function create_directory() {
		self::authorize( 'siteintelix_fm_create_directory' );
		$parent = self::post_path( 'parent' );
		$result = ( new SITEINTELIX_File_Manager_Filesystem() )->create_directory( $parent, self::post_text( 'name' ) );
		self::audit_result( 'create_directory', $parent, $result );
		self::respond( $result );
	}

	/**
	 * Rename an item in its current directory.
	 *
	 * @return void
	 */
	public static function rename_item() {
		self::authorize( 'siteintelix_fm_rename_item' );
		$path   = self::post_path();
		$result = ( new SITEINTELIX_File_Manager_Filesystem() )->rename_item( $path, self::post_text( 'name' ) );
		self::audit_result( 'rename', $path, $result );
		self::respond( $result );
	}

	/**
	 * Move an item to private trash.
	 *
	 * @return void
	 */
	public static function trash_item() {
		self::authorize( 'siteintelix_fm_trash_item' );
		$path   = self::post_path();
		$result = ( new SITEINTELIX_File_Manager_Trash() )->trash( $path, '1' === self::post_text( 'confirmed_non_empty' ) );
		self::audit_result( 'trash', $path, $result );
		self::respond( $result );
	}

	/**
	 * List private trash.
	 *
	 * @return void
	 */
	public static function list_trash() {
		self::authorize( 'siteintelix_fm_list_trash' );
		self::respond( array( 'items' => ( new SITEINTELIX_File_Manager_Trash() )->all( self::post_int( 'limit', 100 ) ) ) );
	}

	/**
	 * Restore one trash item.
	 *
	 * @return void
	 */
	public static function restore_item() {
		self::authorize( 'siteintelix_fm_restore_item' );
		$id     = self::post_identifier( 'id' );
		$result = ( new SITEINTELIX_File_Manager_Trash() )->restore( $id );
		self::audit_result( 'restore', 'trash/' . $id, $result );
		self::respond( $result );
	}

	/**
	 * Permanently delete one private trash item.
	 *
	 * @return void
	 */
	public static function permanently_delete_item() {
		self::authorize( 'siteintelix_fm_permanently_delete_item' );
		$id     = self::post_identifier( 'id' );
		$result = ( new SITEINTELIX_File_Manager_Trash() )->permanently_delete( $id );
		self::audit_result( 'permanent_delete', 'trash/' . $id, $result );
		self::respond( $result );
	}

	/**
	 * List private backups.
	 *
	 * @return void
	 */
	public static function list_backups() {
		self::authorize( 'siteintelix_fm_list_backups' );
		self::respond( array( 'items' => ( new SITEINTELIX_File_Manager_Backups() )->all( self::post_int( 'limit', 100 ) ) ) );
	}

	/**
	 * Restore one backup.
	 *
	 * @return void
	 */
	public static function restore_backup() {
		self::authorize( 'siteintelix_fm_restore_backup' );
		$id      = self::post_identifier( 'id' );
		$backups = new SITEINTELIX_File_Manager_Backups();
		$result  = $backups->restore( $id, new SITEINTELIX_File_Manager_Editor( null, $backups ) );
		self::audit_result( 'backup_restore', 'backups/' . $id, $result );
		self::respond( $result );
	}

	/**
	 * Stream one authorized download.
	 *
	 * @return void
	 */
	public static function download_file() {
		if ( ! is_user_logged_in() || ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'siteintelix' ) );
		}
		check_admin_referer( 'siteintelix_fm_download_file' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_admin_referer() verified this request.
		$path   = isset( $_GET['path'] ) ? wp_unslash( $_GET['path'] ) : '';
		$result = ( new SITEINTELIX_File_Manager_Filesystem() )->stream_file( is_string( $path ) ? $path : '' );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		exit;
	}

	/**
	 * Save File Manager settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		if ( ! is_user_logged_in() || ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to change File Manager settings.', 'siteintelix' ) );
		}
		check_admin_referer( 'siteintelix_save_file_manager_settings' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() verified this request.
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		SITEINTELIX_File_Manager_Settings::save( is_array( $settings ) ? $settings : array() );
		SITEINTELIX_File_Manager_Audit::record( 'settings', 'wp-content', 'success', '' );
		$url = add_query_arg(
			array(
				'page'                       => 'siteintelix-settings',
				'tab'                        => 'file_manager',
				'siteintelix_settings_saved' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url . '#siteintelix-file-manager-settings' );
		exit;
	}

	/**
	 * Enforce logged-in capability and an action-specific AJAX nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private static function authorize( $nonce_action ) {
		if ( ! is_user_logged_in() || ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
			wp_send_json_error(
				array( 'code' => 'forbidden', 'message' => __( 'You do not have permission to perform this action.', 'siteintelix' ) ),
				403
			);
		}
		check_ajax_referer( $nonce_action, 'nonce' );
	}

	/**
	 * Send a safe service response.
	 *
	 * @param mixed $result Result.
	 * @return void
	 */
	private static function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success( $result );
	}

	/**
	 * Audit one result without recording content.
	 *
	 * @param string $operation Operation.
	 * @param string $path Relative path or owned identifier.
	 * @param mixed  $result Result.
	 * @return void
	 */
	private static function audit_result( $operation, $path, $result ) {
		SITEINTELIX_File_Manager_Audit::record( $operation, ltrim( wp_normalize_path( $path ), '/' ), is_wp_error( $result ) ? 'failure' : 'success', is_wp_error( $result ) ? $result->get_error_code() : '' );
	}

	/**
	 * Read an unslashed path field.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_path( $key = 'path' ) {
		return self::post_raw( $key );
	}

	/**
	 * Read raw unslashed text.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_raw( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize() is called before input access in every AJAX handler.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Read sanitized text.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_text( $key ) {
		return sanitize_text_field( self::post_raw( $key ) );
	}

	/**
	 * Read a sanitized key.
	 *
	 * @param string $key Key.
	 * @param string $default Default.
	 * @return string
	 */
	private static function post_key( $key, $default ) {
		$value = sanitize_key( self::post_raw( $key ) );
		return '' === $value ? $default : $value;
	}

	/**
	 * Read an integer.
	 *
	 * @param string $key Key.
	 * @param int    $default Default.
	 * @return int
	 */
	private static function post_int( $key, $default ) {
		$value = self::post_raw( $key );
		return '' === $value ? $default : absint( $value );
	}

	/**
	 * Read an owned identifier.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function post_identifier( $key ) {
		$value = self::post_raw( $key );
		return 1 === preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value ) ? $value : '';
	}

	/**
	 * Normalize a multiple-file upload array.
	 *
	 * @param mixed $files Raw files.
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_uploads( $files ) {
		if ( ! is_array( $files ) || ! isset( $files['name'] ) ) {
			return array();
		}
		if ( ! is_array( $files['name'] ) ) {
			return array( $files );
		}
		$output = array();
		foreach ( array_keys( $files['name'] ) as $index ) {
			$output[] = array(
				'name'     => isset( $files['name'][ $index ] ) ? $files['name'][ $index ] : '',
				'type'     => isset( $files['type'][ $index ] ) ? $files['type'][ $index ] : '',
				'tmp_name' => isset( $files['tmp_name'][ $index ] ) ? $files['tmp_name'][ $index ] : '',
				'error'    => isset( $files['error'][ $index ] ) ? $files['error'][ $index ] : UPLOAD_ERR_NO_FILE,
				'size'     => isset( $files['size'][ $index ] ) ? $files['size'][ $index ] : 0,
			);
		}
		return $output;
	}
}
