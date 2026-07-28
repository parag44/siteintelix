<?php
/**
 * Isolated PHP snippet runner.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Runner {
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'run_active' ), 2 );
		SITEINTELIX_Snippets_Recovery::register();
	}

	public static function run_active() {
		if ( SITEINTELIX_Snippets_Context::should_skip_all() ) return;
		$scope = SITEINTELIX_Snippets_Context::request_scope();
		foreach ( SITEINTELIX_Snippets_Repository::active_for_scope( $scope ) as $snippet ) {
			if ( ! SITEINTELIX_Snippets_Context::snippet_matches_scope( $snippet['scope'], $scope ) ) continue;
			if ( ! self::execute( $snippet ) ) break;
		}
	}

	public static function execute( $snippet ) {
		$id = absint( $snippet['id'] );
		SITEINTELIX_Snippets_Recovery::set_current( $id );
		try {
			self::execute_code( $snippet['code'] );
			SITEINTELIX_Snippets_Repository::mark_last_run( $id );
			SITEINTELIX_Snippets_Recovery::clear_current();
			return true;
		} catch ( Throwable $error ) {
			SITEINTELIX_Snippets_Recovery::handle( $id, $error );
			SITEINTELIX_Snippets_Recovery::clear_current();
			return false;
		}
	}

	private static function execute_code( $code ) {
		$executor = static function ( $snippet_code ) {
			eval( $snippet_code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_eval -- Administrator-authored PHP is this module's explicit purpose.
		};
		$executor( $code );
	}
}
