<?php
/**
 * File Manager authorization and path policy.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonicalizes and authorizes every File Manager path.
 */
class SITEINTELIX_File_Manager_Security {

	/**
	 * Return the effective capability.
	 *
	 * @return string
	 */
	public static function capability() {
		$default = is_multisite() ? 'manage_network_options' : 'manage_options';
		return sanitize_key( apply_filters( 'siteintelix_file_manager_capability', $default ) );
	}

	/**
	 * Determine whether the current user can manage files.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage() {
		return current_user_can( self::capability() );
	}

	/**
	 * Return whether a path is allowed.
	 *
	 * @param string $path Path.
	 * @param string $operation Operation.
	 * @return bool
	 */
	public function is_path_allowed( $path, $operation ) {
		return ! is_wp_error( $this->authorize_path( $path, $operation ) );
	}

	/**
	 * Resolve and authorize an existing path.
	 *
	 * @param string $path Path received from a request.
	 * @param string $operation Operation name.
	 * @param bool   $must_exist Whether the target must exist.
	 * @return string|WP_Error Canonical absolute path or error.
	 */
	public function authorize_path( $path, $operation, $must_exist = true ) {
		$operation = sanitize_key( $operation );
		if ( is_string( $path ) && '' === $path ) {
			if ( ! in_array( $operation, array( 'list', 'tree', 'details' ), true ) ) {
				return $this->error( 'invalid_path', __( 'The requested path is invalid.', 'siteintelix' ) );
			}
			$decoded = '';
		} else {
			$decoded = $this->validate_input( $path );
			if ( is_wp_error( $decoded ) ) {
				return $decoded;
			}
		}

		$root      = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );
		$candidate = '/' === substr( $decoded, 0, 1 ) ? $decoded : trailingslashit( $root ) . ltrim( $decoded, '/' );
		$candidate = wp_normalize_path( $candidate );

		if ( '/' !== substr( $decoded, 0, 1 ) && ! $this->contains( $root, $candidate ) ) {
			return $this->error( 'outside_root', __( 'The requested path is outside the permitted WordPress directories.', 'siteintelix' ) );
		}

		if ( $this->contains_symlink( $candidate ) && ! apply_filters( 'siteintelix_file_manager_allow_symlinks', false, $candidate ) ) {
			return $this->error( 'symlink_blocked', __( 'Symbolic links are not available in Safe Mode.', 'siteintelix' ) );
		}

		$canonical = realpath( $candidate );
		if ( false === $canonical ) {
			if ( $must_exist ) {
				return $this->error( 'missing_path', __( 'The requested file or directory does not exist.', 'siteintelix' ) );
			}
			$canonical = $candidate;
		}
		$canonical = wp_normalize_path( $canonical );

		if ( ! $this->contains( $root, $canonical ) ) {
			return $this->error( 'outside_root', __( 'The requested path is outside the permitted WordPress directories.', 'siteintelix' ) );
		}

		$is_write = $this->is_write_operation( $operation );
		if ( ! $this->inside_allowed_root( $canonical, $is_write ) ) {
			return $this->error( 'outside_allowed_roots', __( 'The requested path is outside the permitted WordPress directories.', 'siteintelix' ) );
		}

		if ( $this->operation_blocked_by_constants( $operation ) ) {
			return $this->error( 'file_modifications_disabled', __( 'WordPress configuration disables this file operation.', 'siteintelix' ) );
		}

		if ( $this->is_protected( $canonical, $operation ) ) {
			return $this->error( 'protected_path', __( 'This file is protected and cannot be modified.', 'siteintelix' ) );
		}

		return $canonical;
	}

	/**
	 * Resolve a new basename under an authorized existing parent.
	 *
	 * @param string $parent Parent path.
	 * @param string $name Basename.
	 * @param string $operation Operation.
	 * @return string|WP_Error
	 */
	public function resolve_destination( $parent, $name, $operation ) {
		$name = (string) $name;
		if (
			'' === $name
			|| false !== strpos( $name, "\0" )
			|| preg_match( '#[\\\\/]#', $name )
			|| in_array( $name, array( '.', '..' ), true )
			|| $name !== basename( $name )
		) {
			return $this->error( 'invalid_name', __( 'This filename is not permitted.', 'siteintelix' ) );
		}

		$parent_path = $this->authorize_path( $parent, $operation );
		if ( is_wp_error( $parent_path ) ) {
			return $parent_path;
		}
		if ( ! is_dir( $parent_path ) ) {
			return $this->error( 'invalid_parent', __( 'The destination directory does not exist.', 'siteintelix' ) );
		}

		$destination = trailingslashit( $parent_path ) . $name;
		if ( file_exists( $destination ) || is_link( $destination ) ) {
			return $this->error( 'destination_exists', __( 'A file or directory with this name already exists.', 'siteintelix' ) );
		}

		return $this->authorize_path( $destination, $operation, false );
	}

	/**
	 * Return canonical allowed roots.
	 *
	 * @return string[]
	 */
	public function allowed_roots() {
		$settings = SITEINTELIX_File_Manager_Settings::get();
		$enabled  = isset( $settings['allowed_locations'] ) && is_array( $settings['allowed_locations'] ) ? $settings['allowed_locations'] : array();
		$uploads  = wp_upload_dir();
		$map      = array(
			'wp_content' => WP_CONTENT_DIR,
			'plugins'    => WP_PLUGIN_DIR,
			'themes'     => get_theme_root(),
			'uploads'    => isset( $uploads['basedir'] ) ? $uploads['basedir'] : '',
			'mu_plugins' => WPMU_PLUGIN_DIR,
			'languages'  => WP_CONTENT_DIR . '/languages',
		);
		$roots    = array();

		foreach ( $map as $key => $path ) {
			if ( empty( $enabled[ $key ] ) || ! $path ) {
				continue;
			}
			$real = realpath( $path );
			if ( false !== $real ) {
				$roots[] = wp_normalize_path( $real );
			}
		}

		foreach ( (array) $settings['custom_roots'] as $relative ) {
			$real = realpath( trailingslashit( ABSPATH ) . ltrim( wp_normalize_path( $relative ), '/' ) );
			if ( false !== $real ) {
				$roots[] = wp_normalize_path( $real );
			}
		}

		$roots       = array_values( array_unique( $roots ) );
		$filtered    = (array) apply_filters( 'siteintelix_file_manager_allowed_roots', $roots );
		$site_root   = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );
		$safe_roots  = array();
		foreach ( $filtered as $filtered_root ) {
			$real = realpath( $filtered_root );
			$real = false !== $real ? wp_normalize_path( $real ) : '';
			if ( $real && $this->contains( $site_root, $real ) ) {
				$safe_roots[] = $real;
			}
		}
		return array_values( array_unique( $safe_roots ) );
	}

	/**
	 * Return protected canonical paths.
	 *
	 * @return string[]
	 */
	public function protected_paths() {
		$paths = array(
			ABSPATH . 'wp-admin',
			ABSPATH . 'wp-includes',
			ABSPATH . 'wp-config.php',
			ABSPATH . '.htaccess',
			ABSPATH . '.user.ini',
			ABSPATH . 'php.ini',
			ABSPATH . 'index.php',
			SITEINTELIX_PLUGIN_DIR,
			get_stylesheet_directory(),
			WPMU_PLUGIN_DIR,
		);
		if ( function_exists( 'get_template_directory' ) ) {
			$paths[] = get_template_directory();
		}
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() && function_exists( 'get_site_option' ) ) {
			$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = ltrim( wp_normalize_path( (string) $plugin_file ), '/' );
			if ( '' === $plugin_file || preg_match( '#(^|/)\.\.(/|$)#', $plugin_file ) ) {
				continue;
			}
			$plugin_directory = dirname( $plugin_file );
			$paths[] = '.' === $plugin_directory ? WP_PLUGIN_DIR . '/' . $plugin_file : WP_PLUGIN_DIR . '/' . $plugin_directory;
		}

		$normalized = array();
		foreach ( $paths as $path ) {
			$real         = realpath( $path );
			$normalized[] = untrailingslashit( wp_normalize_path( false !== $real ? $real : $path ) );
		}

		$immutable = array_values( array_unique( $normalized ) );
		$filtered  = (array) apply_filters( 'siteintelix_file_manager_protected_paths', $immutable );
		return array_values( array_unique( array_merge( $immutable, array_map( 'wp_normalize_path', $filtered ) ) ) );
	}

	/**
	 * Convert an authorized absolute path to an ABSPATH-relative path.
	 *
	 * @param string $absolute Absolute path.
	 * @return string|WP_Error
	 */
	public function relative_path( $absolute ) {
		$absolute = wp_normalize_path( $absolute );
		$root     = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );
		if ( ! $this->contains( $root, $absolute ) ) {
			return $this->error( 'outside_root', __( 'The requested path is outside the permitted WordPress directories.', 'siteintelix' ) );
		}
		return ltrim( substr( $absolute, strlen( $root ) ), '/' );
	}

	/**
	 * Return whether a path is contained by a root using a segment boundary.
	 *
	 * @param string $root Root path.
	 * @param string $path Candidate path.
	 * @return bool
	 */
	public function contains_path( $root, $path ) {
		return $this->contains( $root, $path );
	}

	/**
	 * Authorize one source for ZIP archive inclusion.
	 *
	 * @param string $path Relative or canonical absolute path.
	 * @return string|WP_Error
	 */
	public function archive_source( $path ) {
		$absolute = $this->authorize_path( $path, 'archive' );
		if ( is_wp_error( $absolute ) ) {
			return $absolute;
		}
		$basename = strtolower( basename( $absolute ) );
		if ( in_array( $basename, array( 'wp-config.php', '.htpasswd', '.user.ini', 'php.ini', 'web.config' ), true ) ) {
			return $this->error( 'archive_source_protected', __( 'A protected item cannot be added to an archive.', 'siteintelix' ) );
		}
		$private_path = SITEINTELIX_File_Manager_Storage::path();
		$private      = realpath( $private_path );
		$private      = wp_normalize_path( false === $private ? $private_path : $private );
		if ( $this->contains( $private, $absolute ) ) {
			return $this->error( 'archive_source_protected', __( 'Private File Manager storage cannot be archived.', 'siteintelix' ) );
		}
		return $absolute;
	}

	/**
	 * Validate and decode raw path input.
	 *
	 * @param mixed $path Path.
	 * @return string|WP_Error
	 */
	private function validate_input( $path ) {
		if ( ! is_string( $path ) || '' === trim( $path ) || false !== strpos( $path, "\0" ) || preg_match( '/[\x00-\x1F\x7F]/', $path ) ) {
			return $this->error( 'invalid_path', __( 'The requested path is invalid.', 'siteintelix' ) );
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) || preg_match( '/^[a-z]:[\\\\\/]/i', $path ) ) {
			return $this->error( 'invalid_path', __( 'The requested path is invalid.', 'siteintelix' ) );
		}

		$decoded = $path;
		for ( $pass = 0; $pass < 2; $pass++ ) {
			$decoded = rawurldecode( $decoded );
			$check   = wp_normalize_path( $decoded );
			if ( false !== strpos( $check, "\0" ) || preg_match( '#(^|/)\.\.(/|$)#', $check ) ) {
				return $this->error( 'path_traversal', __( 'The requested path is outside the permitted WordPress directories.', 'siteintelix' ) );
			}
		}

		$decoded = wp_normalize_path( trim( $decoded ) );
		if ( preg_match( '#(^|/)\.(/|$)#', $decoded ) || 0 === strpos( $decoded, '//' ) ) {
			return $this->error( 'invalid_path', __( 'The requested path is invalid.', 'siteintelix' ) );
		}
		return $decoded;
	}

	/**
	 * Determine whether a path is within a root.
	 *
	 * @param string $root Root.
	 * @param string $path Path.
	 * @return bool
	 */
	private function contains( $root, $path ) {
		$root = untrailingslashit( wp_normalize_path( $root ) );
		$path = wp_normalize_path( $path );
		return $path === $root || 0 === strpos( $path, trailingslashit( $root ) );
	}

	/**
	 * Detect a symlink in the requested path before canonicalization.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private function contains_symlink( $path ) {
		$canonical_root = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );
		$raw_root       = untrailingslashit( wp_normalize_path( ABSPATH ) );
		$path           = wp_normalize_path( $path );
		$root           = $this->contains( $raw_root, $path ) ? $raw_root : $canonical_root;
		if ( ! $this->contains( $root, $path ) ) {
			return false;
		}
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );
		$current  = $root;
		foreach ( array_filter( explode( '/', $relative ), 'strlen' ) as $segment ) {
			$current .= '/' . $segment;
			if ( is_link( $current ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check enabled roots.
	 *
	 * @param string $path Canonical path.
	 * @param bool   $write Whether this is a write.
	 * @return bool
	 */
	private function inside_allowed_root( $path, $write ) {
		$root = untrailingslashit( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ) );
		if ( ! $write && $this->contains( $root, $path ) ) {
			return true;
		}
		foreach ( $this->allowed_roots() as $allowed ) {
			if ( $this->contains( $allowed, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine whether an operation changes state.
	 *
	 * @param string $operation Operation.
	 * @return bool
	 */
	private function is_write_operation( $operation ) {
		return in_array( $operation, array( 'write', 'edit', 'save', 'create', 'upload', 'rename', 'trash', 'restore', 'permanent_delete', 'settings' ), true );
	}

	/**
	 * Respect WordPress file-modification constants.
	 *
	 * @param string $operation Operation.
	 * @return bool
	 */
	private function operation_blocked_by_constants( $operation ) {
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS && $this->is_write_operation( $operation ) ) {
			return true;
		}
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT && in_array( $operation, array( 'edit', 'save' ), true );
	}

	/**
	 * Apply protected-path policy.
	 *
	 * @param string $path Canonical path.
	 * @param string $operation Operation.
	 * @return bool
	 */
	private function is_protected( $path, $operation ) {
		$wp_config_path = ABSPATH . 'wp-config.php';
		$wp_config      = untrailingslashit( wp_normalize_path( realpath( $wp_config_path ) ?: $wp_config_path ) );
		if ( $path === $wp_config ) {
			return ! ( 'preview' === $operation && apply_filters( 'siteintelix_file_manager_allow_wp_config_preview', false ) );
		}
		if ( $this->is_write_operation( $operation ) && in_array( strtolower( basename( $path ) ), array( '.htaccess', '.htpasswd', '.user.ini', 'php.ini', 'web.config' ), true ) ) {
			return true;
		}
		if ( $this->is_write_operation( $operation ) && 'php' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return true;
		}
		if ( in_array( $operation, array( 'rename', 'trash' ), true ) && is_dir( $path ) && $this->directory_contains_php( $path ) ) {
			return true;
		}
		if ( ! $this->is_write_operation( $operation ) ) {
			return false;
		}
		foreach ( $this->protected_paths() as $protected ) {
			if ( $this->contains( $protected, $path ) || ( in_array( $operation, array( 'rename', 'trash' ), true ) && is_dir( $path ) && $this->contains( $path, $protected ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fail closed when a renamed or trashed directory contains PHP.
	 *
	 * @param string $directory Directory.
	 * @return bool
	 */
	private function directory_contains_php( $directory ) {
		$scanned = 0;
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $item ) {
				if ( ++$scanned > 5000 || is_link( $item->getPathname() ) ) {
					return true;
				}
				if ( $item->isFile() && 'php' === strtolower( $item->getExtension() ) ) {
					return true;
				}
			}
		} catch ( UnexpectedValueException $exception ) {
			return true;
		}
		return false;
	}

	/**
	 * Create a stable public error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
