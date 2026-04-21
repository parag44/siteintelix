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
<div class="wrap siteintelix-wrap siteintelix-debug-wrap" id="siteintelix-debug-log-page">
	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<?php if ( isset( $_GET['siteintelix_log_cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice <?php echo '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) ) ? 'notice-success' : 'notice-error'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			<p>
				<?php
				echo esc_html(
					'1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						? __( 'Debug log cleared successfully.', 'siteintelix' )
						: __( 'Could not clear debug log. Please check file permissions.', 'siteintelix' )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<section class="siteintelix-debug-hero">
		<div class="siteintelix-debug-hero__top">
			<div class="siteintelix-debug-hero__title-wrap">
				<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
				<h1><?php esc_html_e( 'Debug Log Viewer', 'siteintelix' ); ?></h1>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
			</div>
			<div class="siteintelix-debug-hero__actions">
				<span class="siteintelix-debug-summary-pill">
					<?php
					printf(
						/* translators: 1: total entries 2: fatal count 3: warning count */
						esc_html__( '%1$d Entries | %2$d Fatal | %3$d Warnings', 'siteintelix' ),
						(int) $siteintelix_total_entries,
						(int) ( $siteintelix_fatal_count + $siteintelix_error_count ),
						(int) $siteintelix_warn_count
					);
					?>
				</span>
				<a class="siteintelix-btn siteintelix-btn--primary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
				</a>
				<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>">
					<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
					<input type="hidden" name="action" value="siteintelix_clear_debug_log">
					<button type="submit" class="siteintelix-btn siteintelix-btn--secondary">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Clear', 'siteintelix' ); ?>
					</button>
				</form>
				<?php if ( $siteintelix_log_has_file ) : ?>
					<a class="siteintelix-btn siteintelix-btn--secondary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Metrics -->
		<div class="siteintelix-debug-metrics">
			<div class="siteintelix-debug-metric siteintelix-debug-metric--fatal">
				<span class="dashicons dashicons-warning"></span>
				<strong><?php esc_html_e( 'Fatal', 'siteintelix' ); ?></strong>
				<em><?php echo esc_html( (string) ( $siteintelix_fatal_count + $siteintelix_error_count ) ); ?></em>
			</div>
			<div class="siteintelix-debug-metric siteintelix-debug-metric--warn">
				<span class="dashicons dashicons-warning"></span>
				<strong><?php esc_html_e( 'Warning', 'siteintelix' ); ?></strong>
				<em><?php echo esc_html( (string) $siteintelix_warn_count ); ?></em>
			</div>
			<div class="siteintelix-debug-metric siteintelix-debug-metric--info">
				<span class="dashicons dashicons-info"></span>
				<strong><?php esc_html_e( 'Info', 'siteintelix' ); ?></strong>
				<em><?php echo esc_html( (string) $siteintelix_info_count ); ?></em>
			</div>
			<div class="siteintelix-debug-metric siteintelix-debug-metric--debug">
				<span class="dashicons dashicons-admin-tools"></span>
				<strong><?php esc_html_e( 'Debug', 'siteintelix' ); ?></strong>
				<em><?php echo esc_html( (string) $siteintelix_debug_count ); ?></em>
			</div>
		</div>

		<!-- Active mode source banner (replaces old Enable/Disable capture section) -->
		<div class="siteintelix-debug-source-banner siteintelix-debug-source-banner--<?php echo esc_attr( $siteintelix_active_method ); ?>">
			<div class="siteintelix-debug-source-banner__info">
				<span class="dashicons <?php echo 'wp_config' === $siteintelix_active_method ? 'dashicons-editor-code' : 'dashicons-shield'; ?>" aria-hidden="true"></span>
				<div>
					<strong>
						<?php echo 'wp_config' === $siteintelix_active_method
							? esc_html__( 'wp-config.php Mode Active', 'siteintelix' )
							: esc_html__( 'MU Plugin Mode Active', 'siteintelix' ); ?>
					</strong>
					<p><?php echo esc_html( $siteintelix_mode_label ); ?></p>
				</div>
			</div>
			<a href="<?php echo esc_url( $siteintelix_settings_url ); ?>" class="siteintelix-btn siteintelix-debug-source-banner__btn">
				<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
				<?php esc_html_e( 'Change Method', 'siteintelix' ); ?>
			</a>
		</div>

	</section>

	<!-- Toolbar: filters + search -->
	<section class="siteintelix-debug-toolbar">
		<div class="siteintelix-debug-filter-group">
			<button type="button" class="siteintelix-debug-filter is-active" data-level="all"><?php esc_html_e( 'All', 'siteintelix' ); ?></button>
			<button type="button" class="siteintelix-debug-filter" data-level="fatal"><?php esc_html_e( 'Fatal', 'siteintelix' ); ?></button>
			<button type="button" class="siteintelix-debug-filter" data-level="warn"><?php esc_html_e( 'Warning', 'siteintelix' ); ?></button>
			<button type="button" class="siteintelix-debug-filter" data-level="info"><?php esc_html_e( 'Info', 'siteintelix' ); ?></button>
			<button type="button" class="siteintelix-debug-filter" data-level="debug"><?php esc_html_e( 'Debug', 'siteintelix' ); ?></button>
		</div>
		<div class="siteintelix-debug-toolbar__search">
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<input type="search" id="siteintelix-log-search" placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>">
		</div>
	</section>

	<!-- Meta bar: entry count + log path -->
	<div class="siteintelix-debug-meta">
		<span><?php printf( esc_html__( 'Showing last %d entries', 'siteintelix' ), (int) $siteintelix_total_entries ); ?></span>
		<code><?php echo esc_html( $siteintelix_path_display ); ?></code>
		<?php if ( $siteintelix_log_data['size'] > 0 ) : ?>
			<span class="siteintelix-log-size-badge"><?php echo esc_html( size_format( (int) $siteintelix_log_data['size'] ) ); ?></span>
		<?php endif; ?>
	</div>

	<!-- Log entries -->
	<?php if ( empty( $siteintelix_log_data['entries'] ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				if ( ! $siteintelix_log_has_file ) {
					esc_html_e( 'No log file found yet. Debug log will appear here once errors are captured.', 'siteintelix' );
				} else {
					esc_html_e( 'No debug log entries found yet. Generate an error, warning, or notice to populate this screen.', 'siteintelix' );
				}
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="siteintelix-log-console-wrap">
			<div class="siteintelix-log-console" id="siteintelix-log-console">
				<?php foreach ( $siteintelix_log_data['entries'] as $siteintelix_entry ) : ?>
					<div
						class="siteintelix-log-line siteintelix-log-line--<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>"
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
	<?php endif; ?>
</div>
