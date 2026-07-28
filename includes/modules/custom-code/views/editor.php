<?php
/**
 * Custom CSS & JS editor screen.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$list_url = admin_url( 'admin.php?page=siteintelix-custom-code' );
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-custom-code sitx-code-editor-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-editor-code',
			'title'       => $entry['id'] ? __( 'Edit Custom Code', 'siteintelix' ) : __( 'Add Custom Code', 'siteintelix' ),
			'description' => __( 'Add CSS or JavaScript without editing theme files.', 'siteintelix' ),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button(
					array(
						'label'   => __( 'Back to Custom CSS & JS', 'siteintelix' ),
						'url'     => $list_url,
						'variant' => 'secondary',
						'icon'    => 'dashicons-arrow-left-alt2',
					)
				),
			),
		)
	);
	?>

	<div class="siteintelix-container">
		<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Custom code saved.', 'siteintelix' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="siteintelix_custom_code_save">
			<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry['id'] ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $entry['status'] ); ?>">
			<?php wp_nonce_field( 'siteintelix_custom_code_save' ); ?>

			<div class="sitx-code-editor-workspace">
				<main class="sitx-code-editor-main si-card">
					<div class="sitx-code-editor-card__header">
						<div>
							<h2><?php esc_html_e( 'Code editor', 'siteintelix' ); ?></h2>
							<p><?php esc_html_e( 'Give this entry a clear title, then add the code you want SiteIntelix to load.', 'siteintelix' ); ?></p>
						</div>
						<span class="sitx-badge sitx-badge--info"><?php echo esc_html( 'javascript' === $entry['code_type'] ? 'JavaScript' : 'CSS' ); ?></span>
					</div>

					<div class="sitx-code-editor-card__body">
						<div class="sitx-code-editor-field">
							<label for="sitx-code-title"><?php esc_html_e( 'Title', 'siteintelix' ); ?></label>
							<input class="large-text" id="sitx-code-title" name="title" required value="<?php echo esc_attr( $entry['title'] ); ?>" placeholder="<?php esc_attr_e( 'Example: Checkout button styles', 'siteintelix' ); ?>">
						</div>

						<div class="sitx-code-editor-field sitx-code-editor-field--code">
							<label for="sitx-code-editor"><?php esc_html_e( 'Code', 'siteintelix' ); ?></label>
							<textarea id="sitx-code-editor" name="code" rows="26" spellcheck="false" aria-describedby="sitx-code-tag-warning"><?php echo esc_textarea( $entry['code'] ); ?></textarea>
							<p class="description" id="sitx-code-tag-warning"><?php esc_html_e( 'Paste code only. Outer <style> or <script> tags are removed when saved.', 'siteintelix' ); ?></p>
						</div>
					</div>
				</main>

				<aside class="sitx-code-editor-sidebar">
					<section class="sitx-code-editor-settings si-card" aria-labelledby="sitx-custom-code-settings-title">
						<div class="sitx-code-editor-card__header">
							<div>
								<h2 id="sitx-custom-code-settings-title"><?php esc_html_e( 'Code settings', 'siteintelix' ); ?></h2>
								<p><?php esc_html_e( 'Control where and how this code is loaded.', 'siteintelix' ); ?></p>
							</div>
						</div>
						<div class="sitx-code-editor-card__body">
							<div class="sitx-code-editor-field">
								<label for="sitx-code-type"><?php esc_html_e( 'Code type', 'siteintelix' ); ?></label>
								<select name="code_type" id="sitx-code-type">
									<option value="css" <?php selected( $entry['code_type'], 'css' ); ?>>CSS</option>
									<option value="javascript" <?php selected( $entry['code_type'], 'javascript' ); ?>>JavaScript</option>
								</select>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-code-scope"><?php esc_html_e( 'Where to run', 'siteintelix' ); ?></label>
								<select name="scope" id="sitx-code-scope">
									<option value="frontend" <?php selected( $entry['scope'], 'frontend' ); ?>><?php esc_html_e( 'Frontend', 'siteintelix' ); ?></option>
									<option value="admin" <?php selected( $entry['scope'], 'admin' ); ?>><?php esc_html_e( 'Admin', 'siteintelix' ); ?></option>
									<option value="both" <?php selected( $entry['scope'], 'both' ); ?>><?php esc_html_e( 'Frontend and admin', 'siteintelix' ); ?></option>
								</select>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-code-location"><?php esc_html_e( 'Location', 'siteintelix' ); ?></label>
								<select name="location" id="sitx-code-location">
									<option value="header" <?php selected( $entry['location'], 'header' ); ?>><?php esc_html_e( 'Header', 'siteintelix' ); ?></option>
									<option value="footer" <?php selected( $entry['location'], 'footer' ); ?>><?php esc_html_e( 'Footer', 'siteintelix' ); ?></option>
								</select>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-code-loading-method"><?php esc_html_e( 'Loading method', 'siteintelix' ); ?></label>
								<select name="loading_method" id="sitx-code-loading-method">
									<option value="inline" <?php selected( $entry['loading_method'], 'inline' ); ?>><?php esc_html_e( 'Inline', 'siteintelix' ); ?></option>
									<option value="external" <?php selected( $entry['loading_method'], 'external' ); ?>><?php esc_html_e( 'External file', 'siteintelix' ); ?></option>
								</select>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-code-priority"><?php esc_html_e( 'Priority', 'siteintelix' ); ?></label>
								<input id="sitx-code-priority" type="number" name="priority" min="-999" max="999" value="<?php echo esc_attr( $entry['priority'] ); ?>">
								<p class="description"><?php esc_html_e( 'Lower numbers load first.', 'siteintelix' ); ?></p>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-code-description"><?php esc_html_e( 'Description', 'siteintelix' ); ?></label>
								<textarea id="sitx-code-description" name="description" rows="4" placeholder="<?php esc_attr_e( 'Optional notes about this code', 'siteintelix' ); ?>"><?php echo esc_textarea( $entry['description'] ); ?></textarea>
							</div>
						</div>
					</section>

					<section class="sitx-code-editor-actions si-card" aria-labelledby="sitx-custom-code-actions-title">
						<div class="sitx-code-editor-actions__heading">
							<h2 id="sitx-custom-code-actions-title"><?php esc_html_e( 'Save changes', 'siteintelix' ); ?></h2>
							<span class="sitx-badge <?php echo 'enabled' === $entry['status'] ? 'sitx-badge--good' : 'sitx-badge--default'; ?>"><?php echo esc_html( ucfirst( $entry['status'] ) ); ?></span>
						</div>
						<button type="submit" class="si-button si-button--primary" name="submit_mode" value="save"><?php esc_html_e( 'Save', 'siteintelix' ); ?></button>
						<button type="submit" class="si-button si-button--secondary" name="submit_mode" value="save_enable"><?php esc_html_e( 'Save & Enable', 'siteintelix' ); ?></button>
					</section>
				</aside>
			</div>
		</form>
	</div>
</div>
