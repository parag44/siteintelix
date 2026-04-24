<?php
/**
 * Plugin Name:       SiteIntelix Debug Log Viewer
 * Plugin URI:        https://wordpress.org/plugins/siteintelix
 * Description:       Displays a structured WordPress debug log viewer with pagination, filtering, diagnostics, and export-ready reporting.
 * Version:           2.2.0
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
define( 'SITEINTELIX_VERSION', '2.2.0' );

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
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-debug-log.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-wp-config.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-security.php';
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-debug-source.php';
}
add_action( 'plugins_loaded', 'siteintelix_load_includes' );

/**
 * Load translations from the plugin languages directory.
 *
 * @return void
 */
function siteintelix_load_textdomain() {
	load_plugin_textdomain( 'siteintelix', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'siteintelix_load_textdomain', 5 );

// Boot runtime features after init to avoid early i18n loading notices.
add_action( 'init', array( 'SITEINTELIX_MU_Debug', 'bootstrap' ), 20 );
add_action( 'init', array( 'SITEINTELIX_Security', 'bootstrap' ), 20 );

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
		__( 'SiteIntelix Debug Log Viewer', 'siteintelix' ), // Browser <title>.
		__( 'SiteIntelix Debug Log Viewer', 'siteintelix' ), // Menu label.
		'manage_options',                                       // Capability.
		'siteintelix',                                  // Menu slug.
		'siteintelix_render_admin_page',                                // Callback.
		'dashicons-chart-area',                                 // Icon.
		80                                                      // Position.
	);

	// Overwrite the first submenu item to be "Overview" instead of the parent label.
	add_submenu_page(
		'siteintelix',
		__( 'SiteIntelix Debug Log Viewer Overview', 'siteintelix' ),
		__( 'Overview', 'siteintelix' ),
		'manage_options',
		'siteintelix',
		'siteintelix_render_admin_page'
	);


	add_submenu_page(
		'siteintelix',
		__( 'Debug Log Viewer', 'siteintelix' ),
		__( 'Debug Log Viewer', 'siteintelix' ),
		'manage_options',
		'siteintelix-debug-log',
		'siteintelix_render_debug_log_page'
	);

	add_submenu_page(
		'siteintelix',
		__( 'Settings', 'siteintelix' ),
		__( 'Settings', 'siteintelix' ),
		'manage_options',
		'siteintelix-settings',
		'siteintelix_render_settings_page'
	);

	add_submenu_page(
		'siteintelix',
		__( 'Security Panel', 'siteintelix' ),
		__( 'Security Panel', 'siteintelix' ),
		'manage_options',
		'siteintelix-security',
		'siteintelix_render_security_page'
	);
}
add_action( 'admin_menu', 'siteintelix_register_admin_menu' );

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
	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/debug-log-page.php';
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

/**
 * Render the Security Panel page.
 */
function siteintelix_render_security_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'siteintelix' ) );
	}
	require_once SITEINTELIX_PLUGIN_DIR . 'admin/views/security-page.php';
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
	// Hook suffix can vary by context; keep a stable prefix check for plugin screens.
	if ( false === strpos( (string) $hook_suffix, 'siteintelix' ) ) {
		return;
	}

	wp_enqueue_style(
		'siteintelix-admin-style',
		SITEINTELIX_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		SITEINTELIX_VERSION
	);

	wp_enqueue_script(
		'siteintelix-admin-script',
		SITEINTELIX_PLUGIN_URL . 'assets/js/admin.js',
		array(),   // No dependencies — vanilla JS.
		SITEINTELIX_VERSION,
		true       // Load in footer.
	);

	// Pass localised strings and nonce to JS.
	wp_localize_script(
		'siteintelix-admin-script',
		'siteintelixData',
		array(
			'nonce'       => wp_create_nonce( 'siteintelix_export_nonce' ),
			'copiedLabel' => __( 'Copied!', 'siteintelix' ),
			'errorLabel'  => __( 'Copy failed — please copy manually.', 'siteintelix' ),
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

	check_admin_referer( 'siteintelix_save_debug_settings' );

	$method        = isset( $_POST['siteintelix_debug_method'] ) ? sanitize_key( wp_unslash( $_POST['siteintelix_debug_method'] ) ) : 'mu';
	$logs_per_page = isset( $_POST['siteintelix_logs_per_page'] ) ? absint( wp_unslash( $_POST['siteintelix_logs_per_page'] ) ) : 25;

	if ( ! in_array( $method, array( 'mu', 'wp_config' ), true ) ) {
		$method = 'mu';
	}

	update_option( 'siteintelix_debug_method', $method );
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
		$redirect_url = add_query_arg( 'siteintelix_settings_saved', '1', $redirect_url );
	}

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_siteintelix_save_debug_settings', 'siteintelix_save_debug_settings' );

// ---------------------------------------------------------------------------
// Security settings form handler
// ---------------------------------------------------------------------------

/**
 * Save security panel settings.
 *
 * @return void
 */
function siteintelix_save_security() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to change security settings.', 'siteintelix' ) );
	}

	check_admin_referer( 'siteintelix_save_security' );

	SITEINTELIX_Security::save( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                      => 'siteintelix-security',
				'siteintelix_security_saved' => '1',
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_siteintelix_save_security', 'siteintelix_save_security' );

/**
 * Clear SiteIntelix debug log file contents.
 *
 * @return void
 */
function siteintelix_clear_debug_log() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to clear debug logs.', 'siteintelix' ) );
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
		<h3><?php esc_html_e( 'SiteIntelix Debug Log Viewer', 'siteintelix' ); ?></h3>
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
	update_option( 'siteintelix_activated_at', current_time( 'mysql' ) );

	$siteintelix_mu_debug_class = SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-mu-debug.php';
	if ( ! class_exists( 'SITEINTELIX_MU_Debug' ) && file_exists( $siteintelix_mu_debug_class ) ) {
		require_once $siteintelix_mu_debug_class;
	}

	if ( class_exists( 'SITEINTELIX_MU_Debug' ) ) {
		SITEINTELIX_MU_Debug::ensure_mu_plugin_file();
	}
}
register_activation_hook( __FILE__, 'siteintelix_activate' );
