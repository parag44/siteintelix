<?php
/**
 * Terminal Light Debug Log Viewer page for SiteIntelix.
 *
 * Shows all parsed entries in a scrollable terminal-style viewer.
 *
 * @package SiteIntelix
 * @since   2.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_log_data      = SITEINTELIX_Debug_Log::get_data( 0 );
$siteintelix_entries       = isset( $siteintelix_log_data['entries'] ) && is_array( $siteintelix_log_data['entries'] ) ? $siteintelix_log_data['entries'] : array();
$siteintelix_active_method = isset( $siteintelix_log_data['method'] ) ? $siteintelix_log_data['method'] : 'mu';
$siteintelix_mode_label    = SITEINTELIX_Debug_Log::get_mode_label( $siteintelix_active_method );
$siteintelix_log_has_file  = ! empty( $siteintelix_log_data['exists'] ) && ! empty( $siteintelix_log_data['readable'] );
$siteintelix_log_path      = 'wp-content/siteintelix-debug.log';
$siteintelix_refresh_url   = admin_url( 'admin.php?page=siteintelix-debug-log' );
$siteintelix_clear_url     = admin_url( 'admin-post.php' );
$siteintelix_settings_url  = admin_url( 'admin.php?page=siteintelix-settings' );
$siteintelix_download_url  = wp_nonce_url( admin_url( 'admin-post.php?action=siteintelix_download_debug_log' ), 'siteintelix_download_debug_log' );

$siteintelix_level_labels = array(
	'FATAL'      => __( 'ERROR', 'siteintelix' ),
	'WARNING'    => __( 'WARN', 'siteintelix' ),
	'NOTICE'     => __( 'NOTICE', 'siteintelix' ),
	'DEPRECATED' => __( 'DEPRECATED', 'siteintelix' ),
	'DATABASE'   => __( 'DATABASE', 'siteintelix' ),
	'INFO'       => __( 'INFO', 'siteintelix' ),
);

$siteintelix_entry_text = static function ( $entry ) {
	$message = isset( $entry['message'] ) ? wp_strip_all_tags( (string) $entry['message'] ) : '';
	$message = preg_replace( "/\\r\\n|\\r/", "\n", $message );

	$file = isset( $entry['file'] ) ? (string) $entry['file'] : '';
	$line = isset( $entry['line_number'] ) ? (int) $entry['line_number'] : 0;
	if ( $file ) {
		$message .= ' | ' . $file . ( $line ? ':' . $line : '' );
	}

	return trim( (string) $message );
};

ob_start();
?>
<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_refresh_url ); ?>">
	<span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?>
</a>
<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
	<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
</a>
<form method="post" action="<?php echo esc_url( $siteintelix_clear_url ); ?>" class="sitx-debug-header-form siteintelix-debug-action-form">
	<?php wp_nonce_field( 'siteintelix_clear_debug_log' ); ?>
	<input type="hidden" name="action" value="siteintelix_clear_debug_log">
	<button type="submit" class="sitx-btn sitx-btn--white si-button si-button--danger">
		<span class="dashicons dashicons-trash" aria-hidden="true"></span><?php esc_html_e( 'Clear Logs', 'siteintelix' ); ?>
	</button>
</form>
<?php if ( $siteintelix_log_has_file ) : ?>
	<a class="sitx-btn sitx-btn--primary si-button si-button--primary" href="<?php echo esc_url( $siteintelix_download_url ); ?>" target="_blank" rel="noopener noreferrer">
		<span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Download Log', 'siteintelix' ); ?>
	</a>
<?php endif; ?>
<?php
$siteintelix_header_actions = ob_get_clean();
?>
<div class="wrap siteintelix-wrap si-admin-wrap siteintelix-debug-ui sitx-terminal-ui" id="siteintelix-debug-log-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-editor-code',
			'title'       => __( 'Debug Log Viewer', 'siteintelix' ),
			'description' => __( 'Inspect and monitor captured WordPress debug log entries.', 'siteintelix' ),
			'actions'     => array( $siteintelix_header_actions ),
		)
	);
	?>

	<div class="sitx-debug-modern-body">
		<section class="sitx-terminal-status si-card" aria-label="<?php esc_attr_e( 'Debug log status', 'siteintelix' ); ?>">
			<div>
				<span class="dashicons <?php echo 'wp_config' === $siteintelix_active_method ? 'dashicons-editor-code' : 'dashicons-shield'; ?>" aria-hidden="true"></span>
				<strong><?php echo 'wp_config' === $siteintelix_active_method ? esc_html__( 'wp-config.php Mode Active', 'siteintelix' ) : esc_html__( 'MU Plugin Mode Active', 'siteintelix' ); ?></strong>
				<code><?php echo esc_html( $siteintelix_mode_label ); ?></code>
			</div>
			<a class="sitx-debug-btn sitx-debug-btn--secondary si-button si-button--secondary" href="<?php echo esc_url( $siteintelix_settings_url ); ?>">
				<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Switch Mode', 'siteintelix' ); ?>
			</a>
		</section>

		<section class="sitx-terminal-shell" aria-label="<?php esc_attr_e( 'Terminal light debug log', 'siteintelix' ); ?>">
			<div class="sitx-terminal-titlebar">
				<div class="sitx-terminal-tabs">
					<span class="sitx-terminal-dot sitx-terminal-dot--red" aria-hidden="true"></span>
					<span class="sitx-terminal-dot sitx-terminal-dot--yellow" aria-hidden="true"></span>
					<span class="sitx-terminal-dot sitx-terminal-dot--green" aria-hidden="true"></span>
					<strong><?php echo esc_html( basename( $siteintelix_log_path ) ); ?></strong>
				</div>
				<span><?php echo esc_html( sprintf( __( '%d entries', 'siteintelix' ), count( $siteintelix_entries ) ) ); ?></span>
			</div>

			<div class="sitx-terminal-body" role="log" aria-live="polite">
				<?php if ( empty( $siteintelix_entries ) ) : ?>
					<div class="sitx-terminal-empty">
						<span class="sitx-terminal-prompt">$</span>
						<span><?php echo $siteintelix_log_has_file ? esc_html__( 'No entries found in the log file yet.', 'siteintelix' ) : esc_html__( 'No log file found. Debug log will appear here once errors are captured.', 'siteintelix' ); ?></span>
					</div>
				<?php else : ?>
					<ol class="sitx-terminal-lines">
						<?php foreach ( $siteintelix_entries as $siteintelix_index => $siteintelix_entry ) : ?>
							<?php
							$siteintelix_level       = isset( $siteintelix_entry['level'] ) ? strtoupper( (string) $siteintelix_entry['level'] ) : 'INFO';
							$siteintelix_level_label = isset( $siteintelix_level_labels[ $siteintelix_level ] ) ? $siteintelix_level_labels[ $siteintelix_level ] : $siteintelix_level;
							$siteintelix_message     = $siteintelix_entry_text( $siteintelix_entry );
							$siteintelix_lines       = preg_split( "/\\n/", $siteintelix_message );
							$siteintelix_timestamp   = isset( $siteintelix_entry['timestamp'] ) ? (string) $siteintelix_entry['timestamp'] : '';
							?>
							<li class="sitx-terminal-line sitx-terminal-line--<?php echo esc_attr( strtolower( $siteintelix_level ) ); ?>">
								<span class="sitx-terminal-ln"><?php echo esc_html( (string) ( $siteintelix_index + 1 ) ); ?></span>
								<span class="sitx-terminal-time">[<?php echo esc_html( $siteintelix_timestamp ); ?>]</span>
								<span class="sitx-terminal-level"><?php echo esc_html( $siteintelix_level_label ); ?></span>
								<span class="sitx-terminal-message"><?php echo esc_html( is_array( $siteintelix_lines ) ? array_shift( $siteintelix_lines ) : $siteintelix_message ); ?></span>
								<?php if ( ! empty( $siteintelix_lines ) ) : ?>
									<pre><?php echo esc_html( implode( "\n", $siteintelix_lines ) ); ?></pre>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>
		</section>
	</div>
</div>
