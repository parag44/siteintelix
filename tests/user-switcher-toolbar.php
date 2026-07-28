<?php
/**
 * Dependency-free toolbar visibility tests for User Switcher.
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_test_filters = array();

function add_action() {
	return true;
}

function add_filter( $hook, $callback, $priority = 10 ) {
	global $siteintelix_test_filters;
	$siteintelix_test_filters[ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

class SITEINTELIX_User_Switcher_Session_Manager {
	public static $active_session = false;

	public static function get_active_session() {
		return self::$active_session;
	}
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-toolbar.php';

/**
 * Fail the script with a useful message.
 *
 * @param bool   $condition Expected truthiness.
 * @param string $message   Failure message.
 * @return void
 */
function siteintelix_toolbar_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

SITEINTELIX_User_Switcher_Toolbar::init();

siteintelix_toolbar_test_assert(
	isset( $siteintelix_test_filters['show_admin_bar'] ),
	'The toolbar must register a show_admin_bar filter.'
);
siteintelix_toolbar_test_assert(
	PHP_INT_MAX === $siteintelix_test_filters['show_admin_bar']['priority'],
	'The visibility filter must run at the latest practical priority.'
);

SITEINTELIX_User_Switcher_Session_Manager::$active_session = false;
siteintelix_toolbar_test_assert(
	false === SITEINTELIX_User_Switcher_Toolbar::force_admin_bar( false ),
	'Ordinary hidden-toolbar preferences must remain hidden.'
);

SITEINTELIX_User_Switcher_Session_Manager::$active_session = array( 'switch_id' => 'active' );
siteintelix_toolbar_test_assert(
	true === SITEINTELIX_User_Switcher_Toolbar::force_admin_bar( false ),
	'An active switch must force the toolbar to show.'
);

fwrite( STDOUT, "User Switcher toolbar tests passed.\n" );
