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
$siteintelix_all_entries   = isset( $siteintelix_log_data['entries'] ) && is_array( $siteintelix_log_data['entries'] ) ? $siteintelix_log_data['entries'] : array();
$siteintelix_log_counts    = isset( $siteintelix_log_data['counts'] ) && is_array( $siteintelix_log_data['counts'] ) ? $siteintelix_log_data['counts'] : array();
$siteintelix_total_entries = count( $siteintelix_all_entries );
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );

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

$siteintelix_group_key = static function ( $entry ) use ( $siteintelix_message_title ) {
	$level = isset( $entry['level'] ) ? strtoupper( (string) $entry['level'] ) : 'INFO';
	$title = strtolower( $siteintelix_message_title( isset( $entry['message'] ) ? $entry['message'] : '' ) );
	$file  = isset( $entry['file'] ) ? strtolower( (string) $entry['file'] ) : '';
	$line  = isset( $entry['line_number'] ) ? (int) $entry['line_number'] : 0;

	return md5( $level . '|' . $title . '|' . $file . '|' . $line );
};

$siteintelix_groups = array();
foreach ( $siteintelix_all_entries as $siteintelix_entry_index => $siteintelix_entry ) {
	$key       = $siteintelix_group_key( $siteintelix_entry );
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
		'siteintelix_hide_deprecated' => $siteintelix_hide_deprecated ? '1' : false,
		'siteintelix_only_critical' => $siteintelix_only_critical ? '1' : false,
	),
	admin_url( 'admin.php' )
);

$siteintelix_level_icons = array(
	'fatal'      => 'dashicons-warning',
	'warning'    => 'dashicons-warning',
	'notice'     => 'dashicons-info-outline',
	'info'       => 'dashicons-info-outline',
	'deprecated' => 'dashicons-image-rotate',
	'database'   => 'dashicons-database',
);

$siteintelix_summary_cards = array(
	array( 'key' => 'total', 'label' => __( 'Total Logs', 'siteintelix' ), 'count' => $siteintelix_total_entries, 'icon' => 'dashicons-media-document' ),
	array( 'key' => 'fatal', 'label' => __( 'Critical', 'siteintelix' ), 'count' => $siteintelix_get_count( 'FATAL' ), 'icon' => 'dashicons-warning' ),
	array( 'key' => 'warning', 'label' => __( 'Warnings', 'siteintelix' ), 'count' => $siteintelix_get_count( 'WARNING' ), 'icon' => 'dashicons-warning' ),
	array( 'key' => 'notice', 'label' => __( 'Notices', 'siteintelix' ), 'count' => $siteintelix_get_count( 'NOTICE' ), 'icon' => 'dashicons-info-outline' ),
	array( 'key' => 'deprecated', 'label' => __( 'Deprecated', 'siteintelix' ), 'count' => $siteintelix_get_count( 'DEPRECATED' ), 'icon' => 'dashicons-image-rotate' ),
	array( 'key' => 'database', 'label' => __( 'Database', 'siteintelix' ), 'count' => $siteintelix_get_count( 'DATABASE' ), 'icon' => 'dashicons-database' ),
);

$siteintelix_level_labels = array(
	'FATAL'      => __( 'Fatal', 'siteintelix' ),
	'WARNING'    => __( 'Warning', 'siteintelix' ),
	'NOTICE'     => __( 'Notice', 'siteintelix' ),
	'DEPRECATED' => __( 'Deprecated', 'siteintelix' ),
	'DATABASE'   => __( 'Database', 'siteintelix' ),
	'INFO'       => __( 'Info', 'siteintelix' ),
);

$siteintelix_level_tabs = array(
	'all'        => __( 'All', 'siteintelix' ),
	'fatal'      => __( 'Critical', 'siteintelix' ),
	'warning'    => __( 'Warning', 'siteintelix' ),
	'notice'     => __( 'Notice', 'siteintelix' ),
	'deprecated' => __( 'Deprecated', 'siteintelix' ),
	'info'       => __( 'Info', 'siteintelix' ),
);

$siteintelix_filter_url = static function ( $args = array() ) use ( $siteintelix_search_query, $siteintelix_type_filter, $siteintelix_file_filter, $siteintelix_time_filter, $siteintelix_plugin_filter, $siteintelix_hide_deprecated, $siteintelix_only_critical ) {
	$query = array_merge(
		array(
			'page'                       => 'siteintelix-debug-log',
			'siteintelix_log_search'     => '' !== $siteintelix_search_query ? $siteintelix_search_query : false,
			'siteintelix_log_type'       => 'all' !== $siteintelix_type_filter ? $siteintelix_type_filter : false,
			'siteintelix_log_file'       => 'all' !== $siteintelix_file_filter ? $siteintelix_file_filter : false,
			'siteintelix_log_time'       => 'all' !== $siteintelix_time_filter ? $siteintelix_time_filter : false,
			'siteintelix_log_plugin'     => 'all' !== $siteintelix_plugin_filter ? $siteintelix_plugin_filter : false,
			'siteintelix_hide_deprecated' => $siteintelix_hide_deprecated ? '1' : false,
			'siteintelix_only_critical'  => $siteintelix_only_critical ? '1' : false,
		),
		$args
	);
	unset( $query['siteintelix_log_page'] );

	return add_query_arg( $query, admin_url( 'admin.php' ) );
};

ob_start();
?>
<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
	<span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
</a>
<?php if ( $siteintelix_log_has_file ) : ?>
	<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export Log', 'siteintelix' ); ?>
	</a>
<?php endif; ?>
<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>" class="sitx-debug-header-form siteintelix-debug-action-form">
	<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
	<input type="hidden" name="action" value="siteintelix_clear_debug_log">
	<button type="submit" class="sitx-btn sitx-btn--white si-button si-button--danger">
		<span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Clear Logs', 'siteintelix' ); ?>
	</button>
</form>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
	<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Settings', 'siteintelix' ); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
</a>
<?php
$siteintelix_header_actions = ob_get_clean();
?>
<div class="wrap siteintelix-wrap si-admin-wrap siteintelix-debug-ui is-card-view" id="siteintelix-debug-log-page">
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

	<div class="sitx-debug-modern-body">
	<section class="sitx-debug-summary" aria-label="<?php esc_attr_e( 'Debug log summary', 'siteintelix' ); ?>">
		<?php foreach ( $siteintelix_summary_cards as $siteintelix_card ) : ?>
			<article class="sitx-summary-card sitx-summary-card--<?php echo esc_attr( $siteintelix_card['key'] ); ?>">
				<span class="sitx-summary-card__icon dashicons <?php echo esc_attr( $siteintelix_card['icon'] ); ?>" aria-hidden="true"></span>
				<div class="sitx-summary-card__content">
					<strong><?php echo esc_html( number_format_i18n( (int) $siteintelix_card['count'] ) ); ?></strong>
					<span class="sitx-summary-card__label"><?php echo esc_html( $siteintelix_card['label'] ); ?></span>
				</div>
			</article>
		<?php endforeach; ?>
	</section>

	<section class="sitx-filter-panel si-toolbar" aria-label="<?php esc_attr_e( 'Debug log filters', 'siteintelix' ); ?>">
		<div class="sitx-filter-panel__top">
			<form class="sitx-search-field" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="siteintelix-debug-log">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search logs', 'siteintelix' ); ?></span>
				<input type="search" id="siteintelix-log-search" name="siteintelix_log_search" value="<?php echo esc_attr( $siteintelix_search_query ); ?>" data-siteintelix-global-search placeholder="<?php esc_attr_e( 'Search logs...', 'siteintelix' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( 'Search logs', 'siteintelix' ); ?>">
				<kbd><?php esc_html_e( '⌘K', 'siteintelix' ); ?></kbd>
			</form>
			<nav class="sitx-level-tabs" aria-label="<?php esc_attr_e( 'Filter logs by level', 'siteintelix' ); ?>">
				<?php foreach ( $siteintelix_level_tabs as $siteintelix_level_value => $siteintelix_level_label ) : ?>
					<a class="sitx-level-tab sitx-level-tab--<?php echo esc_attr( $siteintelix_level_value ); ?> <?php echo $siteintelix_type_filter === $siteintelix_level_value ? 'is-active' : ''; ?>" href="<?php echo esc_url( $siteintelix_filter_url( array( 'siteintelix_log_type' => 'all' === $siteintelix_level_value ? false : $siteintelix_level_value ) ) ); ?>">
						<?php echo esc_html( $siteintelix_level_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="sitx-debug-view-toggle" role="group" aria-label="<?php esc_attr_e( 'Debug log view', 'siteintelix' ); ?>">
				<button type="button" class="sitx-debug-view-toggle__button is-active" data-sitx-view="cards" aria-pressed="true">
					<span class="dashicons dashicons-screenoptions" aria-hidden="true"></span><?php esc_html_e( 'Cards', 'siteintelix' ); ?>
				</button>
				<button type="button" class="sitx-debug-view-toggle__button" data-sitx-view="table" aria-pressed="false">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span><?php esc_html_e( 'Table', 'siteintelix' ); ?>
				</button>
			</div>
		</div>
		<div class="sitx-filter-panel__middle">
			<label class="screen-reader-text" for="siteintelix-file-filter"><?php esc_html_e( 'Filter by file', 'siteintelix' ); ?></label>
			<label class="screen-reader-text" for="siteintelix-plugin-filter"><?php esc_html_e( 'Filter by plugin', 'siteintelix' ); ?></label>
			<select id="siteintelix-plugin-filter" class="sitx-select-filter">
				<option value="all"><?php esc_html_e( 'Plugin: All', 'siteintelix' ); ?></option>
				<?php foreach ( $siteintelix_unique_plugins as $siteintelix_plugin ) : ?>
					<option value="<?php echo esc_attr( $siteintelix_plugin ); ?>" <?php selected( $siteintelix_plugin_filter, $siteintelix_plugin ); ?>><?php echo esc_html( $siteintelix_plugin ); ?></option>
				<?php endforeach; ?>
			</select>
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
			<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-sitx-more-filters aria-expanded="false">
				<span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e( 'More Filters', 'siteintelix' ); ?>
			</button>
			<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary sitx-saved-filters" id="siteintelix-saved-filters">
				<span class="dashicons dashicons-bookmark" aria-hidden="true"></span><?php esc_html_e( 'Saved Filters', 'siteintelix' ); ?><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
		</div>
		<div class="sitx-filter-panel__bottom is-collapsed" data-sitx-advanced-filters>
			<label class="sitx-check"><input type="checkbox" id="siteintelix-hide-deprecated" <?php checked( $siteintelix_hide_deprecated ); ?>> <?php esc_html_e( 'Hide Deprecated', 'siteintelix' ); ?></label>
			<label class="sitx-check"><input type="checkbox" id="siteintelix-only-critical" <?php checked( $siteintelix_only_critical ); ?>> <?php esc_html_e( 'Show Only Critical', 'siteintelix' ); ?></label>
			<button type="button" class="sitx-filter-link sitx-filter-link--clear si-button si-button--ghost" id="siteintelix-clear-filters">
				<span class="dashicons dashicons-no-alt" aria-hidden="true"></span><?php esc_html_e( 'Clear Filters', 'siteintelix' ); ?>
			</button>
		</div>
	</section>

	<section class="sitx-log-section si-card" aria-label="<?php esc_attr_e( 'Log entries', 'siteintelix' ); ?>">
		<div class="sitx-log-section__head">
			<div class="sitx-log-section__meta">
				<span id="siteintelix-visible-count"><?php echo esc_html( sprintf(
					/* translators: %d: log group count. */
					_n( '%d log group', '%d log groups', $siteintelix_total_groups, 'siteintelix' ),
					(int) $siteintelix_total_groups
				) ); ?></span>
				<span class="sitx-log-section__divider" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Grouped by message', 'siteintelix' ); ?> <span class="dashicons dashicons-info-outline" aria-hidden="true"></span></span>
			</div>
			<button type="button" class="sitx-sort-label" aria-label="<?php esc_attr_e( 'Sort logs by newest first', 'siteintelix' ); ?>">
				<?php esc_html_e( 'Sort by:', 'siteintelix' ); ?> <strong><?php esc_html_e( 'Newest First', 'siteintelix' ); ?></strong><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button>
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
			<div class="sitx-log-list" data-sitx-log-list>
				<?php foreach ( $siteintelix_page_groups as $siteintelix_index => $siteintelix_group ) : ?>
					<?php
					$siteintelix_level       = strtolower( (string) $siteintelix_group['level'] );
					$siteintelix_group_id    = 'sitx-log-details-' . (int) $siteintelix_index;
					$siteintelix_title       = (string) $siteintelix_group['title'];
					$siteintelix_file        = (string) $siteintelix_group['file'];
					$siteintelix_line        = (int) $siteintelix_group['line_number'];
					$siteintelix_plugin      = (string) $siteintelix_group['plugin'];
					$siteintelix_last_seen   = $siteintelix_group['last_seen'] ? $siteintelix_human_time( (int) $siteintelix_group['last_seen'] ) : __( 'Unknown', 'siteintelix' );
					$siteintelix_first_seen  = $siteintelix_group['first_seen'] ? $siteintelix_human_time( (int) $siteintelix_group['first_seen'] ) : __( 'Unknown', 'siteintelix' );
					$siteintelix_stack_lines = preg_split( "/\\r\\n|\\n|\\r/", wp_strip_all_tags( (string) $siteintelix_group['message'] ) );
					$siteintelix_stack_text  = trim( implode( "\n", array_filter( is_array( $siteintelix_stack_lines ) ? $siteintelix_stack_lines : array( (string) $siteintelix_group['message'] ) ) ) );
					if ( '' === $siteintelix_stack_text ) {
						$siteintelix_stack_text = $siteintelix_title;
					}
					$siteintelix_details_text = sprintf(
						"Level: %s\nMessage: %s\nFile: %s\nLine: %d\nOccurrences: %d",
						(string) $siteintelix_group['level'],
						wp_strip_all_tags( (string) $siteintelix_group['message'] ),
						$siteintelix_file ? $siteintelix_file : '-',
						$siteintelix_line,
						(int) $siteintelix_group['count']
					);
					?>
					<article class="sitx-log-card si-card sitx-log-card--<?php echo esc_attr( $siteintelix_level ); ?>"
						data-log-card
						data-group-card
						data-level="<?php echo esc_attr( $siteintelix_level ); ?>"
						data-file="<?php echo esc_attr( $siteintelix_file ); ?>"
						data-plugin="<?php echo esc_attr( $siteintelix_plugin ); ?>"
						data-message="<?php echo esc_attr( strtolower( $siteintelix_title . ' ' . wp_strip_all_tags( (string) $siteintelix_group['message'] ) ) ); ?>"
						data-last-seen="<?php echo esc_attr( (string) (int) $siteintelix_group['last_seen'] ); ?>"
					>
						<div class="sitx-log-card__summary">
							<div class="sitx-log-card__severity">
								<span class="sitx-severity-icon dashicons <?php echo esc_attr( isset( $siteintelix_level_icons[ $siteintelix_level ] ) ? $siteintelix_level_icons[ $siteintelix_level ] : 'dashicons-info-outline' ); ?>" aria-hidden="true"></span>
								<span class="sitx-severity-badge"><?php echo esc_html( isset( $siteintelix_level_labels[ $siteintelix_group['level'] ] ) ? $siteintelix_level_labels[ $siteintelix_group['level'] ] : $siteintelix_group['level'] ); ?></span>
							</div>
							<div class="sitx-log-card__body">
								<h3><?php echo esc_html( $siteintelix_title ); ?></h3>
								<div class="sitx-log-meta">
									<span class="sitx-source-pill"><?php echo esc_html( $siteintelix_plugin ); ?></span>
									<span class="sitx-log-path"><span class="dashicons dashicons-media-code" aria-hidden="true"></span><?php echo esc_html( $siteintelix_file ? $siteintelix_file : '-' ); ?><?php echo $siteintelix_line ? ':' . (int) $siteintelix_line : ''; ?></span>
								</div>
							</div>
							<div class="sitx-log-card__actions">
								<span class="sitx-occurrence-pill"><?php echo esc_html( sprintf(
									/* translators: %d: log occurrence count. */
									_n( '%d occurrence', '%d occurrences', (int) $siteintelix_group['count'], 'siteintelix' ),
									(int) $siteintelix_group['count']
								) ); ?></span>
								<span class="sitx-latest-pill"><?php esc_html_e( 'Latest:', 'siteintelix' ); ?> <?php echo esc_html( $siteintelix_last_seen ); ?></span>
								<button type="button" class="sitx-icon-btn si-button si-button--icon" data-sitx-toggle-details aria-label="<?php esc_attr_e( 'Expand log details', 'siteintelix' ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $siteintelix_group_id ); ?>">
									<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
								</button>
							</div>
						</div>
						<div class="sitx-log-card__details" id="<?php echo esc_attr( $siteintelix_group_id ); ?>" hidden>
							<div class="sitx-log-details-grid">
								<aside class="sitx-timeline" aria-label="<?php esc_attr_e( 'Occurrence timeline', 'siteintelix' ); ?>">
									<h4><?php esc_html_e( 'Occurrence Timeline', 'siteintelix' ); ?></h4>
									<ol>
										<?php foreach ( array_slice( $siteintelix_group['entries'], 0, 6 ) as $siteintelix_occurrence ) : ?>
											<li>
												<span aria-hidden="true"></span>
												<strong><?php echo esc_html( $siteintelix_human_time( isset( $siteintelix_occurrence['timestamp'] ) ? $siteintelix_occurrence['timestamp'] : '' ) ); ?></strong>
											</li>
										<?php endforeach; ?>
									</ol>
									<?php if ( (int) $siteintelix_group['count'] > 6 ) : ?>
											<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary" disabled><?php echo esc_html( sprintf(
												/* translators: %d: remaining log occurrence count. */
												__( 'Show %d more', 'siteintelix' ),
												(int) $siteintelix_group['count'] - 6
											) ); ?></button>
									<?php endif; ?>
								</aside>
								<div class="sitx-stack">
									<div class="sitx-stack__head">
										<h4><?php esc_html_e( 'Stack Trace', 'siteintelix' ); ?></h4>
										<div>
											<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-copy-text="<?php echo esc_attr( $siteintelix_stack_text ); ?>">
												<span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Copy', 'siteintelix' ); ?>
											</button>
											<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" disabled title="<?php esc_attr_e( 'Editor protocol is not configured.', 'siteintelix' ); ?>">
												<span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Open in Editor', 'siteintelix' ); ?>
											</button>
										</div>
									</div>
									<pre><code><?php echo esc_html( $siteintelix_stack_text ); ?></code></pre>
								</div>
							</div>
							<div class="sitx-log-detail-actions">
								<button type="button" class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" data-copy-text="<?php echo esc_attr( $siteintelix_details_text ); ?>">
									<span class="dashicons dashicons-admin-page" aria-hidden="true"></span><?php esc_html_e( 'Copy Details', 'siteintelix' ); ?>
								</button>
								<?php if ( $siteintelix_log_has_file ) : ?>
									<a class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_download_url ); ?>">
										<span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'View Full Log', 'siteintelix' ); ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<div class="sitx-log-table-wrap" data-sitx-log-table>
				<table class="sitx-log-table">
					<thead>
						<tr>
							<th scope="col" class="sitx-log-table__check"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all log groups', 'siteintelix' ); ?>"></th>
							<th scope="col"><?php esc_html_e( 'Severity', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'Message', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'Plugin / Source', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'File', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'Occurrences', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'Last Seen', 'siteintelix' ); ?> <span class="sitx-sort-caret dashicons dashicons-sort" aria-hidden="true"></span></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $siteintelix_page_groups as $siteintelix_index => $siteintelix_group ) : ?>
							<?php
							$siteintelix_level      = strtolower( (string) $siteintelix_group['level'] );
							$siteintelix_group_id   = 'sitx-log-table-details-' . (int) $siteintelix_index;
							$siteintelix_title      = (string) $siteintelix_group['title'];
							$siteintelix_message    = wp_strip_all_tags( (string) $siteintelix_group['message'] );
							$siteintelix_file       = (string) $siteintelix_group['file'];
							$siteintelix_line       = (int) $siteintelix_group['line_number'];
							$siteintelix_plugin     = (string) $siteintelix_group['plugin'];
							$siteintelix_last_seen  = $siteintelix_group['last_seen'] ? $siteintelix_human_time( (int) $siteintelix_group['last_seen'] ) : __( 'Unknown', 'siteintelix' );
							$siteintelix_stack_text = trim( $siteintelix_message );
							if ( '' === $siteintelix_stack_text ) {
								$siteintelix_stack_text = $siteintelix_title;
							}
							?>
							<tr class="sitx-log-table__row sitx-log-table__row--<?php echo esc_attr( $siteintelix_level ); ?>">
								<td class="sitx-log-table__check"><input type="checkbox" aria-label="<?php echo esc_attr( sprintf(
									/* translators: %s: log title. */
									__( 'Select log group: %s', 'siteintelix' ),
									$siteintelix_title
								) ); ?>"></td>
								<td><span class="sitx-severity-chip sitx-severity-chip--<?php echo esc_attr( $siteintelix_level ); ?>"><?php echo esc_html( isset( $siteintelix_level_labels[ $siteintelix_group['level'] ] ) ? $siteintelix_level_labels[ $siteintelix_group['level'] ] : $siteintelix_group['level'] ); ?></span></td>
								<td>
									<strong><?php echo esc_html( $siteintelix_title ); ?></strong>
									<small><?php echo esc_html( wp_trim_words( $siteintelix_message, 14, '...' ) ); ?></small>
								</td>
								<td><span class="sitx-source-pill"><?php echo esc_html( $siteintelix_plugin ); ?></span></td>
								<td><code><?php echo esc_html( $siteintelix_file ? $siteintelix_file : '-' ); ?><?php echo $siteintelix_line ? ':' . (int) $siteintelix_line : ''; ?></code></td>
								<td><?php echo esc_html( number_format_i18n( (int) $siteintelix_group['count'] ) ); ?></td>
								<td><?php echo esc_html( $siteintelix_last_seen ); ?></td>
								<td>
									<div class="sitx-log-table__actions">
										<button type="button" class="sitx-icon-btn si-button si-button--icon" data-sitx-table-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $siteintelix_group_id ); ?>" aria-label="<?php esc_attr_e( 'View log details', 'siteintelix' ); ?>">
											<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
										</button>
										<button type="button" class="sitx-icon-btn si-button si-button--icon" data-sitx-table-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $siteintelix_group_id ); ?>" aria-label="<?php esc_attr_e( 'Expand log details', 'siteintelix' ); ?>">
											<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
										</button>
									</div>
								</td>
							</tr>
							<tr class="sitx-log-table__details" id="<?php echo esc_attr( $siteintelix_group_id ); ?>" hidden>
								<td colspan="8"><pre><code><?php echo esc_html( $siteintelix_stack_text ); ?></code></pre></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<div class="sitx-log-pagination">
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
			<div class="sitx-log-pagination__controls">
				<?php
				if ( $siteintelix_total_pages > 1 ) {
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
				} else {
					echo '<span class="page-numbers current">1</span>';
				}
				?>
				<span class="sitx-per-page-control"><?php echo esc_html( number_format_i18n( $siteintelix_logs_per_page ) ); ?> <span><?php esc_html_e( 'per page', 'siteintelix' ); ?></span></span>
			</div>
		</div>
	</section>
	</div>
</div>
