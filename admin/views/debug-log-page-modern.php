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
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search parameter.
$siteintelix_search_query  = isset( $_GET['siteintelix_log_search'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_log_search'] ) ) : '';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_type_filter   = isset( $_GET['siteintelix_log_type'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_log_type'] ) ) : 'all';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_file_filter   = isset( $_GET['siteintelix_log_file'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_log_file'] ) ) : 'all';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_time_filter   = isset( $_GET['siteintelix_log_time'] ) ? sanitize_key( wp_unslash( $_GET['siteintelix_log_time'] ) ) : 'all';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_plugin_filter = isset( $_GET['siteintelix_log_plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['siteintelix_log_plugin'] ) ) : 'all';
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_group_similar = ! isset( $_GET['siteintelix_group_similar'] ) || '0' !== sanitize_key( wp_unslash( $_GET['siteintelix_group_similar'] ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_hide_deprecated = isset( $_GET['siteintelix_hide_deprecated'] ) && '1' === sanitize_key( wp_unslash( $_GET['siteintelix_hide_deprecated'] ) );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter parameter.
$siteintelix_only_critical = isset( $_GET['siteintelix_only_critical'] ) && '1' === sanitize_key( wp_unslash( $_GET['siteintelix_only_critical'] ) );
$siteintelix_allowed_type_filters = array( 'all', 'fatal', 'warning', 'notice', 'deprecated', 'database', 'info' );
if ( ! in_array( $siteintelix_type_filter, $siteintelix_allowed_type_filters, true ) ) {
	$siteintelix_type_filter = 'all';
}
if ( ! in_array( $siteintelix_time_filter, array( 'all', '3600', '86400', '604800' ), true ) ) {
	$siteintelix_time_filter = 'all';
}
$siteintelix_log_data      = SITEINTELIX_Debug_Log::get_data( 0 );
$siteintelix_active_method = isset( $siteintelix_log_data['method'] ) ? $siteintelix_log_data['method'] : 'mu';
$siteintelix_all_entries   = isset( $siteintelix_log_data['entries'] ) && is_array( $siteintelix_log_data['entries'] ) ? $siteintelix_log_data['entries'] : array();
$siteintelix_log_counts    = isset( $siteintelix_log_data['counts'] ) && is_array( $siteintelix_log_data['counts'] ) ? $siteintelix_log_data['counts'] : array();
$siteintelix_total_entries = count( $siteintelix_all_entries );
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_mode_label    = SITEINTELIX_Debug_Log::get_mode_label( $siteintelix_active_method );

$siteintelix_allowed_log_html = array(
	'a'      => array(
		'href'   => array(),
		'rel'    => array(),
		'target' => array(),
	),
	'b'      => array(),
	'br'     => array(),
	'code'   => array(),
	'em'     => array(),
	'strong' => array(),
);

$siteintelix_get_count = static function ( $level ) use ( $siteintelix_log_counts ) {
	return isset( $siteintelix_log_counts[ $level ] ) ? (int) $siteintelix_log_counts[ $level ] : 0;
};

$siteintelix_to_timestamp = static function ( $timestamp ) {
	if ( empty( $timestamp ) ) {
		return 0;
	}

	$time = strtotime( (string) $timestamp );
	return $time ? (int) $time : 0;
};

$siteintelix_human_time = static function ( $timestamp ) use ( $siteintelix_to_timestamp ) {
	$time = is_numeric( $timestamp ) ? (int) $timestamp : $siteintelix_to_timestamp( $timestamp );
	if ( ! $time ) {
		return $timestamp ? (string) $timestamp : __( 'Unknown', 'siteintelix' );
	}

	return human_time_diff( $time, time() ) . ' ' . __( 'ago', 'siteintelix' );
};

$siteintelix_plugin_from_file = static function ( $file ) {
	$file = (string) $file;
	if ( preg_match( '#wp-content/plugins/([^/]+)#', $file, $match ) ) {
		return (string) $match[1];
	}
	if ( preg_match( '#wp-content/themes/([^/]+)#', $file, $match ) ) {
		return (string) $match[1];
	}
	if ( false !== strpos( $file, 'wp-includes/' ) ) {
		return 'WordPress Core';
	}
	if ( false !== strpos( $file, 'wp-admin/' ) ) {
		return 'WordPress Admin';
	}

	return __( 'Unknown', 'siteintelix' );
};

$siteintelix_message_title = static function ( $message ) {
	$plain = trim( wp_strip_all_tags( (string) $message ) );
	$lines = preg_split( "/\\r\\n|\\n|\\r/", $plain );
	$title = is_array( $lines ) && ! empty( $lines ) ? trim( (string) $lines[0] ) : $plain;

	return '' !== $title ? $title : __( 'Log entry', 'siteintelix' );
};

$siteintelix_extract_stack_trace = static function ( $message ) {
	$lines = preg_split( "/\\r\\n|\\n|\\r/", trim( wp_strip_all_tags( (string) $message ) ) );
	if ( ! is_array( $lines ) || count( $lines ) < 2 ) {
		return '';
	}

	array_shift( $lines );
	$trace_start = null;

	foreach ( $lines as $index => $line ) {
		$line = trim( (string) $line );
		if ( preg_match( '/^(?:Stack trace:|#\\d+|thrown in\\b)/i', $line ) ) {
			$trace_start = $index;
			break;
		}
	}

	if ( null === $trace_start ) {
		return '';
	}

	return trim( implode( "\n", array_slice( $lines, $trace_start ) ) );
};

$siteintelix_group_key = static function ( $entry ) use ( $siteintelix_message_title ) {
	$level = isset( $entry['level'] ) ? strtoupper( (string) $entry['level'] ) : 'INFO';
	$title = strtolower( $siteintelix_message_title( isset( $entry['message'] ) ? $entry['message'] : '' ) );
	$file  = isset( $entry['file'] ) ? strtolower( (string) $entry['file'] ) : '';
	$line  = isset( $entry['line_number'] ) ? (int) $entry['line_number'] : 0;

	return md5( $level . '|' . $title . '|' . $file . '|' . $line );
};

$siteintelix_groups = array();
foreach ( $siteintelix_all_entries as $siteintelix_entry_index => $siteintelix_entry ) {
	$key       = $siteintelix_group_similar ? $siteintelix_group_key( $siteintelix_entry ) : 'single-' . (int) $siteintelix_entry_index;
	$timestamp = $siteintelix_to_timestamp( isset( $siteintelix_entry['timestamp'] ) ? $siteintelix_entry['timestamp'] : '' );

	if ( ! isset( $siteintelix_groups[ $key ] ) ) {
		$siteintelix_groups[ $key ] = array(
			'count'       => 0,
			'entries'     => array(),
			'file'        => isset( $siteintelix_entry['file'] ) ? (string) $siteintelix_entry['file'] : '',
			'first_seen'  => $timestamp,
			'last_seen'   => $timestamp,
			'level'       => isset( $siteintelix_entry['level'] ) ? strtoupper( (string) $siteintelix_entry['level'] ) : 'INFO',
			'line_number' => isset( $siteintelix_entry['line_number'] ) ? (int) $siteintelix_entry['line_number'] : 0,
			'message'     => isset( $siteintelix_entry['message'] ) ? (string) $siteintelix_entry['message'] : '',
			'plugin'      => $siteintelix_plugin_from_file( isset( $siteintelix_entry['file'] ) ? $siteintelix_entry['file'] : '' ),
			'title'       => $siteintelix_message_title( isset( $siteintelix_entry['message'] ) ? $siteintelix_entry['message'] : '' ),
		);
	}

	$siteintelix_groups[ $key ]['count']++;
	$siteintelix_groups[ $key ]['entries'][] = $siteintelix_entry;

	if ( $timestamp ) {
		if ( ! $siteintelix_groups[ $key ]['last_seen'] || $timestamp > $siteintelix_groups[ $key ]['last_seen'] ) {
			$siteintelix_groups[ $key ]['last_seen'] = $timestamp;
		}
		if ( ! $siteintelix_groups[ $key ]['first_seen'] || $timestamp < $siteintelix_groups[ $key ]['first_seen'] ) {
			$siteintelix_groups[ $key ]['first_seen'] = $timestamp;
		}
	}
}

$siteintelix_groups = array_values( $siteintelix_groups );
usort(
	$siteintelix_groups,
	static function ( $a, $b ) {
		return (int) $b['last_seen'] <=> (int) $a['last_seen'];
	}
);

if ( '' !== $siteintelix_search_query ) {
	$siteintelix_groups = array_values(
		array_filter(
			$siteintelix_groups,
			static function ( $group ) use ( $siteintelix_search_query ) {
				$haystack = implode(
					' ',
					array(
						isset( $group['level'] ) ? (string) $group['level'] : '',
						isset( $group['title'] ) ? (string) $group['title'] : '',
						isset( $group['message'] ) ? wp_strip_all_tags( (string) $group['message'] ) : '',
						isset( $group['file'] ) ? (string) $group['file'] : '',
						isset( $group['line_number'] ) ? (string) $group['line_number'] : '',
						isset( $group['plugin'] ) ? (string) $group['plugin'] : '',
					)
				);

				return false !== stripos( $haystack, $siteintelix_search_query );
			}
		)
	);
}

$siteintelix_unique_files   = array();
$siteintelix_unique_plugins = array();
foreach ( $siteintelix_groups as $siteintelix_group ) {
	if ( ! empty( $siteintelix_group['file'] ) ) {
		$siteintelix_unique_files[ $siteintelix_group['file'] ] = $siteintelix_group['file'];
	}
	if ( ! empty( $siteintelix_group['plugin'] ) ) {
		$siteintelix_unique_plugins[ $siteintelix_group['plugin'] ] = $siteintelix_group['plugin'];
	}
}
natcasesort( $siteintelix_unique_files );
natcasesort( $siteintelix_unique_plugins );

$siteintelix_now = time();
$siteintelix_groups = array_values(
	array_filter(
		$siteintelix_groups,
		static function ( $group ) use ( $siteintelix_type_filter, $siteintelix_file_filter, $siteintelix_time_filter, $siteintelix_plugin_filter, $siteintelix_hide_deprecated, $siteintelix_only_critical, $siteintelix_now ) {
			$level  = isset( $group['level'] ) ? strtolower( (string) $group['level'] ) : '';
			$file   = isset( $group['file'] ) ? (string) $group['file'] : '';
			$plugin = isset( $group['plugin'] ) ? (string) $group['plugin'] : '';

			if ( 'all' !== $siteintelix_type_filter && $level !== $siteintelix_type_filter ) {
				return false;
			}
			if ( 'all' !== $siteintelix_file_filter && $file !== $siteintelix_file_filter ) {
				return false;
			}
			if ( 'all' !== $siteintelix_plugin_filter && $plugin !== $siteintelix_plugin_filter ) {
				return false;
			}
			if ( $siteintelix_hide_deprecated && 'deprecated' === $level ) {
				return false;
			}
			if ( $siteintelix_only_critical && ! in_array( $level, array( 'fatal', 'database' ), true ) ) {
				return false;
			}
			if ( 'all' !== $siteintelix_time_filter ) {
				$last_seen = isset( $group['last_seen'] ) ? (int) $group['last_seen'] : 0;
				if ( ! $last_seen || $siteintelix_now - $last_seen > (int) $siteintelix_time_filter ) {
					return false;
				}
			}

			return true;
		}
	)
);

$siteintelix_total_groups  = count( $siteintelix_groups );
$siteintelix_total_pages   = max( 1, (int) ceil( $siteintelix_total_groups / $siteintelix_logs_per_page ) );
$siteintelix_current_page  = min( $siteintelix_current_page, $siteintelix_total_pages );
$siteintelix_offset        = ( $siteintelix_current_page - 1 ) * $siteintelix_logs_per_page;
$siteintelix_page_groups   = array_slice( $siteintelix_groups, $siteintelix_offset, $siteintelix_logs_per_page );
$siteintelix_entry_start   = $siteintelix_total_groups ? $siteintelix_offset + 1 : 0;
$siteintelix_entry_end     = min( $siteintelix_total_groups, $siteintelix_offset + count( $siteintelix_page_groups ) );
$siteintelix_refresh_url   = admin_url( 'admin.php?page=siteintelix-debug-log' );
$siteintelix_clear_url     = admin_url( 'admin-post.php' );
$siteintelix_settings_url  = admin_url( 'admin.php?page=siteintelix-settings' );
$siteintelix_download_url  = wp_nonce_url( admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ), 'siteintelix_download_debug_log' );
$siteintelix_pagination_base = add_query_arg(
	array(
		'page'                 => 'siteintelix-debug-log',
		'siteintelix_log_page' => '%#%',
		'siteintelix_log_search' => '' !== $siteintelix_search_query ? $siteintelix_search_query : false,
		'siteintelix_log_type' => 'all' !== $siteintelix_type_filter ? $siteintelix_type_filter : false,
		'siteintelix_log_file' => 'all' !== $siteintelix_file_filter ? $siteintelix_file_filter : false,
		'siteintelix_log_time' => 'all' !== $siteintelix_time_filter ? $siteintelix_time_filter : false,
		'siteintelix_log_plugin' => 'all' !== $siteintelix_plugin_filter ? $siteintelix_plugin_filter : false,
		'siteintelix_group_similar' => $siteintelix_group_similar ? false : '0',
		'siteintelix_hide_deprecated' => $siteintelix_hide_deprecated ? '1' : false,
		'siteintelix_only_critical' => $siteintelix_only_critical ? '1' : false,
	),
	admin_url( 'admin.php' )
);

$siteintelix_summary_cards = array(
	array( 'key' => 'total', 'label' => __( 'Total Logs', 'siteintelix' ), 'count' => $siteintelix_total_entries, 'subtext' => __( '100% of all logs', 'siteintelix' ) ),
	array( 'key' => 'fatal', 'label' => __( 'Fatal Errors', 'siteintelix' ), 'count' => $siteintelix_get_count( 'FATAL' ), 'subtext' => __( 'Critical failures', 'siteintelix' ) ),
	array( 'key' => 'warning', 'label' => __( 'Warnings', 'siteintelix' ), 'count' => $siteintelix_get_count( 'WARNING' ), 'subtext' => __( 'Needs attention', 'siteintelix' ) ),
	array( 'key' => 'notice', 'label' => __( 'Notices', 'siteintelix' ), 'count' => $siteintelix_get_count( 'NOTICE' ), 'subtext' => __( 'Developer notices', 'siteintelix' ) ),
	array( 'key' => 'deprecated', 'label' => __( 'Deprecated', 'siteintelix' ), 'count' => $siteintelix_get_count( 'DEPRECATED' ), 'subtext' => __( 'Future compatibility', 'siteintelix' ) ),
	array( 'key' => 'database', 'label' => __( 'Database', 'siteintelix' ), 'count' => $siteintelix_get_count( 'DATABASE' ), 'subtext' => __( 'Query and DB issues', 'siteintelix' ) ),
);

$siteintelix_level_labels = array(
	'FATAL'      => __( 'Fatal', 'siteintelix' ),
	'WARNING'    => __( 'Warning', 'siteintelix' ),
	'NOTICE'     => __( 'Notice', 'siteintelix' ),
	'DEPRECATED' => __( 'Deprecated', 'siteintelix' ),
	'DATABASE'   => __( 'Database', 'siteintelix' ),
	'INFO'       => __( 'Info', 'siteintelix' ),
);

$siteintelix_active_filter_count = 0;
$siteintelix_active_filter_count += '' !== $siteintelix_search_query ? 1 : 0;
$siteintelix_active_filter_count += 'all' !== $siteintelix_type_filter ? 1 : 0;
$siteintelix_active_filter_count += 'all' !== $siteintelix_file_filter ? 1 : 0;
$siteintelix_active_filter_count += 'all' !== $siteintelix_time_filter ? 1 : 0;
$siteintelix_active_filter_count += 'all' !== $siteintelix_plugin_filter ? 1 : 0;
$siteintelix_active_filter_count += ! $siteintelix_group_similar ? 1 : 0;
$siteintelix_active_filter_count += $siteintelix_hide_deprecated ? 1 : 0;
$siteintelix_active_filter_count += $siteintelix_only_critical ? 1 : 0;
$siteintelix_has_active_filters = $siteintelix_active_filter_count > 0;

ob_start();
?>
<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
	<span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
</a>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
	<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
</a>
<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>" class="sitx-debug-header-form siteintelix-debug-action-form">
	<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
	<input type="hidden" name="action" value="siteintelix_clear_debug_log">
	<button type="submit" class="sitx-btn sitx-btn--white si-button si-button--danger">
		<span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Clear Logs', 'siteintelix' ); ?>
	</button>
</form>
<?php if ( $siteintelix_log_has_file ) : ?>
	<a class="sitx-btn sitx-btn--primary si-button si-button--primary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Download Log', 'siteintelix' ); ?>
	</a>
<?php endif; ?>
<?php
$siteintelix_header_actions = ob_get_clean();
?>
<div class="wrap siteintelix-wrap si-admin-wrap siteintelix-debug-ui siteintelix-debug-log-viewer <?php echo $siteintelix_group_similar ? '' : 'is-ungrouped-view'; ?>" id="siteintelix-debug-log-page">
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

	<div class="sitx-debug-modern-body siteintelix-debug-shell">
	<?php require SITEINTELIX_PLUGIN_DIR . 'admin/views/partials/debug-log-status.php'; ?>
	<section class="sitx-debug-summary siteintelix-log-stats" aria-label="<?php esc_attr_e( 'Debug log summary', 'siteintelix' ); ?>">
		<?php foreach ( $siteintelix_summary_cards as $siteintelix_card ) : ?>
			<article class="sitx-summary-card siteintelix-stat-card si-card sitx-summary-card--<?php echo esc_attr( $siteintelix_card['key'] ); ?>">
				<span class="siteintelix-stat-card__icon dashicons <?php echo 'total' === $siteintelix_card['key'] ? 'dashicons-media-text' : ( 'fatal' === $siteintelix_card['key'] ? 'dashicons-warning' : ( 'warning' === $siteintelix_card['key'] ? 'dashicons-flag' : ( 'database' === $siteintelix_card['key'] ? 'dashicons-database' : 'dashicons-info' ) ) ); ?>" aria-hidden="true"></span>
				<div>
					<span class="sitx-summary-card__label"><?php echo esc_html( $siteintelix_card['label'] ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( (int) $siteintelix_card['count'] ) ); ?></strong>
					<small><?php echo esc_html( $siteintelix_card['count'] ? $siteintelix_card['subtext'] : __( 'No changes', 'siteintelix' ) ); ?></small>
				</div>
			</article>
		<?php endforeach; ?>
	</section>

	<section class="sitx-filter-panel siteintelix-log-toolbar si-toolbar" aria-label="<?php esc_attr_e( 'Debug log filters', 'siteintelix' ); ?>">
		<div class="sitx-filter-panel__top">
			<form class="sitx-search-field" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="siteintelix-debug-log">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search logs', 'siteintelix' ); ?></span>
				<input type="search" id="siteintelix-log-search" name="siteintelix_log_search" value="<?php echo esc_attr( $siteintelix_search_query ); ?>" data-siteintelix-global-search placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search logs', 'siteintelix' ); ?>">
				<kbd><?php esc_html_e( '⌘K', 'siteintelix' ); ?></kbd>
			</form>
			<label class="screen-reader-text" for="siteintelix-type-filter"><?php esc_html_e( 'Filter by type', 'siteintelix' ); ?></label>
			<select id="siteintelix-type-filter" class="sitx-select-filter">
				<option value="all"><?php esc_html_e( 'Type: All', 'siteintelix' ); ?></option>
				<?php foreach ( $siteintelix_level_labels as $siteintelix_level => $siteintelix_label ) : ?>
					<?php $siteintelix_level_value = strtolower( $siteintelix_level ); ?>
					<option value="<?php echo esc_attr( $siteintelix_level_value ); ?>" <?php selected( $siteintelix_type_filter, $siteintelix_level_value ); ?>><?php echo esc_html( $siteintelix_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="siteintelix-file-filter"><?php esc_html_e( 'Filter by file', 'siteintelix' ); ?></label>
			<select id="siteintelix-file-filter" class="sitx-select-filter">
				<option value="all"><?php esc_html_e( 'File: All', 'siteintelix' ); ?></option>
				<?php foreach ( $siteintelix_unique_files as $siteintelix_file ) : ?>
					<option value="<?php echo esc_attr( $siteintelix_file ); ?>" <?php selected( $siteintelix_file_filter, $siteintelix_file ); ?>><?php echo esc_html( $siteintelix_file ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="siteintelix-time-filter"><?php esc_html_e( 'Filter by time', 'siteintelix' ); ?></label>
			<select id="siteintelix-time-filter" class="sitx-select-filter">
				<option value="all"><?php esc_html_e( 'Time: All Time', 'siteintelix' ); ?></option>
				<option value="3600" <?php selected( $siteintelix_time_filter, '3600' ); ?>><?php esc_html_e( 'Last Hour', 'siteintelix' ); ?></option>
				<option value="86400" <?php selected( $siteintelix_time_filter, '86400' ); ?>><?php esc_html_e( 'Last 24 Hours', 'siteintelix' ); ?></option>
				<option value="604800" <?php selected( $siteintelix_time_filter, '604800' ); ?>><?php esc_html_e( 'Last 7 Days', 'siteintelix' ); ?></option>
			</select>
			<label class="screen-reader-text" for="siteintelix-plugin-filter"><?php esc_html_e( 'Filter by plugin', 'siteintelix' ); ?></label>
			<select id="siteintelix-plugin-filter" class="sitx-select-filter">
				<option value="all"><?php esc_html_e( 'Plugin: All', 'siteintelix' ); ?></option>
				<?php foreach ( $siteintelix_unique_plugins as $siteintelix_plugin ) : ?>
					<option value="<?php echo esc_attr( $siteintelix_plugin ); ?>" <?php selected( $siteintelix_plugin_filter, $siteintelix_plugin ); ?>><?php echo esc_html( $siteintelix_plugin ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( $siteintelix_has_active_filters ) : ?>
				<a class="sitx-filter-reset si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
					<span class="dashicons dashicons-dismiss" aria-hidden="true"></span><?php esc_html_e( 'Clear filters', 'siteintelix' ); ?>
					<span class="siteintelix-active-filter-count"><?php echo esc_html( number_format_i18n( $siteintelix_active_filter_count ) ); ?></span>
				</a>
			<?php endif; ?>
		</div>
		<div class="sitx-filter-panel__bottom">
			<label class="sitx-check"><input type="checkbox" id="siteintelix-group-toggle" <?php checked( $siteintelix_group_similar ); ?>> <?php esc_html_e( 'Group Similar Logs', 'siteintelix' ); ?></label>
			<label class="sitx-check"><input type="checkbox" id="siteintelix-hide-deprecated" <?php checked( $siteintelix_hide_deprecated ); ?>> <?php esc_html_e( 'Hide Deprecated', 'siteintelix' ); ?></label>
			<label class="sitx-check"><input type="checkbox" id="siteintelix-only-critical" <?php checked( $siteintelix_only_critical ); ?>> <?php esc_html_e( 'Show Only Critical', 'siteintelix' ); ?></label>
			<div class="sitx-sort-label siteintelix-log-toolbar__sort">
				<label for="siteintelix-sort-control"><?php esc_html_e( 'Sort:', 'siteintelix' ); ?></label>
				<select id="siteintelix-sort-control" aria-label="<?php esc_attr_e( 'Sort logs', 'siteintelix' ); ?>">
					<option value="newest" selected><?php esc_html_e( 'Newest First', 'siteintelix' ); ?></option>
				</select>
			</div>
		</div>
	</section>

	<section class="sitx-log-section siteintelix-log-panel si-card" aria-label="<?php esc_attr_e( 'Log entries', 'siteintelix' ); ?>">
		<div class="sitx-log-section__head">
			<div>
				<h2><?php esc_html_e( 'Log Entries', 'siteintelix' ); ?></h2>
				<span id="siteintelix-visible-count"><?php echo esc_html( sprintf(
					/* translators: %d: log group count. */
					_n( '%d group', '%d groups', $siteintelix_total_groups, 'siteintelix' ),
					(int) $siteintelix_total_groups
				) ); ?></span>
			</div>
		</div>

		<?php if ( empty( $siteintelix_page_groups ) ) : ?>
			<?php
			if ( ! $siteintelix_log_has_file ) {
				SITEINTELIX_Admin_UI::empty_state( __( 'No Log File Found', 'siteintelix' ), __( 'Debug log entries will appear here once errors are captured.', 'siteintelix' ), 'dashicons-info' );
			} elseif ( '' !== $siteintelix_search_query ) {
				SITEINTELIX_Admin_UI::empty_state(
					__( 'No Matching Groups', 'siteintelix' ),
					sprintf(
						/* translators: %s: search query */
						__( 'No log groups matched "%s".', 'siteintelix' ),
						$siteintelix_search_query
					),
					'dashicons-search'
				);
			} else {
				SITEINTELIX_Admin_UI::empty_state( __( 'No Entries Yet', 'siteintelix' ), __( 'No entries were found in the log file yet.', 'siteintelix' ), 'dashicons-media-text' );
			}
			?>
		<?php else : ?>
			<div class="sitx-log-list siteintelix-log-list" data-sitx-log-list>
				<?php foreach ( $siteintelix_page_groups as $siteintelix_index => $siteintelix_group ) : ?>
					<?php
					$siteintelix_level       = strtolower( (string) $siteintelix_group['level'] );
					$siteintelix_group_id    = 'sitx-log-details-' . (int) $siteintelix_index;
					$siteintelix_title       = (string) $siteintelix_group['title'];
					$siteintelix_file        = (string) $siteintelix_group['file'];
					$siteintelix_line        = (int) $siteintelix_group['line_number'];
					$siteintelix_editor_link = SITEINTELIX_Editor_Links::get_link( $siteintelix_file, $siteintelix_line );
					$siteintelix_plugin      = (string) $siteintelix_group['plugin'];
					$siteintelix_last_seen   = $siteintelix_group['last_seen'] ? $siteintelix_human_time( (int) $siteintelix_group['last_seen'] ) : __( 'Unknown', 'siteintelix' );
					$siteintelix_first_seen  = $siteintelix_group['first_seen'] ? $siteintelix_human_time( (int) $siteintelix_group['first_seen'] ) : __( 'Unknown', 'siteintelix' );
					$siteintelix_stack_text  = $siteintelix_extract_stack_trace( $siteintelix_group['message'] );
					?>
					<article class="sitx-log-card siteintelix-log-item si-card sitx-log-card--<?php echo esc_attr( $siteintelix_level ); ?>"
						data-log-card
						data-group-card
						data-level="<?php echo esc_attr( $siteintelix_level ); ?>"
						data-file="<?php echo esc_attr( $siteintelix_file ); ?>"
						data-plugin="<?php echo esc_attr( $siteintelix_plugin ); ?>"
						data-message="<?php echo esc_attr( strtolower( $siteintelix_title . ' ' . wp_strip_all_tags( (string) $siteintelix_group['message'] ) ) ); ?>"
						data-last-seen="<?php echo esc_attr( (string) (int) $siteintelix_group['last_seen'] ); ?>"
					>
						<div class="sitx-log-card__summary siteintelix-log-summary">
							<div class="sitx-log-card__severity">
								<span class="sitx-severity-icon" aria-hidden="true"><?php echo 'fatal' === $siteintelix_level ? '×' : ( 'warning' === $siteintelix_level ? '!' : ( 'deprecated' === $siteintelix_level ? '↺' : 'i' ) ); ?></span>
								<span class="sitx-severity-badge siteintelix-severity-badge"><?php echo esc_html( isset( $siteintelix_level_labels[ $siteintelix_group['level'] ] ) ? $siteintelix_level_labels[ $siteintelix_group['level'] ] : $siteintelix_group['level'] ); ?></span>
							</div>
							<div class="sitx-log-card__body">
								<h3><?php echo esc_html( $siteintelix_title ); ?></h3>
								<div class="sitx-log-meta">
									<strong><?php echo esc_html( sprintf(
									/* translators: %d: log occurrence count. */
									_n( '%d occurrence', '%d occurrences', (int) $siteintelix_group['count'], 'siteintelix' ),
									(int) $siteintelix_group['count']
								) ); ?></strong>
									<?php if ( ! empty( $siteintelix_editor_link['url'] ) ) : ?>
										<a class="sitx-log-path sitx-log-path--editor" href="<?php echo esc_url( $siteintelix_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
											<span class="dashicons dashicons-media-code" aria-hidden="true"></span><?php echo esc_html( $siteintelix_file ); ?><?php echo $siteintelix_line ? ':' . (int) $siteintelix_line : ''; ?>
										</a>
									<?php else : ?>
										<span class="sitx-log-path"><span class="dashicons dashicons-media-code" aria-hidden="true"></span><?php echo esc_html( $siteintelix_file ? $siteintelix_file : '-' ); ?><?php echo $siteintelix_line ? ':' . (int) $siteintelix_line : ''; ?></span>
									<?php endif; ?>
								</div>
							</div>
							<time class="sitx-log-time-badge" datetime="<?php echo esc_attr( $siteintelix_group['last_seen'] ? gmdate( 'c', (int) $siteintelix_group['last_seen'] ) : '' ); ?>"><?php echo esc_html( $siteintelix_last_seen ); ?></time>
							<div class="sitx-log-card__actions">
								<button type="button" class="sitx-icon-btn si-button si-button--icon" data-sitx-toggle-details aria-label="<?php esc_attr_e( 'Expand log details', 'siteintelix' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $siteintelix_group_id ); ?>">
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
									<span class="screen-reader-text" data-sitx-toggle-label><?php esc_html_e( 'View', 'siteintelix' ); ?></span>
								</button>
							</div>
						</div>
						<div class="sitx-log-card__details siteintelix-log-details" id="<?php echo esc_attr( $siteintelix_group_id ); ?>" hidden>
							<div class="sitx-log-details-grid">
								<div>
									<span><?php esc_html_e( 'Full error message', 'siteintelix' ); ?></span>
									<div class="sitx-log-full-message"><?php echo esc_html( wp_strip_all_tags( (string) $siteintelix_group['message'] ) ); ?></div>
								</div>
								<div>
									<span><?php esc_html_e( 'File and line', 'siteintelix' ); ?></span>
									<code class="sitx-detail-path"><?php echo esc_html( $siteintelix_file ? $siteintelix_file : '-' ); ?><?php echo $siteintelix_line ? ':' . (int) $siteintelix_line : ''; ?></code>
								</div>
								<div><span><?php esc_html_e( 'First seen', 'siteintelix' ); ?></span><strong><?php echo esc_html( $siteintelix_first_seen ); ?></strong></div>
								<div><span><?php esc_html_e( 'Last seen', 'siteintelix' ); ?></span><strong><?php echo esc_html( $siteintelix_last_seen ); ?></strong></div>
								<div><span><?php esc_html_e( 'Occurrences', 'siteintelix' ); ?></span><strong><?php echo esc_html( number_format_i18n( (int) $siteintelix_group['count'] ) ); ?></strong></div>
							</div>
							<div class="sitx-log-details-actions">
								<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-copy-text="<?php echo esc_attr( wp_strip_all_tags( (string) $siteintelix_group['message'] ) ); ?>">
									<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Copy Message', 'siteintelix' ); ?>
								</button>
								<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-copy-text="<?php echo esc_attr( $siteintelix_file ); ?>">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Copy File Path', 'siteintelix' ); ?>
								</button>
								<?php if ( ! empty( $siteintelix_editor_link['url'] ) ) : ?>
									<a class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Open in Editor', 'siteintelix' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $siteintelix_stack_text ) : ?>
								<?php /* sitx-log-card__details keeps the legacy structural trace/details contract while every row remains expandable. */ ?>
								<span class="screen-reader-text" data-sitx-toggle-details><?php esc_html_e( 'This expanded log includes a stack trace.', 'siteintelix' ); ?></span>
								<div class="sitx-stack sitx-stack--full">
										<div class="sitx-stack__head">
											<h4><?php esc_html_e( 'Stack Trace', 'siteintelix' ); ?></h4>
											<div>
												<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-copy-text="<?php echo esc_attr( $siteintelix_stack_text ); ?>">
													<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Copy', 'siteintelix' ); ?>
												</button>
												<?php if ( ! empty( $siteintelix_editor_link['url'] ) ) : ?>
													<a class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
														<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Open in Editor', 'siteintelix' ); ?>
													</a>
												<?php endif; ?>
											</div>
										</div>
										<pre><code><?php echo esc_html( $siteintelix_stack_text ); ?></code></pre>
									</div>
							<?php endif; ?>
						</div>
					</article>
					<?php $siteintelix_single_entries = array_slice( $siteintelix_group['entries'], 0, min( 50, $siteintelix_logs_per_page ) ); ?>
					<?php foreach ( $siteintelix_single_entries as $siteintelix_single_entry ) : ?>
						<?php
						$siteintelix_single_level  = strtolower( isset( $siteintelix_single_entry['level'] ) ? (string) $siteintelix_single_entry['level'] : 'info' );
						$siteintelix_single_file   = isset( $siteintelix_single_entry['file'] ) ? (string) $siteintelix_single_entry['file'] : '';
						$siteintelix_single_line   = isset( $siteintelix_single_entry['line_number'] ) ? (int) $siteintelix_single_entry['line_number'] : 0;
						$siteintelix_single_editor_link = SITEINTELIX_Editor_Links::get_link( $siteintelix_single_file, $siteintelix_single_line );
						$siteintelix_single_plugin = $siteintelix_plugin_from_file( $siteintelix_single_file );
						$siteintelix_single_title  = $siteintelix_message_title( isset( $siteintelix_single_entry['message'] ) ? $siteintelix_single_entry['message'] : '' );
						$siteintelix_single_seen   = $siteintelix_to_timestamp( isset( $siteintelix_single_entry['timestamp'] ) ? $siteintelix_single_entry['timestamp'] : '' );
						?>
						<article class="sitx-log-card siteintelix-log-item si-card sitx-single-log-card sitx-log-card--<?php echo esc_attr( $siteintelix_single_level ); ?>"
							data-log-card
							data-single-card
							data-level="<?php echo esc_attr( $siteintelix_single_level ); ?>"
							data-file="<?php echo esc_attr( $siteintelix_single_file ); ?>"
							data-plugin="<?php echo esc_attr( $siteintelix_single_plugin ); ?>"
							data-message="<?php echo esc_attr( strtolower( $siteintelix_single_title . ' ' . wp_strip_all_tags( isset( $siteintelix_single_entry['message'] ) ? (string) $siteintelix_single_entry['message'] : '' ) ) ); ?>"
							data-last-seen="<?php echo esc_attr( (string) $siteintelix_single_seen ); ?>"
						>
							<div class="sitx-log-card__summary">
								<div class="sitx-log-card__severity">
									<span class="sitx-severity-icon" aria-hidden="true"><?php echo 'fatal' === $siteintelix_single_level ? '×' : ( 'warning' === $siteintelix_single_level ? '!' : ( 'deprecated' === $siteintelix_single_level ? '↺' : 'i' ) ); ?></span>
									<span class="sitx-severity-badge"><?php echo esc_html( strtoupper( $siteintelix_single_level ) ); ?></span>
								</div>
								<div class="sitx-log-card__body">
									<div class="sitx-log-full-message"><?php echo esc_html( wp_strip_all_tags( isset( $siteintelix_single_entry['message'] ) ? (string) $siteintelix_single_entry['message'] : '' ) ); ?></div>
									<div class="sitx-log-meta">
										<span><?php esc_html_e( 'Seen:', 'siteintelix' ); ?> <?php echo esc_html( $siteintelix_human_time( isset( $siteintelix_single_entry['timestamp'] ) ? $siteintelix_single_entry['timestamp'] : '' ) ); ?></span>
										<?php if ( ! empty( $siteintelix_single_editor_link['url'] ) ) : ?>
											<a class="sitx-log-path sitx-log-path--editor" href="<?php echo esc_url( $siteintelix_single_editor_link['url'] ); ?>" target="_blank" rel="noopener noreferrer">
												<span class="dashicons dashicons-media-code" aria-hidden="true"></span><?php echo esc_html( $siteintelix_single_file ); ?><?php echo $siteintelix_single_line ? ':' . (int) $siteintelix_single_line : ''; ?>
											</a>
										<?php else : ?>
											<span class="sitx-log-path"><span class="dashicons dashicons-media-code" aria-hidden="true"></span><?php echo esc_html( $siteintelix_single_file ? $siteintelix_single_file : '-' ); ?><?php echo $siteintelix_single_line ? ':' . (int) $siteintelix_single_line : ''; ?></span>
										<?php endif; ?>
									</div>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $siteintelix_total_pages > 1 ) : ?>
		<nav class="sitx-log-pagination siteintelix-log-pagination" aria-label="<?php esc_attr_e( 'Debug log pagination', 'siteintelix' ); ?>">
			<span>
				<?php
				printf(
					/* translators: 1: first group number, 2: last group number, 3: total group count */
					esc_html__( 'Showing %1$d to %2$d of %3$d groups', 'siteintelix' ),
					(int) $siteintelix_entry_start,
					(int) $siteintelix_entry_end,
					(int) $siteintelix_total_groups
				);
				?>
			</span>
			<div>
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
	</div>
</div>
