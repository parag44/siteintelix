<?php
/**
 * Standalone regression test for complete debug-log parsing.
 */

define( 'ABSPATH', '/Users/joomshaper/Local Sites/server-info/app/public/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) );
}

require dirname( __DIR__ ) . '/includes/class-siteintelix-debug-log.php';

$raw = '[24-Jun-2026 06:41:22 UTC] PHP Deprecated: Using null as an array offset is deprecated, use an empty string instead in /Users/joomshaper/Local Sites/server-info/app/public/wp-includes/functions.php on line 1189';

$method = new ReflectionMethod( 'SITEINTELIX_Debug_Log', 'parse_line' );
$entry = $method->invoke( null, $raw );

if ( $raw !== $entry['raw'] ) {
	fwrite( STDERR, "FAIL: raw entry was not preserved.\n" );
	exit( 1 );
}

if ( false === strpos( $entry['message'], '/Users/joomshaper/Local Sites/server-info/app/public/wp-includes/functions.php on line 1189' ) ) {
	fwrite( STDERR, "FAIL: complete file reference is missing from message.\n" );
	exit( 1 );
}

if ( '/Users/joomshaper/Local Sites/server-info/app/public/wp-includes/functions.php' !== $entry['file'] ) {
	fwrite( STDERR, "FAIL: absolute file path was not preserved.\n" );
	exit( 1 );
}

fwrite( STDOUT, "PASS: complete raw message and absolute file path preserved.\n" );
