<?php
/**
 * SITEINTELIX_WP_Config — safe wp-config.php editor for debug constants.
 *
 * Inserts and removes a clearly-marked block of debug constants.
 * Always backs up before writing. Never duplicates existing defines.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_WP_Config
 */
class SITEINTELIX_WP_Config {

	/** Block start marker inserted into wp-config.php. */
	const MARKER_START = '// BEGIN SiteIntelix Debug';

	/** Block end marker inserted into wp-config.php. */
	const MARKER_END = '// END SiteIntelix Debug';

	/** Sentinel line WordPress uses to mark end of user edits. */
	const SENTINEL = "/* That's all, stop editing!";

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Locate wp-config.php.
	 *
	 * WordPress looks in ABSPATH first, then one directory up.
	 *
	 * @return string|false  Absolute path or false if not found.
	 */
	public static function locate() {
		$primary = ABSPATH . 'wp-config.php';
		if ( file_exists( $primary ) ) {
			return $primary;
		}

		$parent = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent;
		}

		return false;
	}

	/**
	 * Check whether the located wp-config.php is writable.
	 *
	 * @return bool
	 */
	public static function is_writable() {
		$path = self::locate();
		if ( false === $path ) {
			return false;
		}

		return wp_is_writable( $path );
	}

	/**
	 * Create a .bak backup of wp-config.php.
	 *
	 * @return true|WP_Error
	 */
	public static function backup() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$backup = $path . '.bak';
		if ( ! $fs->copy( $path, $backup, true ) ) {
			return new WP_Error( 'siteintelix_wpc_backup', __( 'Could not create wp-config.php backup.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Insert debug constants into wp-config.php.
	 *
	 * Inserts a tagged block before the sentinel line. Skips any constant
	 * that already appears as a define() elsewhere in the file.
	 *
	 * @return true|WP_Error
	 */
	public static function enable() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		if ( ! self::is_writable() ) {
			return new WP_Error( 'siteintelix_wpc_readonly', __( 'wp-config.php is not writable.', 'siteintelix' ) );
		}

		// Already inserted?
		if ( self::has_siteintelix_block() ) {
			return true;
		}

		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$contents = $fs->get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'siteintelix_wpc_read', __( 'Could not read wp-config.php.', 'siteintelix' ) );
		}

		// Backup first.
		$backup_result = self::backup();
		if ( is_wp_error( $backup_result ) ) {
			return $backup_result;
		}

		// Build the block — only include constants not already defined.
		$constants = array(
			'WP_DEBUG'         => 'true',
			'WP_DEBUG_LOG'     => 'true',
			'WP_DEBUG_DISPLAY' => 'false',
			'SCRIPT_DEBUG'     => 'true',
		);

		$lines = array( self::MARKER_START );
		foreach ( $constants as $name => $value ) {
			if ( ! self::constant_exists_in_file( $contents, $name ) ) {
				$lines[] = "define( '{$name}', {$value} );";
			}
		}
		$lines[] = self::MARKER_END;

		// Nothing to add?
		if ( count( $lines ) <= 2 ) {
			return true;
		}

		$block = "\n" . implode( "\n", $lines ) . "\n";

		// Insert before the sentinel.
		$sentinel_pos = strpos( $contents, self::SENTINEL );
		if ( false !== $sentinel_pos ) {
			$contents = substr_replace( $contents, $block, $sentinel_pos, 0 );
		} else {
			// Fallback: append before closing PHP tag or end of file.
			$close_tag = strrpos( $contents, '?>' );
			if ( false !== $close_tag ) {
				$contents = substr_replace( $contents, $block, $close_tag, 0 );
			} else {
				$contents .= $block;
			}
		}

		if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Remove only the SiteIntelix-managed block from wp-config.php.
	 *
	 * @return true|WP_Error
	 */
	public static function disable() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		if ( ! self::has_siteintelix_block() ) {
			return true; // Nothing to remove.
		}

		if ( ! self::is_writable() ) {
			return new WP_Error( 'siteintelix_wpc_readonly', __( 'wp-config.php is not writable.', 'siteintelix' ) );
		}

		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return $fs;
		}

		$contents = $fs->get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'siteintelix_wpc_read', __( 'Could not read wp-config.php.', 'siteintelix' ) );
		}

		// Backup first.
		$backup_result = self::backup();
		if ( is_wp_error( $backup_result ) ) {
			return $backup_result;
		}

		// Remove the block including surrounding newlines.
		$pattern = '/\n?' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\n?/s';
		$contents = preg_replace( $pattern, '', $contents );

		if ( ! $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Check whether the SiteIntelix block exists in wp-config.php.
	 *
	 * @return bool
	 */
	public static function has_siteintelix_block() {
		$path = self::locate();
		if ( false === $path ) {
			return false;
		}

		$fs = self::filesystem();
		if ( is_wp_error( $fs ) ) {
			return false;
		}

		$contents = $fs->get_contents( $path );
		if ( false === $contents ) {
			return false;
		}

		return false !== strpos( $contents, self::MARKER_START );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Initialise and return the WP_Filesystem global.
	 *
	 * @return WP_Filesystem_Base|WP_Error
	 */
	private static function filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return new WP_Error( 'siteintelix_wpc_fs', __( 'Could not initialise the filesystem API.', 'siteintelix' ) );
		}

		return $wp_filesystem;
	}

	/**
	 * Check whether a define( 'CONSTANT_NAME' ) call exists in raw file contents.
	 *
	 * @param string $contents File contents.
	 * @param string $name     Constant name.
	 * @return bool
	 */
	private static function constant_exists_in_file( $contents, $name ) {
		// Match define( 'NAME' or define('NAME' with optional whitespace.
		return (bool) preg_match( '/define\s*\(\s*[\'"]' . preg_quote( $name, '/' ) . '[\'"]\s*,/', $contents );
	}
}
