<?php
/**
 * User Switcher admin actions.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds switch controls and processes nonce-protected requests.
 */
class SITEINTELIX_User_Switcher_Admin_Actions {

	const SWITCH_ACTION  = 'siteintelix_user_switcher_switch';
	const RESTORE_ACTION = 'siteintelix_user_switcher_restore';

	/**
	 * Register enabled-module actions.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'user_row_actions', array( __CLASS__, 'add_user_row_action' ), 10, 2 );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_action' ) );
		add_action( 'admin_post_siteintelix_user_switcher_switch', array( __CLASS__, 'handle_switch' ) );
		self::init_recovery();
	}

	/**
	 * Register only the return handler needed by an existing session.
	 *
	 * @return void
	 */
	public static function init_recovery() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_dispatch_restore' ), 0 );
		add_action( 'admin_post_siteintelix_user_switcher_restore', array( __CLASS__, 'handle_restore' ) );
	}

	/**
	 * Dispatch restoration before plugins can deny the target wp-admin access.
	 *
	 * WordPress normally fires admin-post actions after admin_init. LMS plugins
	 * may deny the switched target during admin_init, preventing restoration.
	 *
	 * @return void
	 */
	public static function maybe_dispatch_restore() {
		global $pagenow;

		if (
			'admin-post.php' !== $pagenow
			|| ! is_user_logged_in()
			|| ! isset( $_REQUEST['action'] )
			|| ! is_scalar( $_REQUEST['action'] )
			|| self::RESTORE_ACTION !== sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )
		) {
			return;
		}

		do_action( 'admin_post_' . self::RESTORE_ACTION );
	}

	/**
	 * Add Login as User to Users > All Users.
	 *
	 * @param string[] $actions Existing row actions.
	 * @param WP_User  $user    Target user.
	 * @return string[]
	 */
	public static function add_user_row_action( $actions, $user ) {
		if ( self::can_show_switch_action( $user ) ) {
			$actions['siteintelix_login_as_user'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::switch_url( $user ) ),
				esc_html__( 'Login as User', 'siteintelix' )
			);
		}
		return $actions;
	}

	/**
	 * Render a switch button on another user's profile screen.
	 *
	 * @param WP_User $user Target user.
	 * @return void
	 */
	public static function render_profile_action( $user ) {
		if ( ! self::can_show_switch_action( $user ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'SiteIntelix User Switcher', 'siteintelix' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Temporary access', 'siteintelix' ); ?></th>
				<td>
					<a class="button button-secondary" href="<?php echo esc_url( self::switch_url( $user ) ); ?>">
						<?php esc_html_e( 'Login as User', 'siteintelix' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'Temporarily access this account without viewing or changing its password.', 'siteintelix' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Validate and begin a switch, with an extra confirmation for admins.
	 *
	 * @return void
	 */
	public static function handle_switch() {
		if ( ! SITEINTELIX_Modules::is_enabled( 'user_switcher' ) || ! SITEINTELIX_User_Switcher_Permissions::can_operate() ) {
			wp_die( esc_html__( 'You do not have permission to switch users.', 'siteintelix' ), '', array( 'response' => 403 ) );
		}
		if ( SITEINTELIX_User_Switcher_Session_Manager::is_switching() ) {
			wp_die( esc_html__( 'Return to the original account before switching again.', 'siteintelix' ), '', array( 'response' => 409 ) );
		}

		$target_id = isset( $_POST['target_user'] )
			? absint( wp_unslash( $_POST['target_user'] ) )
			: ( isset( $_GET['target_user'] ) ? absint( wp_unslash( $_GET['target_user'] ) ) : 0 );
		check_admin_referer( self::SWITCH_ACTION . '_' . $target_id );
		$target_user  = get_user_by( 'id', $target_id );
		$current_user = wp_get_current_user();

		if ( ! $target_user instanceof WP_User || ! SITEINTELIX_User_Switcher_Permissions::can_switch_to( $target_user, $current_user ) ) {
			wp_die( esc_html__( 'This account is protected or is not an allowed switch target.', 'siteintelix' ), '', array( 'response' => 403 ) );
		}

		$previous_url = isset( $_POST['siteintelix_previous_url'] )
			? SITEINTELIX_User_Switcher_Settings::sanitize_custom_url( esc_url_raw( wp_unslash( $_POST['siteintelix_previous_url'] ) ) )
			: SITEINTELIX_User_Switcher_Settings::sanitize_custom_url( wp_get_referer() );
		if ( SITEINTELIX_User_Switcher_Permissions::is_administrator_target( $target_user ) && empty( $_POST['siteintelix_confirm_admin_switch'] ) ) {
			self::render_admin_confirmation( $target_user, $previous_url );
		}

		$redirect_url = SITEINTELIX_User_Switcher_Settings::get_switch_redirect( $target_user );
		$result       = SITEINTELIX_User_Switcher_Session_Manager::start( $current_user, $target_user, $redirect_url, $previous_url );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		SITEINTELIX_User_Switcher_Settings::redirect( $redirect_url, home_url( '/' ) );
		exit;
	}

	/**
	 * Restore the original operator.
	 *
	 * @return void
	 */
	public static function handle_restore() {
		check_admin_referer( self::RESTORE_ACTION );
		$session = SITEINTELIX_User_Switcher_Session_Manager::get_active_session();
		if ( ! $session ) {
			wp_die( esc_html__( 'The switching session is invalid or expired.', 'siteintelix' ), '', array( 'response' => 400 ) );
		}

		$return_url = SITEINTELIX_User_Switcher_Settings::get_return_redirect( $session );
		$result     = SITEINTELIX_User_Switcher_Session_Manager::restore();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400 ) );
		}

		SITEINTELIX_User_Switcher_Settings::redirect( $return_url, admin_url( 'users.php' ) );
		exit;
	}

	/**
	 * Nonce-protected return URL.
	 *
	 * @return string
	 */
	public static function restore_url() {
		return wp_nonce_url(
			add_query_arg( 'action', self::RESTORE_ACTION, admin_url( 'admin-post.php' ) ),
			self::RESTORE_ACTION
		);
	}

	/**
	 * Whether the current screen may present a switch control.
	 *
	 * @param WP_User $target Target user.
	 * @return bool
	 */
	private static function can_show_switch_action( $target ) {
		return ! SITEINTELIX_User_Switcher_Session_Manager::is_switching()
			&& $target instanceof WP_User
			&& SITEINTELIX_User_Switcher_Permissions::can_switch_to( $target );
	}

	/**
	 * Nonce-protected switch URL.
	 *
	 * @param WP_User $target Target user.
	 * @return string
	 */
	private static function switch_url( $target ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'      => self::SWITCH_ACTION,
					'target_user' => absint( $target->ID ),
				),
				admin_url( 'admin-post.php' )
			),
			self::SWITCH_ACTION . '_' . absint( $target->ID )
		);
	}

	/**
	 * Render a no-JavaScript risk confirmation for administrator targets.
	 *
	 * @param WP_User $target       Target administrator.
	 * @param string  $previous_url Validated originating admin URL.
	 * @return void
	 */
	private static function render_admin_confirmation( $target, $previous_url ) {
		$title = __( 'Confirm administrator account switch', 'siteintelix' );
		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $title ); ?></h1>
			<div class="notice notice-warning inline"><p>
				<?php
				printf(
					/* translators: %s: target account display name. */
					esc_html__( 'You are about to temporarily assume the administrator privileges of %s. Return to your account as soon as troubleshooting is complete.', 'siteintelix' ),
					esc_html( $target->display_name )
				);
				?>
			</p></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::SWITCH_ACTION ); ?>">
				<input type="hidden" name="target_user" value="<?php echo esc_attr( absint( $target->ID ) ); ?>">
				<input type="hidden" name="siteintelix_confirm_admin_switch" value="1">
				<input type="hidden" name="siteintelix_previous_url" value="<?php echo esc_attr( $previous_url ); ?>">
				<?php wp_nonce_field( self::SWITCH_ACTION . '_' . absint( $target->ID ) ); ?>
				<?php submit_button( __( 'Confirm Login as User', 'siteintelix' ), 'primary', 'submit', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></a>
			</form>
		</div>
		<?php
		require_once ABSPATH . 'wp-admin/admin-footer.php';
		exit;
	}
}
