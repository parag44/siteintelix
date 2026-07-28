<?php defined( 'ABSPATH' ) || exit; ?>
<section class="sitx-settings-panel-tab <?php echo 'code_snippets' === $active_tab ? 'is-active' : ''; ?>" id="siteintelix-code-snippets-settings" role="tabpanel" aria-labelledby="siteintelix-settings-tab-code_snippets" data-siteintelix-settings-panel="code_snippets" <?php echo 'code_snippets' === $active_tab ? '' : 'hidden'; ?>>
	<div class="sitx-settings-content-grid">
		<div class="sitx-settings-main">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-tab-form">
				<input type="hidden" name="action" value="siteintelix_save_code_snippets_settings">
				<?php wp_nonce_field( 'siteintelix_save_code_snippets_settings' ); ?>
				<div class="sitx-setting-row si-form-row">
					<div>
						<h3><?php esc_html_e( 'Delete snippets on uninstall', 'siteintelix' ); ?></h3>
						<p><?php esc_html_e( 'Remove all PHP snippets and stored recovery details when SiteIntelix is deleted. Leave disabled to preserve them.', 'siteintelix' ); ?></p>
					</div>
					<label class="sitx-toggle">
						<input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $delete_on_uninstall ); ?>>
						<span class="sitx-toggle__slider"></span>
					</label>
				</div>
				<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save Code Snippets Settings', 'siteintelix' ); ?></button>
			</form>
		</div>
		<aside class="sitx-settings-sidebar">
			<div class="sitx-side-card si-card">
				<h3><?php esc_html_e( 'Data safety', 'siteintelix' ); ?></h3>
				<p><?php esc_html_e( 'This option is off by default. Disabling the module never deletes snippets or recovery information.', 'siteintelix' ); ?></p>
			</div>
		</aside>
	</div>
</section>
