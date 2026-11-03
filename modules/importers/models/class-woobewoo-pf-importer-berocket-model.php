<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Berocket_Model Class
 *
 * BeRocket AJAX Product Filters (woocommerce-ajax-filters, free 3.3). Storage, as captured from a real install:
 * - Single filter = post `br_product_filter`, settings in post meta `br_product_filter` (older installs:
 *   `BeRocket_product_new_filter`), defaults merged on save so every key exists: widget_type filter|update_button|
 *   reset_button|selected_area|search_field|sort_field, filter_type attribute|price|tag|all_product_cat|_rating
 *   (premium: custom_taxonomy, _stock_status, _sale, date), attribute (pa_*), filter_title (label / button text),
 *   style (template style slug: checkbox, clean_rounded, square, radio, color, image, select, select2, slider,
 *   new_slider, button_default, sfa_default...), operator OR|AND ('' + single_selection 1 on select / radio),
 *   widget_collapse ''|with_arrow|without_arrow|without_arrow_mobile + widget_is_hide (starts closed),
 *   hide_child_attributes ''|2 (tree)|1 (tree, children folded), order_values_by ''|Alpha|Numeric, order_values_type
 *   asc|desc, select_first_element_text, price_values (slider stops), text_before_price / text_after_price, height,
 *   description, css_class, is_hide_mobile + hide_group[tablet|desktop], data (conditions).
 * - Group = post `br_filters_group`, meta `br_filters_group`: filters (string ids, display order), filters_data
 *   (id => width), custom_class, hide_group (mobile|tablet|desktop), data (conditions).
 * - Swatch colours: table {prefix}berocket_termmeta (berocket_term_id, meta_key color|image, hex without #).
 * - Options: br_filters_options (disable_ajax_loading, selected_area_show, recount_hide, hide_value, seo_friendly_urls,
 *   slug_urls, products_holder_id, elements_above_products = group ids shown above the products).
 * - Placements: widgets berocket_aapf_group (group_id) / berocket_aapf_single (filter_id), shortcodes
 *   [br_filters_group group_id=N] / [br_filter_single filter_id=N], blocks berocket/filter-group {groupId} /
 *   berocket/single-filter {filterId}.
 * - Links: ?filters=color[red-blue]|size[m]|price[5_750]|product_visibility[rated-4]|product_tag[a+b]
 *   ("-" OR, "+" AND, "_" range, "|" between filters; term ids instead of slugs when slug_urls is off), or with the
 *   "separate link" add-on ?pa-color=red,blue&pa-color_operator=AND&pa-price_from=5&pa-price_to=750.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Berocket_Model extends WooBeWoo_PF_Importer_Base_Model {

	const FILTER_TYPE = 'br_product_filter';
	const GROUP_TYPE  = 'br_filters_group';

	private $cache = array();

	public function slug() {
		return 'berocket';
	}

	public function name() {
		return 'BeRocket AJAX Product Filters';
	}

	public function pluginFiles() {
		return array( 'woocommerce-ajax-filters/woocommerce-filters.php', 'woocommerce-ajax-filters-premium/woocommerce-filters.php' );
	}

	public function installed() {
		global $wpdb;
		$posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('br_product_filter','br_filters_group') AND post_status NOT IN ('trash','auto-draft')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $posts > 0;
	}

	/* ------------------------------------------------------------------ reading their data */

	private function posts( $type ) {
		$key = 'p' . $type;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => array( 'publish', 'draft', 'private' ),
					'posts_per_page'   => 300,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
		}
		return $this->cache[ $key ];
	}

	/**
	 * Settings of a single filter (meta br_product_filter, else the pre-3.0 key), or null.
	 */
	private function filterOptions( $id ) {
		$key = 'o' . (int) $id;
		if ( ! array_key_exists( $key, $this->cache ) ) {
			$o = get_post_meta( (int) $id, self::FILTER_TYPE, true );
			if ( ! is_array( $o ) || ! $o ) {
				$o = get_post_meta( (int) $id, 'BeRocket_product_new_filter', true );
			}
			$this->cache[ $key ] = is_array( $o ) && $o ? $o : null;
		}
		return $this->cache[ $key ];
	}

	private function groupOptions( $id ) {
		$o = get_post_meta( (int) $id, self::GROUP_TYPE, true );
		if ( ! is_array( $o ) || ! $o ) {
			$o = get_post_meta( (int) $id, 'br_filter_group', true );
		}
		return is_array( $o ) ? $o : array();
	}

	/**
	 * Filter ids of a group, in display order (stored as strings).
	 */
	private function groupFilterIds( $groupId ) {
		$o   = $this->groupOptions( $groupId );
		$ids = array();
		foreach ( (array) ( isset( $o['filters'] ) ? $o['filters'] : array() ) as $f ) {
			$id = (int) ( is_array( $f ) ? ( isset( $f['filter_id'] ) ? $f['filter_id'] : 0 ) : $f );
			$p  = $id ? get_post( $id ) : null;
			if ( $p && self::FILTER_TYPE === $p->post_type && ! in_array( $p->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private function options() {
		if ( ! isset( $this->cache['opts'] ) ) {
			$o                   = get_option( 'br_filters_options', array() );
			$this->cache['opts'] = is_array( $o ) ? $o : array();
		}
		return $this->cache['opts'];
	}

	/**
	 * Admin title of a post as typed (no texturize).
	 */
	private static function adminTitle( $id ) {
		$t = (string) get_post_field( 'post_title', (int) $id, 'raw' );
		return '' !== $t ? $t : '#' . (int) $id;
	}

	private static function val( $arr, $key, $default = '' ) {
		return isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ? (string) $arr[ $key ] : $default;
	}

	/**
	 * Name the owner sees for a filter: its Filter Title, else its admin title.
	 */
	private function filterLabel( $id ) {
		$o = $this->filterOptions( $id );
		$t = $o ? trim( html_entity_decode( self::val( $o, 'filter_title' ), ENT_QUOTES ) ) : '';
		if ( '' === $t && $o ) {
			$t = trim( self::val( $o, 'title' ) ); // pre-3.0 key
		}
		return '' !== $t ? $t : self::adminTitle( $id );
	}

	/**
	 * Their colour per term: array( term_id => #hex ), from {prefix}berocket_termmeta.
	 */
	private function termColours( $tax ) {
		global $wpdb;
		$key = 'c' . $tax;
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}
		$out   = array();
		$table = $wpdb->prefix . 'berocket_termmeta';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				$rows = $wpdb->get_results( "SELECT berocket_term_id, meta_value FROM `{$table}` WHERE meta_key = 'color' AND berocket_term_id IN (" . implode( ',', array_map( 'intval', $terms ) ) . ')' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix, ids cast to int
				foreach ( (array) $rows as $r ) {
					$hex = sanitize_hex_color( '#' . ltrim( (string) $r->meta_value, '#' ) );
					if ( $hex ) {
						$out[ (int) $r->berocket_term_id ] = $hex;
					}
				}
			}
		}
		$this->cache[ $key ] = $out;
		return $out;
	}

	/* ------------------------------------------------------------------ where their filters are placed */

	/**
	 * Every placement: array( array( 'place' => sidebar:<id>|post:<id>|auto, 'kind' => group|single, 'id' => int, 'label' ) ).
	 */
	private function placements() {
		global $wpdb;
		if ( isset( $this->cache['placements'] ) ) {
			return $this->cache['placements'];
		}
		$out      = array();
		$sidebars = get_option( 'sidebars_widgets', array() );
		$bases    = array(
			'berocket_aapf_group'  => array( 'group', 'group_id' ),
			'berocket_aapf_single' => array( 'single', 'filter_id' ),
		);
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'array_version' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				if ( ! preg_match( '/^(.+)-(\d+)$/', (string) $wid, $m ) || ! isset( $bases[ $m[1] ] ) ) {
					continue;
				}
				$all  = get_option( 'widget_' . $m[1], array() );
				$inst = isset( $all[ (int) $m[2] ] ) ? (array) $all[ (int) $m[2] ] : array();
				$id   = isset( $inst[ $bases[ $m[1] ][1] ] ) ? (int) $inst[ $bases[ $m[1] ][1] ] : 0;
				if ( $id ) {
					$out[] = array( 'place' => 'sidebar:' . $sidebar, 'kind' => $bases[ $m[1] ][0], 'id' => $id, 'label' => $this->sidebarName( $sidebar ) );
				}
			}
		}
		$rows = $wpdb->get_results( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache') AND post_status IN ('publish','draft','private','future','pending') AND (post_content LIKE '%[br\\_filter%' OR post_content LIKE '%<!-- wp:berocket/%') LIMIT 500" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $rows as $r ) {
			foreach ( $this->inContent( (string) $r->post_content ) as $hit ) {
				$out[] = array( 'place' => 'post:' . $r->ID, 'kind' => $hit[0], 'id' => $hit[1], 'label' => '' !== $r->post_title ? $r->post_title : '#' . $r->ID );
			}
		}
		$el = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND meta_value LIKE '%[br\\_filter%' LIMIT 500" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( (array) $el as $r ) {
			foreach ( $this->inContent( wp_unslash( (string) $r->meta_value ) ) as $hit ) {
				$out[] = array( 'place' => 'post:' . $r->post_id, 'kind' => $hit[0], 'id' => $hit[1], 'label' => self::adminTitle( $r->post_id ) );
			}
		}
		foreach ( $this->aboveProducts() as $gid ) {
			$out[] = array( 'place' => 'auto', 'kind' => 'group', 'id' => $gid, 'label' => __( 'above the products (automatic)', 'woo-product-filter' ) );
		}
		$this->cache['placements'] = $out;
		return $out;
	}

	/**
	 * Their shortcodes and blocks in a text: array( array( group|single, id ) ).
	 */
	private function inContent( $content ) {
		$out = array();
		if ( false !== strpos( $content, '[br_filter' ) && preg_match_all( '/' . get_shortcode_regex( array( 'br_filters_group', 'br_filter_single' ) ) . '/', $content, $all, PREG_SET_ORDER ) ) {
			foreach ( $all as $m ) {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					continue;
				}
				$a  = (array) shortcode_parse_atts( $m[3] );
				$id = 'br_filters_group' === $m[2] ? ( isset( $a['group_id'] ) ? (int) $a['group_id'] : 0 ) : ( isset( $a['filter_id'] ) ? (int) $a['filter_id'] : 0 );
				if ( $id ) {
					$out[] = array( 'br_filters_group' === $m[2] ? 'group' : 'single', $id );
				}
			}
		}
		if ( false !== strpos( $content, '<!-- wp:berocket/' ) ) {
			$walk = function ( $blocks ) use ( &$walk, &$out ) {
				foreach ( (array) $blocks as $b ) {
					if ( 'berocket/filter-group' === $b['blockName'] && ! empty( $b['attrs']['groupId'] ) ) {
						$out[] = array( 'group', (int) $b['attrs']['groupId'] );
					} elseif ( 'berocket/single-filter' === $b['blockName'] && ! empty( $b['attrs']['filterId'] ) ) {
						$out[] = array( 'single', (int) $b['attrs']['filterId'] );
					}
					if ( ! empty( $b['innerBlocks'] ) ) {
						$walk( $b['innerBlocks'] );
					}
				}
			};
			$walk( parse_blocks( $content ) );
		}
		return $out;
	}

	private function sidebarName( $id ) {
		global $wp_registered_sidebars;
		return isset( $wp_registered_sidebars[ $id ]['name'] ) ? $wp_registered_sidebars[ $id ]['name'] : $id;
	}

	/**
	 * Groups shown automatically above the products (br_filters_options elements_above_products).
	 */
	private function aboveProducts() {
		$o   = $this->options();
		$ids = array();
		foreach ( (array) ( isset( $o['elements_above_products'] ) ? $o['elements_above_products'] : array() ) as $id ) {
			$p = get_post( (int) $id );
			if ( $p && self::GROUP_TYPE === $p->post_type && 'publish' === $p->post_status ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * The group placed at $place that contains filter $fid, or 0.
	 */
	private function groupHolding( $place, $fid ) {
		foreach ( $this->placements() as $pl ) {
			if ( $pl['place'] === $place && 'group' === $pl['kind'] && in_array( (int) $fid, $this->groupFilterIds( $pl['id'] ), true ) ) {
				return (int) $pl['id'];
			}
		}
		return 0;
	}

	/**
	 * Single filters placed on their own somewhere (not next to a placed group that holds them): array( id => labels ).
	 */
	private function looseSingles() {
		$out = array();
		foreach ( $this->placements() as $pl ) {
			if ( 'single' !== $pl['kind'] || $this->groupHolding( $pl['place'], $pl['id'] ) ) {
				continue;
			}
			$p = get_post( $pl['id'] );
			if ( ! $p || self::FILTER_TYPE !== $p->post_type || in_array( $p->post_status, array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}
			$out[ (int) $pl['id'] ][] = $pl['label'];
		}
		return $out;
	}

	private function placesOf( $kind, $id ) {
		$labels = array();
		foreach ( $this->placements() as $pl ) {
			if ( $kind === $pl['kind'] && (int) $id === (int) $pl['id'] ) {
				$labels[] = $pl['label'];
			}
		}
		return array_values( array_unique( $labels ) );
	}

	/* ------------------------------------------------------------------ sets */

	public function sets() {
		$out = array();
		foreach ( $this->posts( self::GROUP_TYPE ) as $g ) {
			$ids    = $this->groupFilterIds( $g->ID );
			$places = $this->placesOf( 'group', $g->ID );
			$note   = $places
				? sprintf( /* translators: %s: list of places */ __( 'Shown in: %s', 'woo-product-filter' ), implode( ', ', $places ) )
				: __( 'Not placed anywhere: no widget, shortcode, block or automatic placement shows this group.', 'woo-product-filter' );
			if ( 'publish' !== $g->post_status ) {
				$note .= ' · ' . __( 'not published', 'woo-product-filter' );
			}
			$out[] = array(
				'id'        => 'group-' . $g->ID,
				'title'     => '' !== $g->post_title ? $g->post_title : sprintf( /* translators: %d: group id */ __( 'Group #%d', 'woo-product-filter' ), $g->ID ),
				'blocks'    => count( $this->filterIdsOfType( $ids, 'filter' ) ),
				'shortcode' => '[br_filters_group group_id=' . $g->ID . ']',
				'note'      => $note,
			);
		}
		foreach ( $this->looseSingles() as $fid => $labels ) {
			$o = $this->filterOptions( $fid );
			if ( ! $o || 'filter' !== self::val( $o, 'widget_type', 'filter' ) ) {
				continue; // a lone button or selected-filters area is not a filter of its own
			}
			$out[] = array(
				'id'        => 'single-' . $fid,
				'title'     => $this->filterLabel( $fid ),
				'blocks'    => 1,
				'shortcode' => '[br_filter_single filter_id=' . $fid . ']',
				'note'      => sprintf( /* translators: 1: admin title of the filter, 2: list of places */ __( 'Single filter "%1$s" shown on its own in: %2$s', 'woo-product-filter' ), self::adminTitle( $fid ), implode( ', ', array_unique( $labels ) ) ),
			);
		}
		return $out;
	}

	private function filterIdsOfType( $ids, $type ) {
		$out = array();
		foreach ( $ids as $id ) {
			$o = $this->filterOptions( $id );
			if ( $o && $type === self::val( $o, 'widget_type', 'filter' ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ map */

	public function map( $setId ) {
		$setId    = (string) $setId;
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$opts     = $this->options();
		$group    = null;
		if ( 0 === strpos( $setId, 'group-' ) ) {
			$group = get_post( (int) substr( $setId, 6 ) );
			if ( ! $group || self::GROUP_TYPE !== $group->post_type || 'auto-draft' === $group->post_status ) {
				return new WP_Error( 'wpf_bad_set', __( 'Group not found.', 'woo-product-filter' ) );
			}
			$ids   = $this->groupFilterIds( $group->ID );
			$title = '' !== $group->post_title ? $group->post_title : 'BeRocket group #' . $group->ID;
		} elseif ( 0 === strpos( $setId, 'single-' ) ) {
			$fid = (int) substr( $setId, 7 );
			$p   = get_post( $fid );
			if ( ! $p || self::FILTER_TYPE !== $p->post_type || ! $this->filterOptions( $fid ) ) {
				return new WP_Error( 'wpf_bad_set', __( 'Filter not found.', 'woo-product-filter' ) );
			}
			$ids   = array( $fid );
			$title = $this->filterLabel( $fid );
		} else {
			return new WP_Error( 'wpf_bad_set', __( 'Unknown set.', 'woo-product-filter' ) );
		}

		$hideEmpty = in_array( self::val( $opts, 'recount_hide', 'removeRecount' ), array( 'removeFirst', 'removeFirst_recount', 'removeRecount' ), true );
		$hideLive  = in_array( self::val( $opts, 'recount_hide', 'removeRecount' ), array( 'removeFirst_recount', 'removeRecount' ), true );
		$apply     = '';
		$reset     = null;
		$chips     = false;
		foreach ( $ids as $fid ) {
			$o     = $this->filterOptions( $fid );
			$label = $this->filterLabel( $fid );
			if ( ! $o ) {
				$this->row( $unmapped, 'filter ' . $fid . ' (' . self::adminTitle( $fid ) . ')', '', __( 'Its settings are missing (saved before the editor was finished); nothing to import.', 'woo-product-filter' ) );
				continue;
			}
			$wtype = self::val( $o, 'widget_type', 'filter' );
			switch ( $wtype ) {
				case 'update_button':
					$apply = '' !== $label ? $label : __( 'Apply', 'woo-product-filter' );
					$this->row( $mapped, 'filter ' . $fid . ' (' . self::adminTitle( $fid ) . ')', 'Update Products button "' . $apply . '"', __( 'our Apply button (auto_update_filter 0, show_filtering_button 1, apply_button_word)', 'woo-product-filter' ) );
					break;
				case 'reset_button':
					$reset = '' !== $label ? $label : __( 'Clear all', 'woo-product-filter' );
					$this->row( $mapped, 'filter ' . $fid . ' (' . self::adminTitle( $fid ) . ')', 'Reset Products button "' . $reset . '"', __( 'our "Clear all" button (show_clean_button, show_clean_button_word)', 'woo-product-filter' ) );
					break;
				case 'selected_area':
					$chips = true;
					$this->row( $mapped, 'filter ' . $fid . ' (' . self::adminTitle( $fid ) . ')', 'Selected Filters area', __( 'picked values as chips at the top of our filter (show_chips)', 'woo-product-filter' ) );
					break;
				case 'filter':
					$b = $this->filterBlock( $fid, $o, $label, $hideEmpty, $hideLive, $mapped, $unmapped );
					if ( $b ) {
						$blocks[] = $b;
					}
					if ( $group && '1' === self::val( $o, 'is_hide_mobile' ) ) {
						$this->row( $unmapped, 'is_hide_mobile (' . $label . ')', '1', __( 'Blocks are not hidden per device; this block also shows on phones.', 'woo-product-filter' ) );
					}
					break;
				default:
					$this->row( $unmapped, 'filter ' . $fid . ' (' . self::adminTitle( $fid ) . ')', 'widget_type ' . $wtype, __( 'Search and sort fields are not imported; add a Search or Sort by block by hand.', 'woo-product-filter' ) );
			}
		}

		// Whole filter.
		$ajax = empty( $opts['disable_ajax_loading'] );
		$main = $this->mainFrom( $ajax, false, null, $mapped );
		if ( '' !== $apply ) {
			$main['auto_update_filter']    = '0';
			$main['show_filtering_button'] = '1';
			$main['apply_button_word']     = $apply;
			$main['filtering_button_word'] = $apply;
		} else {
			$main['auto_update_filter']    = $ajax ? '1' : '0';
			$main['show_filtering_button'] = $ajax ? '0' : '1';
			$this->row( $mapped, __( 'Apply button', 'woo-product-filter' ), __( 'none in this set: results update at once', 'woo-product-filter' ), $ajax ? 'auto_update_filter 1' : __( 'AJAX is off, so ours shows a Filter button', 'woo-product-filter' ) );
		}
		$main['show_clean_button'] = null !== $reset ? '1' : '0';
		if ( null !== $reset ) {
			$main['show_clean_button_word'] = $reset;
		}
		$globalChips       = ! empty( $opts['selected_area_show'] );
		$main['show_chips'] = $chips || $globalChips ? '1' : '0';
		if ( $globalChips && ! $chips ) {
			$this->row( $mapped, 'selected_area_show', '1', __( 'their selected filters above the products: our chips at the top of the filter', 'woo-product-filter' ) );
		}
		if ( ! empty( $opts['products_holder_id'] ) && 'ul.products' !== $opts['products_holder_id'] ) {
			$main['product_container_selector'] = sanitize_text_field( (string) $opts['products_holder_id'] );
			$this->row( $mapped, 'products_holder_id', $opts['products_holder_id'], 'product_container_selector' );
		}
		if ( ! empty( $opts['hide_value']['sel'] ) ) {
			$this->row( $unmapped, __( 'Hide selected values', 'woo-product-filter' ), '1', __( 'Picked values stay in the list (ticked) and also show as chips.', 'woo-product-filter' ) );
		}
		if ( ! empty( $opts['scroll_shop_top'] ) ) {
			$this->row( $unmapped, 'scroll_shop_top', $opts['scroll_shop_top'], __( 'Ours keeps the page where it is after each update.', 'woo-product-filter' ) );
		}
		if ( ! empty( $opts['seo_friendly_urls'] ) || ! empty( $opts['slug_urls'] ) ) {
			$this->row( $mapped, __( 'Their filter links', 'woo-product-filter' ), '?filters=…' . ( ! empty( $opts['slug_urls'] ) ? ' (slugs)' : ' (term ids)' ), __( 'old links (bookmarks, search engines) redirect to ours after the switch', 'woo-product-filter' ) );
		}
		if ( ! empty( $opts['seo_meta_title'] ) ) {
			$this->row( $unmapped, 'seo_meta_title', '1', __( 'Filtered page titles are not rewritten.', 'woo-product-filter' ) );
		}
		if ( ! empty( $opts['user_custom_css'] ) ) {
			$this->row( $unmapped, 'user_custom_css', __( 'set', 'woo-product-filter' ), __( 'Their CSS targets their markup; check our look by hand.', 'woo-product-filter' ) );
		}

		// Devices and conditions of the set.
		$hide = array();
		$conds = array();
		if ( $group ) {
			$g     = $this->groupOptions( $group->ID );
			$hide  = isset( $g['hide_group'] ) ? (array) $g['hide_group'] : array();
			$conds = isset( $g['data'] ) ? (array) $g['data'] : array();
			if ( ! empty( $g['custom_class'] ) ) {
				$this->row( $unmapped, 'custom_class', $g['custom_class'], __( 'Add the class by hand under the filter\'s design settings if your CSS needs it.', 'woo-product-filter' ) );
			}
			$widths = array();
			foreach ( (array) ( isset( $g['filters_data'] ) ? $g['filters_data'] : array() ) as $id => $fd ) {
				if ( is_array( $fd ) && ! empty( $fd['width'] ) ) {
					$widths[] = self::adminTitle( (int) $id ) . ' ' . $fd['width'];
				}
			}
			if ( $widths ) {
				$this->row( $unmapped, __( 'Filter widths in the group', 'woo-product-filter' ), implode( ', ', $widths ), __( 'Our blocks are stacked full width (use the horizontal layout for side by side).', 'woo-product-filter' ) );
			}
		} else {
			$o     = $this->filterOptions( $ids[0] );
			$hide  = isset( $o['hide_group'] ) ? (array) $o['hide_group'] : array();
			$hide['mobile'] = self::val( $o, 'is_hide_mobile' );
			$conds = isset( $o['data'] ) ? (array) $o['data'] : array();
		}
		$offMobile  = ! empty( $hide['mobile'] );
		$offDesktop = ! empty( $hide['desktop'] );
		if ( $offMobile && ! $offDesktop ) {
			$main['display_for'] = 'desktop';
			$this->row( $mapped, __( 'Hide on mobile', 'woo-product-filter' ), '1', 'display_for desktop' );
		} elseif ( $offDesktop && ! $offMobile ) {
			$main['display_for'] = 'mobile';
			$this->row( $mapped, __( 'Hide on desktop', 'woo-product-filter' ), '1', 'display_for mobile' );
		} elseif ( $offDesktop && $offMobile ) {
			$this->row( $unmapped, __( 'Hide on mobile and desktop', 'woo-product-filter' ), '1', __( 'Shown on every device.', 'woo-product-filter' ) );
		}
		if ( ! empty( $hide['tablet'] ) ) {
			$this->row( $unmapped, __( 'Hide on tablet', 'woo-product-filter' ), '1', __( 'Tablets get the desktop or phone layout by width.', 'woo-product-filter' ) );
		}
		if ( array_filter( $conds ) ) {
			$this->row( $unmapped, __( 'Conditions', 'woo-product-filter' ), wp_json_encode( $conds ), __( 'Pick where the filter shows under "Where it shows" in the builder.', 'woo-product-filter' ) );
		}

		return array(
			'title'    => $title,
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/**
	 * Their style slug => our display.
	 */
	private static function styleDisplay( $style ) {
		$map = array(
			'checkbox'       => 'checkbox',
			'checkbox_hide'  => 'checkbox',
			'square'         => 'checkbox',
			'square-check'   => 'checkbox',
			'radio'          => 'radio',
			'radio-check'    => 'radio',
			'clean_rounded'  => 'buttons',
			'color'          => 'colors',
			'color_woborder' => 'colors',
			'image'          => 'colors',
			'image_woborder' => 'colors',
			'select'         => 'dropdown',
			'select2'        => 'dropdown',
			'select2classic' => 'dropdown',
		);
		$style = (string) $style;
		if ( 0 === strpos( $style, 'button_' ) ) {
			return 'buttons';
		}
		return isset( $map[ $style ] ) ? $map[ $style ] : '';
	}

	/**
	 * One of their filters (widget_type filter) => our block, or null with an unmapped row.
	 */
	private function filterBlock( $fid, $o, $label, $hideEmpty, $hideLive, &$mapped, &$unmapped ) {
		$ftype  = self::val( $o, 'filter_type', 'attribute' );
		$attr   = self::val( $o, 'attribute' );
		$style  = self::val( $o, 'style' );
		$source = $label . ' (' . self::adminTitle( $fid ) . ')';
		$single = '1' === self::val( $o, 'single_selection' );

		$settings = array( 'f_show_count' => '0' );
		$this->row( $mapped, __( 'Product counts', 'woo-product-filter' ) . ' (' . $label . ')', __( 'not shown', 'woo-product-filter' ), 'f_show_count 0' );

		// Title and folding.
		if ( '' === trim( self::val( $o, 'filter_title' ) ) ) {
			$settings['f_enable_title'] = 'no';
			$this->row( $mapped, 'filter_title (' . self::adminTitle( $fid ) . ')', __( 'empty: no title', 'woo-product-filter' ), 'f_enable_title no' );
		} else {
			$collapse = self::val( $o, 'widget_collapse' );
			$closed   = '1' === self::val( $o, 'widget_is_hide' );
			if ( '' !== $collapse ) {
				$settings['f_enable_title'] = $closed ? 'yes_close' : 'yes_open';
				$this->row( $mapped, 'widget_collapse / widget_is_hide (' . $label . ')', $collapse . ( $closed ? ' / closed' : ' / open' ), 'f_enable_title ' . $settings['f_enable_title'] );
				if ( 'without_arrow_mobile' === $collapse ) {
					$this->row( $unmapped, 'widget_collapse (' . $label . ')', $collapse, __( 'Folding only on phones: ours folds on every device.', 'woo-product-filter' ) );
				}
			} else {
				$settings['f_enable_title'] = 'yes_open';
			}
		}
		$description = trim( self::val( $o, 'description' ) );
		if ( '' !== $description ) {
			$settings['f_description'] = wp_strip_all_tags( $description );
			$this->row( $mapped, 'description (' . $label . ')', $description, 'f_description' );
		}
		$height = (int) self::val( $o, 'height' );
		if ( $height > 0 ) {
			$settings['f_max_height'] = (string) $height;
			$this->row( $mapped, 'height (' . $label . ')', $height, 'f_max_height' );
		}
		if ( '' !== self::val( $o, 'css_class' ) ) {
			$this->row( $unmapped, 'css_class (' . $label . ')', self::val( $o, 'css_class' ), __( 'Their CSS class is not carried over.', 'woo-product-filter' ) );
		}

		$block = null;
		switch ( $ftype ) {
			case 'price':
				$block = $this->priceBlock( $o, $label, $mapped, $unmapped );
				break;
			case '_rating':
				$block = array( 'type' => 'rating', 'title' => $label, 'display' => 'dropdown' === self::styleDisplay( $style ) ? 'dropdown' : 'radio' );
				$this->row( $mapped, 'filter ' . $fid . ' (' . $source . ')', '_rating / ' . $style, 'rating' );
				$this->row( $unmapped, __( 'Rating choices', 'woo-product-filter' ) . ' (' . $label . ')', 'rated-1 … rated-5', __( 'Theirs picks products whose rounded rating is exactly N (several can be ticked); ours picks "N stars and up".', 'woo-product-filter' ) );
				$settings = array_intersect_key( $settings, array_flip( array( 'f_enable_title', 'f_description', 'f_title' ) ) );
				break;
			case 'attribute':
				if ( 'price' === $attr ) {
					$block = $this->priceBlock( $o, $label, $mapped, $unmapped ); // pre-3.0 filters kept price as an "attribute"
					break;
				}
				$block = $this->termBlock( $fid, $o, $attr, $label, $style, $single, $hideEmpty, $hideLive, $settings, $mapped, $unmapped );
				break;
			case 'tag':
				$block = $this->termBlock( $fid, $o, 'product_tag', $label, $style, $single, $hideEmpty, $hideLive, $settings, $mapped, $unmapped );
				break;
			case 'all_product_cat':
				$block = $this->termBlock( $fid, $o, 'product_cat', $label, $style, $single, $hideEmpty, $hideLive, $settings, $mapped, $unmapped );
				break;
			case 'custom_taxonomy':
				$tax   = self::val( $o, 'custom_taxonomy', 'product_cat' );
				$block = $this->termBlock( $fid, $o, '' !== $tax ? $tax : 'product_cat', $label, $style, $single, $hideEmpty, $hideLive, $settings, $mapped, $unmapped );
				break;
			case '_stock_status':
				$block = array( 'type' => 'stock', 'title' => $label, 'settings' => array( 'f_options[]' => 'instock,outofstock,onbackorder' ) );
				$this->row( $mapped, 'filter ' . $fid . ' (' . $source . ')', '_stock_status', 'stock' );
				break;
			case '_sale':
				$block = array( 'type' => 'onsale', 'title' => $label, 'settings' => array( 'f_checkbox_label' => $label ) );
				$this->row( $mapped, 'filter ' . $fid . ' (' . $source . ')', '_sale', 'onsale' );
				break;
			default:
				$this->row( $unmapped, 'filter ' . $fid . ' (' . $source . ')', 'filter_type ' . $ftype, __( 'No matching block in Product Filter (date filters).', 'woo-product-filter' ) );
		}
		if ( ! $block ) {
			return null;
		}
		if ( 'price' !== $block['type'] && in_array( $block['type'], array( 'rating', 'stock', 'onsale' ), true ) ) {
			$block['settings'] = array_merge( $settings, isset( $block['settings'] ) ? $block['settings'] : array() );
		} elseif ( 'price' === $block['type'] ) {
			$keep              = array_intersect_key( $settings, array_flip( array( 'f_enable_title', 'f_description' ) ) );
			$block['settings'] = array_merge( $keep, isset( $block['settings'] ) ? $block['settings'] : array() );
		}
		$block['source'] = $source;
		return $block;
	}

	/**
	 * Price slider (their "Price" filter, new_slider / slider styles).
	 */
	private function priceBlock( $o, $label, &$mapped, &$unmapped ) {
		$settings = array();
		$before   = self::val( $o, 'text_before_price' );
		$after    = self::val( $o, 'text_after_price' );
		$block    = array( 'type' => 'price', 'title' => $label, 'display' => 'slider' );
		$this->row( $mapped, 'filter_type price (' . $label . ')', self::val( $o, 'style' ), 'price / slider' );
		if ( '' !== trim( $after ) && '' === trim( str_replace( '%cur_symbol%', '', $before ) ) ) {
			$settings['f_currency_position'] = 'after';
		}
		if ( '' !== trim( $before ) || '' !== trim( $after ) ) {
			$texts = trim( $before . ' … ' . $after );
			$plain = array( '', '%cur_symbol%', html_entity_decode( get_woocommerce_currency_symbol() ), get_woocommerce_currency() );
			if ( in_array( trim( $before ), $plain, true ) && in_array( trim( $after ), $plain, true ) ) {
				$this->row( $mapped, 'text_before_price / text_after_price (' . $label . ')', $texts, __( 'ours writes the shop currency symbol next to the numbers', 'woo-product-filter' ) );
			} else {
				$this->row( $unmapped, 'text_before_price / text_after_price (' . $label . ')', $texts, __( 'Ours writes the shop currency symbol next to the numbers; other texts around the price are not shown.', 'woo-product-filter' ) );
			}
		}
		$stops = array_values( array_filter( array_map( 'trim', explode( ',', self::val( $o, 'price_values' ) ) ), 'is_numeric' ) );
		if ( count( $stops ) > 1 ) {
			$this->row( $unmapped, 'price_values (' . $label . ')', implode( ',', $stops ), __( 'Their slider jumps between these prices; ours slides freely (step 1) between the cheapest and dearest product.', 'woo-product-filter' ) );
		}
		if ( '' !== self::val( $o, 'min_price' ) || '' !== self::val( $o, 'max_price' ) ) {
			$this->row( $unmapped, 'min_price / max_price (' . $label . ')', self::val( $o, 'min_price' ) . ' – ' . self::val( $o, 'max_price' ), __( 'Our slider ends follow the products shown.', 'woo-product-filter' ) );
		}
		$block['settings'] = $settings;
		return $block;
	}

	/**
	 * Category / tag / attribute filter.
	 */
	private function termBlock( $fid, $o, $tax, $label, $style, $single, $hideEmpty, $hideLive, $settings, &$mapped, &$unmapped ) {
		$display = self::styleDisplay( $style );
		if ( '' === $display ) {
			$this->row( $unmapped, 'style (' . $label . ')', $style, __( 'No such look for terms here; imported as tick boxes.', 'woo-product-filter' ) );
			$display = 'checkbox';
		}
		if ( 0 === strpos( $style, 'image' ) ) {
			$this->row( $unmapped, 'style (' . $label . ')', $style, __( 'Image swatches: add the images under Products > Attributes > terms; colours shown until then.', 'woo-product-filter' ) );
		}
		$swatch = 'colors' === $display;
		if ( $swatch && ! $this->termColours( $tax ) ) {
			$display = 'buttons';
			$swatch  = false;
			$this->row( $unmapped, 'style (' . $label . ')', $style, __( 'No colours were set on their swatches; shown as chips.', 'woo-product-filter' ) );
		}
		if ( 'dropdown' === $display && ! $single ) {
			$display = 'mul_dropdown';
		}
		if ( $single && 'checkbox' === $display ) {
			$display = 'radio';
		}
		$block = $this->taxonomyBlock( $tax, $label, $display, $unmapped );
		if ( ! $block ) {
			return null;
		}
		if ( 'buttons' === $display ) {
			$settings['display_type'] = 'chips';
		} elseif ( 'colors' === $display ) {
			$settings['display_type'] = 'swatches';
			$settings['f_colors_labels'] = '' === self::val( $o, 'use_value_with_color' ) ? '0' : '1';
		}
		if ( $single && in_array( $display, array( 'buttons', 'colors' ), true ) ) {
			$settings['logic'] = 'single';
		}
		$this->row( $mapped, 'filter ' . $fid . ' (' . $label . ' (' . self::adminTitle( $fid ) . '))', self::val( $o, 'filter_type' ) . ' ' . $tax . ' / ' . $style . ( $single ? ' / one choice' : '' ), $block['type'] . ' / ' . $display );
		if ( $swatch ) {
			$this->row( $mapped, __( 'Swatch colours', 'woo-product-filter' ) . ' (' . $tax . ')', count( $this->termColours( $tax ) ) . ' ' . __( 'terms', 'woo-product-filter' ), __( 'copied to our swatch colour of each term when the filter is created (terms that already have one keep it)', 'woo-product-filter' ) );
		}

		// Logic between picked terms.
		$op = strtoupper( self::val( $o, 'operator', 'OR' ) );
		if ( 'AND' === $op && ! $single && ! in_array( $display, array( 'radio', 'dropdown' ), true ) ) {
			$settings['f_query_logic'] = 'and';
			$this->row( $mapped, 'operator (' . $label . ')', 'AND', 'logic and' );
		} elseif ( 'AND' === $op ) {
			$this->row( $unmapped, 'operator (' . $label . ')', 'AND', __( 'One choice at a time in this look, so AND has no effect.', 'woo-product-filter' ) );
		}

		// Empty choices (plugin wide "Values count and output").
		if ( $hideEmpty ) {
			$settings['f_hide_empty'] = '1';
			if ( $hideLive ) {
				$settings['f_hide_empty_active'] = '1';
			}
			$this->row( $mapped, 'recount_hide (' . $label . ')', self::val( $this->options(), 'recount_hide', 'removeRecount' ), 'f_hide_empty' . ( $hideLive ? ' + f_hide_empty_active' : '' ) );
		}

		// Tree.
		if ( is_taxonomy_hierarchical( $tax ) ) {
			$h = self::val( $o, 'hide_child_attributes' );
			if ( '' !== $h && '0' !== $h ) {
				$settings['f_show_hierarchical'] = '1';
				$this->row( $mapped, 'hide_child_attributes (' . $label . ')', '2' === $h ? __( 'hierarchical', 'woo-product-filter' ) : __( 'hierarchical, children folded', 'woo-product-filter' ), 'f_show_hierarchical (tree)' );
				if ( '1' === $h ) {
					$this->row( $unmapped, 'hide_child_attributes (' . $label . ')', '1', __( 'Sub-categories are shown open under their parent, not folded.', 'woo-product-filter' ) );
				}
			} elseif ( ! in_array( $display, array( 'dropdown', 'mul_dropdown' ), true ) ) {
				$this->row( $unmapped, 'hide_child_attributes (' . $label . ')', __( 'off (flat list)', 'woo-product-filter' ), __( 'Our category blocks always show sub-categories under their parent.', 'woo-product-filter' ) );
			}
		}

		// Term order.
		$by  = self::val( $o, 'order_values_by' );
		$dir = 'desc' === strtolower( self::val( $o, 'order_values_type', 'asc' ) ) ? 'desc' : 'asc';
		if ( 'Alpha' === $by || 'Numeric' === $by ) {
			$settings['f_sort_by'] = $dir;
			if ( 'Numeric' === $by ) {
				$settings['f_sort_as_numbers'] = '1';
			}
			$this->row( $mapped, 'order_values_by (' . $label . ')', $by . ' ' . $dir, 'f_sort_by ' . $dir . ( 'Numeric' === $by ? ' + f_sort_as_numbers' : '' ) );
		} elseif ( '' !== $by ) {
			$this->row( $unmapped, 'order_values_by (' . $label . ')', $by, __( 'This sort order is not offered; terms keep the WooCommerce order.', 'woo-product-filter' ) );
		}

		// Dropdown first line.
		$first = trim( self::val( $o, 'select_first_element_text' ) );
		if ( '' !== $first && in_array( $display, array( 'dropdown', 'mul_dropdown' ), true ) ) {
			$settings['f_dropdown_first_option_text'] = $first;
			$this->row( $mapped, 'select_first_element_text (' . $label . ')', $first, 'f_dropdown_first_option_text' );
		}
		if ( 0 === strpos( $style, 'select2' ) ) {
			$settings['f_dropdown_search'] = '1';
			$this->row( $mapped, 'style (' . $label . ')', $style, __( 'dropdown with a search box (f_dropdown_search)', 'woo-product-filter' ) );
		}

		// Premium include / exclude lists, if the premium plugin saved them.
		$list = isset( $o['include_exclude_list'] ) ? array_filter( array_map( 'intval', (array) $o['include_exclude_list'] ) ) : array();
		if ( $list ) {
			if ( 'include' === self::val( $o, 'include_exclude_select' ) ) {
				$settings['f_mlist[]'] = implode( ',', $list );
				$this->row( $mapped, 'include_exclude_list (' . $label . ')', 'include ' . implode( ',', $list ), 'f_mlist[] (only these)' );
			} else {
				$settings['f_exclude_terms'] = implode( ',', $list );
				$this->row( $mapped, 'include_exclude_list (' . $label . ')', 'exclude ' . implode( ',', $list ), 'f_exclude_terms' );
			}
		}
		$block['settings'] = $settings;
		return $block;
	}

	/**
	 * Copy their swatch colours ({prefix}berocket_termmeta) into ours before the filter is created, for every swatch block.
	 */
	public function createDraft( $mapped, $titleSuffix = '', $setId = '' ) {
		if ( class_exists( 'WooBeWoo_PF_Swatches' ) && ! empty( $mapped['blocks'] ) ) {
			foreach ( (array) $mapped['blocks'] as $b ) {
				if ( empty( $b['taxonomy'] ) || empty( $b['display'] ) || 'colors' !== $b['display'] ) {
					continue;
				}
				foreach ( $this->termColours( $b['taxonomy'] ) as $termId => $hex ) {
					if ( '' === (string) get_term_meta( $termId, WooBeWoo_PF_Swatches::META_COLOR, true ) ) {
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
			'widgets'    => array( 'berocket_aapf_group', 'berocket_aapf_single' ),
			'shortcodes' => array( 'br_filters_group', 'br_filter_single' ),
			'blocks'     => array( 'berocket/filter-group', 'berocket/single-filter' ),
			'products'   => array(),
		);
	}

	/**
	 * Group placements show their group set; a single filter shows its own set, or the set of the group that holds it
	 * when that group sits in the same place (sidebar or page), so one switch replaces both.
	 */
	public function setOfPlacement( $kind, $name, $data, $ctx = array() ) {
		$data = (array) $data;
		$gid  = 0;
		$fid  = 0;
		if ( ( 'widget' === $kind && 'berocket_aapf_group' === $name ) || ( 'shortcode' === $kind && 'br_filters_group' === $name ) ) {
			$gid = isset( $data['group_id'] ) ? (int) $data['group_id'] : 0;
		} elseif ( 'block' === $kind && 'berocket/filter-group' === $name ) {
			$gid = isset( $data['groupId'] ) ? (int) $data['groupId'] : 0;
		} elseif ( ( 'widget' === $kind && 'berocket_aapf_single' === $name ) || ( 'shortcode' === $kind && 'br_filter_single' === $name ) ) {
			$fid = isset( $data['filter_id'] ) ? (int) $data['filter_id'] : 0;
		} elseif ( 'block' === $kind && 'berocket/single-filter' === $name ) {
			$fid = isset( $data['filterId'] ) ? (int) $data['filterId'] : 0;
		} else {
			return '';
		}
		if ( $gid ) {
			return 'group-' . $gid;
		}
		if ( ! $fid ) {
			return 'none'; // an empty placement shows nothing: never matched by a set
		}
		$ctx   = (array) $ctx;
		$place = ! empty( $ctx['sidebar'] ) ? 'sidebar:' . $ctx['sidebar'] : ( ! empty( $ctx['post'] ) ? 'post:' . (int) $ctx['post'] : '' );
		if ( '' !== $place ) {
			$holder = $this->groupHolding( $place, $fid );
			if ( $holder ) {
				return 'group-' . $holder;
			}
		}
		return 'single-' . $fid;
	}

	/**
	 * Groups listed in br_filters_options elements_above_products (premium "show above products").
	 */
	public function autoPlacement( $setId ) {
		if ( 0 !== strpos( (string) $setId, 'group-' ) || ! in_array( (int) substr( (string) $setId, 6 ), $this->aboveProducts(), true ) ) {
			return null;
		}
		return array(
			'label' => __( 'BeRocket group above the products', 'woo-product-filter' ),
			'place' => 'above',
			'pages' => 'both',
		);
	}

	public function disableAutoPlacement( $setId ) {
		$opts = get_option( 'br_filters_options', array() );
		if ( ! is_array( $opts ) || empty( $opts['elements_above_products'] ) ) {
			return array();
		}
		$old  = $opts;
		$gid  = (int) substr( (string) $setId, 6 );
		$opts['elements_above_products'] = array_values(
			array_filter(
				(array) $opts['elements_above_products'],
				function ( $id ) use ( $gid ) {
					return (int) $id !== $gid;
				}
			)
		);
		update_option( 'br_filters_options', $opts );
		unset( $this->cache['opts'], $this->cache['placements'] );
		return array( 'br_filters_options' => $old );
	}

	/* ------------------------------------------------------------------ old BeRocket links */

	private static function ourKey( $tax ) {
		$map = array( 'product_cat' => 'category', 'product_tag' => 'tag', 'product_brand' => 'brand' );
		if ( isset( $map[ $tax ] ) ) {
			return $map[ $tax ];
		}
		return 0 === strpos( $tax, 'pa_' ) ? substr( $tax, 3 ) : '';
	}

	/**
	 * Their attribute name in a link => taxonomy (attributes drop "pa_"), or ''.
	 */
	private static function taxOf( $name ) {
		$name = sanitize_key( $name );
		if ( in_array( $name, array( 'product_cat', 'product_tag', 'product_brand', 'product_visibility' ), true ) && taxonomy_exists( $name ) ) {
			return $name;
		}
		if ( taxonomy_exists( 'pa_' . $name ) ) {
			return 'pa_' . $name;
		}
		if ( 0 === strpos( $name, 'pa_' ) && taxonomy_exists( $name ) ) {
			return $name;
		}
		return '';
	}

	/**
	 * A term token (slug, or term id when their slug_urls is off) => slug, or ''.
	 */
	private static function slugOf( $token, $tax ) {
		$token = trim( urldecode( (string) $token ) );
		if ( '' === $token ) {
			return '';
		}
		$t = get_term_by( 'slug', $token, $tax );
		if ( ! $t ) {
			$t = get_term_by( 'slug', sanitize_title( $token ), $tax );
		}
		if ( ! $t && ctype_digit( $token ) ) {
			$t = get_term( (int) $token, $tax );
		}
		return $t && ! is_wp_error( $t ) ? $t->slug : '';
	}

	/**
	 * "red-blue" (OR), "a+b" / "a b" (AND) => slugs. Slugs may hold "-" themselves ("gift-idea"): the longest run of
	 * pieces that is a term wins.
	 */
	private static function splitValues( $raw, $tax ) {
		$raw = trim( (string) $raw );
		if ( preg_match( '/[+ ]/', $raw ) ) {
			$parts = preg_split( '/[+ ]+/', $raw );
		} else {
			$bits  = explode( '-', $raw );
			$parts = array();
			$n     = count( $bits );
			for ( $i = 0; $i < $n; ) {
				$hit = 0;
				for ( $j = $n; $j > $i; $j-- ) {
					if ( '' !== self::slugOf( implode( '-', array_slice( $bits, $i, $j - $i ) ), $tax ) ) {
						$hit = $j;
						break;
					}
				}
				if ( ! $hit ) {
					$i++;
					continue;
				}
				$parts[] = implode( '-', array_slice( $bits, $i, $hit - $i ) );
				$i       = $hit;
			}
		}
		$out = array();
		foreach ( $parts as $p ) {
			$s = self::slugOf( $p, $tax );
			if ( '' !== $s ) {
				$out[] = $s;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Add one of their filters (name + raw values) to our args. Returns true when it was one of theirs.
	 */
	private static function addArg( $name, $raw, &$args ) {
		$name = strtolower( trim( (string) $name ) );
		$raw  = (string) $raw;
		if ( 'price' === $name ) {
			$mm = preg_split( '/[_\-]/', $raw );
			if ( 2 === count( $mm ) && is_numeric( $mm[0] ) && is_numeric( $mm[1] ) ) {
				$args['wpf_price'] = WooBeWoo_PF_Query::trim_number( (float) $mm[0] ) . '-' . WooBeWoo_PF_Query::trim_number( (float) $mm[1] );
				return true;
			}
			return false;
		}
		$tax = self::taxOf( $name );
		if ( 'product_visibility' === $tax ) {
			$min = 0;
			foreach ( preg_split( '/[+ ]+|-(?=rated|featured|outofstock)/', $raw ) as $v ) {
				$v = trim( $v );
				if ( preg_match( '/^rated-([1-5])$/', $v, $m ) ) {
					$min = $min ? min( $min, (int) $m[1] ) : (int) $m[1];
				} elseif ( 'featured' === $v ) {
					$args['wpf_featured'] = '1';
				} elseif ( 'outofstock' === $v ) {
					$args['wpf_stock'] = 'out';
				}
			}
			if ( $min ) {
				$args['wpf_rating'] = (string) $min;
			}
			return $min || isset( $args['wpf_featured'] ) || isset( $args['wpf_stock'] );
		}
		if ( '' === $tax || '' === self::ourKey( $tax ) ) {
			return false;
		}
		$slugs = self::splitValues( $raw, $tax );
		if ( ! $slugs ) {
			return false;
		}
		$args[ 'wpf_' . self::ourKey( $tax ) ] = implode( ',', $slugs );
		return true;
	}

	/**
	 * Their links after the switch => our 4.0 args on the same page:
	 * ?filters=color[red-blue]|size[m]|price[5_750]|product_visibility[rated-4] (also term ids, and the "separate link"
	 * form ?pa-color=red,blue&pa-price_from=5&pa-price_to=750). /page/N/ becomes wpf_page.
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		$args  = array();
		$hit   = false;
		if ( isset( $query['filters'] ) && is_scalar( $query['filters'] ) && '' !== (string) $query['filters'] ) {
			if ( preg_match_all( '/([a-z0-9_\-]+)\[([^\]]*)\]/i', (string) $query['filters'], $all, PREG_SET_ORDER ) ) {
				foreach ( $all as $m ) {
					$hit = self::addArg( $m[1], $m[2], $args ) || $hit;
				}
			}
		}
		$from = array();
		foreach ( $query as $k => $v ) {
			if ( ! is_scalar( $v ) || 0 !== strpos( (string) $k, 'pa-' ) || '' === (string) $v ) {
				continue;
			}
			$k = substr( (string) $k, 3 );
			if ( preg_match( '/^(.+)_(from|to)$/', $k, $m ) ) {
				$from[ $m[1] ][ $m[2] ] = (string) $v;
				continue;
			}
			if ( '_operator' === substr( $k, -9 ) ) {
				continue;
			}
			$hit = self::addArg( $k, str_replace( ',', '-', (string) $v ), $args ) || $hit;
		}
		foreach ( $from as $name => $mm ) {
			if ( isset( $mm['from'], $mm['to'] ) ) {
				$hit = self::addArg( $name, $mm['from'] . '_' . $mm['to'], $args ) || $hit;
			}
		}
		if ( ! $hit ) {
			return null;
		}
		$base = (string) $path;
		if ( preg_match( '#^(.*?/)page/(\d+)/?$#', $base, $m ) ) {
			$base = $m[1];
			if ( (int) $m[2] > 1 ) {
				$args['wpf_page'] = (string) (int) $m[2];
			}
		} elseif ( isset( $query['paged'] ) && (int) $query['paged'] > 1 ) {
			$args['wpf_page'] = (string) (int) $query['paged'];
		}
		if ( ! empty( $query['orderby'] ) && is_scalar( $query['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $query['orderby'] );
		}
		return array( 'path' => $base, 'args' => $args );
	}
}
