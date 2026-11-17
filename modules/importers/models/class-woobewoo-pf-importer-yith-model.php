<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Yith_Model Class
 *
 * YITH WooCommerce Ajax Product Filter (5.x, free and premium).
 *
 * Presets are posts of type `yith_wcan_preset` (only "publish" ones are loaded by YITH). Post meta:
 * `_enabled` yes|no, `_layout` (default|horizontal, premium), `_selector` (premium) and `_filters`:
 * an array of filters, keyed by filter id (1..n) for presets saved in the admin, a 0-based list for
 * the "Default preset" created on activation. Each filter stores every key, premium ones included:
 * type (tax in free), title, taxonomy, use_all_terms, terms (term_id => label, tooltip, color_1,
 * color_2, image, mode), customize_terms, filter_design (checkbox|select|text|color|label),
 * label_position, column_number, hierarchical (no|parents_only|open), multiple, relation (and|or,
 * stored even on single-select filters), enabled, show_count, show_toggle, toggle_style, order_by,
 * order, adoptive ... The free version has no UI for counts, toggles, sort order or adoptive, so
 * those keys always hold their defaults there.
 *
 * Plugin wide switches are separate options: yith_wcan_show_reset, yith_wcan_reset_button_position,
 * yith_wcan_lazy_load_filters, yith_wcan_paginate_terms, yith_wcan_hide_empty_terms and the SEO ones.
 * The free version always filters by AJAX and at once.
 *
 * Placements: preset widget `yith-woocommerce-ajax-navigation-filters` ({preset: slug}),
 * shortcode [yith_wcan_filters slug=""], block yith/yith-wcan-ajax-filters-preset ({slug}),
 * Elementor widget yith_wcan_filters. The pre 4.0 per-attribute widgets `yith-woo-ajax-navigation`
 * (+ the reset widget `yith-woo-ajax-reset-navigation`) are still registered: each widget area that
 * holds them is offered as its own set, "Sidebar widgets".
 *
 * @version 4.0.1
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Yith_Model extends WooBeWoo_PF_Importer_Base_Model {

	const CPT          = 'yith_wcan_preset';
	const W_PRESET     = 'yith-woocommerce-ajax-navigation-filters';
	const W_LEGACY     = 'yith-woo-ajax-navigation';
	const W_RESET      = 'yith-woo-ajax-reset-navigation';
	const SC_PRESET    = 'yith_wcan_filters';
	const BLOCK_PRESET = 'yith/yith-wcan-ajax-filters-preset';
	const LEGACY_SET   = 'widgets-';

	/** Colour the YITH colour picker writes when nothing was picked. */
	const PICKER_DEFAULT = '#007694';

	private $placementCache = null;

	public function slug() {
		return 'yith';
	}

	public function name() {
		return 'YITH WooCommerce Ajax Product Filter';
	}

	public function pluginFiles() {
		return array( 'yith-woocommerce-ajax-navigation/init.php', 'yith-woocommerce-ajax-product-filter-premium/init.php' );
	}

	public function installed() {
		global $wpdb;
		$posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash','auto-draft')", self::CPT ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $posts > 0 ) {
			return true;
		}
		return (bool) $this->legacySidebars();
	}

	/* ------------------------------------------------------------------ reading their data */

	/**
	 * Published presets (YITH loads nothing else).
	 */
	private function presets() {
		return get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);
	}

	private function filtersOf( $postId ) {
		$filters = get_post_meta( $postId, '_filters', true );
		return is_array( $filters ) ? $filters : array();
	}

	private function presetEnabled( $postId ) {
		return 'no' !== get_post_meta( $postId, '_enabled', true );
	}

	/**
	 * Preset id by its slug (all placements point at the slug), or 0.
	 */
	private function presetIdBySlug( $slug ) {
		global $wpdb;
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_name = %s ORDER BY ID ASC LIMIT 1", self::CPT, $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	private static function isYes( $v ) {
		return in_array( $v, array( 'yes', true, 1, '1', 'on' ), true );
	}

	/**
	 * Their option, read the way yith_wcan_get_option() does (new option, then the legacy array, then the code default).
	 */
	private function option( $name, $default ) {
		$v = get_option( $name, null );
		if ( null !== $v && false !== $v && '' !== $v ) {
			return $v;
		}
		$legacy = get_option( 'yit_wcan_options', array() );
		return is_array( $legacy ) && isset( $legacy[ $name ] ) ? $legacy[ $name ] : $default;
	}

	private function sidebarName( $id ) {
		global $wp_registered_sidebars;
		return isset( $wp_registered_sidebars[ $id ]['name'] ) ? $wp_registered_sidebars[ $id ]['name'] : $id;
	}

	private static function widgetNum( $widgetId, $base ) {
		return preg_match( '/^' . preg_quote( $base, '/' ) . '-(\d+)$/', (string) $widgetId, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Widget areas holding the legacy per-attribute widgets: array( sidebar => array( 'nav' => array( num => instance ), 'reset' => array( num => instance ) ) ).
	 */
	private function legacySidebars() {
		$out      = array();
		$sidebars = get_option( 'sidebars_widgets', array() );
		$nav      = get_option( 'widget_' . self::W_LEGACY, array() );
		$reset    = get_option( 'widget_' . self::W_RESET, array() );
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'array_version' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			$row = array( 'nav' => array(), 'reset' => array() );
			foreach ( $ids as $wid ) {
				$n = self::widgetNum( $wid, self::W_LEGACY );
				if ( $n && isset( $nav[ $n ] ) && is_array( $nav[ $n ] ) ) {
					$row['nav'][ $n ] = $nav[ $n ];
					continue;
				}
				$n = self::widgetNum( $wid, self::W_RESET );
				if ( $n && isset( $reset[ $n ] ) && is_array( $reset[ $n ] ) ) {
					$row['reset'][ $n ] = $reset[ $n ];
				}
			}
			if ( $row['nav'] ) {
				$out[ $sidebar ] = $row;
			}
		}
		return $out;
	}

	/**
	 * Where each set is shown today: array( setId => array( labels ) ). Elementor widgets are listed apart
	 * (array( setId => array( page titles ) ) under the 'elementor' key) because the swap cannot reach them.
	 */
	private function placements() {
		if ( null !== $this->placementCache ) {
			return $this->placementCache;
		}
		global $wpdb;
		$out      = array( 'sets' => array(), 'elementor' => array() );
		$sidebars = get_option( 'sidebars_widgets', array() );
		$pw       = get_option( 'widget_' . self::W_PRESET, array() );
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				$n = self::widgetNum( $wid, self::W_PRESET );
				if ( $n && ! empty( $pw[ $n ]['preset'] ) ) {
					$id = $this->presetIdBySlug( $pw[ $n ]['preset'] );
					if ( $id ) {
						/* translators: %s: widget area name */
						$out['sets'][ (string) $id ][] = sprintf( __( 'widget in %s', 'woo-product-filter' ), $this->sidebarName( $sidebar ) );
					}
				}
			}
		}
		foreach ( array_keys( $this->legacySidebars() ) as $sidebar ) {
			/* translators: %s: widget area name */
			$out['sets'][ self::LEGACY_SET . $sidebar ][] = sprintf( __( 'widgets in %s', 'woo-product-filter' ), $this->sidebarName( $sidebar ) );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status IN ('publish','draft','private','future','pending') AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 500", '%[' . $wpdb->esc_like( self::SC_PRESET ) . '%', '%' . $wpdb->esc_like( 'wp:' . self::BLOCK_PRESET ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $r ) {
			$slugs = array();
			if ( preg_match_all( '/' . get_shortcode_regex( array( self::SC_PRESET ) ) . '/', $r['post_content'], $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					$atts = (array) shortcode_parse_atts( $m[3] );
					if ( ! empty( $atts['slug'] ) ) {
						$slugs[] = $atts['slug'];
					}
				}
			}
			if ( preg_match_all( '#<!--\s+wp:' . preg_quote( self::BLOCK_PRESET, '#' ) . '\s+(\{.*?\})\s+/?-->#', $r['post_content'], $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					$a = json_decode( $m[1], true );
					if ( ! empty( $a['slug'] ) ) {
						$slugs[] = $a['slug'];
					}
				}
			}
			foreach ( array_unique( $slugs ) as $slug ) {
				$id = $this->presetIdBySlug( $slug );
				if ( $id ) {
					$out['sets'][ (string) $id ][] = '' !== $r['post_title'] ? $r['post_title'] : '#' . $r['ID'];
				}
			}
		}
		$el = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value, p.post_title FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND p.post_type <> 'revision' AND m.meta_value LIKE %s LIMIT 200", '%' . $wpdb->esc_like( '"widgetType":"' . self::SC_PRESET . '"' ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $el as $r ) {
			if ( preg_match_all( '/"widgetType":"' . self::SC_PRESET . '"/', $r['meta_value'], $unused ) && preg_match_all( '/"slug":"([^"]+)"/', $r['meta_value'], $sl ) ) {
				foreach ( array_unique( $sl[1] ) as $slug ) {
					$id = $this->presetIdBySlug( $slug );
					if ( $id ) {
						$out['elementor'][ (string) $id ][] = $r['post_title'];
					}
				}
			}
		}
		$this->placementCache = $out;
		return $out;
	}

	/* ------------------------------------------------------------------ sets */

	public function sets() {
		$out    = array();
		$places = $this->placements();
		foreach ( $this->presets() as $p ) {
			$id    = (string) $p->ID;
			$where = isset( $places['sets'][ $id ] ) ? array_unique( $places['sets'][ $id ] ) : array();
			$notes = array();
			if ( ! $this->presetEnabled( $p->ID ) ) {
				$notes[] = __( 'Disabled in YITH: it shows nothing today.', 'woo-product-filter' );
			}
			if ( $where ) {
				/* translators: %s: list of places */
				$notes[] = sprintf( __( 'Shown in: %s.', 'woo-product-filter' ), implode( ', ', $where ) );
			} elseif ( empty( $places['elementor'][ $id ] ) ) {
				$notes[] = __( 'Not shown anywhere.', 'woo-product-filter' );
			}
			if ( ! empty( $places['elementor'][ $id ] ) ) {
				/* translators: %s: page titles */
				$notes[] = sprintf( __( 'YITH Elementor widget on: %s (replace it by hand with our Elementor widget).', 'woo-product-filter' ), implode( ', ', array_unique( $places['elementor'][ $id ] ) ) );
			}
			$out[] = array(
				'id'        => $id,
				'title'     => '' !== $p->post_title ? $p->post_title : sprintf( 'Preset #%d', $p->ID ),
				'blocks'    => count( $this->filtersOf( $p->ID ) ),
				'shortcode' => '[' . self::SC_PRESET . ' slug="' . $p->post_name . '"]',
				'note'      => implode( ' ', $notes ),
			);
		}
		$legacy = $this->legacySidebars();
		foreach ( $legacy as $sidebar => $row ) {
			$titles = array();
			foreach ( $row['nav'] as $inst ) {
				$titles[] = ! empty( $inst['title'] ) ? $inst['title'] : ( isset( $inst['attribute'] ) ? $inst['attribute'] : '' );
			}
			$out[] = array(
				'id'        => self::LEGACY_SET . $sidebar,
				'title'     => count( $legacy ) > 1 ? sprintf( '%s (%s)', __( 'Sidebar widgets', 'woo-product-filter' ), $this->sidebarName( $sidebar ) ) : __( 'Sidebar widgets', 'woo-product-filter' ),
				'blocks'    => count( $row['nav'] ),
				'shortcode' => '',
				/* translators: 1: widget area name, 2: widget titles */
				'note'      => sprintf( __( 'Older YITH "AJAX Product Filter" widgets in %1$s: %2$s.', 'woo-product-filter' ), $this->sidebarName( $sidebar ), implode( ', ', array_filter( $titles ) ) ) . ( $row['reset'] ? ' ' . __( 'With a reset widget.', 'woo-product-filter' ) : '' ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ mapping */

	public function map( $setId ) {
		$setId = (string) $setId;
		if ( 0 === strpos( $setId, self::LEGACY_SET ) ) {
			return $this->mapLegacy( substr( $setId, strlen( self::LEGACY_SET ) ) );
		}
		$post = get_post( (int) $setId );
		if ( ! $post || self::CPT !== $post->post_type ) {
			return new WP_Error( 'wpf_bad_set', __( 'Preset not found.', 'woo-product-filter' ) );
		}
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$isPro    = (bool) apply_filters( 'woobewoo_pf_is_pro', false );
		$premium  = $this->premiumActive();
		$hideAll  = self::isYes( $this->option( 'yith_wcan_hide_empty_terms', 'no' ) );

		if ( ! $this->presetEnabled( $post->ID ) ) {
			$this->row( $unmapped, __( 'Preset switched off', 'woo-product-filter' ), 'no', __( 'YITH shows nothing for this preset today; ours will show where you place it.', 'woo-product-filter' ) );
		}
		$layout = get_post_meta( $post->ID, '_layout', true );
		if ( 'horizontal' === $layout ) {
			$this->row( $unmapped, __( 'Layout', 'woo-product-filter' ), 'horizontal', __( 'Pick "Above the products" when you place the filter; blocks then run across.', 'woo-product-filter' ) );
		}

		foreach ( $this->filtersOf( $post->ID ) as $fid => $f ) {
			$f     = (array) $f;
			$type  = isset( $f['type'] ) ? $f['type'] : 'tax';
			$title = isset( $f['title'] ) ? wp_specialchars_decode( (string) $f['title'], ENT_QUOTES ) : '';
			$label = '' !== $title ? $title : sprintf( '#%s', $fid );
			if ( isset( $f['enabled'] ) && ! self::isYes( $f['enabled'] ) ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: switched off', 'woo-product-filter' ), $label ), 'no', __( 'Disabled in YITH, so it is not imported.', 'woo-product-filter' ) );
				continue;
			}
			switch ( $type ) {
				case 'tax':
					$block = $this->mapTax( $f, $label, $isPro, $premium, $hideAll, $mapped, $unmapped );
					if ( $block ) {
						$blocks[] = $block;
					}
					break;
				case 'price_slider':
					$blocks[] = array( 'type' => 'price', 'title' => '' !== $title ? $title : __( 'Price', 'woo-product-filter' ), 'source' => $label, 'display' => 'slider' );
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: price slider', 'woo-product-filter' ), $label ), 'price_slider', 'wpfPrice' );
					break;
				case 'price_range':
					$ranges   = array();
					foreach ( (array) ( isset( $f['price_ranges'] ) ? $f['price_ranges'] : array() ) as $r ) {
						$r = (array) $r;
						$min = isset( $r['min'] ) ? (float) $r['min'] : 0;
						$max = ! empty( $r['unlimited'] ) && self::isYes( $r['unlimited'] ) ? '' : ( isset( $r['max'] ) ? (float) $r['max'] : '' );
						$ranges[] = $min . '-' . $max;
					}
					$settings = $ranges ? array( 'f_range_by_hands' => '1', 'f_range_by_hands_values' => implode( ';', $ranges ) ) : array();
					$settings['display_type'] = 'range_list';
					$blocks[] = array( 'type' => 'price_range', 'title' => '' !== $title ? $title : __( 'Price', 'woo-product-filter' ), 'source' => $label, 'settings' => $settings );
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: price ranges', 'woo-product-filter' ), $label ), count( $ranges ) . ' ranges', 'wpfPriceRange f_range_by_hands_values' );
					break;
				case 'review':
					$blocks[] = array( 'type' => 'rating', 'title' => '' !== $title ? $title : __( 'Rating', 'woo-product-filter' ), 'source' => $label );
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: rating', 'woo-product-filter' ), $label ), 'review', 'wpfRating' );
					break;
				case 'stock_sale':
					if ( ! isset( $f['show_stock_filter'] ) || self::isYes( $f['show_stock_filter'] ) ) {
						$blocks[] = array( 'type' => 'stock', 'title' => __( 'Stock', 'woo-product-filter' ), 'source' => $label );
					}
					if ( ! isset( $f['show_sale_filter'] ) || self::isYes( $f['show_sale_filter'] ) ) {
						$blocks[] = array( 'type' => 'onsale', 'title' => __( 'On sale', 'woo-product-filter' ), 'source' => $label );
					}
					if ( isset( $f['show_featured_filter'] ) && self::isYes( $f['show_featured_filter'] ) ) {
						$blocks[] = array( 'type' => 'featured', 'title' => __( 'Featured', 'woo-product-filter' ), 'source' => $label );
					}
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: stock / sale', 'woo-product-filter' ), $label ), 'stock_sale', 'wpfInStock / wpfOnSale / wpfFeatured' );
					break;
				case 'orderby':
					$blocks[] = array( 'type' => 'sortby', 'title' => '' !== $title ? $title : __( 'Sort by', 'woo-product-filter' ), 'source' => $label );
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: sort by', 'woo-product-filter' ), $label ), 'orderby', 'wpfSortBy' );
					break;
				default:
					/* translators: %s: filter name */
					$this->row( $unmapped, sprintf( __( '%s: filter type', 'woo-product-filter' ), $label ), $type, __( 'No matching block in Product Filter.', 'woo-product-filter' ) );
			}
		}

		$main = $this->mainFromOptions( $premium, $mapped, $unmapped );
		$selector = get_post_meta( $post->ID, '_selector', true );
		if ( $selector ) {
			$main['product_container_selector'] = sanitize_text_field( $selector );
			$this->row( $mapped, __( 'Product list selector', 'woo-product-filter' ), $selector, 'product_container_selector' );
		}
		return array(
			'title'    => '' !== $post->post_title ? $post->post_title : 'YITH preset #' . $post->ID,
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	private function premiumActive() {
		return defined( 'YITH_WCAN_PREMIUM' ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'yith-woocommerce-ajax-product-filter-premium/init.php' ) );
	}

	/**
	 * Display for a YITH design (swatches and chips are drawn by the 4.0 engine in Free and Pro).
	 */
	private function displayFor( $design, $multiple, $isPro, $label, &$mapped, &$unmapped ) {
		switch ( $design ) {
			case 'select':
				return $multiple ? 'mul_dropdown' : 'dropdown';
			case 'text':
				// Plain links: the closest free look is a radio list (one pick) or tick boxes.
				/* translators: %s: filter name */
				$this->row( $mapped, sprintf( __( '%s: plain text links', 'woo-product-filter' ), $label ), 'text', $multiple ? __( 'tick boxes', 'woo-product-filter' ) : __( 'radio list', 'woo-product-filter' ) );
				return $multiple ? 'checkbox' : 'radio';
			case 'color':
			case 'label':
				// The 4.0 engine draws swatches and chips in Free too.
				return 'color' === $design ? 'colors' : 'buttons';
			default:
				return $multiple ? 'checkbox' : 'radio';
		}
	}

	/**
	 * One taxonomy filter => block (or null).
	 */
	private function mapTax( $f, $label, $isPro, $premium, $hideAll, &$mapped, &$unmapped ) {
		$tax      = isset( $f['taxonomy'] ) ? (string) $f['taxonomy'] : '';
		$design   = isset( $f['filter_design'] ) ? (string) $f['filter_design'] : 'checkbox';
		$multiple = isset( $f['multiple'] ) && self::isYes( $f['multiple'] );
		$display  = $this->displayFor( $design, $multiple, $isPro, $label, $mapped, $unmapped );
		$block    = $this->taxonomyBlock( $tax, isset( $f['title'] ) ? wp_specialchars_decode( (string) $f['title'], ENT_QUOTES ) : '', $display, $unmapped );
		if ( ! $block ) {
			return null;
		}
		$block['source'] = $label;
		$s               = array();
		$this->row( $mapped, $label, $tax . ' / ' . $design . ( $multiple ? ' / multiple' : ' / one pick' ), $block['type'] . ' / ' . $display );

		// Picking: one value, or several with any/all logic. "relation" is stored on single-select filters too and means nothing there.
		if ( $multiple ) {
			$relation = isset( $f['relation'] ) && 'or' === strtolower( (string) $f['relation'] ) ? 'or' : 'and';
			$s['f_query_logic'] = $relation;
			if ( 'category' === $block['type'] ) {
				$s['f_multi_logic'] = $relation;
			}
			if ( 'buttons' === $display ) {
				$s['f_buttons_multiselect'] = '1';
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: several picks match', 'woo-product-filter' ), $label ), $relation, 'or' === $relation ? __( 'any picked value', 'woo-product-filter' ) : __( 'all picked values', 'woo-product-filter' ) );
		} else {
			$s['f_single_select'] = '1';
			if ( 'colors' === $display ) {
				$s['f_colors_singleselect'] = '1';
			}
		}

		// Values shown: all (also future ones) or the picked list in the owner's order.
		$terms   = isset( $f['terms'] ) && is_array( $f['terms'] ) ? $f['terms'] : array();
		$useAll  = isset( $f['use_all_terms'] ) && self::isYes( $f['use_all_terms'] );
		$include = array();
		if ( ! $useAll && $terms ) {
			foreach ( array_keys( $terms ) as $tid ) {
				if ( term_exists( (int) $tid ) ) {
					$include[] = (int) $tid;
				}
			}
		}
		$hier = isset( $f['hierarchical'] ) ? (string) $f['hierarchical'] : 'no';
		if ( 'parents_only' === $hier && is_taxonomy_hierarchical( $tax ) ) {
			// Only top level values, flat.
			$top = get_terms( array( 'taxonomy' => $tax, 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' ) );
			$top = is_wp_error( $top ) ? array() : array_map( 'intval', $top );
			$include = $include ? array_values( array_intersect( $include, $top ) ) : $top;
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: only parent values', 'woo-product-filter' ), $label ), 'parents_only', 'f_mlist[] = top level values' );
		} elseif ( is_taxonomy_hierarchical( $tax ) ) {
			if ( 'open' === $hier ) {
				$s['f_show_hierarchical'] = '1';
				if ( $include ) {
					/* translators: %s: filter name */
					$this->row( $unmapped, sprintf( __( '%s: hierarchy', 'woo-product-filter' ), $label ), 'open', __( 'With a picked list of values ours shows them flat, not as a tree.', 'woo-product-filter' ) );
				} else {
					/* translators: %s: filter name */
					$this->row( $mapped, sprintf( __( '%s: hierarchy', 'woo-product-filter' ), $label ), 'open', 'f_show_hierarchical (tree)' );
				}
			} elseif ( ! $include ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: hierarchy', 'woo-product-filter' ), $label ), __( 'all levels in one flat list', 'woo-product-filter' ), __( 'Ours always shows every level of categories as a tree.', 'woo-product-filter' ) );
			}
		}
		if ( $include ) {
			$s['f_mlist[]'] = implode( ',', $include );
			if ( 'parents_only' !== $hier ) {
				/* translators: %s: filter name */
				$this->row( $mapped, sprintf( __( '%s: values shown', 'woo-product-filter' ), $label ), count( $include ) . ' picked', 'f_mlist[] (others hidden)' );
			}
		}

		// Order of the values. YITH free sorts by name; premium can also follow the picked order or counts.
		$orderBy = isset( $f['order_by'] ) ? (string) $f['order_by'] : 'name';
		$order   = isset( $f['order'] ) && 'desc' === strtolower( (string) $f['order'] ) ? 'desc' : 'asc';
		if ( 'include' === $orderBy && $include ) {
			$s['f_order_custom'] = '1';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), __( 'as picked', 'woo-product-filter' ), 'f_order_custom' );
		} elseif ( 'term_order' === $orderBy ) {
			$s['f_sort_by'] = 'default';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), 'term_order', 'f_sort_by default (WooCommerce order)' );
		} elseif ( 'count' === $orderBy ) {
			$s['f_sort_by'] = 'count';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), 'count', 'f_sort_by count' );
		} else {
			$s['f_sort_by'] = $order;
			if ( $premium || 'name' !== $orderBy || 'asc' !== $order ) {
				/* translators: %s: filter name */
				$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), $orderBy . ' ' . $order, 'f_sort_by ' . $order );
			}
		}

		// Counts, folding title, empty values: only premium has controls; free always shows no counts and an open title.
		$showCount       = isset( $f['show_count'] ) && self::isYes( $f['show_count'] );
		$block['show_count'] = $showCount ? 1 : 0;
		if ( $premium ) {
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: product counts', 'woo-product-filter' ), $label ), $showCount ? 'yes' : 'no', 'f_show_count' );
			if ( isset( $f['show_toggle'] ) && self::isYes( $f['show_toggle'] ) ) {
				$s['f_enable_title'] = isset( $f['toggle_style'] ) && 'closed' === $f['toggle_style'] ? 'yes_close' : 'yes_open';
				/* translators: %s: filter name */
				$this->row( $mapped, sprintf( __( '%s: folding title', 'woo-product-filter' ), $label ), isset( $f['toggle_style'] ) ? $f['toggle_style'] : 'opened', 'f_enable_title ' . $s['f_enable_title'] );
			}
		}
		$adoptive = isset( $f['adoptive'] ) ? (string) $f['adoptive'] : 'hide';
		if ( 'hide' === $adoptive || $hideAll ) {
			$s['f_hide_empty'] = '1';
		} elseif ( 'or' === $adoptive && $premium ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: values with no products', 'woo-product-filter' ), $label ), 'greyed out', __( 'Ours greys them out the same way (default).', 'woo-product-filter' ) );
		}

		// Per value customisation (only used by YITH when "Customize terms" is on).
		if ( isset( $f['customize_terms'] ) && self::isYes( $f['customize_terms'] ) && $terms ) {
			$this->mapTermOptions( $terms, $design, $display, $label, $s, $mapped, $unmapped );
		}
		if ( in_array( $design, array( 'color', 'label' ), true ) ) {
			$this->mapDesignExtras( $f, $design, $display, $label, $s, $mapped, $unmapped );
		}
		self::look( $display, $s );
		$block['settings'] = $s;
		return $block;
	}

	/**
	 * The 4.0 look of swatch and chip blocks, so it does not depend on the classic Pro type.
	 */
	private static function look( $display, &$s ) {
		if ( 'colors' === $display ) {
			$s['display_type'] = 'swatches';
		} elseif ( 'buttons' === $display ) {
			$s['display_type'] = 'chips';
		}
	}

	/**
	 * Custom names, swatch colours and tooltips per value.
	 */
	private function mapTermOptions( $terms, $design, $display, $label, &$s, &$mapped, &$unmapped ) {
		$names   = 0;
		$colours = 0;
		$tips    = 0;
		$lost    = array();
		foreach ( $terms as $tid => $o ) {
			$o    = (array) $o;
			$tid  = (int) $tid;
			$term = get_term( $tid );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$custom = isset( $o['label'] ) ? trim( wp_specialchars_decode( (string) $o['label'], ENT_QUOTES ) ) : '';
			// The YITH admin fills every label with the term name: only a different text is a real rename.
			if ( '' !== $custom && 0 !== strcasecmp( $custom, wp_specialchars_decode( $term->name, ENT_QUOTES ) ) ) {
				if ( 'colors' === $display ) {
					$s[ 'f_colors_text_term' . $tid ] = $custom;
					$names++;
				} elseif ( 'buttons' === $display ) {
					$s[ 'f_buttons_text_term' . $tid ] = $custom;
					$names++;
				} else {
					$lost[] = $term->name . ' → ' . $custom;
				}
			}
			if ( 'color' === $design && 'colors' === $display ) {
				$c1 = isset( $o['color_1'] ) ? sanitize_hex_color( (string) $o['color_1'] ) : '';
				$c2 = isset( $o['color_2'] ) ? sanitize_hex_color( (string) $o['color_2'] ) : '';
				if ( $c1 ) {
					$s[ 'f_colors_term' . $tid ] = $c1;
					$colours++;
				}
				if ( $c2 ) {
					$s[ 'f_colors_bicolor_term' . $tid ] = $c2;
				}
				if ( ! empty( $o['image'] ) ) {
					/* translators: %s: term name */
					$this->row( $unmapped, sprintf( __( '%1$s: picture of %2$s', 'woo-product-filter' ), $label, $term->name ), (string) $o['image'], __( 'Set the picture on the value in our swatch editor.', 'woo-product-filter' ) );
				}
			}
			if ( ! empty( $o['tooltip'] ) && 0 !== strcasecmp( trim( (string) $o['tooltip'] ), $term->name ) ) {
				$tips++;
			}
		}
		if ( $names ) {
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: custom value names', 'woo-product-filter' ), $label ), $names, 'colors' === $display ? 'f_colors_text_term{id}' : 'f_buttons_text_term{id}' );
		}
		if ( $lost ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: custom value names', 'woo-product-filter' ), $label ), implode( ', ', $lost ), __( 'Ours shows the value name in lists and dropdowns; rename the value in WooCommerce if needed.', 'woo-product-filter' ) );
		}
		if ( $colours ) {
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: swatch colours', 'woo-product-filter' ), $label ), $colours, 'f_colors_term{id}' );
		}
		if ( $tips ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: tooltips', 'woo-product-filter' ), $label ), $tips, __( 'Ours shows the value name as the tooltip.', 'woo-product-filter' ) );
		}
	}

	/**
	 * Columns and label position of the swatch / label looks.
	 */
	private function mapDesignExtras( $f, $design, $display, $label, &$s, &$mapped, &$unmapped ) {
		$cols = isset( $f['column_number'] ) ? (int) $f['column_number'] : 0;
		if ( 'colors' === $display ) {
			$pos = isset( $f['label_position'] ) ? (string) $f['label_position'] : 'below';
			$s['f_colors_labels'] = 'hide' === $pos ? '0' : '1';
			$s['f_colors_layout'] = 'hor';
			if ( $cols ) {
				$s['f_colors_hor_row'] = (string) $cols;
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: swatch names and columns', 'woo-product-filter' ), $label ), $pos . ', ' . $cols, 'f_colors_labels, f_colors_hor_row' );
			if ( 'below' === $pos ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: name under the swatch', 'woo-product-filter' ), $label ), 'below', __( 'Ours puts the name next to the swatch.', 'woo-product-filter' ) );
			}
		} elseif ( 'buttons' === $display && $cols ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: columns', 'woo-product-filter' ), $label ), $cols, __( 'Our buttons wrap to the width of the area.', 'woo-product-filter' ) );
		}
	}

	/**
	 * Store wide YITH switches => our main settings.
	 */
	private function mainFromOptions( $premium, &$mapped, &$unmapped ) {
		// YITH always filters without reloading; only premium can wait for an Apply button.
		$instant = $premium ? self::isYes( $this->option( 'yith_wcan_instant_filters', 'yes' ) ) : true;
		$main    = $this->mainFrom( true, null, null, $mapped, array( 'auto_update_filter' => $instant ? '1' : '0' ) );
		if ( ! $premium ) {
			// What YITH Free always does (it has no setting for it), carried over so shoppers see the same.
			$this->row( $mapped, __( 'Product counts', 'woo-product-filter' ), __( 'never shown in YITH Free', 'woo-product-filter' ), 'f_show_count off' );
			$this->row( $mapped, __( 'Order of the values', 'woo-product-filter' ), __( 'A to Z in YITH Free', 'woo-product-filter' ), 'f_sort_by asc' );
			$this->row( $mapped, __( 'Values with no products', 'woo-product-filter' ), __( 'hidden in YITH Free', 'woo-product-filter' ), 'f_hide_empty' );
		}
		if ( ! $instant ) {
			$main['show_filtering_button'] = '1';
			$this->row( $mapped, __( 'Apply button', 'woo-product-filter' ), 'yes', 'show_filtering_button' );
		}
		$reset = self::isYes( $this->option( 'yith_wcan_show_reset', 'yes' ) );
		$main['show_clean_button'] = $reset ? '1' : '0';
		$pos = (string) $this->option( 'yith_wcan_reset_button_position', 'after_filters' );
		if ( $reset ) {
			$main['main_buttons_position'] = 'after_filters' === $pos ? 'bottom' : 'top';
		}
		$this->row( $mapped, __( 'Reset button', 'woo-product-filter' ), $reset ? $pos : 'no', $reset ? 'show_clean_button, main_buttons_position ' . $main['main_buttons_position'] : 'show_clean_button off' );
		if ( $reset && 'before_products' === $pos ) {
			$this->row( $unmapped, __( 'Reset button above the products', 'woo-product-filter' ), $pos, __( 'Ours puts "Clear all" at the top of the filter instead.', 'woo-product-filter' ) );
		}
		if ( self::isYes( $this->option( 'yith_wcan_paginate_terms', 'no' ) ) ) {
			$this->row( $mapped, __( 'Paginate terms', 'woo-product-filter' ), 'yes', __( 'Long lists fold behind a "Show more" pill (show_more_rows).', 'woo-product-filter' ) );
		}
		if ( self::isYes( $this->option( 'yith_wcan_lazy_load_filters', 'no' ) ) ) {
			$this->row( $unmapped, __( 'Lazy loading of filters', 'woo-product-filter' ), 'yes', __( 'Not needed: our index makes the filter fast.', 'woo-product-filter' ) );
		}
		if ( self::isYes( $this->option( 'yith_wcan_enable_seo', 'no' ) ) ) {
			$this->row( $unmapped, __( 'Robots meta on filtered pages', 'woo-product-filter' ), (string) $this->option( 'yith_wcan_seo_value', 'noindex-follow' ), __( 'Set noindex for filtered pages store wide under Speed and SEO.', 'woo-product-filter' ) );
		}
		if ( self::isYes( $this->option( 'yith_wcan_seo_rel_nofollow', 'no' ) ) ) {
			$this->row( $unmapped, __( 'rel="nofollow" on filter links', 'woo-product-filter' ), 'yes', __( 'Our values are form controls, not links, so there is nothing to mark.', 'woo-product-filter' ) );
		}
		if ( 'no' === $this->option( 'yith_wcan_change_browser_url', 'yes' ) ) {
			$this->row( $unmapped, __( 'Keep the address unchanged', 'woo-product-filter' ), 'no', __( 'Ours always writes the picks into the address so results can be shared.', 'woo-product-filter' ) );
		}
		return $main;
	}

	/**
	 * The older per-attribute widgets of one widget area => one filter.
	 */
	private function mapLegacy( $sidebar ) {
		$all = $this->legacySidebars();
		if ( ! isset( $all[ $sidebar ] ) ) {
			return new WP_Error( 'wpf_bad_set', __( 'These widgets are no longer in that widget area.', 'woo-product-filter' ) );
		}
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$isPro    = (bool) apply_filters( 'woobewoo_pf_is_pro', false );
		foreach ( $all[ $sidebar ]['nav'] as $num => $w ) {
			$attr   = isset( $w['attribute'] ) ? (string) $w['attribute'] : '';
			$title  = isset( $w['title'] ) ? (string) $w['title'] : '';
			$label  = '' !== $title ? $title : $attr;
			$type   = isset( $w['type'] ) ? (string) $w['type'] : 'list';
			$single = 'select' === $type;
			$design = array( 'list' => 'checkbox', 'select' => 'select', 'color' => 'color', 'label' => 'label' );
			$design = isset( $design[ $type ] ) ? $design[ $type ] : 'checkbox';
			$display = $this->displayFor( $design, ! $single, $isPro, $label, $mapped, $unmapped );
			$block   = $this->taxonomyBlock( $attr, $title, $display, $unmapped );
			if ( ! $block ) {
				continue;
			}
			$block['source'] = $label;
			$s = array( 'f_hide_empty' => '1' );
			if ( $single ) {
				$s['f_single_select'] = '1';
			} else {
				$s['f_query_logic'] = isset( $w['query_type'] ) && 'or' === $w['query_type'] ? 'or' : 'and';
				if ( 'buttons' === $display ) {
					$s['f_buttons_multiselect'] = '1';
				}
			}
			/* translators: %s: widget title */
			$this->row( $mapped, sprintf( __( 'Widget "%s"', 'woo-product-filter' ), $label ), $attr . ' / ' . $type . ( $single ? '' : ' / ' . $s['f_query_logic'] ), $block['type'] . ' / ' . $display . ( $single ? '' : ' / ' . ( 'or' === $s['f_query_logic'] ? __( 'any picked value', 'woo-product-filter' ) : __( 'all picked values', 'woo-product-filter' ) ) ) );
			$disp = isset( $w['display'] ) ? (string) $w['display'] : 'all';
			if ( 'all' !== $disp ) {
				/* translators: %s: widget title */
				$this->row( $unmapped, sprintf( __( 'Widget "%s": display', 'woo-product-filter' ), $label ), $disp, __( 'Attributes have no levels; all values are shown.', 'woo-product-filter' ) );
			}
			if ( 'colors' === $display && ! empty( $w['colors'] ) && is_array( $w['colors'] ) ) {
				foreach ( $w['colors'] as $tid => $hex ) {
					$hex = sanitize_hex_color( (string) $hex );
					if ( $hex ) {
						$s[ 'f_colors_term' . (int) $tid ] = $hex;
					}
				}
				/* translators: %s: widget title */
				$this->row( $mapped, sprintf( __( 'Widget "%s": swatch colours', 'woo-product-filter' ), $label ), count( $w['colors'] ), 'f_colors_term{id}' );
			}
			if ( 'buttons' === $display && ! empty( $w['labels'] ) && is_array( $w['labels'] ) ) {
				foreach ( $w['labels'] as $tid => $text ) {
					if ( '' !== trim( (string) $text ) ) {
						$s[ 'f_buttons_text_term' . (int) $tid ] = (string) $text;
					}
				}
				/* translators: %s: widget title */
				$this->row( $mapped, sprintf( __( 'Widget "%s": value labels', 'woo-product-filter' ), $label ), count( $w['labels'] ), 'f_buttons_text_term{id}' );
			} elseif ( ! empty( $w['labels'] ) && 'buttons' !== $display ) {
				/* translators: %s: widget title */
				$this->row( $unmapped, sprintf( __( 'Widget "%s": value labels', 'woo-product-filter' ), $label ), count( (array) $w['labels'] ), __( 'Value labels only show on the button look.', 'woo-product-filter' ) );
			}
			$block['show_count'] = 1;
			self::look( $display, $s );
			$block['settings']   = $s;
			$blocks[]            = $block;
		}
		$main = $this->mainFrom( true, true, null, $mapped );
		$main['show_clean_button'] = $all[ $sidebar ]['reset'] ? '1' : '0';
		$this->row( $mapped, __( 'Reset widget', 'woo-product-filter' ), $all[ $sidebar ]['reset'] ? 'yes' : 'no', $all[ $sidebar ]['reset'] ? 'show_clean_button' : 'show_clean_button off' );
		if ( $all[ $sidebar ]['reset'] ) {
			$r = reset( $all[ $sidebar ]['reset'] );
			if ( ! empty( $r['label'] ) ) {
				$main['show_clean_button_word'] = sanitize_text_field( $r['label'] );
			}
		}
		return array(
			'title'    => __( 'Sidebar widgets', 'woo-product-filter' ),
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/* ------------------------------------------------------------------ old links */

	/**
	 * YITH links after the switch, on the same page: ?yith_wcan=1&filter_color=red,blue&query_type_color=or|and
	 * &product_cat=a,b (OR) or a+b (AND)&product_tag=..; the older widgets omit yith_wcan=1. Our 4.0 URL has one
	 * list per block (wpf_color=red,blue); AND/OR lives in the imported block. Requests without yith_wcan and
	 * without filter_* / query_type_* are left alone (product_cat alone is a WordPress query var).
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		// Only links YITH presets wrote (yith_wcan=1). Bare filter_*/query_type_* links are WooCommerce's own
		// layered-nav format (also YITH's legacy widgets): WooCommerce keeps answering them, so they are left alone.
		if ( ! isset( $query['yith_wcan'] ) ) {
			return null;
		}
		$args = array();
		foreach ( $query as $k => $v ) {
			if ( ! is_scalar( $v ) ) {
				continue;
			}
			$k = (string) $k;
			if ( 'product_cat' === $k || 'product_tag' === $k ) {
				$key = 'product_cat' === $k ? 'category' : 'tag';
			} elseif ( 0 === strpos( $k, 'filter_' ) && taxonomy_exists( 'pa_' . substr( $k, 7 ) ) ) {
				$key = substr( $k, 7 );
			} else {
				continue;
			}
			$slugs = array();
			// "+" (AND) arrives as a space once PHP decodes the query string.
			foreach ( preg_split( '/[,+ ]|%2c|%2b/i', (string) $v ) as $s ) {
				$s = sanitize_title( rawurldecode( trim( $s ) ) );
				if ( '' !== $s ) {
					$slugs[] = $s;
				}
			}
			if ( $slugs ) {
				$args[ 'wpf_' . $key ] = implode( ',', array_unique( $slugs ) );
			}
		}
		if ( ! $args ) {
			return null;
		}
		if ( ! empty( $query['orderby'] ) && is_scalar( $query['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $query['orderby'] );
		}
		return array( 'path' => (string) $path, 'args' => $args );
	}

	/* ------------------------------------------------------------------ placements */

	public function signatures() {
		return array(
			'widgets'    => array( self::W_PRESET, self::W_LEGACY, self::W_RESET ),
			'shortcodes' => array( self::SC_PRESET ),
			'blocks'     => array( self::BLOCK_PRESET ),
			'products'   => array(),
		);
	}

	public function setOfPlacement( $kind, $name, $data ) {
		$data = (array) $data;
		if ( ( 'widget' === $kind && self::W_PRESET === $name ) || ( 'block' === $kind && self::BLOCK_PRESET === $name ) || ( 'shortcode' === $kind && self::SC_PRESET === $name ) ) {
			$key = 'widget' === $kind ? 'preset' : 'slug';
			$id  = isset( $data[ $key ] ) ? $this->presetIdBySlug( $data[ $key ] ) : 0;
			return $id ? (string) $id : 'none';
		}
		if ( 'widget' === $kind && in_array( $name, array( self::W_LEGACY, self::W_RESET ), true ) ) {
			// The instance alone does not say which widget area it is in: find the area holding an identical instance.
			$sidebars = get_option( 'sidebars_widgets', array() );
			$all      = get_option( 'widget_' . $name, array() );
			foreach ( (array) $sidebars as $sidebar => $ids ) {
				if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
					continue;
				}
				foreach ( $ids as $wid ) {
					$n = self::widgetNum( $wid, $name );
					if ( $n && isset( $all[ $n ] ) && (array) $all[ $n ] == $data ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- array content compare
						$legacy = $this->legacySidebars();
						return isset( $legacy[ $sidebar ] ) ? self::LEGACY_SET . $sidebar : 'none';
					}
				}
			}
			return 'none';
		}
		return '';
	}
}
