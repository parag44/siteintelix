<?php
/**
 * Versioned SiteIntelix migrations.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one-time cleanup without adding frontend request overhead.
 */
class SITEINTELIX_Migrations {

	const VERSION_OPTION  = 'siteintelix_migration_version';
	const CURRENT_VERSION = '2.8.2.0';

	/**
	 * Run pending migrations.
	 *
	 * @return void
	 */
	public static function run() {
		if ( version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::CURRENT_VERSION, '>=' ) ) {
			return;
		}

		$legacy_debug_option  = 'siteintelix_enable_debug_capture';
		$legacy_debug_enabled = (bool) get_option( $legacy_debug_option, false );
		$cleanup              = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_SAFETY_GUARD );
		if ( ! empty( $cleanup['failed'] ) ) {
			return;
		}

		foreach ( array( SITEINTELIX_MU_Files::TYPE_DEBUG, SITEINTELIX_MU_Files::TYPE_LEGACY_DEBUG ) as $debug_type ) {
			$cleanup = SITEINTELIX_MU_Files::remove_type( $debug_type );
			if ( ! empty( $cleanup['failed'] ) ) {
				return;
			}
		}

		update_option( 'siteintelix_debug_method', 'wp_config', false );
		delete_option( 'siteintelix_debug_ui' );

		if ( $legacy_debug_enabled ) {
			if ( ! class_exists( 'SITEINTELIX_Debug_Storage' ) ) {
				require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-debug-storage.php';
			}
			if ( ! class_exists( 'SITEINTELIX_WP_Config' ) ) {
				require_once SITEINTELIX_PLUGIN_DIR . 'includes/class-siteintelix-wp-config.php';
			}
			$result = SITEINTELIX_WP_Config::enable();
			if ( is_wp_error( $result ) ) {
				update_option( $legacy_debug_option, 0, false );
				set_transient( 'siteintelix_debug_migration_notice', sanitize_text_field( $result->get_error_message() ), DAY_IN_SECONDS );
			}
		}

		if ( ! self::disable_smtp_autoload() ) {
			return;
		}

		self::remove_retired_error_ui();
		self::use_wordpress_root_for_file_manager();
		update_option( self::VERSION_OPTION, self::CURRENT_VERSION, false );
	}

	/** Make the 2.8 File Manager open at the WordPress installation root. */
	private static function use_wordpress_root_for_file_manager() {
		$option   = 'siteintelix_file_manager_settings';
		$settings = get_option( $option, array() );
		if ( ! is_array( $settings ) ) {
			return;
		}
		if ( ! isset( $settings['start_directory'] ) || 'wp-content' === trim( wp_normalize_path( (string) $settings['start_directory'] ), '/' ) ) {
			$settings['start_directory'] = '';
			update_option( $option, $settings, false );
		}
	}

	/**
	 * Keep stored SMTP credentials out of the all-options cache.
	 *
	 * @return bool
	 */
	private static function disable_smtp_autoload() {
		if ( null === get_option( 'siteintelix_smtp_settings', null ) ) {
			return true;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration of a fixed core option row.
		$updated = $wpdb->update(
			$wpdb->options,
			array( 'autoload' => 'no' ),
			array( 'option_name' => 'siteintelix_smtp_settings' ),
			array( '%s' ),
			array( '%s' )
		);

		if ( false === $updated ) {
			return false;
		}

		wp_cache_delete( 'siteintelix_smtp_settings', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		return true;
	}

	/**
	 * Remove retired options and only files positively identified as SiteIntelix-owned.
	 *
	 * @return void
	 */
	private static function remove_retired_error_ui() {
		delete_option( 'siteintelix_error_ui_settings' );
		delete_option( 'siteintelix_error_ui_dropins_version' );

		$enabled = get_option( SITEINTELIX_MODULES_OPTION, array() );
		if ( is_array( $enabled ) && in_array( 'error_ui', $enabled, true ) ) {
			update_option(
				SITEINTELIX_MODULES_OPTION,
				array_values( array_diff( $enabled, array( 'error_ui' ) ) )
			);
		}

		$files = array(
			trailingslashit( WP_CONTENT_DIR ) . 'db-error.php',
			trailingslashit( WP_CONTENT_DIR ) . 'fatal-error-handler.php',
			trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-error-ui-config.php',
		);

		foreach ( $files as $file ) {
			if ( ! is_readable( $file ) ) {
				continue;
			}

			$contents = file_get_contents( $file );
			if (
				false !== strpos( (string) $contents, 'SiteIntelix Error UI' )
				|| false !== strpos( (string) $contents, 'SiteIntelix Error Handler generated config' )
			) {
				wp_delete_file( $file );
			}
		}
	}
}
