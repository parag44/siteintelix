<?php
/**
 * SITEINTELIX_WP_Config — safe wp-config.php editor for debug constants.
 *
 * Strategy:
 *  - Uses direct PHP file_get_contents/file_put_contents (WP_Filesystem
 *    can fail in admin-post context when no FTP credentials are configured).
 *  - Detects and REPLACES an existing native WP_DEBUG if/define block so
 *    that our values are not shadowed.
 *  - Inserts SiteIntelix-managed block before the sentinel line.
 *  - Always backs up before writing.
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
		return is_writable( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
	}

	/**
	 * Create a .bak backup of wp-config.php using direct PHP file copy.
	 *
	 * @return true|WP_Error
	 */
	public static function backup() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		$backup   = $path . '.bak';
		$contents = self::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		if ( false === self::write_file( $backup, $contents ) ) {
			return new WP_Error( 'siteintelix_wpc_backup', __( 'Could not create wp-config.php backup.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Enable WP debug constants in wp-config.php.
	 *
	 * Handles two cases:
	 *  a) An existing native WP_DEBUG block (if/define) is present → disable
	 *     (comment it out or remove) and insert our managed block.
	 *  b) No existing block → insert our block before the sentinel.
	 *
	 * @return true|WP_Error
	 */
	public static function enable() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		if ( ! self::is_writable() ) {
			return new WP_Error( 'siteintelix_wpc_readonly', __( 'wp-config.php is not writable. Check file permissions.', 'siteintelix' ) );
		}

		$contents = self::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		// Already our block? Just ensure values are correct by re-inserting.
		if ( self::has_siteintelix_block() ) {
			return true;
		}

		// Backup first.
		$backup = self::backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		// Remove any existing native WP_DEBUG if/define wrapper block so it
		// doesn't shadow our defines.
		$contents = self::remove_native_wp_debug_block( $contents );

		// Build our managed block.
		$block = "\n" . self::MARKER_START . "\n"
			. "define( 'WP_DEBUG', true );\n"
			. "define( 'WP_DEBUG_LOG', true );\n"
			. "define( 'WP_DEBUG_DISPLAY', false );\n"
			. "define( 'SCRIPT_DEBUG', true );\n"
			. self::MARKER_END . "\n";

		// Insert before the sentinel.
		$sentinel_pos = strpos( $contents, self::SENTINEL );
		if ( false !== $sentinel_pos ) {
			$contents = substr_replace( $contents, $block, $sentinel_pos, 0 );
		} else {
			// Fallback: before closing PHP tag.
			$close_tag = strrpos( $contents, '?>' );
			if ( false !== $close_tag ) {
				$contents = substr_replace( $contents, $block, $close_tag, 0 );
			} else {
				$contents .= $block;
			}
		}

		if ( false === self::write_file( $path, $contents ) ) {
			return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Remove only the SiteIntelix-managed block from wp-config.php and
	 * restore a standard WP_DEBUG false block.
	 *
	 * @return true|WP_Error
	 */
	public static function disable() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		$contents = self::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		if ( ! self::has_siteintelix_block( $contents ) ) {
			return true; // Nothing to remove.
		}

		if ( ! self::is_writable() ) {
			return new WP_Error( 'siteintelix_wpc_readonly', __( 'wp-config.php is not writable.', 'siteintelix' ) );
		}

		$backup = self::backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		// Remove our managed block.
		$contents = self::remove_siteintelix_block( $contents );

		// Re-insert the standard disabled WP_DEBUG block before the sentinel.
		$restore_block = "\n// WordPress debugging mode (disabled).\n"
			. "if ( ! defined( 'WP_DEBUG' ) ) {\n"
			. "\tdefine( 'WP_DEBUG', false );\n"
			. "\tdefine( 'WP_DEBUG_LOG', false );\n"
			. "\tdefine( 'WP_DEBUG_DISPLAY', false );\n"
			. "}\n";

		$sentinel_pos = strpos( $contents, self::SENTINEL );
		if ( false !== $sentinel_pos ) {
			$contents = substr_replace( $contents, $restore_block, $sentinel_pos, 0 );
		}

		if ( false === self::write_file( $path, $contents ) ) {
			return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Check whether the SiteIntelix block is present in wp-config.php.
	 *
	 * @param string|null $contents Optional file contents to check (avoids re-read).
	 * @return bool
	 */
	public static function has_siteintelix_block( $contents = null ) {
		if ( null === $contents ) {
			$path = self::locate();
			if ( false === $path || ! file_exists( $path ) ) {
				return false;
			}
			$contents = self::read_file( $path );
			if ( is_wp_error( $contents ) ) {
				return false;
			}
		}

		return false !== strpos( $contents, self::MARKER_START );
	}

	// -----------------------------------------------------------------------
	// Private Helpers
	// -----------------------------------------------------------------------

	/**
	 * Read a file using direct PHP (reliable in admin-post context).
	 *
	 * @param string $path Absolute file path.
	 * @return string|WP_Error  File contents or error.
	 */
	private static function read_file( $path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'siteintelix_wpc_read', __( 'Could not read wp-config.php.', 'siteintelix' ) );
		}
		return $contents;
	}

	/**
	 * Write a file using direct PHP (reliable in admin-post context).
	 *
	 * @param string $path     Absolute file path.
	 * @param string $contents Content to write.
	 * @return bool
	 */
	private static function write_file( $path, $contents ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $path, $contents );
	}

	/**
	 * Remove our SiteIntelix marker block from raw file contents.
	 *
	 * @param string $contents Raw file contents.
	 * @return string  Contents without the block.
	 */
	private static function remove_siteintelix_block( $contents ) {
		$pattern  = '/\n?' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\n?/s';
		return preg_replace( $pattern, '', $contents );
	}

	/**
	 * Detect and remove a native WordPress WP_DEBUG if/define block.
	 *
	 * Matches patterns like:
	 *   if ( ! defined( 'WP_DEBUG' ) ) {
	 *       define( 'WP_DEBUG', false );
	 *       ...
	 *   }
	 * or a bare define( 'WP_DEBUG', ... ); line.
	 *
	 * @param string $contents Raw file contents.
	 * @return string  Contents with native WP_DEBUG block removed.
	 */
	private static function remove_native_wp_debug_block( $contents ) {
		// Match: if ( ! defined( 'WP_DEBUG' ) ) { ... } (multi-line, greedy enough).
		$if_pattern = '/\n?[^\n]*?if\s*\(\s*!\s*defined\s*\(\s*[\'"]WP_DEBUG[\'"]\s*\)\s*\)[^\{]*\{[^}]*\}\n?/s';
		$cleaned    = preg_replace( $if_pattern, "\n", $contents );
		if ( null !== $cleaned ) {
			$contents = $cleaned;
		}

		// Match bare: define( 'WP_DEBUG', ... ); lines (any value).
		$define_pattern = '/\n?[ \t]*define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,[^)]*\)\s*;\n?/';
		$cleaned        = preg_replace( $define_pattern, "\n", $contents );
		if ( null !== $cleaned ) {
			$contents = $cleaned;
		}

		// Also remove bare WP_DEBUG_LOG / WP_DEBUG_DISPLAY / SCRIPT_DEBUG defines
		// that may have been left behind by the old if-block removal.
		foreach ( array( 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG' ) as $const ) {
			$pattern = '/\n?[ \t]*define\s*\(\s*[\'"]' . $const . '[\'"]\s*,[^)]*\)\s*;\n?/';
			$cleaned = preg_replace( $pattern, "\n", $contents );
			if ( null !== $cleaned ) {
				$contents = $cleaned;
			}
		}

		// Also strip ini_set display_errors lines left by the removed block.
		$ini_pattern = '/\n?[ \t]*@?ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,[^)]*\)\s*;\n?/';
		$cleaned     = preg_replace( $ini_pattern, "\n", $contents );
		if ( null !== $cleaned ) {
			$contents = $cleaned;
		}

		// Remove the preceding comment block for the native WP_DEBUG section.
		$comment_pattern = '/\n?\/\*\*\s*\n\s*\*\s*For developers: WordPress debugging mode\..*?\*\/\n?/s';
		$cleaned         = preg_replace( $comment_pattern, "\n", $contents );
		if ( null !== $cleaned ) {
			$contents = $cleaned;
		}

		return $contents;
	}
}
