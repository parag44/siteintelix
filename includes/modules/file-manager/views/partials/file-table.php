<?php
/**
 * File Manager table shell.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<main class="sitx-fm-files" aria-label="<?php esc_attr_e( 'Files', 'siteintelix' ); ?>">
	<div class="sitx-fm-state" data-fm-state><?php esc_html_e( 'Loading files…', 'siteintelix' ); ?></div>
	<div class="sitx-fm-table-scroll">
		<table class="si-table sitx-fm-table" data-fm-table hidden>
			<thead><tr>
				<th scope="col"><button type="button" data-fm-sort="name"><?php esc_html_e( 'Name', 'siteintelix' ); ?></button></th>
				<th scope="col"><button type="button" data-fm-sort="type"><?php esc_html_e( 'Type', 'siteintelix' ); ?></button></th>
				<th scope="col"><button type="button" data-fm-sort="size"><?php esc_html_e( 'Size', 'siteintelix' ); ?></button></th>
				<th scope="col"><button type="button" data-fm-sort="modified"><?php esc_html_e( 'Modified', 'siteintelix' ); ?></button></th>
				<th scope="col"><?php esc_html_e( 'Permissions', 'siteintelix' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Writable', 'siteintelix' ); ?></th>
				<th scope="col" class="sitx-fm-table__menu-column"><span class="screen-reader-text"><?php esc_html_e( 'Item menu', 'siteintelix' ); ?></span></th>
			</tr></thead>
			<tbody></tbody>
		</table>
	</div>
	<div class="sitx-fm-pagination" data-fm-pagination hidden><button type="button" class="si-button si-button--secondary" data-fm-prev><?php esc_html_e( 'Previous', 'siteintelix' ); ?></button><span data-fm-page-label></span><button type="button" class="si-button si-button--secondary" data-fm-next><?php esc_html_e( 'Next', 'siteintelix' ); ?></button></div>
</main>
