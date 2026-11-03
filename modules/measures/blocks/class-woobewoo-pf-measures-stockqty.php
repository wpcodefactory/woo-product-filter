<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Measures_StockQty Class
 *
 * "Stock quantity" block (wpfStockQty): a slider or range list over the units in stock (wpf_index_products
 * stock_qty; NULL = the product does not manage stock). URL key wpf_qty with a "min-max" value. With "Only
 * products that manage stock" on (the default) a picked range leaves products without a quantity out; off, they
 * stay in the results.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Measures_StockQty {

	const ID   = 'wpfStockQty';
	const KIND = 'stockqty';
	const KEY  = 'qty';

	/** @var array|null catalogue bounds for the request */
	private static $bounds = null;

	/**
	 * Register the block with the registry.
	 */
	public static function register() {
		WooBeWoo_PF_Blocks::register(
			self::ID,
			array(
				'kind'         => self::KIND,
				'label'        => __( 'Stock quantity', 'woo-product-filter' ),
				'slug'         => 'stock_qty',
				'unique'       => true,
				'content_type' => 'meta',
				'card'         => array(
					'icon'  => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="9" width="8" height="8" rx="1.5"/><rect x="11" y="9" width="8" height="8" rx="1.5"/><rect x="7" y="2" width="8" height="7" rx="1.5"/><path d="M22 6v10M19 13l3 3 3-3"/></svg>',
					'group' => 'product',
					'spec'  => __( 'How many units are left', 'woo-product-filter' ),
				),
				'displays'     => array( 'slider', 'range_list', 'dropdown' ),
				'controls'     => self::controls(),
				'defaults'     => array(
					'f_title'                      => __( 'In stock', 'woo-product-filter' ),
					'f_managed_only'               => '1',
					'f_show_count'                 => '1',
					'f_dropdown_first_option_text' => __( 'Any', 'woo-product-filter' ),
				),
				'question'     => __( 'How many do you need in stock?', 'woo-product-filter' ),
				'finder'       => true,
				'single'       => true,
				'key'          => self::KEY,
				'parse'        => array( __CLASS__, 'parse' ),
				'param'        => array( __CLASS__, 'param' ),
				'sql'          => array( __CLASS__, 'sql' ),
				'facets'       => array( __CLASS__, 'facets' ),
				'render'       => array( __CLASS__, 'render' ),
				'chip'         => array( __CLASS__, 'chip' ),
				'cli'          => 'qty',
			)
		);
	}

	private static function controls() {
		return array(
			array(
				'key'     => 'f_managed_only',
				'type'    => 'switch',
				'label'   => __( 'Only products that manage stock', 'woo-product-filter' ),
				'help'    => __( 'On: a picked range leaves out products with no stock number. Off: they stay in.', 'woo-product-filter' ),
				'default' => '1',
			),
			array(
				'key'   => 'f_step',
				'type'  => 'number',
				'label' => __( 'Slider moves in steps of', 'woo-product-filter' ),
				'help'  => __( 'Whole units. Empty: 1.', 'woo-product-filter' ),
			),
			array(
				'key'   => 'f_ranges',
				'type'  => 'text',
				'group' => 'look',
				'label' => __( 'Ranges, typed', 'woo-product-filter' ),
				'help'  => __( 'For the range list: "1-5,5-20,20-". "20-" means over 20. Empty: five even steps.', 'woo-product-filter' ),
			),
			array(
				'key'   => 'f_show_count',
				'type'  => 'switch',
				'group' => 'look',
				'label' => __( 'Show how many products each range has', 'woo-product-filter' ),
			),
		);
	}

	/* ------------------------------------------------------------------ query contract */

	public static function parse( $raw, $query, $key ) {
		WooBeWoo_PF_Measures_Range::store( $raw, $query, $key );
	}

	public static function param( $value ) {
		$r = WooBeWoo_PF_Measures_Range::parse( $value );
		return null === $r ? '' : WooBeWoo_PF_Query::format_range( $r );
	}

	/* ------------------------------------------------------------------ engine */

	/**
	 * The picked range as SQL over the sellable rows (a simple product, or any variation of a variable one).
	 * Exact in both engine modes, like the size and weight block.
	 */
	public static function sql( $b, $query, $engine ) {
		$r = WooBeWoo_PF_Measures_Range::picked( $query, $b['key'] );
		if ( null === $r ) {
			return null;
		}
		$between = WooBeWoo_PF_Measures_Range::sql_between( 'GREATEST(v.stock_qty, 0)', $r, true );
		if ( '' === $between ) {
			return null;
		}
		$cond = self::managed_only( $b['block'] ) ? $between : "(v.stock_qty IS NULL OR ($between))";
		return array(
			'where' => array( "EXISTS (SELECT 1 FROM {$engine->t['products']} v WHERE v.root_id = l.root_id AND v.product_type <> 'variable' AND v.published = 1 AND (l.product_type = 'variable' OR v.product_id = l.product_id) AND $cond)" ),
		);
	}

	/**
	 * Bounds and per range counts over the narrowed set (rows without a quantity are left out of both).
	 */
	public static function facets( $blocks, $engine, $skipKey ) {
		global $wpdb;
		$out = array();
		foreach ( $blocks as $k => $b ) {
			$ranges = self::ranges( $b, $engine );
			$lr     = $engine->leaf_rows( $skipKey );
			$row    = null;
			if ( $lr ) {
				$cols = array_merge( array( 'MIN(GREATEST(l.stock_qty, 0)) AS mn', 'MAX(GREATEST(l.stock_qty, 0)) AS mx' ), WooBeWoo_PF_Measures_Range::sql_count_columns( 'GREATEST(l.stock_qty, 0)', $ranges, true ) );
				$row  = $wpdb->get_row( 'SELECT ' . implode( ', ', $cols ) . " FROM {$lr['from']} WHERE {$lr['where']} AND l.stock_qty IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the engine's FROM / WHERE and the count columns are prepared fragments over our own index tables
			}
			$out[ $k ] = array(
				'kind'   => 'meta', // the storefront script moves slider bounds for kind price|meta
				'min'    => ( $row && null !== $row['mn'] ) ? (int) $row['mn'] : 0,
				'max'    => ( $row && null !== $row['mx'] ) ? (int) $row['mx'] : 0,
				'counts' => WooBeWoo_PF_Measures_Range::counts_from_row( $row, $ranges ),
				'labels' => WooBeWoo_PF_Measures_Range::labels( $ranges, self::unit() ),
			);
		}
		return $out;
	}

	/**
	 * Lowest and highest quantity in the catalogue (cached with the index version): the base of automatic ranges.
	 */
	private static function bounds( $engine ) {
		global $wpdb;
		if ( is_array( self::$bounds ) ) {
			return self::$bounds;
		}
		$ck  = $engine->plain_key( 'measures_qty_bounds' );
		$row = WooBeWoo_PF_Engine::cache_get( $ck );
		if ( ! is_array( $row ) ) {
			$row = $wpdb->get_row( "SELECT MIN(GREATEST(l.stock_qty, 0)) AS mn, MAX(GREATEST(l.stock_qty, 0)) AS mx FROM {$engine->t['products']} l WHERE l.product_type <> 'variable' AND l.published = 1 AND l.stock_qty IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own index table, name from WooBeWoo_PF_Index::tables()
			$row = array(
				'min' => ( $row && null !== $row['mn'] ) ? (int) $row['mn'] : 0,
				'max' => ( $row && null !== $row['mx'] ) ? (int) $row['mx'] : 0,
			);
			WooBeWoo_PF_Engine::cache_set( $ck, $row );
		}
		self::$bounds = $row;
		return $row;
	}

	private static function managed_only( $block ) {
		return ! array_key_exists( 'f_managed_only', isset( $block['settings'] ) ? (array) $block['settings'] : array() ) || (bool) WooBeWoo_PF_Frontend4::block_setting( $block, 'f_managed_only' );
	}

	private static function step( $b ) {
		$step = (int) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_step' );
		return $step > 0 ? $step : 1;
	}

	private static function ranges( $b, $engine ) {
		$bounds = self::bounds( $engine );
		$typed  = isset( $b['block']['settings']['f_ranges'] ) ? (string) $b['block']['settings']['f_ranges'] : '';
		return WooBeWoo_PF_Measures_Range::ranges( $typed, $bounds['min'], $bounds['max'], max( 1, self::step( $b ) ) );
	}

	/**
	 * The word after the number: "in stock" ("Under 10 in stock").
	 */
	private static function unit() {
		return __( 'in stock', 'woo-product-filter' );
	}

	/* ------------------------------------------------------------------ storefront */

	public static function render( $b, $display, $mobile, $view ) {
		$key   = $b['key'];
		$cur   = WooBeWoo_PF_Measures_Range::picked( $view->query, $key );
		$facet = isset( $view->facets[ $key ] ) ? $view->facets[ $key ] : array( 'min' => 0, 'max' => 0, 'counts' => array() );
		if ( 'slider' === $display ) {
			return WooBeWoo_PF_Measures_Range::slider( $view, $b, $key, $cur, $facet, self::step( $b ), '', 'after', $mobile );
		}
		$counts = isset( $facet['counts'] ) ? $facet['counts'] : array();
		return WooBeWoo_PF_Measures_Range::range_list( $view, $b, $key, self::ranges( $b, $view->engine ), $cur, $counts, self::unit(), 'after', 'dropdown' === $display ? 'dropdown' : 'list', $mobile );
	}

	/**
	 * Chip text: "Under 10 in stock", "10 to 50 in stock", "Over 50 in stock".
	 */
	public static function chip( $value ) {
		$r = WooBeWoo_PF_Measures_Range::parse( $value );
		return null === $r ? (string) $value : WooBeWoo_PF_Measures_Range::label( $r, self::unit() );
	}
}
