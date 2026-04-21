<?php
/**
 * MU debug bootstrap file manager.
 *
 * @package SiteIntelix
 * @since   1.1.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_MU_Debug
 */
class SITEINTELIX_MU_Debug {

	/**
	 * Bootstrap hooks.
	 *
	 * @return void
	 */
	public static function bootstrap() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_mu_plugin_file' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_render_notice' ) );
	}

	/**
	 * Ensure MU plugin file exists for this feature.
	 *
	 * @return void
	 */
	public static function maybe_ensure_mu_plugin_file() {
		$result = self::ensure_mu_plugin_file();

		if ( is_wp_error( $result ) ) {
			self::queue_notice( $result->get_error_message() );
		}
	}

	/**
	 * Create or refresh MU plugin file.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_mu_plugin_file() {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return new WP_Error(
				'siteintelix_mu_debug_fs',
				__( 'SiteIntelix could not initialize the filesystem API for MU debug capture.', 'siteintelix' )
			);
		}

		if ( ! $wp_filesystem->is_dir( WPMU_PLUGIN_DIR ) && ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			return new WP_Error(
				'siteintelix_mu_debug_dir',
				__( 'SiteIntelix could not create wp-content/mu-plugins.', 'siteintelix' )
			);
		}

		$legacy_files = array(
			trailingslashit( WPMU_PLUGIN_DIR ) . 'my-debug-capture.php',
			trailingslashit( WPMU_PLUGIN_DIR ) . 'siteintelix-debug.php',
		);
		foreach ( $legacy_files as $legacy_file ) {
			if ( $wp_filesystem->exists( $legacy_file ) ) {
				$wp_filesystem->delete( $legacy_file, false, 'f' );
			}
		}

		$path     = trailingslashit( WPMU_PLUGIN_DIR ) . SITEINTELIX_MU_DEBUG_FILENAME;
		$contents = self::get_mu_plugin_contents();
		$current  = '';

		if ( $wp_filesystem->exists( $path ) ) {
			$current = (string) $wp_filesystem->get_contents( $path );
		}

		if ( $current === $contents ) {
			return true;
		}

		$written = $wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );

		if ( ! $written ) {
			return new WP_Error(
				'siteintelix_mu_debug_write',
				__( 'SiteIntelix could not write wp-content/mu-plugins/siteintelix-debug-capture.php.', 'siteintelix' )
			);
		}

		return true;
	}

	/**
	 * Queue admin notice.
	 *
	 * @param string $message Notice text.
	 * @return void
	 */
	private static function queue_notice( $message ) {
		set_transient( 'siteintelix_mu_debug_notice', sanitize_text_field( $message ), DAY_IN_SECONDS );
	}

	/**
	 * Render admin notice when queued.
	 *
	 * @return void
	 */
	public static function maybe_render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$message = get_transient( 'siteintelix_mu_debug_notice' );
		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		delete_transient( 'siteintelix_mu_debug_notice' );
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Build MU plugin source.
	 *
	 * @return string
	 */
	private static function get_mu_plugin_contents() {
		return <<<'PHP'
<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION' ) ) {
	define( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_enable_debug_capture' );
}

if ( ! defined( 'SITEINTELIX_DEBUG_LOG_PATH' ) ) {
	define( 'SITEINTELIX_DEBUG_LOG_PATH', WP_CONTENT_DIR . '/siteintelix-debug.log' );
}

if ( ! defined( 'SITEINTELIX_DEBUG_LOG_MAX_BYTES' ) ) {
	define( 'SITEINTELIX_DEBUG_LOG_MAX_BYTES', 5242880 );
}

function siteintelix_debug_capture_enabled() {
	$enabled = get_option( SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION, false );
	return ! empty( $enabled );
}

function siteintelix_debug_relative_path( $file ) {
	$file = (string) $file;
	if ( defined( 'ABSPATH' ) && 0 === strpos( $file, ABSPATH ) ) {
		$file = ltrim( substr( $file, strlen( ABSPATH ) ), '/\\' );
	}
	return str_replace( '\\', '/', $file );
}

function siteintelix_debug_level_label( $errno ) {
	$levels = array(
		E_ERROR             => 'ERROR',
		E_WARNING           => 'WARNING',
		E_PARSE             => 'PARSE',
		E_NOTICE            => 'NOTICE',
		E_CORE_ERROR        => 'CORE_ERROR',
		E_CORE_WARNING      => 'CORE_WARNING',
		E_COMPILE_ERROR     => 'COMPILE_ERROR',
		E_COMPILE_WARNING   => 'COMPILE_WARNING',
		E_USER_ERROR        => 'USER_ERROR',
		E_USER_WARNING      => 'USER_WARNING',
		E_USER_NOTICE       => 'USER_NOTICE',
		E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
		E_DEPRECATED        => 'DEPRECATED',
		E_USER_DEPRECATED   => 'USER_DEPRECATED',
	);
	return isset( $levels[ $errno ] ) ? $levels[ $errno ] : 'ERROR';
}

function siteintelix_debug_prepare_log_file() {
	$path = SITEINTELIX_DEBUG_LOG_PATH;

	if ( file_exists( $path ) ) {
		$size = @filesize( $path );
		if ( false !== $size && $size > SITEINTELIX_DEBUG_LOG_MAX_BYTES ) {
			$backup = $path . '.bak';
			if ( file_exists( $backup ) ) {
				@unlink( $backup );
			}
			if ( ! @rename( $path, $backup ) ) {
				@file_put_contents( $path, '' );
			}
		}
	}

	if ( ! file_exists( $path ) ) {
		$handle = @fopen( $path, 'ab' );
		if ( false === $handle ) {
			return false;
		}
		@fclose( $handle );
	}

	return is_writable( $path );
}

function siteintelix_write_log_entry( $level, $message, $file, $line ) {
	if ( ! siteintelix_debug_prepare_log_file() ) {
		return;
	}

	$entry = sprintf(
		'[%s] [%s] %s | %s:%d',
		date( 'Y-m-d H:i:s' ),
		strtoupper( (string) $level ),
		trim( (string) $message ),
		siteintelix_debug_relative_path( $file ),
		(int) $line
	);

	@error_log( $entry . PHP_EOL, 3, SITEINTELIX_DEBUG_LOG_PATH );
}

function siteintelix_log( $message, $level = 'INFO' ) {
	if ( ! siteintelix_debug_capture_enabled() ) {
		return;
	}

	siteintelix_write_log_entry( $level, $message, 'manual', 0 );
}

function siteintelix_debug_error_handler( $errno, $errstr, $errfile, $errline ) {
	if ( ! siteintelix_debug_capture_enabled() ) {
		return false;
	}

	if ( 0 === ( error_reporting() & $errno ) ) {
		return false;
	}

	siteintelix_write_log_entry( siteintelix_debug_level_label( $errno ), $errstr, $errfile, $errline );
	return true;
}

function siteintelix_debug_shutdown_handler() {
	if ( ! siteintelix_debug_capture_enabled() ) {
		return;
	}

	$last_error = error_get_last();
	if ( ! is_array( $last_error ) || ! isset( $last_error['type'] ) ) {
		return;
	}

	$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR );
	if ( ! in_array( (int) $last_error['type'], $fatal_types, true ) ) {
		return;
	}

	siteintelix_write_log_entry(
		siteintelix_debug_level_label( (int) $last_error['type'] ),
		isset( $last_error['message'] ) ? $last_error['message'] : 'Fatal error',
		isset( $last_error['file'] ) ? $last_error['file'] : 'unknown',
		isset( $last_error['line'] ) ? (int) $last_error['line'] : 0
	);
}

function siteintelix_debug_bootstrap() {
	if ( ! siteintelix_debug_capture_enabled() ) {
		return;
	}

	if ( ! siteintelix_debug_prepare_log_file() ) {
		return;
	}

	@ini_set( 'display_errors', 'Off' );
	@ini_set( 'log_errors', '1' );
	@ini_set( 'error_log', SITEINTELIX_DEBUG_LOG_PATH );
	error_reporting( E_ALL );

	set_error_handler( 'siteintelix_debug_error_handler' );
	register_shutdown_function( 'siteintelix_debug_shutdown_handler' );
}

siteintelix_debug_bootstrap();
PHP;
	}
}
