<?php
/**
 * Cron Events module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lists and manages scheduled WordPress cron events.
 */
class SITEINTELIX_Cron_Events_Module {

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 35 );
			add_action( 'admin_post_siteintelix_run_cron_event', array( __CLASS__, 'handle_run_event' ) );
			add_action( 'admin_post_siteintelix_delete_cron_event', array( __CLASS__, 'handle_delete_event' ) );
		}
	}

	/**
	 * Register Cron Events submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'siteintelix',
			__( 'Cron Events', 'siteintelix' ),
			__( 'Cron Events', 'siteintelix' ),
			'manage_options',
			'siteintelix-cron-events',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render Cron Events page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view cron events.', 'siteintelix' ) );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$events = self::get_events( $search );
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-cron-events-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-clock',
					'title'       => __( 'Cron Events', 'siteintelix' ),
					'description' => __( 'Inspect, run, and remove scheduled WordPress cron events.', 'siteintelix' ),
					'badges'      => array(
						SITEINTELIX_Admin_UI::badge( 'v' . SITEINTELIX_VERSION, 'neutral' ),
					),
					'actions'     => array(
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Refresh', 'siteintelix' ),
								'url'     => admin_url( 'admin.php?page=siteintelix-cron-events' ),
								'variant' => 'secondary',
								'icon'    => 'dashicons-update',
							)
						),
					),
				)
			);
			?>

			<div class="siteintelix-container">
				<div id="siteintelix-notices-slot">
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_cron_ran'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Cron event executed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected cron hook was run manually.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_cron_deleted'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-trash"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Cron event removed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected scheduled event was unscheduled.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
				</div>

				<div class="sitx-card sitx-cron-summary-card si-card si-toolbar">
					<div>
						<h2><?php esc_html_e( 'Cron - scheduled events', 'siteintelix' ); ?></h2>
						<p><?php echo esc_html( sprintf( __( 'Current time: %s', 'siteintelix' ), date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) ) ); ?></p>
					</div>
					<form method="get" class="sitx-cron-search">
						<input type="hidden" name="page" value="siteintelix-cron-events">
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search hooks or schedules...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search cron events', 'siteintelix' ); ?>">
						<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Search', 'siteintelix' ); ?></button>
					</form>
				</div>

				<div class="sitx-card sitx-cron-table-card si-card">
					<div class="si-table-wrap">
						<table class="widefat striped sitx-cron-table si-table">
							<thead>
								<tr>
									<th><?php esc_html_e( '#', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Hook', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Date', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Time', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Countdown', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Schedule', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Interval (s)', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $events ) ) : ?>
									<tr>
										<td colspan="8">
											<div class="si-empty-state">
												<h2><?php esc_html_e( 'No cron events found.', 'siteintelix' ); ?></h2>
												<p><?php esc_html_e( 'Try a different search term or refresh the event list.', 'siteintelix' ); ?></p>
											</div>
										</td>
									</tr>
								<?php else : ?>
									<?php foreach ( $events as $index => $event ) : ?>
										<?php
										$run_url = wp_nonce_url(
											add_query_arg(
												array(
													'action'    => 'siteintelix_run_cron_event',
													'timestamp' => $event['timestamp'],
													'hook'      => rawurlencode( $event['hook'] ),
													'event_key' => $event['event_key'],
												),
												admin_url( 'admin-post.php' )
											),
											'siteintelix_run_cron_event_' . $event['event_key']
										);
										$delete_url = wp_nonce_url(
											add_query_arg(
												array(
													'action'    => 'siteintelix_delete_cron_event',
													'timestamp' => $event['timestamp'],
													'hook'      => rawurlencode( $event['hook'] ),
													'event_key' => $event['event_key'],
												),
												admin_url( 'admin-post.php' )
											),
											'siteintelix_delete_cron_event_' . $event['event_key']
										);
										$is_due = $event['timestamp'] <= current_time( 'timestamp' );
										?>
										<tr class="<?php echo $is_due ? 'is-due-now' : ''; ?>">
											<td class="si-cell-number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
											<td><code><?php echo esc_html( $event['hook'] ); ?></code><?php echo $event['args_summary'] ? '<small>' . esc_html( $event['args_summary'] ) . '</small>' : ''; ?></td>
											<td><?php echo esc_html( date_i18n( 'Y-m-d', $event['timestamp'] ) ); ?></td>
											<td><?php echo esc_html( date_i18n( 'H:i', $event['timestamp'] ) ); ?></td>
											<td><span class="si-badge <?php echo $is_due ? 'si-badge--warning' : 'si-badge--neutral'; ?>"><?php echo esc_html( self::format_countdown( $event['timestamp'] ) ); ?></span></td>
											<td><?php echo esc_html( $event['schedule'] ); ?></td>
											<td class="si-cell-number"><?php echo esc_html( $event['interval'] ? (string) $event['interval'] : '-' ); ?></td>
											<td class="sitx-cron-actions">
												<a class="sitx-btn sitx-btn--white sitx-btn--icon si-button si-button--icon si-button--secondary" href="<?php echo esc_url( $run_url ); ?>" title="<?php esc_attr_e( 'Run now', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Run cron event now', 'siteintelix' ); ?>"><span class="dashicons dashicons-controls-play"></span></a>
												<a class="sitx-btn sitx-btn--danger sitx-btn--icon si-button si-button--icon si-button--danger" href="<?php echo esc_url( $delete_url ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this cron event?', 'siteintelix' ); ?>" title="<?php esc_attr_e( 'Delete event', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Delete cron event', 'siteintelix' ); ?>"><span class="dashicons dashicons-trash"></span></a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Run a cron event manually.
	 *
	 * @return void
	 */
	public static function handle_run_event() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to run cron events.', 'siteintelix' ) );
		}

		$event = self::get_event_from_request( 'siteintelix_run_cron_event' );
		if ( $event ) {
			do_action_ref_array( $event['hook'], $event['args'] );
		}

		wp_safe_redirect( self::get_redirect_url( array( 'siteintelix_cron_ran' => '1' ) ) );
		exit;
	}

	/**
	 * Delete a scheduled cron event.
	 *
	 * @return void
	 */
	public static function handle_delete_event() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete cron events.', 'siteintelix' ) );
		}

		$event = self::get_event_from_request( 'siteintelix_delete_cron_event' );
		if ( $event ) {
			wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );
		}

		wp_safe_redirect( self::get_redirect_url( array( 'siteintelix_cron_deleted' => '1' ) ) );
		exit;
	}

	/**
	 * Get scheduled events.
	 *
	 * @param string $search Search term.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_events( $search = '' ) {
		$cron      = _get_cron_array();
		$schedules = wp_get_schedules();
		$events    = array();
		$search    = strtolower( trim( $search ) );

		if ( ! is_array( $cron ) ) {
			return $events;
		}

		foreach ( $cron as $timestamp => $hooks ) {
			foreach ( (array) $hooks as $hook => $instances ) {
				foreach ( (array) $instances as $event_key => $event ) {
					$schedule = isset( $event['schedule'] ) && $event['schedule'] ? (string) $event['schedule'] : __( 'Single event', 'siteintelix' );
					$interval = isset( $event['interval'] ) ? absint( $event['interval'] ) : ( isset( $schedules[ $schedule ]['interval'] ) ? absint( $schedules[ $schedule ]['interval'] ) : 0 );
					$args     = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
					$haystack = strtolower( $hook . ' ' . $schedule . ' ' . wp_json_encode( $args ) );

					if ( '' !== $search && false === strpos( $haystack, $search ) ) {
						continue;
					}

					$events[] = array(
						'timestamp'    => absint( $timestamp ),
						'hook'         => (string) $hook,
						'event_key'    => (string) $event_key,
						'args'         => $args,
						'args_summary' => ! empty( $args ) ? wp_json_encode( $args ) : '',
						'schedule'     => $schedule,
						'interval'     => $interval,
					);
				}
			}
		}

		usort(
			$events,
			function ( $a, $b ) {
				return $a['timestamp'] <=> $b['timestamp'];
			}
		);

		return $events;
	}

	/**
	 * Get an event from the request and verify nonce.
	 *
	 * @param string $action Action name.
	 * @return array<string,mixed>|false
	 */
	private static function get_event_from_request( $action ) {
		$timestamp = isset( $_GET['timestamp'] ) ? absint( wp_unslash( $_GET['timestamp'] ) ) : 0;
		$hook      = isset( $_GET['hook'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['hook'] ) ) ) : '';
		$event_key = isset( $_GET['event_key'] ) ? sanitize_text_field( wp_unslash( $_GET['event_key'] ) ) : '';

		check_admin_referer( $action . '_' . $event_key );

		$cron = _get_cron_array();
		if ( empty( $cron[ $timestamp ][ $hook ][ $event_key ] ) ) {
			return false;
		}

		$event = $cron[ $timestamp ][ $hook ][ $event_key ];
		$args  = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();

		return array(
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		);
	}

	/**
	 * Format countdown text.
	 *
	 * @param int $timestamp Event timestamp.
	 * @return string
	 */
	private static function format_countdown( $timestamp ) {
		$diff = absint( $timestamp ) - current_time( 'timestamp' );

		if ( $diff <= 0 ) {
			return __( 'Due now', 'siteintelix' );
		}

		return human_time_diff( current_time( 'timestamp' ), absint( $timestamp ) );
	}

	/**
	 * Build redirect URL.
	 *
	 * @param array<string,string> $extra_args Extra args.
	 * @return string
	 */
	private static function get_redirect_url( $extra_args = array() ) {
		$url = wp_get_referer();

		if ( ! $url ) {
			$url = admin_url( 'admin.php?page=siteintelix-cron-events' );
		}

		return add_query_arg( $extra_args, remove_query_arg( array( '_wpnonce', 'action', 'timestamp', 'hook', 'event_key' ), $url ) );
	}
}
