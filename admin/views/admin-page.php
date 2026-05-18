<?php
/**
 * Admin dashboard view for SiteIntelix.
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$siteintelix_info         = SITEINTELIX_System_Info::get_all();
$siteintelix_checks       = SITEINTELIX_Health_Check::run( $siteintelix_info );
$siteintelix_overall      = SITEINTELIX_Health_Check::overall_status( $siteintelix_checks );
$siteintelix_modules      = SITEINTELIX_Modules::get_all();
$siteintelix_enabled      = SITEINTELIX_Modules::get_enabled();
$siteintelix_active_count = count( $siteintelix_enabled );

/**
 * Render a coloured status badge.
 *
 * @param string $status good | warning | critical.
 * @return string
 */
function siteintelix_badge( $status ) {
	$labels = array(
		SITEINTELIX_Health_Check::STATUS_GOOD     => __( 'Good', 'siteintelix' ),
		SITEINTELIX_Health_Check::STATUS_WARNING  => __( 'Warning', 'siteintelix' ),
		SITEINTELIX_Health_Check::STATUS_CRITICAL => __( 'Critical', 'siteintelix' ),
	);
	$label = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );

	return '<span class="sitx-badge sitx-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

/**
 * Output a single overview table row.
 *
 * @param string $label Row label.
 * @param string $value Row value; may contain pre-escaped safe HTML.
 * @return void
 */
function siteintelix_row( $label, $value ) {
	echo '<tr>';
	echo '<th scope="row">' . esc_html( $label ) . '</th>';
	echo '<td>' . wp_kses_post( $value ) . '</td>';
	echo '</tr>';
}
?>
<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-dashboard">
	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<?php
	SITEINTELIX_Admin_UI::page_header(
		array(
			'icon'        => 'dashicons-chart-area',
			'title'       => __( 'SiteIntelix', 'siteintelix' ),
			'description' => __( 'A modular WordPress toolbox for diagnostics, logs, email, and site utilities.', 'siteintelix' ),
			'badges'      => array(
				'<span class="siteintelix-version-pill">v' . esc_html( SITEINTELIX_VERSION ) . '</span>',
				SITEINTELIX_Admin_UI::badge(
					sprintf(
						/* translators: %d: active module count. */
						_n( '%d Active Module', '%d Active Modules', $siteintelix_active_count, 'siteintelix' ),
						absint( $siteintelix_active_count )
					),
					'info',
					'dashicons-groups'
				),
			),
		)
	);
	?>

	<div class="siteintelix-container">
		<script id="siteintelix-data-json" type="application/json">
			<?php echo wp_json_encode( $siteintelix_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); ?>
		</script>

		<?php $siteintelix_featured_checks = array( 'php_version', 'memory_limit', 'debug_mode', 'https', 'rest_api', 'cron' ); ?>
		<div class="siteintelix-health-strip" role="list" aria-label="<?php esc_attr_e( 'Health check summary', 'siteintelix' ); ?>">
			<?php foreach ( $siteintelix_featured_checks as $siteintelix_check_key ) : ?>
				<?php
				if ( empty( $siteintelix_checks[ $siteintelix_check_key ] ) ) {
					continue;
				}
				$siteintelix_check = $siteintelix_checks[ $siteintelix_check_key ];
				?>
				<div class="siteintelix-health-pill siteintelix-health-pill--<?php echo esc_attr( $siteintelix_check['status'] ); ?>" title="<?php echo esc_attr( $siteintelix_check['message'] ); ?>" role="listitem" tabindex="0">
					<span class="siteintelix-health-pill__name"><?php echo esc_html( $siteintelix_check['label'] ); ?></span>
					<span class="siteintelix-health-pill__value"><?php echo esc_html( $siteintelix_check['value'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="siteintelix-overview-minimal-grid">
			<section class="sitx-card si-card siteintelix-overview-card">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="sitx-module-card__icon sitx-module-card__icon--blue"><span class="dashicons dashicons-portfolio" aria-hidden="true"></span></span>
						<?php esc_html_e( 'System Overview', 'siteintelix' ); ?>
					</h2>
					<?php echo wp_kses_post( siteintelix_badge( $siteintelix_overall ) ); ?>
				</header>

				<table class="siteintelix-overview-table">
					<tbody>
							<?php
							siteintelix_row( __( 'Site Title', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['site_title'] ) );
							siteintelix_row( __( 'Site URL', 'siteintelix' ), '<a href="' . esc_url( $siteintelix_info['wordpress']['site_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $siteintelix_info['wordpress']['site_url'] ) . '</a>' );
							siteintelix_row( __( 'WordPress Version', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['wp_version'] ) );
							siteintelix_row( __( 'Active Theme', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['active_theme'] ) );
							siteintelix_row( __( 'PHP Version', 'siteintelix' ), esc_html( $siteintelix_info['server']['php_version'] ) );
							siteintelix_row( __( 'MySQL Version', 'siteintelix' ), esc_html( $siteintelix_info['server']['mysql_version'] ) );
							siteintelix_row( __( 'Memory Limit', 'siteintelix' ), esc_html( $siteintelix_info['server']['memory_limit'] ) );
							siteintelix_row( __( 'HTTPS', 'siteintelix' ), esc_html( $siteintelix_checks['https']['value'] ) );
							siteintelix_row( __( 'WP Cron', 'siteintelix' ), esc_html( $siteintelix_checks['cron']['value'] ) );
							siteintelix_row( __( 'Debug Mode', 'siteintelix' ), esc_html( $siteintelix_checks['debug_mode']['value'] ) );
							?>
					</tbody>
				</table>

				<div class="siteintelix-overview-actions">
					<?php
						echo SITEINTELIX_Admin_UI::button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes attributes and label.
							array(
								'label'      => __( 'Copy Report', 'siteintelix' ),
								'variant'    => 'secondary',
								'icon'       => 'dashicons-clipboard',
								'attributes' => array(
									'id'         => 'siteintelix-copy-btn',
									'aria-label' => __( 'Copy report', 'siteintelix' ),
								)
							)
						);
					echo SITEINTELIX_Admin_UI::button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper escapes attributes and label.
						array(
							'label'      => __( 'Export JSON', 'siteintelix' ),
							'variant'    => 'primary',
							'icon'       => 'dashicons-download',
							'attributes' => array(
								'id'         => 'siteintelix-export-btn',
								'aria-label' => __( 'Export JSON', 'siteintelix' ),
							),
						)
					);
					?>
				</div>
			</section>

			<section class="sitx-card si-card siteintelix-overview-card">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="sitx-module-card__icon sitx-module-card__icon--blue"><span class="dashicons dashicons-admin-tools" aria-hidden="true"></span></span>
						<?php esc_html_e( 'Active Modules', 'siteintelix' ); ?>
					</h2>
					<span class="sitx-badge sitx-badge--good">
						<?php
						printf(
							/* translators: %d: active module count. */
							esc_html( _n( '%d Active', '%d Active', $siteintelix_active_count, 'siteintelix' ) ),
							absint( $siteintelix_active_count )
						);
						?>
					</span>
				</header>

				<div class="siteintelix-overview-modules">
					<?php foreach ( $siteintelix_modules as $siteintelix_module ) : ?>
						<?php
						if ( empty( $siteintelix_module['available'] ) ) {
							continue;
						}
						$siteintelix_module_id  = sanitize_key( $siteintelix_module['id'] );
						$siteintelix_is_enabled = in_array( $siteintelix_module_id, $siteintelix_enabled, true );
						$siteintelix_color      = isset( $siteintelix_module['color'] ) ? sanitize_key( $siteintelix_module['color'] ) : 'blue';
						?>
						<div class="siteintelix-overview-module <?php echo $siteintelix_is_enabled ? 'is-active' : ''; ?>" data-siteintelix-module-card data-module-id="<?php echo esc_attr( $siteintelix_module_id ); ?>" data-module-title="<?php echo esc_attr( strtolower( (string) $siteintelix_module['title'] ) ); ?>" data-module-description="<?php echo esc_attr( strtolower( (string) $siteintelix_module['description'] ) ); ?>">
							<span class="sitx-module-card__icon sitx-module-card__icon--<?php echo esc_attr( $siteintelix_color ); ?>">
								<span class="dashicons <?php echo esc_attr( (string) $siteintelix_module['icon'] ); ?>" aria-hidden="true"></span>
							</span>
							<span class="siteintelix-overview-module__body">
								<strong><?php echo esc_html( (string) $siteintelix_module['title'] ); ?></strong>
								<span><?php echo esc_html( (string) $siteintelix_module['description'] ); ?></span>
							</span>
							<label class="sitx-toggle sitx-toggle--module" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: module title. */ __( 'Toggle %s module', 'siteintelix' ), (string) $siteintelix_module['title'] ) ); ?>">
								<input type="checkbox" value="<?php echo esc_attr( $siteintelix_module_id ); ?>" data-siteintelix-module-toggle <?php checked( $siteintelix_is_enabled ); ?>>
								<span class="sitx-toggle__slider"></span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		</div>

		</div>
	</div>
