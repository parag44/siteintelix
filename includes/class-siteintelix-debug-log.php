<?php
/**
 * SITEINTELIX_Debug_Log - reads and classifies log entries.
 *
 * Reads from wp-content/siteintelix-debug.log for both MU-plugin and
 * wp-config.php debug modes.
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

		$limit     = absint( $limit );
		$max_bytes = $limit > 0 ? 2 * 1024 * 1024 : max( 1024, (int) $data['size'] );
		$lines     = self::read_tail_lines( $path, $max_bytes );
		$entries = self::group_multiline_entries( $lines );
		if ( empty( $entries ) ) {
			return $data;
		}

		if ( $limit > 0 ) {
			$entries = array_slice( $entries, -1 * $limit );
		}
		$entries = array_reverse( $entries );

		foreach ( $entries as $raw_entry ) {
			$entry = self::parse_line( $raw_entry );
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
	 * Get the SiteIntelix log file path.
	 *
	 * @param string $method Debug method. Accepted for backwards compatibility.
	 * @return string  Absolute path to the log file.
	 */
	public static function get_path_for_mode( $method = 'mu' ) {
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
			return __( 'wp-config.php mode - logging to wp-content/siteintelix-debug.log', 'siteintelix' );
		}
		return __( 'MU Plugin mode - logging to wp-content/siteintelix-debug.log', 'siteintelix' );
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
	 * Group physical log lines into complete logical entries.
	 *
	 * WordPress/PHP often writes stack traces, long SQL statements, and follow-up
	 * context across multiple physical lines. Only timestamped lines should start
	 * a new row in the viewer; non-timestamped lines belong to the previous row.
	 *
	 * @param array<int, string> $lines Log file lines in chronological order.
	 * @return array<int, string>
	 */
	private static function group_multiline_entries( array $lines ) {
		$entries = array();
		$current = '';

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^\[[^\]]+\]/', $line ) ) {
				if ( '' !== $current ) {
					$entries[] = $current;
				}
				$current = $line;
				continue;
			}

			if ( '' === $current ) {
				$current = $line;
			} else {
				$current .= "\n" . $line;
			}
		}

		if ( '' !== $current ) {
			$entries[] = $current;
		}

		return $entries;
	}

	/**
	 * Parse a raw debug log line into timestamp/message/level/file/line.
	 *
	 * @param string $line Log line.
	 * @return array{timestamp: string, level: string, message: string, raw: string, file: string, line_number: int}
	 */
	private static function parse_line( $line ) {
		$line        = (string) $line;
		$timestamp   = '';
		$message     = $line;
		$file        = '';
		$line_number = 0;

		// MU-plugin structured format: [timestamp] [LEVEL] message | file:line
		if ( preg_match( '/^\\[(.*?)\\]\\s*\\[(.*?)\\]\\s*(.*?)\\s*\\|\\s*(.+?):(\\d+)$/s', $line, $match ) ) {
			$timestamp   = isset( $match[1] ) ? (string) $match[1] : '';
			$level       = isset( $match[2] ) ? strtoupper( (string) $match[2] ) : 'OTHER';
			$message     = isset( $match[3] ) ? trim( (string) $match[3] ) : $line;
			$file        = isset( $match[4] ) ? trim( (string) $match[4] ) : '';
			$line_number = isset( $match[5] ) ? (int) $match[5] : 0;
			$full_message = $message . ( $file ? ' | ' . $file . ( $line_number ? ':' . $line_number : '' ) : '' );

			return array(
				'timestamp'   => $timestamp,
				'level'       => self::normalise_level( $level, $full_message ),
				'message'     => $full_message,
				'raw'         => $line,
				'file'        => $file,
				'line_number' => $line_number,
			);
		}

		// Standard WordPress debug format: [timestamp] PHP message in /path/to/file.php on line N
		if ( preg_match( '/^\\[(.*?)\\]\\s*(.*)$/s', $line, $match ) ) {
			$timestamp = isset( $match[1] ) ? (string) $match[1] : '';
			$message   = isset( $match[2] ) ? (string) $match[2] : $line;
		}

		$file_reference = self::extract_file_reference( $message );
		if ( ! empty( $file_reference ) ) {
			$file        = trim( (string) $file_reference['file'], " \t\n\r\0\x0B'\"" );
			$line_number = (int) $file_reference['line_number'];
		}

		return array(
			'timestamp'   => $timestamp,
			'level'       => self::detect_level( $message ),
			'message'     => $message,
			'raw'         => $line,
			'file'        => $file,
			'line_number' => $line_number,
		);
	}

	/**
	 * Convert a path to be relative to ABSPATH.
	 *
	 * @param string $path File path.
	 * @return string
	 */
	private static function relativise_path( $path ) {
		$path = trim( (string) $path, " \t\n\r\0\x0B'\"" );
		if ( defined( 'ABSPATH' ) && 0 === strpos( $path, ABSPATH ) ) {
			$path = ltrim( substr( $path, strlen( ABSPATH ) ), '/\\' );
		}

		$path = str_replace( '\\', '/', $path );
		foreach ( array( 'wp-content', 'wp-includes', 'wp-admin' ) as $wp_dir ) {
			if ( 0 === strpos( $path, $wp_dir . '/' ) ) {
				return $path;
			}

			$position = strpos( $path, '/' . $wp_dir . '/' );
			if ( false !== $position ) {
				return substr( $path, $position + 1 );
			}
		}

		return $path;
	}

	/**
	 * Extract the final file/line reference from a log message.
	 *
	 * @param string $message Log message.
	 * @return array{raw: string, file: string, line_number: int}|array{}
	 */
	private static function extract_file_reference( $message ) {
		$path_pattern = '(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\|(?:wp-content|wp-includes|wp-admin)[\\\\/])[^\\r\\n<>]+?';
		$patterns     = array(
			'~\\s+in\\s+(' . $path_pattern . ')\\s+on\\s+line\\s+(\\d+)~',
			'~\\s+in\\s+(' . $path_pattern . '):(\\d+)~',
		);

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match_all( $pattern, (string) $message, $matches, PREG_SET_ORDER ) ) {
				continue;
			}

			$match = end( $matches );
			if ( ! is_array( $match ) || empty( $match[1] ) || empty( $match[2] ) ) {
				continue;
			}

			return array(
				'raw'         => (string) $match[0],
				'file'        => (string) $match[1],
				'line_number' => (int) $match[2],
			);
		}

		return array();
	}

	/**
	 * Normalise a known level string and refine it using the message.
	 *
	 * @param string $level  Raw level label.
	 * @param string $message Log message.
	 * @return string
	 */
	private static function normalise_level( $level, $message ) {
		$level = strtoupper( trim( $level ) );

		if ( in_array( $level, array( 'FATAL', 'ERROR', 'PARSE', 'USER_ERROR', 'CORE_ERROR', 'COMPILE_ERROR' ), true ) ) {
			return 'FATAL';
		}

		if ( in_array( $level, array( 'WARN', 'WARNING', 'USER_WARNING', 'CORE_WARNING', 'COMPILE_WARNING' ), true ) ) {
			return 'WARNING';
		}

		if ( in_array( $level, array( 'NOTICE', 'USER_NOTICE' ), true ) ) {
			return 'NOTICE';
		}

		if ( in_array( $level, array( 'DEPRECATED', 'USER_DEPRECATED' ), true ) ) {
			return 'DEPRECATED';
		}

		if ( 'DATABASE' === $level ) {
			return 'DATABASE';
		}

		if ( in_array( $level, array( 'INFO', 'DEBUG' ), true ) ) {
			return 'INFO';
		}

		// Fall back to content-based detection.
		return self::detect_level( $message );
	}

	/**
	 * Classify log severity using common WP/PHP log patterns.
	 *
	 * @param string $message Log message.
	 * @return string
	 */
	private static function detect_level( $message ) {
		$haystack = strtolower( (string) $message );

		if ( false !== strpos( $haystack, 'table' ) && ( false !== strpos( $haystack, 'doesn\'t exist' ) || false !== strpos( $haystack, 'query' ) ) ) {
			return 'DATABASE';
		}
		if ( false !== strpos( $haystack, 'database error' ) || false !== strpos( $haystack, 'wordpress database error' ) ) {
			return 'DATABASE';
		}
		if ( false !== strpos( $haystack, 'fatal error' ) || false !== strpos( $haystack, 'uncaught' ) || false !== strpos( $haystack, 'parse error' ) ) {
			return 'FATAL';
		}
		if ( false !== strpos( $haystack, 'error' ) ) {
			return 'FATAL';
		}
		if ( false !== strpos( $haystack, 'deprecated' ) ) {
			return 'DEPRECATED';
		}
		if ( false !== strpos( $haystack, 'notice' ) ) {
			return 'NOTICE';
		}
		if ( false !== strpos( $haystack, 'warning' ) || false !== strpos( $haystack, 'warn' ) ) {
			return 'WARNING';
		}
		if ( false !== strpos( $haystack, 'info' ) ) {
			return 'INFO';
		}

		return 'INFO';
	}
}
