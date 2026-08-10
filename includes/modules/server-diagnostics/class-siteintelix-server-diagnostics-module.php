<?php
/**
 * Server Diagnostics module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks PHP, filesystem, network, WordPress, and database configuration.
 */
class SITEINTELIX_Server_Diagnostics_Module {

	const ENDPOINT_WORDPRESS = 'https://wordpress.org/';
	const ENDPOINT_API       = 'https://api.wordpress.org/';
	const CACHE_PREFIX       = 'siteintelix_server_diagnostics_cache_';
	const CACHE_TTL          = 5 * MINUTE_IN_SECONDS;

	/** @var array<string,array<string,mixed>> Cache metadata for the current report. */
	private static $cache_metadata = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 38 );
		add_action( 'admin_post_siteintelix_server_diag_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'wp_ajax_siteintelix_refresh_server_diagnostics', array( __CLASS__, 'handle_refresh' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'siteintelix',
			__( 'Server Diagnostics', 'siteintelix' ),
			__( 'Server Diagnostics', 'siteintelix' ),
			'manage_options',
			'siteintelix-server-diagnostics',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the diagnostics page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view server diagnostics.', 'siteintelix' ) );
		}

		$report           = self::get_report();
		$redacted_report = self::redact_report( $report );
		$sections        = self::get_section_definitions( $report );
		$json_url         = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'siteintelix_server_diag_export',
					'format' => 'json',
				),
				admin_url( 'admin-post.php' )
			),
			'siteintelix_server_diag_export'
		);
		$text_url = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'siteintelix_server_diag_export',
					'format' => 'text',
				),
				admin_url( 'admin-post.php' )
			),
			'siteintelix_server_diag_export'
		);
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap sitx-serverdiag-page" id="siteintelix-server-diagnostics-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-admin-site-alt3',
					'title'       => __( 'Server Diagnostics', 'siteintelix' ),
					'description' => __( 'Inspect server health, PHP configuration, extensions, filesystem, network, WordPress, and database details.', 'siteintelix' ),
					'badges'      => array(
						SITEINTELIX_Admin_UI::badge( 'v' . SITEINTELIX_VERSION, 'neutral' ),
						SITEINTELIX_Admin_UI::badge(
							sprintf(
								/* translators: %d: health score. */
								__( '%d%% Health', 'siteintelix' ),
								absint( $report['summary']['score'] )
							),
							self::score_badge_type( $report['summary']['score'] ),
							'dashicons-heart'
						),
					),
						'actions'     => array(
							SITEINTELIX_Admin_UI::button(
								array(
									'label'      => __( 'Refresh checks', 'siteintelix' ),
									'variant'    => 'secondary',
									'icon'       => 'dashicons-update',
									'attributes' => array(
										'data-sitx-diag-refresh' => 'true',
									),
								)
							),
							SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Download JSON', 'siteintelix' ),
								'url'     => $json_url,
								'variant' => 'secondary',
								'icon'    => 'dashicons-download',
								'classes' => array( 'sitx-serverdiag-header-secondary' ),
							)
						),
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Support Report', 'siteintelix' ),
								'url'     => $text_url,
								'variant' => 'primary',
								'icon'    => 'dashicons-clipboard',
								'classes' => array( 'sitx-serverdiag-header-secondary' ),
							)
						),
					),
				)
			);
			?>

			<div class="siteintelix-container sitx-serverdiag-container">
				<details class="sitx-serverdiag-actions-menu">
					<summary class="si-button"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e( 'Page actions', 'siteintelix' ); ?></summary>
					<div class="sitx-serverdiag-actions-menu__panel">
						<a href="<?php echo esc_url( $json_url ); ?>"><?php esc_html_e( 'Download JSON', 'siteintelix' ); ?></a>
						<a href="<?php echo esc_url( $text_url ); ?>"><?php esc_html_e( 'Export Report', 'siteintelix' ); ?></a>
						<button type="button" data-sitx-diag-copy-system-info-mobile aria-controls="sitx-serverdiag-copy-source"><?php esc_html_e( 'Copy System Info', 'siteintelix' ); ?></button>
					</div>
				</details>
				<p class="sitx-serverdiag-refresh-status" data-sitx-diag-refresh-status role="status" aria-live="polite">
					<?php echo esc_html( self::cache_status_text( $report ) ); ?>
				</p>
				<?php self::render_overview( $report ); ?>
				<?php self::render_filterbar( $report['summary'] ); ?>
				<div class="sitx-serverdiag-layout">
					<main class="sitx-serverdiag-main">
						<?php foreach ( $sections as $section_index => $section ) : ?>
							<?php self::render_section( $section, 0 === $section_index ); ?>
						<?php endforeach; ?>
					</main>
					<aside class="sitx-serverdiag-sidebar">
						<?php self::render_utilities( $json_url, $text_url, $redacted_report ); ?>
					</aside>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Export redacted diagnostics report.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export diagnostics.', 'siteintelix' ) );
		}

		check_admin_referer( 'siteintelix_server_diag_export' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified above.
		$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'json';
		$report = self::redact_report( self::get_report() );

		if ( 'text' === $format ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Content-Disposition: attachment; filename=siteintelix-server-diagnostics.txt' );
			echo self::format_text_report( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text download generated from sanitized diagnostics values.
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename=siteintelix-server-diagnostics.json' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Refresh expensive checks without blocking the page request.
	 *
	 * @return void
	 */
	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to refresh diagnostics.', 'siteintelix' ) ), 403 );
		}

		check_ajax_referer( 'siteintelix_refresh_server_diagnostics', 'nonce' );

		$rest = self::remote_check( rest_url( '/' ), false );
		set_transient(
			SITEINTELIX_System_Info::OVERVIEW_REMOTE_HEALTH_TRANSIENT,
			array(
				'available'    => 'pass' === $rest['status'],
				'stale'        => false,
				'collected_at' => current_time( 'mysql' ),
			),
			self::CACHE_TTL
		);

		$report = self::get_report( false, true );
		wp_send_json_success(
			array(
				'summary'      => $report['summary'],
				'generated_at' => $report['generated_at'],
			)
		);
	}

	/**
	 * Build and severity-sort diagnostics section presentation metadata.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_section_definitions( $report ) {
		$sections = array(
			array( 'key' => 'php-server', 'title' => __( 'PHP & Server', 'siteintelix' ), 'description' => __( 'Runtime, php.ini, limits, and critical PHP directives.', 'siteintelix' ), 'icon' => 'dashicons-editor-code', 'rows' => $report['php_server'] ),
			array( 'key' => 'extensions', 'title' => __( 'Extensions', 'siteintelix' ), 'description' => __( 'Important PHP extensions commonly required by WordPress plugins.', 'siteintelix' ), 'icon' => 'dashicons-admin-plugins', 'rows' => $report['extensions'] ),
			array( 'key' => 'filesystem', 'title' => __( 'Filesystem', 'siteintelix' ), 'description' => __( 'Writable paths, temporary storage, disk space, and file operation checks.', 'siteintelix' ), 'icon' => 'dashicons-media-default', 'rows' => $report['filesystem'] ),
			array( 'key' => 'network', 'title' => __( 'Network', 'siteintelix' ), 'description' => __( 'DNS, HTTPS, SSL verification, HTTP transports, proxy constants, and loopback reachability.', 'siteintelix' ), 'icon' => 'dashicons-admin-site-alt', 'rows' => $report['network'] ),
			array( 'key' => 'wordpress', 'title' => __( 'WordPress', 'siteintelix' ), 'description' => __( 'Core environment, debug constants, REST, loopback, cron, plugins, MU plugins, and drop-ins.', 'siteintelix' ), 'icon' => 'dashicons-wordpress', 'rows' => $report['wordpress'] ),
			array( 'key' => 'database', 'title' => __( 'Database', 'siteintelix' ), 'description' => __( 'Database engine, charset, key limits, database size, and autoloaded options.', 'siteintelix' ), 'icon' => 'dashicons-database', 'rows' => $report['database'] ),
		);

		foreach ( $sections as $index => &$section ) {
			$section['source_index'] = $index;
			$section['summary']      = self::section_summary( $section['rows'] );
		}
		unset( $section );

		$rank = array( 'danger' => 0, 'warning' => 1, 'pass' => 2, 'info' => 3 );
		usort(
			$sections,
			static function ( $left, $right ) use ( $rank ) {
				$severity_order = $rank[ $left['summary']['severity'] ] <=> $rank[ $right['summary']['severity'] ];
				return 0 !== $severity_order ? $severity_order : $left['source_index'] <=> $right['source_index'];
			}
		);

		return $sections;
	}

	/**
	 * Render overview cards.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return void
	 */
	private static function render_overview( $report ) {
		$summary = $report['summary'];
		?>
		<section class="sitx-serverdiag-health" aria-labelledby="sitx-serverdiag-health-title">
			<h2 id="sitx-serverdiag-health-title" class="screen-reader-text"><?php esc_html_e( 'Diagnostics health overview', 'siteintelix' ); ?></h2>
			<div class="sitx-serverdiag-stats">
			<?php
			self::render_stat_card( __( 'Health Score', 'siteintelix' ), $summary['score'] . '%', self::score_badge_type( $summary['score'] ), self::score_status_text( $summary['score'] ), true );
			self::render_stat_card( __( 'Critical Issues', 'siteintelix' ), (string) $summary['failed'], $summary['failed'] ? 'danger' : 'success', __( 'Checks that need attention', 'siteintelix' ) );
			self::render_stat_card( __( 'Warnings', 'siteintelix' ), (string) $summary['warnings'], $summary['warnings'] ? 'warning' : 'success', __( 'Recommended improvements', 'siteintelix' ) );
			self::render_stat_card( __( 'Passed Checks', 'siteintelix' ), (string) $summary['passed'], 'success', __( 'Checks meeting recommendations', 'siteintelix' ) );
			self::render_stat_card( __( 'Total Checks', 'siteintelix' ), (string) $summary['total'], 'neutral', __( 'Across all categories', 'siteintelix' ), false, 'sitx-serverdiag-stat--total' );
			?>
			</div>
			<?php if ( 0 === $summary['failed'] && 0 === $summary['warnings'] ) : ?>
				<div class="si-card sitx-serverdiag-healthy">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Everything looks healthy.', 'siteintelix' ); ?></strong>
					<p><?php esc_html_e( 'No action required.', 'siteintelix' ); ?></p>
				</div>
			<?php endif; ?>
		</section>
		<?php if ( ! empty( $summary['critical'] ) ) : ?>
			<div class="sitx-alert sitx-alert--warning sitx-serverdiag-alert">
				<div class="sitx-alert__icon"><span class="dashicons dashicons-warning" aria-hidden="true"></span></div>
				<div class="sitx-alert__content">
					<strong class="sitx-alert__title"><?php esc_html_e( 'Critical checks need attention', 'siteintelix' ); ?></strong>
					<ul>
						<?php foreach ( $summary['critical'] as $item ) : ?>
							<li><?php echo esc_html( $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>
		<?php
	}


	/**
	 * Render stat card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type  Badge type.
	 * @param string $meta  Meta text.
	 * @param bool   $primary Whether this is the primary KPI.
	 * @param string $class   Optional modifier class.
	 * @return void
	 */
	private static function render_stat_card( $label, $value, $type, $meta, $primary = false, $class = '' ) {
		$score = $primary ? min( 100, max( 0, absint( $value ) ) ) : 0;
		?>
			<div class="si-card sitx-serverdiag-stat sitx-serverdiag-stat--<?php echo esc_attr( sanitize_html_class( $type ) ); ?><?php echo $primary ? ' is-primary' : ''; ?> <?php echo esc_attr( sanitize_html_class( $class ) ); ?>">
				<?php if ( $primary ) : ?>
					<?php /* translators: %d: server health score percentage. */ ?>
					<span class="sitx-serverdiag-score-ring" style="<?php echo esc_attr( '--sitx-diag-score:' . $score ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Health score: %d percent', 'siteintelix' ), $score ) ); ?>">
					<strong><?php echo esc_html( $score . '%' ); ?></strong>
				</span>
				<span class="sitx-serverdiag-stat__content">
					<small><?php echo esc_html( $label ); ?></small>
					<strong><?php echo esc_html( $meta ); ?></strong>
					<em><?php esc_html_e( 'Current server health', 'siteintelix' ); ?></em>
				</span>
			<?php else : ?>
				<span><?php echo esc_html( $label ); ?></span>
				<strong><?php echo esc_html( $value ); ?></strong>
				<small><?php echo esc_html( $meta ); ?></small>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render diagnostics filters and bulk controls.
	 *
	 * @param array<string,mixed> $summary Summary.
	 * @return void
	 */
	private static function render_filterbar( $summary ) {
		$filters = array(
			'all'         => array( __( 'All', 'siteintelix' ), $summary['total'], self::filter_count_label( 'all', $summary['total'] ) ),
			'issues'      => array( __( 'Issues', 'siteintelix' ), $summary['failed'], self::filter_count_label( 'issues', $summary['failed'] ) ),
			'warnings'    => array( __( 'Warnings', 'siteintelix' ), $summary['warnings'], self::filter_count_label( 'warnings', $summary['warnings'] ) ),
			'passed'      => array( __( 'Passed', 'siteintelix' ), $summary['passed'], self::filter_count_label( 'passed', $summary['passed'] ) ),
			'information' => array( __( 'Information', 'siteintelix' ), $summary['information'], self::filter_count_label( 'information', $summary['information'] ) ),
		);
		?>
		<div class="si-toolbar sitx-serverdiag-filterbar" aria-label="<?php esc_attr_e( 'Filter diagnostics', 'siteintelix' ); ?>">
			<div class="sitx-serverdiag-filters">
				<?php foreach ( $filters as $filter => $definition ) : ?>
					<button type="button" class="si-button" data-sitx-diag-filter="<?php echo esc_attr( $filter ); ?>" aria-pressed="<?php echo 'all' === $filter ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $definition[2] ); ?>">
						<?php echo esc_html( $definition[0] ); ?> <span><?php echo esc_html( (string) $definition[1] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="sitx-serverdiag-filterbar__tools">
				<label class="screen-reader-text" for="sitx-serverdiag-search"><?php esc_html_e( 'Search diagnostics', 'siteintelix' ); ?></label>
				<input type="search" id="sitx-serverdiag-search" data-sitx-diag-search placeholder="<?php esc_attr_e( 'Search checks', 'siteintelix' ); ?>">
				<label class="sitx-serverdiag-hide-passed">
					<input type="checkbox" data-sitx-diag-hide-passed>
					<span><?php esc_html_e( 'Hide Passed', 'siteintelix' ); ?></span>
				</label>
				<label class="screen-reader-text" for="sitx-serverdiag-sort"><?php esc_html_e( 'Sort diagnostics', 'siteintelix' ); ?></label>
				<select id="sitx-serverdiag-sort" data-sitx-diag-sort>
					<option value="severity"><?php esc_html_e( 'Sort: Severity', 'siteintelix' ); ?></option>
					<option value="name"><?php esc_html_e( 'Sort: Check name', 'siteintelix' ); ?></option>
					<option value="status"><?php esc_html_e( 'Sort: Status', 'siteintelix' ); ?></option>
				</select>
				<button type="button" class="si-button" data-sitx-diag-collapse-all><?php esc_html_e( 'Collapse All', 'siteintelix' ); ?></button>
				<button type="button" class="si-button" data-sitx-diag-expand-all><?php esc_html_e( 'Expand All', 'siteintelix' ); ?></button>
				<details class="sitx-serverdiag-section-controls" data-sitx-diag-section-controls>
					<summary><?php esc_html_e( 'Section controls', 'siteintelix' ); ?></summary>
					<button type="button" class="si-button" data-sitx-diag-mobile-collapse-all><?php esc_html_e( 'Collapse All', 'siteintelix' ); ?></button>
					<button type="button" class="si-button" data-sitx-diag-mobile-expand-all><?php esc_html_e( 'Expand All', 'siteintelix' ); ?></button>
				</details>
			</div>
			<p class="screen-reader-text" aria-live="polite" data-sitx-diag-live></p>
		</div>
		<?php
	}

	/**
	 * Build a plural-aware accessible label for a filter count.
	 *
	 * @param string $filter Filter key.
	 * @param int    $count  Count.
	 * @return string
	 */
	private static function filter_count_label( $filter, $count ) {
		$count = absint( $count );

		switch ( $filter ) {
			case 'issues':
				/* translators: %d: number of critical issues. */
				$template = _n( 'Show %d critical issue', 'Show %d critical issues', $count, 'siteintelix' );
				break;
			case 'warnings':
				/* translators: %d: number of warnings. */
				$template = _n( 'Show %d warning', 'Show %d warnings', $count, 'siteintelix' );
				break;
			case 'passed':
				/* translators: %d: number of passed checks. */
				$template = _n( 'Show %d passed check', 'Show %d passed checks', $count, 'siteintelix' );
				break;
			case 'information':
				/* translators: %d: number of informational checks. */
				$template = _n( 'Show %d informational check', 'Show %d informational checks', $count, 'siteintelix' );
				break;
			default:
				/* translators: %d: total number of checks. */
				$template = _n( 'Show all %d check', 'Show all %d checks', $count, 'siteintelix' );
				break;
		}

		return sprintf( $template, $count );
	}

	/**
	 * Build plural-aware section count text.
	 *
	 * @param array<string,int> $summary Section summary.
	 * @return string
	 */
	private static function section_count_text( $summary ) {
		$failed      = absint( $summary['failed'] );
		$warnings    = absint( $summary['warnings'] );
		$passed      = absint( $summary['passed'] );
		$information = absint( $summary['information'] );

		/* translators: %d: number of critical issues. */
		$issue_text = sprintf( _n( '%d issue', '%d issues', $failed, 'siteintelix' ), $failed );
		/* translators: %d: number of warnings. */
		$warning_text = sprintf( _n( '%d warning', '%d warnings', $warnings, 'siteintelix' ), $warnings );
		/* translators: %d: number of passed checks. */
		$passed_text = sprintf( _n( '%d passed', '%d passed', $passed, 'siteintelix' ), $passed );
		/* translators: %d: number of informational checks. */
		$information_text = sprintf( _n( '%d information', '%d information', $information, 'siteintelix' ), $information );

		return implode( ', ', array( $issue_text, $warning_text, $passed_text, $information_text ) );
	}

	/**
	 * Render a diagnostics accordion section.
	 *
	 * @param array<string,mixed> $section      Section definition.
	 * @param bool                $default_open Whether this section opens first.
	 * @return void
	 */
	private static function render_section( $section, $default_open = false ) {
		$toggle_id = 'sitx-serverdiag-toggle-' . $section['key'];
		$panel_id  = 'sitx-serverdiag-panel-' . $section['key'];
		$payload   = array();
		$total     = max( 1, absint( $section['summary']['total'] ) );
		$progress  = (int) round( ( absint( $section['summary']['passed'] ) / $total ) * 100 );
		foreach ( $section['rows'] as $row ) {
			$item = array(
				'label'  => (string) $row['label'],
				'value'  => self::stringify( $row['value'] ),
				'status' => (string) $row['status'],
				'detail' => (string) $row['detail'],
			);
			if ( isset( $row['recommended'] ) ) {
				$item['recommended'] = self::stringify( $row['recommended'] );
			}
			$payload[] = $item;
		}
		?>
		<section id="sitx-serverdiag-section-<?php echo esc_attr( $section['key'] ); ?>" class="si-card sitx-serverdiag-section" data-sitx-diag-section data-severity="<?php echo esc_attr( $section['summary']['severity'] ); ?>"<?php echo $default_open ? ' data-sitx-diag-default-open="true"' : ''; ?> aria-labelledby="<?php echo esc_attr( $toggle_id ); ?>">
			<h2><button type="button" id="<?php echo esc_attr( $toggle_id ); ?>" class="sitx-serverdiag-section__toggle" data-sitx-diag-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
				<?php echo wp_kses_post( SITEINTELIX_Admin_UI::icon( $section['icon'] ) ); ?>
				<span class="sitx-serverdiag-section__heading">
					<strong><?php echo esc_html( $section['title'] ); ?></strong>
					<small><?php echo esc_html( $section['description'] ); ?></small>
				</span>
				<?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( self::status_label( $section['summary']['severity'] ), self::status_badge_type( $section['summary']['severity'] ) ) ); ?>
				<span class="sitx-serverdiag-section__counts">
					<?php echo esc_html( self::section_count_text( $section['summary'] ) ); ?>
				</span>
				<?php /* translators: %d: percentage of diagnostic checks that passed in this category. */ ?>
				<span class="sitx-serverdiag-category-progress" aria-label="<?php echo esc_attr( sprintf( __( '%d percent passed', 'siteintelix' ), $progress ) ); ?>">
					<span style="<?php echo esc_attr( '--sitx-diag-progress:' . $progress . '%' ); ?>"></span>
				</span>
				<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
			</button></h2>
			<div id="<?php echo esc_attr( $panel_id ); ?>" class="sitx-serverdiag-section__panel" data-sitx-diag-panel aria-labelledby="<?php echo esc_attr( $toggle_id ); ?>" hidden></div>
			<script type="application/json" data-sitx-diag-payload><?php echo wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX flags prevent HTML/script injection. ?></script>
			<noscript>
				<div class="sitx-serverdiag-fallback-wrap">
				<table class="widefat striped sitx-serverdiag-fallback-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Check', 'siteintelix' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Current', 'siteintelix' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Description', 'siteintelix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $section['rows'] as $row ) : ?>
							<tr class="is-<?php echo esc_attr( sanitize_html_class( $row['status'] ) ); ?>">
								<td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( self::status_label( $row['status'] ), self::status_badge_type( $row['status'] ) ) ); ?></td>
								<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
								<td><code><?php echo esc_html( self::stringify( $row['value'] ) ); ?></code></td>
								<td><?php echo esc_html( $row['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</noscript>
		</section>
		<?php
	}


	/**
	 * Render compact report and help utilities.
	 *
	 * @param string              $json_url       JSON export URL.
	 * @param string              $text_url       Text export URL.
	 * @param array<string,mixed> $redacted_report Redacted report for copying.
	 * @return void
	 */
	private static function render_utilities( $json_url, $text_url, $redacted_report ) {
		?>
		<section class="si-card sitx-serverdiag-utility">
			<h2><?php esc_html_e( 'Need Help?', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'Create a redacted support report for a plugin, theme, or hosting support team.', 'siteintelix' ); ?></p>
			<a class="si-button" href="<?php echo esc_url( $text_url ); ?>"><span class="dashicons dashicons-media-text" aria-hidden="true"></span><?php esc_html_e( 'Create Support Report', 'siteintelix' ); ?></a>
		</section>
		<section class="si-card sitx-serverdiag-utility">
			<h2><?php esc_html_e( 'Report Actions', 'siteintelix' ); ?></h2>
			<a class="si-button" href="<?php echo esc_url( $json_url ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export JSON', 'siteintelix' ); ?></a>
			<a class="si-button" href="<?php echo esc_url( $text_url ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span><?php esc_html_e( 'Export Report', 'siteintelix' ); ?></a>
			<button type="button" class="si-button" data-sitx-diag-copy-system-info aria-controls="sitx-serverdiag-copy-source"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span><?php esc_html_e( 'Copy System Info', 'siteintelix' ); ?></button>
			<label class="screen-reader-text" for="sitx-serverdiag-copy-source"><?php esc_html_e( 'Redacted system information', 'siteintelix' ); ?></label>
			<textarea id="sitx-serverdiag-copy-source" class="sitx-serverdiag-report" hidden readonly><?php echo esc_textarea( self::format_text_report( $redacted_report ) ); ?></textarea>
		</section>
		<section class="si-card sitx-serverdiag-utility">
			<h2><?php esc_html_e( 'Documentation', 'siteintelix' ); ?></h2>
			<a href="<?php echo esc_url( 'https://wordpress.org/documentation/article/site-health-screen/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress Site Health documentation', 'siteintelix' ); ?></a>
		</section>
		<section class="si-card sitx-serverdiag-utility">
			<h2><?php esc_html_e( 'Privacy Notice', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'Reports avoid database passwords, salts, secret keys, SMTP credentials, license keys, and private tokens. Paths and admin-only technical values are visible only to administrators.', 'siteintelix' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Derive a redacted support report from checks already collected for the page.
	 *
	 * @param array<string,mixed> $report Collected report.
	 * @return array<string,mixed>
	 */
	private static function redact_report( $report ) {
		$sections         = array( 'php_server', 'extensions', 'filesystem', 'network', 'wordpress', 'database' );
		$sensitive_labels = array(
			__( 'Current user', 'siteintelix' ),
			__( 'Table prefix', 'siteintelix' ),
			__( 'Database host', 'siteintelix' ),
			__( 'Database name', 'siteintelix' ),
		);
		$all_rows         = array();

		foreach ( $sections as $section ) {
			if ( empty( $report[ $section ] ) || ! is_array( $report[ $section ] ) ) {
				$report[ $section ] = array();
				continue;
			}

			foreach ( $report[ $section ] as &$row ) {
				if ( in_array( $row['label'], $sensitive_labels, true ) || self::is_path_value( self::stringify( $row['value'] ) ) ) {
					$row['value'] = self::redacted();
				}
				if ( self::is_path_value( (string) $row['detail'] ) ) {
					$row['detail'] = self::redacted();
				}
				if ( isset( $row['recommended'] ) && self::is_path_value( self::stringify( $row['recommended'] ) ) ) {
					$row['recommended'] = self::redacted();
				}
			}
			unset( $row );

			$all_rows = array_merge( $all_rows, $report[ $section ] );
		}

		$report['summary'] = self::summarize( $all_rows );

		return $report;
	}

	/**
	 * Build full diagnostics report.
	 *
	 * @param bool $redacted Whether to redact support output.
	 * @param bool $refresh  Whether to refresh expensive cached checks.
	 * @return array<string,mixed>
	 */
	private static function get_report( $redacted = false, $refresh = false ) {
		self::$cache_metadata = array();
		$php_server = self::get_php_server_checks( $redacted );
		$extensions = self::get_extension_checks();
		$filesystem = self::get_cached_section( 'filesystem', array( __CLASS__, 'get_filesystem_checks' ), $refresh );
		$network    = self::get_cached_section( 'network', array( __CLASS__, 'get_network_checks' ), $refresh );
		$wordpress  = self::get_wordpress_checks();
		$database   = self::get_cached_section( 'database', array( __CLASS__, 'get_database_checks' ), $refresh );
		$all_rows   = array_merge( $php_server, $extensions, $filesystem, $network, $wordpress, $database );

		$report = array(
			'generated_at' => current_time( 'mysql' ),
			'site'         => self::redact_url( home_url( '/' ) ),
			'cache'        => self::$cache_metadata,
			'summary'      => self::summarize( $all_rows ),
			'php_server'   => $php_server,
			'extensions'   => $extensions,
			'filesystem'   => $filesystem,
			'network'      => $network,
			'wordpress'    => $wordpress,
			'database'     => $database,
		);

		return $redacted ? self::redact_report( $report ) : $report;
	}

	/**
	 * Return cached rows and only recollect them during an explicit refresh.
	 *
	 * Stale values are deliberately retained so a failed refresh never replaces
	 * useful diagnostics with an empty screen.
	 *
	 * @param string   $key       Cache key suffix.
	 * @param callable $collector Row collector.
	 * @param bool     $refresh   Whether to recollect now.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_cached_section( $key, $collector, $refresh ) {
		$option_name = self::CACHE_PREFIX . sanitize_key( $key );
		$cached      = get_option( $option_name, array() );
		$cached_rows = isset( $cached['rows'] ) && is_array( $cached['rows'] ) ? $cached['rows'] : array();
		$collected   = isset( $cached['collected_ts'] ) ? absint( $cached['collected_ts'] ) : 0;
		$is_fresh    = $collected && ( time() - $collected ) < self::CACHE_TTL;

		if ( $refresh ) {
			try {
				$rows = call_user_func( $collector, false );
				if ( ! is_array( $rows ) ) {
					throw new RuntimeException( 'Invalid diagnostics collector result.' );
				}
				$cached = array(
					'schema'       => 1,
					'collected_at' => current_time( 'mysql' ),
					'collected_ts' => time(),
					'rows'         => $rows,
					'error'        => '',
				);
				update_option( $option_name, $cached, false );
				$cached_rows = $rows;
				$collected   = $cached['collected_ts'];
				$is_fresh    = true;
			} catch ( Throwable $error ) {
				$cached['error'] = $error->getMessage();
				if ( $cached_rows ) {
					update_option( $option_name, $cached, false );
				}
			}
		}

		self::$cache_metadata[ $key ] = array(
			'collected_at' => isset( $cached['collected_at'] ) ? (string) $cached['collected_at'] : '',
			'collected_ts' => $collected,
			'stale'        => ! $is_fresh,
			'error'        => isset( $cached['error'] ) ? (string) $cached['error'] : '',
		);

		if ( $cached_rows ) {
			return $cached_rows;
		}

		return array(
			self::row(
				__( 'Cached diagnostics', 'siteintelix' ),
				__( 'Not checked yet', 'siteintelix' ),
				'warning',
				__( 'Select Refresh checks to collect this section without delaying the page load.', 'siteintelix' )
			),
		);
	}

	/**
	 * Describe the state of expensive diagnostics caches.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return string
	 */
	private static function cache_status_text( $report ) {
		$cache = isset( $report['cache'] ) && is_array( $report['cache'] ) ? $report['cache'] : array();
		$times = array();
		$stale = false;

		foreach ( $cache as $metadata ) {
			if ( ! empty( $metadata['collected_ts'] ) ) {
				$times[] = absint( $metadata['collected_ts'] );
			}
			$stale = $stale || ! empty( $metadata['stale'] );
		}

		if ( ! $times ) {
			return __( 'Expensive checks have not run yet. Refresh when you need current filesystem, network, and database results.', 'siteintelix' );
		}

		$age = human_time_diff( min( $times ), time() );
		if ( $stale ) {
			/* translators: %s: Human-readable age, for example "8 minutes". */
			return sprintf( __( 'Showing saved results from %s ago. Refresh for current values.', 'siteintelix' ), $age );
		}

		/* translators: %s: Human-readable age, for example "2 minutes". */
		return sprintf( __( 'Expensive checks were refreshed %s ago.', 'siteintelix' ), $age );
	}

	/**
	 * PHP and server checks.
	 *
	 * @param bool $redacted Whether to redact paths.
	 * @return array<int,array>
	 */
	private static function get_php_server_checks( $redacted = false ) {
		$memory_mb = self::size_to_mb( (string) ini_get( 'memory_limit' ) );
		$exec_time = (int) ini_get( 'max_execution_time' );
		$input_vars = (int) ini_get( 'max_input_vars' );

		return array(
			self::row( __( 'PHP version', 'siteintelix' ), PHP_VERSION, version_compare( PHP_VERSION, '8.0', '>=' ) ? 'pass' : 'warning', __( 'WordPress and modern plugins are increasingly optimized for PHP 8.0+.', 'siteintelix' ) ),
			self::row( __( 'PHP SAPI', 'siteintelix' ), PHP_SAPI, 'info', __( 'Shows whether PHP runs through Apache, FPM, CGI, or CLI.', 'siteintelix' ) ),
			self::row( __( 'Loaded php.ini', 'siteintelix' ), self::maybe_redact_path( php_ini_loaded_file(), $redacted ), php_ini_loaded_file() ? 'pass' : 'warning', __( 'The effective PHP configuration file for this request.', 'siteintelix' ) ),
			self::row( __( 'Scanned ini files', 'siteintelix' ), self::maybe_redact_path( php_ini_scanned_files(), $redacted ), php_ini_scanned_files() ? 'info' : 'neutral', __( 'Additional PHP configuration files loaded after php.ini.', 'siteintelix' ) ),
			self::row( __( 'Server software', 'siteintelix' ), self::server_value( 'SERVER_SOFTWARE', __( 'Unknown', 'siteintelix' ) ), 'info', __( 'Web server reported by this request.', 'siteintelix' ) ),
			self::row( __( 'Operating system', 'siteintelix' ), PHP_OS . ' / ' . ( PHP_INT_SIZE === 8 ? '64-bit' : '32-bit' ), 'info', __( 'OS and PHP architecture.', 'siteintelix' ) ),
			self::row( __( 'Current user', 'siteintelix' ), self::get_current_process_user( $redacted ), 'info', __( 'Useful for diagnosing filesystem ownership issues.', 'siteintelix' ) ),
			self::row( __( 'memory_limit', 'siteintelix' ), ini_get( 'memory_limit' ), $memory_mb < 0 || $memory_mb >= 256 ? 'pass' : 'warning', __( '256M or higher is recommended for imports, builders, and large plugin updates.', 'siteintelix' ) ),
			self::row( __( 'max_execution_time', 'siteintelix' ), ini_get( 'max_execution_time' ), 0 === $exec_time || $exec_time >= 120 ? 'pass' : 'warning', __( '120 seconds or higher is recommended for imports and remote package extraction.', 'siteintelix' ) ),
			self::row( __( 'max_input_time', 'siteintelix' ), ini_get( 'max_input_time' ), 'info', __( 'Maximum time PHP spends parsing request input.', 'siteintelix' ) ),
			self::row( __( 'post_max_size', 'siteintelix' ), ini_get( 'post_max_size' ), 'info', __( 'Maximum POST body size.', 'siteintelix' ) ),
			self::row( __( 'upload_max_filesize', 'siteintelix' ), ini_get( 'upload_max_filesize' ), 'info', __( 'Maximum uploaded file size.', 'siteintelix' ) ),
			self::row( __( 'max_file_uploads', 'siteintelix' ), ini_get( 'max_file_uploads' ), 'info', __( 'Maximum number of files accepted in one upload request.', 'siteintelix' ) ),
			self::row( __( 'max_input_vars', 'siteintelix' ), ini_get( 'max_input_vars' ), $input_vars >= 1000 ? 'pass' : 'warning', __( 'Low values can break large option forms and builders.', 'siteintelix' ) ),
			self::row( __( 'allow_url_fopen', 'siteintelix' ), self::ini_bool( 'allow_url_fopen' ), self::ini_enabled( 'allow_url_fopen' ) ? 'pass' : 'warning', __( 'Some importers need URL wrappers unless they use cURL or WordPress HTTP APIs.', 'siteintelix' ) ),
			self::row( __( 'disable_functions', 'siteintelix' ), ini_get( 'disable_functions' ) ?: __( 'None', 'siteintelix' ), ini_get( 'disable_functions' ) ? 'warning' : 'pass', __( 'Disabled file, process, or network functions can break plugins on some hosts.', 'siteintelix' ) ),
			self::row( __( 'disable_classes', 'siteintelix' ), ini_get( 'disable_classes' ) ?: __( 'None', 'siteintelix' ), ini_get( 'disable_classes' ) ? 'warning' : 'pass', __( 'Disabled classes may block archive, image, or network libraries.', 'siteintelix' ) ),
			self::row( __( 'open_basedir', 'siteintelix' ), self::maybe_redact_path( ini_get( 'open_basedir' ) ?: __( 'Not set', 'siteintelix' ), $redacted ), ini_get( 'open_basedir' ) ? 'warning' : 'pass', __( 'open_basedir restrictions can block temp, upload, or plugin file access.', 'siteintelix' ) ),
			self::row( __( 'file_uploads', 'siteintelix' ), self::ini_bool( 'file_uploads' ), self::ini_enabled( 'file_uploads' ) ? 'pass' : 'danger', __( 'File uploads are required for many import and media workflows.', 'siteintelix' ) ),
			self::row( __( 'upload_tmp_dir', 'siteintelix' ), self::maybe_redact_path( ini_get( 'upload_tmp_dir' ) ?: __( 'System default', 'siteintelix' ), $redacted ), 'info', __( 'Temporary location used while handling uploaded files.', 'siteintelix' ) ),
			self::row( __( 'default_socket_timeout', 'siteintelix' ), ini_get( 'default_socket_timeout' ), 'info', __( 'Timeout for stream-based network operations.', 'siteintelix' ) ),
			self::row( __( 'realpath_cache_size', 'siteintelix' ), ini_get( 'realpath_cache_size' ), 'info', __( 'Path cache size can affect file-heavy plugins.', 'siteintelix' ) ),
			self::row( __( 'pcre.backtrack_limit', 'siteintelix' ), ini_get( 'pcre.backtrack_limit' ), 'info', __( 'Low regex backtrack limits can affect importers and page builders.', 'siteintelix' ) ),
		);
	}

	/**
	 * Extension checks.
	 *
	 * @return array<int,array>
	 */
	private static function get_extension_checks() {
		$extensions = array(
			'zip'       => __( 'ZIP extraction and imports.', 'siteintelix' ),
			'curl'      => __( 'Remote HTTP requests and downloads.', 'siteintelix' ),
			'openssl'   => __( 'HTTPS and secure API requests.', 'siteintelix' ),
			'json'      => __( 'JSON encoding/decoding for APIs.', 'siteintelix' ),
			'mbstring'  => __( 'Unicode-safe text handling.', 'siteintelix' ),
			'intl'      => __( 'Internationalization and locale-aware formatting.', 'siteintelix' ),
			'xml'       => __( 'XML parser support.', 'siteintelix' ),
			'dom'       => __( 'DOM document parsing.', 'siteintelix' ),
			'simplexml' => __( 'Simple XML parsing.', 'siteintelix' ),
			'libxml'    => __( 'Core XML library.', 'siteintelix' ),
			'fileinfo'  => __( 'MIME detection for uploads.', 'siteintelix' ),
			'gd'        => __( 'Image editing fallback.', 'siteintelix' ),
			'imagick'   => __( 'Advanced image processing.', 'siteintelix' ),
			'exif'      => __( 'Image metadata handling.', 'siteintelix' ),
			'mysqli'    => __( 'WordPress database connection.', 'siteintelix' ),
			'pdo_mysql' => __( 'Alternative database clients used by some plugins.', 'siteintelix' ),
			'zlib'      => __( 'Compressed content and packages.', 'siteintelix' ),
			'iconv'     => __( 'Character conversion.', 'siteintelix' ),
			'sodium'    => __( 'Modern cryptography support.', 'siteintelix' ),
			'soap'      => __( 'SOAP APIs used by some integrations.', 'siteintelix' ),
			'opcache'   => __( 'PHP bytecode cache.', 'siteintelix' ),
			'redis'     => __( 'Redis object cache support.', 'siteintelix' ),
			'memcached' => __( 'Memcached object cache support.', 'siteintelix' ),
		);
		$rows       = array();

		foreach ( $extensions as $extension => $detail ) {
			$loaded = extension_loaded( $extension );
			$status = $loaded ? 'pass' : ( in_array( $extension, array( 'zip', 'curl', 'openssl', 'json', 'mbstring', 'xml', 'dom', 'simplexml', 'libxml', 'fileinfo', 'mysqli', 'zlib' ), true ) ? 'danger' : 'warning' );
			$rows[] = self::row( $extension, $loaded ? __( 'Loaded', 'siteintelix' ) : __( 'Missing', 'siteintelix' ), $status, $detail );
		}

		$rows[] = self::row( 'ZipArchive', class_exists( 'ZipArchive' ) ? __( 'Available', 'siteintelix' ) : __( 'Missing', 'siteintelix' ), class_exists( 'ZipArchive' ) ? 'pass' : 'danger', __( 'Required by many importers to extract template and plugin packages.', 'siteintelix' ) );

		return $rows;
	}

	/**
	 * Filesystem checks.
	 *
	 * @param bool $redacted Whether to redact paths.
	 * @return array<int,array>
	 */
	private static function get_filesystem_checks( $redacted = false ) {
		$uploads = wp_get_upload_dir();
		$month   = isset( $uploads['path'] ) ? $uploads['path'] : '';
		$temp    = sys_get_temp_dir();
		$rows    = array(
			self::row( __( 'WordPress root writable', 'siteintelix' ), self::yes_no( wp_is_writable( ABSPATH ) ), wp_is_writable( ABSPATH ) ? 'warning' : 'pass', __( 'Root write access is usually not required and can be a security concern.', 'siteintelix' ) ),
			self::row( __( 'wp-content writable', 'siteintelix' ), self::yes_no( wp_is_writable( WP_CONTENT_DIR ) ), wp_is_writable( WP_CONTENT_DIR ) ? 'pass' : 'danger', __( 'Plugin updates, uploads, debug logs, and cache files often need wp-content write access.', 'siteintelix' ) ),
			self::row( __( 'Uploads base writable', 'siteintelix' ), self::yes_no( ! empty( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] ) ), ! empty( $uploads['basedir'] ) && wp_is_writable( $uploads['basedir'] ) ? 'pass' : 'danger', __( 'Media and importer assets need the uploads directory.', 'siteintelix' ) ),
			self::row( __( 'Current month uploads writable', 'siteintelix' ), self::yes_no( $month && wp_is_writable( $month ) ), $month && wp_is_writable( $month ) ? 'pass' : 'danger', __( 'WordPress stores current uploads in this month folder.', 'siteintelix' ) ),
			self::row( __( 'System temp directory', 'siteintelix' ), self::maybe_redact_path( $temp, $redacted ), is_dir( $temp ) ? 'pass' : 'danger', __( 'Temporary directory returned by sys_get_temp_dir().', 'siteintelix' ) ),
			self::row( __( 'Temp directory writable', 'siteintelix' ), self::yes_no( is_dir( $temp ) && wp_is_writable( $temp ) ), is_dir( $temp ) && wp_is_writable( $temp ) ? 'pass' : 'danger', __( 'Remote downloads and ZIP extraction often need writable temp storage.', 'siteintelix' ) ),
			self::row( __( 'Disk free space', 'siteintelix' ), self::disk_free(), 'info', __( 'Available disk space at the WordPress root.', 'siteintelix' ) ),
			self::row( __( 'WP_Filesystem method', 'siteintelix' ), self::filesystem_method(), 'info', __( 'direct is the simplest method; FTP/SSH may require credentials during updates.', 'siteintelix' ) ),
		);
		$rows[]  = self::file_operation_row( $redacted );

		return $rows;
	}

	/**
	 * Network checks.
	 *
	 * @return array<int,array>
	 */
	private static function get_network_checks() {
		$dns_ok       = self::dns_resolves( 'wordpress.org' );
		$wp_response  = self::remote_check( self::ENDPOINT_WORDPRESS, true );
		$api_response = self::remote_check( self::ENDPOINT_API, true );
		$local_no_ssl = self::remote_check( home_url( '/' ), false );

		$rows = array(
			self::row( __( 'DNS resolution', 'siteintelix' ), $dns_ok ? __( 'Resolved wordpress.org', 'siteintelix' ) : __( 'Failed', 'siteintelix' ), $dns_ok ? 'pass' : 'danger', __( 'DNS failures prevent remote template, update, and API requests.', 'siteintelix' ) ),
			self::row( __( 'HTTPS to WordPress.org', 'siteintelix' ), $wp_response['value'], $wp_response['status'], $wp_response['detail'] ),
			self::row( __( 'WordPress.org API', 'siteintelix' ), $api_response['value'], $api_response['status'], $api_response['detail'] ),
			self::row( __( 'Loopback request', 'siteintelix' ), $local_no_ssl['value'], $local_no_ssl['status'], __( 'Loopback requests are used by REST, cron, and some plugin background tasks.', 'siteintelix' ) ),
			self::row( __( 'cURL transport', 'siteintelix' ), extension_loaded( 'curl' ) ? __( 'Available', 'siteintelix' ) : __( 'Missing', 'siteintelix' ), extension_loaded( 'curl' ) ? 'pass' : 'warning', __( 'WordPress can use cURL for HTTP requests when available.', 'siteintelix' ) ),
			self::row( __( 'Streams transport', 'siteintelix' ), self::ini_enabled( 'allow_url_fopen' ) ? __( 'Likely available', 'siteintelix' ) : __( 'URL fopen disabled', 'siteintelix' ), self::ini_enabled( 'allow_url_fopen' ) ? 'pass' : 'warning', __( 'Streams can be a fallback transport for remote downloads.', 'siteintelix' ) ),
			self::row( 'WP_PROXY_HOST', defined( 'WP_PROXY_HOST' ) ? WP_PROXY_HOST : __( 'Not set', 'siteintelix' ), defined( 'WP_PROXY_HOST' ) ? 'warning' : 'pass', __( 'Proxy host constant can affect remote requests.', 'siteintelix' ) ),
			self::row( 'WP_PROXY_PORT', defined( 'WP_PROXY_PORT' ) ? WP_PROXY_PORT : __( 'Not set', 'siteintelix' ), defined( 'WP_PROXY_PORT' ) ? 'warning' : 'pass', __( 'Proxy port constant can affect remote requests.', 'siteintelix' ) ),
			self::row( 'WP_PROXY_USERNAME', defined( 'WP_PROXY_USERNAME' ) ? __( 'Set', 'siteintelix' ) : __( 'Not set', 'siteintelix' ), defined( 'WP_PROXY_USERNAME' ) ? 'warning' : 'pass', __( 'Proxy usernames are redacted.', 'siteintelix' ) ),
			self::row( 'WP_PROXY_BYPASS_HOSTS', defined( 'WP_PROXY_BYPASS_HOSTS' ) ? WP_PROXY_BYPASS_HOSTS : __( 'Not set', 'siteintelix' ), 'info', __( 'Hosts bypassed by the WordPress proxy configuration.', 'siteintelix' ) ),
		);

		return $rows;
	}

	/**
	 * WordPress checks.
	 *
	 * @return array<int,array>
	 */
	private static function get_wordpress_checks() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$mu_plugins     = function_exists( 'get_mu_plugins' ) ? get_mu_plugins() : array();
		$dropins        = function_exists( 'get_dropins' ) ? get_dropins() : array();
		$theme          = wp_get_theme();
		$rest_cache     = get_transient( SITEINTELIX_System_Info::OVERVIEW_REMOTE_HEALTH_TRANSIENT );
		$rest_available = is_array( $rest_cache ) && array_key_exists( 'available', $rest_cache ) ? (bool) $rest_cache['available'] : null;
		$rest_value     = null === $rest_available ? __( 'Not checked yet', 'siteintelix' ) : ( $rest_available ? __( 'Available', 'siteintelix' ) : __( 'Unavailable', 'siteintelix' ) );
		$rest_status    = null === $rest_available ? 'warning' : ( $rest_available ? 'pass' : 'danger' );

		return array(
			self::row( __( 'WordPress version', 'siteintelix' ), get_bloginfo( 'version' ), version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'pass' : 'warning', __( 'Modern plugins are generally tested against recent WordPress versions.', 'siteintelix' ) ),
			self::row( __( 'Site/Home URL match', 'siteintelix' ), site_url() === home_url() ? __( 'Match', 'siteintelix' ) : __( 'Different', 'siteintelix' ), site_url() === home_url() ? 'pass' : 'warning', __( 'Mismatched URLs can affect redirects, REST, and loopback checks.', 'siteintelix' ) ),
			self::row( __( 'Multisite', 'siteintelix' ), self::yes_no( is_multisite() ), is_multisite() ? 'info' : 'neutral', __( 'Some modules behave differently on multisite installs.', 'siteintelix' ) ),
			self::row( __( 'Active theme', 'siteintelix' ), $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ), 'info', __( 'Currently active theme.', 'siteintelix' ) ),
			self::row( __( 'Active plugins', 'siteintelix' ), count( $active_plugins ), 'info', __( 'Number of active regular plugins.', 'siteintelix' ) ),
			self::row( __( 'Active plugin versions', 'siteintelix' ), self::get_active_plugin_versions(), 'info', __( 'Active plugin names and versions for support context.', 'siteintelix' ) ),
			self::row( __( 'Must-use plugins', 'siteintelix' ), count( $mu_plugins ), count( $mu_plugins ) ? 'info' : 'neutral', __( 'Must-use plugins load before normal plugins and can affect diagnostics.', 'siteintelix' ) ),
			self::row( __( 'Must-use plugin versions', 'siteintelix' ), self::get_mu_plugin_versions( $mu_plugins ), count( $mu_plugins ) ? 'info' : 'neutral', __( 'Must-use plugin names and versions.', 'siteintelix' ) ),
			self::row( __( 'Drop-ins', 'siteintelix' ), implode( ', ', array_keys( $dropins ) ) ?: __( 'None', 'siteintelix' ), count( $dropins ) ? 'warning' : 'pass', __( 'Drop-ins such as object-cache.php, advanced-cache.php, and db.php can change core behavior.', 'siteintelix' ) ),
			self::row( __( 'Persistent object cache', 'siteintelix' ), function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ? 'info' : 'neutral', __( 'Persistent object caches can affect transient and cache debugging.', 'siteintelix' ) ),
			self::row( __( 'REST API', 'siteintelix' ), $rest_value, $rest_status, __( 'REST status is checked when diagnostics are refreshed; failures can break builders, importers, and admin screens.', 'siteintelix' ) ),
			self::row( __( 'WP-Cron', 'siteintelix' ), defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Disabled', 'siteintelix' ) : __( 'Enabled', 'siteintelix' ), defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'warning' : 'pass', __( 'Disabled cron requires a real server cron job.', 'siteintelix' ) ),
			self::row( 'WP_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), defined( 'WP_DEBUG' ) && WP_DEBUG ? 'warning' : 'pass', __( 'Debug mode should usually be disabled on production.', 'siteintelix' ) ),
			self::row( 'WP_DEBUG_LOG', defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), 'info', __( 'Controls WordPress debug log capture.', 'siteintelix' ) ),
			self::row( 'WP_DEBUG_DISPLAY', defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ? 'warning' : 'pass', __( 'Public error display should be disabled on production.', 'siteintelix' ) ),
			self::row( 'SCRIPT_DEBUG', defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), 'info', __( 'Loads unminified WordPress assets when enabled.', 'siteintelix' ) ),
			self::row( 'WP_MEMORY_LIMIT', defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : __( 'Default', 'siteintelix' ), 'info', __( 'Frontend/admin memory limit requested by WordPress.', 'siteintelix' ) ),
			self::row( 'WP_MAX_MEMORY_LIMIT', defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : __( 'Default', 'siteintelix' ), 'info', __( 'Maximum admin memory limit requested by WordPress.', 'siteintelix' ) ),
			self::row( 'DISALLOW_FILE_EDIT', defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), 'info', __( 'Disables the built-in theme/plugin editors.', 'siteintelix' ) ),
			self::row( 'DISALLOW_FILE_MODS', defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' ), defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ? 'warning' : 'pass', __( 'Blocks plugin/theme/core installs and updates.', 'siteintelix' ) ),
			self::row( 'WP_ENVIRONMENT_TYPE', defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : wp_get_environment_type(), 'info', __( 'Environment type reported by WordPress.', 'siteintelix' ) ),
		);
	}

	/**
	 * Database checks.
	 *
	 * @param bool $redacted Whether to redact DB name/host/user.
	 * @return array<int,array>
	 */
	private static function get_database_checks( $redacted = false ) {
		global $wpdb;

		$db_size       = self::get_database_size();
		$autoload_size = self::get_autoloaded_options_size();

		return array(
			self::row( __( 'DB server version', 'siteintelix' ), self::get_database_variable_value( 'version' ), 'info', __( 'Database server version.', 'siteintelix' ) ),
			self::row( __( 'DB client version', 'siteintelix' ), is_object( $wpdb ) && method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : __( 'Unknown', 'siteintelix' ), 'info', __( 'Database client/server information reported through wpdb.', 'siteintelix' ) ),
			self::row( __( 'DB extension', 'siteintelix' ), self::get_database_extension(), extension_loaded( 'mysqli' ) || extension_loaded( 'pdo_mysql' ) ? 'pass' : 'danger', __( 'WordPress normally requires mysqli.', 'siteintelix' ) ),
			self::row( __( 'Charset / collation', 'siteintelix' ), $wpdb->charset . ' / ' . $wpdb->collate, 'info', __( 'Database charset and collation used by WordPress.', 'siteintelix' ) ),
			self::row( __( 'Table prefix', 'siteintelix' ), $redacted ? self::redacted() : $wpdb->prefix, 'info', __( 'WordPress table prefix.', 'siteintelix' ) ),
			self::row( __( 'Database host', 'siteintelix' ), $redacted ? self::redacted() : $wpdb->dbhost, 'info', __( 'Database host is shown only in admin context.', 'siteintelix' ) ),
			self::row( __( 'Database name', 'siteintelix' ), $redacted ? self::redacted() : $wpdb->dbname, 'info', __( 'Database name is redacted in support exports.', 'siteintelix' ) ),
			self::row( 'max_allowed_packet', self::get_database_variable_value( 'max_allowed_packet' ), 'info', __( 'Low packet limits can break large option or import writes.', 'siteintelix' ) ),
			self::row( 'max_connections', self::get_database_variable_value( 'max_connections' ), 'info', __( 'Connection limit reported by the database server.', 'siteintelix' ) ),
			self::row( __( 'Database size', 'siteintelix' ), size_format( $db_size ), 'info', __( 'Estimated total size of WordPress database tables.', 'siteintelix' ) ),
			self::row( __( 'Autoloaded options size', 'siteintelix' ), size_format( $autoload_size ), $autoload_size > 1024 * 1024 ? 'warning' : 'pass', __( 'Large autoloaded options can slow every WordPress request.', 'siteintelix' ) ),
		);
	}

	/**
	 * Get active plugin names with versions.
	 *
	 * @return string
	 */
	private static function get_active_plugin_versions() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		$active  = (array) get_option( 'active_plugins', array() );
		$names   = array();

		foreach ( $active as $plugin_file ) {
			if ( isset( $plugins[ $plugin_file ] ) ) {
				$names[] = trim( $plugins[ $plugin_file ]['Name'] . ' ' . $plugins[ $plugin_file ]['Version'] );
			}
		}

		return implode( ', ', $names ) ?: __( 'None', 'siteintelix' );
	}

	/**
	 * Get MU plugin names with versions.
	 *
	 * @param array<string,array> $mu_plugins MU plugins.
	 * @return string
	 */
	private static function get_mu_plugin_versions( $mu_plugins ) {
		$names = array();

		foreach ( $mu_plugins as $plugin ) {
			$name    = isset( $plugin['Name'] ) ? $plugin['Name'] : '';
			$version = isset( $plugin['Version'] ) ? $plugin['Version'] : '';
			if ( $name ) {
				$names[] = trim( $name . ' ' . $version );
			}
		}

		return implode( ', ', $names ) ?: __( 'None', 'siteintelix' );
	}








	/**
	 * Summarize checks.
	 *
	 * @param array<int,array> $rows Rows.
	 * @return array<string,mixed>
	 */
	private static function summarize( $rows ) {
		$total       = count( $rows );
		$failed      = 0;
		$warnings    = 0;
		$passed      = 0;
		$information = 0;
		$critical    = array();

		foreach ( $rows as $row ) {
			if ( 'danger' === $row['status'] ) {
				++$failed;
				if ( count( $critical ) < 6 ) {
					$critical[] = $row['label'] . ': ' . self::stringify( $row['value'] );
				}
			} elseif ( 'warning' === $row['status'] ) {
				++$warnings;
			} elseif ( 'pass' === $row['status'] ) {
				++$passed;
			} elseif ( in_array( $row['status'], array( 'info', 'neutral' ), true ) ) {
				++$information;
			}
		}

		$score = $total ? max( 0, (int) round( 100 - ( ( $failed * 9 + $warnings * 3 ) / $total * 10 ) ) ) : 100;

		return array(
			'total'       => $total,
			'failed'      => $failed,
			'warnings'    => $warnings,
			'passed'      => $passed,
			'information' => $information,
			'score'       => $score,
			'critical'    => $critical,
		);
	}

	/**
	 * Summarize a section and select its highest severity.
	 *
	 * A problem always wins; otherwise a section with at least one passing check
	 * is presented as passed. Sections containing only context remain information.
	 *
	 * @param array<int,array> $rows Rows.
	 * @return array<string,mixed>
	 */
	private static function section_summary( $rows ) {
		$summary = self::summarize( $rows );

		if ( $summary['failed'] > 0 ) {
			$summary['severity'] = 'danger';
		} elseif ( $summary['warnings'] > 0 ) {
			$summary['severity'] = 'warning';
		} elseif ( $summary['passed'] > 0 ) {
			$summary['severity'] = 'pass';
		} else {
			$summary['severity'] = 'info';
		}

		return $summary;
	}

	/**
	 * Format report as text.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return string
	 */
	private static function format_text_report( $report ) {
		$lines   = array();
		$lines[] = 'SiteIntelix Server Diagnostics';
		$lines[] = 'Generated: ' . $report['generated_at'];
		$lines[] = 'Site: ' . $report['site'];
		$lines[] = 'Health: ' . $report['summary']['score'] . '%';
		$lines[] = '';

		foreach ( array( 'php_server', 'extensions', 'filesystem', 'network', 'wordpress', 'database' ) as $section ) {
			$lines[] = strtoupper( str_replace( '_', ' ', $section ) );
			foreach ( $report[ $section ] as $row ) {
				$lines[] = '- [' . self::status_label( $row['status'] ) . '] ' . $row['label'] . ': ' . self::stringify( $row['value'] ) . ' - ' . $row['detail'];
			}
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}

	/**
	 * Build a row.
	 *
	 * @param string $label  Label.
	 * @param mixed  $value  Value.
	 * @param string $status Status.
	 * @param string $detail Details.
	 * @return array<string,mixed>
	 */
	private static function row( $label, $value, $status, $detail ) {
		return array(
			'label'  => (string) $label,
			'value'  => $value,
			'status' => sanitize_key( $status ),
			'detail' => (string) $detail,
		);
	}



	/**
	 * Remote request check.
	 *
	 * @param string $url       URL.
	 * @param bool   $sslverify SSL verify.
	 * @return array<string,string>
	 */
	private static function remote_check( $url, $sslverify = true ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 7,
				'redirection' => 3,
				'sslverify'   => $sslverify,
				'user-agent'  => 'SiteIntelix/' . SITEINTELIX_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'value'  => $response->get_error_message(),
				'status' => 'danger',
				'detail' => __( 'The HTTP request failed before receiving a valid response.', 'siteintelix' ),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return array(
			'value'  => 'HTTP ' . $code,
			'status' => $code >= 200 && $code < 400 ? 'pass' : 'warning',
			'detail' => $sslverify ? __( 'HTTPS request completed with SSL verification enabled.', 'siteintelix' ) : __( 'Request completed with local SSL verification relaxed.', 'siteintelix' ),
		);
	}

	/**
	 * File operation check.
	 *
	 * @param bool $redacted Whether to redact path.
	 * @return array<string,mixed>
	 */
	private static function file_operation_row( $redacted ) {
		$tmp = wp_tempnam( 'siteintelix-diagnostics' );
		if ( ! $tmp ) {
			return self::row( __( 'Temporary file operation', 'siteintelix' ), __( 'Failed', 'siteintelix' ), 'danger', __( 'WordPress could not create a temporary file.', 'siteintelix' ) );
		}

		$written = file_put_contents( $tmp, 'siteintelix' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$read    = false !== $written ? file_get_contents( $tmp ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$deleted = @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		$ok      = false !== $written && 'siteintelix' === $read && $deleted;

		return self::row(
			__( 'Temporary file operation', 'siteintelix' ),
			$ok ? __( 'Create/read/delete passed', 'siteintelix' ) : __( 'Failed', 'siteintelix' ),
			$ok ? 'pass' : 'danger',
			sprintf(
				/* translators: %s: temp file path. */
				__( 'Tested temporary file operations at %s.', 'siteintelix' ),
				self::maybe_redact_path( $tmp, $redacted )
			)
		);
	}


	/**
	 * DNS check.
	 *
	 * @param string $host Host.
	 * @return bool
	 */
	private static function dns_resolves( $host ) {
		$ip = gethostbyname( $host );
		return $ip && $ip !== $host;
	}

	/**
	 * Get server value.
	 *
	 * @param string $key     Server key.
	 * @param string $default Default.
	 * @return string
	 */
	private static function server_value( $key, $default = '' ) {
		return isset( $_SERVER[ $key ] ) ? sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) : $default;
	}

	/**
	 * Process user.
	 *
	 * @param bool $redacted Redacted.
	 * @return string
	 */
	private static function get_current_process_user( $redacted ) {
		if ( $redacted ) {
			return self::redacted();
		}
		if ( function_exists( 'get_current_user' ) ) {
			return get_current_user();
		}
		return __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Get filesystem method.
	 *
	 * @return string
	 */
	private static function filesystem_method() {
		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		return get_filesystem_method();
	}

	/**
	 * Disk free.
	 *
	 * @return string
	 */
	private static function disk_free() {
		if ( ! function_exists( 'disk_free_space' ) ) {
			return __( 'Unknown', 'siteintelix' );
		}
		$bytes = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $bytes ? size_format( $bytes ) : __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Database variable.
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function get_database_variable_value( $name ) {
		global $wpdb;

		if ( 'version' === $name ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (string) $wpdb->get_var( 'SELECT VERSION()' );
		}

		if ( ! in_array( $name, array( 'max_allowed_packet', 'max_connections' ), true ) ) {
			return __( 'Unknown', 'siteintelix' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var( $wpdb->prepare( 'SHOW VARIABLES LIKE %s', $name ), 1 );
		return null === $value ? __( 'Unknown', 'siteintelix' ) : (string) $value;
	}

	/**
	 * Database extension.
	 *
	 * @return string
	 */
	private static function get_database_extension() {
		global $wpdb;
		if ( isset( $wpdb->dbh ) && is_object( $wpdb->dbh ) ) {
			return strtolower( get_class( $wpdb->dbh ) );
		}
		if ( extension_loaded( 'mysqli' ) ) {
			return 'mysqli';
		}
		return extension_loaded( 'pdo_mysql' ) ? 'pdo_mysql' : __( 'Unknown', 'siteintelix' );
	}

	/**
	 * Database size estimate.
	 *
	 * @return int
	 */
	private static function get_database_size() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = DATABASE()" );
	}

	/**
	 * Autoloaded option size estimate.
	 *
	 * @return int
	 */
	private static function get_autoloaded_options_size() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on', 'auto-on', 'auto')" );
	}


	/**
	 * Size string to MB.
	 *
	 * @param string $size Size.
	 * @return int
	 */
	private static function size_to_mb( $size ) {
		$size = trim( $size );
		if ( '-1' === $size ) {
			return -1;
		}
		$unit  = strtolower( substr( $size, -1 ) );
		$value = (float) $size;
		if ( 'g' === $unit ) {
			return (int) ( $value * 1024 );
		}
		if ( 'm' === $unit ) {
			return (int) $value;
		}
		if ( 'k' === $unit ) {
			return (int) ceil( $value / 1024 );
		}
		return (int) ceil( $value / 1024 / 1024 );
	}

	/**
	 * Ini bool.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function ini_bool( $key ) {
		return self::ini_enabled( $key ) ? __( 'Enabled', 'siteintelix' ) : __( 'Disabled', 'siteintelix' );
	}

	/**
	 * Ini enabled.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	private static function ini_enabled( $key ) {
		$value = ini_get( $key );
		return ! in_array( strtolower( (string) $value ), array( '', '0', 'off', 'false', 'no' ), true );
	}

	/**
	 * Yes/no.
	 *
	 * @param bool $value Bool.
	 * @return string
	 */
	private static function yes_no( $value ) {
		return $value ? __( 'Yes', 'siteintelix' ) : __( 'No', 'siteintelix' );
	}

	/**
	 * Maybe redact path.
	 *
	 * @param mixed $path     Path.
	 * @param bool  $redacted Redacted.
	 * @return string
	 */
	private static function maybe_redact_path( $path, $redacted ) {
		$path = self::stringify( $path );
		return $redacted && self::is_path_value( $path ) ? self::redacted() : $path;
	}

	/**
	 * Determine whether a value contains an absolute POSIX, drive, or UNC path.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_path_value( $value ) {
		$value = (string) $value;

		if ( preg_match( '#(?:^|[\s(])/(?!/)[^\s/]#', $value ) ) {
			return true;
		}

		return (bool) preg_match( '#(?:^|[\s(])(?:[A-Za-z]:\\\\|\\\\\\\\)[^\r\n]+#', $value );
	}

	/**
	 * Redact URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function redact_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return $url;
		}
		return $parts['scheme'] . '://' . $parts['host'] . ( ! empty( $parts['path'] ) ? $parts['path'] : '/' );
	}

	/**
	 * Redacted token.
	 *
	 * @return string
	 */
	private static function redacted() {
		return '*** redacted ***';
	}

	/**
	 * Stringify value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function stringify( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'siteintelix' ) : __( 'No', 'siteintelix' );
		}
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( __CLASS__, 'stringify' ), $value ) );
		}
		if ( null === $value || '' === $value ) {
			return __( 'Not set', 'siteintelix' );
		}
		return (string) $value;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_label( $status ) {
		$labels = array(
			'pass'    => __( 'Passed', 'siteintelix' ),
			'warning' => __( 'Warning', 'siteintelix' ),
			'danger'  => __( 'Failed', 'siteintelix' ),
			'info'    => __( 'Info', 'siteintelix' ),
			'neutral' => __( 'Neutral', 'siteintelix' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Info', 'siteintelix' );
	}

	/**
	 * Status badge type.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_badge_type( $status ) {
		if ( 'pass' === $status ) {
			return 'success';
		}
		return 'danger' === $status ? 'danger' : $status;
	}

	/**
	 * Score badge type.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private static function score_badge_type( $score ) {
		if ( $score >= 85 ) {
			return 'success';
		}
		if ( $score >= 65 ) {
			return 'warning';
		}
		return 'danger';
	}

	/**
	 * Short health label for the compact score card.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	private static function score_status_text( $score ) {
		if ( $score >= 85 ) {
			return __( 'Healthy', 'siteintelix' );
		}
		if ( $score >= 65 ) {
			return __( 'Needs attention', 'siteintelix' );
		}
		return __( 'Action required', 'siteintelix' );
	}
}
