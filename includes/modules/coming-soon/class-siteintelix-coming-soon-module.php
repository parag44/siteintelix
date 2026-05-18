<?php
/**
 * Maintenance Mode module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows a maintenance page to visitors while admins keep access.
 */
class SITEINTELIX_Coming_Soon_Module {

	const SETTINGS_OPTION = 'siteintelix_coming_soon_settings';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_maintenance_page' ), 0 );

		if ( is_admin() ) {
			add_action( 'admin_post_siteintelix_save_coming_soon_settings', array( __CLASS__, 'handle_save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
	}

	/**
	 * Render settings section.
	 *
	 * @param string[] $enabled_modules Enabled module IDs.
	 * @param string   $active_tab      Active module tab.
	 * @return void
	 */
	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'coming_soon', (array) $enabled_modules, true ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<section class="sitx-settings-panel-tab <?php echo 'coming_soon' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-coming-soon-settings" data-siteintelix-settings-panel="coming_soon">
			<div class="sitx-settings-content-grid">
				<div class="sitx-settings-main">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form">
						<input type="hidden" name="action" value="siteintelix_save_coming_soon_settings">
						<?php wp_nonce_field( 'siteintelix_save_coming_soon_settings' ); ?>

						<div class="sitx-setting-row si-form-row">
							<div>
								<h3><?php esc_html_e( 'Public Mode', 'siteintelix' ); ?></h3>
								<p><?php esc_html_e( 'Visitors see this page while administrators can continue using the site after login.', 'siteintelix' ); ?></p>
							</div>
							<span class="sitx-badge sitx-badge--good"><?php esc_html_e( 'Active', 'siteintelix' ); ?></span>
						</div>

						<div class="sitx-form-grid">
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Logo URL', 'siteintelix' ); ?></span>
								<input type="url" name="logo_url" value="<?php echo esc_attr( $settings['logo_url'] ); ?>" placeholder="https://example.com/logo.png">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Eyebrow text', 'siteintelix' ); ?></span>
								<input type="text" name="eyebrow" value="<?php echo esc_attr( $settings['eyebrow'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Heading', 'siteintelix' ); ?></span>
								<input type="text" name="heading" value="<?php echo esc_attr( $settings['heading'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Button text', 'siteintelix' ); ?></span>
								<input type="text" name="button_text" value="<?php echo esc_attr( $settings['button_text'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Button URL', 'siteintelix' ); ?></span>
								<input type="url" name="button_url" value="<?php echo esc_attr( $settings['button_url'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Footer text', 'siteintelix' ); ?></span>
								<input type="text" name="footer_text" value="<?php echo esc_attr( $settings['footer_text'] ); ?>">
							</label>
							<label class="sitx-form-field sitx-form-field--full">
								<span><?php esc_html_e( 'Message', 'siteintelix' ); ?></span>
								<textarea name="message" rows="4"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
							</label>
						</div>

						<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Maintenance Mode Settings', 'siteintelix' ); ?></button>
					</form>
				</div>
				<aside class="sitx-settings-sidebar">
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'About Maintenance Mode', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'When enabled, visitors see a maintenance page and administrators can still browse the site while logged in.', 'siteintelix' ); ?></p>
					</div>
				</aside>
			</div>
		</section>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change Maintenance Mode settings.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_save_coming_soon_settings' );

		update_option(
			self::SETTINGS_OPTION,
			array(
				'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
				'eyebrow'     => isset( $_POST['eyebrow'] ) ? sanitize_text_field( wp_unslash( $_POST['eyebrow'] ) ) : '',
				'heading'     => isset( $_POST['heading'] ) ? sanitize_text_field( wp_unslash( $_POST['heading'] ) ) : '',
				'message'     => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
				'button_text' => isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '',
				'button_url'  => isset( $_POST['button_url'] ) ? esc_url_raw( wp_unslash( $_POST['button_url'] ) ) : '',
				'footer_text' => isset( $_POST['footer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_text'] ) ) : '',
			)
		);

		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-settings', 'siteintelix_settings_saved' => '1', 'tab' => 'coming_soon' ), admin_url( 'admin.php' ) ) . '#siteintelix-coming-soon-settings' );
		exit;
	}

	/**
	 * Maybe render maintenance page for visitors.
	 *
	 * @return void
	 */
	public static function maybe_render_maintenance_page() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		status_header( 503 );
		nocache_headers();
		header( 'Retry-After: 3600' );

		$settings = self::get_settings();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $settings['heading'] ); ?></title>
			<style>
				body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f7fb;color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
				.sitx-coming-soon{width:min(680px,calc(100% - 40px));background:#fff;border:1px solid #dbe4f0;border-radius:28px;box-shadow:0 28px 70px rgba(15,23,42,.12);padding:48px;text-align:center}
				.sitx-coming-soon img{max-height:74px;max-width:220px;margin:0 auto 24px;display:block}
				.sitx-coming-soon__eyebrow{color:#2563eb;font-size:13px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
				.sitx-coming-soon h1{font-size:clamp(34px,6vw,58px);line-height:1.02;margin:14px 0 18px}
				.sitx-coming-soon p{color:#64748b;font-size:18px;line-height:1.7;margin:0 auto 28px;max-width:540px}
				.sitx-coming-soon a{display:inline-flex;align-items:center;justify-content:center;background:#2563eb;border-radius:14px;color:#fff;font-weight:800;padding:14px 22px;text-decoration:none}
				.sitx-coming-soon__footer{color:#94a3b8;font-size:13px;margin-top:28px}
			</style>
		</head>
		<body>
			<main class="sitx-coming-soon">
				<?php if ( ! empty( $settings['logo_url'] ) ) : ?>
					<img src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php endif; ?>
				<div class="sitx-coming-soon__eyebrow"><?php echo esc_html( $settings['eyebrow'] ); ?></div>
				<h1><?php echo esc_html( $settings['heading'] ); ?></h1>
				<p><?php echo esc_html( $settings['message'] ); ?></p>
				<?php if ( ! empty( $settings['button_text'] ) && ! empty( $settings['button_url'] ) ) : ?>
					<a href="<?php echo esc_url( $settings['button_url'] ); ?>"><?php echo esc_html( $settings['button_text'] ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $settings['footer_text'] ) ) : ?>
					<div class="sitx-coming-soon__footer"><?php echo esc_html( $settings['footer_text'] ); ?></div>
				<?php endif; ?>
			</main>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,string>
	 */
	private static function get_settings() {
		$defaults = array(
			'logo_url'    => '',
			'eyebrow'     => __( 'Maintenance Mode', 'siteintelix' ),
			'heading'     => __( 'We are making something better.', 'siteintelix' ),
			'message'     => __( 'Our website is temporarily unavailable while we perform maintenance. Please check back soon.', 'siteintelix' ),
			'button_text' => __( 'Admin Login', 'siteintelix' ),
			'button_url'  => wp_login_url(),
			'footer_text' => get_bloginfo( 'name' ),
		);

		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}
}
