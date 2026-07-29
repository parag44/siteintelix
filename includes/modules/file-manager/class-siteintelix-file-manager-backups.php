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
