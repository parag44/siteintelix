<?php
/**
 * Private storage for SiteIntelix debug logs.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns the private debug-log directory and identifiers. */
class SITEINTELIX_Debug_Storage {

	const FILENAME_OPTION = 'siteintelix_debug_log_filename';
	const MIGRATION_OPTION = 'siteintelix_debug_log_migration';

	/** @return string */
	public static function directory() {
		return untrailingslashit( wp_normalize_path( WP_CONTENT_DIR . '/siteintelix' ) );
	}

	/** @return string */
	public static function filename() {
		$filename = get_option( self::FILENAME_OPTION, '' );
		if ( ! self::valid_filename( $filename ) ) {
			$filename = 'debug-' . strtolower( wp_generate_password( 40, false, false ) ) . '.log';
			if ( ! self::valid_filename( $filename ) ) {
				$filename = 'debug-' . bin2hex( random_bytes( 20 ) ) . '.log';
			}
			update_option( self::FILENAME_OPTION, $filename, false );
		}
		return $filename;
	}

	/** @param mixed $filename Filename. @return bool */
	public static function valid_filename( $filename ) {
		return is_string( $filename ) && 1 === preg_match( '/^debug-[a-z0-9]{40}\.log$/', $filename );
	}

	/** @param string $identifier active or rotated. @param bool $must_exist Require file. @return string|WP_Error */
	public static function path( $identifier = 'active', $must_exist = false ) {
		$filename = self::filename();
		if ( 'rotated' === $identifier ) {
			$filename = substr( $filename, 0, -4 ) . '-rotated.log';
		} elseif ( 'active' !== $identifier ) {
			return new WP_Error( 'siteintelix_debug_invalid_identifier', __( 'The requested log is not available.', 'siteintelix' ) );
		}
		$directory = self::directory();
		$path      = wp_normalize_path( trailingslashit( $directory ) . $filename );
		if ( is_link( $directory ) || is_link( $path ) || ! self::contains( $directory, $path ) || 'log' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return new WP_Error( 'siteintelix_debug_unsafe_path', __( 'The requested log is not available.', 'siteintelix' ) );
		}
		if ( $must_exist && ( ! is_file( $path ) || ! is_readable( $path ) ) ) {
			return new WP_Error( 'siteintelix_debug_missing', __( 'The requested log is not available.', 'siteintelix' ) );
		}
		return $path;
	}

	/** @return true|WP_Error */
	public static function ensure() {
		$directory = self::directory();
		if ( is_link( $directory ) || ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) ) {
			return new WP_Error( 'siteintelix_debug_storage', __( 'Private debug storage could not be initialized.', 'siteintelix' ) );
		}
		chmod( $directory, 0750 );
		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "# SiteIntelix private storage\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
		);
		foreach ( $files as $name => $contents ) {
			$path = $directory . '/' . $name;
			if ( file_exists( $path ) ) {
				if ( is_link( $path ) || ! is_file( $path ) ) {
					return new WP_Error( 'siteintelix_debug_protection', __( 'Private debug storage protection is invalid.', 'siteintelix' ) );
				}
				continue;
			}
			if ( false === file_put_contents( $path, $contents, LOCK_EX ) ) {
				return new WP_Error( 'siteintelix_debug_protection', __( 'Private debug storage could not be protected.', 'siteintelix' ) );
			}
			chmod( $path, 0640 );
		}
		self::migrate_legacy_logs();
		return true;
	}

	/** Migrate only the two historical fixed paths after a verified copy. @return void */
	public static function migrate_legacy_logs() {
		$status = get_option( self::MIGRATION_OPTION, array() );
		$status = is_array( $status ) ? $status : array();
		foreach ( array( 'active' => 'siteintelix-debug.log', 'rotated' => 'siteintelix-debug.log.bak' ) as $identifier => $legacy_name ) {
			if ( isset( $status[ $identifier ] ) && 'migrated' === $status[ $identifier ] ) {
				continue;
			}
			$source = wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . $legacy_name );
			$target = self::path( $identifier );
			if ( ! file_exists( $source ) ) {
				$status[ $identifier ] = 'absent';
				continue;
			}
			if ( is_wp_error( $target ) || is_link( $source ) || ! is_file( $source ) || ! is_readable( $source ) || ! is_writable( self::directory() ) ) {
				$status[ $identifier ] = 'failed';
				continue;
			}
			if ( file_exists( $target ) ) {
				$status[ $identifier ] = 'preserved';
				continue;
			}
			$temp = $target . '.migration';
			if ( is_link( $temp ) || ! copy( $source, $temp ) || filesize( $source ) !== filesize( $temp ) || hash_file( 'sha256', $source ) !== hash_file( 'sha256', $temp ) || ! rename( $temp, $target ) ) {
				if ( is_file( $temp ) && ! is_link( $temp ) ) {
					unlink( $temp );
				}
				$status[ $identifier ] = 'failed';
				continue;
			}
			chmod( $target, 0640 );
			if ( filesize( $source ) === filesize( $target ) && hash_file( 'sha256', $source ) === hash_file( 'sha256', $target ) && unlink( $source ) ) {
				$status[ $identifier ] = 'migrated';
			} else {
				$status[ $identifier ] = 'copied';
			}
		}
		update_option( self::MIGRATION_OPTION, $status, false );
	}

	/** @param string $root Root. @param string $path Path. @return bool */
	private static function contains( $root, $path ) {
		$root = untrailingslashit( wp_normalize_path( $root ) );
		$path = wp_normalize_path( $path );
		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}
}
