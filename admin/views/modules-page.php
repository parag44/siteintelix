<?php
/**
 * Modules toolbox page for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_modules      = SITEINTELIX_Modules::get_all();
$siteintelix_enabled      = SITEINTELIX_Modules::get_enabled();
$siteintelix_active_count = count( $siteintelix_enabled );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect status flag.
$siteintelix_modules_saved = isset( $_GET['siteintelix_modules_saved'] );

$siteintelix_active_modules    = array();
$siteintelix_available_modules = array();

foreach ( $siteintelix_modules as $siteintelix_module ) {
	if ( in_array( $siteintelix_module['id'], $siteintelix_enabled, true ) ) {
		$siteintelix_active_modules[] = $siteintelix_module;
	} else {
		$siteintelix_available_modules[] = $siteintelix_module;
	}
}

if ( ! function_exists( 'siteintelix_render_module_card' ) ) {
	/**
	 * Render a module card.
	 *
	 * @param array<string,mixed> $module     Module definition.
	 * @param bool                $is_enabled Whether the module is enabled.
	 * @return void
	 */
	function siteintelix_render_module_card( $module, $is_enabled ) {
		$module_id    = sanitize_key( $module['id'] );
		$is_available = ! empty( $module['available'] );
		$can_manage   = SITEINTELIX_Security::can_manage_module( $module_id );
		$color        = isset( $module['color'] ) ? sanitize_key( $module['color'] ) : 'blue';
		?>
		<article class="sitx-module-card si-module-card si-card <?php echo $is_enabled ? 'is-active' : ''; ?> <?php echo ! $is_available ? 'is-disabled' : ''; ?>" data-siteintelix-module-card data-module-id="<?php echo esc_attr( $module_id ); ?>" data-module-title="<?php echo esc_attr( strtolower( (string) $module['title'] ) ); ?>" data-module-description="<?php echo esc_attr( strtolower( (string) $module['description'] ) ); ?>">
			<div class="sitx-module-card__top">
				<span class="sitx-module-card__icon sitx-module-card__icon--<?php echo esc_attr( $color ); ?>">
					<?php if ( ! empty( $module['icon_svg'] ) ) : ?>
						<?php
						echo wp_kses(
							SITEINTELIX_Admin_UI::svg_icon( (string) $module['icon_svg'] ),
							array(
								'span' => array(
									'class' => true,
								),
								'svg'  => array(
									'viewbox'     => true,
									'aria-hidden' => true,
									'focusable'   => true,
								),
								'path' => array(
									'fill' => true,
									'd'    => true,
								),
							)
						);
						?>
					<?php else : ?>
						<span class="dashicons <?php echo esc_attr( (string) $module['icon'] ); ?>" aria-hidden="true"></span>
					<?php endif; ?>
				</span>
				<span class="sitx-badge si-badge <?php echo $is_enabled ? 'sitx-badge--good si-badge--success' : ( $is_available ? 'sitx-badge--default si-badge--neutral' : 'sitx-badge--warning si-badge--warning' ); ?>" data-siteintelix-module-status>
					<?php
					if ( $is_enabled ) {
						esc_html_e( 'Active', 'siteintelix' );
					} elseif ( $is_available ) {
						esc_html_e( 'Available', 'siteintelix' );
					} else {
						esc_html_e( 'Planned', 'siteintelix' );
					}
					?>
				</span>
			</div>

			<h3 class="sitx-module-card__title"><?php echo esc_html( (string) $module['title'] ); ?></h3>
			<p class="sitx-module-card__desc"><?php echo esc_html( (string) $module['description'] ); ?></p>

			<div class="sitx-module-card__footer">
				<?php
				$module_links   = array(
					'debug_log'          => admin_url( 'admin.php?page=siteintelix-debug-log' ),
					'email_log'          => admin_url( 'admin.php?page=siteintelix-email-log' ),
					'cron_events'        => admin_url( 'admin.php?page=siteintelix-cron-events' ),
					'database_manager'   => admin_url( 'admin.php?page=siteintelix-database-manager' ),
					'safe_mode_debugger' => admin_url( 'admin.php?page=siteintelix-safe-mode' ),
					'custom_code'        => admin_url( 'admin.php?page=siteintelix-custom-code' ),
					'code_snippets'      => admin_url( 'admin.php?page=siteintelix-code-snippets' ),
					'file_manager'       => admin_url( 'admin.php?page=siteintelix-file-manager' ),
				);
				$settings_links = array(
					'debug_log'     => 'siteintelix-debug-log-settings',
					'email_log'     => 'siteintelix-email-log-settings',
					'smtp'          => 'siteintelix-smtp-settings',
					'coming_soon'   => 'siteintelix-coming-soon-settings',
					'user_switcher' => 'siteintelix-user-switcher-settings',
					'custom_code'   => 'siteintelix-custom-code-settings',
					'code_snippets' => 'siteintelix-code-snippets-settings',
					'file_manager'  => 'siteintelix-file-manager-settings',
				);
				if ( $can_manage && $is_enabled && isset( $module_links[ $module_id ] ) ) :
					?>
					<a class="si-button si-button--ghost si-button--small" href="<?php echo esc_url( $module_links[ $module_id ] ); ?>"><?php esc_html_e( 'Open', 'siteintelix' ); ?></a>
				<?php endif; ?>
				<?php if ( $can_manage && $is_enabled && ! empty( $module['settings'] ) && isset( $settings_links[ $module_id ] ) ) : ?>
					<?php
					$module_settings_url = add_query_arg(
						array(
							'page' => 'siteintelix-settings',
							'tab'  => $module_id,
						),
						admin_url( 'admin.php' )
					) . '#' . $settings_links[ $module_id ];
					?>
					<a class="si-button si-button--ghost si-button--small" href="<?php echo esc_url( $module_settings_url ); ?>"><?php esc_html_e( 'Settings', 'siteintelix' ); ?></a>
				<?php endif; ?>
				<?php if ( $is_available && $can_manage ) : ?>
					<label class="sitx-toggle sitx-toggle--module" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: module title. */ __( 'Toggle %s module', 'siteintelix' ), (string) $module['title'] ) ); ?>">
						<input type="checkbox" value="<?php echo esc_attr( $module_id ); ?>" data-siteintelix-module-toggle <?php checked( $is_enabled ); ?>>
						<span class="sitx-toggle__slider"></span>
					</label>
				<?php elseif ( $is_available ) : ?>
					<span class="sitx-module-card__soon"><?php esc_html_e( 'Restricted', 'siteintelix' ); ?></span>
				<?php else : ?>
					<span class="sitx-module-card__soon"><?php esc_html_e( 'Coming soon', 'siteintelix' ); ?></span>
				<?php endif; ?>
			</div>
		</article>
		<?php
	}
}
?>
<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-modules-page">

	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-screenoptions',
			'title'       => __( 'SiteIntelix Modules', 'siteintelix' ),
			'description' => __( 'Turn tools on or off and build the admin toolbox your site needs.', 'siteintelix' ),
			'badges'      => array(
				'<span class="siteintelix-version-pill">v' . esc_html( SITEINTELIX_VERSION ) . '</span>',
				SITEINTELIX_Admin_UI::badge(
					sprintf(
						/* translators: %d: active module count. */
						esc_html( _n( '%d Active Module', '%d Active Modules', $siteintelix_active_count, 'siteintelix' ) ),
						absint( $siteintelix_active_count )
					),
					'info',
					'dashicons-admin-plugins'
				),
			),
		)
	);
	?>

	<div class="siteintelix-container">
		<?php if ( $siteintelix_modules_saved ) : ?>
			<div class="sitx-alert sitx-alert--success">
				<div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div>
				<div class="sitx-alert__content">
					<strong class="sitx-alert__title"><?php esc_html_e( 'Modules updated.', 'siteintelix' ); ?></strong>
					<p class="sitx-alert__msg"><?php esc_html_e( 'Your SiteIntelix admin menu now reflects the enabled modules.', 'siteintelix' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<div class="sitx-modules-toolbar si-toolbar">
			<div>
				<h2 class="sitx-section-title"><?php esc_html_e( 'Toolbox Modules', 'siteintelix' ); ?></h2>
				<p class="sitx-section-desc"><?php esc_html_e( 'Enabled modules appear as submenu items under SiteIntelix and load only their own code.', 'siteintelix' ); ?></p>
			</div>
			<label class="sitx-module-search">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Search modules', 'siteintelix' ); ?></span>
				<input type="search" placeholder="<?php esc_attr_e( 'Search modules...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search modules', 'siteintelix' ); ?>" data-siteintelix-module-search>
			</label>
		</div>

		<?php if ( ! empty( $siteintelix_active_modules ) ) : ?>
			<section class="sitx-modules-section">
				<h2 class="sitx-modules-section__title"><?php esc_html_e( 'Active Modules', 'siteintelix' ); ?></h2>
				<div class="sitx-modules-grid">
					<?php foreach ( $siteintelix_active_modules as $siteintelix_module ) : ?>
						<?php siteintelix_render_module_card( $siteintelix_module, true ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="sitx-modules-section">
			<h2 class="sitx-modules-section__title"><?php esc_html_e( 'Available Modules', 'siteintelix' ); ?></h2>
			<div class="sitx-modules-grid">
				<?php foreach ( $siteintelix_available_modules as $siteintelix_module ) : ?>
					<?php siteintelix_render_module_card( $siteintelix_module, false ); ?>
				<?php endforeach; ?>
			</div>
		</section>
	</div>
</div>
