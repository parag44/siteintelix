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
$siteintelix_recent_entries = count( $siteintelix_all_entries );
$siteintelix_log_counts    = isset( $siteintelix_log_data['counts'] ) && is_array( $siteintelix_log_data['counts'] ) ? $siteintelix_log_data['counts'] : array();
$siteintelix_allowed_levels = array( 'all', 'fatal', 'warning', 'notice', 'deprecated', 'database', 'info' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_active_level = isset( $_GET['siteintelix_log_level'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_log_level'] ) ) : 'all';
if ( ! in_array( $siteintelix_active_level, $siteintelix_allowed_levels, true ) ) {
	$siteintelix_active_level = 'all';
}
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search parameter.
$siteintelix_search_query = isset( $_GET['siteintelix_log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_log_search'] ) ) : '';
$siteintelix_entries_for_view = array_values(
	array_filter(
		$siteintelix_all_entries,
		static function ( $entry ) use ( $siteintelix_active_level, $siteintelix_search_query ) {
			if ( 'all' === $siteintelix_active_level ) {
				$matches_level = true;
			} else {
				$entry_level   = isset( $entry['level'] ) ? strtolower( (string) $entry['level'] ) : '';
				$matches_level = $entry_level === $siteintelix_active_level;
			}

			if ( ! $matches_level || '' === $siteintelix_search_query ) {
				return $matches_level;
			}

			$haystack = implode(
				' ',
				array(
					isset( $entry['level'] ) ? (string) $entry['level'] : '',
					isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '',
					isset( $entry['message'] ) ? wp_strip_all_tags( (string) $entry['message'] ) : '',
					isset( $entry['file'] ) ? (string) $entry['file'] : '',
					isset( $entry['line_number'] ) ? (string) $entry['line_number'] : '',
				)
			);

			return false !== stripos( $haystack, $siteintelix_search_query );
		}
	)
);
$siteintelix_total_entries = count( $siteintelix_entries_for_view );
$siteintelix_total_pages   = max( 1, (int) ceil( $siteintelix_total_entries / $siteintelix_logs_per_page ) );
$siteintelix_current_page  = min( $siteintelix_current_page, $siteintelix_total_pages );
$siteintelix_offset        = ( $siteintelix_current_page - 1 ) * $siteintelix_logs_per_page;
$siteintelix_page_entries  = array_slice( $siteintelix_entries_for_view, $siteintelix_offset, $siteintelix_logs_per_page );
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
		'siteintelix_log_level' => 'all' !== $siteintelix_active_level ? $siteintelix_active_level : false,
		'siteintelix_log_search' => '' !== $siteintelix_search_query ? $siteintelix_search_query : false,
	),
	admin_url( 'admin.php' )
);
$siteintelix_download_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ),
	'siteintelix_download_debug_log'
);
$siteintelix_filter_url = static function ( $level ) use ( $siteintelix_search_query ) {
	$args = array(
		'page' => 'siteintelix-debug-log',
	);

	if ( 'all' !== $level ) {
		$args['siteintelix_log_level'] = $level;
	}
	if ( '' !== $siteintelix_search_query ) {
		$args['siteintelix_log_search'] = $siteintelix_search_query;
	}

	return add_query_arg( $args, admin_url( 'admin.php' ) );
};

ob_start();
?>
<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
	<span class="dashicons dashicons-update" aria-hidden="true"></span>
	<?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
</a>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
	<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
	<?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
</a>
<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>" class="sitx-debug-header-form siteintelix-debug-action-form">
	<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
	<input type="hidden" name="action" value="siteintelix_clear_debug_log">
	<button type="submit" class="sitx-btn sitx-btn--white si-button si-button--danger">
		<span class="dashicons dashicons-trash" aria-hidden="true"></span>
		<?php esc_html_e( 'Clear Logs', 'siteintelix' ); ?>
	</button>
</form>
<?php if ( $siteintelix_log_has_file ) : ?>
	<a class="sitx-btn sitx-btn--primary si-button si-button--primary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons dashicons-download" aria-hidden="true"></span>
		<?php esc_html_e( 'Download Log', 'siteintelix' ); ?>
	</a>
<?php endif; ?>
<?php
$siteintelix_header_actions = ob_get_clean();
?>
<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-debug-log-page">

	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-media-text',
			'title'       => __( 'Debug Log Viewer', 'siteintelix' ),
			'description' => __( 'Inspect and monitor captured WordPress debug log entries.', 'siteintelix' ),
			'actions'     => array( $siteintelix_header_actions ),
		)
	);
	?>

	<div class="siteintelix-container">
		<?php require SITEINTELIX_PLUGIN_DIR . 'admin/views/partials/debug-log-status.php'; ?>

		<!-- ===== 3. Toolbar: Filters + Search ===== -->
		<div class="sitx-card si-toolbar siteintelix-debug-toolbar">
			<div class="siteintelix-debug-toolbar__filters">
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'all' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'all' ) ); ?>" data-level="all"><?php esc_html_e( 'All Levels', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( count( $siteintelix_all_entries ) ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'fatal' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'fatal' ) ); ?>" data-level="fatal"><?php esc_html_e( 'Fatal', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['FATAL'] ) ? (int) $siteintelix_log_counts['FATAL'] : 0 ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'warning' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'warning' ) ); ?>" data-level="warning"><?php esc_html_e( 'Warning', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['WARNING'] ) ? (int) $siteintelix_log_counts['WARNING'] : 0 ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'notice' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'notice' ) ); ?>" data-level="notice"><?php esc_html_e( 'Notice', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['NOTICE'] ) ? (int) $siteintelix_log_counts['NOTICE'] : 0 ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'deprecated' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'deprecated' ) ); ?>" data-level="deprecated"><?php esc_html_e( 'Deprecated', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['DEPRECATED'] ) ? (int) $siteintelix_log_counts['DEPRECATED'] : 0 ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'database' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'database' ) ); ?>" data-level="database"><?php esc_html_e( 'Database', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['DATABASE'] ) ? (int) $siteintelix_log_counts['DATABASE'] : 0 ) ); ?></span></a>
					<a class="sitx-btn sitx-btn--white sitx-btn--sm sitx-filter siteintelix-debug-filter si-button si-button--secondary <?php echo 'info' === $siteintelix_active_level ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( 'info' ) ); ?>" data-level="info"><?php esc_html_e( 'Info', 'siteintelix' ); ?> <span class="siteintelix-filter-count"><?php echo esc_html( number_format_i18n( isset( $siteintelix_log_counts['INFO'] ) ? (int) $siteintelix_log_counts['INFO'] : 0 ) ); ?></span></a>
				</div>
				<form class="siteintelix-debug-search" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
					<input type="hidden" name="page" value="siteintelix-debug-log">
					<?php if ( 'all' !== $siteintelix_active_level ) : ?>
						<input type="hidden" name="siteintelix_log_level" value="<?php echo esc_attr( $siteintelix_active_level ); ?>">
					<?php endif; ?>
					<input type="search" id="siteintelix-log-search" name="siteintelix_log_search" value="<?php echo esc_attr( $siteintelix_search_query ); ?>" data-siteintelix-global-search placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>"
						aria-label="<?php esc_attr_e( 'Search logs', 'siteintelix' ); ?>">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
				</form>
		</div>

		<!-- ===== 4. Log Console ===== -->
		<?php if ( empty( $siteintelix_log_data['entries'] ) ) : ?>
			<div class="si-card">
				<?php
				if ( ! $siteintelix_log_has_file ) {
					SITEINTELIX_Admin_UI::empty_state( __( 'No Log File Found', 'siteintelix' ), __( 'Debug log entries will appear here once errors are captured.', 'siteintelix' ), 'dashicons-info' );
				} elseif ( '' !== $siteintelix_search_query ) {
					SITEINTELIX_Admin_UI::empty_state(
						__( 'No Matching Entries', 'siteintelix' ),
						sprintf(
							/* translators: %s: search query */
							__( 'No log entries matched "%s".', 'siteintelix' ),
							$siteintelix_search_query
						),
						'dashicons-search'
					);
				} elseif ( 'all' !== $siteintelix_active_level ) {
					SITEINTELIX_Admin_UI::empty_state( __( 'No Entries For This Level', 'siteintelix' ), __( 'Try a different log level filter.', 'siteintelix' ), 'dashicons-filter' );
				} else {
					SITEINTELIX_Admin_UI::empty_state( __( 'No Entries Yet', 'siteintelix' ), __( 'No entries were found in the log file yet.', 'siteintelix' ), 'dashicons-media-text' );
				}
				?>
			</div>
		<?php else : ?>
			<div class="siteintelix-log-table-wrap si-table-wrap">
				<table class="siteintelix-log-table si-table" id="siteintelix-log-table">
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
							<?php
							$siteintelix_editor_link = SITEINTELIX_Editor_Links::get_link(
								isset( $siteintelix_entry['file'] ) ? (string) $siteintelix_entry['file'] : '',
								isset( $siteintelix_entry['line_number'] ) ? (int) $siteintelix_entry['line_number'] : 0
							);
							?>
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
									<?php if ( ! empty( $siteintelix_editor_link['url'] ) ) : ?>
										<a class="siteintelix-log-path siteintelix-log-path--editor" href="<?php echo esc_url( $siteintelix_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e( 'Open this file at the reported line in the WordPress file editor', 'siteintelix' ); ?>">
											<?php echo esc_html( $siteintelix_entry['file'] ); ?>
										</a>
									<?php else : ?>
										<span class="siteintelix-log-path" title="<?php echo esc_attr( $siteintelix_entry['file'] ); ?>">
											<?php echo esc_html( $siteintelix_entry['file'] ? $siteintelix_entry['file'] : '-' ); ?>
										</span>
									<?php endif; ?>
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
									'end_size'  => 1,
									'mid_size'  => 1,
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
