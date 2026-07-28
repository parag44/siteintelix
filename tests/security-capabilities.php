<?php
/**
 * Capability policy tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_test_multisite    = false;
$siteintelix_test_super_admin  = false;
$siteintelix_test_capabilities = array();

function is_multisite() {
	global $siteintelix_test_multisite;
	return $siteintelix_test_multisite;
}

function is_super_admin() {
	global $siteintelix_test_super_admin;
	return $siteintelix_test_super_admin;
}

function current_user_can( $capability ) {
	global $siteintelix_test_capabilities;
	return in_array( $capability, $siteintelix_test_capabilities, true );
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function siteintelix_security_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-siteintelix-security.php';

$cases = array(
	array( false, false, array(), false, false, false ),
	array( false, false, array( 'manage_options' ), true, false, true ),
	array( false, false, array( 'manage_options', 'unfiltered_html' ), true, true, true ),
	array( true, false, array( 'manage_options', 'unfiltered_html' ), true, false, false ),
	array( true, true, array( 'manage_options', 'unfiltered_html' ), true, true, true ),
);

foreach ( $cases as $case ) {
	list( $siteintelix_test_multisite, $siteintelix_test_super_admin, $siteintelix_test_capabilities, $manage, $code, $global ) = $case;

	siteintelix_security_assert( $manage === SITEINTELIX_Security::can_manage(), 'Ordinary management policy mismatch.' );
	siteintelix_security_assert( $code === SITEINTELIX_Security::can_manage_code(), 'Executable-code policy mismatch.' );
	siteintelix_security_assert( $global === SITEINTELIX_Security::can_manage_global_tools(), 'Global-tools policy mismatch.' );
}

$siteintelix_test_multisite    = true;
$siteintelix_test_super_admin  = false;
$siteintelix_test_capabilities = array( 'manage_options', 'unfiltered_html' );

siteintelix_security_assert( SITEINTELIX_Security::can_manage_module( 'smtp' ), 'SMTP uses ordinary site administration.' );
siteintelix_security_assert( ! SITEINTELIX_Security::can_manage_module( 'code_snippets' ), 'Code Snippets requires the code policy.' );
siteintelix_security_assert( ! SITEINTELIX_Security::can_manage_module( 'custom_code' ), 'Custom code requires the code policy.' );
siteintelix_security_assert( ! SITEINTELIX_Security::can_manage_module( 'database_manager' ), 'Database Manager requires the global-tools policy.' );
siteintelix_security_assert( ! SITEINTELIX_Security::can_manage_module( 'debug_log' ), 'Debug configuration requires the global-tools policy.' );

fwrite( STDOUT, "Capability policy tests passed.\n" );
