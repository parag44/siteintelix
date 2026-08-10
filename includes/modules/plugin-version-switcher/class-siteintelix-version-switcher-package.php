<?php
/**
 * ZIP inspection and import for Plugin Version Switcher.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

/** Validates plugin archives before storing them in the private vault. */
final class SITEINTELIX_Version_Switcher_Package {

	const MAX_ENTRIES           = 10000;
	const MAX_UNCOMPRESSED_SIZE = 536870912;

	/**
	 * Inspect a ZIP without extracting it.
	 *
	 * @param string $path ZIP path.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function inspect( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'siteintelix_version_zip_unavailable', __( 'ZipArchive is required to inspect plugin builds.', 'siteintelix' ) );
		}
		if ( ! is_file( $path ) || is_link( $path ) || ! is_readable( $path ) ) {
			return new WP_Error( 'siteintelix_version_unreadable_zip', __( 'The uploaded ZIP is not readable.', 'siteintelix' ) );
		}

		$zip    = new ZipArchive();
		$opened = $zip->open( $path, ZipArchive::CHECKCONS );
		if ( true !== $opened ) {
			return new WP_Error( 'siteintelix_version_malformed_zip', __( 'The uploaded file is not a valid readable ZIP package.', 'siteintelix' ) );
		}

		if ( $zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES ) {
			$zip->close();
			return new WP_Error( 'siteintelix_version_archive_size', __( 'The ZIP contains an unsupported number of files.', 'siteintelix' ) );
		}

		$total_size = 0;
		$candidates = array();
		$roots      = array();

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$stat = $zip->statIndex( $index );
			$name = is_array( $stat ) && isset( $stat['name'] ) ? (string) $stat['name'] : '';
			if ( ! self::safe_entry_name( $name ) || self::is_symlink_entry( $zip, $index ) ) {
				$zip->close();
				return new WP_Error( 'siteintelix_version_unsafe_archive', __( 'The ZIP contains an unsafe path or symbolic link.', 'siteintelix' ) );
			}

			$total_size += isset( $stat['size'] ) ? absint( $stat['size'] ) : 0;
			if ( $total_size > self::MAX_UNCOMPRESSED_SIZE ) {
				$zip->close();
				return new WP_Error( 'siteintelix_version_archive_size', __( 'The uncompressed plugin package is too large.', 'siteintelix' ) );
			}

			$trimmed = rtrim( $name, '/' );
			if ( '' === $trimmed ) {
				continue;
			}
			$segments = explode( '/', $trimmed );
			$roots[ $segments[0] ] = true;

			if ( '/' === substr( $name, -1 ) || 'php' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
				continue;
			}
			$contents = $zip->getFromIndex( $index, 131072 );
			if ( false === $contents ) {
				continue;
			}
			$headers = self::parse_headers( $contents );
			if ( '' !== $headers['name'] && '' !== $headers['version'] ) {
				$candidates[] = array( 'path' => $name, 'headers' => $headers, 'depth' => substr_count( $name, '/' ) );
			}
		}

		if ( empty( $candidates ) ) {
			$zip->close();
			return new WP_Error( 'siteintelix_version_no_plugin_header', __( 'No valid WordPress plugin header with a name and version was found.', 'siteintelix' ) );
		}

		usort(
			$candidates,
			static function ( $left, $right ) {
				return $left['depth'] <=> $right['depth'];
			}
		);
		$main     = $candidates[0];
		$shallow  = array_filter( $candidates, static function ( $candidate ) use ( $main ) { return $candidate['depth'] === $main['depth']; } );
		$segments = explode( '/', $main['path'] );
		if ( 1 !== $main['depth'] || 1 !== count( $shallow ) || count( $segments ) < 2 || count( $roots ) !== 1 ) {
			$zip->close();
			return new WP_Error( 'siteintelix_version_archive_layout', __( 'The plugin ZIP must contain one plugin directory at its root.', 'siteintelix' ) );
		}

		$directory = (string) $segments[0];
		if ( $directory !== sanitize_file_name( $directory ) || '' === sanitize_key( $directory ) || '.' === $directory || '..' === $directory ) {
			$zip->close();
			return new WP_Error( 'siteintelix_version_archive_layout', __( 'The plugin directory name in the ZIP is not safe.', 'siteintelix' ) );
		}

		$zip->close();
		return array(
			'plugin_name'         => $main['headers']['name'],
			'plugin_slug'         => sanitize_key( $directory ),
			'plugin_directory'    => $directory,
			'primary_plugin_file' => $main['path'],
			'version'             => $main['headers']['version'],
			'requires_php'        => $main['headers']['requires_php'],
			'requires_wordpress'  => $main['headers']['requires_wordpress'],
		);
	}

	/**
	 * Validate and move one uploaded ZIP into the vault without installing it.
	 *
	 * @param string $temporary_path Temporary uploaded path.
	 * @param string $original_name  Client filename.
	 * @param int    $user_id        Uploading user ID.
	 * @param bool   $uploaded_file  Require PHP-upload provenance.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function import( $temporary_path, $original_name, $user_id, $uploaded_file = true ) {
		$maximum = (int) apply_filters( 'siteintelix_version_switcher_max_upload_size', wp_max_upload_size() );
		$size    = is_file( $temporary_path ) ? filesize( $temporary_path ) : false;
		if ( false === $size || $size < 1 || $size > $maximum ) {
			return new WP_Error( 'siteintelix_version_upload_size', __( 'The plugin ZIP is empty or exceeds the allowed upload size.', 'siteintelix' ) );
		}
		if ( $uploaded_file && ! is_uploaded_file( $temporary_path ) ) {
			return new WP_Error( 'siteintelix_version_invalid_upload', __( 'The uploaded plugin package could not be verified.', 'siteintelix' ) );
		}

		$inspection = self::inspect( $temporary_path );
		if ( is_wp_error( $inspection ) ) {
			return $inspection;
		}
		$checksum = hash_file( 'sha256', $temporary_path );
		if ( ! is_string( $checksum ) || 64 !== strlen( $checksum ) ) {
			return new WP_Error( 'siteintelix_version_checksum_failed', __( 'The plugin package checksum could not be calculated.', 'siteintelix' ) );
		}

		foreach ( SITEINTELIX_Version_Switcher_Storage::manifest() as $stored ) {
			if ( isset( $stored['plugin_slug'] ) && $stored['plugin_slug'] === $inspection['plugin_slug'] && ( ! isset( $stored['plugin_directory'], $stored['primary_plugin_file'] ) || $stored['plugin_directory'] !== $inspection['plugin_directory'] || $stored['primary_plugin_file'] !== $inspection['primary_plugin_file'] ) ) {
				return new WP_Error( 'siteintelix_version_plugin_identity_conflict', __( 'A stored plugin with this slug uses a different primary plugin identity.', 'siteintelix' ) );
			}
			if ( isset( $stored['plugin_slug'], $stored['version'], $stored['checksum'] ) && $stored['plugin_slug'] === $inspection['plugin_slug'] && $stored['version'] === $inspection['version'] && hash_equals( (string) $stored['checksum'], $checksum ) ) {
				return new WP_Error( 'siteintelix_version_duplicate_package', __( 'This exact plugin version and build is already stored.', 'siteintelix' ) );
			}
		}

		$ensured = SITEINTELIX_Version_Switcher_Storage::ensure();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}
		$package_id = wp_generate_uuid4();
		$filename   = 'build-' . bin2hex( random_bytes( 20 ) ) . '.zip';
		$package    = array_merge(
			$inspection,
			array(
				'package_id'      => $package_id,
				'original_name'   => sanitize_file_name( wp_basename( $original_name ) ),
				'stored_filename' => $filename,
				'file_size'       => (int) $size,
				'checksum'        => $checksum,
				'uploaded_at'     => time(),
				'uploaded_by'     => absint( $user_id ),
			)
		);
		$destination = SITEINTELIX_Version_Switcher_Storage::package_path( $package );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}

		$moved = $uploaded_file ? move_uploaded_file( $temporary_path, $destination ) : copy( $temporary_path, $destination );
		if ( ! $moved ) {
			return new WP_Error( 'siteintelix_version_store_failed', __( 'The plugin build could not be moved into the protected Version Vault.', 'siteintelix' ) );
		}
		@chmod( $destination, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.

		$result = SITEINTELIX_Version_Switcher_Storage::add_package( $package );
		if ( is_wp_error( $result ) ) {
			unlink( $destination );
			return $result;
		}
		return $package;
	}

	/** @param array<string,mixed> $package Package data. @return array<int,string> */
	public static function compatibility_warnings( $package ) {
		$warnings = array();
		if ( ! empty( $package['requires_php'] ) && version_compare( PHP_VERSION, (string) $package['requires_php'], '<' ) ) {
			$warnings[] = sprintf( /* translators: %s: required PHP version. */ __( 'Requires PHP %s or newer.', 'siteintelix' ), $package['requires_php'] );
		}
		if ( ! empty( $package['requires_wordpress'] ) && version_compare( get_bloginfo( 'version' ), (string) $package['requires_wordpress'], '<' ) ) {
			$warnings[] = sprintf( /* translators: %s: required WordPress version. */ __( 'Requires WordPress %s or newer.', 'siteintelix' ), $package['requires_wordpress'] );
		}
		return $warnings;
	}

	/** @param string $name Entry name. @return bool */
	private static function safe_entry_name( $name ) {
		if ( '' === $name || false !== strpos( $name, "\0" ) || false !== strpos( $name, '\\' ) || '/' === substr( $name, 0, 1 ) || preg_match( '/^[A-Za-z]:/', $name ) ) {
			return false;
		}
		foreach ( explode( '/', $name ) as $segment ) {
			if ( '..' === $segment ) {
				return false;
			}
		}
		return true;
	}

	/** @param ZipArchive $zip ZIP. @param int $index Entry index. @return bool */
	private static function is_symlink_entry( $zip, $index ) {
		$operations = 0;
		$attributes = 0;
		if ( ! method_exists( $zip, 'getExternalAttributesIndex' ) || ! $zip->getExternalAttributesIndex( $index, $operations, $attributes ) ) {
			return false;
		}
		return 0xA000 === ( ( $attributes >> 16 ) & 0xF000 );
	}

	/** @param string $contents PHP file prefix. @return array<string,string> */
	private static function parse_headers( $contents ) {
		$headers = array(
			'name'               => 'Plugin Name',
			'version'            => 'Version',
			'requires_php'       => 'Requires PHP',
			'requires_wordpress' => 'Requires at least',
		);
		$parsed = array();
		foreach ( $headers as $key => $label ) {
			$pattern = '/^[ \t\/*#@]*' . preg_quote( $label, '/' ) . ':(.*)$/mi';
			$parsed[ $key ] = preg_match( $pattern, $contents, $match ) ? sanitize_text_field( trim( preg_replace( '/\s*(?:\*\/|\?>).*/', '', $match[1] ) ) ) : '';
		}
		return $parsed;
	}
}
