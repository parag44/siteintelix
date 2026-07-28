<?php
/**
 * Custom CSS & JS management screen.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$add_url      = admin_url( 'admin.php?page=siteintelix-custom-code-new' );
$settings_url = admin_url( 'admin.php?page=siteintelix-settings&tab=custom_code' );
$reset_url    = admin_url( 'admin.php?page=siteintelix-custom-code' );
$is_filtered  = '' !== $args['search'] || '' !== $args['code_type'] || '' !== $args['status'];
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-custom-code sitx-code-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-editor-code',
			'title'       => __( 'Custom CSS & JS', 'siteintelix' ),
			'description' => __( 'Manage reusable frontend and admin code.', 'siteintelix' ),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button( array( 'label' => __( 'Settings', 'siteintelix' ), 'url' => $settings_url, 'variant' => 'secondary', 'icon' => 'dashicons-admin-generic' ) ),
				SITEINTELIX_Admin_UI::button( array( 'label' => __( 'Add New Code', 'siteintelix' ), 'url' => $add_url, 'variant' => 'primary', 'icon' => 'dashicons-plus-alt2' ) ),
			),
		)
	);
	?>
	<div class="siteintelix-container">
		<div class="sitx-code-summary" aria-label="<?php esc_attr_e( 'Custom code summary', 'siteintelix' ); ?>">
			<span class="sitx-code-summary__item"><strong><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></strong> <?php esc_html_e( 'Total', 'siteintelix' ); ?></span>
			<span class="sitx-code-summary__item sitx-code-summary__item--good"><strong><?php echo esc_html( number_format_i18n( $summary['enabled'] ) ); ?></strong> <?php esc_html_e( 'Enabled', 'siteintelix' ); ?></span>
			<span class="sitx-code-summary__item"><strong><?php echo esc_html( number_format_i18n( $summary['disabled'] ) ); ?></strong> <?php esc_html_e( 'Disabled', 'siteintelix' ); ?></span>
		</div>

		<section class="sitx-code-manager si-card" aria-labelledby="sitx-custom-code-manager-title">
			<h2 id="sitx-custom-code-manager-title" class="screen-reader-text"><?php esc_html_e( 'Custom code entries', 'siteintelix' ); ?></h2>
			<form method="get" class="sitx-code-toolbar" role="search">
				<input type="hidden" name="page" value="siteintelix-custom-code">
				<label class="sitx-code-search" for="sitx-code-search">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Search custom code', 'siteintelix' ); ?></span>
					<input id="sitx-code-search" type="search" name="s" value="<?php echo esc_attr( $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search by title or description', 'siteintelix' ); ?>">
				</label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Filter by code type', 'siteintelix' ); ?></span>
					<select name="code_type">
						<option value=""><?php esc_html_e( 'All types', 'siteintelix' ); ?></option>
						<option value="css" <?php selected( $args['code_type'], 'css' ); ?>>CSS</option>
						<option value="javascript" <?php selected( $args['code_type'], 'javascript' ); ?>>JavaScript</option>
					</select>
				</label>
				<label>
					<span class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'siteintelix' ); ?></span>
					<select name="status">
						<option value=""><?php esc_html_e( 'All statuses', 'siteintelix' ); ?></option>
						<option value="enabled" <?php selected( $args['status'], 'enabled' ); ?>><?php esc_html_e( 'Enabled', 'siteintelix' ); ?></option>
						<option value="disabled" <?php selected( $args['status'], 'disabled' ); ?>><?php esc_html_e( 'Disabled', 'siteintelix' ); ?></option>
					</select>
				</label>
				<button class="si-button si-button--secondary" type="submit"><?php esc_html_e( 'Filter', 'siteintelix' ); ?></button>
				<?php if ( $is_filtered ) : ?>
					<a class="si-button si-button--ghost" href="<?php echo esc_url( $reset_url ); ?>"><?php esc_html_e( 'Reset', 'siteintelix' ); ?></a>
				<?php endif; ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="siteintelix_custom_code_bulk">
				<?php wp_nonce_field( 'siteintelix_custom_code_bulk' ); ?>
				<div class="sitx-code-bulkbar">
					<label>
						<span class="screen-reader-text"><?php esc_html_e( 'Bulk action', 'siteintelix' ); ?></span>
						<select name="bulk_action">
							<option value=""><?php esc_html_e( 'Bulk actions', 'siteintelix' ); ?></option>
							<option value="enable"><?php esc_html_e( 'Enable', 'siteintelix' ); ?></option>
							<option value="disable"><?php esc_html_e( 'Disable', 'siteintelix' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></option>
						</select>
					</label>
					<button type="submit" class="si-button si-button--secondary" data-siteintelix-confirm="<?php esc_attr_e( 'Apply this bulk action to the selected custom code?', 'siteintelix' ); ?>"><?php esc_html_e( 'Apply', 'siteintelix' ); ?></button>
					<span class="sitx-code-bulkbar__count">
						<?php
						printf(
							/* translators: %d: matching entry count. */
							esc_html( _n( '%d matching entry', '%d matching entries', $total, 'siteintelix' ) ),
							absint( $total )
						);
						?>
					</span>
				</div>

				<div class="si-table-wrap sitx-code-table-wrap">
					<table class="widefat si-table sitx-code-table">
						<thead>
							<tr>
								<td class="check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all custom code entries', 'siteintelix' ); ?>"></td>
								<th><?php esc_html_e( 'Title', 'siteintelix' ); ?></th>
								<th><?php esc_html_e( 'Type', 'siteintelix' ); ?></th>
								<th><?php esc_html_e( 'Scope', 'siteintelix' ); ?></th>
								<th><?php esc_html_e( 'Location', 'siteintelix' ); ?></th>
								<th class="si-cell-number"><?php esc_html_e( 'Priority', 'siteintelix' ); ?></th>
								<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! $entries ) : ?>
								<tr><td colspan="7"><div class="si-empty-state"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><h2><?php esc_html_e( 'No custom code found', 'siteintelix' ); ?></h2><p><?php echo $is_filtered ? esc_html__( 'Try changing or resetting the current filters.', 'siteintelix' ) : esc_html__( 'Create your first CSS or JavaScript entry to get started.', 'siteintelix' ); ?></p><?php if ( ! $is_filtered ) : ?><a class="si-button si-button--primary" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add New Code', 'siteintelix' ); ?></a><?php endif; ?></div></td></tr>
							<?php endif; ?>
							<?php foreach ( $entries as $item ) : ?>
								<?php
								$edit_url = add_query_arg( array( 'page' => 'siteintelix-custom-code-new', 'entry' => $item['id'] ), admin_url( 'admin.php' ) );
								$action_names = array( 'enabled' === $item['status'] ? 'disable' : 'enable', 'duplicate', 'delete' );
								?>
								<tr>
									<th class="check-column"><input type="checkbox" name="entry_ids[]" value="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'siteintelix' ), $item['title'] ) ); ?>"></th>
									<td class="sitx-code-title"><strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $item['title'] ); ?></a></strong><?php if ( $item['description'] ) : ?><small><?php echo esc_html( $item['description'] ); ?></small><?php endif; ?><div class="sitx-code-row-actions"><?php foreach ( $action_names as $index => $do ) : $url = wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_custom_code_action', 'do' => $do, 'entry' => $item['id'] ), admin_url( 'admin-post.php' ) ), 'siteintelix_custom_code_action' ); if ( $index ) : ?><span aria-hidden="true">·</span><?php endif; ?><a class="<?php echo 'delete' === $do ? 'sitx-code-row-actions__danger' : ''; ?>" <?php echo 'delete' === $do ? 'data-siteintelix-confirm="' . esc_attr__( 'Delete this custom code entry?', 'siteintelix' ) . '"' : ''; ?> href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( ucfirst( $do ) ); ?></a><?php endforeach; ?></div></td>
									<td><span class="sitx-badge sitx-badge--info"><?php echo esc_html( 'css' === $item['code_type'] ? 'CSS' : 'JS' ); ?></span></td>
									<td><span class="sitx-badge sitx-badge--default"><?php echo esc_html( ucfirst( $item['scope'] ) ); ?></span></td>
									<td><?php echo esc_html( ucfirst( $item['location'] ) ); ?></td>
									<td class="si-cell-number"><?php echo esc_html( $item['priority'] ); ?></td>
									<td><span class="sitx-badge <?php echo 'enabled' === $item['status'] ? 'sitx-badge--good' : 'sitx-badge--default'; ?>"><?php echo esc_html( ucfirst( $item['status'] ) ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</form>

			<?php if ( $total > 20 ) : ?>
				<nav class="sitx-code-pagination" aria-label="<?php esc_attr_e( 'Custom code pagination', 'siteintelix' ); ?>"><?php echo wp_kses_post( paginate_links( array( 'total' => (int) ceil( $total / 20 ), 'current' => $args['page'] ) ) ); ?></nav>
			<?php endif; ?>
		</section>
	</div>
</div>
