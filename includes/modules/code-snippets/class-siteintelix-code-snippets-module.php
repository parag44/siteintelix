<?php
/**
 * Code Snippets module bootstrap.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$siteintelix_snippets_dir = __DIR__ . '/';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-repository.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-validator.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-context.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-recovery.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-runner.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-transfer.php';
require_once $siteintelix_snippets_dir . 'class-siteintelix-snippets-admin.php';

class SITEINTELIX_Code_Snippets_Module {
	private static $early_registered = false;
	private static $initialized = false;

	public static function register_early_runtime() {
		if ( self::$early_registered ) return;
		SITEINTELIX_Snippets_Runner::init();
		self::$early_registered = true;
	}

	public static function init() {
		if ( self::$initialized ) return;
		self::register_early_runtime();
		if ( is_admin() && SITEINTELIX_Security::can_manage_code() ) {
			SITEINTELIX_Snippets_Repository::maybe_upgrade();
			SITEINTELIX_Snippets_Admin::init();
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
			add_action( 'admin_post_siteintelix_save_code_snippets_settings', array( __CLASS__, 'save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
		self::$initialized = true;
	}

	public static function delete_on_uninstall() {
		return (bool) get_option( 'siteintelix_delete_code_snippets_on_uninstall', get_option( 'siteintelix_delete_custom_code_on_uninstall', false ) );
	}

	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'code_snippets', (array) $enabled_modules, true ) ) return;
		$delete_on_uninstall = self::delete_on_uninstall();
		include __DIR__ . '/views/settings.php';
	}

	public static function save_settings() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			wp_die( esc_html__( 'You do not have permission to change Code Snippets settings.', 'siteintelix' ) );
		}
		check_admin_referer( 'siteintelix_save_code_snippets_settings' );
		update_option( 'siteintelix_delete_code_snippets_on_uninstall', isset( $_POST['delete_on_uninstall'] ) ? 1 : 0, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-settings', 'tab' => 'code_snippets', 'siteintelix_settings_saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function admin_notices() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) return;
		if ( SITEINTELIX_Snippets_Context::is_safe_mode() ) {
			echo '<div class="notice notice-warning siteintelix-notice"><p>' . esc_html__( 'SiteIntelix Code Snippets Safe Mode is active; no PHP snippets ran on this request.', 'siteintelix' ) . '</p></div>';
		}
		$id = absint( get_transient( 'siteintelix_snippet_recovery_notice' ) );
		if ( $id ) {
			delete_transient( 'siteintelix_snippet_recovery_notice' );
			printf( '<div class="notice notice-error siteintelix-notice"><p>%s</p></div>', esc_html( sprintf( __( 'SiteIntelix deactivated snippet #%d after a runtime error.', 'siteintelix' ), $id ) ) );
		}
	}

	public static function activate() {
		SITEINTELIX_Snippets_Repository::install_schema();
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'siteintelix-code-snippets' ) ) return;
		wp_enqueue_style( 'siteintelix-code-snippets', SITEINTELIX_PLUGIN_URL . 'includes/modules/code-snippets/assets/code-snippets.css', array( 'siteintelix-admin-style' ), SITEINTELIX_VERSION );
		$settings = wp_enqueue_code_editor( array( 'type' => 'text/x-php' ) );
		wp_enqueue_script( 'siteintelix-code-snippets', SITEINTELIX_PLUGIN_URL . 'includes/modules/code-snippets/assets/code-snippets.js', array( 'wp-codemirror' ), SITEINTELIX_VERSION, true );
		wp_localize_script( 'siteintelix-code-snippets', 'siteintelixSnippets', array( 'editorSettings' => $settings ) );
	}
}
