<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Query Class
 *
 * The URL contract of the 4.0 storefront, shared with the platform module (pretty paths) and the admin UI.
 *
 * Canonical query vars:
 *   wpf_<block_key>=<slug>,<slug>   terms (block_key = attribute slug without pa_, or category|tag|brand|author)
 *   wpf_price=<min>-<max>           price in the active currency
 *   wpf_stock=in|out|backorder
 *   wpf_sale=1  wpf_featured=1  wpf_rating=<min>  wpf_sort=<key>  wpf_page=<n>  wpf_q=<text>
 *   wpf_<meta_key>=<min>-<max>      numeric custom field
 *   wpf_cf_<key>=<text>             a "Custom field text" search block; wpf_q2, wpf_q3 a second / third search box
 *   wpf_<key>=<value>,<value>       a block registered with WooBeWoo_PF_Blocks (its own `parse` may read it differently)
 * Legacy 3.x vars (wpf_filter_<slug>, wpf_filter_cat_0, product_tag_0, wpf_min_price, wpf_max_price, pr_stock,
 * pr_onsale, pr_featured, pr_rating, pr_author, wpf_orderby / wpf_order, wpf_search) are still accepted.
 *
 * Usage:
 *   $q = WooBeWoo_PF_Query::from_request();            // current request ($_GET + parse_request query vars)
 *   $q = WooBeWoo_PF_Query::from_params( $array );      // any array of query vars (canonical or legacy)
 *   $vars = WooBeWoo_PF_Query::to_params( $q );         // canonical query vars array
 *   $url  = $q->to_url( $base );                       // filtered through `woobewoo_pf_filter_url` for pretty paths
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Query {

	/** @var array block_key => array of slugs */
	public $terms = array();
	/** @var array|null array( min|null, max|null ) */
	public $price = null;
	/** @var string|null in|out|backorder */
	public $stock = null;
	public $sale     = false;
	public $featured = false;
	/** @var int|null */
	public $rating = null;
	/** @var string|null */
	public $sort = null;
	public $page = 1;
	public $q    = '';
	/** @var array meta_key => array( min|null, max|null ) */
	public $meta = array();
	/** @var array text block key (cf_<key>, q2 …) => typed text */
	public $text = array();
	/**
	 * Values of registered blocks (WooBeWoo_PF_Blocks): block key => whatever the block's `parse` stored, a list of
	 * strings by default. Part of params(), hash(), is_empty() and has_filters().
	 *
	 * @since 4.0.0
	 * @var array
	 */
	public $extra = array();

	const RESERVED = array( 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' );
	const FIXED    = array(
		'category' => 'product_cat',
		'tag'      => 'product_tag',
		'brand'    => 'product_brand',
	);
	const SORTS    = array( 'default', 'popularity', 'rating', 'date', 'date-asc', 'price', 'price-desc', 'rand', 'title', 'title-desc', 'sku', 'sku-desc' );

	/* ------------------------------------------------------------------ parsing */

	/**
	 * Query from the current request: $_GET merged with the query vars the platform set at parse_request.
	 */
	public static function from_request( $source = null ) {
		if ( null === $source ) {
			$source = array();
			if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && ! empty( $GLOBALS['wp']->query_vars ) ) {
				foreach ( (array) $GLOBALS['wp']->query_vars as $k => $v ) {
					if ( 0 === strpos( $k, 'wpf_' ) || 0 === strpos( $k, 'pr_' ) || 0 === strpos( $k, 'product_tag_' ) ) {
						$source[ $k ] = $v;
					}
				}
			}
			if ( isset( $_GET ) && is_array( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				foreach ( wp_unslash( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$source[ $k ] = $v;
				}
			}
			if ( isset( $GLOBALS['wp_query'] ) && is_object( $GLOBALS['wp_query'] ) && empty( $source['wpf_page'] ) ) {
				$paged = (int) $GLOBALS['wp_query']->get( 'paged' );
				if ( $paged > 1 ) {
					$source['wpf_page'] = $paged;
				}
			}
		}
		return self::from_params( $source );
	}

	/**
	 * Query from an array of query vars (canonical and legacy names, values as strings or arrays).
	 */
	public static function from_params( $vars ) {
		$q = new self();
		if ( ! is_array( $vars ) ) {
			return $q;
		}
		foreach ( $vars as $key => $raw ) {
			$key = (string) $key;
			if ( is_array( $raw ) ) {
				$raw = implode( ',', array_map( 'strval', $raw ) );
			}
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			// Legacy 3.x names first.
			if ( preg_match( '/^wpf_filter_cat(_\d+)?$/', $key ) || 'wpf_filter_cat_list' === $key ) {
				$q->add_terms( 'category', self::ids_or_slugs_to_slugs( $raw, 'product_cat' ) );
				continue;
			}
			if ( preg_match( '/^product_tag(_\d+)?$/', $key ) ) {
				$q->add_terms( 'tag', self::ids_or_slugs_to_slugs( $raw, 'product_tag' ) );
				continue;
			}
			if ( preg_match( '/^(pr_)?wpf_filter_(pwb|brand|product_brand)(_\d+)?$/', $key ) ) {
				$q->add_terms( 'brand', self::ids_or_slugs_to_slugs( $raw, taxonomy_exists( 'product_brand' ) ? 'product_brand' : 'pwb-brand' ) );
				continue;
			}
			if ( preg_match( '/^(pr_)?wpf_filter_([a-z0-9_-]+?)(_\d+)?$/', $key, $m ) ) {
				$q->add_terms( sanitize_key( $m[2] ), self::split( $raw ) );
				continue;
			}
			if ( 'wpf_min_price' === $key ) {
				$q->price    = is_array( $q->price ) ? $q->price : array( null, null );
				$q->price[0] = self::number( $raw );
				continue;
			}
			if ( 'wpf_max_price' === $key ) {
				$q->price    = is_array( $q->price ) ? $q->price : array( null, null );
				$q->price[1] = self::number( $raw );
				continue;
			}
			if ( 'pr_stock' === $key ) {
				$q->stock = self::stock_value( $raw );
				continue;
			}
			if ( 'pr_onsale' === $key ) {
				$q->sale = '' !== $raw && '0' !== $raw;
				continue;
			}
			if ( 'pr_featured' === $key ) {
				$q->featured = '' !== $raw && '0' !== $raw;
				continue;
			}
			if ( 'pr_rating' === $key ) {
				$q->rating = max( 1, min( 5, (int) $raw ) );
				continue;
			}
			if ( 'pr_author' === $key ) {
				$q->add_terms( 'author', self::split( $raw ) );
				continue;
			}
			if ( 'wpf_orderby' === $key ) {
				$order   = isset( $vars['wpf_order'] ) ? strtolower( (string) $vars['wpf_order'] ) : '';
				$sort    = sanitize_key( $raw );
				$sort    = ( 'desc' === $order && in_array( $sort . '-desc', self::SORTS, true ) ) ? $sort . '-desc' : $sort;
				$q->sort = in_array( $sort, self::SORTS, true ) ? $sort : null;
				continue;
			}
			if ( in_array( $key, array( 'wpf_order', 'wpf_count', 'wpf_fbv', 'wpf_skip', 'wpf_fid', 'wpf_nocache', 'wpf_pretty', 'wpf_query', 'wpf_tax', 'wpf_group', 'wpf_dpv', 'wpf_ebv', 'wpf_light', 'wpf_id', 'wpf_default', 'wpf_preview', 'wpf_page_type', 'wpf_selftest', 'wpf_place', 'wpf_lic_for' ), true ) ) {
				continue;
			}
			// Form fallback without JavaScript: wpf_<key>_min / wpf_<key>_max number inputs.
			if ( preg_match( '/^wpf_([a-z0-9_-]+)_(min|max)$/', $key, $m ) && is_numeric( str_replace( ',', '.', $raw ) ) ) {
				$rk = sanitize_key( $m[1] );
				$i  = 'min' === $m[2] ? 0 : 1;
				if ( 'price' === $rk ) {
					$q->price       = is_array( $q->price ) ? $q->price : array( null, null );
					$q->price[ $i ] = self::number( $raw );
				} elseif ( '' !== $rk && ! self::taxonomy_for_key( $rk ) ) {
					if ( ! isset( $q->meta[ $rk ] ) ) {
						$q->meta[ $rk ] = array( null, null );
					}
					$q->meta[ $rk ][ $i ] = self::number( $raw );
				}
				continue;
			}
			if ( 'wpf_search' === $key || 'wpf_text' === $key ) {
				$q->q = sanitize_text_field( $raw );
				continue;
			}
			// Canonical names.
			if ( 0 !== strpos( $key, 'wpf_' ) ) {
				continue;
			}
			$name = substr( $key, 4 );
			switch ( $name ) {
				case 'price':
					$q->price = self::range( $raw );
					break;
				case 'stock':
					$q->stock = self::stock_value( $raw );
					break;
				case 'sale':
					$q->sale = '' !== $raw && '0' !== $raw;
					break;
				case 'featured':
					$q->featured = '' !== $raw && '0' !== $raw;
					break;
				case 'rating':
					$q->rating = max( 1, min( 5, (int) $raw ) );
					break;
				case 'sort':
					$sort    = sanitize_key( $raw );
					$q->sort = in_array( $sort, self::SORTS, true ) ? $sort : ( '' !== $sort ? $sort : null );
					break;
				case 'page':
					$q->page = max( 1, (int) $raw );
					break;
				case 'q':
					$q->q = sanitize_text_field( $raw );
					break;
				default:
					$name = sanitize_key( $name );
					if ( '' === $name ) {
						break;
					}
					// A registered block (WooBeWoo_PF_Blocks) owns its key before the text / taxonomy / meta guesses.
					$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::by_key( $name ) : null;
					if ( $def ) {
						WooBeWoo_PF_Blocks::parse( $def, $raw, $q, $name );
						break;
					}
					if ( self::is_text_key( $name ) ) {
						$q->text[ $name ] = sanitize_text_field( $raw );
						break;
					}
					$tax = self::taxonomy_for_key( $name );
					if ( $tax || 'author' === $name ) {
						$q->add_terms( $name, self::split( $raw ) );
					} elseif ( preg_match( '/^-?[\d.,]*-[\d.,]*$/', $raw ) || is_numeric( $raw ) ) {
						$q->meta[ $name ] = self::range( $raw );
					} elseif ( apply_filters( 'woobewoo_pf_query_accept_key', false, $name, $raw ) ) {
						$q->add_terms( $name, self::split( $raw ) );
					}
					break;
			}
		}
		if ( is_array( $q->price ) && null === $q->price[0] && null === $q->price[1] ) {
			$q->price = null;
		}
		foreach ( $q->meta as $mk => $mr ) {
			if ( null === $mr[0] && null === $mr[1] ) {
				unset( $q->meta[ $mk ] );
			}
		}
		return apply_filters( 'woobewoo_pf_query_from_params', $q, $vars );
	}

	/* ------------------------------------------------------------------ building */

	/**
	 * Canonical query vars for a query (instance or normalized array).
	 */
	public static function to_params( $query ) {
		if ( $query instanceof self ) {
			return $query->params();
		}
		if ( is_array( $query ) ) {
			$q = new self();
			foreach ( array( 'terms', 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q', 'meta', 'text', 'extra' ) as $k ) {
				if ( array_key_exists( $k, $query ) ) {
					$q->{$k} = $query[ $k ];
				}
			}
			return $q->params();
		}
		return array();
	}

	/**
	 * Canonical query vars of this query, keys in a stable order (good for cache keys and canonical URLs).
	 */
	public function params() {
		$out  = array();
		$keys = array_keys( $this->terms );
		sort( $keys );
		foreach ( $keys as $k ) {
			$slugs = array_values( array_unique( array_filter( array_map( 'strval', (array) $this->terms[ $k ] ) ) ) );
			if ( $slugs ) {
				sort( $slugs );
				$out[ 'wpf_' . $k ] = implode( ',', $slugs );
			}
		}
		if ( is_array( $this->price ) && ( null !== $this->price[0] || null !== $this->price[1] ) ) {
			$out['wpf_price'] = self::format_range( $this->price );
		}
		if ( $this->stock ) {
			$out['wpf_stock'] = $this->stock;
		}
		if ( $this->sale ) {
			$out['wpf_sale'] = '1';
		}
		if ( $this->featured ) {
			$out['wpf_featured'] = '1';
		}
		if ( $this->rating ) {
			$out['wpf_rating'] = (string) (int) $this->rating;
		}
		$metaKeys = array_keys( $this->meta );
		sort( $metaKeys );
		foreach ( $metaKeys as $k ) {
			$r = $this->meta[ $k ];
			if ( is_array( $r ) && ( null !== $r[0] || null !== $r[1] ) ) {
				$out[ 'wpf_' . $k ] = self::format_range( $r );
			}
		}
		if ( '' !== $this->q ) {
			$out['wpf_q'] = $this->q;
		}
		$textKeys = array_keys( $this->text );
		sort( $textKeys );
		foreach ( $textKeys as $k ) {
			if ( '' !== (string) $this->text[ $k ] ) {
				$out[ 'wpf_' . $k ] = (string) $this->text[ $k ];
			}
		}
		$extraKeys = array_keys( (array) $this->extra );
		sort( $extraKeys );
		foreach ( $extraKeys as $k ) {
			$k = sanitize_key( $k );
			if ( '' === $k || isset( $out[ 'wpf_' . $k ] ) ) {
				continue;
			}
			$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::by_key( $k ) : null;
			$v   = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::param( $def, $this->extra[ $k ] ) : ( is_array( $this->extra[ $k ] ) ? implode( ',', array_map( 'strval', $this->extra[ $k ] ) ) : (string) $this->extra[ $k ] );
			if ( '' !== $v ) {
				$out[ 'wpf_' . $k ] = $v;
			}
		}
		if ( $this->sort && 'default' !== $this->sort ) {
			$out['wpf_sort'] = $this->sort;
		}
		if ( (int) $this->page > 1 ) {
			$out['wpf_page'] = (string) (int) $this->page;
		}
		return $out;
	}

	/**
	 * Same as params() but without the page (the cache key of counts and the canonical URL ignore paging).
	 */
	public function params_without_page() {
		$p = $this->params();
		unset( $p['wpf_page'] );
		return $p;
	}

	/**
	 * Plain query string URL on a base, then the `woobewoo_pf_filter_url` filter (the platform module turns it into
	 * a pretty path such as /shop/size-42,43/colour-red/price-50-120/).
	 */
	public function to_url( $base = '' ) {
		if ( '' === $base ) {
			$base = self::current_base();
		}
		$params = $this->params();
		$url    = $params ? add_query_arg( array_map( 'rawurlencode', $params ), $base ) : $base;
		$url    = str_replace( '%2C', ',', $url );
		return apply_filters( 'woobewoo_pf_filter_url', $url, $params, $base, $this );
	}

	/**
	 * Current page URL without any filter query vars (the base filter links are built on).
	 */
	public static function current_base() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$path = preg_replace( '#/page/\d+/?$#', '/', $path );
		$qs   = (string) wp_parse_url( $uri, PHP_URL_QUERY );
		$keep = array();
		if ( '' !== $qs ) {
			parse_str( $qs, $all );
			foreach ( (array) $all as $k => $v ) {
				if ( 0 === strpos( $k, 'wpf_' ) || 0 === strpos( $k, 'pr_' ) || 0 === strpos( $k, 'product_tag_' ) || in_array( $k, array( 'paged', 'product-page' ), true ) ) {
					continue;
				}
				$keep[ $k ] = $v;
			}
		}
		// REQUEST_URI already holds the home path of a site in a folder (a subdirectory network site, WordPress in
		// /store/): drop it before home_url() adds it again (/site2/site2/page/ otherwise).
		$homePath = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $homePath && ( $path === $homePath || 0 === strpos( $path, $homePath . '/' ) ) ) {
			$path = (string) substr( $path, strlen( $homePath ) );
		}
		$base = home_url( '' === $path ? '/' : $path );
		return $keep ? add_query_arg( $keep, $base ) : $base;
	}

	public function is_empty() {
		return empty( $this->terms ) && null === $this->price && null === $this->stock && ! $this->sale && ! $this->featured && null === $this->rating && empty( $this->meta ) && '' === $this->q && ! array_filter( $this->text, 'strlen' ) && ! $this->has_extra() && ( ! $this->sort || 'default' === $this->sort );
	}

	/**
	 * Something besides sort and page is picked (used for "filtered" states, bot cache and loop hooks).
	 */
	public function has_filters() {
		return ! empty( $this->terms ) || null !== $this->price || null !== $this->stock || $this->sale || $this->featured || null !== $this->rating || ! empty( $this->meta ) || '' !== $this->q || (bool) array_filter( $this->text, 'strlen' ) || $this->has_extra();
	}

	/**
	 * Is a registered block's value picked (a non-empty entry in $extra)?
	 *
	 * @since 4.0.0
	 */
	public function has_extra() {
		if ( empty( $this->extra ) ) {
			return false;
		}
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			return WooBeWoo_PF_Blocks::extra_set( $this->extra );
		}
		foreach ( (array) $this->extra as $v ) {
			if ( is_array( $v ) ? (bool) array_filter( array_map( 'strval', $v ), 'strlen' ) : '' !== (string) $v ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Keys of the extra text search blocks: cf_<key> (custom field text) and q2, q3 … (more search boxes).
	 */
	public static function is_text_key( $key ) {
		return (bool) preg_match( '/^(q\d+|cf_[a-z0-9_-]+)$/', (string) $key );
	}

	/**
	 * Custom field keys a Search by text block searches (Pro "Search in: Custom fields"), as typed.
	 */
	public static function search_meta_keys( $settings ) {
		$s  = is_array( $settings ) ? $settings : array();
		$on = isset( $s['f_search_by_meta_fields'] ) && ! in_array( strtolower( trim( (string) $s['f_search_by_meta_fields'] ) ), array( '', '0', 'no', 'false', 'off' ), true );
		if ( ! $on || empty( $s['f_search_by_meta_fields_list'] ) ) {
			return array();
		}
		$keys = array();
		foreach ( explode( ',', (string) $s['f_search_by_meta_fields_list'] ) as $k ) {
			$k = trim( wp_strip_all_tags( $k ) );
			if ( '' !== $k && preg_match( '/^[A-Za-z0-9_\-]{1,191}$/', $k ) ) {
				$keys[] = $k;
			}
		}
		return array_values( array_unique( $keys ) );
	}

	public function add_terms( $key, $slugs ) {
		$slugs = array_values( array_filter( array_map( 'sanitize_title', (array) $slugs ) ) );
		if ( ! $slugs ) {
			return;
		}
		$key = sanitize_key( $key );
		if ( ! isset( $this->terms[ $key ] ) ) {
			$this->terms[ $key ] = array();
		}
		$this->terms[ $key ] = array_values( array_unique( array_merge( $this->terms[ $key ], $slugs ) ) );
	}

	public function hash( $extra = array() ) {
		return md5( wp_json_encode( array( $this->params(), $extra ) ) );
	}

	/* ------------------------------------------------------------------ keys and taxonomies */

	/**
	 * Taxonomy for a block key: category|tag|brand, an attribute slug (pa_<key>) or a custom taxonomy name.
	 */
	public static function taxonomy_for_key( $key ) {
		$key = sanitize_key( $key );
		if ( isset( self::FIXED[ $key ] ) ) {
			if ( 'brand' === $key ) {
				return taxonomy_exists( 'product_brand' ) ? 'product_brand' : ( taxonomy_exists( 'pwb-brand' ) ? 'pwb-brand' : 'product_brand' );
			}
			return self::FIXED[ $key ];
		}
		if ( in_array( $key, self::RESERVED, true ) || 'author' === $key ) {
			return '';
		}
		$custom = apply_filters( 'woobewoo_pf_taxonomy_for_key', '', $key );
		if ( $custom ) {
			return $custom;
		}
		if ( taxonomy_exists( 'pa_' . $key ) ) {
			return 'pa_' . $key;
		}
		if ( taxonomy_exists( $key ) && in_array( 'product', (array) get_taxonomy( $key )->object_type, true ) ) {
			return $key;
		}
		return '';
	}

	/**
	 * Block key for a taxonomy (inverse of taxonomy_for_key).
	 */
	public static function key_for_taxonomy( $taxonomy ) {
		switch ( $taxonomy ) {
			case 'product_cat':
				return 'category';
			case 'product_tag':
				return 'tag';
			case 'product_brand':
			case 'pwb-brand':
				return 'brand';
		}
		if ( 0 === strpos( $taxonomy, 'pa_' ) ) {
			$key = sanitize_key( substr( $taxonomy, 3 ) );
			return in_array( $key, self::RESERVED, true ) ? 'attr-' . $key : $key;
		}
		return sanitize_key( $taxonomy );
	}

	/**
	 * Block key for a filter block (the builder's block array).
	 */
	public static function block_key( $block ) {
		$id = isset( $block['id'] ) ? $block['id'] : '';
		$s  = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		switch ( $id ) {
			case 'wpfCategory':
				return 'category';
			case 'wpfTags':
				return 'tag';
			case 'wpfBrand':
			case 'wpfPerfectBrand':
				return 'brand';
			case 'wpfAuthor':
			case 'wpfVendors':
				return 'author';
			case 'wpfPrice':
			case 'wpfPriceRange':
				return 'price';
			case 'wpfInStock':
				return 'stock';
			case 'wpfOnSale':
				return 'sale';
			case 'wpfFeatured':
				return 'featured';
			case 'wpfRating':
				return 'rating';
			case 'wpfSortBy':
				return 'sort';
			case 'wpfSearchText':
				// A "Custom field text" block searches its own fields under its own key (wpf_cf_<key>).
				$metaKeys = self::search_meta_keys( $s );
				return $metaKeys ? 'cf_' . substr( sanitize_key( implode( '-', $metaKeys ) ), 0, 60 ) : 'q';
			case 'wpfSearchNumber':
			case 'wpfCustomField':
				// Pro's builder writes the picked field to f_custom_meta_field; the Pro save hook copies it to f_meta_key
				// on Save only, so read both (the unsaved builder preview has only the first).
				// The Free builder's "Which custom field" writes f_customfield_ (3.x key).
				$key = empty( $s['f_custom_fields'] ) ? ( empty( $s['f_meta_key'] ) ? ( empty( $s['f_custom_meta_field'] ) ? ( empty( $s['f_customfield_'] ) ? '' : $s['f_customfield_'] ) : $s['f_custom_meta_field'] ) : $s['f_meta_key'] ) : $s['f_custom_fields'];
				return sanitize_key( $key );
			case 'wpfAttribute':
				$tax = self::block_taxonomy( $block );
				return $tax ? self::key_for_taxonomy( $tax ) : '';
		}
		// A registered block (WooBeWoo_PF_Blocks) names its own key.
		$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::get( $id ) : null;
		if ( $def ) {
			return WooBeWoo_PF_Blocks::key_of( $def, $block );
		}
		return apply_filters( 'woobewoo_pf_block_key', '', $block );
	}

	/**
	 * Taxonomy of a block, when it has one.
	 */
	public static function block_taxonomy( $block ) {
		$id = isset( $block['id'] ) ? $block['id'] : '';
		$s  = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		switch ( $id ) {
			case 'wpfCategory':
				return 'product_cat';
			case 'wpfTags':
				return 'product_tag';
			case 'wpfBrand':
				return 'product_brand';
			case 'wpfPerfectBrand':
				return 'pwb-brand';
			case 'wpfAttribute':
				if ( ! empty( $s['f_taxonomy'] ) && taxonomy_exists( $s['f_taxonomy'] ) ) {
					return $s['f_taxonomy'];
				}
				$attrId = empty( $s['f_list'] ) ? 0 : (int) $s['f_list'];
				if ( $attrId && function_exists( 'wc_attribute_taxonomy_name_by_id' ) ) {
					$name = wc_attribute_taxonomy_name_by_id( $attrId );
					return $name ? $name : '';
				}
				return '';
		}
		return (string) apply_filters( 'woobewoo_pf_block_taxonomy', '', $block );
	}

	/**
	 * Query vars to register with WordPress (so the platform's parse_request values survive).
	 */
	public static function query_vars() {
		$vars = array( 'wpf_price', 'wpf_stock', 'wpf_sale', 'wpf_featured', 'wpf_rating', 'wpf_sort', 'wpf_page', 'wpf_q', 'wpf_category', 'wpf_tag', 'wpf_brand', 'wpf_author' );
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( (array) wc_get_attribute_taxonomies() as $a ) {
				$vars[] = 'wpf_' . sanitize_key( $a->attribute_name );
			}
		}
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			$vars = array_merge( $vars, WooBeWoo_PF_Blocks::query_keys() );
		}
		return array_unique( $vars );
	}

	/* ------------------------------------------------------------------ helpers */

	public static function split( $raw ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,|]/', (string) $raw ) ) ) );
	}

	/**
	 * "50-120" => array( 50.0, 120.0 ); "50-" => array( 50.0, null ); "-120" => array( null, 120.0 ); "50" => array( 50.0, 50.0 ).
	 */
	public static function range( $raw ) {
		$raw = str_replace( ' ', '', (string) $raw );
		if ( preg_match( '/^(-?[\d.,]*)-([\d.,]*)$/', $raw, $m ) && ( '' !== $m[1] || '' !== $m[2] ) ) {
			return array( self::number( $m[1] ), self::number( $m[2] ) );
		}
		if ( is_numeric( str_replace( ',', '.', $raw ) ) ) {
			$n = self::number( $raw );
			return array( $n, $n );
		}
		return array( null, null );
	}

	public static function format_range( $r ) {
		$min = null === $r[0] ? '' : self::trim_number( $r[0] );
		$max = null === $r[1] ? '' : self::trim_number( $r[1] );
		return $min . '-' . $max;
	}

	public static function number( $v ) {
		$v = str_replace( ',', '.', trim( (string) $v ) );
		if ( '' === $v || ! is_numeric( $v ) ) {
			return null;
		}
		return (float) $v;
	}

	public static function trim_number( $n ) {
		$s = rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
		return '' === $s || '-' === $s ? '0' : $s;
	}

	public static function stock_value( $raw ) {
		$raw = strtolower( trim( (string) $raw ) );
		$raw = explode( ',', $raw )[0];
		switch ( $raw ) {
			case 'in':
			case 'instock':
				return 'in';
			case 'out':
			case 'outofstock':
				return 'out';
			case 'backorder':
			case 'on':
			case 'onbackorder':
				return 'backorder';
		}
		return null;
	}

	/**
	 * Legacy values may be term ids: turn them into slugs.
	 */
	private static function ids_or_slugs_to_slugs( $raw, $taxonomy ) {
		$out = array();
		foreach ( self::split( $raw ) as $v ) {
			if ( ctype_digit( (string) $v ) ) {
				$term = get_term( (int) $v, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$out[] = $term->slug;
				}
			} else {
				$out[] = sanitize_title( $v );
			}
		}
		return $out;
	}
}
