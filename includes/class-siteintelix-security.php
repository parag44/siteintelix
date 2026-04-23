<?php
/**
 * SITEINTELIX_Security — modular security hardening toggles.
 *
 * Each feature is stored as a separate option and hooked into WordPress
 * at the appropriate point. The feature list is data-driven so the UI
 * and storage are automatically in sync.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Security
 */
class SITEINTELIX_Security {
	/**
	 * Max failed login attempts before temporary lockout.
	 *
	 * @var int
	 */
	const LOGIN_MAX_ATTEMPTS = 5;

	/**
	 * Lockout window in seconds for failed login protection.
	 *
	 * @var int
	 */
	const LOGIN_LOCK_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * Return the feature definition list.
	 *
	 * Each entry:
	 *   id          (string) — unique key, also used as the checkbox name.
	 *   title       (string) — human-readable name.
	 *   description (string) — one-line explanation.
	 *   option_key  (string) — wp_options key.
	 *   default     (bool)   — default state.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_features() {
		return array(
			array(
				'id'          => 'xmlrpc',
				'title'       => __( 'Disable XML-RPC', 'siteintelix' ),
				'description' => __( 'Blocks the XML-RPC interface commonly targeted by brute-force and DDoS attacks.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_xmlrpc',
				'default'     => false,
			),
			array(
				'id'          => 'hide_version',
				'title'       => __( 'Hide WordPress Version', 'siteintelix' ),
				'description' => __( 'Removes the WordPress version meta tag from the site front-end.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_hide_version',
				'default'     => false,
			),
			array(
				'id'          => 'disable_file_edit',
				'title'       => __( 'Disable File Editing', 'siteintelix' ),
				'description' => __( 'Adds DISALLOW_FILE_EDIT in wp-config.php (only if not already defined) to disable Theme/Plugin editors.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_disable_file_edit',
				'default'     => false,
			),
			array(
				'id'          => 'rest_auth',
				'title'       => __( 'Disable REST API for Guests', 'siteintelix' ),
				'description' => __( 'Blocks REST API requests for non-logged-in users while keeping it available for authenticated users.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_rest_auth',
				'default'     => false,
			),
			array(
				'id'          => 'remove_head_links',
				'title'       => __( 'Remove Legacy Head Links', 'siteintelix' ),
				'description' => __( 'Removes rsd_link and wlwmanifest_link from wp_head output.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_remove_head_links',
				'default'     => false,
			),
			array(
				'id'          => 'login_limit',
				'title'       => __( 'Basic Login Attempt Protection', 'siteintelix' ),
				'description' => __( 'Temporarily blocks repeated failed login attempts per IP using WordPress transients.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_login_limit',
				'default'     => false,
			),
		);
	}

	/**
	 * Bootstrap all active security hooks.
	 *
	 * Called on plugins_loaded.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			if ( ! get_option( $feature['option_key'], $feature['default'] ) ) {
				continue;
			}

			switch ( $feature['id'] ) {
				case 'xmlrpc':
					add_filter( 'xmlrpc_enabled', '__return_false' );
					add_filter( 'wp_headers', array( __CLASS__, 'remove_xmlrpc_header' ) );
					break;

				case 'hide_version':
					remove_action( 'wp_head', 'wp_generator' );
					add_filter( 'the_generator', '__return_empty_string' );
					break;

				case 'disable_file_edit':
					// Runtime fallback for this request only. Persistent value is managed in wp-config.php.
					if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
						define( 'DISALLOW_FILE_EDIT', true );
					}
					break;

				case 'rest_auth':
					add_filter( 'rest_authentication_errors', array( __CLASS__, 'restrict_rest_for_guests' ) );
					break;

				case 'remove_head_links':
					remove_action( 'wp_head', 'rsd_link' );
					remove_action( 'wp_head', 'wlwmanifest_link' );
					break;

				case 'login_limit':
					add_filter( 'authenticate', array( __CLASS__, 'block_bruteforce_login' ), 30, 3 );
					add_action( 'wp_login_failed', array( __CLASS__, 'track_failed_login' ) );
					add_action( 'wp_login', array( __CLASS__, 'clear_failed_login' ), 10, 2 );
					break;
			}
		}
	}

	/**
	 * Remove the X-Pingback HTTP header when XML-RPC is disabled.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public static function remove_xmlrpc_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Save security settings from form POST data.
	 *
	 * @param array $posted Raw $_POST data (already nonce-verified by caller).
	 * @return void
	 */
	public static function save( $posted ) {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			$key     = $feature['option_key'];
			$enabled = isset( $posted[ $feature['id'] ] ) && '1' === sanitize_text_field( wp_unslash( $posted[ $feature['id'] ] ) );
			update_option( $key, $enabled ? 1 : 0 );
		}

		if ( self::is_enabled( 'disable_file_edit' ) ) {
			self::ensure_disallow_file_edit_in_wp_config();
		}
	}

	/**
	 * Check whether a specific feature is currently enabled.
	 *
	 * @param string $feature_id Feature ID.
	 * @return bool
	 */
	public static function is_enabled( $feature_id ) {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			if ( $feature['id'] === $feature_id ) {
				return (bool) get_option( $feature['option_key'], $feature['default'] );
			}
		}

		return false;
	}

	/**
	 * Block REST API for non-logged users when enabled.
	 *
	 * @param mixed $result Existing auth result.
	 * @return mixed
	 */
	public static function restrict_rest_for_guests( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		return new WP_Error(
			'siteintelix_rest_forbidden',
			__( 'REST API is restricted to authenticated users.', 'siteintelix' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Track failed login attempts by visitor IP.
	 *
	 * @return void
	 */
	public static function track_failed_login( $username = '' ) {
		unset( $username );
		$ip = self::get_request_ip();
		if ( '' === $ip ) {
			return;
		}

		$key      = self::get_login_key( $ip );
		$attempts = (int) get_transient( $key );
		$attempts++;

		set_transient( $key, $attempts, self::LOGIN_LOCK_SECONDS );
	}

	/**
	 * Remove failed login counter after successful auth.
	 *
	 * @return void
	 */
	public static function clear_failed_login( $user_login = '', $user = null ) {
		unset( $user_login, $user );
		$ip = self::get_request_ip();
		if ( '' === $ip ) {
			return;
		}

		delete_transient( self::get_login_key( $ip ) );
	}

	/**
	 * Block login attempt if IP exceeded allowed failures.
	 *
	 * @param WP_User|WP_Error|null $user     User or error.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_bruteforce_login( $user, $username, $password ) {
		unset( $username, $password );
		$ip = self::get_request_ip();
		if ( '' === $ip ) {
			return $user;
		}

		$attempts = (int) get_transient( self::get_login_key( $ip ) );
		if ( $attempts < self::LOGIN_MAX_ATTEMPTS ) {
			return $user;
		}

		return new WP_Error(
			'siteintelix_login_locked',
			__( 'Too many failed login attempts. Please try again later.', 'siteintelix' )
		);
	}

	/**
	 * Build transient key for login protection.
	 *
	 * @param string $ip Visitor IP.
	 * @return string
	 */
	private static function get_login_key( $ip ) {
		return 'siteintelix_login_attempts_' . md5( $ip );
	}

	/**
	 * Resolve request IP (simple and lightweight).
	 *
	 * @return string
	 */
	private static function get_request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return is_string( $ip ) ? $ip : '';
	}

	/**
	 * Ensure DISALLOW_FILE_EDIT exists in wp-config.php only when not already defined.
	 *
	 * @return void
	 */
	private static function ensure_disallow_file_edit_in_wp_config() {
		SITEINTELIX_WP_Config::define_if_missing( 'DISALLOW_FILE_EDIT', true );
	}
}
