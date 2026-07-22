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
			<title><?php esc_html_e( 'Maintenance Mode', 'siteintelix' ); ?></title>
			<style>
				:root{--sitx-bg:#f5f8fc;--sitx-card:#fff;--sitx-border:#e4eaf3;--sitx-blue:#2377f3;--sitx-blue-dark:#1262d9;--sitx-blue-soft:#eaf2ff;--sitx-ink:#17243b;--sitx-muted:#60708a;--sitx-line:#b9d2fb;--sitx-radius:24px;--sitx-shadow:0 24px 70px rgba(31,54,91,.12)}
				*{box-sizing:border-box}
				html{min-height:100%}
				body{background:var(--sitx-bg);color:var(--sitx-ink);display:grid;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;margin:0;min-height:100vh;min-height:100svh;padding:clamp(16px,4vw,48px);place-items:center}
				.sitx-maintenance{animation:sitxFadeIn .42s ease-out both;background:var(--sitx-card);border:1px solid var(--sitx-border);border-radius:var(--sitx-radius);box-shadow:var(--sitx-shadow);padding:clamp(34px,5.5vw,62px) clamp(20px,5vw,72px);text-align:center;width:min(900px,100%)}
				.sitx-maintenance__inner{align-items:center;display:flex;flex-direction:column;margin:0 auto;max-width:680px}
				.sitx-maintenance__logo{display:block;margin:0 auto 30px;max-height:76px;max-width:210px;object-fit:contain}
				.sitx-maintenance__site{font-size:24px;font-weight:700;margin:0 0 30px}
				.sitx-maintenance__badge{align-items:center;background:var(--sitx-blue-soft);border-radius:999px;color:var(--sitx-blue-dark);display:inline-flex;font-size:13px;font-weight:700;gap:8px;letter-spacing:.1em;line-height:1;margin:0 0 26px;padding:9px 18px;text-transform:uppercase}
				.sitx-maintenance__badge svg{height:14px;width:14px}
				.sitx-maintenance h1{font-size:clamp(42px,7vw,68px);font-weight:800;letter-spacing:0;line-height:1.06;margin:0 auto 20px;max-width:720px}
				.sitx-maintenance__accent{color:var(--sitx-blue)}
				.sitx-maintenance__message{color:var(--sitx-muted);font-size:clamp(17px,2.4vw,21px);line-height:1.6;margin:0 auto 28px;max-width:560px}
				.sitx-maintenance__art{display:block;height:auto;margin:0 auto 30px;max-width:min(260px,68vw);width:100%}
				.sitx-maintenance__thanks{align-items:center;color:var(--sitx-muted);display:inline-flex;font-size:15px;gap:9px;line-height:1.45;margin:0}
				.sitx-maintenance__thanks svg{color:var(--sitx-blue);height:19px;width:19px}
				@keyframes sitxFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
				@media (prefers-reduced-motion:reduce){.sitx-maintenance{animation:none}}
				@media (max-width:560px){body{padding:16px}.sitx-maintenance{border-radius:20px;padding:32px 20px}.sitx-maintenance__logo{margin-bottom:24px;max-height:62px}.sitx-maintenance__badge{font-size:12px;margin-bottom:22px;padding:8px 14px}.sitx-maintenance h1{font-size:clamp(38px,12vw,48px)}.sitx-maintenance__message{font-size:16px;margin-bottom:24px}.sitx-maintenance__art{margin-bottom:24px;max-width:220px}}
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
				<div class="sitx-maintenance__badge" aria-label="<?php esc_attr_e( 'Maintenance mode', 'siteintelix' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5 5L4 17v3h3l5.7-5.7a4 4 0 0 0 5-5l-2.4 2.4-2.8-2.8 2.2-2.6z"/></svg>
					<?php esc_html_e( 'Maintenance Mode', 'siteintelix' ); ?>
				</div>
				<h1><?php echo wp_kses( __( 'We&rsquo;re making<br>something <span class="sitx-maintenance__accent">better.</span>', 'siteintelix' ), array( 'br' => array(), 'span' => array( 'class' => true ) ) ); ?></h1>
				<p class="sitx-maintenance__message"><?php esc_html_e( 'Our website is temporarily unavailable while we perform maintenance. Please check back soon.', 'siteintelix' ); ?></p>
				<?php self::render_maintenance_art(); ?>
				<p class="sitx-maintenance__thanks">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
					<span><?php esc_html_e( 'Thank you for your patience.', 'siteintelix' ); ?></span>
				</p>
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
		<svg class="sitx-maintenance__art" viewBox="0 0 260 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
			<circle cx="132" cy="90" r="66" fill="#eef5ff"/>
			<rect x="48" y="48" width="126" height="92" rx="10" fill="#fff" stroke="#8bb8fb" stroke-width="4"/>
			<path d="M50 72h122" stroke="#8bb8fb" stroke-width="4" stroke-linecap="round"/>
			<circle cx="66" cy="60" r="4" fill="#8bb8fb"/>
			<circle cx="80" cy="60" r="4" fill="#8bb8fb"/>
			<circle cx="94" cy="60" r="4" fill="#8bb8fb"/>
			<circle cx="172" cy="122" r="39" fill="#fff" stroke="#75a8f8" stroke-width="4"/>
			<path d="M186 104a23 23 0 0 0-31 29l-25 25h18l17-17a23 23 0 0 0 29-31l-13 13-8-8 13-11z" fill="#5d98f4"/>
			<path d="M26 146h208" stroke="#c6d8ee" stroke-width="3" stroke-linecap="round"/>
		</svg>
		<?php
	}
}
