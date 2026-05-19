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
			add_action( 'admin_post_siteintelix_bulk_cron_events', array( __CLASS__, 'handle_bulk_events' ) );
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

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$schedule = isset( $_GET['schedule'] ) ? sanitize_key( wp_unslash( $_GET['schedule'] ) ) : '';
		$events   = self::get_events( $search, $schedule );
		$all_events = self::get_events();
		$stats    = self::get_event_stats( $all_events );
		$schedules = wp_get_schedules();
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-cron-events-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-clock',
					'title'       => __( 'Cron Events', 'siteintelix' ),
					'description' => sprintf(
						/* translators: %s: current datetime. */
						__( 'System current time: %s', 'siteintelix' ),
						date_i18n( 'Y-m-d H:i:s', current_time( 'timestamp' ) )
					),
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

			<div class="siteintelix-container sitx-cron-container">
				<div id="siteintelix-notices-slot">
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_cron_ran'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Cron event executed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected cron hook was run manually.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_cron_deleted'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-trash"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Cron event removed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected scheduled event was unscheduled.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_cron_bulk'] ) ) : ?>
							<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Bulk action completed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php echo esc_html( sprintf(
								/* translators: %d: processed cron event count. */
								__( '%d cron events were processed.', 'siteintelix' ),
								absint( wp_unslash( $_GET['siteintelix_cron_bulk'] ) )
							) ); ?></p></div></div>
					<?php endif; ?>
				</div>

				<div class="sitx-cron-stats">
					<div class="si-card sitx-cron-stat sitx-cron-stat--total">
						<span><?php esc_html_e( 'Total Cron Events', 'siteintelix' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( absint( $stats['total'] ) ) ); ?></strong>
					</div>
					<div class="si-card sitx-cron-stat sitx-cron-stat--alert">
						<span class="sitx-cron-stat__label">
							<?php esc_html_e( 'Due Now / Overdue', 'siteintelix' ); ?>
							<?php if ( $stats['due'] > 0 ) : ?>
								<?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( __( 'Alert', 'siteintelix' ), 'danger', 'dashicons-warning' ) ); ?>
							<?php endif; ?>
						</span>
						<strong><?php echo esc_html( number_format_i18n( absint( $stats['due'] ) ) ); ?></strong>
					</div>
					<div class="si-card sitx-cron-stat sitx-cron-stat--soon">
						<span><?php esc_html_e( 'Next 24h', 'siteintelix' ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( absint( $stats['next_24h'] ) ) ); ?></strong>
					</div>
					<div class="si-card sitx-cron-stat">
						<span><?php esc_html_e( 'Most Frequent', 'siteintelix' ); ?></span>
						<strong><?php echo esc_html( $stats['most_frequent'] ); ?></strong>
					</div>
				</div>

				<form method="get" class="sitx-card sitx-cron-toolbar si-card">
					<input type="hidden" name="page" value="siteintelix-cron-events">
					<label class="sitx-cron-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Search the hook name', 'siteintelix' ); ?></span>
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search the hook name', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search cron hooks', 'siteintelix' ); ?>">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
					</label>
					<label class="sitx-cron-schedule-filter">
						<span><?php esc_html_e( 'Schedule Type', 'siteintelix' ); ?></span>
						<select name="schedule" aria-label="<?php esc_attr_e( 'Schedule type', 'siteintelix' ); ?>">
							<option value=""><?php esc_html_e( 'All schedules', 'siteintelix' ); ?></option>
							<option value="single" <?php selected( $schedule, 'single' ); ?>><?php esc_html_e( 'Single events', 'siteintelix' ); ?></option>
							<?php foreach ( $schedules as $key => $data ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $schedule, $key ); ?>><?php echo esc_html( isset( $data['display'] ) ? $data['display'] : $key ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button type="submit" class="si-button si-button--primary"><?php esc_html_e( 'Search', 'siteintelix' ); ?></button>
					<a class="si-button si-button--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-cron-events' ) ); ?>"><?php esc_html_e( 'Clear', 'siteintelix' ); ?></a>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-card sitx-cron-table-card si-card">
					<input type="hidden" name="action" value="siteintelix_bulk_cron_events">
					<?php wp_nonce_field( 'siteintelix_bulk_cron_events' ); ?>
					<div class="sitx-cron-bulkbar">
						<select name="bulk_action" aria-label="<?php esc_attr_e( 'Bulk action', 'siteintelix' ); ?>">
							<option value=""><?php esc_html_e( 'Bulk Actions', 'siteintelix' ); ?></option>
							<option value="run"><?php esc_html_e( 'Run selected', 'siteintelix' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete selected', 'siteintelix' ); ?></option>
						</select>
						<button type="submit" class="si-button si-button--secondary" data-siteintelix-confirm="<?php esc_attr_e( 'Apply this bulk action to the selected cron events?', 'siteintelix' ); ?>"><?php esc_html_e( 'Apply', 'siteintelix' ); ?></button>
					</div>

					<div class="si-table-wrap">
						<table class="widefat striped sitx-cron-table si-table">
							<thead>
								<tr>
										<th class="sitx-cron-check"><input type="checkbox" data-siteintelix-cron-select-all aria-label="<?php esc_attr_e( 'Select all cron events', 'siteintelix' ); ?>"></th>
										<th class="sitx-cron-index"><?php esc_html_e( '#', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Hook / Callback', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Recurrence', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Interval', 'siteintelix' ); ?></th>
									<th><?php esc_html_e( 'Next Run', 'siteintelix' ); ?></th>
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
										<?php self::render_event_row( $event, $index ); ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a cron event row.
	 *
	 * @param array<string,mixed> $event Event.
	 * @param int                 $index Index.
	 * @return void
	 */
	private static function render_event_row( $event, $index ) {
		$run_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'    => 'siteintelix_run_cron_event',
					'timestamp' => $event['timestamp'],
					'hook'      => $event['hook'],
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
					'hook'      => $event['hook'],
					'event_key' => $event['event_key'],
				),
				admin_url( 'admin-post.php' )
			),
			'siteintelix_delete_cron_event_' . $event['event_key']
		);
		$row_key = self::event_row_key( $event );
		$status  = self::get_event_status( $event['timestamp'] );
		?>
		<tr class="<?php echo esc_attr( 'is-' . $status['key'] ); ?>">
				<td class="sitx-cron-check"><input type="checkbox" name="cron_events[]" value="<?php echo esc_attr( $row_key ); ?>" aria-label="<?php echo esc_attr( sprintf(
					/* translators: %s: cron hook name. */
					__( 'Select cron event %s', 'siteintelix' ),
					$event['hook']
				) ); ?>"></td>
			<td class="sitx-cron-index si-cell-number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
			<td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( $status['label'], $status['type'] ) ); ?></td>
			<td>
				<code><?php echo esc_html( $event['hook'] ); ?></code>
				<button type="button" class="sitx-cron-copy" data-siteintelix-copy-text="<?php echo esc_attr( $event['hook'] ); ?>"><?php esc_html_e( 'Copy', 'siteintelix' ); ?></button>
				<?php echo $event['args_summary'] ? '<small>' . esc_html( $event['args_summary'] ) . '</small>' : ''; ?>
			</td>
			<td><?php echo esc_html( $event['schedule_label'] ); ?></td>
			<td><?php echo esc_html( self::format_interval( $event['interval'] ) ); ?></td>
			<td>
				<span><?php echo esc_html( date_i18n( 'Y-m-d H:i:s', $event['timestamp'] ) ); ?></span>
				<small><?php echo esc_html( self::format_countdown( $event['timestamp'] ) ); ?></small>
			</td>
			<td class="sitx-cron-actions-cell">
				<div class="sitx-cron-actions">
					<a class="si-button si-button--icon si-button--secondary" href="<?php echo esc_url( $run_url ); ?>" title="<?php esc_attr_e( 'Run now', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Run cron event now', 'siteintelix' ); ?>"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span></a>
					<a class="si-button si-button--icon si-button--danger" href="<?php echo esc_url( $delete_url ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this cron event?', 'siteintelix' ); ?>" title="<?php esc_attr_e( 'Delete event', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Delete cron event', 'siteintelix' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></a>
				</div>
			</td>
		</tr>
		<?php
	}

		/**
		 * Run bulk cron events.
		 *
		 * @return void
		 */
	public static function handle_bulk_events() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage cron events.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_bulk_cron_events' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$items       = isset( $_POST['cron_events'] ) && is_array( $_POST['cron_events'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cron_events'] ) ) : array();
		$processed   = 0;

		if ( ! in_array( $bulk_action, array( 'run', 'delete' ), true ) || empty( $items ) ) {
			wp_safe_redirect( self::get_redirect_url( array( 'siteintelix_cron_bulk' => '0' ) ) );
			exit;
		}

		foreach ( $items as $item ) {
			$event = self::get_event_from_row_key( $item );
			if ( ! $event ) {
				continue;
			}

			if ( 'run' === $bulk_action ) {
				do_action_ref_array( $event['hook'], $event['args'] );
			} else {
				wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );
			}

			$processed++;
		}

		wp_safe_redirect( self::get_redirect_url( array( 'siteintelix_cron_bulk' => $processed ) ) );
		exit;
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
		 * @param string $search          Search term.
		 * @param string $schedule_filter Schedule key.
		 * @return array<int,array<string,mixed>>
		 */
		private static function get_events( $search = '', $schedule_filter = '' ) {
			$cron            = _get_cron_array();
			$schedules       = wp_get_schedules();
			$events          = array();
			$search          = strtolower( trim( $search ) );
			$schedule_filter = sanitize_key( $schedule_filter );

			if ( ! is_array( $cron ) ) {
				return $events;
			}

			foreach ( $cron as $timestamp => $hooks ) {
				foreach ( (array) $hooks as $hook => $instances ) {
					foreach ( (array) $instances as $event_key => $event ) {
						$schedule       = isset( $event['schedule'] ) && $event['schedule'] ? sanitize_key( $event['schedule'] ) : 'single';
						$schedule_label = 'single' === $schedule ? __( 'Single', 'siteintelix' ) : ( isset( $schedules[ $schedule ]['display'] ) ? (string) $schedules[ $schedule ]['display'] : $schedule );
						$interval       = isset( $event['interval'] ) ? absint( $event['interval'] ) : ( isset( $schedules[ $schedule ]['interval'] ) ? absint( $schedules[ $schedule ]['interval'] ) : 0 );
						$args           = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
						$haystack       = strtolower( $hook . ' ' . $schedule . ' ' . $schedule_label . ' ' . wp_json_encode( $args ) );

						if ( '' !== $schedule_filter && $schedule_filter !== $schedule ) {
							continue;
						}

						if ( '' !== $search && false === strpos( $haystack, $search ) ) {
							continue;
						}

						$events[] = array(
							'timestamp'      => absint( $timestamp ),
							'hook'           => (string) $hook,
							'event_key'      => (string) $event_key,
							'args'           => $args,
							'args_summary'   => ! empty( $args ) ? wp_json_encode( $args ) : '',
							'schedule'       => $schedule,
							'schedule_label' => $schedule_label,
							'interval'       => $interval,
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
		 * Build aggregate metrics for the cron dashboard.
		 *
		 * @param array<int,array<string,mixed>> $events Events.
		 * @return array<string,mixed>
		 */
		private static function get_event_stats( $events ) {
			$now             = current_time( 'timestamp' );
			$next_day        = $now + DAY_IN_SECONDS;
			$due             = 0;
			$next_24h        = 0;
			$schedule_counts = array();

			foreach ( $events as $event ) {
				$timestamp = isset( $event['timestamp'] ) ? absint( $event['timestamp'] ) : 0;

				if ( $timestamp <= $now ) {
					$due++;
				} elseif ( $timestamp <= $next_day ) {
					$next_24h++;
				}

				$schedule_label = isset( $event['schedule_label'] ) ? (string) $event['schedule_label'] : __( 'Single', 'siteintelix' );
				if ( ! isset( $schedule_counts[ $schedule_label ] ) ) {
					$schedule_counts[ $schedule_label ] = 0;
				}
				$schedule_counts[ $schedule_label ]++;
			}

			arsort( $schedule_counts );

			return array(
				'total'         => count( $events ),
				'due'           => $due,
				'next_24h'      => $next_24h,
				'most_frequent' => ! empty( $schedule_counts ) ? (string) key( $schedule_counts ) : __( 'None', 'siteintelix' ),
			);
		}

		/**
		 * Get status metadata for a cron timestamp.
		 *
		 * @param int $timestamp Event timestamp.
		 * @return array{key:string,label:string,type:string}
		 */
		private static function get_event_status( $timestamp ) {
			$now  = current_time( 'timestamp' );
			$time = absint( $timestamp );

			if ( $time <= $now ) {
				return array(
					'key'   => 'overdue',
					'label' => __( 'Overdue', 'siteintelix' ),
					'type'  => 'danger',
				);
			}

			if ( $time <= $now + DAY_IN_SECONDS ) {
				return array(
					'key'   => 'soon',
					'label' => __( 'Soon', 'siteintelix' ),
					'type'  => 'warning',
				);
			}

			return array(
				'key'   => 'scheduled',
				'label' => __( 'Scheduled', 'siteintelix' ),
				'type'  => 'success',
			);
		}

		/**
		 * Format interval seconds.
		 *
		 * @param int $seconds Interval seconds.
		 * @return string
		 */
		private static function format_interval( $seconds ) {
			$seconds = absint( $seconds );

			if ( 0 === $seconds ) {
				return __( 'One-time', 'siteintelix' );
			}

			return human_time_diff( 0, $seconds );
		}

		/**
		 * Build a compact row key for bulk actions.
		 *
		 * @param array<string,mixed> $event Event.
		 * @return string
		 */
		private static function event_row_key( $event ) {
			return implode(
				'|',
				array(
					absint( $event['timestamp'] ),
					rawurlencode( (string) $event['hook'] ),
					rawurlencode( (string) $event['event_key'] ),
				)
			);
		}

		/**
		 * Resolve an event from a row key.
		 *
		 * @param string $key Row key.
		 * @return array<string,mixed>|false
		 */
		private static function get_event_from_row_key( $key ) {
			$parts = explode( '|', (string) $key );

			if ( 3 !== count( $parts ) ) {
				return false;
			}

			$timestamp = absint( $parts[0] );
			$hook      = sanitize_text_field( rawurldecode( $parts[1] ) );
			$event_key = sanitize_text_field( rawurldecode( $parts[2] ) );
			$cron      = _get_cron_array();

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

			return add_query_arg( $extra_args, remove_query_arg( array( '_wpnonce', 'action', 'timestamp', 'hook', 'event_key', 'bulk_action', 'cron_events' ), $url ) );
		}
	}
