<?php
/**
 * SITEINTELIX_Security — modular security hardening toggles.
 *
 * Each feature is stored as a separate option and hooked into WordPress
 * at the appropriate point. The feature list is data-driven so the UI
 * and storage are automatically in sync.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Security
 */
class SITEINTELIX_Security {

	/**
	 * Return the feature definition list.
	 *
	 * Each entry:
	 *   id          (string) — unique key, also used as the checkbox name.
	 *   title       (string) — human-readable name.
	 *   description (string) — one-line explanation.
	 *   option_key  (string) — wp_options key.
	 *   default     (bool)   — default state.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_features() {
		return array(
			array(
				'id'          => 'xmlrpc',
				'title'       => __( 'Disable XML-RPC', 'siteintelix' ),
				'description' => __( 'Blocks the XML-RPC interface commonly targeted by brute-force and DDoS attacks.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_xmlrpc',
				'default'     => false,
			),
			array(
				'id'          => 'hide_version',
				'title'       => __( 'Hide WordPress Version', 'siteintelix' ),
				'description' => __( 'Removes the WordPress version meta tag from the site front-end.', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_hide_version',
				'default'     => false,
			),
			array(
				'id'          => 'disable_file_edit',
				'title'       => __( 'Disable File Editing', 'siteintelix' ),
				'description' => __( 'Disables the Theme and Plugin editor inside the WordPress admin (DISALLOW_FILE_EDIT).', 'siteintelix' ),
				'option_key'  => 'siteintelix_security_disable_file_edit',
				'default'     => false,
			),
		);
	}

	/**
	 * Bootstrap all active security hooks.
	 *
	 * Called on plugins_loaded.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			if ( ! get_option( $feature['option_key'], $feature['default'] ) ) {
				continue;
			}

			switch ( $feature['id'] ) {
				case 'xmlrpc':
					add_filter( 'xmlrpc_enabled', '__return_false' );
					add_filter( 'wp_headers', array( __CLASS__, 'remove_xmlrpc_header' ) );
					break;

				case 'hide_version':
					remove_action( 'wp_head', 'wp_generator' );
					add_filter( 'the_generator', '__return_empty_string' );
					break;

				case 'disable_file_edit':
					if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
						define( 'DISALLOW_FILE_EDIT', true );
					}
					break;
			}
		}
	}

	/**
	 * Remove the X-Pingback HTTP header when XML-RPC is disabled.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public static function remove_xmlrpc_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}

	/**
	 * Save security settings from form POST data.
	 *
	 * @param array $posted Raw $_POST data (already nonce-verified by caller).
	 * @return void
	 */
	public static function save( $posted ) {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			$key     = $feature['option_key'];
			$enabled = isset( $posted[ $feature['id'] ] ) && '1' === sanitize_text_field( wp_unslash( $posted[ $feature['id'] ] ) );
			update_option( $key, $enabled ? 1 : 0 );
		}
	}

	/**
	 * Check whether a specific feature is currently enabled.
	 *
	 * @param string $feature_id Feature ID.
	 * @return bool
	 */
	public static function is_enabled( $feature_id ) {
		$features = self::get_features();

		foreach ( $features as $feature ) {
			if ( $feature['id'] === $feature_id ) {
				return (bool) get_option( $feature['option_key'], $feature['default'] );
			}
		}

		return false;
	}
}
