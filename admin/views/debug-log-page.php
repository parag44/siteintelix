<?php
/**
 * Debug Log Viewer page dispatcher for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   2.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_debug_ui = get_option( SITEINTELIX_DEBUG_UI_OPTION, 'modern' );
$siteintelix_debug_ui = 'terminal_dark' === $siteintelix_debug_ui ? 'terminal_light' : $siteintelix_debug_ui;

if ( 'classic' === $siteintelix_debug_ui ) {
	require SITEINTELIX_PLUGIN_DIR . 'admin/views/debug-log-page-classic.php';
	return;
}

if ( 'terminal_light' === $siteintelix_debug_ui ) {
	require SITEINTELIX_PLUGIN_DIR . 'admin/views/debug-log-page-terminal.php';
	return;
}

require SITEINTELIX_PLUGIN_DIR . 'admin/views/debug-log-page-modern.php';
