<?php
/**
 * Database Manager module for SiteIntelix.
 *
 * @package SiteIntelix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides safe database table overview, row browsing, and row editing.
 */
class SITEINTELIX_Database_Manager_Module {

	const PER_PAGE = 50;

	/**
	 * Register module hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 36 );
			add_action( 'admin_post_siteintelix_db_update_row', array( __CLASS__, 'handle_update_row' ) );
			add_action( 'admin_post_siteintelix_db_delete_row', array( __CLASS__, 'handle_delete_row' ) );
		}
	}

	/**
	 * Register Database Manager submenu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'siteintelix',
			__( 'Database Manager', 'siteintelix' ),
			__( 'Database Manager', 'siteintelix' ),
			'manage_options',
			'siteintelix-database-manager',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the Database Manager page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the database.', 'siteintelix' ) );
		}

		$tables         = self::get_tables();
		$selected_table = isset( $_GET['table'] ) ? sanitize_text_field( wp_unslash( $_GET['table'] ) ) : '';
		$active_view    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'dashboard';
		$active_view    = in_array( $active_view, array( 'dashboard', 'tables', 'edit' ), true ) ? $active_view : 'dashboard';

		if ( in_array( $active_view, array( 'tables', 'edit' ), true ) && ( '' === $selected_table || ! isset( $tables[ $selected_table ] ) ) ) {
			$selected_table = ! empty( $tables ) ? (string) array_key_first( $tables ) : '';
		}

		$total_records = array_sum( wp_list_pluck( $tables, 'rows' ) );
		?>
		<div class="wrap siteintelix-wrap si-admin-wrap sitx-db-manager" id="siteintelix-database-manager-page">
			<?php
			SITEINTELIX_Admin_UI::page_header(
				array(
					'icon'        => 'dashicons-database',
					'title'       => __( 'Database Manager', 'siteintelix' ),
					'description' => __( 'Inspect database tables, records, storage usage, and selected row data.', 'siteintelix' ),
					'badges'      => array(
						SITEINTELIX_Admin_UI::badge( 'v' . SITEINTELIX_VERSION, 'neutral' ),
						SITEINTELIX_Admin_UI::badge(
							sprintf(
								/* translators: %d: database table count. */
								_n( '%d Table', '%d Tables', count( $tables ), 'siteintelix' ),
								absint( count( $tables ) )
							),
							'info',
							'dashicons-database'
						),
						SITEINTELIX_Admin_UI::badge(
							sprintf(
								/* translators: %d: total database rows. */
								_n( '%d Record', '%d Records', $total_records, 'siteintelix' ),
								absint( $total_records )
							),
							'neutral'
						),
					),
				)
			);
			?>

			<header class="sitx-db-header">
				<nav class="sitx-db-tabs" aria-label="<?php esc_attr_e( 'Database Manager views', 'siteintelix' ); ?>">
					<a class="<?php echo 'dashboard' === $active_view ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-database-manager&view=dashboard' ) ); ?>"><?php esc_html_e( 'Dashboard', 'siteintelix' ); ?></a>
					<a class="<?php echo in_array( $active_view, array( 'tables', 'edit' ), true ) ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-database-manager&view=tables' ) ); ?>"><?php esc_html_e( 'Tables', 'siteintelix' ); ?></a>
				</nav>
			</header>

			<div class="siteintelix-container sitx-db-container">
				<div id="siteintelix-notices-slot">
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_db_saved'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Row saved successfully.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected database row was updated.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_db_deleted'] ) ) : ?>
						<div class="sitx-alert sitx-alert--success"><div class="sitx-alert__icon"><span class="dashicons dashicons-trash"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Row deleted successfully.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php esc_html_e( 'The selected database row was removed.', 'siteintelix' ); ?></p></div></div>
					<?php endif; ?>
					<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status flag. ?>
					<?php if ( isset( $_GET['siteintelix_db_error'] ) ) : ?>
						<div class="sitx-alert sitx-alert--error"><div class="sitx-alert__icon"><span class="dashicons dashicons-warning"></span></div><div class="sitx-alert__content"><strong class="sitx-alert__title"><?php esc_html_e( 'Database action failed.', 'siteintelix' ); ?></strong><p class="sitx-alert__msg"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['siteintelix_db_error'] ) ) ); ?></p></div></div>
					<?php endif; ?>
				</div>

				<?php if ( 'edit' === $active_view ) : ?>
					<?php self::render_edit_view( $selected_table ); ?>
				<?php elseif ( 'tables' === $active_view ) : ?>
					<?php self::render_tables_view( $tables, $selected_table ); ?>
				<?php else : ?>
					<?php self::render_dashboard_view( $tables ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render dashboard overview.
	 *
	 * @param array<string,array<string,mixed>> $tables Tables keyed by name.
	 * @return void
	 */
	private static function render_dashboard_view( $tables ) {
		$total_records = array_sum( wp_list_pluck( $tables, 'rows' ) );
		$total_data    = array_sum( wp_list_pluck( $tables, 'data_length' ) );
		$total_index   = array_sum( wp_list_pluck( $tables, 'index_length' ) );
		?>
		<div class="sitx-db-overview-title">
			<h1><?php esc_html_e( 'Overview', 'siteintelix' ); ?></h1>
			<span><?php echo esc_html( sprintf( _n( '%d table', '%d tables', count( $tables ), 'siteintelix' ), count( $tables ) ) ); ?></span>
		</div>
		<div class="sitx-db-stats">
			<div class="si-card sitx-db-stat-card">
				<span><?php esc_html_e( 'Total Tables', 'siteintelix' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( count( $tables ) ) ); ?></strong>
			</div>
			<div class="si-card sitx-db-stat-card">
				<span><?php esc_html_e( 'Total Records', 'siteintelix' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( absint( $total_records ) ) ); ?></strong>
			</div>
			<div class="si-card sitx-db-stat-card">
				<span><?php esc_html_e( 'Data Usage', 'siteintelix' ); ?></span>
				<strong><?php echo esc_html( self::bytes_to_mb( absint( $total_data ) ) ); ?> MB</strong>
			</div>
			<div class="si-card sitx-db-stat-card">
				<span><?php esc_html_e( 'Index Usage', 'siteintelix' ); ?></span>
				<strong><?php echo esc_html( self::bytes_to_mb( absint( $total_index ) ) ); ?> MB</strong>
			</div>
		</div>
		<div class="sitx-db-table-wrap si-table-wrap">
			<table class="widefat striped sitx-db-table si-table">
				<thead><tr><th><?php esc_html_e( 'No.', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Table', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Records', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Data Usage (MB)', 'siteintelix' ); ?></th><th><?php esc_html_e( 'Index Usage (MB)', 'siteintelix' ); ?></th></tr></thead>
				<tbody>
					<?php if ( empty( $tables ) ) : ?>
						<tr>
							<td colspan="5">
								<div class="si-empty-state">
									<h2><?php esc_html_e( 'No database tables found.', 'siteintelix' ); ?></h2>
									<p><?php esc_html_e( 'SiteIntelix could not find database tables for this connection.', 'siteintelix' ); ?></p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php $index = 1; foreach ( $tables as $table ) : ?>
							<tr>
								<td class="si-cell-number"><?php echo esc_html( (string) $index ); ?></td>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-database-manager&view=tables&table=' . rawurlencode( $table['name'] ) ) ); ?>"><?php echo esc_html( $table['name'] ); ?></a></td>
								<td class="si-cell-number"><?php echo esc_html( number_format_i18n( absint( $table['rows'] ) ) ); ?></td>
								<td class="si-cell-number"><?php echo esc_html( self::bytes_to_mb( absint( $table['data_length'] ) ) ); ?></td>
								<td class="si-cell-number"><?php echo esc_html( self::bytes_to_mb( absint( $table['index_length'] ) ) ); ?></td>
							</tr>
						<?php $index++; endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render table browser.
	 *
	 * @param array<string,array<string,mixed>> $tables         Tables keyed by name.
	 * @param string                           $selected_table Selected table.
	 * @return void
	 */
	private static function render_tables_view( $tables, $selected_table ) {
		$table_search = isset( $_GET['table_search'] ) ? sanitize_text_field( wp_unslash( $_GET['table_search'] ) ) : '';
		$row_search   = isset( $_GET['row_search'] ) ? sanitize_text_field( wp_unslash( $_GET['row_search'] ) ) : '';
		$page         = isset( $_GET['db_page'] ) ? max( 1, absint( wp_unslash( $_GET['db_page'] ) ) ) : 1;
		$columns      = $selected_table ? self::get_columns( $selected_table ) : array();
		$primary_key  = self::get_primary_key( $columns );
		$total_rows   = $selected_table ? self::count_rows( $selected_table, $columns, $row_search ) : 0;
		$rows         = $selected_table ? self::get_table_rows( $selected_table, $columns, $row_search, $page ) : array();
		?>
		<div class="sitx-db-browser si-card">
			<aside class="sitx-db-sidebar">
				<form method="get" class="sitx-db-sidebar-search">
					<input type="hidden" name="page" value="siteintelix-database-manager">
					<input type="hidden" name="view" value="tables">
					<input type="search" name="table_search" value="<?php echo esc_attr( $table_search ); ?>" placeholder="<?php esc_attr_e( 'Search for table...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search database tables', 'siteintelix' ); ?>">
					<button type="submit" class="si-button si-button--icon si-button--ghost" aria-label="<?php esc_attr_e( 'Search tables', 'siteintelix' ); ?>"><span class="dashicons dashicons-search"></span></button>
				</form>
				<ul class="sitx-db-table-list">
					<?php foreach ( $tables as $table_name => $table ) : ?>
						<?php if ( '' !== $table_search && false === stripos( $table_name, $table_search ) ) { continue; } ?>
						<li><a class="<?php echo $table_name === $selected_table ? 'is-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=siteintelix-database-manager&view=tables&table=' . rawurlencode( $table_name ) ) ); ?>"><span class="dashicons dashicons-database-view"></span><?php echo esc_html( $table_name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</aside>

			<main class="sitx-db-rows">
				<div class="sitx-db-row-toolbar">
					<div><span class="dashicons dashicons-visibility" aria-hidden="true"></span><strong><?php echo esc_html( $selected_table ? $selected_table : __( 'No table selected', 'siteintelix' ) ); ?></strong></div>
					<form method="get" class="sitx-db-row-search">
						<input type="hidden" name="page" value="siteintelix-database-manager">
						<input type="hidden" name="view" value="tables">
						<input type="hidden" name="table" value="<?php echo esc_attr( $selected_table ); ?>">
						<input type="search" name="row_search" value="<?php echo esc_attr( $row_search ); ?>" placeholder="<?php esc_attr_e( 'Search rows...', 'siteintelix' ); ?>" aria-label="<?php esc_attr_e( 'Search selected table rows', 'siteintelix' ); ?>">
					</form>
				</div>
				<div class="sitx-db-grid-scroll si-table-wrap">
					<table class="widefat striped sitx-db-table sitx-db-row-table si-table">
						<thead>
							<tr>
								<?php if ( $primary_key ) : ?>
									<th class="sitx-db-action-column"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'siteintelix' ); ?></span></th>
								<?php endif; ?>
								<?php foreach ( $columns as $column ) : ?>
									<th><?php echo esc_html( $column['Field'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $rows ) ) : ?>
								<tr>
									<td colspan="<?php echo esc_attr( (string) max( 1, count( $columns ) + ( $primary_key ? 1 : 0 ) ) ); ?>">
										<div class="si-empty-state">
											<h2><?php esc_html_e( 'No rows found.', 'siteintelix' ); ?></h2>
											<p><?php esc_html_e( 'Select another table, change the search term, or move through the pagination.', 'siteintelix' ); ?></p>
										</div>
									</td>
								</tr>
							<?php else : ?>
								<?php foreach ( $rows as $row ) : ?>
									<?php $row_id = $primary_key && isset( $row[ $primary_key ] ) ? (string) $row[ $primary_key ] : ''; ?>
									<tr>
										<?php if ( $primary_key ) : ?>
											<td class="sitx-db-action-column">
												<?php if ( '' !== $row_id ) : ?>
													<a class="si-button si-button--icon si-button--ghost sitx-db-edit-link" href="<?php echo esc_url( self::get_edit_row_url( $selected_table, $primary_key, $row_id, $page, $row_search ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Edit row %s', 'siteintelix' ), $row_id ) ); ?>">
														<span class="dashicons dashicons-edit" aria-hidden="true"></span>
													</a>
												<?php else : ?>
													<span class="sitx-db-action-placeholder" aria-hidden="true">—</span>
												<?php endif; ?>
											</td>
										<?php endif; ?>
										<?php foreach ( $columns as $column ) : ?>
											<td><?php echo esc_html( self::format_cell( isset( $row[ $column['Field'] ] ) ? $row[ $column['Field'] ] : null ) ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
				<?php self::render_row_pagination( $selected_table, $page, $total_rows, $row_search ); ?>
			</main>
		</div>
		<?php
	}

	/**
	 * Render dedicated row edit view.
	 *
	 * @param string $selected_table Selected table.
	 * @return void
	 */
	private static function render_edit_view( $selected_table ) {
		$row_search   = isset( $_GET['row_search'] ) ? sanitize_text_field( wp_unslash( $_GET['row_search'] ) ) : '';
		$page         = isset( $_GET['db_page'] ) ? max( 1, absint( wp_unslash( $_GET['db_page'] ) ) ) : 1;
		$selected_id  = isset( $_GET['row_id'] ) ? sanitize_text_field( wp_unslash( $_GET['row_id'] ) ) : '';
		$columns      = $selected_table ? self::get_columns( $selected_table ) : array();
		$primary_key  = self::get_primary_key( $columns );
		$selected_row = $selected_table && $primary_key && '' !== $selected_id ? self::get_row_by_primary_key( $selected_table, $primary_key, $selected_id ) : null;
		$back_url     = self::get_table_url( $selected_table, $page, $row_search );
		?>
		<div class="sitx-db-edit-page si-card">
			<div class="sitx-db-edit-header">
				<div>
					<a class="sitx-db-back-link" href="<?php echo esc_url( $back_url ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
						<?php esc_html_e( 'Back to rows', 'siteintelix' ); ?>
					</a>
					<h2><?php echo esc_html( sprintf( __( 'Edit: %s', 'siteintelix' ), $selected_table ? $selected_table : __( 'No table selected', 'siteintelix' ) ) ); ?></h2>
					<?php if ( $primary_key && '' !== $selected_id ) : ?>
						<p><?php echo esc_html( sprintf( __( '%1$s = %2$s', 'siteintelix' ), $primary_key, $selected_id ) ); ?></p>
					<?php endif; ?>
				</div>
				<a class="si-button si-button--secondary" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></a>
			</div>
			<div class="sitx-db-editor sitx-db-editor--page">
				<?php self::render_row_editor( $selected_table, $columns, $primary_key, $selected_row, $selected_id, $page, $row_search ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render selected row editor.
	 *
	 * @param string               $table        Table name.
	 * @param array<int,array>     $columns      Columns.
	 * @param string               $primary_key  Primary key column.
	 * @param array<string,mixed>|null $row      Selected row.
	 * @param string               $selected_id  Selected primary key value.
	 * @param int                  $page         Return page number.
	 * @param string               $search       Return row search.
	 * @return void
	 */
	private static function render_row_editor( $table, $columns, $primary_key, $row, $selected_id, $page = 1, $search = '' ) {
		if ( ! $table ) {
			echo '<div class="si-empty-state"><h2>' . esc_html__( 'Choose a table', 'siteintelix' ) . '</h2><p>' . esc_html__( 'Select a table from the list to inspect its rows.', 'siteintelix' ) . '</p></div>';
			return;
		}

		if ( ! $primary_key ) {
			echo '<div class="si-empty-state"><h2>' . esc_html__( 'Read-only table', 'siteintelix' ) . '</h2><p>' . esc_html__( 'This table has no primary key, so rows can be viewed but not edited safely.', 'siteintelix' ) . '</p></div>';
			return;
		}

		if ( ! $row ) {
			echo '<div class="si-empty-state"><h2>' . esc_html__( 'Select a row', 'siteintelix' ) . '</h2><p>' . esc_html__( 'Choose a row from the table to inspect and edit its values.', 'siteintelix' ) . '</p></div>';
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sitx-db-editor-form">
			<input type="hidden" name="action" value="siteintelix_db_update_row">
			<input type="hidden" name="table" value="<?php echo esc_attr( $table ); ?>">
			<input type="hidden" name="primary_key" value="<?php echo esc_attr( $primary_key ); ?>">
			<input type="hidden" name="primary_value" value="<?php echo esc_attr( $selected_id ); ?>">
			<input type="hidden" name="db_page" value="<?php echo esc_attr( (string) absint( $page ) ); ?>">
			<input type="hidden" name="row_search" value="<?php echo esc_attr( $search ); ?>">
			<?php wp_nonce_field( 'siteintelix_db_update_row_' . $table . '_' . $selected_id ); ?>
			<?php foreach ( $columns as $column ) : ?>
				<?php $field = $column['Field']; ?>
				<label class="si-form-row">
					<span><?php echo esc_html( $field ); ?> <em><?php echo esc_html( $column['Type'] ); ?></em></span>
					<textarea name="values[<?php echo esc_attr( $field ); ?>]" rows="<?php echo strlen( (string) ( $row[ $field ] ?? '' ) ) > 80 ? '3' : '1'; ?>" <?php disabled( $field, $primary_key ); ?>><?php echo esc_textarea( (string) ( $row[ $field ] ?? '' ) ); ?></textarea>
				</label>
			<?php endforeach; ?>
			<div class="sitx-db-editor-actions">
				<button type="submit" class="sitx-btn sitx-btn--primary si-button si-button--primary"><?php esc_html_e( 'Save', 'siteintelix' ); ?></button>
				<a class="sitx-btn sitx-btn--white si-button si-button--secondary" href="<?php echo esc_url( self::get_table_url( $table, $page, $search ) ); ?>"><?php esc_html_e( 'Cancel', 'siteintelix' ); ?></a>
				<a class="sitx-btn sitx-btn--danger si-button si-button--danger" href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'siteintelix_db_delete_row', 'table' => $table, 'primary_key' => $primary_key, 'primary_value' => $selected_id, 'db_page' => absint( $page ), 'row_search' => $search ), admin_url( 'admin-post.php' ) ), 'siteintelix_db_delete_row_' . $table . '_' . $selected_id ) ); ?>" data-siteintelix-confirm="<?php esc_attr_e( 'Delete this database row?', 'siteintelix' ); ?>"><?php esc_html_e( 'Delete', 'siteintelix' ); ?></a>
			</div>
		</form>
		<?php
	}

	/**
	 * Handle row update.
	 *
	 * @return void
	 */
	public static function handle_update_row() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to update database rows.', 'siteintelix' ) );
		}

		$table         = isset( $_POST['table'] ) ? sanitize_text_field( wp_unslash( $_POST['table'] ) ) : '';
		$primary_key   = isset( $_POST['primary_key'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_key'] ) ) : '';
		$primary_value = isset( $_POST['primary_value'] ) ? sanitize_text_field( wp_unslash( $_POST['primary_value'] ) ) : '';
		$page          = isset( $_POST['db_page'] ) ? max( 1, absint( wp_unslash( $_POST['db_page'] ) ) ) : 1;
		$row_search    = isset( $_POST['row_search'] ) ? sanitize_text_field( wp_unslash( $_POST['row_search'] ) ) : '';
		check_admin_referer( 'siteintelix_db_update_row_' . $table . '_' . $primary_value );

		$columns = self::get_columns( $table );
		if ( ! self::is_allowed_table( $table ) || $primary_key !== self::get_primary_key( $columns ) ) {
			wp_die( esc_html__( 'Invalid database table or primary key.', 'siteintelix' ) );
		}

		$allowed_columns = wp_list_pluck( $columns, 'Field' );
		$raw_values      = isset( $_POST['values'] ) && is_array( $_POST['values'] ) ? wp_unslash( $_POST['values'] ) : array();
		$data            = array();

		foreach ( $raw_values as $column => $value ) {
			$column = sanitize_text_field( $column );
			if ( $column === $primary_key || ! in_array( $column, $allowed_columns, true ) ) {
				continue;
			}
			$data[ $column ] = is_scalar( $value ) ? str_replace( "\0", '', wp_check_invalid_utf8( (string) $value ) ) : '';
		}

		if ( ! empty( $data ) ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-requested update to selected whitelisted table/columns.
			$result = $wpdb->update( $table, $data, array( $primary_key => $primary_value ), array_fill( 0, count( $data ), '%s' ), array( '%s' ) );
			if ( false === $result ) {
				self::redirect_with_error( $table, $primary_value, $wpdb->last_error, $page, $row_search );
			}
		}

		wp_safe_redirect( add_query_arg( 'siteintelix_db_saved', '1', self::get_edit_row_url( $table, $primary_key, $primary_value, $page, $row_search ) ) );
		exit;
	}

	/**
	 * Handle row deletion.
	 *
	 * @return void
	 */
	public static function handle_delete_row() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to delete database rows.', 'siteintelix' ) );
		}

		$table         = isset( $_GET['table'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['table'] ) ) ) : '';
		$primary_key   = isset( $_GET['primary_key'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['primary_key'] ) ) ) : '';
		$primary_value = isset( $_GET['primary_value'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['primary_value'] ) ) ) : '';
		$page          = isset( $_GET['db_page'] ) ? max( 1, absint( wp_unslash( $_GET['db_page'] ) ) ) : 1;
		$row_search    = isset( $_GET['row_search'] ) ? sanitize_text_field( wp_unslash( $_GET['row_search'] ) ) : '';
		check_admin_referer( 'siteintelix_db_delete_row_' . $table . '_' . $primary_value );

		$columns = self::get_columns( $table );
		if ( ! self::is_allowed_table( $table ) || $primary_key !== self::get_primary_key( $columns ) ) {
			wp_die( esc_html__( 'Invalid database table or primary key.', 'siteintelix' ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-requested delete from selected whitelisted table.
		$result = $wpdb->delete( $table, array( $primary_key => $primary_value ), array( '%s' ) );
		if ( false === $result ) {
			self::redirect_with_error( $table, $primary_value, $wpdb->last_error, $page, $row_search );
		}

		wp_safe_redirect( add_query_arg( 'siteintelix_db_deleted', '1', self::get_table_url( $table, $page, $row_search ) ) );
		exit;
	}

	/**
	 * Get database tables with stats.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function get_tables() {
		global $wpdb;

		static $tables_cache = null;
		if ( null !== $tables_cache ) {
			return $tables_cache;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin database inspection.
		$rows   = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$tables = array();

		foreach ( (array) $rows as $row ) {
			$name = isset( $row['Name'] ) ? (string) $row['Name'] : '';
			if ( '' === $name ) {
				continue;
			}

			$tables[ $name ] = array(
				'name'         => $name,
				'rows'         => isset( $row['Rows'] ) ? absint( $row['Rows'] ) : 0,
				'data_length'  => isset( $row['Data_length'] ) ? absint( $row['Data_length'] ) : 0,
				'index_length' => isset( $row['Index_length'] ) ? absint( $row['Index_length'] ) : 0,
			);
		}

		ksort( $tables );
		$tables_cache = $tables;
		return $tables;
	}

	/**
	 * Get columns for a table.
	 *
	 * @param string $table Table name.
	 * @return array<int,array<string,string>>
	 */
	private static function get_columns( $table ) {
		global $wpdb;

		if ( ! self::is_allowed_table( $table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table identifier is whitelisted before query.
		return (array) $wpdb->get_results( 'DESCRIBE ' . self::identifier( $table ), ARRAY_A );
	}

	/**
	 * Count table rows.
	 *
	 * @param string           $table   Table name.
	 * @param array<int,array> $columns Columns.
	 * @param string           $search  Search term.
	 * @return int
	 */
	private static function count_rows( $table, $columns, $search ) {
		global $wpdb;
		$where = self::build_search_where( $columns, $search );

		$sql = 'SELECT COUNT(*) FROM ' . self::identifier( $table ) . $where['sql'];
		if ( ! empty( $where['args'] ) ) {
			$sql = $wpdb->prepare( $sql, $where['args'] );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table/columns are whitelisted before query.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Get table rows.
	 *
	 * @param string           $table   Table name.
	 * @param array<int,array> $columns Columns.
	 * @param string           $search  Search term.
	 * @param int              $page    Page number.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_table_rows( $table, $columns, $search, $page ) {
		global $wpdb;
		$where  = self::build_search_where( $columns, $search );
		$offset = max( 0, ( absint( $page ) - 1 ) * self::PER_PAGE );
		$sql    = 'SELECT * FROM ' . self::identifier( $table ) . $where['sql'] . ' LIMIT %d OFFSET %d';
		$args   = array_merge( $where['args'], array( self::PER_PAGE, $offset ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table/columns are whitelisted before query.
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/**
	 * Get a row by primary key.
	 *
	 * @param string $table       Table name.
	 * @param string $primary_key Primary key.
	 * @param string $value       Primary key value.
	 * @return array<string,mixed>|null
	 */
	private static function get_row_by_primary_key( $table, $primary_key, $value ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::identifier( $table ) . ' WHERE ' . self::identifier( $primary_key ) . ' = %s LIMIT 1';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table/column identifiers are whitelisted before query.
		$row = $wpdb->get_row( $wpdb->prepare( $sql, $value ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Build search WHERE clause.
	 *
	 * @param array<int,array> $columns Columns.
	 * @param string           $search  Search term.
	 * @return array{sql:string,args:array<int,string>}
	 */
	private static function build_search_where( $columns, $search ) {
		global $wpdb;
		$search = trim( $search );

		if ( '' === $search || empty( $columns ) ) {
			return array( 'sql' => '', 'args' => array() );
		}

		$likes = array();
		$args  = array();
		$like  = '%' . $wpdb->esc_like( $search ) . '%';

		foreach ( $columns as $column ) {
			$field   = isset( $column['Field'] ) ? (string) $column['Field'] : '';
			$type    = isset( $column['Type'] ) ? strtolower( (string) $column['Type'] ) : '';
			$is_text = preg_match( '/char|text|blob|enum|set|json|date|time|int|decimal|float|double/', $type );
			if ( $field && $is_text ) {
				$likes[] = 'CAST(' . self::identifier( $field ) . ' AS CHAR) LIKE %s';
				$args[]  = $like;
			}
		}

		return empty( $likes ) ? array( 'sql' => '', 'args' => array() ) : array( 'sql' => ' WHERE ' . implode( ' OR ', $likes ), 'args' => $args );
	}

	/**
	 * Render row pagination.
	 *
	 * @param string $table      Table name.
	 * @param int    $page       Current page.
	 * @param int    $total_rows Total rows.
	 * @param string $search     Search term.
	 * @return void
	 */
	private static function render_row_pagination( $table, $page, $total_rows, $search ) {
		$total_pages = max( 1, (int) ceil( $total_rows / self::PER_PAGE ) );
		$start       = $total_rows > 0 ? ( ( $page - 1 ) * self::PER_PAGE ) + 1 : 0;
		$end         = min( $total_rows, $page * self::PER_PAGE );
		$base        = admin_url( 'admin.php?page=siteintelix-database-manager&view=tables&table=' . rawurlencode( $table ) . '&row_search=' . rawurlencode( $search ) );
		?>
		<div class="sitx-db-pagination">
			<span><?php echo esc_html( sprintf( __( '%1$d-%2$d of %3$d rows', 'siteintelix' ), $start, $end, $total_rows ) ); ?></span>
			<div>
				<a class="sitx-btn sitx-btn--white sitx-btn--icon <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'db_page', 1, $base ) ); ?>"><span class="dashicons dashicons-controls-skipback"></span></a>
				<a class="sitx-btn sitx-btn--white sitx-btn--icon <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'db_page', max( 1, $page - 1 ), $base ) ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></a>
				<span class="sitx-db-page-number"><?php echo esc_html( (string) $page ); ?></span>
				<a class="sitx-btn sitx-btn--white sitx-btn--icon <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'db_page', min( $total_pages, $page + 1 ), $base ) ); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></a>
				<a class="sitx-btn sitx-btn--white sitx-btn--icon <?php echo $page >= $total_pages ? 'is-disabled' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'db_page', $total_pages, $base ) ); ?>"><span class="dashicons dashicons-controls-skipforward"></span></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Redirect back to the Database Manager with an error message.
	 *
	 * @param string $table    Table name.
	 * @param string $row_id   Row ID.
	 * @param string $message  Error message.
	 * @param int    $page     Return page number.
	 * @param string $search   Return row search.
	 * @return void
	 */
	private static function redirect_with_error( $table, $row_id, $message, $page = 1, $search = '' ) {
		$args = array(
			'page'                 => 'siteintelix-database-manager',
			'view'                 => '' !== $row_id ? 'edit' : 'tables',
			'table'                => $table,
			'db_page'              => max( 1, absint( $page ) ),
			'siteintelix_db_error' => $message ? $message : __( 'WordPress could not complete the database action.', 'siteintelix' ),
		);

		if ( '' !== $search ) {
			$args['row_search'] = $search;
		}

		if ( '' !== $row_id ) {
			$args['row_id'] = $row_id;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Build table rows URL.
	 *
	 * @param string $table  Table.
	 * @param int    $page   Page.
	 * @param string $search Search.
	 * @return string
	 */
	private static function get_table_url( $table, $page = 1, $search = '' ) {
		$args = array(
			'page'    => 'siteintelix-database-manager',
			'view'    => 'tables',
			'table'   => $table,
			'db_page' => max( 1, absint( $page ) ),
		);

		if ( '' !== $search ) {
			$args['row_search'] = $search;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Build row edit URL.
	 *
	 * @param string $table       Table.
	 * @param string $primary_key Primary key.
	 * @param string $row_id      Row ID.
	 * @param int    $page        Page.
	 * @param string $search      Search.
	 * @return string
	 */
	private static function get_edit_row_url( $table, $primary_key, $row_id, $page, $search ) {
		if ( ! $primary_key || '' === $row_id ) {
			return self::get_table_url( $table, $page, $search );
		}

		$args = array(
			'page'       => 'siteintelix-database-manager',
			'view'       => 'edit',
			'table'      => $table,
			'db_page'    => max( 1, absint( $page ) ),
			'row_id'     => $row_id,
			'primary_key' => $primary_key,
		);

		if ( '' !== $search ) {
			$args['row_search'] = $search;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Check table name against real database tables.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function is_allowed_table( $table ) {
		$tables = self::get_tables();
		return isset( $tables[ $table ] );
	}

	/**
	 * Find primary key column.
	 *
	 * @param array<int,array> $columns Columns.
	 * @return string
	 */
	private static function get_primary_key( $columns ) {
		foreach ( $columns as $column ) {
			if ( isset( $column['Key'], $column['Field'] ) && 'PRI' === $column['Key'] ) {
				return (string) $column['Field'];
			}
		}

		return '';
	}

	/**
	 * Escape SQL identifier after whitelist validation.
	 *
	 * @param string $identifier Identifier.
	 * @return string
	 */
	private static function identifier( $identifier ) {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Format a cell value for display.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	private static function format_cell( $value ) {
		if ( null === $value ) {
			return 'NULL';
		}

		$value = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		$value = preg_replace( '/\s+/', ' ', $value );
		return strlen( $value ) > 120 ? substr( $value, 0, 117 ) . '…' : $value;
	}

	/**
	 * Convert bytes to MB string.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	private static function bytes_to_mb( $bytes ) {
		return number_format_i18n( $bytes / 1048576, 2 );
	}
}
