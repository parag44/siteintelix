<?php
/**
 * File Manager details panel.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<aside class="sitx-fm-details" data-fm-details-panel aria-label="<?php esc_attr_e( 'File details', 'siteintelix' ); ?>">
	<div class="sitx-fm-panel-heading"><h2><?php esc_html_e( 'Details', 'siteintelix' ); ?></h2><button type="button" data-fm-close-details aria-label="<?php esc_attr_e( 'Close details', 'siteintelix' ); ?>">×</button></div>
	<div class="sitx-fm-details__empty" data-fm-details-empty><?php esc_html_e( 'Select a file or folder to inspect it.', 'siteintelix' ); ?></div>
	<div data-fm-details-content hidden><h3 data-fm-details-name></h3><pre data-fm-preview tabindex="0"></pre><div class="sitx-fm-image-preview" data-fm-image-preview hidden></div><dl data-fm-metadata></dl><div class="sitx-fm-details__actions" data-fm-details-actions></div></div>
</aside>
