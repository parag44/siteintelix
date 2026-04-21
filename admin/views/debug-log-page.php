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
$siteintelix_info_count    = isset( $siteintelix_log_data['counts']['INFO'] ) ? (int) $siteintelix_log_data['counts']['INFO'] : 0;
$siteintelix_debug_count   = isset( $siteintelix_log_data['counts']['DEBUG'] ) ? (int) $siteintelix_log_data['counts']['DEBUG'] : 0;
$siteintelix_log_enabled   = ! empty( $siteintelix_log_data['enabled'] );
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_critical_path = 'wp-content/siteintelix-debug.log';
$siteintelix_path_display  = $siteintelix_critical_path;
$siteintelix_toggle_url    = admin_url( 'admin-post.php' );
$siteintelix_clear_url     = admin_url( 'admin-post.php' );
$siteintelix_refresh_url   = admin_url( 'admin.php?page=siteintelix-debug-log' );
$siteintelix_download_url  = wp_nonce_url(
	admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ),
	'siteintelix_download_debug_log'
);

if ( isset( $siteintelix_log_data['path'] ) && is_string( $siteintelix_log_data['path'] ) ) {
	$siteintelix_path_pos = strpos( $siteintelix_log_data['path'], $siteintelix_critical_path );
	if ( false !== $siteintelix_path_pos ) {
		$siteintelix_path_display = substr( $siteintelix_log_data['path'], (int) $siteintelix_path_pos );
	}
}
?>
<div class="wrap siteintelix-wrap siteintelix-debug-wrap" id="siteintelix-debug-log-page">
	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<?php if ( isset( $_GET['siteintelix_mu_debug_saved'] ) ) : ?>
		<div class="notice notice-success">
			<p>
				<?php
				$siteintelix_saved_mode = isset( $_GET['siteintelix_mu_debug_mode'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_mu_debug_mode'] ) )
					? __( 'enabled', 'siteintelix' )
					: __( 'disabled', 'siteintelix' );
				printf(
					/* translators: %s: enabled/disabled */
					esc_html__( 'SiteIntelix global error capture has been %s.', 'siteintelix' ),
					esc_html( $siteintelix_saved_mode )
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['siteintelix_log_cleared'] ) ) : ?>
		<div class="notice <?php echo '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) ) ? 'notice-success' : 'notice-error'; ?>">
			<p>
				<?php
				echo esc_html(
					'1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) )
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
				<?php if ( $siteintelix_log_data['exists'] && $siteintelix_log_data['readable'] ) : ?>
					<a class="siteintelix-btn siteintelix-btn--secondary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download', 'siteintelix' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

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

		<div class="siteintelix-debug-capture">
			<div>
				<h2><?php esc_html_e( 'Global Error Capture', 'siteintelix' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						$siteintelix_log_enabled
							? __( 'Enabled via MU-plugin', 'siteintelix' )
							: __( 'Disabled. Enable to capture new errors globally.', 'siteintelix' )
					);
					?>
				</p>
			</div>
			<form method="post" action="<?php echo esc_url( $siteintelix_toggle_url ); ?>" class="siteintelix-debug-capture__actions">
				<?php wp_nonce_field( 'siteintelix_toggle_mu_debug' ); ?>
				<input type="hidden" name="action" value="siteintelix_toggle_mu_debug">
				<input type="hidden" name="siteintelix_mu_debug_enabled" value="<?php echo esc_attr( $siteintelix_log_enabled ? '0' : '1' ); ?>">
				<button type="submit" class="siteintelix-btn <?php echo esc_attr( $siteintelix_log_enabled ? 'siteintelix-btn--secondary' : 'siteintelix-btn--primary' ); ?>">
					<span class="dashicons <?php echo esc_attr( $siteintelix_log_enabled ? 'dashicons-hidden' : 'dashicons-visibility' ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $siteintelix_log_enabled ? __( 'Disable Capture', 'siteintelix' ) : __( 'Enable Capture', 'siteintelix' ) ); ?>
				</button>
			</form>
		</div>
	</section>

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

	<div class="siteintelix-debug-meta">
		<span><?php printf( esc_html__( 'Showing last %d entries', 'siteintelix' ), (int) $siteintelix_total_entries ); ?></span>
		<code><?php echo esc_html( $siteintelix_path_display ); ?></code>
		<?php if ( $siteintelix_log_data['size'] > 0 ) : ?>
			<span class="siteintelix-log-size-badge"><?php echo esc_html( size_format( (int) $siteintelix_log_data['size'] ) ); ?></span>
		<?php endif; ?>
	</div>

	<?php if ( empty( $siteintelix_log_data['entries'] ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'No debug log entries found yet. Generate an error, warning, or notice to populate this screen.', 'siteintelix' ); ?></p>
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
