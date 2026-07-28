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
	$siteintelix_debug_method    = SITEINTELIX_Debug_Source::get_method();
	$siteintelix_external_dbg    = SITEINTELIX_Debug_Source::has_external_wp_debug();
	$siteintelix_fix_url         = add_query_arg(
		array(
			'action'   => 'siteintelix_fix_debug_conflict',
			'_wpnonce' => wp_create_nonce( 'siteintelix_fix_debug_conflict' ),
		),
		$siteintelix_form_url
	);
	$siteintelix_logs_per_page   = min( 500, max( 10, (int) get_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, 25 ) ) );
	$siteintelix_debug_ui        = get_option( SITEINTELIX_DEBUG_UI_OPTION, 'modern' );
	$siteintelix_debug_ui        = 'terminal_dark' === $siteintelix_debug_ui ? 'terminal_light' : $siteintelix_debug_ui;
	$siteintelix_debug_ui        = in_array( $siteintelix_debug_ui, array( 'classic', 'modern', 'terminal_light' ), true ) ? $siteintelix_debug_ui : 'modern';
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

			<?php if ( SITEINTELIX_Modules::is_enabled( 'debug_log' ) && SITEINTELIX_Security::can_manage_global_tools() && $siteintelix_external_dbg ) : ?>
				<div class="sitx-alert sitx-alert--warning">
					<div class="sitx-alert__icon"><span class="dashicons dashicons-warning"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Debug Conflict Detected', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg"><?php esc_html_e( 'WP_DEBUG is defined manually in wp-config.php. This overrides the SiteIntelix Debug Log module and may cause issues.', 'siteintelix' ); ?></p>
					</div>
					<div class="sitx-alert__actions">
						<a href="<?php echo esc_url( $siteintelix_fix_url ); ?>" class="sitx-btn sitx-btn--orange"><?php esc_html_e( 'Fix Automatically', 'siteintelix' ); ?></a>
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
									<span class="dashicons <?php echo esc_attr( (string) $siteintelix_tab['icon'] ); ?>" aria-hidden="true"></span>
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
											<div>
												<h3><?php esc_html_e( 'Debug Method', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Choose how SiteIntelix captures and writes debug logs.', 'siteintelix' ); ?></p>
											</div>
											<select name="siteintelix_debug_method">
												<option value="mu" <?php selected( $siteintelix_debug_method, 'mu' ); ?>><?php esc_html_e( 'MU Plugin (Recommended)', 'siteintelix' ); ?></option>
												<option value="wp_config" <?php selected( $siteintelix_debug_method, 'wp_config' ); ?>><?php esc_html_e( 'wp-config.php (Advanced)', 'siteintelix' ); ?></option>
											</select>
										</div>
										<div class="sitx-setting-row si-form-row">
											<div>
												<h3><?php esc_html_e( 'Log File', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Both debug methods write to the private SiteIntelix log file.', 'siteintelix' ); ?></p>
											</div>
											<code>wp-content/siteintelix-debug.log</code>
										</div>
										<div class="sitx-setting-row si-form-row">
											<div>
												<h3><?php esc_html_e( 'Viewer UI', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Choose the Debug Log Viewer layout.', 'siteintelix' ); ?></p>
											</div>
											<select name="siteintelix_debug_ui">
												<option value="modern" <?php selected( $siteintelix_debug_ui, 'modern' ); ?>><?php esc_html_e( 'Modern grouped cards', 'siteintelix' ); ?></option>
												<option value="classic" <?php selected( $siteintelix_debug_ui, 'classic' ); ?>><?php esc_html_e( 'Classic table', 'siteintelix' ); ?></option>
												<option value="terminal_light" <?php selected( $siteintelix_debug_ui, 'terminal_light' ); ?>><?php esc_html_e( 'Terminal Light', 'siteintelix' ); ?></option>
											</select>
										</div>
										<div class="sitx-setting-row si-form-row">
											<div>
												<h3><?php esc_html_e( 'Logs Per Page', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Controls classic rows and modern grouped cards per page.', 'siteintelix' ); ?></p>
											</div>
											<input type="number" name="siteintelix_logs_per_page" min="10" max="500" step="5" value="<?php echo esc_attr( (string) $siteintelix_logs_per_page ); ?>">
										</div>
										<div class="sitx-setting-row si-form-row">
											<div>
												<h3><?php esc_html_e( 'Current Source', 'siteintelix' ); ?></h3>
												<p><?php esc_html_e( 'Shows which debug capture layer is currently active.', 'siteintelix' ); ?></p>
											</div>
											<span class="sitx-badge sitx-badge--good"><?php echo esc_html( 'wp_config' === $siteintelix_debug_source ? 'wp-config.php' : 'MU Plugin' ); ?></span>
										</div>

										<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Debug Settings', 'siteintelix' ); ?></button>
									</div>

									<aside class="sitx-settings-sidebar">
										<div class="sitx-side-card si-card">
											<h3><?php esc_html_e( 'About Debug Log', 'siteintelix' ); ?></h3>
											<p><?php esc_html_e( 'Track PHP errors, notices, warnings, deprecated messages, and database issues in a private log viewer.', 'siteintelix' ); ?></p>
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
