<?php
/**
 * Security Panel page view for SiteIntelix.
 *
 * Renders dynamic, toggle-based security hardening features.
 *
 * @package SiteIntelix
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_sec_features = SITEINTELIX_Security::get_features();
$siteintelix_sec_form_url = admin_url( 'admin-post.php' );
?>
<div class="wrap siteintelix-wrap siteintelix-security-wrap" id="siteintelix-security-page">

	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<?php if ( isset( $_GET['siteintelix_security_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success">
			<p><?php esc_html_e( 'Security settings saved successfully.', 'siteintelix' ); ?></p>
		</div>
	<?php endif; ?>

	<!-- ===== Page Header ===== -->
	<div class="siteintelix-header">
		<div class="siteintelix-header__inner">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-shield-alt" aria-hidden="true"></span>
				<h1 class="siteintelix-header__title">
					<?php esc_html_e( 'Security Panel', 'siteintelix' ); ?>
				</h1>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
			</div>
		</div>
	</div>

	<!-- ===== Security Features ===== -->
	<form method="post" action="<?php echo esc_url( $siteintelix_sec_form_url ); ?>" id="siteintelix-security-form">
		<?php wp_nonce_field( 'siteintelix_save_security' ); ?>
		<input type="hidden" name="action" value="siteintelix_save_security">

		<div class="siteintelix-security-grid">
			<?php foreach ( $siteintelix_sec_features as $feature ) :
				$is_on = (bool) get_option( $feature['option_key'], $feature['default'] );
			?>
				<div class="siteintelix-security-card<?php echo $is_on ? ' is-active' : ''; ?>">
					<div class="siteintelix-security-card__info">
						<h3 class="siteintelix-security-card__title">
							<?php echo esc_html( $feature['title'] ); ?>
						</h3>
						<p class="siteintelix-security-card__desc">
							<?php echo esc_html( $feature['description'] ); ?>
						</p>
					</div>
					<div class="siteintelix-security-card__control">
						<span class="siteintelix-security-card__status-label">
							<?php echo $is_on ? esc_html__( 'Active', 'siteintelix' ) : esc_html__( 'Inactive', 'siteintelix' ); ?>
						</span>
						<label class="siteintelix-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $feature['id'] ); ?>"
								value="1"
								<?php checked( $is_on ); ?>
							>
							<span class="siteintelix-toggle__slider"></span>
						</label>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="siteintelix-settings-submit">
			<button type="submit" class="siteintelix-btn siteintelix-btn--save">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span>
				<?php esc_html_e( 'Save Security Settings', 'siteintelix' ); ?>
			</button>
		</div>
	</form>

</div><!-- /.wrap -->
