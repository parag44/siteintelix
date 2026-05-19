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
		$logo_url = ! empty( $settings['logo_url'] ) ? $settings['logo_url'] : '';
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
							<div class="sitx-form-field sitx-form-field--full sitx-maintenance-logo-field">
								<span><?php esc_html_e( 'Logo', 'siteintelix' ); ?></span>
								<div class="sitx-maintenance-logo-picker" data-siteintelix-maintenance-logo-picker>
									<div class="sitx-maintenance-logo-preview" data-siteintelix-maintenance-logo-preview>
										<?php if ( $logo_url ) : ?>
											<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Selected logo preview', 'siteintelix' ); ?>">
										<?php else : ?>
											<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
										<?php endif; ?>
									</div>
									<div class="sitx-maintenance-logo-controls">
										<input type="hidden" name="logo_id" value="<?php echo esc_attr( absint( $settings['logo_id'] ) ); ?>" data-siteintelix-maintenance-logo-id>
										<input type="url" name="logo_url" value="<?php echo esc_attr( $logo_url ); ?>" placeholder="https://example.com/logo.png" data-siteintelix-maintenance-logo-url>
										<div class="sitx-maintenance-logo-actions">
											<button type="button" class="si-button si-button--secondary" data-siteintelix-maintenance-logo-select><?php esc_html_e( 'Choose from Media', 'siteintelix' ); ?></button>
											<button type="button" class="si-button si-button--ghost" data-siteintelix-maintenance-logo-remove><?php esc_html_e( 'Remove', 'siteintelix' ); ?></button>
										</div>
									</div>
								</div>
							</div>
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
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Notification title', 'siteintelix' ); ?></span>
								<input type="text" name="notify_title" value="<?php echo esc_attr( $settings['notify_title'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Notification text', 'siteintelix' ); ?></span>
								<input type="text" name="notify_text" value="<?php echo esc_attr( $settings['notify_text'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Notification URL', 'siteintelix' ); ?></span>
								<input type="url" name="notify_url" value="<?php echo esc_attr( $settings['notify_url'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 1 title', 'siteintelix' ); ?></span>
								<input type="text" name="feature_1_title" value="<?php echo esc_attr( $settings['feature_1_title'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 1 text', 'siteintelix' ); ?></span>
								<input type="text" name="feature_1_text" value="<?php echo esc_attr( $settings['feature_1_text'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 2 title', 'siteintelix' ); ?></span>
								<input type="text" name="feature_2_title" value="<?php echo esc_attr( $settings['feature_2_title'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 2 text', 'siteintelix' ); ?></span>
								<input type="text" name="feature_2_text" value="<?php echo esc_attr( $settings['feature_2_text'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 3 title', 'siteintelix' ); ?></span>
								<input type="text" name="feature_3_title" value="<?php echo esc_attr( $settings['feature_3_title'] ); ?>">
							</label>
							<label class="sitx-form-field">
								<span><?php esc_html_e( 'Feature 3 text', 'siteintelix' ); ?></span>
								<input type="text" name="feature_3_text" value="<?php echo esc_attr( $settings['feature_3_text'] ); ?>">
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
				'logo_id'     => isset( $_POST['logo_id'] ) ? absint( wp_unslash( $_POST['logo_id'] ) ) : 0,
				'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
				'eyebrow'     => isset( $_POST['eyebrow'] ) ? sanitize_text_field( wp_unslash( $_POST['eyebrow'] ) ) : '',
				'heading'     => isset( $_POST['heading'] ) ? sanitize_text_field( wp_unslash( $_POST['heading'] ) ) : '',
				'message'     => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
				'button_text' => isset( $_POST['button_text'] ) ? sanitize_text_field( wp_unslash( $_POST['button_text'] ) ) : '',
				'button_url'  => isset( $_POST['button_url'] ) ? esc_url_raw( wp_unslash( $_POST['button_url'] ) ) : '',
				'footer_text' => isset( $_POST['footer_text'] ) ? sanitize_text_field( wp_unslash( $_POST['footer_text'] ) ) : '',
				'notify_title' => isset( $_POST['notify_title'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_title'] ) ) : '',
				'notify_text' => isset( $_POST['notify_text'] ) ? sanitize_text_field( wp_unslash( $_POST['notify_text'] ) ) : '',
				'notify_url'  => isset( $_POST['notify_url'] ) ? esc_url_raw( wp_unslash( $_POST['notify_url'] ) ) : '',
				'feature_1_title' => isset( $_POST['feature_1_title'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_1_title'] ) ) : '',
				'feature_1_text' => isset( $_POST['feature_1_text'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_1_text'] ) ) : '',
				'feature_2_title' => isset( $_POST['feature_2_title'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_2_title'] ) ) : '',
				'feature_2_text' => isset( $_POST['feature_2_text'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_2_text'] ) ) : '',
				'feature_3_title' => isset( $_POST['feature_3_title'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_3_title'] ) ) : '',
				'feature_3_text' => isset( $_POST['feature_3_text'] ) ? sanitize_text_field( wp_unslash( $_POST['feature_3_text'] ) ) : '',
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
		$logo_url = self::get_logo_url( $settings );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<title><?php echo esc_html( $settings['heading'] ); ?></title>
			<style>
				:root{--sitx-blue:#1268f3;--sitx-blue-dark:#0756d7;--sitx-ink:#101a34;--sitx-muted:#536586;--sitx-soft:#eaf2ff}
				*{box-sizing:border-box}
				body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(circle at top,#fff 0,#f6faff 48%,#edf5ff 100%);color:var(--sitx-ink);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;padding:clamp(18px,4vw,48px)}
				.sitx-maintenance{width:min(1180px,100%);background:rgba(255,255,255,.92);border:1px solid #d8e4f5;border-radius:28px;box-shadow:0 24px 70px rgba(28,61,125,.14);overflow:hidden;padding:clamp(28px,5vw,58px);position:relative;text-align:center}
				.sitx-maintenance:before{content:"";position:absolute;inset:-20% -10% auto;height:54%;background:radial-gradient(circle,rgba(18,104,243,.08),rgba(18,104,243,0) 64%);pointer-events:none}
				.sitx-maintenance__inner{margin:0 auto;max-width:760px;position:relative;z-index:1}
				.sitx-maintenance__logo{display:block;margin:0 auto 28px;max-height:90px;max-width:230px;object-fit:contain}
				.sitx-maintenance__site{font-size:26px;font-weight:850;margin:0 0 28px}
				.sitx-maintenance__badge{align-items:center;background:var(--sitx-soft);border-radius:999px;color:var(--sitx-blue-dark);display:inline-flex;font-size:15px;font-weight:850;gap:10px;letter-spacing:.08em;margin-bottom:20px;padding:10px 22px;text-transform:uppercase}
				.sitx-maintenance__badge svg{height:18px;width:18px}
				.sitx-maintenance h1{font-size:clamp(44px,7vw,76px);font-weight:900;letter-spacing:0;line-height:.98;margin:0 auto 22px;max-width:760px}
				.sitx-maintenance__accent{color:var(--sitx-blue)}
				.sitx-maintenance__message{color:var(--sitx-muted);font-size:clamp(17px,2vw,23px);line-height:1.55;margin:0 auto 28px;max-width:700px}
				.sitx-maintenance__art{display:block;height:auto;margin:0 auto 8px;max-width:min(560px,100%);width:100%}
				.sitx-maintenance__button{align-items:center;background:linear-gradient(135deg,var(--sitx-blue),#0458e7);border-radius:999px;box-shadow:0 12px 28px rgba(18,104,243,.24);color:#fff;display:flex;font-size:18px;font-weight:850;gap:10px;justify-content:center;margin:6px auto 0;min-height:54px;padding:0 30px;text-decoration:none;width:max-content}
				.sitx-maintenance__button svg{height:20px;width:20px}
				.sitx-maintenance__divider{align-items:center;color:#8aa0c2;display:flex;font-size:13px;font-weight:800;gap:14px;justify-content:center;margin:28px auto 18px;max-width:390px;text-transform:uppercase}
				.sitx-maintenance__divider:before,.sitx-maintenance__divider:after{background:#d8e5f7;content:"";height:1px;flex:1}
				.sitx-maintenance__notify{align-items:center;color:var(--sitx-ink);display:grid;gap:12px;grid-template-columns:32px minmax(0,1fr) 24px;margin:0 auto;max-width:420px;text-align:left;text-decoration:none;width:max-content}
				.sitx-maintenance__notify-icon,.sitx-maintenance__notify-arrow{align-items:center;color:var(--sitx-blue);display:flex;justify-content:center}
				.sitx-maintenance__notify-icon svg{height:28px;width:28px}
				.sitx-maintenance__notify strong{display:block;font-size:17px;margin-bottom:3px}
				.sitx-maintenance__notify span{color:var(--sitx-muted);font-size:14px}
				.sitx-maintenance__features{align-items:center;display:grid;gap:18px;grid-template-columns:repeat(3,1fr);margin:44px auto 0;max-width:780px}
				.sitx-maintenance__feature{align-items:center;display:flex;gap:12px;justify-content:center;text-align:left}
				.sitx-maintenance__feature svg{color:var(--sitx-blue);flex:0 0 auto;height:34px;width:34px}
				.sitx-maintenance__feature strong{display:block;font-size:15px}
				.sitx-maintenance__feature span{color:var(--sitx-muted);font-size:13px}
				@media (max-width:700px){body{padding:14px}.sitx-maintenance{border-radius:22px;padding:30px 18px}.sitx-maintenance h1{font-size:42px}.sitx-maintenance__features{grid-template-columns:1fr}.sitx-maintenance__feature{justify-content:flex-start}.sitx-maintenance__notify{grid-template-columns:32px minmax(0,1fr);max-width:100%;width:100%}.sitx-maintenance__notify-arrow{display:none}}
			</style>
		</head>
		<body>
			<main class="sitx-maintenance">
				<div class="sitx-maintenance__inner">
				<?php if ( $logo_url ) : ?>
					<img class="sitx-maintenance__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<?php else : ?>
					<div class="sitx-maintenance__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
				<?php endif; ?>
				<div class="sitx-maintenance__badge">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5 5L4 17v3h3l5.7-5.7a4 4 0 0 0 5-5l-2.4 2.4-2.8-2.8 2.2-2.6z"/></svg>
					<?php echo esc_html( $settings['eyebrow'] ); ?>
				</div>
				<h1><?php echo wp_kses( self::format_heading( $settings['heading'] ), array( 'span' => array( 'class' => true ) ) ); ?></h1>
				<p class="sitx-maintenance__message"><?php echo esc_html( $settings['message'] ); ?></p>
				<?php self::render_maintenance_art(); ?>
				<?php if ( ! empty( $settings['button_text'] ) && ! empty( $settings['button_url'] ) ) : ?>
					<a class="sitx-maintenance__button" href="<?php echo esc_url( $settings['button_url'] ); ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l3-7 4 14 3-7h4"/></svg>
						<?php echo esc_html( $settings['button_text'] ); ?>
					</a>
				<?php endif; ?>
				<div class="sitx-maintenance__divider"><span><?php esc_html_e( 'Or', 'siteintelix' ); ?></span></div>
				<a class="sitx-maintenance__notify" href="<?php echo esc_url( $settings['notify_url'] ); ?>">
					<span class="sitx-maintenance__notify-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg></span>
					<span><strong><?php echo esc_html( $settings['notify_title'] ); ?></strong><span><?php echo esc_html( $settings['notify_text'] ); ?></span></span>
					<span class="sitx-maintenance__notify-arrow">&rsaquo;</span>
				</a>
				<div class="sitx-maintenance__features">
					<div class="sitx-maintenance__feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-5"/></svg><span><strong><?php echo esc_html( $settings['feature_1_title'] ); ?></strong><span><?php echo esc_html( $settings['feature_1_text'] ); ?></span></span></div>
					<div class="sitx-maintenance__feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg><span><strong><?php echo esc_html( $settings['feature_2_title'] ); ?></strong><span><?php echo esc_html( $settings['feature_2_text'] ); ?></span></span></div>
					<div class="sitx-maintenance__feature"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span><strong><?php echo esc_html( $settings['feature_3_title'] ); ?></strong><span><?php echo esc_html( $settings['feature_3_text'] ); ?></span></span></div>
				</div>
				<?php if ( ! empty( $settings['footer_text'] ) ) : ?>
					<div class="sitx-coming-soon__footer" hidden><?php echo esc_html( $settings['footer_text'] ); ?></div>
				<?php endif; ?>
				</div>
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
			'logo_id'     => 0,
			'logo_url'    => '',
			'eyebrow'     => __( 'Maintenance Mode', 'siteintelix' ),
			'heading'     => __( 'We are making something better.', 'siteintelix' ),
			'message'     => __( 'Our website is temporarily unavailable while we perform maintenance. Please check back soon.', 'siteintelix' ),
			'button_text' => __( 'Check Status', 'siteintelix' ),
			'button_url'  => home_url( '/' ),
			'footer_text' => get_bloginfo( 'name' ),
			'notify_title' => __( "Get notified when we're back", 'siteintelix' ),
			'notify_text' => __( "We'll send you an update once we're live.", 'siteintelix' ),
			'notify_url'  => wp_login_url(),
			'feature_1_title' => __( '99.9% Uptime', 'siteintelix' ),
			'feature_1_text' => __( 'Our commitment', 'siteintelix' ),
			'feature_2_title' => __( '24/7 Support', 'siteintelix' ),
			'feature_2_text' => __( "We're here for you", 'siteintelix' ),
			'feature_3_title' => __( 'Secure & Reliable', 'siteintelix' ),
			'feature_3_text' => __( 'Your data is safe', 'siteintelix' ),
		);

		$saved = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Resolve logo URL from the selected attachment, falling back to legacy URL.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function get_logo_url( $settings ) {
		$logo_id = isset( $settings['logo_id'] ) ? absint( $settings['logo_id'] ) : 0;
		if ( $logo_id ) {
			$image = wp_get_attachment_image_url( $logo_id, 'medium' );
			if ( $image ) {
				return $image;
			}
		}

		return ! empty( $settings['logo_url'] ) ? (string) $settings['logo_url'] : '';
	}

	/**
	 * Emphasize the last word in the default-style heading.
	 *
	 * @param string $heading Heading text.
	 * @return string
	 */
	private static function format_heading( $heading ) {
		$heading = trim( (string) $heading );
		if ( preg_match( '/^(.*\s)([^\s]+)$/', $heading, $matches ) ) {
			return esc_html( $matches[1] ) . '<span class="sitx-maintenance__accent">' . esc_html( $matches[2] ) . '</span>';
		}

		return esc_html( $heading );
	}

	/**
	 * Render the maintenance illustration.
	 *
	 * @return void
	 */
	private static function render_maintenance_art() {
		?>
		<svg class="sitx-maintenance__art" viewBox="0 0 620 260" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
			<defs>
				<linearGradient id="sitxArtBlue" x1="214" y1="54" x2="414" y2="226" gradientUnits="userSpaceOnUse">
					<stop stop-color="#9fc5ff"/>
					<stop offset="1" stop-color="#5f98f6"/>
				</linearGradient>
				<linearGradient id="sitxArtSoft" x1="86" y1="104" x2="538" y2="222" gradientUnits="userSpaceOnUse">
					<stop stop-color="#f2f7ff"/>
					<stop offset="1" stop-color="#dceaff"/>
				</linearGradient>
				<filter id="sitxArtShadow" x="176" y="48" width="284" height="196" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
					<feDropShadow dx="0" dy="14" stdDeviation="12" flood-color="#1268f3" flood-opacity=".12"/>
				</filter>
			</defs>

			<path d="M90 190c23-40 62-56 105-38 22-43 64-70 113-70 51 0 94 29 115 74 40-18 82-2 107 34H90z" fill="url(#sitxArtSoft)"/>

			<g opacity=".78">
				<path d="M134 188c-16-43-4-83 36-120 11 45-1 85-36 120z" fill="#d7e6ff"/>
				<path d="M162 191c22-38 55-57 101-57-21 39-54 58-101 57z" fill="#e6f0ff"/>
				<path d="M486 188c16-43 4-83-36-120-11 45 1 85 36 120z" fill="#d7e6ff"/>
				<path d="M458 191c-22-38-55-57-101-57 21 39 54 58 101 57z" fill="#e6f0ff"/>
			</g>

			<g filter="url(#sitxArtShadow)">
				<rect x="215" y="62" width="190" height="150" rx="20" fill="#fff" stroke="url(#sitxArtBlue)" stroke-width="10"/>
				<path d="M220 85c0-10 8-18 18-18h144c10 0 18 8 18 18v28H220V85z" fill="#86b5ff"/>
				<circle cx="246" cy="91" r="6" fill="#fff"/>
				<circle cx="268" cy="91" r="6" fill="#fff"/>
				<circle cx="290" cy="91" r="6" fill="#fff"/>

				<path d="M304 126h32l5 18 16-9 23 23-9 16 18 5v32h-32l-5-18-16 9-23-23 9-16-18-5v-32z" fill="#86b5ff"/>
				<circle cx="347" cy="169" r="25" fill="#fff"/>
			</g>

			<g>
				<circle cx="414" cy="183" r="57" fill="#fff" stroke="#78a9fa" stroke-width="11"/>
				<circle cx="414" cy="183" r="43" fill="#f7fbff"/>
				<path d="M432 161c-12-6-27-4-37 6-10 10-12 25-6 37l-31 31h24l21-21c12 6 27 4 37-6 10-10 12-25 6-37l-16 16-14-14 16-12z" fill="#1268f3"/>
			</g>

			<g>
				<path d="M128 210h52l-10-42h-32l-10 42z" fill="#78a9fa"/>
				<path d="M136 192h36M141 177h26" stroke="#fff" stroke-width="7" stroke-linecap="round"/>
				<path d="M116 211h76" stroke="#d3e4ff" stroke-width="8" stroke-linecap="round"/>
			</g>
		</svg>
		<?php
	}
}
