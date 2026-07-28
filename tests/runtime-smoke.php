<?php
/**
 * WP-CLI runtime smoke checks for SiteIntelix admin renderers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_set_current_user( 1 );

if ( ! method_exists( 'SITEINTELIX_Modules', 'reset_cache' ) ) {
	WP_CLI::error( 'Module registry must expose request-cache invalidation.' );
}

$siteintelix_module_filter_calls = 0;
$siteintelix_module_filter       = static function ( $modules ) use ( &$siteintelix_module_filter_calls ) {
	$siteintelix_module_filter_calls++;
	return $modules;
};
add_filter( 'siteintelix_modules', $siteintelix_module_filter );
SITEINTELIX_Modules::reset_cache();
SITEINTELIX_Modules::get_all();
SITEINTELIX_Modules::get_all();
SITEINTELIX_Modules::is_enabled( 'debug_log' );

if ( 1 !== $siteintelix_module_filter_calls ) {
	WP_CLI::error( 'Module registry filter must run once per request cache generation.' );
}

SITEINTELIX_Modules::reset_cache();
SITEINTELIX_Modules::get_all();
if ( 2 !== $siteintelix_module_filter_calls ) {
	WP_CLI::error( 'Resetting module caches must rebuild the filtered registry.' );
}
remove_filter( 'siteintelix_modules', $siteintelix_module_filter );

/**
 * Invoke a private static diagnostics helper for focused runtime assertions.
 *
 * @param string $method Method name.
 * @param array  $args   Method arguments.
 * @return mixed
 */
function siteintelix_runtime_invoke_diagnostics( $method, $args = array() ) {
	$reflection = new ReflectionMethod( 'SITEINTELIX_Server_Diagnostics_Module', $method );
	if ( PHP_VERSION_ID < 80100 ) {
		$reflection->setAccessible( true );
	}

	return $reflection->invokeArgs( null, $args );
}

$siteintelix_summary = siteintelix_runtime_invoke_diagnostics(
	'summarize',
	array(
		array(
			array( 'label' => 'Failure', 'value' => 'No', 'status' => 'danger', 'detail' => '' ),
			array( 'label' => 'Warning', 'value' => 'Low', 'status' => 'warning', 'detail' => '' ),
			array( 'label' => 'Success', 'value' => 'Yes', 'status' => 'pass', 'detail' => '' ),
			array( 'label' => 'Context', 'value' => 'Known', 'status' => 'info', 'detail' => '' ),
		),
	)
);

foreach (
	array(
		'failed'      => 1,
		'warnings'    => 1,
		'passed'      => 1,
		'information' => 1,
	) as $siteintelix_key => $siteintelix_expected
) {
	if ( ! isset( $siteintelix_summary[ $siteintelix_key ] ) || $siteintelix_expected !== $siteintelix_summary[ $siteintelix_key ] ) {
		WP_CLI::error( sprintf( 'Diagnostics summary %s expected %d.', $siteintelix_key, $siteintelix_expected ) );
	}
}

$siteintelix_neutral_summary = siteintelix_runtime_invoke_diagnostics(
	'summarize',
	array(
		array(
			array( 'label' => 'Optional', 'value' => 'Not set', 'status' => 'neutral', 'detail' => '' ),
		),
	)
);

if ( 1 !== ( isset( $siteintelix_neutral_summary['information'] ) ? $siteintelix_neutral_summary['information'] : 0 ) ) {
	WP_CLI::error( 'Diagnostics summary must count neutral rows as information.' );
}

$siteintelix_section_summary = siteintelix_runtime_invoke_diagnostics(
	'section_summary',
	array(
		array(
			array( 'label' => 'Success', 'value' => 'Yes', 'status' => 'pass', 'detail' => '' ),
			array( 'label' => 'Context', 'value' => 'Known', 'status' => 'info', 'detail' => '' ),
		),
	)
);

if ( 'pass' !== $siteintelix_section_summary['severity'] ) {
	WP_CLI::error( 'Diagnostics section with a passed row and no problems must have pass severity.' );
}

$siteintelix_info_section_summary = siteintelix_runtime_invoke_diagnostics(
	'section_summary',
	array(
		array(
			array( 'label' => 'Context', 'value' => 'Known', 'status' => 'info', 'detail' => '' ),
			array( 'label' => 'Optional', 'value' => 'Not set', 'status' => 'neutral', 'detail' => '' ),
		),
	)
);

if ( 'info' !== $siteintelix_info_section_summary['severity'] ) {
	WP_CLI::error( 'All-informational diagnostics section must have info severity.' );
}

$siteintelix_posix_path   = '/Users/Jane Doe/Sites/example/wp-content/file.php';
$siteintelix_windows_path = 'C:\\Users\\Jane Doe\\Sites\\example\\file.php';
$siteintelix_unc_path     = '\\\\Server\\Share\\Jane Doe\\file.php';

foreach ( array( $siteintelix_posix_path, $siteintelix_windows_path, $siteintelix_unc_path ) as $siteintelix_path ) {
	$siteintelix_redacted_path = siteintelix_runtime_invoke_diagnostics( 'maybe_redact_path', array( $siteintelix_path, true ) );
	if ( '*** redacted ***' !== $siteintelix_redacted_path ) {
		WP_CLI::error( 'Diagnostics path redaction leaked a POSIX or Windows path.' );
	}
}

foreach ( array( 'Darwin / 64-bit', 'Create/read/delete passed' ) as $siteintelix_benign_value ) {
	$siteintelix_preserved_value = siteintelix_runtime_invoke_diagnostics( 'maybe_redact_path', array( $siteintelix_benign_value, true ) );
	if ( $siteintelix_benign_value !== $siteintelix_preserved_value ) {
		WP_CLI::error( 'Diagnostics path redaction changed a benign value.' );
	}
}

$siteintelix_report_fixture = array(
	'generated_at' => '2026-07-21 12:00:00',
	'site'         => 'https://example.test/',
	'summary'      => array(),
	'php_server'   => array(
		array( 'label' => 'Loaded php.ini', 'value' => $siteintelix_posix_path, 'status' => 'info', 'detail' => 'Configuration path.' ),
	),
	'extensions'   => array(),
	'filesystem'   => array(
		array( 'label' => 'System temp directory', 'value' => $siteintelix_windows_path, 'status' => 'pass', 'detail' => 'Create/read/delete passed' ),
	),
	'network'      => array(),
	'wordpress'    => array(
		array( 'label' => 'Operating system', 'value' => 'Darwin / 64-bit', 'status' => 'info', 'detail' => 'OS and architecture.' ),
	),
	'database'     => array(),
);
$siteintelix_redacted_report = siteintelix_runtime_invoke_diagnostics( 'redact_report', array( $siteintelix_report_fixture ) );
$siteintelix_redacted_json   = wp_json_encode( $siteintelix_redacted_report );

if ( $siteintelix_posix_path !== $siteintelix_report_fixture['php_server'][0]['value'] || $siteintelix_windows_path !== $siteintelix_report_fixture['filesystem'][0]['value'] ) {
	WP_CLI::error( 'Derived diagnostics redaction must not mutate the collected report.' );
}

foreach ( array( 'Jane', 'Doe', 'Users', 'Sites' ) as $siteintelix_private_fragment ) {
	if ( false !== strpos( $siteintelix_redacted_json, $siteintelix_private_fragment ) ) {
		WP_CLI::error( 'Derived diagnostics report leaked private path fragments.' );
	}
}

if ( 'Darwin / 64-bit' !== $siteintelix_redacted_report['wordpress'][0]['value'] || 'Create/read/delete passed' !== $siteintelix_redacted_report['filesystem'][0]['detail'] ) {
	WP_CLI::error( 'Derived diagnostics report changed benign values.' );
}

$siteintelix_sort_fixture = array(
	'php_server' => array( array( 'label' => 'PHP', 'value' => '', 'status' => 'pass', 'detail' => '' ) ),
	'extensions' => array( array( 'label' => 'Extensions', 'value' => '', 'status' => 'danger', 'detail' => '' ) ),
	'filesystem' => array( array( 'label' => 'Filesystem', 'value' => '', 'status' => 'warning', 'detail' => '' ) ),
	'network'    => array( array( 'label' => 'Network', 'value' => '', 'status' => 'danger', 'detail' => '' ) ),
	'wordpress'  => array( array( 'label' => 'WordPress', 'value' => '', 'status' => 'info', 'detail' => '' ) ),
	'database'   => array( array( 'label' => 'Database', 'value' => '', 'status' => 'pass', 'detail' => '' ) ),
);
$siteintelix_sorted_sections = siteintelix_runtime_invoke_diagnostics( 'get_section_definitions', array( $siteintelix_sort_fixture ) );
$siteintelix_sorted_keys     = array_column( $siteintelix_sorted_sections, 'key' );

if ( array( 'extensions', 'network', 'filesystem', 'php-server', 'database', 'wordpress' ) !== $siteintelix_sorted_keys ) {
	WP_CLI::error( 'Diagnostics sections are not severity sorted with stable source-order ties.' );
}

$siteintelix_hostile_text = '</script><script>alert(1)</script>';
$siteintelix_rendered     = '';

foreach ( array( 'php-server', 'extensions', 'filesystem', 'network', 'wordpress', 'database' ) as $siteintelix_section_key ) {
	$siteintelix_section = array(
		'key'         => $siteintelix_section_key,
		'title'       => ucfirst( $siteintelix_section_key ),
		'description' => 'Fixture section.',
		'icon'        => 'dashicons-admin-generic',
		'rows'        => array(
			array(
				'label'  => 'php-server' === $siteintelix_section_key ? $siteintelix_hostile_text : 'Check',
				'value'  => 'Value',
				'status' => 'pass',
				'detail' => 'Detail',
			),
		),
		'summary'     => array( 'failed' => 0, 'warnings' => 0, 'passed' => 1, 'information' => 0, 'severity' => 'pass' ),
	);

	ob_start();
	siteintelix_runtime_invoke_diagnostics( 'render_section', array( $siteintelix_section ) );
	$siteintelix_rendered .= (string) ob_get_clean();
}

preg_match_all( '/<button\b[^>]*\bid="([^"]+)"[^>]*\bdata-sitx-diag-toggle[^>]*\baria-controls="([^"]+)"[^>]*>/i', $siteintelix_rendered, $siteintelix_toggle_matches, PREG_SET_ORDER );
preg_match_all( '/<div\b[^>]*\bid="([^"]+)"[^>]*\bdata-sitx-diag-panel[^>]*\baria-labelledby="([^"]+)"[^>]*>/i', $siteintelix_rendered, $siteintelix_panel_matches, PREG_SET_ORDER );
preg_match_all( '/<h2>\s*<button\b[^>]*data-sitx-diag-toggle[^>]*>[\s\S]*?<\/button>\s*<\/h2>/i', $siteintelix_rendered, $siteintelix_heading_matches );
preg_match_all( '/<section\b[^>]*\bdata-sitx-diag-section[^>]*\baria-labelledby="(sitx-serverdiag-toggle-[^"]+)"[^>]*>\s*<h2>/i', $siteintelix_rendered, $siteintelix_section_label_matches );

if ( 6 !== count( $siteintelix_toggle_matches ) || 6 !== count( $siteintelix_panel_matches ) || 6 !== count( $siteintelix_heading_matches[0] ) || 6 !== count( $siteintelix_section_label_matches[0] ) ) {
	WP_CLI::error( 'Diagnostics accordion must render six heading-owned toggles and labelled panels.' );
}

$siteintelix_toggle_ids = array();
foreach ( $siteintelix_toggle_matches as $siteintelix_index => $siteintelix_toggle_match ) {
	$siteintelix_toggle_ids[] = $siteintelix_toggle_match[1];
	if ( $siteintelix_toggle_match[1] !== $siteintelix_panel_matches[ $siteintelix_index ][2] || $siteintelix_toggle_match[2] !== $siteintelix_panel_matches[ $siteintelix_index ][1] ) {
		WP_CLI::error( 'Diagnostics accordion ID relationships do not match.' );
	}
}

if ( 6 !== count( array_unique( $siteintelix_toggle_ids ) ) ) {
	WP_CLI::error( 'Diagnostics accordion toggle IDs must be unique.' );
}

preg_match_all( '/<script type="application\/json" data-sitx-diag-payload>([\s\S]*?)<\/script>/', $siteintelix_rendered, $siteintelix_payload_matches );
if ( 6 !== count( $siteintelix_payload_matches[1] ) || false !== strpos( $siteintelix_rendered, $siteintelix_hostile_text ) || false === strpos( $siteintelix_payload_matches[1][0], '\\u003C' ) ) {
	WP_CLI::error( 'Diagnostics JSON payloads must remain parseable and hex-escape hostile HTML.' );
}

foreach ( $siteintelix_payload_matches[1] as $siteintelix_payload_index => $siteintelix_payload_json ) {
	$siteintelix_payload = json_decode( $siteintelix_payload_json, true );
	if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $siteintelix_payload ) ) {
		WP_CLI::error( 'Diagnostics JSON payload did not decode.' );
	}
	if ( 0 === $siteintelix_payload_index && $siteintelix_hostile_text !== $siteintelix_payload[0]['label'] ) {
		WP_CLI::error( 'Diagnostics JSON payload did not preserve the original text.' );
	}
}

$siteintelix_singular_counts = siteintelix_runtime_invoke_diagnostics( 'section_count_text', array( array( 'failed' => 1, 'warnings' => 1, 'passed' => 1, 'information' => 1 ) ) );
$siteintelix_plural_counts   = siteintelix_runtime_invoke_diagnostics( 'section_count_text', array( array( 'failed' => 2, 'warnings' => 2, 'passed' => 2, 'information' => 2 ) ) );

if ( false === strpos( $siteintelix_singular_counts, '1 issue' ) || false === strpos( $siteintelix_singular_counts, '1 warning' ) || false !== strpos( $siteintelix_singular_counts, '1 issues' ) || false !== strpos( $siteintelix_singular_counts, '1 warnings' ) ) {
	WP_CLI::error( 'Diagnostics singular section counts are grammatically incorrect.' );
}
if ( false === strpos( $siteintelix_plural_counts, '2 issues' ) || false === strpos( $siteintelix_plural_counts, '2 warnings' ) ) {
	WP_CLI::error( 'Diagnostics plural section counts are grammatically incorrect.' );
}

if ( 'Show 1 critical issue' !== siteintelix_runtime_invoke_diagnostics( 'filter_count_label', array( 'issues', 1 ) ) || 'Show 2 critical issues' !== siteintelix_runtime_invoke_diagnostics( 'filter_count_label', array( 'issues', 2 ) ) || 'Show 1 warning' !== siteintelix_runtime_invoke_diagnostics( 'filter_count_label', array( 'warnings', 1 ) ) || 'Show 2 warnings' !== siteintelix_runtime_invoke_diagnostics( 'filter_count_label', array( 'warnings', 2 ) ) ) {
	WP_CLI::error( 'Diagnostics filter labels must use correct singular and plural forms.' );
}

$siteintelix_render_page_reflection = new ReflectionMethod( 'SITEINTELIX_Server_Diagnostics_Module', 'render_page' );
$siteintelix_module_lines           = file( $siteintelix_render_page_reflection->getFileName() );
$siteintelix_render_page_source     = implode( '', array_slice( $siteintelix_module_lines, $siteintelix_render_page_reflection->getStartLine() - 1, $siteintelix_render_page_reflection->getEndLine() - $siteintelix_render_page_reflection->getStartLine() + 1 ) );

if ( 1 !== substr_count( $siteintelix_render_page_source, 'self::get_report(' ) || false === strpos( $siteintelix_render_page_source, 'self::redact_report(' ) ) {
	WP_CLI::error( 'Diagnostics page must collect once and derive its redacted copy report without new probes.' );
}

/**
 * Capture a renderer and assert required/forbidden fragments.
 *
 * @param string   $label     Check label.
 * @param callable $renderer  Renderer callback.
 * @param string[] $required  Required fragments.
 * @param string[] $forbidden Forbidden fragments.
 * @return void
 */
function siteintelix_runtime_check( $label, $renderer, $required, $forbidden = array() ) {
	ob_start();
	call_user_func( $renderer );
	$html = (string) ob_get_clean();

	foreach ( $required as $fragment ) {
		if ( false === strpos( $html, $fragment ) ) {
			WP_CLI::error( $label . ' missing: ' . $fragment );
		}
	}

	foreach ( $forbidden as $fragment ) {
		if ( false !== strpos( $html, $fragment ) ) {
			WP_CLI::error( $label . ' unexpectedly contains: ' . $fragment );
		}
	}

	WP_CLI::log( sprintf( '%s: %d bytes', $label, strlen( $html ) ) );
}

siteintelix_runtime_check(
	'Overview',
	'siteintelix_render_admin_page',
	array( 'siteintelix-health-strip', 'System Overview', 'Active Modules', 'siteintelix-copy-btn' ),
	array( 'Custom Error UI' )
);

siteintelix_runtime_check(
	'Modules',
	'siteintelix_render_modules_page',
	array( 'Toolbox Modules', 'data-siteintelix-module-toggle' ),
	array( 'Custom Error UI', 'siteintelix-error-ui-settings' )
);

siteintelix_runtime_check(
	'Settings',
	'siteintelix_render_settings_page',
	array( 'data-siteintelix-settings-tabs', 'Module Settings' ),
	array( 'Custom Error UI', 'siteintelix-error-ui-settings' )
);

siteintelix_runtime_check(
	'Server Diagnostics',
	array( 'SITEINTELIX_Server_Diagnostics_Module', 'render_page' ),
	array( 'PHP &amp; Server', 'Extensions', 'Filesystem', 'Network', 'WordPress', 'Database' ),
	array( 'Compatibility Profiles', 'selected_plugin', 'Check Plugin' )
);

siteintelix_runtime_check(
	'Email Log',
	array( 'SITEINTELIX_Email_Log_Module', 'render_logs_page' ),
	array( 'Email Log', 'sitx-email-log-table' )
);

siteintelix_runtime_check(
	'Cron Events',
	array( 'SITEINTELIX_Cron_Events_Module', 'render_page' ),
	array( 'Cron Events', 'sitx-cron-table' )
);

siteintelix_runtime_check(
	'Database Manager',
	array( 'SITEINTELIX_Database_Manager_Module', 'render_page' ),
	array( 'Database Manager', 'sitx-db-manager' )
);

siteintelix_runtime_check(
	'Transients Manager',
	array( 'SITEINTELIX_Transients_Manager_Module', 'render_page' ),
	array( 'Transients Manager', 'sitx-transients-container' )
);

siteintelix_runtime_check(
	'Safe Mode',
	array( 'SITEINTELIX_Safe_Mode_Debugger_Module', 'render_page' ),
	array( 'Safe Mode Debugger', 'siteintelix-safe-mode-page' )
);

WP_CLI::success( 'SiteIntelix runtime smoke checks passed.' );
