<?php
/**
 * User Switcher activation lifecycle.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates module state and schedules cleanup.
 */
class SITEINTELIX_User_Switcher_Activator {

	/**
	 * Activate the module.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( SITEINTELIX_User_Switcher_Settings::SETTINGS_OPTION, false ) ) {
			add_option( SITEINTELIX_User_Switcher_Settings::SETTINGS_OPTION, SITEINTELIX_User_Switcher_Settings::get_defaults(), '', false );
		}

		SITEINTELIX_User_Switcher_Logger::install();
		$settings = SITEINTELIX_User_Switcher_Settings::get_settings();
		SITEINTELIX_User_Switcher_Permissions::sync_operator_roles( $settings['allowed_operator_roles'] );
		SITEINTELIX_User_Switcher_Logger::schedule_retention();
	}

	/**
	 * Stop background work without deleting data or role customizations.
	 *
	 * @return void
	 */
	public static function deactivate() {
		SITEINTELIX_User_Switcher_Logger::unschedule_retention();
	}
}
