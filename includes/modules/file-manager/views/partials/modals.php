<?php
/**
 * File Manager accessible dialogs.
 *
 * @package SiteIntelix
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="sitx-fm-modal" data-fm-modal hidden role="dialog" aria-modal="true" aria-labelledby="sitx-fm-modal-title" aria-describedby="sitx-fm-modal-description">
	<div class="sitx-fm-modal__surface">
		<h2 id="sitx-fm-modal-title" data-fm-modal-title><?php esc_html_e( 'Confirm action', 'siteintelix' ); ?></h2>
		<p id="sitx-fm-modal-description" data-fm-modal-description></p>
		<div data-fm-modal-fields></div>
		<div class="sitx-fm-modal__actions"><button type="button" class="si-button si-button--secondary" data-fm-modal-cancel><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></button><button type="button" class="si-button si-button--primary" data-fm-modal-confirm><?php esc_html_e( 'Confirm', 'siteintelix' ); ?></button></div>
	</div>
</div>
<div class="sitx-fm-context-menu" data-fm-context-menu role="menu" aria-label="<?php esc_attr_e( 'Item actions', 'siteintelix' ); ?>" hidden></div>
<input type="file" data-fm-upload-input multiple hidden>
