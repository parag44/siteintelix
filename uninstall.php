<?php
/**
 * SiteIntelix — Uninstall handler.
 *
 * Runs when the plugin is deleted from the Plugins screen.
 * Cleans up options and the MU-plugin file. Does NOT modify
 * wp-config.php — the user must revert those changes manually.
 *
 * @package SiteIntelix
 * @since   2.1.0
 */

// Guard: must be called by WordPress uninstaller.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Remove plugin options.
// ---------------------------------------------------------------------------
$siteintelix_options = array(
	'siteintelix_activated_at',
	'siteintelix_enable_debug_capture',
	'siteintelix_debug_ui',
	'siteintelix_enabled_modules',
	'siteintelix_logs_per_page',
	'siteintelix_debug_method',
	'siteintelix_previous_debug_method',
	'siteintelix_email_log_settings',
	'siteintelix_error_ui_settings',
	'siteintelix_error_ui_dropins_version',
	'siteintelix_migration_version',
	'siteintelix_tm_logs',
	'siteintelix_tm_last_cleanup',
);

foreach ( $siteintelix_options as $siteintelix_option ) {
	delete_option( $siteintelix_option );
}

// ---------------------------------------------------------------------------
// Remove MU-plugin file.
// ---------------------------------------------------------------------------
$siteintelix_mu_file = trailingslashit( WPMU_PLUGIN_DIR ) . 'siteintelix-debug-capture.php';

if ( file_exists( $siteintelix_mu_file ) ) {
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	global $wp_filesystem;

	if ( WP_Filesystem() && $wp_filesystem ) {
		$wp_filesystem->delete( $siteintelix_mu_file, false, 'f' );
	}
}

// ---------------------------------------------------------------------------
// Remove SiteIntelix-managed Custom Error UI drop-ins.
// ---------------------------------------------------------------------------
$siteintelix_error_ui_files = array(
	trailingslashit( WP_CONTENT_DIR ) . 'db-error.php',
	trailingslashit( WP_CONTENT_DIR ) . 'fatal-error-handler.php',
	trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-error-ui-config.php',
);

foreach ( $siteintelix_error_ui_files as $siteintelix_error_ui_file ) {
	if ( is_readable( $siteintelix_error_ui_file ) ) {
		$siteintelix_error_ui_contents = file_get_contents( $siteintelix_error_ui_file );
		if ( false !== strpos( (string) $siteintelix_error_ui_contents, 'SiteIntelix Error UI' ) ) {
			wp_delete_file( $siteintelix_error_ui_file );
		}
	}
}

// ---------------------------------------------------------------------------
// Remove Email Log table.
// ---------------------------------------------------------------------------
global $wpdb;

$siteintelix_email_table = $wpdb->prefix . 'siteintelix_email_logs';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing plugin-owned table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$siteintelix_email_table}" );

// Note: wp-config.php is NOT modified during uninstall.
// If the user enabled WP_DEBUG via the plugin's wp-config method,
// they must manually remove the SiteIntelix block from wp-config.php.
