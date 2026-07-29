<?php
/**
 * File Manager atomic text editor.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opens and atomically replaces approved non-PHP text files.
 */
class SITEINTELIX_File_Manager_Editor {

	/** @var SITEINTELIX_File_Manager_Security */
	private $security;

	/** @var object */
	private $backups;

	/** @var array<string,callable> */
	private $operations;

	/**
	 * Constructor.
	 *
	 * @param SITEINTELIX_File_Manager_Security|null $security Security service.
	 * @param object|null                            $backups Backup service.
	 * @param array<string,callable>                 $operations Testable filesystem operations.
	 */
	public function __construct( $security = null, $backups = null, $operations = array() ) {
		$this->security   = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
		$this->backups    = is_object( $backups ) ? $backups : new SITEINTELIX_File_Manager_Backups( $this->security );
		$this->operations = is_array( $operations ) ? $operations : array();
	}

	/**
	 * Open an editable text file.
	 *
	 * @param string $path Relative path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function open( $path ) {
		$file = $this->editable_file( $path );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$size    = max( 0, (int) filesize( $file ) );
		$maximum = (int) SITEINTELIX_File_Manager_Settings::get()['edit_max_bytes'];
		if ( $size > $maximum ) {
			return $this->error( 'file_too_large', __( 'This file is too large to edit in the browser.', 'siteintelix' ) );
		}
		$content = file_get_contents( $file );
		if ( false === $content || false !== strpos( $content, "\0" ) ) {
			return $this->error( 'not_text', __( 'This file is not an editable text file.', 'siteintelix' ) );
		}
		$relative = $this->security->relative_path( $file );
		return array(
			'path'      => is_wp_error( $relative ) ? '' : $relative,
			'content'   => $content,
			'extension' => strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ),
			'modified'  => (int) filemtime( $file ),
			'sha256'    => hash_file( 'sha256', $file ),
			'size'      => $size,
		);
	}

	/**
	 * Save content after stale-state and backup checks.
	 *
	 * @param string $path Relative path.
	 * @param string $content Replacement content.
	 * @param int    $expected_modified Expected modification time.
	 * @param string $expected_hash Expected SHA-256.
	 * @return array<string,mixed>|WP_Error
	 */
	public function save( $path, $content, $expected_modified, $expected_hash ) {
		$file = $this->editable_file( $path );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$settings = SITEINTELIX_File_Manager_Settings::get();
		if ( strlen( $content ) > (int) $settings['edit_max_bytes'] ) {
			return $this->error( 'file_too_large', __( 'This file is too large to edit in the browser.', 'siteintelix' ) );
		}
		$lock = SITEINTELIX_File_Manager_Storage::acquire_lock( 'mutation:global' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$file = $this->editable_file( $path );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			clearstatcache( true, $file );
			if ( (int) filemtime( $file ) !== (int) $expected_modified || ! hash_equals( hash_file( 'sha256', $file ), (string) $expected_hash ) ) {
				return $this->error( 'stale_file', __( 'The file changed after you opened it. Reload it before saving.', 'siteintelix' ) );
			}
			if ( ! is_writable( $file ) || ! is_writable( dirname( $file ) ) ) {
				return $this->error( 'not_writable', __( 'This file is not writable.', 'siteintelix' ) );
			}
			$backup = $this->backups->create( $path, 'edit' );
			if ( is_wp_error( $backup ) ) {
				return $this->error( 'backup_failed', __( 'A safety backup could not be created, so the file was not changed.', 'siteintelix' ) );
			}
			clearstatcache( true, $file );
			if ( is_wp_error( $this->security->authorize_path( $path, 'edit' ) ) || (int) filemtime( $file ) !== (int) $expected_modified || ! hash_equals( hash_file( 'sha256', $file ), (string) $expected_hash ) ) {
				return $this->error( 'stale_file', __( 'The file changed before it could be saved. Reload it and try again.', 'siteintelix' ) );
			}
			$written = $this->atomic_replace( $file, $content );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
			clearstatcache( true, $file );
			do_action( 'siteintelix_file_manager_file_saved', $path, (int) get_current_user_id() );
			return array(
				'modified' => (int) filemtime( $file ),
				'sha256'   => hash_file( 'sha256', $file ),
				'size'     => max( 0, (int) filesize( $file ) ),
				'backup'   => $backup,
			);
		} finally {
			SITEINTELIX_File_Manager_Storage::release_lock( $lock );
		}
	}

	/**
	 * Atomically replace a target from a verified private source.
	 *
	 * @param string $path Relative target.
	 * @param string $source Private source.
	 * @param string $operation Operation.
	 * @return array<string,mixed>|WP_Error
	 */
	public function replace_from_file( $path, $source, $operation ) {
		$file = $this->editable_file( $path );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_file( $source ) || is_link( $source ) || ! is_readable( $source ) ) {
			return $this->error( 'invalid_source', __( 'The replacement file is invalid.', 'siteintelix' ) );
		}
		$lock = SITEINTELIX_File_Manager_Storage::acquire_lock( 'mutation:global' );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			$file = $this->editable_file( $path );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			$backup = $this->backups->create( $path, $operation );
			if ( is_wp_error( $backup ) ) {
				return $this->error( 'backup_failed', __( 'A safety backup could not be created, so the file was not changed.', 'siteintelix' ) );
			}
			if ( ! is_file( $source ) || is_link( $source ) || ! is_readable( $source ) || is_wp_error( $this->security->authorize_path( $path, 'edit' ) ) ) {
				return $this->error( 'invalid_source', __( 'The replacement file is invalid.', 'siteintelix' ) );
			}
			$content = file_get_contents( $source );
			if ( false === $content ) {
				return $this->error( 'invalid_source', __( 'The replacement file is invalid.', 'siteintelix' ) );
			}
			$result = $this->atomic_replace( $file, $content );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'modified' => (int) filemtime( $file ), 'sha256' => hash_file( 'sha256', $file ), 'backup' => $backup );
		} finally {
			SITEINTELIX_File_Manager_Storage::release_lock( $lock );
		}
	}

	/**
	 * Resolve and validate an editable path.
	 *
	 * @param string $path Relative path.
	 * @return string|WP_Error
	 */
	private function editable_file( $path ) {
		$settings = SITEINTELIX_File_Manager_Settings::get();
		if ( empty( $settings['editing_enabled'] ) ) {
			return $this->error( 'editing_disabled', __( 'File editing is disabled in File Manager settings.', 'siteintelix' ) );
		}
		$file = $this->security->authorize_path( $path, 'edit' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_file( $file ) || is_link( $file ) ) {
			return $this->error( 'invalid_file', __( 'This file cannot be edited.', 'siteintelix' ) );
		}
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( 'php' === $extension || ! in_array( $extension, (array) $settings['editable_extensions'], true ) ) {
			return $this->error( 'invalid_extension', __( 'This file type is view-only in Safe Mode.', 'siteintelix' ) );
		}
		return $file;
	}

	/**
	 * Write and atomically install complete replacement content.
	 *
	 * @param string $target Target.
	 * @param string $content Content.
	 * @return true|WP_Error
	 */
	private function atomic_replace( $target, $content ) {
		try {
			$temp = dirname( $target ) . '/.siteintelix-edit-' . bin2hex( random_bytes( 8 ) ) . '.tmp';
		} catch ( Exception $exception ) {
			return $this->error( 'atomic_write_failed', __( 'The file could not be saved safely.', 'siteintelix' ) );
		}
		$handle = fopen( $temp, 'x+b' );
		if ( false === $handle ) {
			return $this->error( 'atomic_write_failed', __( 'The file could not be saved safely.', 'siteintelix' ) );
		}
		$length  = strlen( $content );
		$written = 0;
		while ( $written < $length ) {
			$chunk = fwrite( $handle, substr( $content, $written ) );
			if ( false === $chunk || 0 === $chunk ) {
				fclose( $handle );
				unlink( $temp );
				return $this->error( 'atomic_write_failed', __( 'The file could not be saved safely.', 'siteintelix' ) );
			}
			$written += $chunk;
		}
		fflush( $handle );
		fclose( $handle );
		$mode = fileperms( $target );
		if ( false !== $mode ) {
			chmod( $temp, $mode & 0777 );
		}
		$renamed = isset( $this->operations['rename'] ) ? call_user_func( $this->operations['rename'], $temp, $target ) : rename( $temp, $target );
		if ( ! $renamed ) {
			unlink( $temp );
			return $this->error( 'atomic_write_failed', __( 'The file could not be saved safely.', 'siteintelix' ) );
		}
		return true;
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
