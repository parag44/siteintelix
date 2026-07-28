<?php
/**
 * Transient preview deserialization tests.
 *
 * @package SiteIntelix
 */

define( 'ABSPATH', __DIR__ . '/' );

function is_serialized( $value ) {
	if ( ! is_string( $value ) ) {
		return false;
	}

	$value = trim( $value );
	if ( 'N;' === $value ) {
		return true;
	}

	return 1 === preg_match( '/^[aOsibd]:/', $value );
}

function siteintelix_transient_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

class SITEINTELIX_Wakeup_Probe {
	public static $wakeups = 0;

	public function __wakeup() {
		++self::$wakeups;
	}
}

require_once dirname( __DIR__ ) . '/includes/modules/transients-manager/class-siteintelix-transients-manager-module.php';

$reflection = new ReflectionClass( 'SITEINTELIX_Transients_Manager_Module' );
$method     = $reflection->getMethod( 'safe_unserialize_for_preview' );
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true );
}
$serialized = serialize( new SITEINTELIX_Wakeup_Probe() );
$decoded    = $method->invoke( null, $serialized );

siteintelix_transient_assert( 0 === SITEINTELIX_Wakeup_Probe::$wakeups, 'Preview decoding never instantiates serialized objects.' );
siteintelix_transient_assert( is_object( $decoded ) && '__PHP_Incomplete_Class' === get_class( $decoded ), 'Object payload is represented without loading its class.' );
siteintelix_transient_assert( false === $method->invoke( null, 'b:0;' ), 'Serialized false is decoded correctly.' );
siteintelix_transient_assert( 'plain text' === $method->invoke( null, 'plain text' ), 'Plain values are unchanged.' );

fwrite( STDOUT, "Transient preview deserialization tests passed.\n" );
