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

$siteintelix_mu_files_class = __DIR__ . '/includes/class-siteintelix-mu-files.php';
if ( file_exists( $siteintelix_mu_files_class ) ) {
	require_once $siteintelix_mu_files_class;
	SITEINTELIX_MU_Files::remove_all();
}

$siteintelix_legacy_delete_custom_code = (bool) get_option( 'siteintelix_delete_custom_code_on_uninstall', false );
$siteintelix_delete_custom_css_js      = (bool) get_option( 'siteintelix_delete_custom_css_js_on_uninstall', $siteintelix_legacy_delete_custom_code );
$siteintelix_delete_code_snippets      = (bool) get_option( 'siteintelix_delete_code_snippets_on_uninstall', $siteintelix_legacy_delete_custom_code );
$siteintelix_file_manager_settings     = get_option( 'siteintelix_file_manager_settings', array() );
$siteintelix_delete_file_manager_data  = is_array( $siteintelix_file_manager_settings ) && ! empty( $siteintelix_file_manager_settings['remove_data_on_uninstall'] );

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
	'siteintelix_email_log_schema_version',
	'siteintelix_smtp_settings',
	'siteintelix_coming_soon_settings',
	'siteintelix_server_diagnostics_cache_filesystem',
	'siteintelix_server_diagnostics_cache_network',
	'siteintelix_server_diagnostics_cache_database',
	'siteintelix_error_ui_settings',
	'siteintelix_error_ui_dropins_version',
	'siteintelix_migration_version',
	'siteintelix_tm_logs',
	'siteintelix_tm_last_cleanup',
	'siteintelix_user_switcher_settings',
	'siteintelix_user_switcher_schema_version',
	'siteintelix_user_switcher_managed_roles',
	'siteintelix_delete_custom_code_on_uninstall',
	'siteintelix_delete_custom_css_js_on_uninstall',
	'siteintelix_delete_code_snippets_on_uninstall',
	'siteintelix_custom_code_schema_version',
	'siteintelix_snippets_schema_version',
	'siteintelix_file_manager_settings',
);

// Remove only role capabilities that SiteIntelix recorded as module-managed.
$siteintelix_user_switcher_managed_roles = get_option( 'siteintelix_user_switcher_managed_roles', array() );
foreach ( (array) $siteintelix_user_switcher_managed_roles as $siteintelix_user_switcher_role_slug ) {
	$siteintelix_user_switcher_role = get_role( sanitize_key( $siteintelix_user_switcher_role_slug ) );
	if ( $siteintelix_user_switcher_role ) {
		$siteintelix_user_switcher_role->remove_cap( 'siteintelix_switch_users' );
	}
}

foreach ( $siteintelix_options as $siteintelix_option ) {
	delete_option( $siteintelix_option );
}

delete_metadata( 'user', 0, 'siteintelix_safe_mode_state', '', true );
delete_metadata( 'user', 0, 'siteintelix_safe_mode_log', '', true );
delete_transient( 'siteintelix_overview_remote_health' );

wp_clear_scheduled_hook( 'siteintelix_email_log_retention' );
wp_clear_scheduled_hook( 'siteintelix_user_switcher_retention' );
wp_clear_scheduled_hook( 'siteintelix_user_switcher_retention_continue' );
wp_clear_scheduled_hook( 'siteintelix_file_manager_cleanup' );

if ( $siteintelix_delete_file_manager_data ) {
	$siteintelix_file_manager_storage_class = __DIR__ . '/includes/modules/file-manager/class-siteintelix-file-manager-storage.php';
	if ( file_exists( $siteintelix_file_manager_storage_class ) ) {
		require_once $siteintelix_file_manager_storage_class;
		SITEINTELIX_File_Manager_Storage::delete_all_owned_data();
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
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Removing plugin-owned table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$siteintelix_email_table}" );

$siteintelix_user_switcher_table = $wpdb->prefix . 'siteintelix_user_switch_logs';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Removing plugin-owned table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$siteintelix_user_switcher_table}" );

if ( $siteintelix_delete_custom_css_js ) {
	$siteintelix_custom_code_table = $wpdb->prefix . 'siteintelix_custom_code';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Explicit opt-in removal of a fixed plugin-owned table.
	$wpdb->query( "DROP TABLE IF EXISTS {$siteintelix_custom_code_table}" );
	$siteintelix_uploads = wp_upload_dir();
	$siteintelix_custom_code_dir = trailingslashit( $siteintelix_uploads['basedir'] ) . 'siteintelix/custom-code';
	if ( is_dir( $siteintelix_custom_code_dir ) ) {
		$siteintelix_custom_code_files = glob( $siteintelix_custom_code_dir . '/*' );
		foreach ( (array) $siteintelix_custom_code_files as $siteintelix_custom_code_file ) {
			if (
				is_file( $siteintelix_custom_code_file )
				&& ! is_link( $siteintelix_custom_code_file )
				&& 1 === preg_match( '/^site-[1-9][0-9]*-entry-[1-9][0-9]*\.(?:css|js)$/', basename( $siteintelix_custom_code_file ) )
			) {
				wp_delete_file( $siteintelix_custom_code_file );
			}
		}
		@rmdir( $siteintelix_custom_code_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

if ( $siteintelix_delete_code_snippets ) {
	$siteintelix_snippets_table = $wpdb->prefix . 'siteintelix_snippets';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Explicit opt-in removal of a fixed plugin-owned table.
	$wpdb->query( "DROP TABLE IF EXISTS {$siteintelix_snippets_table}" );
	delete_transient( 'siteintelix_snippet_recovery_notice' );
}

$siteintelix_user_switcher_option_prefixes = array(
	'siteintelix_user_switcher_lock_',
	'_transient_siteintelix_user_switcher_session_',
	'_transient_timeout_siteintelix_user_switcher_session_',
	'_transient_siteintelix_user_switcher_target_',
	'_transient_timeout_siteintelix_user_switcher_target_',
);
foreach ( $siteintelix_user_switcher_option_prefixes as $siteintelix_user_switcher_option_prefix ) {
	$siteintelix_user_switcher_option_pattern = $wpdb->esc_like( $siteintelix_user_switcher_option_prefix ) . '%';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Removing short-lived plugin-owned switching state on uninstall.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$siteintelix_user_switcher_option_pattern
		)
	);
}

// Note: wp-config.php is NOT modified during uninstall.
// If the user enabled WP_DEBUG via the plugin's wp-config method,
// they must manually remove the SiteIntelix block from wp-config.php.
