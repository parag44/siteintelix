<?php
/**
 * Protected storage for Plugin Version Switcher packages and recovery copies.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

/** Owns the private vault, manifest, history, and switch lock. */
final class SITEINTELIX_Version_Switcher_Storage {

	const MANIFEST_OPTION = 'siteintelix_version_switcher_manifest';
	const HISTORY_OPTION  = 'siteintelix_version_switcher_history';
	const LOCK_OPTION     = 'siteintelix_version_switcher_lock';
	const HISTORY_LIMIT   = 100;
	const LOCK_TTL        = 300;

	/** @return string */
	public static function directory() {
		$default = WP_CONTENT_DIR . '/siteintelix/version-vault';
		$path    = defined( 'SITEINTELIX_VERSION_VAULT_DIR' ) && SITEINTELIX_VERSION_VAULT_DIR
			? SITEINTELIX_VERSION_VAULT_DIR
			: $default;

		/**
		 * Filter the private Version Vault directory.
		 *
		 * Prefer a location outside the public web root on production systems.
		 *
		 * @param string $path    Vault directory.
		 * @param string $default Default directory.
		 */
		$path = apply_filters( 'siteintelix_version_vault_directory', $path, $default );

		return untrailingslashit( wp_normalize_path( (string) $path ) );
	}

	/** @return string */
	public static function recovery_directory() {
		return untrailingslashit( wp_normalize_path( dirname( self::directory() ) . '/version-switcher-recovery' ) );
	}

	/** @return true|WP_Error */
	public static function ensure() {
		foreach ( array( self::directory(), self::recovery_directory() ) as $directory ) {
			if ( ! self::safe_storage_directory( $directory ) ) {
				return new WP_Error( 'siteintelix_version_vault_configuration', __( 'The configured Version Vault location is not safe.', 'siteintelix' ) );
			}
			if ( is_link( $directory ) || ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) ) {
				return new WP_Error( 'siteintelix_version_vault_unavailable', __( 'The protected Version Vault could not be initialized.', 'siteintelix' ) );
			}

			if ( ! is_writable( $directory ) ) {
				return new WP_Error( 'siteintelix_version_vault_not_writable', __( 'The protected Version Vault is not writable.', 'siteintelix' ) );
			}
			$real_directory = realpath( $directory );
			if ( false === $real_directory || untrailingslashit( wp_normalize_path( $real_directory ) ) !== untrailingslashit( wp_normalize_path( $directory ) ) ) {
				return new WP_Error( 'siteintelix_version_vault_configuration', __( 'The configured Version Vault location resolves through an unsafe symbolic path.', 'siteintelix' ) );
			}

			@chmod( $directory, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening; writability is checked above.
			$result = self::write_protection_files( $directory );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Get the package manifest keyed by opaque package ID.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function manifest() {
		$manifest = get_option( self::MANIFEST_OPTION, array() );
		return is_array( $manifest ) ? $manifest : array();
	}

	/** @param string $package_id Package ID. @return array<string,mixed>|WP_Error */
	public static function package( $package_id ) {
		$package_id = sanitize_text_field( (string) $package_id );
		$manifest   = self::manifest();
		if ( ! self::valid_package_id( $package_id ) || ! isset( $manifest[ $package_id ] ) || ! is_array( $manifest[ $package_id ] ) ) {
			return new WP_Error( 'siteintelix_version_package_missing', __( 'The requested stored build could not be found.', 'siteintelix' ) );
		}
		return $manifest[ $package_id ];
	}

	/** @param array<string,mixed> $package Package data. @return true|WP_Error */
	public static function add_package( $package ) {
		if ( empty( $package['package_id'] ) || ! self::valid_package_id( $package['package_id'] ) || empty( $package['stored_filename'] ) || ! self::valid_filename( $package['stored_filename'] ) ) {
			return new WP_Error( 'siteintelix_version_invalid_manifest', __( 'The stored build metadata is invalid.', 'siteintelix' ) );
		}
		$manifest                           = self::manifest();
		$manifest[ $package['package_id'] ] = $package;
		update_option( self::MANIFEST_OPTION, $manifest, false );
		return true;
	}

	/** @param string $package_id Package ID. @return true|WP_Error */
	public static function delete_package( $package_id ) {
		$package = self::package( $package_id );
		if ( is_wp_error( $package ) ) {
			return $package;
		}
		$path = self::package_path( $package, true );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( ! unlink( $path ) ) {
			return new WP_Error( 'siteintelix_version_delete_failed', __( 'The stored build could not be deleted.', 'siteintelix' ) );
		}
		$manifest = self::manifest();
		unset( $manifest[ $package_id ] );
		update_option( self::MANIFEST_OPTION, $manifest, false );
		return true;
	}

	/** @param array<string,mixed> $package Package data. @param bool $must_exist Require file. @return string|WP_Error */
	public static function package_path( $package, $must_exist = false ) {
		$filename = isset( $package['stored_filename'] ) ? (string) $package['stored_filename'] : '';
		if ( ! self::valid_filename( $filename ) ) {
			return new WP_Error( 'siteintelix_version_unsafe_package', __( 'The stored build is not available.', 'siteintelix' ) );
		}
		$directory = self::directory();
		$path      = wp_normalize_path( trailingslashit( $directory ) . $filename );
		if ( is_link( $directory ) || is_link( $path ) || ! self::contains( $directory, $path ) ) {
			return new WP_Error( 'siteintelix_version_unsafe_package', __( 'The stored build is not available.', 'siteintelix' ) );
		}
		if ( $must_exist && ( ! is_file( $path ) || ! is_readable( $path ) ) ) {
			return new WP_Error( 'siteintelix_version_package_file_missing', __( 'The stored build file is missing or unreadable.', 'siteintelix' ) );
		}
		if ( $must_exist && realpath( dirname( $path ) ) !== realpath( $directory ) ) {
			return new WP_Error( 'siteintelix_version_unsafe_package', __( 'The stored build is not available.', 'siteintelix' ) );
		}
		return $path;
	}

	/** @param array<string,mixed> $entry History entry. @return void */
	public static function add_history( $entry ) {
		$history = self::history();
		array_unshift(
			$history,
			array(
				'action'           => isset( $entry['action'] ) ? sanitize_key( $entry['action'] ) : 'switch',
				'plugin_name'      => isset( $entry['plugin_name'] ) ? sanitize_text_field( $entry['plugin_name'] ) : '',
				'plugin_slug'      => isset( $entry['plugin_slug'] ) ? sanitize_key( $entry['plugin_slug'] ) : '',
				'previous_version' => isset( $entry['previous_version'] ) ? sanitize_text_field( $entry['previous_version'] ) : '',
				'target_version'   => isset( $entry['target_version'] ) ? sanitize_text_field( $entry['target_version'] ) : '',
				'success'          => ! empty( $entry['success'] ),
				'error_code'       => isset( $entry['error_code'] ) ? sanitize_key( $entry['error_code'] ) : '',
				'error_message'    => isset( $entry['error_message'] ) ? self::safe_message( $entry['error_message'] ) : '',
				'user_id'          => isset( $entry['user_id'] ) ? absint( $entry['user_id'] ) : get_current_user_id(),
				'timestamp'        => time(),
			)
		);
		update_option( self::HISTORY_OPTION, array_slice( $history, 0, self::HISTORY_LIMIT ), false );
	}

	/** @return array<int,array<string,mixed>> */
	public static function history() {
		$history = get_option( self::HISTORY_OPTION, array() );
		return is_array( $history ) ? array_slice( array_values( $history ), 0, self::HISTORY_LIMIT ) : array();
	}

	/** @return string|WP_Error Lock token or error. */
	public static function acquire_lock() {
		$existing = is_multisite() ? get_site_option( self::LOCK_OPTION, array() ) : get_option( self::LOCK_OPTION, array() );
		if ( is_array( $existing ) && ! empty( $existing['time'] ) && ( time() - absint( $existing['time'] ) ) < self::LOCK_TTL ) {
			return new WP_Error( 'siteintelix_version_switch_locked', __( 'Another plugin version switch is already in progress. Try again shortly.', 'siteintelix' ) );
		}
		if ( ! empty( $existing ) ) {
			is_multisite() ? delete_site_option( self::LOCK_OPTION ) : delete_option( self::LOCK_OPTION );
		}
		$token = wp_generate_password( 32, false, false );
		$value = array( 'token' => $token, 'time' => time() );
		$added = is_multisite() ? add_site_option( self::LOCK_OPTION, $value ) : add_option( self::LOCK_OPTION, $value, '', false );
		return $added ? $token : new WP_Error( 'siteintelix_version_switch_locked', __( 'Another plugin version switch is already in progress. Try again shortly.', 'siteintelix' ) );
	}

	/** @param string $token Lock token. @return void */
	public static function release_lock( $token ) {
		$current = is_multisite() ? get_site_option( self::LOCK_OPTION, array() ) : get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) {
			is_multisite() ? delete_site_option( self::LOCK_OPTION ) : delete_option( self::LOCK_OPTION );
		}
	}

	/** @return bool */
	public static function may_be_web_accessible() {
		$directory = self::directory();
		return self::contains( ABSPATH, $directory ) || self::contains( WP_CONTENT_DIR, $directory );
	}

	/**
	 * Remove filesystem details and randomized storage names from user-visible errors.
	 *
	 * @param string $message Error message.
	 * @return string
	 */
	public static function safe_message( $message ) {
		$message = sanitize_text_field( (string) $message );
		foreach ( array( ABSPATH, WP_CONTENT_DIR, self::directory(), self::recovery_directory(), get_temp_dir() ) as $private_root ) {
			if ( is_string( $private_root ) && '' !== $private_root ) {
				$message = str_replace( array( $private_root, wp_normalize_path( $private_root ) ), __( '[private path]', 'siteintelix' ), $message );
			}
		}
		$message = preg_replace( '/build-[a-f0-9]{40}\.zip/i', __( '[private build]', 'siteintelix' ), $message );
		$message = preg_replace( '#(?:[A-Za-z]:[\\\\/]|/)(?:[^\s<>"\']+[\\\\/])+[^\s<>"\']*#', __( '[private path]', 'siteintelix' ), $message );
		return sanitize_text_field( $message );
	}

	/** @param string $filename Filename. @return bool */
	public static function valid_filename( $filename ) {
		return is_string( $filename ) && 1 === preg_match( '/^build-[a-f0-9]{40}\.zip$/', $filename );
	}

	/** @param string $package_id Package ID. @return bool */
	public static function valid_package_id( $package_id ) {
		return is_string( $package_id ) && 1 === preg_match( '/^[a-f0-9-]{36}$/', $package_id );
	}

	/** @param string $root Root. @param string $path Path. @return bool */
	public static function contains( $root, $path ) {
		$root = untrailingslashit( wp_normalize_path( (string) $root ) );
		$path = wp_normalize_path( (string) $path );
		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}

	/** @param string $directory Directory. @return true|WP_Error */
	private static function write_protection_files( $directory ) {
		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "# SiteIntelix private Version Switcher storage\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = wp_normalize_path( trailingslashit( $directory ) . $name );
			if ( file_exists( $path ) ) {
				if ( is_link( $path ) || ! is_file( $path ) ) {
					return new WP_Error( 'siteintelix_version_vault_protection', __( 'The Version Vault protection files are invalid.', 'siteintelix' ) );
				}
				continue;
			}
			if ( false === file_put_contents( $path, $contents, LOCK_EX ) ) {
				return new WP_Error( 'siteintelix_version_vault_protection', __( 'The Version Vault protection files could not be created.', 'siteintelix' ) );
			}
			@chmod( $path, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.
		}
		return true;
	}

	/** @param string $directory Directory. @return bool */
	private static function safe_storage_directory( $directory ) {
		$directory = untrailingslashit( wp_normalize_path( (string) $directory ) );
		$is_absolute = '/' === substr( $directory, 0, 1 ) || 1 === preg_match( '/^[A-Za-z]:\//', $directory );
		if ( ! $is_absolute || false !== strpos( $directory, '/../' ) || in_array( $directory, array( '', '/', untrailingslashit( wp_normalize_path( ABSPATH ) ), untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) ), untrailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) ) ), true ) ) {
			return false;
		}
		if ( self::contains( WP_PLUGIN_DIR, $directory ) || ( defined( 'SITEINTELIX_PLUGIN_DIR' ) && self::contains( SITEINTELIX_PLUGIN_DIR, $directory ) ) ) {
			return false;
		}
		return true;
	}
}
