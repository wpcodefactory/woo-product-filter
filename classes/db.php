<?php
/**
 * Product Filter by WBW - DbWpf Class
 *
 * Shell - class to work with $wpdb global object.
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class DbWpf {

	/**
	 * Execute query and return results.
	 *
	 * @param string $query      query to be executed
	 * @param string $get        what must be returned - one value (one), one row (row), one col (col) or all results (all - by default)
	 * @param const  $outputType type of returned data
	 *
	 * @return mixed data from DB
	 */
	public static $query = '';

	/**
	 * get.
	 *
	 * @version 3.3.0
	 */
	public static function get( $query, $get = 'all', $outputType = ARRAY_A, $args = array()  ) {
		global $wpdb;
		$get         = strtolower( $get );
		$query       = self::prepareQuery( $query );
		self::$query = $query;

		if ( ! is_array( $args ) ) {
			$args = array( $args );
		}

		$wpdb->wpf_prepared_query = ! empty( $args ) ?
			$wpdb->prepare( $query, $args ) : // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query;

		switch ( $get ) {
			case 'one':
				$res = $wpdb->get_var( $wpdb->wpf_prepared_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
			case 'row':
				$res = $wpdb->get_row( $wpdb->wpf_prepared_query, $outputType ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
			case 'col':
				$res = $wpdb->get_col( $wpdb->wpf_prepared_query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
			case 'all':
			default:
				$res = $wpdb->get_results( $wpdb->wpf_prepared_query, $outputType ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
		}
		return empty( $wpdb->last_error ) ? $res : false;
	}

	/**
	 * Execute one query.
	 *
	 * @version 3.3.0
	 */
	public static function query( $query, $affected = false, $args = array() ) {
		global $wpdb;

		$query = self::prepareQuery( $query );
		if ( ! is_array( $args ) ) {
			$args = array( $args );
		}

		$wpdb->wpf_prepared_query = ! empty( $args ) ? $wpdb->prepare( $query, $args ) : $query;

		return (
		$affected ?
			$wpdb->query( $wpdb->wpf_prepared_query ) : // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			( $wpdb->query( $wpdb->wpf_prepared_query ) === false ? false : true )  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		);
	}

	/**
	 * sanitizeIdentifier.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 */
	public static function sanitizeIdentifier( $name ) {
		return esc_sql( preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $name ) );
	}

	/**
	 * Replace prefixes in custom query. Supported next prefixes:
	 * #__  WordPress prefix
	 * ^__  Store plugin tables prefix (@see WPF_DB_PREF if config.php)
	 *
	 * @__  Compared of WP table prefix + Store plugin prefix (@example wp_s_)
	 * @param string $query query to be executed
	 */
	public static function prepareQuery( $query ) {
		global $wpdb;
		return str_replace(
			array( '#__', '^__', '@__' ),
			array( $wpdb->prefix, WPF_DB_PREF, $wpdb->prefix . WPF_DB_PREF ),
			$query
		);
	}

	/**
	 * getTableName.
	 *
	 * @version 3.3.0
	 */
	public static function getTableName( $name ) {
		global $wpdb;
		return $wpdb->prefix . WPF_DB_PREF . self::sanitizeIdentifier( $name );
	}

	public static function getError() {
		global $wpdb;
		return $wpdb->last_error;
	}

	public static function lastID() {
		global $wpdb;
		return $wpdb->insert_id;
	}

	/**
	 * timeToDate.
	 */
	public static function timeToDate( $timestamp = 0 ) {
		if ( $timestamp ) {
			if ( ! is_numeric( $timestamp ) ) {
				$timestamp = dateToTimestampWpf( $timestamp );
			}
			return gmdate( 'Y-m-d', $timestamp );
		} else {
			return gmdate( 'Y-m-d' );
		}
	}

	/**
	 * dateToTime.
	 */
	public static function dateToTime( $date ) {
		if ( empty( $date ) ) {
			return '';
		}
		if ( strpos( $date, WPF_DATE_DL ) ) {
			return dateToTimestampWpf( $date );
		}
		$arr = explode( '-', $date );
		return dateToTimestampWpf( $arr[2] . WPF_DATE_DL . $arr[1] . WPF_DATE_DL . $arr[0] );
	}

	/**
	 * exist.
	 *
	 * @version 3.3.0
	 */
	public static function exist( $table, $column = '', $value = '' ) {
		$table = self::prepareQuery( $table );
		$table = self::sanitizeIdentifier( $table );
		if ( empty( $column ) && empty( $value ) ) { // Check if table exist
			$res = self::get( 'SHOW TABLES LIKE "' . $table . '"', 'one' );
		} elseif ( empty( $value ) ) { // Check if column exist
			$column = self::sanitizeIdentifier( $column );
			$res    = self::get( 'SHOW COLUMNS FROM ' . $table . ' LIKE "' . $column . '"', 'one' );
		} else { // Check if value in column table exist
			$column = self::sanitizeIdentifier( $column );
			$value  = esc_sql( $value );
			$res    = self::get( 'SELECT COUNT(*) AS total FROM ' . $table . ' WHERE ' . $column . ' = "' . $value . '"', 'one' );
		}

		return ! empty( $res );
	}

	public static function escape( $data ) {
		global $wpdb;
		return $wpdb->_escape( $data );
	}

	/**
	 * existsTableColumn.
	 *
	 * @version 3.3.0
	 */
	public static function existsTableColumn( $table, $column ) {
		$table = self::sanitizeIdentifier( self::prepareQuery( $table ) );

		return self::get(
				"SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name=%s AND table_schema=DATABASE() AND column_name=%s",
				'one',
				ARRAY_A,
				array( $table, $column )
			) == 1;
	}
}
