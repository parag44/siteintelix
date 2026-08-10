<?php
/**
 * User Switcher activity log view.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Read-only filters; no state changes occur without the forms' nonces.
$siteintelix_us_search    = isset( $_GET['us_search'] ) ? sanitize_text_field( wp_unslash( $_GET['us_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$siteintelix_us_status    = isset( $_GET['us_status'] ) ? sanitize_key( wp_unslash( $_GET['us_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$siteintelix_us_date_from = isset( $_GET['us_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['us_date_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$siteintelix_us_date_to   = isset( $_GET['us_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['us_date_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$siteintelix_us_paged     = isset( $_GET['us_paged'] ) ? max( 1, absint( wp_unslash( $_GET['us_paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$siteintelix_us_result    = SITEINTELIX_User_Switcher_Logger::query(
	array(
		'search'    => $siteintelix_us_search,
		'status'    => $siteintelix_us_status,
		'date_from' => $siteintelix_us_date_from,
		'date_to'   => $siteintelix_us_date_to,
		'paged'     => $siteintelix_us_paged,
	)
);
?>
<div class="sitx-user-switcher-heading">
	<div>
		<h2><?php esc_html_e( 'User Switcher Activity Log', 'siteintelix' ); ?></h2>
		<p><?php esc_html_e( 'Review temporary account access and how each session ended.', 'siteintelix' ); ?></p>
	</div>
</div>

<form method="get" class="sitx-user-switcher-filters">
	<input type="hidden" name="page" value="siteintelix-user-switcher">
	<input type="hidden" name="view" value="logs">
	<input type="search" name="us_search" value="<?php echo esc_attr( $siteintelix_us_search ); ?>" placeholder="<?php esc_attr_e( 'Search username or email', 'siteintelix' ); ?>">
	<select name="us_status">
		<option value=""><?php esc_html_e( 'All statuses', 'siteintelix' ); ?></option>
		<?php foreach ( SITEINTELIX_User_Switcher_Logger::statuses() as $siteintelix_us_status_choice ) : ?>
			<option value="<?php echo esc_attr( $siteintelix_us_status_choice ); ?>" <?php selected( $siteintelix_us_status, $siteintelix_us_status_choice ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $siteintelix_us_status_choice ) ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<input type="date" name="us_date_from" value="<?php echo esc_attr( $siteintelix_us_date_from ); ?>" aria-label="<?php esc_attr_e( 'Started after', 'siteintelix' ); ?>">
	<input type="date" name="us_date_to" value="<?php echo esc_attr( $siteintelix_us_date_to ); ?>" aria-label="<?php esc_attr_e( 'Started before', 'siteintelix' ); ?>">
	<button class="si-button si-button--primary" type="submit"><?php esc_html_e( 'Filter', 'siteintelix' ); ?></button>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="siteintelix_user_switcher_delete_logs">
	<?php wp_nonce_field( 'siteintelix_user_switcher_delete_logs' ); ?>
	<div class="sitx-user-switcher-table-wrap">
		<table class="widefat striped sitx-user-switcher-table">
			<thead><tr>
				<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'siteintelix' ); ?></span></td>
				<th><?php esc_html_e( 'Administrator', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'Target user', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'Started', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'Ended', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
				<th><?php esc_html_e( 'IP address', 'siteintelix' ); ?></th>
			</tr></thead>
			<tbody>
			<?php if ( empty( $siteintelix_us_result['rows'] ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No switching activity matches these filters.', 'siteintelix' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $siteintelix_us_result['rows'] as $siteintelix_us_row ) : ?>
					<?php
					$siteintelix_us_started  = strtotime( $siteintelix_us_row->started_at . ' UTC' );
					$siteintelix_us_ended    = $siteintelix_us_row->ended_at ? strtotime( $siteintelix_us_row->ended_at . ' UTC' ) : 0;
					$siteintelix_us_duration = $siteintelix_us_ended ? human_time_diff( $siteintelix_us_started, $siteintelix_us_ended ) : ( 'active' === $siteintelix_us_row->status ? human_time_diff( $siteintelix_us_started, time() ) : '—' );
					?>
					<tr>
						<th class="check-column"><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr( absint( $siteintelix_us_row->id ) ); ?>"></th>
						<td><?php /* translators: %d: deleted WordPress user ID. */ echo esc_html( $siteintelix_us_row->original_name ? $siteintelix_us_row->original_name : sprintf( __( 'Deleted user #%d', 'siteintelix' ), $siteintelix_us_row->original_user_id ) ); ?></td>
						<td><?php /* translators: %d: deleted WordPress user ID. */ echo esc_html( $siteintelix_us_row->target_name ? $siteintelix_us_row->target_name : sprintf( __( 'Deleted user #%d', 'siteintelix' ), $siteintelix_us_row->target_user_id ) ); ?></td>
						<td><?php echo esc_html( get_date_from_gmt( $siteintelix_us_row->started_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td><?php echo $siteintelix_us_row->ended_at ? esc_html( get_date_from_gmt( $siteintelix_us_row->ended_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) : '—'; ?></td>
						<td><?php echo esc_html( $siteintelix_us_duration ); ?></td>
						<td><span class="sitx-user-switcher-status sitx-user-switcher-status--<?php echo esc_attr( sanitize_html_class( $siteintelix_us_row->status ) ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $siteintelix_us_row->status ) ) ); ?></span></td>
						<td><code><?php echo esc_html( $siteintelix_us_row->ip_address ? $siteintelix_us_row->ip_address : '—' ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<p><button class="si-button si-button--secondary" type="submit"><?php esc_html_e( 'Delete selected', 'siteintelix' ); ?></button></p>
</form>

<?php
$siteintelix_us_total_pages = (int) ceil( $siteintelix_us_result['total'] / $siteintelix_us_result['per_page'] );
if ( $siteintelix_us_total_pages > 1 ) :
	$siteintelix_us_pagination = paginate_links(
		array(
			'base'      => add_query_arg( 'us_paged', '%#%' ),
			'format'    => '',
			'current'   => $siteintelix_us_result['page'],
			'total'     => $siteintelix_us_total_pages,
			'type'      => 'list',
			'prev_text' => '‹',
			'next_text' => '›',
		)
	);
	?>
	<nav class="sitx-user-switcher-pagination" aria-label="<?php esc_attr_e( 'Activity log pages', 'siteintelix' ); ?>"><?php echo wp_kses_post( $siteintelix_us_pagination ); ?></nav>
<?php endif; ?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-user-switcher-clear" onsubmit="return confirm('<?php echo esc_js( __( 'Clear all User Switcher logs? This cannot be undone.', 'siteintelix' ) ); ?>');">
	<input type="hidden" name="action" value="siteintelix_user_switcher_clear_logs">
	<?php wp_nonce_field( 'siteintelix_user_switcher_clear_logs' ); ?>
	<button class="si-button si-button--danger" type="submit"><?php esc_html_e( 'Clear all logs', 'siteintelix' ); ?></button>
</form>
