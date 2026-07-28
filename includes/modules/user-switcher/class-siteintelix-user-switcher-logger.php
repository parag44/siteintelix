<?php
/**
 * User Switcher audit logger.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and manages switch audit events.
 */
class SITEINTELIX_User_Switcher_Logger {

	const SCHEMA_OPTION           = 'siteintelix_user_switcher_schema_version';
	const SCHEMA_VERSION          = '2';
	const RETENTION_HOOK          = 'siteintelix_user_switcher_retention';
	const RETENTION_CONTINUE_HOOK = 'siteintelix_user_switcher_retention_continue';
	const RETENTION_BATCH_SIZE    = 250;

	/**
	 * Register audit hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::RETENTION_HOOK, array( __CLASS__, 'run_retention' ) );
		add_action( self::RETENTION_CONTINUE_HOOK, array( __CLASS__, 'run_retention' ) );

		if ( is_admin() ) {
			add_action( 'admin_post_siteintelix_user_switcher_delete_logs', array( __CLASS__, 'handle_delete_selected' ) );
			add_action( 'admin_post_siteintelix_user_switcher_clear_logs', array( __CLASS__, 'handle_clear_all' ) );
		}

		if ( self::SCHEMA_VERSION !== (string) get_option( self::SCHEMA_OPTION, '' ) && is_admin() ) {
			self::install();
		}
	}

	/**
	 * Create or update the audit table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			switch_id varchar(64) NOT NULL,
			original_user_id bigint(20) unsigned NOT NULL,
			target_user_id bigint(20) unsigned NOT NULL,
			started_at datetime NOT NULL,
			expires_at datetime NOT NULL,
			ended_at datetime NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			redirect_url text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY switch_id (switch_id),
			KEY original_user_id (original_user_id),
			KEY target_user_id (target_user_id),
			KEY status (status),
			KEY started_at (started_at),
			KEY created_at (created_at),
			KEY expires_at (expires_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Insert an active switch audit row.
	 *
	 * @param array<string,mixed> $data Switch metadata.
	 * @return int
	 */
	public static function start( $data ) {
		if ( empty( SITEINTELIX_User_Switcher_Settings::get_settings()['logging_enabled'] ) ) {
			return 0;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Audit events use a dedicated plugin-owned table.
		$wpdb->insert(
			self::table_name(),
			array(
				'switch_id'        => sanitize_text_field( $data['switch_id'] ),
				'original_user_id' => absint( $data['original_user_id'] ),
				'target_user_id'   => absint( $data['target_user_id'] ),
				'started_at'       => gmdate( 'Y-m-d H:i:s', absint( $data['started_at'] ) ),
				'expires_at'       => gmdate( 'Y-m-d H:i:s', absint( $data['expires_at'] ) ),
				'ended_at'         => null,
				'status'           => 'active',
				'ip_address'       => self::request_ip(),
				'user_agent'       => self::request_user_agent(),
				'redirect_url'     => esc_url_raw( $data['redirect_url'] ),
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Complete an active audit row.
	 *
	 * @param string $switch_id Switch identifier.
	 * @param string $status    Final status.
	 * @return void
	 */
	public static function finish( $switch_id, $status ) {
		if ( ! in_array( $status, array( 'returned', 'expired', 'logged_out', 'invalidated' ), true ) ) {
			$status = 'invalidated';
		}

		global $wpdb;
		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating one plugin-owned audit event.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is plugin-owned; all values use placeholders.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifier is the fixed plugin-owned audit table.
				'UPDATE ' . self::table_name() . ' SET status = %s, ended_at = %s, updated_at = %s WHERE switch_id = %s AND status = %s',
				$status,
				$now,
				$now,
				sanitize_text_field( $switch_id ),
				'active'
			)
		);
	}

	/**
	 * Query a paginated audit list.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array{rows:array<int,object>,total:int,page:int,per_page:int}
	 */
	public static function query( $filters ) {
		global $wpdb;

		$page     = max( 1, absint( isset( $filters['paged'] ) ? $filters['paged'] : 1 ) );
		$per_page = 20;
		$where    = array( '1=1' );
		$args     = array();

		if ( ! empty( $filters['status'] ) && in_array( $filters['status'], self::statuses(), true ) ) {
			$where[] = 'l.status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'l.started_at >= %s';
			$args[]  = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'l.started_at <= %s';
			$args[]  = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}
		if ( ! empty( $filters['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
			$where[] = '(ou.user_login LIKE %s OR ou.user_email LIKE %s OR ou.display_name LIKE %s OR tu.user_login LIKE %s OR tu.user_email LIKE %s OR tu.display_name LIKE %s)';
			$args    = array_merge( $args, array_fill( 0, 6, $like ) );
		}

		$where_sql = implode( ' AND ', $where );
		$from_sql  = ' FROM ' . self::table_name() . " l LEFT JOIN {$wpdb->users} ou ON ou.ID = l.original_user_id LEFT JOIN {$wpdb->users} tu ON tu.ID = l.target_user_id";
		$count_sql = 'SELECT COUNT(*)' . $from_sql . ' WHERE ' . $where_sql;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Clauses are internal allow-listed fragments; user values use placeholders.
		$count_sql = $args ? $wpdb->prepare( $count_sql, $args ) : $count_sql;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only paginated audit query assembled from internal clauses.
		$total = (int) $wpdb->get_var( $count_sql );

		$query_args   = $args;
		$query_args[] = $per_page;
		$query_args[] = ( $page - 1 ) * $per_page;
		$rows_sql     = 'SELECT l.*, ou.display_name AS original_name, ou.user_login AS original_login, tu.display_name AS target_name, tu.user_login AS target_login' . $from_sql . ' WHERE ' . $where_sql . ' ORDER BY l.id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Read-only paginated audit query; all values use placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $rows_sql, $query_args ) );

		return array(
			'rows'     => is_array( $rows ) ? $rows : array(),
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Delete selected audit rows.
	 *
	 * @return void
	 */
	public static function handle_delete_selected() {
		self::authorize_log_action( 'siteintelix_user_switcher_delete_logs' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- authorize_log_action() verifies the request nonce immediately above.
		$ids = isset( $_POST['log_ids'] ) && is_array( $_POST['log_ids'] ) ? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['log_ids'] ) ) ) ) : array();

		if ( $ids ) {
			global $wpdb;
			$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Plugin-owned table and generated integer placeholders.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table_name() . " WHERE id IN ({$placeholders})", $ids ) );
		}

		self::redirect_to_logs( array( 'siteintelix_user_switcher_deleted' => count( $ids ) ) );
	}

	/**
	 * Clear all audit rows.
	 *
	 * @return void
	 */
	public static function handle_clear_all() {
		self::authorize_log_action( 'siteintelix_user_switcher_clear_logs' );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Nonce-protected deletion from the plugin-owned audit table.
		$wpdb->query( 'DELETE FROM ' . self::table_name() );
		self::redirect_to_logs( array( 'siteintelix_user_switcher_cleared' => '1' ) );
	}

	/**
	 * Schedule daily retention.
	 *
	 * @return void
	 */
	public static function schedule_retention() {
		if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_HOOK );
		}
	}

	/**
	 * Clear retention jobs.
	 *
	 * @return void
	 */
	public static function unschedule_retention() {
		wp_clear_scheduled_hook( self::RETENTION_HOOK );
		wp_clear_scheduled_hook( self::RETENTION_CONTINUE_HOOK );
	}

	/**
	 * Mark expired sessions and delete a bounded batch of old rows.
	 *
	 * @return int
	 */
	public static function run_retention() {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled update of the plugin-owned audit table.
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is plugin-owned; all values use placeholders.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifier is the fixed plugin-owned audit table.
				'UPDATE ' . self::table_name() . ' SET status = %s, ended_at = %s, updated_at = %s WHERE status = %s AND expires_at < %s',
				'expired',
				$now,
				$now,
				'active',
				$now
			)
		);

		$days = absint( SITEINTELIX_User_Switcher_Settings::get_settings()['retention_days'] );
		/** Filter User Switcher audit retention days. */
		$days = absint( apply_filters( 'siteintelix_user_switcher_log_retention', $days ) );
		$days = min( SITEINTELIX_User_Switcher_Settings::MAX_RETENTION, max( 1, $days ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded scheduled deletion from the plugin-owned audit table.
		$result = $wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The table name is plugin-owned; all values use placeholders.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic identifier is the fixed plugin-owned audit table.
				'DELETE FROM ' . self::table_name() . ' WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY) ORDER BY id ASC LIMIT %d',
				$now,
				$days,
				self::RETENTION_BATCH_SIZE
			)
		);

		$deleted = false === $result ? 0 : (int) $result;
		if ( self::RETENTION_BATCH_SIZE === $deleted && ! wp_next_scheduled( self::RETENTION_CONTINUE_HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RETENTION_CONTINUE_HOOK );
		}
		return $deleted;
	}

	/**
	 * Audit statuses.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'active', 'returned', 'expired', 'logged_out', 'invalidated' );
	}

	/**
	 * Table name for current site.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'siteintelix_user_switch_logs';
	}

	/**
	 * Verify sensitive log actions.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private static function authorize_log_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage User Switcher logs.', 'siteintelix' ) );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect to activity logs.
	 *
	 * @param array<string,mixed> $args Extra args.
	 * @return void
	 */
	private static function redirect_to_logs( $args ) {
		$url = add_query_arg(
			array_merge(
				array(
					'page' => 'siteintelix-user-switcher',
					'view' => 'logs',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Request IP without trusting proxy headers.
	 *
	 * @return string
	 */
	private static function request_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? substr( $ip, 0, 45 ) : '';
	}

	/**
	 * Bounded request user agent.
	 *
	 * @return string
	 */
	private static function request_user_agent() {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return substr( $agent, 0, 255 );
	}
}
