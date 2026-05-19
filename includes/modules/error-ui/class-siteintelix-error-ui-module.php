<?php
/**
 * Error Handler module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WordPress error drop-ins and public error page copy.
 */
class SITEINTELIX_Error_UI_Module {

	const SETTINGS_OPTION = 'siteintelix_error_ui_settings';
	const VERSION_OPTION  = 'siteintelix_error_ui_dropins_version';
	const DROPIN_VERSION  = '2.6.1-error-details';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_refresh_dropins' ) );
			add_action( 'admin_post_siteintelix_save_error_ui_settings', array( __CLASS__, 'handle_save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
	}

	/**
	 * Install drop-ins.
	 *
	 * @return true|WP_Error
	 */
	public static function activate() {
		return self::install_dropins();
	}

	/**
	 * Remove managed drop-ins.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$files = array(
			trailingslashit( WP_CONTENT_DIR ) . 'db-error.php',
			trailingslashit( WP_CONTENT_DIR ) . 'fatal-error-handler.php',
			trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-error-ui-config.php',
		);

		foreach ( $files as $file ) {
			if ( is_readable( $file ) ) {
				$contents = file_get_contents( $file );
				if ( false !== strpos( (string) $contents, 'SiteIntelix Error UI' ) ) {
					wp_delete_file( $file );
				}
			}
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Refresh managed drop-ins after updates.
	 *
	 * @return void
	 */
	public static function maybe_refresh_dropins() {
		if ( self::DROPIN_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::install_dropins();
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
		if ( ! in_array( 'error_ui', (array) $enabled_modules, true ) ) {
			return;
		}
		?>
		<section class="sitx-settings-panel-tab <?php echo 'error_ui' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-error-ui-settings" data-siteintelix-settings-panel="error_ui">
			<div class="sitx-settings-content-grid">
				<div class="sitx-settings-main">
					<div class="sitx-setting-row si-form-row">
						<div>
							<h3><?php esc_html_e( 'Error Handler', 'siteintelix' ); ?></h3>
							<p><?php esc_html_e( 'Shows branded visitor pages for fatal WordPress and database errors.', 'siteintelix' ); ?></p>
						</div>
						<span class="sitx-badge sitx-badge--good"><?php esc_html_e( 'Active', 'siteintelix' ); ?></span>
					</div>
					<?php self::render_settings_form( 'sitx-tab-form' ); ?>
				</div>
				<aside class="sitx-settings-sidebar">
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'About Error Handler', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'Error Handler manages WordPress drop-ins only while enabled and removes SiteIntelix-managed drop-ins when disabled.', 'siteintelix' ); ?></p>
					</div>
				</aside>
			</div>
		</section>
		<?php
	}

	/**
	 * Render settings form.
	 *
	 * @return void
	 */
	private static function render_settings_form( $class_name = 'sitx-card sitx-error-ui-form' ) {
		$settings = self::get_settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="<?php echo esc_attr( $class_name ); ?>">
			<input type="hidden" name="action" value="siteintelix_save_error_ui_settings">
			<?php wp_nonce_field( 'siteintelix_save_error_ui_settings' ); ?>

			<div class="sitx-setting-row si-form-row">
				<div>
					<h3><?php esc_html_e( 'Enable Email Alerts', 'siteintelix' ); ?></h3>
					<p><?php esc_html_e( 'Send technical fatal and database error details to the WordPress admin email while visitors see the friendly error page.', 'siteintelix' ); ?></p>
				</div>
				<label class="sitx-toggle">
					<input type="checkbox" name="email_alerts" value="1" <?php checked( 1, absint( $settings['email_alerts'] ) ); ?>>
					<span class="sitx-toggle__slider"></span>
				</label>
			</div>

			<div class="sitx-setting-row si-form-row">
				<div>
					<h3><?php esc_html_e( 'Show Actual Error Message', 'siteintelix' ); ?></h3>
					<p><?php esc_html_e( 'Display technical error details on the public error page. Keep this disabled on production sites unless you are actively debugging.', 'siteintelix' ); ?></p>
				</div>
				<label class="sitx-toggle">
					<input type="checkbox" name="show_error_details" value="1" <?php checked( 1, absint( $settings['show_error_details'] ) ); ?>>
					<span class="sitx-toggle__slider"></span>
				</label>
			</div>

			<div class="sitx-form-grid">
				<?php foreach ( self::get_fields() as $key => $field ) : ?>
					<label class="sitx-form-field <?php echo ! empty( $field['textarea'] ) ? 'sitx-form-field--full' : ''; ?>">
						<span><?php echo esc_html( $field['label'] ); ?></span>
						<?php if ( ! empty( $field['textarea'] ) ) : ?>
							<textarea name="<?php echo esc_attr( $key ); ?>" rows="4"><?php echo esc_textarea( $settings[ $key ] ); ?></textarea>
						<?php else : ?>
							<input type="<?php echo esc_attr( $field['type'] ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $settings[ $key ] ); ?>">
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>

			<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Error Handler', 'siteintelix' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change error UI settings.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_save_error_ui_settings' );

		$settings = self::sanitize_settings( wp_unslash( $_POST ) );
		update_option( self::SETTINGS_OPTION, $settings );

		$result = self::write_static_config( $settings );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Error Handler save failed', 'siteintelix' ), array( 'back_link' => true ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-settings', 'siteintelix_settings_saved' => '1', 'tab' => 'error_ui' ), admin_url( 'admin.php' ) ) . '#siteintelix-error-ui-settings' );
		exit;
	}

	/**
	 * Install drop-ins.
	 *
	 * @return true|WP_Error
	 */
	private static function install_dropins() {
		$dropins = array(
			'db-error.php'            => SITEINTELIX_PLUGIN_DIR . 'includes/modules/error-ui/templates/db-error.php',
			'fatal-error-handler.php' => SITEINTELIX_PLUGIN_DIR . 'includes/modules/error-ui/templates/fatal-error-handler.php',
		);

		foreach ( $dropins as $filename => $source ) {
			$destination = trailingslashit( WP_CONTENT_DIR ) . $filename;
			$existing    = is_readable( $destination ) ? file_get_contents( $destination ) : '';

			if ( $existing && false === strpos( (string) $existing, 'SiteIntelix Error UI drop-in' ) && false === strpos( (string) $existing, 'Custom Error UI drop-in' ) ) {
				return new WP_Error(
					'siteintelix_error_ui_existing_dropin',
					sprintf(
						/* translators: %s: drop-in filename. */
						__( 'A different %s drop-in already exists in wp-content. Please back it up or remove it before enabling Error Handler.', 'siteintelix' ),
						$filename
					)
				);
			}

			if ( ! is_readable( $source ) ) {
				return new WP_Error(
					'siteintelix_error_ui_missing_template',
					sprintf(
						/* translators: %s: template filename. */
						__( 'Template file missing: %s', 'siteintelix' ),
						$filename
					)
				);
			}

			if ( ! copy( $source, $destination ) ) {
				return new WP_Error(
					'siteintelix_error_ui_write_failed',
					sprintf(
						/* translators: %s: drop-in filename. */
						__( 'Could not write %s to wp-content.', 'siteintelix' ),
						$filename
					)
				);
			}
		}

		$config_result = self::write_static_config();
		if ( is_wp_error( $config_result ) ) {
			return $config_result;
		}

		update_option( self::VERSION_OPTION, self::DROPIN_VERSION, false );

		return true;
	}

	/**
	 * Write static config for drop-ins.
	 *
	 * @param array<string,string>|null $settings Settings.
	 * @return true|WP_Error
	 */
	private static function write_static_config( $settings = null ) {
		$settings = is_array( $settings ) ? array_merge( self::get_default_settings(), $settings ) : self::get_settings();
		$settings['admin_email'] = sanitize_email( get_option( 'admin_email' ) );
		$file     = trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-error-ui-config.php';
		$content  = "<?php\n/**\n * SiteIntelix Error Handler generated config.\n */\n\nreturn " . var_export( $settings, true ) . ";\n";

		if ( false === file_put_contents( $file, $content ) ) {
			return new WP_Error( 'siteintelix_error_ui_config_failed', __( 'Could not write the SiteIntelix Error Handler config file.', 'siteintelix' ) );
		}

		return true;
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $input Input.
	 * @return array<string,string>
	 */
	private static function sanitize_settings( $input ) {
		$output = array();

		foreach ( self::get_default_settings() as $key => $default ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : $default;

			if ( in_array( $key, array( 'email_alerts', 'show_error_details' ), true ) ) {
				$output[ $key ] = isset( $input[ $key ] ) ? '1' : '0';
			} elseif ( false !== strpos( $key, '_url' ) ) {
				$output[ $key ] = 0 === strpos( trim( (string) $value ), 'mailto:' ) ? sanitize_url( trim( (string) $value ), array( 'mailto' ) ) : esc_url_raw( trim( (string) $value ) );
			} elseif ( 'message' === $key ) {
				$output[ $key ] = sanitize_textarea_field( $value );
			} else {
				$output[ $key ] = sanitize_text_field( $value );
			}

			if ( '' === $output[ $key ] ) {
				$output[ $key ] = $default;
			}
		}

		return $output;
	}

	/**
	 * Fields.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function get_fields() {
		return array(
			'eyebrow'               => array( 'label' => __( 'Badge text', 'siteintelix' ), 'type' => 'text' ),
			'title'                 => array( 'label' => __( 'Headline', 'siteintelix' ), 'type' => 'text' ),
			'message'               => array( 'label' => __( 'Message', 'siteintelix' ), 'type' => 'text', 'textarea' => true ),
			'primary_button_text'   => array( 'label' => __( 'Primary button text', 'siteintelix' ), 'type' => 'text' ),
			'primary_button_url'    => array( 'label' => __( 'Primary button URL', 'siteintelix' ), 'type' => 'url' ),
			'secondary_button_text' => array( 'label' => __( 'Secondary button text', 'siteintelix' ), 'type' => 'text' ),
			'secondary_button_url'  => array( 'label' => __( 'Secondary button URL', 'siteintelix' ), 'type' => 'text' ),
			'status_meta'           => array( 'label' => __( 'Small footer text', 'siteintelix' ), 'type' => 'text' ),
		);
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,string>
	 */
	public static function get_default_settings() {
		return array(
			'eyebrow'               => 'Temporary service interruption',
			'title'                 => 'We got an error, and we are working on it.',
			'message'               => 'Do not worry, your data is safe with us. Our team has been notified and is already working to bring everything back online.',
			'primary_button_text'   => 'Try again',
			'primary_button_url'    => '/',
			'secondary_button_text' => 'Contact support',
			'secondary_button_url'  => 'mailto:support@example.com',
			'status_meta'           => 'Service status · Temporary error',
			'email_alerts'          => '1',
			'show_error_details'    => '0',
			'admin_email'           => '',
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,string>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array_merge( self::get_default_settings(), is_array( $saved ) ? $saved : array() );
	}
}
