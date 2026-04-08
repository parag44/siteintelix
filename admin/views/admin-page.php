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
	return '<span class="siteintelix-badge siteintelix-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
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

	<!-- ===== Page Header ===== -->
	<div class="siteintelix-header">
		<div class="siteintelix-header__inner">

			<!-- Title group -->
			<div class="siteintelix-header__title-group">
				<span class="siteintelix-header__icon dashicons dashicons-chart-area" aria-hidden="true"></span>
				<h1 class="siteintelix-header__title">
					<?php esc_html_e( 'SiteIntelix', 'siteintelix' ); ?>
				</h1>
				<span class="siteintelix-version-pill">v<?php echo esc_html( SITEINTELIX_VERSION ); ?></span>
			</div>

			<!-- Overall health pill -->
			<div class="siteintelix-overall siteintelix-overall--<?php echo esc_attr( $siteintelix_overall ); ?>" role="status">
				<span class="siteintelix-overall__dot" aria-hidden="true"></span>
				<span class="siteintelix-overall__label">
					<?php
					$siteintelix_overall_labels = array(
						'good'     => __( 'System Healthy', 'siteintelix' ),
						'warning'  => __( 'Needs Attention', 'siteintelix' ),
						'critical' => __( 'Critical Issues Found', 'siteintelix' ),
					);
					echo esc_html( isset( $siteintelix_overall_labels[ $siteintelix_overall ] ) ? $siteintelix_overall_labels[ $siteintelix_overall ] : $siteintelix_overall );
					?>
				</span>
			</div>

			<!-- Action buttons -->
			<div class="siteintelix-header__actions">
				<button
					id="siteintelix-copy-btn"
					type="button"
					class="siteintelix-btn siteintelix-btn--secondary"
					aria-label="<?php esc_attr_e( 'Copy system report to clipboard', 'siteintelix' ); ?>"
				>
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<?php esc_html_e( 'Copy Report', 'siteintelix' ); ?>
				</button>
				<button
					id="siteintelix-export-btn"
					type="button"
					class="siteintelix-btn siteintelix-btn--primary"
					aria-label="<?php esc_attr_e( 'Export system info as JSON file', 'siteintelix' ); ?>"
				>
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Export JSON', 'siteintelix' ); ?>
				</button>
			</div>

		</div><!-- /.siteintelix-header__inner -->
	</div><!-- /.siteintelix-header -->

	<!-- Hidden JSON data island — consumed by admin.js, never rendered. -->
	<script id="siteintelix-data-json" type="application/json">
		<?php
		echo wp_json_encode(
			$siteintelix_info,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		?>
	</script>

	<!-- ===== Health check strip ===== -->
	<div class="siteintelix-health-strip" role="list" aria-label="<?php esc_attr_e( 'Health check summary', 'siteintelix' ); ?>">
		<?php foreach ( $siteintelix_checks as $siteintelix_check ) : ?>
			<div
				class="siteintelix-health-pill siteintelix-health-pill--<?php echo esc_attr( $siteintelix_check['status'] ); ?>"
				title="<?php echo esc_attr( $siteintelix_check['message'] ); ?>"
				role="listitem"
				tabindex="0"
			>
				<span class="siteintelix-health-pill__dot" aria-hidden="true"></span>
				<span class="siteintelix-health-pill__name"><?php echo esc_html( $siteintelix_check['label'] ); ?></span>
				<span class="siteintelix-health-pill__value"><?php echo esc_html( $siteintelix_check['value'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<!-- ===== Card grid ===== -->
	<div class="siteintelix-grid">

		<!-- ── WordPress card ─────────────────────────────────────────── -->
		<section class="siteintelix-card" id="siteintelix-card-wordpress" aria-labelledby="siteintelix-card-wp-title">
			<div class="siteintelix-card__header">
				<span class="siteintelix-card__icon dashicons dashicons-wordpress-alt" aria-hidden="true"></span>
				<h2 class="siteintelix-card__title" id="siteintelix-card-wp-title">
					<?php esc_html_e( 'WordPress', 'siteintelix' ); ?>
				</h2>
				<?php echo wp_kses_post( siteintelix_badge( $siteintelix_checks['wp_version']['status'] ) ); ?>
			</div>

			<div class="siteintelix-card__body">
				<table class="siteintelix-table">
					<tbody>
						<?php
						siteintelix_row( __( 'WP Version', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['wp_version'] ) . ' ' . siteintelix_badge( $siteintelix_checks['wp_version']['status'] ) );
						siteintelix_row( __( 'Site URL', 'siteintelix' ), '<a href="' . esc_url( $siteintelix_info['wordpress']['site_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $siteintelix_info['wordpress']['site_url'] ) . '</a>' );
						siteintelix_row( __( 'Home URL', 'siteintelix' ), '<a href="' . esc_url( $siteintelix_info['wordpress']['home_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $siteintelix_info['wordpress']['home_url'] ) . '</a>' );
						siteintelix_row( __( 'Active Theme', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['active_theme'] ) );
						siteintelix_row( __( 'Language', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['language'] ) );
						siteintelix_row( __( 'Charset', 'siteintelix' ), esc_html( $siteintelix_info['wordpress']['charset'] ) );
						siteintelix_row( __( 'Multisite', 'siteintelix' ), $siteintelix_info['wordpress']['multisite'] ? esc_html__( 'Yes', 'siteintelix' ) : esc_html__( 'No', 'siteintelix' ) );
						?>
					</tbody>
				</table>

				<!-- Active plugins sub-panel -->
				<div class="siteintelix-subpanel">
					<h3 class="siteintelix-subpanel__title">
						<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
						<?php
						printf(
							/* translators: %d: number of active plugins */
							esc_html__( 'Active Plugins (%d)', 'siteintelix' ),
							count( $siteintelix_info['wordpress']['active_plugins'] )
						);
						?>
					</h3>
					<ul class="siteintelix-plugin-list">
						<?php foreach ( $siteintelix_info['wordpress']['active_plugins'] as $siteintelix_plugin_name ) : ?>
							<li class="siteintelix-plugin-list__item">
								<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
								<?php echo esc_html( $siteintelix_plugin_name ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div><!-- /.siteintelix-card__body -->
		</section>

		<!-- ── Server card ────────────────────────────────────────────── -->
		<section class="siteintelix-card" id="siteintelix-card-server" aria-labelledby="siteintelix-card-server-title">
			<div class="siteintelix-card__header">
				<span class="siteintelix-card__icon dashicons dashicons-admin-network" aria-hidden="true"></span>
				<h2 class="siteintelix-card__title" id="siteintelix-card-server-title">
					<?php esc_html_e( 'Server', 'siteintelix' ); ?>
				</h2>
				<?php echo wp_kses_post( siteintelix_badge( $siteintelix_checks['php_version']['status'] ) ); ?>
			</div>

			<div class="siteintelix-card__body">
				<table class="siteintelix-table">
					<tbody>
						<?php
						siteintelix_row( __( 'PHP Version', 'siteintelix' ), esc_html( $siteintelix_info['server']['php_version'] ) . ' ' . siteintelix_badge( $siteintelix_checks['php_version']['status'] ) );
						siteintelix_row( __( 'PHP SAPI', 'siteintelix' ), esc_html( $siteintelix_info['server']['php_sapi'] ) );
						siteintelix_row( __( 'Server Software', 'siteintelix' ), esc_html( $siteintelix_info['server']['server_software'] ) );
						siteintelix_row( __( 'MySQL / MariaDB', 'siteintelix' ), esc_html( $siteintelix_info['server']['mysql_version'] ) . ' ' . siteintelix_badge( $siteintelix_checks['mysql_version']['status'] ) );
						siteintelix_row( __( 'Memory Limit', 'siteintelix' ), esc_html( $siteintelix_info['server']['memory_limit'] ) . ' ' . siteintelix_badge( $siteintelix_checks['memory_limit']['status'] ) );
						siteintelix_row( __( 'Max Upload Size', 'siteintelix' ), esc_html( $siteintelix_info['server']['max_upload_size'] ) );
						siteintelix_row( __( 'Max Execution Time', 'siteintelix' ), esc_html( $siteintelix_info['server']['max_exec_time'] ) );
						siteintelix_row( __( 'Post Max Size', 'siteintelix' ), esc_html( $siteintelix_info['server']['post_max_size'] ) );
						siteintelix_row( __( 'Operating System', 'siteintelix' ), esc_html( $siteintelix_info['server']['os'] ) );
						siteintelix_row( __( 'Architecture', 'siteintelix' ), esc_html( $siteintelix_info['server']['architecture'] ) );
						?>
					</tbody>
				</table>
			</div>
		</section>

		<!-- ── Environment card ───────────────────────────────────────── -->
		<section class="siteintelix-card" id="siteintelix-card-environment" aria-labelledby="siteintelix-card-env-title">
			<div class="siteintelix-card__header">
				<span class="siteintelix-card__icon dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
				<h2 class="siteintelix-card__title" id="siteintelix-card-env-title">
					<?php esc_html_e( 'Environment', 'siteintelix' ); ?>
				</h2>
				<?php echo wp_kses_post( siteintelix_badge( $siteintelix_overall ) ); ?>
			</div>

			<div class="siteintelix-card__body">
				<table class="siteintelix-table">
					<tbody>
						<?php
						siteintelix_row( __( 'REST API', 'siteintelix' ), esc_html( $siteintelix_checks['rest_api']['value'] ) . ' ' . siteintelix_badge( $siteintelix_checks['rest_api']['status'] ) );
						siteintelix_row( __( 'WP_DEBUG', 'siteintelix' ), esc_html( $siteintelix_checks['debug_mode']['value'] ) . ' ' . siteintelix_badge( $siteintelix_checks['debug_mode']['status'] ) );
						siteintelix_row( __( 'Debug Log', 'siteintelix' ), $siteintelix_info['environment']['debug_log'] ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ) );
						siteintelix_row( __( 'WP-Cron', 'siteintelix' ), esc_html( $siteintelix_checks['cron']['value'] ) . ' ' . siteintelix_badge( $siteintelix_checks['cron']['status'] ) );
						siteintelix_row( __( 'HTTPS', 'siteintelix' ), esc_html( $siteintelix_checks['https']['value'] ) . ' ' . siteintelix_badge( $siteintelix_checks['https']['status'] ) );
						siteintelix_row( __( 'Environment Type', 'siteintelix' ), esc_html( $siteintelix_info['environment']['environment'] ) );
						siteintelix_row( __( 'Object Cache', 'siteintelix' ), $siteintelix_info['environment']['cache'] ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ) );
						siteintelix_row( __( 'Script Debug', 'siteintelix' ), $siteintelix_info['environment']['script_debug'] ? esc_html__( 'Enabled', 'siteintelix' ) : esc_html__( 'Disabled', 'siteintelix' ) );
						?>
					</tbody>
				</table>
			</div>

			<!-- Health warnings -->
			<?php
			$siteintelix_warnings = array_filter(
				$siteintelix_checks,
				function ( $c ) {
					return SITEINTELIX_Health_Check::STATUS_GOOD !== $c['status'];
				}
			);
			if ( ! empty( $siteintelix_warnings ) ) :
				?>
			<div class="siteintelix-warnings">
				<h3 class="siteintelix-warnings__title">
					<span class="dashicons dashicons-warning" aria-hidden="true"></span>
					<?php esc_html_e( 'Health Warnings', 'siteintelix' ); ?>
				</h3>
				<ul class="siteintelix-warnings__list">
					<?php foreach ( $siteintelix_warnings as $siteintelix_warning ) : ?>
						<li class="siteintelix-warnings__item siteintelix-warnings__item--<?php echo esc_attr( $siteintelix_warning['status'] ); ?>">
							<strong><?php echo esc_html( $siteintelix_warning['label'] ); ?>:</strong>
							<?php echo esc_html( $siteintelix_warning['message'] ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</section>

	</div><!-- /.siteintelix-grid -->

	<!-- ===== REST API endpoints reference ===== -->
	<div class="siteintelix-api-panel">
		<h3 class="siteintelix-api-panel__title">
			<span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
			<?php esc_html_e( 'REST API Endpoints', 'siteintelix' ); ?>
		</h3>
		<div class="siteintelix-api-panel__list">
			<?php
			$siteintelix_endpoints = array(
				array( rest_url( 'siteintelix/v1/info' ), __( 'Full system information', 'siteintelix' ) ),
				array( rest_url( 'siteintelix/v1/health' ), __( 'Health check results', 'siteintelix' ) ),
			);
			foreach ( $siteintelix_endpoints as $siteintelix_endpoint ) :
				?>
				<div class="siteintelix-endpoint">
					<span class="siteintelix-endpoint__method">GET</span>
					<code class="siteintelix-endpoint__url"><?php echo esc_html( $siteintelix_endpoint[0] ); ?></code>
					<span class="siteintelix-endpoint__desc"><?php echo esc_html( $siteintelix_endpoint[1] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="siteintelix-api-panel__note">
			<?php esc_html_e( 'Requires authentication as an Administrator.', 'siteintelix' ); ?>
		</p>
	</div>

	<!-- ===== Footer ===== -->
	<p class="siteintelix-footer">
		<?php
		printf(
			/* translators: 1: plugin name (bold), 2: generation timestamp */
			wp_kses( __( '%1$s &mdash; Report generated on %2$s', 'siteintelix' ), array( 'strong' => array() ) ),
			'<strong>SiteIntelix</strong>',
			esc_html( current_time( 'Y-m-d H:i:s' ) )
		);
		?>
	</p>

</div><!-- /.wrap.siteintelix-wrap -->
