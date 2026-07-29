<?php
/**
 * File Manager editor panel.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<section class="sitx-fm-editor" data-fm-editor hidden aria-labelledby="sitx-fm-editor-title">
	<div class="sitx-fm-editor__header"><div><h2 id="sitx-fm-editor-title" data-fm-editor-title><?php esc_html_e( 'Edit file', 'siteintelix' ); ?></h2><p data-fm-editor-path></p></div><button type="button" data-fm-editor-fullscreen><?php esc_html_e( 'Full screen', 'siteintelix' ); ?></button></div>
	<textarea data-fm-editor-textarea aria-label="<?php esc_attr_e( 'File content', 'siteintelix' ); ?>"></textarea>
	<div class="sitx-fm-editor__actions"><button type="button" class="si-button si-button--primary" data-fm-editor-save><?php esc_html_e( 'Save', 'siteintelix' ); ?></button><button type="button" class="si-button si-button--secondary" data-fm-editor-cancel><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></button></div>
</section>
