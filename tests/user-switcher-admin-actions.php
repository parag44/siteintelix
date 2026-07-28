<?php
/**
 * Dependency-free recovery action tests for User Switcher.
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_test_actions   = array();
$siteintelix_dispatched     = array();
$siteintelix_test_logged_in = true;

function add_action( $hook, $callback, $priority = 10 ) {
	global $siteintelix_test_actions;
	$siteintelix_test_actions[ $hook ] = array(
		'callback' => $callback,
		'priority' => $priority,
	);
	return true;
}

function do_action( $hook ) {
	global $siteintelix_dispatched;
	$siteintelix_dispatched[] = $hook;
}

function is_user_logged_in() {
	global $siteintelix_test_logged_in;
	return $siteintelix_test_logged_in;
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-admin-actions.php';

/**
 * Fail the script with a useful message.
 *
 * @param bool   $condition Expected truthiness.
 * @param string $message   Failure message.
 * @return void
 */
function siteintelix_admin_actions_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

SITEINTELIX_User_Switcher_Admin_Actions::init_recovery();

siteintelix_admin_actions_test_assert(
	isset( $siteintelix_test_actions['admin_init'] )
	&& 0 === $siteintelix_test_actions['admin_init']['priority'],
	'Restore recovery must run before default-priority admin restrictions.'
);

$pagenow            = 'index.php';
$_REQUEST['action'] = SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Ordinary wp-admin requests must be ignored.' );

$pagenow            = 'admin-post.php';
$_REQUEST['action'] = 'unrelated_action';
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Unrelated admin-post actions must be ignored.' );

$siteintelix_test_logged_in = false;
$_REQUEST['action']         = SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert( array() === $siteintelix_dispatched, 'Logged-out requests must not dispatch the authenticated restore action.' );

$siteintelix_test_logged_in = true;
SITEINTELIX_User_Switcher_Admin_Actions::maybe_dispatch_restore();
siteintelix_admin_actions_test_assert(
	array( 'admin_post_' . SITEINTELIX_User_Switcher_Admin_Actions::RESTORE_ACTION ) === $siteintelix_dispatched,
	'The authenticated SiteIntelix restore request must dispatch early.'
);

fwrite( STDOUT, "User Switcher admin action tests passed.\n" );
