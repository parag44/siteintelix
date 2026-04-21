<?php
/**
 * Settings page view for SiteIntelix.
 *
 * Debug mode configuration — selecting a method automatically enables it.
 * No separate "enable capture" toggle; the method choice is the switch.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_debug_source  = SITEINTELIX_Debug_Source::detect();
$siteintelix_debug_label   = SITEINTELIX_Debug_Source::get_label();
$siteintelix_debug_status  = SITEINTELIX_Debug_Source::get_status();
$siteintelix_debug_method  = SITEINTELIX_Debug_Source::get_method();
$siteintelix_wpc_writable  = SITEINTELIX_WP_Config::is_writable();
$siteintelix_wpc_has_block = SITEINTELIX_WP_Config::has_siteintelix_block();
$siteintelix_external_dbg  = SITEINTELIX_Debug_Source::has_external_wp_debug();
$siteintelix_form_url      = admin_url( 'admin-post.php' );

// Log file paths for display.
$siteintelix_log_mu  = 'wp-content/siteintelix-debug.log';
$siteintelix_log_wpc = 'wp-content/debug.log';
?>
<div class="wrap siteintelix-wrap siteintelix-settings-wrap" id="siteintelix-settings-page">

	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<?php if ( isset( $_GET['siteintelix_settings_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success">
			<p><?php esc_html_e( 'Debug settings saved successfully.', 'siteintelix' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['siteintelix_settings_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['siteintelix_settings_error'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $siteintelix_external_dbg ) : ?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Notice:', 'siteintelix' ); ?></strong>
				<?php esc_html_e( 'WP_DEBUG is currently defined in wp-config.php outside of SiteIntelix control. The wp-config.php method may conflict.', 'siteintelix' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- ===== Page Header ===== -->
	<div class="siteintelix-header">
		<div class="siteintelix-header__inner">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-admin-generic" aria-hidden="true"></span>
				<h1 class="siteintelix-header__title">
					<?php esc_html_e( 'Settings', 'siteintelix' ); ?>
				</h1>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
			</div>
		</div>
	</div>

	<!-- ===== Debug Status Banner ===== -->
	<div class="siteintelix-settings-status">
		<div class="siteintelix-settings-status__main">
			<span class="siteintelix-settings-status__icon dashicons dashicons-info-outline" aria-hidden="true"></span>
			<div>
				<strong><?php esc_html_e( 'Debug Mode', 'siteintelix' ); ?></strong>
				<span class="siteintelix-badge siteintelix-badge--<?php echo esc_attr( $siteintelix_debug_status ); ?>">
					<?php echo esc_html( $siteintelix_debug_label ); ?>
				</span>
			</div>
		</div>
		<div class="siteintelix-settings-status__meta">
			<?php if ( 'wp_config' === $siteintelix_debug_method ) : ?>
				<code><?php echo esc_html( $siteintelix_log_wpc ); ?></code>
			<?php else : ?>
				<code><?php echo esc_html( $siteintelix_log_mu ); ?></code>
			<?php endif; ?>
		</div>
	</div>

	<!-- ===== Debug Method Selection ===== -->
	<form method="post" action="<?php echo esc_url( $siteintelix_form_url ); ?>" id="siteintelix-debug-settings-form">
		<?php wp_nonce_field( 'siteintelix_save_debug_settings' ); ?>
		<input type="hidden" name="action" value="siteintelix_save_debug_settings">

		<h2 class="siteintelix-settings-section-title">
			<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
			<?php esc_html_e( 'Debug Mode Configuration', 'siteintelix' ); ?>
		</h2>

		<p class="siteintelix-settings-help">
			<?php esc_html_e( 'Select a debug method and click Save. The selected method is automatically activated — no separate enable step needed.', 'siteintelix' ); ?>
		</p>

		<div class="siteintelix-method-grid">

			<!-- Card 1: MU Plugin -->
			<label class="siteintelix-method-card<?php echo ( 'mu' === $siteintelix_debug_method ) ? ' is-selected' : ''; ?>" data-method="mu">
				<input
					type="radio"
					name="siteintelix_debug_method"
					value="mu"
					<?php checked( $siteintelix_debug_method, 'mu' ); ?>
					class="siteintelix-method-card__radio"
				>
				<div class="siteintelix-method-card__header">
					<span class="siteintelix-method-card__icon dashicons dashicons-shield" aria-hidden="true"></span>
					<span class="siteintelix-method-card__title"><?php esc_html_e( 'MU Plugin', 'siteintelix' ); ?></span>
					<span class="siteintelix-badge siteintelix-badge--good"><?php esc_html_e( 'Recommended', 'siteintelix' ); ?></span>
				</div>
				<p class="siteintelix-method-card__desc">
					<?php esc_html_e( 'Safe and non-invasive. Creates an MU-plugin that captures PHP errors without editing any core files. Fully reversible at any time.', 'siteintelix' ); ?>
				</p>
				<ul class="siteintelix-method-card__features">
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'No core file edits', 'siteintelix' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Isolated log: wp-content/siteintelix-debug.log', 'siteintelix' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Safe for staging and production', 'siteintelix' ); ?></li>
				</ul>
				<div class="siteintelix-method-card__log-path">
					<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
					<code><?php echo esc_html( $siteintelix_log_mu ); ?></code>
				</div>
			</label>

			<!-- Card 2: wp-config.php -->
			<label class="siteintelix-method-card<?php echo ( 'wp_config' === $siteintelix_debug_method ) ? ' is-selected' : ''; ?>" data-method="wp_config">
				<input
					type="radio"
					name="siteintelix_debug_method"
					value="wp_config"
					<?php checked( $siteintelix_debug_method, 'wp_config' ); ?>
					class="siteintelix-method-card__radio"
				>
				<div class="siteintelix-method-card__header">
					<span class="siteintelix-method-card__icon dashicons dashicons-editor-code" aria-hidden="true"></span>
					<span class="siteintelix-method-card__title"><?php esc_html_e( 'wp-config.php', 'siteintelix' ); ?></span>
					<span class="siteintelix-badge siteintelix-badge--warning"><?php esc_html_e( 'Advanced', 'siteintelix' ); ?></span>
				</div>
				<p class="siteintelix-method-card__desc">
					<?php esc_html_e( 'Enables native WP_DEBUG constants directly in wp-config.php. WordPress logs to the standard debug.log file. A backup is created before any edit.', 'siteintelix' ); ?>
				</p>
				<ul class="siteintelix-method-card__features">
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Native WordPress WP_DEBUG + WP_DEBUG_LOG', 'siteintelix' ); ?></li>
					<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Automatic backup: wp-config.php.bak', 'siteintelix' ); ?></li>
					<li><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Modifies wp-config.php directly', 'siteintelix' ); ?></li>
				</ul>
				<?php if ( ! $siteintelix_wpc_writable ) : ?>
					<div class="siteintelix-method-card__warning">
						<span class="dashicons dashicons-lock" aria-hidden="true"></span>
						<?php esc_html_e( 'wp-config.php is not writable. Check file permissions to use this method.', 'siteintelix' ); ?>
					</div>
				<?php endif; ?>
				<div class="siteintelix-method-card__log-path">
					<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
					<code><?php echo esc_html( $siteintelix_log_wpc ); ?></code>
				</div>
			</label>

		</div><!-- /.siteintelix-method-grid -->

		<div class="siteintelix-settings-submit">
			<button type="submit" class="siteintelix-btn siteintelix-btn--save" id="siteintelix-save-settings-btn">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span>
				<?php esc_html_e( 'Save & Activate Selected Method', 'siteintelix' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-debug-log' ) ); ?>" class="siteintelix-settings-view-log-link">
				<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
				<?php esc_html_e( 'View Debug Log', 'siteintelix' ); ?>
			</a>
		</div>
	</form>

</div><!-- /.wrap -->
