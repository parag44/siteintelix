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
$siteintelix_wpc_writable  = SITEINTELIX_WP_Config::is_writable();
$siteintelix_external_dbg  = SITEINTELIX_Debug_Source::has_external_wp_debug();

$siteintelix_form_url      = admin_url( 'admin-post.php' );
$siteintelix_fix_url       = add_query_arg( array( 'action' => 'siteintelix_fix_debug_conflict', '_wpnonce' => wp_create_nonce( 'siteintelix_fix_debug_conflict' ) ), $siteintelix_form_url );
$siteintelix_log_view_url  = admin_url( 'admin.php?page=siteintelix-debug-log' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect status flag.
$siteintelix_settings_saved = isset( $_GET['siteintelix_settings_saved'] );
$siteintelix_logs_per_page  = (int) get_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, 25 );
$siteintelix_logs_per_page  = min( 500, max( 10, $siteintelix_logs_per_page ) );
$siteintelix_debug_ui       = get_option( SITEINTELIX_DEBUG_UI_OPTION, 'modern' );
if ( ! in_array( $siteintelix_debug_ui, array( 'classic', 'modern', 'terminal_dark' ), true ) ) {
	$siteintelix_debug_ui = 'modern';
}

// Log file path for display.
$siteintelix_log_path = 'wp-content/siteintelix-debug.log';

// Active source path.
$siteintelix_active_path = $siteintelix_log_path;
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
			<?php if ( $siteintelix_settings_saved ) : ?>
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
						<p class="sitx-alert__msg"><?php esc_html_e( 'WP_DEBUG is defined manually in wp-config.php. This overrides SiteIntelix Debug Log Viewer and may cause issues.', 'siteintelix' ); ?></p>
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
							<?php /* translators: %s: active debug method label. */ ?>
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
			<p class="sitx-section-desc"><?php esc_html_e( 'Choose how you want SiteIntelix Debug Log Viewer to capture and log errors.', 'siteintelix' ); ?></p>
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
							<?php esc_html_e( 'Non-invasive capture layer that works without editing wp-config.php and writes to the private SiteIntelix log.', 'siteintelix' ); ?>
						</p>
						
						<ul class="sitx-feature-list">
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Captures PHP warnings, notices, deprecated messages, and fatal shutdown errors', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Captures WordPress doing-it-wrong and deprecation notices even when WP_DEBUG is false', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'No wp-config.php edits; logs to wp-content/siteintelix-debug.log', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-warning" style="color:var(--siteintelix-warn);"></span> <?php esc_html_e( 'Cannot capture errors that happen before MU plugins load', 'siteintelix' ); ?></li>
						</ul>
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
							<?php esc_html_e( 'Uses native WordPress/PHP debug constants by writing them directly into wp-config.php.', 'siteintelix' ); ?>
						</p>
						
						<ul class="sitx-feature-list">
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Captures native PHP and WordPress debug output through WP_DEBUG', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Can catch earlier bootstrap errors after wp-config.php is loaded', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Writes native logs to wp-content/siteintelix-debug.log', 'siteintelix' ); ?></li>
							<li><span class="dashicons dashicons-warning" style="color:var(--siteintelix-warn);"></span> <?php esc_html_e( 'Requires wp-config.php file changes and depends on WP_DEBUG being active', 'siteintelix' ); ?></li>
						</ul>
					</div>
				</label>
			</div>

			<!-- ===== 5. Viewer UI Selection ===== -->
			<section class="sitx-section-header">
				<h2 class="sitx-section-title"><?php esc_html_e( 'Select Viewer UI', 'siteintelix' ); ?></h2>
				<p class="sitx-section-desc"><?php esc_html_e( 'Choose the Debug Log Viewer layout that works best for your workflow.', 'siteintelix' ); ?></p>
			</section>

			<div class="sitx-selection-grid sitx-selection-grid--compact">
				<label class="sitx-select-card sitx-select-card--compact <?php echo ( 'modern' === $siteintelix_debug_ui ) ? 'is-selected' : ''; ?>" data-ui="modern">
					<div class="sitx-select-card__radio">
						<input type="radio" name="siteintelix_debug_ui" value="modern" <?php checked( $siteintelix_debug_ui, 'modern' ); ?>>
						<span class="sitx-select-card__radio-custom"></span>
					</div>

					<div class="sitx-select-card__content">
						<div class="sitx-select-card__icon sitx-select-card__icon--blue">
							<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span>
						</div>
						<h3 class="sitx-select-card__title"><?php esc_html_e( 'Modern grouped cards', 'siteintelix' ); ?></h3>
						<p class="sitx-select-card__desc"><?php esc_html_e( 'Grouped error cards with summary metrics, filters, timelines, and expandable stack traces.', 'siteintelix' ); ?></p>
					</div>
				</label>

				<label class="sitx-select-card sitx-select-card--compact <?php echo ( 'classic' === $siteintelix_debug_ui ) ? 'is-selected' : ''; ?>" data-ui="classic">
					<div class="sitx-select-card__radio">
						<input type="radio" name="siteintelix_debug_ui" value="classic" <?php checked( $siteintelix_debug_ui, 'classic' ); ?>>
						<span class="sitx-select-card__radio-custom"></span>
					</div>

					<div class="sitx-select-card__content">
						<div class="sitx-select-card__icon sitx-select-card__icon--orange">
							<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
						</div>
						<h3 class="sitx-select-card__title"><?php esc_html_e( 'Classic table', 'siteintelix' ); ?></h3>
						<p class="sitx-select-card__desc"><?php esc_html_e( 'Traditional table layout with type, datetime, description, file, and line columns.', 'siteintelix' ); ?></p>
					</div>
				</label>

				<label class="sitx-select-card sitx-select-card--compact <?php echo ( 'terminal_dark' === $siteintelix_debug_ui ) ? 'is-selected' : ''; ?>" data-ui="terminal_dark">
					<div class="sitx-select-card__radio">
						<input type="radio" name="siteintelix_debug_ui" value="terminal_dark" <?php checked( $siteintelix_debug_ui, 'terminal_dark' ); ?>>
						<span class="sitx-select-card__radio-custom"></span>
					</div>

					<div class="sitx-select-card__content">
						<div class="sitx-select-card__icon sitx-select-card__icon--dark">
							<span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
						</div>
						<h3 class="sitx-select-card__title"><?php esc_html_e( 'Terminal Dark', 'siteintelix' ); ?></h3>
						<p class="sitx-select-card__desc"><?php esc_html_e( 'Dark terminal-style log stream showing the latest 100 entries without pagination.', 'siteintelix' ); ?></p>
					</div>
				</label>
			</div>

			<!-- ===== 6. Log Display Settings ===== -->
			<section class="sitx-settings-panel sitx-settings-panel--compact">
				<div>
					<h2 class="sitx-settings-panel__title"><?php esc_html_e( 'Log Display Settings', 'siteintelix' ); ?></h2>
					<p class="sitx-settings-panel__desc"><?php esc_html_e( 'Choose how many log entries or groups are shown on each page.', 'siteintelix' ); ?></p>
				</div>
				<label class="sitx-number-field">
					<span><?php esc_html_e( 'Logs per page', 'siteintelix' ); ?></span>
					<input type="number" name="siteintelix_logs_per_page" min="10" max="500" step="5" value="<?php echo esc_attr( (string) $siteintelix_logs_per_page ); ?>">
				</label>
			</section>

			<!-- ===== 7. Footer Actions ===== -->
			<div style="display:flex; align-items:center; gap:24px; border-top:1px solid var(--siteintelix-border); padding-top:32px;">
				<button type="submit" class="sitx-btn sitx-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save & Apply Method', 'siteintelix' ); ?>
				</button>
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
		const radioName = $radio.attr('name');
		
		// Update radio button
		$radio.prop('checked', true);
		
		// Update visual state for the current radio group only.
		$('input[type="radio"][name="' + radioName + '"]').closest('.sitx-select-card').removeClass('is-selected');
		$card.addClass('is-selected');
	});
});
</script>
