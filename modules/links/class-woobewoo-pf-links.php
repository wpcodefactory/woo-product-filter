<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Links Class
 *
 * Pretty URLs for filtered pages (/shop/size-42,43/colour-red/price-50-120/, also under
 * product category, tag and brand archives), 301 from legacy query strings when "Clean links"
 * is on, short shareable links (/shop/f/<slug>) with a server side QR code, a Share button in
 * the storefront and REST wpf/v1/short-links.
 *
 * Canonical query vars (the contract shared with the engine, WooBeWoo_PF_Query::from_request()):
 *   wpf_<block_key>=<slug>,<slug>  wpf_price=<min>-<max>  wpf_stock=in|out|backorder  wpf_sale=1
 *   wpf_featured=1  wpf_rating=<min>  wpf_sort=<key>  wpf_page=<n>  wpf_q=<text>  wpf_<meta_key>=<min>-<max>
 *   block_key = attribute slug without pa_, or category|tag|brand|author.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooBeWoo_PF_QR' ) ) {
	require_once __DIR__ . '/class-woobewoo-pf-qr.php';
}

class WooBeWoo_PF_Links extends WooBeWoo_PF_Module {

	const QV        = 'wpf_pretty';
	const QV_SHORT  = 'wpf_short';
	const HASH_OPT  = 'wpf_links_rules_hash';
	const FLUSH_OPT = 'wpf_links_flush_needed';

	/** Canonical params parsed from the current pretty URL. */
	public $params = array();
	/** Archive base URL of the current pretty URL. */
	public $base = '';
	/** True when this request came through a pretty URL. */
	public $active = false;

	private $shareRendered = array();
	private $segmentsCache = null;

	public function init() {
		parent::init();
		if ( '1' !== (string) get_option( 'wpf_short_links_table' ) ) {
			WooBeWoo_PF_Installer_Db_Updater::createShortLinksTable();
			if ( WooBeWoo_PF_Db::exist( '@__short_links' ) ) {
				update_option( 'wpf_short_links_table', '1', true );
			}
		}
		add_filter( 'query_vars', array( $this, 'queryVars' ) );
		add_action( 'init', array( $this, 'registerRewrites' ), 20 );
		add_action( 'init', array( $this, 'maybeFlush' ), 99 );
		add_action( 'parse_request', array( $this, 'parseRequest' ), 2 );
		add_action( 'rest_api_init', array( $this, 'registerRest' ) );
		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'resolveShort' ), 0 );
			add_action( 'template_redirect', array( $this, 'redirectLegacy' ), 1 );
			add_action( 'wp', array( $this, 'canonicalFix' ), 11 );
			add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
			WooBeWoo_PF_Dispatcher::addFilter( 'addHtmlAfterFilter', array( $this, 'shareButtonLegacy' ), 40, 3 );
			add_filter( 'woobewoo_pf_after_filter_html', array( $this, 'shareButton' ), 40, 3 );
			// The 4.0 view prints its own after-form hook (woobewoo_pf_after_filter_html is not fired anywhere).
			add_filter( 'woobewoo_pf_frontend4_after_form', array( $this, 'shareButton4' ), 40, 3 );
		}
		add_filter( 'woobewoo_pf_selftest_rows', array( $this, 'selfTestRows' ) );
		add_filter( 'woobewoo_pf_filter_url', array( $this, 'filterUrl' ), 10, 4 );
	}

	/* ------------------------------------------------------------------ settings */

	public function cleanLinksOn() {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'isOn' ) ) {
			return $speed->isOn( 'wbw_clean_links' );
		}
		return '1' === (string) WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'wbw_clean_links' );
	}

	public function canonicalOn() {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'isOn' ) ) {
			return $speed->isOn( 'canonical_links' );
		}
		return '1' === (string) WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'canonical_links' );
	}

	/* ------------------------------------------------------------------ segments and keys */

	/**
	 * Canonical key => URL segment word. Translatable and filterable (woobewoo_pf_link_segments).
	 */
	public function segments() {
		if ( null !== $this->segmentsCache ) {
			return $this->segmentsCache;
		}
		$s = array(
			'category' => _x( 'category', 'url segment', 'woo-product-filter' ),
			'tag'      => _x( 'tag', 'url segment', 'woo-product-filter' ),
			'brand'    => _x( 'brand', 'url segment', 'woo-product-filter' ),
			'author'   => _x( 'author', 'url segment', 'woo-product-filter' ),
			'price'    => _x( 'price', 'url segment', 'woo-product-filter' ),
			'stock'    => _x( 'stock', 'url segment', 'woo-product-filter' ),
			'sale'     => _x( 'sale', 'url segment', 'woo-product-filter' ),
			'featured' => _x( 'featured', 'url segment', 'woo-product-filter' ),
			'rating'   => _x( 'rating', 'url segment', 'woo-product-filter' ),
			'sort'     => _x( 'sort', 'url segment', 'woo-product-filter' ),
			'page'     => _x( 'page', 'url segment', 'woo-product-filter' ),
			'q'        => _x( 'search', 'url segment', 'woo-product-filter' ),
		);
		foreach ( $this->attributeSlugs() as $slug ) {
			if ( ! isset( $s[ $slug ] ) ) {
				$s[ $slug ] = $slug;
			}
		}
		foreach ( $this->metaKeys() as $key ) {
			if ( ! isset( $s[ $key ] ) ) {
				$s[ $key ] = $key;
			}
		}
		$s = apply_filters( 'woobewoo_pf_link_segments', $s );
		foreach ( $s as $k => $v ) {
			$s[ $k ] = sanitize_title( $v );
		}
		$this->segmentsCache = $s;
		return $s;
	}

	/** Attribute slugs without the pa_ prefix. */
	public function attributeSlugs() {
		$out = array();
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $a ) {
				$out[] = sanitize_title( $a->attribute_name );
			}
		}
		return $out;
	}

	/**
	 * Numeric meta keys used by custom field blocks (so wpf_<meta_key>=<min>-<max> gets a segment).
	 */
	public function metaKeys() {
		$keys = array();
		$rows = $this->filterRows();
		foreach ( $rows as $row ) {
			foreach ( $row['order'] as $block ) {
				if ( isset( $block['id'] ) && in_array( $block['id'], array( 'wpfCustomField', 'wpfSearchNumber' ), true ) && ! empty( $block['settings']['f_mlist'] ) ) {
					$keys[] = sanitize_key( $block['settings']['f_mlist'] );
				}
			}
		}
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/** Canonical key => taxonomy for term based keys. */
	public function taxonomyForKey() {
		$map = array(
			'category' => 'product_cat',
			'tag'      => 'product_tag',
		);
		if ( taxonomy_exists( 'product_brand' ) ) {
			$map['brand'] = 'product_brand';
		} elseif ( taxonomy_exists( 'pwb-brand' ) ) {
			$map['brand'] = 'pwb-brand';
		}
		foreach ( $this->attributeSlugs() as $slug ) {
			if ( ! isset( $map[ $slug ] ) && taxonomy_exists( 'pa_' . $slug ) ) {
				$map[ $slug ] = 'pa_' . $slug;
			}
		}
		return $map;
	}

	/** Every filter row with its decoded block order (cached per request). */
	public function filterRows() {
		static $rows = null;
		if ( null !== $rows ) {
			return $rows;
		}
		$rows  = array();
		$wf    = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		$model = $wf ? $wf->getModel( 'woofilters' ) : null;
		if ( ! $model ) {
			return $rows;
		}
		$list = $model->setSelectFields( 'id, setting_data' )->getFromTbl();
		if ( ! function_exists( 'woobewoo_pf_decode_settings' ) ) {
			// First request right after a file update: the opcode cache can still hold the old main file, which did not load functions.php.
			require_once WPF_DIR . 'functions.php';
		}
		foreach ( (array) $list as $row ) {
			$settings = woobewoo_pf_decode_settings( $row['setting_data'] );
			$order    = array();
			if ( ! empty( $settings['settings']['filters']['order'] ) ) {
				$order = WooBeWoo_PF_Utils::jsonDecode( $settings['settings']['filters']['order'] );
			}
			$rows[] = array(
				'id'       => (int) $row['id'],
				'settings' => isset( $settings['settings'] ) ? $settings['settings'] : array(),
				'order'    => is_array( $order ) ? $order : array(),
			);
		}
		return $rows;
	}

	/* ------------------------------------------------------------------ rewrite rules */

	public function queryVars( $vars ) {
		$vars[] = self::QV;
		$vars[] = self::QV_SHORT;
		return $vars;
	}

	/**
	 * Archive bases: array of array( regex base, query string prefix ).
	 */
	public function bases() {
		$bases = array();
		$shop  = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop > 0 ) {
			$uri = get_page_uri( $shop );
			if ( $uri ) {
				$bases['shop'] = array( preg_quote( $uri, '#' ), 'post_type=product' );
			}
		}
		$perm = function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure() : array();
		if ( ! empty( $perm['category_rewrite_slug'] ) ) {
			$bases['product_cat'] = array( preg_quote( trim( $perm['category_rewrite_slug'], '/' ), '#' ) . '/(.+?)', 'product_cat=$matches[1]' );
		}
		if ( ! empty( $perm['tag_rewrite_slug'] ) ) {
			$bases['product_tag'] = array( preg_quote( trim( $perm['tag_rewrite_slug'], '/' ), '#' ) . '/(.+?)', 'product_tag=$matches[1]' );
		}
		foreach ( array( 'product_brand', 'pwb-brand' ) as $tax ) {
			$obj = get_taxonomy( $tax );
			if ( $obj && ! empty( $obj->rewrite['slug'] ) ) {
				$bases[ $tax ] = array( preg_quote( trim( $obj->rewrite['slug'], '/' ), '#' ) . '/(.+?)', $tax . '=$matches[1]' );
			}
		}
		return apply_filters( 'woobewoo_pf_link_bases', $bases );
	}

	/**
	 * 4.0.1: pretty URL rules are only registered when something can produce them: clean links on, or a filter on
	 * the 4.0 engine. A store updated from 3.x with classic filters only keeps WordPress' own rules, so a product
	 * under a "/shop/" product base (/shop/sale-hoodie/) or a sub-category (/product-category/clothing/size-guide/)
	 * is never shadowed by a filter rule.
	 */
	public function rulesWanted() {
		if ( $this->cleanLinksOn() ) {
			return true;
		}
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return false;
		}
		foreach ( $this->filterRows() as $row ) {
			if ( WooBeWoo_PF_Frontend4::is_engine4( $row['id'], $row['settings'] ) ) {
				return true;
			}
		}
		return false;
	}

	public function registerRewrites() {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}
		$keys   = array_values( array_unique( $this->segments() ) );
		$wanted = ! empty( $keys ) && $this->rulesWanted();
		if ( ! $wanted ) {
			// Flush once when the rules were registered before (a previous version, or clean links switched off).
			if ( '' !== (string) get_option( self::HASH_OPT, '' ) ) {
				delete_option( self::HASH_OPT );
				update_option( self::FLUSH_OPT, '1', false );
			}
			return;
		}
		$alt = implode( '|', array_map( function ( $k ) {
			return preg_quote( $k, '#' );
		}, $keys ) );
		$one = '(?:' . $alt . ')-[^/]+';
		$seg = '(' . $one . '(?:/' . $one . ')*)';
		$bases = $this->bases();
		if ( isset( $bases['shop'] ) ) {
			add_rewrite_rule( '^' . $bases['shop'][0] . '/f/([A-Za-z0-9_-]+)/?$', 'index.php?' . self::QV_SHORT . '=$matches[1]', 'top' );
		}
		foreach ( $bases as $name => $b ) {
			$n = 'shop' === $name ? 1 : 2;
			add_rewrite_rule( '^' . $b[0] . '/' . $seg . '/?$', 'index.php?' . $b[1] . '&' . self::QV . '=$matches[' . $n . ']', 'top' );
		}
		$hash = md5( wp_json_encode( array( $keys, array_keys( $bases ), WPF_VERSION ) ) );
		if ( get_option( self::HASH_OPT ) !== $hash ) {
			update_option( self::HASH_OPT, $hash, false );
			update_option( self::FLUSH_OPT, '1', false );
		}
	}

	public function maybeFlush() {
		if ( '1' === (string) get_option( self::FLUSH_OPT ) ) {
			delete_option( self::FLUSH_OPT );
			flush_rewrite_rules( false );
		}
	}

	/* ------------------------------------------------------------------ parsing */

	/**
	 * Segments => canonical params.
	 */
	public function parseSegments( $segments ) {
		$map    = array_flip( $this->segments() ); // segment word => canonical key.
		$params = array();
		foreach ( (array) $segments as $seg ) {
			$seg = rawurldecode( $seg );
			if ( '' === $seg ) {
				continue;
			}
			// The key is the longest known segment word that prefixes "<word>-".
			$key = '';
			foreach ( $map as $word => $canonical ) {
				if ( 0 === strpos( $seg, $word . '-' ) && strlen( $word ) > strlen( $key ) ) {
					$key = $word;
				}
			}
			if ( '' === $key ) {
				continue;
			}
			$canonical = $map[ $key ];
			$value     = substr( $seg, strlen( $key ) + 1 );
			if ( '' === $value ) {
				continue;
			}
			switch ( $canonical ) {
				case 'price':
					if ( preg_match( '/^(\d+(?:\.\d+)?)?-(\d+(?:\.\d+)?)?$/', $value ) ) {
						$params['price'] = $value;
					}
					break;
				case 'stock':
					$vals = array_intersect( explode( ',', $value ), array( 'in', 'out', 'backorder' ) );
					if ( $vals ) {
						$params['stock'] = implode( ',', $vals );
					}
					break;
				case 'sale':
				case 'featured':
					$params[ $canonical ] = '1';
					break;
				case 'rating':
					$params['rating'] = (string) max( 1, min( 5, (int) $value ) );
					break;
				case 'page':
					$params['page'] = (string) max( 1, (int) $value );
					break;
				case 'sort':
					$params['sort'] = sanitize_key( $value );
					break;
				case 'q':
					$params['q'] = sanitize_text_field( str_replace( '+', ' ', $value ) );
					break;
				default:
					if ( in_array( $canonical, $this->metaKeys(), true ) ) {
						if ( preg_match( '/^(-?\d+(?:\.\d+)?)?-(-?\d+(?:\.\d+)?)?$/', $value ) ) {
							$params[ $canonical ] = $value;
						}
					} else {
						$vals = array_values( array_filter( array_map( 'sanitize_title', explode( ',', $value ) ), 'strlen' ) );
						if ( $vals ) {
							$params[ $canonical ] = $vals;
						}
					}
			}
		}
		return $params;
	}

	/**
	 * parse_request: map the pretty segments to the canonical query vars (and legacy ones when
	 * the 4.0 engine is not loaded).
	 */
	public function parseRequest( $wp ) {
		if ( is_admin() || ! ( $wp instanceof WP ) || empty( $wp->query_vars[ self::QV ] ) ) {
			return;
		}
		$raw = (string) $wp->query_vars[ self::QV ];
		// 4.0.1: a real product, page or term at this address wins over a filter reading of the same path.
		if ( $this->handOverToContent( $wp, $raw ) ) {
			return;
		}
		$params = $this->parseSegments( explode( '/', trim( $raw, '/' ) ) );
		if ( empty( $params ) ) {
			// Our rule matched but the segments mean nothing (/shop/stock-pot/ with no such product): not the archive.
			$wp->query_vars = array( 'error' => '404' );
			return;
		}
		$this->active = true;
		$this->params = $params;
		if ( ! empty( $params['page'] ) ) {
			$wp->query_vars['paged'] = (int) $params['page'];
		}
		foreach ( $this->toQueryVars( $params ) as $k => $v ) {
			$_GET[ $k ]           = $v; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_REQUEST[ $k ]       = $v;
			$wp->query_vars[ $k ] = $v;
		}
		// The 3.x query path reads its own names; the 4.0 engine reads both, so set both.
		foreach ( $this->legacyQueryVars( $params ) as $k => $v ) {
			$_GET[ $k ]     = $v; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$_REQUEST[ $k ] = $v;
		}
		if ( ! empty( $params['q'] ) ) {
			$wp->query_vars['s'] = $params['q'];
		}
	}

	/**
	 * When the matched path is also a published product (product base equals the shop base, e.g. /shop/sale-hoodie/)
	 * or an existing child term (/product-category/clothing/size-guide/), rewrite the query vars to what WordPress'
	 * own rule would have produced and skip the filter.
	 *
	 * @since 4.0.1
	 */
	protected function handOverToContent( $wp, $raw ) {
		$segments = explode( '/', trim( $raw, '/' ) );
		$last     = end( $segments );
		$qv       = $wp->query_vars;
		$taxonomy = '';
		foreach ( array( 'product_cat', 'product_tag', 'product_brand', 'pwb-brand' ) as $tax ) {
			if ( ! empty( $qv[ $tax ] ) ) {
				$taxonomy = $tax;
				break;
			}
		}
		if ( $taxonomy ) {
			// /<base>/<parent...>/<segment>/ : is the whole path an existing term chain?
			$path = trim( (string) $qv[ $taxonomy ], '/' ) . '/' . implode( '/', $segments );
			$term = get_term_by( 'slug', $last, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$chain = array_reverse( array_map( function ( $t ) {
					return $t->slug;
				}, array_merge( array( $term ), is_taxonomy_hierarchical( $taxonomy ) ? array_map( 'get_term', get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ) : array() ) ) );
				if ( implode( '/', $chain ) === $path || $term->slug === $path ) {
					$wp->query_vars = array( $taxonomy => $path );
					unset( $wp->query_vars[ self::QV ] );
					return true;
				}
			}
			return false;
		}
		if ( ! empty( $qv['post_type'] ) && 'product' === $qv['post_type'] && 1 === count( $segments ) ) {
			$perm = function_exists( 'wc_get_permalink_structure' ) ? wc_get_permalink_structure() : array();
			$base = isset( $perm['product_rewrite_slug'] ) ? trim( (string) $perm['product_rewrite_slug'], '/' ) : '';
			$shop = function_exists( 'wc_get_page_id' ) ? get_page_uri( (int) wc_get_page_id( 'shop' ) ) : '';
			if ( $base && $shop && trim( (string) $shop, '/' ) === $base ) {
				$found = get_page_by_path( $last, OBJECT, 'product' );
				if ( $found && 'publish' === $found->post_status ) {
					$wp->query_vars = array( 'product' => $last, 'post_type' => 'product', 'name' => $last );
					return true;
				}
			}
			// A child page of the shop page (/shop/size-guide/) also wins.
			$page = get_page_by_path( trim( (string) $shop, '/' ) . '/' . $last );
			if ( $page && 'publish' === $page->post_status ) {
				$wp->query_vars = array( 'pagename' => trim( (string) $shop, '/' ) . '/' . $last );
				return true;
			}
		}
		return false;
	}

	/**
	 * Canonical params => canonical query vars (wpf_<key> => string).
	 */
	public function toQueryVars( $params ) {
		$out = array();
		foreach ( (array) $params as $k => $v ) {
			$out[ 'wpf_' . sanitize_key( $k ) ] = is_array( $v ) ? implode( ',', $v ) : (string) $v;
		}
		return $out;
	}

	/**
	 * Canonical params => the query vars the 3.x engine reads (adapter used until
	 * WooBeWoo_PF_Query exists). Attribute and tag values are slugs, categories are ids.
	 */
	public function legacyQueryVars( $params ) {
		$out  = array();
		$keys = $this->legacyBlockKeys();
		$tax  = $this->taxonomyForKey();
		foreach ( (array) $params as $k => $v ) {
			$vals = is_array( $v ) ? $v : array( $v );
			switch ( $k ) {
				case 'category':
					$ids = array();
					foreach ( $vals as $slug ) {
						$t = get_term_by( 'slug', $slug, 'product_cat' );
						if ( $t ) {
							$ids[] = (int) $t->term_id;
						}
					}
					if ( $ids ) {
						$out[ $keys['category'] ] = implode( '|', $ids );
						set_query_var( 'product_category_id', $ids[0] );
					}
					break;
				case 'tag':
					$out[ $keys['tag'] ] = implode( '|', $vals );
					break;
				case 'brand':
					$out[ $keys['brand'] ] = implode( ',', $vals );
					break;
				case 'author':
					$out['pr_author'] = implode( '|', $vals );
					break;
				case 'price':
					list( $min, $max ) = array_pad( explode( '-', (string) $v, 2 ), 2, '' );
					if ( '' !== $min ) {
						$out['wpf_min_price'] = $min;
					}
					if ( '' !== $max ) {
						$out['wpf_max_price'] = $max;
					}
					break;
				case 'stock':
					$m = array( 'in' => 'instock', 'out' => 'outofstock', 'backorder' => 'onbackorder' );
					$s = array();
					foreach ( explode( ',', (string) $v ) as $x ) {
						if ( isset( $m[ $x ] ) ) {
							$s[] = $m[ $x ];
						}
					}
					if ( $s ) {
						$out['pr_stock'] = implode( '|', $s );
					}
					break;
				case 'sale':
					$out['pr_onsale'] = '1';
					break;
				case 'featured':
					$out['pr_featured'] = '1';
					break;
				case 'rating':
					$out['pr_rating'] = (int) $v . '-5';
					break;
				case 'sort':
					$out['orderby'] = sanitize_key( $v );
					break;
				case 'page':
				case 'q':
					break;
				default:
					if ( isset( $tax[ $k ] ) ) {
						$name         = isset( $keys[ $k ] ) ? $keys[ $k ] : 'wpf_filter_' . $k;
						$out[ $name ] = implode( '|', $vals );
					}
			}
		}
		return $out;
	}

	/**
	 * Legacy parameter names per canonical key, taken from the saved filters (block index and
	 * display type decide the suffix), with defaults when no filter has the block.
	 */
	public function legacyBlockKeys() {
		static $keys = null;
		if ( null !== $keys ) {
			return $keys;
		}
		$keys = array(
			'category' => 'wpf_filter_cat_0',
			'tag'      => 'product_tag',
			'brand'    => 'wpf_filter_brand',
		);
		$view = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' ) ? WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView() : null;
		foreach ( $this->filterRows() as $row ) {
			$names = array();
			foreach ( $row['order'] as $block ) {
				if ( ! empty( $block['name'] ) ) {
					$names[] = $block['name'];
				}
			}
			$dupes = array_keys( array_filter( array_count_values( $names ), function ( $n ) {
				return $n > 1;
			} ) );
			foreach ( $row['order'] as $idx => $block ) {
				$id = isset( $block['id'] ) ? $block['id'] : '';
				$s  = isset( $block['settings'] ) ? $block['settings'] : array();
				if ( 'wpfCategory' === $id && 'wpf_filter_cat_0' === $keys['category'] ) {
					$type     = ! empty( $s['f_frontend_type'] ) ? $s['f_frontend_type'] : 'list';
					$children = $view && method_exists( $view, 'findTaxonomyIncludeChildrenStatus' ) ? $view->findTaxonomyIncludeChildrenStatus( ! empty( $s['f_hide_taxonomy'] ), ! empty( $s['f_extend_parent_filtering'] ), $type ) : true;
					$keys['category'] = 'wpf_filter_cat' . ( $children ? '' : '_list' ) . '_' . $idx;
				} elseif ( 'wpfTags' === $id && 'product_tag' === $keys['tag'] ) {
					$keys['tag'] = 'product_tag_' . $idx;
				} elseif ( 'wpfBrand' === $id && 'wpf_filter_brand' === $keys['brand'] ) {
					$keys['brand'] = ( ! empty( $s['f_frontend_type'] ) && 'multi' === $s['f_frontend_type'] ) ? 'product_brand_list' : 'wpf_filter_brand';
				} elseif ( 'wpfAttribute' === $id && ! empty( $s['f_list'] ) && function_exists( 'wc_attribute_taxonomy_name_by_id' ) ) {
					$tax  = wc_attribute_taxonomy_name_by_id( (int) $s['f_list'] );
					$slug = str_replace( 'pa_', '', (string) $tax );
					if ( $slug && ! isset( $keys[ $slug ] ) ) {
						$suffix        = ( ! empty( $block['name'] ) && in_array( $block['name'], $dupes, true ) ) ? '_' . $idx : '';
						$keys[ $slug ] = 'wpf_filter_' . $slug . $suffix;
					}
				}
			}
		}
		return $keys;
	}

	/* ------------------------------------------------------------------ reverse: request => canonical */

	/**
	 * Canonical params from a query array (legacy or canonical keys). Uses the engine when present.
	 */
	public function canonicalFromQuery( $get ) {
		if ( class_exists( 'WooBeWoo_PF_Query' ) && method_exists( 'WooBeWoo_PF_Query', 'from_params' ) ) {
			try {
				$q = WooBeWoo_PF_Query::from_params( (array) $get );
				if ( $q && method_exists( $q, 'params' ) ) {
					$p = $q->params();
					if ( is_array( $p ) ) {
						return $this->fromEngineParams( $p );
					}
				}
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Wrong signature or an engine error: fall through to the adapter so the shop never fatals.
			}
		}
		return $this->cleanCanonical( $this->legacyToCanonical( $get ) );
	}

	/**
	 * Engine canonical query vars (wpf_size => "42,43") => this module's params (size => array).
	 */
	public function fromEngineParams( $vars ) {
		$out    = array();
		$scalar = array( 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' );
		$meta   = $this->metaKeys();
		foreach ( (array) $vars as $k => $v ) {
			if ( 0 !== strpos( $k, 'wpf_' ) ) {
				continue;
			}
			$k = substr( $k, 4 );
			if ( in_array( $k, $scalar, true ) || in_array( $k, $meta, true ) ) {
				$out[ $k ] = (string) $v;
			} else {
				$out[ $k ] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ), 'strlen' ) );
			}
		}
		return $this->cleanCanonical( $out );
	}

	/**
	 * `woobewoo_pf_filter_url`: the engine builds query string URLs; with Clean links on they become pretty paths.
	 */
	public function filterUrl( $url, $params, $base, $query = null ) {
		if ( ! $this->cleanLinksOn() || empty( $params ) ) {
			return $url;
		}
		$canonical = $this->fromEngineParams( $params );
		if ( empty( $canonical ) ) {
			return $url;
		}
		$path = (string) wp_parse_url( $base, PHP_URL_PATH );
		// Only archives get pretty paths (the shop page and product taxonomies); other pages keep the query string.
		$archive = false;
		foreach ( $this->bases() as $b ) {
			if ( preg_match( '#^/' . $b[0] . '/?#', $path ) ) {
				$archive = true;
				break;
			}
		}
		if ( ! $archive ) {
			return $url;
		}
		return $this->prettyUrl( $base, $canonical );
	}

	private function cleanCanonical( $p ) {
		$out = array();
		foreach ( (array) $p as $k => $v ) {
			$k = sanitize_key( $k );
			if ( '' === $k || null === $v || '' === $v || array() === $v ) {
				continue;
			}
			$out[ $k ] = is_array( $v ) ? array_values( array_map( 'strval', $v ) ) : (string) $v;
		}
		return $out;
	}

	private function termSlugs( $taxonomy, $values ) {
		$out = array();
		foreach ( (array) $values as $v ) {
			$v = trim( (string) $v );
			if ( '' === $v ) {
				continue;
			}
			if ( is_numeric( $v ) ) {
				$t = get_term( (int) $v, $taxonomy );
				if ( $t && ! is_wp_error( $t ) ) {
					$out[] = $t->slug;
				}
			} else {
				$out[] = sanitize_title( $v );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * 3.x query string (wpf_filter_cat_0, wpf_filter_size, product_tag, pr_stock...) => canonical.
	 */
	public function legacyToCanonical( $get ) {
		$params   = array();
		$attrs    = $this->attributeSlugs();
		$brandTax = taxonomy_exists( 'product_brand' ) ? 'product_brand' : ( taxonomy_exists( 'pwb-brand' ) ? 'pwb-brand' : '' );
		$ignore   = array( 'wpf_count', 'wpf_fbv', 'wpf_dpv', 'wpf_skip', 'wpf_fid', 'wpf_id', 'wpf_ebv', 'wpf_oistock', 'wpf_reload', 'wpf_preselects', 'wpf_filter_tax_block_logic', 'wpf_preview' );
		foreach ( (array) $get as $key => $value ) {
			if ( in_array( $key, $ignore, true ) || is_array( $value ) ) {
				continue;
			}
			$value = (string) $value;
			if ( '' === $value ) {
				continue;
			}
			$list = preg_split( '/[|,]/', ltrim( $value, '!' ) );
			if ( 0 === strpos( $key, 'wpf_filter_cat' ) || 'filter_cat' === $key ) {
				$params['category'] = $this->termSlugs( 'product_cat', $list );
			} elseif ( 0 === strpos( $key, 'product_tag' ) ) {
				$params['tag'] = $this->termSlugs( 'product_tag', $list );
			} elseif ( $brandTax && ( 0 === strpos( $key, 'product_brand' ) || 0 === strpos( $key, 'wpf_filter_brand' ) || 0 === strpos( $key, 'wpf_filter_pwb' ) ) ) {
				$params['brand'] = $this->termSlugs( $brandTax, $list );
			} elseif ( 'wpf_min_price' === $key || 'wpf_max_price' === $key ) {
				$cur = isset( $params['price'] ) ? explode( '-', $params['price'] ) : array( '', '' );
				if ( 'wpf_min_price' === $key ) {
					$cur[0] = $value;
				} else {
					$cur[1] = $value;
				}
				$params['price'] = $cur[0] . '-' . $cur[1];
			} elseif ( 'pr_stock' === $key ) {
				$m = array( 'instock' => 'in', 'outofstock' => 'out', 'onbackorder' => 'backorder' );
				$s = array();
				foreach ( $list as $x ) {
					if ( isset( $m[ $x ] ) ) {
						$s[] = $m[ $x ];
					}
				}
				if ( $s ) {
					$params['stock'] = implode( ',', $s );
				}
			} elseif ( 'pr_onsale' === $key ) {
				$params['sale'] = '1';
			} elseif ( 'pr_featured' === $key ) {
				$params['featured'] = '1';
			} elseif ( 'pr_rating' === $key ) {
				$params['rating'] = (string) (int) $value;
			} elseif ( 'pr_author' === $key ) {
				$params['author'] = $list;
			} elseif ( 'orderby' === $key || 'wpf_sort' === $key ) {
				$params['sort'] = sanitize_key( $value );
			} elseif ( 'product-page' === $key || 'paged' === $key || 'wpf_page' === $key ) {
				if ( (int) $value > 1 ) {
					$params['page'] = (string) (int) $value;
				}
			} elseif ( 's' === $key || 'wpf_q' === $key || 'wpf_filter_text' === $key ) {
				$params['q'] = sanitize_text_field( $value );
			} elseif ( 0 === strpos( $key, 'wpf_filter_' ) || 0 === strpos( $key, 'pr_wpf_filter_' ) ) {
				$slug = preg_replace( '/_\d+$/', '', substr( $key, strpos( $key, 'wpf_filter_' ) + 11 ) );
				if ( in_array( $slug, $attrs, true ) ) {
					$params[ $slug ] = $this->termSlugs( 'pa_' . $slug, $list );
				}
			} elseif ( 0 === strpos( $key, 'wpf_' ) ) {
				// Canonical passthrough: wpf_size=42,43 / wpf_price=50-120 / wpf_<meta>=min-max.
				$k = substr( $key, 4 );
				if ( in_array( $k, array( 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' ), true ) || in_array( $k, $this->metaKeys(), true ) ) {
					$params[ $k ] = $value;
				} elseif ( in_array( $k, $attrs, true ) || in_array( $k, array( 'category', 'tag', 'brand', 'author' ), true ) ) {
					$params[ $k ] = array_values( array_filter( array_map( 'sanitize_title', $list ) ) );
				}
			}
		}
		return $params;
	}

	/* ------------------------------------------------------------------ building */

	/**
	 * Pretty URL for an archive base and canonical params.
	 */
	public function prettyUrl( $base, $params ) {
		$segments = $this->segments();
		$params   = $this->cleanCanonical( $params );
		$fixed    = array( 'category', 'tag', 'brand', 'author', 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' );
		$ordered  = array();
		foreach ( $params as $k => $v ) {
			if ( ! in_array( $k, $fixed, true ) ) {
				$ordered[ $k ] = $v;
			}
		}
		foreach ( $fixed as $k ) {
			if ( isset( $params[ $k ] ) ) {
				$ordered[ $k ] = $params[ $k ];
			}
		}
		$parts = array();
		$extra = array();
		foreach ( $ordered as $k => $v ) {
			if ( ! isset( $segments[ $k ] ) ) {
				// No pretty segment for this key (a custom field text box, a second search box, a field the
				// segments do not know): keep it in the query string instead of dropping the pick from the link.
				$extra[ 'wpf_' . $k ] = is_array( $v ) ? implode( ',', $v ) : (string) $v;
				continue;
			}
			if ( is_array( $v ) ) {
				$v = implode( ',', array_map( 'rawurlencode', $v ) );
			} elseif ( 'q' === $k ) {
				$v = rawurlencode( str_replace( ' ', '+', $v ) );
			} else {
				$v = rawurlencode( (string) $v );
			}
			if ( '' === $v ) {
				continue;
			}
			$parts[] = $segments[ $k ] . '-' . $v;
		}
		$base = (string) $base;
		if ( '' === $base ) {
			$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		}
		$query = '';
		if ( false !== strpos( $base, '?' ) ) {
			list( $base, $query ) = explode( '?', $base, 2 );
		}
		$url = trailingslashit( $base );
		if ( $parts ) {
			$url .= implode( '/', $parts ) . '/';
		}
		if ( '' !== $query ) {
			$url .= '?' . $query;
		}
		if ( $extra ) {
			$url = str_replace( '%2C', ',', add_query_arg( array_map( 'rawurlencode', $extra ), $url ) );
		}
		return apply_filters( 'woobewoo_pf_pretty_url', $url, $base, $params );
	}

	/**
	 * Archive base URL for the current main query (shop, category, tag, brand, attribute).
	 */
	public function currentBase() {
		if ( $this->base ) {
			return $this->base;
		}
		$obj = get_queried_object();
		if ( $obj instanceof WP_Term ) {
			$link = get_term_link( $obj );
			return is_wp_error( $link ) ? '' : $link;
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			return wc_get_page_permalink( 'shop' );
		}
		if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' ) ) {
			return get_post_type_archive_link( 'product' );
		}
		return '';
	}

	/* ------------------------------------------------------------------ redirects */

	public function isArchive() {
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	}

	/**
	 * 301 from legacy / canonical query strings to the pretty form when Clean links is on.
	 */
	public function redirectLegacy() {
		if ( $this->active || ! $this->cleanLinksOn() || is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( ! $this->isArchive() ) {
			return;
		}
		$get = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $get ) || isset( $get['wpf_skip'] ) || isset( $get['wpf_preview'] ) || isset( $get['wpf_fid'] ) || isset( $get['wpf_reload'] ) || isset( $get['add-to-cart'] ) ) {
			return;
		}
		$wf = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		if ( ! $wf || ! $wf->existsWpfParams( $get ) ) {
			return;
		}
		// Links written by a classic (3.x engine) filter keep their query string while classic filters exist: the
		// classic engine answers them as before (hidden preselects, counts, language), a pretty URL would not.
		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) && WooBeWoo_PF_Frontend4::has_legacy_filters() && ! WooBeWoo_PF_Frontend4::request_has_engine4_params() ) {
			return;
		}
		$params = $this->canonicalFromQuery( $get );
		if ( empty( $params ) ) {
			return;
		}
		$base = $this->currentBase();
		if ( ! $base ) {
			return;
		}
		// Keep foreign query args (utm_*, gclid...) on the pretty URL.
		$keep = array();
		foreach ( $get as $k => $v ) {
			if ( is_scalar( $v ) && ! preg_match( '/^(wpf_|pr_|product_tag|product_brand|filter_cat|orderby$|paged$|product-page$|s$|_$)/', $k ) ) {
				$keep[ $k ] = $v;
			}
		}
		$url = $this->prettyUrl( $base, $params );
		if ( $keep ) {
			$url = add_query_arg( array_map( 'rawurlencode', $keep ), $url );
		}
		$current = home_url( add_query_arg( array() ) );
		if ( untrailingslashit( $url ) === untrailingslashit( $current ) ) {
			return;
		}
		wp_safe_redirect( $url, 301, 'Product Filter clean links' );
		exit;
	}

	/**
	 * /shop/f/<slug> => 302 to the pretty URL.
	 */
	public function resolveShort() {
		$slug = get_query_var( self::QV_SHORT );
		if ( ! $slug ) {
			return;
		}
		$row = $this->getModel()->get( $slug );
		if ( ! $row ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}
		$this->getModel()->hit( $slug );
		$url = $this->prettyUrl( $row['base'], $row['params'] );
		if ( ! $this->cleanLinksOn() ) {
			// Pretty rules still resolve, but keep the address in the form the site uses.
			$url = add_query_arg( array_map( 'rawurlencode', $this->toQueryVars( $row['params'] ) ), $row['base'] ? $row['base'] : wc_get_page_permalink( 'shop' ) );
		}
		wp_safe_redirect( $url, 302, 'Product Filter short link' );
		exit;
	}

	/**
	 * On pretty URLs the canonical and the unfiltered link point to the archive base, not to
	 * the filtered path. Speed and SEO prints the canonical; we only fix the URL it uses.
	 */
	public function canonicalFix() {
		if ( ! $this->active ) {
			return;
		}
		$this->base = $this->currentBase();
		$speed      = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'printCanonical' ) && $this->canonicalOn() ) {
			remove_action( 'wp_head', array( $speed, 'printCanonical' ), 1 );
			remove_filter( 'wpseo_canonical', array( $speed, 'unfilteredUrl' ) );
			remove_filter( 'rank_math/frontend/canonical', array( $speed, 'unfilteredUrl' ) );
			remove_filter( 'aioseo_canonical_url', array( $speed, 'unfilteredUrl' ) );
			remove_filter( 'get_canonical_url', array( $speed, 'unfilteredUrl' ) );
			add_action( 'wp_head', array( $this, 'printCanonical' ), 1 );
			add_filter( 'wpseo_canonical', array( $this, 'baseUrl' ) );
			add_filter( 'rank_math/frontend/canonical', array( $this, 'baseUrl' ) );
			add_filter( 'aioseo_canonical_url', array( $this, 'baseUrl' ) );
			add_filter( 'get_canonical_url', array( $this, 'baseUrl' ) );
		} elseif ( ! $speed && $this->canonicalOn() ) {
			// No Speed and SEO module: same option key, own canonical and noindex.
			remove_action( 'wp_head', 'rel_canonical' );
			add_action( 'wp_head', array( $this, 'printCanonical' ), 1 );
			add_filter( 'wp_robots', array( $this, 'noindexFollow' ), 30 );
		}
	}

	public function baseUrl( $url = '' ) {
		return $this->base ? $this->base : $url;
	}

	public function printCanonical() {
		echo '<link rel="canonical" href="' . esc_url( $this->baseUrl() ) . '" />' . "\n";
	}

	public function noindexFollow( $robots ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'] );
		return $robots;
	}

	/* ------------------------------------------------------------------ storefront: share button */

	public function assets() {
		$wf = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		if ( ! $wf || ( ! $this->isArchive() && ! $wf->existsWpfParams() && ! is_singular() ) ) {
			return;
		}
		if ( ! $this->linksScriptWanted() ) {
			return;
		}
		$path = $this->getModPath();
		wp_register_style( 'woobewoo-pf-links', $path . 'css/frontend.links.css', array(), WPF_VERSION );
		wp_register_script( 'woobewoo-pf-links', $path . 'js/frontend.links.js', array(), WPF_VERSION, true );
		wp_localize_script(
			'woobewoo-pf-links',
			'wpfLinks',
			array(
				'rest'       => esc_url_raw( rest_url( 'wpf/v1/short-links' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'clean'      => $this->cleanLinksOn() ? 1 : 0,
				'base'       => $this->currentBase(),
				'segments'   => $this->segments(),
				'attributes' => $this->attributeSlugs(),
				'metaKeys'   => $this->metaKeys(),
				'blockKeys'  => class_exists( 'WooBeWoo_PF_Query' ) ? array() : $this->legacyBlockKeys(),
				'i18n'       => array(
					'copied'  => __( 'Link copied', 'woo-product-filter' ),
					'failed'  => __( 'Could not create the link. Try again.', 'woo-product-filter' ),
					'working' => __( 'Making your link', 'woo-product-filter' ),
					'qr'      => __( 'QR code for this selection', 'woo-product-filter' ),
				),
			)
		);
		wp_enqueue_style( 'woobewoo-pf-links' );
		wp_enqueue_script( 'woobewoo-pf-links' );
	}

	/**
	 * Share button markup (filter woobewoo_pf_after_filter_html, 4.0 engine).
	 */
	public function shareButton( $html, $settings = array(), $viewId = '' ) {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'shortLinksOn' ) && ! $speed->shortLinksOn() ) {
			return $html; // Speed and SEO, "Short links to share" off.
		}
		$filterId = (int) explode( '_', (string) $viewId )[0];
		if ( isset( $this->shareRendered[ $viewId ] ) ) {
			return $html;
		}
		$this->shareRendered[ $viewId ] = true;
		$s = isset( $settings['settings'] ) && is_array( $settings['settings'] ) ? $settings['settings'] : (array) $settings;
		if ( isset( $s['wbw_share_button'] ) && '0' === (string) $s['wbw_share_button'] ) {
			return $html;
		}
		$id   = 'wpf-share-' . sanitize_html_class( (string) $viewId );
		$icon = '<svg class="wpf-share__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>';
		$out  = '<div class="wpf-share" id="' . esc_attr( $id ) . '" data-filter="' . $filterId . '">';
		$out .= '<button type="button" class="wpf-share__btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '-pop">' . $icon . '<span>' . esc_html__( 'Share', 'woo-product-filter' ) . '</span></button>';
		$out .= '<div class="wpf-share__pop" id="' . esc_attr( $id ) . '-pop" role="dialog" aria-label="' . esc_attr__( 'Share this selection', 'woo-product-filter' ) . '" hidden>';
		$out .= '<label class="wpf-share__label" for="' . esc_attr( $id ) . '-url">' . esc_html__( 'Short link', 'woo-product-filter' ) . '</label>';
		$out .= '<div class="wpf-share__row"><input type="text" readonly class="wpf-share__url" id="' . esc_attr( $id ) . '-url" value="">';
		$out .= '<button type="button" class="wpf-share__copy">' . esc_html__( 'Copy', 'woo-product-filter' ) . '</button></div>';
		$out .= '<div class="wpf-share__qr" aria-hidden="true"></div>';
		$out .= '<span class="wpf-share__live" aria-live="polite"></span>';
		$out .= '<button type="button" class="wpf-share__close" aria-label="' . esc_attr__( 'Close', 'woo-product-filter' ) . '">&times;</button>';
		$out .= '</div></div>';
		return $html . $out;
	}

	public function shareButton4( $html, $filter = null, $query = null ) {
		if ( ! is_array( $filter ) || empty( $filter['id'] ) || ! empty( $filter['draft'] ) ) {
			return $html;
		}
		return $this->shareButton( $html, isset( $filter['settings'] ) ? $filter['settings'] : array(), (string) (int) $filter['id'] );
	}

	/**
	 * Same button through the 3.x Dispatcher hook (wpf_addHtmlAfterFilter), when the 4.0 hook did not fire.
	 */
	public function shareButtonLegacy( $html, $settings = array(), $filterId = 0 ) {
		// A filter on the classic (3.x) engine looks exactly as it did before the upgrade: no Share button unless
		// the owner switches it on for that filter.
		$s = isset( $settings['settings'] ) && is_array( $settings['settings'] ) ? $settings['settings'] : (array) $settings;
		if ( ! isset( $s['wbw_share_button'] ) || '1' !== (string) $s['wbw_share_button'] ) {
			return $html;
		}
		return $this->shareButton( $html, $settings, (string) $filterId );
	}

	/**
	 * Does any filter need the links script on the storefront (a Share button, or clean links)?
	 */
	protected function linksScriptWanted() {
		if ( $this->cleanLinksOn() || ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return true;
		}
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'shortLinksOn' ) && ! $speed->shortLinksOn() ) {
			return false;
		}
		foreach ( WooBeWoo_PF_Frontend4::filters() as $f ) {
			$s = $f['settings']['settings'];
			if ( WooBeWoo_PF_Frontend4::is_engine4( $f['id'], $f['settings'] ) ? ( ! isset( $s['wbw_share_button'] ) || '0' !== (string) $s['wbw_share_button'] ) : ( isset( $s['wbw_share_button'] ) && '1' === (string) $s['wbw_share_button'] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ------------------------------------------------------------------ REST */

	public function registerRest() {
		register_rest_route(
			'wpf/v1',
			'/short-links',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'restPermission' ),
				'args'                => array(
					'url'       => array( 'type' => 'string', 'required' => true, 'format' => 'uri' ),
					'filter_id' => array( 'type' => 'integer', 'default' => 0, 'minimum' => 0 ),
				),
				'callback'            => array( $this, 'restCreate' ),
			)
		);
		register_rest_route(
			'wpf/v1',
			'/short-links/(?P<slug>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'restPermission' ),
				'callback'            => array( $this, 'restGet' ),
			)
		);
		register_rest_route(
			'wpf/v1',
			'/short-links/(?P<slug>[a-z0-9_-]+)/qr',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'restPermission' ),
				'callback'            => array( $this, 'restQr' ),
			)
		);
	}

	/**
	 * Admins pass; everyone else gets 20 requests a minute per IP.
	 */
	public function restPermission() {
		return $this->rateLimit( false );
	}

	/**
	 * 20 requests a minute per IP. Permission callbacks only check (WordPress calls them more than
	 * once per request); the route callbacks count.
	 */
	public function rateLimit( $count = false ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'wpf_rl_links_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 20 ) {
			return new WP_Error( 'wpf_rate', __( 'Too many requests. Try again in a minute.', 'woo-product-filter' ), array( 'status' => 429 ) );
		}
		if ( $count ) {
			set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		}
		return true;
	}

	/**
	 * Base + canonical params from any URL of this site (pretty or query string).
	 */
	public function parseUrl( $url ) {
		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url( '/' ) );
		if ( empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) {
			return null;
		}
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
		$query  = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		$params = array();
		$base   = '';
		$known  = array_values( $this->segments() );
		$segs   = array_values( array_filter( explode( '/', trim( $path, '/' ) ) ) );
		$keep   = array();
		foreach ( $segs as $seg ) {
			$isPretty = false;
			foreach ( $known as $w ) {
				if ( 0 === strpos( $seg, $w . '-' ) ) {
					$isPretty = true;
					break;
				}
			}
			if ( $isPretty ) {
				$params = array_merge( $params, $this->parseSegments( array( $seg ) ) );
			} else {
				$keep[] = $seg;
			}
		}
		$base = home_url( '/' . ( $keep ? implode( '/', $keep ) . '/' : '' ) );
		if ( $query ) {
			$params = array_merge( $params, $this->canonicalFromQuery( $query ) );
		}
		return array( 'base' => $base, 'params' => $this->cleanCanonical( $params ) );
	}

	public function restCreate( $request ) {
		$ok = $this->rateLimit( true );
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$parsed = $this->parseUrl( (string) $request['url'] );
		if ( ! $parsed ) {
			return new WP_Error( 'wpf_bad_url', __( 'The link must point to this shop.', 'woo-product-filter' ), array( 'status' => 400 ) );
		}
		if ( empty( $parsed['params'] ) ) {
			return new WP_Error( 'wpf_no_filters', __( 'Pick at least one filter before sharing.', 'woo-product-filter' ), array( 'status' => 400 ) );
		}
		$row = $this->getModel()->create( (int) $request['filter_id'], $parsed['params'], $parsed['base'] );
		if ( ! $row ) {
			return new WP_Error( 'wpf_failed', __( 'Could not create the link.', 'woo-product-filter' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( $this->rowData( $row ) );
	}

	public function restGet( $request ) {
		$row = $this->getModel()->get( $request['slug'] );
		if ( ! $row ) {
			return new WP_Error( 'wpf_not_found', __( 'No such link.', 'woo-product-filter' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->rowData( $row ) );
	}

	public function restQr( $request ) {
		$row = $this->getModel()->get( $request['slug'] );
		if ( ! $row ) {
			return new WP_Error( 'wpf_not_found', __( 'No such link.', 'woo-product-filter' ), array( 'status' => 404 ) );
		}
		$svg = WooBeWoo_PF_QR::svg( $this->shortUrl( $row['slug'] ), 6 );
		header( 'Content-Type: image/svg+xml; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=86400' );
		echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG generated by WooBeWoo_PF_QR from a URL, nothing user supplied is echoed unescaped.
		exit;
	}

	public function shortUrl( $slug ) {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		if ( get_option( 'permalink_structure' ) && function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
			return trailingslashit( $shop ) . 'f/' . rawurlencode( $slug ) . '/';
		}
		return add_query_arg( self::QV_SHORT, rawurlencode( $slug ), home_url( '/' ) );
	}

	private function rowData( $row ) {
		$short = $this->shortUrl( $row['slug'] );
		return array(
			'slug'      => $row['slug'],
			'short_url' => $short,
			'url'       => $this->prettyUrl( $row['base'], $row['params'] ),
			'filter_id' => (int) $row['filter_id'],
			'params'    => $row['params'],
			'hits'      => (int) $row['hits'],
			'created'   => $row['created'],
			'qr'        => WooBeWoo_PF_QR::svg( $short, 4 ),
			'qr_url'    => rest_url( 'wpf/v1/short-links/' . $row['slug'] . '/qr' ),
		);
	}

	/* ------------------------------------------------------------------ self test */

	public function selfTestRows( $rows ) {
		$rules = get_option( 'rewrite_rules', array() );
		$found = 0;
		if ( is_array( $rules ) ) {
			foreach ( $rules as $regex => $target ) {
				if ( false !== strpos( $target, self::QV . '=' ) ) {
					$found++;
				}
			}
		}
		$rows[] = array(
			'state'  => $found ? 'pass' : 'partial',
			'label'  => __( 'Pretty filter links are registered', 'woo-product-filter' ),
			'detail' => $found ? sprintf( /* translators: %d: count */ __( '%d rewrite rules (shop, categories, tags, brands).', 'woo-product-filter' ), $found ) : __( 'Save Settings > Permalinks once to refresh the rules.', 'woo-product-filter' ),
		);
		return $rows;
	}
}

if ( ! function_exists( 'woobewoo_pf_pretty_url' ) ) {
	/**
	 * Pretty URL for an archive base and canonical params, e.g.
	 * woobewoo_pf_pretty_url( wc_get_page_permalink( 'shop' ), array( 'size' => array( '42', '43' ), 'price' => '50-120' ) )
	 * => https://example.com/shop/size-42,43/price-50-120/
	 *
	 * @param string $base   Archive URL (shop, category, tag or brand page).
	 * @param array  $params Canonical params: block_key => slug|slugs, price => "min-max", stock, sale, featured, rating, sort, page, q.
	 * @return string
	 */
	function woobewoo_pf_pretty_url( $base, $params ) {
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		if ( $links ) {
			return $links->prettyUrl( $base, $params );
		}
		return add_query_arg( $params, $base );
	}
}
