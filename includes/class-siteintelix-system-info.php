<?php
/**
 * SITEINTELIX_System_Info — collects WordPress, server, and environment data.
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_System_Info
 *
 * Provides static helper methods for gathering all system information.
 * Returns plain PHP scalars / arrays so callers can render or serialise
 * the data freely without coupling this class to any output layer.
 */
class SITEINTELIX_System_Info {

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return all system information as a single nested array.
	 *
	 * Keys: 'wordpress', 'server', 'environment'.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all() {
		return array(
			'wordpress'   => self::get_wordpress_info(),
			'server'      => self::get_server_info(),
			'environment' => self::get_environment_info(),
		);
	}

	// -----------------------------------------------------------------------
	// WordPress
	// -----------------------------------------------------------------------

	/**
	 * Gather WordPress-specific information.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_wordpress_info() {
		$theme = wp_get_theme();

		return array(
			'wp_version'     => get_bloginfo( 'version' ),
			'site_title'     => get_bloginfo( 'name' ),
			'site_url'       => get_site_url(),
			'home_url'       => get_home_url(),
			'permalink'     => get_option( 'permalink_structure', '' ),
			'timezone'      => self::get_timezone_string(),
			'admin_email'   => get_bloginfo( 'admin_email' ),
			'active_theme'   => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
			'active_plugins' => self::get_active_plugins_list(),
			'multisite'      => is_multisite(),
			'language'       => get_bloginfo( 'language' ),
			'charset'        => get_bloginfo( 'charset' ),
		);
	}

	/**
	 * Build an array of active plugin "Name Version" strings.
	 *
	 * @return array<int, string>
	 */
	private static function get_active_plugins_list() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active   = get_option( 'active_plugins', array() );
		$plugins  = get_plugins();
		$result   = array();

		foreach ( $active as $plugin_file ) {
			if ( isset( $plugins[ $plugin_file ] ) ) {
				$data     = $plugins[ $plugin_file ];
				$result[] = $data['Name'] . ' ' . $data['Version'];
			}
		}

		return $result;
	}

	// -----------------------------------------------------------------------
	// Server
	// -----------------------------------------------------------------------

	/**
	 * Gather server / PHP environment information.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_server_info() {
		global $wpdb;

		// Safely read SERVER_SOFTWARE — never trusted, always sanitised.
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
			: __( 'Unknown', 'siteintelix' );

		return array(
			'php_version'     => PHP_VERSION,
			'php_sapi'        => PHP_SAPI,
			'server_software' => $server_software,
			'mysql_version'   => self::get_mysql_version( $wpdb ),
			'memory_limit'    => ini_get( 'memory_limit' ),
			'memory_limit_mb' => self::convert_to_mb( (string) ini_get( 'memory_limit' ) ),
			'max_upload_size' => size_format( wp_max_upload_size() ),
			'max_exec_time'   => ini_get( 'max_execution_time' ) . 's',
			'post_max_size'   => ini_get( 'post_max_size' ),
			'os'              => PHP_OS,
			'architecture'    => PHP_INT_SIZE === 8 ? '64-bit' : '32-bit',
			'opcache'         => (bool) ini_get( 'opcache.enable' ),
			'php_extensions'  => self::get_extensions_snapshot(),
			'uploads_dir'     => wp_get_upload_dir(),
			'disk_free'       => self::get_disk_free(),
			'db_name'         => isset( $wpdb->dbname ) ? $wpdb->dbname : '',
			'db_host'         => isset( $wpdb->dbhost ) ? $wpdb->dbhost : '',
		);
	}

	/**
	 * Retrieve the MySQL / MariaDB server version string via wpdb.
	 *
	 * @param wpdb $wpdb  WordPress database abstraction object.
	 * @return string
	 */
	private static function get_mysql_version( $wpdb ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$version = $wpdb->get_var( 'SELECT VERSION()' );
		return $version ? $version : __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Convert a PHP ini size string (e.g. "128M") to an integer in megabytes.
	 *
	 * Returns -1 for unlimited ("-1").
	 *
	 * @param string $size_str  Value from ini_get(), e.g. "256M".
	 * @return int  Size in MB; -1 for unlimited.
	 */
	public static function convert_to_mb( $size_str ) {
		$size_str = trim( $size_str );

		if ( '-1' === $size_str ) {
			return -1;
		}

		$unit  = strtoupper( substr( $size_str, -1 ) );
		$value = (int) $size_str;

		switch ( $unit ) {
			case 'G':
				return $value * 1024;
			case 'M':
				return $value;
			case 'K':
				return (int) round( $value / 1024 );
			default:
				return (int) round( $value / 1024 / 1024 );
		}
	}

	// -----------------------------------------------------------------------
	// Environment
	// -----------------------------------------------------------------------

	/**
	 * Gather WordPress environment / configuration flags.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_environment_info() {
		return array(
			'rest_api'     => self::check_rest_api(),
			'debug_mode'   => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'debug_log'    => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'cron'         => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'https'        => is_ssl(),
			'environment'  => defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production',
			'cache'        => defined( 'WP_CACHE' ) && WP_CACHE,
			'script_debug' => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'file_edit'    => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
			'file_mods'    => defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS,
			'auto_update'  => defined( 'WP_AUTO_UPDATE_CORE' ) ? WP_AUTO_UPDATE_CORE : 'minor',
			'alt_cron'     => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'cron_lock'    => defined( 'WP_CRON_LOCK_TIMEOUT' ) ? WP_CRON_LOCK_TIMEOUT : '',
		);
	}

	/**
	 * Check whether the REST API is reachable with a local loopback request.
	 *
	 * Uses a 5-second timeout and skips SSL verification for local requests.
	 *
	 * @return bool  TRUE when the REST API responds with HTTP 200.
	 */
	private static function check_rest_api() {
		$response = wp_remote_get(
			rest_url( '/' ),
			array(
				'timeout'   => 5,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Return a trimmed list of key PHP extensions useful to surface.
	 *
	 * @return array<string, bool>
	 */
	private static function get_extensions_snapshot() {
		$keys = array( 'curl', 'mbstring', 'intl', 'openssl', 'imagick', 'gd', 'zip', 'pdo', 'pdo_mysql' );
		$result = array();

		foreach ( $keys as $ext ) {
			$result[ $ext ] = extension_loaded( $ext );
		}

		return $result;
	}

	/**
	 * Human-readable free disk space for the WordPress root, if available.
	 *
	 * @return string
	 */
	private static function get_disk_free() {
		$bytes = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $bytes ? size_format( $bytes ) : __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Prefer timezone_string; fall back to gmt_offset.
	 *
	 * @return string
	 */
	private static function get_timezone_string() {
		$tz = get_option( 'timezone_string' );
		if ( ! empty( $tz ) ) {
			return $tz;
		}

		$offset = get_option( 'gmt_offset', 0 );
		$hours  = (int) $offset;
		$mins   = ( $offset - $hours );
		$sign   = $offset >= 0 ? '+' : '-';
		return sprintf( 'UTC%s%02d:%02d', $sign, abs( $hours ), abs( $mins * 60 ) );
	}
}
