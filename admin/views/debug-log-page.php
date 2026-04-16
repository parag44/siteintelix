<?php
/**
 * Debug Log Viewer page for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_log_data      = SITEINTELIX_Debug_Log::get_data( 300 );
$siteintelix_total_entries = count( $siteintelix_log_data['entries'] );
$siteintelix_fatal_count   = isset( $siteintelix_log_data['counts']['FATAL'] ) ? (int) $siteintelix_log_data['counts']['FATAL'] : 0;
$siteintelix_error_count   = isset( $siteintelix_log_data['counts']['ERROR'] ) ? (int) $siteintelix_log_data['counts']['ERROR'] : 0;
$siteintelix_warn_count    = isset( $siteintelix_log_data['counts']['WARN'] ) ? (int) $siteintelix_log_data['counts']['WARN'] : 0;
$siteintelix_log_enabled   = ! empty( $siteintelix_log_data['enabled'] );
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_debug_enable_snippet = "define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );";

$siteintelix_critical_path = 'wp-content/debug.log';
$siteintelix_path_display  = $siteintelix_critical_path;

if ( isset( $siteintelix_log_data['path'] ) && is_string( $siteintelix_log_data['path'] ) ) {
	$siteintelix_path_pos = strpos( $siteintelix_log_data['path'], $siteintelix_critical_path );
	if ( false !== $siteintelix_path_pos ) {
		$siteintelix_path_display = substr( $siteintelix_log_data['path'], (int) $siteintelix_path_pos );
	}
}
?>
<div class="wrap siteintelix-wrap siteintelix-debug-wrap" id="siteintelix-debug-log-page">
	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<div class="siteintelix-header">
		<div class="siteintelix-header__inner">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-media-text" aria-hidden="true"></span>
				<h1 class="siteintelix-header__title"><?php esc_html_e( 'Debug Log Viewer', 'siteintelix' ); ?></h1>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
			</div>
			<div class="siteintelix-overall siteintelix-overall--warning" role="status">
				<span class="siteintelix-overall__dot" aria-hidden="true"></span>
				<span class="siteintelix-overall__label">
					<?php
					printf(
						/* translators: %d: number of log rows */
						esc_html__( 'Showing last %d entries', 'siteintelix' ),
						(int) $siteintelix_total_entries
					);
					?>
				</span>
			</div>
			<div class="siteintelix-header__actions">
				<a class="siteintelix-btn siteintelix-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-debug-log' ) ); ?>">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
				</a>
				<?php if ( $siteintelix_log_data['exists'] && $siteintelix_log_data['readable'] ) : ?>
					<a class="siteintelix-btn siteintelix-btn--primary" href="<?php echo esc_url( content_url( 'debug.log' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="siteintelix-log-status-row">
		<span class="siteintelix-log-level siteintelix-log-level--fatal"><?php echo esc_html( 'FATAL - ' . (string) $siteintelix_fatal_count ); ?></span>
		<span class="siteintelix-log-level siteintelix-log-level--error"><?php echo esc_html( 'ERROR - ' . (string) $siteintelix_error_count ); ?></span>
		<span class="siteintelix-log-level siteintelix-log-level--warn"><?php echo esc_html( 'WARN - ' . (string) $siteintelix_warn_count ); ?></span>
		<?php
		$siteintelix_misc_levels = array( 'INFO', 'DEBUG', 'OTHER' );
		foreach ( $siteintelix_misc_levels as $siteintelix_level_name ) :
			$siteintelix_level_count = isset( $siteintelix_log_data['counts'][ $siteintelix_level_name ] ) ? (int) $siteintelix_log_data['counts'][ $siteintelix_level_name ] : 0;
			?>
			<span class="siteintelix-log-level siteintelix-log-level--<?php echo esc_attr( strtolower( $siteintelix_level_name ) ); ?>">
				<?php echo esc_html( $siteintelix_level_name . ' - ' . (string) $siteintelix_level_count ); ?>
			</span>
		<?php endforeach; ?>
	</div>

	<div class="siteintelix-card siteintelix-log-card siteintelix-log-runtime-card">
		<div class="siteintelix-card__body">
			<?php if ( $siteintelix_log_enabled ) : ?>
				<p class="siteintelix-log-runtime siteintelix-log-runtime--live">
					<?php esc_html_e( 'WP_DEBUG_LOG is enabled in wp-config.php. Live log updates are available.', 'siteintelix' ); ?>
				</p>
			<?php else : ?>
				<p class="siteintelix-log-runtime siteintelix-log-runtime--recorded">
					<?php
					if ( $siteintelix_log_has_file ) {
						esc_html_e( 'WP_DEBUG_LOG is disabled in wp-config.php. Live logging is off; showing previously recorded log entries.', 'siteintelix' );
					} else {
						esc_html_e( 'WP_DEBUG_LOG is disabled in wp-config.php. Live logging is off and no previously recorded debug.log file is currently available.', 'siteintelix' );
					}
					?>
				</p>
				<p class="siteintelix-log-runtime-help">
					<?php esc_html_e( 'To enable live debug logging, add this snippet to your wp-config.php file:', 'siteintelix' ); ?>
				</p>
				<pre class="siteintelix-log-runtime-snippet"><code><?php echo esc_html( $siteintelix_debug_enable_snippet ); ?></code></pre>
			<?php endif; ?>
		</div>
	</div>

	<div class="siteintelix-card siteintelix-log-card siteintelix-log-source-card">
		<div class="siteintelix-card__body">
			<div class="siteintelix-log-source">
				<div class="siteintelix-log-source__main">
					<p class="siteintelix-log-source__path" title="<?php echo esc_attr( $siteintelix_log_data['path'] ); ?>">
						<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
						<span class="siteintelix-log-path-label">
							<?php echo esc_html( $siteintelix_log_enabled ? __( 'Live Log Path', 'siteintelix' ) : __( 'Recorded Log Path', 'siteintelix' ) ); ?>:
						</span>
						<code class="siteintelix-log-path-critical"><?php echo esc_html( $siteintelix_path_display ); ?></code>
					</p>
				</div>
				<div class="siteintelix-log-source__meta">
					<?php if ( $siteintelix_log_data['size'] > 0 ) : ?>
						<span class="siteintelix-log-size-badge"><?php echo esc_html( size_format( (int) $siteintelix_log_data['size'] ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php if ( empty( $siteintelix_log_data['entries'] ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No debug log entries found yet. Generate an error, warning, or notice to populate this screen.', 'siteintelix' ); ?></p>
		</div>
	<?php else : ?>
		<div class="siteintelix-log-console-wrap">
			<div class="siteintelix-log-console">
				<?php foreach ( $siteintelix_log_data['entries'] as $siteintelix_entry ) : ?>
					<div class="siteintelix-log-line siteintelix-log-line--<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>">
						<span class="siteintelix-log-line__ts">[<?php echo esc_html( $siteintelix_entry['timestamp'] ? $siteintelix_entry['timestamp'] : '-' ); ?>]</span>
						<span class="siteintelix-log-line__level"><?php echo esc_html( $siteintelix_entry['level'] ); ?></span>
						<span class="siteintelix-log-line__msg"><?php echo esc_html( $siteintelix_entry['message'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
