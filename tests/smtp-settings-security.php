<?php
/**
 * SMTP settings security tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

$siteintelix_smtp_add_option = array();

function get_option( $name, $default = false ) {
	unset( $name );
	return $default;
}

function add_option( $name, $value, $deprecated = '', $autoload = null ) {
	global $siteintelix_smtp_add_option;
	$siteintelix_smtp_add_option = compact( 'name', 'value', 'deprecated', 'autoload' );
	return true;
}

function get_bloginfo( $field ) {
	unset( $field );
	return 'Example Site';
}

function wp_specialchars_decode( $value, $flags = ENT_QUOTES ) {
	return html_entity_decode( $value, $flags );
}

function siteintelix_smtp_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/smtp/class-siteintelix-smtp-module.php';

SITEINTELIX_SMTP_Module::activate();
siteintelix_smtp_assert( false === $siteintelix_smtp_add_option['autoload'], 'New SMTP credentials are not autoloaded.' );

$password = ' leading space !@#$%^&*() trailing space ';
$clean    = SITEINTELIX_SMTP_Module::sanitize_password( $password );
siteintelix_smtp_assert( $password === $clean, 'Valid password characters and spaces are preserved.' );
siteintelix_smtp_assert( 'nulremoved' === SITEINTELIX_SMTP_Module::sanitize_password( "nul\0removed" ), 'NUL bytes are removed.' );
siteintelix_smtp_assert( 1024 === strlen( SITEINTELIX_SMTP_Module::sanitize_password( str_repeat( 'a', 2048 ) ) ), 'Passwords are bounded.' );
siteintelix_smtp_assert( '' === SITEINTELIX_SMTP_Module::sanitize_password( array( 'invalid' ) ), 'Non-scalar password input is rejected.' );

$source = file_get_contents( dirname( __DIR__ ) . '/includes/modules/smtp/class-siteintelix-smtp-module.php' );
siteintelix_smtp_assert(
	1 === preg_match( '/update_option\\(\\s*self::SETTINGS_OPTION,\\s*\\$settings,\\s*false\\s*\\)/', $source ),
	'SMTP save explicitly disables option autoloading.'
);

fwrite( STDOUT, "SMTP settings security tests passed.\n" );
