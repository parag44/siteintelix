<?php
/**
 * Persistence for Custom CSS & JS entries.
 *
 * @package SiteIntelix
 */

defined( 'ABSPATH' ) || exit;

class SITEINTELIX_Custom_Code_Repository {
	const SCHEMA_VERSION = '1.0.0';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'siteintelix_custom_code';
	}

	public static function install_schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$sql   = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL,
			code longtext NOT NULL,
			code_type varchar(12) NOT NULL,
			scope varchar(12) NOT NULL,
			location varchar(10) NOT NULL,
			loading_method varchar(10) NOT NULL,
			priority int(11) NOT NULL DEFAULT 10,
			status varchar(10) NOT NULL DEFAULT 'disabled',
			description text NOT NULL,
			generated_file varchar(255) NOT NULL DEFAULT '',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status_type (status, code_type),
			KEY scope_location (scope, location),
			KEY priority (priority),
			KEY updated_at (updated_at)
		) {$wpdb->get_charset_collate()};";
		dbDelta( $sql );
		update_option( 'siteintelix_custom_code_schema_version', self::SCHEMA_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION !== get_option( 'siteintelix_custom_code_schema_version' ) ) {
			self::install_schema();
		}
	}

	public static function normalize_entry( $input, $existing = array() ) {
		$pick = static function ( $key, $allowed, $default ) use ( $input, $existing ) {
			$value = isset( $input[ $key ] ) ? sanitize_key( wp_unslash( $input[ $key ] ) ) : ( $existing[ $key ] ?? $default );
			return in_array( $value, $allowed, true ) ? $value : $default;
		};
		return array(
			'title'          => sanitize_text_field( wp_unslash( $input['title'] ?? ( $existing['title'] ?? '' ) ) ),
			'code'           => wp_unslash( $input['code'] ?? ( $existing['code'] ?? '' ) ),
			'code_type'      => $pick( 'code_type', array( 'css', 'javascript' ), 'css' ),
			'scope'          => $pick( 'scope', array( 'frontend', 'admin', 'both' ), 'frontend' ),
			'location'       => $pick( 'location', array( 'header', 'footer' ), 'header' ),
			'loading_method' => $pick( 'loading_method', array( 'inline', 'external' ), 'inline' ),
			'priority'       => max( -999, min( 999, intval( $input['priority'] ?? ( $existing['priority'] ?? 10 ) ) ) ),
			'status'         => $pick( 'status', array( 'enabled', 'disabled' ), 'disabled' ),
			'description'    => sanitize_textarea_field( wp_unslash( $input['description'] ?? ( $existing['description'] ?? '' ) ) ),
		);
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE id = %d', absint( $id ) ), ARRAY_A );
	}

	public static function insert( $entry ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$data = array_merge( self::normalize_entry( $entry ), array(
			'generated_file' => sanitize_text_field( $entry['generated_file'] ?? '' ),
			'created_by'     => get_current_user_id(),
			'created_at'     => $now,
			'updated_at'     => $now,
		) );
		$wpdb->insert( self::table_name(), $data );
		return (int) $wpdb->insert_id;
	}

	public static function update( $id, $entry ) {
		global $wpdb;
		$existing = self::get( $id );
		if ( ! $existing ) {
			return false;
		}
		$data               = self::normalize_entry( $entry, $existing );
		$data['updated_at'] = current_time( 'mysql', true );
		if ( array_key_exists( 'generated_file', $entry ) ) {
			$data['generated_file'] = sanitize_text_field( $entry['generated_file'] );
		}
		return false !== $wpdb->update( self::table_name(), $data, array( 'id' => absint( $id ) ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table_name(), array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	public static function duplicate( $id, $user_id ) {
		$entry = self::get( $id );
		if ( ! $entry ) {
			return 0;
		}
		$entry['title']          = sprintf( __( '%s (Copy)', 'siteintelix' ), $entry['title'] );
		$entry['status']         = 'disabled';
		$entry['generated_file'] = '';
		unset( $entry['id'], $entry['created_at'], $entry['updated_at'] );
		$new_id = self::insert( $entry );
		global $wpdb;
		$wpdb->update( self::table_name(), array( 'created_by' => absint( $user_id ) ), array( 'id' => $new_id ) );
		return $new_id;
	}

	public static function set_status( $ids, $status ) {
		global $wpdb;
		$status = in_array( $status, array( 'enabled', 'disabled' ), true ) ? $status : 'disabled';
		$ids    = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$query        = $wpdb->prepare( 'UPDATE ' . self::table_name() . " SET status = %s, updated_at = %s WHERE id IN ({$placeholders})", array_merge( array( $status, current_time( 'mysql', true ) ), $ids ) );
		return $wpdb->query( $query );
	}

	private static function where_sql( $args, &$values ) {
		$where = array( '1=1' );
		foreach ( array( 'status', 'code_type', 'scope' ) as $key ) {
			if ( ! empty( $args[ $key ] ) ) {
				$where[]  = "{$key} = %s";
				$values[] = sanitize_key( $args[ $key ] );
			}
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[]  = '(title LIKE %s OR description LIKE %s)';
			$values[] = $like;
			$values[] = $like;
		}
		return implode( ' AND ', $where );
	}

	public static function list_entries( $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args( $args, array( 'page' => 1, 'per_page' => 20 ) );
		$values = array();
		$where  = self::where_sql( $args, $values );
		$values[] = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$values[] = ( max( 1, absint( $args['page'] ) ) - 1 ) * end( $values );
		$sql = 'SELECT * FROM ' . self::table_name() . " WHERE {$where} ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d";
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a fixed plugin-owned suffix and every request value is prepared.
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
	}

	public static function count_entries( $args = array() ) {
		global $wpdb;
		$values = array();
		$where  = self::where_sql( $args, $values );
		$sql    = 'SELECT COUNT(*) FROM ' . self::table_name() . " WHERE {$where}";
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and where fragments are generated from fixed internal allow-lists.
		return (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $sql, $values ) ) : $wpdb->get_var( $sql ) );
	}

	public static function get_enabled_for( $scope, $location, $type ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table_name() . ' WHERE status = %s AND code_type = %s AND location = %s AND (scope = %s OR scope = %s) ORDER BY priority ASC, id ASC';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a fixed plugin-owned suffix and every request value is prepared.
		return $wpdb->get_results( $wpdb->prepare( $sql, 'enabled', $type, $location, $scope, 'both' ), ARRAY_A );
	}
}
