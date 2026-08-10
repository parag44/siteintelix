<?php
/**
 * Secure native WordPress editor links for Debug Log file paths.
 *
 * @package SiteIntelix
 * @since   2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve validated plugin and theme files to their native WordPress editors.
 */
class SITEINTELIX_Editor_Links {

	/**
	 * Cached plugin ownership map.
	 *
	 * @var array<string, string>|null
	 */
	private static $plugin_files = null;

	/**
	 * Cached theme ownership map.
	 *
	 * @var array<string, array{stylesheet: string, relative: string}>|null
	 */
	private static $theme_files = null;

	/**
	 * Get native editor link metadata for a file.
	 *
	 * @param string $file Absolute log file path.
	 * @param int    $line Reported line number.
	 * @return array{type: string, url: string}|array{}
	 */
	public static function get_link( $file, $line ) {
		if ( self::file_editing_disabled() || ! is_string( $file ) || '' === trim( $file ) ) {
			return array();
		}

		$real_file = realpath( $file );
		if ( false === $real_file || ! is_file( $real_file ) ) {
			return array();
		}

		$real_file = self::normalize_path( $real_file );
		$line      = max( 1, (int) $line );

		if ( current_user_can( 'edit_plugins' ) ) {
			$plugin_files = self::get_plugin_files();
			if ( isset( $plugin_files[ $real_file ] ) ) {
				$plugin = $plugin_files[ $real_file ];
				$file   = self::relative_path( WP_PLUGIN_DIR, $real_file );

				return array(
					'type' => 'plugin',
					'url'  => add_query_arg(
						array(
							'plugin'           => $plugin,
							'file'             => $file,
							'siteintelix_line' => $line,
						),
						admin_url( 'plugin-editor.php' )
					),
				);
			}
		}

		if ( current_user_can( 'edit_themes' ) ) {
			$theme_files = self::get_theme_files();
			if ( isset( $theme_files[ $real_file ] ) ) {
				$theme_file = $theme_files[ $real_file ];

				return array(
					'type' => 'theme',
					'url'  => add_query_arg(
						array(
							'theme'            => $theme_file['stylesheet'],
							'file'             => $theme_file['relative'],
							'siteintelix_line' => $line,
						),
						admin_url( 'theme-editor.php' )
					),
				);
			}
		}

		return array();
	}

	/**
	 * Whether WordPress file editing is disabled.
	 *
	 * @return bool
	 */
	private static function file_editing_disabled() {
		return ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT )
			|| ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
	}

	/**
	 * Build an absolute-file to owning-plugin map.
	 *
	 * @return array<string, string>
	 */
	private static function get_plugin_files() {
		if ( null !== self::$plugin_files ) {
			return self::$plugin_files;
		}

		self::$plugin_files = array();

		if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_plugin_files' ) || ! function_exists( 'wp_get_plugin_file_editable_extensions' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		foreach ( array_keys( get_plugins() ) as $plugin ) {
			$editable_extensions = array_map( 'strtolower', wp_get_plugin_file_editable_extensions( $plugin ) );

			foreach ( get_plugin_files( $plugin ) as $relative_file ) {
				$extension = strtolower( (string) pathinfo( $relative_file, PATHINFO_EXTENSION ) );
				if ( ! in_array( $extension, $editable_extensions, true ) ) {
					continue;
				}

				$real_file = realpath( trailingslashit( WP_PLUGIN_DIR ) . $relative_file );
				if ( false === $real_file || ! self::is_within( WP_PLUGIN_DIR, $real_file ) ) {
					continue;
				}

				self::$plugin_files[ self::normalize_path( $real_file ) ] = $plugin;
			}
		}

		return self::$plugin_files;
	}

	/**
	 * Build an absolute-file to owning-theme map.
	 *
	 * @return array<string, array{stylesheet: string, relative: string}>
	 */
	private static function get_theme_files() {
		if ( null !== self::$theme_files ) {
			return self::$theme_files;
		}

		self::$theme_files = array();

		if ( ! function_exists( 'wp_get_theme_file_editable_extensions' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			$theme_root = realpath( $theme->get_stylesheet_directory() );
			if ( false === $theme_root ) {
				continue;
			}

			foreach ( wp_get_theme_file_editable_extensions( $theme ) as $extension ) {
				foreach ( $theme->get_files( $extension, -1 ) as $relative_file => $absolute_file ) {
					$real_file = realpath( $absolute_file );
					if ( false === $real_file || ! self::is_within( $theme_root, $real_file ) ) {
						continue;
					}

					self::$theme_files[ self::normalize_path( $real_file ) ] = array(
						'stylesheet' => (string) $stylesheet,
						'relative'   => (string) $relative_file,
					);
				}
			}
		}

		return self::$theme_files;
	}

	/**
	 * Confirm a canonical file is inside a canonical root.
	 *
	 * @param string $root Root directory.
	 * @param string $file File path.
	 * @return bool
	 */
	private static function is_within( $root, $file ) {
		$real_root = realpath( $root );
		$real_file = realpath( $file );
		if ( false === $real_root || false === $real_file ) {
			return false;
		}

		$real_root = trailingslashit( self::normalize_path( $real_root ) );
		$real_file = self::normalize_path( $real_file );

		return 0 === strpos( $real_file, $real_root );
	}

	/**
	 * Get a canonical path relative to a canonical root.
	 *
	 * @param string $root Root directory.
	 * @param string $file File path.
	 * @return string
	 */
	private static function relative_path( $root, $file ) {
		$root = trailingslashit( self::normalize_path( realpath( $root ) ) );
		$file = self::normalize_path( $file );

		return ltrim( substr( $file, strlen( $root ) ), '/' );
	}

	/**
	 * Normalize directory separators.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}
