<?php
/**
 * Admin controller for Custom CSS & JS.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Custom_Code_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 38 );
		add_action( 'admin_post_siteintelix_custom_code_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_siteintelix_custom_code_action', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_siteintelix_custom_code_bulk', array( __CLASS__, 'handle_bulk' ) );
	}

	public static function register_menu() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			return;
		}

		add_submenu_page( 'siteintelix', __( 'Custom CSS & JS', 'siteintelix' ), __( 'Custom CSS & JS', 'siteintelix' ), 'manage_options', 'siteintelix-custom-code', array( __CLASS__, 'render_list' ) );
		add_submenu_page( null, __( 'Add Custom Code', 'siteintelix' ), __( 'Add New Code', 'siteintelix' ), 'manage_options', 'siteintelix-custom-code-new', array( __CLASS__, 'render_editor' ) );
	}

	private static function authorize( $action ) {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			wp_die( esc_html__( 'You do not have permission to manage custom code.', 'siteintelix' ), 403 );
		}
		check_admin_referer( $action );
	}

	public static function render_list() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			wp_die( esc_html__( 'Access denied.', 'siteintelix' ) );
		}
		$args = array(
			'page'      => max( 1, absint( $_GET['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'per_page'  => 20,
			'search'    => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'code_type' => sanitize_key( wp_unslash( $_GET['code_type'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'    => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$entries = SITEINTELIX_Custom_Code_Repository::list_entries( $args );
		$total   = SITEINTELIX_Custom_Code_Repository::count_entries( $args );
		$summary = array(
			'total'    => SITEINTELIX_Custom_Code_Repository::count_entries(),
			'enabled'  => SITEINTELIX_Custom_Code_Repository::count_entries( array( 'status' => 'enabled' ) ),
			'disabled' => SITEINTELIX_Custom_Code_Repository::count_entries( array( 'status' => 'disabled' ) ),
		);
		include __DIR__ . '/views/list.php';
	}

	public static function render_editor() {
		if ( ! SITEINTELIX_Security::can_manage_code() ) {
			wp_die( esc_html__( 'Access denied.', 'siteintelix' ) );
		}
		$id    = absint( $_GET['entry'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$entry = $id ? SITEINTELIX_Custom_Code_Repository::get( $id ) : array();
		$entry = wp_parse_args( (array) $entry, array(
			'id' => 0, 'title' => '', 'code' => '', 'code_type' => 'css', 'scope' => 'frontend',
			'location' => 'header', 'loading_method' => 'inline', 'priority' => 10,
			'status' => 'disabled', 'description' => '',
		) );
		include __DIR__ . '/views/editor.php';
	}

	public static function handle_save() {
		self::authorize( 'siteintelix_custom_code_save' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- authorize() verified the action-specific nonce immediately above.
		$id    = absint( $_POST['entry_id'] ?? 0 );
		$entry = SITEINTELIX_Custom_Code_Repository::normalize_entry( $_POST );
		if ( 'save_enable' === sanitize_key( $_POST['submit_mode'] ?? '' ) ) {
			$entry['status'] = 'enabled';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$entry['code'] = SITEINTELIX_Custom_Code_File_Manager::strip_outer_tags( $entry['code'], $entry['code_type'] );
		$id = $id ? ( SITEINTELIX_Custom_Code_Repository::update( $id, $entry ) ? $id : 0 ) : SITEINTELIX_Custom_Code_Repository::insert( $entry );
		if ( ! $id ) {
			wp_safe_redirect( add_query_arg( 'siteintelix_error', 'save', wp_get_referer() ) );
			exit;
		}
		$saved = SITEINTELIX_Custom_Code_Repository::get( $id );
		if ( 'external' === $saved['loading_method'] ) {
			$relative = SITEINTELIX_Custom_Code_File_Manager::write( $saved );
			if ( ! is_wp_error( $relative ) ) {
				SITEINTELIX_Custom_Code_Repository::update( $id, array_merge( $saved, array( 'generated_file' => $relative ) ) );
			}
		} elseif ( ! empty( $saved['generated_file'] ) ) {
			SITEINTELIX_Custom_Code_File_Manager::delete( $saved['generated_file'] );
			SITEINTELIX_Custom_Code_Repository::update( $id, array_merge( $saved, array( 'generated_file' => '' ) ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-custom-code-new', 'entry' => $id, 'saved' => 1 ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_action() {
		self::authorize( 'siteintelix_custom_code_action' );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- authorize() verified the action-specific nonce immediately above.
		$id     = absint( $_GET['entry'] ?? 0 );
		$action = sanitize_key( $_GET['do'] ?? '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$entry  = SITEINTELIX_Custom_Code_Repository::get( $id );
		if ( $entry && in_array( $action, array( 'enable', 'disable' ), true ) ) {
			SITEINTELIX_Custom_Code_Repository::set_status( array( $id ), 'enable' === $action ? 'enabled' : 'disabled' );
		} elseif ( $entry && 'duplicate' === $action ) {
			SITEINTELIX_Custom_Code_Repository::duplicate( $id, get_current_user_id() );
		} elseif ( $entry && 'delete' === $action ) {
			SITEINTELIX_Custom_Code_File_Manager::delete( $entry['generated_file'] );
			SITEINTELIX_Custom_Code_Repository::delete( $id );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=siteintelix-custom-code' ) );
		exit;
	}

	public static function handle_bulk() {
		self::authorize( 'siteintelix_custom_code_bulk' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- authorize() verified the action-specific nonce immediately above.
		$ids    = array_map( 'absint', (array) ( $_POST['entry_ids'] ?? array() ) );
		$action = sanitize_key( $_POST['bulk_action'] ?? '' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( in_array( $action, array( 'enable', 'disable' ), true ) ) {
			SITEINTELIX_Custom_Code_Repository::set_status( $ids, 'enable' === $action ? 'enabled' : 'disabled' );
		} elseif ( 'delete' === $action ) {
			foreach ( $ids as $id ) {
				$entry = SITEINTELIX_Custom_Code_Repository::get( $id );
				if ( $entry ) {
					SITEINTELIX_Custom_Code_File_Manager::delete( $entry['generated_file'] );
					SITEINTELIX_Custom_Code_Repository::delete( $id );
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=siteintelix-custom-code' ) );
		exit;
	}
}
