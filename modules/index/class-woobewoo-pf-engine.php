<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Engine Class
 *
 * Turns a WooBeWoo_PF_Query plus the filter's blocks into SQL over the facet index: product ids for a page,
 * the total, and counts for every value of every block (recounted for the current selection).
 *
 * Rules: AND between blocks, OR or AND within a block (its `logic`), variation aware (two variation level
 * conditions must meet in one variation row), stock at variation level, price in the shopper's currency.
 *
 * How the SQL is built:
 *  1. The matching product ids are computed once per request (and once more per block that is recounted
 *     without its own selection): term selections are index driven joins on wpf_index_terms, never per-row
 *     subqueries. The id list is kept in PHP and handed to every count as `product_id IN (...)`.
 *  2. Term counts are one GROUP BY per request over the id list (all taxonomy blocks at once), sale / featured /
 *     rating one pass over the product rows, price bounds and stock from the variation rows.
 *  3. Results are ordered on index columns only (menu_order, title, price, rating, sales, created) - no join to
 *     wp_posts in the ORDER BY path.
 *  4. Variation rows are only scanned when a condition can differ between the variations of one product
 *     (two attribute or stock conditions together, a price with a lower bound, numeric meta); a single attribute
 *     is exact on the product rows because parent term rows carry `has_variation`.
 *
 * Results and counts are cached in the object cache and in transients, keyed by filter id, params, index version,
 * currency and language; bumping the index version drops them. Counts of an earlier version are served while the
 * new ones are computed after the response (stale while revalidate).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Engine {

	const CACHE_GROUP = 'wpf4';
	const CACHE_TTL   = 12 * HOUR_IN_SECONDS;

	/** @var WooBeWoo_PF_Query */
	public $query;
	/** @var array */
	public $ctx;
	/** @var array normalized blocks (key => block) */
	public $blocks = array();
	/** @var array */
	public $t;
	/** @var bool when true nothing is read from or written to the caches */
	public static $nocache = false;
	/** @var int queries run by this engine in the request (for benchmarks) */
	public static $queries = 0;
	/** @var bool|null */
	private $leafMode = null;
	/** @var int join alias counter */
	private $joinN = 0;
	/** @var string rows the SQL being built reads: 'product' (parent rows) or 'leaf' (variation rows) */
	private $sqlMode = 'product';
	/** @var array narrowed id sets per skip key: array of ints, or null for "the whole catalogue" */
	private $sets = array();
	/** @var array product level sets per skip key (variation mode narrows these further) */
	private $psets = array();
	/** @var array id lists by the SQL that produced them: a recount that skips an unselected block reuses the set */
	private $bySql = array();
	/** @var array per taxonomy term counts of the whole catalogue, for join ordering */
	private $plainCounts = null;
	/** @var string|null signature of the add-on SQL (woobewoo_pf_engine_extra_sql), part of every cache key */
	private $extraSig = null;

	public function __construct( WooBeWoo_PF_Query $query, $blocks = array(), $ctx = array() ) {
		$this->query = $query;
		$this->t     = WooBeWoo_PF_Index::tables();
		$this->ctx   = wp_parse_args(
			$ctx,
			array(
				'filter_id'              => 0,
				'taxonomy'               => '',
				'term_id'                => 0,
				'search'                 => '',
				'all_products'           => false,
				'variations_as_products' => false,
				'include'                => array(),
				'exclude'                => array(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- engine argument, resolved against the index tables, not a WP_Query parameter
				'currency'               => WooBeWoo_PF_Index::active_currency(),
				'lang'                   => self::lang(),
			)
		);
		foreach ( (array) $blocks as $b ) {
			$nb = self::unique_block( self::normalize_block( $b ), $this->blocks );
			if ( $nb ) {
				$this->blocks[ $nb['key'] ] = $nb;
			}
		}
	}

	/**
	 * A block whose key is already taken: a second (third …) search box gets its own key (q2, cf_<key>-2) so it renders
	 * and filters on its own; any other repeated block is dropped (one block per facet). Shared with the storefront view.
	 */
	public static function unique_block( $nb, $taken ) {
		if ( ! $nb ) {
			return null;
		}
		if ( ! isset( $taken[ $nb['key'] ] ) ) {
			return $nb;
		}
		if ( ! in_array( $nb['kind'], array( 'q', 'text' ), true ) ) {
			return null;
		}
		for ( $i = 2; $i < 50; $i++ ) {
			$key = 'q' === $nb['key'] ? 'q' . $i : $nb['key'] . '-' . $i;
			if ( ! isset( $taken[ $key ] ) ) {
				$nb['key']  = $key;
				$nb['kind'] = 'text';
				return $nb;
			}
		}
		return null;
	}

	public static function lang() {
		$lang = '';
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			$lang = ICL_LANGUAGE_CODE;
		} elseif ( function_exists( 'pll_current_language' ) ) {
			$lang = (string) pll_current_language();
		}
		return (string) apply_filters( 'woobewoo_pf_active_language', $lang ? $lang : get_locale() );
	}

	/**
	 * A builder block array => array( key, kind, taxonomy, logic, display, hierarchical, block ).
	 */
	public static function normalize_block( $block ) {
		if ( empty( $block['id'] ) ) {
			return null;
		}
		$s   = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$key = WooBeWoo_PF_Query::block_key( $block );
		if ( '' === $key ) {
			return null;
		}
		$kind = 'terms';
		switch ( $block['id'] ) {
			case 'wpfPrice':
			case 'wpfPriceRange':
				$kind = 'price';
				break;
			case 'wpfInStock':
				$kind = 'stock';
				break;
			case 'wpfOnSale':
				$kind = 'sale';
				break;
			case 'wpfFeatured':
				$kind = 'featured';
				break;
			case 'wpfRating':
				$kind = 'rating';
				break;
			case 'wpfSortBy':
				$kind = 'sort';
				break;
			case 'wpfSearchText':
				$kind = 'q';
				break;
			case 'wpfSearchNumber':
			case 'wpfCustomField':
				$kind = 'meta';
				break;
			case 'wpfAuthor':
			case 'wpfVendors':
				$kind = 'author';
				break;
			default:
				// A registered block (WooBeWoo_PF_Blocks) brings its own kind; anything else is a taxonomy block.
				$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::get( $block['id'] ) : null;
				if ( $def ) {
					$kind = $def['kind'];
				}
		}
		if ( 'q' === $kind && 'q' !== $key ) {
			$kind = 'text'; // Custom field text: its own box and URL key.
		}
		$logic = isset( $s['logic'] ) ? strtolower( (string) $s['logic'] ) : ( isset( $s['f_query_logic'] ) ? strtolower( (string) $s['f_query_logic'] ) : 'or' );
		$logic = ( 'and' === $logic ) ? 'and' : 'or';
		$tax   = 'terms' === $kind ? WooBeWoo_PF_Query::block_taxonomy( $block ) : '';
		return array(
			'key'          => $key,
			'kind'         => $kind,
			'taxonomy'     => $tax,
			'logic'        => $logic,
			'display'      => isset( $s['display_type'] ) ? sanitize_key( $s['display_type'] ) : '',
			'hierarchical' => $tax ? is_taxonomy_hierarchical( $tax ) : false,
			'uniqId'       => isset( $block['uniqId'] ) ? (string) $block['uniqId'] : '',
			'block'        => $block,
		);
	}

	/* ------------------------------------------------------------------ cache */

	public function cache_key( $what, $extra = array() ) {
		return 'wpf4_' . md5(
			wp_json_encode(
				array(
					$what,
					(int) $this->ctx['filter_id'],
					$this->query->params_without_page(),
					'ids' === $what ? (int) $this->query->page : 0,
					WooBeWoo_PF_Index::version(),
					$this->ctx['currency'],
					$this->ctx['lang'],
					$this->ctx['taxonomy'],
					(int) $this->ctx['term_id'],
					$this->ctx['search'],
					(bool) $this->ctx['all_products'],
					(bool) $this->ctx['variations_as_products'],
					array_values( array_map( 'intval', (array) $this->ctx['include'] ) ),
					array_values( array_map( 'intval', (array) $this->ctx['exclude'] ) ),
					$this->blocks_sig(),
					$extra,
					$this->extra_sql_sig(),
				)
			)
		);
	}

	/**
	 * Joins and where pieces add-ons attach to the narrowed set through `woobewoo_pf_engine_extra_sql` (the Pro vehicle
	 * finder's "my garage", for example): array( joins => array, where => array ). $skipKey and $mode are those of
	 * from_where(); the signature used in cache keys is taken with '' and 'product'.
	 *
	 * @since 4.0.0
	 */
	public function extra_sql( $skipKey = '', $mode = '' ) {
		$extra = apply_filters( 'woobewoo_pf_engine_extra_sql', array( 'joins' => array(), 'where' => array() ), $this->query, $this, $skipKey, $mode );
		return array(
			'joins' => isset( $extra['joins'] ) ? array_values( array_filter( array_map( 'strval', (array) $extra['joins'] ), 'strlen' ) ) : array(),
			'where' => isset( $extra['where'] ) ? array_values( array_filter( array_map( 'strval', (array) $extra['where'] ), 'strlen' ) ) : array(),
		);
	}

	/**
	 * Hash of the add-on SQL, '' when none is attached (so cache keys of filters without add-ons do not change).
	 *
	 * @since 4.0.0
	 */
	public function extra_sql_sig() {
		if ( null === $this->extraSig ) {
			$this->extraSig = ''; // a hook that reads a cache key while answering must not recurse into this
			$extra          = $this->extra_sql( '', 'product' );
			$this->extraSig = ( $extra['joins'] || $extra['where'] ) ? md5( wp_json_encode( $extra ) ) : '';
		}
		return $this->extraSig;
	}

	/**
	 * The blocks as far as they change results and counts (keys, kinds, logic, search settings). Part of every cache
	 * key: a block added, removed or switched to AND logic (a Save, an unsaved builder draft) must never get the
	 * counts of the old block set, not even once as "last known counts".
	 */
	public function blocks_sig() {
		$sig = array();
		foreach ( $this->blocks as $k => $b ) {
			$row = array( $k, $b['kind'], $b['taxonomy'], $b['logic'] );
			if ( in_array( $b['kind'], array( 'q', 'text' ), true ) && isset( $b['block']['settings'] ) && is_array( $b['block']['settings'] ) ) {
				foreach ( $b['block']['settings'] as $sk => $sv ) {
					if ( is_scalar( $sv ) && preg_match( '/^f_(search_|query_logic|meta_match)/', (string) $sk ) ) {
						$row[] = $sk . '=' . $sv;
					}
				}
			} elseif ( $this->is_registry( $b ) ) {
				// A registered block's SQL may read any of its settings.
				$row[] = WooBeWoo_PF_Blocks::sig_row( $b );
			}
			$sig[] = $row;
		}
		return md5( wp_json_encode( $sig ) );
	}

	public static function cache_get( $key ) {
		if ( self::$nocache ) {
			return false;
		}
		$v = wp_cache_get( $key, self::CACHE_GROUP );
		if ( false !== $v ) {
			return $v;
		}
		$v = get_transient( $key );
		if ( false !== $v ) {
			wp_cache_set( $key, $v, self::CACHE_GROUP, self::CACHE_TTL );
		}
		return $v;
	}

	public static function cache_set( $key, $value ) {
		if ( self::$nocache ) {
			return;
		}
		wp_cache_set( $key, $value, self::CACHE_GROUP, self::CACHE_TTL );
		set_transient( $key, $value, self::CACHE_TTL );
	}

	/* ------------------------------------------------------------------ modes */

	/**
	 * Variation rows are needed when two variation level conditions must meet in one variation (attribute terms,
	 * stock), when the price has a lower bound, or for numeric meta. One such condition alone is exact on the
	 * product rows: parent term rows carry has_variation, the parent stock status says "a variation is in stock",
	 * the parent price is the lowest variation price.
	 */
	public function leaf_mode() {
		if ( null !== $this->leafMode ) {
			return $this->leafMode;
		}
		$n = 0;
		foreach ( $this->query->terms as $key => $slugs ) {
			$tax = isset( $this->blocks[ $key ] ) ? $this->blocks[ $key ]['taxonomy'] : WooBeWoo_PF_Query::taxonomy_for_key( $key );
			if ( $tax && 0 === strpos( $tax, 'pa_' ) ) {
				++$n;
			}
		}
		if ( null !== $this->query->stock ) {
			++$n;
		}
		if ( is_array( $this->query->price ) ) {
			++$n;
		}
		$leaf = ! empty( $this->ctx['force_leaf'] ) || $n >= 2 || ! empty( $this->query->meta ) || ( is_array( $this->query->price ) && null !== $this->query->price[0] );
		$this->leafMode = (bool) apply_filters( 'woobewoo_pf_engine_leaf_mode', $leaf, $this );
		return $this->leafMode;
	}

	/**
	 * The product id a row counts as.
	 */
	private function pid() {
		return 'l.root_id';
	}

	/**
	 * The column of the `l` alias that holds the product id a row counts as (a parent row in product mode, a variation
	 * or simple product row in leaf mode), for registered blocks and add-ons that write their own WHERE.
	 *
	 * @since 4.0.0
	 */
	public function pid_column() {
		return $this->pid();
	}

	/**
	 * Is this a block registered with WooBeWoo_PF_Blocks (not one of the engine's own kinds)?
	 *
	 * @since 4.0.0
	 */
	public function is_registry( $b ) {
		return class_exists( 'WooBeWoo_PF_Blocks' ) && ! in_array( $b['kind'], WooBeWoo_PF_Blocks::BUILTIN_KINDS, true ) && null !== WooBeWoo_PF_Blocks::of( $b );
	}

	/* ------------------------------------------------------------------ SQL pieces */

	/**
	 * Term ids for slugs of a taxonomy (ancestors are stored on every product, so no expansion is needed).
	 */
	private function term_ids( $taxonomy, $slugs ) {
		$ids = array();
		foreach ( (array) $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return $ids;
	}

	/**
	 * An inner join on the terms table for "has one of these term ids" (OR), index driven (tax_term_hv).
	 */
	private function term_join( $taxonomy, $ids ) {
		global $wpdb;
		$ids = array_map( 'intval', $ids );
		$a   = 'j' . ( ++$this->joinN );
		return array(
			'alias'    => $a,
			'on'       => $wpdb->prepare( "$a.taxonomy = %s AND $a.term_id IN (" . implode( ',', $ids ) . ") AND $a.has_variation = 1", $taxonomy ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
			'taxonomy' => $taxonomy,
			'ids'      => $ids,
		);
	}

	/**
	 * Rough number of catalogue rows a term join matches (from the cached whole-catalogue counts when there are
	 * any, else the number of ids): the most selective join drives the query.
	 */
	private function join_weight( $j ) {
		if ( null === $this->plainCounts ) {
			$this->plainCounts = array();
			$c                 = self::cache_get( $this->plain_key( 'termcounts' ) );
			if ( is_array( $c ) ) {
				$this->plainCounts = $c;
			}
		}
		if ( isset( $this->plainCounts[ $j['taxonomy'] ] ) ) {
			$w = 0;
			foreach ( $j['ids'] as $id ) {
				$w += isset( $this->plainCounts[ $j['taxonomy'] ][ $id ] ) ? $this->plainCounts[ $j['taxonomy'] ][ $id ] : 0;
			}
			return $w;
		}
		return count( $j['ids'] ) * 1000000;
	}

	private function search_sql( $text ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $text ) . '%';
		return $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->posts} ps WHERE ps.ID = " . $this->pid() . " AND (ps.post_title LIKE %s OR ps.post_excerpt LIKE %s OR ps.post_content LIKE %s))", $like, $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
	}

	/**
	 * INNER JOIN of the numeric values of a custom field as alias $a (columns product_id, value_num). The index holds the
	 * keys that saved filters use; a key it does not hold yet (a block added in the builder, not saved, so the preview
	 * of the draft) is read live from postmeta on the product row.
	 */
	private function meta_join( $a, $key ) {
		global $wpdb;
		static $indexed = array();
		if ( ! isset( $indexed[ $key ] ) ) {
			++self::$queries;
			$indexed[ $key ] = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$this->t['meta']} WHERE meta_key = %s LIMIT 1", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- our own index table, name from self::tables() built on $wpdb->prefix; the key is prepared
		}
		if ( $indexed[ $key ] ) {
			return $wpdb->prepare( "INNER JOIN {$this->t['meta']} $a ON $a.product_id = l.product_id AND $a.meta_key = %s", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alias and table name are fixed strings
		}
		return $wpdb->prepare( "INNER JOIN (SELECT pm.post_id AS product_id, CAST(REPLACE(pm.meta_value, ',', '.') AS DECIMAL(20,6)) AS value_num FROM {$wpdb->postmeta} pm WHERE pm.meta_key = %s AND pm.meta_value REGEXP '^ *-?[0-9]+([.,][0-9]+)? *$') $a ON $a.product_id = l.root_id", $key ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alias and table names are fixed strings
	}

	/**
	 * Text search of one Search by text block: the fields its settings name (Pro "Search in": name, description,
	 * short description, SKU, category / tag / brand / attribute names, custom fields), "Several words must" (any / all),
	 * "Whole words only" and, for custom fields, "Exactly the value". A block without any of these settings searches
	 * the name and both descriptions, like 3.x.
	 */
	private function text_sql( $block, $text ) {
		global $wpdb;
		// An add-on (Pro search engines: SearchWP, Relevanssi, ElasticPress) may answer the text with product ids.
		$ids = apply_filters( 'woobewoo_pf_text_search_ids', null, $text, $block, $this );
		if ( is_array( $ids ) ) {
			return $ids ? $this->pid() . ' IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '0 = 1';
		}
		$s    = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$on   = function ( $k ) use ( $s ) {
			return isset( $s[ $k ] ) && ! in_array( strtolower( trim( (string) $s[ $k ] ) ), array( '', '0', 'no', 'false', 'off' ), true );
		};
		$keys = array( 'f_search_by_title', 'f_search_by_content', 'f_search_by_excerpt', 'f_search_by_meta_sku', 'f_search_by_tax_categories', 'f_search_by_tax_tags', 'f_search_by_perfect_brand', 'f_search_by_attributes', 'f_search_by_meta_fields' );
		$any  = false;
		foreach ( $keys as $k ) {
			if ( array_key_exists( $k, $s ) ) {
				$any = true;
				break;
			}
		}
		$meta    = WooBeWoo_PF_Query::search_meta_keys( $s );
		$title   = ! array_key_exists( 'f_search_by_title', $s ) ? ! $meta : $on( 'f_search_by_title' );
		$content = $any ? $on( 'f_search_by_content' ) : true;
		$excerpt = $any ? $on( 'f_search_by_excerpt' ) : true;
		$taxes   = array();
		if ( $on( 'f_search_by_tax_categories' ) ) {
			$taxes[] = 'product_cat';
		}
		if ( $on( 'f_search_by_tax_tags' ) ) {
			$taxes[] = 'product_tag';
		}
		if ( $on( 'f_search_by_perfect_brand' ) ) {
			$taxes[] = 'product_brand';
			$taxes[] = 'pwb-brand';
		}
		$attrTax = false;
		if ( $on( 'f_search_by_attributes' ) ) {
			$ids = array_filter( array_map( 'intval', explode( ',', isset( $s['f_search_by_attributes_list'] ) ? (string) $s['f_search_by_attributes_list'] : '' ) ) );
			if ( $ids && function_exists( 'wc_attribute_taxonomy_name_by_id' ) ) {
				foreach ( $ids as $id ) {
					$n = wc_attribute_taxonomy_name_by_id( $id );
					if ( $n ) {
						$taxes[] = $n;
					}
				}
			} else {
				$attrTax = true;
			}
		}
		$sku   = $on( 'f_search_by_meta_sku' );
		$exact = $meta && isset( $s['f_meta_match'] ) && 'exact' === $s['f_meta_match'];
		$full  = $on( 'f_search_only_by_full_word' );
		$text  = trim( (string) $text );
		if ( ! $title && ! $content && ! $excerpt && ! $taxes && ! $attrTax && ! $sku && ! $meta ) {
			$title = true;
		}
		// Words: "Several words must" set = each word on its own (any / all); not set = the whole text as one phrase.
		$logic = isset( $s['f_query_logic'] ) ? strtolower( (string) $s['f_query_logic'] ) : '';
		$words = ( 'and' === $logic || 'or' === $logic ) ? array_values( array_filter( preg_split( '/\s+/u', $text ) ) ) : array( $text );
		if ( ! $words ) {
			return '1 = 1';
		}
		$pid   = $this->pid();
		$terms = array();
		foreach ( $words as $word ) {
			$val = $full ? '(^|[^[:alnum:]_])' . preg_replace( '/([.\\+*?\[^\]$(){}=!<>|:\-#])/', '\\\\$1', $word ) . '([^[:alnum:]_]|$)' : '%' . $wpdb->esc_like( $word ) . '%';
			$op    = $full ? 'REGEXP' : 'LIKE';
			$or    = array();
			$cols  = array();
			if ( $title ) {
				$cols[] = 'ps.post_title';
			}
			if ( $excerpt ) {
				$cols[] = 'ps.post_excerpt';
			}
			if ( $content ) {
				$cols[] = 'ps.post_content';
			}
			if ( $cols ) {
				$c = array();
				foreach ( $cols as $col ) {
					$c[] = $wpdb->prepare( "$col $op %s", $val ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- column and operator are fixed strings chosen above
				}
				$or[] = "EXISTS (SELECT 1 FROM {$wpdb->posts} ps WHERE ps.ID = $pid AND (" . implode( ' OR ', $c ) . '))';
			}
			if ( $sku ) {
				$or[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pk WHERE pk.meta_key = '_sku' AND pk.meta_value $op %s AND (pk.post_id = $pid OR pk.post_id IN (SELECT pv.ID FROM {$wpdb->posts} pv WHERE pv.post_parent = $pid AND pv.post_type = 'product_variation')))", $val ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- operator and alias are fixed strings
			}
			if ( $taxes || $attrTax ) {
				$tw = array();
				if ( $taxes ) {
					$tw[] = 'tt.taxonomy IN (' . implode( ',', array_map( function ( $t ) use ( $wpdb ) {
						return $wpdb->prepare( '%s', $t );
					}, array_unique( $taxes ) ) ) . ')';
				}
				if ( $attrTax ) {
					$tw[] = "tt.taxonomy LIKE 'pa\\_%'";
				}
				$or[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} tm ON tm.term_id = tt.term_id WHERE tr.object_id = $pid AND (" . implode( ' OR ', $tw ) . ") AND tm.name $op %s)", $val ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- taxonomy list built with prepare(), operator fixed
			}
			if ( $meta ) {
				$in   = implode( ',', array_map( function ( $k ) use ( $wpdb ) {
					return $wpdb->prepare( '%s', $k );
				}, $meta ) );
				$or[] = $exact
					? $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pf WHERE pf.post_id = $pid AND pf.meta_key IN ($in) AND pf.meta_value = %s)", $text ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- key list built with prepare()
					: $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pf WHERE pf.post_id = $pid AND pf.meta_key IN ($in) AND pf.meta_value $op %s)", $val ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- key list built with prepare(), operator fixed
			}
			$terms[] = '(' . implode( ' OR ', $or ) . ')';
		}
		return '(' . implode( 'and' === $logic ? ' AND ' : ' OR ', $terms ) . ')';
	}

	/**
	 * The current selection of a block as SQL: array( joins => array, where => array ), or null when nothing picked.
	 */
	private function block_sql( $b ) {
		global $wpdb;
		$q      = $this->query;
		$joins  = array();
		$tjoins = array();
		$where  = array();
		switch ( $b['kind'] ) {
			case 'terms':
				if ( empty( $q->terms[ $b['key'] ] ) || ! $b['taxonomy'] ) {
					return null;
				}
				$ids = $this->term_ids( $b['taxonomy'], $q->terms[ $b['key'] ] );
				if ( ! $ids ) {
					$where[] = '0 = 1';
				} elseif ( 'and' === $b['logic'] ) {
					foreach ( $ids as $id ) {
						$tjoins[] = $this->term_join( $b['taxonomy'], array( $id ) );
					}
				} else {
					$tjoins[] = $this->term_join( $b['taxonomy'], $ids );
				}
				break;
			case 'author':
				if ( empty( $q->terms['author'] ) ) {
					return null;
				}
				$ids = array();
				foreach ( $q->terms['author'] as $a ) {
					if ( ctype_digit( (string) $a ) ) {
						$ids[] = (int) $a;
					} else {
						$u = get_user_by( 'slug', $a );
						if ( $u ) {
							$ids[] = (int) $u->ID;
						}
					}
				}
				$where[] = $ids ? 'l.author_id IN (' . implode( ',', $ids ) . ')' : '0 = 1';
				break;
			case 'price':
				if ( ! is_array( $q->price ) ) {
					return null;
				}
				$c = $this->price_condition( $q->price[0], $q->price[1] );
				if ( '' === $c ) {
					return null;
				}
				$where[] = $c;
				break;
			case 'stock':
				if ( ! $q->stock ) {
					return null;
				}
				$where[] = 'in' === $q->stock ? "l.stock_status <> 'outofstock'" : ( 'out' === $q->stock ? "l.stock_status = 'outofstock'" : "l.stock_status = 'onbackorder'" );
				break;
			case 'sale':
				if ( ! $q->sale ) {
					return null;
				}
				$where[] = 'l.on_sale = 1';
				break;
			case 'featured':
				if ( ! $q->featured ) {
					return null;
				}
				$where[] = 'l.featured = 1';
				break;
			case 'rating':
				if ( ! $q->rating ) {
					return null;
				}
				$where[] = $wpdb->prepare( 'l.rating >= %f', (float) $q->rating );
				break;
			case 'text':
				if ( ! isset( $q->text[ $b['key'] ] ) || '' === (string) $q->text[ $b['key'] ] ) {
					return null;
				}
				$where[] = $this->text_sql( $b['block'], $q->text[ $b['key'] ] );
				break;
			case 'meta':
				if ( empty( $q->meta[ $b['key'] ] ) ) {
					return null;
				}
				$r     = $q->meta[ $b['key'] ];
				$parts = array();
				$a     = 'm' . ( ++$this->joinN );
				if ( null !== $r[0] ) {
					$parts[] = $wpdb->prepare( "$a.value_num >= %f", (float) $r[0] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				if ( null !== $r[1] ) {
					$parts[] = $wpdb->prepare( "$a.value_num <= %f", (float) $r[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
				if ( ! $parts ) {
					return null;
				}
				$joins[] = $this->meta_join( $a, $b['key'] ) . ' AND ' . implode( ' AND ', $parts ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
				break;
			default:
				// A registered block (WooBeWoo_PF_Blocks) writes its own pieces.
				$def = $this->is_registry( $b ) ? WooBeWoo_PF_Blocks::of( $b ) : null;
				if ( ! $def ) {
					return null;
				}
				return WooBeWoo_PF_Blocks::sql( $def, $b, $q, $this );
		}
		return array(
			'joins'  => $joins,
			'tjoins' => $tjoins,
			'where'  => $where,
		);
	}

	/**
	 * Is anything picked in this block?
	 */
	public function block_selected( $b ) {
		$n           = $this->joinN;
		$sql         = $this->block_sql( $b );
		$this->joinN = $n;
		return null !== $sql;
	}

	/**
	 * Multilingual stores keep one product per language (Polylang, WPML): only the products of the shopper's
	 * language are counted and listed, else every count and total doubles and pages repeat translated products.
	 * Empty when no such plugin translates products.
	 */
	private function language_sql() {
		global $wpdb;
		$lang = isset( $this->ctx['lang'] ) ? (string) $this->ctx['lang'] : '';
		if ( '' === $lang ) {
			return '';
		}
		if ( function_exists( 'pll_is_translated_post_type' ) && taxonomy_exists( 'language' ) ) {
			if ( ! pll_is_translated_post_type( 'product' ) ) {
				return '';
			}
			$term = get_term_by( 'slug', $lang, 'language' );
			return $term ? $wpdb->prepare( "l.root_id IN (SELECT lng.object_id FROM {$wpdb->term_relationships} lng WHERE lng.term_taxonomy_id = %d)", (int) $term->term_taxonomy_id ) : ''; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core table name
		}
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			return $wpdb->prepare( "l.root_id IN (SELECT lng.element_id FROM {$wpdb->prefix}icl_translations lng WHERE lng.element_type = 'post_product' AND lng.language_code = %s)", $lang ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WPML table name from the prefix
		}
		return '';
	}

	/**
	 * Currency the price SQL reads: the shopper's when the index holds prices for it, else the base currency (a
	 * currency switcher the index does not know); price_rate() then converts between the two.
	 */
	private function sql_currency() {
		$cur = (string) $this->ctx['currency'];
		return in_array( $cur, WooBeWoo_PF_Index::currencies(), true ) ? $cur : WooBeWoo_PF_Index::base_currency();
	}

	/**
	 * Shown price = base price x this rate, when the shopper's currency is not in the index (1 otherwise).
	 */
	private function price_rate() {
		if ( $this->sql_currency() === (string) $this->ctx['currency'] ) {
			return 1.0;
		}
		$rate = WooBeWoo_PF_Index::display_rate( $this->ctx['currency'] );
		return $rate ? (float) $rate : 1.0;
	}

	private function price_condition( $min, $max ) {
		global $wpdb;
		$cur   = $this->sql_currency();
		$base  = WooBeWoo_PF_Index::base_currency();
		$rate  = $this->price_rate();
		if ( 1.0 !== $rate ) {
			$min = null === $min ? null : (float) $min / $rate;
			$max = null === $max ? null : (float) $max / $rate;
		}
		$parts = array();
		if ( $cur === $base ) {
			// Parent rows carry the lowest (price) and highest (price_max) variation price: "some variation lies in
			// the range" is price <= max AND price_max >= min there, and plain bounds on a variation row.
			$lo = 'product' === $this->sqlMode ? 'COALESCE(l.price_max, l.price)' : 'l.price';
			if ( null !== $min ) {
				$parts[] = $wpdb->prepare( "$lo >= %f", (float) $min ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			if ( null !== $max ) {
				$parts[] = $wpdb->prepare( 'l.price <= %f', (float) $max );
			}
			return $parts ? '(' . implode( ' AND ', $parts ) . ')' : '';
		}
		if ( null !== $min ) {
			$parts[] = $wpdb->prepare( 'pr.price >= %f', (float) $min );
		}
		if ( null !== $max ) {
			$parts[] = $wpdb->prepare( 'pr.price <= %f', (float) $max );
		}
		if ( ! $parts ) {
			return '';
		}
		if ( 'product' === $this->sqlMode ) {
			return $wpdb->prepare( "EXISTS (SELECT 1 FROM {$this->t['prices']} pr INNER JOIN {$this->t['products']} v ON v.product_id = pr.product_id AND v.product_type <> 'variable' AND v.published = 1 WHERE v.root_id = l.product_id AND pr.currency = %s AND " . implode( ' AND ', $parts ) . ')', $cur ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
		}
		return $wpdb->prepare( "EXISTS (SELECT 1 FROM {$this->t['prices']} pr WHERE pr.product_id = l.product_id AND pr.currency = %s AND " . implode( ' AND ', $parts ) . ')', $cur ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
	}

	public function price_condition_public( $min, $max ) {
		return $this->price_condition( $min, $max );
	}

	/**
	 * Query conditions that no block covers (a URL can carry more than the filter shows).
	 */
	private function loose_blocks() {
		$have = array();
		foreach ( $this->blocks as $b ) {
			$have[ $b['kind'] . ':' . $b['key'] ] = 1;
		}
		$out = array();
		foreach ( $this->query->terms as $key => $slugs ) {
			if ( isset( $have[ 'terms:' . $key ] ) || isset( $have[ 'author:' . $key ] ) ) {
				continue;
			}
			if ( 'author' === $key ) {
				$out[] = array( 'kind' => 'author', 'key' => 'author', 'logic' => 'or', 'taxonomy' => '' );
				continue;
			}
			$tax = WooBeWoo_PF_Query::taxonomy_for_key( $key );
			if ( $tax ) {
				$out[] = array( 'kind' => 'terms', 'key' => $key, 'logic' => 'or', 'taxonomy' => $tax );
			}
		}
		foreach ( array( 'price', 'stock', 'sale', 'featured', 'rating' ) as $kind ) {
			if ( ! isset( $have[ $kind . ':' . $kind ] ) ) {
				$out[] = array( 'kind' => $kind, 'key' => $kind, 'logic' => 'or', 'taxonomy' => '' );
			}
		}
		// 4.0.1: a meta key the filter has no block for is ignored. Honouring any wpf_<key>=min-max from the URL let a
		// visitor probe every numeric product meta (cost price, exact stock) with an unindexed postmeta scan per request.
		foreach ( array_keys( (array) $this->query->meta ) as $key ) {
			if ( ! isset( $have[ 'meta:' . $key ] ) ) {
				unset( $this->query->meta[ $key ] );
			}
		}
		return $out;
	}

	/**
	 * FROM … JOIN … and WHERE for the rows that match; with $skipKey the named block's own selection is left out
	 * (recounts for OR logic). Returns array( from, where, plain ) with `l` as the products alias; `plain` is true
	 * when nothing narrows the catalogue (no selection, no page context, no search, no include list).
	 */
	public function from_where( $skipKey = '', $mode = '' ) {
		$this->joinN   = 0;
		$mode          = $mode ? $mode : ( $this->leaf_mode() ? 'leaf' : 'product' );
		$this->sqlMode = $mode;
		$joins  = array();
		$tjoins = array();
		$w      = array( 'leaf' === $mode ? "l.product_type <> 'variable'" : 'l.parent_id = 0', 'l.published = 1' );
		$plain  = true;
		// WooCommerce "Hide out of stock items": the shop never lists them, so counts and results leave them out too
		// (a size whose only products are sold out would say "(1)" and then show nothing). Not when the shopper asks for them.
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) && ! in_array( (string) $this->query->stock, array( 'out', 'backorder' ), true ) ) {
			$w[]   = "l.stock_status <> 'outofstock'";
			$plain = false;
		}
		$ctx    = $this->ctx;
		$lang   = $this->language_sql();
		if ( '' !== $lang ) {
			$w[]   = $lang;
			$plain = false;
		}
		if ( ! $ctx['all_products'] && $ctx['taxonomy'] && $ctx['term_id'] && taxonomy_exists( $ctx['taxonomy'] ) ) {
			$tjoins[] = $this->term_join( $ctx['taxonomy'], array( (int) $ctx['term_id'] ) );
			$plain    = false;
		}
		if ( ! empty( $ctx['include'] ) ) {
			$w[]   = $this->pid() . ' IN (' . implode( ',', array_map( 'intval', (array) $ctx['include'] ) ) . ')';
			$plain = false;
		}
		if ( ! empty( $ctx['exclude'] ) ) {
			$w[]   = $this->pid() . ' NOT IN (' . implode( ',', array_map( 'intval', (array) $ctx['exclude'] ) ) . ')';
			$plain = false;
		}
		if ( '' !== $ctx['search'] ) {
			$w[]   = $this->search_sql( $ctx['search'] );
			$plain = false;
		}
		if ( '' !== $this->query->q ) {
			// The Search by text block's own "Search in" settings (Pro) decide the fields; none: name and descriptions.
			$w[]   = isset( $this->blocks['q'] ) ? $this->text_sql( $this->blocks['q']['block'], $this->query->q ) : $this->search_sql( $this->query->q );
			$plain = false;
		}
		foreach ( array_merge( array_values( $this->blocks ), $this->loose_blocks() ) as $b ) {
			if ( $b['key'] === $skipKey && ! ( 'and' === $b['logic'] && 'terms' === $b['kind'] ) ) {
				continue;
			}
			$sql = $this->block_sql( $b );
			if ( null === $sql ) {
				continue;
			}
			$joins  = array_merge( $joins, $sql['joins'] );
			$tjoins = array_merge( $tjoins, $sql['tjoins'] );
			$w      = array_merge( $w, $sql['where'] );
			$plain  = false;
		}
		// Add-ons (the Pro vehicle finder's garage, for example) narrow the set further; see extra_sql().
		$extra = $this->extra_sql( $skipKey, $mode );
		if ( $extra['joins'] || $extra['where'] ) {
			$joins = array_merge( $joins, $extra['joins'] );
			$w     = array_merge( $w, $extra['where'] );
			$plain = false;
		}
		$n = count( $w );
		$w = apply_filters( 'woobewoo_pf_engine_where', $w, $this, $skipKey );
		if ( count( $w ) !== $n ) {
			$plain = false;
		}
		// Term joins first, the most selective one driving (tax_term_hv is covering), then the product rows by
		// primary key, then numeric meta.
		$from = "{$this->t['products']} l";
		if ( $tjoins ) {
			$weights = array();
			foreach ( $tjoins as $i => $j ) {
				$weights[ $i ] = $this->join_weight( $j );
			}
			asort( $weights );
			$order = array_keys( $weights );
			$first = $tjoins[ $order[0] ];
			$from  = "{$this->t['terms']} {$first['alias']}";
			$w[]   = $first['on'];
			for ( $i = 1; $i < count( $order ); $i++ ) {
				$j     = $tjoins[ $order[ $i ] ];
				$from .= " STRAIGHT_JOIN {$this->t['terms']} {$j['alias']} ON {$j['alias']}.product_id = {$first['alias']}.product_id AND {$j['on']}";
			}
			$from .= " STRAIGHT_JOIN {$this->t['products']} l ON l.product_id = {$first['alias']}.product_id";
		}
		if ( $joins ) {
			$from .= ' ' . implode( ' ', $joins );
		}
		return array(
			'from'  => $from,
			'where' => implode( ' AND ', $w ),
			'plain' => $plain,
			'mode'  => $mode,
		);
	}

	/**
	 * Do the conditions left after skipping a block still need variation rows (two variation level conditions,
	 * a price with a lower bound, numeric meta)? With one such condition the product rows are exact.
	 */
	public function needs_leaf( $skipKey = '' ) {
		if ( ! $this->leaf_mode() ) {
			return false;
		}
		if ( ! empty( $this->ctx['force_leaf'] ) ) {
			return true;
		}
		$n = 0;
		foreach ( $this->query->terms as $key => $slugs ) {
			if ( $key === $skipKey ) {
				continue;
			}
			$tax = isset( $this->blocks[ $key ] ) ? $this->blocks[ $key ]['taxonomy'] : WooBeWoo_PF_Query::taxonomy_for_key( $key );
			if ( $tax && 0 === strpos( $tax, 'pa_' ) ) {
				++$n;
			}
		}
		if ( null !== $this->query->stock && 'stock' !== $skipKey ) {
			++$n;
		}
		$price = is_array( $this->query->price ) && 'price' !== $skipKey;
		if ( $price ) {
			++$n;
		}
		foreach ( $this->query->meta as $key => $r ) {
			if ( $key !== $skipKey ) {
				return true;
			}
		}
		return $n >= 2 || ( $price && null !== $this->query->price[0] );
	}

	/**
	 * Product level narrowed set (parent rows, has_variation term rows): exact in product mode, a superset in
	 * variation mode. Null for the whole catalogue.
	 */
	public function product_set( $skipKey = '' ) {
		global $wpdb;
		if ( array_key_exists( $skipKey, $this->psets ) ) {
			return $this->psets[ $skipKey ];
		}
		$fw = $this->from_where( $skipKey, 'product' );
		if ( $fw['plain'] ) {
			$this->psets[ $skipKey ] = null;
			return null;
		}
		$sql = "SELECT DISTINCT l.product_id FROM {$fw['from']} WHERE {$fw['where']}";
		if ( ! isset( $this->bySql[ $sql ] ) ) {
			++self::$queries;
			$this->bySql[ $sql ] = array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		}
		$this->psets[ $skipKey ] = $this->bySql[ $sql ];
		return $this->psets[ $skipKey ];
	}

	/**
	 * Product level superset for narrowing a variation row query: read with a cap, so a wide set (where the term
	 * index drives better than an id list) costs little. Returns the ids, null for the whole catalogue, or false
	 * when the set is wider than $limit.
	 */
	private function product_superset( $skipKey, $limit ) {
		global $wpdb;
		if ( array_key_exists( $skipKey, $this->psets ) ) {
			return $this->psets[ $skipKey ];
		}
		$fw = $this->from_where( $skipKey, 'product' );
		if ( $fw['plain'] ) {
			$this->psets[ $skipKey ] = null;
			return null;
		}
		$sql = "SELECT DISTINCT l.product_id FROM {$fw['from']} WHERE {$fw['where']}";
		if ( isset( $this->bySql[ $sql ] ) ) {
			return $this->bySql[ $sql ];
		}
		$capped = $sql . ' LIMIT ' . ( (int) $limit + 1 );
		if ( ! isset( $this->bySql[ $capped ] ) ) {
			++self::$queries;
			$ids = array_map( 'intval', (array) $wpdb->get_col( $capped ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
			$this->bySql[ $capped ] = count( $ids ) > $limit ? false : $ids;
			if ( false !== $this->bySql[ $capped ] ) {
				$this->bySql[ $sql ] = $ids; // complete: the exact product level set
			}
		}
		return $this->bySql[ $capped ];
	}

	/**
	 * FROM and WHERE of the variation rows (and simple products) that match every condition except $skipKey's:
	 * array( from, where ), or an empty array when nothing can match. When the product level set is small the
	 * variation rows are read through it (root_id), otherwise the term index drives.
	 */
	public function leaf_rows( $skipKey = '' ) {
		if ( ! $this->needs_leaf( $skipKey ) ) {
			$set = $this->product_set( $skipKey );
			if ( is_array( $set ) && ! $set ) {
				return array();
			}
			return array(
				'from'  => "{$this->t['products']} l",
				'where' => $this->leaf_set_cond( $set ),
			);
		}
		$fw = $this->from_where( $skipKey, 'leaf' );
		if ( false !== strpos( $fw['from'], 'STRAIGHT_JOIN' ) ) {
			// Term joins: a small product level set (parent rows, has_variation) narrows the variation rows first.
			$limit = (int) apply_filters( 'woobewoo_pf_engine_leaf_set_limit', 20000, $this );
			$set   = $this->product_superset( $skipKey, $limit );
			if ( is_array( $set ) && ! $set ) {
				return array();
			}
			if ( is_array( $set ) ) {
				$fw['where'] .= ' AND l.root_id IN (' . implode( ',', $set ) . ')';
			}
		}
		return array(
			'from'  => $fw['from'],
			'where' => $fw['where'],
		);
	}

	/**
	 * WHERE string alone (joins may be needed too: prefer from_where()).
	 */
	public function where( $skipKey = '' ) {
		$fw = $this->from_where( $skipKey );
		return $fw['where'];
	}

	/**
	 * The narrowed product id set (computed once per skip key): array of ids, or null for the whole catalogue.
	 */
	public function set_ids( $skipKey = '' ) {
		global $wpdb;
		if ( array_key_exists( $skipKey, $this->sets ) ) {
			return $this->sets[ $skipKey ];
		}
		if ( ! $this->needs_leaf( $skipKey ) ) {
			$this->sets[ $skipKey ] = $this->product_set( $skipKey );
			return $this->sets[ $skipKey ];
		}
		$lr = $this->leaf_rows( $skipKey );
		if ( ! $lr ) {
			$this->sets[ $skipKey ] = array();
			return array();
		}
		$sql = "SELECT DISTINCT l.root_id FROM {$lr['from']} WHERE {$lr['where']}";
		if ( ! isset( $this->bySql[ $sql ] ) ) {
			++self::$queries;
			$this->bySql[ $sql ] = array_map( 'intval', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		}
		$this->sets[ $skipKey ] = $this->bySql[ $sql ];
		return $this->sets[ $skipKey ];
	}

	/**
	 * Cache key for a whole-catalogue value (no selection): per index version, context and currency only, so a
	 * recount that drops the last selection is shared by every request.
	 */
	public function plain_key( $what, $extra = array() ) {
		return 'wpf4p_' . md5( wp_json_encode( array( $what, WooBeWoo_PF_Index::version(), $this->ctx['currency'], $this->ctx['lang'], (bool) $this->ctx['variations_as_products'], $extra ) ) );
	}

	/**
	 * Condition on product rows (alias $a) for a set: the id list, or "every published product".
	 */
	private function set_cond( $ids, $a = 'p' ) {
		if ( null === $ids ) {
			return "$a.parent_id = 0 AND $a.published = 1";
		}
		if ( ! $ids ) {
			return '0 = 1';
		}
		return "$a.product_id IN (" . implode( ',', $ids ) . ')';
	}

	/* ------------------------------------------------------------------ results */

	/**
	 * ORDER BY for a sort key over the product rows (alias p), index columns only.
	 */
	private function order_sql( $sort ) {
		$sort = $sort ? $sort : 'default';
		switch ( $sort ) {
			case 'popularity':
				return 'p.total_sales DESC, p.product_id ASC';
			case 'rating':
				return 'p.rating DESC, p.product_id ASC';
			case 'date':
				return 'p.created DESC, p.product_id DESC';
			case 'date-asc':
				return 'p.created ASC, p.product_id ASC';
			case 'price':
				return 'p.price ASC, p.product_id ASC';
			case 'price-desc':
				return 'p.price DESC, p.product_id ASC';
			case 'rand':
				return 'RAND(' . (int) gmdate( 'Ymd' ) . ')';
			case 'title':
				return 'p.title ASC, p.product_id ASC';
			case 'title-desc':
				return 'p.title DESC, p.product_id ASC';
			case 'sku':
				return 'p.product_id ASC';
			case 'sku-desc':
				return 'p.product_id DESC';
		}
		$custom = apply_filters( 'woobewoo_pf_engine_order_sql', '', $sort, $this );
		if ( $custom ) {
			return $custom;
		}
		return 'p.menu_order ASC, p.title ASC, p.product_id ASC';
	}

	private function ids_sql( $sort, $limit = 0, $offset = 0 ) {
		$set   = $this->set_ids();
		$order = $this->order_sql( $sort );
		$force = '';
		if ( $limit > 0 && is_array( $set ) && count( $set ) > 5000 ) {
			// A wide set: walk the sort index in order and stop at the page instead of sorting the whole id list.
			$sort = $sort ? $sort : 'default';
			if ( 'default' === $sort ) {
				$force = ' FORCE INDEX (sort_default)';
			} elseif ( 'price' === $sort || 'price-desc' === $sort ) {
				$force = ' FORCE INDEX (published_price)';
			}
		}
		$cond = $this->set_cond( $set );
		if ( $force ) {
			$cond = 'p.published = 1 AND p.parent_id = 0 AND ' . $cond;
		}
		$sql = "SELECT p.product_id FROM {$this->t['products']} p$force WHERE $cond ORDER BY $order";
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
		}
		return $sql;
	}

	/**
	 * Product ids for a page: array( ids, total, pages, page, per_page ).
	 */
	public function ids( $page = null, $per_page = null, $sort = null, $accumulate = false ) {
		global $wpdb;
		$page     = max( 1, (int) ( null === $page ? $this->query->page : $page ) );
		$per_page = (int) ( null === $per_page ? self::per_page() : $per_page );
		$per_page = $per_page > 0 ? $per_page : 12;
		$sort     = null === $sort ? $this->query->sort : $sort;
		// The ORDER BY is part of the key: a sort added through woobewoo_pf_engine_order_sql (Pro merchandising rules) changes the order.
		$key      = $this->cache_key( 'ids', array( $page, $per_page, $sort, (bool) $accumulate, md5( $this->order_sql( $sort ) ) ) );
		$cached   = self::cache_get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$limit  = $accumulate ? $per_page * $page : $per_page;
		$offset = $accumulate ? 0 : ( $page - 1 ) * $per_page;
		$total  = $this->count();
		$ids    = array();
		if ( $total > 0 ) {
			++self::$queries;
			$ids = array_map( 'intval', (array) $wpdb->get_col( $this->ids_sql( $sort, $limit, $offset ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		}
		$out = array(
			'ids'      => $ids,
			'total'    => $total,
			'pages'    => (int) ceil( $total / $per_page ),
			'page'     => $page,
			'per_page' => $per_page,
		);
		self::cache_set( $key, $out );
		return $out;
	}

	/**
	 * Every matching product id (no paging) in order, for the main query's post__in.
	 */
	public function all_ids( $sort = null ) {
		global $wpdb;
		$sort   = null === $sort ? $this->query->sort : $sort;
		$key    = $this->cache_key( 'all', array( $sort, md5( $this->order_sql( $sort ) ) ) );
		$cached = self::cache_get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$set = $this->set_ids();
		if ( is_array( $set ) && ! $set ) {
			self::cache_set( $key, array() );
			return array();
		}
		++self::$queries;
		$ids = array_map( 'intval', (array) $wpdb->get_col( $this->ids_sql( $sort ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		self::cache_set( $key, $ids );
		return $ids;
	}

	/**
	 * Number of matching products.
	 */
	public function count() {
		global $wpdb;
		$key    = $this->cache_key( 'count' );
		$cached = self::cache_get( $key );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		$set = $this->set_ids();
		if ( null === $set ) {
			++self::$queries;
			$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->t['products']} p WHERE p.parent_id = 0 AND p.published = 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		} else {
			$n = count( $set );
		}
		self::cache_set( $key, $n );
		return $n;
	}

	public static function per_page() {
		$n = 0;
		if ( function_exists( 'wc_get_default_products_per_row' ) ) {
			$n = (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		}
		// The store-wide "Products per page" (count_product_shop) reaches loop_shop_per_page only outside wp-admin;
		// admin-ajax answers (is_admin()) apply it here, so page 1 after a pick holds as many products as the page load.
		if ( wp_doing_ajax() && class_exists( 'WooBeWoo_PF_Frame' ) ) {
			$wf = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
			if ( $wf && method_exists( $wf, 'newLoopShopPerPage' ) ) {
				$n = (int) $wf->newLoopShopPerPage( $n );
			}
		}
		return (int) apply_filters( 'woobewoo_pf_per_page', $n > 0 ? $n : (int) get_option( 'posts_per_page', 12 ), null ); // 4.0.0: a shopper's wpf_per_page (modules/results), clamped there to 1..200.
	}

	/* ------------------------------------------------------------------ facets */

	/**
	 * Counts for every block: key => array( kind, counts (value => n), min, max ).
	 */
	public function facets( $only = array() ) {
		$key    = $this->cache_key( 'facets', array( array_values( (array) $only ) ) );
		$cached = self::cache_get( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$stale = $this->stale_facets( $key, $only );
		if ( is_array( $stale ) ) {
			return $stale;
		}
		return $this->compute_facets( $key, $only );
	}

	/**
	 * Key of the "last known counts" entry: like the facets cache key but without the index version.
	 */
	private function stale_key( $only ) {
		return 'wpf4s_' . md5( wp_json_encode( array( (int) $this->ctx['filter_id'], $this->blocks_sig(), $this->query->params_without_page(), $this->ctx['currency'], $this->ctx['lang'], $this->ctx['taxonomy'], (int) $this->ctx['term_id'], $this->ctx['search'], (bool) $this->ctx['all_products'], array_values( (array) $only ), $this->extra_sql_sig() ) ) );
	}

	/**
	 * Counts computed for an earlier index version are served now and refreshed after this response went out,
	 * so a product save never makes the next shopper wait for a cold count.
	 */
	private function stale_facets( $key, $only ) {
		if ( self::$nocache || ! apply_filters( 'woobewoo_pf_facets_stale_while_revalidate', true ) ) {
			return null;
		}
		$entry = get_transient( $this->stale_key( $only ) );
		if ( ! is_array( $entry ) || empty( $entry['data'] ) ) {
			return null;
		}
		$engine = $this;
		add_action(
			'shutdown',
			function () use ( $engine, $key, $only ) {
				if ( function_exists( 'fastcgi_finish_request' ) ) {
					fastcgi_finish_request();
				}
				$engine->compute_facets( $key, $only );
			},
			999
		);
		return $entry['data'];
	}

	/**
	 * Compute and store the counts for the current index version (and as the last known counts).
	 */
	public function compute_facets( $key, $only = array() ) {
		$out      = array();
		$combined = array(); // terms blocks with no own selection share one GROUP BY
		$rows     = array(); // sale / featured / rating / stock / price with no own selection share one pass over the product rows
		$registry = array(); // registered kinds with no own selection: one call per kind with all of its blocks
		foreach ( $this->blocks as $k => $b ) {
			if ( $only && ! in_array( $k, (array) $only, true ) ) {
				continue;
			}
			$own = $this->block_selected( $b );
			if ( 'terms' === $b['kind'] && $b['taxonomy'] && ! $own ) {
				$combined[ $k ] = $b;
				continue;
			}
			if ( ! $own && $this->row_kind( $b, '' ) ) {
				$rows[ $k ] = $b;
				continue;
			}
			if ( ! $own && $this->is_registry( $b ) ) {
				$registry[ $b['kind'] ][ $k ] = $b;
				continue;
			}
			$f = $this->facet( $b );
			if ( null !== $f ) {
				$out[ $k ] = $f;
			}
		}
		if ( $combined ) {
			$out = array_merge( $out, $this->terms_facets( $combined, '' ) );
		}
		if ( $rows ) {
			$out = array_merge( $out, $this->row_facets( $rows, '' ) );
		}
		foreach ( $registry as $kind => $group ) {
			$out = array_merge( $out, WooBeWoo_PF_Blocks::facets( WooBeWoo_PF_Blocks::of( reset( $group ) ), $group, $this, '' ) );
		}
		$ordered = array();
		foreach ( $this->blocks as $k => $b ) {
			if ( isset( $out[ $k ] ) ) {
				$ordered[ $k ] = $out[ $k ];
			}
		}
		$out = apply_filters( 'woobewoo_pf_engine_facets', $ordered, $this );
		self::cache_set( $key, $out );
		if ( ! self::$nocache ) {
			set_transient( $this->stale_key( $only ), array( 'version' => WooBeWoo_PF_Index::version(), 'data' => $out ), 7 * DAY_IN_SECONDS );
		}
		return $out;
	}

	/**
	 * Can this block be counted from the product rows of the narrowed set (one pass shared with the other such
	 * blocks)? Stock and price need the variation rows once two variation level conditions remain.
	 */
	private function row_kind( $b, $skipKey ) {
		switch ( $b['kind'] ) {
			case 'sale':
			case 'featured':
			case 'rating':
				return true;
			case 'stock':
				return ! $this->needs_leaf( $skipKey );
			case 'price':
				return ! $this->needs_leaf( $skipKey ) && $this->sql_currency() === WooBeWoo_PF_Index::base_currency();
		}
		return false;
	}

	/**
	 * Term counts for one or more taxonomy blocks over the narrowed set (without $skipKey's selection):
	 * one GROUP BY for all of them.
	 */
	private function terms_facets( $blocks, $skipKey ) {
		global $wpdb;
		$taxes = array();
		foreach ( $blocks as $k => $b ) {
			$taxes[ $b['taxonomy'] ] = $k;
		}
		$out = array();
		foreach ( $taxes as $tax => $k ) {
			$out[ $k ] = array(
				'kind'   => 'terms',
				'counts' => array(),
				'ids'    => array(),
			);
		}
		$set = $this->set_ids( $skipKey );
		if ( is_array( $set ) && ! $set ) {
			return $out;
		}
		$plainKey = null;
		if ( null === $set ) {
			// Whole catalogue counts: shared by every request that drops its last selection.
			$plainKey = $this->plain_key( 'terms', array_keys( $taxes ) );
			$cached   = self::cache_get( $plainKey );
			if ( is_array( $cached ) ) {
				foreach ( $cached as $tax => $rows ) {
					if ( isset( $taxes[ $tax ] ) ) {
						$out[ $taxes[ $tax ] ] = $rows;
					}
				}
				return $out;
			}
		}
		$ph = implode( ',', array_fill( 0, count( $taxes ), '%s' ) );
		if ( null === $set ) {
			$sql = "SELECT t.taxonomy, t.term_id, t.term_slug, COUNT(*) AS c FROM {$this->t['terms']} t INNER JOIN {$this->t['products']} p ON p.product_id = t.product_id AND p.parent_id = 0 AND p.published = 1 WHERE t.taxonomy IN ($ph) AND t.has_variation = 1 GROUP BY t.taxonomy, t.term_id, t.term_slug";
		} else {
			$sql = "SELECT t.taxonomy, t.term_id, t.term_slug, COUNT(*) AS c FROM {$this->t['terms']} t WHERE t.product_id IN (" . implode( ',', $set ) . ") AND t.taxonomy IN ($ph) AND t.has_variation = 1 GROUP BY t.taxonomy, t.term_id, t.term_slug";
		}
		++self::$queries;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array_keys( $taxes ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
		foreach ( (array) $rows as $r ) {
			$k = $taxes[ $r['taxonomy'] ];
			$out[ $k ]['counts'][ $r['term_slug'] ] = (int) $r['c'];
			$out[ $k ]['ids'][ $r['term_slug'] ]    = (int) $r['term_id'];
		}
		if ( $plainKey ) {
			$byTax = array();
			$byId  = is_array( $this->plainCounts ) ? $this->plainCounts : array();
			foreach ( $taxes as $tax => $k ) {
				$byTax[ $tax ] = $out[ $k ];
				$byId[ $tax ]  = array();
				foreach ( $out[ $k ]['ids'] as $slug => $id ) {
					$byId[ $tax ][ $id ] = $out[ $k ]['counts'][ $slug ];
				}
			}
			self::cache_set( $plainKey, $byTax );
			self::cache_set( $this->plain_key( 'termcounts' ), $byId );
			$this->plainCounts = $byId;
		}
		return $out;
	}

	/**
	 * Sale, featured and rating counts in one pass over the product rows of the narrowed set.
	 */
	private function row_facets( $blocks, $skipKey ) {
		global $wpdb;
		$cols = array();
		foreach ( $blocks as $k => $b ) {
			switch ( $b['kind'] ) {
				case 'sale':
					$cols['sale'] = 'COUNT(CASE WHEN p.on_sale = 1 THEN 1 END) AS sale';
					break;
				case 'featured':
					$cols['featured'] = 'COUNT(CASE WHEN p.featured = 1 THEN 1 END) AS featured';
					break;
				case 'rating':
					for ( $i = 1; $i <= 5; $i++ ) {
						$cols[ "r$i" ] = "COUNT(CASE WHEN p.rating >= $i THEN 1 END) AS r$i";
					}
					break;
				case 'stock':
					$cols['i'] = "COUNT(CASE WHEN p.stock_status <> 'outofstock' THEN 1 END) AS i";
					$cols['o'] = "COUNT(CASE WHEN p.stock_status = 'outofstock' THEN 1 END) AS o";
					$cols['b'] = "COUNT(CASE WHEN p.stock_status = 'onbackorder' THEN 1 END) AS b";
					break;
				case 'price':
					$cols['mn'] = 'MIN(p.price) AS mn';
					$cols['mx'] = 'MAX(COALESCE(p.price_max, p.price)) AS mx';
					break;
			}
		}
		$set = $this->set_ids( $skipKey );
		$row = null;
		if ( ! ( is_array( $set ) && ! $set ) ) {
			$plainKey = null === $set ? $this->plain_key( 'rows', array_keys( $cols ) ) : null;
			$row      = $plainKey ? self::cache_get( $plainKey ) : false;
			if ( ! is_array( $row ) ) {
				++self::$queries;
				$row = $wpdb->get_row( 'SELECT ' . implode( ', ', $cols ) . " FROM {$this->t['products']} p WHERE " . $this->set_cond( $set ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
				if ( $plainKey && is_array( $row ) ) {
					self::cache_set( $plainKey, $row );
				}
			}
		}
		$out = array();
		foreach ( $blocks as $k => $b ) {
			switch ( $b['kind'] ) {
				case 'sale':
					$out[ $k ] = array( 'kind' => 'sale', 'counts' => array( '1' => $row ? (int) $row['sale'] : 0 ) );
					break;
				case 'featured':
					$out[ $k ] = array( 'kind' => 'featured', 'counts' => array( '1' => $row ? (int) $row['featured'] : 0 ) );
					break;
				case 'rating':
					$counts = array();
					for ( $i = 1; $i <= 5; $i++ ) {
						$counts[ (string) $i ] = $row ? (int) $row[ 'r' . $i ] : 0;
					}
					$out[ $k ] = array( 'kind' => 'rating', 'counts' => $counts );
					break;
				case 'stock':
					$out[ $k ] = array(
						'kind'   => 'stock',
						'counts' => array(
							'in'        => $row ? (int) $row['i'] : 0,
							'out'       => $row ? (int) $row['o'] : 0,
							'backorder' => $row ? (int) $row['b'] : 0,
						),
					);
					break;
				case 'price':
					$out[ $k ] = array(
						'kind' => 'price',
						'min'  => ( $row && null !== $row['mn'] ) ? floor( (float) $row['mn'] * $this->price_rate() ) : 0,
						'max'  => ( $row && null !== $row['mx'] ) ? ceil( (float) $row['mx'] * $this->price_rate() ) : 0,
					);
					if ( $this->wants_price_bars( $b ) && ! ( is_array( $set ) && ! $set ) ) {
						$out[ $k ]['hist'] = $this->price_hist( "{$this->t['products']} p", $this->set_cond( $set ), 'p.price', 'COUNT(*)', $out[ $k ]['min'], $out[ $k ]['max'] );
					}
					break;
			}
		}
		return $out;
	}

	/**
	 * Variation rows of a product set: condition on alias l.
	 */
	private function leaf_set_cond( $set ) {
		if ( null === $set ) {
			return "l.product_type <> 'variable' AND l.published = 1";
		}
		if ( ! $set ) {
			return '0 = 1';
		}
		return "l.product_type <> 'variable' AND l.published = 1 AND l.root_id IN (" . implode( ',', $set ) . ')';
	}

	/**
	 * Counts for one block, recounted without its own selection (OR logic) or with it (AND logic).
	 */
	public function facet( $b ) {
		global $wpdb;
		if ( $this->row_kind( $b, $b['key'] ) ) {
			$r = $this->row_facets( array( $b['key'] => $b ), $b['key'] );
			return $r[ $b['key'] ];
		}
		switch ( $b['kind'] ) {
			case 'terms':
				if ( ! $b['taxonomy'] ) {
					return null;
				}
				$r = $this->terms_facets( array( $b['key'] => $b ), $b['key'] );
				return $r[ $b['key'] ];
			case 'author':
				$set    = $this->set_ids( $b['key'] );
				$counts = array();
				if ( ! ( is_array( $set ) && ! $set ) ) {
					$plainKey = null === $set ? $this->plain_key( 'author' ) : null;
					$counts   = $plainKey ? self::cache_get( $plainKey ) : false;
					if ( ! is_array( $counts ) ) {
						$counts = array();
						++self::$queries;
						$rows = $wpdb->get_results( "SELECT p.author_id, COUNT(*) AS c FROM {$this->t['products']} p WHERE " . $this->set_cond( $set ) . ' GROUP BY p.author_id', ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
						foreach ( (array) $rows as $r ) {
							$counts[ (string) (int) $r['author_id'] ] = (int) $r['c'];
						}
						if ( $plainKey ) {
							self::cache_set( $plainKey, $counts );
						}
					}
				}
				return array(
					'kind'   => 'author',
					'counts' => $counts,
				);
			case 'price':
				// Bounds over the variation rows that match every other variation level condition, or (other
				// currency) over the converted prices of the narrowed products.
				$cur  = $this->sql_currency();
				$base = WooBeWoo_PF_Index::base_currency();
				$lr   = $this->leaf_rows( $b['key'] );
				$row  = null;
				if ( $lr ) {
					++self::$queries;
					if ( $cur === $base ) {
						$row = $wpdb->get_row( "SELECT MIN(l.price) AS mn, MAX(l.price) AS mx FROM {$lr['from']} WHERE {$lr['where']} AND l.price IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
					} else {
						$row = $wpdb->get_row( $wpdb->prepare( "SELECT MIN(pr.price) AS mn, MAX(pr.price) AS mx FROM {$lr['from']} INNER JOIN {$this->t['prices']} pr ON pr.product_id = l.product_id AND pr.currency = %s WHERE {$lr['where']} AND pr.price IS NOT NULL", $cur ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
					}
				}
				$rate  = $this->price_rate();
				$facet = array(
					'kind' => 'price',
					'min'  => ( $row && null !== $row['mn'] ) ? floor( (float) $row['mn'] * $rate ) : 0,
					'max'  => ( $row && null !== $row['mx'] ) ? ceil( (float) $row['mx'] * $rate ) : 0,
				);
				if ( $lr && $this->wants_price_bars( $b ) ) {
					if ( $cur === $base ) {
						$facet['hist'] = $this->price_hist( $lr['from'], $lr['where'], 'l.price', 'COUNT(DISTINCT l.root_id)', $facet['min'], $facet['max'] );
					} else {
						$facet['hist'] = $this->price_hist( "{$lr['from']} INNER JOIN {$this->t['prices']} pr ON pr.product_id = l.product_id AND pr.currency = '" . esc_sql( $cur ) . "'", $lr['where'], 'pr.price', 'COUNT(DISTINCT l.root_id)', $facet['min'], $facet['max'] );
					}
				}
				return $facet;
			case 'stock':
				$lr  = $this->leaf_rows( $b['key'] );
				$row = null;
				if ( $lr ) {
					++self::$queries;
					$row = $wpdb->get_row( "SELECT COUNT(DISTINCT CASE WHEN l.stock_status <> 'outofstock' THEN l.root_id END) AS i, COUNT(DISTINCT CASE WHEN l.stock_status = 'outofstock' THEN l.root_id END) AS o, COUNT(DISTINCT CASE WHEN l.stock_status = 'onbackorder' THEN l.root_id END) AS b FROM {$lr['from']} WHERE {$lr['where']}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
				}
				return array(
					'kind'   => 'stock',
					'counts' => array(
						'in'        => $row ? (int) $row['i'] : 0,
						'out'       => $row ? (int) $row['o'] : 0,
						'backorder' => $row ? (int) $row['b'] : 0,
					),
				);
			case 'meta':
				$lr  = $this->leaf_rows( $b['key'] );
				$row = null;
				if ( $lr ) {
					++self::$queries;
					$row = $wpdb->get_row( "SELECT MIN(mm.value_num) AS mn, MAX(mm.value_num) AS mx FROM {$lr['from']} " . $this->meta_join( 'mm', $b['key'] ) . " WHERE {$lr['where']} AND mm.value_num IS NOT NULL", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
				}
				return array(
					'kind' => 'meta',
					'min'  => ( $row && null !== $row['mn'] ) ? (float) $row['mn'] : 0,
					'max'  => ( $row && null !== $row['mx'] ) ? (float) $row['mx'] : 0,
				);
		}
		// A registered block: recounted without its own selection (OR logic) like the others.
		if ( $this->is_registry( $b ) ) {
			$r = WooBeWoo_PF_Blocks::facets( WooBeWoo_PF_Blocks::of( $b ), array( $b['key'] => $b ), $this, 'and' === $b['logic'] ? '' : $b['key'] );
			return isset( $r[ $b['key'] ] ) ? $r[ $b['key'] ] : null;
		}
		return null;
	}

	/**
	 * The price block shows the little bar chart of where the prices sit (block setting f_price_bars, on by default).
	 *
	 * @since 4.0.0
	 */
	private function wants_price_bars( $b ) {
		if ( ! isset( $b['block'] ) || ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return true;
		}
		return (bool) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_price_bars' );
	}

	/**
	 * Products per price bucket between the shown bounds: 24 even buckets, for the bars above the price slider.
	 * Same rows and conditions as the bounds; numbers go into the SQL as fixed-point literals (no locale, no
	 * prepare(), because $where already holds prepared fragments that may contain "%").
	 *
	 * @since 4.0.0
	 *
	 * @return int[] 24 counts, or an empty array when there is nothing to draw.
	 */
	private function price_hist( $from, $where, $expr, $countExpr, $mn, $mx ) {
		global $wpdb;
		$bins = 24;
		$mn   = (float) $mn;
		$mx   = (float) $mx;
		if ( $mx <= $mn ) {
			return array();
		}
		$rate = sprintf( '%.6F', $this->price_rate() );
		$w    = sprintf( '%.6F', ( $mx - $mn ) / $bins );
		$lo   = sprintf( '%.6F', $mn );
		++self::$queries;
		$rows = $wpdb->get_results( "SELECT LEAST(" . ( $bins - 1 ) . ", GREATEST(0, FLOOR(({$expr} * {$rate} - {$lo}) / {$w}))) AS b, {$countExpr} AS c FROM {$from} WHERE {$where} AND {$expr} IS NOT NULL GROUP BY b", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- our own index tables; the numbers are sprintf('%F') literals and $where is built from prepared fragments
		$out  = array_fill( 0, $bins, 0 );
		foreach ( (array) $rows as $r ) {
			$i = (int) $r['b'];
			if ( $i >= 0 && $i < $bins ) {
				$out[ $i ] = (int) $r['c'];
			}
		}
		return array_sum( $out ) > 0 ? $out : array();
	}

	/**
	 * Count for a range of prices (price range lists and the finder's budget step).
	 */
	public function count_price_range( $min, $max ) {
		global $wpdb;
		$lr = $this->leaf_rows( 'price' );
		if ( ! $lr ) {
			return 0;
		}
		$this->sqlMode = 'leaf';
		$cond          = $this->price_condition( $min, $max );
		++self::$queries;
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT l.root_id) FROM {$lr['from']} WHERE {$lr['where']}" . ( $cond ? " AND $cond" : '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- direct query on our own facet index tables (names from self::tables() / $this->t, built from $wpdb->prefix); every value is cast or $wpdb->prepare()d
	}

	/**
	 * Units left (sum of variation stock) for one attribute value inside the current result set, or null when no
	 * variation of the set manages stock.
	 */
	public function units_left( $taxonomy, $slug ) {
		global $wpdb;
		$key    = $this->cache_key( 'left', array( $taxonomy, $slug ) );
		$cached = self::cache_get( $key );
		if ( false !== $cached ) {
			return '' === $cached ? null : (int) $cached;
		}
		$set = $this->set_ids();
		$row = null;
		if ( ! ( is_array( $set ) && ! $set ) ) {
			++self::$queries;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(l.stock_qty) AS s, COUNT(l.stock_qty) AS n FROM {$this->t['products']} l INNER JOIN {$this->t['terms']} tl ON tl.product_id = l.product_id AND tl.taxonomy = %s AND tl.term_slug = %s WHERE " . $this->leaf_set_cond( $set ) . " AND l.product_type = 'variation' AND l.stock_status <> 'outofstock'", $taxonomy, $slug ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the interpolated pieces are $wpdb->prepare() fragments, cast integers and our own index table names; nothing comes from the request unprepared
		}
		$left = ( $row && (int) $row['n'] > 0 ) ? (int) $row['s'] : null;
		self::cache_set( $key, null === $left ? '' : $left );
		return $left;
	}

	/**
	 * The variation of a product that matches the picked attribute terms (for variations as products).
	 */
	public function matching_variation( $product_id ) {
		$attr = array();
		foreach ( $this->blocks as $b ) {
			if ( 'terms' === $b['kind'] && 0 === strpos( (string) $b['taxonomy'], 'pa_' ) && ! empty( $this->query->terms[ $b['key'] ] ) ) {
				$attr[ $b['taxonomy'] ] = $this->query->terms[ $b['key'] ];
			}
		}
		foreach ( $this->query->terms as $key => $slugs ) {
			$tax = WooBeWoo_PF_Query::taxonomy_for_key( $key );
			if ( $tax && 0 === strpos( $tax, 'pa_' ) && ! isset( $attr[ $tax ] ) ) {
				$attr[ $tax ] = $slugs;
			}
		}
		if ( ! $attr ) {
			return null;
		}
		return WooBeWoo_PF_Index::lookup_variation( $product_id, $attr );
	}
}
