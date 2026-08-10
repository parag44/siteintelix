<?php
/**
 * User Switcher settings.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and renders User Switcher settings.
 */
class SITEINTELIX_User_Switcher_Settings {

	const SETTINGS_OPTION      = 'siteintelix_user_switcher_settings';
	const MANAGED_ROLES_OPTION = 'siteintelix_user_switcher_managed_roles';
	const MIN_DURATION         = 5;
	const MAX_DURATION         = 480;
	const MIN_RETENTION        = 1;
	const MAX_RETENTION        = 365;

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_post_siteintelix_save_user_switcher_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_submenu' ), 98 );
		add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_section' ), 10, 2 );
	}

	/**
	 * Register the dedicated User Switcher submenu immediately before Settings.
	 *
	 * @return void
	 */
	public static function register_submenu() {
		add_submenu_page(
			'siteintelix',
			__( 'User Switcher', 'siteintelix' ),
			__( 'User Switcher', 'siteintelix' ),
			'manage_options',
			'siteintelix-user-switcher',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$roles           = self::get_registered_roles();
		$target_defaults = array_values( array_intersect( array( 'subscriber', 'customer', 'student', 'instructor', 'editor', 'author', 'contributor' ), array_keys( $roles ) ) );

		return array(
			'allowed_operator_roles' => isset( $roles['administrator'] ) ? array( 'administrator' ) : array(),
			'allowed_target_roles'   => $target_defaults,
			'allow_administrators'   => 0,
			'switch_redirect'        => 'user_dashboard',
			'switch_custom_url'      => '',
			'return_redirect'        => 'users',
			'return_custom_url'      => '',
			'session_duration'       => 60,
			'logging_enabled'        => 1,
			'retention_days'         => 30,
		);
	}

	/**
	 * Normalized settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return self::normalize( is_array( $saved ) ? array_merge( self::get_defaults(), $saved ) : self::get_defaults() );
	}

	/**
	 * Registered editable roles.
	 *
	 * @return array<string,string>
	 */
	public static function get_registered_roles() {
		$wp_roles = wp_roles();
		$choices  = array();

		foreach ( (array) $wp_roles->roles as $slug => $role ) {
			$choices[ sanitize_key( $slug ) ] = translate_user_role( isset( $role['name'] ) ? $role['name'] : $slug );
		}

		natcasesort( $choices );
		return $choices;
	}

	/**
	 * Validate and normalize settings.
	 *
	 * @param array<string,mixed> $settings Raw settings.
	 * @return array<string,mixed>
	 */
	public static function normalize( $settings ) {
		$roles        = self::get_registered_roles();
		$role_slugs   = array_keys( $roles );
		$switch_modes = array( 'user_dashboard', 'homepage', 'wp_admin', 'custom' );
		$return_modes = array( 'users', 'previous_admin', 'wp_dashboard', 'custom' );

		$settings['allowed_operator_roles'] = array_values( array_intersect( array_map( 'sanitize_key', (array) $settings['allowed_operator_roles'] ), $role_slugs ) );
		$settings['allowed_target_roles']   = array_values( array_intersect( array_map( 'sanitize_key', (array) $settings['allowed_target_roles'] ), $role_slugs ) );
		$settings['allow_administrators']   = empty( $settings['allow_administrators'] ) ? 0 : 1;
		$settings['switch_redirect']        = in_array( $settings['switch_redirect'], $switch_modes, true ) ? $settings['switch_redirect'] : 'user_dashboard';
		$settings['return_redirect']        = in_array( $settings['return_redirect'], $return_modes, true ) ? $settings['return_redirect'] : 'users';
		$settings['switch_custom_url']      = self::sanitize_custom_url( isset( $settings['switch_custom_url'] ) ? $settings['switch_custom_url'] : '' );
		$settings['return_custom_url']      = self::sanitize_custom_url( isset( $settings['return_custom_url'] ) ? $settings['return_custom_url'] : '' );
		$settings['session_duration']       = min( self::MAX_DURATION, max( self::MIN_DURATION, absint( $settings['session_duration'] ) ) );
		$settings['logging_enabled']        = empty( $settings['logging_enabled'] ) ? 0 : 1;
		$settings['retention_days']         = min( self::MAX_RETENTION, max( self::MIN_RETENTION, absint( $settings['retention_days'] ) ) );

		return $settings;
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change User Switcher settings.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_save_user_switcher_settings' );

		$settings = self::normalize(
			array(
				'allowed_operator_roles' => isset( $_POST['allowed_operator_roles'] ) && is_array( $_POST['allowed_operator_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['allowed_operator_roles'] ) ) : array(),
				'allowed_target_roles'   => isset( $_POST['allowed_target_roles'] ) && is_array( $_POST['allowed_target_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['allowed_target_roles'] ) ) : array(),
				'allow_administrators'   => isset( $_POST['allow_administrators'] ) ? 1 : 0,
				'switch_redirect'        => isset( $_POST['switch_redirect'] ) ? sanitize_key( wp_unslash( $_POST['switch_redirect'] ) ) : 'user_dashboard',
				'switch_custom_url'      => isset( $_POST['switch_custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['switch_custom_url'] ) ) : '',
				'return_redirect'        => isset( $_POST['return_redirect'] ) ? sanitize_key( wp_unslash( $_POST['return_redirect'] ) ) : 'users',
				'return_custom_url'      => isset( $_POST['return_custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_custom_url'] ) ) : '',
				'session_duration'       => isset( $_POST['session_duration'] ) ? absint( wp_unslash( $_POST['session_duration'] ) ) : 60,
				'logging_enabled'        => isset( $_POST['logging_enabled'] ) ? 1 : 0,
				'retention_days'         => isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 30,
			)
		);

		update_option( self::SETTINGS_OPTION, $settings, false );
		SITEINTELIX_User_Switcher_Permissions::sync_operator_roles( $settings['allowed_operator_roles'] );
		SITEINTELIX_User_Switcher_Logger::schedule_retention();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => 'siteintelix-settings',
					'tab'                        => 'user_switcher',
					'siteintelix_settings_saved' => '1',
				),
				admin_url( 'admin.php' )
			) . '#siteintelix-user-switcher-settings'
		);
		exit;
	}

	/**
	 * Render User Switcher settings inside the shared settings page.
	 *
	 * @param string[] $enabled_modules Enabled module IDs.
	 * @param string   $active_tab      Active shared settings tab.
	 * @return void
	 */
	public static function render_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'user_switcher', (array) $enabled_modules, true ) ) {
			return;
		}

		$settings = self::get_settings();
		$roles    = self::get_registered_roles();
		?>
		<section class="sitx-settings-panel-tab <?php echo 'user_switcher' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-user-switcher-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-user_switcher" data-siteintelix-settings-panel="user_switcher" <?php echo 'user_switcher' === $active_tab ? '' : 'hidden'; ?>>
			<?php require SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/views/settings.php'; ?>
		</section>
		<?php
	}

	/**
	 * Render the dedicated activity-log page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access User Switcher.', 'siteintelix' ) );
		}

		?>
		<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-user-switcher-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-admin-users',
					'title'       => __( 'User Switcher', 'siteintelix' ),
					'description' => __( 'Review temporary account access and how each switching session ended.', 'siteintelix' ),
					'badges'      => array(
						'<span class="siteintelix-version-pill">v' . esc_html( SITEINTELIX_VERSION ) . '</span>',
					),
					'actions'     => array(
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'All Users', 'siteintelix' ),
								'url'     => admin_url( 'users.php' ),
								'variant' => 'secondary',
								'icon'    => 'dashicons-groups',
							)
						),
					),
				)
			);
			?>
			<div class="siteintelix-container">
				<div class="sitx-settings-shell si-card sitx-user-switcher-shell">
					<?php require SITEINTELIX_PLUGIN_DIR . 'includes/modules/user-switcher/views/logs.php'; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Keep custom redirect URLs same-origin unless a developer explicitly opts in.
	 *
	 * @param string $url Candidate URL.
	 * @return string
	 */
	public static function sanitize_custom_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$home_parts = wp_parse_url( home_url( '/' ) );
		$url_parts  = wp_parse_url( $url );
		$home_port  = isset( $home_parts['port'] ) ? absint( $home_parts['port'] ) : ( 'https' === $home_parts['scheme'] ? 443 : 80 );
		$url_port   = isset( $url_parts['port'] ) ? absint( $url_parts['port'] ) : ( isset( $url_parts['scheme'] ) && 'https' === $url_parts['scheme'] ? 443 : 80 );
		$external   = empty( $url_parts['host'] )
			|| empty( $home_parts['host'] )
			|| strtolower( $url_parts['host'] ) !== strtolower( $home_parts['host'] )
			|| strtolower( isset( $url_parts['scheme'] ) ? $url_parts['scheme'] : '' ) !== strtolower( isset( $home_parts['scheme'] ) ? $home_parts['scheme'] : '' )
			|| $url_port !== $home_port;

		/**
		 * Allow explicitly configured external User Switcher redirects.
		 *
		 * @param bool   $allow Whether external redirects are allowed.
		 * @param string $url   Candidate URL.
		 */
		if ( $external && ! apply_filters( 'siteintelix_user_switcher_allow_external_redirect', false, $url ) ) {
			return '';
		}

		return wp_validate_redirect( $url, '' );
	}

	/**
	 * Resolve the destination after switching.
	 *
	 * @param WP_User $target_user Target user.
	 * @return string
	 */
	public static function get_switch_redirect( $target_user ) {
		$settings = self::get_settings();
		$url      = home_url( '/' );

		if ( 'wp_admin' === $settings['switch_redirect'] ) {
			$url = admin_url();
		} elseif ( 'custom' === $settings['switch_redirect'] && $settings['switch_custom_url'] ) {
			$url = $settings['switch_custom_url'];
		} elseif ( 'user_dashboard' === $settings['switch_redirect'] && function_exists( 'tutor_utils' ) ) {
			$tutor = tutor_utils();
			if ( is_object( $tutor ) && method_exists( $tutor, 'tutor_dashboard_url' ) ) {
				$tutor_url = self::sanitize_custom_url( $tutor->tutor_dashboard_url() );
				$url       = $tutor_url ? $tutor_url : $url;
			}
		}

		/** Filter the validated URL used after switching accounts. */
		$url = apply_filters( 'siteintelix_user_switcher_switch_redirect', $url, $target_user, $settings );
		return self::sanitize_custom_url( $url ) ? self::sanitize_custom_url( $url ) : home_url( '/' );
	}

	/**
	 * Resolve the destination after restoring the original operator.
	 *
	 * @param array<string,mixed> $session Trusted session data.
	 * @return string
	 */
	public static function get_return_redirect( $session ) {
		$settings = self::get_settings();
		$url      = admin_url( 'users.php' );

		if ( 'previous_admin' === $settings['return_redirect'] && ! empty( $session['previous_url'] ) ) {
			$url = $session['previous_url'];
		} elseif ( 'wp_dashboard' === $settings['return_redirect'] ) {
			$url = admin_url();
		} elseif ( 'custom' === $settings['return_redirect'] && $settings['return_custom_url'] ) {
			$url = $settings['return_custom_url'];
		}

		/** Filter the validated URL used after restoring the operator. */
		$url = apply_filters( 'siteintelix_user_switcher_return_redirect', $url, $session, $settings );
		return self::sanitize_custom_url( $url ) ? self::sanitize_custom_url( $url ) : admin_url( 'users.php' );
	}

	/**
	 * Send a redirect previously validated by the module's same-origin policy.
	 *
	 * External URLs reach this method only when a developer explicitly enables
	 * them through siteintelix_user_switcher_allow_external_redirect.
	 *
	 * @param string $url      Requested destination.
	 * @param string $fallback Same-origin fallback.
	 * @return void
	 */
	public static function redirect( $url, $fallback ) {
		$validated = self::sanitize_custom_url( $url );
		if ( ! $validated ) {
			$validated = self::sanitize_custom_url( $fallback );
		}
		wp_redirect( $validated ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- The module validates origin and requires an explicit filter for external hosts.
	}
}
