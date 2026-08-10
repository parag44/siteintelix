<?php
/**
 * User Switcher secure session manager.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, validates, restores, and invalidates switch sessions.
 */
class SITEINTELIX_User_Switcher_Session_Manager {

	const COOKIE_PREFIX          = 'siteintelix_user_switcher_';
	const TRANSIENT_PREFIX       = 'siteintelix_user_switcher_session_';
	const TARGET_INDEX_PREFIX    = 'siteintelix_user_switcher_target_';
	const START_LOCK_PREFIX      = 'siteintelix_user_switcher_lock_';
	const START_LOCK_TTL_SECONDS = 30;

	/**
	 * Trusted session cached for the current request.
	 *
	 * @var array<string,mixed>|false|null
	 */
	private static $active_session = null;

	/**
	 * Register validation and logout cleanup.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_logout', array( __CLASS__, 'handle_logout' ), 1, 1 );
		add_action( 'init', array( __CLASS__, 'validate_request' ), 30 );
	}

	/**
	 * Start an impersonation session.
	 *
	 * @param WP_User $original_user Original operator.
	 * @param WP_User $target_user   Target user.
	 * @param string  $redirect_url  Validated switch redirect.
	 * @param string  $previous_url  Previous admin URL.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function start( $original_user, $target_user, $redirect_url, $previous_url ) {
		if ( ! $original_user instanceof WP_User || ! $target_user instanceof WP_User ) {
			return new WP_Error( 'siteintelix_user_switcher_invalid_users', __( 'The switch request contains an invalid user.', 'siteintelix' ) );
		}
		if ( self::is_switching() ) {
			return new WP_Error( 'siteintelix_user_switcher_chained', __( 'Return to the original account before switching to another user.', 'siteintelix' ) );
		}

		$current_token = wp_get_session_token();
		if ( ! $current_token || ! self::acquire_start_lock( $current_token ) ) {
			return new WP_Error( 'siteintelix_user_switcher_concurrent', __( 'Another user switch is already being started. Refresh the page and try again.', 'siteintelix' ) );
		}

		$duration = absint( SITEINTELIX_User_Switcher_Settings::get_settings()['session_duration'] );
		/** Filter User Switcher session duration in minutes. */
		$duration = absint( apply_filters( 'siteintelix_user_switcher_session_duration', $duration, $original_user, $target_user ) );
		$duration = min( SITEINTELIX_User_Switcher_Settings::MAX_DURATION, max( SITEINTELIX_User_Switcher_Settings::MIN_DURATION, $duration ) );

		$started_at  = time();
		$expires_at  = $started_at + ( $duration * MINUTE_IN_SECONDS );
		$switch_id   = self::random_identifier();
		$secret      = wp_generate_password( 64, false, false );
		$original_id = absint( $original_user->ID );
		$target_id   = absint( $target_user->ID );

		$original_sessions = WP_Session_Tokens::get_instance( $original_id );
		$target_sessions   = WP_Session_Tokens::get_instance( $target_id );
		$restore_token     = $original_sessions->create( $expires_at );
		$target_token      = $target_sessions->create( $expires_at );

		$record = array(
			'switch_id'        => $switch_id,
			'original_user_id' => $original_id,
			'target_user_id'   => $target_id,
			'blog_id'          => get_current_blog_id(),
			'secret_hash'      => self::hash_value( $secret ),
			'restore_hash'     => self::hash_value( $restore_token ),
			'target_hash'      => self::hash_value( $target_token ),
			'target_index_key' => self::target_index_key( $target_token ),
			'started_at'       => $started_at,
			'expires_at'       => $expires_at,
			'previous_url'     => SITEINTELIX_User_Switcher_Settings::sanitize_custom_url( $previous_url ),
			'redirect_url'     => SITEINTELIX_User_Switcher_Settings::sanitize_custom_url( $redirect_url ),
		);

		$record['log_id'] = SITEINTELIX_User_Switcher_Logger::start( $record );
		if ( ! set_transient( self::transient_key( $switch_id ), $record, $duration * MINUTE_IN_SECONDS ) ) {
			$original_sessions->destroy( $restore_token );
			$target_sessions->destroy( $target_token );
			SITEINTELIX_User_Switcher_Logger::finish( $switch_id, 'invalidated' );
			self::release_start_lock( $current_token );
			return new WP_Error( 'siteintelix_user_switcher_storage', __( 'The secure switching session could not be stored.', 'siteintelix' ) );
		}
		if ( ! set_transient( $record['target_index_key'], $switch_id, $duration * MINUTE_IN_SECONDS ) ) {
			delete_transient( self::transient_key( $switch_id ) );
			$original_sessions->destroy( $restore_token );
			$target_sessions->destroy( $target_token );
			SITEINTELIX_User_Switcher_Logger::finish( $switch_id, 'invalidated' );
			self::release_start_lock( $current_token );
			return new WP_Error( 'siteintelix_user_switcher_index', __( 'The secure switching session could not be indexed.', 'siteintelix' ) );
		}

		if ( ! self::set_cookie( $switch_id, $secret, $restore_token, $expires_at ) ) {
			delete_transient( self::transient_key( $switch_id ) );
			delete_transient( $record['target_index_key'] );
			$original_sessions->destroy( $restore_token );
			$target_sessions->destroy( $target_token );
			SITEINTELIX_User_Switcher_Logger::finish( $switch_id, 'invalidated' );
			self::release_start_lock( $current_token );
			return new WP_Error( 'siteintelix_user_switcher_cookie', __( 'The secure switching cookie could not be created.', 'siteintelix' ) );
		}

		/**
		 * Fires immediately before WordPress changes to the target identity.
		 *
		 * @param WP_User $original_user Original operator.
		 * @param WP_User $target_user   Target user.
		 */
		do_action( 'siteintelix_user_switcher_before_switch', $original_user, $target_user );

		if ( $current_token ) {
			$original_sessions->destroy( $current_token );
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $target_id );
		wp_set_auth_cookie( $target_id, true, is_ssl(), $target_token );

		/**
		 * Fires after WordPress changes to the target identity.
		 *
		 * @param int    $original_user_id Original operator ID.
		 * @param int    $target_user_id   Target user ID.
		 * @param string $switch_id       Switch identifier.
		 */
		do_action( 'siteintelix_user_switcher_after_switch', $original_id, $target_id, $switch_id );

		self::release_start_lock( $current_token );
		self::$active_session = null;
		return $record;
	}

	/**
	 * Restore the original operator.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public static function restore() {
		$session = self::read_session( true );
		if ( ! $session ) {
			return new WP_Error( 'siteintelix_user_switcher_invalid_restore', __( 'The switching session is invalid or expired.', 'siteintelix' ) );
		}

		$original_user = get_user_by( 'id', absint( $session['original_user_id'] ) );
		$target_user   = get_user_by( 'id', absint( $session['target_user_id'] ) );
		if ( ! $original_user instanceof WP_User || ! $target_user instanceof WP_User ) {
			self::invalidate( $session, 'invalidated' );
			wp_destroy_current_session();
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
			return new WP_Error( 'siteintelix_user_switcher_missing_user', __( 'The original or target account no longer exists.', 'siteintelix' ) );
		}

		$original_sessions = WP_Session_Tokens::get_instance( $original_user->ID );
		if ( ! $original_sessions->get( $session['restore_token'] ) ) {
			self::invalidate( $session, 'invalidated' );
			wp_destroy_current_session();
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
			return new WP_Error( 'siteintelix_user_switcher_restore_token', __( 'The administrator restoration session is no longer valid.', 'siteintelix' ) );
		}

		/**
		 * Fires before the original operator is restored.
		 *
		 * @param int    $original_user_id Original operator ID.
		 * @param int    $target_user_id   Target user ID.
		 * @param string $switch_id       Switch identifier.
		 */
		do_action( 'siteintelix_user_switcher_before_restore', $original_user->ID, $target_user->ID, $session['switch_id'] );

		$current_target_token = wp_get_session_token();
		if ( $current_target_token ) {
			WP_Session_Tokens::get_instance( $target_user->ID )->destroy( $current_target_token );
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $original_user->ID );
		$expiration_filter = static function () use ( $session ) {
			return max( MINUTE_IN_SECONDS, absint( $session['expires_at'] ) - time() );
		};
		add_filter( 'auth_cookie_expiration', $expiration_filter );
		wp_set_auth_cookie( $original_user->ID, false, is_ssl(), $session['restore_token'] );
		remove_filter( 'auth_cookie_expiration', $expiration_filter );

		delete_transient( self::transient_key( $session['switch_id'] ) );
		delete_transient( $session['target_index_key'] );
		self::clear_cookie();
		SITEINTELIX_User_Switcher_Logger::finish( $session['switch_id'], 'returned' );
		self::$active_session = false;

		/**
		 * Fires after the original operator is restored.
		 *
		 * @param int    $original_user_id Original operator ID.
		 * @param int    $target_user_id   Target user ID.
		 * @param string $switch_id       Switch identifier.
		 */
		do_action( 'siteintelix_user_switcher_after_restore', $original_user->ID, $target_user->ID, $session['switch_id'] );

		return $session;
	}

	/**
	 * Active session for the currently authenticated target.
	 *
	 * @return array<string,mixed>|false
	 */
	public static function get_active_session() {
		if ( null === self::$active_session ) {
			self::$active_session = self::read_session( true );
		}
		return self::$active_session;
	}

	/**
	 * Whether a switching cookie exists.
	 *
	 * @return bool
	 */
	public static function has_cookie() {
		$cookie_name = self::cookie_name();
		return ! empty( $_COOKIE[ $cookie_name ] );
	}

	/**
	 * Whether the current browser identity is already a switch target.
	 *
	 * @return bool
	 */
	public static function is_switching() {
		if ( self::has_cookie() ) {
			return true;
		}
		$token = wp_get_session_token();
		return $token && false !== get_transient( self::target_index_key( $token ) );
	}

	/**
	 * Validate active state on every request.
	 *
	 * @return void
	 */
	public static function validate_request() {
		if ( ! self::has_cookie() ) {
			self::invalidate_indexed_target();
			return;
		}

		$session = self::read_session( false );
		if ( ! $session ) {
			self::invalidate_indexed_target();
			return;
		}

		if ( ! is_user_logged_in() || get_current_user_id() !== absint( $session['target_user_id'] ) ) {
			self::invalidate( $session, 'invalidated' );
		}
	}

	/**
	 * Clear switching state when the impersonated user logs out.
	 *
	 * @param int $user_id Logged-out user ID.
	 * @return void
	 */
	public static function handle_logout( $user_id = 0 ) {
		if ( ! self::has_cookie() ) {
			return;
		}

		$session = self::read_session( false );
		if ( $session && ( ! $user_id || absint( $user_id ) === absint( $session['target_user_id'] ) ) ) {
			self::invalidate( $session, 'logged_out' );
		}
	}

	/**
	 * Parse and validate signed session state.
	 *
	 * @param bool $require_current_target Require target authentication.
	 * @return array<string,mixed>|false
	 */
	private static function read_session( $require_current_target ) {
		if ( ! self::has_cookie() ) {
			return false;
		}

		$cookie = self::parse_cookie();
		if ( ! $cookie ) {
			self::clear_cookie();
			return false;
		}

		$record = get_transient( self::transient_key( $cookie['switch_id'] ) );
		if (
			! is_array( $record )
			|| empty( $record['original_user_id'] )
			|| empty( $record['target_user_id'] )
			|| empty( $record['blog_id'] )
			|| get_current_blog_id() !== absint( $record['blog_id'] )
		) {
			self::clear_cookie();
			return false;
		}

		$session = array_merge(
			$record,
			array(
				'secret'        => $cookie['secret'],
				'restore_token' => $cookie['restore_token'],
				'target_token'  => wp_get_session_token(),
			)
		);

		if (
			empty( $record['secret_hash'] )
			|| empty( $record['restore_hash'] )
			|| empty( $record['target_hash'] )
			|| ! hash_equals( (string) $record['secret_hash'], self::hash_value( $cookie['secret'] ) )
			|| ! hash_equals( (string) $record['restore_hash'], self::hash_value( $cookie['restore_token'] ) )
			|| ( $require_current_target && ! hash_equals( (string) $record['target_hash'], self::hash_value( wp_get_session_token() ) ) )
		) {
			self::invalidate( $session, 'invalidated' );
			return false;
		}

		if ( empty( $record['expires_at'] ) || time() > absint( $record['expires_at'] ) ) {
			self::invalidate( $session, 'expired' );
			return false;
		}

		if ( ! get_user_by( 'id', absint( $record['original_user_id'] ) ) || ! get_user_by( 'id', absint( $record['target_user_id'] ) ) ) {
			self::invalidate( $session, 'invalidated' );
			return false;
		}

		if ( $require_current_target && ( ! is_user_logged_in() || get_current_user_id() !== absint( $record['target_user_id'] ) ) ) {
			return false;
		}

		return $session;
	}

	/**
	 * Invalidate trusted switching state.
	 *
	 * @param array<string,mixed> $session Trusted session.
	 * @param string              $status  Final status.
	 * @return void
	 */
	private static function invalidate( $session, $status ) {
		if ( ! empty( $session['restore_token'] ) && ! empty( $session['original_user_id'] ) ) {
			WP_Session_Tokens::get_instance( absint( $session['original_user_id'] ) )->destroy( $session['restore_token'] );
		}
		if ( ! empty( $session['switch_id'] ) ) {
			delete_transient( self::transient_key( $session['switch_id'] ) );
			SITEINTELIX_User_Switcher_Logger::finish( $session['switch_id'], $status );
		}
		if ( ! empty( $session['target_index_key'] ) ) {
			delete_transient( sanitize_key( $session['target_index_key'] ) );
		}
		self::clear_cookie();
		self::$active_session = false;
	}

	/**
	 * Set a signed switching cookie.
	 *
	 * @param string $switch_id    Switch ID.
	 * @param string $secret       Random session secret.
	 * @param string $restore_token WordPress restoration token.
	 * @param int    $expires_at   Expiration timestamp.
	 * @return bool
	 */
	private static function set_cookie( $switch_id, $secret, $restore_token, $expires_at ) {
		$payload = self::base64url_encode(
			wp_json_encode(
				array(
					'v'       => 1,
					'sid'     => $switch_id,
					'secret'  => $secret,
					'restore' => $restore_token,
				)
			)
		);
		$value   = $payload . '.' . hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
		$success = setcookie(
			self::cookie_name(),
			$value,
			array(
				'expires'  => absint( $expires_at ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		if ( $success ) {
			$_COOKIE[ self::cookie_name() ] = $value;
		}
		return $success;
	}

	/**
	 * Parse and verify the signed cookie.
	 *
	 * @return array{switch_id:string,secret:string,restore_token:string}|false
	 */
	private static function parse_cookie() {
		if ( ! self::has_cookie() ) {
			return false;
		}

		$cookie_name = self::cookie_name();
		if ( ! isset( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );
		if ( strlen( $value ) > 4096 ) {
			return false;
		}

		$parts = explode( '.', $value, 2 );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], wp_salt( 'auth' ) ), $parts[1] ) ) {
			return false;
		}

		$decoded = self::base64url_decode( $parts[0] );
		$data    = $decoded ? json_decode( $decoded, true ) : null;
		if (
			! is_array( $data )
			|| 1 !== absint( isset( $data['v'] ) ? $data['v'] : 0 )
			|| empty( $data['sid'] )
			|| empty( $data['secret'] )
			|| empty( $data['restore'] )
			|| ! preg_match( '/^[a-f0-9]{32}$/', $data['sid'] )
		) {
			return false;
		}

		return array(
			'switch_id'     => sanitize_text_field( $data['sid'] ),
			'secret'        => sanitize_text_field( $data['secret'] ),
			'restore_token' => sanitize_text_field( $data['restore'] ),
		);
	}

	/**
	 * Clear the switching cookie.
	 *
	 * @return void
	 */
	public static function clear_cookie() {
		setcookie(
			self::cookie_name(),
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::cookie_name() ] );
	}

	/**
	 * Hash active credentials before server-side storage.
	 *
	 * @param string $value Credential.
	 * @return string
	 */
	private static function hash_value( $value ) {
		return hash_hmac( 'sha256', (string) $value, wp_salt( 'auth' ) );
	}

	/**
	 * Transient key.
	 *
	 * @param string $switch_id Switch ID.
	 * @return string
	 */
	private static function transient_key( $switch_id ) {
		return self::TRANSIENT_PREFIX . sanitize_key( $switch_id );
	}

	/**
	 * Current site's cookie name.
	 *
	 * @return string
	 */
	private static function cookie_name() {
		return self::COOKIE_PREFIX . get_current_blog_id();
	}

	/**
	 * Target-token index used for chained-switch prevention.
	 *
	 * @param string $token WordPress session token.
	 * @return string
	 */
	private static function target_index_key( $token ) {
		return self::TARGET_INDEX_PREFIX . substr( self::hash_value( $token ), 0, 32 );
	}

	/**
	 * Acquire an atomic, short-lived lock for one source session.
	 *
	 * @param string $token Current WordPress session token.
	 * @return bool
	 */
	private static function acquire_start_lock( $token ) {
		$key      = self::START_LOCK_PREFIX . substr( self::hash_value( $token ), 0, 32 );
		$existing = absint( get_option( $key, 0 ) );
		if ( $existing && time() - $existing > self::START_LOCK_TTL_SECONDS ) {
			delete_option( $key );
		}
		return add_option( $key, time(), '', false );
	}

	/**
	 * Release the source-session start lock.
	 *
	 * @param string $token Current WordPress session token.
	 * @return void
	 */
	private static function release_start_lock( $token ) {
		if ( $token ) {
			delete_option( self::START_LOCK_PREFIX . substr( self::hash_value( $token ), 0, 32 ) );
		}
	}

	/**
	 * End a target session whose signed switching cookie is unavailable.
	 *
	 * @return void
	 */
	private static function invalidate_indexed_target() {
		$target_token = wp_get_session_token();
		$index_key    = $target_token ? self::target_index_key( $target_token ) : '';
		$switch_id    = $index_key ? get_transient( $index_key ) : false;
		if ( ! $switch_id ) {
			return;
		}
		delete_transient( $index_key );
		delete_transient( self::transient_key( $switch_id ) );
		SITEINTELIX_User_Switcher_Logger::finish( $switch_id, 'invalidated' );
		wp_destroy_current_session();
		wp_clear_auth_cookie();
		wp_set_current_user( 0 );
	}

	/**
	 * Cryptographically random switch identifier.
	 *
	 * @return string
	 */
	private static function random_identifier() {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( Exception $exception ) {
			return md5( wp_generate_uuid4() . wp_generate_password( 32, true, true ) );
		}
	}

	/**
	 * URL-safe base64 encode.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function base64url_encode( $value ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding a signed JSON cookie payload, not executable code.
		return rtrim( strtr( base64_encode( (string) $value ), '+/', '-_' ), '=' );
	}

	/**
	 * URL-safe base64 decode.
	 *
	 * @param string $value Value.
	 * @return string|false
	 */
	private static function base64url_decode( $value ) {
		$padding = strlen( $value ) % 4;
		if ( $padding ) {
			$value .= str_repeat( '=', 4 - $padding );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a signed JSON cookie payload, not executable code.
		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}
