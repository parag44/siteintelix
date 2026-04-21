<?php
/**
 * SITEINTELIX_WP_Config — minimal, surgical wp-config.php debug toggler.
 *
 * Strategy:
 *   ENABLE  → find existing define( 'WP_DEBUG', ... ) and define( 'WP_DEBUG_LOG', ... )
 *             lines and change their value to true.
 *   DISABLE → change those same values back to false.
 *
 * No blocks are inserted or removed. No markers. The original code structure
 * is preserved. A .bak backup is created before every write.
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

	/**
	 * Constants we toggle. Order matters — WP_DEBUG must come first.
	 *
	 * @var array<string, bool>  name => value_when_enabled
	 */
	private static $toggle_map = array(
		'WP_DEBUG'         => true,
		'WP_DEBUG_LOG'     => true,
		'WP_DEBUG_DISPLAY' => false, // keep display off even when enabled.
	);

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

		// WordPress sometimes lives one level up.
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
		return is_writable( $path );
	}

	/**
	 * Backup wp-config.php to wp-config.php.bak.
	 *
	 * @return true|WP_Error
	 */
	public static function backup() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		$contents = self::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return $contents;
		}

		if ( false === self::write_file( $path . '.bak', $contents ) ) {
			return new WP_Error( 'siteintelix_wpc_backup', __( 'Could not create wp-config.php backup.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Enable WP_DEBUG by changing existing define() values to true/false.
	 *
	 * Only modifies lines that already exist. Does NOT insert new defines.
	 *
	 * @return true|WP_Error
	 */
	public static function enable() {
		return self::apply( true );
	}

	/**
	 * Disable WP_DEBUG by changing existing define() values back to false.
	 *
	 * @return true|WP_Error
	 */
	public static function disable() {
		return self::apply( false );
	}

	/**
	 * Check whether WP_DEBUG is currently set to true in wp-config.php.
	 *
	 * @return bool
	 */
	public static function is_debug_enabled_in_file() {
		$path = self::locate();
		if ( false === $path || ! file_exists( $path ) ) {
			return false;
		}

		$contents = self::read_file( $path );
		if ( is_wp_error( $contents ) ) {
			return false;
		}

		// Match:  define( 'WP_DEBUG', true );
		return (bool) preg_match(
			'/define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,\s*true\s*\)/i',
			$contents
		);
	}

	/**
	 * Alias kept for backward-compatibility with SITEINTELIX_Debug_Source.
	 *
	 * Previously this checked for a block-marker; now it checks the actual
	 * WP_DEBUG value in the file.
	 *
	 * @return bool
	 */
	public static function has_siteintelix_block() {
		return self::is_debug_enabled_in_file();
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Core toggle logic — sets each constant to its enabled or disabled value.
	 *
	 * @param bool $enable  TRUE → set debug values; FALSE → reset to off.
	 * @return true|WP_Error
	 */
	private static function apply( $enable ) {
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

		// Backup before any change.
		$backup = self::backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		$modified = false;

		foreach ( self::$toggle_map as $constant => $enabled_value ) {
			// When enabling use the map value; when disabling use false.
			$target_value = $enable ? $enabled_value : false;
			$target_str   = $target_value ? 'true' : 'false';
			$opposite_str = $target_value ? 'false' : 'true';

			// Pattern: define( 'CONSTANT', <any bool or numeric 0/1> );
			// Captures everything around the value so we can replace just the value.
			$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $constant, '/' ) . '[\'"]\s*,\s*)(' . $opposite_str . '|' . $target_str . ')(\s*\))/i';

			$new_contents = preg_replace( $pattern, '${1}' . $target_str . '${3}', $contents );

			if ( null !== $new_contents && $new_contents !== $contents ) {
				$contents = $new_contents;
				$modified = true;
			}
		}

		// Only write if something actually changed.
		if ( $modified ) {
			if ( false === self::write_file( $path, $contents ) ) {
				return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
			}
		}

		return true;
	}

	/**
	 * Read a file with direct PHP (reliable in admin-post context).
	 *
	 * @param string $path Absolute path.
	 * @return string|WP_Error
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
	 * Write a file with direct PHP (reliable in admin-post context).
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents Content to write.
	 * @return bool
	 */
	private static function write_file( $path, $contents ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return false !== file_put_contents( $path, $contents );
	}
}
