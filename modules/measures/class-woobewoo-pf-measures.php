<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Measures Class
 *
 * Three Free blocks for the 4.0 storefront engine, registered through WooBeWoo_PF_Blocks:
 *   wpfDimensions  "Size and weight"   slider / range list over _weight, _length, _width or _height (wpf_weight ...)
 *   wpfStockQty    "Stock quantity"    slider / range list over the units in stock (wpf_qty)
 *   wpfDate        "New arrivals"      added in the last 7 / 14 / 30 / 90 days (wpf_added)
 * The module also adds the setup wizard's "New arrivals" and "Size and weight" suggestion cards and a small
 * stylesheet for the unit next to the slider numbers. On a classic (3.x engine) filter the blocks render nothing:
 * the classic view only knows its own block methods.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Measures extends WooBeWoo_PF_Module {

	/** Share of products with a weight (percent) above which the wizard suggests the size and weight block. */
	const WEIGHT_FILL_MIN = 30;

	/** Products newer than this many days make the wizard suggest the new arrivals block. */
	const NEW_DAYS = 30;

	/** @var bool stylesheet added for this request */
	private $cssAdded = false;

	public function init() {
		parent::init();
		add_action( 'woobewoo_pf_register_blocks', array( $this, 'registerBlocks' ) );
		add_filter( 'woobewoo_pf_free_blocks', array( $this, 'freeBlocks' ) );
		add_filter( 'woobewoo_pf_setup_items', array( $this, 'setupItems' ), 10, 3 );
		add_action( 'woobewoo_pf_frontend4_enqueue', array( $this, 'enqueue' ) );
	}

	/**
	 * Load the block classes and register them (fired once by the registry after plugins_loaded).
	 */
	public function registerBlocks() {
		if ( ! class_exists( 'WooBeWoo_PF_Blocks' ) || ! class_exists( 'WooBeWoo_PF_Query' ) ) {
			return;
		}
		$dir = $this->getModDir();
		require_once $dir . 'class-woobewoo-pf-measures-range.php';
		require_once $dir . 'blocks' . WPF_DS . 'class-woobewoo-pf-measures-dimensions.php';
		require_once $dir . 'blocks' . WPF_DS . 'class-woobewoo-pf-measures-stockqty.php';
		require_once $dir . 'blocks' . WPF_DS . 'class-woobewoo-pf-measures-date.php';
		WooBeWoo_PF_Measures_Dimensions::register();
		WooBeWoo_PF_Measures_StockQty::register();
		WooBeWoo_PF_Measures_Date::register();
	}

	/**
	 * Block ids available without Pro (the builder picker and the wizard).
	 */
	public function freeBlocks( $blocks ) {
		$blocks = (array) $blocks;
		foreach ( array( 'wpfDimensions', 'wpfStockQty', 'wpfDate' ) as $id ) {
			if ( ! in_array( $id, $blocks, true ) ) {
				$blocks[] = $id;
			}
		}
		return $blocks;
	}

	/**
	 * The storefront stylesheet (unit next to the slider numbers), once per request, when a 4.0 filter renders.
	 */
	public function enqueue() {
		if ( $this->cssAdded ) {
			return;
		}
		$this->cssAdded = true;
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-measures', $this->getModPath() . 'css/frontend.measures.css', array(), WPF_VERSION );
	}

	/* ------------------------------------------------------------------ setup wizard */

	/**
	 * Filter cards for the setup wizard, with an on/off switch and a live preview (both start off):
	 * "New arrivals" when products were added in the last 30 days, "Weight" (the Size and weight block)
	 * when more than 30% of the products have a weight.
	 *
	 * @since 4.0.0
	 *
	 * @param array  $items     Extra items so far (see woobewoo_pf_setup_items).
	 * @param array  $scan      The store scan (products, attributes, ...).
	 * @param string $storeType Benchmark store type.
	 * @return array
	 */
	public function setupItems( $items, $scan = array(), $storeType = '' ) {
		global $wpdb;
		$items = is_array( $items ) ? $items : array();
		$total = isset( $scan['products'] ) ? (int) $scan['products'] : 0;
		if ( $total <= 0 ) {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( $total <= 0 ) {
			return $items;
		}
		$since  = wp_date( 'Y-m-d H:i:s', time() - self::NEW_DAYS * DAY_IN_SECONDS );
		$recent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_date >= %s", $since ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $recent > 0 ) {
			$items[] = array(
				'key'       => 'new_arrivals',
				'kind'      => 'added',
				'block'     => 'wpfDate',
				'attribute' => '',
				'attr_id'   => 0,
				'name'      => __( 'New arrivals', 'woo-product-filter' ),
				'benchmark' => 0,
				'fill'      => (int) round( $recent / $total * 100 ),
				'values'    => 4,
				'missing'   => 0,
				'why'       => __( 'Shoppers who come back want to see what is new first.', 'woo-product-filter' ),
				'watch_out' => __( 'If you rarely add products, its choices match few or no products. Each choice shows how many it finds.', 'woo-product-filter' ),
				'speed'     => __( 'Low with the index', 'woo-product-filter' ),
				'suggested' => false,
				'pro_only'  => false,
				'reason'    => '',
				'fill_text' => sprintf(
					/* translators: 1: number of products, 2: number of days */
					_n( '%1$s added in the last %2$d days', '%1$s added in the last %2$d days', $recent, 'woo-product-filter' ),
					number_format_i18n( $recent ),
					self::NEW_DAYS
				),
				'fill_tone' => 'pass',
				'settings'  => array(),
			);
		}
		$weighed = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_weight' AND m.meta_value <> '' AND m.meta_value <> '0' WHERE p.post_type = 'product' AND p.post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$fill    = (int) round( $weighed / $total * 100 );
		if ( $fill > self::WEIGHT_FILL_MIN ) {
			$items[] = array(
				'key'       => 'measure_weight',
				'kind'      => 'dimensions',
				'block'     => 'wpfDimensions',
				'attribute' => '',
				'attr_id'   => 0,
				'name'      => __( 'Weight', 'woo-product-filter' ),
				'benchmark' => 0,
				'fill'      => $fill,
				'values'    => 0,
				'missing'   => max( 0, $total - $weighed ),
				'why'       => __( 'Shoppers who need something light or heavy find it with one slider.', 'woo-product-filter' ),
				'watch_out' => sprintf(
					/* translators: %s: number of products without a weight */
					__( '%s of your products have no weight. They disappear when a weight is picked. Length, width and height can be added in the builder.', 'woo-product-filter' ),
					number_format_i18n( max( 0, $total - $weighed ) )
				),
				'speed'     => __( 'Low with the index', 'woo-product-filter' ),
				'suggested' => false,
				'pro_only'  => false,
				'reason'    => '',
				'fill_text' => sprintf(
					/* translators: 1: products with a weight, 2: all products */
					__( '%1$s of %2$s have a weight', 'woo-product-filter' ),
					number_format_i18n( $weighed ),
					number_format_i18n( $total )
				),
				'fill_tone' => $fill > 60 ? 'pass' : 'partial',
				'settings'  => array( 'f_measure' => 'weight' ),
			);
		}
		return $items;
	}
}
