<?php
/**
 * File Manager private trash.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Moves authorized items into private, metadata-backed trash.
 */
class SITEINTELIX_File_Manager_Trash {

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
	 * Move a file or directory to private trash.
	 *
	 * @param string $path Relative path.
	 * @param bool   $confirmed_non_empty Whether a non-empty directory was explicitly confirmed.
	 * @return array<string,mixed>|WP_Error
	 */
	public function trash( $path, $confirmed_non_empty = false ) {
		$source = $this->security->authorize_path( $path, 'trash' );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( is_link( $source ) || ! file_exists( $source ) ) {
			return $this->error( 'invalid_item', __( 'This item cannot be moved to trash.', 'siteintelix' ) );
		}
		$is_directory = is_dir( $source );
		if ( $is_directory && $this->directory_has_entries( $source ) && ! $confirmed_non_empty ) {
			return $this->error( 'confirmation_required', __( 'Confirm deletion of this non-empty directory before continuing.', 'siteintelix' ) );
		}
		$ready = SITEINTELIX_File_Manager_Storage::ensure_directories();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		try {
			$id = gmdate( 'Ymd-His' ) . '-' . (int) get_current_user_id() . '-' . bin2hex( random_bytes( 8 ) );
		} catch ( Exception $exception ) {
			return $this->error( 'trash_failed', __( 'This item could not be moved to trash.', 'siteintelix' ) );
		}
		$payload = SITEINTELIX_File_Manager_Storage::path( 'trash/' . $id );
		if ( file_exists( $payload ) || is_link( $payload ) || ! rename( $source, $payload ) ) {
			return $this->error( 'trash_failed', __( 'This item could not be moved to trash.', 'siteintelix' ) );
		}
		$relative = $this->security->relative_path( $source );
		$metadata = array(
			'original_path' => is_wp_error( $relative ) ? '' : $relative,
			'created_at'    => gmdate( 'c' ),
			'user_id'       => (int) get_current_user_id(),
			'operation'     => 'trash',
			'size'          => $is_directory ? 0 : max( 0, (int) filesize( $payload ) ),
			'sha256'        => $is_directory ? '' : hash_file( 'sha256', $payload ),
			'type'          => $is_directory ? 'directory' : 'file',
		);
		$written = SITEINTELIX_File_Manager_Storage::write_metadata( 'trash', $id, $metadata );
		if ( is_wp_error( $written ) ) {
			if ( ! file_exists( $source ) ) {
				rename( $payload, $source );
			}
			return $written;
		}
		$metadata['id'] = $id;
		do_action( 'siteintelix_file_manager_item_trashed', $metadata['original_path'], (int) get_current_user_id() );
		return $metadata;
	}

	/**
	 * List valid trash entries.
	 *
	 * @param int $limit Maximum entries.
	 * @return array<int,array<string,mixed>>
	 */
	public function all( $limit = 100 ) {
		$results = array();
		$files   = glob( SITEINTELIX_File_Manager_Storage::path( 'meta/trash-*.json' ) );
		foreach ( array_slice( is_array( $files ) ? $files : array(), 0, max( 1, min( 500, (int) $limit ) ) ) as $file ) {
			if ( 1 !== preg_match( '/trash-([a-zA-Z0-9_-]+)\.json$/', basename( $file ), $matches ) ) {
				continue;
			}
			$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'trash', $matches[1] );
			$payload  = SITEINTELIX_File_Manager_Storage::path( 'trash/' . $matches[1] );
			if ( ! is_wp_error( $metadata ) && ( file_exists( $payload ) || is_link( $payload ) ) ) {
				$metadata['id'] = $matches[1];
				$results[]      = $metadata;
			}
		}
		usort( $results, static function ( $left, $right ) { return strcmp( $right['created_at'], $left['created_at'] ); } );
		return $results;
	}

	/**
	 * Restore an entry to its original authorized location.
	 *
	 * @param string $id Trash identifier.
	 * @return array<string,string>|WP_Error
	 */
	public function restore( $id ) {
		$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'trash', $id );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		$payload = SITEINTELIX_File_Manager_Storage::path( 'trash/' . $id );
		if ( ! file_exists( $payload ) || is_link( $payload ) ) {
			return $this->error( 'invalid_trash', __( 'This trash item is invalid and cannot be restored.', 'siteintelix' ) );
		}
		$destination = $this->security->authorize_path( $metadata['original_path'], 'restore', false );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		if ( file_exists( $destination ) || is_link( $destination ) ) {
			return $this->error( 'restore_collision', __( 'An item already exists at the original location.', 'siteintelix' ) );
		}
		if ( ! is_dir( dirname( $destination ) ) || ! is_writable( dirname( $destination ) ) || ! rename( $payload, $destination ) ) {
			return $this->error( 'restore_failed', __( 'This item could not be restored.', 'siteintelix' ) );
		}
		$deleted = SITEINTELIX_File_Manager_Storage::delete_metadata( 'trash', $id );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}
		do_action( 'siteintelix_file_manager_after_operation', 'restore', $metadata['original_path'], 'success' );
		return array( 'path' => $metadata['original_path'] );
	}

	/**
	 * Permanently delete one private trash entry.
	 *
	 * @param string $id Trash identifier.
	 * @return true|WP_Error
	 */
	public function permanently_delete( $id ) {
		$metadata = SITEINTELIX_File_Manager_Storage::read_metadata( 'trash', $id );
		if ( is_wp_error( $metadata ) ) {
			return $metadata;
		}
		$deleted = SITEINTELIX_File_Manager_Storage::delete_owned_tree( 'trash', $id );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}
		$metadata_deleted = SITEINTELIX_File_Manager_Storage::delete_metadata( 'trash', $id );
		if ( is_wp_error( $metadata_deleted ) ) {
			return $metadata_deleted;
		}
		do_action( 'siteintelix_file_manager_after_operation', 'permanent_delete', $metadata['original_path'], 'success' );
		return true;
	}

	/**
	 * Determine whether a directory contains entries without recursion.
	 *
	 * @param string $directory Directory.
	 * @return bool
	 */
	private function directory_has_entries( $directory ) {
		$iterator = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );
		return $iterator->valid();
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
