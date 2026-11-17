<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Wcnative_Model Class
 *
 * WooCommerce's own filtering (no plugin to turn off):
 *   - classic widgets in widget areas: woocommerce_layered_nav {title, attribute, display_type list|dropdown, query_type and|or},
 *     woocommerce_price_filter, woocommerce_rating_filter, woocommerce_layered_nav_filters {title}, woocommerce_brand_nav
 *     {title, display_type}. One set per widget area.
 *   - the Product Filters block family (woocommerce/product-filters + product-filter-* inner blocks) inside a Product
 *     Collection. One set per post / template. Only non-default attributes are saved; titles are the inner core/heading.
 *   - the older filter blocks (woocommerce/filter-wrapper {filterType, heading} + attribute-filter, price-filter,
 *     stock-filter, rating-filter, active-filters), usually next to woocommerce/all-products. One set per post / template.
 * URLs: filter_<attr>=a,b & query_type_<attr>=or|and, min_price, max_price, rating_filter, filter_stock_status,
 * categories, tags, brands, filter_product_brand.
 *
 * @version 4.0.1
 * @since   4.0.1
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Wcnative_Model extends WooBeWoo_PF_Importer_Base_Model {

	const WIDGETS = array( 'woocommerce_layered_nav', 'woocommerce_price_filter', 'woocommerce_rating_filter', 'woocommerce_layered_nav_filters', 'woocommerce_brand_nav' );

	const PF_BLOCKS = array(
		'woocommerce/product-filters',
		'woocommerce/product-filter-attribute',
		'woocommerce/product-filter-taxonomy',
		'woocommerce/product-filter-price',
		'woocommerce/product-filter-price-slider',
		'woocommerce/product-filter-status',
		'woocommerce/product-filter-rating',
		'woocommerce/product-filter-active',
		'woocommerce/product-filter-removable-chips',
		'woocommerce/product-filter-clear-button',
		'woocommerce/product-filter-checkbox-list',
		'woocommerce/product-filter-chips',
	);

	const OLD_BLOCKS = array(
		'woocommerce/filter-wrapper',
		'woocommerce/attribute-filter',
		'woocommerce/price-filter',
		'woocommerce/stock-filter',
		'woocommerce/rating-filter',
		'woocommerce/active-filters',
	);

	private $postCache = null;

	public function slug() {
		return 'wcnative';
	}

	public function name() {
		return __( 'WooCommerce filter widgets and blocks', 'woo-product-filter' );
	}

	/** WooCommerce itself is never turned off. */
	public function pluginFiles() {
		return array();
	}

	public function installed() {
		return (bool) $this->sets();
	}

	/**
	 * WooCommerce is always on: show "Active" (pluginFiles() stays empty, so the wizard never offers to turn it off).
	 */
	public function detect() {
		$row = parent::detect();
		if ( $row['installed'] ) {
			$row['active'] = true;
			$row['state']  = 'active';
		}
		return $row;
	}

	/* ------------------------------------------------------------------ where they are */

	/**
	 * array( sidebar id => array( array( 'base', 'num', 'instance' ) ) ) of WooCommerce filter widgets in active areas.
	 */
	private function widgetAreas() {
		$out      = array();
		$sidebars = get_option( 'sidebars_widgets', array() );
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'array_version' === $sidebar || ! is_array( $ids ) || 0 === strpos( $sidebar, 'orphaned_widgets' ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				if ( ! preg_match( '/^(.+)-(\d+)$/', (string) $wid, $m ) || ! in_array( $m[1], self::WIDGETS, true ) ) {
					continue;
				}
				$all = get_option( 'widget_' . $m[1], array() );
				if ( ! isset( $all[ (int) $m[2] ] ) ) {
					continue;
				}
				$out[ $sidebar ][] = array( 'base' => $m[1], 'num' => (int) $m[2], 'instance' => (array) $all[ (int) $m[2] ] );
			}
		}
		return $out;
	}

	private function sidebarName( $id ) {
		global $wp_registered_sidebars;
		return isset( $wp_registered_sidebars[ $id ]['name'] ) ? (string) $wp_registered_sidebars[ $id ]['name'] : (string) $id;
	}

	/**
	 * Posts and templates holding filter blocks: array( id => array( 'pf' => blocks, 'old' => blocks ) ) (top-level matches).
	 */
	private function blockPosts() {
		if ( null !== $this->postCache ) {
			return $this->postCache;
		}
		global $wpdb;
		$like = array(
			$wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( '<!-- wp:woocommerce/product-filter' ) . '%' ),
		);
		foreach ( self::OLD_BLOCKS as $name ) {
			$like[] = $wpdb->prepare( 'post_content LIKE %s', '%' . $wpdb->esc_like( '<!-- wp:' . $name ) . '%' );
		}
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache') AND post_status IN ('publish','draft','private','future','pending') AND (" . implode( ' OR ', $like ) . ') ORDER BY ID LIMIT 500' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- parts prepared above, read only
		$out = array();
		foreach ( (array) $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) {
				continue;
			}
			$found = array( 'pf' => array(), 'old' => array(), 'grid' => false );
			$this->collect( parse_blocks( $post->post_content ), $found );
			if ( $found['pf'] || $found['old'] ) {
				$out[ (int) $id ] = $found;
			}
		}
		$this->postCache = $out;
		return $out;
	}

	/**
	 * Top-level filter blocks (a matched block's inner blocks belong to it), as the switch model counts them.
	 */
	private function collect( $blocks, &$found ) {
		foreach ( (array) $blocks as $b ) {
			$name = isset( $b['blockName'] ) ? (string) $b['blockName'] : '';
			if ( in_array( $name, self::PF_BLOCKS, true ) ) {
				$found['pf'][] = $b;
				continue;
			}
			if ( in_array( $name, self::OLD_BLOCKS, true ) ) {
				$found['old'][] = $b;
				continue;
			}
			if ( 'woocommerce/all-products' === $name ) {
				$found['grid'] = true;
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$this->collect( $b['innerBlocks'], $found );
			}
		}
	}

	private function postTitle( $id ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '#' . $id;
		}
		if ( '' !== $post->post_title ) {
			return $post->post_title;
		}
		return in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ? $post->post_name : '#' . $id;
	}

	public function sets() {
		$sets = array();
		foreach ( $this->widgetAreas() as $sidebar => $list ) {
			$name   = $this->sidebarName( $sidebar );
			$title  = false !== stripos( $name, 'shop' ) ? sprintf( /* translators: %s: widget area */ __( '%s widgets', 'woo-product-filter' ), $name ) : sprintf( /* translators: %s: widget area, lower case */ __( 'Shop %s widgets', 'woo-product-filter' ), function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name ) );
			$sets[] = array(
				'id'        => 'w-' . $sidebar,
				'title'     => $title,
				'blocks'    => count( $list ),
				'shortcode' => '',
				'note'      => sprintf( /* translators: %s: widget area */ __( 'WooCommerce filter widgets in "%s"', 'woo-product-filter' ), $name ),
			);
		}
		foreach ( $this->blockPosts() as $id => $found ) {
			$title = $this->postTitle( $id );
			if ( $found['pf'] ) {
				$n = 0;
				foreach ( $found['pf'] as $b ) {
					$n += 'woocommerce/product-filters' === $b['blockName'] ? count( $this->pfItems( $b ) ) : 1;
				}
				$sets[] = array(
					'id'        => 'pf-' . $id,
					'title'     => $title,
					'blocks'    => $n,
					'shortcode' => '',
					'note'      => __( 'Product Filters block', 'woo-product-filter' ),
				);
			}
			if ( $found['old'] ) {
				$sets[] = array(
					'id'        => 'old-' . $id,
					'title'     => $found['pf'] ? sprintf( /* translators: %s: page title */ __( '%s (older filter blocks)', 'woo-product-filter' ), $title ) : $title,
					'blocks'    => count( $this->oldItems( $found['old'] ) ),
					'shortcode' => '',
					'note'      => __( 'Older WooCommerce filter blocks', 'woo-product-filter' ),
				);
			}
		}
		return $sets;
	}

	private function setTitle( $setId ) {
		foreach ( $this->sets() as $s ) {
			if ( (string) $s['id'] === (string) $setId ) {
				return $s['title'];
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ WooCommerce defaults */

	/**
	 * WooCommerce's rule for a Product Filters attribute block without attributeId: the attribute with the most terms.
	 */
	private static function defaultAttributeId() {
		$cached = get_transient( 'wc_block_product_filter_attribute_default_attribute' );
		if ( is_object( $cached ) && ! empty( $cached->attribute_id ) && '0' !== (string) $cached->attribute_id ) {
			return (int) $cached->attribute_id;
		}
		$best = 0;
		$max  = -1;
		foreach ( (array) wc_get_attribute_taxonomies() as $a ) {
			$n = (int) wp_count_terms( array( 'taxonomy' => 'pa_' . $a->attribute_name, 'hide_empty' => false ) );
			if ( $n > $max ) {
				$max  = $n;
				$best = (int) $a->attribute_id;
			}
		}
		return $best;
	}

	private static function attributeTaxonomy( $attributeId ) {
		foreach ( (array) wc_get_attribute_taxonomies() as $a ) {
			if ( (int) $a->attribute_id === (int) $attributeId ) {
				return 'pa_' . $a->attribute_name;
			}
		}
		return '';
	}

	/**
	 * Attribute term order set in WooCommerce (Products > Attributes) => our block settings.
	 */
	private function attributeOrder( $taxonomy, &$settings, &$mapped, &$unmapped ) {
		$orderby = function_exists( 'wc_attribute_orderby' ) ? wc_attribute_orderby( $taxonomy ) : 'menu_order';
		if ( 'name' === $orderby ) {
			$settings['f_sort_by'] = 'asc';
		} elseif ( 'name_num' === $orderby ) {
			$settings['f_sort_by']         = 'asc';
			$settings['f_sort_as_numbers'] = '1';
		} elseif ( 'id' === $orderby ) {
			$this->row( $unmapped, __( 'Term order', 'woo-product-filter' ) . ' (' . $taxonomy . ')', 'id', __( 'Sorting by term ID is not offered; terms keep the WooCommerce order.', 'woo-product-filter' ) );
			return;
		} else {
			$settings['f_order_custom'] = '1';
		}
		$this->row( $mapped, __( 'Term order', 'woo-product-filter' ) . ' (' . $taxonomy . ')', $orderby, isset( $settings['f_order_custom'] ) ? 'f_order_custom' : 'f_sort_by ' . $settings['f_sort_by'] );
	}

	/**
	 * Display word + multi/single => our display; adds the 4.0 look for chips.
	 */
	private static function look( $display, &$settings ) {
		if ( 'buttons' === $display ) {
			$settings['display_type'] = 'chips';
		}
		return $display;
	}

	/* ------------------------------------------------------------------ map */

	public function map( $setId ) {
		$setId = (string) $setId;
		if ( 0 === strpos( $setId, 'w-' ) ) {
			$areas   = $this->widgetAreas();
			$sidebar = substr( $setId, 2 );
			if ( empty( $areas[ $sidebar ] ) ) {
				return new WP_Error( 'wpf_bad_set', __( 'These WooCommerce widgets were not found.', 'woo-product-filter' ) );
			}
			return $this->mapWidgets( $areas[ $sidebar ], $setId );
		}
		if ( preg_match( '/^(pf|old)-(\d+)$/', $setId, $m ) ) {
			$posts = $this->blockPosts();
			$id    = (int) $m[2];
			if ( empty( $posts[ $id ][ $m[1] ] ) ) {
				return new WP_Error( 'wpf_bad_set', __( 'These WooCommerce filter blocks were not found.', 'woo-product-filter' ) );
			}
			return 'pf' === $m[1] ? $this->mapPf( $posts[ $id ], $setId ) : $this->mapOld( $posts[ $id ], $setId );
		}
		return new WP_Error( 'wpf_bad_set', __( 'WooCommerce filter not found.', 'woo-product-filter' ) );
	}

	/* ---------------------------------------------------------- classic widgets */

	private function mapWidgets( $list, $setId ) {
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$main     = array();
		foreach ( $list as $w ) {
			$i     = $w['instance'];
			$title = isset( $i['title'] ) ? trim( (string) $i['title'] ) : '';
			$id    = $w['base'] . '-' . $w['num'];
			switch ( $w['base'] ) {
				case 'woocommerce_layered_nav':
					$attr  = isset( $i['attribute'] ) ? (string) $i['attribute'] : '';
					$tax   = wc_attribute_taxonomy_name( $attr );
					$qt    = isset( $i['query_type'] ) && 'or' === $i['query_type'] ? 'or' : 'and';
					$dt    = isset( $i['display_type'] ) && 'dropdown' === $i['display_type'] ? 'dropdown' : 'list';
					// WooCommerce: the dropdown is multiple only with "OR"; the list toggles several links.
					$disp  = 'dropdown' === $dt ? ( 'or' === $qt ? 'mul_dropdown' : 'dropdown' ) : 'checkbox';
					$label = '' !== $title ? $title : wc_attribute_label( $tax );
					$block = $this->taxonomyBlock( $tax, $label, $disp, $unmapped );
					if ( ! $block ) {
						break;
					}
					$settings = array( 'f_show_count' => '1', 'f_hide_empty' => '1' );
					if ( 'and' === $qt && 'dropdown' !== $disp ) {
						$settings['f_query_logic'] = 'and';
					}
					$this->attributeOrder( $tax, $settings, $mapped, $unmapped );
					$block['settings'] = $settings;
					$block['source']   = sprintf( '%s (%s)', $label, __( 'Filter Products by Attribute widget', 'woo-product-filter' ) );
					$blocks[]          = $block;
					$this->row( $mapped, $id . ' display_type / query_type', $dt . ' / ' . $qt, $disp . ( 'and' === $qt && 'dropdown' !== $disp ? ', f_query_logic and' : ( 'dropdown' === $disp ? ' (one choice, as WooCommerce)' : ', logic or' ) ) );
					$this->row( $mapped, $id . ' counts', __( 'always shown', 'woo-product-filter' ), 'f_show_count 1' );
					$this->row( $mapped, $id . ' empty terms', __( 'hidden', 'woo-product-filter' ), 'f_hide_empty 1' );
					break;
				case 'woocommerce_brand_nav':
					if ( ! taxonomy_exists( 'product_brand' ) ) {
						$this->row( $unmapped, $id, $title, __( 'Brands are not on in WooCommerce here.', 'woo-product-filter' ) );
						break;
					}
					$disp     = isset( $i['display_type'] ) && 'dropdown' === $i['display_type'] ? 'dropdown' : 'checkbox';
					$blocks[] = array(
						'type'     => 'brand',
						'title'    => '' !== $title ? $title : __( 'Brands', 'woo-product-filter' ),
						'display'  => $disp,
						'source'   => sprintf( '%s (%s)', '' !== $title ? $title : __( 'Brands', 'woo-product-filter' ), __( 'Filter Products by Brand widget', 'woo-product-filter' ) ),
						'settings' => array( 'f_show_count' => '1', 'f_hide_empty' => '1' ),
					);
					$this->row( $mapped, $id . ' display_type', isset( $i['display_type'] ) ? $i['display_type'] : 'list', 'wpfBrand / ' . $disp );
					break;
				case 'woocommerce_price_filter':
					$step     = (int) apply_filters( 'woocommerce_price_filter_widget_step', 10 );
					$blocks[] = array(
						'type'     => 'price',
						'title'    => '' !== $title ? $title : __( 'Filter by price', 'woo-product-filter' ),
						'source'   => sprintf( '%s (%s)', '' !== $title ? $title : __( 'Filter by price', 'woo-product-filter' ), __( 'Filter Products by Price widget', 'woo-product-filter' ) ),
						'settings' => array( 'f_step' => (string) max( 1, $step ) ),
					);
					$this->row( $mapped, $id, __( 'price slider, step', 'woo-product-filter' ) . ' ' . $step, 'wpfPrice, f_step ' . $step );
					$this->row( $unmapped, $id . ' ' . __( '"Filter" button', 'woo-product-filter' ), __( 'the price applies when the button is pressed', 'woo-product-filter' ), __( 'Ours applies the price when the slider handle is let go.', 'woo-product-filter' ) );
					break;
				case 'woocommerce_rating_filter':
					$blocks[] = array(
						'type'     => 'rating',
						'title'    => '' !== $title ? $title : __( 'Average rating', 'woo-product-filter' ),
						'source'   => sprintf( '%s (%s)', '' !== $title ? $title : __( 'Average rating', 'woo-product-filter' ), __( 'Filter Products by Rating widget', 'woo-product-filter' ) ),
						'settings' => array( 'f_show_count' => '1' ),
					);
					$this->row( $mapped, $id, __( 'star rows with counts', 'woo-product-filter' ), 'wpfRating, f_show_count 1' );
					$this->row( $unmapped, $id . ' ' . __( 'rating rule', 'woo-product-filter' ), __( 'exactly N stars (rounded average), several can be picked', 'woo-product-filter' ), __( 'Ours shows "N stars and up" (average of N or more).', 'woo-product-filter' ) );
					break;
				case 'woocommerce_layered_nav_filters':
					$main['show_clean_button'] = '1';
					$this->row( $mapped, $id, '' !== $title ? $title : __( 'Active filters', 'woo-product-filter' ), __( 'picked choices show as removable chips in our filter; show_clean_button 1', 'woo-product-filter' ) );
					break;
			}
		}
		$main = array_merge(
			$this->mainFrom( false, true, null, $mapped, array( 'auto_update_filter' => '1', 'show_filtering_button' => '0' ) ),
			$main
		);
		$this->row( $mapped, __( 'Where WooCommerce shows these widgets', 'woo-product-filter' ), __( 'shop and product category / tag / attribute pages', 'woo-product-filter' ), 'display_on_page both' );
		return array(
			'title'    => $this->setTitle( $setId ),
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/* ---------------------------------------------------------- Product Filters block */

	private static function heading( $b ) {
		foreach ( (array) $b['innerBlocks'] as $c ) {
			if ( 'core/heading' === $c['blockName'] ) {
				return trim( wp_strip_all_tags( implode( '', array_filter( (array) $c['innerContent'], 'is_string' ) ) ) );
			}
		}
		return null;
	}

	private static function innerName( $b, $names ) {
		foreach ( (array) $b['innerBlocks'] as $c ) {
			if ( in_array( $c['blockName'], (array) $names, true ) ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Filter blocks inside one Product Filters container, in order.
	 */
	private function pfItems( $container ) {
		$out = array();
		foreach ( (array) $container['innerBlocks'] as $c ) {
			if ( 0 === strpos( (string) $c['blockName'], 'woocommerce/product-filter-' ) ) {
				$out[] = $c;
			}
		}
		return $out;
	}

	private function mapPf( $found, $setId ) {
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$main     = array( 'show_clean_button' => '0' );
		$overlay  = 'mobile';
		foreach ( $found['pf'] as $container ) {
			if ( 'woocommerce/product-filters' !== $container['blockName'] ) {
				$items = array( $container ); // an inner block outside its container
			} else {
				$a = (array) $container['attrs'];
				if ( isset( $a['overlayMode'] ) && in_array( $a['overlayMode'], array( 'off', 'mobile', 'always' ), true ) ) {
					$overlay = $a['overlayMode'];
				} elseif ( isset( $a['showFilterDrawer'] ) && false === $a['showFilterDrawer'] ) {
					$overlay = 'off';
				}
				$h = self::heading( $container );
				if ( null !== $h && '' !== $h ) {
					$this->row( $unmapped, __( 'Heading above the filter', 'woo-product-filter' ), $h, __( 'Our filter has no heading of its own; add a Heading block above it if you want one.', 'woo-product-filter' ) );
				}
				$items = $this->pfItems( $container );
			}
			foreach ( $items as $b ) {
				$block = $this->pfBlock( $b, $main, $mapped, $unmapped );
				if ( $block ) {
					$blocks[] = $block;
				}
			}
		}
		$main = array_merge(
			$this->mainFrom( true, null, 'off' !== $overlay, $mapped, array( 'auto_update_filter' => '1', 'show_filtering_button' => '0' ) ),
			$main
		);
		if ( 'always' === $overlay ) {
			$main['mobile_drawer_always'] = '1';
			$this->row( $mapped, 'overlayMode', 'always', 'mobile_drawer_always 1' );
		} else {
			$this->row( $mapped, 'overlayMode', $overlay, 'mobile_drawer ' . ( 'off' === $overlay ? '0' : '1' ) );
		}
		$this->row( $mapped, __( 'Products grid', 'woo-product-filter' ), __( 'Product Collection block on the page', 'woo-product-filter' ), __( 'kept; our filter sits where the Product Filters block was and drives it', 'woo-product-filter' ) );
		return array(
			'title'    => $this->setTitle( $setId ),
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/**
	 * Product Filters inner style block => our display ('checkbox', 'radio', 'buttons', 'dropdown', 'mul_dropdown').
	 */
	private static function pfDisplay( $b, $a ) {
		$style  = isset( $a['displayStyle'] ) ? (string) $a['displayStyle'] : '';
		$inner  = self::innerName( $b, array( 'woocommerce/product-filter-checkbox-list', 'woocommerce/product-filter-chips', 'woocommerce/product-filter-dropdown' ) );
		$style  = $inner ? $inner['blockName'] : ( '' !== $style ? $style : 'woocommerce/product-filter-checkbox-list' );
		$single = isset( $a['selectType'] ) && 'single' === $a['selectType'];
		if ( 'woocommerce/product-filter-chips' === $style ) {
			return 'buttons';
		}
		if ( 'woocommerce/product-filter-dropdown' === $style ) {
			return $single ? 'dropdown' : 'mul_dropdown';
		}
		return $single ? 'radio' : 'checkbox';
	}

	private function pfBlock( $b, &$main, &$mapped, &$unmapped ) {
		$name  = (string) $b['blockName'];
		$a     = (array) $b['attrs'];
		$title = self::heading( $b );
		$short = str_replace( 'woocommerce/product-filter-', '', $name );
		$counts = ! empty( $a['showCounts'] );
		$hide   = ! isset( $a['hideEmpty'] ) || ! empty( $a['hideEmpty'] );
		$settings = array( 'f_show_count' => $counts ? '1' : '0' );
		if ( null === $title || '' === $title ) {
			$settings['f_enable_title'] = 'no';
		}
		switch ( $name ) {
			case 'woocommerce/product-filter-attribute':
				$attrId = ! empty( $a['attributeId'] ) ? (int) $a['attributeId'] : self::defaultAttributeId();
				$tax    = self::attributeTaxonomy( $attrId );
				if ( empty( $a['attributeId'] ) ) {
					$this->row( $mapped, __( 'Attribute filter without a saved attribute', 'woo-product-filter' ), $tax, __( 'WooCommerce shows the attribute with the most terms; so does ours', 'woo-product-filter' ) );
				}
				if ( '' === $tax ) {
					$this->row( $unmapped, $short, $attrId, __( 'This attribute does not exist on this site.', 'woo-product-filter' ) );
					return null;
				}
				$disp  = self::pfDisplay( $b, $a );
				$label = null !== $title && '' !== $title ? $title : wc_attribute_label( $tax );
				$block = $this->taxonomyBlock( $tax, $label, $disp, $unmapped );
				if ( ! $block ) {
					return null;
				}
				$logic = isset( $a['queryType'] ) && 'and' === $a['queryType'] ? 'and' : 'or';
				if ( 'and' === $logic && in_array( $disp, array( 'checkbox', 'buttons', 'mul_dropdown' ), true ) ) {
					$settings['f_query_logic'] = 'and';
				}
				$this->pfSort( $a, $tax, $settings, $mapped, $unmapped );
				if ( $hide ) {
					$settings['f_hide_empty'] = '1';
				}
				self::look( $disp, $settings );
				$block['settings'] = $settings;
				$block['source']   = sprintf( '%s (%s)', $label, $tax );
				$this->row( $mapped, $label . ' (' . $tax . ')', 'displayStyle ' . $disp . ', queryType ' . $logic . ', showCounts ' . ( $counts ? 'yes' : 'no' ) . ', hideEmpty ' . ( $hide ? 'yes' : 'no' ), $disp . ( isset( $settings['f_query_logic'] ) ? ', f_query_logic and' : '' ) . ', f_show_count ' . $settings['f_show_count'] . ( $hide ? ', f_hide_empty 1' : '' ) );
				return $block;
			case 'woocommerce/product-filter-taxonomy':
				$tax   = isset( $a['taxonomy'] ) && '' !== $a['taxonomy'] ? sanitize_key( $a['taxonomy'] ) : 'product_cat';
				$disp  = self::pfDisplay( $b, $a );
				$tobj  = get_taxonomy( $tax );
				$label = null !== $title && '' !== $title ? $title : ( $tobj ? $tobj->labels->singular_name : $tax );
				$block = $this->taxonomyBlock( $tax, $label, $disp, $unmapped );
				if ( ! $block ) {
					return null;
				}
				$this->pfSort( $a, $tax, $settings, $mapped, $unmapped );
				if ( $hide ) {
					$settings['f_hide_empty'] = '1';
				}
				if ( is_taxonomy_hierarchical( $tax ) && in_array( $disp, array( 'checkbox', 'radio' ), true ) ) {
					$settings['f_show_hierarchical'] = '1';
				}
				self::look( $disp, $settings );
				$block['settings'] = $settings;
				$block['source']   = sprintf( '%s (%s)', $label, $tax );
				$this->row( $mapped, $label . ' (' . $tax . ')', 'displayStyle ' . $disp . ', showCounts ' . ( $counts ? 'yes' : 'no' ) . ', hideEmpty ' . ( $hide ? 'yes' : 'no' ), $block['type'] . ' / ' . $disp . ( isset( $settings['f_show_hierarchical'] ) ? ', f_show_hierarchical 1' : '' ) );
				return $block;
			case 'woocommerce/product-filter-price':
				$slider = self::innerName( $b, array( 'woocommerce/product-filter-price-slider' ) );
				$sa     = $slider ? (array) $slider['attrs'] : array();
				if ( ! isset( $sa['showInputFields'] ) || ! empty( $sa['showInputFields'] ) ) {
					$settings['f_show_inputs'] = '1';
				}
				unset( $settings['f_show_count'] );
				$label = null !== $title && '' !== $title ? $title : __( 'Price', 'woo-product-filter' );
				$this->row( $mapped, $label, __( 'price slider', 'woo-product-filter' ) . ( isset( $settings['f_show_inputs'] ) ? ' + ' . __( 'price boxes', 'woo-product-filter' ) : '' ), 'wpfPrice' . ( isset( $settings['f_show_inputs'] ) ? ', f_show_inputs 1' : '' ) );
				if ( ! empty( $sa['inlineInput'] ) ) {
					$this->row( $unmapped, $label . ' inlineInput', '1', __( 'Price boxes sit under the slider in ours.', 'woo-product-filter' ) );
				}
				return array( 'type' => 'price', 'title' => $label, 'source' => $label, 'settings' => $settings );
			case 'woocommerce/product-filter-status':
				$label = null !== $title && '' !== $title ? $title : __( 'Status', 'woo-product-filter' );
				$statuses = array_keys( wc_get_product_stock_status_options() );
				$settings['f_options[]'] = implode( ',', $statuses );
				$this->row( $mapped, $label, 'showCounts ' . ( $counts ? 'yes' : 'no' ), 'wpfInStock (' . implode( ', ', $statuses ) . ')' );
				if ( 'radio' !== self::pfDisplay( $b, $a ) ) {
					$this->row( $unmapped, $label . ' ' . __( 'several states', 'woo-product-filter' ), 'selectType multiple', __( 'Shoppers pick one stock state at a time in ours.', 'woo-product-filter' ) );
				}
				return array( 'type' => 'stock', 'title' => $label, 'source' => $label, 'settings' => $settings );
			case 'woocommerce/product-filter-rating':
				$label = null !== $title && '' !== $title ? $title : __( 'Rating', 'woo-product-filter' );
				$this->row( $mapped, $label, 'showCounts ' . ( $counts ? 'yes' : 'no' ), 'wpfRating, f_show_count ' . $settings['f_show_count'] );
				$this->row( $unmapped, $label . ' ' . __( 'rating rule', 'woo-product-filter' ), __( 'exactly N stars (rounded average), several can be picked', 'woo-product-filter' ), __( 'Ours shows "N stars and up" (average of N or more).', 'woo-product-filter' ) );
				if ( ! empty( $a['minRating'] ) && '0' !== (string) $a['minRating'] ) {
					$this->row( $unmapped, $label . ' minRating', $a['minRating'], __( 'Lower star rows are always offered in ours.', 'woo-product-filter' ) );
				}
				return array( 'type' => 'rating', 'title' => $label, 'source' => $label, 'settings' => $settings );
			case 'woocommerce/product-filter-active':
				$clear = (bool) self::innerName( $b, array( 'woocommerce/product-filter-clear-button' ) );
				if ( $clear ) {
					$main['show_clean_button'] = '1';
				}
				$this->row( $mapped, null !== $title && '' !== $title ? $title : __( 'Active filters', 'woo-product-filter' ), $clear ? __( 'chips + Clear button', 'woo-product-filter' ) : __( 'chips', 'woo-product-filter' ), __( 'picked choices show as removable chips in our filter', 'woo-product-filter' ) . ( $clear ? '; show_clean_button 1' : '' ) );
				return null;
			case 'woocommerce/product-filter-clear-button':
				$main['show_clean_button'] = '1';
				$this->row( $mapped, __( 'Clear filters button', 'woo-product-filter' ), 'yes', 'show_clean_button 1' );
				return null;
		}
		$this->row( $unmapped, $short, '', __( 'No matching block in ours.', 'woo-product-filter' ) );
		return null;
	}

	private function pfSort( $a, $tax, &$settings, &$mapped, &$unmapped ) {
		$sort = isset( $a['sortOrder'] ) ? (string) $a['sortOrder'] : 'count-desc';
		if ( 'name-asc' === $sort ) {
			$settings['f_sort_by'] = 'asc';
		} elseif ( 'name-desc' === $sort ) {
			$settings['f_sort_by'] = 'desc';
		} else {
			$this->row( $unmapped, 'sortOrder (' . $tax . ')', $sort, __( 'Sorting choices by product count is not offered; ours keeps the WooCommerce term order.', 'woo-product-filter' ) );
			return;
		}
		$this->row( $mapped, 'sortOrder (' . $tax . ')', $sort, 'f_sort_by ' . $settings['f_sort_by'] );
	}

	/* ---------------------------------------------------------- older filter blocks */

	/**
	 * array( array( 'block' => filter block, 'title' => visible title or null ) ) in page order.
	 */
	private function oldItems( $list ) {
		$out = array();
		foreach ( (array) $list as $b ) {
			if ( 'woocommerce/filter-wrapper' === $b['blockName'] ) {
				$inner = null;
				foreach ( (array) $b['innerBlocks'] as $c ) {
					if ( in_array( $c['blockName'], self::OLD_BLOCKS, true ) ) {
						$inner = $c;
					}
				}
				if ( ! $inner ) {
					continue;
				}
				$h = self::heading( $b );
				if ( null === $h && ! empty( $b['attrs']['heading'] ) ) {
					$h = (string) $b['attrs']['heading'];
				}
				$out[] = array( 'block' => $inner, 'title' => $h );
				continue;
			}
			// A filter block saved without its wrapper (WooCommerce before 6.x): the title is its own heading attribute.
			$out[] = array( 'block' => $b, 'title' => isset( $b['attrs']['heading'] ) ? (string) $b['attrs']['heading'] : null );
		}
		return $out;
	}

	private function mapOld( $found, $setId ) {
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$main     = array( 'show_clean_button' => '0' );
		$buttons  = array();
		$items    = $this->oldItems( $found['old'] );
		foreach ( $items as $it ) {
			$b      = $it['block'];
			$a      = (array) $b['attrs'];
			$title  = null !== $it['title'] ? trim( wp_strip_all_tags( $it['title'] ) ) : '';
			$counts = ! empty( $a['showCounts'] );
			$settings = array( 'f_show_count' => $counts ? '1' : '0' );
			if ( '' === $title ) {
				$settings['f_enable_title'] = 'no';
			}
			$buttons[] = ! empty( $a['showFilterButton'] );
			$single    = isset( $a['selectType'] ) && 'single' === $a['selectType'];
			switch ( $b['blockName'] ) {
				case 'woocommerce/attribute-filter':
					$tax = ! empty( $a['attributeId'] ) ? self::attributeTaxonomy( (int) $a['attributeId'] ) : '';
					if ( '' === $tax ) {
						$this->row( $unmapped, '' !== $title ? $title : 'attribute-filter', isset( $a['attributeId'] ) ? $a['attributeId'] : '', __( 'No attribute chosen in the block (WooCommerce shows nothing); left out.', 'woo-product-filter' ) );
						break;
					}
					$dropdown = isset( $a['displayStyle'] ) && 'dropdown' === $a['displayStyle'];
					$disp     = $dropdown ? ( $single ? 'dropdown' : 'mul_dropdown' ) : ( $single ? 'radio' : 'checkbox' );
					$label    = '' !== $title ? $title : wc_attribute_label( $tax );
					$block    = $this->taxonomyBlock( $tax, $label, $disp, $unmapped );
					if ( ! $block ) {
						break;
					}
					$logic = isset( $a['queryType'] ) && 'and' === $a['queryType'] ? 'and' : 'or';
					if ( 'and' === $logic && ! $single ) {
						$settings['f_query_logic'] = 'and';
					}
					$settings['f_hide_empty'] = '1';
					$this->attributeOrder( $tax, $settings, $mapped, $unmapped );
					$block['settings'] = $settings;
					$block['source']   = sprintf( '%s (%s)', $label, $tax );
					$blocks[]          = $block;
					$this->row( $mapped, $label . ' (' . $tax . ')', 'displayStyle ' . ( $dropdown ? 'dropdown' : 'list' ) . ', selectType ' . ( $single ? 'single' : 'multiple' ) . ', queryType ' . $logic . ', showCounts ' . ( $counts ? 'yes' : 'no' ), $disp . ( isset( $settings['f_query_logic'] ) ? ', f_query_logic and' : '' ) . ', f_show_count ' . $settings['f_show_count'] );
					break;
				case 'woocommerce/price-filter':
					unset( $settings['f_show_count'] );
					if ( ! isset( $a['showInputFields'] ) || ! empty( $a['showInputFields'] ) ) {
						$settings['f_show_inputs'] = '1';
					}
					$label    = '' !== $title ? $title : __( 'Filter by price', 'woo-product-filter' );
					$blocks[] = array( 'type' => 'price', 'title' => $label, 'source' => $label, 'settings' => $settings );
					$this->row( $mapped, $label, __( 'price slider', 'woo-product-filter' ) . ( isset( $settings['f_show_inputs'] ) ? ' + ' . __( 'price boxes', 'woo-product-filter' ) : '' ), 'wpfPrice' . ( isset( $settings['f_show_inputs'] ) ? ', f_show_inputs 1' : '' ) );
					if ( ! empty( $a['inlineInput'] ) ) {
						$this->row( $unmapped, $label . ' inlineInput', '1', __( 'Price boxes sit under the slider in ours.', 'woo-product-filter' ) );
					}
					break;
				case 'woocommerce/stock-filter':
					$label = '' !== $title ? $title : __( 'Filter by stock status', 'woo-product-filter' );
					$settings['f_options[]'] = implode( ',', array_keys( wc_get_product_stock_status_options() ) );
					$blocks[] = array( 'type' => 'stock', 'title' => $label, 'source' => $label, 'display' => isset( $a['displayStyle'] ) && 'dropdown' === $a['displayStyle'] ? 'dropdown' : '', 'settings' => $settings );
					$this->row( $mapped, $label, 'showCounts ' . ( $counts ? 'yes' : 'no' ), 'wpfInStock, f_show_count ' . $settings['f_show_count'] );
					if ( ! $single ) {
						$this->row( $unmapped, $label . ' ' . __( 'several states', 'woo-product-filter' ), 'selectType multiple', __( 'Shoppers pick one stock state at a time in ours.', 'woo-product-filter' ) );
					}
					break;
				case 'woocommerce/rating-filter':
					$label    = '' !== $title ? $title : __( 'Filter by rating', 'woo-product-filter' );
					$blocks[] = array( 'type' => 'rating', 'title' => $label, 'source' => $label, 'settings' => $settings );
					$this->row( $mapped, $label, 'showCounts ' . ( $counts ? 'yes' : 'no' ), 'wpfRating, f_show_count ' . $settings['f_show_count'] );
					$this->row( $unmapped, $label . ' ' . __( 'rating rule', 'woo-product-filter' ), __( 'exactly N stars (rounded average), several can be picked', 'woo-product-filter' ), __( 'Ours shows "N stars and up" (average of N or more).', 'woo-product-filter' ) );
					break;
				case 'woocommerce/active-filters':
					$main['show_clean_button'] = '1';
					array_pop( $buttons );
					$this->row( $mapped, '' !== $title ? $title : __( 'Active filters', 'woo-product-filter' ), 'displayStyle ' . ( isset( $a['displayStyle'] ) ? $a['displayStyle'] : 'list' ), __( 'picked choices show as removable chips in our filter; "Clear All" = show_clean_button 1', 'woo-product-filter' ) );
					break;
			}
		}
		// "Go" buttons are per block in WooCommerce, one setting for the whole filter in ours.
		$withButton = count( array_filter( $buttons ) );
		$apply      = $buttons && count( $buttons ) === $withButton;
		if ( $withButton && ! $apply ) {
			$this->row( $unmapped, 'showFilterButton', sprintf( '%d / %d', $withButton, count( $buttons ) ), __( 'Only some blocks had an Apply button; ours applies every pick at once (for a button, turn "Apply as soon as shoppers pick" off by hand).', 'woo-product-filter' ) );
		}
		$main = array_merge(
			$this->mainFrom( (bool) $found['grid'], null, null, $mapped, array( 'auto_update_filter' => $apply ? '0' : '1', 'show_filtering_button' => $apply ? '1' : '0' ) ),
			$main
		);
		if ( $apply ) {
			$this->row( $mapped, 'showFilterButton', __( 'on every block', 'woo-product-filter' ), 'show_filtering_button 1' );
		}
		if ( $found['grid'] ) {
			$this->row( $mapped, __( 'Products grid', 'woo-product-filter' ), __( 'All Products block on the page', 'woo-product-filter' ), __( 'becomes a WooCommerce [products] grid of the same size and order, which our filter drives (the All Products block ignores it)', 'woo-product-filter' ) );
		}
		return array(
			'title'    => $this->setTitle( $setId ),
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/* ------------------------------------------------------------------ placements */

	public function signatures() {
		return array(
			'widgets'    => self::WIDGETS,
			'shortcodes' => array(),
			'blocks'     => array_merge( self::PF_BLOCKS, self::OLD_BLOCKS ),
			'products'   => array(),
			// The All Products block loads its products from the Store API without our args: it becomes a [products] grid.
			'productBlocks' => array( 'woocommerce/all-products' ),
		);
	}

	/**
	 * woocommerce/all-products {columns, rows, orderby} => WooCommerce [products] of the same size and order (our filter drives it).
	 */
	public function blocksReplacement( $blockName, $attrs ) {
		if ( 'woocommerce/all-products' !== $blockName ) {
			return parent::blocksReplacement( $blockName, $attrs );
		}
		$attrs   = (array) $attrs;
		$columns = ! empty( $attrs['columns'] ) ? (int) $attrs['columns'] : 3;
		$rows    = ! empty( $attrs['rows'] ) ? (int) $attrs['rows'] : 3;
		$orders  = array(
			'date'       => array( 'date', 'DESC' ),
			'popularity' => array( 'popularity', 'DESC' ),
			'price_asc'  => array( 'price', 'ASC' ),
			'price_desc' => array( 'price', 'DESC' ),
			'rating'     => array( 'rating', 'DESC' ),
			'title'      => array( 'title', 'ASC' ),
			'menu_order' => array( 'menu_order', 'ASC' ),
		);
		$order = isset( $attrs['orderby'], $orders[ $attrs['orderby'] ] ) ? $orders[ $attrs['orderby'] ] : $orders['date']; // block default: newest first
		return '[products paginate="true" limit="' . ( $columns * $rows ) . '" columns="' . $columns . '" orderby="' . $order[0] . '" order="' . $order[1] . '"]';
	}

	/**
	 * Widget => 'w-<area>', block => 'pf-<post>' / 'old-<post>', from where the switch model is looking ($ctx sidebar / post).
	 * Without $ctx: the only area / post that holds it, else '' (offered with every set).
	 */
	public function setOfPlacement( $kind, $name, $data, $ctx = array() ) {
		$data = (array) $data;
		$ctx  = (array) $ctx;
		if ( 'widget' === $kind && in_array( $name, self::WIDGETS, true ) ) {
			if ( ! empty( $ctx['sidebar'] ) ) {
				return 'w-' . $ctx['sidebar'];
			}
			$hits = array();
			foreach ( $this->widgetAreas() as $sidebar => $list ) {
				foreach ( $list as $w ) {
					if ( $w['base'] === $name && $w['instance'] == $data ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- same keys and values
						$hits[ $sidebar ] = true;
					}
				}
			}
			return 1 === count( $hits ) ? 'w-' . key( $hits ) : '';
		}
		if ( 'block' !== $kind ) {
			return '';
		}
		$family = in_array( $name, self::PF_BLOCKS, true ) ? 'pf' : ( in_array( $name, self::OLD_BLOCKS, true ) ? 'old' : '' );
		if ( '' === $family ) {
			return '';
		}
		if ( ! empty( $ctx['post'] ) ) {
			return $family . '-' . (int) $ctx['post'];
		}
		$posts = array_filter(
			$this->blockPosts(),
			function ( $f ) use ( $family ) {
				return ! empty( $f[ $family ] );
			}
		);
		return 1 === count( $posts ) ? $family . '-' . key( $posts ) : '';
	}

	/* ------------------------------------------------------------------ old WooCommerce links */

	/**
	 * Our filter on this request: the imported filter of a live switch whose place is this page (widgets: shop and
	 * product archives). Returns its blocks keyed by query key (color, size, category, ...) => logic.
	 */
	private function liveBlocksHere() {
		$log  = get_option( 'wpf_switch_log', array() );
		$here = 0;
		foreach ( (array) $log as $snap ) {
			if ( ! is_array( $snap ) || ! empty( $snap['undone'] ) || 'wcnative' !== ( isset( $snap['rival'] ) ? $snap['rival'] : '' ) ) {
				continue;
			}
			$set = (string) $snap['set'];
			if ( preg_match( '/^(pf|old)-(\d+)$/', $set, $m ) && is_singular() && (int) get_queried_object_id() === (int) $m[2] ) {
				// The All Products block next to the older filter blocks loads its products from the Store API without
				// our args, so a link sent there would show every product: leave those links to WooCommerce.
				if ( 'old' === $m[1] && has_block( 'woocommerce/all-products', (int) $m[2] ) ) {
					return null;
				}
				$here = (int) $snap['filter'];
				break;
			}
			if ( 0 === strpos( $set, 'w-' ) && function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
				$here = (int) $snap['filter'];
			}
		}
		if ( ! $here ) {
			return null;
		}
		$row = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( $here );
		if ( ! $row ) {
			return null;
		}
		$s     = woobewoo_pf_decode_settings( $row['setting_data'] );
		$order = isset( $s['settings']['filters']['order'] ) ? json_decode( (string) $s['settings']['filters']['order'], true ) : array();
		$out   = array();
		foreach ( (array) $order as $b ) {
			$st    = isset( $b['settings'] ) ? (array) $b['settings'] : array();
			$logic = isset( $st['logic'] ) ? (string) $st['logic'] : 'or';
			if ( isset( $st['f_query_logic'] ) && 'and' === $st['f_query_logic'] ) {
				$logic = 'and';
			}
			$key = '';
			if ( 'wpfAttribute' === $b['id'] && ! empty( $st['f_list'] ) ) {
				$key = (string) wc_attribute_taxonomy_name_by_id( (int) $st['f_list'] );
				$key = preg_replace( '/^pa_/', '', $key );
			} elseif ( 'wpfCategory' === $b['id'] ) {
				$key = 'category';
			} elseif ( 'wpfTags' === $b['id'] ) {
				$key = 'tag';
			} elseif ( 'wpfBrand' === $b['id'] ) {
				$key = 'brand';
			} elseif ( 'wpfPrice' === $b['id'] || 'wpfPriceRange' === $b['id'] ) {
				$key = 'price';
			} elseif ( 'wpfInStock' === $b['id'] ) {
				$key = 'stock';
			}
			if ( '' !== $key ) {
				$out[ $key ] = $logic;
			}
		}
		return $out;
	}

	private static function slugList( $raw ) {
		$out = array();
		foreach ( explode( ',', (string) $raw ) as $v ) {
			$v = sanitize_title( urldecode( trim( $v ) ) );
			if ( '' !== $v ) {
				$out[] = $v;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * WooCommerce keeps answering filter_<attr> / query_type_<attr> / min_price / max_price / categories / tags /
	 * filter_stock_status itself, so a link is sent to ours only on a page where our switched filter now sits and only
	 * when ours gives the same products: every picked block exists in our filter with the same AND / OR, one stock
	 * state other than instock, no price and no rating (see below). Anything else is left to WooCommerce.
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		$keys  = array_filter(
			array_keys( $query ),
			function ( $k ) {
				return 0 === strpos( (string) $k, 'filter_' ) || in_array( (string) $k, array( 'min_price', 'max_price', 'categories', 'tags', 'brands', 'rating_filter' ), true );
			}
		);
		if ( ! $keys || isset( $query['rating_filter'] ) ) {
			return null;
		}
		$ours = $this->liveBlocksHere();
		if ( ! $ours ) {
			return null;
		}
		$args = array();
		foreach ( $query as $k => $v ) {
			$k = (string) $k;
			if ( ! is_scalar( $v ) ) {
				return null;
			}
			if ( 'filter_stock_status' === $k ) {
				$v = self::slugList( $v );
				// instock is left out: our "in" also counts products on backorder.
				$m = array( 'outofstock' => 'out', 'onbackorder' => 'backorder' );
				if ( 1 !== count( $v ) || ! isset( $m[ $v[0] ], $ours['stock'] ) ) {
					return null;
				}
				$args['wpf_stock'] = $m[ $v[0] ];
				continue;
			}
			$tax = '';
			if ( 'categories' === $k ) {
				$tax = 'category';
			} elseif ( 'tags' === $k ) {
				$tax = 'tag';
			} elseif ( 'brands' === $k || 'filter_product_brand' === $k ) {
				$tax = 'brand';
			} elseif ( 0 === strpos( $k, 'filter_' ) && taxonomy_exists( 'pa_' . substr( $k, 7 ) ) ) {
				$tax = substr( $k, 7 );
			} elseif ( 0 === strpos( $k, 'filter_' ) ) {
				return null; // a filter we do not know
			}
			if ( '' === $tax ) {
				continue;
			}
			$slugs = self::slugList( $v );
			if ( ! $slugs ) {
				continue;
			}
			if ( ! isset( $ours[ $tax ] ) ) {
				return null;
			}
			if ( count( $slugs ) > 1 ) {
				// Attributes: query_type_<attr> (missing = AND). Categories / tags / brands of the Product Filters block are OR.
				$theirs = in_array( $tax, array( 'category', 'tag', 'brand' ), true ) ? 'or' : ( isset( $query[ 'query_type_' . $tax ] ) && 'or' === $query[ 'query_type_' . $tax ] ? 'or' : 'and' );
				$mine   = 'and' === $ours[ $tax ] ? 'and' : 'or';
				if ( 'single' === $ours[ $tax ] || $theirs !== $mine ) {
					return null;
				}
			}
			$args[ 'wpf_' . $tax ] = implode( ',', $slugs );
		}
		// min_price / max_price stay with WooCommerce: it matches a grouped or variable product whose price range overlaps
		// the picked range, ours does not, so the same link would not give the same products.
		if ( isset( $query['min_price'] ) || isset( $query['max_price'] ) ) {
			return null;
		}
		if ( ! $args ) {
			return null;
		}
		if ( ! empty( $query['orderby'] ) && is_scalar( $query['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $query['orderby'] );
		}
		return array( 'path' => (string) $path, 'args' => $args );
	}
}
