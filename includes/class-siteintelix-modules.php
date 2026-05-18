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
	 * Translate labels only after WordPress has reached init.
	 *
	 * @param string $text Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	private static function translate( $text, $domain ) {
		if ( did_action( 'init' ) ) {
			return __( $text, $domain );
		}

		return $text;
	}

	/**
	 * Return all known modules.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_all() {
		$modules = array(
			'debug_log' => array(
				'id'          => 'debug_log',
				'title'       => self::translate( 'Debug Log', 'siteintelix' ),
				'menu_title'  => self::translate( 'Debug Log', 'siteintelix' ),
				'description' => self::translate( 'Search, filter, group, clear, and download WordPress debug logs.', 'siteintelix' ),
				'icon'        => 'dashicons-media-text',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => true,
				'settings'    => array(
					'title'       => self::translate( 'Debug Log Settings', 'siteintelix' ),
					'description' => self::translate( 'Configure debug capture, viewer layout, and pagination for the Debug Log module.', 'siteintelix' ),
					'action'      => 'siteintelix_save_debug_settings',
				),
			),
			'email_log' => array(
				'id'          => 'email_log',
				'title'       => self::translate( 'Email Log', 'siteintelix' ),
				'menu_title'  => self::translate( 'Email Log', 'siteintelix' ),
				'description' => self::translate( 'Track outgoing WordPress emails, delivery events, recipients, and failures.', 'siteintelix' ),
				'icon'        => 'dashicons-email-alt',
				'color'       => 'orange',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => self::translate( 'Email Log Settings', 'siteintelix' ),
					'description' => self::translate( 'Configure outgoing email capture and retention.', 'siteintelix' ),
					'action'      => 'siteintelix_save_email_log_settings',
				),
			),
			'smtp' => array(
				'id'          => 'smtp',
				'title'       => self::translate( 'SMTP', 'siteintelix' ),
				'menu_title'  => self::translate( 'SMTP', 'siteintelix' ),
				'description' => self::translate( 'Route all WordPress emails through a configured SMTP provider.', 'siteintelix' ),
				'icon'        => 'dashicons-networking',
				'color'       => 'teal',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => self::translate( 'SMTP Settings', 'siteintelix' ),
					'description' => self::translate( 'Configure SMTP delivery for WordPress emails.', 'siteintelix' ),
					'action'      => 'siteintelix_save_smtp_settings',
				),
			),
			'cron_events' => array(
				'id'          => 'cron_events',
				'title'       => self::translate( 'Cron Events', 'siteintelix' ),
				'menu_title'  => self::translate( 'Cron Events', 'siteintelix' ),
				'description' => self::translate( 'View, run, and remove scheduled WordPress cron events.', 'siteintelix' ),
				'icon'        => 'dashicons-clock',
				'color'       => 'indigo',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'database_manager' => array(
				'id'          => 'database_manager',
				'title'       => self::translate( 'Database Manager', 'siteintelix' ),
				'menu_title'  => self::translate( 'Database Manager', 'siteintelix' ),
				'description' => self::translate( 'Inspect database tables, browse rows, and safely edit or delete selected records.', 'siteintelix' ),
				'icon'        => 'dashicons-database',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'download_manager' => array(
				'id'          => 'download_manager',
				'title'       => self::translate( 'Download Manager', 'siteintelix' ),
				'menu_title'  => self::translate( 'Download Manager', 'siteintelix' ),
				'description' => self::translate( 'Add secure Download links for installed plugins and themes in WordPress admin.', 'siteintelix' ),
				'icon'        => 'dashicons-download',
				'color'       => 'blue',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'safe_mode_debugger' => array(
				'id'          => 'safe_mode_debugger',
				'title'       => self::translate( 'Safe Mode Debugger', 'siteintelix' ),
				'menu_title'  => self::translate( 'Safe Mode', 'siteintelix' ),
				'description' => self::translate( 'Debug plugin and theme conflicts privately without affecting live visitors.', 'siteintelix' ),
				'icon'        => 'dashicons-shield-alt',
				'color'       => 'teal',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
			),
			'activity_log' => array(
				'id'          => 'activity_log',
				'title'       => self::translate( 'Activity Log', 'siteintelix' ),
				'menu_title'  => self::translate( 'Activity Log', 'siteintelix' ),
				'description' => self::translate( 'Audit admin actions, content changes, logins, and key site events.', 'siteintelix' ),
				'icon'        => 'dashicons-list-view',
				'color'       => 'purple',
				'status'      => 'planned',
				'available'   => false,
				'default'     => false,
			),
			'error_ui' => array(
				'id'          => 'error_ui',
				'title'       => self::translate( 'Custom Error UI', 'siteintelix' ),
				'menu_title'  => self::translate( 'Error UI', 'siteintelix' ),
				'description' => self::translate( 'Create branded fatal and database error pages for safer public-facing failures.', 'siteintelix' ),
				'icon'        => 'dashicons-sos',
				'color'       => 'red',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => self::translate( 'Custom Error UI Settings', 'siteintelix' ),
					'description' => self::translate( 'Configure custom database and fatal error pages.', 'siteintelix' ),
					'action'      => 'siteintelix_save_error_ui_settings',
				),
			),
			'coming_soon' => array(
				'id'          => 'coming_soon',
				'title'       => self::translate( 'Maintenance Mode', 'siteintelix' ),
				'menu_title'  => self::translate( 'Maintenance Mode', 'siteintelix' ),
				'description' => self::translate( 'Show a public maintenance page while admins keep full site access.', 'siteintelix' ),
				'icon'        => 'dashicons-admin-tools',
				'color'       => 'green',
				'status'      => 'core',
				'available'   => true,
				'default'     => false,
				'settings'    => array(
					'title'       => self::translate( 'Maintenance Mode Settings', 'siteintelix' ),
					'description' => self::translate( 'Customize the public maintenance page shown to visitors.', 'siteintelix' ),
					'action'      => 'siteintelix_save_coming_soon_settings',
				),
			),
			'uptime_monitor' => array(
				'id'          => 'uptime_monitor',
				'title'       => self::translate( 'Uptime Monitor', 'siteintelix' ),
				'menu_title'  => self::translate( 'Uptime Monitor', 'siteintelix' ),
				'description' => self::translate( 'Monitor availability, response time, and downtime incidents.', 'siteintelix' ),
				'icon'        => 'dashicons-performance',
				'color'       => 'teal',
				'status'      => 'planned',
				'available'   => false,
				'default'     => false,
			),
			'backup_tools' => array(
				'id'          => 'backup_tools',
				'title'       => self::translate( 'Backup Tools', 'siteintelix' ),
				'menu_title'  => self::translate( 'Backup Tools', 'siteintelix' ),
				'description' => self::translate( 'Backup, restore, and inspect important site files and database snapshots.', 'siteintelix' ),
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
			return new WP_Error( 'siteintelix_unknown_module', self::translate( 'Unknown module.', 'siteintelix' ) );
		}

		if ( empty( $modules[ $module_id ]['available'] ) ) {
			return new WP_Error( 'siteintelix_unavailable_module', self::translate( 'This module is not available yet.', 'siteintelix' ) );
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
