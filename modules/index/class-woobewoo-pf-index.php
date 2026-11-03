<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Index Class
 *
 * The full facet index: one row per product and per variation, their terms, numeric meta and prices per currency.
 * Everything the 4.0 storefront engine shows (results and counts) comes from these four tables.
 *
 * Tables (all utf8mb4, created by this module on first run and after an upgrade):
 *   wpf_index_products (product_id, parent_id, product_type, price, regular_price, sale_price, on_sale, stock_status,
 *                       stock_qty, rating, review_count, total_sales, featured, published, menu_order, author_id,
 *                       created, modified, indexed_at)
 *   wpf_index_terms    (product_id, taxonomy, term_id, term_slug)
 *   wpf_index_meta     (product_id, meta_key, value_num, value_text)
 *   wpf_index_prices   (product_id, currency, price)
 *
 * A variation row carries every term that applies to it: its own variation attributes plus the parent's terms for
 * all other taxonomies (categories, tags, brand, attributes that are not used for variations, and "any" attributes),
 * so a query over leaf rows (everything except variable parents) is variation aware by construction.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Index extends WooBeWoo_PF_Module {

	const DB_VERSION   = '5';
	const OPT_STATE    = 'wpf_index_state';
	const OPT_PROGRESS = 'wpf_index_rebuild';
	const OPT_QUEUE    = 'wpf_index_queue';
	const LOCK         = 'wpf_index_lock';
	const BATCH        = 500;
	const HOOK_BATCH   = 'wpf_index_batch';
	const HOOK_QUEUE   = 'wpf_index_queue_run';
	const INLINE_LIMIT = 25;

	/**
	 * Singleton when the module is bootstrapped by the woofilters module (no row in the modules table needed).
	 */
	private static $instance = null;
	private static $booted   = false;

	/**
	 * Product ids collected during this request, written on shutdown.
	 */
	private $dirty = array();

	/**
	 * Cached term maps (taxonomy => slug => id) per request.
	 */
	private static $termSlugCache = array();

	/**
	 * The framework passes the modules table row; the woofilters module passes nothing.
	 */
	public function __construct( $d = null ) {
		if ( is_array( $d ) ) {
			parent::__construct( $d );
		} else {
			$this->setCode( 'index' );
			$this->setLabel( 'index' );
			$this->setTypeID( 1 );
		}
		self::$instance = $this;
	}

	/**
	 * Instance (framework module if registered, else our own).
	 */
	public static function _() {
		if ( ! self::$instance ) {
			$mod = WooBeWoo_PF_Frame::_()->getModule( 'index' );
			self::$instance = $mod ? $mod : new self();
		}
		return self::$instance;
	}

	/**
	 * Bootstrap once (called from the woofilters module init, or by the framework through init()).
	 */
	public static function boot() {
		if ( self::$booted ) {
			return self::_();
		}
		self::$booted = true;
		$i = self::_();
		$i->hooks();
		return $i;
	}

	public function init() {
		self::boot();
	}

	/* ------------------------------------------------------------------ tables */

	/**
	 * Table names with the WordPress prefix.
	 */
	public static function tables() {
		global $wpdb;
		return array(
			'products' => $wpdb->prefix . 'wpf_index_products',
			'terms'    => $wpdb->prefix . 'wpf_index_terms',
			'meta'     => $wpdb->prefix . 'wpf_index_meta',
			'prices'   => $wpdb->prefix . 'wpf_index_prices',
		);
	}

	public static function table( $which ) {
		$t = self::tables();
		return $t[ $which ];
	}

	/**
	 * Create or upgrade the tables (dbDelta), stamp the state option and remember filters that pre-date 4.0.
	 */
	public static function install_tables() {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$t       = self::tables();
		$charset = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
		dbDelta(
			"CREATE TABLE {$t['products']} (
  product_id bigint(20) unsigned NOT NULL,
  parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_type varchar(32) NOT NULL DEFAULT 'simple',
  root_id bigint(20) unsigned NOT NULL DEFAULT 0,
  price decimal(19,4) NULL,
  price_max decimal(19,4) NULL,
  regular_price decimal(19,4) NULL,
  sale_price decimal(19,4) NULL,
  on_sale tinyint(1) NOT NULL DEFAULT 0,
  stock_status varchar(20) NOT NULL DEFAULT 'instock',
  stock_qty int(11) NULL,
  rating decimal(3,2) NOT NULL DEFAULT 0,
  review_count int(11) NOT NULL DEFAULT 0,
  total_sales int(11) NOT NULL DEFAULT 0,
  featured tinyint(1) NOT NULL DEFAULT 0,
  published tinyint(1) NOT NULL DEFAULT 1,
  menu_order int(11) NOT NULL DEFAULT 0,
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(191) NOT NULL DEFAULT '',
  created datetime NULL,
  modified datetime NULL,
  indexed_at datetime NOT NULL,
  PRIMARY KEY  (product_id),
  KEY leaf (product_type,published,parent_id),
  KEY parent_published (parent_id,published),
  KEY published_stock_price (published,stock_status,price),
  KEY published_price (published,price),
  KEY sort_default (published,parent_id,menu_order,title),
  KEY author_id (author_id),
  KEY root_id (root_id)
) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['terms']} (
  product_id bigint(20) unsigned NOT NULL,
  taxonomy varchar(64) NOT NULL,
  term_id bigint(20) unsigned NOT NULL,
  term_slug varchar(200) NOT NULL DEFAULT '',
  has_variation tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY  (product_id,taxonomy,term_id),
  KEY tax_term_hv (taxonomy,term_id,has_variation,product_id)
) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['meta']} (
  product_id bigint(20) unsigned NOT NULL,
  meta_key varchar(191) NOT NULL,
  value_num decimal(19,4) NULL,
  value_text varchar(191) NULL,
  PRIMARY KEY  (product_id,meta_key),
  KEY key_num_product (meta_key,value_num,product_id),
  KEY key_text (meta_key,value_text(32))
) $charset;"
		);
		dbDelta(
			"CREATE TABLE {$t['prices']} (
  product_id bigint(20) unsigned NOT NULL,
  currency varchar(8) NOT NULL,
  price decimal(19,4) NULL,
  PRIMARY KEY  (product_id,currency),
  KEY currency_price (currency,price)
) $charset;"
		);

		$state = self::state();
		if ( empty( $state['created'] ) ) {
			$state['created'] = time();
			$state['version'] = 1;
			// Filters that exist before the index is installed keep the legacy storefront engine unless the owner switches.
			$ids = $wpdb->get_col( 'SELECT id FROM `' . $wpdb->prefix . 'wpf_filters`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			update_option( 'wpf_filters_before_4', array_map( 'intval', (array) $ids ), false );
			// Nothing was made by a 4.0 pre-release on this site (see mark_prerelease_filters()).
			update_option( 'wpf_filters_engine4_seen', array(), true );
		}
		$prevVersion         = empty( $state['db_version'] ) ? '' : (string) $state['db_version'];
		$layoutChanged       = '' !== $prevVersion && self::DB_VERSION !== $prevVersion && ! empty( $state['last_full_run'] );
		$state['db_version'] = self::DB_VERSION;
		update_option( self::OPT_STATE, $state, true );
		// Keys replaced since 4.0.0 development builds (dbDelta only adds).
		foreach ( array( array( $t['terms'], 'term_id' ), array( $t['terms'], 'tax_term' ), array( $t['products'], 'parent_id' ), array( $t['products'], 'price' ), array( $t['products'], 'stock_status' ), array( $t['meta'], 'key_num' ) ) as $k ) {
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$k[0]} WHERE Key_name = %s", $k[1] ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
				$wpdb->query( "ALTER TABLE {$k[0]} DROP INDEX {$k[1]}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			}
		}
		if ( $layoutChanged ) {
			if ( (int) $prevVersion >= 3 ) {
				// 3 -> 4 added the title column, 4 -> 5 root_id and price_max: all derivable in place, no rebuild needed.
				if ( (int) $prevVersion < 4 ) {
					$wpdb->query( "UPDATE {$t['products']} i INNER JOIN {$wpdb->posts} p ON p.ID = IF(i.parent_id = 0, i.product_id, i.parent_id) SET i.title = LEFT(p.post_title, 191)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
				}
				self::fill_derived_columns();
				self::bump_version();
			} elseif ( did_action( 'init' ) ) {
				self::schedule_full_rebuild();
			} else {
				add_action( 'init', array( __CLASS__, 'schedule_full_rebuild' ), 20 );
			}
		}
		// First run: fill the index in the background (scheduling needs WordPress fully loaded).
		if ( empty( $state['last_full_run'] ) && ! self::progress_running() ) {
			if ( did_action( 'init' ) ) {
				self::schedule_full_rebuild();
			} else {
				add_action( 'init', array( __CLASS__, 'schedule_full_rebuild' ), 20 );
			}
		}
	}

	/**
	 * Version change check (hooked to init): create or upgrade the tables once.
	 */
	public static function maybe_install() {
		if ( ! self::installed() ) {
			self::install_tables();
		}
		// 4.0.1: read from the database, not from this request's option cache. Right after the folder swap several
		// requests run at once; one of them loaded its options before another wrote the upgrade markers, and marking
		// every existing filter as engine 4 here sent that shopper an empty page.
		if ( false === self::fresh_option( 'wpf_filters_engine4_seen' ) ) {
			self::mark_prerelease_filters();
		}
	}

	/**
	 * Option value straight from the database (bypasses the alloptions / per-option cache of this request).
	 *
	 * @since 4.0.1
	 */
	public static function fresh_option( $name ) {
		global $wpdb;
		$row = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( null === $row ) {
			return false;
		}
		wp_cache_delete( $name, 'options' );
		$all = wp_cache_get( 'alloptions', 'options' );
		if ( is_array( $all ) && isset( $all[ $name ] ) ) {
			$all[ $name ] = $row;
			wp_cache_set( 'alloptions', $all, 'options' );
		}
		return maybe_unserialize( $row );
	}

	/**
	 * Sites that ran a 4.0 pre-release build have filters made by 4.0 without a stored engine. Remember them once
	 * as engine 4, so that WooBeWoo_PF_Frontend4::engine_of() can treat any other row without an engine as written
	 * by 3.x. Runs once (the option is then present); a 4.0.0 install sets the option empty when it installs.
	 */
	public static function mark_prerelease_filters() {
		global $wpdb;
		$before = self::fresh_option( 'wpf_filters_before_4' );
		if ( false === $before ) {
			// The upgrade snapshot is not written yet (another request is installing): decide nothing in this request.
			return;
		}
		$before = array_map( 'intval', (array) $before );
		$seen   = array();
		$table  = $wpdb->prefix . 'wpf_filters';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( 'SELECT id, setting_data FROM `' . esc_sql( $table ) . '`', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the plugin's own filters table, name built from $wpdb->prefix, no input
			foreach ( (array) $rows as $r ) {
				if ( in_array( (int) $r['id'], $before, true ) ) {
					continue;
				}
				$s = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( $r['setting_data'] ) : maybe_unserialize( $r['setting_data'] );
				if ( empty( $s['settings']['frontend_engine'] ) ) {
					$seen[] = (int) $r['id'];
				}
			}
		}
		update_option( 'wpf_filters_engine4_seen', $seen, true );
	}

	/**
	 * root_id (the product a row counts as) and price_max (highest variation price) for rows indexed before 4.0.0 b5.
	 */
	public static function fill_derived_columns() {
		global $wpdb;
		$t = self::tables();
		$wpdb->query( "UPDATE {$t['products']} SET root_id = IF(parent_id = 0, product_id, parent_id), price_max = price WHERE root_id = 0 OR price_max IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "UPDATE {$t['products']} p INNER JOIN (SELECT parent_id, MAX(price) AS mx FROM {$t['products']} WHERE parent_id > 0 AND published = 1 GROUP BY parent_id) v ON v.parent_id = p.product_id SET p.price_max = v.mx WHERE p.product_type = 'variable'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
	}

	public static function onActivated( $plugin ) {
		if ( false !== strpos( (string) $plugin, 'woo-product-filter' ) ) {
			self::install_tables();
		}
	}

	/**
	 * State option (version, db_version, last_full_run, last_duration, created).
	 */
	public static function state() {
		$state = get_option( self::OPT_STATE, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Index version: part of every cache key. Bumping it drops every cached result and count.
	 */
	public static function version() {
		$state = self::state();
		return empty( $state['version'] ) ? 1 : (int) $state['version'];
	}

	public static function bump_version() {
		$state            = self::state();
		$state['version'] = ( empty( $state['version'] ) ? 1 : (int) $state['version'] ) + 1;
		update_option( self::OPT_STATE, $state, true );
		wp_cache_set( 'wpf4_version', $state['version'], 'wpf4' );
		do_action( 'woobewoo_pf_index_version_bumped', $state['version'] );
		return $state['version'];
	}

	public static function installed() {
		global $wpdb;
		$state = self::state();
		if ( empty( $state['db_version'] ) || self::DB_VERSION !== (string) $state['db_version'] ) {
			return false;
		}
		static $exists = null;
		if ( null === $exists ) {
			$t      = self::table( 'products' );
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return $exists;
	}

	/* ------------------------------------------------------------------ hooks */

	protected function hooks() {
		// Tables are created or upgraded after `init` (never while plugins load: cron constants and
		// WooCommerce are not ready yet) and on activation.
		if ( did_action( 'init' ) ) {
			self::maybe_install();
		} else {
			add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		}
		add_action( 'activated_plugin', array( __CLASS__, 'onActivated' ), 10, 1 );
		add_action( 'woocommerce_after_product_object_save', array( $this, 'onProductObjectSave' ), 999, 1 );
		add_action( 'woocommerce_new_product', array( $this, 'onProductId' ), 999, 1 );
		add_action( 'woocommerce_update_product', array( $this, 'onProductId' ), 999, 1 );
		add_action( 'woocommerce_new_product_variation', array( $this, 'onVariationId' ), 999, 1 );
		add_action( 'woocommerce_update_product_variation', array( $this, 'onVariationId' ), 999, 1 );
		add_action( 'woocommerce_product_set_stock', array( $this, 'onProductObjectSave' ), 999, 1 );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'onProductObjectSave' ), 999, 1 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'onStockStatus' ), 999, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'onStockStatus' ), 999, 3 );
		add_action( 'woocommerce_product_import_inserted_product_object', array( $this, 'onProductObjectSave' ), 999, 1 );
		add_action( 'wp_trash_post', array( $this, 'onPostChange' ), 999, 1 );
		add_action( 'untrashed_post', array( $this, 'onPostChange' ), 999, 1 );
		add_action( 'before_delete_post', array( $this, 'onPostDelete' ), 10, 1 );
		add_action( 'set_object_terms', array( $this, 'onSetObjectTerms' ), 999, 6 );
		add_action( 'edited_term', array( $this, 'onEditedTerm' ), 999, 3 );
		add_action( 'delete_term', array( $this, 'onDeletedTerm' ), 999, 4 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'onOrderCompleted' ), 999, 1 );
		add_action( 'woocommerce_recorded_sales', array( $this, 'onOrderCompleted' ), 999, 1 );
		add_action( self::HOOK_BATCH, array( $this, 'runBatch' ), 10, 2 );
		add_action( self::HOOK_QUEUE, array( $this, 'runQueue' ), 10, 0 );
		add_action( 'shutdown', array( $this, 'flush' ), 5 );
		// Keep the legacy "rebuild" buttons (Speed and SEO screen, options) feeding the new index too.
		add_action( 'wpf_calc_meta_indexing', array( __CLASS__, 'schedule_full_rebuild' ), 20, 0 );
		add_action( 'woobewoo_pf_index_rebuild', array( __CLASS__, 'schedule_full_rebuild' ), 10, 0 );
		// WooCommerce "Hide out of stock items" changes what the 4.0 engine counts and lists: drop the cached answers.
		add_action( 'update_option_woocommerce_hide_out_of_stock_items', array( __CLASS__, 'bump_version' ), 10, 0 );
		add_action( 'add_option_woocommerce_hide_out_of_stock_items', array( __CLASS__, 'bump_version' ), 10, 0 );
	}

	public function onProductObjectSave( $product ) {
		if ( defined( 'WPF_INDEX_DEFER' ) && WPF_INDEX_DEFER ) {
			return;
		}
		if ( $product instanceof WC_Product ) {
			$this->mark( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
		}
	}

	public function onProductId( $id ) {
		if ( defined( 'WPF_INDEX_DEFER' ) && WPF_INDEX_DEFER ) {
			return;
		}
		$this->mark( (int) $id );
	}

	public function onVariationId( $id ) {
		if ( defined( 'WPF_INDEX_DEFER' ) && WPF_INDEX_DEFER ) {
			return;
		}
		$parent = (int) wp_get_post_parent_id( (int) $id );
		$this->mark( $parent ? $parent : (int) $id );
	}

	public function onStockStatus( $id, $status = '', $product = null ) {
		if ( defined( 'WPF_INDEX_DEFER' ) && WPF_INDEX_DEFER ) {
			return;
		}
		$parent = (int) wp_get_post_parent_id( (int) $id );
		$this->mark( $parent ? $parent : (int) $id );
	}

	public function onPostChange( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'product' === $type ) {
			$this->mark( (int) $post_id );
		} elseif ( 'product_variation' === $type ) {
			$this->mark( (int) wp_get_post_parent_id( $post_id ) );
		}
	}

	public function onPostDelete( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'product' === $type ) {
			self::remove_product( (int) $post_id );
			self::bump_version();
		} elseif ( 'product_variation' === $type ) {
			$this->mark( (int) wp_get_post_parent_id( $post_id ) );
		}
	}

	public function onSetObjectTerms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( defined( 'WPF_INDEX_DEFER' ) && WPF_INDEX_DEFER ) {
			return;
		}
		if ( in_array( $taxonomy, array( 'product_type', 'product_shipping_class' ), true ) ) {
			return;
		}
		if ( 'product' === get_post_type( $object_id ) ) {
			$this->mark( (int) $object_id );
		}
	}

	/**
	 * Term renamed or re-slugged: update the slug column in place (no re-index needed).
	 */
	public function onEditedTerm( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;
		$term = get_term( $term_id, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$wpdb->update( self::table( 'terms' ), array( 'term_slug' => $term->slug ), array( 'term_id' => (int) $term_id, 'taxonomy' => $taxonomy ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::bump_version();
		}
	}

	public function onDeletedTerm( $term_id, $tt_id, $taxonomy, $deleted_term ) {
		global $wpdb;
		$wpdb->delete( self::table( 'terms' ), array( 'term_id' => (int) $term_id, 'taxonomy' => $taxonomy ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::bump_version();
	}

	public function onOrderCompleted( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		foreach ( $order->get_items() as $item ) {
			if ( is_callable( array( $item, 'get_product_id' ) ) ) {
				$this->mark( (int) $item->get_product_id() );
			}
		}
	}

	/**
	 * Remember a product for this request; written on shutdown (inline up to INLINE_LIMIT, else queued).
	 */
	public function mark( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id > 0 ) {
			$this->dirty[ $product_id ] = 1;
		}
	}

	public function flush() {
		if ( empty( $this->dirty ) ) {
			return;
		}
		$ids         = array_keys( $this->dirty );
		$this->dirty = array();
		if ( count( $ids ) <= self::INLINE_LIMIT ) {
			foreach ( $ids as $id ) {
				self::index_product( $id );
			}
			self::bump_version();
		} else {
			$queue = get_option( self::OPT_QUEUE, array() );
			$queue = array_values( array_unique( array_merge( is_array( $queue ) ? $queue : array(), $ids ) ) );
			update_option( self::OPT_QUEUE, $queue, false );
			self::schedule( self::HOOK_QUEUE, array(), 1 );
		}
	}

	/**
	 * Queue runner (Action Scheduler or WP-Cron): up to BATCH ids per run, then reschedules itself.
	 */
	public function runQueue() {
		if ( ! self::lock() ) {
			self::schedule( self::HOOK_QUEUE, array(), 60 );
			return;
		}
		$queue = get_option( self::OPT_QUEUE, array() );
		$queue = is_array( $queue ) ? $queue : array();
		$chunk = array_splice( $queue, 0, self::BATCH );
		update_option( self::OPT_QUEUE, $queue, false );
		foreach ( $chunk as $id ) {
			self::index_product( (int) $id );
		}
		self::unlock();
		self::bump_version();
		if ( $queue ) {
			self::schedule( self::HOOK_QUEUE, array(), 1 );
		}
	}

	/* ------------------------------------------------------------------ scheduling */

	/**
	 * Schedule a job through Action Scheduler when WooCommerce provides it, else WP-Cron.
	 */
	public static function schedule( $hook, $args = array(), $delay = 0 ) {
		if ( ! did_action( 'init' ) ) {
			// Too early: queue the call for after init.
			add_action(
				'init',
				function () use ( $hook, $args, $delay ) {
					WooBeWoo_PF_Index::schedule( $hook, $args, $delay );
				},
				30
			);
			return true;
		}
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, 'woo-product-filter' ) ) {
				return true;
			}
			as_schedule_single_action( time() + (int) $delay, $hook, $args, 'woo-product-filter' );
			return true;
		}
		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( time() + (int) $delay, $hook, $args );
		}
		return true;
	}

	public static function lock() {
		if ( get_transient( self::LOCK ) ) {
			return false;
		}
		set_transient( self::LOCK, time(), 5 * MINUTE_IN_SECONDS );
		return true;
	}

	public static function unlock() {
		delete_transient( self::LOCK );
	}

	/**
	 * Start a full rebuild in batches of BATCH products with progress in an option.
	 */
	public static function schedule_full_rebuild() {
		global $wpdb;
		if ( self::progress_running() ) {
			return self::progress();
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash','auto-draft')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$prog  = array(
			'total'    => $total,
			'done'     => 0,
			'started'  => time(),
			'eta'      => 0,
			'finished' => 0,
			'duration' => 0,
			'running'  => 1,
			'last_id'  => 0,
		);
		self::save_progress( $prog );
		self::schedule( self::HOOK_BATCH, array( 0, $prog['started'] ), 0 );
		return $prog;
	}

	public static function progress() {
		$p = get_option( self::OPT_PROGRESS, array() );
		return is_array( $p ) ? $p : array();
	}

	/**
	 * Store the progress and mirror it in `wpf_index_rebuild_progress` (offset, total, done) for the Speed screen.
	 */
	private static function save_progress( $prog ) {
		update_option( self::OPT_PROGRESS, $prog, false );
		update_option(
			'wpf_index_rebuild_progress',
			empty( $prog['running'] ) ? array( 'total' => (int) $prog['total'], 'offset' => (int) $prog['done'], 'done' => 1 ) : array( 'total' => (int) $prog['total'], 'offset' => (int) $prog['done'], 'done' => 0 ),
			false
		);
	}

	public static function progress_running() {
		$p = self::progress();
		if ( empty( $p['running'] ) ) {
			return false;
		}
		// A run that has not moved for 15 minutes is stale (crashed job): let a new one start.
		$last = empty( $p['updated'] ) ? ( empty( $p['started'] ) ? 0 : $p['started'] ) : $p['updated'];
		return ( time() - $last ) < 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * One batch of the full rebuild: products after $last_id, BATCH at a time, then reschedules.
	 */
	public function runBatch( $last_id = 0, $run = 0 ) {
		global $wpdb;
		$prog = self::progress();
		if ( $run && ! empty( $prog['started'] ) && (int) $prog['started'] !== (int) $run ) {
			return; // an older run that was replaced
		}
		if ( ! self::lock() ) {
			self::schedule( self::HOOK_BATCH, array( $last_id, $run ), 30 );
			return;
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status NOT IN ('trash','auto-draft') AND ID > %d ORDER BY ID ASC LIMIT %d", (int) $last_id, self::BATCH ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$t0  = microtime( true );
		// Stay well inside small hosts (128 MB, 30 s): stop the batch early at 60% of the memory limit or after
		// 20 s (half of max_execution_time when that is lower) and let the next batch continue from here.
		$memLimit = function_exists( 'wp_convert_hr_to_bytes' ) ? wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) ) : 0;
		$memStop  = $memLimit > 0 ? (int) ( $memLimit * 0.6 ) : 0;
		$maxExec  = (int) ini_get( 'max_execution_time' );
		$timeStop = $maxExec > 0 ? min( 20, max( 5, (int) floor( $maxExec / 2 ) ) ) : 20;
		$done     = 0;
		$partial  = false;
		foreach ( $ids as $id ) {
			self::index_product( (int) $id );
			$last_id = (int) $id;
			++$done;
			if ( 0 === $done % 50 && ! wp_using_ext_object_cache() && function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime(); // the in-memory cache of this request only: product objects pile up otherwise
			}
			if ( $done < count( $ids ) && ( ( $memStop && memory_get_usage() > $memStop ) || ( microtime( true ) - $t0 ) > $timeStop ) ) {
				$partial = true;
				break;
			}
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			wp_cache_flush();
		}
		$prog            = self::progress();
		$prog['done']    = (int) ( empty( $prog['done'] ) ? 0 : $prog['done'] ) + $done;
		$prog['last_id'] = $last_id;
		$prog['updated'] = time();
		$elapsed         = time() - (int) $prog['started'];
		$prog['eta']     = ( $prog['done'] > 0 && ! empty( $prog['total'] ) ) ? (int) round( $elapsed * ( $prog['total'] - $prog['done'] ) / max( 1, $prog['done'] ) ) : 0;
		if ( ! $partial && count( $ids ) < self::BATCH ) {
			// Done: drop rows of products that no longer exist, stamp the state.
			$t = self::tables();
			$wpdb->query( "DELETE i FROM {$t['products']} i LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id WHERE p.ID IS NULL OR p.post_status = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			$wpdb->query( "DELETE i FROM {$t['terms']} i LEFT JOIN {$t['products']} p ON p.product_id = i.product_id WHERE p.product_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			$wpdb->query( "DELETE i FROM {$t['meta']} i LEFT JOIN {$t['products']} p ON p.product_id = i.product_id WHERE p.product_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			$wpdb->query( "DELETE i FROM {$t['prices']} i LEFT JOIN {$t['products']} p ON p.product_id = i.product_id WHERE p.product_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			$prog['running']  = 0;
			$prog['finished'] = time();
			$prog['duration'] = $prog['finished'] - (int) $prog['started'];
			$prog['eta']      = 0;
			$state            = self::state();
			$state['last_full_run'] = $prog['finished'];
			$state['last_duration'] = $prog['duration'];
			update_option( self::OPT_STATE, $state, true );
			self::save_progress( $prog );
			self::unlock();
			self::bump_version();
			do_action( 'woobewoo_pf_index_rebuilt', $prog );
			return;
		}
		self::save_progress( $prog );
		self::unlock();
		self::schedule( self::HOOK_BATCH, array( $last_id, (int) $prog['started'] ), 0 );
	}

	/**
	 * Full rebuild right now, in this process (WP-CLI, benchmarks, tests). Returns the duration in seconds.
	 */
	public static function rebuild_now() {
		// A fresh run: background batches of an older run see a new "started" stamp and stop.
		update_option( self::OPT_PROGRESS, array( 'running' => 0 ), false );
		self::unlock();
		$prog    = self::schedule_full_rebuild();
		$started = empty( $prog['started'] ) ? time() : (int) $prog['started'];
		$last    = 0;
		$guard   = 0;
		do {
			$before = self::progress();
			self::_()->runBatch( $last, $started );
			$prog = self::progress();
			$last = empty( $prog['last_id'] ) ? 0 : (int) $prog['last_id'];
			++$guard;
		} while ( ! empty( $prog['running'] ) && $guard < 100000 && $prog['done'] !== $before['done'] );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK_BATCH, null, 'woo-product-filter' );
		}
		wp_clear_scheduled_hook( self::HOOK_BATCH, array( $last, $started ) );
		return empty( $prog['duration'] ) ? ( time() - $started ) : (int) $prog['duration'];
	}

	/**
	 * Index every published product that has no row yet and drop rows of products that no longer exist
	 * (after imports, restores or an interrupted rebuild). Returns the number of products indexed.
	 */
	public static function index_missing( $limit = 0 ) {
		global $wpdb;
		$t   = self::tables();
		$sql = "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$t['products']} i ON i.product_id = p.ID WHERE p.post_type = 'product' AND p.post_status NOT IN ('trash','auto-draft') AND i.product_id IS NULL ORDER BY p.ID ASC";
		if ( $limit > 0 ) {
			$sql = $wpdb->prepare( $sql . ' LIMIT %d', (int) $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql holds only our own index table name and literals
		}
		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- index table query; the only variable part (LIMIT) is prepared above
		$n   = 0;
		foreach ( (array) $ids as $id ) {
			if ( self::index_product( (int) $id ) ) {
				++$n;
			}
			if ( 0 === $n % 500 ) {
				wp_cache_flush();
			}
		}
		$wpdb->query( "DELETE i FROM {$t['products']} i LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id WHERE p.ID IS NULL OR p.post_status = 'trash'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		foreach ( array( 'terms', 'meta', 'prices' ) as $k ) {
			$wpdb->query( "DELETE i FROM {$t[ $k ]} i LEFT JOIN {$t['products']} p ON p.product_id = i.product_id WHERE p.product_id IS NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		}
		$state                  = self::state();
		$state['last_full_run'] = time();
		update_option( self::OPT_STATE, $state, true );
		self::bump_version();
		return $n;
	}

	/* ------------------------------------------------------------------ health */

	/**
	 * Health data for the Speed and SEO screen and the diagnostics.
	 */
	public static function health() {
		global $wpdb;
		if ( ! self::installed() ) {
			return array(
				'installed' => false,
				'version'   => 0,
			);
		}
		$t        = self::tables();
		$state    = self::state();
		$prog     = self::progress();
		$newest   = $wpdb->get_var( "SELECT MAX(indexed_at) FROM {$t['products']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$indexed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['products']} WHERE parent_id = 0 AND published = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$rows     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['products']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$publ     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$age      = $newest ? max( 0, time() - (int) strtotime( $newest . ' UTC' ) ) : null;
		$queue    = get_option( self::OPT_QUEUE, array() );
		$t0       = microtime( true );
		$wpdb->get_var( "SELECT COUNT(*) FROM {$t['products']} WHERE parent_id = 0 AND published = 1 AND stock_status <> 'outofstock'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$queryMs  = round( ( microtime( true ) - $t0 ) * 1000, 1 );
		$percent  = $publ > 0 ? min( 100, (int) round( $indexed / $publ * 100 ) ) : ( $indexed > 0 ? 100 : 0 );
		return array(
			// Short names the Speed and SEO screen reads.
			'total'              => $publ,
			'indexed'            => $indexed,
			'percent'            => $percent,
			'fresh'              => $percent >= 95 && ( null === $age || $age < DAY_IN_SECONDS ),
			'scheduled'          => self::progress_running(),
			'last_query_ms'      => $queryMs,
			'installed'          => true,
			'version'            => self::version(),
			'db_version'         => self::DB_VERSION,
			'freshness_age'      => $age,
			'newest_row'         => $newest,
			'products_indexed'   => $indexed,
			'rows'               => $rows,
			'products_published' => $publ,
			'coverage'           => $publ > 0 ? min( 1, $indexed / $publ ) : ( $indexed > 0 ? 1 : 0 ),
			'last_run'           => empty( $state['last_full_run'] ) ? 0 : (int) $state['last_full_run'],
			'last_run_duration'  => empty( $state['last_duration'] ) ? 0 : (int) $state['last_duration'],
			'running'            => self::progress_running(),
			'progress'           => $prog,
			'queued'             => is_array( $queue ) ? count( $queue ) : 0,
			'currencies'         => self::currencies(),
			'scheduler'          => function_exists( 'as_schedule_single_action' ) ? 'action-scheduler' : 'wp-cron',
		);
	}

	/* ------------------------------------------------------------------ currencies */

	/**
	 * Currencies the index stores prices for. Always the store base currency; currency switchers add theirs.
	 */
	public static function currencies() {
		$base = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_currency', 'USD' ) : 'USD';
		$list = array( $base );
		// WOOCS
		if ( isset( $GLOBALS['WOOCS'] ) && is_object( $GLOBALS['WOOCS'] ) && method_exists( $GLOBALS['WOOCS'], 'get_currencies' ) ) {
			$list = array_merge( $list, array_keys( (array) $GLOBALS['WOOCS']->get_currencies() ) );
		}
		// WPML WooCommerce Multilingual
		if ( isset( $GLOBALS['woocommerce_wpml'] ) && is_object( $GLOBALS['woocommerce_wpml'] ) && ! empty( $GLOBALS['woocommerce_wpml']->multi_currency ) && method_exists( $GLOBALS['woocommerce_wpml']->multi_currency, 'get_currency_codes' ) ) {
			$list = array_merge( $list, (array) $GLOBALS['woocommerce_wpml']->multi_currency->get_currency_codes() );
		}
		// Aelia Currency Switcher
		if ( class_exists( 'WC_Aelia_CurrencySwitcher' ) ) {
			$enabled = apply_filters( 'wc_aelia_cs_enabled_currencies', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Aelia Currency Switcher hook, not ours
			$list    = array_merge( $list, (array) $enabled );
		}
		// CURCY (WooCommerce Multi Currency by VillaTheme)
		if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) || class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$cls  = class_exists( 'WOOMULTI_CURRENCY_Data' ) ? 'WOOMULTI_CURRENCY_Data' : 'WOOMULTI_CURRENCY_F_Data';
			$data = call_user_func( array( $cls, 'get_ins' ) );
			if ( is_object( $data ) && method_exists( $data, 'get_list_currencies' ) ) {
				$list = array_merge( $list, array_keys( (array) $data->get_list_currencies() ) );
			}
		}
		// FOX (WooCommerce Currency Switcher by realmag777) shares the WOOCS global.
		$list = apply_filters( 'woobewoo_pf_index_currencies', $list, $base );
		$list = array_values( array_unique( array_filter( array_map( 'strtoupper', array_map( 'strval', (array) $list ) ) ) ) );
		if ( ! in_array( strtoupper( $base ), $list, true ) ) {
			array_unshift( $list, strtoupper( $base ) );
		}
		return $list;
	}

	/**
	 * The currency a shopper is browsing in (switchers filter get_woocommerce_currency()).
	 */
	public static function active_currency() {
		$c = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : get_option( 'woocommerce_currency', 'USD' );
		return strtoupper( (string) apply_filters( 'woobewoo_pf_active_currency', $c ) );
	}

	public static function base_currency() {
		return strtoupper( (string) get_option( 'woocommerce_currency', 'USD' ) );
	}

	/**
	 * A base currency price converted to $currency, through the switcher that is active.
	 */
	public static function convert_price( $price, $currency, $product = null ) {
		$base = self::base_currency();
		if ( null === $price || '' === $price ) {
			return null;
		}
		$price = (float) $price;
		if ( strtoupper( $currency ) === $base ) {
			return $price;
		}
		$converted = null;
		if ( isset( $GLOBALS['WOOCS'] ) && is_object( $GLOBALS['WOOCS'] ) && method_exists( $GLOBALS['WOOCS'], 'woocs_exchange_value' ) ) {
			$currencies = method_exists( $GLOBALS['WOOCS'], 'get_currencies' ) ? $GLOBALS['WOOCS']->get_currencies() : array();
			if ( isset( $currencies[ $currency ]['rate'] ) ) {
				$converted = $price * (float) $currencies[ $currency ]['rate'];
			}
		} elseif ( isset( $GLOBALS['woocommerce_wpml'] ) && ! empty( $GLOBALS['woocommerce_wpml']->multi_currency ) && method_exists( $GLOBALS['woocommerce_wpml']->multi_currency->prices, 'raw_price_filter' ) ) {
			$converted = (float) $GLOBALS['woocommerce_wpml']->multi_currency->prices->raw_price_filter( $price, $currency );
		} elseif ( class_exists( 'WC_Aelia_CurrencySwitcher' ) ) {
			$converted = (float) apply_filters( 'wc_aelia_cs_convert', $price, $base, $currency ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Aelia Currency Switcher hook, not ours
		} elseif ( function_exists( 'wmc_get_price' ) ) {
			$converted = (float) wmc_get_price( $price, $currency );
		}
		$converted = apply_filters( 'woobewoo_pf_index_price_in_currency', $converted, $price, $currency, $product );
		return null === $converted ? $price : (float) $converted;
	}

	/**
	 * Rate from the base currency to $currency for a currency the index holds no prices for (a switcher it does not
	 * know, or one that only filters the product prices): the `woobewoo_pf_currency_rate` filter, the known
	 * switchers, the `raw_woocommerce_price` filter (what 3.x read), then the switcher's own product price filter.
	 * Null when the currency is the base or indexed, or nothing converts.
	 *
	 * @since 4.0.0
	 */
	public static function display_rate( $currency ) {
		static $cache = array();
		$currency = strtoupper( (string) $currency );
		if ( '' === $currency || self::base_currency() === $currency || in_array( $currency, self::currencies(), true ) ) {
			return null;
		}
		if ( array_key_exists( $currency, $cache ) ) {
			return $cache[ $currency ];
		}
		$rate = apply_filters( 'woobewoo_pf_currency_rate', null, $currency );
		if ( null === $rate ) {
			$c = self::convert_price( 100, $currency );
			$rate = ( $c > 0 && abs( $c - 100 ) > 0.0001 ) ? $c / 100 : null;
		}
		if ( null === $rate ) {
			$c = (float) apply_filters( 'raw_woocommerce_price', 100.0, 100.0 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook
			$rate = ( $c > 0 && abs( $c - 100 ) > 0.0001 ) ? $c / 100 : null;
		}
		if ( null === $rate && class_exists( 'WC_Product_Simple' ) ) {
			$p = new WC_Product_Simple();
			$p->set_regular_price( '100' );
			$p->set_price( '100' );
			$c = (float) $p->get_price();
			$rate = ( $c > 0 && abs( $c - 100 ) > 0.0001 ) ? $c / 100 : null;
		}
		$cache[ $currency ] = ( null !== $rate && (float) $rate > 0 ) ? (float) $rate : null;
		return $cache[ $currency ];
	}

	/* ------------------------------------------------------------------ indexing */

	/**
	 * Numeric custom field keys the index stores: those used by filters plus the `woobewoo_pf_index_meta_keys` filter.
	 */
	public static function meta_keys() {
		static $keys = null;
		if ( null !== $keys ) {
			return $keys;
		}
		global $wpdb;
		$keys = array();
		$rows = $wpdb->get_col( 'SELECT setting_data FROM `' . $wpdb->prefix . 'wpf_filters`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( (array) $rows as $row ) {
			$s = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( $row ) : maybe_unserialize( $row );
			if ( empty( $s['settings']['filters']['order'] ) ) {
				continue;
			}
			$order = json_decode( $s['settings']['filters']['order'], true );
			foreach ( (array) $order as $block ) {
				if ( empty( $block['id'] ) ) {
					continue;
				}
				if ( 'wpfCustomField' === $block['id'] && ! empty( $block['settings']['f_custom_fields'] ) ) {
					$keys[] = sanitize_key( $block['settings']['f_custom_fields'] );
				}
				if ( 'wpfSearchNumber' === $block['id'] && ! empty( $block['settings']['f_meta_key'] ) ) {
					$keys[] = sanitize_key( $block['settings']['f_meta_key'] );
				}
				if ( ! empty( $block['settings']['f_meta_key'] ) ) {
					$keys[] = sanitize_key( $block['settings']['f_meta_key'] );
				}
			}
		}
		$keys = apply_filters( 'woobewoo_pf_index_meta_keys', array_values( array_unique( array_filter( $keys ) ) ) );
		return $keys;
	}

	/**
	 * Index one product (and all of its variations). Variations passed in resolve to the parent.
	 */
	public static function index_product( $product_id ) {
		global $wpdb;
		$product_id = (int) $product_id;
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return false;
		}
		$post = get_post( $product_id );
		if ( ! $post ) {
			self::remove_product( $product_id );
			return false;
		}
		if ( 'product_variation' === $post->post_type ) {
			return self::index_product( (int) $post->post_parent );
		}
		if ( 'product' !== $post->post_type ) {
			return false;
		}
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			self::remove_product( $product_id );
			return true;
		}
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			self::remove_product( $product_id );
			return false;
		}
		$t          = self::tables();
		$now        = current_time( 'mysql', 1 );
		$currencies = self::currencies();
		$metaKeys   = self::meta_keys();

		$parentTerms = self::object_terms( $product_id );
		$parentMeta  = self::object_meta( $product_id, $metaKeys );
		$visible     = $product->is_visible() && 'publish' === $post->post_status && ! in_array( $product->get_catalog_visibility(), array( 'hidden', 'search' ), true );
		$visible     = (bool) apply_filters( 'woobewoo_pf_index_product_visible', $visible, $product );

		$rowsP = array();
		$rowsT = array();
		$rowsM = array();
		$rowsC = array();

		$rowsP[] = self::product_row( $product, 0, $visible, $now, $post );
		// Parent term rows: for attribute taxonomies, has_variation says whether a variation carries the term
		// (a parent can list a size no variation exists for); every other taxonomy counts as 1.
		$varTerms = array(); // taxonomy => term_id => 1
		$isVariable = $product->is_type( 'variable' );
		if ( $isVariable ) {
			foreach ( $product->get_children() as $vid0 ) {
				$v0 = wc_get_product( $vid0 );
				if ( ! $v0 ) {
					continue;
				}
				foreach ( $v0->get_attributes() as $tx0 => $slug0 ) {
					if ( ! isset( $parentTerms[ $tx0 ] ) ) {
						continue;
					}
					if ( '' === $slug0 ) {
						foreach ( array_keys( $parentTerms[ $tx0 ] ) as $tid0 ) {
							$varTerms[ $tx0 ][ $tid0 ] = 1;
						}
					} else {
						$tid0 = self::term_id_by_slug( $tx0, (string) $slug0, $parentTerms[ $tx0 ] );
						if ( $tid0 ) {
							$varTerms[ $tx0 ][ $tid0 ] = 1;
						}
					}
				}
			}
		}
		foreach ( $parentTerms as $tx => $terms ) {
			$attrTax = 0 === strpos( $tx, 'pa_' );
			foreach ( $terms as $tid => $slug ) {
				$hv = ( $isVariable && $attrTax && isset( $varTerms[ $tx ] ) ) ? ( isset( $varTerms[ $tx ][ $tid ] ) ? 1 : 0 ) : 1;
				$rowsT[] = array( $product_id, $tx, $tid, $slug, $hv );
			}
		}
		foreach ( $parentMeta as $k => $v ) {
			$rowsM[] = array( $product_id, $k, $v[0], $v[1] );
		}
		foreach ( $currencies as $cur ) {
			$rowsC[] = array( $product_id, $cur, self::convert_price( $product->get_price( 'edit' ), $cur, $product ) );
		}

		$children = array();
		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			foreach ( $children as $vid ) {
				$v = wc_get_product( $vid );
				if ( ! $v || ! $v->is_type( 'variation' ) ) {
					continue;
				}
				$vPost    = get_post( $vid );
				$vVisible = $visible && $vPost && 'publish' === $vPost->post_status && '' !== $v->get_price( 'edit' );
				$vVisible = (bool) apply_filters( 'woobewoo_pf_index_variation_visible', $vVisible, $v, $product );
				$rowsP[]  = self::product_row( $v, $product_id, $vVisible, $now, $post, $product );
				// Terms: own variation attribute terms, parent's terms for everything else ("any" inherits all).
				$own = $v->get_attributes(); // taxonomy => slug ('' = any)
				foreach ( $parentTerms as $tx => $terms ) {
					if ( isset( $own[ $tx ] ) && '' !== $own[ $tx ] ) {
						$slug = (string) $own[ $tx ];
						$tid  = self::term_id_by_slug( $tx, $slug, $terms );
						if ( $tid ) {
							$rowsT[] = array( $vid, $tx, $tid, $slug, 1 );
						}
					} else {
						foreach ( $terms as $tid => $slug ) {
							$rowsT[] = array( $vid, $tx, $tid, $slug, 1 );
						}
					}
				}
				$vMeta = self::object_meta( $vid, $metaKeys );
				foreach ( $parentMeta as $k => $val ) {
					if ( ! isset( $vMeta[ $k ] ) ) {
						$vMeta[ $k ] = $val;
					}
				}
				foreach ( $vMeta as $k => $val ) {
					$rowsM[] = array( $vid, $k, $val[0], $val[1] );
				}
				foreach ( $currencies as $cur ) {
					$rowsC[] = array( $vid, $cur, self::convert_price( $v->get_price( 'edit' ), $cur, $v ) );
				}
			}
		}

		// Replace rows for the product and its children in one go.
		$ids    = array_merge( array( $product_id ), array_map( 'intval', $children ) );
		$idList = implode( ',', array_map( 'intval', $ids ) );
		// $idList is a comma separated list of cast integers; the tables are our own index tables.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['products']} WHERE product_id IN ($idList) OR parent_id = %d", (int) $product_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['terms']} WHERE product_id IN ($idList)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['meta']} WHERE product_id IN ($idList)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['prices']} WHERE product_id IN ($idList)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d

		$vals = array();
		foreach ( $rowsP as $r ) {
			$vals[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::num() returns a numeric literal or NULL, never request data
				'(%d,%d,%d,%s,' . self::num( $r['price'] ) . ',' . self::num( isset( $r['price_max'] ) ? $r['price_max'] : $r['price'] ) . ',' . self::num( $r['regular_price'] ) . ',' . self::num( $r['sale_price'] ) . ',%d,%s,' . self::num( $r['stock_qty'], true ) . ',%f,%d,%d,%d,%d,%d,%d,%s,%s,%s,%s)',
				$r['product_id'],
				$r['parent_id'],
				isset( $r['root_id'] ) ? $r['root_id'] : ( $r['parent_id'] ? $r['parent_id'] : $r['product_id'] ),
				$r['product_type'],
				$r['on_sale'],
				$r['stock_status'],
				$r['rating'],
				$r['review_count'],
				$r['total_sales'],
				$r['featured'],
				$r['published'],
				$r['menu_order'],
				$r['author_id'],
				$r['title'],
				$r['created'],
				$r['modified'],
				$now
			);
		}
		$wpdb->query( "INSERT INTO {$t['products']} (product_id,parent_id,root_id,product_type,price,price_max,regular_price,sale_price,on_sale,stock_status,stock_qty,rating,review_count,total_sales,featured,published,menu_order,author_id,title,created,modified,indexed_at) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- every value tuple comes out of $wpdb->prepare() (self::num() returns a numeric literal or NULL); only our own index table names are interpolated

		if ( $rowsT ) {
			$vals = array();
			foreach ( $rowsT as $r ) {
				$vals[] = $wpdb->prepare( '(%d,%s,%d,%s,%d)', $r[0], $r[1], $r[2], $r[3], isset( $r[4] ) ? $r[4] : 1 );
			}
			foreach ( array_chunk( $vals, 1000 ) as $chunk ) {
				$wpdb->query( "INSERT IGNORE INTO {$t['terms']} (product_id,taxonomy,term_id,term_slug,has_variation) VALUES " . implode( ',', $chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- every value tuple comes out of $wpdb->prepare() (self::num() returns a numeric literal or NULL); only our own index table names are interpolated
			}
		}
		if ( $rowsM ) {
			$vals = array();
			foreach ( $rowsM as $r ) {
				$vals[] = $wpdb->prepare( '(%d,%s,' . self::num( $r[2] ) . ',%s)', $r[0], $r[1], $r[3] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::num() returns a numeric literal or NULL
			}
			$wpdb->query( "INSERT IGNORE INTO {$t['meta']} (product_id,meta_key,value_num,value_text) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- every value tuple comes out of $wpdb->prepare() (self::num() returns a numeric literal or NULL); only our own index table names are interpolated
		}
		if ( $rowsC ) {
			$vals = array();
			foreach ( $rowsC as $r ) {
				$vals[] = $wpdb->prepare( '(%d,%s,' . self::num( $r[2] ) . ')', $r[0], $r[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- self::num() returns a numeric literal or NULL
			}
			$wpdb->query( "INSERT IGNORE INTO {$t['prices']} (product_id,currency,price) VALUES " . implode( ',', $vals ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- every value tuple comes out of $wpdb->prepare() (self::num() returns a numeric literal or NULL); only our own index table names are interpolated
		}
		do_action( 'woobewoo_pf_index_product_indexed', $product_id, $product );
		return true;
	}

	/**
	 * SQL literal for a nullable number (never from user input: values come from WooCommerce objects).
	 */
	private static function num( $v, $int = false ) {
		if ( null === $v || '' === $v || false === $v ) {
			return 'NULL';
		}
		return $int ? (string) (int) $v : (string) (float) $v;
	}

	/**
	 * One products table row from a WC product or variation.
	 */
	private static function product_row( $product, $parent_id, $visible, $now, $post, $parent = null ) {
		$price   = $product->get_price( 'edit' );
		$regular = $product->get_regular_price( 'edit' );
		$sale    = $product->get_sale_price( 'edit' );
		$priceMax = $price;
		if ( $product->is_type( 'variable' ) ) {
			$price    = $product->get_variation_price( 'min' );
			$priceMax = $product->get_variation_price( 'max' );
			$regular  = $product->get_variation_regular_price( 'min' );
		}
		$ratingSrc  = $parent ? $parent : $product;
		$created    = $product->get_date_created( 'edit' );
		$modified   = $product->get_date_modified( 'edit' );
		$rating     = (float) $ratingSrc->get_average_rating( 'edit' );
		$stockQty   = $product->get_stock_quantity( 'edit' );
		if ( $product->is_type( 'variation' ) && ! $product->managing_stock() && $parent && $parent->managing_stock() ) {
			$stockQty = $parent->get_stock_quantity( 'edit' );
		}
		$row = array(
			'product_id'    => (int) $product->get_id(),
			'parent_id'     => (int) $parent_id,
			'product_type'  => $product->get_type(),
			'root_id'       => (int) ( $parent_id ? $parent_id : $product->get_id() ),
			'price'         => '' === $price ? null : $price,
			'price_max'     => '' === $priceMax ? null : $priceMax,
			'regular_price' => '' === $regular ? null : $regular,
			'sale_price'    => '' === $sale ? null : $sale,
			'on_sale'       => $product->is_on_sale( 'edit' ) ? 1 : 0,
			'stock_status'  => (string) $product->get_stock_status( 'edit' ),
			'stock_qty'     => ( null === $stockQty || '' === $stockQty ) ? null : (int) $stockQty,
			'rating'        => $rating,
			'review_count'  => (int) $ratingSrc->get_review_count( 'edit' ),
			'total_sales'   => (int) $ratingSrc->get_total_sales( 'edit' ),
			'featured'      => $ratingSrc->is_featured() ? 1 : 0,
			'published'     => $visible ? 1 : 0,
			'menu_order'    => (int) ( $parent ? $parent->get_menu_order( 'edit' ) : $product->get_menu_order( 'edit' ) ),
			'author_id'     => (int) $post->post_author,
			'title'         => mb_substr( (string) $post->post_title, 0, 191 ),
			'created'       => $created ? $created->date( 'Y-m-d H:i:s' ) : $post->post_date_gmt,
			'modified'      => $modified ? $modified->date( 'Y-m-d H:i:s' ) : $post->post_modified_gmt,
		);
		return apply_filters( 'woobewoo_pf_index_product_row', $row, $product, $parent );
	}

	/**
	 * All taxonomy terms of a product (taxonomy => term_id => slug), excluding WooCommerce internals.
	 */
	public static function object_terms( $product_id ) {
		global $wpdb;
		$skip = array( 'product_type', 'product_visibility', 'product_shipping_class' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tt.taxonomy, tt.term_id, t.slug FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id = %d", $product_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $rows as $r ) {
			if ( in_array( $r['taxonomy'], $skip, true ) ) {
				continue;
			}
			$out[ $r['taxonomy'] ][ (int) $r['term_id'] ] = (string) $r['slug'];
		}
		// Hierarchical taxonomies: a product in a child category belongs to the parents too.
		foreach ( $out as $tx => $terms ) {
			if ( is_taxonomy_hierarchical( $tx ) ) {
				foreach ( array_keys( $terms ) as $tid ) {
					foreach ( (array) get_ancestors( $tid, $tx, 'taxonomy' ) as $aid ) {
						if ( ! isset( $out[ $tx ][ $aid ] ) ) {
							$a = get_term( $aid, $tx );
							if ( $a && ! is_wp_error( $a ) ) {
								$out[ $tx ][ (int) $aid ] = $a->slug;
							}
						}
					}
				}
			}
		}
		return apply_filters( 'woobewoo_pf_index_object_terms', $out, $product_id );
	}

	private static function term_id_by_slug( $taxonomy, $slug, $parentTerms ) {
		$slug = (string) $slug;
		foreach ( $parentTerms as $tid => $s ) {
			if ( $s === $slug ) {
				return (int) $tid;
			}
		}
		if ( ! isset( self::$termSlugCache[ $taxonomy ][ $slug ] ) ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			self::$termSlugCache[ $taxonomy ][ $slug ] = ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}
		return self::$termSlugCache[ $taxonomy ][ $slug ];
	}

	/**
	 * Meta of a product restricted to the indexed keys: key => array(number|null, text).
	 */
	public static function object_meta( $product_id, $keys ) {
		$out = array();
		if ( empty( $keys ) ) {
			return $out;
		}
		foreach ( $keys as $k ) {
			$v = get_post_meta( $product_id, $k, true );
			if ( '' === $v || null === $v || is_array( $v ) || is_object( $v ) ) {
				continue;
			}
			$v     = (string) $v;
			$num   = is_numeric( str_replace( ',', '.', $v ) ) ? (float) str_replace( ',', '.', $v ) : null;
			$out[ $k ] = array( $num, mb_substr( $v, 0, 191 ) );
		}
		return $out;
	}

	/**
	 * Drop a product and its variations from every table.
	 */
	public static function remove_product( $product_id ) {
		global $wpdb;
		$t   = self::tables();
		$pid = (int) $product_id;
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT product_id FROM {$t['products']} WHERE product_id = %d OR parent_id = %d", $pid, $pid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$ids = array_unique( array_merge( array( $pid ), array_map( 'intval', (array) $ids ) ) );
		$in  = implode( ',', array_map( 'intval', $ids ) );
		$wpdb->query( "DELETE FROM {$t['products']} WHERE product_id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['terms']} WHERE product_id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['meta']} WHERE product_id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$wpdb->query( "DELETE FROM {$t['prices']} WHERE product_id IN ($in)" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
	}

	/* ------------------------------------------------------------------ lookups */

	/**
	 * The variation of $product_id that matches the picked attribute terms (taxonomy => array of slugs),
	 * in stock first, most stock first. Returns array(variation_id, id, stock_status, stock_qty, price) or null.
	 */
	public static function lookup_variation( $product_id, $attr_terms = array() ) {
		global $wpdb;
		if ( ! self::installed() ) {
			return null;
		}
		$t     = self::tables();
		$where = array( $wpdb->prepare( 'v.parent_id = %d', (int) $product_id ), 'v.published = 1' );
		foreach ( (array) $attr_terms as $tax => $slugs ) {
			$slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $slugs ) ) );
			if ( ! $slugs || ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$ph      = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$where[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$t['terms']} tt WHERE tt.product_id = v.product_id AND tt.taxonomy = %s AND tt.term_slug IN ($ph))", array_merge( array( $tax ), $slugs ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$row = $wpdb->get_row( "SELECT v.product_id, v.stock_status, v.stock_qty, v.price FROM {$t['products']} v WHERE " . implode( ' AND ', $where ) . " ORDER BY (v.stock_status = 'instock') DESC, (v.stock_status <> 'outofstock') DESC, COALESCE(v.stock_qty, 999999) DESC, v.price ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		if ( ! $row ) {
			return null;
		}
		return array(
			'variation_id' => (int) $row['product_id'],
			'id'           => (int) $row['product_id'],
			'stock_status' => (string) $row['stock_status'],
			'stock_qty'    => null === $row['stock_qty'] ? null : (int) $row['stock_qty'],
			'price'        => null === $row['price'] ? null : (float) $row['price'],
		);
	}

	/**
	 * Stock of every variation of a product from the index (variation_id => array(stock_status, stock_qty, price)).
	 */
	public static function variation_stock( $product_id ) {
		global $wpdb;
		if ( ! self::installed() ) {
			return array();
		}
		$t    = self::tables();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, stock_status, stock_qty, price FROM {$t['products']} WHERE parent_id = %d", (int) $product_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[ (int) $r['product_id'] ] = array(
				'stock_status' => $r['stock_status'],
				'stock_qty'    => null === $r['stock_qty'] ? null : (int) $r['stock_qty'],
				'price'        => null === $r['price'] ? null : (float) $r['price'],
			);
		}
		return $out;
	}
}
