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
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect status flag.
$siteintelix_security_saved = isset( $_GET['siteintelix_security_saved'] );
?>
<div class="wrap siteintelix-wrap" id="siteintelix-security-page">

	<!-- ===== 1. Unified Header ===== -->
	<header class="siteintelix-header">
		<div class="siteintelix-header__content">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-shield-alt" aria-hidden="true"></span>
				<div class="siteintelix-header__text">
					<h1 class="siteintelix-header__title"><?php esc_html_e( 'Security Panel', 'siteintelix' ); ?></h1>
					<p class="siteintelix-header__desc"><?php esc_html_e( 'Harden your WordPress installation with one-click security features.', 'siteintelix' ); ?></p>
				</div>
			</div>
			<div class="siteintelix-header__actions">
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
				<span class="sitx-badge sitx-badge--info">
					<span class="dashicons dashicons-lock" aria-hidden="true" style="font-size:14px; width:14px; height:14px;"></span>
					<?php esc_html_e( 'Shield Active', 'siteintelix' ); ?>
				</span>
			</div>
		</div>
	</header>

	<div class="siteintelix-container">

		<!-- ===== 2. Notices ===== -->
		<div id="siteintelix-notices-slot">
			<?php if ( $siteintelix_security_saved ) : ?>
				<div class="sitx-alert sitx-alert--success">
					<div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div>
					<div class="sitx-alert__content">
						<strong class="sitx-alert__title"><?php esc_html_e( 'Security settings updated.', 'siteintelix' ); ?></strong>
						<p class="sitx-alert__msg"><?php esc_html_e( 'Your site hardening configuration has been applied successfully.', 'siteintelix' ); ?></p>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- ===== 3. Security Hardening Features ===== -->
		<section class="sitx-section-header">
			<h2 class="sitx-section-title"><?php esc_html_e( 'Hardening Features', 'siteintelix' ); ?></h2>
			<p class="sitx-section-desc"><?php esc_html_e( 'Enable the following options to improve your site resistance against common attacks.', 'siteintelix' ); ?></p>
		</section>

		<form method="post" action="<?php echo esc_url( $siteintelix_sec_form_url ); ?>" id="siteintelix-security-form">
			<?php wp_nonce_field( 'siteintelix_save_security' ); ?>
			<input type="hidden" name="action" value="siteintelix_save_security">

			<div class="sitx-security-list">
				<?php foreach ( $siteintelix_sec_features as $siteintelix_feature ) :
					$siteintelix_is_on = (bool) get_option( $siteintelix_feature['option_key'], $siteintelix_feature['default'] );
				?>
					<div class="sitx-security-item">
						<div class="sitx-security-item__info">
							<h3 class="sitx-security-item__title"><?php echo esc_html( $siteintelix_feature['title'] ); ?></h3>
							<p class="sitx-security-item__desc"><?php echo esc_html( $siteintelix_feature['description'] ); ?></p>
						</div>
						
						<div class="sitx-security-item__control">
							<span class="sitx-badge <?php echo $siteintelix_is_on ? 'sitx-badge--good' : 'sitx-badge--default'; ?>">
								<?php echo $siteintelix_is_on ? esc_html__( 'Active', 'siteintelix' ) : esc_html__( 'Inactive', 'siteintelix' ); ?>
							</span>
							
							<label class="sitx-toggle">
								<input type="checkbox" name="<?php echo esc_attr( $siteintelix_feature['id'] ); ?>" value="1" <?php checked( $siteintelix_is_on ); ?>>
								<span class="sitx-toggle__slider"></span>
							</label>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div style="border-top: 1px solid var(--siteintelix-border); padding-top: 32px; display: flex; justify-content: flex-end;">
				<button type="submit" class="sitx-btn sitx-btn--primary">
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e( 'Save Security Configuration', 'siteintelix' ); ?>
				</button>
			</div>
		</form>

	</div><!-- /.siteintelix-container -->
</div><!-- /.wrap -->
