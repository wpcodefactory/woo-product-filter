<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Husky_Model Class
 *
 * HUSKY Products Filter (woocommerce-products-filter, formerly WOOF). Where its filters live:
 * - one global configuration: option `woof_settings` (tax, tax_type, excluded_terms, custom_tax_label,
 *   show_title_label, show_toggle_button, comparison_logic, orderby, order, tooltip_text, by_price, by_instock ...)
 *   plus the woof_* options of the Options tab (woof_try_ajax, woof_autosubmit, woof_show_count ...). It is
 *   shown by the WOOF_Widget widget (id_base woof_widget), a plain [woof] shortcode and the automatic shop
 *   filter (woof_set_automatically);
 * - [woof] shortcodes whose attributes (tax_only, by_only, tax_exclude, excluded_terms, items_order) make
 *   another filter out of the global one;
 * - "Filter front builder" forms: rows of the {prefix}woof_front_builder table, shown by
 *   [woof_front_builder name="..."] (selected items, viewtypes, sections_options, options).
 *
 * @version 4.0.1
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Husky_Model extends WooBeWoo_PF_Importer_Base_Model {

	/** [woof] attributes that make a placement show another filter than the global one. */
	const SHAPE_ATTS = array( 'tax_only', 'by_only', 'tax_exclude', 'excluded_terms', 'items_order' );

	/** Non-taxonomy items, in the order HUSKY draws them when items_order is empty. */
	const ITEM_KEYS = array( 'by_price', 'by_author', 'by_instock', 'by_onsales', 'by_rating', 'by_text', 'by_sku', 'by_featured', 'by_backorder' );

	private $placementsCache = null;

	public function slug() {
		return 'husky';
	}

	public function name() {
		return 'HUSKY Products Filter (WOOF)';
	}

	public function pluginFiles() {
		return array( 'woocommerce-products-filter/index.php', 'woocommerce-products-filter-premium/index.php' );
	}

	public function installed() {
		$s = get_option( 'woof_settings', null );
		return is_array( $s ) && ! empty( $s );
	}

	private function settings() {
		$s = get_option( 'woof_settings', array() );
		return is_array( $s ) ? $s : array();
	}

	/* ------------------------------------------------------------------ sources of sets */

	private function fbTable() {
		global $wpdb;
		$table = $wpdb->prefix . 'woof_front_builder';
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $found === $table ? $table : '';
	}

	/**
	 * Front builder forms: array( id => row ) with the JSON columns decoded.
	 */
	private function builderRows() {
		global $wpdb;
		$table = $this->fbTable();
		if ( ! $table ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY id ASC LIMIT 200", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from the prefix
		$out  = array();
		foreach ( (array) $rows as $r ) {
			foreach ( array( 'options', 'layout_options', 'sections_options', 'sections_layout_options', 'viewtypes', 'additional' ) as $k ) {
				$d       = isset( $r[ $k ] ) && is_string( $r[ $k ] ) ? json_decode( $r[ $k ], true ) : null;
				$r[ $k ] = is_array( $d ) ? $d : array();
			}
			$out[ (int) $r['id'] ] = $r;
		}
		return $out;
	}

	private function builderIdByName( $name ) {
		foreach ( $this->builderRows() as $id => $r ) {
			if ( (string) $r['name'] === (string) $name ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * Attributes that shape the filter, normalised; '' for the global filter.
	 */
	private function shapeKey( $atts ) {
		$shape = array();
		foreach ( self::SHAPE_ATTS as $k ) {
			if ( isset( $atts[ $k ] ) && '' !== trim( (string) $atts[ $k ] ) ) {
				$shape[ $k ] = implode( ',', array_map( 'trim', explode( ',', (string) $atts[ $k ] ) ) );
			}
		}
		if ( ! $shape ) {
			return '';
		}
		ksort( $shape );
		return substr( md5( wp_json_encode( $shape ) ), 0, 10 );
	}

	/**
	 * Every [woof] in posts and Elementor data: array( array( 'post' => id, 'atts' => array ) ).
	 */
	private function placements() {
		if ( null !== $this->placementsCache ) {
			return $this->placementsCache;
		}
		global $wpdb;
		$out = array();
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache') AND post_status IN ('publish','draft','private','future','pending') AND post_content LIKE %s ORDER BY ID ASC LIMIT 500", '%[woof%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$el  = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE %s LIMIT 500", '%[woof%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$re  = '/' . get_shortcode_regex( array( 'woof' ) ) . '/';
		foreach ( array_unique( array_merge( array_map( 'intval', $ids ), array_map( 'intval', $el ) ) ) as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'trash' === $post->post_status ) {
				continue;
			}
			$text = $post->post_content . ' ' . wp_unslash( (string) get_post_meta( $id, '_elementor_data', true ) );
			if ( preg_match_all( $re, $text, $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					if ( '[' === $m[1] && ']' === $m[6] ) {
						continue;
					}
					$out[] = array( 'post' => (int) $id, 'atts' => (array) shortcode_parse_atts( $m[3] ) );
				}
			}
		}
		$this->placementsCache = $out;
		return $out;
	}

	/**
	 * Shortcode sets: array( key => array( 'atts' => first atts, 'posts' => ids ) ).
	 */
	private function shortcodeSets() {
		$out = array();
		foreach ( $this->placements() as $p ) {
			$key = $this->shapeKey( $p['atts'] );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array( 'atts' => $p['atts'], 'posts' => array() );
			}
			$out[ $key ]['posts'][] = $p['post'];
		}
		return $out;
	}

	private function widgets() {
		$all = get_option( 'widget_woof_widget', array() );
		$out = array();
		$on  = array();
		foreach ( (array) get_option( 'sidebars_widgets', array() ) as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				if ( preg_match( '/^woof_widget-(\d+)$/', (string) $wid, $m ) ) {
					$on[] = (int) $m[1];
				}
			}
		}
		foreach ( $on as $n ) {
			if ( isset( $all[ $n ] ) && is_array( $all[ $n ] ) ) {
				$out[ $n ] = $all[ $n ];
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ sets */

	public function sets() {
		$s = $this->settings();
		if ( empty( $s ) ) {
			return array();
		}
		$out     = array();
		$widgets = $this->widgets();
		$first   = $widgets ? reset( $widgets ) : array();
		$plain   = 0;
		foreach ( $this->placements() as $p ) {
			if ( '' === $this->shapeKey( $p['atts'] ) ) {
				$plain++;
			}
		}
		$where = array();
		if ( $widgets ) {
			$where[] = sprintf( /* translators: %d: count */ _n( '%d widget', '%d widgets', count( $widgets ), 'woo-product-filter' ), count( $widgets ) );
		}
		if ( $plain ) {
			$where[] = sprintf( /* translators: %d: count */ _n( '%d [woof] shortcode', '%d [woof] shortcodes', $plain, 'woo-product-filter' ), $plain );
		}
		if ( (int) get_option( 'woof_set_automatically', 0 ) ) {
			$where[] = __( 'automatic shop filter', 'woo-product-filter' );
		}
		if ( empty( $first['title'] ) ) {
			// After a switch their widgets sit in "Inactive widgets": keep the name the owner knows.
			foreach ( (array) get_option( 'widget_woof_widget', array() ) as $n => $inst ) {
				if ( is_int( $n ) && ! empty( $inst['title'] ) ) {
					$first = $inst;
					break;
				}
			}
		}
		$cfg   = $this->config( 'global' );
		$out[] = array(
			'id'        => 'global',
			'title'     => ! empty( $first['title'] ) ? (string) $first['title'] : __( 'HUSKY filter', 'woo-product-filter' ),
			'blocks'    => count( $cfg['items'] ),
			'shortcode' => '[woof]',
			'note'      => $where ? implode( ', ', $where ) : __( 'Settings only, not placed', 'woo-product-filter' ),
		);
		foreach ( $this->shortcodeSets() as $key => $sc ) {
			$cfg   = $this->config( 'sc-' . $key );
			$post  = get_post( $sc['posts'][0] );
			$out[] = array(
				'id'        => 'sc-' . $key,
				'title'     => $post && '' !== $post->post_title ? $post->post_title : '[woof] #' . $sc['posts'][0],
				'blocks'    => count( $cfg['items'] ),
				'shortcode' => $this->shortcodeText( $sc['atts'] ),
				'note'      => sprintf( /* translators: %d: count */ _n( '[woof] with its own blocks on %d page', '[woof] with its own blocks on %d pages', count( array_unique( $sc['posts'] ) ), 'woo-product-filter' ), count( array_unique( $sc['posts'] ) ) ),
			);
		}
		foreach ( $this->builderRows() as $id => $r ) {
			$cfg   = $this->config( 'fb-' . $id );
			$out[] = array(
				'id'        => 'fb-' . $id,
				'title'     => '' !== (string) $r['name'] ? (string) $r['name'] : 'Front builder #' . $id,
				'blocks'    => count( $cfg['items'] ),
				'shortcode' => '[woof_front_builder name="' . $r['name'] . '"]',
				'note'      => __( 'Filter front builder form', 'woo-product-filter' ),
			);
		}
		return $out;
	}

	private function shortcodeText( $atts ) {
		$t = '[woof';
		foreach ( self::SHAPE_ATTS as $k ) {
			if ( isset( $atts[ $k ] ) && '' !== (string) $atts[ $k ] ) {
				$t .= ' ' . $k . '="' . $atts[ $k ] . '"';
			}
		}
		return $t . ']';
	}

	/* ------------------------------------------------------------------ one set = settings + item list */

	/**
	 * Resolve a set into array( 'settings', 'items' (ordered keys shown), 'opts' (behaviour), 'source' (label), 'excluded' ).
	 */
	private function config( $setId ) {
		$s    = $this->settings();
		$atts = array();
		$opts = array();
		$excl = null; // null: per taxonomy from woof_settings; array: ids from the placement (replace the global lists)
		if ( 'global' === $setId ) {
			$widgets = $this->widgets();
			$w       = $widgets ? reset( $widgets ) : array();
			foreach ( array( 'autosubmit', 'dynamic_recount', 'ajax_redraw', 'btn_position', 'mobile_mode', 'redirect' ) as $k ) {
				if ( isset( $w[ $k ] ) ) {
					$opts[ $k ] = $w[ $k ];
				}
			}
			if ( isset( $w['woof_start_filtering_btn'] ) ) {
				$opts['start_filtering_btn'] = $w['woof_start_filtering_btn'];
			}
		} elseif ( 0 === strpos( (string) $setId, 'sc-' ) ) {
			$sets = $this->shortcodeSets();
			$key  = substr( (string) $setId, 3 );
			if ( ! isset( $sets[ $key ] ) ) {
				return null;
			}
			$atts = $sets[ $key ]['atts'];
			$opts = $atts;
			if ( isset( $atts['excluded_terms'] ) && '' !== trim( (string) $atts['excluded_terms'] ) ) {
				$excl = array_filter( array_map( 'intval', explode( ',', (string) $atts['excluded_terms'] ) ) );
			}
		} elseif ( 0 === strpos( (string) $setId, 'fb-' ) ) {
			$rows = $this->builderRows();
			$id   = (int) substr( (string) $setId, 3 );
			if ( ! isset( $rows[ $id ] ) ) {
				return null;
			}
			$r        = $rows[ $id ];
			$selected = array_filter( array_map( 'trim', explode( ',', (string) $r['selected'] ) ) );
			$taxOnly  = array();
			$byOnly   = array();
			foreach ( $selected as $k ) {
				if ( taxonomy_exists( $k ) ) {
					$taxOnly[] = $k;
				} else {
					$byOnly[] = $k;
				}
			}
			$atts = array( 'tax_only' => implode( ',', $taxOnly ), 'by_only' => implode( ',', $byOnly ), 'items_order' => implode( ',', $selected ) );
			$opts = array_merge( $atts, $r['options'] );
			if ( $r['layout_options'] || $r['sections_layout_options'] ) {
				$opts['fb_layout'] = wp_json_encode( array_merge( $r['layout_options'], $r['sections_layout_options'] ) );
			}
			// Section options override the global per item settings, view types override tax_type (as HUSKY does).
			foreach ( $r['sections_options'] as $section => $values ) {
				if ( ! is_array( $values ) ) {
					continue;
				}
				foreach ( $values as $k => $v ) {
					if ( taxonomy_exists( $section ) ) {
						$s[ $k ][ $section ] = $v;
					} else {
						$s[ $section ][ $k ] = $v;
					}
				}
			}
			foreach ( $r['viewtypes'] as $tax => $type ) {
				$s['tax_type'][ $tax ] = $type;
			}
		} else {
			return null;
		}
		return array(
			'settings' => $s,
			'items'    => $this->items( $s, $atts ),
			'opts'     => $opts,
			'excluded' => $excl,
		);
	}

	/**
	 * Keys drawn by HUSKY, in its order (views/woof.php): items_order or by_* then taxonomies; tax_only reorders;
	 * an items_order attribute replaces the list; by_only items are always shown.
	 */
	private function items( &$s, $atts ) {
		$taxKeys = array_keys( isset( $s['tax_type'] ) && is_array( $s['tax_type'] ) ? $s['tax_type'] : array() );
		foreach ( get_object_taxonomies( 'product' ) as $t ) {
			if ( ! in_array( $t, $taxKeys, true ) ) {
				$taxKeys[] = $t;
			}
		}
		$order = ! empty( $s['items_order'] ) ? array_map( 'trim', explode( ',', (string) $s['items_order'] ) ) : array_merge( self::ITEM_KEYS, $taxKeys );
		foreach ( array_merge( self::ITEM_KEYS, $taxKeys ) as $k ) {
			if ( ! in_array( $k, $order, true ) ) {
				$order[] = $k;
			}
		}
		$taxOnly = ! empty( $atts['tax_only'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', (string) $atts['tax_only'] ) ) ) ) : array();
		$byOnly  = ! empty( $atts['by_only'] ) ? array_values( array_filter( array_map( 'trim', explode( ',', (string) $atts['by_only'] ) ) ) ) : array();
		$taxExcl = ! empty( $atts['tax_exclude'] ) ? array_map( 'trim', explode( ',', (string) $atts['tax_exclude'] ) ) : array();
		if ( $taxOnly ) {
			$i = 0;
			foreach ( $order as $pos => $k ) {
				if ( in_array( $k, $taxOnly, true ) ) {
					$order[ $pos ] = $taxOnly[ $i++ ];
				}
			}
			foreach ( $taxOnly as $t ) {
				$s['tax'][ $t ] = 1; // tax_only also shows taxonomies not ticked in the settings
			}
		}
		if ( ! empty( $atts['items_order'] ) ) {
			$order = array_values( array_filter( array_map( 'trim', explode( ',', (string) $atts['items_order'] ) ) ) );
		}
		foreach ( $byOnly as $k ) {
			if ( 'by_price' !== $k ) {
				$s[ $k ]['show'] = 1;
			}
		}
		$out = array();
		foreach ( $order as $k ) {
			if ( in_array( $k, $out, true ) || in_array( $k, $taxExcl, true ) ) {
				continue;
			}
			if ( in_array( $k, self::ITEM_KEYS, true ) ) {
				if ( empty( $s[ $k ]['show'] ) || ( $byOnly && ! in_array( $k, $byOnly, true ) ) ) {
					continue;
				}
				$out[] = $k;
			} elseif ( ! empty( $s['tax'][ $k ] ) && taxonomy_exists( $k ) ) {
				if ( $taxOnly && ! in_array( $k, $taxOnly, true ) ) {
					continue;
				}
				$out[] = $k;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ map */

	private static function val( $s, $key, $tax, $default = '' ) {
		return isset( $s[ $key ][ $tax ] ) && '' !== (string) $s[ $key ][ $tax ] ? (string) $s[ $key ][ $tax ] : $default;
	}

	/**
	 * HUSKY show_toggle_button / show_title_label => our f_enable_title.
	 */
	private static function titleMode( $showTitle, $toggle ) {
		if ( '0' === (string) $showTitle ) {
			return 'no';
		}
		return '1' === (string) $toggle ? 'yes_close' : 'yes_open';
	}

	/**
	 * HUSKY price ranges "0-25,25-50,100-i" => our range lines "0-25;25-50;100-".
	 */
	private static function ranges( $raw ) {
		$out = array();
		foreach ( explode( ',', (string) $raw ) as $r ) {
			$p = array_map( 'trim', explode( '-', trim( $r ), 2 ) );
			if ( 2 !== count( $p ) ) {
				continue;
			}
			$from  = 'i' === $p[0] ? '' : $p[0];
			$to    = 'i' === $p[1] ? '' : $p[1];
			if ( ( '' === $from || is_numeric( $from ) ) && ( '' === $to || is_numeric( $to ) ) && ( '' !== $from || '' !== $to ) ) {
				$out[] = $from . '-' . $to;
			}
		}
		return implode( ';', $out );
	}

	private function taxLabel( $tax ) {
		$t = get_taxonomy( $tax );
		return $t ? $t->labels->name : $tax;
	}

	public function map( $setId ) {
		$cfg = $this->config( $setId );
		if ( ! $cfg ) {
			return new WP_Error( 'wpf_bad_set', __( 'HUSKY filter not found.', 'woo-product-filter' ) );
		}
		$s        = $cfg['settings'];
		$o        = $cfg['opts'];
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();

		$showCount = (bool) (int) get_option( 'woof_show_count', 0 );
		if ( isset( $o['hide_terms_count'] ) && (int) $o['hide_terms_count'] ) {
			$showCount = false;
			$this->row( $mapped, 'hide_terms_count', '1', 'f_show_count 0' );
		}
		$hideEmpty = (bool) (int) get_option( 'woof_hide_dynamic_empty_pos', 0 );
		$slide     = (bool) (int) get_option( 'woof_checkboxes_slide', 1 );

		foreach ( $cfg['items'] as $item ) {
			if ( taxonomy_exists( $item ) ) {
				$block = $this->taxBlock( $item, $s, $cfg['excluded'], $showCount, $hideEmpty, $slide, $mapped, $unmapped );
			} else {
				$block = $this->itemBlock( $item, $s, $showCount, $mapped, $unmapped );
			}
			if ( $block ) {
				$blocks[] = $block;
			}
		}

		// Whole filter.
		$ajax = (bool) (int) get_option( 'woof_try_ajax', 0 );
		if ( isset( $o['is_ajax'] ) && '' !== (string) $o['is_ajax'] ) {
			$ajax = (bool) (int) $o['is_ajax'];
		}
		$auto = (bool) (int) get_option( 'woof_autosubmit', 1 );
		if ( isset( $o['autosubmit'] ) && '-1' !== (string) $o['autosubmit'] && '' !== (string) $o['autosubmit'] ) {
			$auto = (bool) (int) $o['autosubmit'];
		}
		$recount = (bool) (int) get_option( 'woof_show_count_dynamic', 0 );
		if ( isset( $o['dynamic_recount'] ) && in_array( (string) $o['dynamic_recount'], array( '0', '1' ), true ) ) {
			$recount = (bool) (int) $o['dynamic_recount'];
		}
		$main = $this->mainFrom(
			$ajax,
			$showCount,
			null,
			$mapped,
			array(
				'auto_update_filter'    => $auto ? '1' : '0',
				'show_filtering_button' => $auto ? '0' : '1',
				'filter_recount'        => $recount ? '1' : '0',
				'show_clean_button'     => '1',
			)
		);
		$this->row( $mapped, __( 'Autosubmit', 'woo-product-filter' ), $auto ? 'yes' : 'no (Filter button)', 'auto_update_filter / show_filtering_button' );
		$this->row( $mapped, __( 'Dynamic recount', 'woo-product-filter' ), $recount ? 'yes' : 'no', 'filter_recount' );
		$filterTxt = trim( (string) get_option( 'woof_filter_btn_txt', '' ) );
		if ( '' !== $filterTxt ) {
			$main['filtering_button_word'] = $filterTxt;
			$main['apply_button_word']     = $filterTxt;
			$this->row( $mapped, 'woof_filter_btn_txt', $filterTxt, 'apply_button_word' );
		}
		$resetTxt = trim( (string) get_option( 'woof_reset_btn_txt', '' ) );
		if ( 'none' === strtolower( $resetTxt ) ) {
			$main['show_clean_button'] = '0';
			$this->row( $mapped, 'woof_reset_btn_txt', 'none', 'show_clean_button 0' );
		} elseif ( '' !== $resetTxt ) {
			$main['show_clean_button_word'] = $resetTxt;
			$this->row( $mapped, 'woof_reset_btn_txt', $resetTxt, 'show_clean_button_word' );
		} else {
			$this->row( $mapped, __( 'Reset button', 'woo-product-filter' ), __( 'always shown', 'woo-product-filter' ), 'show_clean_button' );
		}
		if ( (int) get_option( 'woof_sort_terms_checked', 0 ) ) {
			$main['checked_items_top'] = '1';
			$this->row( $mapped, 'woof_sort_terms_checked', '1', 'checked_items_top' );
		}
		if ( ! (int) get_option( 'woof_hide_red_top_panel', 0 ) ) {
			$this->row( $mapped, __( 'Picked filters above the products', 'woo-product-filter' ), 'yes', __( 'picked chips at the top of our filter', 'woo-product-filter' ) );
		}

		// Phones.
		$mobileMode = isset( $o['mobile_mode'] ) && (int) $o['mobile_mode'];
		$autoMode   = (int) get_option( 'woof_set_automatically', 0 );
		if ( $mobileMode || ( 'global' === $setId && 2 === $autoMode && $this->widgets() ) ) {
			$main['mobile_drawer'] = '1';
			$this->row( $mapped, $mobileMode ? 'mobile_mode' : 'woof_set_automatically', $mobileMode ? '1' : '2 (phones only)', __( 'mobile_drawer: on phones the filter opens from a "Filters" button', 'woo-product-filter' ) );
		}

		// Placement extras without an equivalent.
		if ( ! empty( $o['btn_position'] ) && 'b' !== $o['btn_position'] ) {
			$this->row( $unmapped, 'btn_position', $o['btn_position'], __( 'Our buttons sit under the filter.', 'woo-product-filter' ) );
		}
		if ( ! empty( $o['redirect'] ) ) {
			$this->row( $unmapped, 'redirect', $o['redirect'], __( 'Our filter shows results on the page it sits on.', 'woo-product-filter' ) );
		}
		if ( ! empty( $o['start_filtering_btn'] ) || ! empty( $o['autohide'] ) ) {
			$this->row( $unmapped, 'start_filtering_btn / autohide', '1', __( 'Use "Open as a drawer on phones" / "Use the drawer on every screen size" instead.', 'woo-product-filter' ) );
		}
		if ( ! empty( $o['ajax_redraw'] ) ) {
			$this->row( $unmapped, 'ajax_redraw', '1', __( 'Our filter always redraws its counts itself.', 'woo-product-filter' ) );
		}
		if ( ! empty( $o['taxonomies'] ) ) {
			$this->row( $mapped, 'taxonomies (page grid)', $o['taxonomies'], __( 'Kept on the page: their [woof_products] grid becomes a WooCommerce [products] grid limited to the same terms.', 'woo-product-filter' ) );
		}
		if ( ! empty( $o['fb_layout'] ) ) {
			$this->row( $unmapped, 'layout_options / sections_layout_options', (string) $o['fb_layout'], __( 'Block widths and columns are set with the Look cards and "Width of the whole filter".', 'woo-product-filter' ) );
		}
		if ( ! empty( $s['woof_url_request']['enable'] ) ) {
			$this->row( $unmapped, 'woof_url_request', 'enabled', __( 'Clean links are set under Speed and SEO; old /swoof/ links stop working.', 'woo-product-filter' ) );
		}
		foreach ( array( 'select_design', 'icheck_skin', 'ion_slider_skin', 'overlay_skin', 'use_beauty_scroll', 'woof_auto_filter_skins', 'custom_css_code', 'js_after_ajax_done' ) as $k ) {
			if ( ! empty( $s[ $k ] ) && is_scalar( $s[ $k ] ) && 'none' !== $s[ $k ] && 'default' !== $s[ $k ] ) {
				$this->row( $unmapped, $k, $s[ $k ], __( 'Look is picked from the Look cards; custom CSS/JS has to be checked by hand.', 'woo-product-filter' ) );
			}
		}

		$title = '';
		foreach ( $this->sets() as $row ) {
			if ( (string) $row['id'] === (string) $setId ) {
				$title = $row['title'];
			}
		}
		return array(
			'title'    => '' !== $title ? $title : __( 'Imported from HUSKY', 'woo-product-filter' ),
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	private function taxBlock( $tax, $s, $excluded, $showCount, $hideEmpty, $slide, &$mapped, &$unmapped ) {
		$type   = self::val( $s, 'tax_type', $tax, 'radio' );
		$label  = self::val( $s, 'custom_tax_label', $tax );
		$source = ( '' !== $label ? $label : $this->taxLabel( $tax ) ) . ' (' . $tax . ')';
		if ( '' === $label ) {
			$label = $this->taxLabel( $tax );
		}
		$raw = array( 'radio' => 'radio', 'checkbox' => 'checkbox', 'select' => 'select', 'mselect' => 'mselect', 'label' => 'label', 'color' => 'color', 'image' => 'image', 'select_radio_check' => 'mselect', 'select_hierarchy' => 'select', 'slider' => 'checkbox' );
		if ( 0 === strpos( $type, 'woof_sd_' ) ) {
			$this->row( $unmapped, 'tax_type (' . $tax . ')', $type, __( 'Smart Designer element; imported as tick boxes.', 'woo-product-filter' ) );
			$display = 'checkbox';
		} else {
			if ( in_array( $type, array( 'select_radio_check', 'select_hierarchy', 'slider' ), true ) ) {
				$this->row( $unmapped, 'tax_type (' . $tax . ')', $type, __( 'No exact look; imported as the closest one.', 'woo-product-filter' ) );
			}
			$display = $this->freeDisplay( $this->display( isset( $raw[ $type ] ) ? $raw[ $type ] : 'checkbox' ), $unmapped, 'tax_type (' . $tax . ')' );
		}
		$block = $this->taxonomyBlock( $tax, $label, $display, $unmapped );
		if ( ! $block ) {
			return null;
		}
		$settings = array(
			'f_show_count'   => $showCount ? '1' : '0',
			'f_enable_title' => self::titleMode( self::val( $s, 'show_title_label', $tax, '1' ), self::val( $s, 'show_toggle_button', $tax, '0' ) ),
		);
		// HUSKY labels are chips, colours/images are swatches in the 4.0 engine.
		if ( 'buttons' === $display ) {
			$settings['display_type'] = 'chips';
		} elseif ( 'colors' === $display ) {
			$settings['display_type'] = 'swatches';
		}
		$this->row( $mapped, 'tax_type (' . $source . ')', $type, $block['type'] . ' / ' . $display );
		$this->row( $mapped, 'show_title_label / show_toggle_button (' . $tax . ')', self::val( $s, 'show_title_label', $tax, '1' ) . ' / ' . self::val( $s, 'show_toggle_button', $tax, '0' ), 'f_enable_title ' . $settings['f_enable_title'] );
		if ( $hideEmpty ) {
			$settings['f_hide_empty'] = '1';
		}
		// Logic between picked terms.
		$logic = strtoupper( self::val( $s, 'comparison_logic', $tax, 'OR' ) );
		if ( 'AND' === $logic && in_array( $display, array( 'checkbox', 'mul_dropdown', 'buttons', 'colors' ), true ) ) {
			$settings['f_query_logic'] = 'and';
			$this->row( $mapped, 'comparison_logic (' . $tax . ')', 'AND', 'f_query_logic and' );
		} elseif ( 'NOT IN' === $logic ) {
			$this->row( $unmapped, 'comparison_logic (' . $tax . ')', 'NOT IN', __( 'Our blocks show products that have the picked choices; "everything except" is not available.', 'woo-product-filter' ) );
		}
		// Excluded / included terms (a placement's excluded_terms replaces the settings list).
		if ( is_array( $excluded ) ) {
			$ids = array();
			foreach ( $excluded as $id ) {
				$t = get_term( $id );
				if ( $t && ! is_wp_error( $t ) && $t->taxonomy === $tax ) {
					$ids[] = $id;
				}
			}
			if ( $ids ) {
				$settings['f_exclude_terms'] = implode( ',', $ids );
				$this->row( $mapped, 'excluded_terms attribute (' . $tax . ')', implode( ',', $ids ), 'f_exclude_terms' );
			}
		} else {
			$ids = self::val( $s, 'excluded_terms', $tax );
			$ids = implode( ',', array_filter( array_map( 'intval', explode( ',', $ids ) ) ) );
			if ( '' !== $ids ) {
				if ( ! empty( $s['excluded_terms_reverse'][ $tax ] ) ) {
					$settings['f_mlist[]'] = $ids;
					$this->row( $mapped, 'excluded_terms + Reverse (' . $tax . ')', $ids, 'f_mlist[] (only these)' );
				} else {
					$settings['f_exclude_terms'] = $ids;
					$this->row( $mapped, 'excluded_terms (' . $tax . ')', $ids, 'f_exclude_terms' );
				}
			}
		}
		// Hierarchy.
		if ( is_taxonomy_hierarchical( $tax ) && in_array( $display, array( 'checkbox', 'radio' ), true ) ) {
			$settings['f_show_hierarchical'] = '1';
			$this->row( $mapped, __( 'Child terms under their parent', 'woo-product-filter' ) . ' (' . $tax . ')', $slide ? __( 'folded behind +', 'woo-product-filter' ) : __( 'open', 'woo-product-filter' ), 'f_show_hierarchical' );
		}
		// Term order.
		$orderby = self::val( $s, 'orderby', $tax, '-1' );
		$dir     = strtoupper( self::val( $s, 'order', $tax, 'ASC' ) );
		if ( 'name' === $orderby || 'numeric' === $orderby ) {
			$settings['f_sort_by'] = 'DESC' === $dir ? 'desc' : 'asc';
			if ( 'numeric' === $orderby ) {
				$settings['f_sort_as_numbers'] = '1';
			}
			$this->row( $mapped, 'orderby / order (' . $tax . ')', $orderby . ' ' . $dir, 'f_sort_by ' . $settings['f_sort_by'] );
		} elseif ( 'id' === $orderby ) {
			$this->row( $unmapped, 'orderby (' . $tax . ')', 'id ' . $dir, __( 'Sorting by term ID is not offered; terms keep the WooCommerce order.', 'woo-product-filter' ) );
		}
		$tip = self::val( $s, 'tooltip_text', $tax );
		if ( '' !== $tip ) {
			$settings['f_description'] = $tip;
			$this->row( $mapped, 'tooltip_text (' . $tax . ')', $tip, 'f_description (a line under the title)' );
		}
		if ( '1' === self::val( $s, 'dispay_in_row', $tax ) ) {
			$settings['f_layout'] = 'hor';
			$this->row( $mapped, 'dispay_in_row (' . $tax . ')', '1', 'f_layout hor' );
		}
		$height = (int) self::val( $s, 'tax_block_height', $tax, '0' );
		if ( $height > 0 ) {
			$settings['f_max_height'] = (string) $height;
			$this->row( $mapped, 'tax_block_height (' . $tax . ')', $height, 'f_max_height' );
		}
		$more = (int) self::val( $s, 'not_toggled_terms_count', $tax, '0' );
		if ( $more > 0 ) {
			$this->row( $unmapped, 'not_toggled_terms_count (' . $tax . ')', $more, __( 'Set "Show more after this many rows" on the block by hand.', 'woo-product-filter' ) );
		}
		$block['settings'] = $settings;
		$block['source']   = $source;
		return $block;
	}

	private function itemBlock( $item, $s, $showCount, &$mapped, &$unmapped ) {
		$o     = isset( $s[ $item ] ) && is_array( $s[ $item ] ) ? $s[ $item ] : array();
		$title = ! empty( $o['title_text'] ) ? (string) $o['title_text'] : '';
		switch ( $item ) {
			case 'by_price':
				$show     = isset( $o['show'] ) ? (int) $o['show'] : 3;
				$settings = array( 'f_enable_title' => self::titleMode( '1', isset( $o['show_toggle_button'] ) ? $o['show_toggle_button'] : '0' ) );
				if ( ! empty( $o['tooltip_text'] ) ) {
					$settings['f_description'] = (string) $o['tooltip_text'];
				}
				$names = array( 1 => 'WooCommerce range-slider', 2 => 'drop-down of ranges', 3 => 'ion range-slider', 4 => 'text inputs', 5 => 'radio of ranges' );
				if ( in_array( $show, array( 2, 5 ), true ) ) {
					$ranges = self::ranges( isset( $o['ranges'] ) ? $o['ranges'] : '' );
					if ( '' !== $ranges ) {
						$settings['f_range_by_hands']        = '1';
						$settings['f_range_by_hands_values'] = $ranges;
						$this->row( $mapped, 'by_price.ranges', $o['ranges'], 'f_range_by_hands_values ' . $ranges );
					} else {
						$settings['f_range_automatic'] = '1';
						$this->row( $mapped, 'by_price.ranges', '', 'f_range_automatic' );
					}
					$block = array( 'type' => 'price_range', 'title' => '' !== $title ? $title : __( 'Price', 'woo-product-filter' ), 'settings' => $settings );
					if ( 2 === $show ) {
						$block['display'] = 'dropdown';
						if ( ! empty( $o['first_option_text'] ) ) {
							$block['settings']['f_dropdown_first_option_text'] = (string) $o['first_option_text'];
						}
					}
					$this->row( $mapped, 'by_price.show', $show . ' (' . $names[ $show ] . ')', 'wpfPriceRange' . ( 2 === $show ? ' / dropdown' : ' / range list' ) );
				} else {
					$step = isset( $o['ion_slider_step'] ) ? (float) $o['ion_slider_step'] : 0;
					if ( 3 === $show && $step > 0 ) {
						$settings['f_step'] = (string) $step;
						$this->row( $mapped, 'by_price.ion_slider_step', $o['ion_slider_step'], 'f_step' );
					}
					if ( 4 === $show || ( 3 === $show && ! empty( $o['show_text_input'] ) ) ) {
						$settings['f_show_inputs'] = '1';
						$this->row( $mapped, 4 === $show ? 'by_price.show 4' : 'by_price.show_text_input', '1', 'f_show_inputs' );
					}
					if ( ! empty( $o['price_tax'] ) ) {
						$this->row( $unmapped, 'by_price.price_tax', $o['price_tax'], __( 'Prices follow the WooCommerce tax display setting.', 'woo-product-filter' ) );
					}
					$block = array( 'type' => 'price', 'title' => '' !== $title ? $title : __( 'Price', 'woo-product-filter' ), 'settings' => $settings );
					$this->row( $mapped, 'by_price.show', $show . ' (' . ( isset( $names[ $show ] ) ? $names[ $show ] : $show ) . ')', 'wpfPrice slider' );
				}
				$block['source'] = __( 'Search by Price', 'woo-product-filter' );
				return $block;
			case 'by_instock':
				$this->row( $mapped, 'by_instock.show', '1', 'wpfInStock (in stock only)' );
				return array( 'type' => 'stock', 'title' => __( 'In stock', 'woo-product-filter' ), 'source' => __( 'In stock', 'woo-product-filter' ), 'settings' => array( 'f_options[]' => 'instock', 'f_show_count' => $showCount ? '1' : '0' ) );
			case 'by_onsales':
				$this->row( $mapped, 'by_onsales.show', '1', 'wpfOnSale' );
				return array( 'type' => 'onsale', 'title' => __( 'On sale', 'woo-product-filter' ), 'source' => __( 'On sale', 'woo-product-filter' ), 'settings' => array( 'f_checkbox_label' => __( 'On sale', 'woo-product-filter' ) ) );
			case 'by_featured':
				$this->row( $mapped, 'by_featured.show', '1', 'wpfFeatured' );
				return array( 'type' => 'featured', 'title' => __( 'Featured', 'woo-product-filter' ), 'source' => __( 'Featured products', 'woo-product-filter' ) );
			case 'by_rating':
				$this->row( $mapped, 'by_rating.show', '1', 'wpfRating (N stars and up)' );
				return array( 'type' => 'rating', 'title' => __( 'Rating', 'woo-product-filter' ), 'display' => 'dropdown', 'source' => __( 'By rating drop-down', 'woo-product-filter' ), 'settings' => array( 'f_show_count' => $showCount ? '1' : '0' ) );
			case 'by_author':
				$this->row( $mapped, 'by_author.show', '1', 'wpfAuthor' );
				return array( 'type' => 'author', 'title' => ! empty( $o['placeholder'] ) ? (string) $o['placeholder'] : __( 'Author', 'woo-product-filter' ), 'display' => isset( $o['view'] ) && 'checkbox' === $o['view'] ? 'checkbox' : 'dropdown', 'source' => __( 'Search by Author', 'woo-product-filter' ) );
			case 'by_text':
			case 'by_sku':
				if ( apply_filters( 'woobewoo_pf_is_pro', false ) ) {
					$this->row( $mapped, $item . '.show', '1', 'wpfSearchText' );
					return array( 'type' => 'search', 'title' => ! empty( $o['title'] ) ? (string) $o['title'] : __( 'Search', 'woo-product-filter' ), 'source' => 'by_text' === $item ? __( 'Search by Text', 'woo-product-filter' ) : __( 'Search by SKU', 'woo-product-filter' ) );
				}
				$this->row( $unmapped, $item . '.show', '1', __( 'Text search block needs Pro.', 'woo-product-filter' ) );
				return null;
			case 'by_backorder':
				$this->row( $unmapped, 'by_backorder.show', '1', __( 'Use the "In stock" block or hide backorders in WooCommerce.', 'woo-product-filter' ) );
				return null;
		}
		return null;
	}

	/* ------------------------------------------------------------------ placements */

	public function signatures() {
		return array(
			'widgets'    => array( 'woof_widget' ),
			'shortcodes' => array( 'woof', 'woof_front_builder' ),
			'blocks'     => array(),
			'products'   => array( 'woof_products' ),
		);
	}

	/**
	 * [woof_products per_page=24 columns=3 taxonomies="product_cat:16+pa_color:44,45" orderby=.. order=..] =>
	 * WooCommerce [products] (our filter drives it): category / tag / one attribute, limit, columns, order, paging.
	 */
	public function productsReplacement( $tag, $atts ) {
		if ( 'woof_products' !== $tag ) {
			return parent::productsReplacement( $tag, $atts );
		}
		$atts = (array) $atts;
		$out  = array();
		$per  = isset( $atts['per_page'] ) ? (int) $atts['per_page'] : 0;
		if ( $per <= 0 ) {
			$per = (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() );
		}
		$out['limit'] = $per;
		if ( ! empty( $atts['columns'] ) && (int) $atts['columns'] > 0 ) {
			$out['columns'] = (int) $atts['columns'];
		}
		$attrDone = false;
		foreach ( explode( '+', isset( $atts['taxonomies'] ) ? (string) $atts['taxonomies'] : '' ) as $part ) {
			$part = explode( ':', trim( $part ), 2 );
			if ( 2 !== count( $part ) || ! taxonomy_exists( $part[0] ) ) {
				continue;
			}
			$slugs = array();
			foreach ( array_filter( array_map( 'intval', explode( ',', $part[1] ) ) ) as $id ) {
				$t = get_term( $id, $part[0] );
				if ( $t && ! is_wp_error( $t ) ) {
					$slugs[] = $t->slug;
				}
			}
			if ( ! $slugs ) {
				continue;
			}
			if ( 'product_cat' === $part[0] ) {
				$out['category'] = implode( ',', $slugs );
			} elseif ( 'product_tag' === $part[0] ) {
				$out['tag'] = implode( ',', $slugs );
			} elseif ( 0 === strpos( $part[0], 'pa_' ) && ! $attrDone ) {
				$out['attribute'] = substr( $part[0], 3 );
				$out['terms']     = implode( ',', $slugs );
				$attrDone         = true;
			}
		}
		$orderby = isset( $atts['orderby'] ) ? sanitize_key( $atts['orderby'] ) : 'no';
		if ( 'price-desc' === $orderby ) {
			$out['orderby'] = 'price';
			$out['order']   = 'DESC';
		} elseif ( 'no' !== $orderby && '' !== $orderby ) {
			$out['orderby'] = $orderby;
			if ( ! empty( $atts['order'] ) && 'no' !== $atts['order'] ) {
				$out['order'] = strtoupper( sanitize_key( $atts['order'] ) );
			}
		}
		$out['paginate'] = 'true';
		$out['cache']    = 'false';
		$sc = '[products';
		foreach ( $out as $k => $v ) {
			$sc .= ' ' . $k . '="' . esc_attr( (string) $v ) . '"';
		}
		return $sc . ']';
	}

	/* ------------------------------------------------------------------ old HUSKY links */

	/**
	 * Our key for a taxonomy (4.0 URL contract): category, tag, brand, or the attribute slug without pa_.
	 */
	private static function ourKey( $tax ) {
		$map = array( 'product_cat' => 'category', 'product_tag' => 'tag', 'product_brand' => 'brand' );
		if ( isset( $map[ $tax ] ) ) {
			return $map[ $tax ];
		}
		return 0 === strpos( $tax, 'pa_' ) ? substr( $tax, 3 ) : '';
	}

	private static function slugs( $raw ) {
		$out = array();
		foreach ( preg_split( '/,|-and-|-or-/', (string) $raw ) as $v ) {
			$v = sanitize_title( urldecode( trim( $v ) ) );
			if ( '' !== $v ) {
				$out[] = $v;
			}
		}
		return implode( ',', array_unique( $out ) );
	}

	/**
	 * HUSKY links after the switch: /<page>/swoof/color-red-and-blue/size-m/price-20-to-60/instock/onsale/min_rating-4/
	 * (swoof<N> for front builder forms, slug from swoof_search_slug) and ?swoof=1&pa_color=red,blue&product_cat=..&min_price=..
	 * &max_price=..&stock=instock&onsales=salesonly&min_rating=4 => our wpf_* args on the same page.
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		$s     = $this->settings();
		$slug  = ! empty( $s['swoof_search_slug'] ) ? sanitize_title( $s['swoof_search_slug'] ) : 'swoof';
		$taxes = array();
		foreach ( get_object_taxonomies( 'product' ) as $t ) {
			if ( '' !== self::ourKey( $t ) ) {
				$taxes[ preg_replace( '/^pa_/', '', $t ) ] = $t;
			}
		}
		uksort(
			$taxes,
			function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);
		$args = array();
		$base = (string) $path;
		$segs = array_values( array_filter( explode( '/', trim( (string) $path, '/' ) ), 'strlen' ) );
		$at   = -1;
		foreach ( $segs as $i => $seg ) {
			if ( preg_match( '/^' . preg_quote( $slug, '/' ) . '\d*$/', $seg ) ) {
				$at = $i;
				break;
			}
		}
		if ( $at >= 0 ) {
			$base = '/' . implode( '/', array_slice( $segs, 0, $at ) ) . ( $at ? '/' : '' );
			$rest = array_slice( $segs, $at + 1 );
			for ( $i = 0; $i < count( $rest ); $i++ ) {
				$seg = urldecode( $rest[ $i ] );
				if ( 'page' === $seg && isset( $rest[ $i + 1 ] ) && is_numeric( $rest[ $i + 1 ] ) ) {
					if ( (int) $rest[ $i + 1 ] > 1 ) {
						$args['wpf_page'] = (string) (int) $rest[ $i + 1 ];
					}
					$i++;
					continue;
				}
				if ( 'instock' === $seg ) {
					$args['wpf_stock'] = 'in';
				} elseif ( 'onsale' === $seg ) {
					$args['wpf_sale'] = '1';
				} elseif ( 'featured' === $seg ) {
					$args['wpf_featured'] = '1';
				} elseif ( preg_match( '/^price-([\d.]*)-to-([\d.]*)$/', $seg, $m ) ) {
					$args['wpf_price'] = $m[1] . '-' . $m[2];
				} elseif ( preg_match( '/^min_rating-(\d)$/', $seg, $m ) ) {
					$args['wpf_rating'] = $m[1];
				} else {
					foreach ( $taxes as $key => $tax ) {
						if ( 0 === strpos( $seg, $key . '-' ) ) {
							$v = self::slugs( substr( $seg, strlen( $key ) + 1 ) );
							if ( '' !== $v ) {
								$args[ 'wpf_' . self::ourKey( $tax ) ] = $v;
							}
							break;
						}
					}
				}
			}
		} elseif ( isset( $query[ $slug ] ) ) {
			foreach ( $query as $k => $v ) {
				if ( ! is_scalar( $v ) ) {
					continue;
				}
				$k = (string) $k;
				if ( taxonomy_exists( $k ) && '' !== self::ourKey( $k ) ) {
					$v = self::slugs( $v );
					if ( '' !== $v ) {
						$args[ 'wpf_' . self::ourKey( $k ) ] = $v;
					}
				}
			}
			$min = isset( $query['min_price'] ) && is_numeric( $query['min_price'] ) ? (string) (float) $query['min_price'] : '';
			$max = isset( $query['max_price'] ) && is_numeric( $query['max_price'] ) ? (string) (float) $query['max_price'] : '';
			if ( '' !== $min || '' !== $max ) {
				$args['wpf_price'] = $min . '-' . $max;
			}
			if ( isset( $query['stock'] ) && 'instock' === $query['stock'] ) {
				$args['wpf_stock'] = 'in';
			} elseif ( isset( $query['stock'] ) && 'outofstock' === $query['stock'] ) {
				$args['wpf_stock'] = 'out';
			}
			if ( isset( $query['onsales'] ) && 'salesonly' === $query['onsales'] ) {
				$args['wpf_sale'] = '1';
			}
			if ( isset( $query['product_visibility'] ) && 'featured' === $query['product_visibility'] ) {
				$args['wpf_featured'] = '1';
				unset( $args['wpf_product_visibility'] );
			}
			if ( isset( $query['min_rating'] ) && (int) $query['min_rating'] > 0 ) {
				$args['wpf_rating'] = (string) min( 5, (int) $query['min_rating'] );
			}
			if ( isset( $query['paged'] ) && (int) $query['paged'] > 1 ) {
				$args['wpf_page'] = (string) (int) $query['paged'];
			}
		} else {
			return null;
		}
		if ( ! empty( $query['orderby'] ) && is_scalar( $query['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $query['orderby'] );
		}
		return $args ? array( 'path' => $base, 'args' => $args ) : null;
	}

	public function setOfPlacement( $kind, $name, $data ) {
		$data = (array) $data;
		if ( 'widget' === $kind && 'woof_widget' === $name ) {
			return 'global';
		}
		if ( 'shortcode' === $kind && 'woof' === $name ) {
			$key = $this->shapeKey( $data );
			return '' === $key ? 'global' : 'sc-' . $key;
		}
		if ( 'shortcode' === $kind && 'woof_front_builder' === $name ) {
			$id = isset( $data['name'] ) ? $this->builderIdByName( $data['name'] ) : 0;
			return $id ? 'fb-' . $id : '';
		}
		return '';
	}

	/**
	 * woof_set_automatically: 1 everywhere, 2 phones only, 3 desktop only. HUSKY prints it above the products
	 * on the shop and product archives. Phones only next to a sidebar widget is mapped to our phone drawer instead.
	 */
	public function autoPlacement( $setId ) {
		if ( 'global' !== (string) $setId ) {
			return null;
		}
		$mode = (int) get_option( 'woof_set_automatically', 0 );
		if ( ! $mode || ( 2 === $mode && $this->widgets() ) ) {
			return null;
		}
		$labels = array(
			1 => __( 'HUSKY automatic filter above the products', 'woo-product-filter' ),
			2 => __( 'HUSKY automatic filter above the products (phones only)', 'woo-product-filter' ),
			3 => __( 'HUSKY automatic filter above the products (desktop only)', 'woo-product-filter' ),
		);
		return array(
			'label' => isset( $labels[ $mode ] ) ? $labels[ $mode ] : $labels[1],
			'place' => 'above',
			'pages' => 'both',
		);
	}

	public function disableAutoPlacement( $setId ) {
		if ( 'global' !== (string) $setId ) {
			return array();
		}
		$old = get_option( 'woof_set_automatically', null );
		update_option( 'woof_set_automatically', 0 );
		return array( 'woof_set_automatically' => $old );
	}
}
