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

	const OVERVIEW_REMOTE_HEALTH_TRANSIENT = 'siteintelix_overview_remote_health';

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Return all system information as a single nested array.
	 *
	 * Keys: 'wordpress', 'server', 'environment', 'database'.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_all() {
		return array(
			'wordpress'   => self::get_wordpress_info(),
			'server'      => self::get_server_info(),
			'environment' => self::get_environment_info(),
			'database'    => self::get_database_info(),
		);
	}

	/**
	 * Return an export-safe copy of collected system information.
	 *
	 * @param array<string,array<string,mixed>> $info Collected system information.
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_redacted_export( array $info ) {
		$redacted = $info;

		unset( $redacted['wordpress']['admin_email'] );
		unset( $redacted['server']['db_name'], $redacted['server']['db_host'], $redacted['server']['uploads_dir'] );
		unset(
			$redacted['database']['username'],
			$redacted['database']['host'],
			$redacted['database']['name'],
			$redacted['database']['table_prefix']
		);

		$redacted['privacy'] = array(
			'redacted' => true,
			'note'     => __( 'Private paths, database identifiers, and administrator email are omitted.', 'siteintelix' ),
		);

		return $redacted;
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
	 * Gather database connection and runtime information.
	 *
	 * @return array<string, string>
	 */
	public static function get_database_info() {
		global $wpdb;

		return array(
			'extension'          => self::get_database_extension( $wpdb ),
			'server_version'     => self::get_mysql_version( $wpdb ),
			'client_version'     => self::get_database_client_version(),
			'username'           => defined( 'DB_USER' ) ? DB_USER : '',
			'host'               => isset( $wpdb->dbhost ) ? $wpdb->dbhost : '',
			'name'               => isset( $wpdb->dbname ) ? $wpdb->dbname : '',
			'table_prefix'       => isset( $wpdb->prefix ) ? $wpdb->prefix : '',
			'charset'            => isset( $wpdb->charset ) ? $wpdb->charset : '',
			'collation'          => isset( $wpdb->collate ) ? $wpdb->collate : '',
			'max_allowed_packet' => self::get_database_variable( 'max_allowed_packet' ),
			'max_connections'    => self::get_database_variable( 'max_connections' ),
		);
	}

	/**
	 * Determine the active database PHP extension.
	 *
	 * @param wpdb $wpdb WordPress database abstraction object.
	 * @return string
	 */
	private static function get_database_extension( $wpdb ) {
		if ( isset( $wpdb->dbh ) && is_object( $wpdb->dbh ) ) {
			return strtolower( get_class( $wpdb->dbh ) );
		}

		if ( extension_loaded( 'mysqli' ) ) {
			return 'mysqli';
		}

		return extension_loaded( 'mysql' ) ? 'mysql' : __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Get the database client library version.
	 *
	 * @return string
	 */
	private static function get_database_client_version() {
		global $wpdb;

		if ( is_object( $wpdb ) && method_exists( $wpdb, 'db_server_info' ) ) {
			return (string) $wpdb->db_server_info();
		}

		return __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Read a MySQL server variable.
	 *
	 * @param string $name Variable name.
	 * @return string
	 */
	private static function get_database_variable( $name ) {
		global $wpdb;

		$allowed = array( 'max_allowed_packet', 'max_connections' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return __( 'Unknown', 'siteintelix' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ), 1 );

		return null === $value ? __( 'Unknown', 'siteintelix' ) : (string) $value;
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
		$rest_status = self::get_cached_rest_api_status();

		return array(
			'rest_api'     => $rest_status['available'],
			'rest_api_stale' => $rest_status['stale'],
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
	 * Read the most recently cached REST health result without making a request.
	 *
	 * @return array{available:bool|null,stale:bool,collected_at:string}
	 */
	private static function get_cached_rest_api_status() {
		$cached = get_transient( self::OVERVIEW_REMOTE_HEALTH_TRANSIENT );
		if ( ! is_array( $cached ) || ! array_key_exists( 'available', $cached ) ) {
			return array(
				'available'    => null,
				'stale'        => true,
				'collected_at' => '',
			);
		}

		return array(
			'available'    => (bool) $cached['available'],
			'stale'        => ! empty( $cached['stale'] ),
			'collected_at' => isset( $cached['collected_at'] ) ? sanitize_text_field( $cached['collected_at'] ) : '',
		);
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
		if ( ! function_exists( 'disk_free_space' ) ) {
			return __( 'Unknown', 'siteintelix' );
		}
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
