<?php
/**
 * File Manager bounded ZIP archive service.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, streams, and removes private temporary ZIP downloads.
 */
class SITEINTELIX_File_Manager_Archive {

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
	 * Return whether the server supports ZIP creation.
	 *
	 * @return bool
	 */
	public static function available() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * Create one bounded private ZIP from immediate children of the current path.
	 *
	 * @param string $current_path Current relative directory.
	 * @param mixed  $paths Selected relative paths.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create( $current_path, $paths ) {
		if ( ! self::available() ) {
			return $this->error( 'archive_unavailable', __( 'ZIP archive support is not available on this server.', 'siteintelix' ) );
		}
		if ( ! is_array( $paths ) || empty( $paths ) || count( $paths ) > 100 ) {
			return $this->error( 'archive_selection_limit', __( 'Select between 1 and 100 items.', 'siteintelix' ) );
		}
		foreach ( $paths as $path ) {
			if ( ! is_string( $path ) || '' === $path ) {
				return $this->error( 'archive_invalid_source', __( 'An archive source is invalid.', 'siteintelix' ) );
			}
		}
		$paths   = array_values( array_unique( $paths ) );
		$current = $this->security->authorize_path( $current_path, 'list' );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$storage = SITEINTELIX_File_Manager_Storage::ensure_directories();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}
		try {
			$identifier = bin2hex( random_bytes( 16 ) );
		} catch ( Exception $exception ) {
			return $this->error( 'archive_random_failed', __( 'The ZIP archive could not be created safely.', 'siteintelix' ) );
		}
		$temporary = SITEINTELIX_File_Manager_Storage::path( 'tmp/archive-' . $identifier . '.zip' );
		register_shutdown_function(
			static function () use ( $temporary ) {
				if ( is_file( $temporary ) && ! is_link( $temporary ) ) {
					wp_delete_file( $temporary );
				}
			}
		);

		$zip = new ZipArchive();
		if ( true !== $zip->open( $temporary, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
			return $this->error( 'archive_create_failed', __( 'The ZIP archive could not be created.', 'siteintelix' ) );
		}
		$state = array(
			'entries'     => 0,
			'bytes'       => 0,
			'omitted'     => 0,
			'entry_limit' => max( 1, (int) apply_filters( 'siteintelix_file_manager_archive_entry_limit', 5000 ) ),
			'byte_limit'  => max( 1, (int) apply_filters( 'siteintelix_file_manager_archive_byte_limit', 250 * MB_IN_BYTES ) ),
		);
		$result = $this->add_sources( $zip, $current, $paths, $state );
		$closed = $zip->close();
		if ( is_wp_error( $result ) || ! $closed || 0 === $state['entries'] ) {
			$this->delete( $temporary );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return $this->error( 'archive_empty', __( 'No permitted files were available to archive.', 'siteintelix' ) );
		}

		return array(
			'path'    => $temporary,
			'name'    => $this->download_name( $current_path, $paths ),
			'entries' => $state['entries'],
			'bytes'   => $state['bytes'],
			'omitted' => $state['omitted'],
		);
	}

	/**
	 * Stream a validated private ZIP response without buffering it in memory.
	 *
	 * @param array<string,mixed> $archive Archive descriptor returned by create().
	 * @return true|WP_Error
	 */
	public function stream( $archive ) {
		if ( ! is_array( $archive ) || empty( $archive['path'] ) || empty( $archive['name'] ) || headers_sent() ) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$tmp  = realpath( SITEINTELIX_File_Manager_Storage::path( 'tmp' ) );
		$path = realpath( $archive['path'] );
		if (
			false === $tmp
			|| false === $path
			|| ! $this->security->contains_path( wp_normalize_path( $tmp ), wp_normalize_path( $path ) )
			|| ! is_file( $path )
			|| is_link( $archive['path'] )
			|| ! $this->is_temporary_archive_name( basename( $path ) )
		) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$name = sanitize_file_name( $archive['name'] );
		$name = str_replace( array( "\r", "\n", '"' ), '', $name );
		if ( '' === $name || '.zip' !== strtolower( substr( $name, -4 ) ) ) {
			return $this->error( 'archive_invalid', __( 'The ZIP archive is invalid.', 'siteintelix' ) );
		}
		$length = filesize( $path );
		$handle = fopen( $path, 'rb' );
		if ( false === $length || false === $handle ) {
			return $this->error( 'archive_read_failed', __( 'The ZIP archive could not be read.', 'siteintelix' ) );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) $length );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 65536 );
			if ( false === $chunk ) {
				fclose( $handle );
				return $this->error( 'archive_read_failed', __( 'The ZIP archive could not be read.', 'siteintelix' ) );
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated binary ZIP stream.
			flush();
		}
		fclose( $handle );
		return true;
	}

	/**
	 * Remove one generated ZIP only from canonical private temporary storage.
	 *
	 * @param string $path Archive path.
	 * @return true|WP_Error
	 */
	public function delete( $path ) {
		if ( ! is_string( $path ) || false !== strpos( $path, "\0" ) || ! $this->is_temporary_archive_name( basename( $path ) ) ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		$tmp = realpath( SITEINTELIX_File_Manager_Storage::path( 'tmp' ) );
		if ( false === $tmp ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			$normalized = wp_normalize_path( $path );
			if ( preg_match( '#(^|/)\.\.(/|$)#', $normalized ) || ! $this->security->contains_path( wp_normalize_path( $tmp ), $normalized ) ) {
				return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
			}
			return true;
		}
		$real = realpath( $path );
		if (
			false === $real
			|| is_link( $path )
			|| ! is_file( $real )
			|| ! $this->security->contains_path( wp_normalize_path( $tmp ), wp_normalize_path( $real ) )
		) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		wp_delete_file( $real );
		if ( file_exists( $real ) ) {
			return $this->error( 'archive_cleanup_failed', __( 'The temporary ZIP archive could not be removed.', 'siteintelix' ) );
		}
		return true;
	}

	/**
	 * Add selected immediate children.
	 *
	 * @param ZipArchive          $zip ZIP handle.
	 * @param string              $current Canonical current directory.
	 * @param string[]            $paths Selected paths.
	 * @param array<string,mixed> $state Limit state.
	 * @return true|WP_Error
	 */
	private function add_sources( $zip, $current, $paths, &$state ) {
		foreach ( $paths as $path ) {
			$normalized_path = wp_normalize_path( $path );
			$parent_input    = dirname( $normalized_path );
			$parent_input    = '.' === $parent_input ? '' : $parent_input;
			$parent          = $this->security->authorize_path( $parent_input, 'list' );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
			if ( wp_normalize_path( $parent ) !== wp_normalize_path( $current ) ) {
				return $this->error( 'archive_parent_mismatch', __( 'Archive sources must be selected from the current directory.', 'siteintelix' ) );
			}

			$absolute = $this->security->archive_source( $path );
			if ( is_wp_error( $absolute ) ) {
				if ( $this->omittable_error( $absolute ) ) {
					++$state['omitted'];
					continue;
				}
				return $absolute;
			}
			if ( is_dir( $absolute ) && ! is_link( $absolute ) ) {
				$result = $this->add_directory( $zip, $absolute, $current, $state );
			} elseif ( is_file( $absolute ) && ! is_link( $absolute ) ) {
				$result = $this->add_file( $zip, $absolute, $current, $state );
			} else {
				++$state['omitted'];
				continue;
			}
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/**
	 * Add one selected directory and its permitted descendants.
	 *
	 * @param ZipArchive          $zip ZIP handle.
	 * @param string              $directory Canonical directory.
	 * @param string              $current Canonical current directory.
	 * @param array<string,mixed> $state Limit state.
	 * @return true|WP_Error
	 */
	private function add_directory( $zip, $directory, $current, &$state ) {
		$result = $this->add_directory_record( $zip, $directory, $current, $state );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		try {
			$root = new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS );
			$filter = new RecursiveCallbackFilterIterator(
				$root,
				function ( $item ) use ( &$state ) {
					$path = $item->getPathname();
					if ( $item->isLink() || ! is_readable( $path ) ) {
						++$state['omitted'];
						return false;
					}
					$authorized = $this->security->archive_source( $path );
					if ( is_wp_error( $authorized ) ) {
						++$state['omitted'];
						return false;
					}
					return true;
				}
			);
			$iterator = new RecursiveIteratorIterator( $filter, RecursiveIteratorIterator::SELF_FIRST );
			foreach ( $iterator as $item ) {
				$absolute = $item->getPathname();
				if ( $item->isDir() ) {
					$result = $this->add_directory_record( $zip, $absolute, $current, $state );
				} elseif ( $item->isFile() ) {
					$result = $this->add_file( $zip, $absolute, $current, $state );
				} else {
					++$state['omitted'];
					continue;
				}
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		} catch ( UnexpectedValueException $exception ) {
			return $this->error( 'archive_read_failed', __( 'An archive source could not be read.', 'siteintelix' ) );
		}
		return true;
	}

	/**
	 * Add one directory record within the entry limit.
	 *
	 * @param ZipArchive          $zip ZIP handle.
	 * @param string              $absolute Directory path.
	 * @param string              $current Canonical current directory.
	 * @param array<string,mixed> $state Limit state.
	 * @return true|WP_Error
	 */
	private function add_directory_record( $zip, $absolute, $current, &$state ) {
		$proposed = $state['entries'] + 1;
		if ( $proposed > $state['entry_limit'] ) {
			return $this->error( 'archive_entry_limit', __( 'The ZIP archive exceeds the permitted entry limit.', 'siteintelix' ) );
		}
		$entry = $this->entry_name( $current, $absolute );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		if ( ! $zip->addEmptyDir( rtrim( $entry, '/' ) ) ) {
			return $this->error( 'archive_add_failed', __( 'An item could not be added to the ZIP archive.', 'siteintelix' ) );
		}
		$state['entries'] = $proposed;
		return true;
	}

	/**
	 * Add one ordinary file within entry and byte limits.
	 *
	 * @param ZipArchive          $zip ZIP handle.
	 * @param string              $absolute File path.
	 * @param string              $current Canonical current directory.
	 * @param array<string,mixed> $state Limit state.
	 * @return true|WP_Error
	 */
	private function add_file( $zip, $absolute, $current, &$state ) {
		if ( ! is_file( $absolute ) || ! is_readable( $absolute ) || is_link( $absolute ) ) {
			++$state['omitted'];
			return true;
		}
		$size = filesize( $absolute );
		if ( false === $size || $size < 0 ) {
			++$state['omitted'];
			return true;
		}
		$proposed_entries = $state['entries'] + 1;
		$proposed_bytes   = $state['bytes'] + (int) $size;
		if ( $proposed_entries > $state['entry_limit'] ) {
			return $this->error( 'archive_entry_limit', __( 'The ZIP archive exceeds the permitted entry limit.', 'siteintelix' ) );
		}
		if ( $proposed_bytes > $state['byte_limit'] ) {
			return $this->error( 'archive_size_limit', __( 'The ZIP archive exceeds the permitted uncompressed size.', 'siteintelix' ) );
		}
		$entry = $this->entry_name( $current, $absolute );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		if ( ! $zip->addFile( $absolute, $entry ) ) {
			return $this->error( 'archive_add_failed', __( 'An item could not be added to the ZIP archive.', 'siteintelix' ) );
		}
		$state['entries'] = $proposed_entries;
		$state['bytes']   = $proposed_bytes;
		return true;
	}

	/**
	 * Build a safe ZIP entry name relative to the current directory.
	 *
	 * @param string $current Canonical current directory.
	 * @param string $absolute Canonical source path.
	 * @return string|WP_Error
	 */
	private function entry_name( $current, $absolute ) {
		$current  = untrailingslashit( wp_normalize_path( $current ) );
		$absolute = wp_normalize_path( $absolute );
		if ( ! $this->security->contains_path( $current, $absolute ) || $absolute === $current ) {
			return $this->error( 'archive_entry_invalid', __( 'An archive entry path is invalid.', 'siteintelix' ) );
		}
		$entry = ltrim( substr( $absolute, strlen( $current ) ), '/' );
		$entry = preg_replace( '/[\x00-\x1F\x7F]/', '', str_replace( '\\', '/', $entry ) );
		$entry = ltrim( (string) $entry, '/' );
		$parts = explode( '/', $entry );
		if ( '' === $entry || in_array( '.', $parts, true ) || in_array( '..', $parts, true ) ) {
			return $this->error( 'archive_entry_invalid', __( 'An archive entry path is invalid.', 'siteintelix' ) );
		}
		return $entry;
	}

	/**
	 * Build a sanitized download filename.
	 *
	 * @param string   $current_path Current relative path.
	 * @param string[] $paths Selected paths.
	 * @return string
	 */
	private function download_name( $current_path, $paths ) {
		if ( 1 === count( $paths ) ) {
			$base = sanitize_file_name( basename( reset( $paths ) ) );
		} else {
			$base = sanitize_file_name( basename( $current_path ) );
			$base = ( '' === $base ? 'wordpress-files' : $base ) . '-' . gmdate( 'Ymd-His' );
		}
		$base = preg_replace( '/\.zip$/i', '', (string) $base );
		return ( '' === $base ? 'wordpress-files' : $base ) . '.zip';
	}

	/**
	 * Determine whether a rejected source should be safely omitted.
	 *
	 * @param WP_Error $error Source error.
	 * @return bool
	 */
	private function omittable_error( $error ) {
		return in_array(
			$error->get_error_code(),
			array(
				'siteintelix_file_manager_archive_source_protected',
				'siteintelix_file_manager_protected_path',
				'siteintelix_file_manager_symlink_blocked',
			),
			true
		);
	}

	/**
	 * Match only service-generated temporary archive filenames.
	 *
	 * @param string $name Basename.
	 * @return bool
	 */
	private function is_temporary_archive_name( $name ) {
		return 1 === preg_match( '/^archive-[a-f0-9]{32}\.zip$/', (string) $name );
	}

	/**
	 * Create a service error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
