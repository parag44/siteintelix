<?php
/** Regression test for frontend admin-bar lazy runtime loading. */

define( 'ABSPATH', __DIR__ . '/wp/' );
define( 'SITEINTELIX_PLUGIN_DIR', dirname( __DIR__ ) . '/' );

function current_user_can( $capability ) { return 'update_plugins' === $capability; }
function is_admin_bar_showing() { return true; }
function is_multisite() { return false; }
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function admin_url( $path = '' ) { return '/wp-admin/' . ltrim( $path, '/' ); }
function sanitize_html_class( $value ) { return preg_replace( '/[^a-zA-Z0-9_-]/', '-', (string) $value ); }
function get_site_option( $key, $default = false ) { return $default; }
function get_plugins() {
	return array(
		'example-plugin/example.php' => array( 'Version' => '1.0.0' ),
	);
}
function get_option( $key, $default = false ) {
	if ( 'siteintelix_version_switcher_manifest' === $key ) {
		return array(
			'package-one' => array(
				'package_id'         => 'package-one',
				'plugin_name'        => 'Example Plugin',
				'plugin_slug'        => 'example-plugin',
				'plugin_directory'   => 'example-plugin',
				'primary_plugin_file' => 'example-plugin/example.php',
				'version'            => '1.0.0',
			),
			'package-two' => array(
				'package_id'         => 'package-two',
				'plugin_name'        => 'Example Plugin',
				'plugin_slug'        => 'example-plugin',
				'plugin_directory'   => 'example-plugin',
				'primary_plugin_file' => 'example-plugin/example.php',
				'version'            => '2.0.0',
			),
		);
	}
	if ( 'active_plugins' === $key ) {
		return array( 'example-plugin/example.php' );
	}
	return $default;
}

class SITEINTELIX_Security {
	public static function can_manage_global_tools() { return true; }
}

class Test_Admin_Bar {
	public $nodes = array();
	public function get_node( $id ) { return 'siteintelix' === $id ? array( 'id' => $id ) : false; }
	public function add_node( $node ) { $this->nodes[] = $node; }
}

require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-storage.php';
require_once SITEINTELIX_PLUGIN_DIR . 'includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-module.php';

if ( class_exists( 'SITEINTELIX_Version_Switcher_Switcher', false ) ) {
	fwrite( STDERR, "FAIL: switcher runtime loaded before toolbar callback\n" );
	exit( 1 );
}

$bar = new Test_Admin_Bar();
SITEINTELIX_Version_Switcher_Module::register_admin_bar( $bar );

if ( ! class_exists( 'SITEINTELIX_Version_Switcher_Switcher', false ) || count( $bar->nodes ) < 2 ) {
	fwrite( STDERR, "FAIL: frontend toolbar did not lazy-load its runtime\n" );
	exit( 1 );
}

echo "PASS: frontend toolbar lazy-loads Version Switcher runtime without a fatal error\n";
