<?php
/**
 * File Manager folder tree.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<aside id="siteintelix-file-manager-tree" class="sitx-fm-tree" data-fm-tree-panel aria-label="<?php esc_attr_e( 'Folders', 'siteintelix' ); ?>">
	<div class="sitx-fm-panel-heading"><h2><span class="dashicons dashicons-portfolio" aria-hidden="true"></span><?php esc_html_e( 'WordPress files', 'siteintelix' ); ?></h2><button type="button" data-fm-close-tree aria-label="<?php esc_attr_e( 'Close folders', 'siteintelix' ); ?>">×</button></div>
	<ul class="sitx-fm-tree__list" data-fm-tree-root role="tree"></ul>
</aside>
