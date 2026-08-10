<?php
/**
 * Standalone request-policy tests for Code Snippets.
 *
 * Run with: php tests/code-snippets-context.test.php
 */

define( 'ABSPATH', __DIR__ . '/../' );

$siteintelix_test_is_admin = true;

function is_admin() {
	global $siteintelix_test_is_admin;
	return $siteintelix_test_is_admin;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return (string) $value;
}

function wp_unslash( $value ) {
	return $value;
}

class SITEINTELIX_Security {
	public static function can_manage_code() {
		return true;
	}
}

require_once ABSPATH . 'includes/modules/code-snippets/class-siteintelix-snippets-context.php';

function siteintelix_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function siteintelix_set_request( $page = '', $action = '' ) {
	$_GET     = array();
	$_REQUEST = array();
	if ( '' !== $page ) {
		$_GET['page']     = $page;
		$_REQUEST['page'] = $page;
	}
	if ( '' !== $action ) {
		$_REQUEST['action'] = $action;
	}
}

siteintelix_set_request( 'siteintelix-debug-log' );
siteintelix_assert_same( true, SITEINTELIX_Snippets_Context::is_management_request(), 'Debug Log must remain reachable when an active snippet is broken.' );
siteintelix_assert_same( true, SITEINTELIX_Snippets_Context::should_skip_all(), 'Snippets must not run on the Debug Log page.' );

siteintelix_set_request( 'siteintelix' );
siteintelix_assert_same( false, SITEINTELIX_Snippets_Context::is_management_request(), 'The SiteIntelix overview must still run matching snippets so errors are captured.' );

siteintelix_set_request( 'siteintelix-code-snippets-new' );
siteintelix_assert_same( true, SITEINTELIX_Snippets_Context::is_management_request(), 'Code Snippets management pages must remain protected.' );

siteintelix_set_request( 'siteintelix-tools' );
siteintelix_assert_same( false, SITEINTELIX_Snippets_Context::is_management_request(), 'Non-recovery SiteIntelix pages must still run matching snippets.' );

siteintelix_set_request( 'unrelated-admin-page' );
siteintelix_assert_same( false, SITEINTELIX_Snippets_Context::is_management_request(), 'Unrelated admin pages must still run matching snippets.' );

siteintelix_set_request( '', 'siteintelix_snippet_save' );
siteintelix_assert_same( true, SITEINTELIX_Snippets_Context::is_management_request(), 'SiteIntelix actions must remain protected.' );

$siteintelix_test_is_admin = false;
siteintelix_set_request( 'siteintelix-debug-log' );
siteintelix_assert_same( false, SITEINTELIX_Snippets_Context::is_management_request(), 'A forged frontend page query must not be treated as an admin management request.' );

fwrite( STDOUT, "Code Snippets context tests passed.\n" );
