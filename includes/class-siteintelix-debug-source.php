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

	/** @var string Debug is not managed by SiteIntelix. */
	const SOURCE_DISABLED = 'disabled';

	/**
	 * Detect the current debug source.
	 *
	 * @return string  One of the SOURCE_* constants.
	 */
	public static function detect() {
		if ( class_exists( 'SITEINTELIX_WP_Config' ) ) {
			$state = SITEINTELIX_WP_Config::get_state();
			if ( ! empty( $state[ SITEINTELIX_WP_Config::WP_DEBUG ] ) && ! empty( $state[ SITEINTELIX_WP_Config::WP_DEBUG_LOG ] ) ) {
				return self::SOURCE_WP_CONFIG;
			}
		}

		return self::SOURCE_DISABLED;
	}

	/**
	 * Get the stored debug method preference.
	 *
	 * @return string
	 */
	public static function get_method() {
		return 'wp_config';
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
	 * The single-mode viewer intentionally manages wp-config.php directly, so
	 * an enabled WP_DEBUG constant is not considered a mode conflict.
	 *
	 * @return bool
	 */
	public static function has_external_wp_debug() {
		return false;
	}
}
