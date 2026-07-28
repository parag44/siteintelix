<?php
/**
 * Dependency-free permission policy tests for User Switcher.
 */

define( 'ABSPATH', __DIR__ . '/' );

class WP_User {
	public $ID;
	public $roles;
	public $caps;

	public function __construct( $id, $roles, $caps = array() ) {
		$this->ID    = $id;
		$this->roles = $roles;
		$this->caps  = $caps;
	}
}

class SITEINTELIX_User_Switcher_Settings {
	const MANAGED_ROLES_OPTION = 'siteintelix_user_switcher_managed_roles';

	public static $settings = array(
		'allow_administrators' => 0,
		'allowed_target_roles' => array( 'subscriber', 'editor' ),
	);

	public static function get_settings() {
		return self::$settings;
	}
}

$siteintelix_test_users = array();
$siteintelix_test_multisite = false;
$siteintelix_test_members = array();
$siteintelix_test_super_admins = array();
$siteintelix_test_filters = array();

function get_current_user_id() {
	return 1;
}

function get_user_by( $field, $id ) {
	global $siteintelix_test_users;
	return isset( $siteintelix_test_users[ $id ] ) ? $siteintelix_test_users[ $id ] : false;
}

function user_can( $user, $capability ) {
	return ! empty( $user->caps[ $capability ] );
}

function is_multisite() {
	global $siteintelix_test_multisite;
	return $siteintelix_test_multisite;
}

function is_user_member_of_blog( $user_id, $blog_id ) {
	global $siteintelix_test_members;
	return in_array( $user_id, $siteintelix_test_members, true );
}

function get_current_blog_id() {
	return 1;
}

function is_super_admin( $user_id ) {
	global $siteintelix_test_super_admins;
	return in_array( $user_id, $siteintelix_test_super_admins, true );
}

function apply_filters( $hook, $value ) {
	global $siteintelix_test_filters;
	return isset( $siteintelix_test_filters[ $hook ] ) ? $siteintelix_test_filters[ $hook ] : $value;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\\-]/', '', strtolower( $value ) );
}

function get_option( $name, $default = false ) {
	return $default;
}

function update_option() {
	return true;
}

function get_role() {
	return false;
}

require dirname( __DIR__ ) . '/includes/modules/user-switcher/class-siteintelix-user-switcher-permissions.php';

/**
 * Fail the script with a useful message.
 *
 * @param bool   $condition Expected truthiness.
 * @param string $message   Failure message.
 * @return void
 */
function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$operator = new WP_User( 1, array( 'administrator' ), array( 'siteintelix_switch_users' => true ) );
$subscriber = new WP_User( 2, array( 'subscriber' ) );
$administrator = new WP_User( 3, array( 'administrator' ) );
$unauthorized = new WP_User( 4, array( 'editor' ) );
$no_role = new WP_User( 5, array() );
$stale_operator = new WP_User( 6, array( 'administrator' ), array( 'siteintelix_switch_users' => true ) );
$siteintelix_test_users = array(
	1 => $operator,
	2 => $subscriber,
	3 => $administrator,
	4 => $unauthorized,
	5 => $no_role,
	6 => $stale_operator,
);

siteintelix_test_assert( SITEINTELIX_User_Switcher_Permissions::can_switch_to( $subscriber, $operator ), 'An authorised administrator should switch to an allowed subscriber.' );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $subscriber, $unauthorized ), 'An operator without the dedicated capability must be blocked.' );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $operator, $operator ), 'An operator must not switch to themselves.' );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $administrator, $operator ), 'Administrator targets must be blocked by default.' );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $no_role, $operator ), 'Accounts without a role must be blocked.' );

SITEINTELIX_User_Switcher_Settings::$settings['allow_administrators'] = 1;
siteintelix_test_assert( SITEINTELIX_User_Switcher_Permissions::can_switch_to( $administrator, $operator ), 'An administrator operator should reach an administrator only when explicitly enabled.' );

$siteintelix_test_multisite = true;
$siteintelix_test_members = array( 1, 2, 3 );
$siteintelix_test_super_admins = array( 3 );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $administrator, $operator ), 'A multisite super administrator target must remain protected by default.' );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $subscriber, $stale_operator ), 'A non-super-admin operator outside the current multisite site must be blocked.' );

$siteintelix_test_members = array( 1, 3 );
siteintelix_test_assert( ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $subscriber, $operator ), 'A target outside the current multisite site must be blocked.' );

fwrite( STDOUT, "User Switcher permission tests passed.\n" );
