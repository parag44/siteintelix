<?php
/**
 * Dependency-free File Manager filesystem tests.
 *
 * @package SiteIntelix
 */

$siteintelix_test_root = sys_get_temp_dir() . '/siteintelix-fm-filesystem-' . bin2hex( random_bytes( 4 ) );
mkdir( $siteintelix_test_root . '/wp-content/uploads/folder', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/plugins/siteintelix', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/themes/active', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/mu-plugins', 0777, true );
mkdir( $siteintelix_test_root . '/wp-content/languages', 0777, true );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/alpha.txt', "hello <script>alert(1)</script>\n" );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/beta.log', "log\n" );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/.secret', 'hidden' );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/binary.bin', "a\0b" );
file_put_contents( $siteintelix_test_root . '/wp-content/uploads/large.txt', str_repeat( 'x', 128 ) );
file_put_contents( $siteintelix_test_root . '/wp-config.php', "<?php define( 'DB_PASSWORD', 'visible-secret' );" );

define( 'ABSPATH', $siteintelix_test_root . '/' );
define( 'WP_CONTENT_DIR', $siteintelix_test_root . '/wp-content' );
define( 'WP_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/mu-plugins' );
define( 'SITEINTELIX_PLUGIN_DIR', $siteintelix_test_root . '/wp-content/plugins/siteintelix/' );
define( 'KB_IN_BYTES', 1024 );
define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );

$siteintelix_test_options = array(
	'siteintelix_file_manager_settings' => array(
		'preview_max_bytes' => 64,
	),
);
$siteintelix_test_filters = array();

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code, $message ) {
		$this->code = $code;
		$this->message = $message;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}
function __( $text ) {
	return $text;
}
function apply_filters( $hook, $value ) {
	global $siteintelix_test_filters;
	if ( isset( $siteintelix_test_filters[ $hook ] ) ) {
		return call_user_func( $siteintelix_test_filters[ $hook ], $value );
	}
	return $value;
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function wp_normalize_path( $path ) {
	return str_replace( '\\', '/', (string) $path );
}
function trailingslashit( $path ) {
	return rtrim( wp_normalize_path( $path ), '/' ) . '/';
}
function untrailingslashit( $path ) {
	return rtrim( wp_normalize_path( $path ), '/' );
}
function wp_upload_dir() {
	return array( 'basedir' => WP_CONTENT_DIR . '/uploads' );
}
function get_theme_root() {
	return WP_CONTENT_DIR . '/themes';
}
function get_stylesheet_directory() {
	return WP_CONTENT_DIR . '/themes/active';
}
function is_multisite() {
	return false;
}
function current_user_can( $capability ) {
	return true;
}
function get_option( $key, $default = false ) {
	global $siteintelix_test_options;
	return array_key_exists( $key, $siteintelix_test_options ) ? $siteintelix_test_options[ $key ] : $default;
}
function wp_check_filetype( $file ) {
	$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$mimes = array(
		'txt' => 'text/plain',
		'log' => 'text/plain',
		'jpg' => 'image/jpeg',
	);
	return array( 'ext' => $extension, 'type' => isset( $mimes[ $extension ] ) ? $mimes[ $extension ] : 'application/octet-stream' );
}
function size_format( $bytes ) {
	return $bytes . ' B';
}
function siteintelix_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

$base = dirname( __DIR__ ) . '/includes/modules/file-manager/';
foreach ( array( 'settings', 'security', 'redactor', 'filesystem' ) as $class ) {
	$file = $base . 'class-siteintelix-file-manager-' . $class . '.php';
	siteintelix_test_assert( file_exists( $file ), "{$class} class exists" );
	require_once $file;
}

$filesystem = new SITEINTELIX_File_Manager_Filesystem( new SITEINTELIX_File_Manager_Security() );
$listing = $filesystem->list_directory(
	'wp-content/uploads',
	array(
		'page' => 1,
		'per_page' => 2,
		'sort' => 'name',
		'order' => 'asc',
		'search' => '',
	)
);
siteintelix_test_assert( ! is_wp_error( $listing ), 'directory listing succeeds' );
siteintelix_test_assert( 2 === count( $listing['items'] ), 'listing is paginated' );
siteintelix_test_assert( 'directory' === $listing['items'][0]['type'], 'directories sort first' );
siteintelix_test_assert( false === in_array( '.secret', array_column( $listing['items'], 'name' ), true ), 'hidden files stay hidden' );
siteintelix_test_assert( 3 === count( $listing['breadcrumbs'] ), 'breadcrumbs are rooted and bounded' );

$search = $filesystem->list_directory( 'wp-content/uploads', array( 'search' => 'beta', 'per_page' => 50 ) );
siteintelix_test_assert( 1 === count( $search['items'] ) && 'beta.log' === $search['items'][0]['name'], 'search is limited to current-directory filenames' );

$preview = $filesystem->preview( 'wp-content/uploads/alpha.txt' );
siteintelix_test_assert( true === $preview['previewable'], 'approved text is previewable' );
siteintelix_test_assert( false !== strpos( $preview['content'], '<script>' ), 'HTML-like content is returned as source text' );
$binary = $filesystem->preview( 'wp-content/uploads/binary.bin' );
siteintelix_test_assert( false === $binary['previewable'] && 'binary' === $binary['reason'], 'binary content is not rendered as text' );
$large = $filesystem->preview( 'wp-content/uploads/large.txt' );
siteintelix_test_assert( false === $large['previewable'] && 'too_large' === $large['reason'], 'oversized preview is blocked' );

$details = $filesystem->details( 'wp-content/uploads/alpha.txt', false );
siteintelix_test_assert( ! isset( $details['md5'] ), 'hashes are not calculated by default' );
$hashed = $filesystem->details( 'wp-content/uploads/alpha.txt', true );
siteintelix_test_assert( hash_file( 'sha256', $siteintelix_test_root . '/wp-content/uploads/alpha.txt' ) === $hashed['sha256'], 'SHA-256 is calculated on demand' );

$siteintelix_test_filters['siteintelix_file_manager_allow_wp_config_preview'] = static function () {
	return true;
};
$config = $filesystem->preview( 'wp-config.php' );
siteintelix_test_assert( ! is_wp_error( $config ) && false === strpos( $config['content'], 'visible-secret' ), 'explicit wp-config preview is redacted' );

echo "File Manager filesystem tests passed.\n";
