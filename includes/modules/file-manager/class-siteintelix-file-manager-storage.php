<?php
/**
 * File Manager owned storage.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns private backup, trash, metadata, and audit paths.
 */
class SITEINTELIX_File_Manager_Storage {

	/** @var array<string,array{handle:resource,count:int}> */
	private static $locks = array();

	/**
	 * Return an owned path.
	 *
	 * @param string $relative Relative owned path.
	 * @return string
	 */
	public static function path( $relative = '' ) {
		$base = apply_filters( 'siteintelix_file_manager_storage_path', WP_CONTENT_DIR . '/siteintelix/file-manager' );
		$base = untrailingslashit( wp_normalize_path( (string) $base ) );
		$relative = ltrim( wp_normalize_path( (string) $relative ), '/' );
		return '' === $relative ? $base : trailingslashit( $base ) . $relative;
	}

	/**
	 * Create protected owned directories.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_directories() {
		$base   = self::path();
		$parent = realpath( dirname( $base ) );
		$owner_path = WP_CONTENT_DIR . '/siteintelix';
		$owner      = untrailingslashit( wp_normalize_path( realpath( $owner_path ) ?: $owner_path ) );
		if ( is_link( $owner_path ) || ! self::contains( untrailingslashit( wp_normalize_path( $owner_path ) ), $base ) ) {
			return self::error( 'unsafe_storage', __( 'File Manager storage could not be initialized safely.', 'siteintelix' ) );
		}
		if ( false !== $parent && ! self::contains( $owner, wp_normalize_path( $parent ) ) ) {
			return self::error( 'unsafe_storage', __( 'File Manager storage could not be initialized safely.', 'siteintelix' ) );
		}
		if ( is_link( $base ) ) {
			return self::error( 'unsafe_storage', __( 'File Manager storage could not be initialized safely.', 'siteintelix' ) );
		}

		foreach ( array( '', 'backups', 'trash', 'meta', 'audit', 'tmp' ) as $area ) {
			$directory = self::path( $area );
			if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
				return self::error( 'storage_create_failed', __( 'File Manager storage could not be created.', 'siteintelix' ) );
			}
			if ( is_link( $directory ) ) {
				return self::error( 'unsafe_storage', __( 'File Manager storage could not be initialized safely.', 'siteintelix' ) );
			}
			$result = self::write_protection_files( $directory );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Remove expired generated ZIP archives from private temporary storage.
	 *
	 * @param int $maximum_age Maximum age in seconds.
	 * @return void
	 */
	public static function cleanup_temporary_archives( $maximum_age = 3600 ) {
		$directory = self::path( 'tmp' );
		if ( ! is_dir( $directory ) || is_link( $directory ) ) {
			return;
		}
		$cutoff = time() - max( 60, (int) $maximum_age );
		try {
			foreach ( new DirectoryIterator( $directory ) as $item ) {
				if (
					$item->isDot()
					|| $item->isLink()
					|| ! $item->isFile()
					|| 1 !== preg_match( '/^archive-[a-f0-9]{32}\.zip$/', $item->getFilename() )
					|| $item->getMTime() >= $cutoff
				) {
					continue;
				}
				wp_delete_file( $item->getPathname() );
			}
		} catch ( UnexpectedValueException $exception ) {
			return;
		}
	}

	/**
	 * Atomically write validated metadata.
	 *
	 * @param string              $area backups or trash.
	 * @param string              $id Owned identifier.
	 * @param array<string,mixed> $metadata Metadata.
	 * @return true|WP_Error
	 */
	public static function write_metadata( $area, $id, $metadata ) {
		$valid = self::validate_metadata( $area, $id, $metadata );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$result = self::ensure_directories();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::atomic_write( self::metadata_path( $area, $id ), wp_json_encode( $metadata ) . "\n" );
	}

	/**
	 * Read schema-validated metadata.
	 *
	 * @param string $area backups or trash.
	 * @param string $id Owned identifier.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function read_metadata( $area, $id ) {
		if ( ! self::valid_identifier( $area, $id ) ) {
			return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
		}
		$path = self::metadata_path( $area, $id );
		if ( ! is_file( $path ) || is_link( $path ) || filesize( $path ) > 64 * 1024 ) {
			return self::error( 'missing_metadata', __( 'File Manager metadata could not be found.', 'siteintelix' ) );
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true, 32 );
		$valid   = self::validate_metadata( $area, $id, $decoded );
		return is_wp_error( $valid ) ? $valid : $decoded;
	}

	/**
	 * Delete one owned metadata record.
	 *
	 * @param string $area backups or trash.
	 * @param string $id Owned identifier.
	 * @return true|WP_Error
	 */
	public static function delete_metadata( $area, $id ) {
		if ( ! self::valid_identifier( $area, $id ) ) {
			return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
		}
		$path = self::metadata_path( $area, $id );
		if ( ! file_exists( $path ) ) {
			return true;
		}
		if ( is_link( $path ) || ! is_file( $path ) ) {
			return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
		}
		return unlink( $path ) ? true : self::error( 'delete_failed', __( 'File Manager metadata could not be removed.', 'siteintelix' ) );
	}

	/**
	 * Delete one owned payload tree without following links.
	 *
	 * @param string $area backups or trash.
	 * @param string $id Owned identifier.
	 * @return true|WP_Error
	 */
	public static function delete_owned_tree( $area, $id ) {
		if ( ! self::valid_identifier( $area, $id ) ) {
			return self::error( 'invalid_owned_path', __( 'The File Manager storage path is invalid.', 'siteintelix' ) );
		}
		$area_root = realpath( self::path( $area ) );
		$target    = self::path( $area . '/' . $id );
		if ( false === $area_root || ! file_exists( $target ) && ! is_link( $target ) ) {
			return true;
		}
		if ( is_link( $target ) ) {
			return self::error( 'symlink_blocked', __( 'Symbolic links cannot be removed by File Manager cleanup.', 'siteintelix' ) );
		}
		$real = realpath( $target );
		if ( false === $real || ! self::contains( wp_normalize_path( $area_root ), wp_normalize_path( $real ) ) || wp_normalize_path( $real ) === wp_normalize_path( $area_root ) ) {
			return self::error( 'invalid_owned_path', __( 'The File Manager storage path is invalid.', 'siteintelix' ) );
		}

		if ( is_file( $real ) ) {
			return unlink( $real ) ? true : self::error( 'delete_failed', __( 'The File Manager storage item could not be removed.', 'siteintelix' ) );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			$pathname = $item->getPathname();
			if ( is_link( $pathname ) ) {
				return self::error( 'symlink_blocked', __( 'Symbolic links cannot be removed by File Manager cleanup.', 'siteintelix' ) );
			}
			$removed = $item->isDir() ? rmdir( $pathname ) : unlink( $pathname );
			if ( ! $removed ) {
				return self::error( 'delete_failed', __( 'The File Manager storage item could not be removed.', 'siteintelix' ) );
			}
		}
		return rmdir( $real ) ? true : self::error( 'delete_failed', __( 'The File Manager storage item could not be removed.', 'siteintelix' ) );
	}

	/**
	 * Delete the complete fixed File Manager-owned root during opted-in uninstall.
	 *
	 * This method does not inspect or act on original paths stored in metadata.
	 *
	 * @return true|WP_Error
	 */
	public static function delete_all_owned_data() {
		$expected   = untrailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/siteintelix/file-manager' ) );
		$configured = untrailingslashit( wp_normalize_path( self::path() ) );
		if ( $configured !== $expected ) {
			return self::error( 'invalid_owned_path', __( 'The File Manager storage path is invalid.', 'siteintelix' ) );
		}
		if ( ! file_exists( $expected ) && ! is_link( $expected ) ) {
			return true;
		}
		if ( is_link( $expected ) || ! is_dir( $expected ) ) {
			return self::error( 'symlink_blocked', __( 'Symbolic links cannot be removed by File Manager cleanup.', 'siteintelix' ) );
		}
		$real = realpath( $expected );
		if ( false === $real || untrailingslashit( wp_normalize_path( $real ) ) !== $expected ) {
			return self::error( 'invalid_owned_path', __( 'The File Manager storage path is invalid.', 'siteintelix' ) );
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( is_link( $item->getPathname() ) ) {
				return self::error( 'symlink_blocked', __( 'Symbolic links cannot be removed by File Manager cleanup.', 'siteintelix' ) );
			}
		}
		$iterator->rewind();
		foreach ( $iterator as $item ) {
			$removed = $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			if ( ! $removed ) {
				return self::error( 'delete_failed', __( 'The File Manager storage item could not be removed.', 'siteintelix' ) );
			}
		}
		return rmdir( $real ) ? true : self::error( 'delete_failed', __( 'The File Manager storage item could not be removed.', 'siteintelix' ) );
	}

	/**
	 * Atomically write bytes to an owned file.
	 *
	 * @param string $path Owned destination.
	 * @param string $contents Contents.
	 * @return true|WP_Error
	 */
	public static function atomic_write( $path, $contents ) {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) || is_link( $directory ) ) {
			return self::error( 'write_failed', __( 'File Manager data could not be written.', 'siteintelix' ) );
		}
		try {
			$suffix = bin2hex( random_bytes( 8 ) );
		} catch ( Exception $exception ) {
			return self::error( 'random_failed', __( 'File Manager data could not be written safely.', 'siteintelix' ) );
		}
		$temp   = $directory . '/.siteintelix-' . $suffix . '.tmp';
		$handle = fopen( $temp, 'x+b' );
		if ( false === $handle ) {
			return self::error( 'write_failed', __( 'File Manager data could not be written.', 'siteintelix' ) );
		}
		$length  = strlen( $contents );
		$written = 0;
		while ( $written < $length ) {
			$chunk = fwrite( $handle, substr( $contents, $written ) );
			if ( false === $chunk || 0 === $chunk ) {
				fclose( $handle );
				unlink( $temp );
				return self::error( 'write_failed', __( 'File Manager data could not be written.', 'siteintelix' ) );
			}
			$written += $chunk;
		}
		fflush( $handle );
		fclose( $handle );
		chmod( $temp, 0640 );
		if ( ! rename( $temp, $path ) ) {
			unlink( $temp );
			return self::error( 'write_failed', __( 'File Manager data could not be written.', 'siteintelix' ) );
		}
		return true;
	}

	/**
	 * Acquire an exclusive process lock for one mutation target.
	 *
	 * @param string $key Canonical operation key.
	 * @return resource|WP_Error
	 */
	public static function acquire_lock( $key ) {
		if ( '' === trim( (string) $key ) ) {
			return self::error( 'invalid_lock', __( 'The File Manager operation lock is invalid.', 'siteintelix' ) );
		}
		$lock_id = hash( 'sha256', wp_normalize_path( (string) $key ) );
		if ( isset( self::$locks[ $lock_id ] ) && is_resource( self::$locks[ $lock_id ]['handle'] ) ) {
			++self::$locks[ $lock_id ]['count'];
			return self::$locks[ $lock_id ]['handle'];
		}
		$ready = self::ensure_directories();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$path   = self::path( 'meta/operation-' . $lock_id . '.lock' );
		$handle = fopen( $path, 'c+b' );
		if ( false === $handle || ! flock( $handle, LOCK_EX ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
			return self::error( 'lock_failed', __( 'The File Manager operation could not be locked safely.', 'siteintelix' ) );
		}
		chmod( $path, 0640 );
		self::$locks[ $lock_id ] = array(
			'handle' => $handle,
			'count'  => 1,
		);
		return $handle;
	}

	/**
	 * Release a process lock.
	 *
	 * @param resource $handle Lock handle.
	 * @return void
	 */
	public static function release_lock( $handle ) {
		if ( ! is_resource( $handle ) ) {
			return;
		}
		foreach ( self::$locks as $lock_id => $lock ) {
			if ( $lock['handle'] !== $handle ) {
				continue;
			}
			--self::$locks[ $lock_id ]['count'];
			if ( self::$locks[ $lock_id ]['count'] > 0 ) {
				return;
			}
			unset( self::$locks[ $lock_id ] );
			flock( $handle, LOCK_UN );
			fclose( $handle );
			return;
		}
	}

	/**
	 * Return a metadata path.
	 *
	 * @param string $area Area.
	 * @param string $id Identifier.
	 * @return string
	 */
	private static function metadata_path( $area, $id ) {
		return self::path( 'meta/' . $area . '-' . $id . '.json' );
	}

	/**
	 * Validate metadata.
	 *
	 * @param string $area Area.
	 * @param string $id Identifier.
	 * @param mixed  $metadata Metadata.
	 * @return true|WP_Error
	 */
	private static function validate_metadata( $area, $id, $metadata ) {
		if ( ! self::valid_identifier( $area, $id ) || ! is_array( $metadata ) ) {
			return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
		}
		$required = array( 'original_path', 'created_at', 'user_id', 'operation', 'size', 'sha256' );
		foreach ( $required as $key ) {
			if ( ! array_key_exists( $key, $metadata ) ) {
				return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
			}
		}
		$path = wp_normalize_path( (string) $metadata['original_path'] );
		if ( '' === $path || '/' === substr( $path, 0, 1 ) || preg_match( '#(^|/)\.\.(/|$)#', $path ) || false !== strpos( $path, "\0" ) ) {
			return self::error( 'invalid_metadata', __( 'File Manager metadata is invalid.', 'siteintelix' ) );
		}
		return true;
	}

	/**
	 * Validate owned identifiers.
	 *
	 * @param string $area Area.
	 * @param string $id Identifier.
	 * @return bool
	 */
	private static function valid_identifier( $area, $id ) {
		return in_array( $area, array( 'backups', 'trash' ), true ) && 1 === preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', (string) $id );
	}

	/**
	 * Write fixed web protection files.
	 *
	 * @param string $directory Owned directory.
	 * @return true|WP_Error
	 */
	private static function write_protection_files( $directory ) {
		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\ndefined( 'ABSPATH' ) || exit;\n",
			'.htaccess'  => "Deny from all\n",
			'web.config' => "<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$result = self::atomic_write( trailingslashit( $directory ) . $name, $contents );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Prefix-safe containment check.
	 *
	 * @param string $root Root.
	 * @param string $path Path.
	 * @return bool
	 */
	private static function contains( $root, $path ) {
		$root = untrailingslashit( wp_normalize_path( $root ) );
		$path = wp_normalize_path( $path );
		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}

	/**
	 * Create an error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
