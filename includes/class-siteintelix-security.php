<?php
/**
 * Central authorization policy for SiteIntelix.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies least-privilege checks to ordinary and high-risk tools.
 */
final class SITEINTELIX_Security {

	/**
	 * Modules that store or execute administrator-authored code.
	 *
	 * @var string[]
	 */
	const CODE_MODULES = array(
		'code_snippets',
		'custom_code',
	);

	/**
	 * Modules that can affect network-wide state, configuration, or source.
	 *
	 * @var string[]
	 */
	const GLOBAL_MODULES = array(
		'database_manager',
		'debug_log',
		'download_manager',
		'safe_mode_debugger',
		'transients_manager',
	);

	/**
	 * Determine whether the current user can manage ordinary SiteIntelix tools.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Determine whether the current user can store executable code.
	 *
	 * @return bool
	 */
	public static function can_manage_code() {
		return self::can_manage()
			&& current_user_can( 'unfiltered_html' )
			&& ( ! is_multisite() || is_super_admin() );
	}

	/**
	 * Determine whether the current user can manage network-sensitive tools.
	 *
	 * @return bool
	 */
	public static function can_manage_global_tools() {
		return self::can_manage() && ( ! is_multisite() || is_super_admin() );
	}

	/**
	 * Apply the appropriate policy to one module.
	 *
	 * @param string $module_id Module identifier.
	 * @return bool
	 */
	public static function can_manage_module( $module_id ) {
		$module_id = sanitize_key( $module_id );

		if ( in_array( $module_id, self::CODE_MODULES, true ) ) {
			return self::can_manage_code();
		}

		if ( in_array( $module_id, self::GLOBAL_MODULES, true ) ) {
			return self::can_manage_global_tools();
		}

		return self::can_manage();
	}
}
