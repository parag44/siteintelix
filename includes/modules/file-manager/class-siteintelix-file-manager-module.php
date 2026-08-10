<?php
/**
 * File Manager module bootstrap.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_file_manager_dir = __DIR__ . '/';
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-settings.php';
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-security.php';
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-storage.php';
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-backups.php';
require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-trash.php';
$siteintelix_file_manager_is_cron = function_exists( 'wp_doing_cron' ) && wp_doing_cron();
if ( ! $siteintelix_file_manager_is_cron ) {
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-redactor.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-audit.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-filesystem.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-tree.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-archive.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-editor.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-upload.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-extractor.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-ajax.php';
	require_once $siteintelix_file_manager_dir . 'class-siteintelix-file-manager-admin.php';
}

/**
 * Coordinates the enabled File Manager services.
 */
class SITEINTELIX_File_Manager_Module {

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'siteintelix_file_manager_cleanup', array( __CLASS__, 'cleanup' ) );
		self::schedule_cleanup();

		if ( class_exists( 'SITEINTELIX_File_Manager_Admin' ) ) {
			SITEINTELIX_File_Manager_Admin::init();
		}

		if ( class_exists( 'SITEINTELIX_File_Manager_Ajax' ) ) {
			SITEINTELIX_File_Manager_Ajax::init();
		}
	}

	/**
	 * Initialize safe defaults and owned storage when the module is enabled.
	 *
	 * @return true|WP_Error
	 */
	public static function activate() {
		if ( class_exists( 'SITEINTELIX_File_Manager_Settings' ) ) {
			SITEINTELIX_File_Manager_Settings::add_defaults();
		}

		if ( class_exists( 'SITEINTELIX_File_Manager_Storage' ) ) {
			$result = SITEINTELIX_File_Manager_Storage::ensure_directories();
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		self::schedule_cleanup();
		return true;
	}

	/**
	 * Stop scheduled work without deleting settings or owned data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'siteintelix_file_manager_cleanup' );
	}

	/**
	 * Schedule bounded retention without creating duplicate events.
	 *
	 * @return void
	 */
	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'siteintelix_file_manager_cleanup' ) ) {
			$hour = defined( 'HOUR_IN_SECONDS' ) ? HOUR_IN_SECONDS : 3600;
			wp_schedule_event( time() + $hour, 'daily', 'siteintelix_file_manager_cleanup' );
		}
	}

	/**
	 * Apply backup and trash retention.
	 *
	 * @return void
	 */
	public static function cleanup() {
		$backups = new SITEINTELIX_File_Manager_Backups();
		$trash   = new SITEINTELIX_File_Manager_Trash();
		$backups->cleanup();
		$trash->cleanup();
		SITEINTELIX_File_Manager_Storage::cleanup_temporary_archives();
	}
}
