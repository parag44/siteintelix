<?php
/**
 * WP-CLI smoke tests for native WordPress editor links.
 *
 * Run with: wp eval-file wp-content/plugins/siteintelix/tests/editor-links.php
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	exit;
}

wp_set_current_user( 1 );

/**
 * Assert editor-link behavior.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Assertion label.
 * @return void
 */
function siteintelix_editor_link_assert( $condition, $message ) {
	if ( ! $condition ) {
		WP_CLI::error( $message );
	}

	WP_CLI::log( 'PASS: ' . $message );
}

$plugin_link = SITEINTELIX_Editor_Links::get_link(
	SITEINTELIX_PLUGIN_DIR . 'siteintelix.php',
	42
);

siteintelix_editor_link_assert( 'plugin' === $plugin_link['type'], 'Plugin file classified as plugin.' );
siteintelix_editor_link_assert( false !== strpos( $plugin_link['url'], 'plugin-editor.php' ), 'Plugin editor URL used.' );
siteintelix_editor_link_assert( false !== strpos( $plugin_link['url'], 'siteintelix_line=42' ), 'Plugin editor URL includes the line.' );

$theme      = wp_get_theme();
$theme_file = trailingslashit( $theme->get_stylesheet_directory() ) . 'functions.php';
if ( is_file( $theme_file ) ) {
	$theme_link = SITEINTELIX_Editor_Links::get_link( $theme_file, 12 );
	siteintelix_editor_link_assert( 'theme' === $theme_link['type'], 'Theme file classified as theme.' );
	siteintelix_editor_link_assert( false !== strpos( $theme_link['url'], 'theme-editor.php' ), 'Theme editor URL used.' );
	siteintelix_editor_link_assert( false !== strpos( $theme_link['url'], 'siteintelix_line=12' ), 'Theme editor URL includes the line.' );
}

$core_link = SITEINTELIX_Editor_Links::get_link( ABSPATH . WPINC . '/functions.php', 6170 );
siteintelix_editor_link_assert( empty( $core_link ), 'WordPress core files remain unlinked.' );

WP_CLI::success( 'SiteIntelix editor-link checks passed.' );
