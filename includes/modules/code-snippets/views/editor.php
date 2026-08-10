<?php
/**
 * Code Snippets editor screen.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

$list_url = admin_url( 'admin.php?page=siteintelix-code-snippets' );
?>
<div class="wrap siteintelix-wrap si-admin-wrap sitx-snippets sitx-code-editor-page">
	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-editor-code',
			'title'       => $item['id'] ? __( 'Edit Snippet', 'siteintelix' ) : __( 'Add New Snippet', 'siteintelix' ),
			'description' => __( 'PHP runs in an isolated closure with automatic error deactivation.', 'siteintelix' ),
			'actions'     => array(
				SITEINTELIX_Admin_UI::button(
					array(
						'label'   => __( 'Back to Code Snippets', 'siteintelix' ),
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
		<?php if ( ! empty( $_GET['snippet_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['snippet_error'] ) ) ); ?></p></div>
		<?php elseif ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Snippet saved.', 'siteintelix' ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="siteintelix_snippet_save">
			<input type="hidden" name="snippet_id" value="<?php echo esc_attr( $item['id'] ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( $item['status'] ); ?>">
			<?php wp_nonce_field( 'siteintelix_snippet_save' ); ?>

			<div class="sitx-code-editor-workspace">
				<main class="sitx-code-editor-main si-card">
					<div class="sitx-code-editor-card__header">
						<div>
							<h2><?php esc_html_e( 'PHP editor', 'siteintelix' ); ?></h2>
							<p><?php esc_html_e( 'Add a descriptive name, then enter the PHP customization to run.', 'siteintelix' ); ?></p>
						</div>
						<span class="sitx-badge sitx-badge--info">PHP</span>
					</div>

					<div class="sitx-code-editor-card__body">
						<div class="sitx-code-editor-field">
							<label for="sitx-snippet-name"><?php esc_html_e( 'Snippet name', 'siteintelix' ); ?></label>
							<input id="sitx-snippet-name" class="large-text" name="name" required value="<?php echo esc_attr( $item['name'] ); ?>" placeholder="<?php esc_attr_e( 'Example: Add Tutor dashboard link', 'siteintelix' ); ?>">
						</div>

						<div class="sitx-code-editor-field sitx-code-editor-field--code">
							<label for="sitx-snippet-code"><?php esc_html_e( 'PHP code', 'siteintelix' ); ?></label>
							<textarea id="sitx-snippet-code" name="code" rows="26" spellcheck="false" aria-describedby="sitx-snippet-code-help"><?php echo esc_textarea( $item['code'] ); ?></textarea>
							<p class="description" id="sitx-snippet-code-help"><?php esc_html_e( 'Snippets start in PHP mode. You may close and reopen PHP when outputting template markup. Syntax is checked before saving.', 'siteintelix' ); ?></p>
						</div>

						<?php if ( ! empty( $item['error_message'] ) ) : ?>
							<div class="sitx-code-editor-error" role="alert">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<div>
									<strong><?php esc_html_e( 'Runtime error recorded', 'siteintelix' ); ?></strong>
									<p><?php echo esc_html( $item['error_message'] ); ?></p>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</main>

				<aside class="sitx-code-editor-sidebar">
					<section class="sitx-code-editor-settings si-card" aria-labelledby="sitx-snippet-settings-title">
						<div class="sitx-code-editor-card__header">
							<div>
								<h2 id="sitx-snippet-settings-title"><?php esc_html_e( 'Snippet settings', 'siteintelix' ); ?></h2>
								<p><?php esc_html_e( 'Choose the execution context and organization details.', 'siteintelix' ); ?></p>
							</div>
						</div>
						<div class="sitx-code-editor-card__body">
							<div class="sitx-code-editor-field">
								<label for="sitx-snippet-scope"><?php esc_html_e( 'Run on', 'siteintelix' ); ?></label>
								<select id="sitx-snippet-scope" name="scope">
									<option value="everywhere" <?php selected( $item['scope'], 'everywhere' ); ?>><?php esc_html_e( 'Everywhere', 'siteintelix' ); ?></option>
									<option value="frontend" <?php selected( $item['scope'], 'frontend' ); ?>><?php esc_html_e( 'Frontend only', 'siteintelix' ); ?></option>
									<option value="admin" <?php selected( $item['scope'], 'admin' ); ?>><?php esc_html_e( 'Admin only', 'siteintelix' ); ?></option>
								</select>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-snippet-priority"><?php esc_html_e( 'Priority', 'siteintelix' ); ?></label>
								<input id="sitx-snippet-priority" type="number" min="-999" max="999" name="priority" value="<?php echo esc_attr( $item['priority'] ); ?>">
								<p class="description"><?php esc_html_e( 'Lower numbers run first.', 'siteintelix' ); ?></p>
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-snippet-tags"><?php esc_html_e( 'Tags', 'siteintelix' ); ?></label>
								<input id="sitx-snippet-tags" name="tags" value="<?php echo esc_attr( $item['tags'] ); ?>" placeholder="<?php esc_attr_e( 'performance, admin', 'siteintelix' ); ?>">
							</div>

							<div class="sitx-code-editor-field">
								<label for="sitx-snippet-description"><?php esc_html_e( 'Description', 'siteintelix' ); ?></label>
								<textarea id="sitx-snippet-description" name="description" rows="4" placeholder="<?php esc_attr_e( 'Optional notes about this snippet', 'siteintelix' ); ?>"><?php echo esc_textarea( $item['description'] ); ?></textarea>
							</div>
						</div>
					</section>

					<section class="sitx-code-editor-actions si-card" aria-labelledby="sitx-snippet-actions-title">
						<div class="sitx-code-editor-actions__heading">
							<h2 id="sitx-snippet-actions-title"><?php esc_html_e( 'Save changes', 'siteintelix' ); ?></h2>
							<span class="sitx-badge <?php echo 'active' === $item['status'] ? 'sitx-badge--good' : 'sitx-badge--default'; ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $item['status'] ) ) ); ?></span>
						</div>
						<button type="submit" class="si-button si-button--primary" name="submit_mode" value="save"><?php esc_html_e( 'Save', 'siteintelix' ); ?></button>
						<button type="submit" class="si-button si-button--secondary" name="submit_mode" value="save_activate"><?php esc_html_e( 'Save & Activate', 'siteintelix' ); ?></button>
						<p class="description"><?php esc_html_e( 'Test risky PHP on a staging site before activation.', 'siteintelix' ); ?></p>
					</section>
				</aside>
			</div>
		</form>
	</div>
</div>
