<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Annasta_Model Class
 *
 * annasta Filters for WooCommerce (1.8.x, free; premium keys are read when present).
 *
 * Everything lives in wp_options. `awf_presets` = array( preset id => array( position, filters => array( filter id =>
 * position ), associations => array( 'all' | 'shop-pages' | 'wp_page--<id>' | 'awf-open--<tax>--...' ), overrides ) ).
 * The free version allows one preset. Preset options: awf_preset_<pid>_name, _title, _description, _type
 * (ajax|ajax-button|ajax-delegated|url|form|sbs), _reset_btn (none|top|bottom|both), _reset_btn_label,
 * _filter_btn_label, _show_title_badges, _display_mode (visible|visible-on-s|visible-on-l|togglable|togglable-on-s),
 * _togglable_mode, _responsive_width. Filters: awf_filter_<pid>-<fid>_name (search|price|stock|onsale|rating|
 * featured|ppp|orderby|meta|taxonomy--<taxonomy>), _module and _settings (PHP booleans inside settings; preset and
 * plugin wide options use "yes"/"no"). Term lists (excluded_items, included_items, style_options.colours) are keyed
 * by term id.
 *
 * Placements: widget `awf_widget` ({preset_id}), shortcode [annasta_filters preset_id="" | preset_name=""], block
 * a-w-f/awf-block ({annastaPreset: "1", blockID}). Activation creates "Default Preset" (search, categories, price) in
 * the "togglable" display mode: a "Filters" button and popup sidebar inserted automatically on every page the preset
 * is associated with. "togglable-on-s" shows the placed preset on wide screens and the popup below the responsive width.
 *
 * @version 4.0.1
 * @since   4.0.1
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Annasta_Model extends WooBeWoo_PF_Importer_Base_Model {

	const WIDGET = 'awf_widget';
	const SC     = 'annasta_filters';
	const BLOCK  = 'a-w-f/awf-block';

	/** What a fresh activation creates: an owner who never configured annasta has only this. */
	const DEFAULT_NAME = 'Default Preset';

	private $placementCache = null;

	public function slug() {
		return 'annasta';
	}

	public function name() {
		return 'annasta Filters for WooCommerce';
	}

	public function pluginFiles() {
		return array( 'annasta-woocommerce-product-filters/annasta-woocommerce-product-filters.php', 'annasta-woocommerce-product-filters-premium/annasta-woocommerce-product-filters.php' );
	}

	public function installed() {
		return (bool) $this->presets();
	}

	/* ------------------------------------------------------------------ reading their data */

	private function presets() {
		$p = get_option( 'awf_presets', array() );
		return is_array( $p ) ? $p : array();
	}

	/**
	 * "yes"/"no" strings (preset and plugin options) and real booleans (filter settings) alike.
	 */
	private static function isYes( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( strtolower( trim( (string) $v ) ), array( 'yes', '1', 'on', 'true' ), true );
	}

	private function presetOpt( $pid, $key, $default = '' ) {
		$v = get_option( 'awf_preset_' . (int) $pid . '_' . $key, null );
		return null === $v || false === $v ? $default : $v;
	}

	private function presetName( $pid ) {
		$name = trim( (string) $this->presetOpt( $pid, 'name', '' ) );
		/* translators: %d: preset id */
		return '' !== $name ? $name : sprintf( __( 'annasta preset #%d', 'woo-product-filter' ), (int) $pid );
	}

	/**
	 * Filters of a preset in display order: array( array( 'id', 'name', 'module', 'settings' ) ).
	 */
	private function filtersOf( $pid ) {
		$all = $this->presets();
		if ( empty( $all[ $pid ]['filters'] ) || ! is_array( $all[ $pid ]['filters'] ) ) {
			return array();
		}
		$pos = $all[ $pid ]['filters'];
		asort( $pos, SORT_NUMERIC );
		$out = array();
		foreach ( array_keys( $pos ) as $fid ) {
			$prefix   = 'awf_filter_' . (int) $pid . '-' . (int) $fid . '_';
			$name     = (string) get_option( $prefix . 'name', '' );
			$settings = get_option( $prefix . 'settings', array() );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'       => (int) $fid,
				'name'     => $name,
				'module'   => (string) get_option( $prefix . 'module', '' ),
				'settings' => is_array( $settings ) ? $settings : array(),
			);
		}
		return $out;
	}

	/**
	 * Preset id named by a widget instance, shortcode atts or block attrs, or 0.
	 */
	private function presetFrom( $data, $idKey, $nameKey = '' ) {
		$data = (array) $data;
		$all  = $this->presets();
		if ( isset( $data[ $idKey ] ) && '' !== (string) $data[ $idKey ] && isset( $all[ (int) $data[ $idKey ] ] ) ) {
			return (int) $data[ $idKey ];
		}
		if ( $nameKey && ! empty( $data[ $nameKey ] ) ) {
			foreach ( array_keys( $all ) as $pid ) {
				if ( (string) $this->presetOpt( $pid, 'name', '' ) === (string) $data[ $nameKey ] ) {
					return (int) $pid;
				}
			}
		}
		return 0;
	}

	private function sidebarName( $id ) {
		global $wp_registered_sidebars;
		return isset( $wp_registered_sidebars[ $id ]['name'] ) ? $wp_registered_sidebars[ $id ]['name'] : $id;
	}

	/**
	 * Where each preset is placed today: array( preset id => array( labels ) ).
	 */
	private function placements() {
		if ( null !== $this->placementCache ) {
			return $this->placementCache;
		}
		global $wpdb;
		$out      = array();
		$sidebars = get_option( 'sidebars_widgets', array() );
		$inst     = get_option( 'widget_' . self::WIDGET, array() );
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				if ( preg_match( '/^' . preg_quote( self::WIDGET, '/' ) . '-(\d+)$/', (string) $wid, $m ) && isset( $inst[ (int) $m[1] ] ) ) {
					$pid = $this->presetFrom( $inst[ (int) $m[1] ], 'preset_id' );
					if ( $pid ) {
						/* translators: %s: widget area name */
						$out[ $pid ][] = sprintf( __( 'widget in %s', 'woo-product-filter' ), $this->sidebarName( $sidebar ) );
					}
				}
			}
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status IN ('publish','draft','private','future','pending') AND (post_content LIKE %s OR post_content LIKE %s) LIMIT 500", '%[' . $wpdb->esc_like( self::SC ) . '%', '%' . $wpdb->esc_like( 'wp:' . self::BLOCK ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $r ) {
			$pids = array();
			if ( preg_match_all( '/' . get_shortcode_regex( array( self::SC ) ) . '/', $r['post_content'], $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					$pids[] = $this->presetFrom( shortcode_parse_atts( $m[3] ), 'preset_id', 'preset_name' );
				}
			}
			if ( preg_match_all( '#<!--\s+wp:' . preg_quote( self::BLOCK, '#' ) . '\s+(\{.*?\})\s+/?-->#', $r['post_content'], $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					$pids[] = $this->presetFrom( json_decode( $m[1], true ), 'annastaPreset' );
				}
			}
			foreach ( array_unique( array_filter( $pids ) ) as $pid ) {
				$out[ $pid ][] = '' !== $r['post_title'] ? $r['post_title'] : '#' . $r['ID'];
			}
		}
		$this->placementCache = $out;
		return $out;
	}

	/**
	 * The preset as activation left it: Default Preset with search, categories and price, never placed.
	 */
	private function untouched( $pid ) {
		$places = $this->placements();
		if ( ! empty( $places[ $pid ] ) || self::DEFAULT_NAME !== (string) $this->presetOpt( $pid, 'name', '' ) ) {
			return false;
		}
		return array( 'search', 'taxonomy--product_cat', 'price' ) === wp_list_pluck( $this->filtersOf( $pid ), 'name' );
	}

	private function isTogglable( $pid ) {
		return in_array( (string) $this->presetOpt( $pid, 'display_mode', 'togglable' ), array( 'togglable', 'togglable-on-s' ), true );
	}

	/* ------------------------------------------------------------------ sets */

	public function sets() {
		$out    = array();
		$places = $this->placements();
		foreach ( array_keys( $this->presets() ) as $pid ) {
			$pid   = (int) $pid;
			$where = isset( $places[ $pid ] ) ? array_unique( $places[ $pid ] ) : array();
			$mode  = (string) $this->presetOpt( $pid, 'display_mode', 'togglable' );
			$notes = array();
			if ( $this->untouched( $pid ) ) {
				$notes[] = __( 'Not configured: the preset annasta creates on activation, shown only as its automatic "Filters" popup.', 'woo-product-filter' );
			} elseif ( $where ) {
				/* translators: %s: list of places */
				$notes[] = sprintf( __( 'Shown in: %s.', 'woo-product-filter' ), implode( ', ', $where ) );
			}
			if ( 'togglable' === $mode ) {
				$notes[] = __( 'annasta adds a "Filters" button with a popup on every screen size.', 'woo-product-filter' );
			} elseif ( 'togglable-on-s' === $mode ) {
				$notes[] = __( 'On phones annasta shows a "Filters" button with a popup.', 'woo-product-filter' );
			} elseif ( ! $where ) {
				$notes[] = __( 'Not shown anywhere.', 'woo-product-filter' );
			}
			$out[] = array(
				'id'        => (string) $pid,
				'title'     => $this->presetName( $pid ),
				'blocks'    => count( $this->filtersOf( $pid ) ),
				'shortcode' => '[' . self::SC . ' preset_id=' . $pid . ']',
				'note'      => implode( ' ', $notes ),
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ mapping */

	public function map( $setId ) {
		$pid = (int) $setId;
		$all = $this->presets();
		if ( ! isset( $all[ $pid ] ) ) {
			return new WP_Error( 'wpf_bad_set', __( 'Preset not found.', 'woo-product-filter' ) );
		}
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$isPro    = (bool) apply_filters( 'woobewoo_pf_is_pro', false );

		foreach ( $this->filtersOf( $pid ) as $f ) {
			$s     = $f['settings'];
			$title = isset( $s['title'] ) ? wp_specialchars_decode( (string) $s['title'], ENT_QUOTES ) : '';
			$label = '' !== $title ? $title : $f['name'];
			$block = null;
			if ( 0 === strpos( $f['name'], 'taxonomy--' ) ) {
				$block = $this->mapTax( substr( $f['name'], 10 ), $s, $title, $label, $mapped, $unmapped );
			} else {
				$block = $this->mapModule( $f['name'], $s, $title, $label, $isPro, $mapped, $unmapped );
			}
			if ( ! $block ) {
				continue;
			}
			$block['source'] = $label;
			$this->mapCommon( $block, $s, $label, $mapped, $unmapped );
			$blocks[] = $block;
		}

		return array(
			'title'    => $this->presetName( $pid ),
			'blocks'   => $blocks,
			'main'     => $this->mainFor( $pid, $mapped, $unmapped ),
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/**
	 * Title, folding title and per-filter Apply button (every filter type has them).
	 */
	private function mapCommon( &$block, $s, $label, &$mapped, &$unmapped ) {
		$settings = isset( $block['settings'] ) ? $block['settings'] : array();
		$show     = ! isset( $s['show_title'] ) || self::isYes( $s['show_title'] );
		$fold     = ! empty( $s['is_collapsible'] ) && self::isYes( $s['is_collapsible'] );
		$closed   = $fold && ! empty( $s['collapsed_on'] ) && self::isYes( $s['collapsed_on'] );
		if ( ! $show && ! $fold ) {
			$settings['f_enable_title'] = 'no';
		} else {
			$settings['f_enable_title'] = $closed ? 'yes_close' : 'yes_open';
		}
		/* translators: %s: filter name */
		$this->row( $mapped, sprintf( __( '%s: title', 'woo-product-filter' ), $label ), $show || $fold ? ( $closed ? __( 'collapsed', 'woo-product-filter' ) : ( $fold ? __( 'collapsible, open', 'woo-product-filter' ) : __( 'shown', 'woo-product-filter' ) ) ) : __( 'hidden', 'woo-product-filter' ), 'f_enable_title ' . $settings['f_enable_title'] );
		if ( ! empty( $s['button_submission'] ) && self::isYes( $s['button_submission'] ) ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: own Apply button', 'woo-product-filter' ), $label ), isset( $s['style_options']['submit_button_label'] ) ? $s['style_options']['submit_button_label'] : 'yes', __( 'Ours applies a block at once or has one Apply button for the whole filter; this block now applies at once.', 'woo-product-filter' ) );
		}
		if ( ! empty( $s['show_active'] ) && self::isYes( $s['show_active'] ) ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: active badges in the block', 'woo-product-filter' ), $label ), isset( $s['active_prefix'] ) ? (string) $s['active_prefix'] : 'yes', __( 'Ours marks the picked values in the block itself; the drawer can list them as chips.', 'woo-product-filter' ) );
		}
		if ( ! empty( $s['height_limit'] ) && (int) $s['height_limit'] > 0 ) {
			$settings['f_max_height'] = (string) (int) $s['height_limit'];
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: height limit', 'woo-product-filter' ), $label ), (int) $s['height_limit'] . 'px', 'f_max_height' );
		}
		$layout = isset( $s['layout'] ) ? (string) $s['layout'] : '1-column';
		if ( '' !== $layout && '1-column' !== $layout && in_array( $block['type'], array( 'category', 'attribute', 'tags', 'stock', 'brand' ), true ) ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: layout', 'woo-product-filter' ), $label ), $layout, __( 'Our lists are one column (buttons and swatches wrap to the width).', 'woo-product-filter' ) );
		}
		$block['settings'] = $settings;
	}

	/**
	 * Display for an annasta taxonomy style.
	 */
	private function displayFor( $style, $single ) {
		switch ( $style ) {
			case 'colours':
				return 'colors';
			case 'tags':
			case 'labels':
				return 'buttons';
			default: // checkboxes, radios, icons (custom tick icons), custom-terms, images
				return $single ? 'radio' : 'checkbox';
		}
	}

	private function mapTax( $tax, $s, $title, $label, &$mapped, &$unmapped ) {
		$type    = isset( $s['type'] ) ? (string) $s['type'] : 'multi';
		$style   = isset( $s['style'] ) ? (string) $s['style'] : 'checkboxes';
		$single  = 'single' === $type;
		$display = $this->displayFor( $style, $single );
		$block   = $this->taxonomyBlock( $tax, $title, $display, $unmapped );
		if ( ! $block ) {
			return null;
		}
		$set = array();
		$this->row( $mapped, $label, $tax . ' / ' . $style . ' / ' . $type, $block['type'] . ' / ' . $display );
		if ( 'icons' === $style || 'images' === $style || 'custom-terms' === $style ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: custom icons', 'woo-product-filter' ), $label ), $style, $single ? __( 'Shown as a radio list.', 'woo-product-filter' ) : __( 'Shown as tick boxes.', 'woo-product-filter' ) );
		}

		// Picking: one value, or several. annasta free always matches any picked value (IN); an AND operator per
		// taxonomy only exists as the premium option awf_<taxonomy>_query_operator.
		if ( $single ) {
			$set['logic']           = 'single';
			$set['f_single_select'] = '1';
			if ( 'colors' === $display ) {
				$set['f_colors_singleselect'] = '1';
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: picking', 'woo-product-filter' ), $label ), 'single', __( 'one value', 'woo-product-filter' ) );
		} else {
			$and = 'AND' === strtoupper( (string) get_option( 'awf_' . $tax . '_query_operator', 'IN' ) );
			$set['logic']         = $and ? 'and' : 'or';
			$set['f_query_logic'] = $set['logic'];
			if ( 'category' === $block['type'] ) {
				$set['f_multi_logic'] = $set['logic'];
			}
			if ( 'buttons' === $display ) {
				$set['f_buttons_multiselect'] = '1';
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: several picks match', 'woo-product-filter' ), $label ), $and ? 'AND' : 'OR', $and ? __( 'all picked values', 'woo-product-filter' ) : __( 'any picked value', 'woo-product-filter' ) );
		}

		// Counts.
		$count               = ! empty( $s['show_count'] ) && self::isYes( $s['show_count'] );
		$block['show_count'] = $count ? 1 : 0;
		/* translators: %s: filter name */
		$this->row( $mapped, sprintf( __( '%s: product counts', 'woo-product-filter' ), $label ), $count ? 'yes' : 'no', 'f_show_count' );
		if ( isset( $s['hide_empty'] ) && '' !== (string) $s['hide_empty'] && 'none' !== (string) $s['hide_empty'] ) {
			$set['f_hide_empty'] = '1';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: values with no products', 'woo-product-filter' ), $label ), (string) $s['hide_empty'], 'f_hide_empty' );
		}

		// Values shown (term ids).
		$mode     = isset( $s['terms_limitation_mode'] ) ? (string) $s['terms_limitation_mode'] : 'exclude';
		$excluded = $this->existingTerms( isset( $s['excluded_items'] ) ? $s['excluded_items'] : array(), $tax );
		$included = $this->existingTerms( isset( $s['included_items'] ) ? $s['included_items'] : array(), $tax );
		if ( 'include' === $mode && $included ) {
			$set['f_mlist[]'] = implode( ',', $included );
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: only these values', 'woo-product-filter' ), $label ), implode( ', ', $this->termNames( $included ) ), 'f_mlist[]' );
		} elseif ( 'exclude' === $mode && $excluded ) {
			$set['f_exclude_terms'] = implode( ',', $excluded );
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: hidden values', 'woo-product-filter' ), $label ), implode( ', ', $this->termNames( $excluded ) ), 'f_exclude_terms' );
		} elseif ( 'active' === $mode ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: "active filters" mode', 'woo-product-filter' ), $label ), 'active', __( 'Ours always lists every value of the block.', 'woo-product-filter' ) );
		}

		// Order of the values.
		$sortBy = isset( $s['sort_by'] ) ? (string) $s['sort_by'] : 'admin';
		$order  = isset( $s['sort_order'] ) && 'desc' === strtolower( (string) $s['sort_order'] ) ? 'desc' : 'asc';
		if ( 'name' === $sortBy || 'numeric' === $sortBy ) {
			$set['f_sort_by'] = $order;
			if ( 'numeric' === $sortBy ) {
				$set['f_sort_as_numbers'] = '1';
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), $sortBy . ' ' . $order, 'f_sort_by ' . $order . ( 'numeric' === $sortBy ? ' + f_sort_as_numbers' : '' ) );
		} elseif ( 'term_id' === $sortBy ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), 'term_id ' . $order, __( 'Ours uses the WooCommerce order of the values.', 'woo-product-filter' ) );
		} else {
			$set['f_sort_by'] = 'default';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: order', 'woo-product-filter' ), $label ), 'admin', 'f_sort_by default (WooCommerce order)' );
		}

		// Hierarchy (categories): ours always draws the whole tree, unfolded.
		if ( is_taxonomy_hierarchical( $tax ) ) {
			$children = ! isset( $s['display_children'] ) || self::isYes( $s['display_children'] );
			if ( $children ) {
				$set['f_show_hierarchical'] = '1';
			} elseif ( empty( $set['f_mlist[]'] ) ) {
				// Top level values only: ours lists exactly those (a picked list is drawn flat).
				$top = get_terms( array( 'taxonomy' => $tax, 'parent' => 0, 'hide_empty' => false, 'fields' => 'ids' ) );
				$top = is_wp_error( $top ) ? array() : array_values( array_diff( array_map( 'intval', $top ), $excluded ) );
				if ( $top ) {
					$set['f_mlist[]'] = implode( ',', $top );
				}
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: hierarchy', 'woo-product-filter' ), $label ), $children ? __( 'parents with children', 'woo-product-filter' ) : __( 'top level only', 'woo-product-filter' ), $children ? __( 'tree', 'woo-product-filter' ) : __( 'f_mlist[] = top level values', 'woo-product-filter' ) );
			if ( $children && ! empty( $s['children_collapsible'] ) && self::isYes( $s['children_collapsible'] ) ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: foldable sub-levels', 'woo-product-filter' ), $label ), ! empty( $s['children_collapsible_on'] ) && self::isYes( $s['children_collapsible_on'] ) ? __( 'folded', 'woo-product-filter' ) : __( 'open', 'woo-product-filter' ), __( 'Ours shows the whole tree open.', 'woo-product-filter' ) );
			}
			if ( isset( $s['hierarchical_level'] ) && (int) $s['hierarchical_level'] > 1 ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: starts at level', 'woo-product-filter' ), $label ), (int) $s['hierarchical_level'], __( 'Ours starts at the top level; pick "Only these values" in the builder to narrow it.', 'woo-product-filter' ) );
			}
			if ( 'yes' !== get_option( 'awf_include_children_on', 'yes' ) ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: parent pick includes children', 'woo-product-filter' ), $label ), 'no', __( 'Ours finds products of the sub-categories too when a parent is picked.', 'woo-product-filter' ) );
			}
		}

		// Search box inside the block.
		if ( ! empty( $s['show_search'] ) && self::isYes( $s['show_search'] ) ) {
			$set['f_show_search_input'] = '1';
			if ( ! empty( $s['show_search_placeholder'] ) ) {
				$set['f_search_label'] = (string) $s['show_search_placeholder'];
			}
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: search box', 'woo-product-filter' ), $label ), 'yes', 'f_show_search_input' );
		}

		// Swatch colours (term id => hex).
		if ( 'colors' === $display ) {
			$colours = isset( $s['style_options']['colours'] ) && is_array( $s['style_options']['colours'] ) ? $s['style_options']['colours'] : array();
			$n       = 0;
			foreach ( $colours as $tid => $hex ) {
				$hex = sanitize_hex_color( (string) $hex );
				if ( $hex && term_exists( (int) $tid ) ) {
					$set[ 'f_colors_term' . (int) $tid ] = $hex;
					$n++;
				}
			}
			$set['f_colors_labels'] = empty( $s['style_options']['hide_label'] ) ? '1' : '0';
			/* translators: %s: filter name */
			$this->row( $mapped, sprintf( __( '%s: swatch colours', 'woo-product-filter' ), $label ), $n, __( 'swatch colour of each value (set on values that have none yet)', 'woo-product-filter' ) );
		}

		$block['settings'] = $set;
		return $block;
	}

	/**
	 * The non taxonomy modules.
	 */
	private function mapModule( $name, $s, $title, $label, $isPro, &$mapped, &$unmapped ) {
		switch ( $name ) {
			case 'search':
				if ( ! $isPro ) {
					/* translators: %s: filter name */
					$this->row( $unmapped, $label, __( 'product search box', 'woo-product-filter' ), __( 'The search block needs Product Filter Pro.', 'woo-product-filter' ) );
					return null;
				}
				$set = array();
				if ( ! empty( $s['placeholder'] ) ) {
					$set['f_search_label'] = (string) $s['placeholder'];
				}
				$this->row( $mapped, $label, 'search', 'wpfSearchText (Pro)' );
				if ( ! empty( $s['autocomplete'] ) && self::isYes( $s['autocomplete'] ) ) {
					/* translators: %s: filter name */
					$this->row( $unmapped, sprintf( __( '%s: suggestions while typing', 'woo-product-filter' ), $label ), 'autocomplete', __( 'Ours filters the product list as the shopper types, without a suggestions box.', 'woo-product-filter' ) );
				}
				return array( 'type' => 'search', 'title' => '' !== $title ? $title : __( 'Search', 'woo-product-filter' ), 'settings' => $set );

			case 'price':
				return $this->mapPrice( $s, $title, $label, $mapped, $unmapped );

			case 'stock':
				$keep = array( 'instock', 'outofstock', 'onbackorder' );
				if ( isset( $s['terms_limitation_mode'] ) && 'include' === $s['terms_limitation_mode'] && ! empty( $s['included_items'] ) ) {
					$keep = array_values( array_intersect( $keep, array_map( 'strval', (array) $s['included_items'] ) ) );
				} elseif ( ! empty( $s['excluded_items'] ) ) {
					$keep = array_values( array_diff( $keep, array_map( 'strval', (array) $s['excluded_items'] ) ) );
				}
				$display = isset( $s['type'] ) && 'multi' === $s['type'] ? 'checkbox' : 'radio';
				$this->row( $mapped, $label, 'stock / ' . ( isset( $s['style'] ) ? $s['style'] : 'radios' ), 'wpfInStock / ' . $display . ' (' . implode( ', ', $keep ) . ')' );
				return array( 'type' => 'stock', 'title' => '' !== $title ? $title : __( 'Stock', 'woo-product-filter' ), 'display' => $display, 'show_count' => 0, 'settings' => array( 'f_options[]' => implode( ',', $keep ) ) );

			case 'onsale':
				$this->row( $mapped, $label, 'onsale', 'wpfOnSale' );
				return array( 'type' => 'onsale', 'title' => '' !== $title ? $title : __( 'On sale', 'woo-product-filter' ) );

			case 'featured':
				$this->row( $mapped, $label, 'featured', 'wpfFeatured' );
				return array( 'type' => 'featured', 'title' => '' !== $title ? $title : __( 'Featured', 'woo-product-filter' ) );

			case 'rating':
				$values = isset( $s['type_options']['range_values'] ) ? array_map( 'floatval', (array) $s['type_options']['range_values'] ) : array();
				$this->row( $mapped, $label, 'rating / ' . ( isset( $s['style'] ) ? $s['style'] : 'radios' ), 'wpfRating' );
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: rating bands', 'woo-product-filter' ), $label ), implode( ', ', $values ), __( 'annasta offers bands such as "4 - 5" (4.00 to 4.99, so 5-star products are left out); ours offers "4 stars and up".', 'woo-product-filter' ) );
				return array( 'type' => 'rating', 'title' => '' !== $title ? $title : __( 'Rating', 'woo-product-filter' ) );

			case 'orderby':
				$this->row( $mapped, $label, 'orderby', 'wpfSortBy' );
				return array( 'type' => 'sortby', 'title' => '' !== $title ? $title : __( 'Sort by', 'woo-product-filter' ) );

			case 'meta':
				$key = isset( $s['meta_name'] ) ? sanitize_key( (string) $s['meta_name'] ) : '';
				if ( '' === $key ) {
					$this->row( $unmapped, $label, 'meta', __( 'No custom field was picked in annasta.', 'woo-product-filter' ) );
					return null;
				}
				$this->row( $mapped, $label, 'meta ' . $key, 'wpfCustomField ' . $key );
				return array( 'type' => 'custom_field', 'meta_key' => $key, 'title' => '' !== $title ? $title : $key );

			case 'ppp':
				$this->row( $unmapped, $label, __( 'products per page', 'woo-product-filter' ), __( 'Set products per page under Products in the filter settings; there is no per page block.', 'woo-product-filter' ) );
				return null;
		}
		/* translators: %s: filter name */
		$this->row( $unmapped, sprintf( __( '%s: filter type', 'woo-product-filter' ), $label ), $name, __( 'No matching block in Product Filter.', 'woo-product-filter' ) );
		return null;
	}

	private function mapPrice( $s, $title, $label, &$mapped, &$unmapped ) {
		$style  = isset( $s['style'] ) ? (string) $s['style'] : 'range-slider';
		$values = isset( $s['type_options']['range_values'] ) ? array_values( array_map( 'floatval', (array) $s['type_options']['range_values'] ) ) : array();
		$title  = '' !== $title ? $title : __( 'Price', 'woo-product-filter' );
		$prefix = isset( $s['style_options']['value_prefix'] ) ? trim( (string) $s['style_options']['value_prefix'] ) : '';
		$suffix = isset( $s['style_options']['value_postfix'] ) ? trim( (string) $s['style_options']['value_postfix'] ) : '';
		if ( '' !== $prefix || '' !== $suffix ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: value prefix / suffix', 'woo-product-filter' ), $label ), trim( $prefix . ' … ' . $suffix ), __( 'Ours formats prices with the store currency.', 'woo-product-filter' ) );
		}
		if ( 'range-slider' === $style ) {
			$set  = array();
			$step = isset( $s['style_options']['step'] ) ? (float) $s['style_options']['step'] : 1;
			if ( $step > 0 && 1.0 !== $step ) {
				$set['f_step'] = (string) $step;
			}
			$this->row( $mapped, $label, 'price / range slider', 'wpfPrice slider' . ( isset( $set['f_step'] ) ? ', f_step ' . $set['f_step'] : '' ) );
			if ( 'yes' !== get_option( 'awf_dynamic_price_ranges', 'no' ) && count( $values ) >= 2 ) {
				/* translators: %s: filter name */
				$this->row( $unmapped, sprintf( __( '%s: fixed slider ends', 'woo-product-filter' ), $label ), reset( $values ) . ' - ' . end( $values ), __( 'Our slider runs from the cheapest to the dearest product shown.', 'woo-product-filter' ) );
			}
			return array( 'type' => 'price', 'title' => $title, 'display' => 'slider', 'settings' => $set );
		}
		// Radios, labels, icons: consecutive range values make the ranges ("0, 50, 100" = 0-50, 50-100).
		$lines = array();
		$n     = count( $values );
		for ( $i = 0; $i + 1 < $n; $i++ ) {
			$lines[] = $values[ $i ] . '-' . $values[ $i + 1 ];
		}
		$set = $lines ? array( 'f_range_by_hands' => '1', 'f_range_by_hands_values' => implode( ';', $lines ) ) : array();
		$this->row( $mapped, $label, 'price / ' . $style . ' / ' . count( $lines ) . ' ranges', 'wpfPriceRange f_range_by_hands_values' );
		if ( 'labels' === $style ) {
			/* translators: %s: filter name */
			$this->row( $unmapped, sprintf( __( '%s: look', 'woo-product-filter' ), $label ), 'labels', __( 'Price ranges are shown as a radio list.', 'woo-product-filter' ) );
		}
		return array( 'type' => 'price_range', 'title' => $title, 'settings' => $set );
	}

	private function existingTerms( $ids, $tax ) {
		$out = array();
		foreach ( (array) $ids as $id ) {
			$id = (int) $id;
			if ( $id && term_exists( $id, $tax ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	private function termNames( $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$t     = get_term( (int) $id );
			$out[] = $t && ! is_wp_error( $t ) ? $t->name : '#' . $id;
		}
		return $out;
	}

	/**
	 * Preset-wide switches and the plugin wide ones that change what shoppers get.
	 */
	private function mainFor( $pid, &$mapped, &$unmapped ) {
		$type    = (string) $this->presetOpt( $pid, 'type', 'ajax' );
		$ajax    = in_array( $type, array( 'ajax', 'ajax-button', 'ajax-delegated', 'sbs' ), true );
		$instant = in_array( $type, array( 'ajax', 'ajax-delegated', 'url' ), true );
		$main    = $this->mainFrom( $ajax, null, null, $mapped, array( 'auto_update_filter' => $instant ? '1' : '0' ) );
		$this->row( $mapped, __( 'Filtering style', 'woo-product-filter' ), $type, $instant ? __( 'apply at once', 'woo-product-filter' ) : 'show_filtering_button' );
		if ( ! $instant ) {
			$main['show_filtering_button'] = '1';
			$word                          = trim( (string) $this->presetOpt( $pid, 'filter_btn_label', '' ) );
			if ( '' !== $word ) {
				$main['filtering_button_word'] = $word;
			}
		}
		if ( 'sbs' === $type ) {
			$this->row( $unmapped, __( 'Step by step filtering', 'woo-product-filter' ), 'sbs', __( 'Ours shows all blocks at once with an Apply button.', 'woo-product-filter' ) );
		}

		// Reset button.
		$reset = (string) $this->presetOpt( $pid, 'reset_btn', 'none' );
		$main['show_clean_button'] = 'none' === $reset || '' === $reset ? '0' : '1';
		if ( '1' === $main['show_clean_button'] ) {
			$main['main_buttons_position'] = in_array( $reset, array( 'top', 'bottom', 'both' ), true ) ? $reset : 'top';
			$word                          = trim( (string) $this->presetOpt( $pid, 'reset_btn_label', '' ) );
			if ( '' !== $word ) {
				$main['show_clean_button_word'] = $word;
			}
		}
		$this->row( $mapped, __( 'Reset button', 'woo-product-filter' ), $reset, '1' === $main['show_clean_button'] ? 'show_clean_button, main_buttons_position ' . $main['main_buttons_position'] : 'show_clean_button off' );

		// Phones and the "Filters" popup button.
		$mode  = (string) $this->presetOpt( $pid, 'display_mode', 'togglable' );
		$label = trim( (string) get_option( 'awf_toggle_btn_label', '' ) );
		switch ( $mode ) {
			case 'togglable':
				$main['mobile_drawer']        = '1';
				$main['mobile_drawer_always'] = '1';
				break;
			case 'togglable-on-s':
				$main['mobile_drawer'] = '1';
				if ( ! $this->placementsOf( $pid ) ) {
					$main['display_for'] = 'mobile'; // annasta shows nothing on wide screens without a placement
				}
				break;
			case 'visible-on-s':
				$main['mobile_drawer'] = '0';
				$main['display_for']   = 'mobile';
				break;
			case 'visible-on-l':
				$main['mobile_drawer'] = '0';
				$main['display_for']   = 'desktop';
				break;
			default:
				$main['mobile_drawer'] = '0';
		}
		if ( '1' === $main['mobile_drawer'] && '' !== $label ) {
			$main['mobile_drawer_text'] = $label;
		}
		$ours = 'mobile_drawer ' . $main['mobile_drawer'] . ( isset( $main['mobile_drawer_always'] ) ? ', mobile_drawer_always' : '' ) . ( isset( $main['display_for'] ) ? ', display_for ' . $main['display_for'] : '' );
		$this->row( $mapped, __( 'Phone behaviour', 'woo-product-filter' ), $mode, $ours );
		$width = (int) $this->presetOpt( $pid, 'responsive_width', 768 );
		if ( $width && 768 !== $width ) {
			$main['desctop_mobile_breakpoint_switcher'] = '1';
			$main['desctop_mobile_breakpoint_width']    = (string) $width;
			$this->row( $mapped, __( 'Phone width', 'woo-product-filter' ), $width . 'px', 'desctop_mobile_breakpoint_width' );
		}
		if ( 'above-products' === (string) $this->presetOpt( $pid, 'togglable_mode', 'left-popup-sidebar' ) && '1' === $main['mobile_drawer'] ) {
			$this->row( $unmapped, __( '"Filters" button opens above the products', 'woo-product-filter' ), 'above-products', __( 'Our button opens a side drawer.', 'woo-product-filter' ) );
		}
		if ( self::isYes( $this->presetOpt( $pid, 'show_title_badges', 'yes' ) ) ) {
			$main['mobile_drawer_chips'] = '1';
			$this->row( $mapped, __( 'Active filter badges', 'woo-product-filter' ), 'yes', __( 'mobile_drawer_chips (desktop marks the picked values in each block)', 'woo-product-filter' ) );
		}
		foreach ( array( 'title' => __( 'Preset title', 'woo-product-filter' ), 'description' => __( 'Preset description', 'woo-product-filter' ) ) as $k => $what ) {
			$v = trim( (string) $this->presetOpt( $pid, $k, '' ) );
			if ( '' !== $v ) {
				$this->row( $unmapped, $what, $v, __( 'Ours has no heading above the blocks; add it in the widget title or the page.', 'woo-product-filter' ) );
			}
		}

		// Plugin wide options.
		if ( self::isYes( get_option( 'awf_add_noindex_filtered', 'no' ) ) || self::isYes( get_option( 'awf_add_canonical', 'no' ) ) || 'seo' === get_option( 'awf_page_title', 'wc_default' ) ) {
			$this->row( $unmapped, __( 'SEO for filtered pages', 'woo-product-filter' ), __( 'noindex / canonical / page titles', 'woo-product-filter' ), __( 'Set these store wide under Speed and SEO.', 'woo-product-filter' ) );
		}
		if ( 'none' !== get_option( 'awf_ajax_pagination', 'none' ) && 'page_numbers' !== get_option( 'awf_ajax_pagination', 'none' ) ) {
			$this->row( $unmapped, __( 'Pagination', 'woo-product-filter' ), (string) get_option( 'awf_ajax_pagination', '' ), __( 'Ours keeps the theme page numbers.', 'woo-product-filter' ) );
		}
		return $main;
	}

	/**
	 * Places of the preset; during a switch they already hold our imported filter, so those count too.
	 */
	/**
	 * Their widget for this preset, or our widget with its imported filter, sits in an active widget area.
	 */
	private function inWidgetArea( $pid ) {
		$fid    = $this->importedAs( (string) (int) $pid );
		$theirs = get_option( 'widget_' . self::WIDGET, array() );
		$ours   = get_option( 'widget_wpfwoofilterswidget', array() );
		foreach ( (array) get_option( 'sidebars_widgets', array() ) as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				if ( preg_match( '/^' . preg_quote( self::WIDGET, '/' ) . '-(\d+)$/', (string) $wid, $m ) && isset( $theirs[ (int) $m[1] ] ) && (int) $this->presetFrom( $theirs[ (int) $m[1] ], 'preset_id' ) === (int) $pid ) {
					return true;
				}
				if ( $fid && preg_match( '/^wpfwoofilterswidget-(\d+)$/', (string) $wid, $m ) && isset( $ours[ (int) $m[1] ]['id'] ) && (int) $ours[ (int) $m[1] ]['id'] === $fid ) {
					return true;
				}
			}
		}
		return false;
	}

	private function placementsOf( $pid ) {
		$p   = $this->placements();
		$out = isset( $p[ (int) $pid ] ) ? $p[ (int) $pid ] : array();
		$fid = $this->importedAs( (string) (int) $pid );
		if ( ! $out && $fid ) {
			$sidebars = get_option( 'sidebars_widgets', array() );
			$ours     = get_option( 'widget_wpfwoofilterswidget', array() );
			foreach ( (array) $sidebars as $sidebar => $ids ) {
				if ( 'wp_inactive_widgets' === $sidebar || ! is_array( $ids ) ) {
					continue;
				}
				foreach ( $ids as $wid ) {
					if ( preg_match( '/^wpfwoofilterswidget-(\d+)$/', (string) $wid, $m ) && isset( $ours[ (int) $m[1] ]['id'] ) && (int) $ours[ (int) $m[1] ]['id'] === $fid ) {
						$out[] = $sidebar;
					}
				}
			}
			if ( ! $out ) {
				global $wpdb;
				$hit = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status IN ('publish','draft','private','future','pending') AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) LIMIT 1", '%[' . $wpdb->esc_like( WPF_SHORTCODE . ' id=' . $fid ) . ']%', '%[' . $wpdb->esc_like( WPF_SHORTCODE . ' id="' . $fid . '"' ) . '%', '%[' . $wpdb->esc_like( WPF_SHORTCODE . ' id=' . $fid . ' ' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $hit ) {
					$out[] = '#' . $hit;
				}
			}
		}
		return $out;
	}

	/**
	 * Their per-filter swatch colours become the colour of the value (our swatches read it there), only where the
	 * value has no colour yet: an owner's own swatch colours are never overwritten.
	 */
	public function createDraft( $mapped, $titleSuffix = '', $setId = '' ) {
		if ( class_exists( 'WooBeWoo_PF_Swatches' ) && ! empty( $mapped['blocks'] ) ) {
			foreach ( (array) $mapped['blocks'] as $b ) {
				if ( empty( $b['display'] ) || 'colors' !== $b['display'] || empty( $b['settings'] ) ) {
					continue;
				}
				foreach ( (array) $b['settings'] as $k => $hex ) {
					$hex = sanitize_hex_color( (string) $hex );
					if ( ! $hex || ! preg_match( '/^f_colors_term(\d+)$/', (string) $k, $m ) ) {
						continue;
					}
					$termId = (int) $m[1];
					if ( term_exists( $termId ) && '' === (string) get_term_meta( $termId, WooBeWoo_PF_Swatches::META_COLOR, true ) ) {
						update_term_meta( $termId, WooBeWoo_PF_Swatches::META_COLOR, strtoupper( $hex ) );
					}
				}
			}
		}
		return parent::createDraft( $mapped, $titleSuffix, $setId );
	}

	/* ------------------------------------------------------------------ placements */

	public function signatures() {
		return array(
			'widgets'    => array( self::WIDGET ),
			'shortcodes' => array( self::SC ),
			'blocks'     => array( self::BLOCK ),
			'products'   => array(), // pages hold WooCommerce's own [products] grid: it keeps working
		);
	}

	public function setOfPlacement( $kind, $name, $data ) {
		if ( 'widget' === $kind && self::WIDGET === $name ) {
			$pid = $this->presetFrom( $data, 'preset_id' );
		} elseif ( 'shortcode' === $kind && self::SC === $name ) {
			$pid = $this->presetFrom( $data, 'preset_id', 'preset_name' );
		} elseif ( 'block' === $kind && self::BLOCK === $name ) {
			$pid = $this->presetFrom( $data, 'annastaPreset' );
		} else {
			return '';
		}
		return $pid ? (string) $pid : 'none';
	}

	/**
	 * annasta's own placement is the "Filters" popup button: on every screen (togglable), or on phones only
	 * (togglable-on-s). The phone part is our drawer (mobile_drawer from map()); the auto row decides where our
	 * filter goes when annasta's button was its only place.
	 */
	public function autoPlacement( $setId ) {
		$pid = (int) $setId;
		if ( ! isset( $this->presets()[ $pid ] ) || ! $this->isTogglable( $pid ) ) {
			return null;
		}
		// Only a widget area covers the shop and category pages the popup was on; a single page ('#id') does not
		// (block themes have no shop sidebar, so the shop would be left without a filter).
		$placed = $this->inWidgetArea( $pid );
		if ( 'togglable' === (string) $this->presetOpt( $pid, 'display_mode', 'togglable' ) ) {
			return array(
				'label' => __( 'annasta "Filters" popup button (every screen size)', 'woo-product-filter' ),
				'place' => $placed ? '' : 'drawer',
				'pages' => 'both',
			);
		}
		return array(
			'label' => __( 'annasta "Filters" popup button on phones (becomes our phone drawer)', 'woo-product-filter' ),
			// Placed somewhere: those places carry our filter and its phone drawer, so no extra placement.
			'place' => $placed ? '' : 'drawer',
			'pages' => 'both',
		);
	}

	public function disableAutoPlacement( $setId ) {
		$pid = (int) $setId;
		if ( ! isset( $this->presets()[ $pid ] ) || ! $this->isTogglable( $pid ) ) {
			return array();
		}
		$opt = 'awf_preset_' . $pid . '_display_mode';
		$old = get_option( $opt, null );
		update_option( $opt, 'visible' );
		return array( $opt => $old );
	}

	/**
	 * annasta links (comma separated slugs): product-categories, product-tags, <pa_x>-filter, price-min/max,
	 * rating-min/max, availability, onsale=yes. Pretty names set in awf_<tax>_pretty_name are honoured.
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		$names = array(
			(string) get_option( 'awf_product_cat_pretty_name', 'product-categories' ) => 'category',
			(string) get_option( 'awf_product_tag_pretty_name', 'product-tags' )       => 'tag',
		);
		$args  = array();
		foreach ( $query as $k => $v ) {
			if ( ! is_scalar( $v ) ) {
				continue;
			}
			$k   = (string) $k;
			$key = '';
			if ( isset( $names[ $k ] ) ) {
				$key = $names[ $k ];
			} elseif ( preg_match( '/^pa_([a-z0-9_-]+)-filter$/', $k, $m ) && taxonomy_exists( 'pa_' . $m[1] ) ) {
				$key = $m[1];
			}
			if ( '' === $key ) {
				continue;
			}
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $v ) ) );
			if ( $slugs ) {
				$args[ 'wpf_' . $key ] = implode( ',', $slugs );
			}
		}
		if ( isset( $query['price-min'] ) || isset( $query['price-max'] ) ) {
			$args['wpf_price'] = ( isset( $query['price-min'] ) ? (float) $query['price-min'] : '' ) . '-' . ( isset( $query['price-max'] ) ? (float) $query['price-max'] : '' );
		}
		if ( isset( $query['rating-min'] ) && is_scalar( $query['rating-min'] ) ) {
			$args['wpf_rating'] = (string) (int) $query['rating-min']; // their band (4 to 4.99) becomes "4 and up"
		}
		if ( isset( $query['availability'] ) && 'instock' === (string) $query['availability'] ) {
			$args['wpf_stock'] = 'in';
		}
		if ( isset( $query['onsale'] ) && 'yes' === (string) $query['onsale'] ) {
			$args['wpf_sale'] = '1';
		}
		return $args ? array( 'path' => $path, 'args' => $args ) : null;
	}
}
