<?php
/**
 * Admin dashboard view for SiteIntelix.
 *
 * Loaded by siteintelix_render_admin_page() which has already verified the
 * manage_options capability and ABSPATH guard before including this file.
 *
 * @package SiteIntelix
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Collect data once per page load.
// ---------------------------------------------------------------------------
$siteintelix_info    = SITEINTELIX_System_Info::get_all();
$siteintelix_checks  = SITEINTELIX_Health_Check::run( $siteintelix_info );
$siteintelix_overall = SITEINTELIX_Health_Check::overall_status( $siteintelix_checks );

// ---------------------------------------------------------------------------
// Template helpers (scoped to this file).
// ---------------------------------------------------------------------------

/**
 * Render a coloured status badge.
 *
 * @param string $status  good | warning | critical
 * @return string  Escaped HTML.
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
 * Output a single table row inside an info card.
 *
 * @param string $label  Row label (plain text).
 * @param string $value  Row value — may contain pre-escaped HTML (badge, link).
 */
function siteintelix_row( $label, $value ) {
	echo '<tr>';
	echo '<th scope="row">' . esc_html( $label ) . '</th>';
	echo '<td>' . wp_kses_post( $value ) . '</td>';
	echo '</tr>';
}
?>
<div class="wrap siteintelix-wrap" id="siteintelix-dashboard">

	<!-- Third-party admin notices are rendered here above SiteIntelix UI. -->
	<div id="siteintelix-notices-slot" class="siteintelix-notices-slot" aria-live="polite"></div>

	<!-- ===== Page Header ===== -->
	<header class="siteintelix-header">
		<div class="siteintelix-header__content">
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-chart-area" aria-hidden="true"></span>
				<div class="siteintelix-header__text">
					<h1 class="siteintelix-header__title"><?php esc_html_e( 'SiteIntelix', 'siteintelix' ); ?></h1>
					<p class="siteintelix-header__desc"><?php esc_html_e( 'Monitor your WordPress, server, and environment health.', 'siteintelix' ); ?></p>
				</div>
			</div>

			<div class="siteintelix-header__actions">
				<span class="sitx-badge sitx-badge--<?php echo esc_attr( $siteintelix_overall ); ?>">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<?php
					$overall_labels = array(
						'good'     => __( 'System Healthy', 'siteintelix' ),
						'warning'  => __( 'Needs Attention', 'siteintelix' ),
						'critical' => __( 'Critical Issues Found', 'siteintelix' ),
					);
					echo esc_html( isset( $overall_labels[ $siteintelix_overall ] ) ? $overall_labels[ $siteintelix_overall ] : $siteintelix_overall );
					?>
				</span>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
				
				<button id="siteintelix-copy-btn" type="button" class="sitx-btn sitx-btn--white" aria-label="<?php esc_attr_e( 'Copy report', 'siteintelix' ); ?>">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy Report', 'siteintelix' ); ?>
				</button>
				<button id="siteintelix-export-btn" type="button" class="sitx-btn sitx-btn--primary" aria-label="<?php esc_attr_e( 'Export JSON', 'siteintelix' ); ?>">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Export JSON', 'siteintelix' ); ?>
				</button>
			</div>
		</div>
	</header>

	<div class="siteintelix-container">

		<!-- Hidden JSON data island consumed by JS -->
		<script id="siteintelix-data-json" type="application/json">
			<?php echo wp_json_encode( $siteintelix_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); ?>
		</script>

		<!-- ===== Health Metrics Strip ===== -->
		<div class="siteintelix-health-strip" role="list" aria-label="<?php esc_attr_e( 'Health check summary', 'siteintelix' ); ?>">
			<?php foreach ( $siteintelix_checks as $check ) : ?>
				<div class="siteintelix-health-pill siteintelix-health-pill--<?php echo esc_attr( $check['status'] ); ?>" title="<?php echo esc_attr( $check['message'] ); ?>" role="listitem" tabindex="0">
					<span class="siteintelix-health-pill__name"><?php echo esc_html( $check['label'] ); ?></span>
					<span class="siteintelix-health-pill__value"><?php echo esc_html( $check['value'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- ===== Main Detail Cards ===== -->
		<div class="siteintelix-grid">

			<!-- ── WordPress Card ── -->
			<section class="sitx-card" id="siteintelix-card-wordpress">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="dashicons dashicons-wordpress-alt" aria-hidden="true"></span>
						<?php esc_html_e( 'WordPress', 'siteintelix' ); ?>
					</h2>
					<span class="sitx-badge sitx-badge--<?php echo esc_attr( $siteintelix_checks['wp_version']['status'] ); ?>">
						<?php echo esc_html( $siteintelix_checks['wp_version']['status'] ); ?>
					</span>
				</header>

				<div class="sitx-card__body">
					<table class="siteintelix-table">
						<tbody>
						<?php
							siteintelix_row( __( 'Site Title', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['site_title'] ) );
							siteintelix_row( __( 'WP Version', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['wp_version'] ) );
							siteintelix_row( __( 'Site URL', 'siteintelix' ), '<a href="' . esc_url( $siteintelix_info['wordpress']['site_url'] ) . '" target="_blank">' . esc_html( $siteintelix_info['wordpress']['site_url'] ) . '</a>' );
							siteintelix_row( __( 'Admin Email', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['admin_email'] ) );
							siteintelix_row( __( 'Active Theme', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['active_theme'] ) );
							siteintelix_row( __( 'Multisite', 'siteintelix' ), $siteintelix_info['wordpress']['multisite'] ? esc_html__( 'Yes', 'siteintelix' ) : esc_html__( 'No', 'siteintelix' ) );
						?>
						</tbody>
					</table>

					<div class="siteintelix-subpanel">
						<h3 class="siteintelix-subpanel__title">
							<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
							<?php printf( esc_html__( 'Active Plugins (%d)', 'siteintelix' ), count( $siteintelix_info['wordpress']['active_plugins'] ) ); ?>
						</h3>
						<ul class="siteintelix-plugin-list">
							<?php foreach ( array_slice( $siteintelix_info['wordpress']['active_plugins'], 0, 8 ) as $plugin_name ) : ?>
								<li class="siteintelix-plugin-list__item"><?php echo esc_html( $plugin_name ); ?></li>
							<?php endforeach; ?>
							<?php if ( count( $siteintelix_info['wordpress']['active_plugins'] ) > 8 ) : ?>
								<li class="siteintelix-plugin-list__more">+ <?php echo count( $siteintelix_info['wordpress']['active_plugins'] ) - 8; ?> more</li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</section>

			<!-- ── Server Card ── -->
			<section class="sitx-card" id="siteintelix-card-server">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
						<?php esc_html_e( 'Server', 'siteintelix' ); ?>
					</h2>
					<span class="sitx-badge sitx-badge--<?php echo esc_attr( $siteintelix_checks['php_version']['status'] ); ?>">
						<?php echo esc_html( $siteintelix_checks['php_version']['status'] ); ?>
					</span>
				</header>

				<div class="sitx-card__body">
					<table class="siteintelix-table">
						<tbody>
						<?php
							siteintelix_row( __( 'PHP Version', 'siteintelix' ), esc_html( $siteintelix_info['server']['php_version'] ) );
							siteintelix_row( __( 'Memory Limit', 'siteintelix' ), esc_html( $siteintelix_info['server']['memory_limit'] ) );
							siteintelix_row( __( 'Max Upload', 'siteintelix' ), esc_html( $siteintelix_info['server']['max_upload_size'] ) );
							siteintelix_row( __( 'Database', 'siteintelix' ), esc_html( $siteintelix_info['server']['mysql_version'] ) );
							siteintelix_row( __( 'Disk Free', 'siteintelix' ), esc_html( $siteintelix_info['server']['disk_free'] ) );
							siteintelix_row( __( 'Operating System', 'siteintelix' ), esc_html( $siteintelix_info['server']['os'] ) );
						?>
						</tbody>
					</table>
				</div>
			</section>

			<!-- ── Environment Card ── -->
			<section class="sitx-card" id="siteintelix-card-environment">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
						<?php esc_html_e( 'Environment', 'siteintelix' ); ?>
					</h2>
					<span class="sitx-badge sitx-badge--<?php echo esc_attr( $siteintelix_overall ); ?>">
						<?php echo esc_html( $siteintelix_overall ); ?>
					</span>
				</header>

				<div class="sitx-card__body">
					<table class="siteintelix-table">
						<tbody>
						<?php
							siteintelix_row( __( 'REST API', 'siteintelix' ), esc_html( $siteintelix_checks['rest_api']['value'] ) );
							siteintelix_row( __( 'WP_DEBUG', 'siteintelix' ), esc_html( $siteintelix_checks['debug_mode']['value'] ) );
							siteintelix_row( __( 'HTTPS', 'siteintelix' ), esc_html( $siteintelix_checks['https']['value'] ) );
							siteintelix_row( __( 'Environment Type', 'siteintelix' ), esc_html( $siteintelix_info['environment']['environment'] ) );
							siteintelix_row( __( 'Core Auto-Updates', 'siteintelix' ), is_string( $siteintelix_info['environment']['auto_update'] ) ? esc_html( $siteintelix_info['environment']['auto_update'] ) : ( $siteintelix_info['environment']['auto_update'] ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ) ) );
						?>
						</tbody>
					</table>

					<!-- Health warnings -->
					<?php
					$siteintelix_warnings = array_filter( $siteintelix_checks, function ( $c ) {
						return 'good' !== $c['status'];
					} );
					if ( ! empty( $siteintelix_warnings ) ) :
					?>
						<div class="sitx-warnings">
							<h3 class="sitx-warnings__title">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<?php esc_html_e( 'Health Warnings', 'siteintelix' ); ?>
							</h3>
							<ul class="sitx-warnings__list">
								<?php foreach ( $siteintelix_warnings as $chk ) : ?>
									<li class="sitx-warnings__item sitx-warnings__item--<?php echo esc_attr( $chk['status'] ); ?>">
										<strong><?php echo esc_html( $chk['label'] ); ?>:</strong> <?php echo esc_html( $chk['message'] ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<!-- ── API Card ── -->
			<section class="sitx-card" id="siteintelix-card-api">
				<header class="sitx-card__header">
					<h2 class="sitx-card__title">
						<span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
						<?php esc_html_e( 'REST API Endpoints', 'siteintelix' ); ?>
					</h2>
					<span class="sitx-badge sitx-badge--info"><?php esc_html_e( 'Secure', 'siteintelix' ); ?></span>
				</header>

				<div class="sitx-card__body">
					<div class="sitx-api-list">
						<div class="sitx-api-item">
							<code class="sitx-api-item__url">/siteintelix/v1/info</code>
							<span class="sitx-api-item__desc"><?php esc_html_e( 'System diagnostics data', 'siteintelix' ); ?></span>
						</div>
						<div class="sitx-api-item">
							<code class="sitx-api-item__url">/siteintelix/v1/health</code>
							<span class="sitx-api-item__desc"><?php esc_html_e( 'Individual health checks', 'siteintelix' ); ?></span>
						</div>
					</div>
					<p class="sitx-card__meta-note">
						<?php esc_html_e( 'Requires authentication as an Administrator.', 'siteintelix' ); ?>
					</p>
				</div>
			</section>

		</div><!-- /.siteintelix-grid -->

		<!-- ===== Footer ===== -->
		<footer class="siteintelix-footer">
			<p>
				<?php
				printf(
					/* translators: 1: plugin name, 2: generation timestamp */
					wp_kses( __( '%1$s &mdash; Report generated on %2$s', 'siteintelix' ), array( 'strong' => array() ) ),
					'<strong>SiteIntelix</strong>',
					esc_html( current_time( 'Y-m-d H:i:s' ) )
				);
				?>
			</p>
		</footer>

	</div><!-- /.siteintelix-container -->
</div><!-- /.siteintelix-wrap -->

