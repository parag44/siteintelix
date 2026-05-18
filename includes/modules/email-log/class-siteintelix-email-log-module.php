<?php
/**
 * Email Log module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures outgoing WordPress emails and renders the Email Log module UI.
 */
class SITEINTELIX_Email_Log_Module {

	const SETTINGS_OPTION = 'siteintelix_email_log_settings';

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'log_success' ), 10, 1 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'log_failure' ), 10, 1 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
			add_action( 'admin_post_siteintelix_clear_email_logs', array( __CLASS__, 'handle_clear_logs' ) );
			add_action( 'admin_post_siteintelix_delete_email_log', array( __CLASS__, 'handle_delete_log' ) );
			add_action( 'admin_post_siteintelix_bulk_email_logs', array( __CLASS__, 'handle_bulk_action' ) );
			add_action( 'admin_post_siteintelix_send_test_email', array( __CLASS__, 'handle_send_test_email' ) );
			add_action( 'admin_post_siteintelix_save_email_log_settings', array( __CLASS__, 'handle_save_settings' ) );
			add_action( 'siteintelix_render_module_settings_sections', array( __CLASS__, 'render_settings_section' ), 10, 2 );
		}
	}

	/**
	 * Create the email logs table and default settings.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'sent',
			sent_at datetime NOT NULL,
			to_email longtext NULL,
			subject text NULL,
			message longtext NULL,
			headers longtext NULL,
			attachments longtext NULL,
			error_message longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY sent_at (sent_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		if ( false === get_option( self::SETTINGS_OPTION, false ) ) {
			add_option( self::SETTINGS_OPTION, self::get_default_settings() );
		}
	}

	/**
	 * Register Email Log submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'siteintelix',
			__( 'Email Log', 'siteintelix' ),
			__( 'Email Log', 'siteintelix' ),
			'manage_options',
			'siteintelix-email-log',
			array( __CLASS__, 'render_logs_page' )
		);
	}

	/**
	 * Log successful mail.
	 *
	 * @param array $mail_data Mail payload.
	 * @return void
	 */
	public static function log_success( $mail_data ) {
		if ( self::is_prelogged_error_alert( isset( $mail_data['headers'] ) ? $mail_data['headers'] : '' ) ) {
			return;
		}

		self::insert_log(
			array(
				'status'      => 'sent',
				'to'          => isset( $mail_data['to'] ) ? $mail_data['to'] : '',
				'subject'     => isset( $mail_data['subject'] ) ? $mail_data['subject'] : '',
				'message'     => isset( $mail_data['message'] ) ? $mail_data['message'] : '',
				'headers'     => isset( $mail_data['headers'] ) ? $mail_data['headers'] : '',
				'attachments' => isset( $mail_data['attachments'] ) ? $mail_data['attachments'] : '',
			)
		);
	}

	/**
	 * Log failed mail.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	public static function log_failure( $error ) {
		$mail_data = array();

		if ( is_wp_error( $error ) && is_array( $error->get_error_data() ) ) {
			$mail_data = $error->get_error_data();
		}

		if ( self::is_prelogged_error_alert( isset( $mail_data['headers'] ) ? $mail_data['headers'] : '' ) ) {
			return;
		}

		self::insert_log(
			array(
				'status'        => 'failed',
				'to'            => isset( $mail_data['to'] ) ? $mail_data['to'] : '',
				'subject'       => isset( $mail_data['subject'] ) ? $mail_data['subject'] : '',
				'message'       => isset( $mail_data['message'] ) ? $mail_data['message'] : '',
				'headers'       => isset( $mail_data['headers'] ) ? $mail_data['headers'] : '',
				'attachments'   => isset( $mail_data['attachments'] ) ? $mail_data['attachments'] : '',
				'error_message' => is_wp_error( $error ) ? $error->get_error_message() : '',
			)
		);
	}

	/**
	 * Detect SiteIntelix error alert emails that are inserted manually.
	 *
	 * @param mixed $headers Mail headers.
	 * @return bool
	 */
	private static function is_prelogged_error_alert( $headers ) {
		$headers = self::normalize_value( $headers );

		return false !== stripos( $headers, 'X-SiteIntelix-Error-Alert: 1' );
	}

	/**
	 * Insert one email log row.
	 *
	 * @param array<string,mixed> $data Log data.
	 * @return int|false
	 */
	public static function insert_log( $data ) {
		global $wpdb;

		$settings = self::get_settings();
		if ( empty( $settings['logging_enabled'] ) ) {
			return false;
		}

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Module-owned log table.
		$inserted = $wpdb->insert(
			self::get_table_name(),
			array(
				'status'        => self::sanitize_status( isset( $data['status'] ) ? $data['status'] : 'sent' ),
				'sent_at'       => isset( $data['sent_at'] ) ? sanitize_text_field( $data['sent_at'] ) : $now,
				'to_email'      => self::normalize_value( isset( $data['to'] ) ? $data['to'] : '' ),
				'subject'       => sanitize_text_field( isset( $data['subject'] ) ? $data['subject'] : '' ),
				'message'       => self::clean_email_message( isset( $data['message'] ) ? $data['message'] : '' ),
				'headers'       => self::normalize_value( isset( $data['headers'] ) ? $data['headers'] : '' ),
				'attachments'   => self::normalize_value( isset( $data['attachments'] ) ? $data['attachments'] : '' ),
				'error_message' => self::clean_payload( isset( $data['error_message'] ) ? $data['error_message'] : '' ),
				'created_at'    => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		self::maybe_apply_retention();

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Render Email Log page.
	 *
	 * @return void
	 */
	public static function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view email logs.', 'siteintelix' ) );
		}

		global $wpdb;

		$status       = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page     = 20;
		$table_name   = esc_sql( self::get_table_name() );
		$where        = array( '1=1' );
		$args         = array();

		if ( in_array( $status, array( 'sent', 'failed' ), true ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}

		if ( '' !== $search ) {
			$where[] = '(subject LIKE %s OR to_email LIKE %s OR error_message LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
		$rows_sql  = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY sent_at DESC, id DESC LIMIT %d OFFSET %d";
		$args_rows = array_merge( $args, array( $per_page, ( $current_page - 1 ) * $per_page ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned admin log query.
		$total = $args ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : (int) $wpdb->get_var( $count_sql );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned admin log query.
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $args_rows ) );

		$clear_url = wp_nonce_url( admin_url( 'admin-post.php?action=siteintelix_clear_email_logs' ), 'siteintelix_clear_email_logs' );
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-email-alt',
					'title'       => __( 'Email Log', 'siteintelix' ),
					'description' => __( 'Inspect outgoing WordPress emails and delivery failures.', 'siteintelix' ),
					'badges'      => array(
						SITEINTELIX_Admin_UI::badge( 'v' . SITEINTELIX_VERSION, 'neutral' ),
					),
					'actions'     => array(
						SITEINTELIX_Admin_UI::button(
							array(
								'label'      => __( 'Send Test Email', 'siteintelix' ),
								'variant'    => 'secondary',
								'icon'       => 'dashicons-email-alt',
								'attributes' => array(
									'data-siteintelix-send-test-email' => 'true',
									'data-default-recipient'           => get_option( 'admin_email' ),
								),
							)
						),
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Settings', 'siteintelix' ),
								'url'     => admin_url( 'admin.php?page=siteintelix-settings#siteintelix-email-log-settings' ),
								'variant' => 'secondary',
								'icon'    => 'dashicons-admin-generic',
							)
						),
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Clear Logs', 'siteintelix' ),
								'url'     => $clear_url,
								'variant' => 'danger',
								'icon'    => 'dashicons-trash',
							)
						),
					),
				)
			);
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-siteintelix-test-email-form hidden>
				<input type="hidden" name="action" value="siteintelix_send_test_email">
				<?php wp_nonce_field( 'siteintelix_send_test_email' ); ?>
				<input type="email" name="recipient" value="">
			</form>

			<div class="siteintelix-container">
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag. ?>
				<?php if ( isset( $_GET['siteintelix_email_test_sent'] ) ) : ?>
					<div class="sitx-alert sitx-alert--success">
						<div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div>
						<div class="sitx-alert__content">
							<strong class="sitx-alert__title"><?php esc_html_e( 'Test email requested.', 'siteintelix' ); ?></strong>
							<p class="sitx-alert__msg"><?php esc_html_e( 'Open the latest Email Log entry to inspect the full HTML preview and source.', 'siteintelix' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
				<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag. ?>
				<?php if ( isset( $_GET['siteintelix_email_deleted'] ) ) : ?>
					<div class="sitx-alert sitx-alert--success">
						<div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div>
						<div class="sitx-alert__content">
							<strong class="sitx-alert__title"><?php esc_html_e( 'Email log deleted.', 'siteintelix' ); ?></strong>
							<p class="sitx-alert__msg"><?php esc_html_e( 'The selected email log entries were removed.', 'siteintelix' ); ?></p>
						</div>
					</div>
				<?php endif; ?>

				<div class="sitx-card si-card si-toolbar">
					<form method="get" class="sitx-email-log-filters">
						<input type="hidden" name="page" value="siteintelix-email-log">
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search recipient, subject, or error...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search email logs', 'siteintelix' ); ?>">
						<select name="status" aria-label="<?php esc_attr_e( 'Filter email logs by status', 'siteintelix' ); ?>">
							<option value=""><?php esc_html_e( 'All statuses', 'siteintelix' ); ?></option>
							<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'siteintelix' ); ?></option>
							<option value="failed" <?php selected( $status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'siteintelix' ); ?></option>
						</select>
						<button class="sitx-btn sitx-btn--primary si-button si-button--primary" type="submit"><?php esc_html_e( 'Filter', 'siteintelix' ); ?></button>
					</form>
				</div>

				<div class="sitx-card si-card">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-email-log-bulk-form">
						<input type="hidden" name="action" value="siteintelix_bulk_email_logs">
						<?php wp_nonce_field( 'siteintelix_bulk_email_logs' ); ?>
						<div class="sitx-email-log-bulk-actions">
							<select name="bulk_action" aria-label="<?php esc_attr_e( 'Bulk action', 'siteintelix' ); ?>">
								<option value=""><?php esc_html_e( 'Bulk actions', 'siteintelix' ); ?></option>
								<option value="delete"><?php esc_html_e( 'Delete selected', 'siteintelix' ); ?></option>
							</select>
							<button type="submit" class="sitx-btn sitx-btn--white sitx-btn--small si-button si-button--secondary si-button--small"><?php esc_html_e( 'Apply', 'siteintelix' ); ?></button>
						</div>
						<div class="si-table-wrap">
							<table class="widefat striped sitx-email-log-table si-table">
								<thead>
									<tr>
										<td class="manage-column column-cb check-column"><input type="checkbox" data-siteintelix-email-select-all aria-label="<?php esc_attr_e( 'Select all email logs', 'siteintelix' ); ?>"></td>
										<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
										<th><?php esc_html_e( 'Sent At', 'siteintelix' ); ?></th>
										<th><?php esc_html_e( 'To', 'siteintelix' ); ?></th>
										<th><?php esc_html_e( 'Subject', 'siteintelix' ); ?></th>
										<th><?php esc_html_e( 'Error', 'siteintelix' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $rows ) ) : ?>
										<tr>
											<td colspan="7">
												<div class="si-empty-state">
													<h2><?php esc_html_e( 'No email logs found.', 'siteintelix' ); ?></h2>
													<p><?php esc_html_e( 'Send a test email or adjust your filters to inspect captured messages.', 'siteintelix' ); ?></p>
												</div>
											</td>
										</tr>
									<?php else : ?>
										<?php foreach ( $rows as $row ) : ?>
											<?php
											$preview    = self::get_preview_payload( $row );
											$delete_url = wp_nonce_url(
												add_query_arg(
													array(
														'action' => 'siteintelix_delete_email_log',
														'log_id' => absint( $row->id ),
													),
													admin_url( 'admin-post.php' )
												),
												'siteintelix_delete_email_log_' . absint( $row->id )
											);
											?>
											<tr>
												<th scope="row" class="check-column"><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( (string) absint( $row->id ) ); ?>" aria-label="<?php esc_attr_e( 'Select email log', 'siteintelix' ); ?>"></th>
												<td><?php echo wp_kses_post( self::status_badge( $row->status ) ); ?></td>
												<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->sent_at ) ); ?></td>
												<td><?php echo esc_html( wp_trim_words( (string) $row->to_email, 8, '…' ) ); ?></td>
												<td><strong><?php echo esc_html( $row->subject ? $row->subject : __( '(No subject)', 'siteintelix' ) ); ?></strong></td>
												<td><?php echo esc_html( wp_trim_words( (string) $row->error_message, 16, '…' ) ); ?></td>
												<td class="sitx-email-log-actions">
													<button type="button" class="sitx-btn sitx-btn--white sitx-btn--small si-button si-button--secondary si-button--small" data-siteintelix-email-preview="<?php echo esc_attr( wp_json_encode( $preview ) ); ?>"><?php esc_html_e( 'View email', 'siteintelix' ); ?></button>
													<a class="sitx-btn sitx-btn--danger sitx-btn--small si-button si-button--danger si-button--small" href="<?php echo esc_url( $delete_url ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this email log?', 'siteintelix' ); ?>"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></a>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</form>
				</div>

				<?php self::render_pagination( $total, $per_page, $current_page, $status, $search ); ?>
			</div>

			<div class="sitx-email-modal" data-siteintelix-email-modal aria-hidden="true">
				<div class="sitx-email-modal__backdrop" data-siteintelix-email-modal-close></div>
				<div class="sitx-email-modal__panel" role="dialog" aria-modal="true" aria-labelledby="siteintelix-email-modal-title">
					<button type="button" class="sitx-email-modal__close" data-siteintelix-email-modal-close aria-label="<?php esc_attr_e( 'Close email preview', 'siteintelix' ); ?>">&times;</button>
					<h2 id="siteintelix-email-modal-title"></h2>
					<div class="sitx-email-modal__meta" data-siteintelix-email-modal-meta></div>
					<div class="sitx-email-modal__grid">
						<div><h3><?php esc_html_e( 'Headers', 'siteintelix' ); ?></h3><pre data-siteintelix-email-modal-headers></pre></div>
						<div><h3><?php esc_html_e( 'Attachments', 'siteintelix' ); ?></h3><pre data-siteintelix-email-modal-attachments></pre></div>
					</div>
					<div class="sitx-email-modal__tabs">
						<button type="button" class="is-active" data-siteintelix-email-tab="html"><?php esc_html_e( 'HTML view', 'siteintelix' ); ?></button>
						<button type="button" data-siteintelix-email-tab="source"><?php esc_html_e( 'Text/source view', 'siteintelix' ); ?></button>
					</div>
					<iframe class="sitx-email-modal__frame is-active" data-siteintelix-email-panel="html" title="<?php esc_attr_e( 'Email HTML preview', 'siteintelix' ); ?>" sandbox></iframe>
					<pre class="sitx-email-modal__source" data-siteintelix-email-panel="source"></pre>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings section.
	 *
	 * @param string[] $enabled_modules Enabled module IDs.
	 * @param string   $active_tab      Active module tab.
	 * @return void
	 */
	public static function render_settings_section( $enabled_modules, $active_tab = '' ) {
		if ( ! in_array( 'email_log', (array) $enabled_modules, true ) ) {
			return;
		}

		$settings = self::get_settings();
		?>
		<section class="sitx-settings-panel-tab <?php echo 'email_log' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-email-log-settings" data-siteintelix-settings-panel="email_log">
			<div class="sitx-settings-content-grid">
				<div class="sitx-settings-main">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form">
						<input type="hidden" name="action" value="siteintelix_save_email_log_settings">
						<?php wp_nonce_field( 'siteintelix_save_email_log_settings' ); ?>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Enable Email Logging', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Capture successful and failed wp_mail() events.', 'siteintelix' ); ?></p></div>
							<label class="sitx-toggle"><input type="checkbox" name="logging_enabled" value="1" <?php checked( 1, absint( $settings['logging_enabled'] ) ); ?>><span class="sitx-toggle__slider"></span></label>
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Retention Days', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Use 0 to keep email logs forever.', 'siteintelix' ); ?></p></div>
							<input type="number" name="retention_days" min="0" value="<?php echo esc_attr( (string) absint( $settings['retention_days'] ) ); ?>">
						</div>
						<div class="sitx-setting-row si-form-row">
							<div><h3><?php esc_html_e( 'Maximum Logs', 'siteintelix' ); ?></h3><p><?php esc_html_e( 'Use 0 for no maximum limit.', 'siteintelix' ); ?></p></div>
							<input type="number" name="max_logs" min="0" value="<?php echo esc_attr( (string) absint( $settings['max_logs'] ) ); ?>">
						</div>
						<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Email Settings', 'siteintelix' ); ?></button>
					</form>
				</div>
				<aside class="sitx-settings-sidebar">
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'About Email Log', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'Email Log captures outgoing WordPress emails, failures, recipients, headers, and message body previews.', 'siteintelix' ); ?></p>
					</div>
					<div class="sitx-side-card si-card">
						<h3><?php esc_html_e( 'Quick Actions', 'siteintelix' ); ?></h3>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-email-log' ) ); ?>"><?php esc_html_e( 'View Email Logs', 'siteintelix' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span></a>
					</div>
				</aside>
			</div>
		</section>
		<?php
	}

	/**
	 * Send a test email.
	 *
	 * @return void
	 */
	public static function handle_send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to send test emails.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_send_test_email' );

		$recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : get_option( 'admin_email' );
		if ( ! is_email( $recipient ) ) {
			$recipient = get_option( 'admin_email' );
		}

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject   = sprintf(
			/* translators: %s: site name. */
			__( 'SiteIntelix test email from %s', 'siteintelix' ),
			$site_name
		);
		$message   = self::get_test_email_html( $site_name );
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $recipient, $subject, $message, $headers );

		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-email-log', 'siteintelix_email_test_sent' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change email log settings.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_save_email_log_settings' );

		update_option(
			self::SETTINGS_OPTION,
			array(
				'logging_enabled' => isset( $_POST['logging_enabled'] ) ? 1 : 0,
				'retention_days'  => isset( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 0,
				'max_logs'        => isset( $_POST['max_logs'] ) ? absint( wp_unslash( $_POST['max_logs'] ) ) : 5000,
			)
		);

		self::maybe_apply_retention();

		wp_safe_redirect( add_query_arg( array( 'page' => 'siteintelix-settings', 'siteintelix_settings_saved' => '1', 'tab' => 'email_log' ), admin_url( 'admin.php' ) ) . '#siteintelix-email-log-settings' );
		exit;
	}

	/**
	 * Clear all email logs.
	 *
	 * @return void
	 */
	public static function handle_clear_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear email logs.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_clear_email_logs' );

		global $wpdb;
		$table_name = esc_sql( self::get_table_name() );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Clearing module-owned table.
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		wp_safe_redirect( admin_url( 'admin.php?page=siteintelix-email-log' ) );
		exit;
	}

	/**
	 * Delete a single email log row.
	 *
	 * @return void
	 */
	public static function handle_delete_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete email logs.', 'siteintelix' ) );
		}

		$log_id = isset( $_GET['log_id'] ) ? absint( wp_unslash( $_GET['log_id'] ) ) : 0;
		check_admin_referer( 'siteintelix_delete_email_log_' . $log_id );

		if ( $log_id > 0 ) {
			self::delete_logs_by_ids( array( $log_id ) );
		}

		wp_safe_redirect( self::get_email_log_redirect_url( array( 'siteintelix_email_deleted' => '1' ) ) );
		exit;
	}

	/**
	 * Handle bulk email log actions.
	 *
	 * @return void
	 */
	public static function handle_bulk_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update email logs.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_bulk_email_logs' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$log_ids     = isset( $_POST['log_ids'] ) && is_array( $_POST['log_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['log_ids'] ) ) : array();

		if ( 'delete' === $bulk_action && ! empty( $log_ids ) ) {
			self::delete_logs_by_ids( $log_ids );
			wp_safe_redirect( self::get_email_log_redirect_url( array( 'siteintelix_email_deleted' => '1' ) ) );
			exit;
		}

		wp_safe_redirect( self::get_email_log_redirect_url() );
		exit;
	}

	/**
	 * Delete email logs by IDs.
	 *
	 * @param int[] $log_ids Log IDs.
	 * @return void
	 */
	private static function delete_logs_by_ids( $log_ids ) {
		global $wpdb;

		$log_ids = array_values( array_filter( array_map( 'absint', (array) $log_ids ) ) );
		if ( empty( $log_ids ) ) {
			return;
		}

		$table_name   = esc_sql( self::get_table_name() );
		$placeholders = implode( ', ', array_fill( 0, count( $log_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting selected rows from module-owned log table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ({$placeholders})", $log_ids ) );
	}

	/**
	 * Build Email Log redirect URL preserving the current admin page.
	 *
	 * @param array<string,string> $extra_args Extra args.
	 * @return string
	 */
	private static function get_email_log_redirect_url( $extra_args = array() ) {
		$url = wp_get_referer();

		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=siteintelix-email-log' );
		}

		return add_query_arg( $extra_args, remove_query_arg( array( '_wpnonce', 'action', 'log_id' ), $url ) );
	}

	/**
	 * Apply retention settings.
	 *
	 * @return void
	 */
	public static function maybe_apply_retention() {
		global $wpdb;

		$settings   = self::get_settings();
		$table_name = esc_sql( self::get_table_name() );

		if ( ! empty( $settings['retention_days'] ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned retention cleanup.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)", current_time( 'mysql' ), absint( $settings['retention_days'] ) ) );
		}

		if ( ! empty( $settings['max_logs'] ) ) {
			$max_logs = absint( $settings['max_logs'] );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned count.
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

			if ( $count > $max_logs ) {
				$offset = max( 0, $max_logs - 1 );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned retention lookup.
				$cutoff = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1 OFFSET %d", $offset ) );
				if ( $cutoff ) {
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Module-owned retention cleanup.
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE id < %d", absint( $cutoff ) ) );
				}
			}
		}
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'siteintelix_email_logs';
	}

	/**
	 * Defaults.
	 *
	 * @return array<string,int>
	 */
	public static function get_default_settings() {
		return array(
			'logging_enabled' => 1,
			'retention_days'  => 0,
			'max_logs'        => 5000,
		);
	}

	/**
	 * Settings.
	 *
	 * @return array<string,int>
	 */
	public static function get_settings() {
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return array_merge( self::get_default_settings(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Render pagination.
	 *
	 * @param int    $total        Total rows.
	 * @param int    $per_page     Rows per page.
	 * @param int    $current_page Current page.
	 * @param string $status       Status filter.
	 * @param string $search       Search term.
	 * @return void
	 */
	private static function render_pagination( $total, $per_page, $current_page, $status, $search ) {
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$start       = $total > 0 ? ( ( $current_page - 1 ) * $per_page ) + 1 : 0;
		$end         = min( $total, $current_page * $per_page );

		echo '<div class="sitx-pagination">';
		printf(
			'<span class="sitx-pagination__summary">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: start row, 2: end row, 3: total rows. */
					__( 'Showing %1$d-%2$d of %3$d email logs', 'siteintelix' ),
					absint( $start ),
					absint( $end ),
					absint( $total )
				)
			)
		);

		if ( $total_pages > 1 ) {
			$base_url = add_query_arg(
				array_filter(
					array(
						'page'   => 'siteintelix-email-log',
						'status' => $status,
						's'      => $search,
					)
				),
				admin_url( 'admin.php' )
			);

			echo '<div class="sitx-pagination__links">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', $base_url ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'prev_text' => __( 'Previous', 'siteintelix' ),
						'next_text' => __( 'Next', 'siteintelix' ),
						'type'      => 'plain',
					)
				)
			);
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Render status badge.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_badge( $status ) {
		$status = self::sanitize_status( $status );
		$type   = 'failed' === $status ? 'danger' : 'success';
		return sprintf( '<span class="sitx-email-status sitx-email-status--%1$s si-badge si-badge--%2$s">%3$s</span>', esc_attr( $status ), esc_attr( $type ), esc_html( ucfirst( $status ) ) );
	}

	/**
	 * Build email preview payload for JS.
	 *
	 * @param object $row Email log row.
	 * @return array<string,string>
	 */
	private static function get_preview_payload( $row ) {
		return array(
			'subject'     => (string) $row->subject,
			'status'      => ucfirst( self::sanitize_status( $row->status ) ),
			'sentAt'      => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->sent_at ),
			'to'          => (string) $row->to_email,
			'headers'     => (string) $row->headers,
			'attachments' => (string) $row->attachments,
			'message'     => (string) $row->message,
			'error'       => (string) $row->error_message,
		);
	}

	/**
	 * Test email HTML.
	 *
	 * @param string $site_name Site name.
	 * @return string
	 */
	private static function get_test_email_html( $site_name ) {
		$home_url = home_url( '/' );

		return sprintf(
			'<!doctype html><html><body style="margin:0;background:#f3f6fb;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#0f172a;"><table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;padding:32px 0;"><tr><td align="center"><table role="presentation" width="620" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;"><tr><td style="background:#0f766e;color:#fff;padding:28px 34px;"><div style="font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;">SiteIntelix Email Log</div><h1 style="margin:8px 0 0;font-size:28px;line-height:1.25;">Test email delivered to WordPress mail flow</h1></td></tr><tr><td style="padding:34px;"><p>Hi there,</p><p>This is a polished HTML test email from <strong>%1$s</strong>. If you can see this in SiteIntelix Email Log, your WordPress email logging and preview flow is working.</p><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin:24px 0;"><strong>Status:</strong> Test message generated<br><strong>Source:</strong> wp_mail()<br><strong>Site:</strong> %1$s</div><p>Use this message to confirm HTML rendering, headers, logging status, and source preview behavior.</p><p><a href="%2$s" style="display:inline-block;background:#0f766e;color:#fff;text-decoration:none;border-radius:8px;padding:12px 18px;font-weight:800;">Visit site</a></p></td></tr><tr><td style="background:#f8fafc;color:#64748b;padding:18px 34px;font-size:13px;">Generated by SiteIntelix for email logging diagnostics.</td></tr></table></td></tr></table></body></html>',
			esc_html( $site_name ),
			esc_url( $home_url )
		);
	}

	/**
	 * Normalize value for storage.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function normalize_value( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return wp_json_encode( $value );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Clean payload.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_payload( $value ) {
		return wp_kses_post( (string) $value );
	}

	/**
	 * Clean email message while preserving renderable HTML.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_email_message( $value ) {
		$value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		return wp_check_invalid_utf8( $value );
	}

	/**
	 * Sanitize status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function sanitize_status( $status ) {
		$status = sanitize_key( $status );
		return in_array( $status, array( 'sent', 'failed' ), true ) ? $status : 'sent';
	}
}
