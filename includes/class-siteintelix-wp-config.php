<?php
/**
 * SITEINTELIX_WP_Config — minimal, surgical wp-config.php debug toggler.
 *
 * Strategy:
 *   ENABLE  → find existing debug define() lines and set WP_DEBUG true,
 *             WP_DEBUG_LOG to the SiteIntelix log path, and display off.
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
	 * @var array<string, bool|string>  name => value_when_enabled
	 */
	private static $toggle_map = array(
		'WP_DEBUG'         => true,
		'WP_DEBUG_LOG'     => '',
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
	 * Missing debug constants are inserted next to WP_DEBUG when needed.
	 *
	 * @return true|WP_Error
	 */
	public static function enable() {
		self::$toggle_map['WP_DEBUG_LOG'] = self::get_debug_log_path();
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

	/**
	 * Add a constant define() to wp-config.php only if not already present.
	 *
	 * The new define is inserted before the standard "stop editing" comment.
	 *
	 * @param string $constant Constant name.
	 * @param bool   $value    Boolean constant value.
	 * @return true|WP_Error
	 */
	public static function define_if_missing( $constant, $value ) {
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

		$pattern = '/define\s*\(\s*[\'"]' . preg_quote( $constant, '/' ) . '[\'"]\s*,/i';
		if ( preg_match( $pattern, $contents ) ) {
			return true;
		}

		$value_str = $value ? 'true' : 'false';
		$line      = "define( '" . $constant . "', " . $value_str . " );\n";

		$new_contents = preg_replace(
			'/\/\*\s*That\'s all,\s*stop editing!\s*Happy publishing\.\s*\*\//i',
			$line . "\n" . '$0',
			$contents,
			1
		);

		if ( ! is_string( $new_contents ) || $new_contents === $contents ) {
			return new WP_Error( 'siteintelix_wpc_insert', __( 'Could not safely insert constant into wp-config.php.', 'siteintelix' ) );
		}

		if ( false === self::write_file( $path, $new_contents ) ) {
			return new WP_Error( 'siteintelix_wpc_write', __( 'Could not write to wp-config.php.', 'siteintelix' ) );
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Get the absolute custom debug log path used by both SiteIntelix modes.
	 *
	 * @return string
	 */
	private static function get_debug_log_path() {
		return trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-debug.log';
	}

	/**
	 * Convert a PHP value into a wp-config.php define literal.
	 *
	 * @param bool|string $value Constant value.
	 * @return string
	 */
	private static function php_literal( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return "'" . str_replace( "'", "\\'", (string) $value ) . "'";
	}

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

		// Ensure all constants exist in the file.
		// If WP_DEBUG exists but others are missing, surgically insert them after WP_DEBUG.
		$contents = self::ensure_constants_exist( $contents, $enable );

		$modified = false;

		foreach ( self::$toggle_map as $constant => $enabled_value ) {
			// When enabling use the map value; when disabling use false.
			$target_value = $enable ? $enabled_value : false;
			$target_str   = self::php_literal( $target_value );

			// Captures everything around the value so we can replace just the value.
			$pattern = '/(define\s*\(\s*[\'"]' . preg_quote( $constant, '/' ) . '[\'"]\s*,\s*)(.*?)(\s*\)\s*;)/i';

			$new_contents = preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( $target_str ) {
					return $matches[1] . $target_str . $matches[3];
				},
				$contents
			);

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
	 * Ensures that WP_DEBUG_LOG and WP_DEBUG_DISPLAY exist if WP_DEBUG is present.
	 * Inserts them right after the WP_DEBUG line if missing.
	 *
	 * @param string $contents Raw wp-config.php contents.
	 * @param bool   $enable   Whether debug mode is being enabled.
	 * @return string Modified contents.
	 */
	private static function ensure_constants_exist( $contents, $enable ) {
		// If WP_DEBUG is missing entirely, we don't want to mess with it (stay surgical).
		if ( ! preg_match( '/define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,/i', $contents, $matches ) ) {
			return $contents;
		}

		foreach ( array( 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY' ) as $const ) {
			if ( ! preg_match( '/define\s*\(\s*[\'"]' . $const . '[\'"]\s*,/i', $contents ) ) {
				// Constant is missing. Find WP_DEBUG line and insert after it.
				$pattern = '/^([ \t]*define\s*\(\s*[\'"]WP_DEBUG[\'"]\s*,.*?\);)(.*?)$/im';

				$contents = preg_replace_callback( $pattern, function( $m ) use ( $const, $enable ) {
					$indent = '';
					if ( preg_match( '/^([ \t]*)/', $m[1], $mi ) ) {
						$indent = $mi[1];
					}
					$value = false;
					if ( $enable && 'WP_DEBUG_LOG' === $const ) {
						$value = self::get_debug_log_path();
					}
					// Return original line + the new constant line with same indentation.
					$comment = ( 'WP_DEBUG_LOG' === $const ) ? $indent . '// Set a custom path for the SiteIntelix debug log file.' . "\n" : '';
					return $m[1] . "\n" . $comment . $indent . "define( '" . $const . "', " . self::php_literal( $value ) . " );" . $m[2];
				}, $contents );
			}
		}

		return $contents;
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
