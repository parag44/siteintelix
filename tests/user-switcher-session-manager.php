<?php
/**
 * Dependency-free session lifecycle tests for User Switcher.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'COOKIEPATH', '/' );
define( 'COOKIE_DOMAIN', '' );

function get_current_blog_id() {
	return 1;
}

function is_ssl() {
	return false;
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-session-manager.php';

echo "Session manager headers started.\n";
if ( ! headers_sent() ) {
	fwrite( STDERR, "FAIL: Test setup did not mark headers as sent.\n" );
	exit( 1 );
}

set_error_handler(
	static function ( $severity, $message ) {
		if ( E_WARNING === $severity && false !== strpos( $message, 'Cannot modify header information' ) ) {
			throw new RuntimeException( $message );
		}
		return false;
	}
);

try {
	$session = SITEINTELIX_User_Switcher_Session_Manager::get_active_session();
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, "FAIL: {$exception->getMessage()}\n" );
	exit( 1 );
} finally {
	restore_error_handler();
}

if ( false !== $session ) {
	fwrite( STDERR, "FAIL: No cookie should produce no active session.\n" );
	exit( 1 );
}

fwrite( STDOUT, "User Switcher session manager tests passed.\n" );
