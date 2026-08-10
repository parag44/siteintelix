<?php
/**
 * Snippet failure recovery.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Recovery {
	private static $current_id = 0;
	private static $registered = false;

	public static function register() {
		if ( ! self::$registered ) {
			register_shutdown_function( array( __CLASS__, 'shutdown' ) );
			self::$registered = true;
		}
	}

	public static function set_current( $id ) { self::$current_id = absint( $id ); }
	public static function clear_current() { self::$current_id = 0; }

	public static function handle( $id, $error ) {
		SITEINTELIX_Snippets_Repository::record_error( $id, $error );
		set_transient( 'siteintelix_snippet_recovery_notice', absint( $id ), DAY_IN_SECONDS );
	}

	public static function shutdown() {
		if ( ! self::$current_id ) return;
		$error = error_get_last();
		if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			self::handle( self::$current_id, new ErrorException( $error['message'], 0, $error['type'], $error['file'], $error['line'] ) );
		}
	}
}
