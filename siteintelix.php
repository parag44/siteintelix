<?php
/**
 * Plugin Name:       SiteIntelix – Debug Logs, Email Logs & Diagnostics
 * Plugin URI:        https://wordpress.org/plugins/siteintelix
 * Description:       Lightweight WordPress diagnostics for debug logs, email logs, server health, PHP configuration, cron, database, and troubleshooting.
 * Version:           2.7.1
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
define( 'SITEINTELIX_VERSION', '2.7.1' );

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
 * Require all class files used by the plugin.
 *
 * Called on plugins_loaded so WordPress core is fully bootstrapped first.
 */
function siteintelix_load_includes() {
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-system-info.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-health-check.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-modules.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-admin-ui.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-migrations.php';

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) ) {
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

	if ( SITEINTELIX_Modules::is_enabled( 'coming_soon' ) ) {
		require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/coming-soon/class-siteintelix-coming-soon-module.php';
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
 * Load translations from the plugin languages directory.
 *
 * @return void
 */
function siteintelix_load_textdomain() {
	load_plugin_textdomain( 'siteintelix', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'siteintelix_load_textdomain', 5 );

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

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) ) {
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

	if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) ) {
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

	$wp_admin_bar->add_node(
		array(
			'id'    => 'siteintelix',
			'title' => __( 'SiteIntelix', 'siteintelix' ),
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

/**
 * Hide core and third-party admin notices on SiteIntelix screens only.
 *
 * SiteIntelix renders its own notice area inside each page template, so
 * removing the shared WordPress notice hooks keeps the UI focused without
 * affecting the rest of wp-admin.
 *
 * @return void
 */
function siteintelix_suppress_admin_notices() {
	if ( ! siteintelix_is_admin_screen() ) {
		return;
	}

	remove_all_actions( 'admin_notices' );
	remove_all_actions( 'all_admin_notices' );
	remove_all_actions( 'network_admin_notices' );
	remove_all_actions( 'user_admin_notices' );
	remove_action( 'admin_notices', 'update_nag', 3 );
}
add_action( 'in_admin_header', 'siteintelix_suppress_admin_notices', 0 );

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
	if ( ! current_user_can( 'manage_options' ) ) {
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
		'siteintelix-admin-script',
		SITEINTELIX_PLUGIN_URL . 'assets/admin/js/siteintelix-admin.js',
		array(),   // No dependencies — vanilla JS.
		SITEINTELIX_VERSION,
		true       // Load in footer.
	);

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
			array(),
			SITEINTELIX_VERSION,
			true
		);

		// Cover the complete normal diagnostics range while keeping the localized payload bounded.
		$siteintelix_diagnostics_count_max = 200;
		$siteintelix_count_strings         = array(
			'checksShown'     => array(),
			'showPassed'      => array(),
			'hidePassed'      => array(),
			'showInformation' => array(),
			'hideInformation' => array(),
			'listTotal'       => array(),
			'issueCount'      => array(),
			'warningCount'    => array(),
			'passedCount'     => array(),
			'informationCount' => array(),
		);
		for ( $siteintelix_count = 0; $siteintelix_count <= $siteintelix_diagnostics_count_max; $siteintelix_count++ ) {
			/* translators: %d: Number of diagnostic checks. */
			$siteintelix_count_strings['checksShown'][ $siteintelix_count ] = _n( '%d check shown', '%d checks shown', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of passed diagnostic checks. */
			$siteintelix_count_strings['showPassed'][ $siteintelix_count ] = _n( 'Show %d passed check', 'Show %d passed checks', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of passed diagnostic checks. */
			$siteintelix_count_strings['hidePassed'][ $siteintelix_count ] = _n( 'Hide %d passed check', 'Hide %d passed checks', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of informational diagnostic checks. */
			$siteintelix_count_strings['showInformation'][ $siteintelix_count ] = _n( 'Show %d informational check', 'Show %d informational checks', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of informational diagnostic checks. */
			$siteintelix_count_strings['hideInformation'][ $siteintelix_count ] = _n( 'Hide %d informational check', 'Hide %d informational checks', $siteintelix_count, 'siteintelix' );
			/* translators: 1: Diagnostics list label, 2: Total number of checks. */
			$siteintelix_count_strings['listTotal'][ $siteintelix_count ] = _n( '%1$s (%2$d total check)', '%1$s (%2$d total checks)', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of diagnostic issues. */
			$siteintelix_count_strings['issueCount'][ $siteintelix_count ] = _n( '%d issue', '%d issues', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of diagnostic warnings. */
			$siteintelix_count_strings['warningCount'][ $siteintelix_count ] = _n( '%d warning', '%d warnings', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of passed diagnostic checks. */
			$siteintelix_count_strings['passedCount'][ $siteintelix_count ] = _n( '%d passed', '%d passed', $siteintelix_count, 'siteintelix' );
			/* translators: %d: Number of informational diagnostic checks. */
			$siteintelix_count_strings['informationCount'][ $siteintelix_count ] = _n( '%d information', '%d information', $siteintelix_count, 'siteintelix' );
		}

		wp_localize_script(
			'siteintelix-server-diagnostics-script',
			'siteintelixDiagnosticsData',
			array_merge(
				$siteintelix_count_strings,
				array(
					'noResults'          => __( 'No diagnostic checks match the current filters.', 'siteintelix' ),
					'resetFilters'       => __( 'Reset filters', 'siteintelix' ),
					'partialLoad'        => __( 'Some sections could not be loaded.', 'siteintelix' ),
					'sectionLoadError'   => __( 'This diagnostics section could not be displayed.', 'siteintelix' ),
					'copySuccess'        => __( 'System information copied.', 'siteintelix' ),
					'copyFailure'        => __( 'System information could not be copied.', 'siteintelix' ),
					'systemInfoFallback' => __( 'System information is unavailable.', 'siteintelix' ),
					/* translators: %s: Diagnostics section name. */
					'expandLabel'        => __( 'Expand %s', 'siteintelix' ),
					/* translators: %s: Diagnostics section name. */
					'collapseLabel'      => __( 'Collapse %s', 'siteintelix' ),
					'problemsListLabel'  => __( 'Checks needing attention', 'siteintelix' ),
					'informationListLabel' => __( 'Informational checks', 'siteintelix' ),
					'passedListLabel'    => __( 'Passed checks', 'siteintelix' ),
					'sectionNoMatches'   => __( 'No checks in this section match the current filters.', 'siteintelix' ),
					'unnamedCheck'       => __( 'Unnamed check', 'siteintelix' ),
					'criticalIssue'      => __( 'Critical issue', 'siteintelix' ),
					'warning'            => __( 'Warning', 'siteintelix' ),
					'information'        => __( 'Information', 'siteintelix' ),
					'passed'             => __( 'Passed', 'siteintelix' ),
					'current'            => __( 'Current', 'siteintelix' ),
					'recommended'        => __( 'Recommended', 'siteintelix' ),
				)
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

	// Pass localised strings and nonce to JS.
	wp_localize_script(
		'siteintelix-admin-script',
		'siteintelixData',
		array(
			'nonce'             => wp_create_nonce( 'siteintelix_export_nonce' ),
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'moduleToggleNonce' => wp_create_nonce( 'siteintelix_toggle_module' ),
			'copiedLabel'       => __( 'Copied!', 'siteintelix' ),
			'errorLabel'        => __( 'Copy failed — please copy manually.', 'siteintelix' ),
			'moduleUpdating'    => __( 'Updating module…', 'siteintelix' ),
			'moduleUpdated'     => __( 'Module updated.', 'siteintelix' ),
			'chooseLogoLabel'   => __( 'Choose Logo', 'siteintelix' ),
			'useLogoLabel'      => __( 'Use this logo', 'siteintelix' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'siteintelix_enqueue_admin_assets' );

/**
 * Toggle MU debug capture option.
 *
 * @return void
 */
function siteintelix_toggle_mu_debug_capture() {
	if ( ! current_user_can( 'manage_options' ) ) {
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
			'page'                      => 'siteintelix-debug-log',
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
	if ( ! current_user_can( 'manage_options' ) ) {
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
		$redirect_url = add_query_arg( array( 'siteintelix_settings_saved' => '1', 'tab' => 'debug_log' ), $redirect_url );
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
	if ( ! current_user_can( 'manage_options' ) ) {
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
		$redirect_url = add_query_arg( array( 'siteintelix_settings_saved' => '1', 'tab' => 'debug_log' ), $redirect_url );
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
		'debug_log' => array(
			'class' => 'SITEINTELIX_MU_Debug',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php',
		),
		'email_log' => array(
			'class' => 'SITEINTELIX_Email_Log_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/email-log/class-siteintelix-email-log-module.php',
		),
		'smtp' => array(
			'class' => 'SITEINTELIX_SMTP_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/smtp/class-siteintelix-smtp-module.php',
		),
		'cron_events' => array(
			'class' => 'SITEINTELIX_Cron_Events_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/cron-events/class-siteintelix-cron-events-module.php',
		),
		'database_manager' => array(
			'class' => 'SITEINTELIX_Database_Manager_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/database-manager/class-siteintelix-database-manager-module.php',
		),
		'download_manager' => array(
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
		'coming_soon' => array(
			'class' => 'SITEINTELIX_Coming_Soon_Module',
			'file'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/coming-soon/class-siteintelix-coming-soon-module.php',
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
}

/**
 * Toggle one module immediately from the Modules page.
 *
 * @return void
 */
function siteintelix_ajax_toggle_module() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'You do not have permission to change SiteIntelix modules.', 'siteintelix' ) ),
			403
		);
	}

	check_ajax_referer( 'siteintelix_toggle_module', 'nonce' );

	$module_id = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
	$enabled   = isset( $_POST['enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
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
	if ( ! current_user_can( 'manage_options' ) ) {
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
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to download debug logs.', 'siteintelix' ) );
	}

	if ( ! SITEINTELIX_Modules::is_enabled( 'debug_log' ) || ! class_exists( 'SITEINTELIX_Debug_Log' ) ) {
		wp_die( esc_html__( 'The Debug Log module is not enabled.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_download_debug_log' );

	// Resolve the shared SiteIntelix log path.
	$active_method    = get_option( 'siteintelix_debug_method', 'mu' );
	$log_path         = SITEINTELIX_Debug_Log::get_path_for_mode( $active_method );
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
}
register_activation_hook( __FILE__, 'siteintelix_activate' );
