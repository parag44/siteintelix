<?php
/**
 * Request policy for PHP snippet execution.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Context {
	public static function is_safe_mode() {
		if ( defined( 'SITEINTELIX_SAFE_MODE' ) && SITEINTELIX_SAFE_MODE ) return true;
		if ( defined( 'SITEINTELIX_SAFE_MODE_ACTIVE' ) && SITEINTELIX_SAFE_MODE_ACTIVE ) return true;
		return is_admin() && SITEINTELIX_Security::can_manage_code() && isset( $_GET['siteintelix_safe_mode'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_safe_mode'] ) );
	}

	public static function is_management_request() {
		$action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) );
		$page   = sanitize_key( wp_unslash( $_REQUEST['page'] ?? '' ) );
		return 0 === strpos( $action, 'siteintelix_' ) || 0 === strpos( $page, 'siteintelix-code-snippets' ) || defined( 'WP_UNINSTALL_PLUGIN' );
	}

	public static function should_skip_all() {
		return ( defined( 'WP_CLI' ) && WP_CLI ) || self::is_safe_mode() || self::is_management_request();
	}

	public static function request_scope() {
		if ( ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) return 'machine';
		return is_admin() ? 'admin' : 'frontend';
	}

	public static function snippet_matches_scope( $snippet_scope, $request_scope ) {
		return 'everywhere' === $snippet_scope || ( 'machine' !== $request_scope && $snippet_scope === $request_scope );
	}
}
