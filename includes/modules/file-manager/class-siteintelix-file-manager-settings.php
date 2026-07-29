<?php
/**
 * File Manager settings.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides conservative Safe Mode defaults and sanitization.
 */
class SITEINTELIX_File_Manager_Settings {

	const OPTION = 'siteintelix_file_manager_settings';

	/**
	 * Return default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'safe_mode'                  => 1,
			'show_hidden'                => 0,
			'start_directory'            => 'wp-content',
			'preview_max_bytes'          => 2 * MB_IN_BYTES,
			'edit_max_bytes'             => 1 * MB_IN_BYTES,
			'upload_max_bytes'           => 5 * MB_IN_BYTES,
			'allow_absolute_paths'       => 0,
			'allowed_locations'          => array(
				'wp_content' => 1,
				'plugins'    => 1,
				'themes'     => 1,
				'uploads'    => 1,
				'mu_plugins' => 1,
				'languages'  => 1,
			),
			'custom_roots'               => array(),
			'editing_enabled'            => 1,
			'editable_extensions'        => array( 'txt', 'log', 'md', 'css', 'js', 'json', 'html', 'htm', 'xml', 'yml', 'yaml', 'ini', 'conf', 'csv' ),
			'uploads_enabled'             => 1,
			'upload_extensions'          => array( 'txt', 'log', 'md', 'css', 'js', 'json', 'html', 'htm', 'xml', 'yml', 'yaml', 'ini', 'conf', 'csv', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'zip' ),
			'allow_archive_uploads'       => 0,
			'allow_overwrite'             => 0,
			'backup_retention_days'       => 30,
			'backup_max_per_file'         => 10,
			'backup_max_storage_bytes'    => 100 * MB_IN_BYTES,
			'trash_retention_days'        => 30,
			'trash_max_storage_bytes'     => 100 * MB_IN_BYTES,
			'trash_auto_cleanup'          => 1,
			'audit_enabled'               => 1,
			'audit_views'                 => 0,
			'audit_downloads'             => 1,
			'audit_edits'                 => 1,
			'audit_uploads'               => 1,
			'audit_trash'                 => 1,
			'remove_data_on_uninstall'    => 0,
		);
	}

	/**
	 * Add defaults without overwriting saved settings.
	 *
	 * @return void
	 */
	public static function add_defaults() {
		add_option( self::OPTION, self::defaults(), '', false );
	}

	/**
	 * Return merged settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_replace_recursive( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Save sanitized settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return bool
	 */
	public static function save( $input ) {
		return update_option( self::OPTION, self::sanitize( $input ), false );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$output   = $defaults;
		$booleans = array(
			'show_hidden',
			'allow_absolute_paths',
			'editing_enabled',
			'uploads_enabled',
			'allow_archive_uploads',
			'allow_overwrite',
			'trash_auto_cleanup',
			'audit_enabled',
			'audit_views',
			'audit_downloads',
			'audit_edits',
			'audit_uploads',
			'audit_trash',
			'remove_data_on_uninstall',
		);

		foreach ( $booleans as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$output['safe_mode']         = 1;
		$output['preview_max_bytes'] = self::bounded_int( $input, 'preview_max_bytes', 64 * KB_IN_BYTES, 10 * MB_IN_BYTES, $defaults['preview_max_bytes'] );
		$output['edit_max_bytes']    = self::bounded_int( $input, 'edit_max_bytes', 16 * KB_IN_BYTES, 5 * MB_IN_BYTES, $defaults['edit_max_bytes'] );
		$output['upload_max_bytes']  = self::bounded_int( $input, 'upload_max_bytes', 64 * KB_IN_BYTES, 100 * MB_IN_BYTES, $defaults['upload_max_bytes'] );

		$output['backup_retention_days']    = self::bounded_int( $input, 'backup_retention_days', 1, 365, $defaults['backup_retention_days'] );
		$output['backup_max_per_file']      = self::bounded_int( $input, 'backup_max_per_file', 1, 100, $defaults['backup_max_per_file'] );
		$output['backup_max_storage_bytes'] = self::bounded_int( $input, 'backup_max_storage_bytes', MB_IN_BYTES, 5 * GB_IN_BYTES, $defaults['backup_max_storage_bytes'] );
		$output['trash_retention_days']     = self::bounded_int( $input, 'trash_retention_days', 1, 365, $defaults['trash_retention_days'] );
		$output['trash_max_storage_bytes']  = self::bounded_int( $input, 'trash_max_storage_bytes', MB_IN_BYTES, 5 * GB_IN_BYTES, $defaults['trash_max_storage_bytes'] );

		$start = isset( $input['start_directory'] ) ? self::relative_path( $input['start_directory'] ) : $defaults['start_directory'];
		$output['start_directory'] = '' !== $start ? $start : $defaults['start_directory'];

		$output['allowed_locations'] = array();
		foreach ( array_keys( $defaults['allowed_locations'] ) as $key ) {
			$output['allowed_locations'][ $key ] = empty( $input['allowed_locations'][ $key ] ) ? 0 : 1;
		}

		$output['editable_extensions'] = self::extensions( isset( $input['editable_extensions'] ) ? $input['editable_extensions'] : $defaults['editable_extensions'], false );
		$output['upload_extensions']   = self::extensions( isset( $input['upload_extensions'] ) ? $input['upload_extensions'] : $defaults['upload_extensions'], true );
		$output['custom_roots']        = self::custom_roots( isset( $input['custom_roots'] ) ? $input['custom_roots'] : array() );

		return $output;
	}

	/**
	 * Clamp an integer input.
	 *
	 * @param array<string,mixed> $input Input array.
	 * @param string              $key Key.
	 * @param int                 $minimum Minimum.
	 * @param int                 $maximum Maximum.
	 * @param int                 $default Default.
	 * @return int
	 */
	private static function bounded_int( $input, $key, $minimum, $maximum, $default ) {
		$value = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $default;
		return min( $maximum, max( $minimum, $value ) );
	}

	/**
	 * Sanitize an extension allowlist.
	 *
	 * @param mixed $values Values.
	 * @param bool  $uploads Whether this is the upload list.
	 * @return string[]
	 */
	private static function extensions( $values, $uploads ) {
		$values  = is_array( $values ) ? $values : preg_split( '/[\s,]+/', (string) $values );
		$blocked = array( 'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'dll', 'so', 'htaccess', 'user.ini' );
		$output  = array();

		foreach ( (array) $values as $value ) {
			$value = ltrim( sanitize_key( $value ), '.' );
			if ( '' === $value || in_array( $value, $blocked, true ) || ( ! $uploads && 'zip' === $value ) ) {
				continue;
			}
			$output[] = $value;
		}

		return array_values( array_unique( $output ) );
	}

	/**
	 * Sanitize custom roots inside ABSPATH.
	 *
	 * @param mixed $values Values.
	 * @return string[]
	 */
	private static function custom_roots( $values ) {
		$values = is_array( $values ) ? $values : preg_split( '/[\r\n]+/', (string) $values );
		$output = array();
		$root   = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );

		foreach ( (array) $values as $value ) {
			$relative = self::relative_path( $value );
			if ( '' === $relative ) {
				continue;
			}
			$real = realpath( trailingslashit( ABSPATH ) . $relative );
			$real = false !== $real ? wp_normalize_path( $real ) : '';
			if ( $real && ( $real === $root || 0 === strpos( $real, trailingslashit( $root ) ) ) ) {
				$output[] = $relative;
			}
		}

		return array_values( array_unique( $output ) );
	}

	/**
	 * Sanitize a relative path without resolving it.
	 *
	 * @param mixed $path Path.
	 * @return string
	 */
	private static function relative_path( $path ) {
		$path = trim( wp_normalize_path( (string) $path ), '/' );
		if ( '' === $path || false !== strpos( $path, "\0" ) || preg_match( '#(^|/)\.{1,2}(/|$)#', $path ) ) {
			return '';
		}
		return implode( '/', array_filter( explode( '/', $path ), 'strlen' ) );
	}
}
