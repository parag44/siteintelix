<?php
/**
 * Transients Manager module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inspect, search, export, and safely delete WordPress transients.
 */
class SITEINTELIX_Transients_Manager_Module {

	const PER_PAGE            = 20;
	const LOG_OPTION          = 'siteintelix_tm_logs';
	const LAST_CLEANUP_OPTION = 'siteintelix_tm_last_cleanup';
	const MAX_PREVIEW_BYTES   = 120000;
	const BATCH_LIMIT         = 500;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() || ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 37 );
		add_action( 'admin_post_siteintelix_tm_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_siteintelix_tm_bulk', array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_siteintelix_tm_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'wp_ajax_siteintelix_tm_preview', array( __CLASS__, 'handle_preview' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			return;
		}

		add_submenu_page(
			'siteintelix',
			__( 'Transients Manager', 'siteintelix' ),
			__( 'Transients Manager', 'siteintelix' ),
			'manage_options',
			'siteintelix-transients-manager',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to manage transients.', 'siteintelix' ) );
		}

		$args       = self::get_request_args();
		$overview   = self::get_overview();
		$rows       = self::get_rows( $args );
		$total      = self::count_rows( $args );
		$pages      = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$logs       = self::get_logs();
		$cache_info = self::get_object_cache_info();
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap sitx-transients-page" id="siteintelix-transients-manager-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-database-view',
					'title'       => __( 'Transients Manager', 'siteintelix' ),
					'description' => __( 'Inspect, search, analyze, and safely manage WordPress transients and temporary cache data.', 'siteintelix' ),
					'badges'      => array(
						SITEINTELIX_Admin_UI::badge( 'v' . SITEINTELIX_VERSION, 'neutral' ),
						SITEINTELIX_Admin_UI::badge(
							sprintf(
								/* translators: %d: transient count. */
								_n( '%d Transient', '%d Transients', $overview['total'], 'siteintelix' ),
								absint( $overview['total'] )
							),
							'info',
							'dashicons-database'
						),
					),
				)
			);
			?>

			<div class="siteintelix-container sitx-transients-container">
				<?php self::render_notices(); ?>
				<?php self::render_overview_cards( $overview ); ?>

				<div class="sitx-transients-layout">
					<main class="sitx-transients-main">
						<?php self::render_filters( $args ); ?>
						<?php self::render_table( $rows, $args, $total, $pages ); ?>
					</main>
					<aside class="sitx-transients-sidebar">
						<?php self::render_quick_actions(); ?>
						<?php self::render_object_cache_card( $cache_info ); ?>
						<?php self::render_help_card(); ?>
						<?php self::render_logs_card( $logs ); ?>
					</aside>
				</div>
				<?php self::render_view_modal(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render notices from query args.
	 *
	 * @return void
	 */
	private static function render_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only status notices.
		if ( isset( $_GET['siteintelix_tm_deleted'] ) ) {
			self::alert( 'success', __( 'Transient deleted.', 'siteintelix' ), __( 'The selected transient and its timeout pair were removed.', 'siteintelix' ) );
		}

		if ( isset( $_GET['siteintelix_tm_bulk_deleted'] ) ) {
			$count = absint( wp_unslash( $_GET['siteintelix_tm_bulk_deleted'] ) );
			self::alert(
				'success',
				__( 'Cleanup completed.', 'siteintelix' ),
				sprintf(
					/* translators: %d: deleted transient count. */
					_n( '%d transient was deleted.', '%d transients were deleted.', $count, 'siteintelix' ),
					$count
				)
			);
		}

		if ( isset( $_GET['siteintelix_tm_error'] ) ) {
			self::alert( 'error', __( 'Transient action failed.', 'siteintelix' ), sanitize_text_field( wp_unslash( $_GET['siteintelix_tm_error'] ) ) );
		}
		// phpcs:enable
	}

	/**
	 * Render alert.
	 *
	 * @param string $type    Alert type.
	 * @param string $title   Title.
	 * @param string $message Message.
	 * @return void
	 */
	private static function alert( $type, $title, $message ) {
		$type = sanitize_key( $type );
		$icon = 'error' === $type ? 'dashicons-warning' : 'dashicons-yes-alt';
		?>
		<div class="sitx-alert sitx-alert--<?php echo esc_attr( $type ); ?>">
			<div class="sitx-alert__icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span></div>
			<div class="sitx-alert__content">
				<strong class="sitx-alert__title"><?php echo esc_html( $title ); ?></strong>
				<p class="sitx-alert__msg"><?php echo esc_html( $message ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render overview cards.
	 *
	 * @param array<string,mixed> $overview Overview.
	 * @return void
	 */
	private static function render_overview_cards( $overview ) {
		$cards = array(
			array( __( 'Total Transients', 'siteintelix' ), number_format_i18n( absint( $overview['total'] ) ) ),
			array( __( 'Expired Transients', 'siteintelix' ), number_format_i18n( absint( $overview['expired'] ) ) ),
			array( __( 'Site Transients', 'siteintelix' ), number_format_i18n( absint( $overview['site'] ) ) ),
			array( __( 'Largest Transient', 'siteintelix' ), self::format_bytes( absint( $overview['largest'] ) ) ),
			array( __( 'Storage Estimate', 'siteintelix' ), self::format_bytes( absint( $overview['storage'] ) ) ),
			array( __( 'Last Cleanup', 'siteintelix' ), $overview['last_cleanup'] ? date_i18n( 'M j, Y g:i A', absint( $overview['last_cleanup'] ) ) : __( 'Never', 'siteintelix' ) ),
		);
		?>
		<div class="sitx-transients-stats">
			<?php foreach ( $cards as $card ) : ?>
				<div class="si-card sitx-db-stat-card sitx-transients-stat">
					<span><?php echo esc_html( $card[0] ); ?></span>
					<strong><?php echo esc_html( $card[1] ); ?></strong>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render filters.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return void
	 */
	private static function render_filters( $args ) {
		?>
		<form class="si-card sitx-transients-toolbar" method="get">
			<input type="hidden" name="page" value="siteintelix-transients-manager">
			<label class="sitx-settings-search sitx-transients-search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search transients', 'siteintelix' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search transient name...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search transient name', 'siteintelix' ); ?>">
			</label>
			<select name="filter" aria-label="<?php esc_attr_e( 'Filter transients', 'siteintelix' ); ?>">
				<?php foreach ( self::filter_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $args['filter'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="sort" aria-label="<?php esc_attr_e( 'Sort transients', 'siteintelix' ); ?>">
				<?php foreach ( self::sort_options() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $args['sort'], $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button class="si-button si-button--primary" type="submit"><span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e( 'Filter', 'siteintelix' ); ?></button>
			<a class="si-button si-button--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-transients-manager' ) ); ?>"><?php esc_html_e( 'Reset', 'siteintelix' ); ?></a>
		</form>
		<?php
	}

	/**
	 * Render table.
	 *
	 * @param array<int,array<string,mixed>> $rows  Rows.
	 * @param array<string,mixed>            $args  Args.
	 * @param int                            $total Total.
	 * @param int                            $pages Pages.
	 * @return void
	 */
	private static function render_table( $rows, $args, $total, $pages ) {
		?>
		<form class="si-card sitx-transients-table-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="siteintelix_tm_bulk">
			<?php wp_nonce_field( 'siteintelix_tm_bulk' ); ?>
			<div class="sitx-transients-bulkbar">
				<select name="bulk_action" aria-label="<?php esc_attr_e( 'Bulk action', 'siteintelix' ); ?>">
					<option value=""><?php esc_html_e( 'Bulk actions', 'siteintelix' ); ?></option>
					<option value="delete_selected"><?php esc_html_e( 'Delete selected', 'siteintelix' ); ?></option>
					<option value="delete_expired"><?php esc_html_e( 'Delete expired', 'siteintelix' ); ?></option>
					<option value="delete_all"><?php esc_html_e( 'Delete all transients', 'siteintelix' ); ?></option>
					<option value="delete_all_site"><?php esc_html_e( 'Delete all site transients', 'siteintelix' ); ?></option>
					<option value="export_selected"><?php esc_html_e( 'Export selected', 'siteintelix' ); ?></option>
				</select>
				<input type="text" name="pattern" placeholder="<?php esc_attr_e( 'Keyword/pattern cleanup...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Delete by keyword or pattern', 'siteintelix' ); ?>">
				<button class="si-button si-button--secondary" type="submit" data-siteintelix-confirm="<?php esc_attr_e( 'Run this transient bulk action?', 'siteintelix' ); ?>"><?php esc_html_e( 'Apply', 'siteintelix' ); ?></button>
				<span class="sitx-transients-count">
					<?php
					printf(
						/* translators: %d: transient count. */
						esc_html__( '%d found', 'siteintelix' ),
						absint( $total )
					);
					?>
				</span>
			</div>
			<div class="si-table-wrap sitx-transients-table-wrap">
				<table class="widefat striped si-table sitx-transients-table">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" data-siteintelix-transients-select-all aria-label="<?php esc_attr_e( 'Select all transients', 'siteintelix' ); ?>"></th>
							<th><?php esc_html_e( 'Transient Name', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Type', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Size', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Expiration', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Autoload', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Detected', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $rows ) ) : ?>
							<tr>
								<td colspan="9">
									<?php SITEINTELIX_Admin_UI::empty_state( __( 'No transients found.', 'siteintelix' ), __( 'Try a different search, filter, or cleanup action.', 'siteintelix' ), 'dashicons-database-view' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $rows as $row ) : ?>
								<?php self::render_table_row( $row ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<?php self::render_pagination( $args, $pages ); ?>
		</form>
		<?php
	}

	/**
	 * Render one table row.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return void
	 */
	private static function render_table_row( $row ) {
		$key          = self::row_key( $row['type'], $row['name'] );
		$delete_url   = wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_tm_delete', 'type' => $row['type'], 'name' => $row['name'] ), admin_url( 'admin-post.php' ) ), 'siteintelix_tm_delete_' . $key );
		$json_url     = wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_tm_export', 'type' => $row['type'], 'name' => $row['name'], 'format' => 'json' ), admin_url( 'admin-post.php' ) ), 'siteintelix_tm_export_' . $key );
		$status_type  = 'expired' === $row['status'] ? 'danger' : ( 'no_expiration' === $row['status'] ? 'warning' : 'success' );
		$status_label = 'no_expiration' === $row['status'] ? __( 'No expiration', 'siteintelix' ) : ucwords( (string) $row['status'] );
		$warnings     = self::get_row_warnings( $row );
		?>
		<tr>
				<th class="check-column"><input type="checkbox" name="transients[]" value="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( sprintf(
					/* translators: %s: transient name. */
					__( 'Select %s', 'siteintelix' ),
					$row['name']
				) ); ?>"></th>
			<td>
				<strong class="sitx-transient-name"><?php echo esc_html( $row['name'] ); ?></strong>
				<?php foreach ( $warnings as $warning ) : ?>
					<span class="si-badge si-badge--warning sitx-transient-warning"><?php echo esc_html( $warning ); ?></span>
				<?php endforeach; ?>
			</td>
			<td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( 'site_transient' === $row['type'] ? __( 'Site transient', 'siteintelix' ) : __( 'Transient', 'siteintelix' ), 'neutral' ) ); ?></td>
			<td class="si-cell-number"><?php echo esc_html( self::format_bytes( absint( $row['size'] ) ) ); ?></td>
			<td><?php echo esc_html( self::format_expiration( $row['timeout'] ) ); ?></td>
			<td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( $status_label, $status_type ) ); ?></td>
			<td><?php echo esc_html( $row['autoload'] ); ?></td>
			<td><?php esc_html_e( 'Stored option', 'siteintelix' ); ?></td>
			<td class="sitx-transient-actions">
				<button type="button" class="si-button si-button--ghost si-button--compact" data-siteintelix-transient-view data-type="<?php echo esc_attr( $row['type'] ); ?>" data-name="<?php echo esc_attr( $row['name'] ); ?>"><?php esc_html_e( 'View', 'siteintelix' ); ?></button>
				<a class="si-button si-button--ghost si-button--compact" href="<?php echo esc_url( $json_url ); ?>"><?php esc_html_e( 'Export', 'siteintelix' ); ?></a>
				<button type="button" class="si-button si-button--ghost si-button--compact" data-siteintelix-copy-text="<?php echo esc_attr( $row['name'] ); ?>"><?php esc_html_e( 'Copy', 'siteintelix' ); ?></button>
				<a class="si-button si-button--danger si-button--compact" href="<?php echo esc_url( $delete_url ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this transient?', 'siteintelix' ); ?>"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></a>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render pagination.
	 *
	 * @param array<string,mixed> $args  Args.
	 * @param int                 $pages Total pages.
	 * @return void
	 */
	private static function render_pagination( $args, $pages ) {
		if ( $pages <= 1 ) {
			return;
		}

		$current = max( 1, absint( $args['paged'] ) );
		?>
		<nav class="sitx-db-pagination sitx-transients-pagination" aria-label="<?php esc_attr_e( 'Transients pagination', 'siteintelix' ); ?>">
			<?php if ( $current > 1 ) : ?>
				<a class="si-button si-button--secondary si-button--compact" href="<?php echo esc_url( self::page_url( array_merge( $args, array( 'paged' => $current - 1 ) ) ) ); ?>"><?php esc_html_e( 'Previous', 'siteintelix' ); ?></a>
			<?php endif; ?>
			<span><?php echo esc_html( sprintf( '%d / %d', $current, $pages ) ); ?></span>
			<?php if ( $current < $pages ) : ?>
				<a class="si-button si-button--secondary si-button--compact" href="<?php echo esc_url( self::page_url( array_merge( $args, array( 'paged' => $current + 1 ) ) ) ); ?>"><?php esc_html_e( 'Next', 'siteintelix' ); ?></a>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * Render transient view card.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return void
	 */
	private static function render_view_modal() {
		?>
		<div class="sitx-transient-modal" data-siteintelix-transient-modal hidden role="dialog" aria-modal="true" aria-labelledby="sitx-transient-modal-title">
			<div class="sitx-transient-modal__surface">
				<header><div><h2 id="sitx-transient-modal-title"><?php esc_html_e( 'Transient Details', 'siteintelix' ); ?></h2><p data-transient-modal-name></p></div><button type="button" class="si-button si-button--ghost" data-transient-modal-close aria-label="<?php esc_attr_e( 'Close transient details', 'siteintelix' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>
				<div class="sitx-transient-modal__status" data-transient-modal-status><?php esc_html_e( 'Loading transient data…', 'siteintelix' ); ?></div>
				<div data-transient-modal-content hidden>
					<div class="sitx-transient-meta-grid"><div><span><?php esc_html_e( 'Type', 'siteintelix' ); ?></span><strong data-transient-modal-type></strong></div><div><span><?php esc_html_e( 'Expiration', 'siteintelix' ); ?></span><strong data-transient-modal-expiration></strong></div><div><span><?php esc_html_e( 'Size estimate', 'siteintelix' ); ?></span><strong data-transient-modal-size></strong></div></div>
					<details open><summary><?php esc_html_e( 'Formatted preview', 'siteintelix' ); ?></summary><pre class="sitx-code-preview" data-transient-modal-formatted></pre></details>
					<details><summary><?php esc_html_e( 'Raw value', 'siteintelix' ); ?></summary><pre class="sitx-code-preview" data-transient-modal-raw></pre></details>
					<p class="description" data-transient-modal-truncated hidden><?php esc_html_e( 'Preview truncated for safety. Export the transient to download its full value.', 'siteintelix' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	/** Return one transient preview to an authenticated administrator. */
	public static function handle_preview() {
		self::require_global_tools();
		check_ajax_referer( 'siteintelix_tm_preview', 'nonce' );
		$name = self::sanitize_name( self::request_value( 'name' ) );
		$type = self::sanitize_type( self::request_value( 'type' ) );
		$item = $name && $type ? self::fetch_item( $type, $name ) : null;
		if ( ! $item ) {
			wp_send_json_error( array( 'message' => __( 'The transient could not be found.', 'siteintelix' ) ), 404 );
		}
		$serialized = maybe_serialize( $item['value'] );
		wp_send_json_success(
			array(
				'name'       => $item['name'],
				'type'       => 'site_transient' === $item['type'] ? __( 'Site transient', 'siteintelix' ) : __( 'Transient', 'siteintelix' ),
				'expiration' => self::format_expiration( $item['timeout'] ),
				'size'       => self::format_bytes( strlen( $serialized ) ),
				'formatted'  => substr( self::format_value_preview( $item['value'] ), 0, self::MAX_PREVIEW_BYTES ),
				'raw'        => substr( $serialized, 0, self::MAX_PREVIEW_BYTES ),
				'truncated'  => strlen( $serialized ) > self::MAX_PREVIEW_BYTES,
			)
		);
	}

	/**
	 * Render quick actions.
	 *
	 * @return void
	 */
	private static function render_quick_actions() {
		$actions = array(
			'expired'     => array( __( 'Delete expired transients', 'siteintelix' ), 'dashicons-trash' ),
			'woocommerce' => array( __( 'Delete WooCommerce transients', 'siteintelix' ), 'dashicons-store' ),
			'site_health' => array( __( 'Delete Site Health transients', 'siteintelix' ), 'dashicons-heart' ),
			'updates'     => array( __( 'Delete update transients', 'siteintelix' ), 'dashicons-update' ),
			'object_cache' => array( __( 'Clear object cache', 'siteintelix' ), 'dashicons-cloud' ),
			'rewrite'     => array( __( 'Flush rewrite transient cache', 'siteintelix' ), 'dashicons-admin-links' ),
		);
		?>
		<section class="si-card sitx-card">
			<h2><?php esc_html_e( 'Quick Actions', 'siteintelix' ); ?></h2>
			<div class="sitx-transients-quick-actions">
				<?php foreach ( $actions as $action => $data ) : ?>
					<a class="si-button si-button--secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_tm_bulk', 'bulk_action' => $action ), admin_url( 'admin-post.php' ) ), 'siteintelix_tm_bulk' ) ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Run this cleanup action?', 'siteintelix' ); ?>">
						<span class="dashicons <?php echo esc_attr( $data[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $data[0] ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Render object cache card.
	 *
	 * @param array<string,mixed> $cache_info Cache info.
	 * @return void
	 */
	private static function render_object_cache_card( $cache_info ) {
		?>
		<section class="si-card sitx-card">
			<h2><?php esc_html_e( 'Object Cache', 'siteintelix' ); ?></h2>
			<ul class="sitx-transients-info-list">
				<li><span><?php esc_html_e( 'Persistent cache', 'siteintelix' ); ?></span><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( $cache_info['persistent'] ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), $cache_info['persistent'] ? 'success' : 'neutral' ) ); ?></li>
				<li><span><?php esc_html_e( 'Drop-in', 'siteintelix' ); ?></span><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( $cache_info['dropin'] ? __( 'Detected', 'siteintelix' ) : __( 'Not found', 'siteintelix' ), $cache_info['dropin'] ? 'info' : 'neutral' ) ); ?></li>
				<li><span><?php esc_html_e( 'Provider hints', 'siteintelix' ); ?></span><strong><?php echo esc_html( $cache_info['provider'] ); ?></strong></li>
			</ul>
		</section>
		<?php
	}

	/**
	 * Render help card.
	 *
	 * @return void
	 */
	private static function render_help_card() {
		?>
		<section class="si-card sitx-card">
			<h2><?php esc_html_e( 'What are transients?', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'Transients are temporary WordPress cache records used by core, plugins, and themes to avoid repeated expensive work.', 'siteintelix' ); ?></p>
			<p><?php esc_html_e( 'They can improve performance, but expired, oversized, or autoloaded transients may add database bloat and slow option loading.', 'siteintelix' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Render logs card.
	 *
	 * @param array<int,array<string,mixed>> $logs Logs.
	 * @return void
	 */
	private static function render_logs_card( $logs ) {
		?>
		<section class="si-card sitx-card">
			<h2><?php esc_html_e( 'Recent Cleanup Log', 'siteintelix' ); ?></h2>
			<?php if ( empty( $logs ) ) : ?>
				<p class="sitx-section-desc"><?php esc_html_e( 'No transient actions logged yet.', 'siteintelix' ); ?></p>
			<?php else : ?>
				<ul class="sitx-safe-log sitx-transients-log">
					<?php foreach ( array_slice( $logs, 0, 8 ) as $entry ) : ?>
						<li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['action'] ) ) ); ?></strong><span><?php echo esc_html( date_i18n( 'Y-m-d H:i', absint( $entry['created_at'] ) ) ); ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * Handle single delete.
	 *
	 * @return void
	 */
	public static function handle_delete() {
		self::require_global_tools();

		$type = self::sanitize_type( self::request_value( 'type' ) );
		$name = self::sanitize_name( self::request_value( 'name' ) );
		check_admin_referer( 'siteintelix_tm_delete_' . self::row_key( $type, $name ) );

		if ( '' === $name || ! $type ) {
			self::redirect_error( __( 'Invalid transient request.', 'siteintelix' ) );
		}

		$deleted = self::delete_transient_item( $type, $name );
		self::log_event( 'transient_deleted', $name, array( 'type' => $type, 'deleted' => $deleted ) );

		wp_safe_redirect( add_query_arg( 'siteintelix_tm_deleted', '1', self::page_url() ) );
		exit;
	}

	/**
	 * Handle bulk and quick actions.
	 *
	 * @return void
	 */
	public static function handle_bulk() {
		self::require_global_tools();
		check_admin_referer( 'siteintelix_tm_bulk' );

		$action = sanitize_key( self::request_value( 'bulk_action' ) );
		$count  = 0;

		if ( 'delete_selected' === $action ) {
			$items = isset( $_POST['transients'] ) && is_array( $_POST['transients'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['transients'] ) ) : array();
			$count = self::delete_selected( $items );
			self::log_event( 'bulk_cleanup', 'selected', array( 'deleted' => $count ) );
		} elseif ( 'export_selected' === $action ) {
			self::export_selected();
			return;
		} elseif ( 'delete_expired' === $action || 'expired' === $action ) {
			$count = self::delete_by_filter( 'expired' );
			self::log_event( 'expired_cleanup', 'expired', array( 'deleted' => $count ) );
		} elseif ( 'delete_all' === $action ) {
			$count = self::delete_by_filter( 'all' );
			self::log_event( 'bulk_cleanup', 'all', array( 'deleted' => $count ) );
		} elseif ( 'delete_all_site' === $action ) {
			$count = self::delete_by_filter( 'site_transients' );
			self::log_event( 'bulk_cleanup', 'site_transients', array( 'deleted' => $count ) );
		} elseif ( 'woocommerce' === $action ) {
			$count = self::delete_by_keyword( array( 'wc_', 'woocommerce' ) );
			self::log_event( 'pattern_cleanup', 'woocommerce', array( 'deleted' => $count ) );
		} elseif ( 'site_health' === $action ) {
			$count = self::delete_by_keyword( array( 'site_health', 'health-check', 'health_check' ) );
			self::log_event( 'pattern_cleanup', 'site_health', array( 'deleted' => $count ) );
		} elseif ( 'updates' === $action ) {
			$count = self::delete_known_update_transients();
			self::log_event( 'bulk_cleanup', 'updates', array( 'deleted' => $count ) );
		} elseif ( 'object_cache' === $action ) {
			if ( function_exists( 'wp_cache_flush' ) ) {
				wp_cache_flush();
				$count = 1;
			}
			self::log_event( 'object_cache_detected', 'object_cache_flush', array( 'flushed' => (bool) $count ) );
		} elseif ( 'rewrite' === $action ) {
			delete_transient( 'rewrite_rules' );
			$count = 1;
			self::log_event( 'bulk_cleanup', 'rewrite', array( 'deleted' => $count ) );
		}

		$pattern = self::sanitize_name( self::request_value( 'pattern' ) );
		if ( '' !== $pattern ) {
			$count = self::delete_by_keyword( array( $pattern ) );
			self::log_event( 'pattern_cleanup', $pattern, array( 'deleted' => $count ) );
		}

		update_option( self::LAST_CLEANUP_OPTION, time(), false );
		wp_safe_redirect( add_query_arg( 'siteintelix_tm_bulk_deleted', absint( $count ), self::page_url() ) );
		exit;
	}

	/**
	 * Handle single export.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::require_global_tools();

		$type   = self::sanitize_type( self::request_value( 'type' ) );
		$name   = self::sanitize_name( self::request_value( 'name' ) );
		$format = 'txt' === sanitize_key( self::request_value( 'format' ) ) ? 'txt' : 'json';
		check_admin_referer( 'siteintelix_tm_export_' . self::row_key( $type, $name ) );

		$item = self::fetch_item( $type, $name );
		if ( ! $item ) {
			wp_die( esc_html__( 'Transient was not found.', 'siteintelix' ) );
		}

		self::log_event( 'transient_exported', $name, array( 'type' => $type, 'format' => $format ) );
		self::stream_export( array( $item ), $format, 'siteintelix-transient-' . sanitize_file_name( $name ) );
	}

	/**
	 * Get request args.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		return array(
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'filter' => isset( $_GET['filter'] ) && isset( self::filter_options()[ sanitize_key( wp_unslash( $_GET['filter'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all',
			'sort'   => isset( $_GET['sort'] ) && isset( self::sort_options()[ sanitize_key( wp_unslash( $_GET['sort'] ) ) ] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'name',
			'paged'  => isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1,
		);
		// phpcs:enable
	}

	/**
	 * Filter options.
	 *
	 * @return array<string,string>
	 */
	private static function filter_options() {
		return array(
			'all'             => __( 'All transients', 'siteintelix' ),
			'expired'         => __( 'Expired', 'siteintelix' ),
			'active'          => __( 'Active', 'siteintelix' ),
			'no_expiration'   => __( 'No expiration', 'siteintelix' ),
			'large'           => __( 'Large transients', 'siteintelix' ),
			'site_transients' => __( 'Site transients', 'siteintelix' ),
			'regular'         => __( 'Regular transients', 'siteintelix' ),
		);
	}

	/**
	 * Sort options.
	 *
	 * @return array<string,string>
	 */
	private static function sort_options() {
		return array(
			'name'       => __( 'Name', 'siteintelix' ),
			'size'       => __( 'Size', 'siteintelix' ),
			'expiration' => __( 'Expiration', 'siteintelix' ),
			'newest'     => __( 'Newest expiry', 'siteintelix' ),
			'oldest'     => __( 'Oldest expiry', 'siteintelix' ),
		);
	}

	/**
	 * Get transient rows.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_rows( $args ) {
		$limit  = ( absint( $args['paged'] ) * self::PER_PAGE );
		$rows   = array();
		$sources = self::get_sources_for_filter( $args['filter'] );

		foreach ( $sources as $source ) {
			$rows = array_merge( $rows, self::query_source_rows( $source, $args, $limit ) );
		}

		usort(
			$rows,
			static function ( $a, $b ) use ( $args ) {
				if ( 'size' === $args['sort'] ) {
					return $b['size'] <=> $a['size'];
				}

				if ( in_array( $args['sort'], array( 'expiration', 'oldest' ), true ) ) {
					return absint( $a['timeout'] ) <=> absint( $b['timeout'] );
				}

				if ( 'newest' === $args['sort'] ) {
					return absint( $b['timeout'] ) <=> absint( $a['timeout'] );
				}

				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);

		$offset = ( max( 1, absint( $args['paged'] ) ) - 1 ) * self::PER_PAGE;
		return array_slice( $rows, $offset, self::PER_PAGE );
	}

	/**
	 * Query one source.
	 *
	 * @param array<string,string> $source Source.
	 * @param array<string,mixed>  $args   Args.
	 * @param int                  $limit  Limit.
	 * @return array<int,array<string,mixed>>
	 */
	private static function query_source_rows( $source, $args, $limit ) {
		global $wpdb;

		$where = self::build_where( $source, $args );
		$order = 'name ASC';
		if ( 'size' === $args['sort'] ) {
			$order = 'size DESC';
		} elseif ( in_array( $args['sort'], array( 'expiration', 'oldest' ), true ) ) {
			$order = 'timeout_num ASC';
		} elseif ( 'newest' === $args['sort'] ) {
			$order = 'timeout_num DESC';
		}

		$sql = "
			SELECT
				%s AS transient_type,
				SUBSTRING(value_row.{$source['key_col']}, %d) AS name,
				value_row.{$source['value_col']} AS transient_value,
				LENGTH(value_row.{$source['value_col']}) AS size,
				COALESCE(timeout_row.{$source['value_col']}, '') AS timeout_value,
				CAST(COALESCE(timeout_row.{$source['value_col']}, '0') AS UNSIGNED) AS timeout_num,
				{$source['autoload_expr']} AS autoload
			FROM {$source['table']} value_row
			LEFT JOIN {$source['table']} timeout_row
				ON timeout_row.{$source['key_col']} = CONCAT(%s, SUBSTRING(value_row.{$source['key_col']}, %d))
				{$source['timeout_join_extra']}
			WHERE {$where}
			ORDER BY {$order}
			LIMIT %d
		";

		$prepared = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL identifiers are built from get_sources() internal allow-list; values use placeholders.
			$sql,
			$source['type'],
			strlen( $source['value_prefix'] ) + 1,
			$source['timeout_prefix'],
			strlen( $source['value_prefix'] ) + 1,
			max( self::PER_PAGE, absint( $limit ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query identifiers come from get_sources() internal allow-list and values are prepared above.
		$results = $wpdb->get_results( $prepared, ARRAY_A );
		$rows    = array();

		foreach ( (array) $results as $result ) {
			$timeout = absint( $result['timeout_value'] );
			$rows[] = array(
				'name'     => (string) $result['name'],
				'type'     => (string) $result['transient_type'],
				'size'     => absint( $result['size'] ),
				'timeout'  => $timeout,
				'status'   => self::status_from_timeout( $timeout ),
				'autoload' => (string) $result['autoload'],
			);
		}

		return $rows;
	}

	/**
	 * Count rows.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return int
	 */
	private static function count_rows( $args ) {
		$total = 0;
		foreach ( self::get_sources_for_filter( $args['filter'] ) as $source ) {
			$total += self::count_source_rows( $source, $args );
		}
		return $total;
	}

	/**
	 * Count source rows.
	 *
	 * @param array<string,string> $source Source.
	 * @param array<string,mixed>  $args   Args.
	 * @return int
	 */
	private static function count_source_rows( $source, $args ) {
		global $wpdb;

		$where = self::build_where( $source, $args );
		$sql   = "
			SELECT COUNT(*)
			FROM {$source['table']} value_row
			LEFT JOIN {$source['table']} timeout_row
				ON timeout_row.{$source['key_col']} = CONCAT(%s, SUBSTRING(value_row.{$source['key_col']}, %d))
				{$source['timeout_join_extra']}
			WHERE {$where}
		";

		$prepared = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query identifiers come from get_sources() internal allow-list; values use placeholders.
			$sql,
			$source['timeout_prefix'],
			strlen( $source['value_prefix'] ) + 1
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query identifiers are allow-listed and values are prepared above.
		return absint( $wpdb->get_var( $prepared ) );
	}

	/**
	 * Build where SQL.
	 *
	 * @param array<string,string> $source Source.
	 * @param array<string,mixed>  $args   Args.
	 * @return string
	 */
	private static function build_where( $source, $args ) {
		global $wpdb;

		$key_col   = self::identifier( $source['key_col'] );
		$value_col = self::identifier( $source['value_col'] );
		$where     = array(
			$wpdb->prepare( "value_row.{$key_col} LIKE %s", $wpdb->esc_like( $source['value_prefix'] ) . '%' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column identifier is allow-listed in get_sources().
			$wpdb->prepare( "value_row.{$key_col} NOT LIKE %s", $wpdb->esc_like( $source['timeout_prefix'] ) . '%' ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column identifier is allow-listed in get_sources().
		);

		if ( ! empty( $source['site_where'] ) ) {
			$where[] = $source['site_where'];
		}

		if ( '' !== $args['search'] ) {
			$where[] = $wpdb->prepare( "value_row.{$key_col} LIKE %s", '%' . $wpdb->esc_like( $args['search'] ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column identifier is allow-listed in get_sources().
		}

		if ( 'expired' === $args['filter'] ) {
			$where[] = 'CAST(COALESCE(timeout_row.' . $value_col . ", '0') AS UNSIGNED) > 0";
			$where[] = $wpdb->prepare( 'CAST(COALESCE(timeout_row.' . $value_col . ", '0') AS UNSIGNED) < %d", time() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column identifier is allow-listed in get_sources().
		} elseif ( 'active' === $args['filter'] ) {
			$where[] = $wpdb->prepare( 'CAST(COALESCE(timeout_row.' . $value_col . ", '0') AS UNSIGNED) >= %d", time() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column identifier is allow-listed in get_sources().
		} elseif ( 'no_expiration' === $args['filter'] ) {
			$where[] = '(timeout_row.' . $key_col . ' IS NULL OR timeout_row.' . $value_col . " = '')";
		} elseif ( 'large' === $args['filter'] ) {
			$where[] = 'LENGTH(value_row.' . $value_col . ') >= 100000';
		}

		return implode( ' AND ', $where );
	}

	/**
	 * Escape a known-safe SQL identifier from this module's internal source map.
	 *
	 * @param string $identifier Table or column identifier.
	 * @return string
	 */
	private static function identifier( $identifier ) {
		return '`' . str_replace( '`', '``', (string) $identifier ) . '`';
	}

	/**
	 * Get overview values.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_overview() {
		$overview = array(
			'total'        => 0,
			'expired'      => 0,
			'site'         => 0,
			'largest'      => 0,
			'storage'      => 0,
			'last_cleanup' => absint( get_option( self::LAST_CLEANUP_OPTION, 0 ) ),
		);

		foreach ( self::get_sources() as $source ) {
			$stats = self::source_stats( $source );
			$overview['total']   += $stats['total'];
			$overview['expired'] += $stats['expired'];
			$overview['storage'] += $stats['storage'];
			$overview['largest'] = max( $overview['largest'], $stats['largest'] );
			if ( 'site_transient' === $source['type'] ) {
				$overview['site'] += $stats['total'];
			}
		}

		return $overview;
	}

	/**
	 * Source stats.
	 *
	 * @param array<string,string> $source Source.
	 * @return array<string,int>
	 */
	private static function source_stats( $source ) {
		global $wpdb;

		$where = self::build_where( $source, array( 'search' => '', 'filter' => 'all' ) );
		$sql   = "
			SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN CAST(COALESCE(timeout_row.{$source['value_col']}, '0') AS UNSIGNED) > 0 AND CAST(COALESCE(timeout_row.{$source['value_col']}, '0') AS UNSIGNED) < %d THEN 1 ELSE 0 END) AS expired,
				COALESCE(MAX(LENGTH(value_row.{$source['value_col']})), 0) AS largest,
				COALESCE(SUM(LENGTH(value_row.{$source['value_col']})), 0) AS storage
			FROM {$source['table']} value_row
			LEFT JOIN {$source['table']} timeout_row
				ON timeout_row.{$source['key_col']} = CONCAT(%s, SUBSTRING(value_row.{$source['key_col']}, %d))
				{$source['timeout_join_extra']}
			WHERE {$where}
		";

		$prepared = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query identifiers come from get_sources() internal allow-list; values use placeholders.
			$sql,
			time(),
			$source['timeout_prefix'],
			strlen( $source['value_prefix'] ) + 1
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query identifiers are allow-listed and values are prepared above.
		$row = $wpdb->get_row( $prepared, ARRAY_A );

		return array(
			'total'   => absint( $row['total'] ?? 0 ),
			'expired' => absint( $row['expired'] ?? 0 ),
			'largest' => absint( $row['largest'] ?? 0 ),
			'storage' => absint( $row['storage'] ?? 0 ),
		);
	}

	/**
	 * Get transient sources.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function get_sources() {
		global $wpdb;

		$sources = array(
			array(
				'type'               => 'transient',
				'table'              => $wpdb->options,
				'key_col'            => 'option_name',
				'value_col'          => 'option_value',
				'autoload_expr'      => 'value_row.autoload',
				'value_prefix'       => '_transient_',
				'timeout_prefix'     => '_transient_timeout_',
				'timeout_join_extra' => '',
				'site_where'         => '',
			),
		);

		if ( is_multisite() && ! empty( $wpdb->sitemeta ) ) {
			$sources[] = array(
				'type'               => 'site_transient',
				'table'              => $wpdb->sitemeta,
				'key_col'            => 'meta_key',
				'value_col'          => 'meta_value',
				'autoload_expr'      => "'n/a'",
				'value_prefix'       => '_site_transient_',
				'timeout_prefix'     => '_site_transient_timeout_',
				'timeout_join_extra' => $wpdb->prepare( 'AND timeout_row.site_id = value_row.site_id AND timeout_row.site_id = %d', get_current_network_id() ),
				'site_where'         => $wpdb->prepare( 'value_row.site_id = %d', get_current_network_id() ),
			);
		} else {
			$sources[] = array(
				'type'               => 'site_transient',
				'table'              => $wpdb->options,
				'key_col'            => 'option_name',
				'value_col'          => 'option_value',
				'autoload_expr'      => 'value_row.autoload',
				'value_prefix'       => '_site_transient_',
				'timeout_prefix'     => '_site_transient_timeout_',
				'timeout_join_extra' => '',
				'site_where'         => '',
			);
		}

		return $sources;
	}

	/**
	 * Get sources for current filter.
	 *
	 * @param string $filter Filter.
	 * @return array<int,array<string,string>>
	 */
	private static function get_sources_for_filter( $filter ) {
		$sources = self::get_sources();
		if ( 'regular' === $filter ) {
			return array_values( array_filter( $sources, static function ( $source ) { return 'transient' === $source['type']; } ) );
		}
		if ( 'site_transients' === $filter ) {
			return array_values( array_filter( $sources, static function ( $source ) { return 'site_transient' === $source['type']; } ) );
		}
		return $sources;
	}

	/**
	 * Fetch a single item.
	 *
	 * @param string $type Type.
	 * @param string $name Name.
	 * @return array<string,mixed>|null
	 */
	private static function fetch_item( $type, $name ) {
		if ( ! $type || '' === $name ) {
			return null;
		}

		$source = null;
		foreach ( self::get_sources() as $candidate ) {
			if ( $candidate['type'] === $type ) {
				$source = $candidate;
				break;
			}
		}

		if ( ! $source ) {
			return null;
		}

		global $wpdb;
		$value_key   = $source['value_prefix'] . $name;
		$timeout_key = $source['timeout_prefix'] . $name;
		$site_where  = ! empty( $source['site_where'] ) ? ' AND ' . str_replace( 'value_row.', '', $source['site_where'] ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Source table/columns are internal allow-listed identifiers.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT {$source['value_col']} FROM {$source['table']} WHERE {$source['key_col']} = %s{$site_where} LIMIT 1", $value_key ) );
		if ( null === $value ) {
			return null;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Source table/columns are internal allow-listed identifiers.
		$timeout = $wpdb->get_var( $wpdb->prepare( "SELECT {$source['value_col']} FROM {$source['table']} WHERE {$source['key_col']} = %s{$site_where} LIMIT 1", $timeout_key ) );

		return array(
			'name'    => $name,
			'type'    => $type,
			'value'   => self::safe_unserialize_for_preview( $value ),
			'timeout' => absint( $timeout ),
		);
	}

	/**
	 * Decode a stored value without instantiating serialized classes.
	 *
	 * @param mixed $value Stored option value.
	 * @return mixed
	 */
	private static function safe_unserialize_for_preview( $value ) {
		if ( ! is_serialized( $value ) ) {
			return $value;
		}

		return unserialize( trim( $value ), array( 'allowed_classes' => false ) );
	}

	/**
	 * Get currently requested view item.
	 *
	 * @return array<string,mixed>|null
	 */
	/**
	 * Delete selected rows.
	 *
	 * @param string[] $items Items.
	 * @return int
	 */
	private static function delete_selected( $items ) {
		$count = 0;
		foreach ( $items as $item ) {
			$parts = self::parse_row_key( $item );
			if ( $parts && self::delete_transient_item( $parts['type'], $parts['name'] ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Delete by filter.
	 *
	 * @param string $filter Filter.
	 * @return int
	 */
	private static function delete_by_filter( $filter ) {
		$args = array(
			'search' => '',
			'filter' => 'all' === $filter ? 'all' : $filter,
			'sort'   => 'name',
			'paged'  => 1,
		);
		$count = 0;

		for ( $i = 0; $i < 20; $i++ ) {
			$rows = self::get_rows_for_cleanup( $args );
			if ( empty( $rows ) ) {
				break;
			}
			foreach ( $rows as $row ) {
				if ( self::delete_transient_item( $row['type'], $row['name'] ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Delete by keyword.
	 *
	 * @param string[] $keywords Keywords.
	 * @return int
	 */
	private static function delete_by_keyword( $keywords ) {
		$count = 0;
		foreach ( $keywords as $keyword ) {
			$args = array(
				'search' => self::sanitize_name( $keyword ),
				'filter' => 'all',
				'sort'   => 'name',
				'paged'  => 1,
			);
			for ( $i = 0; $i < 20; $i++ ) {
				$rows = self::get_rows_for_cleanup( $args );
				if ( empty( $rows ) ) {
					break;
				}
				foreach ( $rows as $row ) {
					if ( self::delete_transient_item( $row['type'], $row['name'] ) ) {
						$count++;
					}
				}
			}
		}
		return $count;
	}

	/**
	 * Get cleanup rows.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_rows_for_cleanup( $args ) {
		$rows = array();
		foreach ( self::get_sources_for_filter( $args['filter'] ) as $source ) {
			$rows = array_merge( $rows, self::query_source_rows( $source, $args, self::BATCH_LIMIT ) );
		}
		return array_slice( $rows, 0, self::BATCH_LIMIT );
	}

	/**
	 * Delete known update transients.
	 *
	 * @return int
	 */
	private static function delete_known_update_transients() {
		$count = 0;
		foreach ( array( 'update_core', 'update_plugins', 'update_themes', 'doing_cron' ) as $name ) {
			$count += delete_site_transient( $name ) ? 1 : 0;
			$count += delete_transient( $name ) ? 1 : 0;
		}
		return $count;
	}

	/**
	 * Delete one transient safely.
	 *
	 * @param string $type Type.
	 * @param string $name Name.
	 * @return bool
	 */
	private static function delete_transient_item( $type, $name ) {
		if ( 'site_transient' === $type ) {
			return (bool) delete_site_transient( $name );
		}
		return (bool) delete_transient( $name );
	}

	/**
	 * Export selected rows.
	 *
	 * @return void
	 */
	private static function export_selected() {
		// Nonce and capability are verified by handle_export() before this helper is called.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$items = isset( $_POST['transients'] ) && is_array( $_POST['transients'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['transients'] ) ) : array();
		$rows  = array();

		foreach ( array_slice( $items, 0, 100 ) as $item ) {
			$parts = self::parse_row_key( $item );
			if ( $parts ) {
				$row = self::fetch_item( $parts['type'], $parts['name'] );
				if ( $row ) {
					$rows[] = $row;
				}
			}
		}

		self::log_event( 'transient_exported', 'selected', array( 'count' => count( $rows ) ) );
		self::stream_export( $rows, 'json', 'siteintelix-transients-selected' );
	}

	/**
	 * Stream export response.
	 *
	 * @param array<int,array<string,mixed>> $items  Items.
	 * @param string                         $format Format.
	 * @param string                         $name   Filename stem.
	 * @return void
	 */
	private static function stream_export( $items, $format, $name ) {
		nocache_headers();
		header( 'Content-Type: ' . ( 'txt' === $format ? 'text/plain' : 'application/json' ) . '; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '.' . $format . '"' );

		if ( 'txt' === $format ) {
			foreach ( $items as $item ) {
				echo $item['name'] . "\n" . str_repeat( '=', 60 ) . "\n" . maybe_serialize( $item['value'] ) . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text attachment for administrators.
			}
		} else {
			echo wp_json_encode( $items, JSON_PRETTY_PRINT );
		}
		exit;
	}

	/**
	 * Object cache detection.
	 *
	 * @return array<string,mixed>
	 */
	private static function get_object_cache_info() {
		$providers = array();
		if ( class_exists( 'Redis' ) || class_exists( 'RedisCluster' ) ) {
			$providers[] = 'Redis';
		}
		if ( class_exists( 'Memcached' ) || class_exists( 'Memcache' ) ) {
			$providers[] = 'Memcached';
		}
		if ( defined( 'LSCWP_V' ) ) {
			$providers[] = 'LiteSpeed';
		}
		if ( class_exists( 'Docket_Cache' ) || defined( 'DOCKET_CACHE' ) ) {
			$providers[] = 'Docket Cache';
		}
		if ( class_exists( 'RedisCachePro\\Plugin' ) || defined( 'WP_REDIS_CONFIG' ) ) {
			$providers[] = 'Object Cache Pro / Redis config';
		}

		return array(
			'persistent' => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache(),
			'dropin'     => file_exists( WP_CONTENT_DIR . '/object-cache.php' ),
			'provider'   => empty( $providers ) ? __( 'None detected', 'siteintelix' ) : implode( ', ', array_unique( $providers ) ),
		);
	}

	/**
	 * Row warnings.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return string[]
	 */
	private static function get_row_warnings( $row ) {
		$warnings = array();
		if ( absint( $row['size'] ) >= 100000 ) {
			$warnings[] = __( 'Large', 'siteintelix' );
		}
		if ( 'yes' === strtolower( (string) $row['autoload'] ) && absint( $row['size'] ) >= 50000 ) {
			$warnings[] = __( 'Autoloaded', 'siteintelix' );
		}
		if ( 0 === absint( $row['timeout'] ) ) {
			$warnings[] = __( 'Long-living', 'siteintelix' );
		}
		if ( false !== stripos( $row['name'], 'woocommerce' ) || false !== stripos( $row['name'], 'wc_' ) ) {
			$warnings[] = __( 'WooCommerce', 'siteintelix' );
		}
		return $warnings;
	}

	/**
	 * Log event.
	 *
	 * @param string              $action  Action.
	 * @param string              $subject Subject.
	 * @param array<string,mixed> $details Details.
	 * @return void
	 */
	private static function log_event( $action, $subject, $details = array() ) {
		$logs = self::get_logs();
		array_unshift(
			$logs,
			array(
				'action'     => sanitize_key( $action ),
				'subject'    => sanitize_text_field( $subject ),
				'details'    => $details,
				'created_at' => time(),
			)
		);
		update_option( self::LOG_OPTION, array_slice( $logs, 0, 50 ), false );
	}

	/**
	 * Get logs.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_logs() {
		return (array) get_option( self::LOG_OPTION, array() );
	}

	/**
	 * Format value preview.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function format_value_preview( $value ) {
		$json = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $json ) {
			return substr( $json, 0, self::MAX_PREVIEW_BYTES );
		}
		return substr( print_r( $value, true ), 0, self::MAX_PREVIEW_BYTES ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	/**
	 * Format expiration.
	 *
	 * @param int $timeout Timeout.
	 * @return string
	 */
	private static function format_expiration( $timeout ) {
		$timeout = absint( $timeout );
		if ( ! $timeout ) {
			return __( 'No expiration', 'siteintelix' );
		}
		if ( $timeout < time() ) {
			return sprintf(
				/* translators: %s: relative time. */
				__( 'Expired %s ago', 'siteintelix' ),
				human_time_diff( $timeout, time() )
			);
		}
		return sprintf(
			/* translators: %s: relative time. */
			__( 'Expires in %s', 'siteintelix' ),
			human_time_diff( time(), $timeout )
		);
	}

	/**
	 * Status from timeout.
	 *
	 * @param int $timeout Timeout.
	 * @return string
	 */
	private static function status_from_timeout( $timeout ) {
		$timeout = absint( $timeout );
		if ( ! $timeout ) {
			return 'no_expiration';
		}
		return $timeout < time() ? 'expired' : 'active';
	}

	/**
	 * Format bytes.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private static function format_bytes( $bytes ) {
		$bytes = absint( $bytes );
		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576, 2 ) . ' MB';
		}
		if ( $bytes >= 1024 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		return $bytes . ' B';
	}

	/**
	 * Sanitize transient type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function sanitize_type( $type ) {
		$type = sanitize_key( $type );
		return in_array( $type, array( 'transient', 'site_transient' ), true ) ? $type : '';
	}

	/**
	 * Sanitize transient name.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function sanitize_name( $name ) {
		$name = sanitize_text_field( wp_unslash( $name ) );
		$name = str_replace( "\0", '', $name );
		return substr( $name, 0, 191 );
	}

	/**
	 * Build row key.
	 *
	 * @param string $type Type.
	 * @param string $name Name.
	 * @return string
	 */
	private static function row_key( $type, $name ) {
		return base64_encode( $type . '|' . $name ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Parse row key.
	 *
	 * @param string $key Key.
	 * @return array<string,string>|null
	 */
	private static function parse_row_key( $key ) {
		$decoded = base64_decode( $key, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( ! $decoded || false === strpos( $decoded, '|' ) ) {
			return null;
		}
		list( $type, $name ) = explode( '|', $decoded, 2 );
		$type = self::sanitize_type( $type );
		$name = self::sanitize_name( $name );
		return $type && $name ? array( 'type' => $type, 'name' => $name ) : null;
	}

	/**
	 * Read request value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function request_value( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shared reader; every mutating caller verifies its action nonce before use.
		if ( isset( $_POST[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Shared reader; mutating callers verify nonces before use.
			return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}
		if ( isset( $_GET[ $key ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters are intentionally accepted via query string.
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
		return '';
	}

	/**
	 * Require capability.
	 *
	 * @return void
	 */
	private static function require_global_tools() {
		if ( ! SITEINTELIX_Security::can_manage_global_tools() ) {
			wp_die( esc_html__( 'You do not have permission to manage transients.', 'siteintelix' ) );
		}
	}

	/**
	 * Redirect with error.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private static function redirect_error( $message ) {
		wp_safe_redirect( add_query_arg( 'siteintelix_tm_error', rawurlencode( $message ), self::page_url() ) );
		exit;
	}

	/**
	 * Page URL.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return string
	 */
	private static function page_url( $args = array() ) {
		$base = array( 'page' => 'siteintelix-transients-manager' );
		unset( $args['page'] );
		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}
}
