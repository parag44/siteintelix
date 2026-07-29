<?php
/**
 * File Manager admin integration.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the File Manager screen, settings panel, and scoped assets.
 */
class SITEINTELIX_File_Manager_Admin {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 39 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
	}

	/**
	 * Register the enabled module submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'siteintelix',
			__( 'File Manager', 'siteintelix' ),
			__( 'File Manager', 'siteintelix' ),
			SITEINTELIX_File_Manager_Security::capability(),
			'siteintelix-file-manager',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the File Manager page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access File Manager.', 'siteintelix' ) );
		}
		if ( ! SITEINTELIX_Modules::is_enabled( 'file_manager' ) ) {
			wp_die( esc_html__( 'The File Manager module is not enabled.', 'siteintelix' ) );
		}
		require SITEINTELIX_PLUGIN_DIR . 'includes/modules/file-manager/views/file-manager.php';
	}

	/**
	 * Enqueue local assets only on File Manager screens.
	 *
	 * @param string $hook_suffix Current admin hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		$is_manager = 'siteintelix_page_siteintelix-file-manager' === (string) $hook_suffix;
		$is_settings = 'siteintelix_page_siteintelix-settings' === (string) $hook_suffix && self::requested_settings_tab();
		if ( ! $is_manager && ! $is_settings ) {
			return;
		}
		wp_enqueue_style(
			'siteintelix-file-manager',
			SITEINTELIX_PLUGIN_URL . 'includes/modules/file-manager/assets/file-manager.css',
			array( 'siteintelix-admin-style' ),
			SITEINTELIX_VERSION
		);
		if ( ! $is_manager ) {
			return;
		}

		$editor_settings = wp_enqueue_code_editor( array( 'type' => 'text/plain' ) );
		wp_enqueue_script(
			'siteintelix-file-manager',
			SITEINTELIX_PLUGIN_URL . 'includes/modules/file-manager/assets/file-manager.js',
			array( 'siteintelix-core', 'wp-a11y' ),
			SITEINTELIX_VERSION,
			true
		);
		$settings = SITEINTELIX_File_Manager_Settings::get();
		$actions  = array(
			'list_directory',
			'get_file',
			'get_details',
			'save_file',
			'upload_files',
			'create_file',
			'create_directory',
			'rename_item',
			'trash_item',
			'list_trash',
			'restore_item',
			'permanently_delete_item',
			'list_backups',
			'restore_backup',
		);
		$nonces = array();
		foreach ( $actions as $action ) {
			$nonces[ $action ] = wp_create_nonce( 'siteintelix_fm_' . $action );
		}
		wp_localize_script(
			'siteintelix-file-manager',
			'siteintelixFileManager',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'downloadUrl'    => admin_url( 'admin-post.php' ),
				'downloadNonce'  => wp_create_nonce( 'siteintelix_fm_download_file' ),
				'nonces'         => $nonces,
				'startPath'      => (string) $settings['start_directory'],
				'editorSettings' => is_array( $editor_settings ) ? $editor_settings : array(),
				'limits'         => array(
					'preview' => (int) $settings['preview_max_bytes'],
					'edit'    => (int) $settings['edit_max_bytes'],
					'upload'  => (int) $settings['upload_max_bytes'],
				),
				'features'       => array(
					'editing' => ! empty( $settings['editing_enabled'] ) && ! ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ),
					'uploads' => ! empty( $settings['uploads_enabled'] ) && ! ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ),
				),
				'i18n'           => array(
					'loading'          => __( 'Loading files…', 'siteintelix' ),
					'loadFailed'       => __( 'The directory could not be loaded.', 'siteintelix' ),
					'empty'            => __( 'This directory is empty.', 'siteintelix' ),
					'saved'            => __( 'File saved.', 'siteintelix' ),
					'uploaded'         => __( 'Upload complete.', 'siteintelix' ),
					'operationFailed'  => __( 'The operation could not be completed.', 'siteintelix' ),
					'unsavedChanges'   => __( 'You have unsaved changes. Discard them?', 'siteintelix' ),
					'confirmPermanent' => __( 'Type the item name to permanently delete it.', 'siteintelix' ),
				),
			)
		);
		wp_set_script_translations( 'siteintelix-file-manager', 'siteintelix', SITEINTELIX_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Render File Manager's global settings tab.
	 *
	 * @param string[] $enabled_modules Enabled module IDs.
	 * @param string   $active_tab Active settings tab.
	 * @return void
	 */
	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'file_manager', (array) $enabled_modules, true ) || ! SITEINTELIX_File_Manager_Security::current_user_can_manage() ) {
			return;
		}
		?>
		<section class="sitx-settings-panel-tab <?php echo 'file_manager' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-file-manager-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-file_manager" data-siteintelix-settings-panel="file_manager" <?php echo 'file_manager' === $active_tab ? '' : 'hidden'; ?>>
			<?php require SITEINTELIX_PLUGIN_DIR . 'includes/modules/file-manager/views/settings.php'; ?>
		</section>
		<?php
	}

	/**
	 * Determine whether the shared Settings page requests File Manager.
	 *
	 * @return bool
	 */
	private static function requested_settings_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
		return isset( $_GET['tab'] ) && 'file_manager' === sanitize_key( wp_unslash( $_GET['tab'] ) );
	}
}
