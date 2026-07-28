<?php
/**
 * Ordered runtime output for Custom CSS & JS.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Custom_Code_Runner {
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'frontend_header' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'frontend_footer' ), 1 );
		add_action( 'admin_head', array( __CLASS__, 'admin_header' ), 1 );
		add_action( 'admin_footer', array( __CLASS__, 'admin_footer' ), 1 );
	}

	public static function frontend_header() { self::output( 'frontend', 'header' ); }
	public static function frontend_footer() { self::output( 'frontend', 'footer' ); }
	public static function admin_header() { self::output( 'admin', 'header' ); }
	public static function admin_footer() { self::output( 'admin', 'footer' ); }

	public static function output( $scope, $location ) {
		foreach ( array( 'css', 'javascript' ) as $type ) {
			$entries = SITEINTELIX_Custom_Code_Repository::get_enabled_for( $scope, $location, $type );
			foreach ( $entries as $entry ) {
				self::output_entry( $entry );
			}
		}
	}

	public static function output_entry( $entry ) {
		$id   = absint( $entry['id'] );
		$type = 'css' === $entry['code_type'] ? 'css' : 'javascript';
		if ( 'external' === $entry['loading_method'] ) {
			$relative = (string) $entry['generated_file'];
			$path     = SITEINTELIX_Custom_Code_File_Manager::path( $relative );
			if ( '' !== $path && is_readable( $path ) ) {
				$url     = SITEINTELIX_Custom_Code_File_Manager::url( $relative );
				$version = (string) filemtime( $path );
				if ( 'css' === $type ) {
					printf( "\n<link id=\"siteintelix-custom-css-%s\" rel=\"stylesheet\" href=\"%s\">\n", esc_attr( (string) $id ), esc_url( add_query_arg( 'ver', $version, $url ) ) );
				} else {
					printf( "\n<script id=\"siteintelix-custom-js-%s\" src=\"%s\"></script>\n", esc_attr( (string) $id ), esc_url( add_query_arg( 'ver', $version, $url ) ) );
				}
				return;
			}
		}
		$code = SITEINTELIX_Custom_Code_File_Manager::strip_outer_tags( $entry['code'], $type );
		if ( 'css' === $type ) {
			printf( "\n<style id=\"siteintelix-custom-css-%d\">\n%s\n</style>\n", $id, $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Administrators intentionally save executable CSS.
		} else {
			printf( "\n<script id=\"siteintelix-custom-js-%d\">\n%s\n</script>\n", $id, $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Administrators intentionally save executable JavaScript.
		}
	}
}
