<?php
/**
 * Secure ZIP extraction for plugin and theme directories.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_File_Manager_Extractor {
	const MAX_ENTRIES = 5000;
	const MAX_BYTES   = 262144000;

	/** @var SITEINTELIX_File_Manager_Security */
	private $security;

	public function __construct( $security = null ) {
		$this->security = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
	}

	/**
	 * Extract a verified ZIP beside itself, without overwriting existing files.
	 *
	 * @param string $path ABSPATH-relative ZIP path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function extract( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return $this->error( 'unavailable', __( 'ZIP extraction is unavailable on this server.', 'siteintelix' ) );
		}
		$archive = $this->security->authorize_path( $path, 'extract' );
		if ( is_wp_error( $archive ) || ! is_file( $archive ) || is_link( $archive ) || 'zip' !== strtolower( pathinfo( $archive, PATHINFO_EXTENSION ) ) ) {
			return is_wp_error( $archive ) ? $archive : $this->error( 'invalid_archive', __( 'Select a valid ZIP archive.', 'siteintelix' ) );
		}
		$destination = wp_normalize_path( dirname( $archive ) );
		$plugins     = untrailingslashit( wp_normalize_path( realpath( WP_PLUGIN_DIR ) ?: WP_PLUGIN_DIR ) );
		$themes      = untrailingslashit( wp_normalize_path( realpath( get_theme_root() ) ?: get_theme_root() ) );
		if ( ! $this->security->contains_path( $plugins, $destination ) && ! $this->security->contains_path( $themes, $destination ) ) {
			return $this->error( 'invalid_destination', __( 'ZIP files can only be extracted inside the plugins or themes directory.', 'siteintelix' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $archive ) || $zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES ) {
			return $this->error( 'invalid_archive', __( 'The ZIP archive is invalid or contains too many entries.', 'siteintelix' ) );
		}

		$entries = array();
		$seen    = array();
		$total   = 0;
		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$stat = $zip->statIndex( $index );
			$name = is_array( $stat ) && isset( $stat['name'] ) ? str_replace( '\\', '/', (string) $stat['name'] ) : '';
			if ( ! $this->valid_entry_name( $name ) || $this->is_symlink( $zip, $index ) ) {
				$zip->close();
				return $this->error( 'unsafe_archive', __( 'The ZIP contains an unsafe path or symbolic link.', 'siteintelix' ) );
			}
			$total += isset( $stat['size'] ) ? max( 0, (int) $stat['size'] ) : 0;
			if ( $total > self::MAX_BYTES ) {
				$zip->close();
				return $this->error( 'archive_too_large', __( 'The extracted ZIP would exceed the 250 MB safety limit.', 'siteintelix' ) );
			}
			$target = wp_normalize_path( trailingslashit( $destination ) . ltrim( $name, '/' ) );
			$target_key = strtolower( untrailingslashit( $target ) );
			if ( isset( $seen[ $target_key ] ) || ! $this->security->contains_path( $destination, $target ) || ( file_exists( $target ) && ! ( '/' === substr( $name, -1 ) && is_dir( $target ) ) ) ) {
				$zip->close();
				return $this->error( 'destination_exists', __( 'Extraction stopped because an archive item is duplicated or already exists.', 'siteintelix' ) );
			}
			$seen[ $target_key ] = true;
			$entries[] = array( 'index' => $index, 'name' => $name, 'target' => $target, 'directory' => '/' === substr( $name, -1 ) );
		}

		$lock = SITEINTELIX_File_Manager_Storage::acquire_lock( 'mutation:global' );
		if ( is_wp_error( $lock ) ) {
			$zip->close();
			return $lock;
		}
		$files = 0;
		try {
			foreach ( $entries as $entry ) {
				if ( $entry['directory'] ) {
					if ( ! is_dir( $entry['target'] ) && ! wp_mkdir_p( $entry['target'] ) ) {
						return $this->error( 'extract_failed', __( 'A directory from the ZIP could not be created.', 'siteintelix' ) );
					}
					continue;
				}
				if ( ! is_dir( dirname( $entry['target'] ) ) && ! wp_mkdir_p( dirname( $entry['target'] ) ) ) {
					return $this->error( 'extract_failed', __( 'A directory from the ZIP could not be created.', 'siteintelix' ) );
				}
				$source = $zip->getStream( $entry['name'] );
				$target = fopen( $entry['target'], 'xb' );
				if ( false === $source || false === $target ) {
					return $this->error( 'extract_failed', __( 'A file from the ZIP could not be written.', 'siteintelix' ) );
				}
				stream_copy_to_stream( $source, $target );
				fclose( $source );
				fclose( $target );
				chmod( $entry['target'], 0644 );
				$files++;
			}
		} finally {
			$zip->close();
			SITEINTELIX_File_Manager_Storage::release_lock( $lock );
		}
		$relative = $this->security->relative_path( $archive );
		return array( 'path' => is_wp_error( $relative ) ? '' : $relative, 'files' => $files );
	}

	private function valid_entry_name( $name ) {
		if ( '' === $name || strlen( $name ) > 1024 || false !== strpos( $name, "\0" ) || '/' === $name[0] || preg_match( '/^[a-z]:/i', $name ) || preg_match( '#(^|/)\.{1,2}(/|$)#', $name ) ) {
			return false;
		}
		$basename = strtolower( basename( rtrim( $name, '/' ) ) );
		return ! preg_match( '/^\.env(?:\..+)?$/', $basename ) && ! in_array( $basename, array( 'wp-config.php', '.htaccess', '.htpasswd', '.user.ini', 'php.ini', 'web.config' ), true );
	}

	private function is_symlink( $zip, $index ) {
		$operations = 0;
		$attributes = 0;
		if ( ! $zip->getExternalAttributesIndex( $index, $operations, $attributes ) ) {
			return false;
		}
		return 0120000 === ( ( $attributes >> 16 ) & 0170000 );
	}

	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_extract_' . $code, $message );
	}
}
