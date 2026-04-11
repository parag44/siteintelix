<?php
/**
 * Plugin Name:       SiteIntelix
 * Plugin URI:        https://parag.bd/siteintelix
 * Description:       Displays comprehensive WordPress, server, and environment information in a clean admin dashboard with colour-coded health checks and export tools.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Parag Das
 * Author URI:        https://wordpress.org/plugins/siteintelix
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
define( 'SITEINTELIX_VERSION', '1.1.0' );

/** Absolute path to the plugin directory (trailing slash). */
define( 'SITEINTELIX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/** Public URL to the plugin directory (trailing slash). */
define( 'SITEINTELIX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

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
	require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-rest-api.php';
}
add_action( 'plugins_loaded', 'siteintelix_load_includes' );

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
		__( 'SiteIntelix', 'siteintelix' ), // Browser <title>.
	__( 'SiteIntelix Panel', 'siteintelix' ),          // Menu label.
		'manage_options',                                       // Capability.
		'siteintelix',                                  // Menu slug.
		'siteintelix_render_admin_page',                                // Callback.
		'dashicons-chart-area',                                 // Icon.
		80                                                      // Position.
	);
}
add_action( 'admin_menu', 'siteintelix_register_admin_menu' );

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

// ---------------------------------------------------------------------------
// Enqueue admin assets
// ---------------------------------------------------------------------------

/**
 * Enqueue the plugin's CSS and JS only on its own admin page.
 *
 * @param string $hook_suffix Current admin page hook suffix.
 */
function siteintelix_enqueue_admin_assets( $hook_suffix ) {
	if ( 'toplevel_page_siteintelix' !== $hook_suffix ) {
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
 * Stores the activation timestamp and flushes rewrite rules so the REST
 * route is available immediately after activation.
 */
function siteintelix_activate() {
	update_option( 'siteintelix_activated_at', current_time( 'mysql' ) );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'siteintelix_activate' );

/**
 * Plugin deactivation callback.
 */
function siteintelix_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'siteintelix_deactivate' );
