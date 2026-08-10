<?php
/**
 * File Manager lazy folder-tree service.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns bounded immediate child directories for one authorized branch.
 */
class SITEINTELIX_File_Manager_Tree {

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
	 * Return one level of visible child directories.
	 *
	 * @param string $path Relative directory path.
	 * @return array<string,mixed>|WP_Error
	 */
	public function children( $path ) {
		$directory = $this->security->authorize_path( $path, 'tree' );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return $this->error( 'unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}

		$settings = SITEINTELIX_File_Manager_Settings::get();
		$limit    = max( 200, (int) apply_filters( 'siteintelix_file_manager_directory_scan_limit', 5000 ) );
		$children = array();
		$scanned  = 0;

		try {
			foreach ( new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS ) as $item ) {
				if ( ++$scanned > $limit ) {
					break;
				}
				$name = $item->getFilename();
				if (
					$item->isLink()
					|| ! $item->isDir()
					|| ( empty( $settings['show_hidden'] ) && 0 === strpos( $name, '.' ) )
				) {
					continue;
				}
				$relative = $this->security->relative_path( $item->getPathname() );
				if ( is_wp_error( $relative ) || is_wp_error( $this->security->authorize_path( $item->getPathname(), 'tree' ) ) ) {
					continue;
				}
				$children[] = array(
					'name'         => $name,
					'path'         => $relative,
					'has_children' => $this->has_children( $item->getPathname(), $settings, $limit ),
					'read_only'    => is_wp_error( $this->security->authorize_path( $item->getPathname(), 'write' ) ),
					'level'        => substr_count( $relative, '/' ) + 1,
				);
			}
		} catch ( UnexpectedValueException $exception ) {
			return $this->error( 'unreadable_directory', __( 'This directory cannot be read.', 'siteintelix' ) );
		}

		usort(
			$children,
			static function ( $left, $right ) {
				return strnatcasecmp( $left['name'], $right['name'] );
			}
		);
		$relative_directory = $this->security->relative_path( $directory );

		return array(
			'path'      => is_wp_error( $relative_directory ) ? '' : $relative_directory,
			'children'  => $children,
			'truncated' => $scanned > $limit,
		);
	}

	/**
	 * Determine whether a directory exposes at least one visible child branch.
	 *
	 * @param string              $directory Directory path.
	 * @param array<string,mixed> $settings Settings.
	 * @param int                 $limit Scan limit.
	 * @return bool
	 */
	private function has_children( $directory, $settings, $limit ) {
		$scanned = 0;
		try {
			foreach ( new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS ) as $item ) {
				if ( ++$scanned > $limit ) {
					return false;
				}
				$name = $item->getFilename();
				if (
					! $item->isLink()
					&& $item->isDir()
					&& ! is_wp_error( $this->security->authorize_path( $item->getPathname(), 'tree' ) )
					&& ( ! empty( $settings['show_hidden'] ) || 0 !== strpos( $name, '.' ) )
				) {
					return true;
				}
			}
		} catch ( UnexpectedValueException $exception ) {
			return false;
		}
		return false;
	}

	/**
	 * Create a service error.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function error( $code, $message ) {
		return new WP_Error( 'siteintelix_file_manager_' . $code, $message );
	}
}
