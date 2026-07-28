<?php
/**
 * Syntax policy for PHP snippets.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Validator {
	public static function validate( $code ) {
		$source = "<?php\n" . (string) $code;

		try {
			$tokens = token_get_all( $source, TOKEN_PARSE );
		} catch ( ParseError $error ) {
			return new WP_Error( 'siteintelix_snippet_parse', self::bounded_parse_message( $error ) );
		}

		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && T_HALT_COMPILER === $token[0] ) {
				return new WP_Error( 'siteintelix_snippet_halt', __( '__halt_compiler is not allowed.', 'siteintelix' ) );
			}
		}

		return true;
	}

	public static function bounded_parse_message( $error ) {
		$message = preg_replace( '/\s+/', ' ', $error->getMessage() );
		return function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 300 ) : substr( $message, 0, 300 );
	}
}
