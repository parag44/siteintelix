<?php
/**
 * SITEINTELIX_Health_Check — evaluates system health and produces status results.
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Health_Check
 *
 * Accepts the array produced by SITEINTELIX_System_Info::get_all() and computes a
 * "status" (good | warning | critical) plus a human-readable message for
 * each metric.
 *
 * Status levels:
 *   good     → green  — everything is fine.
 *   warning  → amber  — sub-optimal, should be addressed.
 *   critical → red    — requires immediate attention.
 */
class SITEINTELIX_Health_Check {

	/** @var string */
	const STATUS_GOOD = 'good';

	/** @var string */
	const STATUS_WARNING = 'warning';

	/** @var string */
	const STATUS_CRITICAL = 'critical';

	// -----------------------------------------------------------------------
	// Public API
	// -----------------------------------------------------------------------

	/**
	 * Run all health checks and return an array of results keyed by check ID.
	 *
	 * Each result contains:
	 *   status  (string) — good | warning | critical
	 *   label   (string) — human-readable check name
	 *   value   (string) — current value
	 *   message (string) — contextual explanation
	 *
	 * @param array $info  Return value of SITEINTELIX_System_Info::get_all().
	 * @return array<string, array{status: string, label: string, value: string, message: string}>
	 */
	public static function run( array $info ) {
		return array(
			'php_version'    => self::check_php_version( $info['server']['php_version'] ),
			'memory_limit'   => self::check_memory_limit( $info['server']['memory_limit_mb'] ),
			'debug_mode'     => self::check_debug_mode( $info['environment']['debug_mode'], $info['environment']['environment'] ),
			'https'          => self::check_https( $info['environment']['https'] ),
			'rest_api'       => self::check_rest_api( $info['environment']['rest_api'] ),
			'cron'           => self::check_cron( $info['environment']['cron'] ),
			'mysql_version'  => self::check_mysql_version( $info['server']['mysql_version'] ),
			'wp_version'     => self::check_wp_version( $info['wordpress']['wp_version'] ),
		);
	}

	// -----------------------------------------------------------------------
	// Individual checks
	// -----------------------------------------------------------------------

	/**
	 * PHP version check.
	 * good: >= 8.0 | warning: >= 7.4 | critical: < 7.4
	 *
	 * @param string $version Installed PHP version.
	 * @return array
	 */
	private static function check_php_version( $version ) {
		if ( version_compare( $version, '8.0', '>=' ) ) {
			$status  = self::STATUS_GOOD;
			$message = __( 'Your PHP version is up to date.', 'siteintelix' );
		} elseif ( version_compare( $version, '7.4', '>=' ) ) {
			$status  = self::STATUS_WARNING;
			$message = __( 'PHP 7.4 is end-of-life. Please upgrade to PHP 8.0 or higher.', 'siteintelix' );
		} else {
			$status  = self::STATUS_CRITICAL;
			$message = __( 'PHP version is critically outdated. Upgrade immediately.', 'siteintelix' );
		}

		return array(
			'status'  => $status,
			'label'   => __( 'PHP Version', 'siteintelix' ),
			'value'   => esc_html( $version ),
			'message' => $message,
		);
	}

	/**
	 * Memory limit check.
	 * good: >= 256 MB or unlimited | warning: >= 128 MB | critical: < 128 MB
	 *
	 * @param int $mb  Memory limit in MB; -1 = unlimited.
	 * @return array
	 */
	private static function check_memory_limit( $mb ) {
		if ( -1 === $mb ) {
			return array(
				'status'  => self::STATUS_GOOD,
				'label'   => __( 'Memory Limit', 'siteintelix' ),
				'value'   => __( 'Unlimited', 'siteintelix' ),
				'message' => __( 'Memory limit is set to unlimited.', 'siteintelix' ),
			);
		}

		if ( $mb >= 256 ) {
			$status  = self::STATUS_GOOD;
			$message = __( 'Memory limit is sufficient.', 'siteintelix' );
		} elseif ( $mb >= SITEINTELIX_MIN_MEMORY_MB ) {
			$status  = self::STATUS_WARNING;
			$message = __( 'Memory limit is below the recommended 256 MB. Consider increasing it.', 'siteintelix' );
		} else {
			$status  = self::STATUS_CRITICAL;
			$message = __( 'Memory limit is critically low. Increase to at least 128 MB.', 'siteintelix' );
		}

		return array(
			'status'  => $status,
			'label'   => __( 'Memory Limit', 'siteintelix' ),
			'value'   => $mb . ' MB',
			'message' => $message,
		);
	}

	/**
	 * WP_DEBUG mode check.
	 * good: debug OFF | warning: debug ON in non-production | critical: debug ON in production
	 *
	 * @param bool   $debug_on    Whether WP_DEBUG is true.
	 * @param string $environment WP_ENVIRONMENT_TYPE value.
	 * @return array
	 */
	private static function check_debug_mode( $debug_on, $environment ) {
		if ( ! $debug_on ) {
			return array(
				'status'  => self::STATUS_GOOD,
				'label'   => __( 'Debug Mode', 'siteintelix' ),
				'value'   => __( 'Disabled', 'siteintelix' ),
				'message' => __( 'Debug mode is disabled. Good for production.', 'siteintelix' ),
			);
		}

		$is_production = ( 'production' === $environment );

		return array(
			'status'  => $is_production ? self::STATUS_CRITICAL : self::STATUS_WARNING,
			'label'   => __( 'Debug Mode', 'siteintelix' ),
			'value'   => __( 'Enabled', 'siteintelix' ),
			'message' => $is_production
				? __( 'WP_DEBUG is ON in a production environment. Disable it immediately.', 'siteintelix' )
				: __( 'WP_DEBUG is enabled. Disable it before going live.', 'siteintelix' ),
		);
	}

	/**
	 * HTTPS check.
	 *
	 * @param bool $is_ssl Whether the request uses HTTPS.
	 * @return array
	 */
	private static function check_https( $is_ssl ) {
		return array(
			'status'  => $is_ssl ? self::STATUS_GOOD : self::STATUS_WARNING,
			'label'   => __( 'HTTPS', 'siteintelix' ),
			'value'   => $is_ssl ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ),
			'message' => $is_ssl
				? __( 'Site is served over HTTPS.', 'siteintelix' )
				: __( 'HTTPS is not active. Enable an SSL certificate.', 'siteintelix' ),
		);
	}

	/**
	 * REST API availability check.
	 *
	 * @param bool $available Whether the REST API returned HTTP 200.
	 * @return array
	 */
	private static function check_rest_api( $available ) {
		if ( null === $available ) {
			return array(
				'status'  => self::STATUS_WARNING,
				'label'   => __( 'REST API', 'siteintelix' ),
				'value'   => __( 'Not checked', 'siteintelix' ),
				'message' => __( 'Open Server Diagnostics to run a fresh REST API check.', 'siteintelix' ),
			);
		}

		return array(
			'status'  => $available ? self::STATUS_GOOD : self::STATUS_CRITICAL,
			'label'   => __( 'REST API', 'siteintelix' ),
			'value'   => $available ? __( 'Accessible', 'siteintelix' ) : __( 'Blocked', 'siteintelix' ),
			'message' => $available
				? __( 'The REST API is accessible.', 'siteintelix' )
				: __( 'The REST API is blocked or unreachable. Many features may be broken.', 'siteintelix' ),
		);
	}

	/**
	 * WP-Cron status check.
	 *
	 * @param bool $enabled Whether WP-Cron is enabled.
	 * @return array
	 */
	private static function check_cron( $enabled ) {
		return array(
			'status'  => $enabled ? self::STATUS_GOOD : self::STATUS_WARNING,
			'label'   => __( 'WP Cron', 'siteintelix' ),
			'value'   => $enabled ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ),
			'message' => $enabled
				? __( 'WP-Cron is running normally.', 'siteintelix' )
				: __( 'WP-Cron is disabled. Scheduled tasks will not run without an external cron job.', 'siteintelix' ),
		);
	}

	/**
	 * MySQL / MariaDB version check.
	 * good: >= 8.0 | warning: >= 5.7 | critical: < 5.7
	 *
	 * @param string $version MySQL version string.
	 * @return array
	 */
	private static function check_mysql_version( $version ) {
		$clean = (string) preg_replace( '/[^0-9.].*/', '', $version );

		if ( version_compare( $clean, '8.0', '>=' ) ) {
			$status  = self::STATUS_GOOD;
			$message = __( 'MySQL / MariaDB version is current.', 'siteintelix' );
		} elseif ( version_compare( $clean, '5.7', '>=' ) ) {
			$status  = self::STATUS_WARNING;
			$message = __( 'MySQL 5.7 is nearing end-of-life. Consider upgrading to 8.0.', 'siteintelix' );
		} else {
			$status  = self::STATUS_CRITICAL;
			$message = __( 'MySQL / MariaDB version is outdated. Upgrade required.', 'siteintelix' );
		}

		return array(
			'status'  => $status,
			'label'   => __( 'MySQL Version', 'siteintelix' ),
			'value'   => esc_html( $version ),
			'message' => $message,
		);
	}

	/**
	 * WordPress version check (uses the core update transient — no HTTP call).
	 *
	 * @param string $installed Installed WordPress version.
	 * @return array
	 */
	private static function check_wp_version( $installed ) {
		$update_data = get_site_transient( 'update_core' );
		$latest      = isset( $update_data->updates[0]->version )
			? $update_data->updates[0]->version
			: $installed;

		if ( version_compare( $installed, $latest, '>=' ) ) {
			$status  = self::STATUS_GOOD;
			$message = __( 'WordPress is up to date.', 'siteintelix' );
		} else {
			$status  = self::STATUS_WARNING;
			$message = sprintf(
				/* translators: %s: latest WordPress version */
				__( 'WordPress %s is available. Please update.', 'siteintelix' ),
				esc_html( $latest )
			);
		}

		return array(
			'status'  => $status,
			'label'   => __( 'WordPress Version', 'siteintelix' ),
			'value'   => esc_html( $installed ),
			'message' => $message,
		);
	}

	// -----------------------------------------------------------------------
	// Utility
	// -----------------------------------------------------------------------

	/**
	 * Derive an overall health status from a full set of check results.
	 *
	 * Returns 'critical' if any check is critical, 'warning' if any is a
	 * warning, otherwise 'good'.
	 *
	 * @param array $checks  Return value of self::run().
	 * @return string  good | warning | critical
	 */
	public static function overall_status( array $checks ) {
		$has_warning = false;

		foreach ( $checks as $check ) {
			if ( self::STATUS_CRITICAL === $check['status'] ) {
				return self::STATUS_CRITICAL;
			}
			if ( self::STATUS_WARNING === $check['status'] ) {
				$has_warning = true;
			}
		}

		return $has_warning ? self::STATUS_WARNING : self::STATUS_GOOD;
	}
}
