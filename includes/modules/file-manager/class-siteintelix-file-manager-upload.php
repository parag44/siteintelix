<?php
/**
 * File Manager upload service.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates and stores uploads in authorized destinations.
 */
class SITEINTELIX_File_Manager_Upload {

	/** @var SITEINTELIX_File_Manager_Security */
	private $security;

	/** @var array<string,callable> */
	private $operations;

	/**
	 * Constructor.
	 *
	 * @param SITEINTELIX_File_Manager_Security|null $security Security service.
	 * @param array<string,callable>                 $operations Testable upload operations.
	 */
	public function __construct( $security = null, $operations = array() ) {
		$this->security   = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
		$this->operations = is_array( $operations ) ? $operations : array();
	}

	/**
	 * Validate and store one uploaded file.
	 *
	 * @param array<string,mixed> $file Uploaded file data.
	 * @param string              $destination Relative destination directory.
	 * @return array<string,mixed>|WP_Error
	 */
	public function store( $file, $destination ) {
		$settings = SITEINTELIX_File_Manager_Settings::get();
		if ( empty( $settings['uploads_enabled'] ) ) {
			return $this->error( 'uploads_disabled', __( 'File uploads are disabled in File Manager settings.', 'siteintelix' ) );
		}
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( isset( $file['error'] ) ? $file['error'] : -1 ) ) {
			return $this->error( 'upload_failed', __( 'The file upload did not complete.', 'siteintelix' ) );
		}
		$raw_name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$flat     = str_replace( '\\', '/', $raw_name );
		$name     = sanitize_file_name( $raw_name );
		if ( '' === $name || $flat !== basename( $flat ) || $name !== basename( $name ) || false !== strpos( $name, "\0" ) ) {
			return $this->error( 'invalid_upload_name', __( 'This filename is not permitted.', 'siteintelix' ) );
		}
		if ( preg_match( '/(?:^|\.)(?:php\d*|phtml|phar|cgi|pl|py|sh|bash|exe|dll|so|ini|htaccess)(?:\.|$)/i', $name ) ) {
			return $this->error( 'invalid_upload_name', __( 'This filename is not permitted.', 'siteintelix' ) );
		}
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, (array) $settings['upload_extensions'], true ) ) {
			return $this->error( 'invalid_upload_type', __( 'This file type is not permitted for upload.', 'siteintelix' ) );
		}
		if ( 'zip' === $extension && empty( $settings['allow_archive_uploads'] ) ) {
			return $this->error( 'archive_upload_disabled', __( 'Archive uploads are disabled.', 'siteintelix' ) );
		}
		$temp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$is_uploaded = isset( $this->operations['is_uploaded_file'] ) ? call_user_func( $this->operations['is_uploaded_file'], $temp ) : is_uploaded_file( $temp );
		if ( ! $is_uploaded || ! is_file( $temp ) || is_link( $temp ) ) {
			return $this->error( 'invalid_upload', __( 'The uploaded file could not be verified.', 'siteintelix' ) );
		}
		$actual_size = max( 0, (int) filesize( $temp ) );
		if ( $actual_size < 1 || $actual_size > (int) $settings['upload_max_bytes'] || (int) $file['size'] !== $actual_size ) {
			return $this->error( 'upload_too_large', __( 'The uploaded file exceeds the permitted size.', 'siteintelix' ) );
		}
		$checked = wp_check_filetype_and_ext( $temp, $name );
		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) || strtolower( $checked['ext'] ) !== $extension ) {
			return $this->error( 'invalid_upload_type', __( 'The uploaded file type could not be verified.', 'siteintelix' ) );
		}
		$target = $this->security->resolve_destination( $destination, $name, 'upload' );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$moved = isset( $this->operations['move_uploaded_file'] ) ? call_user_func( $this->operations['move_uploaded_file'], $temp, $target ) : move_uploaded_file( $temp, $target );
		if ( ! $moved || ! is_file( $target ) || filesize( $target ) !== $actual_size ) {
			if ( is_file( $target ) ) {
				unlink( $target );
			}
			return $this->error( 'upload_failed', __( 'The uploaded file could not be stored.', 'siteintelix' ) );
		}
		$relative = $this->security->relative_path( $target );
		do_action( 'siteintelix_file_manager_file_uploaded', is_wp_error( $relative ) ? '' : $relative, (int) get_current_user_id() );
		return array(
			'name' => $name,
			'path' => is_wp_error( $relative ) ? '' : $relative,
			'size' => $actual_size,
			'mime' => $checked['type'],
		);
	}

	/**
	 * Create an error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
