<?php
/**
 * SITEINTELIX_Rest_API — registers REST API endpoints for system information.
 *
 * Namespace : siteintelix/v1
 * Routes    : GET /info   — full system data (filterable by ?section=)
 *             GET /health — health check results
 *
 * Both routes require the user to be authenticated and to hold the
 * manage_options capability (Administrators only).
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Rest_API
 */
class SITEINTELIX_Rest_API {

	/** REST API namespace. */
	const NAMESPACE = 'siteintelix/v1';

	/**
	 * Register hooks.
	 * Called once via siteintelix_load_includes() on plugins_loaded.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {

		// GET /siteintelix/v1/info[?section=wordpress|server|environment]
		register_rest_route(
			self::NAMESPACE,
			'/info',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_info' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
					'args'                => array(
						'section' => array(
							'description'       => __( 'Filter by section: wordpress, server, or environment. Defaults to all.', 'siteintelix' ),
							'type'              => 'string',
							'default'           => 'all',
							'enum'              => array( 'all', 'wordpress', 'server', 'environment' ),
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		// GET /siteintelix/v1/health
		register_rest_route(
			self::NAMESPACE,
			'/health',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'handle_health' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
				),
			)
		);
	}

	// -----------------------------------------------------------------------
	// Permission
	// -----------------------------------------------------------------------

	/**
	 * Allow only authenticated users with manage_options capability.
	 *
	 * @return bool|WP_Error
	 */
	public static function permission_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'siteintelix_forbidden',
				__( 'You do not have permission to access system information.', 'siteintelix' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	// -----------------------------------------------------------------------
	// Handlers
	// -----------------------------------------------------------------------

	/**
	 * Handle GET /info
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public static function handle_info( $request ) {
		$all     = SITEINTELIX_System_Info::get_all();
		$section = $request->get_param( 'section' );
		$data    = ( 'all' !== $section && isset( $all[ $section ] ) ) ? array( $section => $all[ $section ] ) : $all;

		$response = new WP_REST_Response(
			array(
				'success'   => true,
				'plugin'    => 'SiteIntelix',
				'version'   => SITEINTELIX_VERSION,
				'generated' => current_time( 'c' ),
				'data'      => $data,
			),
			200
		);

		// Prevent caching of sensitive system data.
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );

		return $response;
	}

	/**
	 * Handle GET /health
	 *
	 * @param WP_REST_Request $request Incoming request (unused).
	 * @return WP_REST_Response
	 */
	public static function handle_health( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$info    = SITEINTELIX_System_Info::get_all();
		$checks  = SITEINTELIX_Health_Check::run( $info );
		$overall = SITEINTELIX_Health_Check::overall_status( $checks );

		$response = new WP_REST_Response(
			array(
				'success'        => true,
				'overall_status' => $overall,
				'generated'      => current_time( 'c' ),
				'checks'         => $checks,
			),
			200
		);

		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );

		return $response;
	}
}

// Boot.
SITEINTELIX_Rest_API::init();
