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

	/** @var SITEINTELIX_File_Manager_Backups */
	private $backups;

	/**
	 * Constructor.
	 *
	 * @param SITEINTELIX_File_Manager_Security|null $security Security service.
	 * @param array<string,callable>                 $operations Testable upload operations.
	 * @param SITEINTELIX_File_Manager_Backups|null  $backups Backup service.
	 */
	public function __construct( $security = null, $operations = array(), $backups = null ) {
		$this->security   = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
		$this->operations = is_array( $operations ) ? $operations : array();
		$this->backups    = $backups instanceof SITEINTELIX_File_Manager_Backups ? $backups : new SITEINTELIX_File_Manager_Backups( $this->security );
	}

	/**
	 * Validate and store one uploaded file.
	 *
	 * @param array<string,mixed> $file Uploaded file data.
	 * @param string              $destination Relative destination directory.
	 * @param bool                $overwrite Explicit request to replace an existing file.
	 * @return array<string,mixed>|WP_Error
	 */
	public function store( $file, $destination, $overwrite = false ) {
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
		if ( 1 !== substr_count( $name, '.' ) || '.' === substr( $name, -1 ) || preg_match( '/[。．｡]/u', $name ) ) {
			return $this->error( 'invalid_upload_name', __( 'Uploads must use a filename with one verified extension.', 'siteintelix' ) );
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
		$parent = $this->security->authorize_path( $destination, 'upload' );
		if ( is_wp_error( $parent ) || ! is_dir( $parent ) ) {
			return is_wp_error( $parent ) ? $parent : $this->error( 'invalid_destination', __( 'The upload destination is invalid.', 'siteintelix' ) );
		}
		$target    = trailingslashit( $parent ) . $name;
		$replacing = file_exists( $target ) || is_link( $target );
		if ( $replacing ) {
			if ( empty( $settings['allow_overwrite'] ) || ! $overwrite || ! is_file( $target ) || is_link( $target ) || is_wp_error( $this->security->authorize_path( $target, 'upload' ) ) ) {
				return $this->error( 'destination_exists', __( 'A file with this name already exists and was not replaced.', 'siteintelix' ) );
			}
		} else {
			$target = $this->security->resolve_destination( $destination, $name, 'upload' );
			if ( is_wp_error( $target ) ) {
				return $target;
			}
		}
		$lock = SITEINTELIX_File_Manager_Storage::acquire_lock( 'upload:' . $target );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			if ( $replacing ) {
				$parent = $this->security->authorize_path( $destination, 'upload' );
				if ( is_wp_error( $parent ) ) {
					return $parent;
				}
				$target = trailingslashit( $parent ) . $name;
				if ( ! is_file( $target ) || is_link( $target ) || is_wp_error( $this->security->authorize_path( $target, 'upload' ) ) ) {
					return $this->error( 'destination_changed', __( 'The destination changed before the upload could be stored.', 'siteintelix' ) );
				}
				clearstatcache( true, $target );
				$existing_modified = (int) filemtime( $target );
				$existing_hash     = hash_file( 'sha256', $target );
				$relative_target = $this->security->relative_path( $target );
				$backup = is_wp_error( $relative_target ) ? $relative_target : $this->backups->create( $relative_target, 'upload_overwrite' );
				if ( is_wp_error( $backup ) ) {
					return $this->error( 'backup_failed', __( 'A safety backup could not be created, so the existing file was not replaced.', 'siteintelix' ) );
				}
				try {
					$write_target = dirname( $target ) . '/.siteintelix-upload-' . bin2hex( random_bytes( 8 ) ) . '.tmp';
				} catch ( Exception $exception ) {
					return $this->error( 'upload_failed', __( 'The uploaded file could not be stored safely.', 'siteintelix' ) );
				}
			} else {
				$target = $this->security->resolve_destination( $destination, $name, 'upload' );
				if ( is_wp_error( $target ) ) {
					return $target;
				}
				$write_target = $target;
			}
			$source_handle = fopen( $temp, 'rb' );
			$target_handle = fopen( $write_target, 'x+b' );
			if ( false === $source_handle || false === $target_handle ) {
				if ( is_resource( $source_handle ) ) {
					fclose( $source_handle );
				}
				if ( is_resource( $target_handle ) ) {
					fclose( $target_handle );
				}
				if ( isset( $write_target ) && is_file( $write_target ) && $write_target !== $target ) {
					unlink( $write_target );
				}
				return $this->error( 'upload_failed', __( 'The uploaded file could not be stored.', 'siteintelix' ) );
			}
			$copied = stream_copy_to_stream( $source_handle, $target_handle, $actual_size + 1 );
			fflush( $target_handle );
			fclose( $source_handle );
			fclose( $target_handle );
			chmod( $write_target, 0644 );
			clearstatcache( true, $write_target );
			if ( $copied !== $actual_size || ! is_file( $write_target ) || filesize( $write_target ) !== $actual_size ) {
				if ( is_file( $write_target ) ) {
					unlink( $write_target );
				}
				return $this->error( 'upload_failed', __( 'The uploaded file could not be stored.', 'siteintelix' ) );
			}
			if ( $replacing ) {
				clearstatcache( true, $target );
				$destination_changed = ! is_file( $target )
					|| is_link( $target )
					|| is_wp_error( $this->security->authorize_path( $target, 'upload' ) )
					|| (int) filemtime( $target ) !== $existing_modified
					|| ! hash_equals( $existing_hash, hash_file( 'sha256', $target ) );
				if ( $destination_changed || ! rename( $write_target, $target ) ) {
					unlink( $write_target );
					return $this->error( 'upload_failed', __( 'The existing file could not be replaced safely.', 'siteintelix' ) );
				}
			}
		} finally {
			SITEINTELIX_File_Manager_Storage::release_lock( $lock );
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
