<?php
/**
 * Code Snippets request-context security tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_context_is_admin = false;

function is_admin() {
	global $siteintelix_context_is_admin;
	return $siteintelix_context_is_admin;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

class SITEINTELIX_Security {
	public static function can_manage_code() {
		return false;
	}
}

function siteintelix_context_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/code-snippets/class-siteintelix-snippets-context.php';

$_REQUEST = array( 'action' => 'siteintelix_public_bypass' );
siteintelix_context_assert(
	! SITEINTELIX_Snippets_Context::is_management_request(),
	'Public query parameters must not disable active snippets.'
);

$siteintelix_context_is_admin = true;
siteintelix_context_assert(
	SITEINTELIX_Snippets_Context::is_management_request(),
	'SiteIntelix admin actions must skip active snippets.'
);

$_REQUEST = array( 'page' => 'siteintelix-code-snippets-new' );
siteintelix_context_assert(
	SITEINTELIX_Snippets_Context::is_management_request(),
	'Code Snippets admin pages must skip active snippets.'
);

fwrite( STDOUT, "Code Snippets context tests passed.\n" );
