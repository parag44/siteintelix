<?php
/**
 * SITEINTELIX_Debug_Source — detects the active debug source.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Debug_Source
 */
class SITEINTELIX_Debug_Source {

	/** @var string Debug is active via wp-config.php SiteIntelix block. */
	const SOURCE_WP_CONFIG = 'wp_config';

	/** @var string Debug capture is active via the MU-plugin method. */
	const SOURCE_MU_PLUGIN = 'mu_plugin';

	/** @var string Debug is not managed by SiteIntelix. */
	const SOURCE_DISABLED = 'disabled';

	/**
	 * Detect the current debug source.
	 *
	 * @return string  One of the SOURCE_* constants.
	 */
	public static function detect() {
		// Check if wp-config.php method is active: WP_DEBUG is true in the file.
		if ( 'wp_config' === self::get_method()
			&& class_exists( 'SITEINTELIX_WP_Config' )
			&& SITEINTELIX_WP_Config::is_debug_enabled_in_file()
		) {
			return self::SOURCE_WP_CONFIG;
		}

		// Check if MU-plugin capture option is enabled.
		$mu_enabled = get_option( 'siteintelix_enable_debug_capture', false );
		if ( ! empty( $mu_enabled ) ) {
			return self::SOURCE_MU_PLUGIN;
		}

		return self::SOURCE_DISABLED;
	}

	/**
	 * Get the stored debug method preference.
	 *
	 * @return string  'mu' or 'wp_config'.
	 */
	public static function get_method() {
		return get_option( 'siteintelix_debug_method', 'mu' );
	}

	/**
	 * Return a human-readable label for the current debug source.
	 *
	 * @return string  Translated label.
	 */
	public static function get_label() {
		$source = self::detect();

		switch ( $source ) {
			case self::SOURCE_WP_CONFIG:
				return __( 'Enabled via wp-config.php', 'siteintelix' );

			case self::SOURCE_MU_PLUGIN:
				return __( 'Enabled via MU Plugin', 'siteintelix' );

			default:
				return __( 'Disabled', 'siteintelix' );
		}
	}

	/**
	 * Return 'good', 'warning', or 'critical' status string for display.
	 *
	 * @return string
	 */
	public static function get_status() {
		$source = self::detect();

		if ( self::SOURCE_DISABLED === $source ) {
			return 'good';
		}

		// Debug is active — warning unless it is production.
		$env = defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production';
		return ( 'production' === $env ) ? 'critical' : 'warning';
	}

	/**
	 * Check whether wp-config.php defines WP_DEBUG independently of SiteIntelix.
	 *
	 * @return bool  True if WP_DEBUG is defined AND our block is NOT present.
	 */
	public static function has_external_wp_debug() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}

		if ( class_exists( 'SITEINTELIX_WP_Config' ) && SITEINTELIX_WP_Config::has_siteintelix_block() ) {
			return false;
		}

		return true;
	}
}
