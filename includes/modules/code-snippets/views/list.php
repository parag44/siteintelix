<?php
/**
 * Code Snippets management screen.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$add_url      = admin_url( 'admin.php?page=siteintelix-code-snippets-new' );
$settings_url = admin_url( 'admin.php?page=siteintelix-settings&tab=code_snippets' );
$reset_url    = admin_url( 'admin.php?page=siteintelix-code-snippets' );
$is_filtered  = '' !== $args['search'] || '' !== $args['status'] || '' !== $args['scope'] || $args['recent'];
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-snippets sitx-code-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-editor-code',
			'title'       => __( 'Code Snippets', 'siteintelix' ),
			'description' => __( 'Manage isolated PHP customizations with automatic error deactivation.', 'siteintelix' ),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button( array( 'label' => __( 'Settings', 'siteintelix' ), 'url' => $settings_url, 'variant' => 'secondary', 'icon' => 'dashicons-admin-generic' ) ),
				SITEINTELIX_Admin_UI::button( array( 'label' => __( 'Add New Snippet', 'siteintelix' ), 'url' => $add_url, 'variant' => 'primary', 'icon' => 'dashicons-plus-alt2' ) ),
			),
		)
	);
	?>
	<div class="siteintelix-container">
		<div class="sitx-code-summary" aria-label="<?php esc_attr_e( 'Snippet summary', 'siteintelix' ); ?>">
			<span class="sitx-code-summary__item"><strong><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></strong> <?php esc_html_e( 'Total', 'siteintelix' ); ?></span>
			<span class="sitx-code-summary__item sitx-code-summary__item--good"><strong><?php echo esc_html( number_format_i18n( $summary['active'] ) ); ?></strong> <?php esc_html_e( 'Active', 'siteintelix' ); ?></span>
			<span class="sitx-code-summary__item"><strong><?php echo esc_html( number_format_i18n( $summary['inactive'] ) ); ?></strong> <?php esc_html_e( 'Inactive', 'siteintelix' ); ?></span>
		</div>

		<section class="sitx-code-manager si-card" aria-labelledby="sitx-snippet-manager-title">
			<h2 id="sitx-snippet-manager-title" class="screen-reader-text"><?php esc_html_e( 'PHP snippets', 'siteintelix' ); ?></h2>
			<form method="get" class="sitx-code-toolbar" role="search">
				<input type="hidden" name="page" value="siteintelix-code-snippets">
				<label class="sitx-code-search" for="sitx-snippet-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Search code snippets', 'siteintelix' ); ?></span>
					<input id="sitx-snippet-search" type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search by name or description', 'siteintelix' ); ?>">
				</label>
				<label><span class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'siteintelix' ); ?></span><select name="status"><option value=""><?php esc_html_e( 'All statuses', 'siteintelix' ); ?></option><option value="active" <?php selected( $args['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'siteintelix' ); ?></option><option value="inactive" <?php selected( $args['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'siteintelix' ); ?></option></select></label>
				<label><span class="screen-reader-text"><?php esc_html_e( 'Filter by scope', 'siteintelix' ); ?></span><select name="scope"><option value=""><?php esc_html_e( 'All scopes', 'siteintelix' ); ?></option><option value="everywhere" <?php selected( $args['scope'], 'everywhere' ); ?>><?php esc_html_e( 'Everywhere', 'siteintelix' ); ?></option><option value="frontend" <?php selected( $args['scope'], 'frontend' ); ?>><?php esc_html_e( 'Frontend', 'siteintelix' ); ?></option><option value="admin" <?php selected( $args['scope'], 'admin' ); ?>><?php esc_html_e( 'Admin', 'siteintelix' ); ?></option></select></label>
				<label class="sitx-code-recent"><input type="checkbox" name="recent" value="1" <?php checked( $args['recent'] ); ?>><span><?php esc_html_e( 'Recently deactivated', 'siteintelix' ); ?><small><?php esc_html_e( 'Last 7 days', 'siteintelix' ); ?></small></span></label>
				<button class="si-button si-button--secondary" type="submit"><?php esc_html_e( 'Filter', 'siteintelix' ); ?></button>
				<?php if ( $is_filtered ) : ?><a class="si-button si-button--ghost" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset', 'siteintelix' ); ?></a><?php endif; ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="siteintelix_snippet_bulk">
				<?php wp_nonce_field( 'siteintelix_snippet_bulk' ); ?>
				<div class="sitx-code-bulkbar">
					<label><span class="screen-reader-text"><?php esc_html_e( 'Bulk action', 'siteintelix' ); ?></span><select name="bulk_action"><option value=""><?php esc_html_e( 'Bulk actions', 'siteintelix' ); ?></option><option value="activate"><?php esc_html_e( 'Activate', 'siteintelix' ); ?></option><option value="deactivate"><?php esc_html_e( 'Deactivate', 'siteintelix' ); ?></option><option value="delete"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></option></select></label>
					<button type="submit" class="si-button si-button--secondary" data-siteintelix-confirm="<?php esc_attr_e( 'Apply this bulk action to the selected snippets?', 'siteintelix' ); ?>"><?php esc_html_e( 'Apply', 'siteintelix' ); ?></button>
					<span class="sitx-code-bulkbar__count"><?php printf( esc_html( _n( '%d matching snippet', '%d matching snippets', $total, 'siteintelix' ) ), absint( $total ) ); ?></span>
				</div>

				<div class="si-table-wrap sitx-code-table-wrap">
					<table class="widefat si-table sitx-code-table">
						<thead><tr><td class="check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all snippets', 'siteintelix' ); ?>"></td><th><?php esc_html_e( 'Name', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Scope', 'siteintelix' ); ?></th><th class="si-cell-number"><?php esc_html_e( 'Priority', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Last run', 'siteintelix' ); ?></th></tr></thead>
						<tbody>
							<?php if ( ! $items ) : ?><tr><td colspan="6"><div class="si-empty-state"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><h2><?php esc_html_e( 'No snippets found', 'siteintelix' ); ?></h2><p><?php echo $is_filtered ? esc_html__( 'Try changing or resetting the current filters.', 'siteintelix' ) : esc_html__( 'Create your first isolated PHP snippet to get started.', 'siteintelix' ); ?></p><?php if ( ! $is_filtered ) : ?><a class="si-button si-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add New Snippet', 'siteintelix' ); ?></a><?php endif; ?></div></td></tr><?php endif; ?>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$edit_url = add_query_arg( array( 'page' => 'siteintelix-code-snippets-new', 'snippet' => $item['id'] ), admin_url( 'admin.php' ) );
								$actions  = array( 'active' === $item['status'] ? 'deactivate' : 'activate', 'duplicate', 'run-once', 'delete' );
								?>
								<tr>
									<th class="check-column"><input type="checkbox" name="snippet_ids[]" value="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'siteintelix' ), $item['name'] ) ); ?>"></th>
									<td class="sitx-code-title"><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $item['name'] ); ?></a></strong><?php if ( $item['description'] ) : ?><small><?php echo esc_html( $item['description'] ); ?></small><?php endif; ?><?php if ( ! empty( $item['error_message'] ) ) : ?><span class="sitx-code-error"><span class="dashicons dashicons-warning" aria-hidden="true"></span><?php esc_html_e( 'Runtime error recorded', 'siteintelix' ); ?></span><?php endif; ?><div class="sitx-code-row-actions"><?php foreach ( $actions as $index => $do ) : $page = 'run-once' === $do ? 'siteintelix-code-snippets-run-once' : ''; $url = $page ? add_query_arg( array( 'page' => $page, 'snippet' => $item['id'] ), admin_url( 'admin.php' ) ) : wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_snippet_action', 'do' => $do, 'snippet' => $item['id'] ), admin_url( 'admin-post.php' ) ), 'siteintelix_snippet_action' ); if ( $index ) : ?><span aria-hidden="true">·</span><?php endif; ?><a class="<?php echo 'delete' === $do ? 'sitx-code-row-actions__danger' : ''; ?>" <?php echo 'delete' === $do ? 'data-siteintelix-confirm="' . esc_attr__( 'Delete this snippet?', 'siteintelix' ) . '"' : ''; ?> href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( ucwords( str_replace( '-', ' ', $do ) ) ); ?></a><?php endforeach; ?></div></td>
									<td><span class="sitx-badge sitx-badge--default"><?php echo esc_html( ucfirst( $item['scope'] ) ); ?></span></td>
									<td class="si-cell-number"><?php echo esc_html( $item['priority'] ); ?></td>
									<td><span class="sitx-badge <?php echo 'active' === $item['status'] ? 'sitx-badge--good' : 'sitx-badge--default'; ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item['status'] ) ) ); ?></span></td>
									<td><?php echo esc_html( $item['last_run_at'] ? get_date_from_gmt( $item['last_run_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '—' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</form>

			<?php if ( $total > 20 ) : ?><nav class="sitx-code-pagination" aria-label="<?php esc_attr_e( 'Snippet pagination', 'siteintelix' ); ?>"><?php echo wp_kses_post( paginate_links( array( 'total' => (int) ceil( $total / 20 ), 'current' => max( 1, $args['page'] ) ) ) ); ?></nav><?php endif; ?>
		</section>
	</div>
</div>
