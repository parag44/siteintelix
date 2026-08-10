<?php
/**
 * Tabbed settings page for enabled SiteIntelix modules.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_form_url        = admin_url( 'admin-post.php' );
$siteintelix_modules_url     = admin_url( 'admin.php?page=siteintelix-modules' );
$siteintelix_enabled_modules = SITEINTELIX_Modules::get_enabled();
$siteintelix_all_modules     = SITEINTELIX_Modules::get_all();
$siteintelix_tabs            = array();

foreach ( $siteintelix_enabled_modules as $siteintelix_module_id ) {
	if (
		SITEINTELIX_Security::can_manage_module( $siteintelix_module_id )
		&& isset( $siteintelix_all_modules[ $siteintelix_module_id ]['settings'] )
	) {
		$siteintelix_tabs[ $siteintelix_module_id ] = $siteintelix_all_modules[ $siteintelix_module_id ];
	}
}

$siteintelix_active_tab = ! empty( $siteintelix_tabs ) ? (string) array_key_first( $siteintelix_tabs ) : '';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI state.
$siteintelix_requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
if ( $siteintelix_requested_tab && isset( $siteintelix_tabs[ $siteintelix_requested_tab ] ) ) {
	$siteintelix_active_tab = $siteintelix_requested_tab;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect and verification status flags.
$siteintelix_settings_saved = isset( $_GET['siteintelix_settings_saved'] );
$siteintelix_settings_error = isset( $_GET['siteintelix_settings_error'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_settings_error'] ) ) : '';
$siteintelix_smtp_status    = isset( $_GET['siteintelix_smtp_status'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_smtp_status'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$siteintelix_smtp_message = '';

if ( $siteintelix_smtp_status ) {
	$siteintelix_smtp_message = get_transient( 'siteintelix_smtp_verify_notice_' . get_current_user_id() );
	delete_transient( 'siteintelix_smtp_verify_notice_' . get_current_user_id() );
	$siteintelix_smtp_message = is_string( $siteintelix_smtp_message ) ? $siteintelix_smtp_message : '';
}

if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() ) {
	$siteintelix_debug_source    = SITEINTELIX_Debug_Source::detect();
	$siteintelix_debug_state     = SITEINTELIX_WP_Config::get_state();
	$siteintelix_logs_per_page   = min( 500, max( 10, (int) get_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, 25 ) ) );
	$siteintelix_wp_debug       = ! empty( $siteintelix_debug_state[ SITEINTELIX_WP_Config::WP_DEBUG ] );
	$siteintelix_wp_debug_log   = ! empty( $siteintelix_debug_state[ SITEINTELIX_WP_Config::WP_DEBUG_LOG ] );
	$siteintelix_debug_display  = ! empty( $siteintelix_debug_state[ SITEINTELIX_WP_Config::WP_DEBUG_DISPLAY ] );
	$siteintelix_script_debug    = ! empty( $siteintelix_debug_state[ SITEINTELIX_WP_Config::SCRIPT_DEBUG ] );
	$siteintelix_savequeries     = ! empty( $siteintelix_debug_state[ SITEINTELIX_WP_Config::SAVEQUERIES ] );
	$siteintelix_storage_path    = SITEINTELIX_Debug_Storage::path( 'active' );
	$siteintelix_storage_display = is_wp_error( $siteintelix_storage_path ) ? __( 'Protected SiteIntelix storage', 'siteintelix' ) : ltrim( str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $siteintelix_storage_path ) ), '/' );
	$siteintelix_debug_log_url   = admin_url( 'admin.php?page=siteintelix-debug-log' );
	$siteintelix_clear_debug_url = wp_nonce_url( admin_url( 'admin-post.php?action=siteintelix_clear_debug_log' ), 'siteintelix_clear_debug_log' );
}
?>

<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-settings-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-admin-generic',
			'title'       => __( 'All Module Settings', 'siteintelix' ),
			'description' => __( 'Configure settings for each enabled SiteIntelix module.', 'siteintelix' ),
			'badges'      => array(
				'<span class="siteintelix-version-pill">v' . esc_html( SITEINTELIX_VERSION ) . '</span>',
			),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button(
					array(
						'label'   => __( 'Manage Modules', 'siteintelix' ),
						'url'     => $siteintelix_modules_url,
						'variant' => 'secondary',
						'icon'    => 'dashicons-screenoptions',
					)
				),
			),
		)
	);
	?>

	<div class="siteintelix-container">
		<div id="siteintelix-notices-slot">
			<?php if ( $siteintelix_settings_saved ) : ?>
				<div class="sitx-alert <?php echo 'failed' === $siteintelix_smtp_status ? 'sitx-alert--warning' : 'sitx-alert--success'; ?>">
					<div class="sitx-alert__icon"><span class="dashicons <?php echo 'failed' === $siteintelix_smtp_status ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Settings saved successfully.', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg">
							<?php
							echo esc_html(
								$siteintelix_smtp_message
									? $siteintelix_smtp_message
									: __( 'The enabled module configuration has been updated.', 'siteintelix' )
							);
							?>
						</p>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $siteintelix_settings_error ) : ?>
				<div class="sitx-alert sitx-alert--error">
					<div class="sitx-alert__icon"><span class="dashicons dashicons-warning"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Settings update failed.', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg"><?php echo esc_html( $siteintelix_settings_error ); ?></p>
					</div>
				</div>
			<?php endif; ?>

		</div>

		<?php if ( empty( $siteintelix_tabs ) ) : ?>
			<div class="sitx-card">
				<h2 class="sitx-card__title"><?php esc_html_e( 'No configurable modules enabled', 'siteintelix' ); ?></h2>
				<p class="sitx-card__body"><?php esc_html_e( 'Enable a module from the Modules screen to show its settings here.', 'siteintelix' ); ?></p>
				<a href="<?php echo esc_url( $siteintelix_modules_url ); ?>" class="sitx-btn sitx-btn--primary"><?php esc_html_e( 'Open Modules', 'siteintelix' ); ?></a>
			</div>
		<?php else : ?>
			<div class="sitx-settings-shell si-card" data-siteintelix-settings-tabs>
				<div class="sitx-settings-topbar">
					<div class="sitx-settings-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Module settings', 'siteintelix' ); ?>">
						<?php foreach ( $siteintelix_tabs as $siteintelix_tab_id => $siteintelix_tab ) : ?>
								<button type="button" id="siteintelix-settings-tab-<?php echo esc_attr( $siteintelix_tab_id ); ?>" class="sitx-settings-tab <?php echo $siteintelix_tab_id === $siteintelix_active_tab ? 'is-active' : ''; ?>" role="tab" aria-controls="siteintelix-<?php echo esc_attr( str_replace( '_', '-', $siteintelix_tab_id ) ); ?>-settings" aria-selected="<?php echo $siteintelix_tab_id === $siteintelix_active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo $siteintelix_tab_id === $siteintelix_active_tab ? '0' : '-1'; ?>" data-siteintelix-settings-tab="<?php echo esc_attr( $siteintelix_tab_id ); ?>">
									<span class="sitx-settings-tab__icon sitx-module-card__icon--<?php echo esc_attr( sanitize_key( (string) $siteintelix_tab['color'] ) ); ?>">
										<?php if ( ! empty( $siteintelix_tab['icon_svg'] ) ) : ?>
											<?php
											echo wp_kses(
												SITEINTELIX_Admin_UI::svg_icon( (string) $siteintelix_tab['icon_svg'] ),
												array(
													'span' => array(
														'class' => true,
													),
													'svg'  => array(
														'viewbox'     => true,
														'aria-hidden' => true,
														'focusable'   => true,
													),
													'path' => array(
														'fill' => true,
														'd'    => true,
													),
												)
											);
											?>
										<?php else : ?>
											<span class="dashicons <?php echo esc_attr( (string) $siteintelix_tab['icon'] ); ?>" aria-hidden="true"></span>
										<?php endif; ?>
									</span>
								<?php echo esc_html( (string) $siteintelix_tab['title'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

						<label class="sitx-settings-search">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Search module settings', 'siteintelix' ); ?></span>
						<input type="search" placeholder="<?php esc_attr_e( 'Search module settings...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search module settings', 'siteintelix' ); ?>" data-siteintelix-settings-search>
						</label>
						<button type="button" class="si-button si-button--secondary" data-siteintelix-settings-clear hidden><?php esc_html_e( 'Clear search', 'siteintelix' ); ?></button>
						<p class="screen-reader-text" data-siteintelix-settings-results aria-live="polite"></p>
				</div>

				<div class="sitx-settings-panels">
					<?php if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() ) : ?>
							<section class="sitx-settings-panel-tab <?php echo 'debug_log' === $siteintelix_active_tab ? 'is-active' : ''; ?>" id="siteintelix-debug-log-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-debug_log" data-siteintelix-settings-panel="debug_log" <?php echo 'debug_log' === $siteintelix_active_tab ? '' : 'hidden'; ?>>
							<form method="post" action="<?php echo esc_url( $siteintelix_form_url ); ?>" class="sitx-tab-form" id="siteintelix-debug-settings-form">
								<?php wp_nonce_field( 'siteintelix_save_debug_settings' ); ?>
								<input type="hidden" name="action" value="siteintelix_save_debug_settings">

								<div class="sitx-settings-content-grid">
									<div class="sitx-settings-main">
										<div class="sitx-setting-row si-form-row">
											<div class="sitx-setting-row__copy">
												<h3><?php esc_html_e( 'Debug Method', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'SiteIntelix manages WordPress debug constants directly and safely backs up wp-config.php before every change.', 'siteintelix' ); ?></p>
											</div>
											<span class="sitx-setting-row__value"><strong><?php esc_html_e( 'WPConfigTransformer', 'siteintelix' ); ?></strong></span>
										</div>
										<div class="sitx-setting-row si-form-row">
											<div class="sitx-setting-row__copy">
												<h3><?php esc_html_e( 'Log File', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'WP_DEBUG_LOG writes to randomized protected SiteIntelix storage instead of the public default debug.log path.', 'siteintelix' ); ?></p>
											</div>
											<code class="sitx-setting-row__code"><?php echo esc_html( $siteintelix_storage_display ); ?></code>
										</div>
										<div class="sitx-setting-row si-form-row" data-setting-keywords="wp debug capture errors">
											<div class="sitx-setting-row__copy"><h3><?php esc_html_e( 'WordPress debug mode', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Enable WP_DEBUG so WordPress records development notices, warnings, and errors.', 'siteintelix' ); ?></p></div>
											<label class="sitx-settings-toggle"><span class="sitx-settings-toggle__text"><?php echo $siteintelix_wp_debug ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ); ?></span><span class="sitx-toggle"><input type="checkbox" name="siteintelix_wp_debug" value="1" <?php checked( $siteintelix_wp_debug ); ?>><span class="sitx-toggle__slider"></span></span></label>
										</div>
										<div class="sitx-setting-row si-form-row" data-setting-keywords="wp debug log protected file">
											<div class="sitx-setting-row__copy"><h3><?php esc_html_e( 'Protected file logging', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Write WP_DEBUG_LOG events to the randomized private SiteIntelix log file.', 'siteintelix' ); ?></p></div>
											<label class="sitx-settings-toggle"><span class="sitx-settings-toggle__text"><?php echo $siteintelix_wp_debug_log ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ); ?></span><span class="sitx-toggle"><input type="checkbox" name="siteintelix_wp_debug_log" value="1" <?php checked( $siteintelix_wp_debug_log ); ?>><span class="sitx-toggle__slider"></span></span></label>
										</div>
										<div class="sitx-setting-row si-form-row" data-setting-keywords="display php errors visitors">
											<div class="sitx-setting-row__copy"><h3><?php esc_html_e( 'Display PHP errors', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Controls WP_DEBUG_DISPLAY. Keep this disabled on public and production sites.', 'siteintelix' ); ?></p></div>
											<label class="sitx-settings-toggle sitx-settings-toggle--danger"><span class="sitx-settings-toggle__text"><?php echo $siteintelix_debug_display ? esc_html__( 'Visible', 'siteintelix' ) : esc_html__( 'Hidden', 'siteintelix' ); ?></span><span class="sitx-toggle"><input type="checkbox" name="siteintelix_wp_debug_display" value="1" <?php checked( $siteintelix_debug_display ); ?>><span class="sitx-toggle__slider"></span></span></label>
										</div>
										<div class="sitx-setting-row si-form-row" data-setting-keywords="script debug unminified assets">
											<div class="sitx-setting-row__copy"><h3><?php esc_html_e( 'Unminified core scripts', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Temporarily enable SCRIPT_DEBUG for WordPress asset diagnostics.', 'siteintelix' ); ?></p></div>
											<label class="sitx-settings-toggle"><span class="sitx-settings-toggle__text"><?php echo $siteintelix_script_debug ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ); ?></span><span class="sitx-toggle"><input type="checkbox" name="<?php echo esc_attr( SITEINTELIX_SCRIPT_DEBUG_OPTION ); ?>" value="1" <?php checked( $siteintelix_script_debug ); ?>><span class="sitx-toggle__slider"></span></span></label>
										</div>
										<div class="sitx-setting-row si-form-row" data-setting-keywords="savequeries database query diagnostics">
											<div class="sitx-setting-row__copy"><h3><?php esc_html_e( 'Database query diagnostics', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Temporarily enable SAVEQUERIES. It increases memory use and can expose sensitive query context.', 'siteintelix' ); ?></p></div>
											<label class="sitx-settings-toggle sitx-settings-toggle--warning"><span class="sitx-settings-toggle__text"><?php echo $siteintelix_savequeries ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ); ?></span><span class="sitx-toggle"><input type="checkbox" name="<?php echo esc_attr( SITEINTELIX_SAVEQUERIES_OPTION ); ?>" value="1" <?php checked( $siteintelix_savequeries ); ?>><span class="sitx-toggle__slider"></span></span></label>
										</div>
										<div class="sitx-setting-row si-form-row">
											<div class="sitx-setting-row__copy">
												<h3><?php esc_html_e( 'Logs Per Page', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Choose how many grouped issues appear on each Debug Log page.', 'siteintelix' ); ?></p>
											</div>
											<input class="sitx-setting-row__number" type="number" name="siteintelix_logs_per_page" min="10" max="500" step="5" value="<?php echo esc_attr( (string) $siteintelix_logs_per_page ); ?>">
										</div>
										<div class="sitx-setting-row si-form-row">
											<div class="sitx-setting-row__copy">
												<h3><?php esc_html_e( 'Current Source', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Shows whether the required WordPress debug constants are currently active.', 'siteintelix' ); ?></p>
											</div>
											<span class="si-badge <?php echo SITEINTELIX_Debug_Source::SOURCE_WP_CONFIG === $siteintelix_debug_source ? 'si-badge--success' : 'si-badge--warning'; ?>"><?php echo SITEINTELIX_Debug_Source::SOURCE_WP_CONFIG === $siteintelix_debug_source ? esc_html__( 'WordPress debug logging enabled', 'siteintelix' ) : esc_html__( 'WordPress debug logging disabled', 'siteintelix' ); ?></span>
										</div>

										<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Debug Settings', 'siteintelix' ); ?></button>
									</div>

									<aside class="sitx-settings-sidebar">
										<div class="sitx-side-card si-card">
											<h3><?php esc_html_e( 'About Debug Log', 'siteintelix' ); ?></h3>
											<p><?php esc_html_e( 'Track PHP errors, notices, warnings, deprecated messages, and database issues in a private log viewer.', 'siteintelix' ); ?></p>
											<p><?php esc_html_e( 'Nginx administrators must deny the complete SiteIntelix content URL directory; see the plugin readme for the exact rule.', 'siteintelix' ); ?></p>
										</div>
										<div class="sitx-side-card si-card">
											<h3><?php esc_html_e( 'Quick Actions', 'siteintelix' ); ?></h3>
											<a href="<?php echo esc_url( $siteintelix_debug_log_url ); ?>"><?php esc_html_e( 'View Logs', 'siteintelix' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
											<a href="<?php echo esc_url( $siteintelix_clear_debug_url ); ?>"><?php esc_html_e( 'Clear Logs', 'siteintelix' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
										</div>
									</aside>
								</div>
							</form>
						</section>
					<?php endif; ?>

					<?php do_action( 'siteintelix_render_module_settings_sections', $siteintelix_enabled_modules, $siteintelix_active_tab ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
