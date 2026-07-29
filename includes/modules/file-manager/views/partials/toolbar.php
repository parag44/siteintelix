<?php
/**
 * File Manager toolbar.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="sitx-fm-toolbar si-toolbar">
	<div class="sitx-fm-toolbar__group">
		<button type="button" class="si-button si-button--secondary" data-fm-back disabled><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Back', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-forward disabled><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Forward', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-up><span class="sitx-fm-toolbar__icon dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'Up', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-refresh><span class="sitx-fm-toolbar__icon dashicons dashicons-update" aria-hidden="true"></span><span><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></span></button>
	</div>
	<div class="sitx-fm-toolbar__group">
		<button type="button" class="si-button si-button--primary" data-fm-new><span class="sitx-fm-toolbar__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span><span><?php esc_html_e( 'New', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-upload><span class="sitx-fm-toolbar__icon dashicons dashicons-upload" aria-hidden="true"></span><span><?php esc_html_e( 'Upload', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-toggle-tree aria-controls="siteintelix-file-manager-tree" aria-expanded="true"><span class="sitx-fm-toolbar__icon dashicons dashicons-category" aria-hidden="true"></span><span><?php esc_html_e( 'Folders', 'siteintelix' ); ?></span></button>
		<button type="button" class="si-button si-button--secondary" data-fm-toggle-details aria-controls="siteintelix-file-manager-details" aria-expanded="true"><span class="sitx-fm-toolbar__icon dashicons dashicons-info-outline" aria-hidden="true"></span><span><?php esc_html_e( 'Details', 'siteintelix' ); ?></span></button>
	</div>
	<label class="sitx-fm-search"><span class="sitx-fm-search__icon dashicons dashicons-search" aria-hidden="true"></span><span class="screen-reader-text"><?php esc_html_e( 'Search current directory', 'siteintelix' ); ?></span><input type="search" data-fm-search placeholder="<?php esc_attr_e( 'Search current folder', 'siteintelix' ); ?>"></label>
</div>
<nav class="sitx-fm-breadcrumbs" aria-label="<?php esc_attr_e( 'Current directory', 'siteintelix' ); ?>" data-fm-breadcrumbs></nav>
