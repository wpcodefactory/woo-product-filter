<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Storeapi Class
 *
 * WooCommerce Store API integration:
 *  - /wc/store/v1/products honours the canonical wpf_* query parameters (and the 3.x names) through
 *    the engine's query class. WooCommerce exposes `woocommerce_store_api_product_query` on some
 *    versions; when it does not, the same arguments are applied on pre_get_posts for the Store API
 *    product query.
 *  - Extension data on the products endpoint (`woocommerce_store_api_register_endpoint_data`,
 *    namespace woobewoo) with the facets and counts for the current query, so block themes and the
 *    core Product Collection block can show live counts.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Storeapi extends WooBeWoo_PF_Module {

	const NS = 'woobewoo';

	/** Query params of the current Store API products request. */
	private $params = null;
	private $facetsCache = null;
	private $applied = false;

	public function init() {
		parent::init();
		add_filter( 'woocommerce_store_api_product_query', array( $this, 'productQuery' ), 20, 2 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'captureRequest' ), 10, 3 );
		add_action( 'pre_get_posts', array( $this, 'onPreGetPosts' ), 5 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'registerEndpointData' ) );
		add_action( 'rest_api_init', array( $this, 'collectionParams' ), 20 );
	}

	/* ------------------------------------------------------------------ request capture */

	/**
	 * Remember the wpf_* (and legacy) params of a Store API products request.
	 */
	public function captureRequest( $response, $handler, $request ) {
		if ( $request instanceof WP_REST_Request && 0 === strpos( $request->get_route(), '/wc/store/v1/products' ) ) {
			$this->params      = $this->paramsFromRequest( $request );
			$this->facetsCache = null;
			$this->applied     = false;
		}
		return $response;
	}

	public function paramsFromRequest( $request ) {
		$get = array();
		foreach ( (array) $request->get_query_params() as $k => $v ) {
			if ( 0 === strpos( $k, 'wpf_' ) || 0 === strpos( $k, 'pr_' ) || 0 === strpos( $k, 'product_tag' ) ) {
				$get[ $k ] = is_array( $v ) ? implode( ',', $v ) : $v;
			}
		}
		return $get;
	}

	/**
	 * Canonical params (key => slugs|string) from query vars, through the engine when present.
	 */
	public function canonical( $get ) {
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		if ( $links && method_exists( $links, 'canonicalFromQuery' ) ) {
			return $links->canonicalFromQuery( $get );
		}
		$out = array();
		foreach ( (array) $get as $k => $v ) {
			if ( 0 === strpos( $k, 'wpf_' ) ) {
				$key         = substr( $k, 4 );
				$out[ $key ] = in_array( $key, array( 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'page', 'q' ), true ) ? (string) $v : explode( ',', (string) $v );
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ query */

	/**
	 * `woocommerce_store_api_product_query` (when WooCommerce provides it).
	 */
	public function productQuery( $args, $request ) {
		$this->params = $this->paramsFromRequest( $request );
		$args         = $this->applyToArgs( $args, $this->canonical( $this->params ) );
		$this->applied = true;
		return $args;
	}

	/**
	 * Store API product query without the filter above: apply the same arguments on pre_get_posts.
	 */
	public function onPreGetPosts( $query ) {
		if ( $this->applied || null === $this->params || empty( $this->params ) ) {
			return;
		}
		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		$canonical = $this->canonical( $this->params );
		if ( empty( $canonical ) ) {
			return;
		}
		$this->applied = true; // One product query per request (the count fallback re-runs with the same args).
		$args = $this->applyToArgs( $query->query_vars, $canonical );
		foreach ( $args as $k => $v ) {
			$query->set( $k, $v );
		}
	}

	/**
	 * Canonical params => WP_Query args merged into $args.
	 */
	public function applyToArgs( $args, $canonical ) {
		if ( empty( $canonical ) ) {
			return $args;
		}
		$links  = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		$taxMap = $links ? $links->taxonomyForKey() : array( 'category' => 'product_cat', 'tag' => 'product_tag' );
		$tax    = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
		$meta   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		foreach ( $canonical as $key => $value ) {
			if ( isset( $taxMap[ $key ] ) && is_array( $value ) ) {
				$tax[] = array(
					'taxonomy'         => $taxMap[ $key ],
					'field'            => 'slug',
					'terms'            => array_map( 'sanitize_title', $value ),
					'operator'         => 'IN',
					'include_children' => 'category' === $key,
				);
				continue;
			}
			switch ( $key ) {
				case 'price':
					list( $min, $max ) = array_pad( explode( '-', (string) $value, 2 ), 2, '' );
					if ( '' !== $min && '' !== $max ) {
						$meta[] = array( 'key' => '_price', 'value' => array( (float) $min, (float) $max ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(19,4)' );
					} elseif ( '' !== $min ) {
						$meta[] = array( 'key' => '_price', 'value' => (float) $min, 'compare' => '>=', 'type' => 'DECIMAL(19,4)' );
					} elseif ( '' !== $max ) {
						$meta[] = array( 'key' => '_price', 'value' => (float) $max, 'compare' => '<=', 'type' => 'DECIMAL(19,4)' );
					}
					break;
				case 'stock':
					$m = array( 'in' => 'instock', 'out' => 'outofstock', 'backorder' => 'onbackorder' );
					$s = array();
					foreach ( explode( ',', (string) $value ) as $x ) {
						if ( isset( $m[ $x ] ) ) {
							$s[] = $m[ $x ];
						}
					}
					if ( $s ) {
						$meta[] = array( 'key' => '_stock_status', 'value' => $s, 'compare' => 'IN' );
					}
					break;
				case 'sale':
					if ( function_exists( 'wc_get_product_ids_on_sale' ) ) {
						$ids = array_map( 'intval', wc_get_product_ids_on_sale() );
						if ( ! empty( $args['post__in'] ) ) {
							$ids = array_values( array_intersect( (array) $args['post__in'], $ids ) );
						}
						$args['post__in'] = $ids ? $ids : array( 0 );
					}
					break;
				case 'featured':
					$tax[] = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'featured' ), 'operator' => 'IN' );
					break;
				case 'rating':
					$meta[] = array( 'key' => '_wc_average_rating', 'value' => (int) $value, 'compare' => '>=', 'type' => 'DECIMAL(3,2)' );
					break;
				case 'sort':
					$map = array(
						'price'      => array( 'meta_key' => '_price', 'orderby' => 'meta_value_num', 'order' => 'ASC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
						'price-desc' => array( 'meta_key' => '_price', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
						'date'       => array( 'orderby' => 'date', 'order' => 'DESC' ),
						'popularity' => array( 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
						'rating'     => array( 'meta_key' => '_wc_average_rating', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
						'title'      => array( 'orderby' => 'title', 'order' => 'ASC' ),
					);
					if ( isset( $map[ $value ] ) ) {
						$args = array_merge( $args, $map[ $value ] );
					}
					break;
				case 'page':
					$args['paged'] = max( 1, (int) $value );
					break;
				case 'q':
					$args['s'] = sanitize_text_field( (string) $value );
					break;
				default:
					// Numeric custom field range wpf_<meta_key>=min-max.
					if ( is_string( $value ) && preg_match( '/^(-?[\d.]*)-(-?[\d.]*)$/', $value, $m ) && ( '' !== $m[1] || '' !== $m[2] ) ) {
						$k = sanitize_key( $key );
						if ( '' !== $m[1] && '' !== $m[2] ) {
							$meta[] = array( 'key' => $k, 'value' => array( (float) $m[1], (float) $m[2] ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(19,4)' );
						} elseif ( '' !== $m[1] ) {
							$meta[] = array( 'key' => $k, 'value' => (float) $m[1], 'compare' => '>=', 'type' => 'DECIMAL(19,4)' );
						} else {
							$meta[] = array( 'key' => $k, 'value' => (float) $m[2], 'compare' => '<=', 'type' => 'DECIMAL(19,4)' );
						}
					}
			}
		}
		if ( count( $tax ) > 1 && ! isset( $tax['relation'] ) ) {
			$tax['relation'] = 'AND';
		}
		if ( count( $meta ) > 1 && ! isset( $meta['relation'] ) ) {
			$meta['relation'] = 'AND';
		}
		if ( $tax ) {
			$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		return apply_filters( 'woobewoo_pf_storeapi_query_args', $args, $canonical );
	}

	/* ------------------------------------------------------------------ extension data */

	public function registerEndpointData() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'product',
				'namespace'       => self::NS,
				'schema_callback' => array( $this, 'extensionSchema' ),
				'data_callback'   => array( $this, 'extensionData' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	public function extensionSchema() {
		return array(
			'facets'     => array(
				'description' => __( 'Facets with product counts for the current filtered query (same shape as wpf/v1/facets). Sent on the first product of a page only.', 'woo-product-filter' ),
				'type'        => array( 'object', 'null' ),
				'readonly'    => true,
			),
			'filter_url' => array(
				'description' => __( 'Shop URL of the current selection.', 'woo-product-filter' ),
				'type'        => array( 'string', 'null' ),
				'readonly'    => true,
			),
		);
	}

	/**
	 * Facets are computed once per request and attached to the first product only (keeps payloads small).
	 */
	public function extensionData( $product ) {
		static $sent = false;
		if ( $sent || null === $this->params ) {
			return array( 'facets' => null, 'filter_url' => null );
		}
		$sent   = true;
		$facets = $this->facets();
		return array(
			'facets'     => $facets,
			'filter_url' => isset( $facets['url'] ) ? $facets['url'] : null,
		);
	}

	public function facets() {
		if ( null !== $this->facetsCache ) {
			return $this->facetsCache;
		}
		$abilities = WooBeWoo_PF_Frame::_()->getModule( 'abilities' );
		if ( ! $abilities ) {
			return null;
		}
		$canonical = $this->canonical( (array) $this->params );
		$input     = array();
		$attrs     = array();
		$links     = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		$taxMap    = $links ? $links->taxonomyForKey() : array();
		foreach ( $canonical as $k => $v ) {
			if ( in_array( $k, array( 'category', 'tag', 'brand' ), true ) ) {
				$input[ $k ] = is_array( $v ) ? $v[0] : $v;
			} elseif ( isset( $taxMap[ $k ] ) ) {
				$attrs[ $k ] = (array) $v;
			} elseif ( 'price' === $k ) {
				list( $min, $max ) = array_pad( explode( '-', (string) $v, 2 ), 2, '' );
				if ( '' !== $min ) {
					$input['min_price'] = (float) $min;
				}
				if ( '' !== $max ) {
					$input['max_price'] = (float) $max;
				}
			} elseif ( 'stock' === $k && 'in' === $v ) {
				$input['in_stock'] = true;
			} elseif ( 'sale' === $k ) {
				$input['on_sale'] = true;
			} elseif ( 'featured' === $k ) {
				$input['featured'] = true;
			} elseif ( 'rating' === $k ) {
				$input['min_rating'] = (int) $v;
			} elseif ( 'q' === $k ) {
				$input['search'] = $v;
			}
		}
		if ( $attrs ) {
			$input['attributes'] = $attrs;
		}
		$this->facetsCache = $abilities->getModel()->facetsList( $input );
		return $this->facetsCache;
	}

	/**
	 * Document the wpf_* parameters on the Store API products collection.
	 */
	public function collectionParams() {
		add_filter( 'rest_product_collection_params', array( $this, 'describeParams' ), 20 );
	}

	public function describeParams( $params ) {
		$params['wpf_price'] = array(
			'description' => __( 'Product Filter price range "min-max".', 'woo-product-filter' ),
			'type'        => 'string',
		);
		$params['wpf_stock'] = array(
			'description' => __( 'Product Filter stock: in, out or backorder.', 'woo-product-filter' ),
			'type'        => 'string',
		);
		return $params;
	}
}
