<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Frontend4 Class
 *
 * The 4.0 storefront engine runtime: picks the engine per filter (4 or legacy), feeds the main product query from
 * the facet index, serves the JSON endpoint the vanilla JS talks to, hooks the WooCommerce loop for
 * "variations as products", runs the structural accessibility report and renders the WCAG badge shortcode.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Frontend4 {

	const AJAX_QUERY = 'woobewoo_pf_query';
	const AJAX_A11Y  = 'woobewoo_pf_a11y_report';
	const OPT_A11Y   = 'wpf_a11y_reports';
	const VERSION    = '4.0.0';

	/** @var WooBeWoo_PF_Woofilters */
	public static $module = null;
	/** @var array|null the request's active filter context: filter, settings, blocks, query, engine, ctx */
	public static $current = null;
	private static $filters = null;

	/** True while an add-on grid AJAX request (load more, pagination) carries the filter in `with_wpf_filter`. */
	public static $addonAjax = false;

	public static function flush_filters() {
		self::$filters = null;
	}
	private static $schema  = null;
	private static $booted  = false;
	private static $loopHooked = false;
	private static $assetsDone = false;
	private static $variationCache = array();

	/* ------------------------------------------------------------------ boot */

	public static function boot( $module ) {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		self::$module = $module;
		$dir          = WPF_MODULES_DIR . 'index' . WPF_DS;
		foreach ( array( 'index', 'query', 'engine', 'bot-cache' ) as $f ) {
			if ( ! class_exists( 'WooBeWoo_PF_' . str_replace( '-', '_', ucwords( $f, '-' ) ) ) ) {
				require_once $dir . 'class-woobewoo-pf-' . $f . '.php';
			}
		}
		WooBeWoo_PF_Index::boot();
		WooBeWoo_PF_Bot_Cache::boot();
		// 4.0.0: the block registry (also under WP-CLI and REST, where the woofilters module boots this early).
		if ( ! class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			require_once WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'class-woobewoo-pf-blocks.php';
		}
		WooBeWoo_PF_Blocks::boot();

		if ( ! class_exists( 'WooBeWoo_PF_Engine_Migration' ) ) {
			require_once WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'class-woobewoo-pf-engine-migration.php';
		}
		WooBeWoo_PF_Engine_Migration::boot();

		// Page-builder product grids (Essential Addons, Ultimate Addons, Element Pack, JetWooBuilder, Divi Plus...):
		// their own WP_Query gets the engine's ids, on the page and in their load-more / pagination AJAX calls.
		add_action( 'init', array( __CLASS__, 'readAddonParams' ), 1 );
		add_action( 'pre_get_posts', array( __CLASS__, 'onAddonPreGetPosts' ), 998 );
		add_filter( 'uael_woo_product_query_args', array( __CLASS__, 'onAddonQueryArgs' ), 1000, 2 );
		add_filter( 'jet-woo-builder/shortcodes/jet-woo-products/final-query-args', array( __CLASS__, 'onAddonQueryArgs' ), 1000, 1 );
		add_filter( 'dipl_woo_products_args', array( __CLASS__, 'onAddonQueryArgs' ), 1000, 1 );
		add_filter( 'woobewoo_pf_frontend4_active_filter', array( __CLASS__, 'addonActiveFilter' ), 5, 2 );

		add_filter( 'query_vars', array( __CLASS__, 'queryVars' ) );
		add_action( 'wp_ajax_' . self::AJAX_QUERY, array( __CLASS__, 'ajaxQuery' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_QUERY, array( __CLASS__, 'ajaxQuery' ) );
		add_action( 'wp_ajax_' . self::AJAX_A11Y, array( __CLASS__, 'ajaxA11yReport' ) );
		add_shortcode( 'wpf-a11y-badge', array( __CLASS__, 'badgeShortcode' ) );

		if ( ! is_admin() ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'onPreGetPosts' ), 1 );
			add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'onBlockQueryVars' ), 1000, 3 );
			add_filter( 'woocommerce_shortcode_products_query', array( __CLASS__, 'onShortcodeQuery' ), 1000, 3 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybeEnqueue' ), 20 );
		}
		// Pro probes: the index exposes variation level stock.
		add_filter( 'woobewoo_pf_has_variation_stock_index', array( __CLASS__, 'hasVariationStockIndex' ), 20 );
		add_filter( 'woobewoo_pf_variation_stock_lookup', array( __CLASS__, 'variationStockLookup' ), 20, 3 );
		// Save hooks: a filter save drops cached counts (settings change the blocks) and refreshes indexed meta keys.
		add_filter( 'woobewoo_pf_filter_settings_saving_data', array( __CLASS__, 'onFilterSave' ), 10, 3 );
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_filter_settings_saving_data', array( __CLASS__, 'onFilterSave' ), 10, 3 );
		// History restore writes the row directly: drop the cached counts and pages the same way.
		add_action( 'woobewoo_pf_filter_restored', array( __CLASS__, 'onFilterRestored' ) );
	}

	/* ------------------------------------------------------------------ page-builder grids */

	/** admin-ajax actions of add-on grids whose "load more" / pagination calls carry the filter. */
	public static function addonActions() {
		return apply_filters(
			'woobewoo_pf_frontend4_addon_actions',
			array( 'load_more', 'woo_product_pagination_product', 'woo_product_pagination', 'get_woo_products', 'uael_get_products', 'divi_filter_loadmore_ajax_handler', 'bdt_ep_products_load_more', 'element_pack_load_more' )
		);
	}

	/** Wrappers of add-on grids: the script swaps their content from the filtered page instead of the WooCommerce loop. */
	public static function addonWrappers() {
		return apply_filters(
			'woobewoo_pf_frontend4_addon_wrappers',
			array( '.eael-product-grid', '.eael-woo-product-gallery', '.hfe-woo-products-wrapper', '.uael-woo-products', '.uael-woocommerce', '.bdt-wc-products', '.bdt-ep-wc-products', '.bdt-wc-carousel', '.jet-woo-products', '.dipl_woo_products' )
		);
	}

	/**
	 * A load-more / pagination call of an add-on grid: the script appended `with_wpf_filter` (the filter's query
	 * string) and `wpf4_filter` (its id). Read them into the request so WooBeWoo_PF_Query::from_request() sees them.
	 */
	public static function readAddonParams() {
		if ( ! wp_doing_ajax() || empty( $_POST['with_wpf_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $action, self::addonActions(), true ) ) {
			return;
		}
		parse_str( (string) wp_unslash( $_POST['with_wpf_filter'] ), $params ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( (array) $params as $k => $v ) {
			if ( 0 === strpos( (string) $k, 'wpf_' ) && is_scalar( $v ) ) {
				$_GET[ $k ] = sanitize_text_field( (string) $v );
			}
		}
		if ( ! empty( $_POST['wpf4_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_GET['wpf4_filter'] = (int) $_POST['wpf4_filter']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		self::$addonAjax = true;
	}

	/** `wpf4_filter=<id>` (add-on AJAX calls) names the filter that drives the request. */
	public static function addonActiveFilter( $found, $wpQuery ) {
		$id = isset( $_GET['wpf4_filter'] ) ? (int) $_GET['wpf4_filter'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $id ) {
			$f = self::filter( $id );
			if ( $f && self::is_engine4( $f['id'], $f['settings'] ) ) {
				return $f;
			}
		}
		return $found;
	}

	/**
	 * Is this WP_Query built by a page-builder product grid widget? (Same detection as the classic engine: the widget
	 * class sits in the call stack.)
	 */
	private static function fromAddonGrid() {
		$classes = apply_filters(
			'woobewoo_pf_frontend4_addon_classes',
			array(
				'Essential_Addons_Elementor\\Elements\\Product_Grid',
				'Essential_Addons_Elementor\\Elements\\Woo_Product_Gallery',
				'Essential_Addons_Elementor\\Pro\\Elements\\Woo_Product_Slider',
				'ElementPack\\Modules\\Woocommerce\\Widgets\\Products',
				'ElementPack\\Modules\\Woocommerce\\Widgets\\WC_Carousel',
				'ElementPack\\Modules\\Woocommerce\\Widgets\\Products_Grid',
				'DynamicContentForElementor\\Widgets\\DCE_Widget_DynamicPosts_Base',
				'DynamicContentForElementor\\Widgets\\DynamicPostsBase',
				'HFE\\WidgetsManager\\',
				'UltimateElementor\\',
				'Essential_Addons_Elementor\\Pro\\',
				'JetWooBuilder\\',
				'DiviPlus\\',
			)
		);
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 25 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			if ( ! empty( $frame['class'] ) ) {
				foreach ( $classes as $c ) {
					if ( $frame['class'] === $c || 0 === strpos( $frame['class'], $c ) ) {
						return true;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Secondary product queries of add-on grids on a page with an engine 4 filter (and their AJAX calls): the
	 * engine's ids, in the engine's order unless the widget sorts itself.
	 */
	public static function onAddonPreGetPosts( $q ) {
		if ( $q->is_main_query() || $q->get( 'wpf4' ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$module = self::$module ? self::$module : WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		if ( ! $module || ! method_exists( $module, 'isProductQuery' ) || ! $module->isProductQuery( $q->get( 'post_type' ) ) ) {
			return;
		}
		if ( ! self::$addonAjax && ! self::fromAddonGrid() ) {
			return;
		}
		$ids = self::addonIds();
		if ( null === $ids ) {
			return;
		}
		$have = $q->get( 'post__in' );
		if ( ! empty( $have ) && is_array( $have ) ) {
			$ids = array_values( array_intersect( array_map( 'intval', $have ), $ids ) );
		}
		$q->set( 'post__in', $ids ? $ids : array( 0 ) );
		if ( ! $q->get( 'orderby' ) || 'menu_order title' === $q->get( 'orderby' ) ) {
			$q->set( 'orderby', 'post__in' );
		}
		$q->set( 'wpf4', 1 );
	}

	/** Same for widgets that build their query through a filter on the args (Ultimate Addons, JetWooBuilder, Divi Plus). */
	public static function onAddonQueryArgs( $args, $settings = null ) {
		if ( ! is_array( $args ) || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $args;
		}
		$ids = self::addonIds();
		if ( null === $ids ) {
			return $args;
		}
		if ( ! empty( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
			$ids = array_values( array_intersect( array_map( 'intval', $args['post__in'] ), $ids ) );
		}
		$args['post__in'] = $ids ? $ids : array( 0 );
		if ( empty( $args['orderby'] ) || 'menu_order title' === $args['orderby'] ) {
			$args['orderby'] = 'post__in';
		}
		$args['wpf4'] = 1;
		return $args;
	}

	/** Engine ids for the current request, or null when no engine 4 filter drives it or nothing is picked. */
	private static function addonIds() {
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}
		$filter = self::active_filter();
		if ( ! $filter ) {
			$cache = null;
			return null;
		}
		$query = WooBeWoo_PF_Query::from_request();
		if ( $query->is_empty() ) {
			$cache = null;
			return null;
		}
		$eng = self::make_engine( $filter, $query );
		if ( ! self::$current ) {
			self::begin_request( $filter, $query, self::page_context( $filter['settings'] ), $eng );
		}
		$cache = array_map( 'intval', (array) $eng->all_ids( $query->sort ) );
		return $cache;
	}


	public static function onFilterRestored() {
		WooBeWoo_PF_Index::bump_version();
		self::$filters = null;
	}

	public static function queryVars( $vars ) {
		return array_values( array_unique( array_merge( (array) $vars, WooBeWoo_PF_Query::query_vars() ) ) );
	}

	public static function onFilterSave( $data, $settings, $update ) {
		WooBeWoo_PF_Index::bump_version();
		self::$filters = null;
		return $data;
	}

	/* ------------------------------------------------------------------ settings */

	public static function schema() {
		if ( null === self::$schema ) {
			self::$schema = include WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'settings-schema.php';
		}
		return self::$schema;
	}

	/**
	 * A typed filter level setting with the schema default.
	 */
	public static function setting( $settings, $key, $default = null ) {
		$schema = self::schema();
		$def    = isset( $schema[ $key ] ) ? $schema[ $key ] : array( 'type' => 'string', 'default' => $default );
		if ( null !== $default ) {
			$def['default'] = $default;
		}
		$s = ( isset( $settings['settings'] ) && is_array( $settings['settings'] ) ) ? $settings['settings'] : $settings;
		if ( ! is_array( $s ) || ! array_key_exists( $key, $s ) ) {
			return $def['default'];
		}
		return self::cast( $s[ $key ], $def );
	}

	/**
	 * A typed block level setting with the schema default.
	 */
	public static function block_setting( $block, $key, $default = null ) {
		$schema = self::schema();
		$def    = isset( $schema[ $key ] ) ? $schema[ $key ] : array( 'type' => 'string', 'default' => $default );
		if ( null !== $default ) {
			$def['default'] = $default;
		}
		$s = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		if ( ! array_key_exists( $key, $s ) ) {
			return $def['default'];
		}
		return self::cast( $s[ $key ], $def );
	}

	private static function cast( $v, $def ) {
		$type = isset( $def['type'] ) ? $def['type'] : 'string';
		switch ( $type ) {
			case 'bool':
				if ( is_string( $v ) ) {
					$v = strtolower( trim( $v ) );
					return ! in_array( $v, array( '', '0', 'no', 'false', 'off' ), true );
				}
				return (bool) $v;
			case 'int':
				return (int) $v;
			case 'number':
				return is_numeric( $v ) ? (float) $v : $def['default'];
			case 'enum':
				$v = sanitize_key( (string) $v );
				return ( '' !== $v && in_array( $v, (array) $def['options'], true ) ) ? $v : $def['default'];
			case 'hex':
				$h = sanitize_hex_color( (string) $v );
				return $h ? $h : $def['default'];
			case 'array':
				if ( is_string( $v ) ) {
					$d = json_decode( $v, true );
					$v = is_array( $d ) ? $d : array();
				}
				return is_array( $v ) ? wp_parse_args( $v, (array) $def['default'] ) : $def['default'];
		}
		return is_scalar( $v ) ? (string) $v : $def['default'];
	}

	/**
	 * Storefront engine of a filter: 4 or legacy.
	 */
	public static function engine_of( $id, $settings ) {
		$s = ( isset( $settings['settings'] ) && is_array( $settings['settings'] ) ) ? $settings['settings'] : (array) $settings;
		if ( isset( $s['frontend_engine'] ) && '' !== $s['frontend_engine'] ) {
			// 'classic' was the builder's option key in early 4.0 builds; both mean the 3.x engine.
			return in_array( (string) $s['frontend_engine'], array( 'legacy', 'classic' ), true ) ? 'legacy' : '4';
		}
		$before = get_option( 'wpf_filters_before_4', false );
		if ( false === $before ) {
			// 4.0.1: no upgrade snapshot in this request (it is being written by a concurrent request): a row without
			// a stored engine is a 3.x filter.
			return 'legacy';
		}
		if ( is_array( $before ) && in_array( (int) $id, array_map( 'intval', $before ), true ) ) {
			return 'legacy';
		}
		$seen = get_option( 'wpf_filters_engine4_seen', array() );
		if ( is_array( $seen ) && in_array( (int) $id, array_map( 'intval', $seen ), true ) ) {
			return '4';
		}
		// No engine stored: every 4.0 save stores one (WooBeWoo_PF_Woofilters_Model::_dataSave()), so this row was
		// written by 3.x after the upgrade marker was taken (a rollback to 3.4.7, a database copy): keep the 3.x engine.
		return (string) apply_filters( 'woobewoo_pf_default_frontend_engine', 'legacy', $id, $settings );
	}

	public static function is_engine4( $id, $settings ) {
		return '4' === self::engine_of( $id, $settings );
	}

	/**
	 * Does the request carry a 4.0 filter parameter (wpf_size, wpf_price, pretty link vars...) and not only the
	 * 3.x names a classic filter writes (wpf_filter_*, wpf_min_price, pr_*, product_tag_N, wpf_fbv...)?
	 */
	public static function request_has_engine4_params() {
		$keys = array_keys( isset( $_GET ) && is_array( $_GET ) ? $_GET : array() ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars ) ) {
			$keys = array_merge( $keys, array_keys( (array) $GLOBALS['wp']->query_vars ) );
		}
		foreach ( $keys as $k ) {
			$k = (string) $k;
			if ( 0 === strpos( $k, 'wpf_' ) && ! preg_match( '/^wpf_(filter_.+|min_price|max_price|fbv|dpv|ebv|count|skip|fid|id|oistock|reload|preselects|preview|page_type)$/', $k ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Filters made before 4.0 (in the upgrade marker, or written by 3.x later without an engine): how many, how many
	 * still run the classic engine, how many the owner switched to 4.0, and the ones whose settings cannot be read.
	 *
	 * @return array total, classic, switched, errors (list of "title: reason").
	 */
	public static function legacy_report() {
		$before = array_map( 'intval', (array) get_option( 'wpf_filters_before_4', array() ) );
		$seen   = array_map( 'intval', (array) get_option( 'wpf_filters_engine4_seen', array() ) );
		$out    = array( 'total' => 0, 'classic' => 0, 'switched' => 0, 'errors' => array() );
		foreach ( self::filters() as $f ) {
			$s        = $f['settings']['settings'];
			$explicit = isset( $s['frontend_engine'] ) && '' !== (string) $s['frontend_engine'];
			$old      = in_array( $f['id'], $before, true ) || ( ! $explicit && ! in_array( $f['id'], $seen, true ) );
			if ( ! $old ) {
				continue;
			}
			$out['total']++;
			if ( self::is_engine4( $f['id'], $f['settings'] ) ) {
				$out['switched']++;
			} else {
				$out['classic']++;
			}
			$order = empty( $s['filters']['order'] ) ? null : json_decode( (string) $s['filters']['order'], true );
			if ( empty( $s ) ) {
				$out['errors'][] = $f['title'] . ': ' . __( 'settings cannot be read', 'woo-product-filter' );
			} elseif ( ! is_array( $order ) ) {
				$out['errors'][] = $f['title'] . ': ' . __( 'no blocks', 'woo-product-filter' );
			}
		}
		return $out;
	}

	/**
	 * Does any filter still run the legacy storefront engine? (Decides whether the jQuery helpers load.)
	 */
	public static function has_legacy_filters() {
		static $has = null;
		if ( null === $has ) {
			$has = false;
			foreach ( self::filters() as $f ) {
				if ( ! self::is_engine4( $f['id'], $f['settings'] ) ) {
					$has = true;
					break;
				}
			}
		}
		return $has;
	}

	/**
	 * Enqueue the engine 4 assets early when a filter shows on this page (no flash of unstyled filter).
	 */
	public static function maybeEnqueue() {
		$f = self::active_filter();
		if ( $f ) {
			self::enqueue( $f );
		}
	}

	/**
	 * All filters: id => array( id, title, settings ).
	 */
	public static function filters() {
		global $wpdb;
		if ( null !== self::$filters ) {
			return self::$filters;
		}
		self::$filters = array();
		$rows = $wpdb->get_results( 'SELECT id, title, setting_data FROM `' . $wpdb->prefix . 'wpf_filters` ORDER BY id ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $r ) {
			// 4.0 stores JSON and reads the old serialized rows transparently.
			$s = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( $r['setting_data'] ) : maybe_unserialize( $r['setting_data'] );
			if ( ! is_array( $s ) ) {
				$s = array();
			}
			if ( ! isset( $s['settings'] ) || ! is_array( $s['settings'] ) ) {
				$s['settings'] = array();
			}
			self::$filters[ (int) $r['id'] ] = array(
				'id'       => (int) $r['id'],
				'title'    => (string) $r['title'],
				'settings' => $s,
			);
		}
		return self::$filters;
	}

	/**
	 * A filter by id, or a wizard / builder draft by "draft_<id>" (the transient the setup module writes).
	 */
	public static function filter( $id ) {
		if ( is_string( $id ) && ( 0 === strpos( $id, 'draft_' ) || preg_match( '/^[a-z0-9]{8,32}$/', $id ) ) && ! ctype_digit( $id ) ) {
			return self::draft( preg_replace( '/^draft_/', '', $id ) );
		}
		$all = self::filters();
		if ( ! isset( $all[ (int) $id ] ) ) {
			self::$filters = null; // a filter saved in this request
			$all           = self::filters();
		}
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	public static function reset_cache() {
		self::$filters = null;
	}

	/**
	 * A draft (unsaved settings) as a filter row: array( id => preview id, draft => draft id, title, settings ).
	 */
	public static function draft( $draftId ) {
		$draftId = (string) $draftId;
		if ( ! preg_match( '/^[a-z0-9]{8,32}$/', $draftId ) ) {
			return null;
		}
		$data = get_transient( 'wpf_setup_draft_' . $draftId );
		$data = apply_filters( 'woobewoo_pf_frontend4_draft', $data, $draftId );
		if ( ! is_array( $data ) || empty( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return null;
		}
		$settings = isset( $data['settings']['settings'] ) && is_array( $data['settings']['settings'] ) ? $data['settings'] : array( 'settings' => $data['settings'] );
		// Drafts come from the 4.0 wizard and builder: engine 4 unless the draft says otherwise (the builder sends
		// "legacy" for a filter made before 4.0).
		if ( ! isset( $settings['settings']['frontend_engine'] ) || '' === (string) $settings['settings']['frontend_engine'] ) {
			$settings['settings']['frontend_engine'] = '4';
		}
		$setup    = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		$pid      = ( $setup && method_exists( $setup->getModel(), 'previewId' ) ) ? (int) $setup->getModel()->previewId( $draftId ) : 900000000 + ( crc32( $draftId ) % 90000000 );
		return array(
			'id'       => $pid,
			'draft'    => $draftId,
			'title'    => isset( $data['title'] ) ? (string) $data['title'] : '',
			'settings' => $settings,
		);
	}

	/**
	 * Enabled blocks of a filter, with key, kind, taxonomy, display type and logic resolved.
	 */
	public static function blocks( $settings ) {
		$s     = ( isset( $settings['settings'] ) && is_array( $settings['settings'] ) ) ? $settings['settings'] : (array) $settings;
		$order = empty( $s['filters']['order'] ) ? array() : json_decode( $s['filters']['order'], true );
		$out   = array();
		$seen  = array();
		foreach ( (array) $order as $i => $block ) {
			if ( empty( $block['id'] ) ) {
				continue;
			}
			$enabled = self::block_setting( $block, 'f_enable' );
			if ( ! $enabled ) {
				continue;
			}
			// A second search box gets its own key (q2, cf_<key>-2), the same rule as the engine.
			$nb = WooBeWoo_PF_Engine::unique_block( WooBeWoo_PF_Engine::normalize_block( $block ), $seen );
			if ( ! $nb ) {
				continue;
			}
			$seen[ $nb['key'] ] = 1;
			$nb['index']        = $i;
			$nb['display']      = self::display_type( $block, $nb['kind'], false, self::setting( $s, 'wbw_style' ) );
			// One pick only: the builder's "Shoppers can pick more than one" off writes logic=single (and the 3.x radio look).
			$raw                = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
			$pickOne            = ( isset( $raw['logic'] ) && 'single' === $raw['logic'] ) || ( isset( $raw['f_frontend_type'] ) && 'radio' === $raw['f_frontend_type'] );
			// 3.x blocks (no "logic"): on categories and brands the 3.x "list" type is the one-choice radio list.
			if ( ! isset( $raw['logic'] ) && isset( $raw['f_frontend_type'] ) && 'list' === $raw['f_frontend_type'] && in_array( $block['id'], array( 'wpfCategory', 'wpfBrand', 'wpfPerfectBrand' ), true ) ) {
				$pickOne = true;
			}
			$nb['single']       = self::block_setting( $block, 'f_single_select' ) || $pickOne || in_array( $nb['kind'], array( 'rating', 'sort', 'stock' ), true );
			// A registered block may be a one-pick block by definition.
			$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::get( $block['id'] ) : null;
			if ( $def && ! empty( $def['single'] ) ) {
				$nb['single'] = true;
			}
			$nb['title']        = self::block_title( $block, $nb );
			$out[ $nb['key'] ]  = $nb;
		}
		return apply_filters( 'woobewoo_pf_frontend4_blocks', $out, $settings );
	}

	/**
	 * Display type of a block: `display_type` or the legacy f_frontend_type mapped, with per kind defaults.
	 */
	public static function display_type( $block, $kind, $mobile = false, $preset = '' ) {
		$s = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$d = isset( $s['display_type'] ) ? sanitize_key( $s['display_type'] ) : '';
		if ( '' === $d && 'terms' === $kind && $preset ) {
			// The look preset decides the default display of attribute and taxonomy blocks.
			$tax = WooBeWoo_PF_Query::block_taxonomy( $block );
			$isAttr = $tax && 0 === strpos( $tax, 'pa_' );
			$looksColour = $isAttr && preg_match( '/colou?r|farbe|couleur|colore|kleur/i', $tax . ' ' . ( function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $tax ) : '' ) );
			switch ( $preset ) {
				case 'chips':
					$d = $isAttr ? 'chips' : 'list';
					break;
				case 'swatches':
					$d = $looksColour ? 'swatches' : ( $isAttr ? 'squares' : 'list' );
					break;
				case 'compact':
					$d = 'dropdown';
					break;
			}
		}
		if ( '' === $d && ! empty( $s['f_wbw_style'] ) && in_array( $s['f_wbw_style'], array( 'chips', 'swatches' ), true ) ) {
			$d = $s['f_wbw_style'];
		}
		if ( '' === $d && isset( $s['f_frontend_type'] ) ) {
			switch ( $s['f_frontend_type'] ) {
				case 'dropdown':
				case 'mul_dropdown':
					$d = 'dropdown';
					break;
				case 'slider':
					$d = 'slider';
					break;
				case 'buttons':
				case 'chips':
					$d = 'chips';
					break;
				case 'colors':
				case 'swatches':
					$d = 'swatches';
					break;
				case 'list':
				case 'radio':
				case 'text':
				case 'multi':
					$d = 'list';
					break;
			}
		}
		$allowed = array( 'list', 'dropdown', 'chips', 'swatches', 'squares', 'slider', 'toggle', 'range_list' );
		if ( ! in_array( $d, $allowed, true ) ) {
			$d = '';
		}
		switch ( $kind ) {
			case 'price':
				if ( ! in_array( $d, array( 'slider', 'range_list', 'list', 'dropdown' ), true ) ) {
					$d = ( ! empty( $s['f_range_by_hands_values'] ) || 'wpfPriceRange' === $block['id'] ) ? 'range_list' : 'slider';
				}
				break;
			case 'meta':
				$d = in_array( $d, array( 'slider', 'range_list' ), true ) ? $d : 'slider';
				break;
			case 'sale':
			case 'featured':
				$d = 'toggle';
				break;
			case 'stock':
				$d = in_array( $d, array( 'list', 'dropdown', 'chips', 'toggle' ), true ) ? $d : 'list';
				break;
			case 'rating':
				$d = in_array( $d, array( 'list', 'dropdown', 'chips' ), true ) ? $d : 'list';
				break;
			case 'sort':
				$d = in_array( $d, array( 'dropdown', 'list', 'chips' ), true ) ? $d : 'dropdown';
				break;
			case 'q':
			case 'text':
				$d = 'search';
				break;
			default:
				// A registered block offers its own display types; the first one is its default.
				$displays = class_exists( 'WooBeWoo_PF_Blocks' ) && ! empty( $block['id'] ) ? WooBeWoo_PF_Blocks::displays( $block['id'] ) : null;
				if ( $displays ) {
					$d = in_array( $d, $displays, true ) ? $d : $displays[0];
				} elseif ( '' === $d ) {
					$d = 'list';
				}
		}
		return $d;
	}

	public static function block_title( $block, $nb ) {
		$t = self::block_setting( $block, 'f_title' );
		if ( '' !== trim( (string) $t ) ) {
			return function_exists( 'woobewoo_pf_translate_string' ) ? woobewoo_pf_translate_string( $t ) : $t;
		}
		switch ( $nb['kind'] ) {
			case 'terms':
				if ( $nb['taxonomy'] && 0 === strpos( $nb['taxonomy'], 'pa_' ) && function_exists( 'wc_attribute_label' ) ) {
					return wc_attribute_label( $nb['taxonomy'] );
				}
				$tx = $nb['taxonomy'] ? get_taxonomy( $nb['taxonomy'] ) : null;
				return $tx ? $tx->labels->singular_name : ucfirst( $nb['key'] );
			case 'price':
				return __( 'Price', 'woo-product-filter' );
			case 'stock':
				return __( 'Stock', 'woo-product-filter' );
			case 'sale':
				return __( 'On sale', 'woo-product-filter' );
			case 'featured':
				return __( 'Featured', 'woo-product-filter' );
			case 'rating':
				return __( 'Rating', 'woo-product-filter' );
			case 'sort':
				return __( 'Sort by', 'woo-product-filter' );
			case 'q':
				return __( 'Search', 'woo-product-filter' );
			case 'text':
				$mk = WooBeWoo_PF_Query::search_meta_keys( isset( $block['settings'] ) ? $block['settings'] : array() );
				return $mk ? ucwords( trim( str_replace( array( '_', '-' ), ' ', implode( ', ', $mk ) ) ) ) : __( 'Search', 'woo-product-filter' );
			case 'author':
				return __( 'Seller', 'woo-product-filter' );
			case 'meta':
				return ucwords( str_replace( array( '_', '-' ), ' ', $nb['key'] ) );
		}
		$def = class_exists( 'WooBeWoo_PF_Blocks' ) && ! empty( $block['id'] ) ? WooBeWoo_PF_Blocks::get( $block['id'] ) : null;
		if ( $def ) {
			return $def['label'];
		}
		return ucfirst( $nb['key'] );
	}

	/* ------------------------------------------------------------------ page context */

	/**
	 * What the current page is about: taxonomy term, search, "all products" switch, base URL, title.
	 */
	public static function page_context( $settings, $wpQuery = null ) {
		$ctx = array(
			'taxonomy'     => '',
			'term_id'      => 0,
			'search'       => '',
			'all_products' => (bool) self::setting( $settings, 'all_products_filtering' ),
			'base'         => WooBeWoo_PF_Query::current_base(),
			'title'        => '',
			// Part of the JSON endpoint URL (which browsers and CDNs cache for 5 minutes): a currency switch, another
			// language or an index update after a stock change never gets an answer cached for the other one.
			'currency'     => WooBeWoo_PF_Index::active_currency(),
			'lang'         => ( defined( 'ICL_LANGUAGE_CODE' ) || function_exists( 'pll_current_language' ) ) ? WooBeWoo_PF_Engine::lang() : '',
			'v'            => WooBeWoo_PF_Index::version(),
		);
		if ( '' === $ctx['lang'] ) {
			unset( $ctx['lang'] );
		}
		$q   = $wpQuery ? $wpQuery : ( isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null );
		if ( $q && is_object( $q ) ) {
			$obj = $q->get_queried_object();
			if ( $obj instanceof WP_Term && in_array( 'product', (array) get_taxonomy( $obj->taxonomy )->object_type, true ) ) {
				$ctx['taxonomy'] = $obj->taxonomy;
				$ctx['term_id']  = (int) $obj->term_id;
				$ctx['title']    = $obj->name;
			}
			$s = $q->get( 's' );
			if ( is_string( $s ) && '' !== $s ) {
				$ctx['search'] = $s;
			}
		}
		if ( ! $ctx['title'] && function_exists( 'woocommerce_page_title' ) && ! wp_doing_ajax() ) {
			$ctx['title'] = woocommerce_page_title( false );
		}
		return apply_filters( 'woobewoo_pf_frontend4_context', $ctx, $settings );
	}

	/**
	 * Context from the JSON the storefront script sends back (sanitized).
	 */
	public static function sanitize_context( $raw ) {
		$in  = is_array( $raw ) ? $raw : ( is_string( $raw ) ? json_decode( $raw, true ) : array() );
		$in  = is_array( $in ) ? $in : array();
		$ctx = array(
			'taxonomy'     => '',
			'term_id'      => 0,
			'search'       => '',
			'all_products' => ! empty( $in['all_products'] ),
			'base'         => '',
			'title'        => isset( $in['title'] ) ? sanitize_text_field( (string) $in['title'] ) : '',
		);
		if ( ! empty( $in['taxonomy'] ) && taxonomy_exists( sanitize_key( $in['taxonomy'] ) ) && ! empty( $in['term_id'] ) ) {
			$ctx['taxonomy'] = sanitize_key( $in['taxonomy'] );
			$ctx['term_id']  = (int) $in['term_id'];
		}
		if ( ! empty( $in['search'] ) ) {
			$ctx['search'] = sanitize_text_field( (string) $in['search'] );
		}
		if ( ! empty( $in['base'] ) ) {
			$base = esc_url_raw( (string) $in['base'] );
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $base && wp_parse_url( $base, PHP_URL_HOST ) === $home ) {
				$ctx['base'] = $base;
			}
		}
		if ( ! $ctx['base'] ) {
			$ctx['base'] = $ctx['taxonomy'] ? get_term_link( $ctx['term_id'], $ctx['taxonomy'] ) : ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) );
			if ( is_wp_error( $ctx['base'] ) ) {
				$ctx['base'] = home_url( '/' );
			}
		}
		return $ctx;
	}

	/**
	 * Engine for a filter row, query and page context.
	 */
	public static function make_engine( $filter, $query = null, $ctx = null ) {
		$query = $query ? $query : WooBeWoo_PF_Query::from_request();
		$ctx   = null === $ctx ? self::page_context( $filter['settings'] ) : $ctx;
		$ectx  = array(
			'filter_id'              => (int) $filter['id'],
			'taxonomy'               => $ctx['taxonomy'],
			'term_id'                => (int) $ctx['term_id'],
			'search'                 => $ctx['search'],
			'all_products'           => ! empty( $ctx['all_products'] ),
			'variations_as_products' => (bool) self::setting( $filter['settings'], 'variations_as_products' ),
		);
		$ectx  = apply_filters( 'woobewoo_pf_frontend4_engine_context', $ectx, $filter, $ctx );
		$blocks = self::blocks( $filter['settings'] );
		$engine = new WooBeWoo_PF_Engine( $query, array_map( function ( $b ) {
			return $b['block'];
		}, $blocks ), $ectx );
		// normalize_block() already ran; copy resolved logic/display from blocks().
		foreach ( $blocks as $k => $b ) {
			if ( isset( $engine->blocks[ $k ] ) ) {
				$engine->blocks[ $k ]['logic']   = $b['logic'];
				$engine->blocks[ $k ]['display'] = $b['display'];
			}
		}
		return $engine;
	}

	/**
	 * The first engine 4 filter shown on this page (display rules), or the first engine 4 filter when the URL
	 * carries filter params and none matches (shortcode placements).
	 */
	public static function active_filter( $wpQuery = null ) {
		static $resolved = false;
		static $found    = null;
		// A filter printed on the page itself (shortcode or block) drives that page's grids, e.g. a Product Collection
		// or [products] beside it, before any filter whose display rules merely allow it here.
		$onPage = self::page_filter();
		if ( $onPage ) {
			return apply_filters( 'woobewoo_pf_frontend4_active_filter', $onPage, $wpQuery );
		}
		if ( $resolved ) {
			return $found;
		}
		$resolved = true;
		$module   = self::$module ? self::$module : WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		$first4   = null;
		foreach ( self::filters() as $f ) {
			if ( ! self::is_engine4( $f['id'], $f['settings'] ) ) {
				continue;
			}
			if ( null === $first4 ) {
				$first4 = $f;
			}
			if ( $module && method_exists( $module, 'getRenderMode' ) ) {
				$mode = $module->getRenderMode( $f['id'], $f['settings'], false );
				// A filter the owner places with the shortcode or block is not on an archive just because its "Where the
				// filter shows" rule allows it: while classic filters exist it only drives the page when the request
				// carries its own (4.0) parameters, so classic preselects and counts on shop, category and tag pages stay.
				if ( ! empty( $mode ) && isset( $f['settings']['settings']['wbw_placement'] ) && 'shortcode' === $f['settings']['settings']['wbw_placement'] && self::has_legacy_filters() && ! self::request_has_engine4_params() ) {
					continue;
				}
				if ( ! empty( $mode ) ) {
					$found = $f;
					return $found;
				}
			}
		}
		// No engine 4 filter on this page but filter parameters in the URL: let engine 4 answer them, unless they are
		// only the 3.x names (links and bookmarks made by a classic filter), which the classic engine keeps answering.
		if ( $first4 && ! WooBeWoo_PF_Query::from_request()->is_empty() && ( ! self::has_legacy_filters() || self::request_has_engine4_params() ) ) {
			$found = $first4;
		}
		$found = apply_filters( 'woobewoo_pf_frontend4_active_filter', $found, $wpQuery );
		return $found;
	}

	/**
	 * The engine 4 filter printed in the queried singular post: [wpf-filters id=N], or our blocks (woobewoo/product-filter
	 * filterId, wbw/woofilters filter_id). With several, the first one whose blocks cover every picked key in the URL,
	 * else the first one. Null on archives, before the main query is resolved, or when the post prints none.
	 */
	public static function page_filter() {
		static $cache = array();
		if ( ! did_action( 'wp' ) || ! is_singular() ) {
			return null;
		}
		$postId = (int) get_queried_object_id();
		if ( array_key_exists( $postId, $cache ) ) {
			return $cache[ $postId ];
		}
		$cache[ $postId ] = null;
		$post             = get_post( $postId );
		if ( ! $post || ( false === strpos( $post->post_content, '[' . WPF_SHORTCODE ) && false === strpos( $post->post_content, '<!-- wp:woobewoo/product-filter' ) && false === strpos( $post->post_content, '<!-- wp:wbw/woofilters' ) ) ) {
			return null;
		}
		$ids = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( WPF_SHORTCODE ) ) . '/', $post->post_content, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				$atts = shortcode_parse_atts( $m[3] );
				if ( '[' !== $m[1] && ! empty( $atts['id'] ) ) {
					$ids[ strpos( $post->post_content, $m[0] ) ] = (int) $atts['id'];
				}
			}
		}
		if ( preg_match_all( '/<!-- wp:(?:woobewoo\/product-filter|wbw\/woofilters) (\{.*?\}) \/?-->/s', $post->post_content, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $all as $m ) {
				$a  = json_decode( $m[1][0], true );
				$id = isset( $a['filterId'] ) ? (int) $a['filterId'] : ( isset( $a['filter_id'] ) ? (int) $a['filter_id'] : 0 );
				if ( $id ) {
					$ids[ $m[0][1] ] = $id;
				}
			}
		}
		ksort( $ids );
		$keys  = array_keys( WooBeWoo_PF_Query::from_request()->terms );
		$first = null;
		foreach ( array_unique( $ids ) as $id ) {
			$f = self::filter( $id );
			if ( ! $f || ! self::is_engine4( $f['id'], $f['settings'] ) ) {
				continue;
			}
			if ( null === $first ) {
				$first = $f;
			}
			if ( ! array_diff( $keys, array_keys( self::blocks( $f['settings'] ) ) ) ) {
				$cache[ $postId ] = $f;
				return $f;
			}
		}
		$cache[ $postId ] = $first;
		return $first;
	}

	/* ------------------------------------------------------------------ main query */

	/**
	 * Before WooCommerce touches the main query: when an engine 4 filter is shown here, detach the legacy engine
	 * and let the index drive the product query.
	 */
	public static function onPreGetPosts( $q ) {
		if ( ! $q->is_main_query() || is_admin() ) {
			return;
		}
		$filter = self::active_filter( $q );
		if ( ! $filter ) {
			return;
		}
		$module = self::$module;
		if ( $module ) {
			$prio = (int) WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'load_products_filter_wc_product_query_priority' );
			remove_action( 'woocommerce_product_query', array( $module, 'loadProductsFilter' ), $prio ? $prio : 999 );
			remove_action( 'pre_get_posts', array( $module, 'forceProductFilter' ), 9999 );
			// newLoopShopPerPage stays: "Products per page" (count_product_shop, Advanced settings) is a store-wide
			// option the owner set in 3.x and it keeps applying when an engine 4 filter drives the page.
			remove_filter( 'query_loop_block_query_vars', array( $module, 'addFilterToWoocommerceBlocksAgrs' ), 999 );
			remove_action( 'woocommerce_shortcode_products_query', array( $module, 'loadShortcodeProductsFilter' ), 999 );
		}
		add_action( 'woocommerce_product_query', array( __CLASS__, 'onProductQuery' ), 1000, 1 );
		add_filter( 'woocommerce_is_filtered', array( __CLASS__, 'isFiltered' ), 20 );
	}

	/**
	 * Feed the main WooCommerce product query from the index.
	 */
	public static function onProductQuery( $q ) {
		$filter = self::active_filter( $q );
		if ( ! $filter ) {
			return;
		}
		$query = WooBeWoo_PF_Query::from_request();
		$ctx   = self::page_context( $filter['settings'], $q );
		$eng   = self::make_engine( $filter, $query, $ctx );
		self::begin_request( $filter, $query, $ctx, $eng );
		$mode = self::setting( $filter['settings'], 'pagination_mode' );
		if ( $query->is_empty() ) {
			// "Show more" / infinite scroll: a reload or Back with page N in the URL shows pages 1..N.
			if ( 'pages' !== $mode && $query->page > 1 ) {
				$q->set( 'posts_per_page', WooBeWoo_PF_Engine::per_page() * $query->page );
				$q->set( 'paged', 1 );
			}
			return;
		}
		$ids = $eng->all_ids( $query->sort );
		$q->set( 'post__in', $ids ? $ids : array( 0 ) );
		$q->set( 'orderby', 'post__in' );
		$q->set( 'order', 'ASC' );
		$q->set( 'wpf4', 1 );
		$per  = WooBeWoo_PF_Engine::per_page();
		if ( 'pages' !== $mode && $query->page > 1 ) {
			$q->set( 'posts_per_page', $per * $query->page );
			$q->set( 'paged', 1 );
		} else {
			$q->set( 'paged', max( 1, (int) $query->page ) );
		}
		// Our own price range and stock are applied through post__in; remove WooCommerce's price meta query so a
		// variable product whose variation matches is not dropped by the parent's min/max price.
		$meta = $q->get( 'meta_query' );
		if ( is_array( $meta ) ) {
			foreach ( $meta as $k => $m ) {
				if ( is_array( $m ) && isset( $m['key'] ) && in_array( $m['key'], array( '_price' ), true ) ) {
					unset( $meta[ $k ] );
				}
			}
			$q->set( 'meta_query', $meta );
		}
	}

	public static function isFiltered( $filtered ) {
		return $filtered || WooBeWoo_PF_Query::from_request()->has_filters();
	}

	/**
	 * Product Collection / Query Loop blocks on a page with an engine 4 filter.
	 */
	public static function onBlockQueryVars( $vars, $block, $page ) {
		if ( empty( $vars['post_type'] ) || 'product' !== $vars['post_type'] ) {
			return $vars;
		}
		$filter = self::active_filter();
		if ( ! $filter ) {
			return $vars;
		}
		$query = WooBeWoo_PF_Query::from_request();
		if ( $query->is_empty() ) {
			return $vars;
		}
		$eng = self::make_engine( $filter, $query );
		if ( ! self::$current ) {
			self::begin_request( $filter, $query, self::page_context( $filter['settings'] ), $eng );
		}
		$ids              = $eng->all_ids( $query->sort );
		$vars['post__in'] = $ids ? $ids : array( 0 );
		$vars['orderby']  = 'post__in';
		if ( $query->page > 1 ) {
			$vars['paged'] = (int) $query->page;
		}
		return $vars;
	}

	/**
	 * [products] shortcode on a page with an engine 4 filter.
	 */
	public static function onShortcodeQuery( $args, $atts = array(), $type = '' ) {
		// The filter printed on this page (shortcode or block above the grid) drives it, else the page's active filter.
		$filter = ! empty( self::$current['filter'] ) ? self::$current['filter'] : self::active_filter();
		if ( ! $filter ) {
			return $args;
		}
		$query = WooBeWoo_PF_Query::from_request();
		if ( $query->is_empty() ) {
			return $args;
		}
		$eng = self::make_engine( $filter, $query );
		if ( ! self::$current ) {
			self::begin_request( $filter, $query, self::page_context( $filter['settings'] ), $eng );
		}
		$ids              = $eng->all_ids( $query->sort );
		$args['post__in'] = $ids ? $ids : array( 0 );
		$args['orderby']  = 'post__in';
		if ( $query->page > 1 ) {
			$args['paged'] = (int) $query->page;
		}
		return $args;
	}

	/* ------------------------------------------------------------------ loop hooks: variations as products */

	/**
	 * Mark the request as a filter request; adds the loop hooks when variations as products is on.
	 */
	public static function begin_request( $filter, $query, $ctx, $engine ) {
		self::$current = array(
			'filter'   => $filter,
			'settings' => $filter['settings'],
			'query'    => $query,
			'ctx'      => $ctx,
			'engine'   => $engine,
		);
		if ( self::$loopHooked || ! $query->has_filters() ) {
			return;
		}
		$vap = self::setting( $filter['settings'], 'variations_as_products' );
		if ( ! $vap ) {
			return;
		}
		self::$loopHooked = true;
		add_filter( 'woocommerce_product_get_image', array( __CLASS__, 'loopImage' ), 20, 6 );
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'loopPriceHtml' ), 20, 2 );
		if ( self::setting( $filter['settings'], 'open_matching_variation' ) ) {
			add_filter( 'woocommerce_loop_product_link', array( __CLASS__, 'loopLink' ), 20, 2 );
		}
		if ( self::setting( $filter['settings'], 'sizes_left_badge' ) ) {
			add_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'loopBadge' ), 15 );
		}
	}

	public static function end_request() {
		if ( self::$loopHooked ) {
			remove_filter( 'woocommerce_product_get_image', array( __CLASS__, 'loopImage' ), 20 );
			remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'loopPriceHtml' ), 20 );
			remove_filter( 'woocommerce_loop_product_link', array( __CLASS__, 'loopLink' ), 20 );
			remove_action( 'woocommerce_after_shop_loop_item_title', array( __CLASS__, 'loopBadge' ), 15 );
			self::$loopHooked = false;
		}
	}

	/**
	 * The matching variation (WC object + index row) for a product in the loop, cached per request.
	 */
	public static function matching_variation( $product ) {
		if ( ! self::$current || ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
			return null;
		}
		$pid = $product->get_id();
		if ( array_key_exists( $pid, self::$variationCache ) ) {
			return self::$variationCache[ $pid ];
		}
		$row = self::$current['engine']->matching_variation( $pid );
		$out = null;
		if ( $row && ! empty( $row['variation_id'] ) ) {
			$v = wc_get_product( $row['variation_id'] );
			if ( $v ) {
				$out = array(
					'row'    => $row,
					'object' => $v,
				);
			}
		}
		self::$variationCache[ $pid ] = $out;
		return $out;
	}

	public static function loopImage( $image, $product, $size = 'woocommerce_thumbnail', $attr = array(), $placeholder = true, $orig = '' ) {
		if ( ! in_the_loop() && ! wp_doing_ajax() ) {
			return $image;
		}
		$m = self::matching_variation( $product );
		if ( $m && $m['object']->get_image_id() ) {
			return $m['object']->get_image( $size, $attr, $placeholder );
		}
		return $image;
	}

	public static function loopPriceHtml( $html, $product ) {
		if ( ! in_the_loop() && ! wp_doing_ajax() ) {
			return $html;
		}
		$m = self::matching_variation( $product );
		if ( $m ) {
			remove_filter( 'woocommerce_get_price_html', array( __CLASS__, 'loopPriceHtml' ), 20 );
			$html = $m['object']->get_price_html();
			add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'loopPriceHtml' ), 20, 2 );
		}
		return $html;
	}

	public static function loopLink( $link, $product ) {
		$m = self::matching_variation( $product );
		if ( $m ) {
			$args = array();
			foreach ( $m['object']->get_attributes() as $tax => $slug ) {
				if ( '' !== $slug ) {
					$args[ 'attribute_' . $tax ] = $slug;
				}
			}
			if ( $args ) {
				$link = add_query_arg( array_map( 'rawurlencode', $args ), $link );
			}
		}
		return $link;
	}

	/**
	 * "Only 2 left in 42" / "In stock in 42" on the result card, from the index.
	 */
	public static function loopBadge() {
		global $product;
		if ( ! self::$current || ! $product instanceof WC_Product ) {
			return;
		}
		$m = self::matching_variation( $product );
		if ( ! $m ) {
			return;
		}
		$row       = $m['row'];
		$threshold = (int) self::setting( self::$current['settings'], 'sizes_left_threshold' );
		$label     = self::picked_attribute_label( $m['object'] );
		if ( 'outofstock' === $row['stock_status'] ) {
			/* translators: %s: variation label (for example "size 42") */
			$text = $label ? sprintf( __( 'Sold out in %s', 'woo-product-filter' ), $label ) : __( 'Sold out', 'woo-product-filter' );
			$tone = 'critical';
		} elseif ( null !== $row['stock_qty'] && $row['stock_qty'] <= $threshold && 'instock' === $row['stock_status'] ) {
			/* translators: 1: number of items left in stock, 2: variation label; and %d: number of items left in stock */
			$text = $label ? sprintf( _n( 'Only %1$d left in %2$s', 'Only %1$d left in %2$s', (int) $row['stock_qty'], 'woo-product-filter' ), (int) $row['stock_qty'], $label ) : sprintf( _n( 'Only %d left', 'Only %d left', (int) $row['stock_qty'], 'woo-product-filter' ), (int) $row['stock_qty'] );
			$tone = 'partial';
		} else {
			/* translators: %s: variation label (for example "size 42") */
			$text = $label ? sprintf( __( 'In stock in %s', 'woo-product-filter' ), $label ) : __( 'In stock', 'woo-product-filter' );
			$tone = 'passed';
		}
		echo '<span class="wpf4-badge wpf4-badge--' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * "42" or "42, Red": the picked attribute values the variation matches.
	 */
	private static function picked_attribute_label( $variation ) {
		if ( ! self::$current ) {
			return '';
		}
		$parts = array();
		foreach ( $variation->get_attributes() as $tax => $slug ) {
			if ( '' === $slug || 0 !== strpos( $tax, 'pa_' ) ) {
				continue;
			}
			$key = WooBeWoo_PF_Query::key_for_taxonomy( $tax );
			if ( empty( self::$current['query']->terms[ $key ] ) ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$parts[] = $term->name;
			}
		}
		return implode( ', ', $parts );
	}

	public static function hasVariationStockIndex( $has ) {
		return WooBeWoo_PF_Index::installed() ? true : (bool) $has;
	}

	public static function variationStockLookup( $result, $productId = 0, $attributes = array() ) {
		if ( ! WooBeWoo_PF_Index::installed() || ! $productId ) {
			return $result;
		}
		$row = WooBeWoo_PF_Index::lookup_variation( (int) $productId, (array) $attributes );
		return $row ? $row : $result;
	}

	/* ------------------------------------------------------------------ JSON endpoint */

	/**
	 * GET admin-ajax.php?action=woobewoo_pf_query&filter=ID&wpf_...=&ctx={json}[&facets_only=1][&title=]
	 * Public, read only, no cookies needed; cacheable by full page caches.
	 */
	public static function ajaxQuery() {
		$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST['filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$get = array_merge( $get, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		$filterRaw = isset( $get['filter'] ) ? sanitize_text_field( (string) $get['filter'] ) : '';
		$filter    = '' !== $filterRaw ? self::filter( ctype_digit( $filterRaw ) ? (int) $filterRaw : $filterRaw ) : null;
		if ( ! $filter ) {
			wp_send_json_error( array( 'message' => 'Unknown filter' ), 404 );
		}
		if ( ! self::is_engine4( $filter['id'], $filter['settings'] ) ) {
			wp_send_json_error( array( 'message' => 'This filter uses the legacy engine' ), 400 );
		}
		if ( ! empty( $get['wpf_nocache'] ) && ( defined( 'WPF_BENCHMARK' ) || apply_filters( 'woobewoo_pf_allow_nocache', false ) ) ) {
			WooBeWoo_PF_Engine::$nocache = true;
		}
		$t0     = microtime( true );
		$query  = WooBeWoo_PF_Query::from_params( $get );
		$ctx    = self::sanitize_context( isset( $get['ctx'] ) ? $get['ctx'] : array() );
		$engine = self::make_engine( $filter, $query, $ctx );
		self::begin_request( $filter, $query, $ctx, $engine );

		$facetsOnly = ! empty( $get['facets_only'] );
		$facets     = self::facets_payload( $engine, $filter );
		$count      = $engine->count();
		$base       = $ctx['base'];
		$noPage     = clone $query;
		$noPage->page = 1;
		$out        = array(
			'count'     => $count,
			'facets'    => $facets,
			'params'    => $query->params(),
			'url'       => $query->to_url( $base ),
			'canonical' => $noPage->to_url( $base ),
			'title'     => self::title_for( $query, $filter, $ctx ),
			'page'      => (int) $query->page,
			'filterId'  => empty( $filter['draft'] ) ? (string) (int) $filter['id'] : 'draft_' . $filter['draft'],
			/* translators: %s: number of products */
			'live'      => sprintf( _n( '%s product', '%s products', $count, 'woo-product-filter' ), number_format_i18n( $count ) ),
		);
		if ( ! $facetsOnly ) {
			$mode     = self::setting( $filter['settings'], 'pagination_mode' );
			$per      = WooBeWoo_PF_Engine::per_page();
			$page     = $engine->ids( $query->page, $per, $query->sort, false );
			$out['pages']       = (int) $page['pages'];
			$out['per_page']    = $per;
			$out['html']        = self::render_products( $page['ids'], $page, $filter );
			$out['result_count_html'] = self::result_count_html( $page );
			$out['pagination_html']   = 'pages' === $mode ? self::pagination_html( $query, $page, $base ) : '';
			$out['has_more']    = $page['page'] < $page['pages'];
		}
		$out['took_ms'] = round( ( microtime( true ) - $t0 ) * 1000, 1 );
		$out['queries'] = WooBeWoo_PF_Engine::$queries;
		self::end_request();
		$out = apply_filters( 'woobewoo_pf_frontend4_response', $out, $query, $filter, $ctx );
		if ( ! headers_sent() ) {
			header_remove( 'Expires' );
			header_remove( 'Pragma' );
			// Browsers never reuse an answer (a re-pick in the same tab after a stock or price change showed the old
			// count for 5 minutes); shared caches may keep it 60 s (the URL carries currency, language, index version).
			// Logged in shoppers can see their own prices (roles, wholesale): never in a shared cache.
			header( 'Cache-Control: ' . ( is_user_logged_in() ? 'private, no-cache' : 'public, max-age=0, s-maxage=60' ), true );
			header( 'X-WPF-Engine: 4' );
			header( 'Vary: Accept-Encoding' );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			status_header( 200 );
		}
		// Not wp_send_json(): its wp_die() handler sends no-cache headers again.
		echo wp_json_encode( $out, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON payload.
		die();
	}

	/**
	 * Facets for the JSON: key => array( kind, counts, min, max, labels for finder ).
	 */
	public static function facets_payload( $engine, $filter ) {
		$facets = $engine->facets();
		$blocks = self::blocks( $filter['settings'] );
		foreach ( $blocks as $k => $b ) {
			if ( ! isset( $facets[ $k ] ) ) {
				continue;
			}
			$facets[ $k ]['display'] = $b['display'];
			$facets[ $k ]['title']   = $b['title'];
			if ( 'price' === $b['kind'] ) {
				$facets[ $k ]['ranges'] = self::price_ranges( $engine, $facets[ $k ] );
			}
			// Author counts come keyed by user id; the controls carry the user's nicename (and so does the URL).
			if ( 'author' === $b['kind'] && isset( $facets[ $k ]['counts'] ) && is_array( $facets[ $k ]['counts'] ) ) {
				$byName = array();
				$labels = array();
				foreach ( $facets[ $k ]['counts'] as $uid => $n ) {
					$u = ctype_digit( (string) $uid ) ? get_user_by( 'id', (int) $uid ) : get_user_by( 'slug', (string) $uid );
					if ( $u ) {
						$byName[ $u->user_nicename ] = (int) $n;
						$labels[ $u->user_nicename ] = $u->display_name;
					}
				}
				$facets[ $k ]['counts'] = $byName;
				$facets[ $k ]['labels'] = $labels;
			}
			if ( 'terms' === $b['kind'] && isset( $facets[ $k ]['counts'] ) ) {
				$labels = array();
				foreach ( array_keys( $facets[ $k ]['counts'] ) as $slug ) {
					$term = get_term_by( 'slug', $slug, $b['taxonomy'] );
					$labels[ $slug ] = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
				}
				$facets[ $k ]['labels'] = $labels;
			}
		}
		return $facets;
	}

	/**
	 * Four budget steps with counts for the finder: Under X, X to Y, Y to Z, Over Z.
	 */
	public static function price_ranges( $engine, $facet ) {
		$min = (float) $facet['min'];
		$max = (float) $facet['max'];
		if ( $max <= $min ) {
			return array();
		}
		$step = ( $max - $min ) / 4;
		$nice = function ( $v ) {
			$mag = pow( 10, max( 0, floor( log10( max( 1, $v ) ) ) - 1 ) );
			return (float) ( round( $v / $mag ) * $mag );
		};
		$cuts = array( $nice( $min + $step ), $nice( $min + 2 * $step ), $nice( $min + 3 * $step ) );
		$cuts = array_values( array_unique( $cuts ) );
		$out  = array();
		$prev = null;
		foreach ( $cuts as $i => $c ) {
			$r     = array( $prev, $c );
			$out[] = array(
				'value' => WooBeWoo_PF_Query::format_range( $r ),
				/* translators: %s: price; and 1: lower price, 2: higher price */
				'label' => null === $prev ? sprintf( __( 'Under %s', 'woo-product-filter' ), self::plain_price( $c ) ) : sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), self::plain_price( $prev ), self::plain_price( $c ) ),
				'count' => $engine->count_price_range( $prev, $c ),
			);
			$prev  = $c;
		}
		$out[] = array(
			'value' => WooBeWoo_PF_Query::format_range( array( $prev, null ) ),
			/* translators: %s: price */
			'label' => sprintf( __( 'Over %s', 'woo-product-filter' ), self::plain_price( $prev ) ),
			'count' => $engine->count_price_range( $prev, null ),
		);
		return $out;
	}

	/**
	 * Picked values as words, for the page title and chips: "Red, Size 42, up to $120".
	 */
	public static function picked_labels( $query, $filter ) {
		$blocks = self::blocks( $filter['settings'] );
		$out    = array();
		foreach ( $query->terms as $key => $slugs ) {
			$tax = isset( $blocks[ $key ] ) ? $blocks[ $key ]['taxonomy'] : WooBeWoo_PF_Query::taxonomy_for_key( $key );
			foreach ( $slugs as $slug ) {
				if ( 'author' === $key ) {
					$u     = ctype_digit( (string) $slug ) ? get_user_by( 'id', (int) $slug ) : get_user_by( 'slug', $slug );
					$out[] = array( 'key' => $key, 'value' => $slug, 'label' => $u ? $u->display_name : $slug );
					continue;
				}
				$term  = $tax ? get_term_by( 'slug', $slug, $tax ) : null;
				$label = ( $term && ! is_wp_error( $term ) ) ? $term->name : $slug;
				// "42" alone says nothing: numeric values carry the block title ("Size 42").
				if ( isset( $blocks[ $key ] ) && preg_match( '/^\d/', $label ) ) {
					$label = $blocks[ $key ]['title'] . ' ' . $label;
				}
				$out[] = array( 'key' => $key, 'value' => $slug, 'label' => $label );
			}
		}
		if ( is_array( $query->price ) ) {
			$out[] = array( 'key' => 'price', 'value' => WooBeWoo_PF_Query::format_range( $query->price ), 'label' => self::price_label( $query->price ) );
		}
		if ( $query->stock ) {
			$map   = array( 'in' => __( 'In stock', 'woo-product-filter' ), 'out' => __( 'Sold out', 'woo-product-filter' ), 'backorder' => __( 'On backorder', 'woo-product-filter' ) );
			$out[] = array( 'key' => 'stock', 'value' => $query->stock, 'label' => $map[ $query->stock ] );
		}
		if ( $query->sale ) {
			$out[] = array( 'key' => 'sale', 'value' => '1', 'label' => __( 'On sale', 'woo-product-filter' ) );
		}
		if ( $query->featured ) {
			$out[] = array( 'key' => 'featured', 'value' => '1', 'label' => __( 'Featured', 'woo-product-filter' ) );
		}
		if ( $query->rating ) {
			/* translators: %d: number of stars */
			$out[] = array( 'key' => 'rating', 'value' => (string) $query->rating, 'label' => sprintf( __( '%d stars and up', 'woo-product-filter' ), $query->rating ) );
		}
		foreach ( $query->meta as $key => $r ) {
			$title = isset( $blocks[ $key ] ) ? $blocks[ $key ]['title'] : $key;
			$out[] = array( 'key' => $key, 'value' => WooBeWoo_PF_Query::format_range( $r ), 'label' => $title . ' ' . self::range_label( $r ) );
		}
		foreach ( $query->text as $tk => $tv ) {
			if ( '' !== (string) $tv ) {
				$out[] = array( 'key' => $tk, 'value' => $tv, 'label' => '"' . $tv . '"' );
			}
		}
		if ( '' !== $query->q ) {
			$out[] = array( 'key' => 'q', 'value' => $query->q, 'label' => '"' . $query->q . '"' );
		}
		// Registered blocks: one chip per picked value, worded by the block's `chip` callback.
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			foreach ( (array) $query->extra as $key => $value ) {
				$def = WooBeWoo_PF_Blocks::by_key( $key );
				if ( ! $def ) {
					continue;
				}
				$b = isset( $blocks[ $key ] ) ? $blocks[ $key ] : null;
				foreach ( is_array( $value ) ? $value : array( $value ) as $v ) {
					if ( '' === (string) $v ) {
						continue;
					}
					$out[] = array( 'key' => $key, 'value' => (string) $v, 'label' => WooBeWoo_PF_Blocks::chip_label( $def, $v, $b ) );
				}
			}
		}
		return $out;
	}

	public static function price_label( $r ) {
		$min = $r[0];
		$max = $r[1];
		if ( null !== $min && null !== $max ) {
			/* translators: 1: lower price, 2: higher price */
			return sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), self::plain_price( $min ), self::plain_price( $max ) );
		}
		if ( null !== $max ) {
			/* translators: %s: price */
			return sprintf( __( 'up to %s', 'woo-product-filter' ), self::plain_price( $max ) );
		}
		/* translators: %s: price */
		return sprintf( __( 'from %s', 'woo-product-filter' ), self::plain_price( $min ) );
	}

	/**
	 * A price as plain text ("$82" not "&#36;82.00"), whole numbers without decimals.
	 */
	public static function plain_price( $v ) {
		$v    = (float) $v;
		$args = array();
		if ( floor( $v ) === $v ) {
			$args['decimals'] = 0;
		}
		return html_entity_decode( wp_strip_all_tags( wc_price( $v, $args ) ), ENT_QUOTES, 'UTF-8' );
	}

	public static function range_label( $r ) {
		if ( null !== $r[0] && null !== $r[1] ) {
			return WooBeWoo_PF_Query::trim_number( $r[0] ) . ' – ' . WooBeWoo_PF_Query::trim_number( $r[1] );
		}
		if ( null !== $r[1] ) {
			return '≤ ' . WooBeWoo_PF_Query::trim_number( $r[1] );
		}
		return '≥ ' . WooBeWoo_PF_Query::trim_number( $r[0] );
	}

	public static function title_for( $query, $filter, $ctx ) {
		$labels = array_map( function ( $p ) {
			return $p['label'];
		}, self::picked_labels( $query, $filter ) );
		$page   = ! empty( $ctx['title'] ) ? $ctx['title'] : '';
		$site   = get_bloginfo( 'name' );
		$parts  = array_filter( array( $labels ? implode( ', ', $labels ) : '', $page, $site ) );
		return apply_filters( 'woobewoo_pf_frontend4_title', implode( ' – ', $parts ), $query, $filter, $ctx );
	}

	/**
	 * The WooCommerce product loop for a list of ids (the theme's own card template).
	 */
	public static function render_products( $ids, $page, $filter ) {
		$settings = $filter['settings'];
		if ( empty( $ids ) ) {
			$msg = self::setting( $settings, 'text_no_products' );
			return '<ul class="products columns-' . esc_attr( wc_get_default_products_per_row() ) . ' wpf4-products wpf4-products--empty"><li class="wpf4-empty"><p class="woocommerce-info wpf4-empty__text">' . esc_html( $msg ) . '</p></li></ul>';
		}
		// The legacy engine must not touch this query.
		if ( self::$module ) {
			remove_action( 'pre_get_posts', array( self::$module, 'forceProductFilter' ), 9999 );
			remove_filter( 'posts_clauses_request', array( self::$module, 'addFilterClausesRequest' ), 10 );
		}
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'post__in'            => array_map( 'intval', $ids ),
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'wpf4'                => 1,
		);
		$args = apply_filters( 'woobewoo_pf_frontend4_loop_args', $args, $filter );
		$q    = new WP_Query( $args );
		$cols = wc_get_default_products_per_row();
		wc_setup_loop(
			array(
				'name'         => 'wpf4',
				'is_shortcode' => false,
				'is_search'    => false,
				'is_paginated' => true,
				'total'        => (int) $page['total'],
				'total_pages'  => (int) $page['pages'],
				'per_page'     => (int) $page['per_page'],
				'current_page' => (int) $page['page'],
				'columns'      => $cols,
			)
		);
		ob_start();
		if ( $q->have_posts() ) {
			woocommerce_product_loop_start();
			while ( $q->have_posts() ) {
				$q->the_post();
				global $product;
				if ( $product && $product->is_visible() ) {
					wc_get_template_part( 'content', 'product' );
				}
			}
			woocommerce_product_loop_end();
		}
		$html = ob_get_clean();
		wp_reset_postdata();
		wc_reset_loop();
		return $html;
	}

	public static function result_count_html( $page ) {
		if ( ! function_exists( 'wc_get_template_html' ) ) {
			return '';
		}
		return wc_get_template_html(
			'loop/result-count.php',
			array(
				'total'    => (int) $page['total'],
				'per_page' => (int) $page['per_page'],
				'current'  => (int) $page['page'],
				'orderedby'=> '',
			)
		);
	}

	/**
	 * Page links as pretty URLs (prev, numbers with gaps, next).
	 */
	public static function pagination_html( $query, $page, $base ) {
		$pages = (int) $page['pages'];
		if ( $pages <= 1 ) {
			return '';
		}
		$cur   = (int) $page['page'];
		$link  = function ( $n ) use ( $query, $base ) {
			$q       = clone $query;
			$q->page = $n;
			return $q->to_url( $base );
		};
		$items = array();
		$items[] = $cur > 1 ? '<li><a class="prev page-numbers" href="' . esc_url( $link( $cur - 1 ) ) . '" data-page="' . ( $cur - 1 ) . '" aria-label="' . esc_attr__( 'Previous page', 'woo-product-filter' ) . '">' . esc_html__( 'Previous', 'woo-product-filter' ) . '</a></li>' : '';
		$shown = array( 1, $pages, $cur - 1, $cur, $cur + 1 );
		$last  = 0;
		for ( $n = 1; $n <= $pages; $n++ ) {
			if ( ! in_array( $n, $shown, true ) ) {
				continue;
			}
			if ( $last && $n > $last + 1 ) {
				$items[] = '<li><span class="page-numbers dots" aria-hidden="true">…</span></li>';
			}
			$last    = $n;
			$items[] = $n === $cur
				? '<li><span aria-current="page" class="page-numbers current">' . $n . '</span></li>'
				: '<li><a class="page-numbers" href="' . esc_url( $link( $n ) ) . '" data-page="' . $n . '">' . $n . '</a></li>';
		}
		$items[] = $cur < $pages ? '<li><a class="next page-numbers" href="' . esc_url( $link( $cur + 1 ) ) . '" data-page="' . ( $cur + 1 ) . '" aria-label="' . esc_attr__( 'Next page', 'woo-product-filter' ) . '">' . esc_html__( 'Next', 'woo-product-filter' ) . '</a></li>' : '';
		return '<nav class="woocommerce-pagination wpf4-pagination" aria-label="' . esc_attr__( 'Product pages', 'woo-product-filter' ) . '"><ul class="page-numbers">' . implode( '', array_filter( $items ) ) . '</ul></nav>';
	}

	/* ------------------------------------------------------------------ assets */

	public static function enqueue( $filter ) {
		if ( self::$assetsDone ) {
			return;
		}
		self::$assetsDone = true;
		$path = WPF_MODULES_PATH . 'woofilters/';
		$ver  = defined( 'WPF_VERSION' ) ? WPF_VERSION : self::VERSION;
		wp_enqueue_style( 'woobewoo-pf-frontend-4', $path . 'css/frontend-4/filter.css', array(), $ver );
		$deps = array();
		foreach ( array( 'slider', 'swatches', 'drawer', 'finder', 'bar' ) as $m ) {
			wp_enqueue_script( 'woobewoo-pf-frontend-4-' . $m, $path . 'js/frontend-4/' . $m . '.js', array(), $ver, true );
			$deps[] = 'woobewoo-pf-frontend-4-' . $m;
		}
		wp_enqueue_script( 'woobewoo-pf-frontend-4', $path . 'js/frontend-4/filter.js', $deps, $ver, true );
		/**
		 * Engine 4 assets are queued: add-ons (Pro) queue their storefront scripts here.
		 *
		 * @since 4.0.0
		 * @param array $filter The filter shown on this page.
		 */
		do_action( 'woobewoo_pf_frontend4_enqueue', $filter );
		wp_localize_script(
			'woobewoo-pf-frontend-4',
			'wpf4Config',
			array(
				'ajax'      => admin_url( 'admin-ajax.php' ),
				'action'    => self::AJAX_QUERY,
				// Speed and SEO, "Update results without reloading" off: picks load the filtered page.
				'reload'    => ( WooBeWoo_PF_Frame::_()->getModule( 'speed' ) && method_exists( WooBeWoo_PF_Frame::_()->getModule( 'speed' ), 'ajaxResultsOn' ) && ! WooBeWoo_PF_Frame::_()->getModule( 'speed' )->ajaxResultsOn() ),
				'selectors' => apply_filters(
					'woobewoo_pf_frontend4_loop_selectors',
					array( 'ul.products', '.wc-block-product-template', '.wc-block-grid__products', '.products', '.elementor-widget-woocommerce-products .products', '.woocommerce ul.products' )
				),
				// Page-builder grids: the script fetches the filtered page and swaps these wrappers' content, and appends
				// the filter to these admin-ajax actions (load more, pagination) so the next page is filtered too.
				'addon'     => array(
					'wrappers' => self::addonWrappers(),
					'actions'  => self::addonActions(),
				),
				'i18n'      => array(
					/* translators: %s: number of products */
					'products'    => __( '%s products', 'woo-product-filter' ),
					/* translators: %s: number of products */
					'product'     => __( '%s product', 'woo-product-filter' ),
					/* translators: %s: number of results */
					'showResults' => __( 'Show %s results', 'woo-product-filter' ),
					/* translators: %s: number of results */
					'showResult'  => __( 'Show %s result', 'woo-product-filter' ),
					/* translators: %s: number of products */
					'match'       => __( '%s products match', 'woo-product-filter' ),
					'filters'     => __( 'Filters', 'woo-product-filter' ),
					'clearAll'    => __( 'Clear all', 'woo-product-filter' ),
					'close'       => __( 'Close filters', 'woo-product-filter' ),
					'remove'      => __( 'remove', 'woo-product-filter' ),
					/* translators: %s: list of the remembered filter choices */
					'remembered'  => __( 'You filtered by %s last time.', 'woo-product-filter' ),
					'useAgain'    => __( 'Use it again?', 'woo-product-filter' ),
					'showMore'    => __( 'Show more', 'woo-product-filter' ),
					'loading'     => __( 'Loading…', 'woo-product-filter' ),
					'error'       => __( 'Something went wrong. Try again.', 'woo-product-filter' ),
					/* translators: %s: price */
					'upTo'        => __( 'up to %s', 'woo-product-filter' ),
					/* translators: %s: price */
					'from'        => __( 'from %s', 'woo-product-filter' ),
					/* translators: 1: lower value, 2: higher value */
					'to'          => __( '%1$s to %2$s', 'woo-product-filter' ),
					/* translators: 1: current question number, 2: number of questions */
					'question'    => __( 'Question %1$d of %2$d', 'woo-product-filter' ),
					/* translators: %s: title of the next question */
					'next'        => __( 'Next: %s', 'woo-product-filter' ),
					'showAll'     => __( 'Show results', 'woo-product-filter' ),
					'back'        => __( 'Back', 'woo-product-filter' ),
					'skipAll'     => __( 'Skip to all products', 'woo-product-filter' ),
					/* translators: %s: price */
					'under'       => __( 'Under %s', 'woo-product-filter' ),
					/* translators: %s: price */
					'over'        => __( 'Over %s', 'woo-product-filter' ),
					'any'         => __( 'Any', 'woo-product-filter' ),
					/* translators: 1: number of items left in stock, 2: term name */
					'leftIn'      => __( '%1$d left in %2$s', 'woo-product-filter' ),
					/* translators: %s: option label */
					'picked'      => __( '%s, picked', 'woo-product-filter' ),
					/* translators: %d: step number */
					'stepDone'    => __( 'Step %d done', 'woo-product-filter' ),
					/* translators: %d: step number */
					'stepCurrent' => __( 'Step %d, current', 'woo-product-filter' ),
					/* translators: %d: step number */
					'step'        => __( 'Step %d', 'woo-product-filter' ),
					/* translators: 1: number of products matching so far */
					'finderHelp'  => __( '%1$s products so far. Pick one to narrow it down.', 'woo-product-filter' ),
					'and'         => __( 'and', 'woo-product-filter' ),
					'inStock'     => __( 'In stock only', 'woo-product-filter' ),
					/* translators: %d: number of stars */
					'starsUp'     => __( '%d stars and up', 'woo-product-filter' ),
					'noOptions'   => __( 'Nothing left to pick here.', 'woo-product-filter' ),
				),
				'currency'  => array(
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol() ),
					'position' => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimals' => wc_get_price_decimals(),
					'dec'      => wc_get_price_decimal_separator(),
					'thou'     => wc_get_price_thousand_separator(),
				),
			)
		);
	}

	/* ------------------------------------------------------------------ accessibility report */

	/**
	 * Admin AJAX (manage_options or manage_woocommerce + nonce): structural checks on a filter's markup.
	 */
	public static function ajaxA11yReport() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! ( wp_verify_nonce( $nonce, self::AJAX_A11Y ) || wp_verify_nonce( $nonce, 'woobewoo-pf-save-nonce' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'woo-product-filter' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-product-filter' ) ), 403 );
		}
		$id     = isset( $_REQUEST['filter'] ) ? (int) $_REQUEST['filter'] : ( isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0 );
		$report = self::a11y_report( $id );
		if ( ! $report ) {
			wp_send_json_error( array( 'message' => __( 'Unknown filter', 'woo-product-filter' ) ), 404 );
		}
		wp_send_json_success( $report );
	}

	/**
	 * Run the checks for a filter, store the result in the option and return it.
	 */
	public static function a11y_report( $filterId ) {
		$filter = self::filter( $filterId );
		if ( ! $filter ) {
			return null;
		}
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4_View' ) ) {
			require_once WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'views' . WPF_DS . 'class-woobewoo-pf-frontend4-view.php';
		}
		$query  = new WooBeWoo_PF_Query();
		$ctx    = self::sanitize_context( array() );
		$engine = self::make_engine( $filter, $query, $ctx );
		$view   = new WooBeWoo_PF_Frontend4_View();
		$html   = $view->render( $filter, $query, $engine, $ctx, array( 'force_drawer' => true, 'force_finder' => true ) );
		$rows   = self::a11y_checks( $html, $filter );
		$pass   = true;
		foreach ( $rows as $r ) {
			if ( ! $r['pass'] ) {
				$pass = false;
			}
		}
		$report = array(
			'filter_id' => (int) $filter['id'],
			'title'     => $filter['title'],
			'date'      => current_time( 'mysql' ),
			'timestamp' => time(),
			'pass'      => $pass,
			'standard'  => 'WCAG 2.2 AA',
			'rows'      => $rows,
			'engine'    => self::engine_of( $filter['id'], $filter['settings'] ),
		);
		$all = get_option( self::OPT_A11Y, array() );
		$all = is_array( $all ) ? $all : array();
		$all[ (int) $filter['id'] ] = $report;
		update_option( self::OPT_A11Y, $all, false );
		return $report;
	}

	public static function last_a11y_report( $filterId ) {
		$all = get_option( self::OPT_A11Y, array() );
		return ( is_array( $all ) && isset( $all[ (int) $filterId ] ) ) ? $all[ (int) $filterId ] : null;
	}

	/**
	 * The structural checks (pass/fail rows with plain words).
	 */
	public static function a11y_checks( $html, $filter ) {
		$rows = array();
		$row  = function ( $id, $label, $pass, $detail = '' ) use ( &$rows ) {
			$rows[] = array(
				'id'     => $id,
				'label'  => $label,
				'pass'   => (bool) $pass,
				'detail' => $detail,
			);
		};
		if ( 'legacy' === self::engine_of( $filter['id'], $filter['settings'] ) ) {
			$row( 'engine', __( 'Uses the 4.0 storefront engine', 'woo-product-filter' ), false, __( 'This filter runs the legacy engine. Switch it to engine 4 in the builder to get the tested markup.', 'woo-product-filter' ) );
		} else {
			$row( 'engine', __( 'Uses the 4.0 storefront engine', 'woo-product-filter' ), true );
		}
		$dom  = new DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8" ?><!doctype html><html><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		$xp = new DOMXPath( $dom );

		// 1. Every input has a label.
		$unlabelled = 0;
		$inputs     = $xp->query( '//input[not(@type="hidden")] | //select | //textarea' );
		foreach ( $inputs as $el ) {
			$ok = false;
			if ( $el->hasAttribute( 'aria-label' ) && '' !== trim( $el->getAttribute( 'aria-label' ) ) ) {
				$ok = true;
			} elseif ( $el->hasAttribute( 'aria-labelledby' ) ) {
				$ok = $xp->query( '//*[@id="' . $el->getAttribute( 'aria-labelledby' ) . '"]' )->length > 0;
			} elseif ( $el->hasAttribute( 'id' ) && $xp->query( '//label[@for="' . $el->getAttribute( 'id' ) . '"]' )->length > 0 ) {
				$ok = true;
			} else {
				$p = $el->parentNode;
				while ( $p && XML_ELEMENT_NODE === $p->nodeType ) {
					if ( 'label' === $p->nodeName ) {
						$ok = '' !== trim( $p->textContent );
						break;
					}
					$p = $p->parentNode;
				}
			}
			if ( ! $ok ) {
				++$unlabelled;
			}
		}
		/* translators: %d: number of controls */
		$row( 'labels', __( 'Every control has a label', 'woo-product-filter' ), 0 === $unlabelled, $unlabelled ? sprintf( __( '%d controls have no label.', 'woo-product-filter' ), $unlabelled ) : sprintf( __( '%d controls checked.', 'woo-product-filter' ), $inputs->length ) );

		// 2. Buttons have names.
		$unnamed = 0;
		$buttons = $xp->query( '//button | //a[@role="button"]' );
		foreach ( $buttons as $b ) {
			$name = trim( $b->textContent );
			if ( '' === $name && ! ( $b->hasAttribute( 'aria-label' ) && '' !== trim( $b->getAttribute( 'aria-label' ) ) ) ) {
				++$unnamed;
			}
		}
		/* translators: %d: number of buttons */
		$row( 'buttons', __( 'Every button has a name', 'woo-product-filter' ), 0 === $unnamed, $unnamed ? sprintf( __( '%d buttons have no name.', 'woo-product-filter' ), $unnamed ) : sprintf( __( '%d buttons checked.', 'woo-product-filter' ), $buttons->length ) );

		// 3. Fieldsets have legends.
		$noLegend = 0;
		$sets     = $xp->query( '//fieldset' );
		foreach ( $sets as $fs ) {
			if ( 0 === $xp->query( './legend', $fs )->length ) {
				++$noLegend;
			}
		}
		/* translators: %d: number of groups */
		$row( 'legends', __( 'Every group of options has a heading (legend)', 'woo-product-filter' ), 0 === $noLegend, sprintf( __( '%d groups checked.', 'woo-product-filter' ), $sets->length ) );

		// 4. Roles on custom controls.
		$badRole = 0;
		foreach ( $xp->query( '//*[@role]' ) as $el ) {
			$r = $el->getAttribute( 'role' );
			if ( in_array( $r, array( 'checkbox', 'radio', 'switch' ), true ) && ! $el->hasAttribute( 'aria-checked' ) ) {
				++$badRole;
			}
			if ( 'dialog' === $r && ( ! $el->hasAttribute( 'aria-modal' ) || ! ( $el->hasAttribute( 'aria-label' ) || $el->hasAttribute( 'aria-labelledby' ) ) ) ) {
				++$badRole;
			}
			if ( 'slider' === $r && ( ! $el->hasAttribute( 'aria-valuenow' ) || ! $el->hasAttribute( 'aria-valuemin' ) || ! $el->hasAttribute( 'aria-valuemax' ) ) ) {
				++$badRole;
			}
		}
		/* translators: %d: number of roles */
		$row( 'roles', __( 'Roles carry their state (checked, modal, slider values)', 'woo-product-filter' ), 0 === $badRole, $badRole ? sprintf( __( '%d roles are incomplete.', 'woo-product-filter' ), $badRole ) : '' );

		// 5. Live region.
		$live = $xp->query( '//*[@aria-live] | //*[@role="status"]' )->length;
		$row( 'live', __( 'A live region announces the product count', 'woo-product-filter' ), $live > 0 );

		// 6. Dialog (drawer and finder) labelled, focus trap markers present.
		$dialogs = $xp->query( '//*[@role="dialog"]' );
		$dialogOk = $dialogs->length > 0;
		foreach ( $dialogs as $d ) {
			if ( ! $d->hasAttribute( 'aria-modal' ) || ! ( $d->hasAttribute( 'aria-label' ) || $d->hasAttribute( 'aria-labelledby' ) ) ) {
				$dialogOk = false;
			}
		}
		/* translators: %d: number of dialogs */
		$row( 'dialog', __( 'The drawer and the finder are labelled dialogs', 'woo-product-filter' ), $dialogOk, sprintf( __( '%d dialogs found.', 'woo-product-filter' ), $dialogs->length ) );

		// 7. Unique ids.
		$ids  = array();
		$dups = 0;
		foreach ( $xp->query( '//*[@id]' ) as $el ) {
			$i = $el->getAttribute( 'id' );
			if ( isset( $ids[ $i ] ) ) {
				++$dups;
			}
			$ids[ $i ] = 1;
		}
		/* translators: %d: number of duplicate ids */
		$row( 'ids', __( 'No duplicate ids', 'woo-product-filter' ), 0 === $dups, $dups ? sprintf( __( '%d duplicate ids.', 'woo-product-filter' ), $dups ) : '' );

		// 8. Images have alt text; decorative SVGs are hidden.
		$noAlt = 0;
		foreach ( $xp->query( '//img' ) as $img ) {
			if ( ! $img->hasAttribute( 'alt' ) ) {
				++$noAlt;
			}
		}
		$svgBad = 0;
		foreach ( $xp->query( '//svg' ) as $svg ) {
			if ( ! $svg->hasAttribute( 'aria-hidden' ) && ! $svg->hasAttribute( 'aria-label' ) && ! $svg->hasAttribute( 'role' ) ) {
				++$svgBad;
			}
		}
		$row( 'images', __( 'Pictures have alt text and icons are hidden from screen readers', 'woo-product-filter' ), 0 === $noAlt && 0 === $svgBad );

		// 9. Contrast of the configured colours.
		$accent  = self::setting( $filter['settings'], 'wbw_accent' );
		$onTool  = self::contrast_text( $accent );
		$pairs   = array(
			array( 'ink on card', '#171509', '#FFFFFF' ),
			array( 'ink on paper', '#171509', '#F7F4EA' ),
			array( 'ink-2 on card', '#5A5645', '#FFFFFF' ),
			array( 'tool-text on paper', '#046BD2', '#F7F4EA' ),
			array( 'text on accent', $onTool, $accent ),
			array( 'white on ink', '#FFFFFF', '#171509' ),
		);
		$bad     = array();
		$ratios  = array();
		foreach ( $pairs as $p ) {
			$ratio    = self::contrast_ratio( $p[1], $p[2] );
			$ratios[] = $p[0] . ' ' . number_format( $ratio, 1 ) . ':1';
			if ( $ratio < 4.5 ) {
				$bad[] = $p[0];
			}
		}
		$row( 'contrast', __( 'Text contrast is at least 4.5:1 for the configured colours', 'woo-product-filter' ), empty( $bad ), implode( ', ', $ratios ) );

		// 10. Focus style and target size in the stylesheet.
		$css = (string) @file_get_contents( WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'css' . WPF_DS . 'frontend-4' . WPF_DS . 'filter.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$row( 'focus', __( 'Keyboard focus is visible (3px ring)', 'woo-product-filter' ), false !== strpos( $css, ':focus-visible' ) && false !== strpos( $css, 'outline: 3px solid' ) );
		$row( 'targets', __( 'Tap targets are at least 44px', 'woo-product-filter' ), false !== strpos( $css, 'min-height: 44px' ) );
		$row( 'motion', __( 'Motion is reduced when the shopper asks for it', 'woo-product-filter' ), false !== strpos( $css, 'prefers-reduced-motion' ) );
		$row( 'important', __( 'The stylesheet has no !important rules', 'woo-product-filter' ), false === strpos( $css, '!important' ) );

		// 11. Keyboard: swatches and squares are real inputs (Space, Enter, arrows).
		$swatches = $xp->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wpf4__swatch ")]//input | //*[contains(concat(" ", normalize-space(@class), " "), " wpf4__square ")]//input' )->length;
		$customNoInput = $xp->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wpf4__swatch ")][not(.//input)]' )->length;
		/* translators: %d: number of swatch controls */
		$row( 'keyboard', __( 'Swatches and squares are keyboard operable controls', 'woo-product-filter' ), 0 === $customNoInput, sprintf( __( '%d swatch controls checked.', 'woo-product-filter' ), $swatches ) );

		// 12. Range inputs expose their values.
		$rangeBad = 0;
		foreach ( $xp->query( '//input[@type="range"]' ) as $r ) {
			if ( ! $r->hasAttribute( 'aria-valuetext' ) && ! $r->hasAttribute( 'aria-label' ) ) {
				++$rangeBad;
			}
		}
		$row( 'ranges', __( 'Price sliders expose their values to screen readers', 'woo-product-filter' ), 0 === $rangeBad );

		return apply_filters( 'woobewoo_pf_a11y_rows', $rows, $html, $filter );
	}

	/**
	 * WCAG relative luminance contrast ratio of two hex colours.
	 */
	public static function contrast_ratio( $fg, $bg ) {
		$l1 = self::luminance( $fg );
		$l2 = self::luminance( $bg );
		$hi = max( $l1, $l2 );
		$lo = min( $l1, $l2 );
		return ( $hi + 0.05 ) / ( $lo + 0.05 );
	}

	private static function luminance( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return 0;
		}
		$c = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v   = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	/**
	 * Ink or white on a background, whichever reads better (ink wins ties: text on blue is always ink).
	 */
	public static function contrast_text( $bg ) {
		return self::contrast_ratio( '#171509', $bg ) >= self::contrast_ratio( '#FFFFFF', $bg ) ? '#171509' : '#FFFFFF';
	}

	/**
	 * [wpf-a11y-badge filter="1"] - "Filter tested to WCAG 2.2 AA" with the date when every check passed.
	 */
	public static function badgeShortcode( $atts ) {
		$atts   = shortcode_atts( array( 'filter' => 0, 'id' => 0 ), $atts, 'wpf-a11y-badge' );
		$id     = (int) ( $atts['filter'] ? $atts['filter'] : $atts['id'] );
		$report = null;
		if ( $id ) {
			$report = self::last_a11y_report( $id );
		} else {
			$all = get_option( self::OPT_A11Y, array() );
			foreach ( (array) $all as $r ) {
				if ( ! empty( $r['pass'] ) ) {
					$report = $r;
					break;
				}
			}
		}
		if ( ! $report || empty( $report['pass'] ) ) {
			return '';
		}
		$date = date_i18n( get_option( 'date_format' ), (int) $report['timestamp'] );
		$svg  = '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
		return '<span class="wpf4-a11y-badge">' . $svg . '<span>' . esc_html__( 'Filter tested to WCAG 2.2 AA', 'woo-product-filter' ) . '</span> <time datetime="' . esc_attr( gmdate( 'Y-m-d', (int) $report['timestamp'] ) ) . '">' . esc_html( $date ) . '</time></span>';
	}
}
