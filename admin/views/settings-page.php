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

<div class="wrap siteintelix-wrap" id="siteintelix-settings-page">

	<!-- ===== 1. Unified Header ===== -->
	<header class="siteintelix-header">
		<div class="siteintelix-header__content">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-editor-code" aria-hidden="true"></span>
				<div class="siteintelix-header__text">
					<h1 class="siteintelix-header__title"><?php esc_html_e( 'Debug Settings', 'siteintelix' ); ?></h1>
					<p class="siteintelix-header__desc"><?php esc_html_e( 'Configure how WordPress debug mode is enabled.', 'siteintelix' ); ?></p>
				</div>
			</div>
			<div class="siteintelix-header__actions">
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
				<a href="<?php echo esc_url( $siteintelix_log_view_url ); ?>" class="sitx-btn sitx-btn--white">
					<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
					<?php esc_html_e( 'View Debug Log', 'siteintelix' ); ?>
				</a>
			</div>
		</div>
	</header>

	<div class="siteintelix-container">

		<!-- ===== 2. Notices & Alerts ===== -->
		<div id="siteintelix-notices-slot">
			<?php if ( isset( $_GET['siteintelix_settings_saved'] ) ) : ?>
				<div class="sitx-alert sitx-alert--success">
					<div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Settings saved successfully.', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg"><?php esc_html_e( 'Your debug configuration has been updated.', 'siteintelix' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $siteintelix_external_dbg ) : ?>
				<div class="sitx-alert sitx-alert--warning">
					<div class="sitx-alert__icon"><span class="dashicons dashicons-warning"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Debug Conflict Detected', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg"><?php esc_html_e( 'WP_DEBUG is defined manually in wp-config.php. This overrides SiteIntelix and may cause issues.', 'siteintelix' ); ?></p>
					</div>
					<div class="sitx-alert__actions">
						<a href="<?php echo esc_url( $siteintelix_fix_url ); ?>" class="sitx-btn sitx-btn--orange">
							<?php esc_html_e( 'Fix Automatically', 'siteintelix' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- ===== 3. Status Bar ===== -->
		<div class="sitx-card sitx-card--interactive" style="margin-bottom: 32px; padding: 16px 24px;">
			<div style="display:flex; align-items:center; justify-content:space-between;">
				<div style="display:flex; align-items:center; gap:16px;">
					<span class="sitx-badge sitx-badge--good"><?php esc_html_e( 'System Active', 'siteintelix' ); ?></span>
					<span style="color:var(--siteintelix-text); font-weight:700; font-size:15px;">
						<?php printf( esc_html__( 'Connected via %s', 'siteintelix' ), 'wp_config' === $siteintelix_debug_source ? 'wp-config.php' : 'MU Plugin' ); ?>
					</span>
				</div>
				<div class="sitx-path-box">
					<span class="dashicons dashicons-editor-code"></span>
					<code><?php echo esc_html( $siteintelix_active_path ); ?></code>
				</div>
			</div>
		</div>

		<!-- ===== 4. Method Selection ===== -->
		<section class="sitx-section-header">
			<h2 class="sitx-section-title"><?php esc_html_e( 'Select Debug Method', 'siteintelix' ); ?></h2>
			<p class="sitx-section-desc"><?php esc_html_e( 'Choose how you want SiteIntelix to capture and log errors.', 'siteintelix' ); ?></p>
		</section>

		<form method="post" action="<?php echo esc_url( $siteintelix_form_url ); ?>" id="siteintelix-debug-settings-form">
			<?php wp_nonce_field( 'siteintelix_save_debug_settings' ); ?>
			<input type="hidden" name="action" value="siteintelix_save_debug_settings">

			<div class="sitx-selection-grid">
				
				<!-- Option 1: MU Plugin -->
				<label class="sitx-select-card <?php echo ( 'mu' === $siteintelix_debug_method ) ? 'is-selected' : ''; ?>" data-method="mu">
					<div class="sitx-select-card__radio">
						<input type="radio" name="siteintelix_debug_method" value="mu" <?php checked( $siteintelix_debug_method, 'mu' ); ?>>
						<span class="sitx-select-card__radio-custom"></span>
					</div>
					
					<div class="sitx-select-card__content">
						<div class="sitx-select-card__icon sitx-select-card__icon--blue">
							<span class="dashicons dashicons-art" aria-hidden="true" style="font-size:28px; width:28px; height:28px;"></span>
						</div>
						<h3 class="sitx-select-card__title"><?php esc_html_e( 'MU Plugin (Recommended)', 'siteintelix' ); ?></h3>
						<p class="sitx-select-card__desc">
							<?php esc_html_e( 'Safe and non-invasive. Captures PHP errors without modifying any core files or site configuration.', 'siteintelix' ); ?>
						</p>
						
						<ul class="sitx-feature-list">
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'No core file edits', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Private log: siteintelix-debug.log', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Production-ready safety', 'siteintelix' ); ?></li>
						</ul>

						<div class="sitx-path-box">
							<span class="dashicons dashicons-editor-code"></span>
							<code><?php echo esc_html( $siteintelix_log_mu ); ?></code>
						</div>
					</div>
				</label>

				<!-- Option 2: wp-config.php -->
				<label class="sitx-select-card <?php echo ( 'wp_config' === $siteintelix_debug_method ) ? 'is-selected' : ''; ?>" data-method="wp_config">
					<div class="sitx-select-card__radio">
						<input type="radio" name="siteintelix_debug_method" value="wp_config" <?php checked( $siteintelix_debug_method, 'wp_config' ); ?>>
						<span class="sitx-select-card__radio-custom"></span>
					</div>
					
					<div class="sitx-select-card__content">
						<div class="sitx-select-card__icon sitx-select-card__icon--orange">
							<span class="dashicons dashicons-editor-code" aria-hidden="true" style="font-size:28px; width:28px; height:28px;"></span>
						</div>
						<h3 class="sitx-select-card__title"><?php esc_html_e( 'wp-config.php (Advanced)', 'siteintelix' ); ?></h3>
						<p class="sitx-select-card__desc">
							<?php esc_html_e( 'Uses native WordPress debugging constants. Directly edits your site configuration file.', 'siteintelix' ); ?>
						</p>
						
						<ul class="sitx-feature-list">
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Native WP_DEBUG integration', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Standard debug.log output', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-warning" style="color:var(--siteintelix-warn);"></span> <?php esc_html_e( 'File-level modifications', 'siteintelix' ); ?></li>
						</ul>

						<div class="sitx-path-box">
							<span class="dashicons dashicons-editor-code"></span>
							<code><?php echo esc_html( $siteintelix_log_wpc ); ?></code>
						</div>
					</div>
				</label>
			</div>

			<!-- ===== 5. Footer Actions ===== -->
			<div style="display:flex; align-items:center; gap:24px; border-top:1px solid var(--siteintelix-border); padding-top:32px;">
				<button type="submit" class="sitx-btn sitx-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save & Apply Method', 'siteintelix' ); ?>
				</button>
				
				<?php if ( $siteintelix_prev_method ) : ?>
					<a href="<?php echo esc_url( $siteintelix_revert_url ); ?>" class="sitx-btn sitx-btn--outline" style="font-weight:600;">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Revert to Previous', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</form>

	</div><!-- /.siteintelix-container -->
</div><!-- /.wrap -->

<script>
jQuery(document).ready(function($) {
	// Enhanced Card Selection Logic
	$('.sitx-select-card').on('click', function() {
		const $card = $(this);
		const $radio = $card.find('input[type="radio"]');
		
		// Update radio button
		$radio.prop('checked', true);
		
		// Update visual state
		$('.sitx-select-card').removeClass('is-selected');
		$card.addClass('is-selected');
	});
});
</script>

