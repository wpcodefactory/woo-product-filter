<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Schema Class
 *
 * Structured data and freshness for filtered pages (Free): CollectionPage + ItemList with the
 * products of the current result page, Product/Offer (price, availability) from the index
 * (WooBeWoo_PF_Index when present, else WooCommerce objects) and BreadcrumbList; Last-Modified
 * and ETag headers from the index version; sitemap lastmod through the
 * `woobewoo_pf_index_version` filter. Only acts on filtered archive pages, never on single
 * products (Yoast, Rank Math and WooCommerce already print Product schema there).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Schema extends WooBeWoo_PF_Module {

	private $printed = false;

	public function init() {
		parent::init();
		if ( is_admin() ) {
			return;
		}
		add_action( 'template_redirect', array( $this, 'freshnessHeaders' ), 5 );
		add_action( 'wp_footer', array( $this, 'printJsonLd' ), 5 );
		add_filter( 'woobewoo_pf_index_version', array( $this, 'indexVersionFilter' ) );
		add_filter( 'woobewoo_pf_selftest_rows', array( $this, 'selfTestRows' ) );
	}

	/* ------------------------------------------------------------------ state */

	/**
	 * True on product archives that carry filter parameters (pretty or query string).
	 */
	public function isFilteredArchive() {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_singular() ) {
			return false;
		}
		if ( ! function_exists( 'is_shop' ) || ! ( is_shop() || is_product_taxonomy() ) ) {
			return false;
		}
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		if ( $links && $links->active ) {
			return true;
		}
		$wf = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		return $wf && $wf->existsWpfParams();
	}

	/**
	 * Index version: a string that changes when products, stock or the index change.
	 * Uses WooBeWoo_PF_Index when the 4.0 engine is loaded, else the counts cache salt and the
	 * newest product modification time.
	 */
	public function indexVersion() {
		$v = '';
		if ( class_exists( 'WooBeWoo_PF_Index' ) ) {
			try {
				if ( method_exists( 'WooBeWoo_PF_Index', 'version' ) ) {
					$v = (string) WooBeWoo_PF_Index::version();
				} elseif ( method_exists( 'WooBeWoo_PF_Index', 'get_version' ) ) {
					$v = (string) WooBeWoo_PF_Index::get_version();
				}
			} catch ( Throwable $e ) {
				$v = '';
			}
		}
		if ( '' === $v ) {
			global $wpdb;
			$salt   = (string) get_option( 'wpf_counts_cache_ver', '' );
			$latest = (string) $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$v      = $salt . '|' . $latest;
		}
		return (string) apply_filters( 'woobewoo_pf_index_version', $v );
	}

	public function indexVersionFilter( $v ) {
		return $v;
	}

	/**
	 * Unix time of the last index change, for Last-Modified and sitemap lastmod.
	 */
	public function lastModified() {
		global $wpdb;
		$times  = array();
		$latest = $wpdb->get_var( "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $latest ) {
			$times[] = strtotime( $latest . ' UTC' );
		}
		$salt = get_option( 'wpf_counts_cache_ver' );
		if ( $salt && is_numeric( $salt ) ) {
			$times[] = (int) $salt;
		}
		if ( WooBeWoo_PF_Db::exist( '@__meta_data' ) ) {
			$idx = WooBeWoo_PF_Db::get( 'SELECT MAX(updated) FROM `@__meta_data`', 'one' );
			if ( $idx ) {
				$times[] = strtotime( $idx . ' UTC' );
			}
		}
		$t = $times ? max( array_filter( $times ) ) : time();
		return (int) apply_filters( 'woobewoo_pf_index_last_modified', $t );
	}

	/* ------------------------------------------------------------------ headers */

	public function freshnessHeaders() {
		if ( ! $this->isFilteredArchive() || headers_sent() || is_user_logged_in() ) {
			return;
		}
		// A shopper with a cart or a session sees a personal page (mini cart, prices): never answer 304 for it.
		foreach ( array_keys( $_COOKIE ) as $cookie ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			if ( 0 === strpos( (string) $cookie, 'woocommerce_items_in_cart' ) || 0 === strpos( (string) $cookie, 'wp_woocommerce_session_' ) ) {
				return;
			}
		}
		// Pages driven by a classic (3.x engine) filter keep the 3.x HTTP behaviour: no validators, no 304.
		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) && ! WooBeWoo_PF_Frontend4::active_filter() ) {
			return;
		}
		$version = $this->indexVersion();
		$last    = $this->lastModified();
		$etag    = '"wpf-' . substr( md5( $version . '|' . $this->requestKey() ), 0, 20 ) . '"';
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last ) . ' GMT' );
		header( 'ETag: ' . $etag );
		header( 'Vary: Accept-Encoding' );
		$ifNone = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) : '';
		$ifMod  = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ) : 0;
		if ( ( $ifNone && $ifNone === $etag ) || ( ! $ifNone && $ifMod && $ifMod >= $last ) ) {
			status_header( 304 );
			exit;
		}
	}

	private function requestKey() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return $uri . '|' . ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' );
	}

	/* ------------------------------------------------------------------ JSON-LD */

	/**
	 * Product rows of the current main query: id, name, url, image, price, currency, availability.
	 */
	public function currentProducts() {
		global $wp_query;
		$rows = array();
		if ( ! $wp_query || empty( $wp_query->posts ) ) {
			return $rows;
		}
		$ids   = array();
		foreach ( $wp_query->posts as $p ) {
			if ( isset( $p->post_type ) && 'product' === $p->post_type ) {
				$ids[] = (int) $p->ID;
			}
		}
		$ids = array_slice( $ids, 0, 60 );
		if ( empty( $ids ) ) {
			return $rows;
		}
		$fromIndex = array();
		if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'products' ) ) {
			try {
				$fromIndex = (array) WooBeWoo_PF_Index::products( $ids );
			} catch ( Throwable $e ) {
				$fromIndex = array();
			}
		}
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		foreach ( $ids as $pos => $id ) {
			if ( isset( $fromIndex[ $id ] ) && is_array( $fromIndex[ $id ] ) ) {
				$r = wp_parse_args(
					$fromIndex[ $id ],
					array(
						'name'         => get_the_title( $id ),
						'url'          => get_permalink( $id ),
						'image'        => '',
						'price'        => '',
						'price_max'    => '',
						'currency'     => $currency,
						'stock_status' => 'instock',
						'sku'          => '',
					)
				);
			} else {
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
				if ( ! $product ) {
					continue;
				}
				$img = get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' );
				$r   = array(
					'name'         => $product->get_name(),
					'url'          => $product->get_permalink(),
					'image'        => $img ? $img : '',
					'price'        => $product->is_type( 'variable' ) ? $product->get_variation_price( 'min' ) : $product->get_price(),
					'price_max'    => $product->is_type( 'variable' ) ? $product->get_variation_price( 'max' ) : '',
					'currency'     => $currency,
					'stock_status' => $product->get_stock_status(),
					'sku'          => $product->get_sku(),
				);
			}
			$r['id']       = $id;
			$r['position'] = $pos + 1;
			$rows[]        = $r;
		}
		return $rows;
	}

	private function availability( $status ) {
		switch ( $status ) {
			case 'outofstock':
				return 'https://schema.org/OutOfStock';
			case 'onbackorder':
				return 'https://schema.org/BackOrder';
			default:
				return 'https://schema.org/InStock';
		}
	}

	/**
	 * Build the JSON-LD graph for the current filtered page.
	 */
	public function graph() {
		global $wp_query;
		$links    = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		$url      = home_url( add_query_arg( array() ) );
		$base     = $links ? $links->currentBase() : '';
		$params   = $links ? ( $links->active ? $links->params : $links->canonicalFromQuery( wp_unslash( $_GET ) ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$name     = wp_get_document_title();
		$products = $this->currentProducts();
		$total    = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : count( $products );
		$site     = home_url( '/' );

		$items = array();
		foreach ( $products as $p ) {
			$offer = array(
				'@type'         => 'Offer',
				'price'         => '' === $p['price'] ? '0' : (string) wc_format_decimal( $p['price'], wc_get_price_decimals() ),
				'priceCurrency' => $p['currency'],
				'availability'  => $this->availability( $p['stock_status'] ),
				'url'           => $p['url'],
			);
			if ( '' !== $p['price_max'] && (string) $p['price_max'] !== (string) $p['price'] ) {
				$offer = array(
					'@type'         => 'AggregateOffer',
					'lowPrice'      => (string) wc_format_decimal( $p['price'], wc_get_price_decimals() ),
					'highPrice'     => (string) wc_format_decimal( $p['price_max'], wc_get_price_decimals() ),
					'priceCurrency' => $p['currency'],
					'availability'  => $this->availability( $p['stock_status'] ),
					'url'           => $p['url'],
				);
			}
			$product = array(
				'@type'  => 'Product',
				'@id'    => $p['url'] . '#product',
				'name'   => $p['name'],
				'url'    => $p['url'],
				'offers' => $offer,
			);
			if ( ! empty( $p['image'] ) ) {
				$product['image'] = $p['image'];
			}
			if ( ! empty( $p['sku'] ) ) {
				$product['sku'] = $p['sku'];
			}
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $p['position'],
				'item'     => $product,
			);
		}

		$crumbs   = array();
		$position = 1;
		$crumbs[] = array( '@type' => 'ListItem', 'position' => $position++, 'name' => get_bloginfo( 'name' ), 'item' => $site );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shopId = wc_get_page_id( 'shop' );
			if ( $shopId > 0 ) {
				$crumbs[] = array( '@type' => 'ListItem', 'position' => $position++, 'name' => get_the_title( $shopId ), 'item' => wc_get_page_permalink( 'shop' ) );
			}
		}
		$obj = get_queried_object();
		if ( $obj instanceof WP_Term ) {
			$ancestors = array_reverse( get_ancestors( $obj->term_id, $obj->taxonomy, 'taxonomy' ) );
			foreach ( $ancestors as $aid ) {
				$a = get_term( $aid, $obj->taxonomy );
				if ( $a && ! is_wp_error( $a ) ) {
					$l = get_term_link( $a );
					if ( ! is_wp_error( $l ) ) {
						$crumbs[] = array( '@type' => 'ListItem', 'position' => $position++, 'name' => $a->name, 'item' => $l );
					}
				}
			}
			$l = get_term_link( $obj );
			if ( ! is_wp_error( $l ) ) {
				$crumbs[] = array( '@type' => 'ListItem', 'position' => $position++, 'name' => $obj->name, 'item' => $l );
			}
		}
		$filterWords = $this->describe( $params );
		if ( $filterWords ) {
			$crumbs[] = array( '@type' => 'ListItem', 'position' => $position++, 'name' => $filterWords, 'item' => $url );
		}

		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'           => 'CollectionPage',
					'@id'             => $url . '#webpage',
					'url'             => $url,
					'name'            => $name,
					'isPartOf'        => array( '@type' => 'WebSite', '@id' => $site . '#website', 'url' => $site, 'name' => get_bloginfo( 'name' ) ),
					'dateModified'    => gmdate( 'c', $this->lastModified() ),
					'mainEntity'      => array( '@id' => $url . '#list' ),
					'breadcrumb'      => array( '@id' => $url . '#breadcrumb' ),
				),
				array(
					'@type'           => 'ItemList',
					'@id'             => $url . '#list',
					'name'            => $filterWords ? $filterWords : $name,
					'numberOfItems'   => $total,
					'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
					'itemListElement' => $items,
				),
				array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $url . '#breadcrumb',
					'itemListElement' => $crumbs,
				),
			),
		);
		if ( $base ) {
			$graph['@graph'][0]['isPartOf']['url'] = $site;
			$graph['@graph'][0]['about']           = array( '@type' => 'Thing', 'url' => $base );
		}
		return apply_filters( 'woobewoo_pf_schema_graph', $graph, $params, $products );
	}

	/**
	 * Plain words for the picked filters: "Size 42, 43 · Colour Red · $50 to $120".
	 */
	public function describe( $params ) {
		$parts = array();
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		$tax   = $links ? $links->taxonomyForKey() : array();
		foreach ( (array) $params as $k => $v ) {
			$vals = is_array( $v ) ? $v : array( $v );
			if ( isset( $tax[ $k ] ) ) {
				$names = array();
				foreach ( $vals as $slug ) {
					$t       = get_term_by( 'slug', $slug, $tax[ $k ] );
					$names[] = $t && ! is_wp_error( $t ) ? $t->name : $slug;
				}
				$label   = 0 === strpos( $tax[ $k ], 'pa_' ) && function_exists( 'wc_attribute_label' ) ? wc_attribute_label( $tax[ $k ] ) : ucfirst( $k );
				$parts[] = $label . ' ' . implode( ', ', $names );
			} elseif ( 'price' === $k ) {
				list( $min, $max ) = array_pad( explode( '-', (string) $v, 2 ), 2, '' );
				$sym = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES ) : '';
				if ( '' !== $min && '' !== $max ) {
					/* translators: 1: min price 2: max price */
					$parts[] = sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), $sym . $min, $sym . $max );
				} elseif ( '' !== $min ) {
					/* translators: %s: price */
					$parts[] = sprintf( __( 'from %s', 'woo-product-filter' ), $sym . $min );
				} elseif ( '' !== $max ) {
					/* translators: %s: price */
					$parts[] = sprintf( __( 'up to %s', 'woo-product-filter' ), $sym . $max );
				}
			} elseif ( 'stock' === $k ) {
				$parts[] = __( 'In stock', 'woo-product-filter' );
			} elseif ( 'sale' === $k ) {
				$parts[] = __( 'On sale', 'woo-product-filter' );
			} elseif ( 'featured' === $k ) {
				$parts[] = __( 'Featured', 'woo-product-filter' );
			} elseif ( 'rating' === $k ) {
				/* translators: %d: stars */
				$parts[] = sprintf( __( '%d stars and up', 'woo-product-filter' ), (int) $v );
			}
		}
		return implode( ' · ', $parts );
	}

	public function printJsonLd() {
		if ( $this->printed || ! $this->isFilteredArchive() ) {
			return;
		}
		$this->printed = true;
		$graph = $this->graph();
		if ( empty( $graph ) ) {
			return;
		}
		echo "\n" . '<script type="application/ld+json" class="wpf-schema">' . wp_json_encode( $graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built from escaped values with wp_json_encode.
	}

	/* ------------------------------------------------------------------ sitemap helper */

	/**
	 * lastmod for sitemaps of filtered landing pages (the Pro answer page sitemap calls this).
	 */
	public function sitemapLastmod() {
		return gmdate( 'c', $this->lastModified() );
	}

	public function selfTestRows( $rows ) {
		$rows[] = array(
			'state'  => 'pass',
			'label'  => __( 'Structured data on filtered pages', 'woo-product-filter' ),
			'detail' => __( 'CollectionPage, ItemList, Product offers and BreadcrumbList are printed on filtered archive pages only.', 'woo-product-filter' ),
		);
		return $rows;
	}
}

if ( ! function_exists( 'woobewoo_pf_index_version' ) ) {
	/**
	 * Current index version string (changes when products, stock or the index change).
	 *
	 * @since 4.0.0
	 */
	function woobewoo_pf_index_version() {
		$m = WooBeWoo_PF_Frame::_()->getModule( 'schema' );
		return $m ? $m->indexVersion() : (string) apply_filters( 'woobewoo_pf_index_version', '' );
	}
}
