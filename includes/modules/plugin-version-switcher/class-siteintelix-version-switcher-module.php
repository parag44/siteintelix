<?php
/**
 * Plugin Version Switcher module controller and UI.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

/** Registers the optional module screen, actions, assets, and admin-bar control. */
final class SITEINTELIX_Version_Switcher_Module {

	const PAGE_SLUG = 'siteintelix-version-switcher';

	/** @return void */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 36 );
		add_action( 'admin_post_siteintelix_version_upload', array( __CLASS__, 'handle_upload' ) );
		add_action( 'admin_post_siteintelix_version_switch', array( __CLASS__, 'handle_switch' ) );
		add_action( 'admin_post_siteintelix_version_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar' ), 90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_toolbar_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_toolbar_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_toolbar_forms' ), 100 );
		add_action( 'admin_footer', array( __CLASS__, 'render_toolbar_forms' ), 100 );
	}

	/** @return bool */
	public static function can_switch() {
		return current_user_can( 'update_plugins' ) && SITEINTELIX_Security::can_manage_global_tools();
	}

	/**
	 * Load package inspection and switching classes when an authorized request
	 * actually needs them. The current user is not reliably established during
	 * plugins_loaded on frontend requests, so capability-based boot loading can
	 * leave admin-bar callbacks without their runtime dependencies.
	 *
	 * @return bool
	 */
	private static function load_runtime() {
		if ( ! self::can_switch() ) {
			return false;
		}

		$classes = array(
			'SITEINTELIX_Version_Switcher_Package'  => SITEINTELIX_PLUGIN_DIR . 'includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-package.php',
			'SITEINTELIX_Version_Switcher_Switcher' => SITEINTELIX_PLUGIN_DIR . 'includes/modules/plugin-version-switcher/class-siteintelix-version-switcher-switcher.php',
		);
		foreach ( $classes as $class_name => $class_file ) {
			if ( class_exists( $class_name, false ) ) {
				continue;
			}
			if ( ! is_readable( $class_file ) ) {
				return false;
			}
			require_once $class_file;
		}

		return class_exists( 'SITEINTELIX_Version_Switcher_Package', false )
			&& class_exists( 'SITEINTELIX_Version_Switcher_Switcher', false );
	}

	/** @return void */
	private static function require_runtime() {
		if ( self::load_runtime() ) {
			return;
		}
		wp_die(
			esc_html__( 'Plugin Version Switcher could not load its required files. Reinstall SiteIntelix and try again.', 'siteintelix' ),
			'',
			array( 'response' => 500 )
		);
	}

	/** @return void */
	public static function register_menu() {
		if ( ! self::can_switch() ) {
			return;
		}
		add_submenu_page(
			'siteintelix',
			__( 'Plugin Version Switcher', 'siteintelix' ),
			__( 'Version Switcher', 'siteintelix' ),
			'update_plugins',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** @param string $hook_suffix Hook suffix. @return void */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'siteintelix_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'siteintelix-version-switcher', SITEINTELIX_PLUGIN_URL . 'includes/modules/plugin-version-switcher/assets/version-switcher.css', array( 'siteintelix-admin-style' ), SITEINTELIX_VERSION );
		wp_enqueue_script( 'siteintelix-version-switcher', SITEINTELIX_PLUGIN_URL . 'includes/modules/plugin-version-switcher/assets/version-switcher.js', array(), SITEINTELIX_VERSION, true );
		self::localize_script();
	}

	/** @return void */
	public static function enqueue_toolbar_assets() {
		if ( ! is_admin_bar_showing() || ! self::can_switch() ) {
			return;
		}
		if ( empty( self::toolbar_groups() ) ) {
			return;
		}
		wp_enqueue_style( 'siteintelix-version-switcher', SITEINTELIX_PLUGIN_URL . 'includes/modules/plugin-version-switcher/assets/version-switcher.css', array(), SITEINTELIX_VERSION );
		wp_enqueue_script( 'siteintelix-version-switcher', SITEINTELIX_PLUGIN_URL . 'includes/modules/plugin-version-switcher/assets/version-switcher.js', array(), SITEINTELIX_VERSION, true );
		self::localize_script();
	}

	/** @return void */
	private static function localize_script() {
		wp_localize_script(
			'siteintelix-version-switcher',
			'siteintelixVersionSwitcher',
			array(
				'environment'       => wp_get_environment_type(),
				'confirmTitle'      => __( 'Switch this plugin site-wide?', 'siteintelix' ),
				'confirmMessage'    => __( 'Switching replaces the plugin files for the entire website. All visitors and administrators will use the selected version.', 'siteintelix' ),
				'databaseWarning'   => __( 'SiteIntelix replaces plugin files only. It will not reverse database migrations, options, custom tables, metadata, or scheduled events. Create a database backup and use staging for versions that change the database.', 'siteintelix' ),
				'productionWarning' => __( 'This site is marked as production. Confirm that you have a current backup before continuing.', 'siteintelix' ),
			)
		);
	}

	/** @param WP_Admin_Bar $bar Admin bar. @return void */
	public static function register_admin_bar( $bar ) {
		if ( ! is_admin_bar_showing() || ! self::can_switch() ) {
			return;
		}
		$groups = self::toolbar_groups();
		if ( empty( $groups ) ) {
			return;
		}
		if ( ! $bar->get_node( 'siteintelix' ) ) {
			$bar->add_node(
				array(
					'id'    => 'siteintelix',
					'title' => '<span class="ab-icon dashicons dashicons-chart-area" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'SiteIntelix', 'siteintelix' ) . '</span>',
					'href'  => admin_url( 'admin.php?page=siteintelix' ),
				)
			);
		}
		$bar->add_node(
			array(
				'id'     => 'siteintelix-version-switcher',
				'parent' => 'siteintelix',
				'title'  => __( 'Version Switcher (site-wide)', 'siteintelix' ),
				'href'   => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			)
		);
		foreach ( $groups as $slug => $group ) {
			$group_id = 'siteintelix-version-' . sanitize_html_class( $slug );
			$bar->add_node(
				array(
					'id'     => $group_id,
					'parent' => 'siteintelix-version-switcher',
					'title'  => sprintf( /* translators: 1: plugin name, 2: installed version. */ __( '%1$s: %2$s', 'siteintelix' ), $group['name'], $group['current_version'] ),
					'href'   => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '#plugin-' . sanitize_html_class( $slug ) ),
				)
			);
			foreach ( $group['packages'] as $package ) {
				$is_current = (string) $package['version'] === (string) $group['current_version'];
				$bar->add_node(
					array(
						'id'     => 'siteintelix-version-package-' . $package['package_id'],
						'parent' => $group_id,
						'title'  => $is_current ? sprintf( /* translators: %s: version. */ __( '%s — current version', 'siteintelix' ), $package['version'] ) : sprintf( /* translators: %s: version. */ __( 'Switch to %s', 'siteintelix' ), $package['version'] ),
						'href'   => $is_current ? false : '#siteintelix-version-form-' . $package['package_id'],
						'meta'   => $is_current ? array() : array( 'class' => 'siteintelix-version-toolbar-action' ),
					)
				);
			}
		}
	}

	/** @return void */
	public static function render_toolbar_forms() {
		if ( ! is_admin_bar_showing() || ! self::can_switch() ) {
			return;
		}
		$groups = self::toolbar_groups();
		if ( empty( $groups ) ) {
			return;
		}
		$return_url = self::validated_return_url( self::current_url() );
		foreach ( $groups as $group ) {
			foreach ( $group['packages'] as $package ) {
				if ( (string) $package['version'] === (string) $group['current_version'] || SITEINTELIX_Version_Switcher_Switcher::is_self_package( $package ) ) {
					continue;
				}
				self::render_switch_form( $package, $return_url, true );
			}
		}
	}

	/** @return void */
	public static function handle_upload() {
		self::authorize( 'siteintelix_version_upload' );
		self::require_runtime();
		$files   = self::uploaded_files();
		$success = 0;
		$errors  = array();
		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_OK !== $file['error'] ) {
				$errors[] = __( 'One selected ZIP could not be uploaded by PHP.', 'siteintelix' );
				continue;
			}
			$result = SITEINTELIX_Version_Switcher_Package::import( $file['tmp_name'], $file['name'], get_current_user_id(), true );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$success++;
			}
		}
		if ( $success ) {
			self::set_notice( 'success', sprintf( /* translators: %d: build count. */ _n( '%d plugin build was added to the Version Vault.', '%d plugin builds were added to the Version Vault.', $success, 'siteintelix' ), $success ) );
		}
		if ( $errors ) {
			self::set_notice( 'error', implode( ' ', array_map( 'sanitize_text_field', $errors ) ) );
		}
		self::redirect( self::page_url() );
	}

	/** @return void */
	public static function handle_switch() {
		if ( ! self::can_switch() ) {
			wp_die( esc_html__( 'You do not have permission to switch plugin versions.', 'siteintelix' ), '', array( 'response' => 403 ) );
		}
		self::require_runtime();
		$package_id = isset( $_POST['package_id'] ) ? sanitize_text_field( wp_unslash( $_POST['package_id'] ) ) : '';
		check_admin_referer( 'siteintelix_version_switch_' . $package_id );
		$return_url = isset( $_POST['return_url'] ) ? self::validated_return_url( wp_unslash( $_POST['return_url'] ) ) : self::page_url();
		if ( 'production' === wp_get_environment_type() ) {
			$confirmed = isset( $_POST['production_confirm'] ) ? sanitize_key( wp_unslash( $_POST['production_confirm'] ) ) : '';
			if ( 'yes' !== $confirmed ) {
				self::set_notice( 'error', __( 'Production switching requires explicit backup confirmation.', 'siteintelix' ) );
				self::redirect( $return_url );
			}
		}

		$result = SITEINTELIX_Version_Switcher_Switcher::switch_to( $package_id, $return_url );
		if ( is_wp_error( $result ) ) {
			self::set_notice( 'error', $result->get_error_message() );
		} else {
			self::set_notice( 'success', sprintf( /* translators: 1: plugin name, 2: version. */ __( '%1$s was switched to version %2$s. Activation state was preserved.', 'siteintelix' ), $result['plugin_name'], $result['target_version'] ) );
		}
		self::redirect( $return_url );
	}

	/** @return void */
	public static function handle_delete() {
		if ( ! self::can_switch() ) {
			wp_die( esc_html__( 'You do not have permission to delete stored plugin builds.', 'siteintelix' ), '', array( 'response' => 403 ) );
		}
		$package_id = isset( $_POST['package_id'] ) ? sanitize_text_field( wp_unslash( $_POST['package_id'] ) ) : '';
		check_admin_referer( 'siteintelix_version_delete_' . $package_id );
		$package = SITEINTELIX_Version_Switcher_Storage::package( $package_id );
		$result  = is_wp_error( $package ) ? $package : SITEINTELIX_Version_Switcher_Storage::delete_package( $package_id );
		if ( is_wp_error( $result ) ) {
			self::set_notice( 'error', $result->get_error_message() );
		} else {
			SITEINTELIX_Version_Switcher_Storage::add_history(
				array(
					'action'         => 'delete',
					'plugin_name'    => $package['plugin_name'],
					'plugin_slug'    => $package['plugin_slug'],
					'target_version' => $package['version'],
					'success'        => true,
				)
			);
			self::set_notice( 'success', __( 'The stored plugin build was deleted from the Version Vault.', 'siteintelix' ) );
		}
		self::redirect( self::page_url() );
	}

	/** @return void */
	public static function render_page() {
		if ( ! self::can_switch() ) {
			wp_die( esc_html__( 'You do not have permission to manage plugin versions.', 'siteintelix' ) );
		}
		self::require_runtime();
		$groups  = self::all_groups();
		$history = SITEINTELIX_Version_Switcher_Storage::history();
		$notice  = self::get_notice();
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap" id="siteintelix-version-switcher-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-update-alt',
					'title'       => __( 'Plugin Version Switcher', 'siteintelix' ),
					'description' => __( 'Compare private plugin builds by safely replacing the one installed site-wide.', 'siteintelix' ),
					'badges'      => array( SITEINTELIX_Admin_UI::badge( __( 'Version Vault', 'siteintelix' ), 'info', 'dashicons-lock' ) ),
				)
			);
			?>
			<div class="siteintelix-container sitx-version-container">
				<div id="siteintelix-notices-slot">
					<?php if ( $notice ) : ?>
						<div class="sitx-alert sitx-alert--<?php echo esc_attr( 'error' === $notice['type'] ? 'danger' : 'success' ); ?> siteintelix-notice" role="<?php echo esc_attr( 'error' === $notice['type'] ? 'alert' : 'status' ); ?>"><div class="sitx-alert__icon"><span class="dashicons <?php echo esc_attr( 'error' === $notice['type'] ? 'dashicons-warning' : 'dashicons-yes-alt' ); ?>"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php echo esc_html( 'error' === $notice['type'] ? __( 'Version Switcher could not complete the action.', 'siteintelix' ) : __( 'Version Switcher action completed.', 'siteintelix' ) ); ?></strong><p class="sitx-alert__msg"><?php echo esc_html( $notice['message'] ); ?></p></div></div>
					<?php endif; ?>
					<?php if ( SITEINTELIX_Version_Switcher_Storage::may_be_web_accessible() ) : ?>
						<div class="sitx-alert sitx-alert--warning siteintelix-notice"><div class="sitx-alert__icon"><span class="dashicons dashicons-shield"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Verify Nginx or server-level protection', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The default vault may be beneath the web root. Apache and IIS denial files were created, but Nginx does not read them. Deny web access to the SiteIntelix private storage directory, or define SITEINTELIX_VERSION_VAULT_DIR outside the public web root.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
				</div>

				<section class="si-card sitx-version-warning" aria-labelledby="sitx-version-warning-title">
					<h2 id="sitx-version-warning-title"><?php esc_html_e( 'A switch affects the entire website', 'siteintelix' ); ?></h2>
					<p><?php esc_html_e( 'Switching replaces the active plugin files for the entire website. All visitors and administrators will use the selected version.', 'siteintelix' ); ?></p>
					<p><strong><?php esc_html_e( 'Database warning:', 'siteintelix' ); ?></strong> <?php esc_html_e( 'SiteIntelix replaces plugin files only. It does not reverse database migrations, changed options, custom tables, post metadata, or scheduled events. Create a database backup and use staging when testing versions that modify the database.', 'siteintelix' ); ?></p>
				</section>

				<section class="si-card sitx-version-upload" aria-labelledby="sitx-version-upload-title">
					<div><h2 id="sitx-version-upload-title"><?php esc_html_e( 'Upload Builds', 'siteintelix' ); ?></h2><p><?php esc_html_e( 'ZIPs are inspected and stored privately. Uploading never installs or activates a plugin.', 'siteintelix' ); ?></p></div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<input type="hidden" name="action" value="siteintelix_version_upload">
						<?php wp_nonce_field( 'siteintelix_version_upload' ); ?>
						<label class="sitx-version-file"><span><?php esc_html_e( 'Plugin ZIP packages', 'siteintelix' ); ?></span><input type="file" name="plugin_builds[]" accept=".zip,application/zip" multiple required></label>
						<button type="submit" class="si-button si-button--primary"><span class="dashicons dashicons-upload"></span><span><?php esc_html_e( 'Add to Version Vault', 'siteintelix' ); ?></span></button>
					</form>
				</section>

				<section aria-labelledby="sitx-version-builds-title"><div class="sitx-version-section-heading"><div><h2 id="sitx-version-builds-title"><?php esc_html_e( 'Stored plugin builds', 'siteintelix' ); ?></h2><p><?php esc_html_e( 'Builds are grouped from saved metadata; the vault is not rescanned during page loads.', 'siteintelix' ); ?></p></div><a class="si-button si-button--ghost" href="#sitx-version-history"><?php esc_html_e( 'View switching history', 'siteintelix' ); ?></a></div>
					<?php if ( empty( $groups ) ) : ?>
						<div class="si-card"><?php SITEINTELIX_Admin_UI::empty_state( __( 'No builds stored yet', 'siteintelix' ), __( 'Upload two versions of a plugin to begin comparing them.', 'siteintelix' ), 'dashicons-archive' ); ?></div>
					<?php else : foreach ( $groups as $slug => $group ) : self::render_group( $slug, $group ); endforeach; endif; ?>
				</section>

				<section class="si-card sitx-version-history" id="sitx-version-history" aria-labelledby="sitx-version-history-title"><h2 id="sitx-version-history-title"><?php esc_html_e( 'Switching history', 'siteintelix' ); ?></h2>
					<div class="si-table-wrap"><table class="widefat striped si-table"><thead><tr><th><?php esc_html_e( 'Date', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Plugin', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Change', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Result', 'siteintelix' ); ?></th><th><?php esc_html_e( 'User', 'siteintelix' ); ?></th></tr></thead><tbody>
					<?php if ( empty( $history ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'No version-switch history yet.', 'siteintelix' ); ?></td></tr><?php else : foreach ( $history as $entry ) : ?>
						<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', absint( $entry['timestamp'] ) ) ); ?></td><td><?php echo esc_html( $entry['plugin_name'] ); ?><small><code><?php echo esc_html( $entry['plugin_slug'] ); ?></code></small></td><td><?php echo esc_html( 'delete' === $entry['action'] ? sprintf( /* translators: %s: version. */ __( 'Deleted build %s', 'siteintelix' ), $entry['target_version'] ) : sprintf( /* translators: 1: old version, 2: new version. */ __( '%1$s to %2$s', 'siteintelix' ), $entry['previous_version'], $entry['target_version'] ) ); ?></td><td><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( ! empty( $entry['success'] ) ? __( 'Success', 'siteintelix' ) : __( 'Failed', 'siteintelix' ), ! empty( $entry['success'] ) ? 'success' : 'danger' ) ); ?><?php if ( empty( $entry['success'] ) && ! empty( $entry['error_message'] ) ) : ?><small><?php echo esc_html( $entry['error_message'] ); ?></small><?php endif; ?></td><td><?php echo esc_html( get_the_author_meta( 'display_name', absint( $entry['user_id'] ) ) ?: __( 'Unknown user', 'siteintelix' ) ); ?></td></tr>
					<?php endforeach; endif; ?></tbody></table></div>
				</section>
			</div>
		</div>
		<?php
	}

	/** @param string $slug Slug. @param array<string,mixed> $group Group. @return void */
	private static function render_group( $slug, $group ) {
		?>
		<article class="si-card sitx-version-group" id="plugin-<?php echo esc_attr( sanitize_html_class( $slug ) ); ?>"><header><div><h3><?php echo esc_html( $group['name'] ); ?></h3><code><?php echo esc_html( $slug ); ?></code></div><div><?php if ( $group['installed'] ) : ?><span><?php echo esc_html( sprintf( /* translators: %s: installed version. */ __( 'Installed: %s', 'siteintelix' ), $group['current_version'] ) ); ?></span><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( $group['active'] ? __( 'Active', 'siteintelix' ) : __( 'Inactive', 'siteintelix' ), $group['active'] ? 'success' : 'neutral' ) ); ?><?php else : ?><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( __( 'Not installed', 'siteintelix' ), 'warning' ) ); ?><?php endif; ?></div></header>
			<div class="si-table-wrap"><table class="widefat striped si-table sitx-version-table"><thead><tr><th><?php esc_html_e( 'Stored build', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Uploaded', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Size', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Checksum', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Compatibility', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Actions', 'siteintelix' ); ?></th></tr></thead><tbody>
			<?php foreach ( $group['packages'] as $package ) : $warnings = SITEINTELIX_Version_Switcher_Package::compatibility_warnings( $package ); $same_version = $group['installed'] && (string) $group['current_version'] === (string) $package['version']; ?>
				<tr><td><strong><?php echo esc_html( $package['version'] ); ?></strong><?php if ( $same_version ) : ?> <?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( __( 'Current version', 'siteintelix' ), 'info' ) ); ?><?php endif; ?><small><?php echo esc_html( $package['original_name'] ); ?></small><?php if ( SITEINTELIX_Version_Switcher_Switcher::is_self_package( $package ) ) : ?><small class="sitx-version-error"><?php esc_html_e( 'SiteIntelix builds may be stored, but cannot be switched by this running module.', 'siteintelix' ); ?></small><?php endif; ?></td><td><?php echo esc_html( wp_date( get_option( 'date_format' ), absint( $package['uploaded_at'] ) ) ); ?></td><td><?php echo esc_html( size_format( absint( $package['file_size'] ) ) ); ?></td><td><code title="<?php esc_attr_e( 'SHA-256 checksum prefix', 'siteintelix' ); ?>"><?php echo esc_html( substr( $package['checksum'], 0, 12 ) ); ?>&hellip;</code></td><td><?php if ( $warnings ) : foreach ( $warnings as $warning ) : ?><span class="sitx-version-error"><?php echo esc_html( $warning ); ?></span><?php endforeach; else : ?><?php echo wp_kses_post( SITEINTELIX_Admin_UI::badge( __( 'Compatible', 'siteintelix' ), 'success' ) ); ?><?php endif; ?></td><td><div class="sitx-version-actions">
				<?php if ( $group['installed'] && empty( $warnings ) && ! SITEINTELIX_Version_Switcher_Switcher::is_self_package( $package ) ) : self::render_switch_form( $package, self::page_url(), false, $same_version ); endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-version-delete-form"><input type="hidden" name="action" value="siteintelix_version_delete"><input type="hidden" name="package_id" value="<?php echo esc_attr( $package['package_id'] ); ?>"><?php wp_nonce_field( 'siteintelix_version_delete_' . $package['package_id'] ); ?><button type="submit" class="si-button si-button--danger si-button--small" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this stored build from the Version Vault?', 'siteintelix' ); ?>"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></button></form>
				</div></td></tr>
			<?php endforeach; ?></tbody></table></div>
		</article>
		<?php
	}

	/** @param array<string,mixed> $package Package. @param string $return_url Return URL. @param bool $toolbar Toolbar form. @param bool $same_version Same version. @return void */
	private static function render_switch_form( $package, $return_url, $toolbar, $same_version = false ) {
		$classes = $toolbar ? 'siteintelix-version-switch-form siteintelix-version-toolbar-form' : 'siteintelix-version-switch-form';
		?>
		<form id="siteintelix-version-form-<?php echo esc_attr( $package['package_id'] ); ?>" class="<?php echo esc_attr( $classes ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" <?php echo $toolbar ? 'hidden' : ''; ?>>
			<input type="hidden" name="action" value="siteintelix_version_switch"><input type="hidden" name="package_id" value="<?php echo esc_attr( $package['package_id'] ); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr( $return_url ); ?>"><input type="hidden" name="production_confirm" value="">
			<?php wp_nonce_field( 'siteintelix_version_switch_' . $package['package_id'] ); ?>
			<?php if ( ! $toolbar ) : ?><button type="submit" class="si-button si-button--primary si-button--small"><?php echo esc_html( $same_version ? __( 'Reinstall this build', 'siteintelix' ) : __( 'Switch to this version', 'siteintelix' ) ); ?></button><?php endif; ?>
		</form>
		<?php
	}

	/** @return array<string,array<string,mixed>> */
	private static function all_groups() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed = get_plugins();
		$active    = get_option( 'active_plugins', array() );
		$network   = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$groups    = array();
		foreach ( SITEINTELIX_Version_Switcher_Storage::manifest() as $package ) {
			if ( ! is_array( $package ) || empty( $package['plugin_slug'] ) || empty( $package['primary_plugin_file'] ) ) {
				continue;
			}
			$slug = $package['plugin_slug'];
			if ( ! isset( $groups[ $slug ] ) ) {
				$primary = $package['primary_plugin_file'];
				$groups[ $slug ] = array(
					'name'            => $package['plugin_name'],
					'installed'       => isset( $installed[ $primary ] ),
					'current_version' => isset( $installed[ $primary ]['Version'] ) ? $installed[ $primary ]['Version'] : __( 'Not installed', 'siteintelix' ),
					'active'          => in_array( $primary, (array) $active, true ) || isset( $network[ $primary ] ),
					'packages'        => array(),
				);
			}
			$groups[ $slug ]['packages'][] = $package;
		}
		foreach ( $groups as &$group ) {
			usort( $group['packages'], static function ( $left, $right ) { return version_compare( $right['version'], $left['version'] ); } );
		}
		unset( $group );
		return $groups;
	}

	/** @return array<string,array<string,mixed>> */
	private static function toolbar_groups() {
		if ( ! self::load_runtime() ) {
			return array();
		}
		$groups = self::all_groups();
		foreach ( $groups as $slug => $group ) {
			$alternatives = array_filter( $group['packages'], static function ( $package ) use ( $group ) { return (string) $package['version'] !== (string) $group['current_version']; } );
			if ( ! $group['installed'] || empty( $alternatives ) || SITEINTELIX_Version_Switcher_Switcher::is_self_package( $group['packages'][0] ) ) {
				unset( $groups[ $slug ] );
			}
		}
		return $groups;
	}

	/** @return array<int,array<string,mixed>> */
	private static function uploaded_files() {
		if ( empty( $_FILES['plugin_builds'] ) || ! is_array( $_FILES['plugin_builds'] ) ) {
			return array();
		}
		$input = $_FILES['plugin_builds']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Individual names are sanitized and temporary paths are upload-verified during import.
		$files = array();
		foreach ( (array) $input['name'] as $index => $name ) {
			$files[] = array(
				'name'     => sanitize_file_name( wp_unslash( $name ) ),
				'tmp_name' => isset( $input['tmp_name'][ $index ] ) ? (string) $input['tmp_name'][ $index ] : '',
				'error'    => isset( $input['error'][ $index ] ) ? absint( $input['error'][ $index ] ) : UPLOAD_ERR_NO_FILE,
			);
		}
		return $files;
	}

	/** @param string $nonce_action Nonce action. @return void */
	private static function authorize( $nonce_action ) {
		if ( ! self::can_switch() ) {
			wp_die( esc_html__( 'You do not have permission to manage stored plugin builds.', 'siteintelix' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	/** @param string $url Candidate URL. @return string */
	public static function validated_return_url( $url ) {
		$fallback = self::page_url();
		return wp_validate_redirect( esc_url_raw( (string) $url ), $fallback );
	}

	/** @return string */
	private static function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		return $host ? $scheme . $host . $uri : self::page_url();
	}

	/** @return string */
	private static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/** @param string $type Notice type. @param string $message Message. @return void */
	private static function set_notice( $type, $message ) {
		set_transient( 'siteintelix_version_notice_' . get_current_user_id(), array( 'type' => sanitize_key( $type ), 'message' => SITEINTELIX_Version_Switcher_Storage::safe_message( $message ) ), 120 );
	}

	/** @return array<string,string>|null */
	private static function get_notice() {
		$key    = 'siteintelix_version_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		return is_array( $notice ) ? $notice : null;
	}

	/** @param string $url URL. @return void */
	private static function redirect( $url ) {
		wp_safe_redirect( self::validated_return_url( $url ) );
		exit;
	}
}
