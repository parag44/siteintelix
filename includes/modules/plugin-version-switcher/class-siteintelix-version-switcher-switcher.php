<?php
/**
 * Native upgrader and recovery orchestration for Plugin Version Switcher.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

/** Replaces one installed plugin build while preserving activation state. */
final class SITEINTELIX_Version_Switcher_Switcher {

	/**
	 * Switch to a manifest package.
	 *
	 * @param string $package_id Opaque package ID.
	 * @param string $return_url Validated return URL for hook context only.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function switch_to( $package_id, $return_url ) {
		$package = SITEINTELIX_Version_Switcher_Storage::package( $package_id );
		if ( is_wp_error( $package ) ) {
			return $package;
		}
		$context = array(
			'action'           => 'switch',
			'plugin_name'      => $package['plugin_name'],
			'plugin_slug'      => $package['plugin_slug'],
			'previous_version' => '',
			'target_version'   => $package['version'],
			'user_id'          => get_current_user_id(),
		);

		if ( self::is_self_package( $package ) ) {
			return self::record_error( new WP_Error( 'siteintelix_version_self_switch', __( 'SiteIntelix cannot switch its own version while the Version Switcher module is running.', 'siteintelix' ) ), $context );
		}

		$warnings = SITEINTELIX_Version_Switcher_Package::compatibility_warnings( $package );
		if ( ! empty( $warnings ) ) {
			return self::record_error( new WP_Error( 'siteintelix_version_incompatible', implode( ' ', $warnings ) ), $context );
		}

		$path = SITEINTELIX_Version_Switcher_Storage::package_path( $package, true );
		if ( is_wp_error( $path ) ) {
			return self::record_error( $path, $context );
		}
		$checksum = hash_file( 'sha256', $path );
		if ( ! is_string( $checksum ) || ! hash_equals( (string) $package['checksum'], $checksum ) ) {
			return self::record_error( new WP_Error( 'siteintelix_version_checksum_mismatch', __( 'The stored build failed checksum verification and was not installed.', 'siteintelix' ) ), $context );
		}

		$inspection = SITEINTELIX_Version_Switcher_Package::inspect( $path );
		if ( is_wp_error( $inspection ) ) {
			return self::record_error( $inspection, $context );
		}
		foreach ( array( 'plugin_slug', 'plugin_directory', 'primary_plugin_file', 'version' ) as $identity_key ) {
			if ( ! isset( $package[ $identity_key ], $inspection[ $identity_key ] ) || $package[ $identity_key ] !== $inspection[ $identity_key ] ) {
				return self::record_error( new WP_Error( 'siteintelix_version_identity_mismatch', __( 'The stored build identity no longer matches its manifest.', 'siteintelix' ) ), $context );
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		$primary = (string) $package['primary_plugin_file'];
		if ( ! isset( $plugins[ $primary ] ) ) {
			return self::record_error( new WP_Error( 'siteintelix_version_plugin_not_installed', __( 'The matching plugin must already be installed before switching versions.', 'siteintelix' ) ), $context );
		}
		$context['previous_version'] = isset( $plugins[ $primary ]['Version'] ) ? (string) $plugins[ $primary ]['Version'] : '';

		$lock = SITEINTELIX_Version_Switcher_Storage::acquire_lock();
		if ( is_wp_error( $lock ) ) {
			return self::record_error( $lock, $context );
		}

		$activation = self::activation_state( $primary );
		$source     = wp_normalize_path( trailingslashit( WP_PLUGIN_DIR ) . $package['plugin_directory'] );
		if ( ! self::valid_live_directory( $source, $package['plugin_directory'] ) || self::contains_symlink( $source ) ) {
			SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );
			return self::record_error( new WP_Error( 'siteintelix_version_unsafe_live_plugin', __( 'The installed plugin directory is not safe to replace.', 'siteintelix' ) ), $context );
		}

		$filesystem = self::filesystem();
		if ( is_wp_error( $filesystem ) ) {
			SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );
			return self::record_error( $filesystem, $context );
		}
		$backup = self::create_backup( $source, $package['plugin_slug'], $filesystem );
		if ( is_wp_error( $backup ) ) {
			SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );
			return self::record_error( $backup, $context );
		}

		$hook_context = array(
			'package_id'        => $package_id,
			'plugin'            => $primary,
			'previous_version'  => $context['previous_version'],
			'target_version'    => $package['version'],
			'active'            => $activation['site_active'],
			'network_active'    => $activation['network_active'],
			'user_id'           => get_current_user_id(),
			'time'              => time(),
			'return_url'        => $return_url,
		);
		do_action( 'siteintelix_before_plugin_version_switch', $hook_context );

		$temp_package = wp_tempnam( 'siteintelix-version-switch.zip' );
		if ( ! $temp_package || ! copy( $path, $temp_package ) ) {
			$error = new WP_Error( 'siteintelix_version_temp_package', __( 'A temporary installer package could not be prepared.', 'siteintelix' ) );
			return self::recover_failure( $error, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, '' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$skin     = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result   = $upgrader->install(
			$temp_package,
			array(
				'clear_update_cache' => false,
				'overwrite_package'  => true,
			)
		);

		if ( is_file( $temp_package ) && ! is_link( $temp_package ) ) {
			unlink( $temp_package );
		}
		if ( is_wp_error( $result ) ) {
			return self::recover_failure( $result, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, '' );
		}
		if ( ! $result ) {
			$error = new WP_Error( 'siteintelix_version_upgrade_failed', $skin->get_errors()->has_errors() ? $skin->get_errors()->get_error_message() : __( 'WordPress could not install the selected plugin build.', 'siteintelix' ) );
			return self::recover_failure( $error, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, '' );
		}

		$main_file = wp_normalize_path( trailingslashit( WP_PLUGIN_DIR ) . $primary );
		wp_clean_plugins_cache( true );
		if ( ! is_dir( $source ) || ! is_file( $main_file ) || is_link( $main_file ) ) {
			$error = new WP_Error( 'siteintelix_version_verification_failed', __( 'The selected build installed, but its plugin files could not be verified.', 'siteintelix' ) );
			return self::recover_failure( $error, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, '' );
		}
		$installed = get_plugin_data( $main_file, false, false );
		if ( empty( $installed['Version'] ) || (string) $installed['Version'] !== (string) $package['version'] ) {
			$error = new WP_Error( 'siteintelix_version_verification_failed', __( 'The installed plugin version does not match the selected stored build.', 'siteintelix' ) );
			return self::recover_failure( $error, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, '' );
		}

		self::restore_activation_state( $primary, $activation );
		wp_clean_plugins_cache( true );
		$filesystem->delete( $backup, true );
		SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );
		$context['success'] = true;
		SITEINTELIX_Version_Switcher_Storage::add_history( $context );
		do_action( 'siteintelix_after_plugin_version_switch', $hook_context, $package );

		return array(
			'plugin_name'      => $package['plugin_name'],
			'previous_version' => $context['previous_version'],
			'target_version'   => $package['version'],
		);
	}

	/** @param array<string,mixed> $package Package. @return bool */
	public static function is_self_package( $package ) {
		return isset( $package['plugin_directory'] ) && 'siteintelix' === strtolower( (string) $package['plugin_directory'] );
	}

	/** @param string $primary Plugin basename. @return array<string,bool> */
	private static function activation_state( $primary ) {
		$active = get_option( 'active_plugins', array() );
		$active = is_array( $active ) ? $active : array();
		$network = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$network = is_array( $network ) ? $network : array();
		return array(
			'site_active'    => in_array( $primary, $active, true ),
			'network_active' => isset( $network[ $primary ] ),
		);
	}

	/** @param string $primary Plugin basename. @param array<string,bool> $state State. @return void */
	private static function restore_activation_state( $primary, $state ) {
		$active = get_option( 'active_plugins', array() );
		$active = is_array( $active ) ? array_values( array_unique( $active ) ) : array();
		$active = array_values( array_diff( $active, array( $primary ) ) );
		if ( ! empty( $state['site_active'] ) ) {
			$active[] = $primary;
		}
		update_option( 'active_plugins', array_values( array_unique( $active ) ) );

		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			$network = is_array( $network ) ? $network : array();
			unset( $network[ $primary ] );
			if ( ! empty( $state['network_active'] ) ) {
				$network[ $primary ] = time();
			}
			update_site_option( 'active_sitewide_plugins', $network );
		}
	}

	/** @return WP_Filesystem_Base|WP_Error */
	private static function filesystem() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( ! WP_Filesystem() || ! $wp_filesystem ) {
			return new WP_Error( 'siteintelix_version_filesystem_credentials', __( 'WordPress filesystem access is required. Configure direct filesystem access or retry from an environment where WordPress can request credentials.', 'siteintelix' ) );
		}
		return $wp_filesystem;
	}

	/** @param string $source Source. @param string $slug Slug. @param WP_Filesystem_Base $filesystem Filesystem. @return string|WP_Error */
	private static function create_backup( $source, $slug, $filesystem ) {
		$ensured = SITEINTELIX_Version_Switcher_Storage::ensure();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}
		$backup = wp_normalize_path( trailingslashit( SITEINTELIX_Version_Switcher_Storage::recovery_directory() ) . sanitize_key( $slug ) . '-' . wp_generate_password( 24, false, false ) );
		if ( ! SITEINTELIX_Version_Switcher_Storage::contains( SITEINTELIX_Version_Switcher_Storage::recovery_directory(), $backup ) || $filesystem->exists( $backup ) ) {
			return new WP_Error( 'siteintelix_version_backup_failed', __( 'A safe recovery location could not be prepared.', 'siteintelix' ) );
		}
		$result = copy_dir( $source, $backup );
		if ( is_wp_error( $result ) || ! $filesystem->is_dir( $backup ) ) {
			$filesystem->delete( $backup, true );
			return is_wp_error( $result ) ? $result : new WP_Error( 'siteintelix_version_backup_failed', __( 'The installed plugin could not be copied to protected recovery storage.', 'siteintelix' ) );
		}
		return $backup;
	}

	/**
	 * Restore the recovery copy, activation state, lock, history, and failure hook.
	 *
	 * @param WP_Error $error Error.
	 * @param array<string,mixed> $context History context.
	 * @param array<string,mixed> $hook_context Hook context.
	 * @param string $source Live directory.
	 * @param string $backup Backup directory.
	 * @param array<string,bool> $activation Activation state.
	 * @param WP_Filesystem_Base $filesystem Filesystem.
	 * @param string $lock Lock token.
	 * @param string $unused Reserved.
	 * @return WP_Error
	 */
	private static function recover_failure( $error, $context, $hook_context, $source, $backup, $activation, $filesystem, $lock, $unused ) {
		unset( $unused );
		$restored = false;
		if ( self::safe_live_target( $source, basename( $source ) ) && SITEINTELIX_Version_Switcher_Storage::contains( SITEINTELIX_Version_Switcher_Storage::recovery_directory(), $backup ) && $filesystem->is_dir( $backup ) ) {
			$deleted = $filesystem->delete( $source, true );
			if ( $deleted || ! $filesystem->exists( $source ) ) {
				$copy_result = copy_dir( $backup, $source );
				$restored    = ! is_wp_error( $copy_result ) && $filesystem->is_dir( $source );
			}
			if ( $restored ) {
				$filesystem->delete( $backup, true );
			}
		}
		self::restore_activation_state( $hook_context['plugin'], $activation );
		wp_clean_plugins_cache( true );
		SITEINTELIX_Version_Switcher_Storage::release_lock( $lock );
		if ( ! $restored ) {
			$error->add( 'siteintelix_version_recovery_failed', __( 'Automatic recovery could not be verified; the protected recovery copy was retained for manual recovery.', 'siteintelix' ) );
		}
		$context['success']       = false;
		$context['error_code']    = $error->get_error_code();
		$context['error_message'] = $error->get_error_message();
		SITEINTELIX_Version_Switcher_Storage::add_history( $context );
		do_action( 'siteintelix_plugin_version_switch_failed', $hook_context, $error, $restored );
		return $error;
	}

	/** @param WP_Error $error Error. @param array<string,mixed> $context Context. @return WP_Error */
	private static function record_error( $error, $context ) {
		$context['success']       = false;
		$context['error_code']    = $error->get_error_code();
		$context['error_message'] = $error->get_error_message();
		SITEINTELIX_Version_Switcher_Storage::add_history( $context );
		return $error;
	}

	/** @param string $source Source. @param string $directory Directory name. @return bool */
	private static function valid_live_directory( $source, $directory ) {
		return self::safe_live_target( $source, $directory ) && is_dir( $source ) && ! is_link( $source );
	}

	/** @param string $source Source. @param string $directory Directory name. @return bool */
	private static function safe_live_target( $source, $directory ) {
		$expected = wp_normalize_path( trailingslashit( WP_PLUGIN_DIR ) . $directory );
		return $source === $expected && SITEINTELIX_Version_Switcher_Storage::contains( WP_PLUGIN_DIR, $source ) && WP_PLUGIN_DIR !== $source;
	}

	/** @param string $directory Directory. @return bool */
	private static function contains_symlink( $directory ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				return true;
			}
		}
		return false;
	}
}
