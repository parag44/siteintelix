<?php
/**
 * Premium Settings page view for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_debug_source  = SITEINTELIX_Debug_Source::detect();
$siteintelix_debug_label   = SITEINTELIX_Debug_Source::get_label();
$siteintelix_debug_status  = SITEINTELIX_Debug_Source::get_status(); // badge class: good, warning, critical
$siteintelix_debug_method  = SITEINTELIX_Debug_Source::get_method();
$siteintelix_prev_method   = get_option( 'siteintelix_previous_debug_method' );
$siteintelix_wpc_writable  = SITEINTELIX_WP_Config::is_writable();
$siteintelix_external_dbg  = SITEINTELIX_Debug_Source::has_external_wp_debug();

$siteintelix_form_url      = admin_url( 'admin-post.php' );
$siteintelix_fix_url       = add_query_arg( array( 'action' => 'siteintelix_fix_debug_conflict', '_wpnonce' => wp_create_nonce( 'siteintelix_fix_debug_conflict' ) ), $siteintelix_form_url );
$siteintelix_revert_url    = add_query_arg( array( 'action' => 'siteintelix_revert_debug_method', '_wpnonce' => wp_create_nonce( 'siteintelix_revert_debug_method' ) ), $siteintelix_form_url );
$siteintelix_log_view_url  = admin_url( 'admin.php?page=siteintelix-debug-log' );

// Log file paths for display.
$siteintelix_log_mu  = 'wp-content/siteintelix-debug.log';
$siteintelix_log_wpc = 'wp-content/debug.log';

// Active source path.
$siteintelix_active_path = ( 'wp_config' === $siteintelix_debug_source ) ? $siteintelix_log_wpc : $siteintelix_log_mu;
?>

<div class="wrap siteintelix-wrap siteintelix-settings-premium" id="siteintelix-settings-page">

	<!-- ===== 1. Premium Header ===== -->
	<header class="siteintelix-premium-header">
		<div class="siteintelix-premium-header__content">
			<div class="siteintelix-premium-header__title-group">
				<span class="siteintelix-premium-header__icon dashicons dashicons-admin-generic" aria-hidden="true"></span>
				<div class="siteintelix-premium-header__text">
					<h1 class="siteintelix-premium-header__title"><?php esc_html_e( 'Debug Settings', 'siteintelix' ); ?></h1>
					<p class="siteintelix-premium-header__desc"><?php esc_html_e( 'Configure how WordPress debug mode is enabled.', 'siteintelix' ); ?></p>
				</div>
			</div>
			<div class="siteintelix-premium-header__meta">
				<span class="siteintelix-premium-header__version">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
				<a href="<?php echo esc_url( $siteintelix_log_view_url ); ?>" class="siteintelix-premium-btn siteintelix-premium-btn--white">
					<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
					<?php esc_html_e( 'View Debug Log', 'siteintelix' ); ?>
				</a>
			</div>
		</div>
	</header>

	<div class="siteintelix-premium-container">

		<!-- ===== 2. Notices Section ===== -->
		<div id="siteintelix-notices-slot">
			<?php if ( isset( $_GET['siteintelix_settings_saved'] ) ) : ?>
				<div class="siteintelix-premium-alert siteintelix-premium-alert--success">
					<div class="siteintelix-premium-alert__icon">
						<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					</div>
					<div class="siteintelix-premium-alert__content">
						<strong><?php esc_html_e( 'Settings saved successfully.', 'siteintelix' ); ?></strong>
						<p><?php esc_html_e( 'Your debug configuration has been updated.', 'siteintelix' ); ?></p>
					</div>
					<button type="button" class="siteintelix-premium-alert__close" onclick="this.parentElement.remove();">&times;</button>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['siteintelix_conflict_fixed'] ) ) : ?>
				<div class="siteintelix-premium-alert siteintelix-premium-alert--info">
					<div class="siteintelix-premium-alert__icon">
						<span class="dashicons dashicons-info" aria-hidden="true"></span>
					</div>
					<div class="siteintelix-premium-alert__content">
						<strong><?php esc_html_e( 'Conflict Resolved.', 'siteintelix' ); ?></strong>
						<p><?php esc_html_e( 'The external wp-config.php debug settings have been removed.', 'siteintelix' ); ?></p>
					</div>
					<button type="button" class="siteintelix-premium-alert__close" onclick="this.parentElement.remove();">&times;</button>
				</div>
			<?php endif; ?>

			<?php if ( $siteintelix_external_dbg ) : ?>
				<div class="siteintelix-premium-alert siteintelix-premium-alert--warning">
					<div class="siteintelix-premium-alert__icon">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					</div>
					<div class="siteintelix-premium-alert__content">
						<strong><?php esc_html_e( 'Debug Conflict Detected', 'siteintelix' ); ?></strong>
						<p><?php esc_html_e( 'WP_DEBUG is defined in wp-config.php outside of SiteIntelix control. This may override your selected method and cause unexpected behavior.', 'siteintelix' ); ?></p>
					</div>
					<div class="siteintelix-premium-alert__actions">
						<a href="#" class="siteintelix-premium-btn siteintelix-premium-btn--outline-dark"><?php esc_html_e( 'Learn More', 'siteintelix' ); ?></a>
						<a href="<?php echo esc_url( $siteintelix_fix_url ); ?>" class="siteintelix-premium-btn siteintelix-premium-btn--orange">
							<?php esc_html_e( 'Fix Automatically', 'siteintelix' ); ?>
						</a>
					</div>
					<button type="button" class="siteintelix-premium-alert__close" onclick="this.parentElement.remove();">&times;</button>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['siteintelix_settings_error'] ) ) : ?>
				<div class="siteintelix-premium-alert siteintelix-premium-alert--error">
					<div class="siteintelix-premium-alert__icon">
						<span class="dashicons dashicons-no" aria-hidden="true"></span>
					</div>
					<div class="siteintelix-premium-alert__content">
						<strong><?php esc_html_e( 'Configuration Error', 'siteintelix' ); ?></strong>
						<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['siteintelix_settings_error'] ) ) ) ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- ===== 3. Current Debug Status Bar ===== -->
		<div class="siteintelix-premium-status-bar">
			<div class="siteintelix-premium-status-bar__info">
				<span class="siteintelix-premium-status-bar__icon dashicons dashicons-info" aria-hidden="true"></span>
				<span class="siteintelix-premium-status-bar__label"><?php esc_html_e( 'Current Debug Status', 'siteintelix' ); ?></span>
				<span class="siteintelix-premium-status-bar__badge siteintelix-status-badge--active"><?php esc_html_e( 'ACTIVE', 'siteintelix' ); ?></span>
				<p class="siteintelix-premium-status-bar__text">
					<?php printf( esc_html__( 'Debug mode is currently active via %s.', 'siteintelix' ), 'wp_config' === $siteintelix_debug_source ? 'wp-config.php' : 'MU Plugin' ); ?>
				</p>
			</div>
			<div class="siteintelix-premium-status-bar__path">
				<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
				<code><?php echo esc_html( $siteintelix_active_path ); ?></code>
			</div>
		</div>

		<!-- ===== 4. Method Selection Header ===== -->
		<div class="siteintelix-premium-selection-header">
			<h2 class="siteintelix-premium-section-title">
				<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
				<?php esc_html_e( 'Select Debug Method', 'siteintelix' ); ?>
			</h2>
			<p class="siteintelix-premium-section-desc">
				<?php esc_html_e( 'Choose the method you prefer to enable WordPress debug mode. The selected method will be activated immediately.', 'siteintelix' ); ?>
			</p>
		</div>

		<!-- ===== 5. Selection Grid ===== -->
		<form method="post" action="<?php echo esc_url( $siteintelix_form_url ); ?>" id="siteintelix-debug-settings-form">
			<?php wp_nonce_field( 'siteintelix_save_debug_settings' ); ?>
			<input type="hidden" name="action" value="siteintelix_save_debug_settings">

			<div class="siteintelix-premium-grid">
				
				<!-- Card 1: MU Plugin -->
				<label class="siteintelix-premium-card <?php echo ( 'mu' === $siteintelix_debug_method ) ? 'is-selected' : ''; ?>" data-method="mu">
					<div class="siteintelix-premium-card__selection">
						<input type="radio" name="siteintelix_debug_method" value="mu" <?php checked( $siteintelix_debug_method, 'mu' ); ?>>
						<span class="siteintelix-premium-card__radio-custom"></span>
					</div>
					
					<div class="siteintelix-premium-card__body">
						<div class="siteintelix-premium-card__header">
							<div class="siteintelix-premium-card__icon siteintelix-premium-card__icon--blue">
								<span class="dashicons dashicons-art" aria-hidden="true"></span>
							</div>
							<div class="siteintelix-premium-card__title-group">
								<h3 class="siteintelix-premium-card__title"><?php esc_html_e( 'MU Plugin (Recommended)', 'siteintelix' ); ?></h3>
								<span class="siteintelix-premium-badge siteintelix-premium-badge--recommended"><?php esc_html_e( 'Recommended', 'siteintelix' ); ?></span>
							</div>
						</div>
						
						<p class="siteintelix-premium-card__desc">
							<?php esc_html_e( 'Safe and non-invasive. Creates an MU-plugin that captures PHP errors without modifying any core files.', 'siteintelix' ); ?>
						</p>
						
						<ul class="siteintelix-premium-feature-list">
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'No core file edits', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Isolated log: wp-content/siteintelix-debug.log', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Safe for staging and production', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Fully reversible', 'siteintelix' ); ?></li>
						</ul>
						
						<div class="siteintelix-premium-card__path-box">
							<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
							<code><?php echo esc_html( $siteintelix_log_mu ); ?></code>
						</div>
					</div>
				</label>

				<!-- Card 2: wp-config.php -->
				<label class="siteintelix-premium-card <?php echo ( 'wp_config' === $siteintelix_debug_method ) ? 'is-selected' : ''; ?>" data-method="wp_config">
					<div class="siteintelix-premium-card__selection">
						<input type="radio" name="siteintelix_debug_method" value="wp_config" <?php checked( $siteintelix_debug_method, 'wp_config' ); ?>>
						<span class="siteintelix-premium-card__radio-custom"></span>
					</div>
					
					<div class="siteintelix-premium-card__body">
						<div class="siteintelix-premium-card__header">
							<div class="siteintelix-premium-card__icon siteintelix-premium-card__icon--orange">
								<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
							</div>
							<div class="siteintelix-premium-card__title-group">
								<h3 class="siteintelix-premium-card__title"><?php esc_html_e( 'wp-config.php (Advanced)', 'siteintelix' ); ?></h3>
								<span class="siteintelix-premium-badge siteintelix-premium-badge--advanced"><?php esc_html_e( 'Advanced', 'siteintelix' ); ?></span>
							</div>
						</div>
						
						<p class="siteintelix-premium-card__desc">
							<?php esc_html_e( 'Enables native WP_DEBUG constants directly in wp-config.php. WordPress logs to the standard debug.log file.', 'siteintelix' ); ?>
						</p>
						
						<ul class="siteintelix-premium-feature-list">
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Native WordPress WP_DEBUG + WP_DEBUG_LOG', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e( 'Automatic backup: wp-config.php.bak', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Modifies wp-config.php directly', 'siteintelix' ); ?></li>
						</ul>
						
						<div class="siteintelix-premium-card__path-box">
							<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
							<code><?php echo esc_html( $siteintelix_log_wpc ); ?></code>
						</div>
					</div>
				</label>
			</div>

			<!-- ===== 6. Footer Actions ===== -->
			<div class="siteintelix-premium-footer">
				<button type="submit" class="siteintelix-premium-btn siteintelix-premium-btn--primary">
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e( 'Save & Activate Selected Method', 'siteintelix' ); ?>
				</button>
				
				<?php if ( $siteintelix_prev_method ) : ?>
					<a href="<?php echo esc_url( $siteintelix_revert_url ); ?>" class="siteintelix-premium-revert-link">
						<span class="dashicons dashicons-undo" aria-hidden="true"></span>
						<?php esc_html_e( 'Revert to Previous Method', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</form>

	</div><!-- /.siteintelix-premium-container -->
</div><!-- /.wrap -->

<script>
jQuery(document).ready(function($) {
	// Card selection visual feedback
	$('.siteintelix-premium-card').on('click', function() {
		$('.siteintelix-premium-card').removeClass('is-selected');
		$(this).addClass('is-selected');
	});
});
</script>
