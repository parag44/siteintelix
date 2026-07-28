<?php
/**
 * Dependency-free regression coverage for PHP namespace preservation.
 */

define( 'ABSPATH', __DIR__ . '/' );

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}
function sanitize_textarea_field( $value ) {
	return trim( (string) $value );
}
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}

require_once dirname( __DIR__ ) . '/includes/modules/code-snippets/class-siteintelix-snippets-repository.php';

function siteintelix_test_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$message}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
}

$canonical_code = "add_filter( 'tutor_dashboard/nav_items_all', 'callback' );\n\\TUTOR\\Icon::RIGHT_ARROW_UP;";
$request        = array(
	'name'        => 'Tutor namespaced icon',
	'code'        => addslashes( $canonical_code ),
	'description' => '',
	'scope'       => 'frontend',
	'priority'    => '10',
	'status'      => 'active',
	'tags'        => 'tutor',
);

$normalized = SITEINTELIX_Snippets_Repository::normalize_request( $request );
siteintelix_test_assert_same( $canonical_code, $normalized['code'], 'Raw request code must be unslashed exactly once.' );

$prepared = SITEINTELIX_Snippets_Repository::prepare_entry( $normalized );
siteintelix_test_assert_same( $canonical_code, $prepared['code'], 'Canonical repository data must preserve namespace separators.' );

$metadata_update = SITEINTELIX_Snippets_Repository::prepare_entry(
	array( 'name' => 'Renamed' ),
	array_merge( $prepared, array( 'name' => 'Original' ) )
);
siteintelix_test_assert_same( $canonical_code, $metadata_update['code'], 'Metadata-only updates must preserve stored code.' );

echo "Code Snippets normalization tests passed.\n";
