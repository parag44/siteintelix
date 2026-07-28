<?php
/**
 * User Switcher module bootstrap.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_user_switcher_dir = __DIR__ . '/';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-settings.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-permissions.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-logger.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-session-manager.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-admin-actions.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-toolbar.php';
require_once $siteintelix_user_switcher_dir . 'class-siteintelix-user-switcher-activator.php';

/**
 * Coordinates User Switcher services.
 */
class SITEINTELIX_User_Switcher_Module {

	/**
	 * Register the complete enabled module.
	 *
	 * @return void
	 */
	public static function init() {
		SITEINTELIX_User_Switcher_Session_Manager::init();
		SITEINTELIX_User_Switcher_Logger::init();
		SITEINTELIX_User_Switcher_Settings::init();
		SITEINTELIX_User_Switcher_Admin_Actions::init(); // Registers user_row_actions.
		SITEINTELIX_User_Switcher_Toolbar::init();
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Load only the code needed to validate, return, or clear an existing session.
	 *
	 * This recovery path remains available after the module is disabled so an
	 * impersonated browser cannot be stranded in an ambiguous state.
	 *
	 * @return void
	 */
	public static function init_recovery() {
		SITEINTELIX_User_Switcher_Session_Manager::init();
		SITEINTELIX_User_Switcher_Logger::init();
		SITEINTELIX_User_Switcher_Admin_Actions::init_recovery();
		SITEINTELIX_User_Switcher_Toolbar::init();
	}

	/**
	 * Load module styles only on the User Switcher settings and log screens.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$allowed_screens = array(
			'siteintelix_page_siteintelix-user-switcher',
			'siteintelix_page_siteintelix-settings',
		);

		if ( ! in_array( (string) $hook_suffix, $allowed_screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'siteintelix-settings',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-settings.css',
			array( 'siteintelix-admin-style' ),
			SITEINTELIX_VERSION
		);
		wp_enqueue_style(
			'siteintelix-user-switcher',
			SITEINTELIX_PLUGIN_URL . 'includes/modules/user-switcher/assets/user-switcher.css',
			array( 'siteintelix-settings' ),
			SITEINTELIX_VERSION
		);
	}
}
