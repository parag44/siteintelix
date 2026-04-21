<?php
/**
 * SiteIntelix — Uninstall handler.
 *
 * Runs when the plugin is deleted from the Plugins screen.
 * Cleans up options and the MU-plugin file. Does NOT modify
 * wp-config.php — the user must revert those changes manually.
 *
 * @package SiteIntelix
 * @since   1.2.0
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
	'siteintelix_debug_method',
	'siteintelix_security_xmlrpc',
	'siteintelix_security_hide_version',
	'siteintelix_security_disable_file_edit',
);

foreach ( $siteintelix_options as $option ) {
	delete_option( $option );
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

// Note: wp-config.php is NOT modified during uninstall.
// If the user enabled WP_DEBUG via the plugin's wp-config method,
// they must manually remove the SiteIntelix block from wp-config.php.
