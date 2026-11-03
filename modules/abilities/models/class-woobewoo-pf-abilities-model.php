<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Abilities_Model Class
 *
 * Strict JSON schemas, the facets-list and products-filter implementations and the OpenAPI 3.1
 * document built from WooBeWoo_PF_Abilities::registry().
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Abilities_Model extends WooBeWoo_PF_Model {

	/* ------------------------------------------------------------------ schemas */

	public function schemas() {
		$term    = array(
			'type'       => 'object',
			'properties' => array(
				'id'    => array( 'type' => 'integer' ),
				'name'  => array( 'type' => 'string' ),
				'slug'  => array( 'type' => 'string' ),
				'count' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'id', 'name', 'slug', 'count' ),
			'additionalProperties' => false,
		);
		$product = array(
			'type'       => 'object',
			'properties' => array(
				'id'                    => array( 'type' => 'integer' ),
				'name'                  => array( 'type' => 'string' ),
				'sku'                   => array( 'type' => 'string' ),
				'price'                 => array( 'type' => 'number' ),
				'price_max'             => array( 'type' => array( 'number', 'null' ) ),
				'currency'              => array( 'type' => 'string' ),
				'stock_status'          => array( 'type' => 'string', 'enum' => array( 'instock', 'outofstock', 'onbackorder' ) ),
				'in_stock'              => array( 'type' => 'boolean' ),
				'on_sale'               => array( 'type' => 'boolean' ),
				'rating'                => array( 'type' => 'number' ),
				'matching_variation_id' => array( 'type' => array( 'integer', 'null' ) ),
				'permalink'             => array( 'type' => 'string', 'format' => 'uri' ),
				'image'                 => array( 'type' => array( 'string', 'null' ) ),
			),
			'required'   => array( 'id', 'name', 'price', 'currency', 'stock_status', 'in_stock', 'permalink' ),
			'additionalProperties' => false,
		);
		$selection = array(
			'category'   => array( 'type' => array( 'string', 'integer' ), 'description' => 'Category id or slug.' ),
			'tag'        => array( 'type' => array( 'string', 'integer' ), 'description' => 'Tag id or slug.' ),
			'brand'      => array( 'type' => array( 'string', 'integer' ), 'description' => 'Brand id or slug (WooCommerce Brands or Perfect Brands).' ),
			'attributes' => array(
				'type'                 => 'object',
				'description'          => 'taxonomy or attribute slug => list of term slugs or ids, e.g. {"color":["red"],"size":["42","43"]}. Several values of one attribute are OR, attributes are AND.',
				'additionalProperties' => array( 'type' => 'array', 'items' => array( 'type' => array( 'string', 'integer' ) ) ),
			),
			'min_price'  => array( 'type' => 'number', 'minimum' => 0 ),
			'max_price'  => array( 'type' => 'number', 'minimum' => 0 ),
			'in_stock'   => array( 'type' => 'boolean', 'description' => 'Only products that can be bought now.' ),
			'on_sale'    => array( 'type' => 'boolean' ),
			'featured'   => array( 'type' => 'boolean' ),
			'min_rating' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ),
			'search'     => array( 'type' => 'string', 'maxLength' => 200 ),
		);
		return array(
			'facets-list'     => array(
				'input'  => array(
					'type'                 => 'object',
					'properties'           => $selection,
					'additionalProperties' => false,
				),
				'output' => array(
					'type'       => 'object',
					'properties' => array(
						'products'   => array( 'type' => 'integer', 'description' => 'Products matching the selection.' ),
						'currency'   => array( 'type' => 'string' ),
						'categories' => array( 'type' => 'array', 'items' => $term ),
						'tags'       => array( 'type' => 'array', 'items' => $term ),
						'brands'     => array( 'type' => 'array', 'items' => $term ),
						'attributes' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'key'      => array( 'type' => 'string', 'description' => 'Attribute slug without pa_ (the url segment and the wpf_<key> query var).' ),
									'taxonomy' => array( 'type' => 'string' ),
									'label'    => array( 'type' => 'string' ),
									'terms'    => array( 'type' => 'array', 'items' => $term ),
								),
								'required'   => array( 'key', 'taxonomy', 'label', 'terms' ),
							),
						),
						'price'      => array(
							'type'       => 'object',
							'properties' => array(
								'min' => array( 'type' => 'number' ),
								'max' => array( 'type' => 'number' ),
							),
							'required'   => array( 'min', 'max' ),
						),
						'stock'      => array(
							'type'       => 'object',
							'properties' => array(
								'instock'     => array( 'type' => 'integer' ),
								'outofstock'  => array( 'type' => 'integer' ),
								'onbackorder' => array( 'type' => 'integer' ),
							),
						),
						'on_sale'    => array( 'type' => 'integer' ),
						'featured'   => array( 'type' => 'integer' ),
						'url'        => array( 'type' => 'string', 'format' => 'uri', 'description' => 'Shop page for this selection (pretty URL when clean links are on).' ),
					),
					'required'   => array( 'products', 'currency', 'categories', 'attributes', 'price', 'stock' ),
				),
			),
			'products-filter' => array(
				'input'  => array(
					'type'                 => 'object',
					'properties'           => array_merge(
						$selection,
						array(
							'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20 ),
							'page'     => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
							'orderby'  => array( 'type' => 'string', 'enum' => array( 'default', 'price', 'price-desc', 'date', 'popularity', 'rating', 'title' ), 'default' => 'default' ),
						)
					),
					'additionalProperties' => false,
				),
				'output' => array(
					'type'       => 'object',
					'properties' => array(
						'total'    => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
						'per_page' => array( 'type' => 'integer' ),
						'pages'    => array( 'type' => 'integer' ),
						'url'      => array( 'type' => 'string', 'format' => 'uri' ),
						'products' => array( 'type' => 'array', 'items' => $product ),
					),
					'required'   => array( 'total', 'page', 'per_page', 'products' ),
				),
			),
		);
	}

	/* ------------------------------------------------------------------ helpers */

	public function brandTaxonomy() {
		if ( taxonomy_exists( 'product_brand' ) ) {
			return 'product_brand';
		}
		if ( taxonomy_exists( 'pwb-brand' ) ) {
			return 'pwb-brand';
		}
		return '';
	}

	public function resolveTerm( $taxonomy, $value ) {
		if ( '' === $taxonomy || null === $value || '' === $value ) {
			return null;
		}
		// Slug first (sizes like "42" are numeric slugs), then name, then id.
		$t = get_term_by( 'slug', sanitize_title( (string) $value ), $taxonomy );
		if ( ! $t ) {
			$t = get_term_by( 'name', (string) $value, $taxonomy );
		}
		if ( ! $t && is_numeric( $value ) ) {
			$t = get_term( (int) $value, $taxonomy );
		}
		return ( $t && ! is_wp_error( $t ) ) ? $t : null;
	}

	private function termRow( $t, $count ) {
		return array(
			'id'    => (int) $t->term_id,
			'name'  => html_entity_decode( $t->name, ENT_QUOTES ),
			'slug'  => $t->slug,
			'count' => (int) $count,
		);
	}

	/**
	 * Input => canonical params (as the links module understands them) and the WP_Query tax/meta
	 * structure. Unknown values are dropped; nothing is fatal.
	 */
	public function selection( $input ) {
		$canonical = array();
		$taxQuery  = array( 'relation' => 'AND' );
		$metaQuery = array( 'relation' => 'AND' );
		$attrTerms = array();
		$brandTax  = $this->brandTaxonomy();

		foreach ( array( 'category' => 'product_cat', 'tag' => 'product_tag', 'brand' => $brandTax ) as $key => $tax ) {
			if ( empty( $input[ $key ] ) || ! $tax ) {
				continue;
			}
			$vals  = is_array( $input[ $key ] ) ? $input[ $key ] : preg_split( '/[,|]/', (string) $input[ $key ] );
			$slugs = array();
			$ids   = array();
			foreach ( $vals as $v ) {
				$t = $this->resolveTerm( $tax, $v );
				if ( $t ) {
					$slugs[] = $t->slug;
					$ids[]   = (int) $t->term_id;
				}
			}
			if ( $ids ) {
				$canonical[ $key ] = $slugs;
				$taxQuery[]        = array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $ids, 'operator' => 'IN', 'include_children' => 'category' === $key );
			}
		}
		if ( ! empty( $input['attributes'] ) && is_array( $input['attributes'] ) ) {
			foreach ( $input['attributes'] as $tax => $vals ) {
				$tax = sanitize_key( $tax );
				if ( ! taxonomy_exists( $tax ) && taxonomy_exists( 'pa_' . $tax ) ) {
					$tax = 'pa_' . $tax;
				}
				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$slugs = array();
				$ids   = array();
				foreach ( (array) $vals as $v ) {
					$t = $this->resolveTerm( $tax, $v );
					if ( $t ) {
						$slugs[] = $t->slug;
						$ids[]   = (int) $t->term_id;
					}
				}
				if ( $ids ) {
					$key               = preg_replace( '/^pa_/', '', $tax );
					$canonical[ $key ] = $slugs;
					$attrTerms[ $tax ] = $slugs;
					$taxQuery[]        = array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $ids, 'operator' => 'IN' );
				}
			}
		}
		if ( ! empty( $input['featured'] ) ) {
			$canonical['featured'] = '1';
			$taxQuery[]            = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'featured' ), 'operator' => 'IN' );
		}
		$min = isset( $input['min_price'] ) && '' !== $input['min_price'] ? (float) $input['min_price'] : null;
		$max = isset( $input['max_price'] ) && '' !== $input['max_price'] ? (float) $input['max_price'] : null;
		if ( null !== $min || null !== $max ) {
			$canonical['price'] = ( null === $min ? '' : rtrim( rtrim( number_format( $min, 2, '.', '' ), '0' ), '.' ) ) . '-' . ( null === $max ? '' : rtrim( rtrim( number_format( $max, 2, '.', '' ), '0' ), '.' ) );
			if ( null !== $min && null !== $max ) {
				$metaQuery[] = array( 'key' => '_price', 'value' => array( $min, $max ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(19,4)' );
			} elseif ( null !== $min ) {
				$metaQuery[] = array( 'key' => '_price', 'value' => $min, 'compare' => '>=', 'type' => 'DECIMAL(19,4)' );
			} else {
				$metaQuery[] = array( 'key' => '_price', 'value' => $max, 'compare' => '<=', 'type' => 'DECIMAL(19,4)' );
			}
		}
		if ( ! empty( $input['in_stock'] ) ) {
			$canonical['stock'] = 'in';
			$metaQuery[]        = array( 'key' => '_stock_status', 'value' => 'instock' );
		}
		if ( ! empty( $input['min_rating'] ) ) {
			$canonical['rating'] = (string) (int) $input['min_rating'];
			$metaQuery[]         = array( 'key' => '_wc_average_rating', 'value' => (int) $input['min_rating'], 'compare' => '>=', 'type' => 'DECIMAL(3,2)' );
		}
		if ( ! empty( $input['on_sale'] ) ) {
			$canonical['sale'] = '1';
		}
		if ( ! empty( $input['search'] ) ) {
			$canonical['q'] = sanitize_text_field( (string) $input['search'] );
		}
		return array(
			'canonical' => $canonical,
			'tax_query' => count( $taxQuery ) > 1 ? $taxQuery : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- this is the query spec the ability returns to the agent, built from the facets it asked for
			'meta_query' => count( $metaQuery ) > 1 ? $metaQuery : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- this is the query spec the ability returns to the agent, built from the facets it asked for
			'attr_terms' => $attrTerms,
			'on_sale'   => ! empty( $input['on_sale'] ),
			'search'    => isset( $canonical['q'] ) ? $canonical['q'] : '',
		);
	}

	/**
	 * Product ids for a selection (published, visible in the catalogue), sorted.
	 */
	public function productIds( $sel, $orderby = 'default' ) {
		$args = array(
			'post_type'           => 'product',
			'post_status'         => 'publish',
			'fields'              => 'ids',
			'posts_per_page'      => 5000,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
		);
		if ( $sel['tax_query'] ) {
			$args['tax_query'] = $sel['tax_query']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		if ( $sel['meta_query'] ) {
			$args['meta_query'] = $sel['meta_query']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( '' !== $sel['search'] ) {
			$args['s'] = $sel['search'];
		}
		// Hide products the catalogue hides.
		$hidden = get_term_by( 'name', 'exclude-from-catalog', 'product_visibility' );
		if ( $hidden ) {
			$args['tax_query'] = isset( $args['tax_query'] ) ? $args['tax_query'] : array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			$args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'term_id', 'terms' => array( (int) $hidden->term_id ), 'operator' => 'NOT IN' );
		}
		$map = array(
			'price'      => array( 'meta_key' => '_price', 'orderby' => 'meta_value_num', 'order' => 'ASC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
			'price-desc' => array( 'meta_key' => '_price', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
			'date'       => array( 'orderby' => 'date', 'order' => 'DESC' ),
			'popularity' => array( 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
			'rating'     => array( 'meta_key' => '_wc_average_rating', 'orderby' => 'meta_value_num', 'order' => 'DESC' ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the WooCommerce catalogue orderings (price, popularity, rating) are meta ordered by design and backed by the wc_product_meta_lookup table
			'title'      => array( 'orderby' => 'title', 'order' => 'ASC' ),
		);
		if ( isset( $map[ $orderby ] ) ) {
			$args = array_merge( $args, $map[ $orderby ] );
		} else {
			$args['orderby'] = 'menu_order title';
			$args['order']   = 'ASC';
		}
		$q   = new WP_Query( $args );
		$ids = array_map( 'intval', (array) $q->posts );
		if ( $sel['on_sale'] && function_exists( 'wc_get_product_ids_on_sale' ) ) {
			$ids = array_values( array_intersect( $ids, array_map( 'intval', wc_get_product_ids_on_sale() ) ) );
		}
		return apply_filters( 'woobewoo_pf_abilities_product_ids', $ids, $sel, $orderby );
	}

	private function selectionUrl( $canonical, $page = 1 ) {
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		$base  = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		if ( $page > 1 ) {
			$canonical['page'] = (string) (int) $page;
		}
		if ( $links && method_exists( $links, 'prettyUrl' ) && $links->cleanLinksOn() ) {
			return $links->prettyUrl( $base, $canonical );
		}
		$vars = array();
		foreach ( $canonical as $k => $v ) {
			$vars[ 'wpf_' . $k ] = is_array( $v ) ? implode( ',', $v ) : $v;
		}
		return $vars ? add_query_arg( array_map( 'rawurlencode', $vars ), $base ) : $base;
	}

	/* ------------------------------------------------------------------ facets-list */

	public function facetsList( $input ) {
		global $wpdb;
		$sel = $this->selection( (array) $input );
		$ids = $this->productIds( $sel );
		$out = array(
			'products'   => count( $ids ),
			'currency'   => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'categories' => array(),
			'tags'       => array(),
			'brands'     => array(),
			'attributes' => array(),
			'price'      => array( 'min' => 0, 'max' => 0 ),
			'stock'      => array( 'instock' => 0, 'outofstock' => 0, 'onbackorder' => 0 ),
			'on_sale'    => 0,
			'featured'   => 0,
			'url'        => $this->selectionUrl( $sel['canonical'] ),
		);
		if ( empty( $ids ) ) {
			return $out;
		}
		$in     = implode( ',', array_map( 'intval', array_slice( $ids, 0, 5000 ) ) );
		$counts = function ( $taxonomy ) use ( $wpdb, $in ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) AS c FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tt.taxonomy = %s AND tr.object_id IN ({$in}) GROUP BY t.term_id ORDER BY c DESC, t.name ASC LIMIT 500", $taxonomy ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery -- $in is a comma separated list of cast integers
			$list = array();
			foreach ( (array) $rows as $r ) {
				$list[] = array(
					'id'    => (int) $r->term_id,
					'name'  => html_entity_decode( $r->name, ENT_QUOTES ),
					'slug'  => $r->slug,
					'count' => (int) $r->c,
				);
			}
			return $list;
		};
		$out['categories'] = $counts( 'product_cat' );
		$out['tags']       = $counts( 'product_tag' );
		$brandTax          = $this->brandTaxonomy();
		if ( $brandTax ) {
			$out['brands'] = $counts( $brandTax );
		}
		if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
			foreach ( wc_get_attribute_taxonomies() as $attr ) {
				$tax = wc_attribute_taxonomy_name( $attr->attribute_name );
				if ( ! taxonomy_exists( $tax ) ) {
					continue;
				}
				$terms = $counts( $tax );
				if ( $terms ) {
					$out['attributes'][] = array(
						'key'      => sanitize_title( $attr->attribute_name ),
						'taxonomy' => $tax,
						'label'    => $attr->attribute_label,
						'terms'    => $terms,
					);
				}
			}
		}
		$row = $wpdb->get_row( "SELECT MIN(min_price) AS minp, MAX(max_price) AS maxp, SUM(stock_status='instock') AS i, SUM(stock_status='outofstock') AS o, SUM(stock_status='onbackorder') AS b, SUM(onsale=1) AS s FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $row ) {
			$out['price'] = array( 'min' => (float) $row['minp'], 'max' => (float) $row['maxp'] );
			$out['stock'] = array( 'instock' => (int) $row['i'], 'outofstock' => (int) $row['o'], 'onbackorder' => (int) $row['b'] );
			$out['on_sale'] = (int) $row['s'];
		}
		$featured = get_term_by( 'name', 'featured', 'product_visibility' );
		if ( $featured ) {
			$out['featured'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_id = %d AND tr.object_id IN ({$in})", $featured->term_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
		return apply_filters( 'woobewoo_pf_abilities_facets', $out, $sel, $ids );
	}

	/* ------------------------------------------------------------------ products-filter */

	public function productsFilter( $input ) {
		$input   = (array) $input;
		$sel     = $this->selection( $input );
		$orderby = isset( $input['orderby'] ) ? sanitize_key( $input['orderby'] ) : 'default';
		$ids     = $this->productIds( $sel, $orderby );
		$perPage = isset( $input['per_page'] ) ? max( 1, min( 50, (int) $input['per_page'] ) ) : 20;
		$page    = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$slice   = array_slice( $ids, ( $page - 1 ) * $perPage, $perPage );
		$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		$rows    = array();
		foreach ( $slice as $id ) {
			$p = wc_get_product( $id );
			if ( ! $p ) {
				continue;
			}
			$isVar = $p->is_type( 'variable' );
			$row   = array(
				'id'                    => (int) $id,
				'name'                  => html_entity_decode( $p->get_name(), ENT_QUOTES ),
				'sku'                   => (string) $p->get_sku(),
				'price'                 => (float) ( $isVar ? $p->get_variation_price( 'min' ) : $p->get_price() ),
				'price_max'             => $isVar ? (float) $p->get_variation_price( 'max' ) : null,
				'currency'              => $currency,
				'stock_status'          => $p->get_stock_status(),
				'in_stock'              => $p->is_in_stock(),
				'on_sale'               => $p->is_on_sale(),
				'rating'                => (float) $p->get_average_rating(),
				'matching_variation_id' => null,
				'permalink'             => $p->get_permalink(),
				'image'                 => get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' ) ? get_the_post_thumbnail_url( $id, 'woocommerce_thumbnail' ) : null,
			);
			if ( $isVar && $sel['attr_terms'] ) {
				$v = $this->matchingVariation( $p, $sel['attr_terms'], ! empty( $input['in_stock'] ) );
				if ( $v ) {
					$row['matching_variation_id'] = (int) $v['id'];
					$row['stock_status']          = $v['stock_status'];
					$row['in_stock']              = 'instock' === $v['stock_status'];
					if ( null !== $v['price'] ) {
						$row['price']     = (float) $v['price'];
						$row['price_max'] = null;
					}
				}
			}
			$rows[] = $row;
		}
		return array(
			'total'    => count( $ids ),
			'page'     => $page,
			'per_page' => $perPage,
			'pages'    => (int) ceil( count( $ids ) / $perPage ),
			'url'      => $this->selectionUrl( $sel['canonical'], $page ),
			'products' => $rows,
		);
	}

	/**
	 * The variation that matches the given attribute slugs: the engine's index when present
	 * (woobewoo_pf_variation_stock_lookup), else WooCommerce objects.
	 */
	private function matchingVariation( $product, $attrTerms, $needStock ) {
		$indexed = apply_filters( 'woobewoo_pf_variation_stock_lookup', null, $product->get_id(), $attrTerms );
		if ( is_array( $indexed ) && isset( $indexed['id'] ) ) {
			return array(
				'id'           => (int) $indexed['id'],
				'stock_status' => isset( $indexed['stock_status'] ) ? $indexed['stock_status'] : 'instock',
				'price'        => isset( $indexed['price'] ) ? $indexed['price'] : null,
			);
		}
		$fallback = null;
		foreach ( $product->get_children() as $vid ) {
			$v = wc_get_product( $vid );
			if ( ! $v ) {
				continue;
			}
			$attrs = $v->get_attributes();
			$ok    = true;
			foreach ( $attrTerms as $tax => $slugs ) {
				$val = isset( $attrs[ $tax ] ) ? $attrs[ $tax ] : '';
				if ( '' !== $val && ! in_array( $val, $slugs, true ) ) {
					$ok = false;
					break;
				}
			}
			if ( ! $ok ) {
				continue;
			}
			$row = array(
				'id'           => (int) $vid,
				'stock_status' => $v->get_stock_status(),
				'price'        => '' === $v->get_price() ? null : (float) $v->get_price(),
			);
			if ( 'instock' === $row['stock_status'] ) {
				return $row;
			}
			if ( null === $fallback ) {
				$fallback = $row;
			}
		}
		return $needStock ? null : $fallback;
	}

	/* ------------------------------------------------------------------ OpenAPI */

	/**
	 * OpenAPI 3.1 document from the registry (Free and Pro abilities) plus the platform routes.
	 */
	public function openapi( $base ) {
		$paths      = array();
		$components = array();
		$security   = array( array( 'cookieAuth' => array() ), array( 'applicationPassword' => array() ) );
		foreach ( WooBeWoo_PF_Abilities::registry() as $name => $d ) {
			if ( empty( $d['rest']['path'] ) ) {
				continue;
			}
			$opId   = lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) ) );
			$schemaName = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) );
			$components[ $schemaName . 'Input' ]  = $d['input'];
			$components[ $schemaName . 'Output' ] = $d['output'];
			$path = $d['rest']['path'];
			foreach ( (array) $d['rest']['methods'] as $m ) {
				$op = array(
					'operationId' => $opId . ( 'GET' === $m && count( (array) $d['rest']['methods'] ) > 1 ? 'Get' : '' ),
					'summary'     => $d['label'],
					'description' => $d['description'] . ' ' . sprintf( 'Also available as the WordPress Ability "%s".', WooBeWoo_PF_Abilities::NS . '/' . $name ),
					'tags'        => array( ! empty( $d['readonly'] ) ? 'read' : 'write' ),
					'responses'   => array(
						'200' => array(
							'description' => 'OK',
							'content'     => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/' . $schemaName . 'Output' ) ) ),
						),
						'429' => array( 'description' => 'Rate limit: 120 requests a minute per IP for public routes.' ),
					),
				);
				if ( 'public' !== $d['permission'] ) {
					$op['security'] = $security;
					$op['responses']['403'] = array( 'description' => 'Needs the manage_options capability.' );
				}
				if ( preg_match_all( '/\(\?P<(\w+)>/', $path, $pm ) ) {
					foreach ( $pm[1] as $pname ) {
						$op['parameters'][] = array( 'name' => $pname, 'in' => 'path', 'required' => true, 'schema' => array( 'type' => 'string' ) );
					}
				}
				if ( 'GET' === $m ) {
					foreach ( (array) ( isset( $d['input']['properties'] ) ? $d['input']['properties'] : array() ) as $pname => $pschema ) {
						$op['parameters'][] = array(
							'name'     => $pname,
							'in'       => 'query',
							'required' => ! empty( $d['input']['required'] ) && in_array( $pname, $d['input']['required'], true ),
							'schema'   => $pschema,
							'style'    => 'object' === ( isset( $pschema['type'] ) ? $pschema['type'] : '' ) ? 'deepObject' : 'form',
							'explode'  => true,
						);
					}
				} else {
					$op['requestBody'] = array(
						'required' => true,
						'content'  => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/' . $schemaName . 'Input' ) ) ),
					);
				}
				$cleanPath = preg_replace( '/\(\?P<(\w+)>[^)]*\)/', '{$1}', $path );
				$paths[ $cleanPath ][ strtolower( $m ) ] = $op;
			}
		}
		$paths['/short-links'] = array(
			'post' => array(
				'operationId' => 'shortLinkCreate',
				'summary'     => 'Create a short shareable link for a filtered page.',
				'tags'        => array( 'links' ),
				'requestBody' => array(
					'required' => true,
					'content'  => array( 'application/json' => array( 'schema' => array( 'type' => 'object', 'properties' => array( 'url' => array( 'type' => 'string', 'format' => 'uri' ), 'filter_id' => array( 'type' => 'integer' ) ), 'required' => array( 'url' ) ) ) ),
				),
				'responses'   => array(
					'200' => array( 'description' => 'Short link', 'content' => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/ShortLink' ) ) ) ),
					'429' => array( 'description' => 'Rate limit: 20 a minute per IP.' ),
				),
			),
		);
		$paths['/short-links/{slug}'] = array(
			'get' => array(
				'operationId' => 'shortLinkGet',
				'summary'     => 'Read a short link.',
				'tags'        => array( 'links' ),
				'parameters'  => array( array( 'name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => array( 'type' => 'string' ) ) ),
				'responses'   => array( '200' => array( 'description' => 'Short link', 'content' => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/ShortLink' ) ) ) ), '404' => array( 'description' => 'No such link' ) ),
			),
		);
		$paths['/short-links/{slug}/qr'] = array(
			'get' => array(
				'operationId' => 'shortLinkQr',
				'summary'     => 'QR code (SVG) of a short link.',
				'tags'        => array( 'links' ),
				'parameters'  => array( array( 'name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => array( 'type' => 'string' ) ) ),
				'responses'   => array( '200' => array( 'description' => 'SVG image', 'content' => array( 'image/svg+xml' => array( 'schema' => array( 'type' => 'string' ) ) ) ) ),
			),
		);
		$paths['/openapi'] = array(
			'get' => array( 'operationId' => 'openapi', 'summary' => 'This document.', 'tags' => array( 'meta' ), 'responses' => array( '200' => array( 'description' => 'OpenAPI 3.1 document' ) ) ),
		);
		$paths['/readiness'] = array(
			'get' => array( 'operationId' => 'readiness', 'summary' => 'AI shopping readiness score with a fix line per failed check (manage_options).', 'tags' => array( 'meta' ), 'security' => $security, 'responses' => array( '200' => array( 'description' => 'Score', 'content' => array( 'application/json' => array( 'schema' => array( '$ref' => '#/components/schemas/Readiness' ) ) ) ) ) ),
		);
		$paths['/llms'] = array(
			'post' => array( 'operationId' => 'llmsCreate', 'summary' => 'Create or refresh llms.txt (manage_options).', 'tags' => array( 'meta' ), 'security' => $security, 'responses' => array( '200' => array( 'description' => 'Result' ) ) ),
		);
		$components['ShortLink'] = array(
			'type'       => 'object',
			'properties' => array(
				'slug'      => array( 'type' => 'string' ),
				'short_url' => array( 'type' => 'string', 'format' => 'uri' ),
				'url'       => array( 'type' => 'string', 'format' => 'uri' ),
				'filter_id' => array( 'type' => 'integer' ),
				'params'    => array( 'type' => 'object' ),
				'hits'      => array( 'type' => 'integer' ),
				'created'   => array( 'type' => 'string' ),
				'qr'        => array( 'type' => 'string', 'description' => 'Inline SVG' ),
				'qr_url'    => array( 'type' => 'string', 'format' => 'uri' ),
			),
		);
		$components['Readiness'] = array(
			'type'       => 'object',
			'properties' => array(
				'score'    => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 100 ),
				'checks'   => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'id' => array( 'type' => 'string' ), 'label' => array( 'type' => 'string' ), 'weight' => array( 'type' => 'integer' ), 'points' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string', 'enum' => array( 'pass', 'partial', 'fail' ) ), 'detail' => array( 'type' => 'string' ), 'fix' => array( 'type' => 'string' ) ) ) ),
				'computed' => array( 'type' => 'string' ),
			),
		);
		$mcp = WooBeWoo_PF_Abilities::mcp_endpoint();
		$paths['/mcp'] = array(
			'get'  => array( 'operationId' => 'mcpCard', 'summary' => 'MCP server card: name, protocol versions, endpoint and tool names.', 'tags' => array( 'meta' ), 'responses' => array( '200' => array( 'description' => 'Server card (JSON)' ) ) ),
			'post' => array(
				'operationId' => 'mcp',
				'summary'     => 'MCP endpoint (Streamable HTTP, JSON-RPC 2.0): initialize, ping, tools/list, tools/call. Each ability is a tool.',
				'tags'        => array( 'meta' ),
				'requestBody' => array( 'required' => true, 'content' => array( 'application/json' => array( 'schema' => array( 'type' => 'object', 'description' => 'A JSON-RPC 2.0 request, or an array of them (batch).' ) ) ) ),
				'responses'   => array(
					'200' => array( 'description' => 'JSON-RPC 2.0 response (or an array for a batch)' ),
					'202' => array( 'description' => 'Notification accepted, empty body' ),
					'400' => array( 'description' => 'Invalid JSON or unsupported MCP-Protocol-Version' ),
					'403' => array( 'description' => 'A write tool without a signed in user or a valid X-WPF-Key' ),
					'429' => array( 'description' => 'Rate limit: 120 tool calls a minute per IP for public tools.' ),
				),
			),
		);
		$doc = array(
			'openapi' => '3.1.0',
			'info'    => array(
				'title'       => 'Product Filter for WooCommerce by WBW',
				'version'     => WPF_VERSION,
				'description' => 'Facets, product filtering and short links for this WooCommerce shop. Read operations are public (rate limited); the same operations are WordPress Abilities (woobewoo/*) and are served over MCP by the plugin itself at ' . $mcp['url'] . '.' . ( ! empty( $mcp['adapter']['active'] ) ? ' The WordPress MCP Adapter also serves them at ' . $mcp['adapter']['url'] . '.' : '' ),
			),
			'servers' => array( array( 'url' => rtrim( $base, '/' ) ) ),
			'x-mcp'   => array(
				'endpoint'         => $mcp['url'],
				'transport'        => 'streamable-http',
				'protocolVersions' => isset( $mcp['versions'] ) ? $mcp['versions'] : array( '2025-06-18' ),
				'tools'            => class_exists( 'WooBeWoo_PF_MCP' ) ? array_keys( WooBeWoo_PF_MCP::tools() ) : array(),
				'config'           => array( 'mcpServers' => array( 'product-filter' => array( 'url' => $mcp['url'] ) ) ),
			),
			'tags'    => array(
				array( 'name' => 'read', 'description' => 'Public read operations (120 requests a minute per IP).' ),
				array( 'name' => 'write', 'description' => 'Admin write operations (Pro).' ),
				array( 'name' => 'links', 'description' => 'Short shareable links.' ),
				array( 'name' => 'meta', 'description' => 'Documents and scores.' ),
			),
			'paths'   => $paths,
			'components' => array(
				'schemas'         => $components,
				'securitySchemes' => array(
					'cookieAuth'          => array( 'type' => 'apiKey', 'in' => 'header', 'name' => 'X-WP-Nonce' ),
					'applicationPassword' => array( 'type' => 'http', 'scheme' => 'basic' ),
				),
			),
		);
		return apply_filters( 'woobewoo_pf_openapi', $doc, $base );
	}
}
