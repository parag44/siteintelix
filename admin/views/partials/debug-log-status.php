<?php
/**
 * Shared Debug Log status and mode switch component.
 *
 * @package SiteIntelix
 * @since   2.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_recent_entries = isset( $siteintelix_recent_entries ) ? (int) $siteintelix_recent_entries : (int) $siteintelix_total_entries;
$siteintelix_log_path       = isset( $siteintelix_log_data['path'] ) ? (string) $siteintelix_log_data['path'] : SITEINTELIX_Debug_Log::get_path_for_mode( $siteintelix_active_method );
?>
<section class="siteintelix-debug-shared-status siteintelix-mode-status" aria-label="<?php esc_attr_e( 'Debug log status', 'siteintelix' ); ?>">
	<div class="siteintelix-debug-shared-status__mode siteintelix-mode-status__inner si-card">
		<span class="siteintelix-debug-shared-status__icon dashicons <?php echo 'wp_config' === $siteintelix_active_method ? 'dashicons-editor-code' : 'dashicons-shield'; ?>" aria-hidden="true"></span>
		<div class="siteintelix-debug-shared-status__copy">
			<strong><?php echo 'wp_config' === $siteintelix_active_method ? esc_html__( 'wp-config.php Mode Active', 'siteintelix' ) : esc_html__( 'MU Plugin Mode Active', 'siteintelix' ); ?></strong>
			<p><span class="screen-reader-text"><?php esc_html_e( 'Current log path:', 'siteintelix' ); ?></span><?php echo esc_html( $siteintelix_log_path ); ?></p>
			<span class="screen-reader-text"><?php esc_html_e( 'Recent Entries', 'siteintelix' ); ?>: <?php echo esc_html( number_format_i18n( $siteintelix_recent_entries ) ); ?></span>
		</div>
		<a class="sitx-btn sitx-btn--outline si-button si-button--secondary siteintelix-debug-shared-status__switch" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
			<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
			<?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
		</a>
	</div>
</section>
