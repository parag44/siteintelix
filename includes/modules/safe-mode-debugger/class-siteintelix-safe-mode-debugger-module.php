<?php
/**
 * Safe Mode Debugger module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides private, session-bound plugin/theme conflict debugging.
 */
class SITEINTELIX_Safe_Mode_Debugger_Module {

	const META_KEY          = 'siteintelix_safe_mode_state';
	const LOG_META_KEY      = 'siteintelix_safe_mode_log';
	const COOKIE_NAME       = 'siteintelix_safe_mode';
	const MU_FILENAME       = 'siteintelix-safe-mode.php';
	const DEFAULT_EXPIRY    = 14400;
	const PLUGIN_MODE_KEEP  = 'keep';
	const PLUGIN_MODE_NONE  = 'none';
	const PLUGIN_MODE_ONLY  = 'only';
	const THEME_MODE_KEEP   = 'keep';
	const THEME_MODE_SWITCH = 'switch';

	/**
	 * Cached active state.
	 *
	 * @var array<string,mixed>|null
	 */
	private static $active_state = null;

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 36 );
		add_action( 'admin_post_siteintelix_start_safe_mode', array( __CLASS__, 'handle_start' ) );
		add_action( 'admin_post_siteintelix_stop_safe_mode', array( __CLASS__, 'handle_stop' ) );
		add_action( 'admin_post_siteintelix_reset_safe_mode', array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_mu_plugin' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar' ), 79 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_frontend_badge' ), 99 );
		add_filter( 'template', array( __CLASS__, 'filter_template' ), 99 );
		add_filter( 'stylesheet', array( __CLASS__, 'filter_stylesheet' ), 99 );
		add_filter( 'pre_option_wp_debug', array( __CLASS__, 'filter_wp_debug_option' ) );
		add_action( 'init', array( __CLASS__, 'maybe_define_debug_flags' ), 1 );
	}

	/**
	 * Ensure the early MU bootstrap exists when the module is enabled.
	 *
	 * @return true|WP_Error
	 */
	public static function activate() {
		return self::ensure_mu_plugin_file();
	}

	/**
	 * Stop the current user's Safe Mode when the module is disabled.
	 *
	 * @return void
	 */
	public static function deactivate() {
		if ( is_user_logged_in() ) {
			self::stop_for_user( get_current_user_id(), 'stopped' );
		}

		if ( class_exists( 'SITEINTELIX_MU_Files' ) ) {
			SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_SAFE_MODE );
		}
	}

	/**
	 * Repair missing MU bootstrap for already-enabled installs.
	 *
	 * @return void
	 */
	public static function maybe_ensure_mu_plugin() {
		if ( SITEINTELIX_Security::can_manage_global_tools() ) {
			self::ensure_mu_plugin_file();
		}
	}

	/**
	 * Register Safe Mode submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return;
		}

		add_submenu_page(
			'siteintelix',
			__( 'Safe Mode Debugger', 'siteintelix' ),
			__( 'Safe Mode', 'siteintelix' ),
			'manage_options',
			'siteintelix-safe-mode',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Safe Mode page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to use Safe Mode.', 'siteintelix' ) );
		}

		require SITEINTELIX_PLUGIN_DIR . 'admin/views/safe-mode-page.php';
	}

	/**
	 * Start Safe Mode for the current admin session.
	 *
	 * @return void
	 */
	public static function handle_start() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to start Safe Mode.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_start_safe_mode' );

		$plugin_mode = isset( $_POST['siteintelix_plugin_mode'] ) ? sanitize_key( wp_unslash( $_POST['siteintelix_plugin_mode'] ) ) : self::PLUGIN_MODE_KEEP;
		if ( ! in_array( $plugin_mode, array( self::PLUGIN_MODE_KEEP, self::PLUGIN_MODE_NONE, self::PLUGIN_MODE_ONLY ), true ) ) {
			$plugin_mode = self::PLUGIN_MODE_KEEP;
		}

			$selected_plugins = array();
			if ( self::PLUGIN_MODE_ONLY === $plugin_mode && isset( $_POST['siteintelix_safe_plugins'] ) && is_array( $_POST['siteintelix_safe_plugins'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_plugin_basenames() immediately after unslashing.
				$selected_plugins = self::sanitize_plugin_basenames( wp_unslash( $_POST['siteintelix_safe_plugins'] ) );
			}

		$theme_mode = isset( $_POST['siteintelix_theme_mode'] ) ? sanitize_key( wp_unslash( $_POST['siteintelix_theme_mode'] ) ) : self::THEME_MODE_KEEP;
		if ( ! in_array( $theme_mode, array( self::THEME_MODE_KEEP, self::THEME_MODE_SWITCH ), true ) ) {
			$theme_mode = self::THEME_MODE_KEEP;
		}

			$selected_theme = '';
			if ( self::THEME_MODE_SWITCH === $theme_mode && isset( $_POST['siteintelix_safe_theme'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by sanitize_theme_stylesheet() immediately after unslashing.
				$selected_theme = self::sanitize_theme_stylesheet( wp_unslash( $_POST['siteintelix_safe_theme'] ) );
			if ( '' === $selected_theme ) {
				$theme_mode = self::THEME_MODE_KEEP;
			}
		}

		$debug_options = array(
			'wp_debug'     => ! empty( $_POST['siteintelix_debug_options']['wp_debug'] ),
			'script_debug' => ! empty( $_POST['siteintelix_debug_options']['script_debug'] ),
			'savequeries'  => ! empty( $_POST['siteintelix_debug_options']['savequeries'] ),
		);

		$token      = wp_generate_password( 48, false, false );
		$started_at = time();
		$state      = array(
			'enabled'          => true,
			'token_hash'       => self::hash_token( $token ),
			'user_id'          => get_current_user_id(),
			'plugin_mode'      => $plugin_mode,
			'selected_plugins' => $selected_plugins,
			'theme_mode'       => $theme_mode,
			'selected_theme'   => $selected_theme,
			'debug_options'    => $debug_options,
			'started_at'       => $started_at,
			'expires_at'       => $started_at + self::DEFAULT_EXPIRY,
		);

		update_user_meta( get_current_user_id(), self::META_KEY, $state );
		self::$active_state = null;
		self::set_cookie( get_current_user_id(), $token, $state['expires_at'] );
		self::log_action( 'started', __( 'Safe Mode started.', 'siteintelix' ) );

		if ( self::PLUGIN_MODE_ONLY === $plugin_mode || self::PLUGIN_MODE_NONE === $plugin_mode ) {
			self::log_action( 'plugin_set_changed', __( 'Temporary plugin set changed.', 'siteintelix' ) );
		}

		if ( self::THEME_MODE_SWITCH === $theme_mode ) {
			self::log_action( 'temporary_theme_changed', __( 'Temporary theme changed.', 'siteintelix' ) );
		}

		wp_safe_redirect( self::get_page_url( array( 'siteintelix_safe_mode_started' => '1' ) ) );
		exit;
	}

	/**
	 * Stop Safe Mode for current user.
	 *
	 * @return void
	 */
	public static function handle_stop() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to stop Safe Mode.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_stop_safe_mode' );
		self::stop_for_user( get_current_user_id(), 'stopped' );

		wp_safe_redirect( self::get_page_url( array( 'siteintelix_safe_mode_stopped' => '1' ) ) );
		exit;
	}

	/**
	 * Reset saved Safe Mode configuration for current user.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to reset Safe Mode.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_reset_safe_mode' );
		self::stop_for_user( get_current_user_id(), 'stopped' );
		delete_user_meta( get_current_user_id(), self::LOG_META_KEY );

		wp_safe_redirect( self::get_page_url( array( 'siteintelix_safe_mode_reset' => '1' ) ) );
		exit;
	}

	/**
	 * Get active Safe Mode state for the current request.
	 *
	 * @return array<string,mixed>|false
	 */
	public static function get_active_state() {
		if ( null !== self::$active_state ) {
			return self::$active_state;
		}

		self::$active_state = false;

		if ( ! is_user_logged_in() || ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return false;
		}

		$user_id = get_current_user_id();
		$state   = get_user_meta( $user_id, self::META_KEY, true );
		if ( ! is_array( $state ) || empty( $state['enabled'] ) ) {
			return false;
		}

		if ( empty( $state['expires_at'] ) || time() > absint( $state['expires_at'] ) ) {
			self::stop_for_user( $user_id, 'expired' );
			return false;
		}

		$cookie = self::get_cookie_parts();
		if ( ! $cookie || absint( $cookie['user_id'] ) !== $user_id || empty( $state['token_hash'] ) || ! hash_equals( (string) $state['token_hash'], self::hash_token( $cookie['token'] ) ) ) {
			return false;
		}

		self::$active_state = self::normalize_state( $state );
		return self::$active_state;
	}

	/**
	 * Get stored state without requiring a valid cookie.
	 *
	 * @return array<string,mixed>|false
	 */
	public static function get_saved_state() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$state = get_user_meta( get_current_user_id(), self::META_KEY, true );
		return is_array( $state ) ? self::normalize_state( $state ) : false;
	}

	/**
	 * Filter temporary parent theme.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function filter_template( $template ) {
		$state = self::get_active_state();
		if ( ! $state || self::THEME_MODE_SWITCH !== $state['theme_mode'] || empty( $state['selected_theme'] ) ) {
			return $template;
		}

		$theme = wp_get_theme( $state['selected_theme'] );
		return $theme->exists() ? $theme->get_template() : $template;
	}

	/**
	 * Filter temporary child/stylesheet theme.
	 *
	 * @param string $stylesheet Current stylesheet.
	 * @return string
	 */
	public static function filter_stylesheet( $stylesheet ) {
		$state = self::get_active_state();
		if ( ! $state || self::THEME_MODE_SWITCH !== $state['theme_mode'] || empty( $state['selected_theme'] ) ) {
			return $stylesheet;
		}

		$theme = wp_get_theme( $state['selected_theme'] );
		return $theme->exists() ? $theme->get_stylesheet() : $stylesheet;
	}

	/**
	 * Best-effort WP_DEBUG option override.
	 *
	 * @param mixed $value Pre-option value.
	 * @return mixed
	 */
	public static function filter_wp_debug_option( $value ) {
		$state = self::get_active_state();
		if ( $state && ! empty( $state['debug_options']['wp_debug'] ) ) {
			return true;
		}

		return $value;
	}

	/**
	 * Define debug flags when WordPress has not already defined them.
	 *
	 * @return void
	 */
	public static function maybe_define_debug_flags() {
		$state = self::get_active_state();
		if ( ! $state ) {
			return;
		}

		if ( ! empty( $state['debug_options']['wp_debug'] ) && ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
		}

		if ( ! empty( $state['debug_options']['script_debug'] ) && ! defined( 'SCRIPT_DEBUG' ) ) {
			define( 'SCRIPT_DEBUG', true );
		}

		if ( ! empty( $state['debug_options']['savequeries'] ) && ! defined( 'SAVEQUERIES' ) ) {
			define( 'SAVEQUERIES', true );
		}
	}

	/**
	 * Add Safe Mode admin bar indicator.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function register_admin_bar( $wp_admin_bar ) {
		if ( ! is_admin_bar_showing() || ! self::get_active_state() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'siteintelix-safe-mode-active',
				'title' => __( 'SiteIntelix Safe Mode Active', 'siteintelix' ),
				'href'  => self::get_page_url(),
				'meta'  => array(
					'class' => 'siteintelix-safe-mode-admin-bar',
					'title' => __( 'Safe Mode is active only for your session.', 'siteintelix' ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'siteintelix-safe-mode-view',
				'parent' => 'siteintelix-safe-mode-active',
				'title'  => __( 'View Safe Mode page', 'siteintelix' ),
				'href'   => self::get_page_url(),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'siteintelix-safe-mode-stop',
				'parent' => 'siteintelix-safe-mode-active',
				'title'  => __( 'Stop Safe Mode', 'siteintelix' ),
				'href'   => wp_nonce_url(
					add_query_arg( 'action', 'siteintelix_stop_safe_mode', admin_url( 'admin-post.php' ) ),
					'siteintelix_stop_safe_mode'
				),
			)
		);
	}

	/**
	 * Render admin notice on SiteIntelix pages.
	 *
	 * @return void
	 */
	public static function render_admin_notice() {
		if ( ! function_exists( 'siteintelix_is_admin_screen' ) || ! siteintelix_is_admin_screen() || ! self::get_active_state() ) {
			return;
		}

		echo '<div class="sitx-alert sitx-alert--info siteintelix-safe-mode-notice"><div class="sitx-alert__icon"><span class="dashicons dashicons-shield-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title">' . esc_html__( 'Safe Mode is active for your session only.', 'siteintelix' ) . '</strong><p class="sitx-alert__msg">' . esc_html__( 'Visitors and other administrators are not affected.', 'siteintelix' ) . '</p></div></div>';
	}

	/**
	 * Render frontend badge for current admin only.
	 *
	 * @return void
	 */
	public static function render_frontend_badge() {
		if ( is_admin() || ! self::get_active_state() ) {
			return;
		}

		$stop_url = wp_nonce_url(
			add_query_arg( 'action', 'siteintelix_stop_safe_mode', admin_url( 'admin-post.php' ) ),
			'siteintelix_stop_safe_mode'
		);
		?>
		<div class="siteintelix-safe-mode-frontend-badge">
			<span><?php esc_html_e( 'Safe Mode Active', 'siteintelix' ); ?></span>
			<a href="<?php echo esc_url( $stop_url ); ?>"><?php esc_html_e( 'Exit', 'siteintelix' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Get installed plugin choices.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_plugins_for_form() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins        = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$choices        = array();

		foreach ( $plugins as $basename => $data ) {
			$choices[ $basename ] = array(
				'name'        => isset( $data['Name'] ) ? $data['Name'] : $basename,
				'version'     => isset( $data['Version'] ) ? $data['Version'] : '',
				'basename'    => $basename,
				'is_active'   => in_array( $basename, $active_plugins, true ),
				'is_required' => self::get_siteintelix_basename() === $basename,
			);
		}

		uasort(
			$choices,
			function ( $a, $b ) {
				return strcasecmp( (string) $a['name'], (string) $b['name'] );
			}
		);

		return $choices;
	}

	/**
	 * Get installed theme choices.
	 *
	 * @return array<string,WP_Theme>
	 */
	public static function get_themes_for_form() {
		$themes = wp_get_themes();
		uksort( $themes, 'strcasecmp' );
		return $themes;
	}

	/**
	 * Get recent Safe Mode log entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_log_entries() {
		$log = get_user_meta( get_current_user_id(), self::LOG_META_KEY, true );
		return is_array( $log ) ? array_slice( array_reverse( $log ), 0, 8 ) : array();
	}

	/**
	 * Stop safe mode and clear cookie.
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  stopped|expired.
	 * @return void
	 */
	private static function stop_for_user( $user_id, $reason ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		$state = get_user_meta( $user_id, self::META_KEY, true );
		if ( is_array( $state ) ) {
			$state['enabled']    = false;
			$state['stopped_at'] = time();
			update_user_meta( $user_id, self::META_KEY, $state );
		}

		self::clear_cookie();
		self::$active_state = null;
		self::log_action( $reason, 'expired' === $reason ? __( 'Safe Mode expired automatically.', 'siteintelix' ) : __( 'Safe Mode stopped.', 'siteintelix' ), $user_id );
	}

	/**
	 * Log a Safe Mode action.
	 *
	 * @param string $action  Action key.
	 * @param string $message Message.
	 * @param int    $user_id Optional user ID.
	 * @return void
	 */
	private static function log_action( $action, $message, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$log = get_user_meta( $user_id, self::LOG_META_KEY, true );
		$log = is_array( $log ) ? $log : array();
		$log[] = array(
			'action'     => sanitize_key( $action ),
			'message'    => sanitize_text_field( $message ),
			'created_at' => time(),
		);

		update_user_meta( $user_id, self::LOG_META_KEY, array_slice( $log, -50 ) );
	}

	/**
	 * Normalize stored state.
	 *
	 * @param array<string,mixed> $state Raw state.
	 * @return array<string,mixed>
	 */
	private static function normalize_state( $state ) {
		$state['enabled']          = ! empty( $state['enabled'] );
		$state['user_id']          = isset( $state['user_id'] ) ? absint( $state['user_id'] ) : 0;
		$state['plugin_mode']      = isset( $state['plugin_mode'] ) ? sanitize_key( $state['plugin_mode'] ) : self::PLUGIN_MODE_KEEP;
		$state['selected_plugins'] = isset( $state['selected_plugins'] ) && is_array( $state['selected_plugins'] ) ? self::sanitize_plugin_basenames( $state['selected_plugins'] ) : array();
		$state['theme_mode']       = isset( $state['theme_mode'] ) ? sanitize_key( $state['theme_mode'] ) : self::THEME_MODE_KEEP;
		$state['selected_theme']   = isset( $state['selected_theme'] ) ? sanitize_key( $state['selected_theme'] ) : '';
		$state['debug_options']    = isset( $state['debug_options'] ) && is_array( $state['debug_options'] ) ? $state['debug_options'] : array();
		$state['started_at']       = isset( $state['started_at'] ) ? absint( $state['started_at'] ) : 0;
		$state['expires_at']       = isset( $state['expires_at'] ) ? absint( $state['expires_at'] ) : 0;

		foreach ( array( 'wp_debug', 'script_debug', 'savequeries' ) as $key ) {
			$state['debug_options'][ $key ] = ! empty( $state['debug_options'][ $key ] );
		}

		return $state;
	}

	/**
	 * Sanitize plugin basenames against installed plugins.
	 *
	 * @param array<int,string> $plugins Raw plugin basenames.
	 * @return string[]
	 */
	private static function sanitize_plugin_basenames( $plugins ) {
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$clean     = array();

		foreach ( (array) $plugins as $plugin ) {
			$plugin = plugin_basename( sanitize_text_field( (string) $plugin ) );
			if ( isset( $installed[ $plugin ] ) ) {
				$clean[] = $plugin;
			}
		}

		$siteintelix = self::get_siteintelix_basename();
		if ( $siteintelix && isset( $installed[ $siteintelix ] ) ) {
			$clean[] = $siteintelix;
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Validate theme stylesheet.
	 *
	 * @param string $stylesheet Raw stylesheet.
	 * @return string
	 */
	private static function sanitize_theme_stylesheet( $stylesheet ) {
		$stylesheet = sanitize_key( (string) $stylesheet );
		$theme      = wp_get_theme( $stylesheet );
		return $theme->exists() ? $theme->get_stylesheet() : '';
	}

	/**
	 * Hash a token for storage/comparison.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function hash_token( $token ) {
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'siteintelix-safe-mode' );
		return hash_hmac( 'sha256', (string) $token, (string) $salt );
	}

	/**
	 * Set secure Safe Mode cookie.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $token      Plain token.
	 * @param int    $expires_at Expiry timestamp.
	 * @return void
	 */
	private static function set_cookie( $user_id, $token, $expires_at ) {
		$value  = absint( $user_id ) . ':' . rawurlencode( $token );
		$secure = is_ssl();

		setcookie(
			self::COOKIE_NAME,
			$value,
			array(
				'expires'  => absint( $expires_at ),
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE_NAME ] = $value;
	}

	/**
	 * Clear Safe Mode cookie.
	 *
	 * @return void
	 */
	private static function clear_cookie() {
		setcookie(
			self::COOKIE_NAME,
			'',
			array(
				'expires'  => time() - HOUR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		unset( $_COOKIE[ self::COOKIE_NAME ] );
	}

	/**
	 * Parse Safe Mode cookie.
	 *
	 * @return array{user_id:int,token:string}|false
	 */
	private static function get_cookie_parts() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return false;
		}

		$value = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
		$parts = explode( ':', $value, 2 );
		if ( 2 !== count( $parts ) || ! absint( $parts[0] ) || '' === $parts[1] ) {
			return false;
		}

		return array(
			'user_id' => absint( $parts[0] ),
			'token'   => rawurldecode( $parts[1] ),
		);
	}

	/**
	 * Get SiteIntelix plugin basename.
	 *
	 * @return string
	 */
	private static function get_siteintelix_basename() {
		return plugin_basename( SITEINTELIX_PLUGIN_DIR . 'siteintelix.php' );
	}

	/**
	 * Get Safe Mode page URL.
	 *
	 * @param array<string,string> $args Optional query args.
	 * @return string
	 */
	private static function get_page_url( $args = array() ) {
		$args = array_merge( array( 'page' => 'siteintelix-safe-mode' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Ensure early Safe Mode MU plugin exists.
	 *
	 * @return true|WP_Error
	 */
	private static function ensure_mu_plugin_file() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		global $wp_filesystem;

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return new WP_Error( 'siteintelix_safe_mode_fs', __( 'Could not initialize the WordPress filesystem.', 'siteintelix' ) );
		}

		$mu_dir = trailingslashit( WPMU_PLUGIN_DIR );
		if ( ! $wp_filesystem->is_dir( $mu_dir ) && ! $wp_filesystem->mkdir( $mu_dir, FS_CHMOD_DIR ) ) {
			return new WP_Error( 'siteintelix_safe_mode_mu_dir', __( 'Could not create the mu-plugins directory for Safe Mode.', 'siteintelix' ) );
		}

		$file    = $mu_dir . self::MU_FILENAME;
		$content = self::get_mu_plugin_contents( self::get_siteintelix_basename() );

		if ( ! $wp_filesystem->put_contents( $file, $content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'siteintelix_safe_mode_mu_write', __( 'Could not write the Safe Mode bootstrap file.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Build MU plugin contents.
	 *
	 * @param string $siteintelix_basename Plugin basename.
	 * @return string
	 */
	private static function get_mu_plugin_contents( $siteintelix_basename ) {
		$siteintelix_basename = var_export( (string) $siteintelix_basename, true );

		return "<?php\n"
			. "/**\n"
			. " * Plugin Name: SiteIntelix Safe Mode\n"
			. " * Description: Applies private, session-based plugin and theme isolation before normal plugins load.\n"
			. " * Version: " . SITEINTELIX_VERSION . "\n"
			. " * Author: Parag Das\n"
			. " *\n"
			. " * SiteIntelix Safe Mode bootstrap.\n"
			. " *\n"
			. " * @package SiteIntelix\n"
			. " */\n\n"
			. "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n"
			. "if ( ! function_exists( 'siteintelix_safe_mode_hash_token' ) ) {\n\tfunction siteintelix_safe_mode_hash_token( \$token ) {\n\t\t\$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'siteintelix-safe-mode' );\n\t\treturn hash_hmac( 'sha256', (string) \$token, (string) \$salt );\n\t}\n}\n\n"
			. "if ( ! function_exists( 'siteintelix_safe_mode_state' ) ) {\n\tfunction siteintelix_safe_mode_state() {\n\t\tif ( empty( \$_COOKIE['siteintelix_safe_mode'] ) ) {\n\t\t\treturn false;\n\t\t}\n\n\t\t\$cookie = sanitize_text_field( wp_unslash( \$_COOKIE['siteintelix_safe_mode'] ) );\n\t\t\$parts  = explode( ':', \$cookie, 2 );\n\t\tif ( 2 !== count( \$parts ) || ! absint( \$parts[0] ) || '' === \$parts[1] ) {\n\t\t\treturn false;\n\t\t}\n\n\t\t\$user_id = absint( \$parts[0] );\n\t\t\$token   = rawurldecode( \$parts[1] );\n\t\t\$state   = get_user_meta( \$user_id, 'siteintelix_safe_mode_state', true );\n\t\tif ( ! is_array( \$state ) || empty( \$state['enabled'] ) || empty( \$state['token_hash'] ) ) {\n\t\t\treturn false;\n\t\t}\n\n\t\tif ( empty( \$state['expires_at'] ) || time() > absint( \$state['expires_at'] ) ) {\n\t\t\t\$state['enabled'] = false;\n\t\t\t\$state['stopped_at'] = time();\n\t\t\tupdate_user_meta( \$user_id, 'siteintelix_safe_mode_state', \$state );\n\t\t\treturn false;\n\t\t}\n\n\t\tif ( empty( \$state['user_id'] ) || absint( \$state['user_id'] ) !== \$user_id ) {\n\t\t\treturn false;\n\t\t}\n\n\t\tif ( ! hash_equals( (string) \$state['token_hash'], siteintelix_safe_mode_hash_token( \$token ) ) ) {\n\t\t\treturn false;\n\t\t}\n\n\t\treturn \$state;\n\t}\n}\n\n"
			. "if ( ! function_exists( 'siteintelix_safe_mode_filter_plugins' ) ) {\n\tfunction siteintelix_safe_mode_filter_plugins( \$plugins ) {\n\t\t\$state = siteintelix_safe_mode_state();\n\t\tif ( ! \$state || empty( \$state['plugin_mode'] ) || 'keep' === \$state['plugin_mode'] ) {\n\t\t\treturn \$plugins;\n\t\t}\n\n\t\t\$siteintelix = {$siteintelix_basename};\n\t\tif ( 'none' === \$state['plugin_mode'] ) {\n\t\t\treturn in_array( \$siteintelix, (array) \$plugins, true ) ? array( \$siteintelix ) : array();\n\t\t}\n\n\t\tif ( 'only' === \$state['plugin_mode'] ) {\n\t\t\t\$selected = isset( \$state['selected_plugins'] ) && is_array( \$state['selected_plugins'] ) ? \$state['selected_plugins'] : array();\n\t\t\t\$selected[] = \$siteintelix;\n\t\t\t\$selected = array_values( array_unique( array_map( 'plugin_basename', \$selected ) ) );\n\t\t\treturn array_values( array_intersect( (array) \$plugins, \$selected ) );\n\t\t}\n\n\t\treturn \$plugins;\n\t}\n}\n\nadd_filter( 'option_active_plugins', 'siteintelix_safe_mode_filter_plugins', 1 );\n\n"
			. "if ( ! function_exists( 'siteintelix_safe_mode_filter_network_plugins' ) ) {\n\tfunction siteintelix_safe_mode_filter_network_plugins( \$plugins ) {\n\t\t\$state = siteintelix_safe_mode_state();\n\t\tif ( ! \$state || empty( \$state['plugin_mode'] ) || 'keep' === \$state['plugin_mode'] ) {\n\t\t\treturn \$plugins;\n\t\t}\n\n\t\t\$siteintelix = {$siteintelix_basename};\n\t\tif ( 'none' === \$state['plugin_mode'] ) {\n\t\t\treturn isset( \$plugins[ \$siteintelix ] ) ? array( \$siteintelix => \$plugins[ \$siteintelix ] ) : array();\n\t\t}\n\n\t\tif ( 'only' === \$state['plugin_mode'] ) {\n\t\t\t\$selected = isset( \$state['selected_plugins'] ) && is_array( \$state['selected_plugins'] ) ? \$state['selected_plugins'] : array();\n\t\t\t\$selected[] = \$siteintelix;\n\t\t\t\$selected = array_values( array_unique( array_map( 'plugin_basename', \$selected ) ) );\n\t\t\treturn array_intersect_key( (array) \$plugins, array_flip( \$selected ) );\n\t\t}\n\n\t\treturn \$plugins;\n\t}\n}\n\nadd_filter( 'site_option_active_sitewide_plugins', 'siteintelix_safe_mode_filter_network_plugins', 1 );\n\n"
			. "if ( ! function_exists( 'siteintelix_safe_mode_neutralize_legacy_wp_safe_mode' ) ) {\n\tfunction siteintelix_safe_mode_neutralize_legacy_wp_safe_mode( \$value ) {\n\t\t\$state = siteintelix_safe_mode_state();\n\t\tif ( ! \$state ) {\n\t\t\treturn \$value;\n\t\t}\n\n\t\treturn array(\n\t\t\t'load_mu_plugins'   => true,\n\t\t\t'disable_themes'    => false,\n\t\t\t'default_themes'    => array(),\n\t\t\t'disable_plugins'   => false,\n\t\t\t'plugins_to_keep'   => array(),\n\t\t\t'plugins_to_enable' => array(),\n\t\t);\n\t}\n}\n\nadd_filter( 'pre_option_wp_safe_mode_settings', 'siteintelix_safe_mode_neutralize_legacy_wp_safe_mode', 1 );\n";
	}
}
