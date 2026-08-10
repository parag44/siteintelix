<?php
/**
 * User Switcher toolbar and fallback notice.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes active impersonation visually obvious.
 */
class SITEINTELIX_User_Switcher_Toolbar {

	/**
	 * Register display hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'show_admin_bar', array( __CLASS__, 'force_admin_bar' ), PHP_INT_MAX );
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_toolbar_nodes' ), 999 );
		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notice' ) );
		add_action( 'wp_head', array( __CLASS__, 'render_toolbar_style' ) );
		add_action( 'admin_head', array( __CLASS__, 'render_toolbar_style' ) );
	}

	/**
	 * Keep the return control visible during a trusted switching session.
	 *
	 * @param bool $show Whether WordPress would otherwise show the toolbar.
	 * @return bool
	 */
	public static function force_admin_bar( $show ) {
		if ( SITEINTELIX_User_Switcher_Session_Manager::get_active_session() ) {
			return true;
		}

		return (bool) $show;
	}

	/**
	 * Add a persistent viewing-as message and return control.
	 *
	 * @param WP_Admin_Bar $admin_bar Toolbar object.
	 * @return void
	 */
	public static function add_toolbar_nodes( $admin_bar ) {
		$context = self::get_context();
		if ( ! $context ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'siteintelix-user-switcher',
				'title' => sprintf(
					/* translators: %s: impersonated account display name. */
					__( 'Viewing as %s', 'siteintelix' ),
					esc_html( $context['target']->display_name )
				),
				'href'  => SITEINTELIX_User_Switcher_Admin_Actions::restore_url(),
				'meta'  => array( 'class' => 'siteintelix-user-switcher-active' ),
			)
		);
		$admin_bar->add_node(
			array(
				'parent' => 'siteintelix-user-switcher',
				'id'     => 'siteintelix-user-switcher-return',
				'title'  => sprintf(
					/* translators: %s: original account display name. */
					__( 'Return to %s', 'siteintelix' ),
					esc_html( $context['original']->display_name )
				),
				'href'   => SITEINTELIX_User_Switcher_Admin_Actions::restore_url(),
			)
		);
	}

	/**
	 * Show a fallback in wp-admin when the toolbar is unavailable.
	 *
	 * @return void
	 */
	public static function render_admin_notice() {
		if ( is_admin_bar_showing() ) {
			return;
		}
		$context = self::get_context();
		if ( ! $context ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong>
					<?php
					printf(
						/* translators: %s: impersonated account display name. */
						esc_html__( 'You are viewing the site as %s.', 'siteintelix' ),
						esc_html( $context['target']->display_name )
					);
					?>
				</strong>
				<a class="button button-primary" href="<?php echo esc_url( SITEINTELIX_User_Switcher_Admin_Actions::restore_url() ); ?>">
					<?php
					printf(
						/* translators: %s: original account display name. */
						esc_html__( 'Return to %s', 'siteintelix' ),
						esc_html( $context['original']->display_name )
					);
					?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Emphasize the toolbar state without loading a frontend stylesheet.
	 *
	 * @return void
	 */
	public static function render_toolbar_style() {
		if ( ! self::get_context() || ! is_admin_bar_showing() ) {
			return;
		}
		?>
		<style id="siteintelix-user-switcher-toolbar-css">
			#wpadminbar #wp-admin-bar-siteintelix-user-switcher > .ab-item {
				background: #b45309;
				color: #fff;
				font-weight: 700;
			}
			#wpadminbar #wp-admin-bar-siteintelix-user-switcher:hover > .ab-item,
			#wpadminbar #wp-admin-bar-siteintelix-user-switcher > .ab-item:focus {
				background: #92400e;
				color: #fff;
			}
		</style>
		<?php
	}

	/**
	 * Resolve trusted session users for display.
	 *
	 * @return array{original:WP_User,target:WP_User}|false
	 */
	private static function get_context() {
		$session = SITEINTELIX_User_Switcher_Session_Manager::get_active_session();
		if ( ! $session ) {
			return false;
		}

		$original = get_user_by( 'id', absint( $session['original_user_id'] ) );
		$target   = get_user_by( 'id', absint( $session['target_user_id'] ) );
		if ( ! $original instanceof WP_User || ! $target instanceof WP_User ) {
			return false;
		}
		return array(
			'original' => $original,
			'target'   => $target,
		);
	}
}
