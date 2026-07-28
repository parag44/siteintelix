<?php
/**
 * SMTP module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routes WordPress email through a configured SMTP server.
 */
class SITEINTELIX_SMTP_Module {

	const SETTINGS_OPTION = 'siteintelix_smtp_settings';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_phpmailer' ), 20 );

		if ( is_admin() ) {
			add_action( 'admin_post_siteintelix_save_smtp_settings', array( __CLASS__, 'handle_save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
	}

	/**
	 * Add default settings when the module is enabled.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::get_default_settings(), '', false );
		}
	}

	/**
	 * Configure PHPMailer for SMTP delivery.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public static function configure_phpmailer( $phpmailer ) {
		$settings = self::get_settings();

		if ( empty( $settings['smtp_enabled'] ) || empty( $settings['host'] ) ) {
			return;
		}

		self::apply_settings_to_mailer( $phpmailer, $settings );
	}

	/**
	 * Render SMTP settings tab.
	 *
	 * @param string[] $enabled_modules Enabled module IDs.
	 * @param string   $active_tab      Active module tab.
	 * @return void
	 */
	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'smtp', (array) $enabled_modules, true ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<section class="sitx-settings-panel-tab <?php echo 'smtp' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-smtp-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-smtp" data-siteintelix-settings-panel="smtp" <?php echo 'smtp' === $active_tab ? '' : 'hidden'; ?>>
			<div class="sitx-settings-content-grid">
				<div class="sitx-settings-main">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form">
						<input type="hidden" name="action" value="siteintelix_save_smtp_settings">
						<?php wp_nonce_field( 'siteintelix_save_smtp_settings' ); ?>

						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Enable SMTP Delivery', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Route WordPress emails through your SMTP server when configured.', 'siteintelix' ); ?></p></div>
							<label class="sitx-toggle"><input type="checkbox" name="smtp_enabled" value="1" <?php checked( 1, absint( $settings['smtp_enabled'] ) ); ?>><span class="sitx-toggle__slider"></span></label>
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'SMTP Host', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Example: smtp.gmail.com or mail.example.com.', 'siteintelix' ); ?></p></div>
							<input type="text" name="host" value="<?php echo esc_attr( $settings['host'] ); ?>" autocomplete="off">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Port', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Common values: 587 for TLS, 465 for SSL.', 'siteintelix' ); ?></p></div>
							<input type="number" name="port" min="1" max="65535" value="<?php echo esc_attr( (string) absint( $settings['port'] ) ); ?>">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Encryption', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Choose the encryption required by your SMTP provider.', 'siteintelix' ); ?></p></div>
							<select name="encryption">
								<option value="tls" <?php selected( $settings['encryption'], 'tls' ); ?>><?php esc_html_e( 'TLS', 'siteintelix' ); ?></option>
								<option value="ssl" <?php selected( $settings['encryption'], 'ssl' ); ?>><?php esc_html_e( 'SSL', 'siteintelix' ); ?></option>
								<option value="none" <?php selected( $settings['encryption'], 'none' ); ?>><?php esc_html_e( 'None', 'siteintelix' ); ?></option>
							</select>
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Authentication', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Most SMTP providers require a username and password.', 'siteintelix' ); ?></p></div>
							<label class="sitx-toggle"><input type="checkbox" name="auth" value="1" <?php checked( 1, absint( $settings['auth'] ) ); ?>><span class="sitx-toggle__slider"></span></label>
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Username', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Usually your SMTP account email address.', 'siteintelix' ); ?></p></div>
							<input type="text" name="username" value="<?php echo esc_attr( $settings['username'] ); ?>" autocomplete="username">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Password', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Leave blank to keep the saved password unchanged.', 'siteintelix' ); ?></p></div>
							<input type="password" name="password" value="" placeholder="<?php echo ! empty( $settings['password'] ) ? esc_attr__( 'Saved password unchanged', 'siteintelix' ) : ''; ?>" autocomplete="new-password">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'From Email', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Optional sender address applied to outgoing emails.', 'siteintelix' ); ?></p></div>
							<input type="email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'From Name', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Optional sender name shown in inboxes.', 'siteintelix' ); ?></p></div>
							<input type="text" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Timeout', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Connection timeout in seconds.', 'siteintelix' ); ?></p></div>
							<input type="number" name="timeout" min="5" max="120" value="<?php echo esc_attr( (string) absint( $settings['timeout'] ) ); ?>">
						</div>

						<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save SMTP Settings', 'siteintelix' ); ?></button>
					</form>
				</div>
				<aside class="sitx-settings-sidebar">
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'About SMTP', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'When this module is enabled and SMTP delivery is configured, SiteIntelix routes all WordPress emails through your SMTP provider.', 'siteintelix' ); ?></p>
					</div>
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'Tip', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'Use the Email Log module test email to verify SMTP delivery after saving these settings.', 'siteintelix' ); ?></p>
					</div>
				</aside>
			</div>
		</section>
		<?php
	}

	/**
	 * Save SMTP settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change SMTP settings.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_save_smtp_settings' );

		$current  = self::get_settings();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Valid password characters are preserved by bounded sanitize_password().
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		$settings = array(
			'smtp_enabled' => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
			'host'         => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
			'port'         => isset( $_POST['port'] ) ? min( 65535, max( 1, absint( wp_unslash( $_POST['port'] ) ) ) ) : 587,
			'encryption'   => isset( $_POST['encryption'] ) ? sanitize_key( wp_unslash( $_POST['encryption'] ) ) : 'tls',
			'auth'         => isset( $_POST['auth'] ) ? 1 : 0,
			'username'     => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
			'password'     => '' !== $password ? self::sanitize_password( $password ) : $current['password'],
			'from_email'   => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
			'from_name'    => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
			'timeout'      => isset( $_POST['timeout'] ) ? min( 120, max( 5, absint( wp_unslash( $_POST['timeout'] ) ) ) ) : 15,
		);

		if ( ! in_array( $settings['encryption'], array( 'none', 'ssl', 'tls' ), true ) ) {
			$settings['encryption'] = 'tls';
		}

		update_option( self::SETTINGS_OPTION, $settings, false );

		$verification = self::verify_connection( $settings );
		set_transient(
			'siteintelix_smtp_verify_notice_' . get_current_user_id(),
			$verification['message'],
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => 'siteintelix-settings',
					'siteintelix_settings_saved' => '1',
					'siteintelix_smtp_status'    => $verification['status'],
					'tab'                        => 'smtp',
				),
				admin_url( 'admin.php' )
			) . '#siteintelix-smtp-settings'
		);
		exit;
	}

	/**
	 * Verify the saved SMTP connection.
	 *
	 * @param array<string,mixed> $settings SMTP settings.
	 * @return array{status:string,message:string}
	 */
	private static function verify_connection( $settings ) {
		if ( empty( $settings['smtp_enabled'] ) ) {
			return array(
				'status'  => 'skipped',
				'message' => __( 'SMTP is disabled, so connection verification was skipped.', 'siteintelix' ),
			);
		}

		if ( empty( $settings['host'] ) ) {
			return array(
				'status'  => 'failed',
				'message' => __( 'SMTP verification failed: SMTP host is required.', 'siteintelix' ),
			);
		}

		if ( ! empty( $settings['auth'] ) && ( '' === (string) $settings['username'] || '' === (string) $settings['password'] ) ) {
			return array(
				'status'  => 'failed',
				'message' => __( 'SMTP verification failed: username and password are required when authentication is enabled.', 'siteintelix' ),
			);
		}

		self::load_phpmailer_classes();

		if ( ! class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			return array(
				'status'  => 'failed',
				'message' => __( 'SMTP verification failed: PHPMailer is not available.', 'siteintelix' ),
			);
		}

		try {
			$mailer = new \PHPMailer\PHPMailer\PHPMailer( true );
			self::apply_settings_to_mailer( $mailer, $settings );

			if ( $mailer->smtpConnect() ) {
				$mailer->smtpClose();

				return array(
					'status'  => 'success',
					'message' => sprintf(
						/* translators: 1: SMTP host, 2: SMTP port. */
						__( 'SMTP connection verified successfully for %1$s:%2$d.', 'siteintelix' ),
						$settings['host'],
						absint( $settings['port'] )
					),
				);
			}
		} catch ( \Exception $exception ) {
			return array(
				'status'  => 'failed',
				'message' => sprintf(
					/* translators: %s: SMTP error message. */
					__( 'SMTP verification failed: %s', 'siteintelix' ),
					$exception->getMessage()
				),
			);
		}

		return array(
			'status'  => 'failed',
			'message' => __( 'SMTP verification failed: the server refused the connection.', 'siteintelix' ),
		);
	}

	/**
	 * Load PHPMailer classes when WordPress has not loaded them yet.
	 *
	 * @return void
	 */
	private static function load_phpmailer_classes() {
		if ( class_exists( '\\PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			return;
		}

		$phpmailer_dir = ABSPATH . WPINC . '/PHPMailer/';
		if ( file_exists( $phpmailer_dir . 'Exception.php' ) ) {
			require_once $phpmailer_dir . 'Exception.php';
		}
		if ( file_exists( $phpmailer_dir . 'SMTP.php' ) ) {
			require_once $phpmailer_dir . 'SMTP.php';
		}
		if ( file_exists( $phpmailer_dir . 'PHPMailer.php' ) ) {
			require_once $phpmailer_dir . 'PHPMailer.php';
		}
	}

	/**
	 * Apply saved settings to a PHPMailer instance.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @param array<string,mixed>           $settings  SMTP settings.
	 * @return void
	 */
	private static function apply_settings_to_mailer( $phpmailer, $settings ) {
		$phpmailer->isSMTP();
		$phpmailer->Host       = (string) $settings['host'];
		$phpmailer->Port       = max( 1, absint( $settings['port'] ) );
		$phpmailer->SMTPAuth   = ! empty( $settings['auth'] );
		$phpmailer->SMTPSecure = in_array( $settings['encryption'], array( 'ssl', 'tls' ), true ) ? $settings['encryption'] : '';
		$phpmailer->Username   = (string) $settings['username'];
		$phpmailer->Password   = (string) $settings['password'];
		$phpmailer->Timeout    = max( 5, absint( $settings['timeout'] ) );
		$phpmailer->SMTPDebug  = 0;

		if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			$from_name = ! empty( $settings['from_name'] ) ? (string) $settings['from_name'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$phpmailer->setFrom( (string) $settings['from_email'], $from_name, false );
		}
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_default_settings() {
		return array(
			'smtp_enabled' => 0,
			'host'         => '',
			'port'         => 587,
			'encryption'   => 'tls',
			'auth'         => 1,
			'username'     => '',
			'password'     => '',
			'from_email'   => get_option( 'admin_email' ),
			'from_name'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'timeout'      => 15,
		);
	}

	/**
	 * Validate an SMTP password without destroying valid special characters.
	 *
	 * @param mixed $password Submitted password.
	 * @return string
	 */
	public static function sanitize_password( $password ) {
		if ( ! is_scalar( $password ) ) {
			return '';
		}

		return substr( str_replace( "\0", '', (string) $password ), 0, 1024 );
	}

	/**
	 * Current settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array_merge( self::get_default_settings(), is_array( $saved ) ? $saved : array() );
	}
}
