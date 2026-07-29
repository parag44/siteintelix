<?php
/**
 * File Manager admin screen.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
$siteintelix_fm_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'browser';
$siteintelix_fm_tab = in_array( $siteintelix_fm_tab, array( 'browser', 'backups', 'trash', 'settings' ), true ) ? $siteintelix_fm_tab : 'browser';
$siteintelix_fm_base = admin_url( 'admin.php?page=siteintelix-file-manager' );
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-file-manager" data-siteintelix-file-manager data-active-tab="<?php echo esc_attr( $siteintelix_fm_tab ); ?>">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-open-folder',
			'title'       => __( 'File Manager', 'siteintelix' ),
			'description' => __( 'Browse and safely manage files inside this WordPress site.', 'siteintelix' ),
			'badges'      => array(
				SITEINTELIX_Admin_UI::badge( __( 'Safe Mode', 'siteintelix' ), 'success', 'dashicons-shield-alt' ),
			),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button(
					array(
						'label'   => __( 'Settings', 'siteintelix' ),
						'url'     => add_query_arg( 'tab', 'settings', $siteintelix_fm_base ),
						'variant' => 'secondary',
						'icon'    => 'dashicons-admin-generic',
					)
				),
			),
		)
	);
	?>
	<div class="siteintelix-container">
		<nav class="sitx-fm-tabs" aria-label="<?php esc_attr_e( 'File Manager sections', 'siteintelix' ); ?>">
			<a data-file-manager-tab="browser" class="sitx-fm-tab <?php echo 'browser' === $siteintelix_fm_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'browser', $siteintelix_fm_base ) ); ?>" <?php echo 'browser' === $siteintelix_fm_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Browser', 'siteintelix' ); ?></a>
			<a data-file-manager-tab="backups" class="sitx-fm-tab <?php echo 'backups' === $siteintelix_fm_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'backups', $siteintelix_fm_base ) ); ?>" <?php echo 'backups' === $siteintelix_fm_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Backups', 'siteintelix' ); ?></a>
			<a data-file-manager-tab="trash" class="sitx-fm-tab <?php echo 'trash' === $siteintelix_fm_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'trash', $siteintelix_fm_base ) ); ?>" <?php echo 'trash' === $siteintelix_fm_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Trash', 'siteintelix' ); ?></a>
			<a data-file-manager-tab="settings" class="sitx-fm-tab <?php echo 'settings' === $siteintelix_fm_tab ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'settings', $siteintelix_fm_base ) ); ?>" <?php echo 'settings' === $siteintelix_fm_tab ? 'aria-current="page"' : ''; ?>><?php esc_html_e( 'Settings', 'siteintelix' ); ?></a>
		</nav>

		<div class="sitx-fm-announcer screen-reader-text" aria-live="polite" data-fm-announcer></div>

		<?php if ( 'browser' === $siteintelix_fm_tab ) : ?>
			<section class="sitx-fm-browser si-card" aria-label="<?php esc_attr_e( 'File browser', 'siteintelix' ); ?>">
				<?php require __DIR__ . '/partials/toolbar.php'; ?>
				<div class="sitx-fm-workspace">
					<?php require __DIR__ . '/partials/folder-tree.php'; ?>
					<?php require __DIR__ . '/partials/file-table.php'; ?>
					<?php require __DIR__ . '/partials/details-panel.php'; ?>
				</div>
				<?php require __DIR__ . '/partials/editor.php'; ?>
				<?php require __DIR__ . '/partials/modals.php'; ?>
			</section>
		<?php elseif ( 'backups' === $siteintelix_fm_tab ) : ?>
			<section class="sitx-fm-utility si-card" data-fm-backups>
				<div class="sitx-fm-section-heading"><div><h2><?php esc_html_e( 'Backups', 'siteintelix' ); ?></h2><p><?php esc_html_e( 'Restore verified safety copies created before file edits.', 'siteintelix' ); ?></p></div><button type="button" class="si-button si-button--secondary" data-fm-refresh-backups><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></button></div>
				<div class="sitx-fm-state" data-fm-backups-state><?php esc_html_e( 'Loading backups…', 'siteintelix' ); ?></div>
				<div class="sitx-fm-table-scroll"><table class="si-table" data-fm-backups-table hidden><thead><tr><th><?php esc_html_e( 'Original path', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Created', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Size', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th></tr></thead><tbody></tbody></table></div>
			</section>
		<?php elseif ( 'trash' === $siteintelix_fm_tab ) : ?>
			<section class="sitx-fm-utility si-card" data-fm-trash>
				<div class="sitx-fm-section-heading"><div><h2><?php esc_html_e( 'Trash', 'siteintelix' ); ?></h2><p><?php esc_html_e( 'Restore or permanently remove items previously moved to private trash.', 'siteintelix' ); ?></p></div><button type="button" class="si-button si-button--secondary" data-fm-refresh-trash><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></button></div>
				<div class="sitx-fm-state" data-fm-trash-state><?php esc_html_e( 'Loading trash…', 'siteintelix' ); ?></div>
				<div class="sitx-fm-table-scroll"><table class="si-table" data-fm-trash-table hidden><thead><tr><th><?php esc_html_e( 'Original path', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Deleted', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Type', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th></tr></thead><tbody></tbody></table></div>
			</section>
		<?php else : ?>
			<section class="sitx-fm-utility si-card" id="siteintelix-file-manager-settings">
				<?php require __DIR__ . '/settings.php'; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
