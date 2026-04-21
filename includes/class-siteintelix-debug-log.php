<?php
/**
 * SITEINTELIX_Debug_Log - reads and classifies log entries.
 *
 * Reads from wp-content/siteintelix-debug.log (MU-plugin mode)
 * or wp-content/debug.log (wp-config.php mode) based on the
 * selected debug method option.
 *
 * @package SiteIntelix
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SITEINTELIX_Debug_Log
 */
class SITEINTELIX_Debug_Log {

	/**
	 * Return debug log data for rendering.
	 *
	 * @param int $limit Max rows to return (newest first).
	 * @return array<string, mixed>
	 */
	public static function get_data( $limit = 300 ) {
		$method = get_option( 'siteintelix_debug_method', 'mu' );
		$path   = self::get_path_for_mode( $method );
		$data = array(
			'method'   => $method,
			'path'     => $path,
			'exists'   => file_exists( $path ),
			'readable' => is_readable( $path ),
			'enabled'  => (bool) get_option( SITEINTELIX_MU_DEBUG_OPTION, false ),
			'size'     => 0,
			'entries'  => array(),
			'counts'   => array(),
		);

		if ( ! $data['exists'] || ! $data['readable'] ) {
			return $data;
		}

		$size = filesize( $path );
		if ( false !== $size ) {
			$data['size'] = (int) $size;
		}

		$lines = self::read_tail_lines( $path, 2 * 1024 * 1024 );
		if ( empty( $lines ) ) {
			return $data;
		}

		$lines = array_slice( $lines, -1 * absint( $limit ) );
		$lines = array_reverse( $lines );

		foreach ( $lines as $line ) {
			$entry = self::parse_line( $line );
			if ( '' === $entry['message'] ) {
				continue;
			}

			$data['entries'][] = $entry;

			if ( ! isset( $data['counts'][ $entry['level'] ] ) ) {
				$data['counts'][ $entry['level'] ] = 0;
			}
			$data['counts'][ $entry['level'] ]++;
		}

		return $data;
	}

	/**
	 * Get the log file path for a given debug method.
	 *
	 * @param string $method 'mu' or 'wp_config'.
	 * @return string  Absolute path to the log file.
	 */
	public static function get_path_for_mode( $method = 'mu' ) {
		if ( 'wp_config' === $method ) {
			return trailingslashit( WP_CONTENT_DIR ) . 'debug.log';
		}
		return trailingslashit( WP_CONTENT_DIR ) . 'siteintelix-debug.log';
	}

	/**
	 * Return a short human-readable description of the active mode's log source.
	 *
	 * @param string $method 'mu' or 'wp_config'.
	 * @return string
	 */
	public static function get_mode_label( $method = 'mu' ) {
		if ( 'wp_config' === $method ) {
			return __( 'wp-config.php mode — logging to wp-content/debug.log', 'siteintelix' );
		}
		return __( 'MU Plugin mode — logging to wp-content/siteintelix-debug.log', 'siteintelix' );
	}

	/**
	 * Read the tail chunk from a log file and return split lines.
	 *
	 * @param string $path File path.
	 * @param int    $max_bytes Tail size in bytes.
	 * @return array<int, string>
	 */
	private static function read_tail_lines( $path, $max_bytes ) {
		$size = filesize( $path );
		if ( false === $size || 0 === (int) $size ) {
			return array();
		}

		$bytes  = min( (int) $size, max( 1024, (int) $max_bytes ) );
		$offset = max( 0, (int) $size - $bytes );
		$chunk  = file_get_contents( $path, false, null, $offset, $bytes );

		if ( false === $chunk ) {
			return array();
		}

		$lines = preg_split( "/\\r\\n|\\n|\\r/", $chunk );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		// If we read from the middle of the file, drop a likely partial first line.
		if ( $offset > 0 && ! empty( $lines ) ) {
			array_shift( $lines );
		}

		return array_values(
			array_filter(
				array_map( 'trim', $lines ),
				static function ( $line ) {
					return '' !== $line;
				}
			)
		);
	}

	/**
	 * Parse a raw debug.log line into timestamp/message/level.
	 *
	 * @param string $line Log line.
	 * @return array{timestamp: string, level: string, message: string}
	 */
	private static function parse_line( $line ) {
		$line      = (string) $line;
		$timestamp = '';
		$message   = $line;

		if ( preg_match( '/^\\[(.*?)\\]\\s*\\[(.*?)\\]\\s*(.*?)\\s*\\|\\s*([^:]+):(\\d+)$/', $line, $match ) ) {
			$timestamp = isset( $match[1] ) ? (string) $match[1] : '';
			$level     = isset( $match[2] ) ? strtoupper( (string) $match[2] ) : 'OTHER';
			$message   = isset( $match[3] ) ? (string) $match[3] : $line;

			return array(
				'timestamp' => $timestamp,
				'level'     => $level,
				'message'   => $message,
			);
		}

		if ( preg_match( '/^\\[(.*?)\\]\\s*(.*)$/', $line, $match ) ) {
			$timestamp = isset( $match[1] ) ? (string) $match[1] : '';
			$message   = isset( $match[2] ) ? (string) $match[2] : $line;
		}

		return array(
			'timestamp' => $timestamp,
			'level'     => self::detect_level( $message ),
			'message'   => $message,
		);
	}

	/**
	 * Classify log severity using common WP/PHP log patterns.
	 *
	 * @param string $message Log message.
	 * @return string
	 */
	private static function detect_level( $message ) {
		$haystack = strtolower( (string) $message );

		if ( false !== strpos( $haystack, 'fatal error' ) || false !== strpos( $haystack, 'uncaught' ) ) {
			return 'FATAL';
		}
		if ( false !== strpos( $haystack, 'error' ) ) {
			return 'ERROR';
		}
		if ( false !== strpos( $haystack, 'warning' ) || false !== strpos( $haystack, 'warn' ) ) {
			return 'WARN';
		}
		if ( false !== strpos( $haystack, 'debug' ) ) {
			return 'DEBUG';
		}
		if ( false !== strpos( $haystack, 'notice' ) || false !== strpos( $haystack, 'deprecated' ) || false !== strpos( $haystack, 'info' ) ) {
			return 'INFO';
		}

		return 'OTHER';
	}
}
