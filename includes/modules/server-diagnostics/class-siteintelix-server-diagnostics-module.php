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
 * Checks PHP, filesystem, network, WordPress, database, and plugin compatibility.
 */
class SITEINTELIX_Server_Diagnostics_Module {

	const ENDPOINT_WORDPRESS = 'https://wordpress.org/';
	const ENDPOINT_API       = 'https://api.wordpress.org/';

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

		$selected_plugin = self::get_requested_plugin();
		$report          = self::get_report( $selected_plugin );
		$json_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'siteintelix_server_diag_export',
					'format'          => 'json',
					'selected_plugin' => $selected_plugin,
				),
				admin_url( 'admin-post.php' )
			),
			'siteintelix_server_diag_export'
		);
		$text_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'          => 'siteintelix_server_diag_export',
					'format'          => 'text',
					'selected_plugin' => $selected_plugin,
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
					'description' => __( 'Find PHP, filesystem, network, database, and compatibility issues that can make plugins fail on this server.', 'siteintelix' ),
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
								'label'   => __( 'Download JSON', 'siteintelix' ),
								'url'     => $json_url,
								'variant' => 'secondary',
								'icon'    => 'dashicons-download',
							)
						),
						SITEINTELIX_Admin_UI::button(
							array(
								'label'   => __( 'Support Report', 'siteintelix' ),
								'url'     => $text_url,
								'variant' => 'primary',
								'icon'    => 'dashicons-clipboard',
							)
						),
					),
				)
			);
			?>

			<div class="siteintelix-container sitx-serverdiag-container">
				<?php self::render_overview( $report ); ?>
				<?php self::render_plugin_form( $selected_plugin ); ?>

				<div class="sitx-serverdiag-layout">
					<main class="sitx-serverdiag-main">
						<?php self::render_section( __( 'PHP & Server', 'siteintelix' ), __( 'Runtime, php.ini, limits, and critical PHP directives.', 'siteintelix' ), $report['php_server'], 'dashicons-editor-code' ); ?>
						<?php self::render_section( __( 'Extensions', 'siteintelix' ), __( 'Important PHP extensions commonly required by WordPress plugins.', 'siteintelix' ), $report['extensions'], 'dashicons-admin-plugins' ); ?>
						<?php self::render_section( __( 'Filesystem', 'siteintelix' ), __( 'Writable paths, temporary storage, disk space, and file operation checks.', 'siteintelix' ), $report['filesystem'], 'dashicons-media-default' ); ?>
						<?php self::render_section( __( 'Network', 'siteintelix' ), __( 'DNS, HTTPS, SSL verification, HTTP transports, proxy constants, and loopback reachability.', 'siteintelix' ), $report['network'], 'dashicons-admin-site-alt' ); ?>
						<?php self::render_section( __( 'WordPress', 'siteintelix' ), __( 'Core environment, debug constants, REST, loopback, cron, plugins, MU plugins, and drop-ins.', 'siteintelix' ), $report['wordpress'], 'dashicons-wordpress' ); ?>
						<?php self::render_section( __( 'Database', 'siteintelix' ), __( 'Database engine, charset, key limits, database size, and autoloaded options.', 'siteintelix' ), $report['database'], 'dashicons-database' ); ?>
					</main>
					<aside class="sitx-serverdiag-sidebar">
						<?php self::render_compatibility_card( $report['compatibility'] ); ?>
						<?php self::render_report_card( $report ); ?>
						<?php self::render_privacy_card(); ?>
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
		$format          = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'json';
		$selected_plugin = self::get_requested_plugin();
		$report          = self::get_report( $selected_plugin, true );

		if ( 'text' === $format ) {
			nocache_headers();
			header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
			header( 'Content-Disposition: attachment; filename=siteintelix-server-diagnostics.txt' );
			echo self::format_text_report( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text download generated from sanitized diagnostics values.
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename=siteintelix-server-diagnostics.json' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT );
		exit;
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
		<div class="sitx-serverdiag-stats">
			<?php
			self::render_stat_card( __( 'Health Score', 'siteintelix' ), $summary['score'] . '%', self::score_badge_type( $summary['score'] ), __( 'Overall server readiness', 'siteintelix' ) );
			self::render_stat_card( __( 'Critical Failed', 'siteintelix' ), (string) $summary['failed'], $summary['failed'] ? 'danger' : 'success', __( 'Checks that need attention', 'siteintelix' ) );
			self::render_stat_card( __( 'Warnings', 'siteintelix' ), (string) $summary['warnings'], $summary['warnings'] ? 'warning' : 'success', __( 'Recommended improvements', 'siteintelix' ) );
			self::render_stat_card( __( 'Checks Run', 'siteintelix' ), (string) $summary['total'], 'info', __( 'Across diagnostics sections', 'siteintelix' ) );
			?>
		</div>
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
	 * Render plugin compatibility form.
	 *
	 * @param string $selected_plugin Selected plugin basename.
	 * @return void
	 */
	private static function render_plugin_form( $selected_plugin ) {
		$plugins = self::get_plugins();
		?>
		<form class="si-card sitx-serverdiag-endpoint" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="siteintelix-server-diagnostics" />
			<label for="siteintelix-diagnostics-plugin"><?php esc_html_e( 'Check server compatibility for plugin', 'siteintelix' ); ?></label>
			<select id="siteintelix-diagnostics-plugin" name="selected_plugin">
				<option value=""><?php esc_html_e( 'Select an installed plugin', 'siteintelix' ); ?></option>
				<?php foreach ( $plugins as $plugin_file => $plugin ) : ?>
					<option value="<?php echo esc_attr( $plugin_file ); ?>" <?php selected( $selected_plugin, $plugin_file ); ?>>
						<?php echo esc_html( $plugin['Name'] . ( ! empty( $plugin['Version'] ) ? ' ' . $plugin['Version'] : '' ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
			echo wp_kses_post(
				SITEINTELIX_Admin_UI::button(
					array(
						'label'   => __( 'Check Plugin', 'siteintelix' ),
						'type'    => 'submit',
						'variant' => 'primary',
						'icon'    => 'dashicons-search',
					)
				)
			);
			?>
		</form>
		<?php
	}

	/**
	 * Render stat card.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $type  Badge type.
	 * @param string $meta  Meta text.
	 * @return void
	 */
	private static function render_stat_card( $label, $value, $type, $meta ) {
		?>
		<div class="si-card sitx-serverdiag-stat sitx-serverdiag-stat--<?php echo esc_attr( sanitize_html_class( $type ) ); ?>">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
			<small><?php echo esc_html( $meta ); ?></small>
		</div>
		<?php
	}

	/**
	 * Render a diagnostics section.
	 *
	 * @param string               $title       Title.
	 * @param string               $description Description.
	 * @param array<int,array>     $rows        Rows.
	 * @param string               $icon        Dashicon.
	 * @return void
	 */
	private static function render_section( $title, $description, $rows, $icon ) {
		?>
		<section class="si-card sitx-serverdiag-section">
			<div class="sitx-card__header">
				<?php echo wp_kses_post( SITEINTELIX_Admin_UI::icon( $icon ) ); ?>
				<div>
					<h2><?php echo esc_html( $title ); ?></h2>
					<p><?php echo esc_html( $description ); ?></p>
				</div>
			</div>
			<div class="sitx-serverdiag-table-wrap">
				<table class="widefat striped sitx-serverdiag-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Check', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Status', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Value', 'siteintelix' ); ?></th>
							<th><?php esc_html_e( 'Details', 'siteintelix' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr class="is-<?php echo esc_attr( sanitize_html_class( $row['status'] ) ); ?>">
								<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
								<td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( self::status_label( $row['status'] ), self::status_badge_type( $row['status'] ) ) ); ?></td>
								<td><code><?php echo esc_html( self::stringify( $row['value'] ) ); ?></code></td>
								<td><?php echo esc_html( $row['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}

	/**
	 * Render compatibility card.
	 *
	 * @param array<string,array> $profiles Compatibility profiles.
	 * @return void
	 */
	private static function render_compatibility_card( $profiles ) {
		?>
		<section class="si-card sitx-serverdiag-compat">
			<div class="sitx-card__header">
				<?php echo wp_kses_post( SITEINTELIX_Admin_UI::icon( 'dashicons-yes-alt' ) ); ?>
				<div>
					<h2><?php esc_html_e( 'Compatibility Profiles', 'siteintelix' ); ?></h2>
					<p><?php esc_html_e( 'Selected plugin requirements compared with this server.', 'siteintelix' ); ?></p>
				</div>
			</div>
			<?php foreach ( $profiles as $profile ) : ?>
				<div class="sitx-serverdiag-profile">
					<div>
						<strong><?php echo esc_html( $profile['title'] ); ?></strong>
						<p><?php echo esc_html( $profile['description'] ); ?></p>
					</div>
					<?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( self::status_label( $profile['status'] ), self::status_badge_type( $profile['status'] ) ) ); ?>
				</div>
				<ul class="sitx-serverdiag-profile-list">
					<?php foreach ( $profile['checks'] as $check ) : ?>
						<li>
							<span><?php echo esc_html( $check['label'] ); ?></span>
							<?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( self::status_label( $check['status'] ), self::status_badge_type( $check['status'] ) ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * Render copy report card.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return void
	 */
	private static function render_report_card( $report ) {
		?>
		<section class="si-card">
			<h2><?php esc_html_e( 'Redacted Support Report', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'Copy this report when asking plugin/theme support teams to diagnose server-specific failures.', 'siteintelix' ); ?></p>
			<textarea class="sitx-serverdiag-report" readonly rows="12"><?php echo esc_textarea( self::format_text_report( $report ) ); ?></textarea>
		</section>
		<?php
	}

	/**
	 * Render privacy card.
	 *
	 * @return void
	 */
	private static function render_privacy_card() {
		?>
		<section class="si-card">
			<h2><?php esc_html_e( 'Privacy & Redaction', 'siteintelix' ); ?></h2>
			<p><?php esc_html_e( 'Reports avoid database passwords, salts, secret keys, SMTP credentials, license keys, and private tokens. Paths and admin-only technical values are visible only to administrators.', 'siteintelix' ); ?></p>
		</section>
		<?php
	}

	/**
	 * Build full diagnostics report.
	 *
	 * @param string $selected_plugin Selected plugin basename.
	 * @param bool   $redacted Whether to redact support output.
	 * @return array<string,mixed>
	 */
	private static function get_report( $selected_plugin = '', $redacted = false ) {
		$php_server    = self::get_php_server_checks( $redacted );
		$extensions    = self::get_extension_checks();
		$filesystem    = self::get_filesystem_checks( $redacted );
		$network       = self::get_network_checks();
		$wordpress     = self::get_wordpress_checks();
		$database      = self::get_database_checks( $redacted );
		$compatibility = self::get_compatibility_profiles( $selected_plugin, $extensions, $filesystem, $network, $wordpress );
		$all_rows      = array_merge( $php_server, $extensions, $filesystem, $network, $wordpress, $database );

		foreach ( $compatibility as $profile ) {
			$all_rows = array_merge( $all_rows, $profile['checks'] );
		}

		return array(
			'generated_at'   => current_time( 'mysql' ),
			'site'           => self::redact_url( home_url( '/' ) ),
			'summary'        => self::summarize( $all_rows ),
			'php_server'     => $php_server,
			'extensions'     => $extensions,
			'filesystem'     => $filesystem,
			'network'        => $network,
			'wordpress'      => $wordpress,
			'database'       => $database,
			'compatibility'  => $compatibility,
			'selected_plugin' => $selected_plugin,
		);
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
		$rest           = self::remote_check( rest_url( '/' ), false );

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
			self::row( __( 'REST API', 'siteintelix' ), $rest['value'], $rest['status'], __( 'REST failures can break builders, importers, and admin screens.', 'siteintelix' ) ),
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
	 * Get installed plugins.
	 *
	 * @return array<string,array>
	 */
	private static function get_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugins();
	}

	/**
	 * Build selected plugin compatibility profile.
	 *
	 * @param string           $plugin_file Plugin basename.
	 * @param array<int,array> $extensions  Extension checks.
	 * @param array<int,array> $filesystem  Filesystem checks.
	 * @param array<int,array> $network     Network checks.
	 * @param array<int,array> $wordpress   WordPress checks.
	 * @return array<string,mixed>
	 */
	private static function build_plugin_profile( $plugin_file, $extensions, $filesystem, $network, $wordpress ) {
		$plugins = self::get_plugins();

		if ( ! isset( $plugins[ $plugin_file ] ) ) {
			return array(
				'title'       => __( 'Selected Plugin Compatibility', 'siteintelix' ),
				'description' => __( 'The selected plugin could not be found.', 'siteintelix' ),
				'checks'      => array(
					self::row( __( 'Plugin file', 'siteintelix' ), $plugin_file, 'danger', __( 'The plugin is no longer installed or the plugin basename is invalid.', 'siteintelix' ) ),
				),
			);
		}

		$plugin       = $plugins[ $plugin_file ];
		$plugin_path  = WP_PLUGIN_DIR . '/' . $plugin_file;
		$requirements = self::detect_plugin_requirements( $plugin_file, $plugin );
		$checks       = array(
			self::row( __( 'Plugin file', 'siteintelix' ), $plugin_file, file_exists( $plugin_path ) ? 'pass' : 'danger', __( 'Main plugin file must exist and be readable.', 'siteintelix' ) ),
			self::row( __( 'Plugin header', 'siteintelix' ), ! empty( $plugin['Name'] ) ? $plugin['Name'] : __( 'Missing', 'siteintelix' ), ! empty( $plugin['Name'] ) ? 'pass' : 'danger', __( 'A valid plugin header is required for WordPress to manage the plugin.', 'siteintelix' ) ),
		);

		$requires_php = ! empty( $plugin['RequiresPHP'] ) ? $plugin['RequiresPHP'] : '';
		$checks[]     = self::row(
			__( 'Requires PHP', 'siteintelix' ),
			$requires_php ? $requires_php : __( 'Not declared', 'siteintelix' ),
			$requires_php ? ( version_compare( PHP_VERSION, $requires_php, '>=' ) ? 'pass' : 'danger' ) : 'info',
			$requires_php ? __( 'Compared with the current PHP runtime.', 'siteintelix' ) : __( 'This plugin does not declare a minimum PHP version in its header.', 'siteintelix' )
		);

		$requires_wp = ! empty( $plugin['RequiresWP'] ) ? $plugin['RequiresWP'] : '';
		$checks[]    = self::row(
			__( 'Requires WordPress', 'siteintelix' ),
			$requires_wp ? $requires_wp : __( 'Not declared', 'siteintelix' ),
			$requires_wp ? ( version_compare( get_bloginfo( 'version' ), $requires_wp, '>=' ) ? 'pass' : 'danger' ) : 'info',
			$requires_wp ? __( 'Compared with the current WordPress version.', 'siteintelix' ) : __( 'This plugin does not declare a minimum WordPress version in its header.', 'siteintelix' )
		);

		foreach ( $requirements['extensions'] as $extension ) {
			$checks[] = self::extension_requirement_row( $extension, $extensions );
		}

		foreach ( $requirements['features'] as $feature ) {
			$checks[] = self::feature_requirement_row( $feature, $filesystem, $network, $wordpress );
		}

		if ( ! empty( $requirements['composer_autoload'] ) ) {
			$checks[] = self::row(
				__( 'Composer autoload file', 'siteintelix' ),
				file_exists( dirname( $plugin_path ) . '/vendor/autoload.php' ) ? __( 'Found', 'siteintelix' ) : __( 'Missing', 'siteintelix' ),
				file_exists( dirname( $plugin_path ) . '/vendor/autoload.php' ) ? 'pass' : 'warning',
				__( 'The plugin source references vendor/autoload.php. Missing Composer files can cause fatal errors.', 'siteintelix' )
			);
		}

		$slug          = dirname( $plugin_file );
		$fatal_matches = self::recent_log_matches( '.' === $slug ? basename( $plugin_file, '.php' ) : $slug );
		$checks[]      = self::row(
			__( 'Recent related fatals', 'siteintelix' ),
			$fatal_matches ? $fatal_matches : __( 'No recent matches', 'siteintelix' ),
			$fatal_matches ? 'warning' : 'pass',
			__( 'Looks for recent SiteIntelix debug log lines mentioning this plugin slug.', 'siteintelix' )
		);

		if ( empty( $requirements['extensions'] ) && empty( $requirements['features'] ) ) {
			$checks[] = self::row( __( 'Detected special requirements', 'siteintelix' ), __( 'None detected', 'siteintelix' ), 'info', __( 'SiteIntelix did not detect common extension, filesystem, or network requirements from the plugin source.', 'siteintelix' ) );
		}

		return array(
			'title'       => sprintf(
				/* translators: %s: plugin name. */
				__( '%s Compatibility', 'siteintelix' ),
				$plugin['Name']
			),
			'description' => sprintf(
				/* translators: %s: plugin basename. */
				__( 'Checks detectable server requirements for %s.', 'siteintelix' ),
				$plugin_file
			),
			'checks'      => $checks,
		);
	}

	/**
	 * Detect likely plugin requirements from headers and source.
	 *
	 * @param string              $plugin_file Plugin basename.
	 * @param array<string,mixed> $plugin      Plugin data.
	 * @return array<string,array>
	 */
	private static function detect_plugin_requirements( $plugin_file, $plugin ) {
		$source = self::read_plugin_source_sample( $plugin_file );
		$rules  = array(
			'ZipArchive' => '/\bZipArchive\b/',
			'curl'      => '/\bcurl_[a-z0-9_]+\b/i',
			'openssl'   => '/\bopenssl_[a-z0-9_]+\b/i',
			'json'      => '/\bjson_(encode|decode)\b/i',
			'mbstring'  => '/\bmb_[a-z0-9_]+\b/i',
			'intl'      => '/\b(IntlDateFormatter|NumberFormatter|Transliterator)\b/i',
			'xml'       => '/\bxml_[a-z0-9_]+\b/i',
			'dom'       => '/\bDOM(Document|Element|XPath)\b/',
			'simplexml' => '/\b(SimpleXMLElement|simplexml_load_(string|file))\b/i',
			'fileinfo'  => '/\b(finfo_|mime_content_type)\b/i',
			'gd'        => '/\b(imagecreate|imagejpeg|imagepng|imagewebp|getimagesize)\b/i',
			'imagick'   => '/\bImagick\b/',
			'mysqli'    => '/\bmysqli_[a-z0-9_]+\b/i',
			'pdo_mysql' => '/\bPDO\b/',
			'zlib'      => '/\b(gzopen|gzdecode|gzencode|gzinflate|gzdeflate)\b/i',
			'iconv'     => '/\biconv(_[a-z0-9_]+)?\b/i',
			'sodium'    => '/\bsodium_[a-z0-9_]+\b/i',
			'soap'      => '/\bSoap(Client|Server)\b/',
			'redis'     => '/\bRedis\b/',
			'memcached' => '/\bMemcached\b/',
		);
		$features = array();
		$found    = array();

		foreach ( $rules as $extension => $pattern ) {
			if ( preg_match( $pattern, $source ) ) {
				$found[] = $extension;
			}
		}

		if ( preg_match( '/\b(wp_remote_(get|post|request)|download_url|curl_[a-z0-9_]+|https?:\/\/)/i', $source ) ) {
			$features[] = 'remote_downloads';
		}

		if (
			preg_match( '/\b(file_get_contents|fopen|copy)\s*\([^;\n]*(https?:\/\/|url|remote|download)/i', $source )
			|| preg_match( '/\ballow_url_fopen\b/i', $source )
		) {
			$features[] = 'url_stream_wrappers';
		}

		if ( preg_match( '/\b(wp_upload_dir|wp_handle_upload|media_handle_upload|wp_insert_attachment)\b/i', $source ) ) {
			$features[] = 'uploads_writable';
		}

		if ( preg_match( '/\b(wp_tempnam|sys_get_temp_dir|tempnam|ZipArchive)\b/i', $source ) ) {
			$features[] = 'temp_writable';
		}

		if ( preg_match( '/\b(register_rest_route|wp_ajax_)/i', $source ) ) {
			$features[] = 'rest_ajax';
		}

		if ( preg_match( '/\b(vendor\/autoload\.php|vendor\\\\autoload\.php)\b/i', $source ) ) {
			$features[] = 'composer_autoload';
		}

		return array(
			'extensions'        => array_values( array_unique( $found ) ),
			'features'          => array_values( array_unique( array_diff( $features, array( 'composer_autoload' ) ) ) ),
			'composer_autoload' => in_array( 'composer_autoload', $features, true ),
		);
	}

	/**
	 * Read a bounded sample of plugin PHP source.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @return string
	 */
	private static function read_plugin_source_sample( $plugin_file ) {
		$plugin_path = realpath( WP_PLUGIN_DIR . '/' . $plugin_file );
		$plugin_dir  = dirname( $plugin_file );
		$plugin_root = '.' === $plugin_dir ? false : realpath( WP_PLUGIN_DIR . '/' . $plugin_dir );

		if ( ! $plugin_path || 0 !== strpos( $plugin_path, realpath( WP_PLUGIN_DIR ) ) ) {
			return '';
		}

		$files = array( $plugin_path );
		if ( $plugin_root && is_dir( $plugin_root ) && 0 === strpos( $plugin_root, realpath( WP_PLUGIN_DIR ) ) ) {
			$plugin_files = array();
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_root, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
					$plugin_files[] = $file->getPathname();
				}
			}

			usort(
				$plugin_files,
				static function ( $a, $b ) {
					$priority_pattern = '/(autoload|compat|dependency|download|fetch|helper|http|import|install|remote|request|template|updater|zip)/i';
					$a_priority       = preg_match( $priority_pattern, $a ) ? 0 : 1;
					$b_priority       = preg_match( $priority_pattern, $b ) ? 0 : 1;

					if ( $a_priority === $b_priority ) {
						return strcasecmp( $a, $b );
					}

					return $a_priority <=> $b_priority;
				}
			);

			foreach ( $plugin_files as $file ) {
				if ( count( $files ) >= 120 ) {
					break;
				}
				$files[] = $file;
			}
		}

		$source = '';
		foreach ( array_unique( $files ) as $file ) {
			if ( strlen( $source ) > 1048576 || ! is_readable( $file ) ) {
				continue;
			}
			$chunk = file_get_contents( $file, false, null, 0, 262144 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false !== $chunk ) {
				$source .= "\n" . $chunk;
			}
		}

		return $source;
	}

	/**
	 * Build extension requirement row.
	 *
	 * @param string           $extension  Extension.
	 * @param array<int,array> $extensions Existing checks.
	 * @return array<string,mixed>
	 */
	private static function extension_requirement_row( $extension, $extensions ) {
		if ( 'ZipArchive' === $extension ) {
			return self::row( 'ZipArchive', class_exists( 'ZipArchive' ) ? __( 'Available', 'siteintelix' ) : __( 'Missing', 'siteintelix' ), class_exists( 'ZipArchive' ) ? 'pass' : 'danger', __( 'Detected from plugin source. Required for ZIP archive operations.', 'siteintelix' ) );
		}

		$match = self::find_check( $extensions, $extension, __( 'Detected from plugin source.', 'siteintelix' ) );
		$match['detail'] = __( 'Detected from plugin source. The server should provide this extension for reliable plugin behavior.', 'siteintelix' );
		return $match;
	}

	/**
	 * Build feature requirement row.
	 *
	 * @param string           $feature    Feature.
	 * @param array<int,array> $filesystem Filesystem checks.
	 * @param array<int,array> $network    Network checks.
	 * @param array<int,array> $wordpress  WordPress checks.
	 * @return array<string,mixed>
	 */
	private static function feature_requirement_row( $feature, $filesystem, $network, $wordpress ) {
		if ( 'remote_downloads' === $feature ) {
			return self::find_check( $network, __( 'HTTPS to WordPress.org', 'siteintelix' ), __( 'Detected remote download/API usage in plugin source.', 'siteintelix' ) );
		}

		if ( 'url_stream_wrappers' === $feature ) {
			$disabled_functions = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
			$blocked_functions  = array_intersect( $disabled_functions, array( 'file_get_contents', 'fopen', 'copy' ) );
			$enabled            = self::ini_enabled( 'allow_url_fopen' ) && empty( $blocked_functions );
			$detail             = __( 'Detected PHP URL stream usage in the selected plugin. Enable allow_url_fopen and keep file_get_contents/fopen/copy available, or remote/template imports may fail.', 'siteintelix' );

			if ( ! empty( $blocked_functions ) ) {
				$detail .= ' ' . sprintf(
					/* translators: %s: disabled PHP functions. */
					__( 'Blocked function(s): %s.', 'siteintelix' ),
					implode( ', ', $blocked_functions )
				);
			}

			return self::row(
				__( 'allow_url_fopen', 'siteintelix' ),
				self::ini_bool( 'allow_url_fopen' ),
				$enabled ? 'pass' : 'danger',
				$detail
			);
		}

		if ( 'uploads_writable' === $feature ) {
			return self::find_check( $filesystem, __( 'Uploads base writable', 'siteintelix' ), __( 'Detected upload/media usage in plugin source.', 'siteintelix' ) );
		}

		if ( 'temp_writable' === $feature ) {
			return self::find_check( $filesystem, __( 'Temp directory writable', 'siteintelix' ), __( 'Detected temporary file or archive usage in plugin source.', 'siteintelix' ) );
		}

		if ( 'rest_ajax' === $feature ) {
			return self::find_check( $wordpress, __( 'REST API', 'siteintelix' ), __( 'Detected REST/AJAX usage in plugin source.', 'siteintelix' ) );
		}

		return self::row( $feature, __( 'Detected', 'siteintelix' ), 'info', __( 'Detected from plugin source.', 'siteintelix' ) );
	}

	/**
	 * Compatibility profiles.
	 *
	 * @param string           $selected_plugin Selected plugin basename.
	 * @param array<int,array> $extensions      Extension checks.
	 * @param array<int,array> $filesystem      Filesystem checks.
	 * @param array<int,array> $network         Network checks.
	 * @param array<int,array> $wordpress       WordPress checks.
	 * @return array<string,array>
	 */
	private static function get_compatibility_profiles( $selected_plugin, $extensions, $filesystem, $network, $wordpress ) {
		if ( '' === $selected_plugin ) {
			$checks = array(
				self::row( __( 'Plugin selection', 'siteintelix' ), __( 'No plugin selected', 'siteintelix' ), 'info', __( 'Select an installed plugin above to compare its detectable requirements with this server.', 'siteintelix' ) ),
			);

			return array(
				'selected_plugin' => array(
					'title'       => __( 'Selected Plugin Compatibility', 'siteintelix' ),
					'description' => __( 'Choose a plugin to check required PHP, WordPress, extensions, filesystem, and network readiness.', 'siteintelix' ),
					'status'      => 'info',
					'checks'      => $checks,
				),
			);
		}

		$profile = self::build_plugin_profile( $selected_plugin, $extensions, $filesystem, $network, $wordpress );

		return array(
			'selected_plugin' => array(
				'title'       => $profile['title'],
				'description' => $profile['description'],
				'status'      => self::worst_status( $profile['checks'] ),
				'checks'      => $profile['checks'],
			),
		);
	}

	/**
	 * Summarize checks.
	 *
	 * @param array<int,array> $rows Rows.
	 * @return array<string,mixed>
	 */
	private static function summarize( $rows ) {
		$total    = count( $rows );
		$failed   = 0;
		$warnings = 0;
		$critical = array();

		foreach ( $rows as $row ) {
			if ( 'danger' === $row['status'] ) {
				++$failed;
				if ( count( $critical ) < 6 ) {
					$critical[] = $row['label'] . ': ' . self::stringify( $row['value'] );
				}
			} elseif ( 'warning' === $row['status'] ) {
				++$warnings;
			}
		}

		$score = $total ? max( 0, (int) round( 100 - ( ( $failed * 9 + $warnings * 3 ) / $total * 10 ) ) ) : 100;

		return array(
			'total'    => $total,
			'failed'   => $failed,
			'warnings' => $warnings,
			'score'    => $score,
			'critical' => $critical,
		);
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

		$lines[] = 'COMPATIBILITY';
		foreach ( $report['compatibility'] as $profile ) {
			$lines[] = $profile['title'] . ': ' . self::status_label( $profile['status'] );
			foreach ( $profile['checks'] as $check ) {
				$lines[] = '- [' . self::status_label( $check['status'] ) . '] ' . $check['label'] . ': ' . self::stringify( $check['value'] );
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
	 * Find a check by label.
	 *
	 * @param array<int,array> $rows     Rows.
	 * @param string           $label    Label.
	 * @param string           $fallback Fallback detail.
	 * @return array<string,mixed>
	 */
	private static function find_check( $rows, $label, $fallback ) {
		foreach ( $rows as $row ) {
			if ( $label === $row['label'] ) {
				return $row;
			}
		}

		return self::row( $label, __( 'Not checked', 'siteintelix' ), 'warning', $fallback );
	}

	/**
	 * Determine worst status.
	 *
	 * @param array<int,array> $rows Rows.
	 * @return string
	 */
	private static function worst_status( $rows ) {
		$status = 'pass';
		foreach ( $rows as $row ) {
			if ( 'danger' === $row['status'] ) {
				return 'danger';
			}
			if ( 'warning' === $row['status'] ) {
				$status = 'warning';
			}
		}
		return $status;
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
				'user-agent'  => 'SiteIntelix/' . SITEINTELIX_VERSION . '; ' . home_url( '/' ),
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
	 * Get requested plugin basename.
	 *
	 * @return string
	 */
	private static function get_requested_plugin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selected plugin.
		$plugin = isset( $_GET['selected_plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['selected_plugin'] ) ) : '';
		if ( '' === $plugin ) {
			return '';
		}

		$plugins = self::get_plugins();
		return isset( $plugins[ $plugin ] ) ? $plugin : '';
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
	 * Recent debug log matches.
	 *
	 * @param string $needle Needle.
	 * @return string
	 */
	private static function recent_log_matches( $needle ) {
		$path = WP_CONTENT_DIR . '/' . SITEINTELIX_DEBUG_LOG_FILENAME;
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$contents = file_get_contents( $path, false, null, 0, 300000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			return '';
		}

		$count = substr_count( strtolower( $contents ), strtolower( $needle ) );
			return $count ? sprintf(
				/* translators: %d: number of matching source lines. */
				__( '%d matching line(s)', 'siteintelix' ),
				$count
			) : '';
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
		return $redacted ? preg_replace( '#/[^,\s:]+#', '/…', $path ) : $path;
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
}
