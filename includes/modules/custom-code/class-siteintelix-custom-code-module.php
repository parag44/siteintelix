<?php
/**
 * Custom CSS & JS module bootstrap.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$siteintelix_custom_code_dir = __DIR__ . '/';
require_once $siteintelix_custom_code_dir . 'class-siteintelix-custom-code-repository.php';
require_once $siteintelix_custom_code_dir . 'class-siteintelix-custom-code-file-manager.php';
require_once $siteintelix_custom_code_dir . 'class-siteintelix-custom-code-runner.php';
require_once $siteintelix_custom_code_dir . 'class-siteintelix-custom-code-admin.php';

class SITEINTELIX_Custom_Code_Module {
	public static function init() {
		SITEINTELIX_Custom_Code_Runner::init();
		if ( is_admin() && SITEINTELIX_Security::can_manage_code() ) {
			SITEINTELIX_Custom_Code_Repository::maybe_upgrade();
			SITEINTELIX_Custom_Code_Admin::init();
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'admin_post_siteintelix_save_custom_css_js_settings', array( __CLASS__, 'save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
	}

	public static function delete_on_uninstall() {
		return (bool) get_option( 'siteintelix_delete_custom_css_js_on_uninstall', get_option( 'siteintelix_delete_custom_code_on_uninstall', false ) );
	}

	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'custom_code', (array) $enabled_modules, true ) ) {
			return;
		}
		$delete_on_uninstall = self::delete_on_uninstall();
		include __DIR__ . '/views/settings.php';
	}

	public static function save_settings() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			wp_die( esc_html__( 'You do not have permission to change Custom CSS & JS settings.', 'siteintelix' ) );
		}
		check_admin_referer( 'siteintelix_save_custom_css_js_settings' );
		update_option( 'siteintelix_delete_custom_css_js_on_uninstall', isset( $_POST['delete_on_uninstall'] ) ? 1 : 0, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-settings', 'tab' => 'custom_code', 'siteintelix_settings_saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function activate() {
		SITEINTELIX_Custom_Code_Repository::install_schema();
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'siteintelix-custom-code' ) ) {
			return;
		}
		wp_enqueue_style( 'siteintelix-custom-code', SITEINTELIX_PLUGIN_URL . 'includes/modules/custom-code/assets/custom-code.css', array( 'siteintelix-admin-style' ), SITEINTELIX_VERSION );
		$type = isset( $_GET['type'] ) && 'javascript' === sanitize_key( wp_unslash( $_GET['type'] ) ) ? 'application/javascript' : 'text/css'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings = wp_enqueue_code_editor( array( 'type' => $type ) );
		wp_enqueue_script( 'siteintelix-custom-code', SITEINTELIX_PLUGIN_URL . 'includes/modules/custom-code/assets/custom-code.js', array( 'wp-codemirror' ), SITEINTELIX_VERSION, true );
		wp_localize_script( 'siteintelix-custom-code', 'siteintelixCustomCode', array( 'editorSettings' => $settings ) );
	}
}
