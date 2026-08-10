<?php
/**
 * File Manager editor panel.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<section class="sitx-fm-editor" data-fm-editor hidden aria-labelledby="sitx-fm-editor-title">
	<div class="sitx-fm-editor__header">
		<div>
			<h2 id="sitx-fm-editor-title" data-fm-editor-title><?php esc_html_e( 'Edit file', 'siteintelix' ); ?></h2>
			<p data-fm-editor-path></p>
		</div>
		<button type="button" class="si-button si-button--secondary sitx-fm-editor__fullscreen" data-fm-editor-fullscreen aria-pressed="false">
			<span class="dashicons dashicons-editor-expand" aria-hidden="true"></span>
			<span data-fm-editor-fullscreen-label><?php esc_html_e( 'Full screen', 'siteintelix' ); ?></span>
		</button>
	</div>
	<textarea data-fm-editor-textarea aria-label="<?php esc_attr_e( 'File content', 'siteintelix' ); ?>"></textarea>
	<div class="sitx-fm-editor__actions">
		<p class="sitx-fm-editor__status" data-fm-editor-status role="status" aria-live="polite"></p>
		<button type="button" class="si-button si-button--primary" data-fm-editor-save><?php esc_html_e( 'Save', 'siteintelix' ); ?></button>
		<button type="button" class="si-button si-button--secondary" data-fm-editor-cancel><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></button>
	</div>
</section>
