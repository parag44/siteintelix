<?php
/**
 * Module registry for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry for toolbox modules.
 */
class SITEINTELIX_Modules {

	/**
	 * Return all known modules.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_all() {
		$modules = array(
			'debug_log' => array(
				'id'          => 'debug_log',
				'title'       => ( did_action( 'init' ) ? __( 'Debug Log', 'siteintelix' ) : 'Debug Log' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Debug Log', 'siteintelix' ) : 'Debug Log' ),
				'description' => ( did_action( 'init' ) ? __( 'Search, filter, group, clear, and download WordPress debug logs.', 'siteintelix' ) : 'Search, filter, group, clear, and download WordPress debug logs.' ),
				'icon'        => 'dashicons-media-text',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => true,
				'settings'    => array(
					'title'       => ( did_action( 'init' ) ? __( 'Debug Log Settings', 'siteintelix' ) : 'Debug Log Settings' ),
					'description' => ( did_action( 'init' ) ? __( 'Configure debug capture and pagination for the Debug Log module.', 'siteintelix' ) : 'Configure debug capture and pagination for the Debug Log module.' ),
					'action'      => 'siteintelix_save_debug_settings',
				),
			),
			'email_log' => array(
				'id'          => 'email_log',
				'title'       => ( did_action( 'init' ) ? __( 'Email Log', 'siteintelix' ) : 'Email Log' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Email Log', 'siteintelix' ) : 'Email Log' ),
				'description' => ( did_action( 'init' ) ? __( 'Track outgoing WordPress emails, delivery events, recipients, and failures.', 'siteintelix' ) : 'Track outgoing WordPress emails, delivery events, recipients, and failures.' ),
				'icon'        => 'dashicons-email-alt',
				'color'       => 'orange',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => ( did_action( 'init' ) ? __( 'Email Log Settings', 'siteintelix' ) : 'Email Log Settings' ),
					'description' => ( did_action( 'init' ) ? __( 'Configure outgoing email capture and retention.', 'siteintelix' ) : 'Configure outgoing email capture and retention.' ),
					'action'      => 'siteintelix_save_email_log_settings',
				),
			),
			'smtp' => array(
				'id'          => 'smtp',
				'title'       => ( did_action( 'init' ) ? __( 'SMTP', 'siteintelix' ) : 'SMTP' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'SMTP', 'siteintelix' ) : 'SMTP' ),
				'description' => ( did_action( 'init' ) ? __( 'Route all WordPress emails through a configured SMTP provider.', 'siteintelix' ) : 'Route all WordPress emails through a configured SMTP provider.' ),
				'icon'        => 'dashicons-networking',
				'color'       => 'teal',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => ( did_action( 'init' ) ? __( 'SMTP Settings', 'siteintelix' ) : 'SMTP Settings' ),
					'description' => ( did_action( 'init' ) ? __( 'Configure SMTP delivery for WordPress emails.', 'siteintelix' ) : 'Configure SMTP delivery for WordPress emails.' ),
					'action'      => 'siteintelix_save_smtp_settings',
				),
			),
			'cron_events' => array(
				'id'          => 'cron_events',
				'title'       => ( did_action( 'init' ) ? __( 'Cron Events', 'siteintelix' ) : 'Cron Events' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Cron Events', 'siteintelix' ) : 'Cron Events' ),
				'description' => ( did_action( 'init' ) ? __( 'View, run, and remove scheduled WordPress cron events.', 'siteintelix' ) : 'View, run, and remove scheduled WordPress cron events.' ),
				'icon'        => 'dashicons-clock',
				'color'       => 'indigo',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'database_manager' => array(
				'id'          => 'database_manager',
				'title'       => ( did_action( 'init' ) ? __( 'Database Manager', 'siteintelix' ) : 'Database Manager' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Database Manager', 'siteintelix' ) : 'Database Manager' ),
				'description' => ( did_action( 'init' ) ? __( 'Inspect database tables, browse rows, and safely edit or delete selected records.', 'siteintelix' ) : 'Inspect database tables, browse rows, and safely edit or delete selected records.' ),
				'icon'        => 'dashicons-database',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'download_manager' => array(
				'id'          => 'download_manager',
				'title'       => ( did_action( 'init' ) ? __( 'Download Manager', 'siteintelix' ) : 'Download Manager' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Download Manager', 'siteintelix' ) : 'Download Manager' ),
				'description' => ( did_action( 'init' ) ? __( 'Add secure download links for installed plugins and themes in WordPress admin.', 'siteintelix' ) : 'Add secure download links for installed plugins and themes in WordPress admin.' ),
				'icon'        => 'dashicons-download',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'transients_manager' => array(
				'id'          => 'transients_manager',
				'title'       => ( did_action( 'init' ) ? __( 'Transients Manager', 'siteintelix' ) : 'Transients Manager' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Transients Manager', 'siteintelix' ) : 'Transients Manager' ),
				'description' => ( did_action( 'init' ) ? __( 'Inspect, search, analyze, and safely manage WordPress transients and temporary cache data.', 'siteintelix' ) : 'Inspect, search, analyze, and safely manage WordPress transients and temporary cache data.' ),
				'icon'        => 'dashicons-database-view',
				'color'       => 'teal',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'server_diagnostics' => array(
				'id'          => 'server_diagnostics',
				'title'       => ( did_action( 'init' ) ? __( 'Server Diagnostics', 'siteintelix' ) : 'Server Diagnostics' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Server Diagnostics', 'siteintelix' ) : 'Server Diagnostics' ),
				'description' => ( did_action( 'init' ) ? __( 'Find PHP, filesystem, network, database, and compatibility issues that can make plugins fail on this server.', 'siteintelix' ) : 'Find PHP, filesystem, network, database, and compatibility issues that can make plugins fail on this server.' ),
				'icon'        => 'dashicons-admin-site-alt3',
				'color'       => 'indigo',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'safe_mode_debugger' => array(
				'id'          => 'safe_mode_debugger',
				'title'       => ( did_action( 'init' ) ? __( 'Safe Mode Debugger', 'siteintelix' ) : 'Safe Mode Debugger' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Safe Mode', 'siteintelix' ) : 'Safe Mode' ),
				'description' => ( did_action( 'init' ) ? __( 'Debug plugin and theme conflicts privately without affecting live visitors.', 'siteintelix' ) : 'Debug plugin and theme conflicts privately without affecting live visitors.' ),
				'icon'        => 'dashicons-shield-alt',
				'color'       => 'teal',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'activity_log' => array(
				'id'          => 'activity_log',
				'title'       => ( did_action( 'init' ) ? __( 'Activity Log', 'siteintelix' ) : 'Activity Log' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Activity Log', 'siteintelix' ) : 'Activity Log' ),
				'description' => ( did_action( 'init' ) ? __( 'Audit admin actions, content changes, logins, and key site events.', 'siteintelix' ) : 'Audit admin actions, content changes, logins, and key site events.' ),
				'icon'        => 'dashicons-list-view',
				'color'       => 'purple',
				'status'      => 'planned',
				'available'   => false,
				'default'     => false,
			),
			'error_ui' => array(
				'id'          => 'error_ui',
				'title'       => ( did_action( 'init' ) ? __( 'Custom Error UI', 'siteintelix' ) : 'Custom Error UI' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Error UI', 'siteintelix' ) : 'Error UI' ),
				'description' => ( did_action( 'init' ) ? __( 'Create branded fatal and database error pages for safer public-facing failures.', 'siteintelix' ) : 'Create branded fatal and database error pages for safer public-facing failures.' ),
				'icon'        => 'dashicons-sos',
				'color'       => 'red',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => ( did_action( 'init' ) ? __( 'Custom Error UI Settings', 'siteintelix' ) : 'Custom Error UI Settings' ),
					'description' => ( did_action( 'init' ) ? __( 'Configure custom database and fatal error pages.', 'siteintelix' ) : 'Configure custom database and fatal error pages.' ),
					'action'      => 'siteintelix_save_error_ui_settings',
				),
			),
			'coming_soon' => array(
				'id'          => 'coming_soon',
				'title'       => ( did_action( 'init' ) ? __( 'Maintenance Mode', 'siteintelix' ) : 'Maintenance Mode' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Maintenance Mode', 'siteintelix' ) : 'Maintenance Mode' ),
				'description' => ( did_action( 'init' ) ? __( 'Show a public maintenance page while admins keep full site access.', 'siteintelix' ) : 'Show a public maintenance page while admins keep full site access.' ),
				'icon'        => 'dashicons-admin-tools',
				'color'       => 'green',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => ( did_action( 'init' ) ? __( 'Maintenance Mode Settings', 'siteintelix' ) : 'Maintenance Mode Settings' ),
					'description' => ( did_action( 'init' ) ? __( 'Customize the public maintenance page shown to visitors.', 'siteintelix' ) : 'Customize the public maintenance page shown to visitors.' ),
					'action'      => 'siteintelix_save_coming_soon_settings',
				),
			),
			'uptime_monitor' => array(
				'id'          => 'uptime_monitor',
				'title'       => ( did_action( 'init' ) ? __( 'Uptime Monitor', 'siteintelix' ) : 'Uptime Monitor' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Uptime Monitor', 'siteintelix' ) : 'Uptime Monitor' ),
				'description' => ( did_action( 'init' ) ? __( 'Monitor availability, response time, and downtime incidents.', 'siteintelix' ) : 'Monitor availability, response time, and downtime incidents.' ),
				'icon'        => 'dashicons-performance',
				'color'       => 'teal',
				'status'      => 'planned',
				'available'   => false,
				'default'     => false,
			),
			'backup_tools' => array(
				'id'          => 'backup_tools',
				'title'       => ( did_action( 'init' ) ? __( 'Backup Tools', 'siteintelix' ) : 'Backup Tools' ),
				'menu_title'  => ( did_action( 'init' ) ? __( 'Backup Tools', 'siteintelix' ) : 'Backup Tools' ),
				'description' => ( did_action( 'init' ) ? __( 'Backup, restore, and inspect important site files and database snapshots.', 'siteintelix' ) : 'Backup, restore, and inspect important site files and database snapshots.' ),
				'icon'        => 'dashicons-database-export',
				'color'       => 'indigo',
				'status'      => 'planned',
				'available'   => false,
				'default'     => false,
			),
		);

		/**
		 * Filter the registered SiteIntelix modules.
		 *
		 * @param array<string,array<string,mixed>> $modules Module definitions.
		 */
		return apply_filters( 'siteintelix_modules', $modules );
	}

	/**
	 * Get IDs of modules enabled by default.
	 *
	 * @return string[]
	 */
	public static function get_default_enabled() {
		$enabled = array();

		foreach ( self::get_all() as $module ) {
			if ( ! empty( $module['available'] ) && ! empty( $module['default'] ) ) {
				$enabled[] = sanitize_key( $module['id'] );
			}
		}

		return $enabled;
	}

	/**
	 * Get enabled module IDs.
	 *
	 * @return string[]
	 */
	public static function get_enabled() {
		$saved = get_option( SITEINTELIX_MODULES_OPTION, null );

		if ( ! is_array( $saved ) ) {
			return self::get_default_enabled();
		}

		$enabled = array();
		$modules = self::get_all();

		foreach ( $saved as $module_id ) {
			$module_id = sanitize_key( $module_id );
			if ( isset( $modules[ $module_id ] ) && ! empty( $modules[ $module_id ]['available'] ) ) {
				$enabled[] = $module_id;
			}
		}

		return array_values( array_unique( $enabled ) );
	}

	/**
	 * Determine if a module is enabled.
	 *
	 * @param string $module_id Module ID.
	 * @return bool
	 */
	public static function is_enabled( $module_id ) {
		return in_array( sanitize_key( $module_id ), self::get_enabled(), true );
	}

	/**
	 * Save enabled module IDs.
	 *
	 * @param string[] $module_ids Module IDs.
	 * @return void
	 */
	public static function save_enabled( $module_ids ) {
		$modules = self::get_all();
		$enabled = array();

		foreach ( (array) $module_ids as $module_id ) {
			$module_id = sanitize_key( $module_id );
			if ( isset( $modules[ $module_id ] ) && ! empty( $modules[ $module_id ]['available'] ) ) {
				$enabled[] = $module_id;
			}
		}

		update_option( SITEINTELIX_MODULES_OPTION, array_values( array_unique( $enabled ) ) );
	}

	/**
	 * Enable or disable a single module.
	 *
	 * @param string $module_id Module ID.
	 * @param bool   $enabled   Whether the module should be enabled.
	 * @return true|WP_Error
	 */
	public static function set_enabled( $module_id, $enabled ) {
		$module_id = sanitize_key( $module_id );
		$modules   = self::get_all();

		if ( ! isset( $modules[ $module_id ] ) ) {
			return new WP_Error( 'siteintelix_unknown_module', ( did_action( 'init' ) ? __( 'Unknown module.', 'siteintelix' ) : 'Unknown module.' ) );
		}

		if ( empty( $modules[ $module_id ]['available'] ) ) {
			return new WP_Error( 'siteintelix_unavailable_module', ( did_action( 'init' ) ? __( 'This module is not available yet.', 'siteintelix' ) : 'This module is not available yet.' ) );
		}

		$current = self::get_enabled();

		if ( $enabled ) {
			$current[] = $module_id;
		} else {
			$current = array_values( array_diff( $current, array( $module_id ) ) );
		}

		self::save_enabled( $current );

		return true;
	}
}
