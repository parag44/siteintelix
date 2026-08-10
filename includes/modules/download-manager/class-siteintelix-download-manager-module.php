<?php
/**
 * Download Manager module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds secure download links for installed plugins and themes.
 */
class SITEINTELIX_Download_Manager_Module {

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() || ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return;
		}

		add_filter( 'plugin_action_links', array( __CLASS__, 'add_plugin_download_link' ), 20, 4 );
		add_filter( 'theme_action_links', array( __CLASS__, 'add_theme_download_link' ), 20, 3 );
		add_action( 'admin_footer-themes.php', array( __CLASS__, 'render_theme_grid_script' ) );
		add_action( 'admin_post_siteintelix_download_package', array( __CLASS__, 'handle_download' ) );
	}

	/**
	 * Add a Download link to each installed plugin row.
	 *
	 * @param array<string,string> $actions     Plugin row actions.
	 * @param string               $plugin_file Plugin file relative to plugins directory.
	 * @param array<string,mixed>  $plugin_data Plugin headers.
	 * @param string               $context     Plugin list context.
	 * @return array<string,string>
	 */
	public static function add_plugin_download_link( $actions, $plugin_file, $plugin_data, $context ) {
		unset( $plugin_data, $context );

		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return $actions;
		}

		$actions['siteintelix_download'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::get_download_url( 'plugin', $plugin_file ) ),
			esc_html__( 'Download', 'siteintelix' )
		);

		return $actions;
	}

	/**
	 * Add a Download link to theme list-table rows.
	 *
	 * @param array<string,string> $actions Theme row actions.
	 * @param WP_Theme             $theme   Theme object.
	 * @param string               $context Theme list context.
	 * @return array<string,string>
	 */
	public static function add_theme_download_link( $actions, $theme, $context ) {
		unset( $context );

		if ( ! SITEINTELIX_Security::can_manage_global_tools() || ! $theme instanceof WP_Theme ) {
			return $actions;
		}

		$stylesheet = $theme->get_stylesheet();
		$actions['siteintelix_download'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( self::get_download_url( 'theme', $stylesheet ) ),
			esc_html__( 'Download', 'siteintelix' )
		);

		return $actions;
	}

	/**
	 * Render a small script that adds Download buttons to the modern Themes grid.
	 *
	 * @return void
	 */
	public static function render_theme_grid_script() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return;
		}

		$themes = wp_get_themes();
		$urls   = array();

		foreach ( $themes as $stylesheet => $theme ) {
			if ( $theme instanceof WP_Theme ) {
				$urls[ $stylesheet ] = self::get_download_url( 'theme', $stylesheet );
			}
		}

		if ( empty( $urls ) ) {
			return;
		}
		?>
		<script>
		( function () {
			var downloadUrls = <?php echo wp_json_encode( $urls ); ?>;

			function addDownloadLinks() {
				document.querySelectorAll( '.theme' ).forEach( function ( themeEl ) {
					var slug = themeEl.getAttribute( 'data-slug' ) || themeEl.getAttribute( 'aria-describedby' );
					var actions = themeEl.querySelector( '.theme-actions' );

					if ( ! slug || ! actions || ! downloadUrls[ slug ] || actions.querySelector( '.siteintelix-theme-download' ) ) {
						return;
					}

					var link = document.createElement( 'a' );
					link.className = 'button siteintelix-theme-download';
					link.href = downloadUrls[ slug ];
					link.textContent = <?php echo wp_json_encode( __( 'Download', 'siteintelix' ) ); ?>;
					actions.appendChild( link );
				} );
			}

			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', addDownloadLinks );
			} else {
				addDownloadLinks();
			}

			if ( window.wp && window.wp.themes && window.wp.themes.view && window.wp.themes.view.Installer ) {
				window.setTimeout( addDownloadLinks, 250 );
			}
		}() );
		</script>
		<?php
	}

	/**
	 * Handle a secure package download request.
	 *
	 * @return void
	 */
	public static function handle_download() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to download packages.', 'siteintelix' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive is not available on this server.', 'siteintelix' ) );
		}

		$type = sanitize_key( self::get_request_value( 'type' ) );
		$item = sanitize_text_field( self::get_request_value( 'item' ) );

		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) || '' === $item ) {
			wp_die( esc_html__( 'Invalid download request.', 'siteintelix' ) );
		}

		self::normalize_escaped_nonce();
		check_admin_referer( self::get_nonce_action( $type, $item ) );

		$package = self::resolve_package( $type, $item );
		if ( is_wp_error( $package ) ) {
			wp_die( esc_html( $package->get_error_message() ) );
		}

		$zip_file = self::create_zip( $package['source'], $package['zip_root'], $package['filename'] );
		if ( is_wp_error( $zip_file ) ) {
			wp_die( esc_html( $zip_file->get_error_message() ) );
		}

		self::stream_zip( $zip_file, $package['filename'] );
	}

	/**
	 * Build a protected download URL.
	 *
	 * @param string $type plugin|theme.
	 * @param string $item Plugin file or theme stylesheet.
	 * @return string
	 */
	private static function get_download_url( $type, $item ) {
		return add_query_arg(
			array(
				'action'   => 'siteintelix_download_package',
				'type'     => $type,
				'item'     => $item,
				'_wpnonce' => wp_create_nonce( self::get_nonce_action( $type, $item ) ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Read a query parameter, including values from copied HTML-escaped URLs.
	 *
	 * @param string $key Query parameter key.
	 * @return string
	 */
	private static function get_request_value( $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shared reader; the fixed type and package item are sanitized and allow-listed by handle_download().
			return (string) wp_unslash( $_GET[ $key ] );
		}

		$escaped_key = 'amp;' . $key;
		if ( isset( $_GET[ $escaped_key ] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Shared reader for HTML-escaped URLs; handle_download() sanitizes and allow-lists the result.
			return (string) wp_unslash( $_GET[ $escaped_key ] );
		}

		return '';
	}

	/**
	 * Make nonce verification work for copied HTML-escaped admin URLs.
	 *
	 * @return void
	 */
	private static function normalize_escaped_nonce() {
		if ( isset( $_REQUEST['_wpnonce'] ) || ! isset( $_REQUEST['amp;_wpnonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The copied nonce is immediately validated by check_admin_referer().
		$_REQUEST['_wpnonce'] = wp_unslash( $_REQUEST['amp;_wpnonce'] );
	}

	/**
	 * Get nonce action for a package request.
	 *
	 * @param string $type plugin|theme.
	 * @param string $item Plugin file or theme stylesheet.
	 * @return string
	 */
	private static function get_nonce_action( $type, $item ) {
		return 'siteintelix_download_' . sanitize_key( $type ) . '_' . md5( (string) $item );
	}

	/**
	 * Resolve a plugin/theme request to a safe local source path.
	 *
	 * @param string $type plugin|theme.
	 * @param string $item Plugin file or theme stylesheet.
	 * @return array<string,string>|WP_Error
	 */
	private static function resolve_package( $type, $item ) {
		if ( 'plugin' === $type ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugins = get_plugins();
			if ( ! isset( $plugins[ $item ] ) ) {
				return new WP_Error( 'siteintelix_invalid_plugin', __( 'The requested plugin could not be found.', 'siteintelix' ) );
			}

			$relative_dir = dirname( $item );
			$source       = '.' === $relative_dir ? WP_PLUGIN_DIR . '/' . $item : WP_PLUGIN_DIR . '/' . $relative_dir;
			$zip_root     = '.' === $relative_dir ? basename( $item ) : basename( $relative_dir );
			$filename     = sanitize_file_name( $zip_root ) . '.zip';

			return self::validate_source( $source, WP_PLUGIN_DIR, $zip_root, $filename );
		}

		$themes = wp_get_themes();
		if ( ! isset( $themes[ $item ] ) ) {
			return new WP_Error( 'siteintelix_invalid_theme', __( 'The requested theme could not be found.', 'siteintelix' ) );
		}

		$source   = get_theme_root( $item ) . '/' . $item;
		$zip_root = basename( $item );
		$filename = sanitize_file_name( $zip_root ) . '.zip';

		return self::validate_source( $source, get_theme_root( $item ), $zip_root, $filename );
	}

	/**
	 * Validate source path and return package data.
	 *
	 * @param string $source   Source file/directory.
	 * @param string $base_dir Allowed base directory.
	 * @param string $zip_root Root name inside ZIP.
	 * @param string $filename Download filename.
	 * @return array<string,string>|WP_Error
	 */
	private static function validate_source( $source, $base_dir, $zip_root, $filename ) {
		$real_source = realpath( $source );
		$real_base   = realpath( $base_dir );

		if ( false === $real_source || false === $real_base || ! file_exists( $real_source ) ) {
			return new WP_Error( 'siteintelix_invalid_source', __( 'The requested package path is not valid.', 'siteintelix' ) );
		}

		$real_base_with_slash = trailingslashit( $real_base );

		if ( $real_source !== $real_base && 0 !== strpos( $real_source, $real_base_with_slash ) ) {
			return new WP_Error( 'siteintelix_invalid_source', __( 'The requested package path is not valid.', 'siteintelix' ) );
		}

		return array(
			'source'   => $real_source,
			'zip_root' => sanitize_file_name( $zip_root ),
			'filename' => $filename,
		);
	}

	/**
	 * Create a ZIP for a file or directory.
	 *
	 * @param string $source   Source path.
	 * @param string $zip_root Root name inside ZIP.
	 * @param string $filename Download filename.
	 * @return string|WP_Error
	 */
	private static function create_zip( $source, $zip_root, $filename ) {
		$temp_file = trailingslashit( get_temp_dir() ) . uniqid( 'siteintelix-', true ) . '-' . $filename;
		$zip       = new ZipArchive();

		if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'siteintelix_zip_failed', __( 'Could not create the ZIP file.', 'siteintelix' ) );
		}

		if ( is_file( $source ) ) {
			$zip->addFile( $source, $zip_root );
		} else {
			self::add_directory_to_zip( $zip, $source, $zip_root );
		}

		$zip->close();

		return $temp_file;
	}

	/**
	 * Add a directory recursively to a ZIP archive.
	 *
	 * @param ZipArchive $zip      ZIP archive.
	 * @param string     $source   Source directory.
	 * @param string     $zip_root Root name inside ZIP.
	 * @return void
	 */
	private static function add_directory_to_zip( $zip, $source, $zip_root ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( ! $file instanceof SplFileInfo ) {
				continue;
			}

			if ( $file->isLink() ) {
				continue;
			}

			$path = $file->getRealPath();
			if ( false === $path ) {
				continue;
			}

			$relative = $zip_root . '/' . ltrim( str_replace( $source, '', $path ), DIRECTORY_SEPARATOR );
			$relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative );
			} elseif ( $file->isFile() ) {
				$zip->addFile( $path, $relative );
			}
		}
	}

	/**
	 * Stream ZIP file to browser and remove temporary file.
	 *
	 * @param string $zip_file ZIP file path.
	 * @param string $filename Download filename.
	 * @return void
	 */
	private static function stream_zip( $zip_file, $filename ) {
		if ( ! is_readable( $zip_file ) ) {
			wp_die( esc_html__( 'The ZIP file could not be read.', 'siteintelix' ) );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		clearstatcache( true, $zip_file );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Content-Length: ' . filesize( $zip_file ) );
		header( 'X-Content-Type-Options: nosniff' );

		readfile( $zip_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $zip_file );
		exit;
	}
}
