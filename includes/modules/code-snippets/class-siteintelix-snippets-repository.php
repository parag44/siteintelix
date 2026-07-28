<?php
/**
 * Persistence for PHP snippets.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Snippets_Repository {
	const SCHEMA_VERSION = '1.0.0';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'siteintelix_snippets';
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			code longtext NOT NULL,
			description text NOT NULL,
			scope varchar(12) NOT NULL DEFAULT 'everywhere',
			priority int(11) NOT NULL DEFAULT 10,
			status varchar(16) NOT NULL DEFAULT 'inactive',
			tags text NOT NULL,
			error_message text NOT NULL,
			error_file varchar(255) NOT NULL DEFAULT '',
			error_line int(11) unsigned NOT NULL DEFAULT 0,
			error_count int(11) unsigned NOT NULL DEFAULT 0,
			deactivated_at datetime NULL,
			last_run_at datetime NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_scope (status, scope),
			KEY priority (priority),
			KEY deactivated_at (deactivated_at),
			KEY updated_at (updated_at)
		) {$wpdb->get_charset_collate()};";
		dbDelta( $sql );
		update_option( 'siteintelix_snippets_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== get_option( 'siteintelix_snippets_schema_version' ) ) {
			self::install_schema();
		}
	}

	/**
	 * Normalize raw WordPress request data exactly once.
	 *
	 * @param array<string,mixed> $input Raw, slashed request data.
	 * @return array<string,mixed>
	 */
	public static function normalize_request( $input ) {
		return self::prepare_entry( wp_unslash( $input ) );
	}

	/**
	 * Sanitize canonical, already-unslashed snippet data for persistence.
	 *
	 * @param array<string,mixed> $input    Canonical input.
	 * @param array<string,mixed> $existing Existing canonical row.
	 * @return array<string,mixed>
	 */
	public static function prepare_entry( $input, $existing = array() ) {
		$scope  = sanitize_key( $input['scope'] ?? ( $existing['scope'] ?? 'everywhere' ) );
		$status = sanitize_key( $input['status'] ?? ( $existing['status'] ?? 'inactive' ) );
		return array(
			'name'        => sanitize_text_field( $input['name'] ?? ( $existing['name'] ?? '' ) ),
			'code'        => (string) ( $input['code'] ?? ( $existing['code'] ?? '' ) ),
			'description' => sanitize_textarea_field( $input['description'] ?? ( $existing['description'] ?? '' ) ),
			'scope'       => in_array( $scope, array( 'everywhere', 'frontend', 'admin' ), true ) ? $scope : 'everywhere',
			'priority'    => max( -999, min( 999, intval( $input['priority'] ?? ( $existing['priority'] ?? 10 ) ) ) ),
			'status'      => in_array( $status, array( 'active', 'inactive', 'running_once' ), true ) ? $status : 'inactive',
			'tags'        => implode( ',', array_filter( array_map( 'sanitize_key', preg_split( '/[\s,]+/', (string) ( $input['tags'] ?? ( $existing['tags'] ?? '' ) ) ) ) ) ),
		);
	}

	/**
	 * Backward-compatible canonical normalization alias.
	 *
	 * @param array<string,mixed> $input    Canonical input.
	 * @param array<string,mixed> $existing Existing canonical row.
	 * @return array<string,mixed>
	 */
	public static function normalize( $input, $existing = array() ) {
		return self::prepare_entry( $input, $existing );
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	public static function insert( $input ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$data = array_merge( self::prepare_entry( $input ), array(
			'error_message' => '', 'error_file' => '', 'error_line' => 0, 'error_count' => 0,
			'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now,
		) );
		$wpdb->insert( self::table_name(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, $input ) {
		global $wpdb;
		$old = self::get( $id );
		if ( ! $old ) return false;
		$data = self::prepare_entry( $input, $old );
		$data['updated_at'] = current_time( 'mysql', true );
		return false !== $wpdb->update( self::table_name(), $data, array( 'id' => absint( $id ) ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function set_status( $ids, $status ) {
		global $wpdb;
		$status = in_array( $status, array( 'active', 'inactive', 'running_once' ), true ) ? $status : 'inactive';
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		foreach ( $ids as $id ) {
			$data = array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) );
			if ( 'inactive' === $status ) $data['deactivated_at'] = current_time( 'mysql', true );
			if ( 'active' === $status ) $data['deactivated_at'] = null;
			$wpdb->update( self::table_name(), $data, array( 'id' => $id ) );
		}
		return count( $ids );
	}

	public static function duplicate( $id ) {
		$item = self::get( $id );
		if ( ! $item ) return 0;
		$item['name'] = sprintf( __( '%s (Copy)', 'siteintelix' ), $item['name'] );
		$item['status'] = 'inactive';
		return self::insert( $item );
	}

	public static function active_for_scope( $scope ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE status = %s AND (scope = %s OR scope = %s) ORDER BY priority ASC, id ASC', 'active', $scope, 'everywhere' ), ARRAY_A );
	}

	public static function record_error( $id, $error ) {
		global $wpdb;
		$file = (string) $error->getFile();
		$file = 0 === strpos( $file, ABSPATH ) ? ltrim( substr( $file, strlen( ABSPATH ) ), '/\\' ) : basename( $file );
		$message = preg_replace( '#(?:[A-Za-z]:)?[/\\\\][^\s:]+#', '[path]', (string) $error->getMessage() );
		$message = function_exists( 'mb_substr' ) ? mb_substr( $message, 0, 500 ) : substr( $message, 0, 500 );
		$file    = function_exists( 'mb_substr' ) ? mb_substr( $file, 0, 255 ) : substr( $file, 0, 255 );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . ' SET status=%s, deactivated_at=%s, error_message=%s, error_file=%s, error_line=%d, error_count=error_count+1, updated_at=%s WHERE id=%d', 'inactive', current_time( 'mysql', true ), $message, $file, absint( $error->getLine() ), current_time( 'mysql', true ), absint( $id ) ) );
	}

	public static function mark_last_run( $id ) {
		global $wpdb;
		return $wpdb->update( self::table_name(), array( 'last_run_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $id ) ) );
	}

	public static function list_items( $args = array() ) {
		global $wpdb;
		$page = max( 1, absint( $args['page'] ?? 1 ) ); $per_page = 20; $where = array( '1=1' ); $values = array();
		if ( ! empty( $args['status'] ) ) { $where[]='status=%s'; $values[]=sanitize_key( $args['status'] ); }
		if ( ! empty( $args['scope'] ) ) { $where[]='scope=%s'; $values[]=sanitize_key( $args['scope'] ); }
		if ( ! empty( $args['recent'] ) ) { $where[]='deactivated_at >= %s'; $values[]=gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ); }
		if ( ! empty( $args['search'] ) ) { $like='%'.$wpdb->esc_like( sanitize_text_field( $args['search'] ) ).'%'; $where[]='(name LIKE %s OR description LIKE %s)'; $values[]=$like; $values[]=$like; }
		$base=' FROM '.self::table_name().' WHERE '.implode(' AND ',$where);
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and where fragments are generated from fixed internal allow-lists.
		$total=(int)( $values ? $wpdb->get_var($wpdb->prepare('SELECT COUNT(*)'.$base,$values)) : $wpdb->get_var('SELECT COUNT(*)'.$base) );
		$query='SELECT *'.$base.' ORDER BY updated_at DESC,id DESC LIMIT %d OFFSET %d';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is fixed and all request values are prepared.
		$rows=$wpdb->get_results($wpdb->prepare($query,array_merge($values,array($per_page,($page-1)*$per_page))),ARRAY_A);
		return array( $rows, $total );
	}
}
