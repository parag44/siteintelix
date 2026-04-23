<?php
/**
 * Debug Log Viewer page for SiteIntelix.
 *
 * Reads from the log file that matches the active debug method:
 *   - MU Plugin mode  → wp-content/siteintelix-debug.log
 *   - wp-config mode  → wp-content/debug.log
 *
 * @package SiteIntelix
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_log_data      = SITEINTELIX_Debug_Log::get_data( 300 );
$siteintelix_active_method = isset( $siteintelix_log_data['method'] ) ? $siteintelix_log_data['method'] : 'mu';
$siteintelix_total_entries = count( $siteintelix_log_data['entries'] );
$siteintelix_fatal_count   = isset( $siteintelix_log_data['counts']['FATAL'] ) ? (int) $siteintelix_log_data['counts']['FATAL'] : 0;
$siteintelix_error_count   = isset( $siteintelix_log_data['counts']['ERROR'] ) ? (int) $siteintelix_log_data['counts']['ERROR'] : 0;
$siteintelix_warn_count    = isset( $siteintelix_log_data['counts']['WARN'] )  ? (int) $siteintelix_log_data['counts']['WARN']  : 0;
$siteintelix_info_count    = isset( $siteintelix_log_data['counts']['INFO'] )  ? (int) $siteintelix_log_data['counts']['INFO']  : 0;
$siteintelix_debug_count   = isset( $siteintelix_log_data['counts']['DEBUG'] ) ? (int) $siteintelix_log_data['counts']['DEBUG'] : 0;
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_mode_label    = SITEINTELIX_Debug_Log::get_mode_label( $siteintelix_active_method );

// Build path display (relative to ABSPATH for brevity).
$siteintelix_path_display = isset( $siteintelix_log_data['path'] ) ? $siteintelix_log_data['path'] : '';
if ( '' !== $siteintelix_path_display && defined( 'ABSPATH' ) ) {
	$siteintelix_path_display = str_replace( ABSPATH, '', $siteintelix_path_display );
}

$siteintelix_clear_url   = admin_url( 'admin-post.php' );
$siteintelix_refresh_url = admin_url( 'admin.php?page=siteintelix-debug-log' );
$siteintelix_settings_url = admin_url( 'admin.php?page=siteintelix-settings' );
$siteintelix_download_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ),
	'siteintelix_download_debug_log'
);
?>
<div class="wrap siteintelix-wrap" id="siteintelix-debug-log-page">

	<!-- ===== 1. Unified Header ===== -->
	<header class="siteintelix-header">
		<div class="siteintelix-header__content">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-media-text" aria-hidden="true"></span>
				<div class="siteintelix-header__text">
					<h1 class="siteintelix-header__title"><?php esc_html_e( 'Debug Log Viewer', 'siteintelix' ); ?></h1>
					<p class="siteintelix-header__desc"><?php esc_html_e( 'Live stream of errors and system notices from your environment.', 'siteintelix' ); ?></p>
				</div>
			</div>
			
			<div class="siteintelix-header__actions">
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
				
				<a class="sitx-btn sitx-btn--white" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
				</a>

				<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>" style="margin:0;">
					<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
					<input type="hidden" name="action" value="siteintelix_clear_debug_log">
					<button type="submit" class="sitx-btn sitx-btn--white">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Clear', 'siteintelix' ); ?>
					</button>
				</form>

				<?php if ( $siteintelix_log_has_file ) : ?>
					<a class="sitx-btn sitx-btn--primary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download Log', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</header>

	<div class="siteintelix-container">

		<!-- ===== 2. Quick Stats & Mode Info ===== -->
		<div class="siteintelix-debug-top-grid">
			
			<!-- Mode Banner -->
			<div class="sitx-card sitx-card--interactive siteintelix-debug-mode-card">
				<div class="siteintelix-debug-mode-card__content">
					<div class="sitx-select-card__icon <?php echo 'wp_config' === $siteintelix_active_method ? 'sitx-select-card__icon--orange' : 'sitx-select-card__icon--blue'; ?>">
						<span class="dashicons <?php echo 'wp_config' === $siteintelix_active_method ? 'dashicons-editor-code' : 'dashicons-shield'; ?>"></span>
					</div>
					<div class="siteintelix-debug-mode-card__text">
						<h3 class="siteintelix-debug-mode-card__title">
							<?php echo 'wp_config' === $siteintelix_active_method ? 'wp-config.php' : 'MU Plugin'; ?> <?php esc_html_e( 'Mode Active', 'siteintelix' ); ?>
						</h3>
						<p class="siteintelix-debug-mode-card__desc"><?php echo esc_html( $siteintelix_mode_label ); ?></p>
					</div>
				</div>
				<a href="<?php echo esc_url( $siteintelix_settings_url ); ?>" class="sitx-btn sitx-btn--outline siteintelix-debug-mode-card__switch">
					<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Switch', 'siteintelix' ); ?>
				</a>
			</div>

			<!-- Quick Count -->
			<div class="sitx-card siteintelix-debug-entries-card">
				<span class="siteintelix-debug-entries-card__label">
					<?php esc_html_e( 'Recent Entries', 'siteintelix' ); ?>
				</span>
				<span class="siteintelix-debug-entries-card__value">
					<?php echo (int) $siteintelix_total_entries; ?>
				</span>
			</div>
		</div>

		<!-- ===== 3. Summary Badges ===== -->
			<div style="display:flex; gap:12px; margin-bottom: 24px; flex-wrap:wrap;">
				<div class="sitx-badge sitx-badge--critical" style="padding: 8px 16px;">
					<span class="dashicons dashicons-warning"></span>
					<?php /* translators: %d: number of fatal and error log entries. */ ?>
					<?php printf( esc_html__( '%d Fatal Errors', 'siteintelix' ), (int) ( $siteintelix_fatal_count + $siteintelix_error_count ) ); ?>
				</div>
				<div class="sitx-badge sitx-badge--warning" style="padding: 8px 16px;">
					<span class="dashicons dashicons-warning"></span>
					<?php /* translators: %d: number of warning log entries. */ ?>
					<?php printf( esc_html__( '%d Warnings', 'siteintelix' ), (int) $siteintelix_warn_count ); ?>
				</div>
				<div class="sitx-badge sitx-badge--info" style="padding: 8px 16px;">
					<span class="dashicons dashicons-info"></span>
					<?php /* translators: %d: number of informational log entries. */ ?>
					<?php printf( esc_html__( '%d Info', 'siteintelix' ), (int) $siteintelix_info_count ); ?>
				</div>
			</div>

		<!-- ===== 4. Toolbar: Filters + Search ===== -->
		<div class="sitx-card" style="padding: 12px; margin-bottom: 12px;">
			<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
				<div style="display:flex; gap:8px;">
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter is-active" data-level="all"><?php esc_html_e( 'All Levels', 'siteintelix' ); ?></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="fatal"><?php esc_html_e( 'Fatal', 'siteintelix' ); ?></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="warn"><?php esc_html_e( 'Warning', 'siteintelix' ); ?></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="info"><?php esc_html_e( 'Info', 'siteintelix' ); ?></button>
				</div>
				<div style="flex:1; max-width:400px; position:relative;">
					<input type="search" id="siteintelix-log-search" placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>" 
						style="width:100%; height:40px; padding: 0 40px; border-radius:10px; border:1px solid var(--siteintelix-border); background:var(--siteintelix-bg);">
					<span class="dashicons dashicons-search" style="position:absolute; left:12px; top:10px; color:var(--siteintelix-text-muted);"></span>
				</div>
			</div>
		</div>

		<!-- ===== 5. Log Console ===== -->
		<?php if ( empty( $siteintelix_log_data['entries'] ) ) : ?>
			<div class="sitx-alert sitx-alert--warning">
				<div class="sitx-alert__icon"><span class="dashicons dashicons-info"></span></div>
				<div class="sitx-alert__content">
					<p class="sitx-alert__msg">
						<?php
						if ( ! $siteintelix_log_has_file ) {
							esc_html_e( 'No log file found. Debug log will appear here once errors are captured.', 'siteintelix' );
						} else {
							esc_html_e( 'No entries found in the log file yet.', 'siteintelix' );
						}
						?>
					</p>
				</div>
			</div>
		<?php else : ?>
			<div class="siteintelix-log-console-wrap">
				<div class="siteintelix-log-console" id="siteintelix-log-console" style="border-radius:12px; border: 1px solid #26324a;">
					<?php foreach ( $siteintelix_log_data['entries'] as $siteintelix_entry ) : ?>
						<div class="siteintelix-log-line siteintelix-log-line--<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>"
							data-level="<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>"
							data-message="<?php echo esc_attr( strtolower( $siteintelix_entry['message'] ) ); ?>"
						>
							<span class="siteintelix-log-line__ts">[<?php echo esc_html( $siteintelix_entry['timestamp'] ? $siteintelix_entry['timestamp'] : '-' ); ?>]</span>
							<span class="siteintelix-log-line__level"><?php echo esc_html( $siteintelix_entry['level'] ); ?></span>
							<span class="siteintelix-log-line__msg"><?php echo esc_html( $siteintelix_entry['message'] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			
			<div style="margin-top:16px; display:flex; align-items:center; justify-content:space-between; color:var(--siteintelix-text-muted); font-size:12px;">
				<div style="display:flex; align-items:center; gap:8px;">
					<span class="dashicons dashicons-editor-code" style="font-size:16px;"></span>
					<code><?php echo esc_html( $siteintelix_path_display ); ?></code>
				</div>
				<?php if ( $siteintelix_log_data['size'] > 0 ) : ?>
					<span class="sitx-badge sitx-badge--info"><?php echo esc_html( size_format( (int) $siteintelix_log_data['size'] ) ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div><!-- /.siteintelix-container -->
</div><!-- /.wrap -->
