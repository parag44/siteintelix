<?php
/**
 * File Manager module bootstrap.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
			return SITEINTELIX_File_Manager_Storage::ensure_directories();
		}

		return true;
	}
}
