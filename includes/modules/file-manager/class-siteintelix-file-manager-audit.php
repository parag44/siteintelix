<?php
/**
 * File Manager private audit log.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes bounded, content-free security audit records.
 */
class SITEINTELIX_File_Manager_Audit {

	/**
	 * Return the private audit path.
	 *
	 * @return string
	 */
	public static function log_path() {
		return SITEINTELIX_File_Manager_Storage::path( 'audit/activity.jsonl' );
	}

	/**
	 * Append one audit record.
	 *
	 * @param string $operation Operation.
	 * @param string $path ABSPATH-relative path.
	 * @param string $result Result.
	 * @param string $error_category Error category.
	 * @return true|WP_Error
	 */
	public static function record( $operation, $path, $result, $error_category = '' ) {
		$settings = get_option( 'siteintelix_file_manager_settings', array() );
		if ( is_array( $settings ) && isset( $settings['audit_enabled'] ) && empty( $settings['audit_enabled'] ) ) {
			return true;
		}
		$path = ltrim( wp_normalize_path( (string) $path ), '/' );
		if ( '' === $path || preg_match( '#(^|/)\.\.(/|$)#', $path ) || false !== strpos( $path, "\0" ) ) {
			return new WP_Error( 'siteintelix_file_manager_invalid_audit_path', __( 'The File Manager audit path is invalid.', 'siteintelix' ) );
		}
		$ready = SITEINTELIX_File_Manager_Storage::ensure_directories();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}
		$record = array(
			'user_id'        => (int) get_current_user_id(),
			'timestamp'      => gmdate( 'c' ),
			'operation'      => preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $operation ) ),
			'path'           => $path,
			'result'         => preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $result ) ),
			'error_category' => preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $error_category ) ),
		);
		$path     = self::log_path();
		$maximum  = (int) apply_filters( 'siteintelix_file_manager_audit_max_bytes', 5 * MB_IN_BYTES );
		if ( is_file( $path ) && filesize( $path ) >= $maximum ) {
			$rotated = $path . '.1';
			if ( is_file( $rotated ) ) {
				unlink( $rotated );
			}
			rename( $path, $rotated );
		}
		$handle = fopen( $path, 'ab' );
		if ( false === $handle ) {
			return new WP_Error( 'siteintelix_file_manager_audit_failed', __( 'File Manager activity could not be logged.', 'siteintelix' ) );
		}
		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle );
			return new WP_Error( 'siteintelix_file_manager_audit_failed', __( 'File Manager activity could not be logged.', 'siteintelix' ) );
		}
		$line   = wp_json_encode( $record ) . "\n";
		$result = fwrite( $handle, $line );
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );
		return strlen( $line ) === $result ? true : new WP_Error( 'siteintelix_file_manager_audit_failed', __( 'File Manager activity could not be logged.', 'siteintelix' ) );
	}
}
