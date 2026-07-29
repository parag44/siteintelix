<?php
/**
 * File Manager folder tree.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<aside class="sitx-fm-tree" data-fm-tree-panel aria-label="<?php esc_attr_e( 'Folder tree', 'siteintelix' ); ?>">
	<div class="sitx-fm-panel-heading"><h2><?php esc_html_e( 'Folders', 'siteintelix' ); ?></h2><button type="button" data-fm-close-tree aria-label="<?php esc_attr_e( 'Close folder tree', 'siteintelix' ); ?>">×</button></div>
	<div data-fm-tree role="tree"></div>
</aside>
