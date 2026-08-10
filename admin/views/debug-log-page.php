<?php
/**
 * Single table-based Debug Log Viewer.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_state        = SITEINTELIX_WP_Config::get_state();
$siteintelix_log_data     = SITEINTELIX_Debug_Log::get_data( 0 );
$siteintelix_entries      = isset( $siteintelix_log_data['entries'] ) && is_array( $siteintelix_log_data['entries'] ) ? $siteintelix_log_data['entries'] : array();
$siteintelix_log_exists   = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_config_path  = SITEINTELIX_WP_Config::locate();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only viewer controls.
$siteintelix_search = isset( $_GET['siteintelix_log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_log_search'] ) ) : '';
$siteintelix_type   = isset( $_GET['siteintelix_log_type'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_log_type'] ) ) : 'all';
$siteintelix_time   = isset( $_GET['siteintelix_log_time'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_log_time'] ) ) : 'all';
$siteintelix_page   = isset( $_GET['siteintelix_log_page'] ) ? max( 1, absint( wp_unslash( $_GET['siteintelix_log_page'] ) ) ) : 1;
$siteintelix_per    = isset( $_GET['siteintelix_logs_per_page'] ) ? absint( wp_unslash( $_GET['siteintelix_logs_per_page'] ) ) : (int) get_option( SITEINTELIX_LOGS_PER_PAGE_OPTION, 10 );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$siteintelix_allowed_types = array( 'all', 'fatal', 'warning', 'notice', 'deprecated', 'database', 'info' );
$siteintelix_allowed_times = array( 'all', '300', '1800', '3600', '43200' );
$siteintelix_allowed_per   = array( 10, 25, 50, 100 );
$siteintelix_type          = in_array( $siteintelix_type, $siteintelix_allowed_types, true ) ? $siteintelix_type : 'all';
$siteintelix_time          = in_array( $siteintelix_time, $siteintelix_allowed_times, true ) ? $siteintelix_time : 'all';
$siteintelix_per           = in_array( $siteintelix_per, $siteintelix_allowed_per, true ) ? $siteintelix_per : 10;

$siteintelix_title = static function ( $message ) {
	$message = trim( wp_strip_all_tags( (string) $message ) );
	$lines   = preg_split( "/\r\n|\n|\r/", $message );
	return is_array( $lines ) && isset( $lines[0] ) ? trim( (string) $lines[0] ) : $message;
};

$siteintelix_trace = static function ( $message ) {
	$message = wp_strip_all_tags( (string) $message );
	if ( preg_match( '/(?:Stack trace:|#0\s).*$/s', $message, $match ) ) {
		return trim( (string) $match[0] );
	}
	return '';
};

$siteintelix_timestamp = static function ( $value ) {
	$timestamp = strtotime( (string) $value );
	return false === $timestamp ? 0 : (int) $timestamp;
};

$siteintelix_groups = array();
foreach ( $siteintelix_entries as $siteintelix_entry ) {
	$level   = strtolower( isset( $siteintelix_entry['level'] ) ? (string) $siteintelix_entry['level'] : 'info' );
	$message = isset( $siteintelix_entry['message'] ) ? (string) $siteintelix_entry['message'] : '';
	$file    = isset( $siteintelix_entry['file'] ) ? (string) $siteintelix_entry['file'] : '';
	$line    = isset( $siteintelix_entry['line_number'] ) ? (int) $siteintelix_entry['line_number'] : 0;
	$seen    = $siteintelix_timestamp( isset( $siteintelix_entry['timestamp'] ) ? $siteintelix_entry['timestamp'] : '' );
	$key     = md5( $level . '|' . strtolower( $siteintelix_title( $message ) ) . '|' . strtolower( $file ) . '|' . $line );

	if ( ! isset( $siteintelix_groups[ $key ] ) ) {
		$siteintelix_groups[ $key ] = array(
			'level'     => $level,
			'message'   => $message,
			'title'     => $siteintelix_title( $message ),
			'trace'     => $siteintelix_trace( $message ),
			'file'      => $file,
			'line'      => $line,
			'last_seen' => $seen,
			'count'     => 0,
		);
	}
	$siteintelix_groups[ $key ]['count']++;
	$siteintelix_groups[ $key ]['last_seen'] = max( (int) $siteintelix_groups[ $key ]['last_seen'], $seen );
}

$siteintelix_groups = array_values( $siteintelix_groups );
usort( $siteintelix_groups, static function ( $left, $right ) { return (int) $right['last_seen'] <=> (int) $left['last_seen']; } );

$siteintelix_now = time();
$siteintelix_groups = array_values(
	array_filter(
		$siteintelix_groups,
		static function ( $group ) use ( $siteintelix_search, $siteintelix_type, $siteintelix_time, $siteintelix_now ) {
			if ( 'all' !== $siteintelix_type && $siteintelix_type !== $group['level'] ) {
				return false;
			}
			if ( 'all' !== $siteintelix_time && ( empty( $group['last_seen'] ) || $siteintelix_now - (int) $group['last_seen'] > (int) $siteintelix_time ) ) {
				return false;
			}
			if ( '' !== $siteintelix_search ) {
				$haystack = implode( ' ', array( $group['level'], $group['message'], $group['file'], (string) $group['line'] ) );
				return false !== stripos( $haystack, $siteintelix_search );
			}
			return true;
		}
	)
);

$siteintelix_total       = count( $siteintelix_groups );
$siteintelix_pages       = max( 1, (int) ceil( $siteintelix_total / $siteintelix_per ) );
$siteintelix_page        = min( $siteintelix_page, $siteintelix_pages );
$siteintelix_offset      = ( $siteintelix_page - 1 ) * $siteintelix_per;
$siteintelix_page_groups = array_slice( $siteintelix_groups, $siteintelix_offset, $siteintelix_per );
$siteintelix_start       = $siteintelix_total ? $siteintelix_offset + 1 : 0;
$siteintelix_end         = min( $siteintelix_total, $siteintelix_offset + count( $siteintelix_page_groups ) );
$siteintelix_clear_url   = admin_url( 'admin-post.php' );
$siteintelix_download    = wp_nonce_url( admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ), 'siteintelix_download_debug_log' );
$siteintelix_debug_ready = ! empty( $siteintelix_state[ SITEINTELIX_WP_Config::WP_DEBUG ] ) && ! empty( $siteintelix_state[ SITEINTELIX_WP_Config::WP_DEBUG_LOG ] );
$siteintelix_first_run   = ! $siteintelix_debug_ready;
$siteintelix_header_actions = array(
	SITEINTELIX_Admin_UI::button(
		array(
			'label'   => __( 'Debug settings', 'siteintelix' ),
			'url'     => admin_url( 'admin.php?page=siteintelix-settings&tab=debug_log' ),
			'variant' => 'secondary',
			'icon'    => 'dashicons-admin-generic',
		)
	),
);
if ( $siteintelix_log_exists ) {
	$siteintelix_header_actions[] = SITEINTELIX_Admin_UI::button(
		array(
			'label'   => __( 'Download log', 'siteintelix' ),
			'url'     => $siteintelix_download,
			'variant' => 'secondary',
			'icon'    => 'dashicons-download',
		)
	);
}

$siteintelix_level_labels = array(
	'fatal'      => __( 'Fatal', 'siteintelix' ),
	'warning'    => __( 'Warning', 'siteintelix' ),
	'notice'     => __( 'Notice', 'siteintelix' ),
	'deprecated' => __( 'Deprecated', 'siteintelix' ),
	'database'   => __( 'Database', 'siteintelix' ),
	'info'       => __( 'Custom', 'siteintelix' ),
);

$siteintelix_base_args = array(
	'page'                       => 'siteintelix-debug-log',
	'siteintelix_log_search'     => '' !== $siteintelix_search ? $siteintelix_search : false,
	'siteintelix_log_type'       => 'all' !== $siteintelix_type ? $siteintelix_type : false,
	'siteintelix_log_time'       => 'all' !== $siteintelix_time ? $siteintelix_time : false,
	'siteintelix_logs_per_page'  => $siteintelix_per,
);
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-dlv" id="siteintelix-debug-log-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-media-text',
			'title'       => __( 'Debug Log', 'siteintelix' ),
			'description' => __( 'Monitor WordPress errors from one protected, searchable workspace.', 'siteintelix' ),
			'badges'      => array(
				SITEINTELIX_Admin_UI::badge( __( 'WPConfig mode', 'siteintelix' ), 'neutral', 'dashicons-admin-settings' ),
				SITEINTELIX_Admin_UI::badge( $siteintelix_debug_ready ? __( 'Logging active', 'siteintelix' ) : __( 'Logging inactive', 'siteintelix' ), $siteintelix_debug_ready ? 'success' : 'warning', $siteintelix_debug_ready ? 'dashicons-yes-alt' : 'dashicons-warning' ),
			),
			'actions'     => $siteintelix_header_actions,
		)
	);
	?>

	<div class="siteintelix-container sitx-dlv__container">
	<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only status feedback. ?>
	<?php if ( isset( $_GET['siteintelix_log_cleared'] ) ) : ?>
		<div class="notice siteintelix-notice <?php echo '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) ) ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo '1' === sanitize_text_field( wp_unslash( $_GET['siteintelix_log_cleared'] ) ) ? esc_html__( 'The debug log was cleared.', 'siteintelix' ) : esc_html__( 'The debug log could not be cleared.', 'siteintelix' ); ?></p></div>
	<?php endif; ?>
	<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

	<?php if ( isset( $siteintelix_state['error'] ) ) : ?>
		<div class="notice siteintelix-notice notice-error inline"><p><?php echo esc_html( $siteintelix_state['error'] ); ?></p></div>
	<?php elseif ( ! $siteintelix_config_path || empty( $siteintelix_state['writable'] ) ) : ?>
		<div class="notice siteintelix-notice notice-warning inline"><p><?php esc_html_e( 'wp-config.php is read-only. The viewer can read existing logs, but its debug switches are disabled.', 'siteintelix' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $siteintelix_first_run ) : ?>
	<section class="si-card sitx-dlv-onboarding" aria-labelledby="sitx-dlv-onboarding-title">
		<span class="sitx-dlv-onboarding__icon"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span></span>
		<div class="sitx-dlv-onboarding__copy">
			<p class="sitx-dlv-onboarding__eyebrow"><?php esc_html_e( 'Debug logging is not configured', 'siteintelix' ); ?></p>
			<h2 id="sitx-dlv-onboarding-title"><?php esc_html_e( 'Start debugging your WordPress site', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'SiteIntelix will back up wp-config.php, enable WP_DEBUG and protected file logging, and keep visitor-facing error display off.', 'siteintelix' ); ?></p>
		</div>
		<button type="button" class="si-button si-button--primary sitx-dlv-onboarding__start" data-sitx-start-debugging <?php disabled( empty( $siteintelix_state['writable'] ) ); ?>><span class="dashicons dashicons-controls-play" aria-hidden="true"></span><span><?php esc_html_e( 'Start debugging now', 'siteintelix' ); ?></span></button>
		<p class="sitx-dlv__switch-status" data-sitx-debug-status role="status" aria-live="polite"></p>
	</section>
	<?php endif; ?>

	<section class="si-card sitx-dlv__log-card" aria-labelledby="sitx-dlv-log-title">
		<header class="sitx-dlv__section-header"><div><h2 id="sitx-dlv-log-title"><?php esc_html_e( 'Log entries', 'siteintelix' ); ?></h2><p><?php echo esc_html( sprintf( /* translators: %d: grouped log entry count. */ _n( '%d grouped issue', '%d grouped issues', $siteintelix_total, 'siteintelix' ), $siteintelix_total ) ); ?></p></div></header>
	<form class="si-toolbar sitx-dlv__toolbar" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="siteintelix-debug-log">
		<label class="sitx-dlv__per"><span class="screen-reader-text"><?php esc_html_e( 'Rows per page', 'siteintelix' ); ?></span><select name="siteintelix_logs_per_page" data-sitx-auto-submit><?php foreach ( $siteintelix_allowed_per as $siteintelix_option ) : ?><option value="<?php echo esc_attr( (string) $siteintelix_option ); ?>" <?php selected( $siteintelix_per, $siteintelix_option ); ?>><?php echo esc_html( (string) $siteintelix_option ); ?></option><?php endforeach; ?></select></label>
		<div class="sitx-dlv-dropdown">
			<button type="button" data-sitx-menu-button aria-expanded="false"><?php esc_html_e( 'Show/Hide', 'siteintelix' ); ?><span class="dashicons dashicons-arrow-down-alt2"></span></button>
			<div class="sitx-dlv-menu" hidden><?php foreach ( array( 'type' => __( 'Type', 'siteintelix' ), 'seen' => __( 'Last seen', 'siteintelix' ), 'count' => __( 'Count', 'siteintelix' ), 'description' => __( 'Description', 'siteintelix' ), 'file' => __( 'File', 'siteintelix' ), 'line' => __( 'Line', 'siteintelix' ) ) as $siteintelix_column => $siteintelix_label ) : ?><label><input type="checkbox" data-sitx-column-toggle="<?php echo esc_attr( $siteintelix_column ); ?>" checked> <?php echo esc_html( $siteintelix_label ); ?></label><?php endforeach; ?></div>
		</div>
		<div class="sitx-dlv__times" aria-label="<?php esc_attr_e( 'Time range', 'siteintelix' ); ?>"><?php foreach ( array( '300' => '5m', '1800' => '30m', '3600' => '1h', '43200' => '12h', 'all' => __( 'All', 'siteintelix' ) ) as $siteintelix_seconds => $siteintelix_label ) : ?><button type="submit" name="siteintelix_log_time" value="<?php echo esc_attr( $siteintelix_seconds ); ?>" class="<?php echo $siteintelix_time === $siteintelix_seconds ? 'is-active' : ''; ?>"><?php echo esc_html( $siteintelix_label ); ?></button><?php endforeach; ?></div>
		<label class="sitx-dlv__filter"><span class="dashicons dashicons-filter"></span><span class="screen-reader-text"><?php esc_html_e( 'Filter by error type', 'siteintelix' ); ?></span><select name="siteintelix_log_type" data-sitx-auto-submit><option value="all"><?php esc_html_e( 'All types', 'siteintelix' ); ?></option><?php foreach ( $siteintelix_level_labels as $siteintelix_level => $siteintelix_label ) : ?><option value="<?php echo esc_attr( $siteintelix_level ); ?>" <?php selected( $siteintelix_type, $siteintelix_level ); ?>><?php echo esc_html( $siteintelix_label ); ?></option><?php endforeach; ?></select></label>
		<label class="sitx-dlv__search"><span class="dashicons dashicons-search"></span><span class="screen-reader-text"><?php esc_html_e( 'Search in log', 'siteintelix' ); ?></span><input type="search" name="siteintelix_log_search" value="<?php echo esc_attr( $siteintelix_search ); ?>" placeholder="<?php esc_attr_e( 'Search in log…', 'siteintelix' ); ?>"></label>
		<button class="sitx-dlv__refresh" type="submit" title="<?php esc_attr_e( 'Refresh', 'siteintelix' ); ?>"><span class="dashicons dashicons-update"></span></button>
		<?php if ( $siteintelix_log_exists ) : ?><button class="si-button si-button--danger sitx-dlv__clear" type="submit" form="sitx-clear-log" data-siteintelix-confirm="<?php esc_attr_e( 'Clear every entry from the current debug log?', 'siteintelix' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span><span><?php esc_html_e( 'Clear log', 'siteintelix' ); ?></span></button><?php endif; ?>
	</form>

	<form id="sitx-clear-log" method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>">
		<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?><input type="hidden" name="action" value="siteintelix_clear_debug_log">
	</form>

	<section class="sitx-dlv__table-wrap" aria-label="<?php esc_attr_e( 'Debug log entries', 'siteintelix' ); ?>">
		<table class="sitx-dlv__table">
			<thead><tr><th data-column="type"><?php esc_html_e( 'Type', 'siteintelix' ); ?></th><th data-column="seen"><?php esc_html_e( 'Last seen', 'siteintelix' ); ?></th><th data-column="count"><?php esc_html_e( 'Count', 'siteintelix' ); ?></th><th data-column="description"><?php esc_html_e( 'Description', 'siteintelix' ); ?></th><th data-column="file"><?php esc_html_e( 'File', 'siteintelix' ); ?></th><th data-column="line"><?php esc_html_e( 'Line', 'siteintelix' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $siteintelix_page_groups ) ) : ?>
				<tr><td colspan="6" class="sitx-dlv__empty"><?php echo $siteintelix_first_run ? esc_html__( 'Start debugging above, then reproduce the issue you want to inspect.', 'siteintelix' ) : ( $siteintelix_log_exists ? esc_html__( 'No log entries match the current filters.', 'siteintelix' ) : esc_html__( 'Logging is active. Reproduce the issue, then refresh this page.', 'siteintelix' ) ); ?></td></tr>
			<?php else : foreach ( $siteintelix_page_groups as $siteintelix_index => $siteintelix_group ) :
				$siteintelix_details_id = 'sitx-dlv-details-' . (int) $siteintelix_index;
				$siteintelix_editor     = SITEINTELIX_Editor_Links::get_link( $siteintelix_group['file'], $siteintelix_group['line'] );
				?>
				<tr class="sitx-dlv-row sitx-dlv-row--<?php echo esc_attr( $siteintelix_group['level'] ); ?>">
					<td data-column="type"><span class="sitx-dlv-type sitx-dlv-type--<?php echo esc_attr( $siteintelix_group['level'] ); ?>"><?php echo esc_html( isset( $siteintelix_level_labels[ $siteintelix_group['level'] ] ) ? $siteintelix_level_labels[ $siteintelix_group['level'] ] : ucfirst( $siteintelix_group['level'] ) ); ?></span></td>
					<td data-column="seen"><?php echo $siteintelix_group['last_seen'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $siteintelix_group['last_seen'] ) ) : '—'; ?></td>
					<td data-column="count"><span class="sitx-dlv-count"><?php echo esc_html( number_format_i18n( $siteintelix_group['count'] ) ); ?></span></td>
					<td data-column="description"><div class="sitx-dlv-message"><?php echo esc_html( $siteintelix_group['title'] ); ?></div><?php if ( '' !== $siteintelix_group['trace'] ) : ?><button type="button" class="sitx-dlv-trace-toggle" data-sitx-toggle-trace aria-controls="<?php echo esc_attr( $siteintelix_details_id ); ?>" aria-expanded="false"><span class="dashicons dashicons-editor-code"></span><span><?php esc_html_e( 'Stack Trace', 'siteintelix' ); ?></span></button><pre id="<?php echo esc_attr( $siteintelix_details_id ); ?>" class="sitx-dlv-trace" hidden><code><?php echo esc_html( $siteintelix_group['trace'] ); ?></code><button type="button" data-copy-text="<?php echo esc_attr( $siteintelix_group['trace'] ); ?>" title="<?php esc_attr_e( 'Copy stack trace', 'siteintelix' ); ?>"><span class="dashicons dashicons-clipboard"></span></button></pre><?php endif; ?></td>
					<td data-column="file"><?php if ( ! empty( $siteintelix_editor['url'] ) ) : ?><a href="<?php echo esc_url( $siteintelix_editor['url'] ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $siteintelix_group['file'] ); ?></code></a><?php else : ?><code><?php echo esc_html( $siteintelix_group['file'] ? $siteintelix_group['file'] : '—' ); ?></code><?php endif; ?></td>
					<td data-column="line"><?php echo $siteintelix_group['line'] ? esc_html( number_format_i18n( $siteintelix_group['line'] ) ) : '—'; ?></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
		<footer class="sitx-dlv__footer"><span><?php printf( esc_html__( 'Showing %1$d to %2$d of %3$d entries', 'siteintelix' ), (int) $siteintelix_start, (int) $siteintelix_end, (int) $siteintelix_total ); ?></span><?php if ( $siteintelix_pages > 1 ) : ?><nav><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array_merge( $siteintelix_base_args, array( 'siteintelix_log_page' => '%#%' ) ), admin_url( 'admin.php' ) ), 'current' => $siteintelix_page, 'total' => $siteintelix_pages, 'prev_text' => '‹', 'next_text' => '›' ) ) ); ?></nav><?php endif; ?></footer>
	</section>
	</section>
	</div>
</div>
