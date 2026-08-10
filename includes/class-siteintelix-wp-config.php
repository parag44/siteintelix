<?php
/**
 * Manage WordPress debug constants with WP-CLI's WPConfigTransformer.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_transformer = SITEINTELIX_PLUGIN_DIR . 'vendor/wp-cli/wp-config-transformer/src/WPConfigTransformer.php';
if ( ! class_exists( 'SiteIntelixVendor\\WPConfigTransformer', false ) && file_exists( $siteintelix_transformer ) ) {
	require_once $siteintelix_transformer;
}
unset( $siteintelix_transformer );

/**
 * Safe SiteIntelix adapter around WPConfigTransformer.
 */
class SITEINTELIX_WP_Config {

	/** Constants exposed by the Debug Log Viewer. */
	const WP_DEBUG         = 'WP_DEBUG';
	const WP_DEBUG_LOG     = 'WP_DEBUG_LOG';
	const WP_DEBUG_DISPLAY = 'WP_DEBUG_DISPLAY';
	const SCRIPT_DEBUG     = 'SCRIPT_DEBUG';
	const SAVEQUERIES      = 'SAVEQUERIES';

	/** Option recording that SiteIntelix has written the debug configuration. */
	const MANAGED_OPTION = 'siteintelix_wp_config_debug_managed';

	/** @return array<int,string> */
	private static function allowed_constants() {
		return array( self::WP_DEBUG, self::WP_DEBUG_LOG, self::WP_DEBUG_DISPLAY, self::SCRIPT_DEBUG, self::SAVEQUERIES );
	}

	/** Locate the active wp-config.php. @return string|false */
	public static function locate() {
		$primary = ABSPATH . 'wp-config.php';
		if ( is_file( $primary ) ) {
			return $primary;
		}

		$parent = dirname( ABSPATH ) . '/wp-config.php';
		if ( is_file( $parent ) && ! is_file( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent;
		}

		return false;
	}

	/** @return bool */
	public static function is_writable() {
		$path = self::locate();
		return false !== $path && is_writable( $path );
	}

	/** @return SiteIntelixVendor\WPConfigTransformer|WP_Error */
	private static function transformer( $read_only = false ) {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}
		if ( ! class_exists( 'SiteIntelixVendor\\WPConfigTransformer' ) ) {
			return new WP_Error( 'siteintelix_wpc_transformer', __( 'WPConfigTransformer could not be loaded.', 'siteintelix' ) );
		}

		try {
			return new SiteIntelixVendor\WPConfigTransformer( $path, (bool) $read_only );
		} catch ( Exception $exception ) {
			return new WP_Error( 'siteintelix_wpc_open', sanitize_text_field( $exception->getMessage() ) );
		}
	}

	/** Create an adjacent backup before changing wp-config.php. @return true|WP_Error */
	public static function backup() {
		$path = self::locate();
		if ( false === $path ) {
			return new WP_Error( 'siteintelix_wpc_not_found', __( 'wp-config.php could not be located.', 'siteintelix' ) );
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'siteintelix_wpc_read', __( 'Could not read wp-config.php.', 'siteintelix' ) );
		}

		$backup = $path . '.siteintelix.bak';
		if ( is_link( $backup ) || ( file_exists( $backup ) && ! is_file( $backup ) ) ) {
			return new WP_Error( 'siteintelix_wpc_backup', __( 'The wp-config.php backup path is not safe.', 'siteintelix' ) );
		}
		if ( is_file( $backup ) ) {
			return true;
		}
		if ( false === file_put_contents( $backup, $contents, LOCK_EX ) ) {
			return new WP_Error( 'siteintelix_wpc_backup', __( 'Could not create the wp-config.php backup.', 'siteintelix' ) );
		}
		@chmod( $backup, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return true;
	}

	/** Resolve the private randomized log path. @return string|WP_Error */
	public static function get_debug_log_path() {
		if ( ! class_exists( 'SITEINTELIX_Debug_Storage' ) ) {
			return new WP_Error( 'siteintelix_debug_storage', __( 'Private debug storage is unavailable.', 'siteintelix' ) );
		}
		$storage = SITEINTELIX_Debug_Storage::ensure();
		if ( is_wp_error( $storage ) ) {
			return $storage;
		}
		return SITEINTELIX_Debug_Storage::path( 'active' );
	}

	/** Convert a transformer value into a boolean. @param mixed $value Value. @return bool */
	private static function value_is_enabled( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( 'true', '1', "'1'", '"1"' ), true );
	}

	/** Determine whether a log value enables logging. @param mixed $value Value. @return bool */
	private static function log_value_is_enabled( $value ) {
		$value = trim( (string) $value );
		if ( self::value_is_enabled( $value ) ) {
			return true;
		}
		return ! in_array( strtolower( $value ), array( '', 'false', '0', "'0'", '"0"', "''", '""' ), true );
	}

	/** Read current values directly from wp-config.php. @return array<string,mixed> */
	public static function get_state() {
		$state = array(
			self::WP_DEBUG         => false,
			self::WP_DEBUG_LOG     => false,
			self::WP_DEBUG_DISPLAY => false,
			self::SCRIPT_DEBUG     => false,
			self::SAVEQUERIES      => false,
			'writable'             => self::is_writable(),
		);
		$transformer = self::transformer( true );
		if ( is_wp_error( $transformer ) ) {
			$state['error'] = $transformer->get_error_message();
			return $state;
		}

		foreach ( self::allowed_constants() as $constant ) {
			try {
				if ( ! $transformer->exists( 'constant', $constant ) ) {
					continue;
				}
				$value = $transformer->get_value( 'constant', $constant );
				$state[ $constant ] = self::WP_DEBUG_LOG === $constant ? self::log_value_is_enabled( $value ) : self::value_is_enabled( $value );
			} catch ( Exception $exception ) {
				$state['error'] = sanitize_text_field( $exception->getMessage() );
				break;
			}
		}
		return $state;
	}

	/** Update one allowlisted constant. @param string $constant Constant. @param bool $enabled State. @return true|WP_Error */
	public static function update_constant( $constant, $enabled ) {
		$constant = strtoupper( sanitize_key( $constant ) );
		if ( ! in_array( $constant, self::allowed_constants(), true ) ) {
			return new WP_Error( 'siteintelix_wpc_constant', __( 'That debug setting is not supported.', 'siteintelix' ) );
		}
		if ( ! self::is_writable() ) {
			return new WP_Error( 'siteintelix_wpc_readonly', __( 'wp-config.php is not writable. Check its file permissions.', 'siteintelix' ) );
		}

		$transformer = self::transformer();
		if ( is_wp_error( $transformer ) ) {
			return $transformer;
		}
		$backup = self::backup();
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		$value = $enabled ? 'true' : 'false';
		$raw   = true;
		if ( self::WP_DEBUG_LOG === $constant && $enabled ) {
			$value = self::get_debug_log_path();
			if ( is_wp_error( $value ) ) {
				return $value;
			}
			$raw = false;
		}

		try {
			$transformer->update( 'constant', $constant, (string) $value, array( 'raw' => $raw ) );
			if ( $enabled && in_array( $constant, array( self::WP_DEBUG_LOG, self::WP_DEBUG_DISPLAY ), true ) ) {
				$transformer->update( 'constant', self::WP_DEBUG, 'true', array( 'raw' => true ) );
			}
		} catch ( Exception $exception ) {
			return new WP_Error( 'siteintelix_wpc_write', sanitize_text_field( $exception->getMessage() ) );
		}

		update_option( self::MANAGED_OPTION, 1, false );
		$state = self::get_state();
		update_option( SITEINTELIX_MU_DEBUG_OPTION, ! empty( $state[ self::WP_DEBUG ] ) && ! empty( $state[ self::WP_DEBUG_LOG ] ) ? 1 : 0, false );
		return true;
	}

	/** Enable secure WordPress debug logging. @return true|WP_Error */
	public static function enable() {
		foreach ( array( self::WP_DEBUG => true, self::WP_DEBUG_LOG => true, self::WP_DEBUG_DISPLAY => false ) as $constant => $enabled ) {
			$result = self::update_constant( $constant, $enabled );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/** Disable SiteIntelix debug logging while keeping errors hidden. @return true|WP_Error */
	public static function disable() {
		foreach ( array( self::WP_DEBUG_DISPLAY, self::WP_DEBUG_LOG, self::WP_DEBUG ) as $constant ) {
			$result = self::update_constant( $constant, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return true;
	}

	/** @return bool */
	public static function is_debug_enabled_in_file() {
		$state = self::get_state();
		return ! empty( $state[ self::WP_DEBUG ] );
	}

	/** Backward-compatible alias. @return bool */
	public static function has_siteintelix_block() {
		return self::is_debug_enabled_in_file();
	}

	/** Add a boolean constant if missing. @param string $constant Name. @param bool $value Value. @return true|WP_Error */
	public static function define_if_missing( $constant, $value ) {
		$transformer = self::transformer();
		if ( is_wp_error( $transformer ) ) {
			return $transformer;
		}
		try {
			if ( ! $transformer->exists( 'constant', $constant ) ) {
				$backup = self::backup();
				if ( is_wp_error( $backup ) ) {
					return $backup;
				}
				$transformer->add( 'constant', $constant, $value ? 'true' : 'false', array( 'raw' => true ) );
			}
		} catch ( Exception $exception ) {
			return new WP_Error( 'siteintelix_wpc_write', sanitize_text_field( $exception->getMessage() ) );
		}
		return true;
	}
}
