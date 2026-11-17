<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Setup_Model Class
 *
 * Smart setup: catalogue scan, store type detection, theme and builder detection,
 * suggestion builder, style mapping, drafts for the live preview and publishing.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Setup_Model extends WooBeWoo_PF_Model {

	const SCAN_TRANSIENT   = 'wpf_setup_scan';
	const SCAN_TTL         = 12 * HOUR_IN_SECONDS;
	const SCAN_STATE       = 'wpf_setup_scan_state';
	const SCAN_STATE_TTL   = 2 * HOUR_IN_SECONDS;
	const SCAN_BATCH       = 250;  // products per database round trip; never more in one batch
	const SCAN_BUDGET      = 20;   // seconds of work per AJAX request, then progress is returned
	const SCAN_INLINE_MAX  = 5000; // kept for the WP-CLI / cron path (runScanBatchEvent)
	const SCAN_EVENT       = 'wpf_setup_scan_batch';
	const DRAFT_PREFIX     = 'wpf_setup_draft_';
	const DRAFT_TTL        = 2 * HOUR_IN_SECONDS;
	const STATE_OPTION     = 'wpf_setup_state';
	const FIRST_WEEK_EVENT = 'wpf_setup_first_week';
	const FILL_MIN         = 60;   // percent
	const VALUES_MIN       = 2;

	protected $_benchmarks = null;
	protected $_prosCons   = null;
	protected $_keywords   = null;

	/* ------------------------------------------------------------------ libraries */

	public function getBenchmarks() {
		if ( is_null( $this->_benchmarks ) ) {
			$this->_benchmarks = include WPF_MODULES_DIR . 'setup' . WPF_DS . 'benchmarks.php';
		}
		return $this->_benchmarks;
	}

	public function getProsCons() {
		if ( is_null( $this->_prosCons ) ) {
			$this->_prosCons = include WPF_MODULES_DIR . 'setup' . WPF_DS . 'pros-cons.php';
		}
		return $this->_prosCons;
	}

	public function getKeywords() {
		if ( is_null( $this->_keywords ) ) {
			$this->_keywords = include WPF_MODULES_DIR . 'setup' . WPF_DS . 'keywords.php';
		}
		return $this->_keywords;
	}

	/* ------------------------------------------------------------------ scan */

	/**
	 * Cached scan or false.
	 */
	public function getScan() {
		$scan = get_transient( self::SCAN_TRANSIENT );
		if ( ! is_array( $scan ) || empty( $scan['done'] ) ) {
			return false;
		}
		// A cached scan from before an import (or a deleted catalogue) is worthless: scan again.
		if ( (int) $scan['products'] !== $this->countProducts() ) {
			$this->clearScan();
			return false;
		}
		return $scan;
	}

	/**
	 * Attributes worth a filter: fill rate above FILL_MIN and at least VALUES_MIN values.
	 *
	 * @since 4.0.0
	 */
	public function countAttributesWorthFilter( $scan ) {
		$n = 0;
		foreach ( (array) $scan['attributes'] as $attr ) {
			if ( (int) $attr['fill'] > self::FILL_MIN && (int) $attr['terms'] >= self::VALUES_MIN ) {
				++$n;
			}
		}
		if ( ! empty( $scan['brands']['taxonomy'] ) && (int) $scan['brands']['fill'] > self::FILL_MIN && (int) $scan['brands']['terms'] >= self::VALUES_MIN ) {
			++$n;
		}
		return $n;
	}

	public function clearScan() {
		delete_transient( self::SCAN_TRANSIENT );
		delete_transient( self::SCAN_STATE );
		delete_option( self::SCAN_STATE ); // state of builds before 4.0.0 final lived in an option
	}

	/**
	 * Batch state between requests (transient; falls back to the pre-release option).
	 *
	 * @since 4.0.0
	 */
	protected function getScanState() {
		$state = get_transient( self::SCAN_STATE );
		if ( ! is_array( $state ) ) {
			$state = get_option( self::SCAN_STATE );
		}
		return is_array( $state ) && ! empty( $state['total'] ) ? $state : false;
	}

	protected function saveScanState( $state ) {
		set_transient( self::SCAN_STATE, $state, self::SCAN_STATE_TTL );
	}

	/**
	 * Seconds one request may spend scanning: SCAN_BUDGET, or less when PHP's
	 * max_execution_time is lower (keeps 5 s for the rest of the request).
	 *
	 * @since 4.0.0
	 */
	public function scanBudget() {
		$budget = self::SCAN_BUDGET;
		$max    = (int) ini_get( 'max_execution_time' );
		if ( $max > 0 && $max - 5 < $budget ) {
			$budget = max( 3, $max - 5 );
		}
		return (int) apply_filters( 'woobewoo_pf_setup_scan_budget', $budget );
	}

	/**
	 * Number of published products.
	 */
	public function countProducts() {
		$counts = wp_count_posts( 'product' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Start or continue a scan.
	 *
	 * Returns array( 'done' => bool, 'progress' => 0..100, 'scan' => array|null, 'offset', 'total', 'empty' ).
	 * Each call processes batches of SCAN_BATCH products with direct SQL until the catalogue is
	 * done or the time budget (scanBudget(), 20 s at most) is used up; then it returns the
	 * progress and the wizard calls again. No WP-Cron is involved: the first run is driven by
	 * the browser. Only when the owner leaves the page mid scan is the remainder queued
	 * (see scheduleScanBatch()).
	 *
	 * @param bool $force      Drop the cached scan and start over.
	 * @param bool $inlineOnly Ignore the time budget and finish in this request (CLI).
	 */
	public function runScan( $force = false, $inlineOnly = false ) {
		if ( ! $force ) {
			$scan = $this->getScan();
			if ( $scan ) {
				return array( 'done' => true, 'progress' => 100, 'scan' => $scan, 'offset' => $scan['products'], 'total' => $scan['products'], 'empty' => false );
			}
		} else {
			$this->clearScan();
		}

		$state = $this->getScanState();
		if ( ! $state ) {
			$state = $this->initScanState();
		}
		if ( (int) $state['total'] <= 0 ) {
			// Nothing to scan: say so plainly instead of pretending.
			$this->clearScan();
			return array( 'done' => true, 'progress' => 100, 'scan' => null, 'offset' => 0, 'total' => 0, 'empty' => true );
		}

		$started = microtime( true );
		$budget  = $inlineOnly ? PHP_INT_MAX : $this->scanBudget();
		$guard   = 0;
		while ( $state['offset'] < $state['total'] && $guard < 100000 ) {
			$state = $this->processScanBatch( $state );
			++$guard;
			if ( $state['offset'] < $state['total'] && ( microtime( true ) - $started ) >= $budget ) {
				$this->saveScanState( $state );
				return array(
					'done'     => false,
					'progress' => (int) floor( $state['offset'] / max( 1, $state['total'] ) * 100 ),
					'scan'     => null,
					'offset'   => (int) $state['offset'],
					'total'    => (int) $state['total'],
					'empty'    => false,
				);
			}
		}

		$scan = $this->finalizeScan( $state );
		return array( 'done' => true, 'progress' => 100, 'scan' => $scan, 'offset' => $scan['products'], 'total' => $scan['products'], 'empty' => false );
	}

	/**
	 * Queue the remainder of an unfinished scan (the owner left the wizard page).
	 *
	 * @since 4.0.0
	 * @return bool true when something was queued.
	 */
	public function queueRemainder() {
		$state = $this->getScanState();
		if ( ! $state || $state['offset'] >= $state['total'] ) {
			return false;
		}
		$this->scheduleScanBatch();
		return true;
	}

	/**
	 * Cron / Action Scheduler callback: work for one budget, then reschedule or finish.
	 */
	public function runScanBatchEvent() {
		$state = $this->getScanState();
		if ( ! $state ) {
			return;
		}
		$started = microtime( true );
		$budget  = $this->scanBudget();
		while ( $state['offset'] < $state['total'] ) {
			$state = $this->processScanBatch( $state );
			if ( $state['offset'] < $state['total'] && ( microtime( true ) - $started ) >= $budget ) {
				$this->saveScanState( $state );
				$this->scheduleScanBatch();
				return;
			}
		}
		$this->finalizeScan( $state );
	}

	protected function scheduleScanBatch() {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! as_next_scheduled_action( self::SCAN_EVENT, array(), 'woo-product-filter' ) ) {
				as_schedule_single_action( time() + 1, self::SCAN_EVENT, array(), 'woo-product-filter' );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::SCAN_EVENT ) ) {
			wp_schedule_single_event( time() + 1, self::SCAN_EVENT );
		}
		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			spawn_cron();
		}
	}

	/**
	 * Scan progress 0..100 for the wizard poll.
	 */
	public function getScanProgress() {
		if ( $this->getScan() ) {
			return 100;
		}
		$state = $this->getScanState();
		if ( ! $state ) {
			return 0;
		}
		return (int) floor( $state['offset'] / max( 1, $state['total'] ) * 100 );
	}

	protected function initScanState() {
		$taxonomies = array( 'product_cat', 'product_tag' );
		$attributes = array();
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $attr ) {
				$tax                = wc_attribute_taxonomy_name( $attr->attribute_name );
				$taxonomies[]       = $tax;
				$attributes[ $tax ] = array(
					'id'       => (int) $attr->attribute_id,
					'name'     => $attr->attribute_label,
					'slug'     => $attr->attribute_name,
					'taxonomy' => $tax,
					'terms'    => (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ),
				);
			}
		}
		$brandTax = '';
		if ( taxonomy_exists( 'product_brand' ) ) {
			$brandTax = 'product_brand';
		} elseif ( taxonomy_exists( 'pwb-brand' ) ) {
			$brandTax = 'pwb-brand';
		}
		if ( $brandTax ) {
			$taxonomies[] = $brandTax;
		}

		$varCounts = wp_count_posts( 'product_variation' );

		return array(
			'started'    => time(),
			'total'      => $this->countProducts(),
			'offset'     => 0,
			'lookup'     => $this->hasLookupTable(),
			'taxonomies' => array_values( array_unique( $taxonomies ) ),
			'attributes' => $attributes,
			'brand_tax'  => $brandTax,
			'variations' => isset( $varCounts->publish ) ? (int) $varCounts->publish : 0,
			'categories' => (int) wp_count_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) ),
			'tags'       => (int) wp_count_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) ),
			'tax_counts' => array(),
			'stock'      => array( 'instock' => 0, 'outofstock' => 0, 'onbackorder' => 0 ),
			'sale'       => 0,
			'rated'      => 0,
			'with_price' => 0,
		);
	}

	/**
	 * One batch of SCAN_BATCH products: taxonomy fill counts and lookup table counts.
	 */
	protected function processScanBatch( $state ) {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID ASC LIMIT %d, %d",
				(int) $state['offset'],
				self::SCAN_BATCH
			)
		);
		$state['offset'] += self::SCAN_BATCH;
		if ( empty( $ids ) ) {
			$state['offset'] = max( $state['offset'], (int) $state['total'] );
			return $state;
		}
		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $placeholders / $taxPlaceholders are lists of %d / %s built with array_fill(), $lookup is $wpdb->prefix plus a WooCommerce table name; the values go through $wpdb->prepare()
		if ( ! empty( $state['taxonomies'] ) ) {
			$taxPlaceholders = implode( ',', array_fill( 0, count( $state['taxonomies'] ), '%s' ) );
			$rows            = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT tt.taxonomy AS taxonomy, COUNT(DISTINCT tr.object_id) AS cnt
					FROM {$wpdb->term_relationships} tr
					INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					WHERE tr.object_id IN ({$placeholders}) AND tt.taxonomy IN ({$taxPlaceholders})
					GROUP BY tt.taxonomy", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					array_merge( $ids, $state['taxonomies'] )
				),
				ARRAY_A
			);
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					$tax = $row['taxonomy'];
					if ( ! isset( $state['tax_counts'][ $tax ] ) ) {
						$state['tax_counts'][ $tax ] = 0;
					}
					$state['tax_counts'][ $tax ] += (int) $row['cnt'];
				}
			}
		}

		if ( ! empty( $state['lookup'] ) ) {
			$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
			$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT stock_status, COUNT(*) AS cnt, SUM(onsale) AS onsale, SUM(rating_count > 0) AS rated, SUM(min_price > 0 OR max_price > 0) AS priced
					FROM {$lookup} WHERE product_id IN ({$placeholders}) GROUP BY stock_status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $lookup is $wpdb->prefix plus a WooCommerce table name, $placeholders is a list of %d
					$ids
				),
				ARRAY_A
			);
		} else {
			// No wc_product_meta_lookup table (very old WooCommerce or a failed install): read postmeta.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT COALESCE(st.meta_value, 'instock') AS stock_status, COUNT(*) AS cnt,
						SUM(sp.meta_value IS NOT NULL AND sp.meta_value <> '') AS onsale,
						SUM(rc.meta_value + 0 > 0) AS rated,
						SUM(pr.meta_value + 0 > 0) AS priced
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_stock_status'
					LEFT JOIN {$wpdb->postmeta} sp ON sp.post_id = p.ID AND sp.meta_key = '_sale_price'
					LEFT JOIN {$wpdb->postmeta} rc ON rc.post_id = p.ID AND rc.meta_key = '_wc_review_count'
					LEFT JOIN {$wpdb->postmeta} pr ON pr.post_id = p.ID AND pr.meta_key = '_price'
					WHERE p.ID IN ({$placeholders}) GROUP BY COALESCE(st.meta_value, 'instock')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- $placeholders is a list of %d; fallback path for stores without the lookup table
					$ids
				),
				ARRAY_A
			);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = isset( $state['stock'][ $row['stock_status'] ] ) ? $row['stock_status'] : 'instock';
				$state['stock'][ $status ] += (int) $row['cnt'];
				$state['sale']             += (int) $row['onsale'];
				$state['rated']            += (int) $row['rated'];
				$state['with_price']       += (int) $row['priced'];
			}
		}

		return $state;
	}

	/**
	 * Turn the accumulated state into the scan result and cache it.
	 */
	protected function finalizeScan( $state ) {
		global $wpdb;
		$total = max( 0, (int) $state['total'] );

		$attributes = array();
		foreach ( $state['attributes'] as $tax => $attr ) {
			$with                   = isset( $state['tax_counts'][ $tax ] ) ? (int) $state['tax_counts'][ $tax ] : 0;
			$attr['with_value']     = $with;
			$attr['fill']           = $total ? (int) round( $with / $total * 100 ) : 0;
			$attributes[ $tax ]     = $attr;
		}

		$brands = array( 'taxonomy' => $state['brand_tax'], 'terms' => 0, 'with_value' => 0, 'fill' => 0 );
		if ( $state['brand_tax'] ) {
			$brands['terms']      = (int) wp_count_terms( array( 'taxonomy' => $state['brand_tax'], 'hide_empty' => false ) );
			$brands['with_value'] = isset( $state['tax_counts'][ $state['brand_tax'] ] ) ? (int) $state['tax_counts'][ $state['brand_tax'] ] : 0;
			$brands['fill']       = $total ? (int) round( $brands['with_value'] / $total * 100 ) : 0;
		}

		$median = 0;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $lookup is $wpdb->prefix plus a WooCommerce table name; the only value (LIMIT offset) is prepared
		if ( ! empty( $state['lookup'] ) ) {
			$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
			$price  = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $lookup is $wpdb->prefix plus a WooCommerce table name; no user input
				"SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price, COUNT(*) AS cnt FROM {$lookup} l
				INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
				WHERE p.post_type = 'product' AND p.post_status = 'publish' AND l.min_price > 0",
				ARRAY_A
			);
			if ( $price && (int) $price['cnt'] > 0 ) {
				$offset = (int) floor( ( (int) $price['cnt'] - 1 ) / 2 );
				$median = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare(
						"SELECT l.min_price FROM {$lookup} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id
						WHERE p.post_type = 'product' AND p.post_status = 'publish' AND l.min_price > 0 ORDER BY l.min_price ASC LIMIT %d, 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $lookup is $wpdb->prefix plus a WooCommerce table name
						$offset
					)
				);
			}
		} else {
			$price = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fallback for stores without wc_product_meta_lookup
				"SELECT MIN(pm.meta_value + 0) AS min_price, MAX(pm.meta_value + 0) AS max_price, COUNT(*) AS cnt FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_price' AND pm.meta_value + 0 > 0 AND p.post_type = 'product' AND p.post_status = 'publish'",
				ARRAY_A
			);
			if ( $price && (int) $price['cnt'] > 0 ) {
				$offset = (int) floor( ( (int) $price['cnt'] - 1 ) / 2 );
				$median = (float) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- fallback for stores without wc_product_meta_lookup
					$wpdb->prepare(
						"SELECT pm.meta_value + 0 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						WHERE pm.meta_key = '_price' AND pm.meta_value + 0 > 0 AND p.post_type = 'product' AND p.post_status = 'publish' ORDER BY pm.meta_value + 0 ASC LIMIT %d, 1",
						$offset
					)
				);
			}
		}

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$scan = array(
			'done'        => true,
			'time'        => time(),
			'products'    => $total,
			'variations'  => (int) $state['variations'],
			'categories'  => (int) $state['categories'],
			'tags'        => (int) $state['tags'],
			'categorized' => isset( $state['tax_counts']['product_cat'] ) ? (int) $state['tax_counts']['product_cat'] : 0,
			'tagged'      => isset( $state['tax_counts']['product_tag'] ) ? (int) $state['tax_counts']['product_tag'] : 0,
			'attributes'  => $attributes,
			'brands'      => $brands,
			'price'       => array(
				'min'        => $price ? (float) $price['min_price'] : 0,
				'max'        => $price ? (float) $price['max_price'] : 0,
				'median'     => $median,
				'with_price' => (int) $state['with_price'],
			),
			'stock'       => $state['stock'],
			'rating'      => array( 'rated' => (int) $state['rated'] ),
			'sale'        => (int) $state['sale'],
			'acf_numeric' => $this->getAcfNumericFields(),
		);
		$scan['store_type'] = $this->detectStoreType( $scan );

		set_transient( self::SCAN_TRANSIENT, $scan, self::SCAN_TTL );
		delete_transient( self::SCAN_STATE );
		delete_option( self::SCAN_STATE );

		return $scan;
	}

	/**
	 * Does the WooCommerce product lookup table exist?
	 *
	 * @since 4.0.0
	 */
	public function hasLookupTable() {
		global $wpdb;
		$table = $wpdb->prefix . 'wc_product_meta_lookup';
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check, once per scan
	}

	/**
	 * ACF numeric fields attached to products (if ACF is active).
	 */
	public function getAcfNumericFields() {
		$fields = array();
		if ( ! class_exists( 'ACF' ) || ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return $fields;
		}
		$groups = acf_get_field_groups( array( 'post_type' => 'product' ) );
		if ( empty( $groups ) ) {
			return $fields;
		}
		foreach ( $groups as $group ) {
			$groupFields = acf_get_fields( $group['key'] );
			if ( empty( $groupFields ) ) {
				continue;
			}
			foreach ( $groupFields as $field ) {
				if ( ! empty( $field['name'] ) && in_array( $field['type'], array( 'number', 'range' ), true ) ) {
					$fields[] = array( 'name' => $field['name'], 'label' => $field['label'] );
				}
			}
		}
		return $fields;
	}

	/* ------------------------------------------------------------------ store type */

	/**
	 * Rule based store type with a confidence 0..1.
	 */
	public function detectStoreType( $scan ) {
		global $wpdb;
		$keywords = $this->getKeywords();
		$scores   = array_fill_keys( array_keys( $keywords ), 0 );

		$sources = array();
		$cats    = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'names', 'number' => 500 ) );
		if ( is_array( $cats ) ) {
			$sources[] = array( 'weight' => 3, 'text' => implode( ' | ', $cats ) );
		}
		$attrNames = array();
		foreach ( $scan['attributes'] as $attr ) {
			$attrNames[] = $attr['name'];
		}
		if ( $attrNames ) {
			$sources[] = array( 'weight' => 2, 'text' => implode( ' | ', $attrNames ) );
		}
		$titles = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID DESC LIMIT 200" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( is_array( $titles ) && $titles ) {
			$sources[] = array( 'weight' => 1, 'text' => implode( ' | ', $titles ) );
		}

		foreach ( $sources as $source ) {
			$text = ' ' . preg_replace( '/[^\p{L}\p{N}\- ]+/u', ' ', mb_strtolower( $source['text'] ) ) . ' ';
			foreach ( $keywords as $type => $words ) {
				foreach ( $words as $word ) {
					$hits = substr_count( $text, ' ' . $word . ' ' );
					if ( $hits ) {
						$scores[ $type ] += $hits * $source['weight'];
					}
				}
			}
		}

		arsort( $scores );
		$total = array_sum( $scores );
		$type  = 'general';
		$conf  = 0.0;
		if ( $total > 0 ) {
			$type = key( $scores );
			$top  = current( $scores );
			$conf = round( $top / $total, 2 );
			// A thin lead over the runner up is not a confident call.
			$values = array_values( $scores );
			if ( isset( $values[1] ) && $values[1] > 0 && $top / $values[1] < 1.2 ) {
				$conf = min( $conf, 0.5 );
			}
			if ( $top < 3 ) {
				$type = 'general';
				$conf = 0.3;
			}
		}
		$benchmarks = $this->getBenchmarks();

		return array(
			'type'       => $type,
			'label'      => isset( $benchmarks[ $type ] ) ? $benchmarks[ $type ]['label'] : $type,
			'confidence' => (float) $conf,
			'scores'     => $scores,
		);
	}

	/* ------------------------------------------------------------------ theme and builders */

	/**
	 * Active theme, parents, block or classic, builders present, loop selector guess,
	 * and whether the core Product Filters block is placed.
	 */
	public function detectEnvironment() {
		$theme   = wp_get_theme();
		$parents = array();
		$t       = $theme;
		while ( $t && $t->parent() ) {
			$t         = $t->parent();
			$parents[] = array( 'name' => $t->get( 'Name' ), 'slug' => $t->get_stylesheet() );
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$slugs    = array_merge( array( $theme->get_stylesheet(), $theme->get_template() ), wp_list_pluck( $parents, 'slug' ) );
		$slugs    = array_map( 'strtolower', array_unique( array_filter( $slugs ) ) );
		$builders = array(
			'elementor'  => defined( 'ELEMENTOR_VERSION' ) || is_plugin_active( 'elementor/elementor.php' ),
			'divi'       => defined( 'ET_BUILDER_VERSION' ) || in_array( 'divi', $slugs, true ) || is_plugin_active( 'divi-builder/divi-builder.php' ),
			'bricks'     => defined( 'BRICKS_VERSION' ) || in_array( 'bricks', $slugs, true ),
			'breakdance' => defined( '__BREAKDANCE_VERSION' ) || is_plugin_active( 'breakdance/plugin.php' ),
			'oxygen'     => defined( 'CT_VERSION' ) || is_plugin_active( 'oxygen/functions.php' ),
			'wpbakery'   => defined( 'WPB_VC_VERSION' ) || is_plugin_active( 'js_composer/js_composer.php' ),
			'avada'      => class_exists( 'Avada' ) || in_array( 'avada', $slugs, true ),
			'beaver'     => class_exists( 'FLBuilder' ) || is_plugin_active( 'bb-plugin/fl-builder.php' ) || is_plugin_active( 'beaver-builder-lite-version/fl-builder.php' ),
		);

		return array(
			'theme'              => array(
				'name'    => $theme->get( 'Name' ),
				'slug'    => $theme->get_stylesheet(),
				'version' => $theme->get( 'Version' ),
				'parents' => $parents,
			),
			'is_block_theme'     => function_exists( 'wp_is_block_theme' ) ? (bool) wp_is_block_theme() : false,
			'builders'           => $builders,
			'loop_selector'      => $this->guessLoopSelector( $slugs, $builders ),
			'core_filters_block' => $this->hasCoreProductFiltersBlock(),
			'shop_url'           => $this->getShopUrl(),
		);
	}

	/**
	 * Small map of product loop selectors per theme or builder. Default: ul.products.
	 */
	public function guessLoopSelector( $slugs = array(), $builders = array() ) {
		$map = array(
			'storefront'     => 'ul.products',
			'astra'          => 'ul.products',
			'flatsome'       => '.products',
			'woodmart'       => '.products',
			'divi'           => 'ul.products',
			'avada'          => '.products',
			'oceanwp'        => 'ul.products',
			'kadence'        => 'ul.products',
			'shoptimizer'    => 'ul.products',
			'generatepress'  => 'ul.products',
			'blocksy'        => '.products',
			'hello-elementor' => '.products',
			'neve'           => 'ul.products',
			'botiga'         => 'ul.products',
			'bricks'         => '.products',
			'twentytwentyfour' => '.wc-block-product-template',
			'twentytwentyfive' => '.wc-block-product-template',
		);
		foreach ( $slugs as $slug ) {
			if ( isset( $map[ $slug ] ) ) {
				return $map[ $slug ];
			}
		}
		if ( ! empty( $builders['elementor'] ) ) {
			return '.products';
		}
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return '.wc-block-product-template';
		}
		return 'ul.products';
	}

	/**
	 * Candidate loop selectors tried by the self test, best first.
	 */
	public function getLoopSelectorCandidates() {
		return array(
			'ul.products',
			'.products',
			'.wc-block-product-template',
			'.wp-block-woocommerce-product-collection',
			'.wc-block-grid',
			'.wp-block-woocommerce-product-template',
			'.product-grid',
			'.products-grid',
			'.shop-container',
			'.elementor-widget-wc-archive-products',
		);
	}

	/**
	 * Is the WooCommerce core Product Filters block placed somewhere (block templates, shop page, block widgets)?
	 */
	public function hasCoreProductFiltersBlock() {
		global $wpdb;
		$needle = '%' . $wpdb->esc_like( 'woocommerce/product-filter' ) . '%';
		$found  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part','page') AND post_status IN ('publish','auto-draft') AND post_content LIKE %s",
				$needle
			)
		);
		if ( $found ) {
			return true;
		}
		$widgets = get_option( 'widget_block' );
		if ( is_array( $widgets ) ) {
			foreach ( $widgets as $w ) {
				if ( is_array( $w ) && ! empty( $w['content'] ) && false !== strpos( $w['content'], 'woocommerce/product-filter' ) ) {
					return true;
				}
			}
		}
		return false;
	}

	public function getShopUrl() {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		if ( empty( $url ) || is_wp_error( $url ) ) {
			$url = home_url( '/' );
		}
		return $url;
	}

	/**
	 * Fetch the shop page server side and look for the loop selector.
	 *
	 * Returns array( 'found' => bool, 'selector' => tested, 'suggest' => string|null, 'result_count' => bool, 'error' => string|null ).
	 */
	public function testLoopSelector( $selector, $url = '' ) {
		$url        = $url ? $url : $this->getShopUrl();
		$comingSoon = $this->isComingSoon();
		// Send the current admin's login cookies so WooCommerce "coming soon" mode (and other
		// logged in only views) shows the real shop page to the self test.
		$cookies = array();
		foreach ( $_COOKIE as $name => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- forwarding the current admin's own session to a loopback request
			if ( is_string( $value ) && preg_match( '/^(wordpress_logged_in_|wordpress_sec_|wordpress_|wp-settings-|woocommerce_)/', (string) $name ) ) {
				$cookies[ (string) $name ] = $value;
			}
		}
		$res = wp_remote_get(
			add_query_arg( 'wpf_selftest', '1', $url ),
			array(
				'timeout'    => 15,
				'user-agent' => 'WBW Product Filter self test (' . home_url() . ')',
				'cookies'    => $cookies,
				'sslverify'  => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'found' => false, 'selector' => $selector, 'suggest' => null, 'result_count' => false, 'error' => $res->get_error_message(), 'coming_soon' => $comingSoon );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$html = (string) wp_remote_retrieve_body( $res );
		if ( $code >= 400 || '' === $html ) {
			return array( 'found' => false, 'selector' => $selector, 'suggest' => null, 'result_count' => false, 'error' => sprintf( 'HTTP %d', $code ), 'coming_soon' => $comingSoon );
		}
		if ( $comingSoon && false !== strpos( $html, 'coming-soon' ) && ! $this->selectorInHtml( $selector, $html ) ) {
			// The loopback got the coming soon page, not the shop: skip with a plain note.
			return array( 'found' => false, 'selector' => $selector, 'suggest' => null, 'result_count' => false, 'error' => null, 'coming_soon' => true, 'skipped' => true );
		}

		$found   = $this->selectorInHtml( $selector, $html );
		$suggest = null;
		if ( ! $found ) {
			foreach ( $this->getLoopSelectorCandidates() as $candidate ) {
				if ( $candidate !== $selector && $this->selectorInHtml( $candidate, $html ) ) {
					$suggest = $candidate;
					break;
				}
			}
		}
		return array(
			'found'        => $found,
			'selector'     => $selector,
			'suggest'      => $suggest,
			'result_count' => false !== strpos( $html, 'woocommerce-result-count' ),
			'error'        => null,
			'coming_soon'  => $comingSoon,
		);
	}

	/**
	 * Is WooCommerce "Store coming soon" mode on?
	 *
	 * @since 4.0.0
	 */
	public function isComingSoon() {
		return 'yes' === get_option( 'woocommerce_coming_soon' );
	}

	/**
	 * Minimal CSS selector matcher on raw HTML: supports "tag", ".class", "#id",
	 * "tag.class" and descendant combinations separated by spaces (last part decides).
	 */
	public function selectorInHtml( $selector, $html ) {
		$selector = trim( (string) $selector );
		if ( '' === $selector ) {
			return false;
		}
		$parts = preg_split( '/\s+/', $selector );
		$last  = end( $parts );
		if ( ! preg_match( '/^([a-z0-9]*)((?:[.#][a-zA-Z0-9_-]+)*)$/i', $last, $m ) ) {
			return false;
		}
		$tag     = strtolower( $m[1] );
		$classes = array();
		$id      = '';
		if ( preg_match_all( '/([.#])([a-zA-Z0-9_-]+)/', $m[2], $mm, PREG_SET_ORDER ) ) {
			foreach ( $mm as $x ) {
				if ( '.' === $x[1] ) {
					$classes[] = $x[2];
				} else {
					$id = $x[2];
				}
			}
		}
		$tagRe = $tag ? preg_quote( $tag, '/' ) : '[a-z0-9]+';
		if ( ! preg_match_all( '/<' . $tagRe . '\b([^>]*)>/i', $html, $tags ) ) {
			return false;
		}
		foreach ( $tags[1] as $attrs ) {
			if ( $id && ! preg_match( '/\bid=["\']' . preg_quote( $id, '/' ) . '["\']/i', $attrs ) ) {
				continue;
			}
			if ( $classes ) {
				if ( ! preg_match( '/\bclass=["\']([^"\']*)["\']/i', $attrs, $cm ) ) {
					continue;
				}
				$have = preg_split( '/\s+/', trim( $cm[1] ) );
				if ( array_diff( $classes, $have ) ) {
					continue;
				}
			}
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ suggestions */

	/**
	 * Keywords that map a filter kind to an attribute name.
	 */
	protected function kindAttributeWords() {
		return array(
			'size'     => array( 'size', 'sizes', 'taille', 'größe', 'groesse', 'talla', 'taglia', 'maat', 'rozmiar', 'размер' ),
			'colour'   => array( 'color', 'colour', 'colors', 'colours', 'farbe', 'couleur', 'colore', 'kleur', 'kolor', 'цвет' ),
			'material' => array( 'material', 'materials', 'fabric', 'matériau', 'materiaal', 'materiał', 'материал' ),
			'year'     => array( 'year', 'years', 'jahr', 'année', 'año', 'год' ),
			'make'     => array( 'make', 'marque', 'marca', 'hersteller', 'manufacturer', 'производитель' ),
			'model'    => array( 'model', 'modell', 'modèle', 'modelo', 'модель' ),
			'age'      => array( 'age', 'ages', 'age range', 'alter', 'âge', 'edad', 'возраст' ),
			'brand'    => array( 'brand', 'brands', 'marke', 'marque', 'marca', 'бренд' ),
		);
	}

	protected function findAttributeForKind( $kind, $attributes ) {
		$words = $this->kindAttributeWords();
		if ( empty( $words[ $kind ] ) ) {
			return null;
		}
		foreach ( $attributes as $attr ) {
			$name = mb_strtolower( $attr['name'] );
			$slug = mb_strtolower( str_replace( array( '-', '_' ), ' ', $attr['slug'] ) );
			foreach ( $words[ $kind ] as $w ) {
				if ( $name === $w || $slug === $w || false !== strpos( ' ' . $name . ' ', ' ' . $w . ' ' ) ) {
					return $attr;
				}
			}
		}
		return null;
	}

	/**
	 * Build suggestions for the store type: ordered by the benchmark, each with why,
	 * watch out, benchmark percent, fill rate, values and products that would disappear.
	 */
	public function buildSuggestions( $scan, $storeType = '' ) {
		$benchmarks = $this->getBenchmarks();
		$prosCons   = $this->getProsCons();
		$storeType  = $storeType && isset( $benchmarks[ $storeType ] ) ? $storeType : $scan['store_type']['type'];
		if ( ! isset( $benchmarks[ $storeType ] ) ) {
			$storeType = 'general';
		}
		$total      = max( 0, (int) $scan['products'] );
		$used       = array();
		$result     = array();
		$usedAttrs  = array();
		$allFilters = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getAllFilters();

		foreach ( $benchmarks[ $storeType ]['filters'] as $kind => $percent ) {
			$item = $this->describeKind( $kind, $scan, $total, $prosCons, $allFilters, $usedAttrs );
			if ( $item ) {
				$item['benchmark'] = (int) $percent;
				$result[]          = $item;
				$used[]            = $kind;
				if ( ! empty( $item['attribute'] ) ) {
					$usedAttrs[] = $item['attribute'];
				}
			}
		}
		// Attributes with a good fill rate that the benchmark did not name, by fill rate.
		$extra = array();
		foreach ( $scan['attributes'] as $tax => $attr ) {
			if ( in_array( $tax, $usedAttrs, true ) ) {
				continue;
			}
			$extra[] = $this->describeAttribute( 'attribute', $attr, $total, $prosCons, 0 );
		}
		usort(
			$extra,
			function ( $a, $b ) {
				return $b['fill'] - $a['fill'];
			}
		);
		foreach ( $extra as $item ) {
			$result[] = $item;
		}
		// Price is always worth having; if the benchmark skipped it, append it.
		if ( ! in_array( 'price', $used, true ) ) {
			$item = $this->describeKind( 'price', $scan, $total, $prosCons, $allFilters, $usedAttrs );
			if ( $item ) {
				$item['benchmark'] = 100;
				$result[]          = $item;
			}
		}

		$suggestions = array(
			'store_type' => $storeType,
			'label'      => $benchmarks[ $storeType ]['label'],
			'items'      => $result,
			'cards'      => array(),
		);

		/**
		 * The rule based suggestions are final: an AI layer (Pro, when its optional AI endpoint is set)
		 * may refine them here before the cards are collected.
		 *
		 * Pro's `WooBeWoo_PF_Agents::aiSuggest()` rewrites `why` / `watch_out` on the items it was given and
		 * appends extra attribute items (same keys as describeAttribute()). The callback must return the
		 * whole array again; anything that is not an array with an `items` list is ignored.
		 *
		 * @since 4.0.0
		 *
		 * @param array  $suggestions array( 'store_type', 'label', 'items' => array( array( 'key', 'kind', 'block',
		 *                            'attribute', 'attr_id', 'name', 'benchmark', 'fill', 'values', 'missing', 'why',
		 *                            'watch_out', 'speed', 'suggested', 'pro_only', 'reason' ) ), 'cards' => array() ).
		 * @param array  $scan        The store scan (products, attributes keyed by taxonomy, store_type, ...).
		 * @param string $storeType   The benchmark store type key (fashion, shoes, general, ...).
		 */
		$refined = apply_filters( 'woobewoo_pf_setup_ai_suggest', $suggestions, $scan, $storeType );
		if ( is_array( $refined ) && isset( $refined['items'] ) && is_array( $refined['items'] ) ) {
			$suggestions['items'] = array_values( $refined['items'] );
		}

		/**
		 * Extra filter cards with an on/off switch and a live preview, such as "New arrivals" or "Size and
		 * weight" (Free measures module). Each item has the keys of describeAttribute(); `block` is a block id
		 * from the block registry (WooBeWoo_PF_Blocks) or a built-in block. Optional `fill_text` and `fill_tone`
		 * (pass|partial) replace the "N of M have a value" tag. Items start switched off unless `suggested`.
		 *
		 * @since 4.0.0
		 *
		 * @param array  $items     The items so far.
		 * @param array  $scan      The store scan.
		 * @param string $storeType The benchmark store type key.
		 */
		$more = apply_filters( 'woobewoo_pf_setup_items', array(), $scan, $storeType );
		if ( is_array( $more ) ) {
			$keys = wp_list_pluck( $suggestions['items'], 'key' );
			foreach ( $more as $item ) {
				if ( is_array( $item ) && ! empty( $item['key'] ) && ! empty( $item['block'] ) && ! in_array( $item['key'], $keys, true ) ) {
					$suggestions['items'][] = $item;
					$keys[]                 = $item['key'];
				}
			}
		}

		/**
		 * Extra suggestion cards (Pro: "we found 3 numeric fields"). Each card:
		 * array( 'title', 'text', 'tag' => 'pass|partial|neutral', 'tag_text', 'url' => '', 'button' => '' ).
		 *
		 * @since 4.0.0
		 */
		$suggestions['cards'] = apply_filters( 'woobewoo_pf_setup_suggestions', array(), $scan, $storeType );

		return $suggestions;
	}

	protected function describeAttribute( $kind, $attr, $total, $prosCons, $benchmark ) {
		$fill    = isset( $attr['fill'] ) ? (int) $attr['fill'] : 0;
		$values  = isset( $attr['terms'] ) ? (int) $attr['terms'] : 0;
		$missing = max( 0, $total - (int) $attr['with_value'] );
		$text    = isset( $prosCons[ $kind ] ) ? $prosCons[ $kind ] : $prosCons['attribute'];
		return array(
			'key'        => 'attr_' . $attr['taxonomy'],
			'kind'       => $kind,
			'block'      => 'wpfAttribute',
			'attribute'  => $attr['taxonomy'],
			'attr_id'    => (int) $attr['id'],
			'name'       => $attr['name'],
			'benchmark'  => (int) $benchmark,
			'fill'       => $fill,
			'values'     => $values,
			'missing'    => $missing,
			'why'        => $text['why'],
			'watch_out'  => str_replace( '{missing}', number_format_i18n( $missing ), $text['watch_out'] ),
			'speed'      => $text['speed'],
			'suggested'  => ( $fill > self::FILL_MIN && $values >= self::VALUES_MIN ),
			'pro_only'   => false,
			'reason'     => $this->notSuggestedReason( $fill, $values ),
		);
	}

	protected function notSuggestedReason( $fill, $values ) {
		if ( $values < self::VALUES_MIN ) {
			return __( 'Only one choice, so there is nothing to pick.', 'woo-product-filter' );
		}
		if ( $fill <= self::FILL_MIN ) {
			/* translators: %d: percent of products with a value */
			return sprintf( __( 'Only %d%% of your products have a value.', 'woo-product-filter' ), $fill );
		}
		return '';
	}

	/**
	 * Describe one filter kind as a suggestion row, or null when the store has no such data at all.
	 */
	protected function describeKind( $kind, $scan, $total, $prosCons, $allFilters, $usedAttrs = array() ) {
		$text = isset( $prosCons[ $kind ] ) ? $prosCons[ $kind ] : $prosCons['attribute'];
		$row  = array(
			'key'       => $kind,
			'kind'      => $kind,
			'block'     => '',
			'attribute' => '',
			'attr_id'   => 0,
			'name'      => $text['name'],
			'benchmark' => 0,
			'fill'      => 0,
			'values'    => 0,
			'missing'   => 0,
			'why'       => $text['why'],
			'watch_out' => $text['watch_out'],
			'speed'     => $text['speed'],
			'suggested' => false,
			'pro_only'  => false,
			'reason'    => '',
		);
		switch ( $kind ) {
			case 'price':
				$with          = (int) $scan['price']['with_price'];
				$row['block']  = 'wpfPrice';
				$row['fill']   = $total ? (int) round( $with / $total * 100 ) : 0;
				$row['values'] = ( $scan['price']['max'] > $scan['price']['min'] ) ? 2 : 1;
				break;
			case 'category':
				$row['block']  = 'wpfCategory';
				$row['fill']   = $total ? (int) round( (int) $scan['categorized'] / $total * 100 ) : 0;
				$row['values'] = (int) $scan['categories'];
				break;
			case 'tags':
				$row['block']  = 'wpfTags';
				$row['fill']   = $total ? (int) round( (int) $scan['tagged'] / $total * 100 ) : 0;
				$row['values'] = (int) $scan['tags'];
				break;
			case 'sale':
				$row['block']     = 'wpfOnSale';
				$row['fill']      = 100;
				$row['values']    = ( $scan['sale'] > 0 && $scan['sale'] < $total ) ? 2 : 1;
				$row['watch_out'] = str_replace( '{onsale}', number_format_i18n( (int) $scan['sale'] ), $row['watch_out'] );
				break;
			case 'stock':
				$row['block']     = 'wpfInStock';
				$row['fill']      = 100;
				$states           = 0;
				foreach ( $scan['stock'] as $n ) {
					if ( $n > 0 ) {
						++$states;
					}
				}
				$row['values']    = $states;
				$row['watch_out'] = str_replace( '{outofstock}', number_format_i18n( (int) $scan['stock']['outofstock'] ), $row['watch_out'] );
				break;
			case 'rating':
				$row['block']  = 'wpfRating';
				$row['fill']   = $total ? (int) round( (int) $scan['rating']['rated'] / $total * 100 ) : 0;
				$row['values'] = $scan['rating']['rated'] > 0 ? 2 : 1;
				break;
			case 'author':
				$row['block']  = 'wpfAuthor';
				$authors       = count_users();
				$row['fill']   = 100;
				$row['values'] = isset( $authors['total_users'] ) ? (int) $authors['total_users'] : 1;
				break;
			case 'brand':
				$brands = $scan['brands'];
				if ( 'pwb-brand' === $brands['taxonomy'] && isset( $allFilters['wpfPerfectBrand'] ) ) {
					$row['block']  = 'wpfPerfectBrand';
					$row['fill']   = (int) $brands['fill'];
					$row['values'] = (int) $brands['terms'];
				} else {
					$attr = $this->findAttributeForKind( 'brand', $scan['attributes'] );
					if ( $attr && ! in_array( $attr['taxonomy'], $usedAttrs, true ) ) {
						$item              = $this->describeAttribute( 'brand', $attr, $total, $prosCons, 0 );
						$item['key']       = 'brand';
						return $item;
					}
					if ( 'product_brand' === $brands['taxonomy'] ) {
						$row['block']    = 'wpfBrand';
						$row['fill']     = (int) $brands['fill'];
						$row['values']   = (int) $brands['terms'];
						$row['pro_only'] = empty( $allFilters['wpfBrand']['enabled'] );
					} else {
						return null;
					}
				}
				$row['missing'] = max( 0, $total - (int) $brands['with_value'] );
				break;
			case 'numeric_fields':
				if ( empty( $scan['acf_numeric'] ) ) {
					return null;
				}
				$row['block']    = 'wpfCustomField';
				$row['fill']     = 0;
				$row['values']   = count( $scan['acf_numeric'] );
				$row['pro_only'] = true;
				$row['name']    .= ' (' . implode( ', ', wp_list_pluck( $scan['acf_numeric'], 'label' ) ) . ')';
				break;
			default:
				// Attribute kinds: size, colour, material, year, make, model, age.
				$attr = $this->findAttributeForKind( $kind, $scan['attributes'] );
				if ( ! $attr || in_array( $attr['taxonomy'], $usedAttrs, true ) ) {
					return null;
				}
				$item        = $this->describeAttribute( $kind, $attr, $total, $prosCons, 0 );
				$item['key'] = $kind;
				return $item;
		}
		if ( ! $row['missing'] ) {
			$row['missing'] = $total ? max( 0, $total - (int) round( $row['fill'] / 100 * $total ) ) : 0;
		}
		$row['watch_out'] = str_replace( '{missing}', number_format_i18n( $row['missing'] ), $row['watch_out'] );
		$row['suggested'] = ! $row['pro_only'] && $row['fill'] > self::FILL_MIN && $row['values'] >= self::VALUES_MIN;
		$row['reason']    = $row['pro_only'] ? __( 'Needs Pro.', 'woo-product-filter' ) : $this->notSuggestedReason( $row['fill'], $row['values'] );
		return $row;
	}

	/* ------------------------------------------------------------------ styles */

	/**
	 * Style cards -> concrete filter settings.
	 */
	public function getStyles() {
		return array(
			'clean'    => array(
				'label'    => __( 'Clean', 'woo-product-filter' ),
				'desc'     => __( 'Checkbox lists with counts, colour as dots. Works with any theme.', 'woo-product-filter' ),
				'type'     => 'list',
				'display_type' => 'list',
				'settings' => array( 'wbw_style' => 'clean' ),
				'block'    => array( 'f_show_count' => '1', 'f_hide_empty' => '1' ),
			),
			'chips'    => array(
				'label'    => __( 'Chips', 'woo-product-filter' ),
				'desc'     => __( 'Round pills shoppers tap, colour as dots. Great on phones.', 'woo-product-filter' ),
				'type'     => 'list',
				'display_type' => 'chips',
				'settings' => array( 'wbw_style' => 'chips' ),
				'block'    => array( 'f_show_count' => '', 'f_hide_empty' => '1', 'f_wbw_style' => 'chips' ),
			),
			'swatches' => array(
				'label'    => __( 'Swatches', 'woo-product-filter' ),
				'desc'     => __( 'Colour dots for colour, squares for sizes, chips for the rest.', 'woo-product-filter' ),
				'type'     => 'list',
				'display_type' => 'swatches',
				'settings' => array( 'wbw_style' => 'swatches' ),
				'block'    => array( 'f_show_count' => '', 'f_hide_empty' => '1', 'f_wbw_style' => 'chips' ),
			),
			'compact'  => array(
				'label'    => __( 'Compact', 'woo-product-filter' ),
				'desc'     => __( 'Dropdowns in a tight row. Saves space above the products.', 'woo-product-filter' ),
				'type'     => 'dropdown',
				'display_type' => 'dropdown',
				'settings' => array( 'wbw_style' => 'compact', 'filter_block_width' => '25', 'filter_block_width_in' => '%' ),
				'block'    => array( 'f_show_count' => '', 'f_hide_empty' => '1' ),
			),
		);
	}

	/**
	 * Colours from theme.json (block themes) with a sensible fallback.
	 */
	public function getThemeColours() {
		$colours = array( 'accent' => '#171509', 'ink' => '#171509', 'paper' => '#ffffff' );
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = wp_get_global_settings( array( 'color', 'palette' ) );
			$list    = array();
			if ( is_array( $palette ) ) {
				foreach ( array( 'theme', 'custom', 'default' ) as $k ) {
					if ( ! empty( $palette[ $k ] ) && is_array( $palette[ $k ] ) ) {
						$list = array_merge( $list, $palette[ $k ] );
					}
				}
			}
			foreach ( $list as $c ) {
				if ( empty( $c['color'] ) || empty( $c['slug'] ) ) {
					continue;
				}
				$slug = strtolower( $c['slug'] );
				if ( in_array( $slug, array( 'primary', 'accent', 'vivid-cyan-blue', 'contrast' ), true ) && '#171509' === $colours['accent'] ) {
					$colours['accent'] = sanitize_hex_color( $c['color'] ) ? $c['color'] : $colours['accent'];
				}
				if ( in_array( $slug, array( 'base', 'background', 'white' ), true ) ) {
					$colours['paper'] = sanitize_hex_color( $c['color'] ) ? $c['color'] : $colours['paper'];
				}
				if ( in_array( $slug, array( 'contrast', 'foreground', 'black' ), true ) ) {
					$colours['ink'] = sanitize_hex_color( $c['color'] ) ? $c['color'] : $colours['ink'];
				}
			}
		}
		return $colours;
	}

	/* ------------------------------------------------------------------ settings builder */

	/**
	 * Build the full filter settings array (what the woofilters model saves) from wizard choices.
	 *
	 * $choices: array(
	 *   'title', 'blocks' => array of suggestion rows the owner left on (key, block, attr_id, name, kind),
	 *   'style' => clean|chips|swatches|compact, 'colours' => array,
	 *   'phone' => array( 'drawer' => 1, 'button_text' => 'Filters', 'chips' => 1, 'results_button' => 1 ),
	 *   'place' => sidebar|above|drawer|shortcode, 'selector' => 'ul.products', 'display_on_page' => 'both',
	 * )
	 */
	public function buildFilterData( $choices ) {
		$styles = $this->getStyles();
		$style  = isset( $choices['style'] ) && isset( $styles[ $choices['style'] ] ) ? $choices['style'] : 'clean';
		$styleD = $styles[ $style ];
		$phone  = isset( $choices['phone'] ) && is_array( $choices['phone'] ) ? $choices['phone'] : array();
		$place  = isset( $choices['place'] ) ? sanitize_key( $choices['place'] ) : 'above';
		if ( ! in_array( $place, array( 'sidebar', 'above', 'drawer', 'shortcode' ), true ) ) {
			$place = 'above';
		}

		$order = array();
		$i     = 0;
		foreach ( (array) $choices['blocks'] as $b ) {
			$block = isset( $b['block'] ) ? sanitize_text_field( $b['block'] ) : '';
			if ( ! preg_match( '/^wpf[A-Za-z]+$/', $block ) ) {
				continue;
			}
			++$i;
			$settings = array(
				'f_enable'              => true,
				'f_title'               => isset( $b['name'] ) ? sanitize_text_field( $b['name'] ) : '',
				'f_enable_title'        => 'yes_open',
				'f_enable_title_mobile' => 'yes_open',
				'logic'                 => 'or',
			);
			$kind     = isset( $b['kind'] ) ? $b['kind'] : '';
			// Long value lists start short: the five values with the most products, then "Show more" (4.0.0).
			// Sizes keep their natural order (S, M, L or 38 to 46): they fold by rows instead.
			if ( in_array( $block, array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand' ), true ) && 'size' !== ( isset( $b['kind'] ) ? $b['kind'] : '' ) ) {
				$settings['show_more_top'] = '5';
			}
			switch ( $block ) {
				case 'wpfAttribute':
					$settings['f_list']          = isset( $b['attr_id'] ) ? (string) absint( $b['attr_id'] ) : '';
					$settings['f_frontend_type'] = $styleD['type'];
					$settings                    = array_merge( $settings, $styleD['block'] );
					$settings['display_type']    = $styleD['display_type'];
					if ( 'swatches' === $style ) {
						// Squares fit short values (sizes); names such as brands or materials get chips that fit their text.
						$settings['display_type'] = ( 'colour' === $kind ) ? 'swatches' : ( 'size' === $kind ? 'squares' : 'chips' );
						$settings['f_wbw_style']  = ( 'colour' === $kind ) ? 'swatches' : 'chips';
					} elseif ( 'colour' === $kind && 'compact' !== $style ) {
						// A colour is picked faster from a dot than from a word (the card says so): colour swatches
						// in every look except Compact, which is dropdowns only.
						$settings['display_type'] = 'swatches';
						$settings['f_wbw_style']  = 'swatches';
					}
					break;
				case 'wpfCategory':
				case 'wpfTags':
				case 'wpfPerfectBrand':
				case 'wpfBrand':
					$settings['f_frontend_type'] = ( 'dropdown' === $styleD['type'] ) ? 'dropdown' : 'list';
					$settings                    = array_merge( $settings, $styleD['block'] );
					$settings['display_type']    = ( 'swatches' === $style ) ? 'chips' : $styleD['display_type'];
					break;
				case 'wpfInStock':
					$settings['f_options[]']  = 'instock,outofstock';
					$settings['display_type'] = 'toggle';
					break;
				case 'wpfOnSale':
				case 'wpfFeatured':
					$settings['display_type'] = 'toggle';
					break;
				case 'wpfPrice':
					$settings['display_type'] = 'slider';
					break;
				case 'wpfRating':
					$settings['display_type'] = 'list';
					break;
				case 'wpfSortBy':
					$settings['f_options[]']  = 'default,popularity';
					$settings['display_type'] = 'dropdown';
					break;
				default:
					// A block from the block registry (4.0: New arrivals, Size and weight, ...): its own defaults,
					// the wizard's title, and the display closest to the chosen look.
					$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::get( $block ) : null;
					if ( $def ) {
						if ( ! empty( $def['defaults'] ) && is_array( $def['defaults'] ) ) {
							$settings = array_merge( $def['defaults'], $settings );
						}
						if ( isset( $b['settings'] ) && is_array( $b['settings'] ) ) {
							foreach ( $b['settings'] as $k => $v ) {
								if ( is_string( $k ) && preg_match( '/^f_[a-z0-9_]+$/', $k ) && is_scalar( $v ) ) {
									$settings[ $k ] = sanitize_text_field( (string) $v );
								}
							}
						}
						$displays = empty( $def['displays'] ) ? array() : array_values( (array) $def['displays'] );
						$want     = ( 'compact' === $style ) ? 'dropdown' : ( in_array( $style, array( 'chips', 'swatches' ), true ) ? 'chips' : '' );
						if ( $want && in_array( $want, $displays, true ) ) {
							$settings['display_type'] = $want;
						} elseif ( $displays ) {
							$settings['display_type'] = $displays[0];
						}
					}
					break;
			}
			$order[] = array(
				'id'      => $block,
				'uniqId'  => uniqid( 'wpf_' ),
				'settings' => $settings,
			);
		}

		$main = array(
			'filters'                   => array( 'order' => wp_json_encode( $order ) ),
			'display_on_page'           => isset( $choices['display_on_page'] ) && in_array( $choices['display_on_page'], array( 'shop', 'category', 'tag', 'both' ), true ) ? $choices['display_on_page'] : 'both',
			'enable_ajax'               => '1',
			'auto_update_filter'        => '1',
			'filter_recount'            => '1',
			'filter_recount_price'      => '1',
			'show_filter_immediately'   => '1',
			'product_container_selector' => isset( $choices['selector'] ) ? sanitize_text_field( $choices['selector'] ) : '',
			'show_clean_button'         => '1',
			'show_clean_button_word'    => __( 'Clear', 'woo-product-filter' ),
			'main_buttons_position'     => 'bottom',
			'mobile_drawer'             => empty( $phone['drawer'] ) ? '' : '1',
			'mobile_drawer_text'        => isset( $phone['button_text'] ) && '' !== trim( $phone['button_text'] ) ? sanitize_text_field( $phone['button_text'] ) : __( 'Filters', 'woo-product-filter' ),
			'mobile_drawer_chips'       => empty( $phone['chips'] ) ? '' : '1',
			'mobile_drawer_results'     => empty( $phone['results_button'] ) ? '' : '1',
			'mobile_drawer_always'      => '',
			'remember_filters'          => empty( $phone['remember'] ) ? '' : '1',
			'wbw_placement'             => $place,
			'wbw_accent'                => isset( $choices['colours']['accent'] ) && sanitize_hex_color( $choices['colours']['accent'] ) ? $choices['colours']['accent'] : '',
		);
		if ( 'drawer' === $place ) {
			$main['mobile_drawer']       = '1';
			$main['mobile_drawer_always'] = '1';
		}
		$main = array_merge( $main, $styleD['settings'] );

		return array(
			'title'    => isset( $choices['title'] ) && '' !== trim( $choices['title'] ) ? sanitize_text_field( $choices['title'] ) : __( 'Shop filter', 'woo-product-filter' ),
			'settings' => $main,
		);
	}

	/* ------------------------------------------------------------------ drafts for the live preview */

	public function saveDraft( $choices, $draftId = '' ) {
		$draftId = preg_match( '/^[a-z0-9]{8,32}$/', (string) $draftId ) ? $draftId : strtolower( wp_generate_password( 16, false, false ) );
		$data    = $this->buildFilterData( $choices );
		// Publish saves through the filter model, which stores the 4.0 engine; the draft must preview that engine too
		// (a draft without one fell back to the classic engine, so the preview did not show what Publish puts live).
		if ( empty( $data['settings']['frontend_engine'] ) ) {
			$data['settings']['frontend_engine'] = '4';
		}
		set_transient( self::DRAFT_PREFIX . $draftId, $data, self::DRAFT_TTL );
		return $draftId;
	}

	/**
	 * Store a complete settings array (what the builder would save) as a draft for the live preview.
	 *
	 * @since 4.0.0
	 */
	public function saveDraftSettings( $title, $settings, $draftId = '' ) {
		$draftId = preg_match( '/^[a-z0-9]{8,32}$/', (string) $draftId ) ? $draftId : strtolower( wp_generate_password( 16, false, false ) );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		if ( empty( $settings['frontend_engine'] ) ) {
			$settings['frontend_engine'] = '4'; // as the builder's Save stores it for a new filter
		}
		$data = array(
			'title'    => sanitize_text_field( (string) $title ),
			'settings' => $settings,
		);
		set_transient( self::DRAFT_PREFIX . $draftId, $data, self::DRAFT_TTL );
		return $draftId;
	}

	/**
	 * Stable filter id used while a draft is previewed (never collides with real rows).
	 *
	 * @since 4.0.0
	 */
	public function previewId( $draftId ) {
		return 900000000 + ( crc32( (string) $draftId ) % 90000000 );
	}

	public function getDraft( $draftId ) {
		if ( ! preg_match( '/^[a-z0-9]{8,32}$/', (string) $draftId ) ) {
			return false;
		}
		$data = get_transient( self::DRAFT_PREFIX . $draftId );
		return is_array( $data ) ? $data : false;
	}

	public function previewUrl( $draftId, $place = '', $showOn = '' ) {
		$args = array( 'wpf_preview' => $draftId );
		if ( $place ) {
			$args['wpf_place'] = $place;
		}
		return add_query_arg( $args, $this->previewBase( $showOn ) );
	}

	/**
	 * A page the filter's "Show on" rule allows, so the preview shows what that page will show: the shop, the
	 * first category or tag with products, or a product page.
	 *
	 * @since 4.0.0
	 */
	public function previewBase( $showOn = '' ) {
		$tax = array(
			'category' => 'product_cat',
			'tag'      => 'product_tag',
		);
		if ( isset( $tax[ $showOn ] ) ) {
			$terms = get_terms( array( 'taxonomy' => $tax[ $showOn ], 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$link = get_term_link( $terms[0] );
				if ( ! is_wp_error( $link ) ) {
					return $link;
				}
			}
		}
		if ( 'product' === $showOn ) {
			$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
			if ( $ids ) {
				return get_permalink( $ids[0] );
			}
		}
		return $this->getShopUrl();
	}

	/* ------------------------------------------------------------------ publish and state */

	/**
	 * Create the real filter row from the wizard choices, store state, schedule the first week card.
	 */
	public function publish( $choices ) {
		$data   = $this->buildFilterData( $choices );
		$model  = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' );
		$id     = $model->save( array( 'title' => $data['title'], 'settings' => $data['settings'] ) );
		if ( ! $id ) {
			$this->pushError( $model->getErrors() );
			return false;
		}
		// Speed and SEO choices from step 6 go to the plugin options.
		$opts   = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel();
		$seo    = isset( $choices['speed'] ) && is_array( $choices['speed'] ) ? $choices['speed'] : array();
		$values = array();
		foreach ( array( 'wbw_cache_counts', 'wbw_clean_links', 'canonical_links', 'wbw_bot_limit', 'wbw_allow_ai_shoppers' ) as $key ) {
			$values[ $key ] = empty( $seo[ $key ] ) ? '0' : '1';
		}
		$opts->woobewoo_pf_save_group( array( 'opt_values' => $values ) );
		$paused = $this->pauseOtherFilters( (int) $id, $data['settings']['display_on_page'] );
		do_action( 'woobewoo_pf_setup_published', (int) $id, $choices );

		$state = array(
			'filter_id'    => (int) $id,
			'paused'       => $paused,
			'published'    => time(),
			'review_at'    => time() + 7 * DAY_IN_SECONDS,
			'review_done'  => 0,
			'store_type'   => isset( $choices['store_type'] ) ? sanitize_key( $choices['store_type'] ) : '',
			'style'        => isset( $choices['style'] ) ? sanitize_key( $choices['style'] ) : 'clean',
			'place'        => $data['settings']['wbw_placement'],
			'selector'     => $data['settings']['product_container_selector'],
			'blocks'       => wp_list_pluck( (array) $choices['blocks'], 'key' ),
			'phone'        => isset( $choices['phone'] ) ? array_map( 'sanitize_text_field', (array) $choices['phone'] ) : array(),
			'speed'        => array_map( 'absint', $seo ),
		);
		update_option( self::STATE_OPTION, $state, false );
		if ( ! wp_next_scheduled( self::FIRST_WEEK_EVENT ) ) {
			wp_schedule_single_event( $state['review_at'], self::FIRST_WEEK_EVENT );
		}
		return (int) $id;
	}

	/**
	 * One shop page, one filter. Every other filter the wizard placed on the same pages (above, sidebar
	 * or drawer) is switched to "shortcode only" and marked paused, so the shop does not show two filters
	 * after a new publish. Each change is a normal save, so History can undo it.
	 *
	 * @since 4.0.0
	 * @param int    $newId   The filter that was just published.
	 * @param string $newPage Its display_on_page value.
	 * @return array List of array( 'id' => int, 'title' => string ) that were paused.
	 */
	public function pauseOtherFilters( $newId, $newPage = 'both' ) {
		$model  = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' );
		$rows   = $model->getFromTbl();
		$paused = array();
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return $paused;
		}
		$newPage = in_array( $newPage, array( 'shop', 'category', 'tag', 'both' ), true ) ? $newPage : 'both';
		foreach ( $rows as $row ) {
			$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
			if ( ! $rid || $rid === (int) $newId ) {
				continue;
			}
			$data = woobewoo_pf_decode_settings( $row['setting_data'] );
			$s    = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
			$oldPlace = empty( $s['wbw_placement'] ) ? '' : $s['wbw_placement'];
			if ( empty( $s ) || ! empty( $s['wbw_draft'] ) || ! in_array( $oldPlace, array( 'above', 'sidebar', 'drawer' ), true ) ) {
				continue; // Draft, shortcode only, or placed by hand (widget, block, shortcode): nothing to pause.
			}
			$page = empty( $s['display_on_page'] ) ? 'shop' : $s['display_on_page'];
			if ( ! in_array( $page, array( 'shop', 'category', 'tag', 'both' ), true ) ) {
				continue; // Product pages, custom lists and Pro page rules are left alone.
			}
			$overlap = ( 'both' === $page || 'both' === $newPage || $page === $newPage );
			if ( ! $overlap ) {
				continue;
			}
			$s['wbw_placement']    = 'shortcode';
			$s['wbw_paused_place'] = $oldPlace;
			$s['wbw_paused_by']    = (int) $newId;
			$s['wbw_paused_at']    = time();
			$title = isset( $row['title'] ) ? (string) $row['title'] : '';
			if ( '' === trim( $title ) ) {
				/* translators: %d: filter id */
				$title = sprintf( __( 'Filter %d', 'woo-product-filter' ), $rid );
			}
			$ok = $model->save( array( 'id' => $rid, 'title' => $title, 'settings' => $s ) );
			if ( $ok ) {
				$paused[] = array( 'id' => $rid, 'title' => $title );
			}
		}
		return $paused;
	}

	public function getState() {
		$state = get_option( self::STATE_OPTION );
		return is_array( $state ) ? $state : array();
	}

	public function dismissFirstWeek() {
		$state = $this->getState();
		if ( $state ) {
			$state['review_done'] = time();
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	/**
	 * Cron: mark the first week as due (the card itself is date based, this just flags it).
	 */
	public function firstWeekEvent() {
		$state = $this->getState();
		if ( $state ) {
			$state['review_due'] = time();
			update_option( self::STATE_OPTION, $state, false );
		}
	}

	/**
	 * First week card data or false when it is not time yet / dismissed / never published.
	 */
	public function getFirstWeekCard() {
		$state = $this->getState();
		if ( empty( $state['review_at'] ) || ! empty( $state['review_done'] ) || time() < (int) $state['review_at'] ) {
			return false;
		}
		$suggestion = '';
		$scan       = $this->getScan();
		if ( ! $scan && $this->countProducts() <= self::SCAN_INLINE_MAX ) {
			// Small stores: refresh the numbers now. Big stores show the tip until the next wizard scan.
			$run  = $this->runScan( false, true );
			$scan = $run['scan'];
		}
		$filter = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( (int) $state['filter_id'] );
		if ( $scan && $filter ) {
			$settings = woobewoo_pf_decode_settings( $filter['setting_data'] );
			$order    = empty( $settings['settings']['filters']['order'] ) ? array() : WooBeWoo_PF_Utils::jsonDecode( $settings['settings']['filters']['order'] );
			$worst    = null;
			foreach ( (array) $order as $block ) {
				if ( 'wpfAttribute' === $block['id'] && ! empty( $block['settings']['f_list'] ) ) {
					foreach ( $scan['attributes'] as $attr ) {
						if ( (int) $attr['id'] === (int) $block['settings']['f_list'] ) {
							if ( is_null( $worst ) || $attr['fill'] < $worst['fill'] ) {
								$worst = $attr;
							}
						}
					}
				}
			}
			if ( $worst && $worst['fill'] < 90 ) {
				$missing    = max( 0, (int) $scan['products'] - (int) $worst['with_value'] );
				/* translators: 1: number of products, 2: attribute name */
				$suggestion = sprintf( __( '%1$s products have no %2$s. They disappear when shoppers pick one. Add the value to them, or turn that block off in the builder.', 'woo-product-filter' ), number_format_i18n( $missing ), $worst['name'] );
			} elseif ( ! empty( $scan['stock']['outofstock'] ) && (int) $scan['stock']['outofstock'] > (int) $scan['products'] * 0.2 ) {
				/* translators: %s: number of products */
				$suggestion = sprintf( __( '%s of your products are out of stock. Turn on "Hide choices with no products" in each block so shoppers never click a dead end.', 'woo-product-filter' ), number_format_i18n( (int) $scan['stock']['outofstock'] ) );
			}
		}
		if ( '' === $suggestion ) {
			$suggestion = __( 'One small change we suggest: open Speed and SEO and keep the counts cache on. Fewer queries, faster picks.', 'woo-product-filter' );
		}
		$names    = $filter ? WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'versions' )->blocksSummary( $filter['setting_data'] ) : array();
		$headline = $names
			/* translators: %s: list of block names */
			? sprintf( __( 'Your filter has been live for a week with %s. Here is what we noticed.', 'woo-product-filter' ), implode( ', ', $names ) )
			: __( 'Your filter has been live for a week. Here is what we noticed.', 'woo-product-filter' );
		return array(
			'filter_id'  => (int) $state['filter_id'],
			'published'  => isset( $state['published'] ) ? (int) $state['published'] : 0,
			'headline'   => $headline,
			'suggestion' => $suggestion,
			'button'     => __( 'Open the builder', 'woo-product-filter' ),
		);
	}
}
