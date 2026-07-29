<?php
/**
 * Plugin Name:       SiteIntelix – WordPress Toolkit
 * Plugin URI:        https://wordpress.org/plugins/siteintelix
 * Description:       Modular diagnostics, logs, SMTP, user switching, custom CSS/JS, PHP snippets, Safe Mode, and maintenance tools.
 * Version:           2.7.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Parag Das
 * Author URI:        https://parag.bd
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       siteintelix
 * Domain Path:       /languages
 *
 * @package SiteIntelix
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Plugin Constants
// ---------------------------------------------------------------------------

/** Plugin version. */
define( 'SITEINTELIX_VERSION', '2.7.3' );

/** Absolute path to the plugin directory (trailing slash). */
define( 'SITEINTELIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** Public URL to the plugin directory (trailing slash). */
define( 'SITEINTELIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** MU debug option key. */
define( 'SITEINTELIX_MU_DEBUG_OPTION', 'siteintelix_enable_debug_capture' );

/** MU debug bootstrap filename. */
define( 'SITEINTELIX_MU_DEBUG_FILENAME', 'siteintelix-debug-capture.php' );

/** SiteIntelix debug log filename under wp-content. */
define( 'SITEINTELIX_DEBUG_LOG_FILENAME', 'siteintelix-debug.log' );

/** Option key for debug log entries shown per page. */
define( 'SITEINTELIX_LOGS_PER_PAGE_OPTION', 'siteintelix_logs_per_page' );

/** Option key for Debug Log Viewer UI mode. */
define( 'SITEINTELIX_DEBUG_UI_OPTION', 'siteintelix_debug_ui' );

/** Option key for enabled SiteIntelix modules. */
define( 'SITEINTELIX_MODULES_OPTION', 'siteintelix_enabled_modules' );

/** Minimum recommended PHP version. */
define( 'SITEINTELIX_MIN_PHP_VERSION', '8.0' );

/** Minimum recommended memory limit in MB. */
define( 'SITEINTELIX_MIN_MEMORY_MB', 128 );

// ---------------------------------------------------------------------------
// Load includes
// ---------------------------------------------------------------------------

/**
 * Register Email Log capture before other plugin lifecycle callbacks can send.
 *
 * The saved option is read directly so the filtered module registry is not
 * cached before every plugin has loaded.
 *
 * @return void
 */
function siteintelix_boot_early_email_capture() {
	$enabled_modules = get_option( SITEINTELIX_MODULES_OPTION, null );

	if ( ! is_array( $enabled_modules ) ) {
		return;
	}

	$enabled_modules = array_map( 'sanitize_key', $enabled_modules );
	if ( ! in_array( 'email_log', $enabled_modules, true ) ) {
		return;
	}

	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/email-log/class-siteintelix-email-log-module.php';
	SITEINTELIX_Email_Log_Module::register_capture_hooks();
}
siteintelix_boot_early_email_capture();

/**
 * Register active PHP snippets before ordinary plugin lifecycle callbacks.
 *
 * @return void
 */
function siteintelix_boot_early_code_snippets() {
	$enabled_modules = get_option( SITEINTELIX_MODULES_OPTION, null );
	if ( ! is_array( $enabled_modules ) || ! in_array( 'code_snippets', array_map( 'sanitize_key', $enabled_modules ), true ) ) {
		return;
	}
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-security.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/code-snippets/class-siteintelix-code-snippets-module.php';
	SITEINTELIX_Code_Snippets_Module::register_early_runtime();
}
siteintelix_boot_early_code_snippets();

/**
 * Determine whether admin-only module classes are needed for this request.
 *
 * @return bool
 */
function siteintelix_should_load_admin_modules() {
	if ( is_admin() ) {
		return true;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return true;
	}

	return defined( 'WP_CLI' ) && WP_CLI;
}

/**
 * Require all class files used by the plugin.
 *
 * Called on plugins_loaded so WordPress core is fully bootstrapped first.
 */
function siteintelix_load_includes() {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-security.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-files.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-system-info.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-health-check.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-modules.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-admin-ui.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-migrations.php';

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-debug-log.php';
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-editor-links.php';
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php';
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-wp-config.php';
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-debug-source.php';
	}

	if ( SITEINTELIX_Modules::is_enabled( 'email_log' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/email-log/class-siteintelix-email-log-module.php';
	}

	if ( SITEINTELIX_Modules::is_enabled( 'smtp' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/smtp/class-siteintelix-smtp-module.php';
	}

	if ( siteintelix_should_load_admin_modules() ) {
		if ( SITEINTELIX_Modules::is_enabled( 'cron_events' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/cron-events/class-siteintelix-cron-events-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'database_manager' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/database-manager/class-siteintelix-database-manager-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'download_manager' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/download-manager/class-siteintelix-download-manager-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'transients_manager' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/transients-manager/class-siteintelix-transients-manager-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'server_diagnostics' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/server-diagnostics/class-siteintelix-server-diagnostics-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'safe_mode_debugger' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php';
		}

		if ( SITEINTELIX_Modules::is_enabled( 'file_manager' ) ) {
			require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/file-manager/class-siteintelix-file-manager-module.php';
		}
	}

	if ( SITEINTELIX_Modules::is_enabled( 'coming_soon' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/coming-soon/class-siteintelix-coming-soon-module.php';
	}

	if ( SITEINTELIX_Modules::is_enabled( 'custom_code' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/custom-code/class-siteintelix-custom-code-module.php';
	}

	if ( SITEINTELIX_Modules::is_enabled( 'code_snippets' ) && ! class_exists( 'SITEINTELIX_Code_Snippets_Module' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/code-snippets/class-siteintelix-code-snippets-module.php';
	}

	$siteintelix_user_switcher_cookie = 'siteintelix_user_switcher_' . get_current_blog_id();
	if ( SITEINTELIX_Modules::is_enabled( 'user_switcher' ) || ! empty( $_COOKIE[ $siteintelix_user_switcher_cookie ] ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/class-siteintelix-user-switcher-module.php';
	}
}
add_action( 'plugins_loaded', 'siteintelix_load_includes' );

/**
 * Run lightweight, versioned admin migrations.
 *
 * @return void
 */
function siteintelix_run_migrations() {
	if ( class_exists( 'SITEINTELIX_Migrations' ) ) {
		SITEINTELIX_Migrations::run();
	}
}
add_action( 'admin_init', 'siteintelix_run_migrations', 1 );

/**
 * Boot enabled module runtime hooks.
 *
 * @return void
 */
function siteintelix_boot_enabled_modules() {
	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && class_exists( 'SITEINTELIX_MU_Debug' ) ) {
		SITEINTELIX_MU_Debug::bootstrap();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'email_log' ) && class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
		SITEINTELIX_Email_Log_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'smtp' ) && class_exists( 'SITEINTELIX_SMTP_Module' ) ) {
		SITEINTELIX_SMTP_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'cron_events' ) && class_exists( 'SITEINTELIX_Cron_Events_Module' ) ) {
		SITEINTELIX_Cron_Events_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'database_manager' ) && class_exists( 'SITEINTELIX_Database_Manager_Module' ) ) {
		SITEINTELIX_Database_Manager_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'download_manager' ) && class_exists( 'SITEINTELIX_Download_Manager_Module' ) ) {
		SITEINTELIX_Download_Manager_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'transients_manager' ) && class_exists( 'SITEINTELIX_Transients_Manager_Module' ) ) {
		SITEINTELIX_Transients_Manager_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'server_diagnostics' ) && class_exists( 'SITEINTELIX_Server_Diagnostics_Module' ) ) {
		SITEINTELIX_Server_Diagnostics_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'safe_mode_debugger' ) && class_exists( 'SITEINTELIX_Safe_Mode_Debugger_Module' ) ) {
		SITEINTELIX_Safe_Mode_Debugger_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'coming_soon' ) && class_exists( 'SITEINTELIX_Coming_Soon_Module' ) ) {
		SITEINTELIX_Coming_Soon_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'user_switcher' ) && class_exists( 'SITEINTELIX_User_Switcher_Module' ) ) {
		SITEINTELIX_User_Switcher_Module::init();
	} elseif ( class_exists( 'SITEINTELIX_User_Switcher_Module' ) && SITEINTELIX_User_Switcher_Session_Manager::has_cookie() ) {
		SITEINTELIX_User_Switcher_Module::init_recovery();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'custom_code' ) && class_exists( 'SITEINTELIX_Custom_Code_Module' ) ) {
		SITEINTELIX_Custom_Code_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'code_snippets' ) && class_exists( 'SITEINTELIX_Code_Snippets_Module' ) ) {
		SITEINTELIX_Code_Snippets_Module::init();
	}

	if ( SITEINTELIX_Modules::is_enabled( 'file_manager' ) && class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
		SITEINTELIX_File_Manager_Module::init();
	}
}
add_action( 'init', 'siteintelix_boot_enabled_modules', 20 );

// ---------------------------------------------------------------------------
// Admin menu
// ---------------------------------------------------------------------------

/**
 * Register the top-level admin menu page.
 *
 * Only users with the manage_options capability (Administrators) can see it.
 */
function siteintelix_register_admin_menu() {
	add_menu_page(
		__( 'SiteIntelix Toolbox', 'siteintelix' ), // Browser <title>.
		__( 'SiteIntelix', 'siteintelix' ),                  // Menu label.
		'manage_options',                                       // Capability.
		'siteintelix',                                  // Menu slug.
		'siteintelix_render_admin_page',                                // Callback.
		'dashicons-chart-area',                                 // Icon.
		80                                                      // Position.
	);

	// Overwrite the first submenu item to be "Overview" instead of the parent label.
	add_submenu_page(
		'siteintelix',
		__( 'SiteIntelix Overview', 'siteintelix' ),
		__( 'Overview', 'siteintelix' ),
		'manage_options',
		'siteintelix',
		'siteintelix_render_admin_page'
	);

	add_submenu_page(
		'siteintelix',
		__( 'SiteIntelix Modules', 'siteintelix' ),
		__( 'Modules', 'siteintelix' ),
		'manage_options',
		'siteintelix-modules',
		'siteintelix_render_modules_page'
	);

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() ) {
		add_submenu_page(
			'siteintelix',
			__( 'Debug Log', 'siteintelix' ),
			__( 'Debug Log', 'siteintelix' ),
			'manage_options',
			'siteintelix-debug-log',
			'siteintelix_render_debug_log_page'
		);
	}
}
add_action( 'admin_menu', 'siteintelix_register_admin_menu' );

/**
 * Register Settings after module menus so it stays last.
 *
 * @return void
 */
function siteintelix_register_settings_menu() {
	add_submenu_page(
		'siteintelix',
		__( 'Settings', 'siteintelix' ),
		__( 'Settings', 'siteintelix' ),
		'manage_options',
		'siteintelix-settings',
		'siteintelix_render_settings_page'
	);
}
add_action( 'admin_menu', 'siteintelix_register_settings_menu', 99 );

/**
 * Add a quick Debug Log Viewer shortcut to the WordPress admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 * @return void
 */
function siteintelix_register_admin_bar_link( $wp_admin_bar ) {
	if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$log_links = array();

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() ) {
		$log_links[] = array(
			'id'    => 'siteintelix-debug-log',
			'title' => __( 'Debug Log', 'siteintelix' ),
			'href'  => admin_url( 'admin.php?page=siteintelix-debug-log' ),
			'meta'  => array(
				'title' => __( 'View SiteIntelix Debug Log', 'siteintelix' ),
			),
		);
	}

	if ( SITEINTELIX_Modules::is_enabled( 'email_log' ) ) {
		$log_links[] = array(
			'id'    => 'siteintelix-email-log',
			'title' => __( 'Email Log', 'siteintelix' ),
			'href'  => admin_url( 'admin.php?page=siteintelix-email-log' ),
			'meta'  => array(
				'title' => __( 'View SiteIntelix Email Log', 'siteintelix' ),
			),
		);
	}

	if ( empty( $log_links ) ) {
		return;
	}

	$siteintelix_admin_bar_icon  = '<span class="ab-icon dashicons dashicons-chart-area" aria-hidden="true"></span>';
	$siteintelix_admin_bar_label = '<span class="ab-label">' . esc_html__( 'SiteIntelix', 'siteintelix' ) . '</span>';

	$wp_admin_bar->add_node(
		array(
			'id'    => 'siteintelix',
			'title' => $siteintelix_admin_bar_icon . $siteintelix_admin_bar_label,
			'href'  => admin_url( 'admin.php?page=siteintelix' ),
			'meta'  => array(
				'title' => __( 'SiteIntelix log shortcuts', 'siteintelix' ),
			),
		)
	);

	foreach ( $log_links as $link ) {
		$link['parent'] = 'siteintelix';
		$wp_admin_bar->add_node( $link );
	}
}
add_action( 'admin_bar_menu', 'siteintelix_register_admin_bar_link', 80 );

// ---------------------------------------------------------------------------
// SiteIntelix admin screen helpers
// ---------------------------------------------------------------------------

/**
 * Determine whether the current admin request is for a SiteIntelix screen.
 *
 * @return bool
 */
function siteintelix_is_admin_screen() {
	if ( ! is_admin() ) {
		return false;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin screen detection.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

	return 0 === strpos( $page, 'siteintelix' );
}

// ---------------------------------------------------------------------------
// Admin page renderer
// ---------------------------------------------------------------------------

/**
 * Render the admin dashboard.
 *
 * Capability check is applied here as a defence-in-depth measure even though
 * WordPress already enforces capabilities when rendering menu pages.
 */
function siteintelix_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'siteintelix' ) );
	}
	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/admin-page.php';
}

/**
 * Render the debug log viewer page.
 */
function siteintelix_render_debug_log_page() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/debug-log-page.php';
}

/**
 * Render the Modules page.
 */
function siteintelix_render_modules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'siteintelix' ) );
	}
	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/modules-page.php';
}

/**
 * Render the Settings page.
 */
function siteintelix_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'siteintelix' ) );
	}
	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/settings-page.php';
}

// ---------------------------------------------------------------------------
// Enqueue admin assets
// ---------------------------------------------------------------------------

/**
 * Enqueue the plugin's CSS and JS only on its own admin page.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function siteintelix_enqueue_admin_assets( $hook_suffix ) {
	$siteintelix_editor_page = in_array( (string) $hook_suffix, array( 'plugin-editor.php', 'theme-editor.php' ), true );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only editor navigation parameter.
	$siteintelix_editor_line = isset( $_GET['siteintelix_line'] ) ? absint( wp_unslash( $_GET['siteintelix_line'] ) ) : 0;

	if ( $siteintelix_editor_page && $siteintelix_editor_line > 0 ) {
		wp_enqueue_script(
			'siteintelix-editor-line',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-editor-line.js',
			array( 'wp-theme-plugin-editor' ),
			SITEINTELIX_VERSION,
			true
		);

		wp_localize_script(
			'siteintelix-editor-line',
			'siteintelixEditorLine',
			array(
				'line' => $siteintelix_editor_line,
			)
		);

		wp_register_style( 'siteintelix-editor-line', false, array(), SITEINTELIX_VERSION );
		wp_enqueue_style( 'siteintelix-editor-line' );
		wp_add_inline_style(
			'siteintelix-editor-line',
			'.siteintelix-editor-target-line{background:#fff3bf!important;box-shadow:inset 4px 0 #f59e0b}.siteintelix-editor-target-textarea{outline:3px solid #f59e0b;outline-offset:2px}'
		);

		return;
	}

	// Hook suffix can vary by context; keep a stable prefix check for plugin screens.
	if ( false === strpos( (string) $hook_suffix, 'siteintelix' ) ) {
		return;
	}

	wp_enqueue_style(
		'siteintelix-admin-style',
		SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-admin.css',
		array(),
		SITEINTELIX_VERSION
	);

	wp_enqueue_script(
		'siteintelix-core',
		SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-core.js',
		array(),   // No dependencies — vanilla JS.
		SITEINTELIX_VERSION,
		true       // Load in footer.
	);

	$siteintelix_legacy_script_screen = false !== strpos( (string) $hook_suffix, 'siteintelix-debug-log' ) || false !== strpos( (string) $hook_suffix, 'siteintelix-database-manager' );
	if ( $siteintelix_legacy_script_screen ) {
		wp_enqueue_script( 'siteintelix-admin-script', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-admin.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-modules' ) ) {
		wp_enqueue_script( 'siteintelix-modules', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-modules.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
		wp_localize_script(
			'siteintelix-modules',
			'siteintelixModulesData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'siteintelix_toggle_module' ),
				'updated' => __( 'Module updated.', 'siteintelix' ),
				'failed'  => __( 'Module update failed.', 'siteintelix' ),
			)
		);
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-cron-events' ) ) {
		wp_enqueue_script( 'siteintelix-cron-events', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-cron-events.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
	}
	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-transients-manager' ) ) {
		wp_enqueue_script( 'siteintelix-transients', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-transients.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
	}
	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-safe-mode' ) ) {
		wp_enqueue_script( 'siteintelix-safe-mode', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-safe-mode.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
	}

	if ( 'toplevel_page_siteintelix' === (string) $hook_suffix ) {
		wp_enqueue_script(
			'siteintelix-overview',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-overview.js',
			array( 'siteintelix-core' ),
			SITEINTELIX_VERSION,
			true
		);

		wp_localize_script(
			'siteintelix-overview',
			'siteintelixOverviewData',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'moduleToggleNonce'  => wp_create_nonce( 'siteintelix_toggle_module' ),
				'moduleUpdating'     => __( 'Updating module…', 'siteintelix' ),
				'moduleUpdated'      => __( 'Module updated.', 'siteintelix' ),
				'moduleUpdateFailed' => __( 'Module update failed.', 'siteintelix' ),
				'copied'             => __( 'Redacted report copied.', 'siteintelix' ),
				'copyFailed'         => __( 'Copy failed. Select and copy the report manually.', 'siteintelix' ),
				'exported'           => __( 'Redacted report exported.', 'siteintelix' ),
			)
		);
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-email-log' ) ) {
		wp_enqueue_style(
			'siteintelix-email-log',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-email-log.css',
			array( 'siteintelix-admin-style' ),
			SITEINTELIX_VERSION
		);
		wp_enqueue_script(
			'siteintelix-email-log',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-email-log.js',
			array( 'siteintelix-core' ),
			SITEINTELIX_VERSION,
			true
		);
		wp_localize_script(
			'siteintelix-email-log',
			'siteintelixEmailLogData',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'previewNonce'      => wp_create_nonce( 'siteintelix_email_preview' ),
				'loading'           => __( 'Loading email preview…', 'siteintelix' ),
				'loadFailed'        => __( 'Email preview could not be loaded.', 'siteintelix' ),
				'noSubject'         => __( '(No subject)', 'siteintelix' ),
				'invalidEmail'      => __( 'Enter a valid email address.', 'siteintelix' ),
				'confirm'           => __( 'Confirm', 'siteintelix' ),
				'cancel'            => __( 'Cancel', 'siteintelix' ),
				'sendEmail'         => __( 'Send Email', 'siteintelix' ),
				'sendTestTitle'     => __( 'Send test email', 'siteintelix' ),
				'sendTestHelp'      => __( 'Enter the recipient address for the test message.', 'siteintelix' ),
				'deleteSelected'    => __( 'Delete the selected email logs?', 'siteintelix' ),
				'deleteAll'         => __( 'Delete all email logs? This cannot be undone.', 'siteintelix' ),
				'selectionSingular' => __( '1 email selected', 'siteintelix' ),
				/* translators: %d: number of selected email log entries. */
				'selectionPlural'   => __( '%d emails selected', 'siteintelix' ),
			)
		);
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-settings' ) ) {
		wp_enqueue_style( 'siteintelix-settings', SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-settings.css', array( 'siteintelix-admin-style' ), SITEINTELIX_VERSION );
		wp_enqueue_script( 'siteintelix-settings', SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-settings.js', array( 'siteintelix-core' ), SITEINTELIX_VERSION, true );
		wp_localize_script(
			'siteintelix-settings',
			'siteintelixSettingsData',
			array(
				'chooseLogo' => __( 'Choose Logo', 'siteintelix' ),
				'useLogo'    => __( 'Use this logo', 'siteintelix' ),
			)
		);
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-server-diagnostics' ) ) {
		wp_enqueue_style(
			'siteintelix-server-diagnostics-style',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-server-diagnostics.css',
			array(),
			SITEINTELIX_VERSION
		);

		wp_enqueue_script(
			'siteintelix-server-diagnostics-script',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-server-diagnostics.js',
			array( 'wp-i18n' ),
			SITEINTELIX_VERSION,
			true
		);

		wp_set_script_translations(
			'siteintelix-server-diagnostics-script',
			'siteintelix',
			SITEINTELIX_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'siteintelix-server-diagnostics-script',
			'siteintelixDiagnosticsData',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'refreshNonce'         => wp_create_nonce( 'siteintelix_refresh_server_diagnostics' ),
				'refreshing'           => __( 'Refreshing diagnostics…', 'siteintelix' ),
				'refreshComplete'      => __( 'Diagnostics refreshed.', 'siteintelix' ),
				'refreshFailed'        => __( 'Refresh failed. The previous results are still available.', 'siteintelix' ),
				'noResults'            => __( 'No diagnostic checks match the current filters.', 'siteintelix' ),
				'resetFilters'         => __( 'Reset filters', 'siteintelix' ),
				'partialLoad'          => __( 'Some sections could not be loaded.', 'siteintelix' ),
				'sectionLoadError'     => __( 'This diagnostics section could not be displayed.', 'siteintelix' ),
				'copySuccess'          => __( 'System information copied.', 'siteintelix' ),
				'copyFailure'          => __( 'System information could not be copied.', 'siteintelix' ),
				'systemInfoFallback'   => __( 'System information is unavailable.', 'siteintelix' ),
				/* translators: %s: Diagnostics section name. */
				'expandLabel'          => __( 'Expand %s', 'siteintelix' ),
				/* translators: %s: Diagnostics section name. */
				'collapseLabel'        => __( 'Collapse %s', 'siteintelix' ),
				'problemsListLabel'    => __( 'Checks needing attention', 'siteintelix' ),
				'informationListLabel' => __( 'Informational checks', 'siteintelix' ),
				'passedListLabel'      => __( 'Passed checks', 'siteintelix' ),
				'sectionNoMatches'     => __( 'No checks in this section match the current filters.', 'siteintelix' ),
				'unnamedCheck'         => __( 'Unnamed check', 'siteintelix' ),
				'criticalIssue'        => __( 'Critical issue', 'siteintelix' ),
				'warning'              => __( 'Warning', 'siteintelix' ),
				'information'          => __( 'Information', 'siteintelix' ),
				'passed'               => __( 'Passed', 'siteintelix' ),
				'current'              => __( 'Current', 'siteintelix' ),
				'recommended'          => __( 'Recommended', 'siteintelix' ),
				'details'              => __( 'Details', 'siteintelix' ),
				'showAllChecks'        => __( 'Show all checks', 'siteintelix' ),
				'showFewer'            => __( 'Show fewer', 'siteintelix' ),
				'statusColumn'         => __( 'Status', 'siteintelix' ),
				'checkColumn'          => __( 'Check', 'siteintelix' ),
				'descriptionColumn'    => __( 'Description', 'siteintelix' ),
				'actionColumn'         => __( 'Action', 'siteintelix' ),
			)
		);
	}

	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-settings' ) && SITEINTELIX_Modules::is_enabled( 'coming_soon' ) ) {
		wp_enqueue_media();
	}

	// Load the rebuilt Debug Log Viewer assets for the Modern and Terminal log screens.
	$debug_ui_mode = get_option( SITEINTELIX_DEBUG_UI_OPTION, 'modern' );
	$debug_ui_mode = 'terminal_dark' === $debug_ui_mode ? 'terminal_light' : $debug_ui_mode;
	if ( false !== strpos( (string) $hook_suffix, 'siteintelix-debug-log' ) && in_array( $debug_ui_mode, array( 'modern', 'terminal_light' ), true ) ) {
		wp_enqueue_style(
			'siteintelix-debug-log-viewer',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/css/siteintelix-debug-log.css',
			array( 'siteintelix-admin-style' ),
			SITEINTELIX_VERSION
		);

		wp_enqueue_script(
			'siteintelix-debug-log-viewer',
			SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-debug-log.js',
			array(),
			SITEINTELIX_VERSION,
			true
		);
	}

	if ( $siteintelix_legacy_script_screen ) {
		wp_localize_script(
			'siteintelix-admin-script',
			'siteintelixData',
			array(
				'copiedLabel' => __( 'Copied!', 'siteintelix' ),
				'errorLabel'  => __( 'Copy failed — please copy manually.', 'siteintelix' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'siteintelix_enqueue_admin_assets' );

/**
 * Toggle MU debug capture option.
 *
 * @return void
 */
function siteintelix_toggle_mu_debug_capture() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have permission to change debug capture settings.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_MU_Debug' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_toggle_mu_debug' );

	$enabled = isset( $_POST['siteintelix_mu_debug_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['siteintelix_mu_debug_enabled'] ) ) ? 1 : 0;
	update_option( SITEINTELIX_MU_DEBUG_OPTION, $enabled );

	$ensure_result = SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
	if ( is_wp_error( $ensure_result ) ) {
		set_transient( 'siteintelix_mu_debug_notice', sanitize_text_field( $ensure_result->get_error_message() ), DAY_IN_SECONDS );
	}

	$redirect_url = add_query_arg(
		array(
			'page'                       => 'siteintelix-debug-log',
			'siteintelix_mu_debug_saved' => '1',
			'siteintelix_mu_debug_mode'  => (string) $enabled,
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_siteintelix_toggle_mu_debug', 'siteintelix_toggle_mu_debug_capture' );

// ---------------------------------------------------------------------------
// Debug Settings form handler
// ---------------------------------------------------------------------------

/**
 * Save debug settings from the Settings page.
 *
 * @return void
 */
function siteintelix_save_debug_settings() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have permission to change debug settings.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_MU_Debug' ) || ! class_exists( 'SITEINTELIX_WP_Config' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_save_debug_settings' );

	$method        = isset( $_POST['siteintelix_debug_method'] ) ? sanitize_key( wp_unslash( $_POST['siteintelix_debug_method'] ) ) : 'mu';
	$ui_mode       = isset( $_POST['siteintelix_debug_ui'] ) ? sanitize_key( wp_unslash( $_POST['siteintelix_debug_ui'] ) ) : 'modern';
	$logs_per_page = isset( $_POST['siteintelix_logs_per_page'] ) ? absint( wp_unslash( $_POST['siteintelix_logs_per_page'] ) ) : 25;
	$ui_mode       = 'terminal_dark' === $ui_mode ? 'terminal_light' : $ui_mode;

	if ( ! in_array( $method, array( 'mu', 'wp_config' ), true ) ) {
		$method = 'mu';
	}

	if ( ! in_array( $ui_mode, array( 'classic', 'modern', 'terminal_light' ), true ) ) {
		$ui_mode = 'modern';
	}

	update_option( 'siteintelix_debug_method', $method );
	update_option( SITEINTELIX_DEBUG_UI_OPTION, $ui_mode );
	update_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, min( 500, max( 10, $logs_per_page ) ) );

	$error_msg = '';

	if ( 'mu' === $method ) {
		// MU mode: always enable capture (method selection = activation).
		update_option( SITEINTELIX_MU_DEBUG_OPTION, 1 );

		$ensure_result = SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
		if ( is_wp_error( $ensure_result ) ) {
			$error_msg = $ensure_result->get_error_message();
		}

		// Disable wp-config debug if switching from that method.
		if ( SITEINTELIX_WP_Config::is_debug_enabled_in_file() ) {
			$disable_result = SITEINTELIX_WP_Config::disable();
			if ( is_wp_error( $disable_result ) ) {
				$error_msg = $disable_result->get_error_message();
			}
		}
	} else {
		// wp-config mode: enable debug block in wp-config.php (always on when selected).
		update_option( SITEINTELIX_MU_DEBUG_OPTION, 0 ); // Disable MU capture logic.
		$enable_result = SITEINTELIX_WP_Config::enable();
		if ( is_wp_error( $enable_result ) ) {
			$error_msg = $enable_result->get_error_message();
		}
	}

	$redirect_url = add_query_arg( 'page', 'siteintelix-settings', admin_url( 'admin.php' ) );
	if ( $error_msg ) {
		$redirect_url = add_query_arg( 'siteintelix_settings_error', urlencode( $error_msg ), $redirect_url );
	} else {
		$redirect_url = add_query_arg(
			array(
				'siteintelix_settings_saved' => '1',
				'tab'                        => 'debug_log',
			),
			$redirect_url
		);
	}
	$redirect_url .= '#siteintelix-debug-log-settings';

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_siteintelix_save_debug_settings', 'siteintelix_save_debug_settings' );

/**
 * Repair a detected conflict where wp-config.php debug mode overrides MU capture.
 *
 * @return void
 */
function siteintelix_fix_debug_conflict() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have permission to fix debug settings.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_MU_Debug' ) || ! class_exists( 'SITEINTELIX_WP_Config' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_fix_debug_conflict' );

	update_option( 'siteintelix_debug_method', 'mu' );
	update_option( SITEINTELIX_MU_DEBUG_OPTION, 1 );

	$error_msg     = '';
	$ensure_result = SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
	if ( is_wp_error( $ensure_result ) ) {
		$error_msg = $ensure_result->get_error_message();
	}

	if ( ! $error_msg && SITEINTELIX_WP_Config::is_debug_enabled_in_file() ) {
		$disable_result = SITEINTELIX_WP_Config::disable();
		if ( is_wp_error( $disable_result ) ) {
			$error_msg = $disable_result->get_error_message();
		}
	}

	$redirect_url = add_query_arg( 'page', 'siteintelix-settings', admin_url( 'admin.php' ) );
	if ( $error_msg ) {
		$redirect_url = add_query_arg( 'siteintelix_settings_error', rawurlencode( $error_msg ), $redirect_url );
	} else {
		$redirect_url = add_query_arg(
			array(
				'siteintelix_settings_saved' => '1',
				'tab'                        => 'debug_log',
			),
			$redirect_url
		);
	}
	$redirect_url .= '#siteintelix-debug-log-settings';

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_siteintelix_fix_debug_conflict', 'siteintelix_fix_debug_conflict' );

// ---------------------------------------------------------------------------
// Modules form handler
// ---------------------------------------------------------------------------

/**
 * Load a module class on demand for activation/deactivation cleanup.
 *
 * @param string $module_id Module ID.
 * @return void
 */
function siteintelix_load_module_class_for_management( $module_id ) {
	$module_id = sanitize_key( $module_id );
	$classes   = array(
		'debug_log'          => array(
			'class' => 'SITEINTELIX_MU_Debug',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php',
		),
		'email_log'          => array(
			'class' => 'SITEINTELIX_Email_Log_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/email-log/class-siteintelix-email-log-module.php',
		),
		'smtp'               => array(
			'class' => 'SITEINTELIX_SMTP_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/smtp/class-siteintelix-smtp-module.php',
		),
		'cron_events'        => array(
			'class' => 'SITEINTELIX_Cron_Events_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/cron-events/class-siteintelix-cron-events-module.php',
		),
		'database_manager'   => array(
			'class' => 'SITEINTELIX_Database_Manager_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/database-manager/class-siteintelix-database-manager-module.php',
		),
		'download_manager'   => array(
			'class' => 'SITEINTELIX_Download_Manager_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/download-manager/class-siteintelix-download-manager-module.php',
		),
		'transients_manager' => array(
			'class' => 'SITEINTELIX_Transients_Manager_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/transients-manager/class-siteintelix-transients-manager-module.php',
		),
		'safe_mode_debugger' => array(
			'class' => 'SITEINTELIX_Safe_Mode_Debugger_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php',
		),
		'coming_soon'        => array(
			'class' => 'SITEINTELIX_Coming_Soon_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/coming-soon/class-siteintelix-coming-soon-module.php',
		),
		'user_switcher'      => array(
			'class' => 'SITEINTELIX_User_Switcher_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/class-siteintelix-user-switcher-module.php',
		),
		'custom_code'        => array(
			'class' => 'SITEINTELIX_Custom_Code_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/custom-code/class-siteintelix-custom-code-module.php',
		),
		'code_snippets'      => array(
			'class' => 'SITEINTELIX_Code_Snippets_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/code-snippets/class-siteintelix-code-snippets-module.php',
		),
		'file_manager'       => array(
			'class' => 'SITEINTELIX_File_Manager_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/file-manager/class-siteintelix-file-manager-module.php',
		),
	);

	if ( isset( $classes[ $module_id ] ) && ! class_exists( $classes[ $module_id ]['class'] ) && file_exists( $classes[ $module_id ]['file'] ) ) {
		require_once $classes[ $module_id ]['file'];
	}
}

/**
 * Run module activation housekeeping.
 *
 * @param string $module_id Module ID.
 * @return true|WP_Error
 */
function siteintelix_activate_module_runtime( $module_id ) {
	$module_id = sanitize_key( $module_id );

	if ( 'debug_log' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_MU_Debug' ) ) {
			return SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
		}
	}

	if ( 'email_log' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
			SITEINTELIX_Email_Log_Module::activate();
		}
	}

	if ( 'smtp' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_SMTP_Module' ) ) {
			SITEINTELIX_SMTP_Module::activate();
		}
	}

	if ( 'safe_mode_debugger' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_Safe_Mode_Debugger_Module' ) ) {
			return SITEINTELIX_Safe_Mode_Debugger_Module::activate();
		}
	}

	if ( 'user_switcher' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_User_Switcher_Activator' ) ) {
			SITEINTELIX_User_Switcher_Activator::activate();
		}
	}

	if ( 'custom_code' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_Custom_Code_Module' ) ) {
			SITEINTELIX_Custom_Code_Module::activate();
		}
	}

	if ( 'code_snippets' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_Code_Snippets_Module' ) ) {
			SITEINTELIX_Code_Snippets_Module::activate();
		}
	}

	if ( 'file_manager' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
			return SITEINTELIX_File_Manager_Module::activate();
		}
	}

	return true;
}

/**
 * Run module deactivation cleanup so disabled modules stop executing.
 *
 * @param string $module_id Module ID.
 * @return void
 */
function siteintelix_deactivate_module_runtime( $module_id ) {
	$module_id = sanitize_key( $module_id );

	if ( 'debug_log' === $module_id ) {
		update_option( SITEINTELIX_MU_DEBUG_OPTION, 0 );
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_MU_Debug' ) ) {
			SITEINTELIX_MU_Debug::remove_mu_plugin_file();
		}
	}

	if ( 'safe_mode_debugger' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );

		if ( class_exists( 'SITEINTELIX_Safe_Mode_Debugger_Module' ) ) {
			SITEINTELIX_Safe_Mode_Debugger_Module::deactivate();
		}
	}

	if ( 'email_log' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
			SITEINTELIX_Email_Log_Module::unschedule_retention();
		}
	}

	if ( 'user_switcher' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_User_Switcher_Activator' ) ) {
			SITEINTELIX_User_Switcher_Activator::deactivate();
		}
	}

	if ( 'file_manager' === $module_id ) {
		siteintelix_load_module_class_for_management( $module_id );
		if ( class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
			SITEINTELIX_File_Manager_Module::deactivate();
		}
	}
}

/**
 * Toggle one module immediately from the Modules page.
 *
 * @return void
 */
function siteintelix_ajax_toggle_module() {
	if ( ! SITEINTELIX_Security::can_manage() ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to change SiteIntelix modules.', 'siteintelix' ) ),
			403
		);
	}

	check_ajax_referer( 'siteintelix_toggle_module', 'nonce' );

	$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
	$enabled   = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );

	if ( ! SITEINTELIX_Security::can_manage_module( $module_id ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to manage this module.', 'siteintelix' ) ),
			403
		);
	}

	$result    = SITEINTELIX_Modules::set_enabled( $module_id, $enabled );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error(
			array( 'message' => $result->get_error_message() ),
			400
		);
	}

	if ( $enabled ) {
		$runtime_result = siteintelix_activate_module_runtime( $module_id );

		if ( is_wp_error( $runtime_result ) ) {
			SITEINTELIX_Modules::set_enabled( $module_id, false );

			wp_send_json_error(
				array( 'message' => $runtime_result->get_error_message() ),
				500
			);
		}
	} else {
		siteintelix_deactivate_module_runtime( $module_id );
	}

	wp_send_json_success(
		array(
			'enabled' => $enabled,
			'message' => __( 'Module updated.', 'siteintelix' ),
		)
	);
}
add_action( 'wp_ajax_siteintelix_toggle_module', 'siteintelix_ajax_toggle_module' );

/**
 * Clear SiteIntelix debug log file contents.
 *
 * @return void
 */
function siteintelix_clear_debug_log() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have permission to clear debug logs.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_Debug_Log' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_clear_debug_log' );

	// Resolve log path based on active debug method.
	$active_method = get_option( 'siteintelix_debug_method', 'mu' );
	$log_path      = SITEINTELIX_Debug_Log::get_path_for_mode( $active_method );
	$cleared       = 0;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;

	if ( WP_Filesystem() && $wp_filesystem ) {
		$written = $wp_filesystem->put_contents( $log_path, '', FS_CHMOD_FILE );
		$cleared = $written ? 1 : 0;
	}

	$redirect_url = add_query_arg(
		array(
			'page'                    => 'siteintelix-debug-log',
			'siteintelix_log_cleared' => (string) $cleared,
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_siteintelix_clear_debug_log', 'siteintelix_clear_debug_log' );

/**
 * Securely download SiteIntelix debug log for administrators only.
 *
 * @return void
 */
function siteintelix_download_debug_log() {
	if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
		wp_die( esc_html__( 'You do not have permission to download debug logs.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_Debug_Log' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_download_debug_log' );

	// Resolve the shared SiteIntelix log path.
	$active_method     = get_option( 'siteintelix_debug_method', 'mu' );
	$log_path          = SITEINTELIX_Debug_Log::get_path_for_mode( $active_method );
	$download_filename = 'siteintelix-debug.log';

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;

	if ( ! WP_Filesystem() || ! $wp_filesystem || ! $wp_filesystem->exists( $log_path ) || ! $wp_filesystem->is_readable( $log_path ) ) {
		wp_die( esc_html__( 'Debug log file is not available for download.', 'siteintelix' ) );
	}

	$contents = $wp_filesystem->get_contents( $log_path );

	if ( ! is_string( $contents ) ) {
		wp_die( esc_html__( 'Could not read debug log file.', 'siteintelix' ) );
	}

	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . $download_filename . '"' );
	header( 'Content-Length: ' . strlen( $contents ) );
	echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_siteintelix_download_debug_log', 'siteintelix_download_debug_log' );

// ---------------------------------------------------------------------------
// Shortcode: [siteintelix_panel]
// ---------------------------------------------------------------------------

/**
 * Render a compact info table on the front end.
 *
 * Visible only to logged-in administrators. All other visitors receive an
 * empty string so no server information is leaked publicly.
 *
 * @return string  Safe HTML or empty string.
 */
function siteintelix_shortcode_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$info = SITEINTELIX_System_Info::get_all();

	ob_start();
	?>
	<div class="siteintelix-shortcode-panel">
		<h3><?php esc_html_e( 'SiteIntelix', 'siteintelix' ); ?></h3>
		<table>
			<tbody>
				<tr>
					<th><?php esc_html_e( 'WordPress Version', 'siteintelix' ); ?></th>
					<td><?php echo esc_html( $info['wordpress']['wp_version'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP Version', 'siteintelix' ); ?></th>
					<td><?php echo esc_html( $info['server']['php_version'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'MySQL Version', 'siteintelix' ); ?></th>
					<td><?php echo esc_html( $info['server']['mysql_version'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Memory Limit', 'siteintelix' ); ?></th>
					<td><?php echo esc_html( $info['server']['memory_limit'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'HTTPS', 'siteintelix' ); ?></th>
					<td><?php echo esc_html( $info['environment']['https'] ? __( 'Yes', 'siteintelix' ) : __( 'No', 'siteintelix' ) ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'siteintelix_panel', 'siteintelix_shortcode_panel' );

// ---------------------------------------------------------------------------
// Activation / deactivation
// ---------------------------------------------------------------------------

/**
 * Plugin activation callback.
 * Stores the activation timestamp and prepares the debug capture MU-plugin.
 */
function siteintelix_activate() {
	add_option( SITEINTELIX_MU_DEBUG_OPTION, 0 );

	$siteintelix_modules_class = SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-modules.php';
	if ( ! class_exists( 'SITEINTELIX_Modules' ) && file_exists( $siteintelix_modules_class ) ) {
		require_once $siteintelix_modules_class;
	}

	$siteintelix_default_modules = SITEINTELIX_Modules::get_default_enabled();
	add_option( SITEINTELIX_MODULES_OPTION, $siteintelix_default_modules );
	update_option( 'siteintelix_activated_at', current_time( 'mysql' ) );

	$siteintelix_mu_debug_class = SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php';
	if ( in_array( 'debug_log', $siteintelix_default_modules, true ) && ! class_exists( 'SITEINTELIX_MU_Debug' ) && file_exists( $siteintelix_mu_debug_class ) ) {
		require_once $siteintelix_mu_debug_class;
	}

	if ( class_exists( 'SITEINTELIX_MU_Debug' ) ) {
		SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
	}

	if ( in_array( 'email_log', $siteintelix_default_modules, true ) && class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
		SITEINTELIX_Email_Log_Module::activate();
	}
}
register_activation_hook( __FILE__, 'siteintelix_activate' );

/** Clear scheduled plugin work on deactivation without deleting user data. */
function siteintelix_deactivate() {
	if ( class_exists( 'SITEINTELIX_Email_Log_Module' ) ) {
		SITEINTELIX_Email_Log_Module::unschedule_retention();
	}

	update_option( SITEINTELIX_MU_DEBUG_OPTION, 0 );

	$siteintelix_mu_files_class = SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-files.php';
	if ( ! class_exists( 'SITEINTELIX_MU_Files' ) && file_exists( $siteintelix_mu_files_class ) ) {
		require_once $siteintelix_mu_files_class;
	}

	$siteintelix_safe_mode_class = SITEINTELIX_PLUGIN_DIR . 'includes/modules/safe-mode-debugger/class-siteintelix-safe-mode-debugger-module.php';
	if ( ! class_exists( 'SITEINTELIX_Safe_Mode_Debugger_Module' ) && file_exists( $siteintelix_safe_mode_class ) ) {
		require_once $siteintelix_safe_mode_class;
	}

	if ( class_exists( 'SITEINTELIX_Safe_Mode_Debugger_Module' ) ) {
		SITEINTELIX_Safe_Mode_Debugger_Module::deactivate();
	}

	if ( class_exists( 'SITEINTELIX_MU_Files' ) ) {
		SITEINTELIX_MU_Files::remove_all();
	}

	siteintelix_load_module_class_for_management( 'file_manager' );
	if ( class_exists( 'SITEINTELIX_File_Manager_Module' ) ) {
		SITEINTELIX_File_Manager_Module::deactivate();
	}
}
register_deactivation_hook( __FILE__, 'siteintelix_deactivate' );
