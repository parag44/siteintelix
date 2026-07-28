<?php
/**
 * Standalone ownership tests for SiteIntelix MU bootstrap cleanup.
 *
 * Run with: php tests/mu-files-cleanup.php
 */

$siteintelix_test_root    = sys_get_temp_dir() . '/siteintelix-mu-files-' . bin2hex( random_bytes( 6 ) );
$siteintelix_active_dir   = $siteintelix_test_root . '/active-mu';
$siteintelix_content_dir  = $siteintelix_test_root . '/wp-content';
$siteintelix_legacy_dir   = $siteintelix_content_dir . '/mu-plugins';
$siteintelix_outside_file = $siteintelix_test_root . '/outside-target.php';
$siteintelix_options      = array();
$siteintelix_fail_delete  = false;

mkdir( $siteintelix_active_dir, 0777, true );
mkdir( $siteintelix_legacy_dir, 0777, true );

define( 'ABSPATH', $siteintelix_test_root . '/' );
define( 'WP_CONTENT_DIR', $siteintelix_content_dir );
define( 'WPMU_PLUGIN_DIR', $siteintelix_active_dir );
define( 'SITEINTELIX_MODULES_OPTION', 'siteintelix_enabled_modules' );

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		global $siteintelix_options;
		return array_key_exists( $name, $siteintelix_options ) ? $siteintelix_options[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		global $siteintelix_options;
		$siteintelix_options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		global $siteintelix_options;
		unset( $siteintelix_options[ $name ] );
		return true;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', $path );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $path ) {
		global $siteintelix_fail_delete;
		if ( ! $siteintelix_fail_delete ) {
			unlink( $path );
		}
	}
}

/**
 * Remove the isolated temporary test tree.
 *
 * @param string $path Path inside the test root.
 * @return void
 */
function siteintelix_test_remove_tree( $path ) {
	if ( is_link( $path ) || is_file( $path ) ) {
		unlink( $path );
		return;
	}

	if ( ! is_dir( $path ) ) {
		return;
	}

	foreach ( scandir( $path ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		siteintelix_test_remove_tree( $path . '/' . $entry );
	}

	rmdir( $path );
}

register_shutdown_function(
	static function () use ( $siteintelix_test_root ) {
		siteintelix_test_remove_tree( $siteintelix_test_root );
	}
);

$siteintelix_failures = array();

/**
 * Record a test expectation.
 *
 * @param bool   $condition Whether the assertion passed.
 * @param string $message   Failure message.
 * @return void
 */
function siteintelix_test_assert( $condition, $message ) {
	global $siteintelix_failures;

	if ( ! $condition ) {
		$siteintelix_failures[] = $message;
	}
}

/**
 * Write a test fixture.
 *
 * @param string $directory Directory path.
 * @param string $filename  File name.
 * @param string $contents  File contents.
 * @return string
 */
function siteintelix_test_fixture( $directory, $filename, $contents ) {
	$path = trailingslashit( $directory ) . $filename;
	file_put_contents( $path, $contents );
	return $path;
}

$siteintelix_manager = dirname( __DIR__ ) . '/includes/class-siteintelix-mu-files.php';
siteintelix_test_assert( file_exists( $siteintelix_manager ), 'MU ownership manager file must exist.' );

if ( file_exists( $siteintelix_manager ) ) {
	require_once $siteintelix_manager;
}

siteintelix_test_assert( class_exists( 'SITEINTELIX_MU_Files' ), 'SITEINTELIX_MU_Files class must load.' );

if ( class_exists( 'SITEINTELIX_MU_Files' ) ) {
	$current_debug = "<?php\n/** Plugin Name: SiteIntelix Debug Capture */\nfunction siteintelix_debug_capture_enabled() {}\n";
	$legacy_debug  = "<?php\ndefine( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_enable_debug_capture' );\nfunction siteintelix_debug_capture_enabled() {}\n";
	$safe_mode     = "<?php\n/** Plugin Name: SiteIntelix Safe Mode */\nfunction siteintelix_safe_mode_hash_token() {}\n";
	$safety_guard  = "<?php\n/** Plugin Name: SiteIntelix Plugin Safety Guard */\nfunction siteintelix_psg_filter_active_plugins() {}\n";

	$current_active = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-debug-capture.php', $current_debug );
	$current_legacy = siteintelix_test_fixture( $siteintelix_legacy_dir, 'siteintelix-debug-capture.php', $current_debug );
	$result         = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_DEBUG );

	siteintelix_test_assert( ! file_exists( $current_active ), 'Current Debug Capture must be removed from the active MU directory.' );
	siteintelix_test_assert( ! file_exists( $current_legacy ), 'Current Debug Capture must be removed from the legacy MU directory.' );
	siteintelix_test_assert( 2 === count( $result['removed'] ?? array() ), 'Debug cleanup must report both removed files.' );
	siteintelix_test_assert( isset( $result['preserved'], $result['failed'] ), 'Cleanup result must expose preserved and failed lists.' );

	$legacy_active = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-debug-capture.php', $legacy_debug );
	$legacy_copy   = siteintelix_test_fixture( $siteintelix_legacy_dir, 'siteintelix-debug-capture.php', $legacy_debug );
	$result        = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_DEBUG );

	siteintelix_test_assert( ! file_exists( $legacy_active ) && ! file_exists( $legacy_copy ), 'Legacy Debug Capture signatures must be removed from both directories.' );
	siteintelix_test_assert( 2 === count( $result['removed'] ?? array() ), 'Legacy Debug cleanup must not process a duplicate directory twice.' );

	$foreign_debug = siteintelix_test_fixture(
		$siteintelix_active_dir,
		'siteintelix-debug-capture.php',
		"<?php\n/** Plugin Name: Foreign Debug Tool */\n"
	);
	$unknown       = siteintelix_test_fixture(
		$siteintelix_active_dir,
		'third-party-loader.php',
		"<?php\n/** Plugin Name: Third Party Loader */\n"
	);
	$result        = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_DEBUG );

	siteintelix_test_assert( file_exists( $foreign_debug ), 'Foreign content using an allowed filename must be preserved.' );
	siteintelix_test_assert( file_exists( $unknown ), 'Unknown MU filenames must be preserved.' );
	siteintelix_test_assert( in_array( wp_normalize_path( $foreign_debug ), $result['preserved'] ?? array(), true ), 'Preserved foreign same-name file must be reported.' );

	$foreign_legacy = siteintelix_test_fixture(
		$siteintelix_active_dir,
		'my-debug-capture.php',
		"<?php\n/** Plugin Name: Another Debug Tool */\nfunction another_debug_capture() {}\n"
	);
	$owned_legacy   = siteintelix_test_fixture(
		$siteintelix_legacy_dir,
		'siteintelix-debug.php',
		"<?php\n/** Plugin Name: SiteIntelix Debug Capture */\ndefine( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_enable_debug_capture' );\nfunction siteintelix_debug_capture_enabled() {}\n"
	);
	$result         = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_LEGACY_DEBUG );

	siteintelix_test_assert( file_exists( $foreign_legacy ), 'A foreign same-name legacy MU file must be preserved.' );
	siteintelix_test_assert( ! file_exists( $owned_legacy ), 'A signature-verified legacy SiteIntelix MU file must be removed.' );
	siteintelix_test_assert( in_array( wp_normalize_path( $foreign_legacy ), $result['preserved'] ?? array(), true ), 'The foreign legacy file must be reported as preserved.' );

	$safe_path  = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-safe-mode.php', $safe_mode );
	$debug_path = siteintelix_test_fixture( $siteintelix_legacy_dir, 'siteintelix-debug-capture.php', $current_debug );
	SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_SAFE_MODE );

	siteintelix_test_assert( ! file_exists( $safe_path ), 'Safe Mode cleanup must remove the verified Safe Mode bootstrap.' );
	siteintelix_test_assert( file_exists( $debug_path ), 'Safe Mode cleanup must not remove Debug Capture.' );

	$safe_path   = siteintelix_test_fixture( $siteintelix_legacy_dir, 'siteintelix-safe-mode.php', $safe_mode );
	$guard_path  = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-plugin-safety-guard.php', $safety_guard );
	$full_result = SITEINTELIX_MU_Files::remove_all();

	siteintelix_test_assert( ! file_exists( $debug_path ), 'Full cleanup must remove verified Debug Capture files.' );
	siteintelix_test_assert( ! file_exists( $safe_path ), 'Full cleanup must remove verified Safe Mode files.' );
	siteintelix_test_assert( ! file_exists( $guard_path ), 'Full cleanup must remove the verified retired safety guard.' );
	siteintelix_test_assert( file_exists( $foreign_debug ), 'Full cleanup must preserve foreign content using a SiteIntelix filename.' );
	siteintelix_test_assert( file_exists( $unknown ), 'Full cleanup must preserve unrelated MU files.' );
	siteintelix_test_assert( empty( $full_result['failed'] ?? array( 'missing' ) ), 'Successful full cleanup must report no failures.' );

	file_put_contents( $siteintelix_outside_file, "<?php\n/** Plugin Name: Outside target */\n" );
	$symlink_path = trailingslashit( $siteintelix_legacy_dir ) . 'siteintelix-safe-mode.php';
	$linked       = function_exists( 'symlink' ) && @symlink( $siteintelix_outside_file, $symlink_path );

	if ( $linked ) {
		$symlink_result = SITEINTELIX_MU_Files::remove_type( SITEINTELIX_MU_Files::TYPE_SAFE_MODE );
		siteintelix_test_assert( is_link( $symlink_path ), 'Cleanup must preserve allowed-name symlinks.' );
		siteintelix_test_assert( file_exists( $siteintelix_outside_file ), 'Cleanup must not delete a symlink target.' );
		siteintelix_test_assert( in_array( wp_normalize_path( $symlink_path ), $symlink_result['preserved'] ?? array(), true ), 'Preserved symlink must be reported.' );
	}

	$migrations_file = dirname( __DIR__ ) . '/includes/class-siteintelix-migrations.php';
	require_once $migrations_file;

	$migration_guard = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-plugin-safety-guard.php', $safety_guard );
	update_option( SITEINTELIX_Migrations::VERSION_OPTION, '2.7.2', false );
	SITEINTELIX_Migrations::run();

	siteintelix_test_assert( ! file_exists( $migration_guard ), 'Migration must remove the verified retired safety guard.' );
	siteintelix_test_assert( SITEINTELIX_Migrations::CURRENT_VERSION === get_option( SITEINTELIX_Migrations::VERSION_OPTION ), 'Successful safety-guard cleanup must advance the migration version.' );

	$foreign_guard = siteintelix_test_fixture(
		$siteintelix_active_dir,
		'siteintelix-plugin-safety-guard.php',
		"<?php\n/** Plugin Name: Foreign Safety Guard */\n"
	);
	update_option( SITEINTELIX_Migrations::VERSION_OPTION, '2.7.2', false );
	SITEINTELIX_Migrations::run();

	siteintelix_test_assert( file_exists( $foreign_guard ), 'Migration must preserve a same-name file without the complete SiteIntelix signature.' );
	siteintelix_test_assert( SITEINTELIX_Migrations::CURRENT_VERSION === get_option( SITEINTELIX_Migrations::VERSION_OPTION ), 'Preserving an unverified file must still complete the migration.' );

	$migration_guard = siteintelix_test_fixture( $siteintelix_active_dir, 'siteintelix-plugin-safety-guard.php', $safety_guard );
	update_option( SITEINTELIX_Migrations::VERSION_OPTION, '2.7.2', false );
	$siteintelix_fail_delete = true;
	SITEINTELIX_Migrations::run();

	siteintelix_test_assert( file_exists( $migration_guard ), 'A failed safety-guard deletion must leave the file in place.' );
	siteintelix_test_assert( '2.7.2' === get_option( SITEINTELIX_Migrations::VERSION_OPTION ), 'Failed cleanup must not advance the migration version.' );

	$siteintelix_fail_delete = false;
	SITEINTELIX_Migrations::run();
	siteintelix_test_assert( ! file_exists( $migration_guard ), 'A later migration request must retry and remove the verified safety guard.' );
}

if ( $siteintelix_failures ) {
	fwrite( STDERR, "MU files cleanup tests failed:\n- " . implode( "\n- ", $siteintelix_failures ) . "\n" );
	exit( 1 );
}

echo "MU files cleanup tests passed.\n";
