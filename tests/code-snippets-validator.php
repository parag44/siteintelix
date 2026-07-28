<?php
/**
 * Dependency-free regression coverage for PHP snippet validation.
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $message, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $message;
}

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

require_once dirname( __DIR__ ) . '/includes/modules/code-snippets/class-siteintelix-snippets-validator.php';

function siteintelix_validator_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

function siteintelix_validator_assert_error( $expected_code, $actual, $message ) {
	$passed = is_wp_error( $actual ) && $expected_code === $actual->get_error_code();
	siteintelix_validator_assert( $passed, $message );
}

$xml_string_code = <<<'PHP'
$loaded = $document->loadHTML(
	'<?xml encoding="utf-8" ?><div id="' . $root_id . '">' . $content . '</div>',
	LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
);
PHP;

siteintelix_validator_assert(
	true === SITEINTELIX_Snippets_Validator::validate( $xml_string_code ),
	'An XML declaration inside a PHP string must be accepted.'
);

$comment_code = <<<'PHP'
/* Documentation may show <?php and ?> examples without changing PHP mode. */
$value = 1;
PHP;

siteintelix_validator_assert(
	true === SITEINTELIX_Snippets_Validator::validate( $comment_code ),
	'PHP-tag examples inside a block comment must be accepted.'
);

siteintelix_validator_assert(
	true === SITEINTELIX_Snippets_Validator::validate( 'function render_template() { ?>Template<?php }' ),
	'A valid mixed PHP and HTML template must be accepted.'
);

$pdf_viewer_fixture = file_get_contents( __DIR__ . '/fixtures/tutor-branded-pdf-viewer.php.txt' );
siteintelix_validator_assert( false !== $pdf_viewer_fixture, 'The complete Tutor PDF viewer regression fixture must be readable.' );
siteintelix_validator_assert(
	true === SITEINTELIX_Snippets_Validator::validate( $pdf_viewer_fixture ),
	'The complete Tutor PDF viewer snippet must be accepted unchanged.'
);

siteintelix_validator_assert_error(
	'siteintelix_snippet_parse',
	SITEINTELIX_Snippets_Validator::validate( 'function broken_template() { ?>Template<?php if (' ),
	'Malformed mixed PHP and HTML must return a native parse error.'
);

siteintelix_validator_assert_error(
	'siteintelix_snippet_parse',
	SITEINTELIX_Snippets_Validator::validate( '$broken = ;' ),
	'Invalid tagless PHP must return a native parse error.'
);

siteintelix_validator_assert_error(
	'siteintelix_snippet_halt',
	SITEINTELIX_Snippets_Validator::validate( '__halt_compiler();' ),
	'__halt_compiler must remain prohibited.'
);

echo "Code Snippets validator tests passed.\n";
