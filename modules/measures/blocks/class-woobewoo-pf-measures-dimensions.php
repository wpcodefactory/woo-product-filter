<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Measures_Dimensions Class
 *
 * "Size and weight" block (wpfDimensions): a slider or range list over one product measure (weight, length,
 * width or height from _weight / _length / _width / _height) in the store's WooCommerce units. One block per
 * measure, URL key wpf_weight, wpf_length, wpf_width or wpf_height with a "min-max" value. The facet index
 * carries the four meta keys (variations included, parent values as the fallback), so counts and bounds come
 * from wpf_index_meta.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Measures_Dimensions {

	const ID   = 'wpfDimensions';
	const KIND = 'dimensions';

	/** measure => meta key */
	const MEASURES = array(
		'weight' => '_weight',
		'length' => '_length',
		'width'  => '_width',
		'height' => '_height',
	);

	/** @var array catalogue bounds per measure for the request */
	private static $bounds = array();

	/**
	 * Register the block with the registry.
	 */
	public static function register() {
		WooBeWoo_PF_Blocks::register(
			self::ID,
			array(
				'kind'         => self::KIND,
				'label'        => __( 'Size and weight', 'woo-product-filter' ),
				'slug'         => 'dimensions',
				'unique'       => false, // up to four blocks, one per measure (a repeated measure is dropped by the engine)
				'content_type' => 'meta',
				'card'         => array(
					'icon'  => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h22"/><path d="M3 11v6M9 12v4M15 11v6M21 12v4M25 11v6"/><path d="M10 7l4-4 4 4"/></svg>',
					'group' => 'product',
					'spec'  => __( 'Weight, length, width or height', 'woo-product-filter' ),
				),
				'displays'     => array( 'slider', 'range_list', 'dropdown' ),
				'controls'     => self::controls(),
				'defaults'     => array(
					'f_title'                      => __( 'Weight', 'woo-product-filter' ),
					'f_measure'                    => 'weight',
					'f_unit_position'              => 'after',
					'f_show_count'                 => '1',
					'f_dropdown_first_option_text' => __( 'Any', 'woo-product-filter' ),
				),
				'question'     => __( 'How heavy or how big may it be?', 'woo-product-filter' ),
				'finder'       => true,
				'single'       => true,
				'key'          => array( __CLASS__, 'key' ),
				'match'        => array( __CLASS__, 'match' ),
				'parse'        => array( __CLASS__, 'parse' ),
				'param'        => array( __CLASS__, 'param' ),
				'sql'          => array( __CLASS__, 'sql' ),
				'facets'       => array( __CLASS__, 'facets' ),
				'index'        => array( 'meta_keys' => array_values( self::MEASURES ) ),
				'render'       => array( __CLASS__, 'render' ),
				'chip'         => array( __CLASS__, 'chip' ),
				'cli'          => 'weight',
			)
		);
	}

	/**
	 * Builder controls (plain words).
	 */
	private static function controls() {
		return array(
			array(
				'key'     => 'f_measure',
				'type'    => 'select',
				'label'   => __( 'Which measure', 'woo-product-filter' ),
				'help'    => __( 'From the product\'s shipping fields. One block per measure.', 'woo-product-filter' ),
				'options' => array(
					'weight' => __( 'Weight', 'woo-product-filter' ),
					'length' => __( 'Length', 'woo-product-filter' ),
					'width'  => __( 'Width', 'woo-product-filter' ),
					'height' => __( 'Height', 'woo-product-filter' ),
				),
				'default' => 'weight',
			),
			array(
				'key'   => 'f_step',
				'type'  => 'number',
				'label' => __( 'Slider moves in steps of', 'woo-product-filter' ),
				'help'  => __( 'Empty: picked for you from how far apart the lightest and heaviest products are.', 'woo-product-filter' ),
			),
			array(
				'key'     => 'f_unit_position',
				'type'    => 'select',
				'label'   => __( 'Unit goes', 'woo-product-filter' ),
				'options' => array(
					'after'  => __( 'After the number (2 kg)', 'woo-product-filter' ),
					'before' => __( 'Before the number (kg 2)', 'woo-product-filter' ),
				),
				'default' => 'after',
			),
			array(
				'key'   => 'f_unit_label',
				'type'  => 'text',
				'label' => __( 'Unit word', 'woo-product-filter' ),
				'help'  => __( 'Empty: the unit from your WooCommerce settings (kg, lbs, cm, in).', 'woo-product-filter' ),
			),
			array(
				'key'   => 'f_ranges',
				'type'  => 'text',
				'group' => 'look',
				'label' => __( 'Ranges, typed', 'woo-product-filter' ),
				'help'  => __( 'For the range list: "0-1,1-5,5-10". "10-" means over 10. Empty: five even steps.', 'woo-product-filter' ),
			),
			array(
				'key'   => 'f_show_count',
				'type'  => 'switch',
				'group' => 'look',
				'label' => __( 'Show how many products each range has', 'woo-product-filter' ),
			),
		);
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * The measure of a builder block: weight, length, width or height.
	 */
	public static function measure( $block ) {
		$m = isset( $block['settings']['f_measure'] ) ? sanitize_key( (string) $block['settings']['f_measure'] ) : '';
		return isset( self::MEASURES[ $m ] ) ? $m : 'weight';
	}

	/**
	 * The unit word of a measure: the block's own, else WooCommerce's weight or dimension unit.
	 */
	public static function unit( $block, $measure = '' ) {
		$own = isset( $block['settings']['f_unit_label'] ) ? trim( (string) $block['settings']['f_unit_label'] ) : '';
		if ( '' !== $own ) {
			return $own;
		}
		$measure = $measure ? $measure : self::measure( $block );
		return (string) get_option( 'weight' === $measure ? 'woocommerce_weight_unit' : 'woocommerce_dimension_unit', 'weight' === $measure ? 'kg' : 'cm' );
	}

	private static function position( $block ) {
		return isset( $block['settings']['f_unit_position'] ) && 'before' === $block['settings']['f_unit_position'] ? 'before' : 'after';
	}

	/* ------------------------------------------------------------------ query contract */

	/**
	 * URL key: the measure (wpf_weight, wpf_length, wpf_width, wpf_height).
	 */
	public static function key( $block ) {
		return self::measure( $block );
	}

	/**
	 * Claims the four measure keys.
	 */
	public static function match( $name ) {
		return isset( self::MEASURES[ $name ] );
	}

	public static function parse( $raw, $query, $key ) {
		WooBeWoo_PF_Measures_Range::store( $raw, $query, $key );
	}

	public static function param( $value ) {
		$r = WooBeWoo_PF_Measures_Range::parse( $value );
		return null === $r ? '' : WooBeWoo_PF_Query::format_range( $r );
	}

	/* ------------------------------------------------------------------ engine */

	/**
	 * The picked range as SQL: a product matches when one of its sellable rows (itself, or any variation of a
	 * variable product) carries the meta in range. Exact in both engine modes: on a parent row (product mode)
	 * any variation counts, on a leaf row (variation mode) only that row.
	 */
	public static function sql( $b, $query, $engine ) {
		global $wpdb;
		$r = WooBeWoo_PF_Measures_Range::picked( $query, $b['key'] );
		if ( null === $r ) {
			return null;
		}
		$between = WooBeWoo_PF_Measures_Range::sql_between( 'w.value_num', $r );
		if ( '' === $between ) {
			return null;
		}
		$metaKey = self::MEASURES[ self::measure( $b['block'] ) ];
		$exists  = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$engine->t['products']} v INNER JOIN {$engine->t['meta']} w ON w.product_id = v.product_id AND w.meta_key = %s WHERE v.root_id = l.root_id AND v.product_type <> 'variable' AND v.published = 1 AND (l.product_type = 'variable' OR v.product_id = l.product_id) AND ", $metaKey ) . $between . ')'; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- our own index table names; the range is a prepared fragment
		return array( 'where' => array( $exists ) );
	}

	/**
	 * Bounds (min, max) and per range counts over the narrowed set, one query per block.
	 */
	public static function facets( $blocks, $engine, $skipKey ) {
		global $wpdb;
		$out = array();
		foreach ( $blocks as $k => $b ) {
			$measure  = self::measure( $b['block'] );
			$metaKey  = self::MEASURES[ $measure ];
			$ranges   = self::ranges( $b, $engine );
			$lr       = $engine->leaf_rows( $skipKey );
			$row      = null;
			if ( $lr ) {
				$cols = array_merge( array( 'MIN(mw.value_num) AS mn', 'MAX(mw.value_num) AS mx' ), WooBeWoo_PF_Measures_Range::sql_count_columns( 'mw.value_num', $ranges ) );
				// The engine's FROM / WHERE are already prepared (they may hold literal % from a search): only the join is prepared here.
				$join = $wpdb->prepare( "INNER JOIN {$engine->t['meta']} mw ON mw.product_id = l.product_id AND mw.meta_key = %s", $metaKey ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own index table name
				$row  = $wpdb->get_row( 'SELECT ' . implode( ', ', $cols ) . " FROM {$lr['from']} $join WHERE {$lr['where']} AND mw.value_num IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- every piece is a prepared fragment or our own index table name
			}
			$out[ $k ] = array(
				'kind'   => 'meta', // the storefront script moves slider bounds for kind price|meta
				'min'    => ( $row && null !== $row['mn'] ) ? (float) $row['mn'] : 0,
				'max'    => ( $row && null !== $row['mx'] ) ? (float) $row['mx'] : 0,
				'counts' => WooBeWoo_PF_Measures_Range::counts_from_row( $row, $ranges ),
				'labels' => WooBeWoo_PF_Measures_Range::labels( $ranges, self::unit( $b['block'], $measure ), self::position( $b['block'] ) ),
			);
		}
		return $out;
	}

	/**
	 * Lowest and highest value of a measure in the whole catalogue (cached with the index version): the fixed
	 * base of automatic range lists, so the radio values stay the same while the counts change.
	 */
	private static function bounds( $measure, $engine ) {
		global $wpdb;
		if ( isset( self::$bounds[ $measure ] ) ) {
			return self::$bounds[ $measure ];
		}
		$ck  = $engine->plain_key( 'measures_bounds', array( $measure ) );
		$row = WooBeWoo_PF_Engine::cache_get( $ck );
		if ( ! is_array( $row ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT MIN(w.value_num) AS mn, MAX(w.value_num) AS mx FROM {$engine->t['products']} l INNER JOIN {$engine->t['meta']} w ON w.product_id = l.product_id AND w.meta_key = %s WHERE l.product_type <> 'variable' AND l.published = 1 AND w.value_num IS NOT NULL", self::MEASURES[ $measure ] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own index tables, names from WooBeWoo_PF_Index::tables()
			$row = array(
				'min' => ( $row && null !== $row['mn'] ) ? (float) $row['mn'] : 0,
				'max' => ( $row && null !== $row['mx'] ) ? (float) $row['mx'] : 0,
			);
			WooBeWoo_PF_Engine::cache_set( $ck, $row );
		}
		self::$bounds[ $measure ] = $row;
		return $row;
	}

	/**
	 * The step of a block: its setting, else one that suits the catalogue span.
	 */
	private static function step( $b, $engine ) {
		$step = (float) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_step' );
		if ( $step > 0 ) {
			return $step;
		}
		$bounds = self::bounds( self::measure( $b['block'] ), $engine );
		return WooBeWoo_PF_Measures_Range::auto_step( $bounds['min'], $bounds['max'] );
	}

	/**
	 * The range list of a block: typed ranges, else five even steps over the catalogue bounds.
	 */
	private static function ranges( $b, $engine ) {
		$bounds = self::bounds( self::measure( $b['block'] ), $engine );
		$typed  = isset( $b['block']['settings']['f_ranges'] ) ? (string) $b['block']['settings']['f_ranges'] : '';
		return WooBeWoo_PF_Measures_Range::ranges( $typed, $bounds['min'], $bounds['max'], self::step( $b, $engine ) );
	}

	/* ------------------------------------------------------------------ storefront */

	public static function render( $b, $display, $mobile, $view ) {
		$key      = $b['key'];
		$measure  = self::measure( $b['block'] );
		$unit     = self::unit( $b['block'], $measure );
		$position = self::position( $b['block'] );
		$cur      = WooBeWoo_PF_Measures_Range::picked( $view->query, $key );
		$facet    = isset( $view->facets[ $key ] ) ? $view->facets[ $key ] : array( 'min' => 0, 'max' => 0, 'counts' => array() );
		if ( 'slider' === $display ) {
			return WooBeWoo_PF_Measures_Range::slider( $view, $b, $key, $cur, $facet, self::step( $b, $view->engine ), $unit, $position, $mobile );
		}
		$counts = isset( $facet['counts'] ) ? $facet['counts'] : array();
		return WooBeWoo_PF_Measures_Range::range_list( $view, $b, $key, self::ranges( $b, $view->engine ), $cur, $counts, $unit, $position, 'dropdown' === $display ? 'dropdown' : 'list', $mobile );
	}

	/**
	 * Chip text: "Under 2 kg", "2 to 5 kg", "Over 5 kg".
	 */
	public static function chip( $value, $b ) {
		$r = WooBeWoo_PF_Measures_Range::parse( $value );
		if ( null === $r ) {
			return (string) $value;
		}
		$block = ( is_array( $b ) && isset( $b['block'] ) ) ? $b['block'] : array();
		return WooBeWoo_PF_Measures_Range::label( $r, self::unit( $block ), self::position( $block ) );
	}
}
