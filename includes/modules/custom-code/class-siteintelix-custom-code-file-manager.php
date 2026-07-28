<?php
/**
 * Generated-file cache for Custom CSS & JS.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Custom_Code_File_Manager {
	public static function strip_outer_tags( $code, $type ) {
		$tag = 'css' === $type ? 'style' : 'script';
		return preg_replace( '#^\s*<' . $tag . '\b[^>]*>(.*)</' . $tag . '>\s*$#is', '$1', (string) $code );
	}

	public static function relative_filename( $entry_id, $type, $blog_id = null ) {
		$extension = 'css' === $type ? 'css' : 'js';
		return 'siteintelix/custom-code/site-' . absint( null === $blog_id ? get_current_blog_id() : $blog_id ) . '-entry-' . absint( $entry_id ) . '.' . $extension;
	}

	public static function is_managed_file( $relative_file ) {
		if ( ! is_string( $relative_file ) ) {
			return false;
		}

		$relative_file = wp_normalize_path( ltrim( $relative_file, '/' ) );

		return 1 === preg_match(
			'#^siteintelix/custom-code/site-[1-9][0-9]*-entry-[1-9][0-9]*\.(?:css|js)$#',
			$relative_file
		);
	}

	public static function is_managed_file_for_entry( $relative_file, $entry_id, $type = null, $blog_id = null ) {
		if ( ! self::is_managed_file( $relative_file ) ) {
			return false;
		}

		$relative_file = wp_normalize_path( ltrim( $relative_file, '/' ) );
		if ( null !== $type ) {
			return hash_equals( self::relative_filename( $entry_id, $type, $blog_id ), $relative_file );
		}

		$site_id = absint( null === $blog_id ? get_current_blog_id() : $blog_id );
		$entry_id = absint( $entry_id );
		return 1 === preg_match(
			'#^siteintelix/custom-code/site-' . $site_id . '-entry-' . $entry_id . '\.(?:css|js)$#',
			$relative_file
		);
	}

	public static function path( $relative_file ) {
		if ( ! self::is_managed_file( $relative_file ) ) {
			return '';
		}

		$uploads     = wp_upload_dir();
		$base        = rtrim( wp_normalize_path( $uploads['basedir'] ), '/' );
		$managed_dir = $base . '/siteintelix/custom-code/';
		$path        = $base . '/' . wp_normalize_path( ltrim( $relative_file, '/' ) );

		return 0 === strpos( $path, $managed_dir )
			&& self::managed_directory_is_safe( $base )
			&& ! is_link( $path )
				? $path
				: '';
	}

	public static function write( $entry ) {
		$uploads  = wp_upload_dir();
		$base     = rtrim( wp_normalize_path( $uploads['basedir'] ), '/' );
		$relative = self::relative_filename( $entry['id'], $entry['code_type'] );
		$path     = $base . '/' . $relative;
		if ( ! self::managed_directory_is_safe( $base ) || is_link( $path ) ) {
			return new WP_Error( 'siteintelix_custom_code_symlink', __( 'The generated file path is not safe to write.', 'siteintelix' ) );
		}
		if ( ! wp_mkdir_p( dirname( $path ) ) ) {
			return new WP_Error( 'siteintelix_custom_code_directory', __( 'The custom-code directory could not be created.', 'siteintelix' ) );
		}
		if ( ! self::managed_directory_is_safe( $base ) ) {
			return new WP_Error( 'siteintelix_custom_code_symlink', __( 'The generated file path is not safe to write.', 'siteintelix' ) );
		}
		$code = self::strip_outer_tags( $entry['code'], $entry['code_type'] );
		if ( false === file_put_contents( $path, $code, LOCK_EX ) ) {
			return new WP_Error( 'siteintelix_custom_code_write', __( 'The generated file could not be written.', 'siteintelix' ) );
		}
		return $relative;
	}

	public static function delete( $relative_file ) {
		$path = self::path( $relative_file );
		if ( '' === $path ) {
			return false;
		}

		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
			return ! file_exists( $path );
		}

		return true;
	}

	public static function delete_for_entry( $relative_file, $entry_id ) {
		if ( ! self::is_managed_file_for_entry( $relative_file, $entry_id ) ) {
			return false;
		}

		return self::delete( $relative_file );
	}

	public static function url( $relative_file ) {
		if ( ! self::is_managed_file( $relative_file ) || '' === self::path( $relative_file ) ) {
			return '';
		}

		$uploads = wp_upload_dir();
		return trailingslashit( $uploads['baseurl'] ) . ltrim( $relative_file, '/' );
	}

	private static function managed_directory_is_safe( $base ) {
		$base = rtrim( wp_normalize_path( $base ), '/' );
		return ! is_link( $base . '/siteintelix' ) && ! is_link( $base . '/siteintelix/custom-code' );
	}
}
