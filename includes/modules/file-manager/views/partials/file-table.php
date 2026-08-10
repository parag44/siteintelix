<?php
/**
 * File Manager table shell.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<main class="sitx-fm-files" aria-label="<?php esc_attr_e( 'Files', 'siteintelix' ); ?>">
	<div class="sitx-fm-selection-actions" data-fm-selection-actions hidden>
		<span data-fm-selection-count></span>
		<button type="button" data-fm-selection-download><?php esc_html_e( 'Download', 'siteintelix' ); ?></button>
		<button type="button" data-fm-selection-archive><?php esc_html_e( 'Archive ZIP', 'siteintelix' ); ?></button>
		<button type="button" data-fm-selection-details><?php esc_html_e( 'Details', 'siteintelix' ); ?></button>
		<button type="button" data-fm-selection-rename><?php esc_html_e( 'Rename', 'siteintelix' ); ?></button>
		<button type="button" class="is-destructive" data-fm-selection-trash><?php esc_html_e( 'Trash', 'siteintelix' ); ?></button>
		<button type="button" data-fm-selection-clear><?php esc_html_e( 'Clear selection', 'siteintelix' ); ?></button>
	</div>
	<div class="sitx-fm-state" data-fm-state><?php esc_html_e( 'Loading files…', 'siteintelix' ); ?></div>
	<div class="sitx-fm-table-scroll">
		<table class="si-table sitx-fm-table" data-fm-table hidden>
			<thead><tr>
				<th scope="col" class="sitx-fm-select-column"><input type="checkbox" data-fm-select-all aria-label="<?php esc_attr_e( 'Select all visible items', 'siteintelix' ); ?>"></th>
				<th scope="col"><button type="button" data-fm-sort="name"><?php esc_html_e( 'Name', 'siteintelix' ); ?></button></th>
				<th scope="col"><button type="button" data-fm-sort="size"><?php esc_html_e( 'Size', 'siteintelix' ); ?></button></th>
				<th scope="col"><?php esc_html_e( 'Permissions', 'siteintelix' ); ?></th>
				<th scope="col"><button type="button" data-fm-sort="modified"><?php esc_html_e( 'Last modified', 'siteintelix' ); ?></button></th>
				<th scope="col"><?php esc_html_e( 'Access', 'siteintelix' ); ?></th>
				<th scope="col" class="sitx-fm-table__menu-column"><span class="screen-reader-text"><?php esc_html_e( 'Item menu', 'siteintelix' ); ?></span></th>
			</tr></thead>
			<tbody></tbody>
		</table>
	</div>
	<div class="sitx-fm-pagination" data-fm-pagination hidden><button type="button" class="si-button si-button--secondary" data-fm-prev><?php esc_html_e( 'Previous', 'siteintelix' ); ?></button><span data-fm-page-label></span><button type="button" class="si-button si-button--secondary" data-fm-next><?php esc_html_e( 'Next', 'siteintelix' ); ?></button></div>
</main>
