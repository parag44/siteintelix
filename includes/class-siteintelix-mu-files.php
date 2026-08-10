<?php
/**
 * Ownership-aware cleanup for SiteIntelix MU bootstrap files.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Some activation/update flows may load lifecycle dependencies independently.
if ( class_exists( 'SITEINTELIX_MU_Files', false ) ) {
	return;
}

/**
 * Removes only verified SiteIntelix MU bootstrap files.
 */
class SITEINTELIX_MU_Files {

	const TYPE_DEBUG        = 'debug';
	const TYPE_LEGACY_DEBUG = 'legacy_debug';
	const TYPE_SAFE_MODE    = 'safe_mode';
	const TYPE_SAFETY_GUARD = 'safety_guard';

	/**
	 * Remove verified MU bootstrap files for one SiteIntelix feature.
	 *
	 * @param string $type Bootstrap type.
	 * @return array{removed:array<int,string>,preserved:array<int,string>,failed:array<int,string>}
	 */
	public static function remove_type( $type ) {
		$definitions = self::get_definitions();
		$result      = self::empty_result();

		if ( ! isset( $definitions[ $type ] ) ) {
			return $result;
		}

		foreach ( self::get_directories() as $directory ) {
			foreach ( $definitions[ $type ]['filenames'] as $filename ) {
				$path   = trailingslashit( $directory ) . $filename;
				$result = self::remove_candidate( $path, $definitions[ $type ], $result );
			}
		}

		return $result;
	}

	/**
	 * Remove every verified SiteIntelix MU bootstrap.
	 *
	 * @return array{removed:array<int,string>,preserved:array<int,string>,failed:array<int,string>}
	 */
	public static function remove_all() {
		$result = self::empty_result();

		foreach ( array_keys( self::get_definitions() ) as $type ) {
			$type_result = self::remove_type( $type );
			foreach ( array_keys( $result ) as $status ) {
				$result[ $status ] = array_merge( $result[ $status ], $type_result[ $status ] );
			}
		}

		return $result;
	}

	/**
	 * Get the fixed SiteIntelix MU bootstrap allowlist.
	 *
	 * @return array<string,array{filenames:array<int,string>,signatures:array<int,array<int,string>>}>
	 */
	private static function get_definitions() {
		return array(
			self::TYPE_DEBUG => array(
				'filenames'  => array( 'siteintelix-debug-capture.php' ),
				'signatures' => array(
					array( 'SiteIntelix Debug Capture', 'siteintelix_debug_capture_enabled' ),
					array( 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_debug_capture_enabled' ),
				),
			),
			self::TYPE_LEGACY_DEBUG => array(
				'filenames'  => array(
					'my-debug-capture.php',
					'siteintelix-debug.php',
				),
				'signatures' => array(
					array( 'SiteIntelix Debug Capture', 'SITEINTELIX_ENABLE_DEBUG_CAPTURE_OPTION', 'siteintelix_debug_capture_enabled' ),
				),
			),
			self::TYPE_SAFE_MODE => array(
				'filenames'  => array( 'siteintelix-safe-mode.php' ),
				'signatures' => array(
					array( 'SiteIntelix Safe Mode', 'siteintelix_safe_mode_hash_token' ),
				),
			),
			self::TYPE_SAFETY_GUARD => array(
				'filenames'  => array( 'siteintelix-plugin-safety-guard.php' ),
				'signatures' => array(
					array( 'SiteIntelix Plugin Safety Guard', 'siteintelix_psg_filter_active_plugins' ),
				),
			),
		);
	}

	/**
	 * Resolve the active and standard legacy MU directories.
	 *
	 * @return array<int,string>
	 */
	private static function get_directories() {
		$directories = array(
			WPMU_PLUGIN_DIR,
			trailingslashit( WP_CONTENT_DIR ) . 'mu-plugins',
		);
		$unique      = array();

		foreach ( $directories as $directory ) {
			$normalized = rtrim( wp_normalize_path( $directory ), '/' );
			if ( '' !== $normalized ) {
				$unique[ $normalized ] = $normalized;
			}
		}

		return array_values( $unique );
	}

	/**
	 * Return an empty cleanup result.
	 *
	 * @return array{removed:array<int,string>,preserved:array<int,string>,failed:array<int,string>}
	 */
	private static function empty_result() {
		return array(
			'removed'   => array(),
			'preserved' => array(),
			'failed'    => array(),
		);
	}

	/**
	 * Inspect and, when ownership is proven, remove one candidate.
	 *
	 * @param string                                                               $path       Exact candidate path.
	 * @param array{filenames:array<int,string>,signatures:array<int,array<int,string>>} $definition Ownership definition.
	 * @param array{removed:array<int,string>,preserved:array<int,string>,failed:array<int,string>} $result Current result.
	 * @return array{removed:array<int,string>,preserved:array<int,string>,failed:array<int,string>}
	 */
	private static function remove_candidate( $path, $definition, $result ) {
		$path = wp_normalize_path( $path );

		if ( ! file_exists( $path ) && ! is_link( $path ) ) {
			return $result;
		}

		if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			$result['preserved'][] = $path;
			return $result;
		}

		$contents = file_get_contents( $path );
		if ( false === $contents || ! self::matches_signature( $contents, $definition['signatures'] ) ) {
			$result['preserved'][] = $path;
			return $result;
		}

		wp_delete_file( $path );

		if ( file_exists( $path ) || is_link( $path ) ) {
			$result['failed'][] = $path;
		} else {
			$result['removed'][] = $path;
		}

		return $result;
	}

	/**
	 * Determine whether file contents match one complete ownership signature.
	 *
	 * @param string                       $contents   File contents.
	 * @param array<int,array<int,string>> $signatures Accepted signature sets.
	 * @return bool
	 */
	private static function matches_signature( $contents, $signatures ) {
		foreach ( $signatures as $signature ) {
			$matches = true;

			foreach ( $signature as $marker ) {
				if ( false === strpos( $contents, $marker ) ) {
					$matches = false;
					break;
				}
			}

			if ( $matches ) {
				return true;
			}
		}

		return false;
	}
}
