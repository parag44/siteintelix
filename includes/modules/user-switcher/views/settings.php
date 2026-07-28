<?php
/**
 * User Switcher settings view.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<?php if ( isset( $_GET['siteintelix_settings_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice notice-success inline"><p><?php esc_html_e( 'User Switcher settings saved.', 'siteintelix' ); ?></p></div>
<?php endif; ?>

<div class="sitx-settings-content-grid">
	<div class="sitx-settings-main">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form">
			<input type="hidden" name="action" value="siteintelix_save_user_switcher_settings">
			<?php wp_nonce_field( 'siteintelix_save_user_switcher_settings' ); ?>

			<div class="sitx-user-switcher-section">
				<h3><?php esc_html_e( 'Role permissions', 'siteintelix' ); ?></h3>
				<p><?php esc_html_e( 'Selected operator roles receive the dedicated siteintelix_switch_users capability. Target roles define which accounts may be entered.', 'siteintelix' ); ?></p>
				<div class="sitx-user-switcher-role-grid">
					<fieldset>
						<legend><?php esc_html_e( 'Allowed operator roles', 'siteintelix' ); ?></legend>
						<?php foreach ( $roles as $siteintelix_role_slug => $siteintelix_role_name ) : ?>
							<label><input type="checkbox" name="allowed_operator_roles[]" value="<?php echo esc_attr( $siteintelix_role_slug ); ?>" <?php checked( in_array( $siteintelix_role_slug, $settings['allowed_operator_roles'], true ) ); ?>> <?php echo esc_html( $siteintelix_role_name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<fieldset>
						<legend><?php esc_html_e( 'Allowed target roles', 'siteintelix' ); ?></legend>
						<?php foreach ( $roles as $siteintelix_role_slug => $siteintelix_role_name ) : ?>
							<label><input type="checkbox" name="allowed_target_roles[]" value="<?php echo esc_attr( $siteintelix_role_slug ); ?>" <?php checked( in_array( $siteintelix_role_slug, $settings['allowed_target_roles'], true ) ); ?>> <?php echo esc_html( $siteintelix_role_name ); ?></label>
						<?php endforeach; ?>
					</fieldset>
				</div>
				<label class="sitx-user-switcher-check">
					<input type="checkbox" name="allow_administrators" value="1" <?php checked( ! empty( $settings['allow_administrators'] ) ); ?>>
					<span><strong><?php esc_html_e( 'Allow switching to administrators', 'siteintelix' ); ?></strong><small><?php esc_html_e( 'Off by default. Super administrators remain protected unless a developer explicitly allows them with the target filter.', 'siteintelix' ); ?></small></span>
				</label>
			</div>

			<div class="sitx-user-switcher-section">
				<h3><?php esc_html_e( 'Redirects and session', 'siteintelix' ); ?></h3>
				<div class="sitx-form-grid">
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Redirect after switching', 'siteintelix' ); ?></span>
						<select name="switch_redirect">
							<option value="user_dashboard" <?php selected( $settings['switch_redirect'], 'user_dashboard' ); ?>><?php esc_html_e( 'User dashboard', 'siteintelix' ); ?></option>
							<option value="homepage" <?php selected( $settings['switch_redirect'], 'homepage' ); ?>><?php esc_html_e( 'Site homepage', 'siteintelix' ); ?></option>
							<option value="wp_admin" <?php selected( $settings['switch_redirect'], 'wp_admin' ); ?>><?php esc_html_e( 'WordPress admin', 'siteintelix' ); ?></option>
							<option value="custom" <?php selected( $settings['switch_redirect'], 'custom' ); ?>><?php esc_html_e( 'Custom URL', 'siteintelix' ); ?></option>
						</select>
					</label>
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Switch custom URL', 'siteintelix' ); ?></span>
						<input type="url" name="switch_custom_url" value="<?php echo esc_attr( $settings['switch_custom_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
					</label>
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Return redirect', 'siteintelix' ); ?></span>
						<select name="return_redirect">
							<option value="users" <?php selected( $settings['return_redirect'], 'users' ); ?>><?php esc_html_e( 'Users screen', 'siteintelix' ); ?></option>
							<option value="previous_admin" <?php selected( $settings['return_redirect'], 'previous_admin' ); ?>><?php esc_html_e( 'Previous admin URL', 'siteintelix' ); ?></option>
							<option value="wp_dashboard" <?php selected( $settings['return_redirect'], 'wp_dashboard' ); ?>><?php esc_html_e( 'WordPress dashboard', 'siteintelix' ); ?></option>
							<option value="custom" <?php selected( $settings['return_redirect'], 'custom' ); ?>><?php esc_html_e( 'Custom URL', 'siteintelix' ); ?></option>
						</select>
					</label>
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Return custom URL', 'siteintelix' ); ?></span>
						<input type="url" name="return_custom_url" value="<?php echo esc_attr( $settings['return_custom_url'] ); ?>" placeholder="<?php echo esc_attr( admin_url( 'users.php' ) ); ?>">
					</label>
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Session duration (minutes)', 'siteintelix' ); ?></span>
						<input type="number" name="session_duration" min="<?php echo esc_attr( SITEINTELIX_User_Switcher_Settings::MIN_DURATION ); ?>" max="<?php echo esc_attr( SITEINTELIX_User_Switcher_Settings::MAX_DURATION ); ?>" value="<?php echo esc_attr( absint( $settings['session_duration'] ) ); ?>">
					</label>
					<label class="sitx-form-field">
						<span><?php esc_html_e( 'Log retention (days)', 'siteintelix' ); ?></span>
						<input type="number" name="retention_days" min="<?php echo esc_attr( SITEINTELIX_User_Switcher_Settings::MIN_RETENTION ); ?>" max="<?php echo esc_attr( SITEINTELIX_User_Switcher_Settings::MAX_RETENTION ); ?>" value="<?php echo esc_attr( absint( $settings['retention_days'] ) ); ?>">
					</label>
				</div>
				<label class="sitx-user-switcher-check">
					<input type="checkbox" name="logging_enabled" value="1" <?php checked( ! empty( $settings['logging_enabled'] ) ); ?>>
					<span><strong><?php esc_html_e( 'Log switching activity', 'siteintelix' ); ?></strong><small><?php esc_html_e( 'Records the operator, target, timestamps, status, IP address, and bounded user agent for accountability.', 'siteintelix' ); ?></small></span>
				</label>
			</div>

			<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save User Switcher Settings', 'siteintelix' ); ?></button>
		</form>
	</div>
	<aside class="sitx-settings-sidebar">
		<div class="sitx-side-card si-card">
			<h3><?php esc_html_e( 'Security model', 'siteintelix' ); ?></h3>
			<p><?php esc_html_e( 'Passwords are never requested or changed. WordPress session tokens, a signed HTTP-only cookie, nonces, and a short-lived server record protect every switch.', 'siteintelix' ); ?></p>
		</div>
		<div class="sitx-side-card si-card">
			<h3><?php esc_html_e( 'How to use', 'siteintelix' ); ?></h3>
			<p><?php esc_html_e( 'Open Users → All Users and select Login as User. Use the toolbar return control when troubleshooting is complete.', 'siteintelix' ); ?></p>
		</div>
	</aside>
</div>
