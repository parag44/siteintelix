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
		<button type="button" class="si-button si-button--secondary" data-fm-back disabled><?php esc_html_e( 'Back', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-forward disabled><?php esc_html_e( 'Forward', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-up><?php esc_html_e( 'Up', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-refresh><?php esc_html_e( 'Refresh', 'siteintelix' ); ?></button>
	</div>
	<div class="sitx-fm-toolbar__group">
		<button type="button" class="si-button si-button--primary" data-fm-new><?php esc_html_e( 'New', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-upload><?php esc_html_e( 'Upload', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-toggle-tree aria-expanded="true"><?php esc_html_e( 'Folders', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-toggle-details aria-expanded="true"><?php esc_html_e( 'Details', 'siteintelix' ); ?></button>
	</div>
	<label class="sitx-fm-search"><span class="screen-reader-text"><?php esc_html_e( 'Search current directory', 'siteintelix' ); ?></span><input type="search" data-fm-search placeholder="<?php esc_attr_e( 'Search current folder', 'siteintelix' ); ?>"></label>
</div>
<nav class="sitx-fm-breadcrumbs" aria-label="<?php esc_attr_e( 'Current directory', 'siteintelix' ); ?>" data-fm-breadcrumbs></nav>
