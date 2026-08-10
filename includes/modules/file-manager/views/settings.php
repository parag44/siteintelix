<?php
/**
 * File Manager settings form.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$siteintelix_fm_settings = SITEINTELIX_File_Manager_Settings::get();
$siteintelix_fm_locations = array(
	'wp_content' => __( 'wp-content', 'siteintelix' ),
	'plugins'    => __( 'Plugins', 'siteintelix' ),
	'themes'     => __( 'Themes', 'siteintelix' ),
	'uploads'    => __( 'Uploads', 'siteintelix' ),
	'mu_plugins' => __( 'Must-use plugins', 'siteintelix' ),
	'languages'  => __( 'Languages', 'siteintelix' ),
);
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form sitx-fm-settings">
	<input type="hidden" name="action" value="siteintelix_save_file_manager_settings">
	<?php wp_nonce_field( 'siteintelix_save_file_manager_settings' ); ?>
	<div class="sitx-settings-content-grid">
		<div class="sitx-settings-main">
			<h2><?php esc_html_e( 'General', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Operating mode', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'This release always uses Safe Mode. Advanced Mode is not available.', 'siteintelix' ); ?></p></div><span class="si-badge si-badge--success"><?php esc_html_e( 'Safe Mode', 'siteintelix' ); ?></span></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Show hidden files', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Hidden files remain subject to all protected-path rules.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[show_hidden]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['show_hidden'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Starting directory', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Use a path relative to the WordPress root.', 'siteintelix' ); ?></p></div><input type="text" name="settings[start_directory]" value="<?php echo esc_attr( $siteintelix_fm_settings['start_directory'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Preview limit', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Maximum bytes loaded for an inline preview.', 'siteintelix' ); ?></p></div><input type="number" min="65536" max="10485760" name="settings[preview_max_bytes]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['preview_max_bytes'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Edit limit', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Maximum bytes accepted by the browser editor.', 'siteintelix' ); ?></p></div><input type="number" min="16384" max="5242880" name="settings[edit_max_bytes]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['edit_max_bytes'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Upload limit', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Maximum verified size for each uploaded file.', 'siteintelix' ); ?></p></div><input type="number" min="65536" max="104857600" name="settings[upload_max_bytes]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['upload_max_bytes'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Show absolute paths', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Off by default to avoid exposing server paths in the interface.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[allow_absolute_paths]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['allow_absolute_paths'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>

			<h2><?php esc_html_e( 'Allowed locations', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'WordPress root', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'The installation root can be browsed but is always read-only.', 'siteintelix' ); ?></p></div><span class="si-badge"><?php esc_html_e( 'Read-only', 'siteintelix' ); ?></span></div>
			<fieldset class="sitx-fm-settings-group">
				<legend class="screen-reader-text"><?php esc_html_e( 'Permitted WordPress locations', 'siteintelix' ); ?></legend>
				<?php foreach ( $siteintelix_fm_locations as $siteintelix_fm_location_key => $siteintelix_fm_location_label ) : ?>
					<label><input type="checkbox" name="settings[allowed_locations][<?php echo esc_attr( $siteintelix_fm_location_key ); ?>]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['allowed_locations'][ $siteintelix_fm_location_key ] ) ); ?>> <?php echo esc_html( $siteintelix_fm_location_label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Custom permitted directories', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'One existing path per line, relative to the WordPress root. Paths outside the installation are rejected.', 'siteintelix' ); ?></p></div><textarea rows="4" name="settings[custom_roots]"><?php echo esc_textarea( implode( "\n", $siteintelix_fm_settings['custom_roots'] ) ); ?></textarea></div>

			<h2><?php esc_html_e( 'Editing', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Enable text editing', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'PHP remains view-only. Every save creates a verified backup.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[editing_enabled]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['editing_enabled'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Editable extensions', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Comma-separated non-PHP extensions. Executable types are always rejected.', 'siteintelix' ); ?></p></div><textarea rows="3" name="settings[editable_extensions]"><?php echo esc_textarea( implode( ', ', $siteintelix_fm_settings['editable_extensions'] ) ); ?></textarea></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Save protections', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Backups before save and active plugin/theme write protection are always enabled.', 'siteintelix' ); ?></p></div><span class="si-badge si-badge--success"><?php esc_html_e( 'Required', 'siteintelix' ); ?></span></div>

			<h2><?php esc_html_e( 'Uploads', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Enable uploads', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Uploads use extension, MIME, size, filename, and destination validation.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[uploads_enabled]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['uploads_enabled'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Upload extensions', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Comma-separated allowlist. Server-executable extensions are always rejected.', 'siteintelix' ); ?></p></div><textarea rows="3" name="settings[upload_extensions]"><?php echo esc_textarea( implode( ', ', $siteintelix_fm_settings['upload_extensions'] ) ); ?></textarea></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'ZIP archives', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Permit verified ZIP uploads and secure extraction inside plugin and theme directories.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[allow_archive_uploads]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['allow_archive_uploads'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Allow overwrite', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'When disabled, an upload cannot replace an existing file.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[allow_overwrite]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['allow_overwrite'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>

			<h2><?php esc_html_e( 'Backups and trash', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Automatic backups', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'A verified backup before every edit is mandatory in Safe Mode.', 'siteintelix' ); ?></p></div><span class="si-badge si-badge--success"><?php esc_html_e( 'Enabled', 'siteintelix' ); ?></span></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Backup retention days', 'siteintelix' ); ?></h3></div><input type="number" min="1" max="365" name="settings[backup_retention_days]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['backup_retention_days'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Maximum backups per file', 'siteintelix' ); ?></h3></div><input type="number" min="1" max="100" name="settings[backup_max_per_file]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['backup_max_per_file'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Maximum backup storage (bytes)', 'siteintelix' ); ?></h3></div><input type="number" min="1048576" max="5368709120" name="settings[backup_max_storage_bytes]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['backup_max_storage_bytes'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Trash', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Delete operations always move eligible items to private trash first.', 'siteintelix' ); ?></p></div><span class="si-badge si-badge--success"><?php esc_html_e( 'Enabled', 'siteintelix' ); ?></span></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Trash retention days', 'siteintelix' ); ?></h3></div><input type="number" min="1" max="365" name="settings[trash_retention_days]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['trash_retention_days'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Maximum trash storage (bytes)', 'siteintelix' ); ?></h3></div><input type="number" min="1048576" max="5368709120" name="settings[trash_max_storage_bytes]" value="<?php echo esc_attr( (string) $siteintelix_fm_settings['trash_max_storage_bytes'] ); ?>"></div>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Automatic trash cleanup', 'siteintelix' ); ?></h3></div><label class="sitx-toggle"><input type="checkbox" name="settings[trash_auto_cleanup]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['trash_auto_cleanup'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>

			<h2><?php esc_html_e( 'Audit', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Activity logging', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Record operations and relative paths without recording file contents.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[audit_enabled]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_enabled'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<fieldset class="sitx-fm-settings-group">
				<legend><?php esc_html_e( 'Logged operations', 'siteintelix' ); ?></legend>
				<label><input type="checkbox" name="settings[audit_views]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_views'] ) ); ?>> <?php esc_html_e( 'Views', 'siteintelix' ); ?></label>
				<label><input type="checkbox" name="settings[audit_downloads]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_downloads'] ) ); ?>> <?php esc_html_e( 'Downloads', 'siteintelix' ); ?></label>
				<label><input type="checkbox" name="settings[audit_edits]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_edits'] ) ); ?>> <?php esc_html_e( 'Edits', 'siteintelix' ); ?></label>
				<label><input type="checkbox" name="settings[audit_uploads]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_uploads'] ) ); ?>> <?php esc_html_e( 'Uploads', 'siteintelix' ); ?></label>
				<label><input type="checkbox" name="settings[audit_trash]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['audit_trash'] ) ); ?>> <?php esc_html_e( 'Trash and restores', 'siteintelix' ); ?></label>
			</fieldset>

			<h2><?php esc_html_e( 'Data retention', 'siteintelix' ); ?></h2>
			<div class="sitx-setting-row si-form-row"><div><h3><?php esc_html_e( 'Remove owned data on uninstall', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Deletes only SiteIntelix File Manager backups, trash, metadata, and logs. Original site files are never removed.', 'siteintelix' ); ?></p></div><label class="sitx-toggle"><input type="checkbox" name="settings[remove_data_on_uninstall]" value="1" <?php checked( ! empty( $siteintelix_fm_settings['remove_data_on_uninstall'] ) ); ?>><span class="sitx-toggle__slider"></span></label></div>
			<button type="submit" class="si-button si-button--primary"><?php esc_html_e( 'Save File Manager Settings', 'siteintelix' ); ?></button>
		</div>
		<aside class="sitx-settings-sidebar"><div class="sitx-side-card si-card"><h3><?php esc_html_e( 'Absolute protections', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'WordPress core, wp-config.php, sensitive credentials, and private SiteIntelix storage remain protected.', 'siteintelix' ); ?></p></div><div class="sitx-side-card si-card"><h3><?php esc_html_e( 'WordPress controls', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS are enforced for every request.', 'siteintelix' ); ?></p></div></aside>
	</div>
</form>
