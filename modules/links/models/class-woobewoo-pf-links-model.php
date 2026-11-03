<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Links_Model Class
 *
 * Short shareable links: table `wpf_short_links` (slug, filter_id, params JSON, base, hits, created).
 * All writes go through $wpdb->insert / $wpdb->update.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Links_Model extends WooBeWoo_PF_Model {

	public function table() {
		global $wpdb;
		return $wpdb->prefix . WPF_DB_PREF . 'short_links';
	}

	/**
	 * Row by slug or null.
	 */
	public function get( $slug ) {
		global $wpdb;
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM `' . esc_sql( $this->table() ) . '` WHERE slug = %s', $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $row ) {
			return null;
		}
		$row['params'] = json_decode( (string) $row['params'], true );
		if ( ! is_array( $row['params'] ) ) {
			$row['params'] = array();
		}
		return $row;
	}

	/**
	 * Create (or reuse) a short link for a base URL and canonical params.
	 *
	 * @return array|null row
	 */
	public function create( $filterId, $params, $base ) {
		global $wpdb;
		$filterId = (int) $filterId;
		$params   = $this->normalize( $params );
		$json     = wp_json_encode( $params );
		$base     = esc_url_raw( (string) $base );
		if ( empty( $params ) ) {
			return null;
		}
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT slug FROM `' . esc_sql( $this->table() ) . '` WHERE filter_id = %d AND base = %s AND params = %s LIMIT 1', $filterId, $base, $json ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $existing ) {
			return $this->get( $existing['slug'] );
		}
		$slug = $this->uniqueSlug( $this->readableSlug( $params, $base ) );
		$ok   = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->table(),
			array(
				'slug'      => $slug,
				'filter_id' => $filterId,
				'params'    => $json,
				'base'      => $base,
				'hits'      => 0,
				'created'   => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%d', '%s' )
		);
		return $ok ? $this->get( $slug ) : null;
	}

	public function hit( $slug ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE `' . esc_sql( $this->table() ) . '` SET hits = hits + 1 WHERE slug = %s', sanitize_title( $slug ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM `' . esc_sql( $this->table() ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Canonical params: strings or arrays of strings, keys sanitized, sorted for stable JSON.
	 */
	public function normalize( $params ) {
		$out = array();
		foreach ( (array) $params as $k => $v ) {
			$k = sanitize_key( $k );
			if ( '' === $k ) {
				continue;
			}
			if ( is_array( $v ) ) {
				$v = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $v ) ), 'strlen' ) );
				if ( empty( $v ) ) {
					continue;
				}
				sort( $v );
				$out[ $k ] = $v;
			} else {
				$v = sanitize_text_field( (string) $v );
				if ( '' === $v ) {
					continue;
				}
				$out[ $k ] = $v;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * "red-shoes-42" from the readable names of the picked terms.
	 */
	public function readableSlug( $params, $base = '' ) {
		$words = array();
		$tax   = WooBeWoo_PF_Frame::_()->getModule( 'links' )->taxonomyForKey();
		// Readable order: attribute values, then category, brand, tag, then price and flags ("red-shoes-42").
		$later   = array( 'category', 'brand', 'tag', 'author', 'price', 'stock', 'sale', 'featured', 'rating' );
		$ordered = array();
		foreach ( $params as $k => $v ) {
			if ( ! in_array( $k, $later, true ) ) {
				$ordered[ $k ] = $v;
			}
		}
		foreach ( $later as $k ) {
			if ( isset( $params[ $k ] ) ) {
				$ordered[ $k ] = $params[ $k ];
			}
		}
		foreach ( $ordered as $k => $v ) {
			if ( in_array( $k, array( 'page', 'sort', 'q' ), true ) ) {
				continue;
			}
			$vals = is_array( $v ) ? $v : array( $v );
			if ( isset( $tax[ $k ] ) ) {
				foreach ( array_slice( $vals, 0, 3 ) as $slug ) {
					$t = get_term_by( 'slug', $slug, $tax[ $k ] );
					$words[] = $t && ! is_wp_error( $t ) ? $t->name : $slug;
				}
			} elseif ( 'price' === $k ) {
				$words[] = str_replace( '-', '-to-', (string) $v );
			} elseif ( in_array( $k, array( 'sale', 'featured' ), true ) ) {
				$words[] = $k;
			} else {
				$words[] = $k . '-' . implode( '-', array_slice( $vals, 0, 3 ) );
			}
		}
		// Taxonomy words first: attribute values, then category, brand, tag already in key order; keep as is.
		$slug = sanitize_title( implode( '-', $words ) );
		$slug = substr( $slug, 0, 60 );
		$slug = trim( $slug, '-' );
		if ( '' === $slug ) {
			$slug = 'filter';
		}
		// Reserved words that would clash with the rewrite rule or the base.
		if ( in_array( $slug, array( 'f', 'page', 'feed', 'embed' ), true ) ) {
			$slug .= '-link';
		}
		return $slug;
	}

	protected function uniqueSlug( $slug ) {
		global $wpdb;
		$try = $slug;
		$n   = 2;
		while ( $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM `' . esc_sql( $this->table() ) . '` WHERE slug = %s', $try ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$try = substr( $slug, 0, 60 - strlen( '-' . $n ) ) . '-' . $n;
			$n++;
			if ( $n > 500 ) {
				$try = $slug . '-' . wp_generate_password( 6, false, false );
				break;
			}
		}
		return $try;
	}
}
