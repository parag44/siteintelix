<?php
/**
 * Debug Log Viewer page for SiteIntelix.
 *
 * Reads from wp-content/siteintelix-debug.log for both debug methods.
 *
 * @package SiteIntelix
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_logs_per_page = (int) get_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, 25 );
$siteintelix_logs_per_page = min( 500, max( 10, $siteintelix_logs_per_page ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination parameter.
$siteintelix_current_page  = isset( $_GET['siteintelix_log_page'] ) ? max( 1, absint( wp_unslash( $_GET['siteintelix_log_page'] ) ) ) : 1;
$siteintelix_log_data      = SITEINTELIX_Debug_Log::get_data( 0 );
$siteintelix_active_method = isset( $siteintelix_log_data['method'] ) ? $siteintelix_log_data['method'] : 'mu';
$siteintelix_all_entries   = isset( $siteintelix_log_data['entries'] ) ? $siteintelix_log_data['entries'] : array();
$siteintelix_log_counts    = isset( $siteintelix_log_data['counts'] ) && is_array( $siteintelix_log_data['counts'] ) ? $siteintelix_log_data['counts'] : array();
$siteintelix_total_entries = count( $siteintelix_all_entries );
$siteintelix_total_pages   = max( 1, (int) ceil( $siteintelix_total_entries / $siteintelix_logs_per_page ) );
$siteintelix_current_page  = min( $siteintelix_current_page, $siteintelix_total_pages );
$siteintelix_offset        = ( $siteintelix_current_page - 1 ) * $siteintelix_logs_per_page;
$siteintelix_page_entries  = array_slice( $siteintelix_all_entries, $siteintelix_offset, $siteintelix_logs_per_page );
$siteintelix_entry_start   = $siteintelix_total_entries ? $siteintelix_offset + 1 : 0;
$siteintelix_entry_end     = min( $siteintelix_total_entries, $siteintelix_offset + count( $siteintelix_page_entries ) );
$siteintelix_log_data['entries'] = $siteintelix_page_entries;
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_mode_label    = SITEINTELIX_Debug_Log::get_mode_label( $siteintelix_active_method );
$siteintelix_allowed_log_html = array(
	'strong' => array(),
	'b'      => array(),
	'em'     => array(),
	'code'   => array(),
	'a'      => array(
		'href'   => array(),
		'target' => array(),
		'rel'    => array(),
	),
);

$siteintelix_clear_url   = admin_url( 'admin-post.php' );
$siteintelix_refresh_url = admin_url( 'admin.php?page=siteintelix-debug-log' );
$siteintelix_settings_url = admin_url( 'admin.php?page=siteintelix-settings' );
$siteintelix_pagination_base = add_query_arg(
	array(
		'page'                 => 'siteintelix-debug-log',
		'siteintelix_log_page' => '%#%',
	),
	admin_url( 'admin.php' )
);
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

		<!-- ===== 3. Toolbar: Filters + Search ===== -->
		<div class="sitx-card" style="padding: 12px; margin-bottom: 12px;">
			<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
				<div style="display:flex; gap:8px; flex-wrap:wrap;">
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter is-active" data-level="all"><?php esc_html_e( 'All Levels', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( $siteintelix_total_entries ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="fatal"><?php esc_html_e( 'Fatal', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['FATAL'] ) ? (int) $siteintelix_log_counts['FATAL'] : 0 ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="warning"><?php esc_html_e( 'Warning', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['WARNING'] ) ? (int) $siteintelix_log_counts['WARNING'] : 0 ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="notice"><?php esc_html_e( 'Notice', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['NOTICE'] ) ? (int) $siteintelix_log_counts['NOTICE'] : 0 ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="deprecated"><?php esc_html_e( 'Deprecated', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['DEPRECATED'] ) ? (int) $siteintelix_log_counts['DEPRECATED'] : 0 ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="database"><?php esc_html_e( 'Database', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['DATABASE'] ) ? (int) $siteintelix_log_counts['DATABASE'] : 0 ) ); ?></span></button>
					<button type="button" class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter" data-level="info"><?php esc_html_e( 'Info', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['INFO'] ) ? (int) $siteintelix_log_counts['INFO'] : 0 ) ); ?></span></button>
				</div>
				<div style="flex:1; max-width:400px; position:relative;">
					<input type="search" id="siteintelix-log-search" placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>" 
						style="width:100%; height:40px; padding: 0 40px; border-radius:10px; border:1px solid var(--siteintelix-border); background:var(--siteintelix-bg);">
					<span class="dashicons dashicons-search" style="position:absolute; left:12px; top:10px; color:var(--siteintelix-text-muted);"></span>
				</div>
			</div>
		</div>

		<!-- ===== 4. Log Console ===== -->
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
			<div class="siteintelix-log-table-wrap">
				<table class="siteintelix-log-table" id="siteintelix-log-table">
					<thead>
						<tr>
							<th class="col-type"><?php esc_html_e( 'Type', 'siteintelix' ); ?></th>
							<th class="col-date"><?php esc_html_e( 'Datetime', 'siteintelix' ); ?></th>
							<th class="col-desc"><?php esc_html_e( 'Description', 'siteintelix' ); ?></th>
							<th class="col-file"><?php esc_html_e( 'File', 'siteintelix' ); ?></th>
							<th class="col-line"><?php esc_html_e( 'Line', 'siteintelix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $siteintelix_log_data['entries'] as $siteintelix_entry ) : ?>
							<tr class="siteintelix-log-row siteintelix-log-row--<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>"
								data-level="<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>"
								data-message="<?php echo esc_attr( strtolower( $siteintelix_entry['message'] ) ); ?>"
							>
								<td class="col-type">
									<span class="sitx-log-badge sitx-log-badge--<?php echo esc_attr( strtolower( $siteintelix_entry['level'] ) ); ?>">
										<?php echo esc_html( $siteintelix_entry['level'] ); ?>
									</span>
								</td>
								<td class="col-date">
									<span class="siteintelix-log-time" title="<?php echo esc_attr( $siteintelix_entry['timestamp'] ); ?>">
										<?php
										if ( ! empty( $siteintelix_entry['timestamp'] ) ) {
											$ts = strtotime( $siteintelix_entry['timestamp'] );
											if ( $ts ) {
												echo esc_html( human_time_diff( $ts, current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'siteintelix' ) );
											} else {
												echo esc_html( $siteintelix_entry['timestamp'] );
											}
										} else {
											echo '-';
										}
										?>
									</span>
								</td>
								<td class="col-desc">
									<div class="siteintelix-log-msg"><?php echo wp_kses( $siteintelix_entry['message'], $siteintelix_allowed_log_html ); ?></div>
								</td>
								<td class="col-file">
									<span class="siteintelix-log-path" title="<?php echo esc_attr( $siteintelix_entry['file'] ); ?>">
										<?php echo esc_html( $siteintelix_entry['file'] ? $siteintelix_entry['file'] : '-' ); ?>
									</span>
								</td>
								<td class="col-line">
									<span class="siteintelix-log-ln">
										<?php echo $siteintelix_entry['line_number'] ? (int) $siteintelix_entry['line_number'] : '-'; ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $siteintelix_total_pages > 1 ) : ?>
				<nav class="siteintelix-log-pagination" aria-label="<?php esc_attr_e( 'Debug log pagination', 'siteintelix' ); ?>">
					<span class="siteintelix-log-pagination__summary">
						<?php
						printf(
							/* translators: 1: first entry number, 2: last entry number, 3: total entry count */
							esc_html__( 'Showing %1$d-%2$d of %3$d entries', 'siteintelix' ),
							(int) $siteintelix_entry_start,
							(int) $siteintelix_entry_end,
							(int) $siteintelix_total_entries
						);
						?>
					</span>
					<div class="siteintelix-log-pagination__links">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => esc_url_raw( $siteintelix_pagination_base ),
									'format'    => '',
									'current'   => $siteintelix_current_page,
									'total'     => $siteintelix_total_pages,
									'prev_text' => __( 'Previous', 'siteintelix' ),
									'next_text' => __( 'Next', 'siteintelix' ),
								)
							)
						);
						?>
					</div>
				</nav>
			<?php endif; ?>
		<?php endif; ?>

	</div><!-- /.siteintelix-container -->
</div><!-- /.wrap -->
