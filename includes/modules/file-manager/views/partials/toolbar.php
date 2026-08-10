<?php
/**
 * File Manager toolbar.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="sitx-fm-toolbar si-toolbar" aria-label="<?php esc_attr_e( 'File actions', 'siteintelix' ); ?>">
	<div class="sitx-fm-toolbar__identity" aria-hidden="true"><span class="dashicons dashicons-category"></span><strong><?php esc_html_e( 'SiteIntelix Files', 'siteintelix' ); ?></strong></div>
	<button type="button" class="sitx-fm-tool" data-fm-new-folder><span class="dashicons dashicons-category" aria-hidden="true"></span><span><?php esc_html_e( 'New Folder', 'siteintelix' ); ?></span></button>
	<button type="button" class="sitx-fm-tool" data-fm-new-file><span class="dashicons dashicons-media-default" aria-hidden="true"></span><span><?php esc_html_e( 'New File', 'siteintelix' ); ?></span></button>
	<button type="button" class="sitx-fm-tool" data-fm-upload><span class="dashicons dashicons-upload" aria-hidden="true"></span><span><?php esc_html_e( 'Upload', 'siteintelix' ); ?></span></button>
	<div class="sitx-fm-sort">
		<button type="button" class="sitx-fm-tool" data-fm-sort-toggle aria-expanded="false" aria-controls="siteintelix-file-manager-sort"><span class="dashicons dashicons-sort" aria-hidden="true"></span><span><?php esc_html_e( 'Sort by', 'siteintelix' ); ?></span></button>
		<div id="siteintelix-file-manager-sort" class="sitx-fm-sort__menu" data-fm-sort-menu role="menu" hidden></div>
	</div>
	<button type="button" class="sitx-fm-tool sitx-fm-tool--refresh" data-fm-refresh><span class="dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></span></button>
</div>
<div class="sitx-fm-locationbar">
	<button type="button" class="sitx-fm-tree-trigger" data-fm-toggle-tree aria-controls="siteintelix-file-manager-tree" aria-expanded="false"><span class="dashicons dashicons-category" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Show folders', 'siteintelix' ); ?></span></button>
	<nav class="sitx-fm-breadcrumbs" aria-label="<?php esc_attr_e( 'Current directory', 'siteintelix' ); ?>" data-fm-breadcrumbs></nav>
	<div class="sitx-fm-progress" data-fm-progress hidden role="status" aria-live="polite">
		<div class="sitx-fm-progress__header">
			<strong data-fm-progress-label></strong>
			<span data-fm-progress-percent></span>
			<button type="button" class="sitx-fm-progress__close" data-fm-progress-close aria-label="<?php esc_attr_e( 'Dismiss operation status', 'siteintelix' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</div>
		<div class="sitx-fm-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-fm-progress-track><span data-fm-progress-bar></span></div>
		<p data-fm-progress-message></p>
	</div>
	<label class="sitx-fm-search"><span class="screen-reader-text"><?php esc_html_e( 'Search current directory', 'siteintelix' ); ?></span><input type="search" data-fm-search placeholder="<?php esc_attr_e( 'Search current folder', 'siteintelix' ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span></label>
</div>
