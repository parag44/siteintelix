<?php
/**
 * Bounded snippet import/export helpers.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Transfer {
	const MAX_BYTES = 1048576;
	const MAX_ITEMS = 100;

	public static function export_data( $items ) {
		return array_map( static function ( $item ) {
			return array_intersect_key( $item, array_flip( array( 'name', 'code', 'description', 'scope', 'priority', 'tags' ) ) );
		}, array_slice( (array) $items, 0, self::MAX_ITEMS ) );
	}

	public static function export_php( $items ) {
		$output = "<?php\n/** SiteIntelix snippet reference export. Review before use. */\n\n";
		foreach ( self::export_data( $items ) as $item ) {
			$output .= '/** ' . str_replace( '*/', '* /', $item['name'] ?? 'Snippet' ) . " */\n";
			$output .= (string) ( $item['code'] ?? '' ) . "\n\n";
		}
		return $output;
	}

	public static function decode_import( $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) return new WP_Error( 'siteintelix_snippets_import_size', __( 'Import file is too large.', 'siteintelix' ) );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || count( $data ) > self::MAX_ITEMS ) return new WP_Error( 'siteintelix_snippets_import_format', __( 'Invalid or oversized snippet import.', 'siteintelix' ) );
		$clean = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) continue;
			$item = array_intersect_key( $item, array_flip( array( 'name', 'code', 'description', 'scope', 'priority', 'tags' ) ) );
			$valid = SITEINTELIX_Snippets_Validator::validate( $item['code'] ?? '' );
			if ( is_wp_error( $valid ) ) return $valid;
			$item['status'] = 'inactive';
			$clean[] = $item;
		}
		return $clean;
	}
}
