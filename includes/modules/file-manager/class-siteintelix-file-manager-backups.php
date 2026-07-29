<?php
/**
 * File Manager automatic backups.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and restores private, metadata-backed file backups.
 */
class SITEINTELIX_File_Manager_Backups {

	/** @var SITEINTELIX_File_Manager_Security */
	private $security;

	/**
	 * Constructor.
	 *
	 * @param SITEINTELIX_File_Manager_Security|null $security Security service.
	 */
	public function __construct( $security = null ) {
		$this->security = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
	}

	/**
	 * Create a verified backup of an existing file.
	 *
	 * @param string $path Relative path.
	 * @param string $operation Operation.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create( $path, $operation ) {
		$source = $this->security->authorize_path( $path, 'read' );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( ! is_file( $source ) || is_link( $source ) || ! is_readable( $source ) ) {
			return $this->error( 'backup_failed', __( 'A safety backup could not be created.', 'siteintelix' ) );
		}
		$ready = SITEINTELIX_File_Manager_Storage::ensure_directories();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		try {
			$id = gmdate( 'Ymd-His' ) . '-' . (int) get_current_user_id() . '-' . bin2hex( random_bytes( 8 ) );
		} catch ( Exception $exception ) {
			return $this->error( 'backup_failed', __( 'A safety backup could not be created.', 'siteintelix' ) );
		}
		$destination = SITEINTELIX_File_Manager_Storage::path( 'backups/' . $id );
		$copied      = $this->copy_exclusive( $source, $destination );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}
		$relative = $this->security->relative_path( $source );
		$metadata = array(
			'original_path' => is_wp_error( $relative ) ? '' : $relative,
			'created_at'    => gmdate( 'c' ),
			'user_id'       => (int) get_current_user_id(),
			'operation'     => sanitize_key( $operation ),
			'size'          => max( 0, (int) filesize( $source ) ),
			'sha256'        => hash_file( 'sha256', $source ),
		);
		if ( filesize( $destination ) !== $metadata['size'] || hash_file( 'sha256', $destination ) !== $metadata['sha256'] ) {
			unlink( $destination );
			return $this->error( 'backup_failed', __( 'A safety backup could not be verified.', 'siteintelix' ) );
		}
		$written = SITEINTELIX_File_Manager_Storage::write_metadata( 'backups', $id, $metadata );
		if ( is_wp_error( $written ) ) {
			unlink( $destination );
			return $written;
		}
		$metadata['id'] = $id;
		$this->cleanup( $id );
		do_action( 'siteintelix_file_manager_after_operation', 'backup', $metadata['original_path'], 'success' );
		return $metadata;
	}

	/**
	 * List backups for one relative path.
	 *
	 * @param string $path Relative path.
	 * @param int    $limit Maximum results.
	 * @return array<int,array<string,mixed>>
	 */
	public function for_path( $path, $limit = 100 ) {
		$path    = ltrim( wp_normalize_path( (string) $path ), '/' );
		$results = array();
		$files   = glob( SITEINTELIX_File_Manager_Storage::path( 'meta/backups-*.json' ) );
		foreach ( array_slice( is_array( $files ) ? $files : array(), 0, max( 1, min( 500, (int) $limit ) ) ) as $file ) {
			if ( is_link( $file ) || 1 !== preg_match( '/backups-([a-zA-Z0-9_-]+)\.json$/', basename( $file ), $matches ) ) {
				continue;
			}
			$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'backups', $matches[1] );
			if ( is_wp_error( $metadata ) || $metadata['original_path'] !== $path ) {
				continue;
			}
			$metadata['id'] = $matches[1];
			$results[]      = $metadata;
		}
		usort(
			$results,
			static function ( $left, $right ) {
				return strcmp( $right['created_at'], $left['created_at'] );
			}
		);
		return $results;
	}

	/**
	 * Return all valid backups in bounded pages.
	 *
	 * @param int $limit Maximum results.
	 * @return array<int,array<string,mixed>>
	 */
	public function all( $limit = 100 ) {
		$results = array();
		$files   = glob( SITEINTELIX_File_Manager_Storage::path( 'meta/backups-*.json' ) );
		foreach ( array_slice( is_array( $files ) ? $files : array(), 0, max( 1, min( 500, (int) $limit ) ) ) as $file ) {
			if ( 1 !== preg_match( '/backups-([a-zA-Z0-9_-]+)\.json$/', basename( $file ), $matches ) ) {
				continue;
			}
			$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'backups', $matches[1] );
			if ( ! is_wp_error( $metadata ) ) {
				$metadata['id'] = $matches[1];
				$results[]      = $metadata;
			}
		}
		usort( $results, static function ( $left, $right ) { return strcmp( $right['created_at'], $left['created_at'] ); } );
		return $results;
	}

	/**
	 * Remove a bounded number of expired or over-quota backups.
	 *
	 * @param string $preserve_id Newly created backup that must survive this pass.
	 * @return int Number of removed backups.
	 */
	public function cleanup( $preserve_id = '' ) {
		$settings      = SITEINTELIX_File_Manager_Settings::get();
		$day           = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$retention     = max( 1, (int) $settings['backup_retention_days'] ) * $day;
		$per_file      = max( 1, (int) $settings['backup_max_per_file'] );
		$storage_limit = max( MB_IN_BYTES, (int) $settings['backup_max_storage_bytes'] );
		$delete_limit  = max( 1, min( 500, (int) apply_filters( 'siteintelix_file_manager_cleanup_batch_size', 100 ) ) );
		$cutoff        = time() - $retention;
		$path_counts   = array();
		$kept_bytes    = 0;
		$removed       = 0;

		foreach ( $this->all( 500 ) as $backup ) {
			$path = (string) $backup['original_path'];
			$path_counts[ $path ] = isset( $path_counts[ $path ] ) ? $path_counts[ $path ] + 1 : 1;
			$payload = SITEINTELIX_File_Manager_Storage::path( 'backups/' . $backup['id'] );
			$size    = is_file( $payload ) && ! is_link( $payload ) ? max( 0, (int) filesize( $payload ) ) : 0;
			$created = strtotime( (string) $backup['created_at'] );
			$expired = false === $created || $created < $cutoff;
			$over_per_file = $path_counts[ $path ] > $per_file;
			$over_storage  = $kept_bytes + $size > $storage_limit;

			if ( $backup['id'] !== $preserve_id && $removed < $delete_limit && ( $expired || $over_per_file || $over_storage ) ) {
				$deleted = SITEINTELIX_File_Manager_Storage::delete_owned_tree( 'backups', $backup['id'] );
				if ( ! is_wp_error( $deleted ) ) {
					$metadata_deleted = SITEINTELIX_File_Manager_Storage::delete_metadata( 'backups', $backup['id'] );
					if ( ! is_wp_error( $metadata_deleted ) ) {
						++$removed;
						continue;
					}
				}
			}
			$kept_bytes += $size;
		}

		return $removed;
	}

	/**
	 * Restore a backup through the editor's atomic replacement primitive.
	 *
	 * @param string                          $id Backup ID.
	 * @param SITEINTELIX_File_Manager_Editor $editor Editor.
	 * @return array<string,mixed>|WP_Error
	 */
	public function restore( $id, $editor ) {
		$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'backups', $id );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		$payload = SITEINTELIX_File_Manager_Storage::path( 'backups/' . $id );
		if ( ! is_file( $payload ) || is_link( $payload ) || hash_file( 'sha256', $payload ) !== $metadata['sha256'] ) {
			return $this->error( 'invalid_backup', __( 'This backup is invalid and cannot be restored.', 'siteintelix' ) );
		}
		return $editor->replace_from_file( $metadata['original_path'], $payload, 'backup_restore' );
	}

	/**
	 * Stream one verified private backup.
	 *
	 * @param string $id Backup ID.
	 * @return true|WP_Error
	 */
	public function stream( $id ) {
		$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'backups', $id );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		$payload = SITEINTELIX_File_Manager_Storage::path( 'backups/' . $id );
		if ( ! is_file( $payload ) || is_link( $payload ) || ! is_readable( $payload ) || (int) filesize( $payload ) !== (int) $metadata['size'] || ! hash_equals( (string) $metadata['sha256'], hash_file( 'sha256', $payload ) ) ) {
			return $this->error( 'invalid_backup', __( 'This backup is invalid and cannot be downloaded.', 'siteintelix' ) );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		$name = str_replace( array( "\r", "\n", '"' ), '', basename( (string) $metadata['original_path'] ) );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $payload ) );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		$handle = fopen( $payload, 'rb' );
		if ( false === $handle ) {
			return $this->error( 'invalid_backup', __( 'This backup could not be downloaded.', 'siteintelix' ) );
		}
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 65536 );
			if ( false === $chunk ) {
				fclose( $handle );
				return $this->error( 'download_failed', __( 'The backup download could not be completed.', 'siteintelix' ) );
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated verified backup stream.
			flush();
		}
		fclose( $handle );
		return true;
	}

	/**
	 * Copy a file to an exclusive destination.
	 *
	 * @param string $source Source.
	 * @param string $destination Destination.
	 * @return true|WP_Error
	 */
	private function copy_exclusive( $source, $destination ) {
		$input  = fopen( $source, 'rb' );
		$output = fopen( $destination, 'x+b' );
		if ( false === $input || false === $output ) {
			if ( is_resource( $input ) ) {
				fclose( $input );
			}
			if ( is_resource( $output ) ) {
				fclose( $output );
			}
			return $this->error( 'backup_failed', __( 'A safety backup could not be created.', 'siteintelix' ) );
		}
		$copied = stream_copy_to_stream( $input, $output );
		fflush( $output );
		fclose( $input );
		fclose( $output );
		if ( false === $copied || $copied !== filesize( $source ) ) {
			unlink( $destination );
			return $this->error( 'backup_failed', __( 'A safety backup could not be created.', 'siteintelix' ) );
		}
		chmod( $destination, 0640 );
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
