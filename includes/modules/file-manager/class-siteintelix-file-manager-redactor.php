<?php
/**
 * File Manager sensitive-source redaction.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redacts secrets from supported preview-only files.
 */
class SITEINTELIX_File_Manager_Redactor {

	/**
	 * Redact sensitive wp-config.php constants without modifying source files.
	 *
	 * @param string $source Source.
	 * @return string
	 */
	public static function wp_config( $source ) {
		$names = array(
			'DB_NAME',
			'DB_USER',
			'DB_PASSWORD',
			'DB_HOST',
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);
		$pattern = '/(define\s*\(\s*([\'"])(?:' . implode( '|', $names ) . ')\2\s*,\s*)([\'"])(?:\\\\.|(?!\3).)*\3(\s*\)\s*;?)/i';

		return (string) preg_replace_callback(
			$pattern,
			static function ( $matches ) {
				return $matches[1] . $matches[3] . '********' . $matches[3] . $matches[4];
			},
			(string) $source
		);
	}
}
