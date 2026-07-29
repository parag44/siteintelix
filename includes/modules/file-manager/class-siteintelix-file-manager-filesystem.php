<?php
/**
 * File Manager read-only filesystem service.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides bounded directory listings, previews, details, and downloads.
 */
class SITEINTELIX_File_Manager_Filesystem {

	/** @var SITEINTELIX_File_Manager_Security */
	private $security;

	/**
	 * Constructor.
	 *
	 * @param SITEINTELIX_File_Manager_Security|null $security Security service.
	 */
	public function __construct( $security = null ) {
		$this->security = $security instanceof SITEINTELIX_File_Manager_Security ? $security : new SITEINTELIX_File_Manager_Security();
	}

	/**
	 * List one directory without recursion.
	 *
	 * @param string              $path Relative directory path.
	 * @param array<string,mixed> $args Listing arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_directory( $path, $args = array() ) {
		$directory = $this->security->authorize_path( $path, 'list' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $this->error( 'unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}

		$args     = is_array( $args ) ? $args : array();
		$page     = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$per_page = min( 200, max( 1, isset( $args['per_page'] ) ? (int) $args['per_page'] : 50 ) );
		$sort     = isset( $args['sort'] ) ? sanitize_key( $args['sort'] ) : 'name';
		$order    = isset( $args['order'] ) && 'desc' === strtolower( (string) $args['order'] ) ? 'desc' : 'asc';
		$search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$sort     = in_array( $sort, array( 'name', 'type', 'size', 'modified' ), true ) ? $sort : 'name';
		$settings = SITEINTELIX_File_Manager_Settings::get();
		$maximum  = max( 200, (int) apply_filters( 'siteintelix_file_manager_directory_scan_limit', 5000 ) );
		$items    = array();
		$scanned  = 0;

		try {
			$iterator = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );
			foreach ( $iterator as $item ) {
				if ( ++$scanned > $maximum ) {
					break;
				}
				$name = $item->getFilename();
				if ( empty( $settings['show_hidden'] ) && 0 === strpos( $name, '.' ) ) {
					continue;
				}
				if ( '' !== $search && false === stripos( $name, $search ) ) {
					continue;
				}
				$absolute = wp_normalize_path( $item->getPathname() );
				$relative = $this->security->relative_path( $absolute );
				if ( is_wp_error( $relative ) ) {
					continue;
				}
				$is_directory = $item->isDir() && ! $item->isLink();
				$items[]      = array(
					'name'        => $name,
					'path'        => $relative,
					'type'        => $is_directory ? 'directory' : 'file',
					'extension'   => $is_directory ? '' : strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
					'size'        => $is_directory ? null : max( 0, (int) $item->getSize() ),
					'size_label'  => $is_directory ? '—' : size_format( max( 0, (int) $item->getSize() ) ),
					'modified'    => (int) $item->getMTime(),
					'permissions' => $this->permissions( $absolute ),
					'readable'    => is_readable( $absolute ),
					'writable'    => is_writable( $absolute ) && ! $item->isLink() && ! is_wp_error( $this->security->authorize_path( $absolute, 'write' ) ),
					'actions'     => $this->actions( $absolute, $is_directory ),
				);
			}
		} catch ( UnexpectedValueException $exception ) {
			return $this->error( 'unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}

		usort(
			$items,
			static function ( $left, $right ) use ( $sort, $order ) {
				if ( $left['type'] !== $right['type'] ) {
					return 'directory' === $left['type'] ? -1 : 1;
				}
				$left_value  = $left[ $sort ];
				$right_value = $right[ $sort ];
				if ( 'name' === $sort || 'type' === $sort ) {
					$comparison = strnatcasecmp( (string) $left_value, (string) $right_value );
				} else {
					$comparison = (int) $left_value <=> (int) $right_value;
					if ( 0 === $comparison ) {
						$comparison = strnatcasecmp( $left['name'], $right['name'] );
					}
				}
				return 'desc' === $order ? -$comparison : $comparison;
			}
		);

		$total      = count( $items );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page       = min( $page, $total_pages );
		$items      = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );
		$relative   = $this->security->relative_path( $directory );

		return array(
			'path'        => is_wp_error( $relative ) ? '' : $relative,
			'breadcrumbs' => $this->breadcrumbs( $directory ),
			'items'       => array_values( $items ),
			'page'        => $page,
			'per_page'    => $per_page,
			'total'       => $total,
			'total_pages' => $total_pages,
			'truncated'   => $scanned > $maximum,
		);
	}

	/**
	 * Return a safe inline preview descriptor.
	 *
	 * @param string $path Relative file path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function preview( $path ) {
		$file = $this->security->authorize_path( $path, 'preview' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_file( $file ) || ! is_readable( $file ) || is_link( $file ) ) {
			return $this->error( 'unreadable_file', __( 'This file cannot be previewed.', 'siteintelix' ) );
		}
		$size      = max( 0, (int) filesize( $file ) );
		$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$maximum   = (int) apply_filters( 'siteintelix_file_manager_preview_max_bytes', SITEINTELIX_File_Manager_Settings::get()['preview_max_bytes'] );
		if ( $size > $maximum ) {
			return array( 'previewable' => false, 'reason' => 'too_large', 'size' => $size, 'mime' => $this->mime( $file ) );
		}
		if ( in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
			return array( 'previewable' => true, 'kind' => 'image', 'content' => '', 'size' => $size, 'mime' => $this->mime( $file ) );
		}
		$sample = file_get_contents( $file, false, null, 0, min( 8192, max( 1, $size ) ) );
		if ( is_string( $sample ) && false !== strpos( $sample, "\0" ) ) {
			return array( 'previewable' => false, 'reason' => 'binary', 'size' => $size, 'mime' => $this->mime( $file ) );
		}
		if ( ! in_array( $extension, $this->preview_extensions(), true ) ) {
			return array( 'previewable' => false, 'reason' => 'unsupported', 'size' => $size, 'mime' => $this->mime( $file ) );
		}

		$handle = fopen( $file, 'rb' );
		if ( false === $handle ) {
			return $this->error( 'unreadable_file', __( 'This file cannot be previewed.', 'siteintelix' ) );
		}
		$content = '';
		while ( ! feof( $handle ) && strlen( $content ) <= $maximum ) {
			$chunk = fread( $handle, min( 65536, $maximum + 1 - strlen( $content ) ) );
			if ( false === $chunk ) {
				fclose( $handle );
				return $this->error( 'unreadable_file', __( 'This file cannot be previewed.', 'siteintelix' ) );
			}
			$content .= $chunk;
		}
		fclose( $handle );
		if ( false !== strpos( $content, "\0" ) ) {
			return array( 'previewable' => false, 'reason' => 'binary', 'size' => $size, 'mime' => $this->mime( $file ) );
		}
		if ( 'wp-config.php' === basename( $file ) ) {
			$content = SITEINTELIX_File_Manager_Redactor::wp_config( $content );
		}
		return array(
			'previewable' => true,
			'kind'        => 'text',
			'content'     => $content,
			'size'        => $size,
			'mime'        => $this->mime( $file ),
			'extension'   => $extension,
		);
	}

	/**
	 * Return file details and optional hashes.
	 *
	 * @param string $path Relative path.
	 * @param bool   $include_hashes Whether to calculate hashes.
	 * @return array<string,mixed>|WP_Error
	 */
	public function details( $path, $include_hashes = false ) {
		$absolute = $this->security->authorize_path( $path, 'details' );
		if ( is_wp_error( $absolute ) ) {
			return $absolute;
		}
		$relative = $this->security->relative_path( $absolute );
		$is_file  = is_file( $absolute );
		$details  = array(
			'name'        => basename( $absolute ),
			'path'        => is_wp_error( $relative ) ? '' : $relative,
			'extension'   => $is_file ? strtolower( pathinfo( $absolute, PATHINFO_EXTENSION ) ) : '',
			'mime'        => $is_file ? $this->mime( $absolute ) : 'inode/directory',
			'type'        => $is_file ? 'file' : 'directory',
			'size'        => $is_file ? max( 0, (int) filesize( $absolute ) ) : null,
			'modified'    => (int) filemtime( $absolute ),
			'permissions' => $this->permissions( $absolute ),
			'readable'    => is_readable( $absolute ),
			'writable'    => is_writable( $absolute ) && ! is_wp_error( $this->security->authorize_path( $absolute, 'write' ) ),
		);
		if ( $include_hashes && $is_file ) {
			$maximum = (int) apply_filters( 'siteintelix_file_manager_hash_max_bytes', 50 * MB_IN_BYTES );
			if ( $details['size'] <= $maximum ) {
				$details['md5']    = hash_file( 'md5', $absolute );
				$details['sha256'] = hash_file( 'sha256', $absolute );
			}
		}
		return $details;
	}

	/**
	 * Stream an authorized file to the current response.
	 *
	 * @param string $path Relative path.
	 * @return true|WP_Error
	 */
	public function stream_file( $path ) {
		$file = $this->security->authorize_path( $path, 'download' );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_file( $file ) || ! is_readable( $file ) || is_link( $file ) ) {
			return $this->error( 'unreadable_file', __( 'This file cannot be downloaded.', 'siteintelix' ) );
		}
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		$name = str_replace( array( "\r", "\n", '"' ), '', basename( $file ) );
		header( 'Content-Type: ' . $this->mime( $file ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		$handle = fopen( $file, 'rb' );
		if ( false === $handle ) {
			return $this->error( 'unreadable_file', __( 'This file cannot be downloaded.', 'siteintelix' ) );
		}
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 65536 );
			if ( false === $chunk ) {
				fclose( $handle );
				return $this->error( 'download_failed', __( 'The file download could not be completed.', 'siteintelix' ) );
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authenticated binary download stream.
			flush();
		}
		fclose( $handle );
		return true;
	}

	/**
	 * Create an authorized directory.
	 *
	 * @param string $parent Relative parent.
	 * @param string $name Directory name.
	 * @return array<string,string>|WP_Error
	 */
	public function create_directory( $parent, $name ) {
		$destination = $this->security->resolve_destination( $parent, $name, 'create' );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		if ( ! wp_mkdir_p( $destination ) || ! is_dir( $destination ) ) {
			return $this->error( 'create_failed', __( 'The folder could not be created.', 'siteintelix' ) );
		}
		$relative = $this->security->relative_path( $destination );
		do_action( 'siteintelix_file_manager_after_operation', 'create_directory', is_wp_error( $relative ) ? '' : $relative, 'success' );
		return array( 'path' => is_wp_error( $relative ) ? '' : $relative );
	}

	/**
	 * Create an authorized non-PHP text file.
	 *
	 * @param string $parent Relative parent.
	 * @param string $name Filename.
	 * @param string $content Initial content.
	 * @return array<string,string>|WP_Error
	 */
	public function create_file( $parent, $name, $content = '' ) {
		$extension = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
		$settings  = SITEINTELIX_File_Manager_Settings::get();
		if ( 'php' === $extension || ! in_array( $extension, (array) $settings['editable_extensions'], true ) ) {
			return $this->error( 'invalid_extension', __( 'This file type cannot be created in Safe Mode.', 'siteintelix' ) );
		}
		if ( strlen( $content ) > (int) $settings['edit_max_bytes'] || false !== strpos( $content, "\0" ) ) {
			return $this->error( 'file_too_large', __( 'The new file content exceeds the permitted size.', 'siteintelix' ) );
		}
		$destination = $this->security->resolve_destination( $parent, $name, 'create' );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		$handle = fopen( $destination, 'x+b' );
		if ( false === $handle ) {
			return $this->error( 'create_failed', __( 'The file could not be created.', 'siteintelix' ) );
		}
		$length  = strlen( $content );
		$written = 0;
		while ( $written < $length ) {
			$chunk = fwrite( $handle, substr( $content, $written ) );
			if ( false === $chunk || 0 === $chunk ) {
				fclose( $handle );
				unlink( $destination );
				return $this->error( 'create_failed', __( 'The file could not be created.', 'siteintelix' ) );
			}
			$written += $chunk;
		}
		fflush( $handle );
		fclose( $handle );
		chmod( $destination, 0644 );
		$relative = $this->security->relative_path( $destination );
		do_action( 'siteintelix_file_manager_after_operation', 'create_file', is_wp_error( $relative ) ? '' : $relative, 'success' );
		return array( 'path' => is_wp_error( $relative ) ? '' : $relative );
	}

	/**
	 * Rename an item inside its current parent.
	 *
	 * @param string $path Relative source.
	 * @param string $new_name New basename.
	 * @return array<string,string>|WP_Error
	 */
	public function rename_item( $path, $new_name ) {
		$source = $this->security->authorize_path( $path, 'rename' );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$old_extension = is_file( $source ) ? strtolower( pathinfo( $source, PATHINFO_EXTENSION ) ) : '';
		$new_extension = is_file( $source ) ? strtolower( pathinfo( (string) $new_name, PATHINFO_EXTENSION ) ) : '';
		if ( $old_extension !== $new_extension ) {
			return $this->error( 'extension_change_blocked', __( 'File extensions cannot be changed during rename in Safe Mode.', 'siteintelix' ) );
		}
		$parent_relative = $this->security->relative_path( dirname( $source ) );
		if ( is_wp_error( $parent_relative ) ) {
			return $parent_relative;
		}
		$destination = $this->security->resolve_destination( $parent_relative, $new_name, 'rename' );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		if ( ! rename( $source, $destination ) ) {
			return $this->error( 'rename_failed', __( 'The file or directory could not be renamed.', 'siteintelix' ) );
		}
		$relative = $this->security->relative_path( $destination );
		do_action( 'siteintelix_file_manager_after_operation', 'rename', is_wp_error( $relative ) ? '' : $relative, 'success' );
		return array( 'path' => is_wp_error( $relative ) ? '' : $relative );
	}

	/**
	 * Build root-bounded breadcrumbs.
	 *
	 * @param string $directory Canonical directory.
	 * @return array<int,array<string,string>>
	 */
	private function breadcrumbs( $directory ) {
		$relative = $this->security->relative_path( $directory );
		$crumbs   = array( array( 'label' => __( 'WordPress', 'siteintelix' ), 'path' => '' ) );
		if ( is_wp_error( $relative ) || '' === $relative ) {
			return $crumbs;
		}
		$current = array();
		foreach ( explode( '/', $relative ) as $segment ) {
			$current[] = $segment;
			$crumbs[]  = array( 'label' => $segment, 'path' => implode( '/', $current ) );
		}
		return $crumbs;
	}

	/**
	 * Return allowed row actions.
	 *
	 * @param string $absolute Absolute path.
	 * @param bool   $directory Whether directory.
	 * @return string[]
	 */
	private function actions( $absolute, $directory ) {
		$actions = $directory ? array( 'open', 'details' ) : array( 'view', 'details', 'download' );
		if ( ! is_wp_error( $this->security->authorize_path( $absolute, 'write' ) ) ) {
			$actions[] = 'rename';
			$actions[] = 'trash';
		}
		if ( ! $directory && in_array( strtolower( pathinfo( $absolute, PATHINFO_EXTENSION ) ), SITEINTELIX_File_Manager_Settings::get()['editable_extensions'], true ) && ! is_wp_error( $this->security->authorize_path( $absolute, 'edit' ) ) ) {
			$actions[] = 'edit';
		}
		return $actions;
	}

	/**
	 * Format Unix-like permissions where available.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function permissions( $path ) {
		$permissions = fileperms( $path );
		return false === $permissions ? '—' : substr( sprintf( '%o', $permissions ), -4 );
	}

	/**
	 * Determine MIME without trusting a browser.
	 *
	 * @param string $file File.
	 * @return string
	 */
	private function mime( $file ) {
		if ( function_exists( 'wp_check_filetype' ) ) {
			$checked = wp_check_filetype( basename( $file ) );
			if ( ! empty( $checked['type'] ) ) {
				return $checked['type'];
			}
		}
		if ( class_exists( 'finfo' ) ) {
			$finfo = new finfo( FILEINFO_MIME_TYPE );
			$mime  = $finfo->file( $file );
			if ( is_string( $mime ) && '' !== $mime ) {
				return $mime;
			}
		}
		return 'application/octet-stream';
	}

	/**
	 * Return text preview extensions.
	 *
	 * @return string[]
	 */
	private function preview_extensions() {
		return array( 'txt', 'log', 'md', 'php', 'css', 'js', 'json', 'html', 'htm', 'xml', 'yml', 'yaml', 'ini', 'conf', 'csv', 'svg' );
	}

	/**
	 * Create an error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
