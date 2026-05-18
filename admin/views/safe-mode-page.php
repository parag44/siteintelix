<?php
/**
 * Safe Mode Debugger admin page.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_safe_state   = SITEINTELIX_Safe_Mode_Debugger_Module::get_active_state();
$siteintelix_saved_state  = SITEINTELIX_Safe_Mode_Debugger_Module::get_saved_state();
$siteintelix_plugins      = SITEINTELIX_Safe_Mode_Debugger_Module::get_plugins_for_form();
$siteintelix_themes       = SITEINTELIX_Safe_Mode_Debugger_Module::get_themes_for_form();
$siteintelix_logs         = SITEINTELIX_Safe_Mode_Debugger_Module::get_log_entries();
$siteintelix_current_theme = wp_get_theme();
$siteintelix_form_state   = $siteintelix_safe_state ? $siteintelix_safe_state : ( is_array( $siteintelix_saved_state ) ? $siteintelix_saved_state : array() );
$siteintelix_plugin_mode  = isset( $siteintelix_form_state['plugin_mode'] ) ? sanitize_key( $siteintelix_form_state['plugin_mode'] ) : SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_KEEP;
$siteintelix_theme_mode   = isset( $siteintelix_form_state['theme_mode'] ) ? sanitize_key( $siteintelix_form_state['theme_mode'] ) : SITEINTELIX_Safe_Mode_Debugger_Module::THEME_MODE_KEEP;
$siteintelix_selected_plugins = isset( $siteintelix_form_state['selected_plugins'] ) && is_array( $siteintelix_form_state['selected_plugins'] ) ? $siteintelix_form_state['selected_plugins'] : array_keys( array_filter( $siteintelix_plugins, static function ( $plugin ) { return ! empty( $plugin['is_active'] ); } ) );
$siteintelix_selected_theme   = isset( $siteintelix_form_state['selected_theme'] ) ? sanitize_key( $siteintelix_form_state['selected_theme'] ) : $siteintelix_current_theme->get_stylesheet();
$siteintelix_debug_options    = isset( $siteintelix_form_state['debug_options'] ) && is_array( $siteintelix_form_state['debug_options'] ) ? $siteintelix_form_state['debug_options'] : array();
$siteintelix_enabled_count    = SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_ONLY === $siteintelix_plugin_mode ? count( $siteintelix_selected_plugins ) : ( SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_NONE === $siteintelix_plugin_mode ? 1 : count( array_filter( $siteintelix_plugins, static function ( $plugin ) { return ! empty( $plugin['is_active'] ); } ) ) );
?>
<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-safe-mode-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-shield-alt',
			'title'       => __( 'Safe Mode Debugger', 'siteintelix' ),
			'description' => __( 'Debug plugin and theme conflicts privately without affecting live visitors.', 'siteintelix' ),
			'badges'      => array(
				'<span class="siteintelix-version-pill">v' . esc_html( SITEINTELIX_VERSION ) . '</span>',
				SITEINTELIX_Admin_UI::badge(
					$siteintelix_safe_state ? __( 'Active', 'siteintelix' ) : __( 'Inactive', 'siteintelix' ),
					$siteintelix_safe_state ? 'warning' : 'neutral',
					$siteintelix_safe_state ? 'dashicons-shield' : 'dashicons-lock'
				),
			),
		)
	);
	?>

	<div class="siteintelix-container">
		<?php if ( $siteintelix_safe_state ) : ?>
			<div class="sitx-alert sitx-alert--info siteintelix-safe-mode-notice">
				<div class="sitx-alert__icon"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span></div>
				<div class="sitx-alert__content">
					<strong class="sitx-alert__title"><?php esc_html_e( 'Safe Mode is active for your session only.', 'siteintelix' ); ?></strong>
					<p class="sitx-alert__msg"><?php esc_html_e( 'Visitors and other administrators are not affected.', 'siteintelix' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flags. ?>
		<?php if ( isset( $_GET['siteintelix_safe_mode_started'] ) ) : ?>
			<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Safe Mode started.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'This private session is now using your temporary debugging configuration.', 'siteintelix' ); ?></p></div></div>
		<?php endif; ?>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flags. ?>
		<?php if ( isset( $_GET['siteintelix_safe_mode_stopped'] ) ) : ?>
			<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-dismiss"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Safe Mode stopped.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'Your session has returned to the live site configuration.', 'siteintelix' ); ?></p></div></div>
		<?php endif; ?>
		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flags. ?>
		<?php if ( isset( $_GET['siteintelix_safe_mode_reset'] ) ) : ?>
			<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-update"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Safe Mode reset.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'Saved Safe Mode state and local action log were cleared.', 'siteintelix' ); ?></p></div></div>
		<?php endif; ?>

		<section class="si-card sitx-card sitx-safe-status-card">
			<header class="sitx-card__header">
				<h2 class="sitx-card__title"><?php esc_html_e( 'Session Status', 'siteintelix' ); ?></h2>
				<span class="si-badge <?php echo $siteintelix_safe_state ? 'si-badge--warning' : 'si-badge--neutral'; ?>"><?php echo esc_html( $siteintelix_safe_state ? __( 'Active', 'siteintelix' ) : __( 'Inactive', 'siteintelix' ) ); ?></span>
			</header>
			<div class="sitx-safe-status-grid">
				<div><span><?php esc_html_e( 'Safe Mode status', 'siteintelix' ); ?></span><strong><?php echo esc_html( $siteintelix_safe_state ? __( 'Active', 'siteintelix' ) : __( 'Inactive', 'siteintelix' ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Active preset', 'siteintelix' ); ?></span><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $siteintelix_plugin_mode ) ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Current temporary theme', 'siteintelix' ); ?></span><strong><?php echo esc_html( SITEINTELIX_Safe_Mode_Debugger_Module::THEME_MODE_SWITCH === $siteintelix_theme_mode ? $siteintelix_selected_theme : __( 'Live theme', 'siteintelix' ) ); ?></strong></div>
				<div><span><?php esc_html_e( 'Plugins enabled in Safe Mode', 'siteintelix' ); ?></span><strong><?php echo esc_html( (string) $siteintelix_enabled_count ); ?></strong></div>
				<div><span><?php esc_html_e( 'Session expiry', 'siteintelix' ); ?></span><strong><?php echo $siteintelix_safe_state && ! empty( $siteintelix_safe_state['expires_at'] ) ? esc_html( date_i18n( 'Y-m-d H:i', absint( $siteintelix_safe_state['expires_at'] ) ) ) : esc_html__( 'Not active', 'siteintelix' ); ?></strong></div>
			</div>
		</section>

		<div class="sitx-safe-layout">
			<form class="si-card sitx-card sitx-safe-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="siteintelix_start_safe_mode">
				<?php wp_nonce_field( 'siteintelix_start_safe_mode' ); ?>

				<section class="sitx-safe-section">
					<h2><?php esc_html_e( 'Plugin mode', 'siteintelix' ); ?></h2>
					<p><?php esc_html_e( 'Choose which plugins should be loaded only for this Safe Mode session.', 'siteintelix' ); ?></p>
					<div class="sitx-safe-radio-grid">
						<label><input type="radio" name="siteintelix_plugin_mode" value="keep" <?php checked( $siteintelix_plugin_mode, SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_KEEP ); ?>> <span><?php esc_html_e( 'Keep current active plugins', 'siteintelix' ); ?></span></label>
						<label><input type="radio" name="siteintelix_plugin_mode" value="none" <?php checked( $siteintelix_plugin_mode, SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_NONE ); ?>> <span><?php esc_html_e( 'Disable all plugins', 'siteintelix' ); ?></span></label>
						<label><input type="radio" name="siteintelix_plugin_mode" value="only" <?php checked( $siteintelix_plugin_mode, SITEINTELIX_Safe_Mode_Debugger_Module::PLUGIN_MODE_ONLY ); ?>> <span><?php esc_html_e( 'Enable selected plugins only', 'siteintelix' ); ?></span></label>
					</div>

					<div class="sitx-safe-plugin-picker" data-siteintelix-safe-plugin-picker>
						<label class="sitx-settings-search sitx-safe-search">
							<span class="dashicons dashicons-search" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Search installed plugins', 'siteintelix' ); ?></span>
							<input type="search" placeholder="<?php esc_attr_e( 'Search installed plugins...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search installed plugins', 'siteintelix' ); ?>" data-siteintelix-safe-plugin-search>
						</label>
						<div class="sitx-safe-plugin-list">
							<?php foreach ( $siteintelix_plugins as $siteintelix_plugin ) : ?>
								<?php
								$basename = (string) $siteintelix_plugin['basename'];
								$checked  = in_array( $basename, $siteintelix_selected_plugins, true ) || ! empty( $siteintelix_plugin['is_required'] );
								?>
								<label class="sitx-safe-plugin <?php echo ! empty( $siteintelix_plugin['is_active'] ) ? 'is-live-active' : ''; ?>" data-plugin-name="<?php echo esc_attr( strtolower( (string) $siteintelix_plugin['name'] . ' ' . $basename ) ); ?>">
									<input type="checkbox" name="siteintelix_safe_plugins[]" value="<?php echo esc_attr( $basename ); ?>" <?php checked( $checked ); ?> <?php disabled( ! empty( $siteintelix_plugin['is_required'] ) ); ?>>
									<span>
										<strong><?php echo esc_html( (string) $siteintelix_plugin['name'] ); ?></strong>
										<small><?php echo esc_html( $basename ); ?><?php echo ! empty( $siteintelix_plugin['version'] ) ? ' · ' . esc_html( (string) $siteintelix_plugin['version'] ) : ''; ?></small>
									</span>
									<?php if ( ! empty( $siteintelix_plugin['is_active'] ) ) : ?>
										<em><?php esc_html_e( 'Active now', 'siteintelix' ); ?></em>
									<?php endif; ?>
								</label>
								<?php if ( ! empty( $siteintelix_plugin['is_required'] ) ) : ?>
									<input type="hidden" name="siteintelix_safe_plugins[]" value="<?php echo esc_attr( $basename ); ?>">
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</section>

				<section class="sitx-safe-section">
					<h2><?php esc_html_e( 'Theme mode', 'siteintelix' ); ?></h2>
					<div class="sitx-safe-radio-grid">
						<label><input type="radio" name="siteintelix_theme_mode" value="keep" <?php checked( $siteintelix_theme_mode, SITEINTELIX_Safe_Mode_Debugger_Module::THEME_MODE_KEEP ); ?>> <span><?php esc_html_e( 'Keep current theme', 'siteintelix' ); ?></span></label>
						<label><input type="radio" name="siteintelix_theme_mode" value="switch" <?php checked( $siteintelix_theme_mode, SITEINTELIX_Safe_Mode_Debugger_Module::THEME_MODE_SWITCH ); ?>> <span><?php esc_html_e( 'Temporarily switch theme', 'siteintelix' ); ?></span></label>
					</div>
					<label class="sitx-safe-theme-select">
						<span><?php esc_html_e( 'Temporary theme', 'siteintelix' ); ?></span>
						<select name="siteintelix_safe_theme">
							<?php foreach ( $siteintelix_themes as $stylesheet => $theme ) : ?>
								<option value="<?php echo esc_attr( $stylesheet ); ?>" <?php selected( $siteintelix_selected_theme, $stylesheet ); ?>><?php echo esc_html( $theme->get( 'Name' ) . ' (' . $stylesheet . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</section>

				<section class="sitx-safe-section">
					<h2><?php esc_html_e( 'Debug options', 'siteintelix' ); ?></h2>
					<div class="sitx-safe-checks">
						<label><input type="checkbox" name="siteintelix_debug_options[wp_debug]" value="1" <?php checked( ! empty( $siteintelix_debug_options['wp_debug'] ) ); ?>> <?php esc_html_e( 'Enable WP_DEBUG for this session', 'siteintelix' ); ?></label>
						<label><input type="checkbox" name="siteintelix_debug_options[script_debug]" value="1" <?php checked( ! empty( $siteintelix_debug_options['script_debug'] ) ); ?>> <?php esc_html_e( 'Enable SCRIPT_DEBUG for this session', 'siteintelix' ); ?></label>
						<label><input type="checkbox" name="siteintelix_debug_options[savequeries]" value="1" <?php checked( ! empty( $siteintelix_debug_options['savequeries'] ) ); ?>> <?php esc_html_e( 'Enable SAVEQUERIES for this session', 'siteintelix' ); ?></label>
					</div>
				</section>

				<div class="sitx-safe-actions">
					<button type="submit" class="si-button si-button--primary"><span class="dashicons dashicons-shield-alt" aria-hidden="true"></span><?php esc_html_e( 'Start Safe Mode', 'siteintelix' ); ?></button>
					<a class="si-button si-button--secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'siteintelix_stop_safe_mode', admin_url( 'admin-post.php' ) ), 'siteintelix_stop_safe_mode' ) ); ?>"><?php esc_html_e( 'Stop Safe Mode', 'siteintelix' ); ?></a>
					<a class="si-button si-button--ghost" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'siteintelix_reset_safe_mode', admin_url( 'admin-post.php' ) ), 'siteintelix_reset_safe_mode' ) ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Reset Safe Mode configuration and log?', 'siteintelix' ); ?>"><?php esc_html_e( 'Reset configuration', 'siteintelix' ); ?></a>
				</div>
			</form>

			<aside class="sitx-safe-sidebar">
				<section class="si-card sitx-card">
					<h2><?php esc_html_e( 'What Safe Mode does', 'siteintelix' ); ?></h2>
					<ul class="sitx-safe-list">
						<li><?php esc_html_e( 'Applies only to your current admin session.', 'siteintelix' ); ?></li>
						<li><?php esc_html_e( 'Does not affect visitors.', 'siteintelix' ); ?></li>
						<li><?php esc_html_e( 'Does not change real plugin or theme settings.', 'siteintelix' ); ?></li>
						<li><?php esc_html_e( 'Automatically expires after 4 hours.', 'siteintelix' ); ?></li>
					</ul>
				</section>

				<section class="si-card sitx-card">
					<h2><?php esc_html_e( 'Recent Safe Mode log', 'siteintelix' ); ?></h2>
					<?php if ( empty( $siteintelix_logs ) ) : ?>
						<p class="sitx-section-desc"><?php esc_html_e( 'No Safe Mode actions logged yet.', 'siteintelix' ); ?></p>
					<?php else : ?>
						<ul class="sitx-safe-log">
							<?php foreach ( $siteintelix_logs as $entry ) : ?>
								<li><strong><?php echo esc_html( isset( $entry['action'] ) ? ucwords( str_replace( '_', ' ', (string) $entry['action'] ) ) : '' ); ?></strong><span><?php echo esc_html( isset( $entry['created_at'] ) ? date_i18n( 'Y-m-d H:i', absint( $entry['created_at'] ) ) : '' ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</aside>
		</div>
	</div>
</div>
