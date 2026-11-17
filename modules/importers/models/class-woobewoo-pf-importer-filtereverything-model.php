<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Filtereverything_Model Class
 *
 * Filter Everything (free 1.9.x). Storage, as captured from a real install:
 * - Filter set = post `filter-set`: post_title (name), post_excerpt (filtered post type, also meta
 *   wpc_filter_set_post_type), post_name (location term: `1` = every archive of the post type, `common___shop_page`,
 *   `product_cat___16`, `product_cat___-1`, `page___12`...), post_content = serialized array( wp_page_type, wp_filter_query,
 *   hide_empty no|yes|initial, show_count yes|no, use_search_field, search_field_label, search_field_placeholder,
 *   use_apply_button, apply_button_text, reset_button_text, horizontal_view... ). Meta wpc_filter_set_query_vars is a
 *   double serialized copy of the chosen query vars.
 * - Filter = post `filter-field` (post_parent = set, menu_order = position): post_title (label), post_name (URL var),
 *   post_excerpt (entity key, e.g. taxonomy_pa_color), post_content = serialized array( entity taxonomy|post_meta|
 *   post_meta_num|post_meta_date|post_date|author, e_name, view checkboxes|radio|labels|dropdown|range|date|rating,
 *   logic or|and, orderby, exclude (term ids), include no|yes (yes: "exclude" is an only-these list), hierarchy, collapse,
 *   more_less, search, show_chips, range_slider, step, dropdown_label, tooltip, parent_filter, in_path... ).
 * - Options: wpc_filter_settings (enable_ajax "on" or missing, mobile_filter_settings, show_terms_in_content, primary_color...),
 *   wpc_filter_experimental (use_color_swatches, color_swatches_taxonomies), wpc_filter_permalinks (entity#ename => URL var).
 *   Swatch colours: term meta product_attribute_color (pa_*) or color.
 * - Placements: widget wpc_filters_widget, shortcode [fe_widget id=..], block wpc-filter-everything/filter. Widget and block
 *   carry no set: Filter Everything shows the set whose location matches the page.
 * - Links: ?colour=blue;red&size=m&min_price=20&max_price=60 (values joined with ";", numeric as min_<var> / max_<var>).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Filtereverything_Model extends WooBeWoo_PF_Importer_Base_Model {

	const SET_TYPE    = 'filter-set';
	const FIELD_TYPE  = 'filter-field';
	const MORE_LESS   = 5; // Filter Everything shows 5 terms before "More" (filter wpc_more_less_count).

	private $cache = array();

	public function slug() {
		return 'filtereverything';
	}

	public function name() {
		return 'Filter Everything';
	}

	public function pluginFiles() {
		return array( 'filter-everything/filter-everything.php', 'filter-everything-pro/filter-everything.php' );
	}

	public function installed() {
		global $wpdb;
		$posts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('filter-set','filter-field') AND post_status NOT IN ('trash','auto-draft')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $posts > 0 || false !== get_option( 'wpc_filter_settings', false );
	}

	/* ------------------------------------------------------------------ reading their data */

	/**
	 * Published / draft / private sets, auto-drafts left out. $productOnly: only sets that filter products.
	 */
	private function setPosts( $productOnly = true ) {
		$key = 'sets' . ( $productOnly ? 'p' : 'a' );
		if ( ! isset( $this->cache[ $key ] ) ) {
			$all = get_posts(
				array(
					'post_type'        => self::SET_TYPE,
					'post_status'      => array( 'publish', 'draft', 'private' ),
					'posts_per_page'   => 200,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
			$out = array();
			foreach ( $all as $p ) {
				if ( ! $productOnly || 'product' === $this->postTypeOf( $p ) ) {
					$out[] = $p;
				}
			}
			$this->cache[ $key ] = $out;
		}
		return $this->cache[ $key ];
	}

	/**
	 * The post type a set filters: post_excerpt, else the meta copy.
	 */
	private function postTypeOf( $post ) {
		$pt = (string) $post->post_excerpt;
		if ( '' === $pt ) {
			$pt = (string) get_post_meta( $post->ID, 'wpc_filter_set_post_type', true );
		}
		return '' === $pt ? 'post' : $pt;
	}

	private function fieldsOf( $setId ) {
		$key = 'f' . (int) $setId;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = get_posts(
				array(
					'post_type'        => self::FIELD_TYPE,
					'post_status'      => array( 'publish', 'draft', 'private' ),
					'post_parent'      => (int) $setId,
					'posts_per_page'   => 200,
					'orderby'          => 'menu_order',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);
		}
		return $this->cache[ $key ];
	}

	private function content( $post ) {
		$d = maybe_unserialize( $post->post_content );
		return is_array( $d ) ? $d : array();
	}

	private static function yes( $arr, $key ) {
		return isset( $arr[ $key ] ) && in_array( $arr[ $key ], array( 'yes', 'on', '1', 1, true ), true );
	}

	private function globalSettings() {
		$g = get_option( 'wpc_filter_settings', array() );
		return is_array( $g ) ? $g : array();
	}

	private function experimental() {
		$e = get_option( 'wpc_filter_experimental', array() );
		return is_array( $e ) ? $e : array();
	}

	/**
	 * Swatches are on for this taxonomy (Settings > Experimental).
	 */
	private function swatchTaxonomy( $tax ) {
		$e = $this->experimental();
		return self::yes( $e, 'use_color_swatches' ) && in_array( $tax, (array) ( isset( $e['color_swatches_taxonomies'] ) ? $e['color_swatches_taxonomies'] : array() ), true );
	}

	private static function swatchMetaKey( $tax, $what = 'color' ) {
		return 0 === strpos( $tax, 'pa_' ) ? 'product_attribute_' . $what : $what;
	}

	/**
	 * Their colour per term of a taxonomy: array( term_id => #hex ).
	 */
	private function termColours( $tax ) {
		$out   = array();
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return $out;
		}
		foreach ( $terms as $t ) {
			$c = sanitize_hex_color( (string) get_term_meta( $t->term_id, self::swatchMetaKey( $tax ), true ) );
			if ( $c ) {
				$out[ (int) $t->term_id ] = $c;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ location */

	/**
	 * Where a set filters: array( 'label', 'pages' => our display_on_page, 'cats' => term ids, 'page' => page id ).
	 */
	private function location( $post ) {
		$c    = $this->content( $post );
		$type = isset( $c['wp_page_type'] ) ? (string) $c['wp_page_type'] : 'common___common';
		$name = (string) $post->post_name;
		$out  = array( 'label' => __( 'Every product archive', 'woo-product-filter' ), 'pages' => 'both', 'cats' => array(), 'page' => 0, 'type' => $type, 'name' => $name, 'exact' => true );
		if ( '1' === $name || '' === $name ) {
			return $out;
		}
		if ( 'common___shop_page' === $name ) {
			$out['label'] = __( 'Shop page', 'woo-product-filter' );
			$out['pages'] = 'shop';
			return $out;
		}
		if ( 'common___search_results' === $name ) {
			$out['label'] = __( 'Search results page', 'woo-product-filter' );
			$out['exact'] = false;
			return $out;
		}
		$parts = explode( '___', $name, 2 );
		$what  = $parts[0];
		$id    = isset( $parts[1] ) ? (int) $parts[1] : 0;
		if ( 'product_cat' === $what ) {
			if ( $id > 0 ) {
				$t            = get_term( $id, 'product_cat' );
				$out['label'] = sprintf( /* translators: %s: category name */ __( 'Category "%s"', 'woo-product-filter' ), $t && ! is_wp_error( $t ) ? $t->name : $id );
				$out['cats']  = array( $id );
				$out['pages'] = 'custom_cats';
			} else {
				$out['label'] = __( 'Every category page', 'woo-product-filter' );
				$out['pages'] = 'category';
			}
		} elseif ( 'product_tag' === $what ) {
			$out['label'] = __( 'Tag pages', 'woo-product-filter' );
			$out['pages'] = 'tag';
			$out['exact'] = $id <= 0;
		} elseif ( 'page' === $what ) {
			$out['label'] = $id > 0 ? sprintf( /* translators: %s: page title */ __( 'Page "%s"', 'woo-product-filter' ), get_the_title( $id ) ) : __( 'Every page', 'woo-product-filter' );
			$out['pages'] = $id > 0 ? 'specific' : 'all_pages';
			$out['page']  = $id;
		} elseif ( 'product' === $what ) {
			$out['label'] = __( 'Product pages', 'woo-product-filter' );
			$out['pages'] = 'product';
			$out['exact'] = $id <= 0;
		} else {
			$out['label'] = $what . ( $id ? ' #' . $id : '' );
			$out['exact'] = false;
		}
		return $out;
	}

	/**
	 * The set Filter Everything shows on the Shop page (sidebar widgets show there first): a set for the shop page wins over
	 * an "every archive" set; same rank: menu_order DESC, ID ASC (their query order). '' when none.
	 */
	private function shopSet() {
		$best = null;
		foreach ( $this->setPosts( true ) as $p ) {
			if ( 'publish' !== $p->post_status ) {
				continue;
			}
			$rank = 'common___shop_page' === $p->post_name ? 2 : ( in_array( $p->post_name, array( '1', '' ), true ) ? 1 : 0 );
			if ( ! $rank ) {
				continue;
			}
			if ( ! $best || $rank > $best[0] || ( $rank === $best[0] && (int) $p->menu_order > $best[1] ) ) {
				$best = array( $rank, (int) $p->menu_order, (int) $p->ID );
			}
		}
		if ( $best ) {
			return (string) $best[2];
		}
		// No shop set: a sidebar widget still shows the set of the product archives (categories, tags).
		foreach ( $this->setPosts( true ) as $p ) {
			if ( 'publish' === $p->post_status && preg_match( '/^(product_cat|product_tag|product_brand)___/', $p->post_name ) ) {
				return (string) $p->ID;
			}
		}
		return '';
	}

	/**
	 * Every published product set a sidebar widget shows somewhere: sets of the shop page, of every archive, or of
	 * category / tag / brand pages (the Shop page set first). Filter Everything picks one of them per page.
	 */
	private function archiveSets() {
		$out  = array();
		$shop = $this->shopSet();
		if ( '' !== $shop ) {
			$out[] = $shop;
		}
		foreach ( $this->setPosts( true ) as $p ) {
			if ( 'publish' === $p->post_status && ( in_array( $p->post_name, array( '1', '', 'common___shop_page' ), true ) || preg_match( '/^(product_cat|product_tag|product_brand)___/', $p->post_name ) ) ) {
				$out[] = (string) $p->ID;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * The product set shown on page $postId: a set targeting that page, else one targeting every page. '' when none.
	 */
	private function pageSet( $postId ) {
		$postId = (int) $postId;
		if ( function_exists( 'wc_get_page_id' ) && $postId === (int) wc_get_page_id( 'shop' ) ) {
			return $this->shopSet();
		}
		$any = '';
		foreach ( $this->setPosts( true ) as $p ) {
			if ( 'publish' !== $p->post_status ) {
				continue;
			}
			if ( 'page___' . $postId === $p->post_name ) {
				return (string) $p->ID;
			}
			if ( '' === $any && 'page___-1' === $p->post_name ) {
				$any = (string) $p->ID;
			}
		}
		return $any;
	}

	/* ------------------------------------------------------------------ sets */

	public function sets() {
		$out = array();
		foreach ( $this->setPosts( true ) as $p ) {
			$loc   = $this->location( $p );
			$note  = sprintf( /* translators: %s: where the set filters */ __( 'Filters: %s', 'woo-product-filter' ), $loc['label'] );
			if ( 'publish' !== $p->post_status ) {
				$note .= ' · ' . __( 'not published', 'woo-product-filter' );
			}
			$out[] = array(
				'id'        => (string) $p->ID,
				'title'     => '' !== $p->post_title ? $p->post_title : sprintf( /* translators: %d: set id */ __( 'Filter set #%d', 'woo-product-filter' ), $p->ID ),
				'blocks'    => count( $this->fieldsOf( $p->ID ) ),
				'shortcode' => '[fe_widget id="' . (int) $p->ID . '"]',
				'note'      => $note,
			);
		}
		$other = array();
		foreach ( $this->setPosts( false ) as $p ) {
			if ( 'product' !== $this->postTypeOf( $p ) ) {
				$other[] = '"' . $p->post_title . '"';
			}
		}
		if ( $other && $out ) {
			$out[0]['note'] .= ' · ' . sprintf( /* translators: %s: list of set names */ __( 'Not importable (they filter posts, not products): %s', 'woo-product-filter' ), implode( ', ', $other ) );
		}
		return $out;
	}

	/* ------------------------------------------------------------------ map */

	public function map( $setId ) {
		$post = get_post( (int) $setId );
		if ( ! $post || self::SET_TYPE !== $post->post_type || 'auto-draft' === $post->post_status ) {
			return new WP_Error( 'wpf_bad_set', __( 'Filter set not found.', 'woo-product-filter' ) );
		}
		$pt = $this->postTypeOf( $post );
		if ( 'product' !== $pt ) {
			return new WP_Error( 'wpf_bad_set', sprintf( /* translators: %s: post type */ __( 'This set filters "%s", not products. Product Filter filters WooCommerce products only.', 'woo-product-filter' ), $pt ) );
		}
		$mapped   = array();
		$unmapped = array();
		$blocks   = array();
		$set      = $this->content( $post );
		$g        = $this->globalSettings();
		$isPro    = (bool) apply_filters( 'woobewoo_pf_is_pro', false );

		$showCount = ! isset( $set['show_count'] ) || self::yes( $set, 'show_count' );
		$hideEmpty = isset( $set['hide_empty'] ) ? (string) $set['hide_empty'] : 'no';
		if ( 'yes' === $hideEmpty ) {
			$this->row( $mapped, __( 'Empty Terms', 'woo-product-filter' ), __( 'Always hide', 'woo-product-filter' ), 'f_hide_empty on every block' );
		} elseif ( 'initial' === $hideEmpty ) {
			$this->row( $unmapped, __( 'Empty Terms', 'woo-product-filter' ), __( 'Hide in the initial Filter only', 'woo-product-filter' ), __( 'Ours hides empty choices always or never; kept visible (greyed out once nothing matches).', 'woo-product-filter' ) );
		}

		$fields = $this->fieldsOf( $post->ID );
		$searchAt = -1;
		if ( self::yes( $set, 'use_search_field' ) ) {
			$searchAt = isset( $set['search_field_menu_order'] ) ? (int) $set['search_field_menu_order'] : count( $fields ) + 1;
		}
		$searchDone = false;
		foreach ( $fields as $f ) {
			if ( $searchAt > -1 && ! $searchDone && (int) $f->menu_order >= $searchAt ) {
				$this->searchBlock( $set, $isPro, $blocks, $mapped, $unmapped );
				$searchDone = true;
			}
			$block = $this->fieldBlock( $f, $showCount, $hideEmpty, $mapped, $unmapped );
			if ( $block ) {
				$blocks[] = $block;
			}
		}
		if ( $searchAt > -1 && ! $searchDone ) {
			$this->searchBlock( $set, $isPro, $blocks, $mapped, $unmapped );
		}

		// Whole filter: AJAX, phones, apply / reset buttons.
		$ajax   = self::yes( $g, 'enable_ajax' );
		$mobile = isset( $g['mobile_filter_settings'] ) && in_array( $g['mobile_filter_settings'], array( 'show_open_close_button', 'show_bottom_widget' ), true );
		$apply  = self::yes( $set, 'use_apply_button' );
		$main   = $this->mainFrom(
			$ajax,
			$showCount,
			$mobile,
			$mapped,
			array(
				'auto_update_filter'    => $apply ? '0' : '1',
				'show_filtering_button' => $apply ? '1' : '0',
			)
		);
		$this->row( $mapped, '«Apply Button» mode', $apply ? 'yes' : 'no', $apply ? 'auto_update_filter 0 / show_filtering_button 1' : 'auto_update_filter 1' );
		if ( $apply ) {
			$applyText = isset( $set['apply_button_text'] ) ? trim( html_entity_decode( (string) $set['apply_button_text'], ENT_QUOTES ) ) : '';
			if ( '' !== $applyText ) {
				$main['apply_button_word']     = $applyText;
				$main['filtering_button_word'] = $applyText;
				$this->row( $mapped, 'apply_button_text', $applyText, 'apply_button_word' );
			}
			$resetText = isset( $set['reset_button_text'] ) ? trim( html_entity_decode( (string) $set['reset_button_text'], ENT_QUOTES ) ) : '';
			$main['show_clean_button'] = '1';
			if ( '' !== $resetText ) {
				$main['show_clean_button_word'] = $resetText;
				$this->row( $mapped, 'reset_button_text', $resetText, 'show_clean_button / show_clean_button_word' );
			}
		} else {
			$this->row( $mapped, __( 'Reset', 'woo-product-filter' ), __( 'chips with a reset link', 'woo-product-filter' ), 'show_clean_button ("Clear all")' );
		}
		if ( $mobile ) {
			$this->row( $mapped, 'mobile_filter_settings', $g['mobile_filter_settings'], 'mobile_drawer' );
		}
		if ( self::yes( $g, 'try_move_to_top_sidebar' ) ) {
			$this->row( $unmapped, 'try_move_to_top_sidebar', 'on', __( 'Moving the theme sidebar above the products on phones is not ours to do; our phone drawer opens from a button above the products.', 'woo-product-filter' ) );
		}
		if ( self::yes( $set, 'horizontal_view' ) ) {
			$this->row( $unmapped, 'horizontal_view', 'yes', __( 'Put our filter above the products ("Where it shows") for a horizontal layout.', 'woo-product-filter' ) );
		}
		$chipsHooks = isset( $g['show_terms_in_content'] ) ? array_filter( (array) $g['show_terms_in_content'] ) : array();
		$main['show_chips'] = $chipsHooks ? '1' : '0';
		$this->row( $mapped, 'show_terms_in_content', $chipsHooks ? implode( ', ', $chipsHooks ) : 'none', $chipsHooks ? __( 'show_chips: picked values as chips at the top of our filter', 'woo-product-filter' ) : 'show_chips 0' );
		$color = isset( $g['primary_color'] ) ? sanitize_hex_color( (string) $g['primary_color'] ) : '';
		if ( $color ) {
			$main['wbw_accent'] = $color;
			$this->row( $mapped, 'primary_color', $color, 'wbw_accent' );
		}
		if ( isset( $g['container_height'] ) && (int) $g['container_height'] > 0 ) {
			$this->row( $unmapped, 'container_height', (int) $g['container_height'] . 'px', __( 'Long lists fold behind "Show more" instead of scrolling; set "Tallest a block can be" on a block to scroll.', 'woo-product-filter' ) );
		}
		$e = $this->experimental();
		if ( self::yes( $e, 'rounded_swatches' ) ) {
			$this->row( $unmapped, 'rounded_swatches', 'on', __( 'Swatch shape follows the Look cards.', 'woo-product-filter' ) );
		}
		if ( ! empty( $e['custom_css'] ) ) {
			$this->row( $unmapped, 'custom_css', __( 'set', 'woo-product-filter' ), __( 'Their CSS targets their markup; check our look by hand.', 'woo-product-filter' ) );
		}
		if ( self::yes( $e, 'disable_woo_orderby' ) ) {
			$this->row( $unmapped, 'disable_woo_orderby', 'on', __( 'The WooCommerce sorting dropdown is left as it is.', 'woo-product-filter' ) );
		}
		if ( ! empty( $g['posts_container'] ) && '#primary' !== $g['posts_container'] ) {
			$this->row( $unmapped, 'posts_container', $g['posts_container'], __( 'Ours finds the product list itself; set "CSS selector of the product list" only if it does not.', 'woo-product-filter' ) );
		}

		// Where it shows.
		$loc = $this->location( $post );
		if ( 'custom_cats' === $loc['pages'] && ! $isPro ) {
			$main['display_on_page'] = 'category';
			$this->row( $unmapped, __( 'Where to filter', 'woo-product-filter' ), $loc['label'], __( 'Limiting to some categories needs Pro; ours shows on every category page.', 'woo-product-filter' ) );
		} elseif ( in_array( $loc['pages'], array( 'specific', 'all_pages' ), true ) && ! $isPro ) {
			$main['display_on_page'] = 'both';
			$this->row( $unmapped, __( 'Where to filter', 'woo-product-filter' ), $loc['label'], __( 'Showing on normal pages needs Pro.', 'woo-product-filter' ) );
		} else {
			$main['display_on_page'] = $loc['pages'];
			if ( $loc['cats'] ) {
				$main['display_cat_list']  = implode( ',', $loc['cats'] );
				$main['display_child_cat'] = '0';
			}
			if ( $loc['page'] ) {
				$main['display_page_list'] = (string) $loc['page'];
			}
			if ( $loc['exact'] ) {
				$this->row( $mapped, __( 'Where to filter', 'woo-product-filter' ), $loc['label'], 'display_on_page ' . $loc['pages'] . ( $loc['cats'] ? ' (' . implode( ',', $loc['cats'] ) . ')' : '' ) );
			} else {
				$this->row( $unmapped, __( 'Where to filter', 'woo-product-filter' ), $loc['label'], __( 'No exact equivalent; ours shows on the shop and category pages.', 'woo-product-filter' ) );
			}
		}

		return array(
			'title'    => '' !== $post->post_title ? $post->post_title : 'Filter Everything set #' . $post->ID,
			'blocks'   => $blocks,
			'main'     => $main,
			'mapped'   => $mapped,
			'unmapped' => $unmapped,
		);
	}

	/**
	 * Their "Search Field" (set level) => our text search block (Pro).
	 */
	private function searchBlock( $set, $isPro, &$blocks, &$mapped, &$unmapped ) {
		$label = isset( $set['search_field_label'] ) ? html_entity_decode( (string) $set['search_field_label'], ENT_QUOTES ) : '';
		if ( ! $isPro ) {
			$this->row( $unmapped, 'use_search_field', 'yes', __( 'Text search block needs Pro.', 'woo-product-filter' ) );
			return;
		}
		$settings = array();
		if ( ! empty( $set['search_field_placeholder'] ) ) {
			$settings['f_search_label'] = html_entity_decode( (string) $set['search_field_placeholder'], ENT_QUOTES );
		}
		$blocks[] = array( 'type' => 'search', 'title' => '' !== $label ? $label : __( 'Search', 'woo-product-filter' ), 'source' => __( 'Search Field', 'woo-product-filter' ), 'settings' => $settings );
		$this->row( $mapped, 'use_search_field', 'yes', 'wpfSearchText' . ( $settings ? ' (placeholder: f_search_label)' : '' ) );
	}

	/**
	 * One of their filters => our block (or null with an unmapped row).
	 */
	private function fieldBlock( $f, $showCount, $hideEmpty, &$mapped, &$unmapped ) {
		$d      = $this->content( $f );
		$entity = isset( $d['entity'] ) ? (string) $d['entity'] : 'taxonomy';
		$ename  = isset( $d['e_name'] ) ? (string) $d['e_name'] : '';
		$view   = isset( $d['view'] ) ? (string) $d['view'] : 'checkboxes';
		$label  = '' !== $f->post_title ? html_entity_decode( $f->post_title, ENT_QUOTES ) : $ename;
		$source = $label . ' (' . $ename . ')';
		$tag    = $label;

		$settings = array( 'f_show_count' => $showCount ? '1' : '0' );
		if ( 'yes' === $hideEmpty ) {
			$settings['f_hide_empty'] = '1';
		}
		if ( self::yes( $d, 'collapse' ) ) {
			$settings['f_enable_title'] = 'yes_close';
			$this->row( $mapped, 'collapse (' . $tag . ')', 'yes', 'f_enable_title yes_close (starts folded)' );
		}
		if ( ! empty( $d['tooltip'] ) ) {
			$settings['f_description'] = html_entity_decode( (string) $d['tooltip'], ENT_QUOTES );
			$this->row( $mapped, 'tooltip (' . $tag . ')', $d['tooltip'], 'f_description (a line under the title)' );
		}
		if ( isset( $d['show_chips'] ) && ! self::yes( $d, 'show_chips' ) ) {
			$this->row( $unmapped, 'show_chips (' . $tag . ')', 'no', __( 'Our picked-value chips show every block.', 'woo-product-filter' ) );
		}
		if ( isset( $d['parent_filter'] ) && '' !== (string) $d['parent_filter'] && '-1' !== (string) $d['parent_filter'] ) {
			$this->row( $unmapped, 'parent_filter (' . $tag . ')', $d['parent_filter'], __( 'Dependent filters (shown after a parent pick) are not available; the block is always shown.', 'woo-product-filter' ) );
		}

		$block = null;
		switch ( $entity ) {
			case 'taxonomy':
				if ( 'product_visibility' === $ename ) {
					if ( 'rating' !== $view ) {
						$this->row( $unmapped, 'filter ' . $f->ID . ' (' . $tag . ')', 'product_visibility / ' . $view, __( 'Product visibility terms are not a shopper filter here; use Featured or Stock blocks.', 'woo-product-filter' ) );
						return null;
					}
					$block = array( 'type' => 'rating', 'title' => $label ? $label : __( 'Rating', 'woo-product-filter' ), 'settings' => $settings );
					$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'taxonomy product_visibility / rating', 'rating' );
					$this->row( $unmapped, __( 'Rating choices', 'woo-product-filter' ) . ' (' . $tag . ')', 'rated-1 to rated-5', __( 'Theirs picks products whose rounded rating is exactly N; ours picks "N stars and up".', 'woo-product-filter' ) );
					break;
				}
				$block = $this->termBlock( $f, $d, $ename, $view, $label, $settings, $mapped, $unmapped );
				break;
			case 'post_meta_num':
				if ( '_price' === $ename ) {
					$block = array( 'type' => 'price', 'title' => $label ? $label : __( 'Price', 'woo-product-filter' ), 'settings' => $settings );
					unset( $block['settings']['f_show_count'], $block['settings']['f_hide_empty'] );
					if ( isset( $d['step'] ) && (float) $d['step'] > 0 ) {
						$block['settings']['f_step'] = (string) (float) $d['step'];
					}
					$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'post_meta_num _price / range', 'price / slider' . ( isset( $block['settings']['f_step'] ) ? ', f_step ' . $block['settings']['f_step'] : '' ) );
					if ( isset( $d['range_slider'] ) && ! self::yes( $d, 'range_slider' ) ) {
						$this->row( $unmapped, 'range_slider (' . $tag . ')', 'no', __( 'Ours always shows the slider together with the Min / Max boxes.', 'woo-product-filter' ) );
					}
					if ( ! empty( $d['min_num_label'] ) || ! empty( $d['max_num_label'] ) ) {
						$this->row( $unmapped, 'min_num_label / max_num_label (' . $tag . ')', trim( ( isset( $d['min_num_label'] ) ? $d['min_num_label'] : '' ) . ' / ' . ( isset( $d['max_num_label'] ) ? $d['max_num_label'] : '' ) ), __( 'Our price boxes say Min and Max.', 'woo-product-filter' ) );
					}
				} elseif ( apply_filters( 'woobewoo_pf_is_pro', false ) && '' !== $ename ) {
					$block = array( 'type' => 'custom_field', 'meta_key' => $ename, 'title' => $label, 'display' => 'slider', 'settings' => array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- array key of the imported block definition, not a query argument
					$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'post_meta_num ' . $ename, 'custom_field / slider' );
				} else {
					$this->row( $unmapped, 'filter ' . $f->ID . ' (' . $tag . ')', 'post_meta_num ' . $ename, __( 'Numeric custom field blocks need Pro.', 'woo-product-filter' ) );
				}
				break;
			case 'post_meta':
				if ( '_stock_status' === $ename ) {
					$block = array( 'type' => 'stock', 'title' => $label ? $label : __( 'Stock', 'woo-product-filter' ), 'settings' => array_merge( $settings, array( 'f_options[]' => 'instock,outofstock,onbackorder' ) ) );
					if ( in_array( $view, array( 'dropdown' ), true ) ) {
						$block['display'] = 'dropdown';
					}
					$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'post_meta _stock_status / ' . $view, 'stock (in stock, out of stock, on backorder)' );
					if ( 'checkboxes' === $view || 'labels' === $view ) {
						$this->row( $unmapped, __( 'Several stock statuses at once', 'woo-product-filter' ) . ' (' . $tag . ')', $view, __( 'Our stock block takes one status at a time.', 'woo-product-filter' ) );
					}
				} else {
					$this->row( $unmapped, 'filter ' . $f->ID . ' (' . $tag . ')', 'post_meta ' . $ename, __( 'Text custom field blocks are not imported; add a "Custom field" block by hand (Pro).', 'woo-product-filter' ) );
				}
				break;
			case 'post_meta_exists':
				if ( in_array( $ename, array( '_sale_price', 'onsale' ), true ) ) {
					$block = array( 'type' => 'onsale', 'title' => $label ? $label : __( 'On sale', 'woo-product-filter' ), 'settings' => array( 'f_checkbox_label' => $label ? $label : __( 'On sale', 'woo-product-filter' ) ) );
					$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'post_meta_exists ' . $ename, 'onsale' );
				} else {
					$this->row( $unmapped, 'filter ' . $f->ID . ' (' . $tag . ')', 'post_meta_exists ' . $ename, __( 'No matching block in Product Filter.', 'woo-product-filter' ) );
				}
				break;
			case 'author':
			case 'author_author':
				$block = array( 'type' => 'author', 'title' => $label ? $label : __( 'Seller', 'woo-product-filter' ), 'display' => 'dropdown' === $view ? 'dropdown' : ( 'radio' === $view ? 'radio' : 'checkbox' ), 'settings' => $settings );
				$this->row( $mapped, 'filter ' . $f->ID . ' (' . $source . ')', 'author / ' . $view, 'author' );
				break;
			default:
				$this->row( $unmapped, 'filter ' . $f->ID . ' (' . $tag . ')', $entity . ' ' . $ename, __( 'Date filters have no matching block in Product Filter.', 'woo-product-filter' ) );
		}
		if ( $block ) {
			$block['source'] = $source;
		}
		return $block;
	}

	/**
	 * Category / tag / brand / attribute filter.
	 */
	private function termBlock( $f, $d, $tax, $view, $label, $settings, &$mapped, &$unmapped ) {
		$tag     = $label;
		$swatch  = in_array( $view, array( 'labels', 'checkboxes' ), true ) && $this->swatchTaxonomy( $tax ) && $this->termColours( $tax );
		$looks   = array( 'checkboxes' => 'checkbox', 'radio' => 'radio', 'labels' => 'buttons', 'dropdown' => 'dropdown' );
		if ( ! isset( $looks[ $view ] ) ) {
			$this->row( $unmapped, 'view (' . $tag . ')', $view, __( 'No such look for terms; imported as tick boxes.', 'woo-product-filter' ) );
		}
		$display = $swatch ? 'colors' : $this->freeDisplay( isset( $looks[ $view ] ) ? $looks[ $view ] : 'checkbox', $unmapped, 'view (' . $tag . ')' );
		$block   = $this->taxonomyBlock( $tax, $label, $display, $unmapped );
		if ( ! $block ) {
			return null;
		}
		if ( 'buttons' === $display ) {
			$settings['display_type'] = 'chips';
		} elseif ( 'colors' === $display ) {
			$settings['display_type'] = 'swatches';
		}
		$this->row( $mapped, 'filter ' . $f->ID . ' (' . $label . ' (' . $tax . '))', 'taxonomy ' . $tax . ' / ' . $view . ( $swatch ? ' + colour swatches' : '' ), $block['type'] . ' / ' . $display );
		if ( $swatch ) {
			$this->row( $mapped, __( 'Swatch colours', 'woo-product-filter' ) . ' (' . $tax . ')', count( $this->termColours( $tax ) ) . ' ' . __( 'terms', 'woo-product-filter' ), __( 'copied to our swatch colour of each term when the filter is created (terms that already have one keep it)', 'woo-product-filter' ) );
		}

		// Logic between picked terms (radio and dropdown pick one).
		$logic = isset( $d['logic'] ) ? strtolower( (string) $d['logic'] ) : 'or';
		if ( 'and' === $logic && ! in_array( $display, array( 'radio', 'dropdown' ), true ) ) {
			$settings['f_query_logic'] = 'and';
			$this->row( $mapped, 'logic (' . $tag . ')', 'AND', 'logic and' );
		} elseif ( 'and' === $logic ) {
			$this->row( $unmapped, 'logic (' . $tag . ')', 'AND', __( 'One choice at a time in this look, so AND has no effect.', 'woo-product-filter' ) );
		}

		// Excluded terms, or "include": only these terms.
		$ids = array();
		foreach ( (array) ( isset( $d['exclude'] ) ? $d['exclude'] : array() ) as $id ) {
			$t = get_term( (int) $id, $tax );
			if ( $t && ! is_wp_error( $t ) ) {
				$ids[] = (int) $id;
			}
		}
		if ( $ids ) {
			if ( self::yes( $d, 'include' ) ) {
				$settings['f_mlist[]'] = implode( ',', $ids );
				$this->row( $mapped, 'include + terms (' . $tag . ')', implode( ',', $ids ), 'f_mlist[] (only these)' );
			} else {
				$settings['f_exclude_terms'] = implode( ',', $ids );
				$this->row( $mapped, 'exclude (' . $tag . ')', implode( ',', $ids ), 'f_exclude_terms' );
			}
		}

		// Tree.
		if ( is_taxonomy_hierarchical( $tax ) ) {
			if ( self::yes( $d, 'hierarchy' ) ) {
				$settings['f_show_hierarchical'] = '1';
				$this->row( $mapped, 'hierarchy (' . $tag . ')', 'yes', 'f_show_hierarchical (tree)' );
			} elseif ( 'dropdown' !== $view ) {
				$this->row( $unmapped, 'hierarchy (' . $tag . ')', 'no', __( 'Our category blocks always show sub-categories under their parent.', 'woo-product-filter' ) );
			}
		}

		// Term order.
		$orderby = isset( $d['orderby'] ) ? (string) $d['orderby'] : 'default';
		$orders  = array( 'nameasc' => 'asc', 'namedesc' => 'desc', 'postcountdesc' => 'count' );
		if ( isset( $orders[ $orderby ] ) ) {
			$settings['f_sort_by'] = $orders[ $orderby ];
			$this->row( $mapped, 'orderby (' . $tag . ')', $orderby, 'f_sort_by ' . $orders[ $orderby ] );
		} elseif ( 'menuasc' === $orderby ) {
			$this->row( $mapped, 'orderby (' . $tag . ')', $orderby, __( 'default (attribute terms follow their WooCommerce order)', 'woo-product-filter' ) );
		} elseif ( 'default' !== $orderby && '' !== $orderby ) {
			$this->row( $unmapped, 'orderby (' . $tag . ')', $orderby, __( 'This sort order is not offered; terms keep the WooCommerce order.', 'woo-product-filter' ) );
		}

		// "More / Less" after 5 terms, and the term search box.
		if ( in_array( $display, array( 'checkbox', 'radio', 'buttons', 'colors' ), true ) ) {
			$settings['show_more_rows'] = self::yes( $d, 'more_less' ) ? (string) self::MORE_LESS : '0';
			$this->row( $mapped, 'more_less (' . $tag . ')', self::yes( $d, 'more_less' ) ? 'yes' : 'no', 'show_more_rows ' . $settings['show_more_rows'] );
		}
		if ( self::yes( $d, 'search' ) ) {
			$settings['f_show_search_input'] = '1';
			$this->row( $mapped, 'search (' . $tag . ')', 'yes', 'f_show_search_input' );
		}
		if ( 'dropdown' === $view && ! empty( $d['dropdown_label'] ) ) {
			$settings['f_dropdown_first_option_text'] = html_entity_decode( (string) $d['dropdown_label'], ENT_QUOTES );
			$this->row( $mapped, 'dropdown_label (' . $tag . ')', $d['dropdown_label'], 'f_dropdown_first_option_text' );
		}
		if ( isset( $d['show_term_names'] ) && ! self::yes( $d, 'show_term_names' ) ) {
			$this->row( $unmapped, 'show_term_names (' . $tag . ')', 'no', __( 'Our swatches always carry the name for screen readers and on hover.', 'woo-product-filter' ) );
		}
		$block['settings'] = $settings;
		return $block;
	}

	/**
	 * Copy their swatch colours (term meta) into ours before the filter is created, for every swatch block.
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
			'widgets'    => array( 'wpc_filters_widget' ),
			'shortcodes' => array( 'fe_widget' ),
			'blocks'     => array( 'wpc-filter-everything/filter' ),
			'products'   => array(),
		);
	}

	/**
	 * Widget and block carry no set: Filter Everything shows the set whose location matches the page. A sidebar widget
	 * answers every product set it shows on some archive page (array, Shop page set first); a block or a [fe_widget]
	 * without id the set of the page that holds it ($ctx['post'] from the switch, else every page holding it).
	 * Sets of other post types come back as "post-<id>" so they never match a product set.
	 */
	public function setOfPlacement( $kind, $name, $data, $ctx = array() ) {
		$data = (array) $data;
		$ctx  = (array) $ctx;
		if ( 'widget' === $kind && 'wpc_filters_widget' === $name ) {
			$sets = $this->archiveSets();
			return $sets ? $sets : '';
		}
		if ( ! empty( $ctx['post'] ) && in_array( $kind, array( 'shortcode', 'block' ), true ) && ! ( 'shortcode' === $kind && ! empty( $data['id'] ) ) ) {
			// The switch says which page holds it: the set Filter Everything shows on that page ('' when none does).
			return $this->pageSet( (int) $ctx['post'] );
		}
		if ( 'shortcode' === $kind && 'fe_widget' === $name ) {
			$id = isset( $data['id'] ) ? (int) preg_replace( '/[^\d]/', '', (string) $data['id'] ) : 0;
			if ( $id ) {
				$p = get_post( $id );
				if ( $p && self::SET_TYPE === $p->post_type ) {
					return 'product' === $this->postTypeOf( $p ) ? (string) $id : 'post-' . $id;
				}
				return '';
			}
			return $this->setOfPages( $this->postsHolding( 'shortcode', $data ) );
		}
		if ( 'block' === $kind && 'wpc-filter-everything/filter' === $name ) {
			return $this->setOfPages( $this->postsHolding( 'block', $data ) );
		}
		return '';
	}

	/**
	 * One set when every page holding the placement resolves to the same set, else ''.
	 */
	private function setOfPages( $ids ) {
		$found = array();
		foreach ( $ids as $id ) {
			$found[ $this->pageSet( $id ) ] = true;
		}
		return 1 === count( $found ) ? (string) key( $found ) : '';
	}

	/**
	 * Posts whose content holds this block (same attrs) or this [fe_widget] (same atts, no id).
	 */
	private function postsHolding( $kind, $data ) {
		global $wpdb;
		$key = 'h' . $kind . md5( wp_json_encode( $data ) );
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}
		$like = 'block' === $kind ? '%<!-- wp:wpc-filter-everything/filter%' : '%[fe\_widget%';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status IN ('publish','draft','private','future','pending') AND post_content LIKE %s LIMIT 300", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$out  = array();
		foreach ( (array) $rows as $r ) {
			if ( 'block' === $kind ) {
				$hit = false;
				$walk = function ( $blocks ) use ( &$walk, &$hit, $data ) {
					foreach ( (array) $blocks as $b ) {
						if ( 'wpc-filter-everything/filter' === $b['blockName'] && (array) $b['attrs'] == $data ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- attribute arrays, order independent
							$hit = true;
						}
						if ( ! empty( $b['innerBlocks'] ) ) {
							$walk( $b['innerBlocks'] );
						}
					}
				};
				$walk( parse_blocks( $r->post_content ) );
			} else {
				$hit = false;
				if ( preg_match_all( '/' . get_shortcode_regex( array( 'fe_widget' ) ) . '/', $r->post_content, $all, PREG_SET_ORDER ) ) {
					foreach ( $all as $m ) {
						if ( (array) shortcode_parse_atts( $m[3] ) == $data ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
							$hit = true;
						}
					}
				}
			}
			if ( $hit ) {
				$out[] = (int) $r->ID;
			}
		}
		$this->cache[ $key ] = $out;
		return $out;
	}

	/* ------------------------------------------------------------------ old Filter Everything links */

	/**
	 * Their URL vars on this site: array( var => array( entity, e_name ) ), from the product sets' filters
	 * (post_name) and the global prefix list.
	 */
	private function urlVars() {
		if ( isset( $this->cache['vars'] ) ) {
			return $this->cache['vars'];
		}
		$vars = array();
		$perm = get_option( 'wpc_filter_permalinks', array() );
		foreach ( (array) $perm as $entityKey => $var ) {
			$parts = explode( '#', (string) $entityKey, 2 );
			if ( 2 === count( $parts ) && '' !== (string) $var ) {
				$vars[ (string) $var ] = array( $parts[0], $parts[1] );
			}
		}
		foreach ( $this->setPosts( true ) as $p ) {
			foreach ( $this->fieldsOf( $p->ID ) as $f ) {
				$d = $this->content( $f );
				if ( '' !== $f->post_name && isset( $d['entity'], $d['e_name'] ) ) {
					$vars[ (string) $f->post_name ] = array( (string) $d['entity'], (string) $d['e_name'], isset( $d['step'] ) ? (string) $d['step'] : '1' );
				}
			}
		}
		$this->cache['vars'] = $vars;
		return $vars;
	}

	private static function ourKey( $tax ) {
		$map = array( 'product_cat' => 'category', 'product_tag' => 'tag', 'product_brand' => 'brand' );
		if ( isset( $map[ $tax ] ) ) {
			return $map[ $tax ];
		}
		return 0 === strpos( $tax, 'pa_' ) ? substr( $tax, 3 ) : '';
	}

	/**
	 * "blue;red" => existing term slugs of $tax.
	 */
	private static function termSlugs( $raw, $tax ) {
		$out = array();
		foreach ( preg_split( '/[;,]/', (string) $raw ) as $v ) {
			$v = trim( urldecode( $v ) );
			if ( '' === $v ) {
				continue;
			}
			$t = get_term_by( 'slug', sanitize_title( $v ), $tax );
			if ( ! $t ) {
				$t = get_term_by( 'slug', $v, $tax );
			}
			if ( $t && ! is_wp_error( $t ) ) {
				$out[] = $t->slug;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Their links after the switch: /shop/?colour=blue;red&size=m&min_price=20&max_price=60&stock=instock&rating=rated-4
	 * (/page/N/ kept as wpf_page) => our 4.0 args on the same page. Price alone (min_price / max_price) is left to
	 * WooCommerce, which reads the same names itself.
	 */
	public function legacyUrl( $path, $query ) {
		$query = (array) $query;
		$vars  = $this->urlVars();
		$args  = array();
		$hit   = false;
		foreach ( $vars as $var => $def ) {
			$entity = $def[0];
			$ename  = $def[1];
			$step   = isset( $def[2] ) ? $def[2] : '1';
			if ( 'post_meta_num' === $entity ) {
				$min = isset( $query[ 'min_' . $var ] ) && is_numeric( $query[ 'min_' . $var ] ) ? (string) (float) $query[ 'min_' . $var ] : '';
				$max = isset( $query[ 'max_' . $var ] ) && is_numeric( $query[ 'max_' . $var ] ) ? (string) (float) $query[ 'max_' . $var ] : '';
				if ( '' === $min && '' === $max ) {
					continue;
				}
				// With a whole step they compare the value cast to an integer (meta_query NUMERIC): 60.72 passes "max 60".
				if ( '' !== $max && floor( (float) $max ) === (float) $max && floor( (float) $step ) === (float) $step ) {
					$max = (string) ( (float) $max + 0.99 );
				}
				if ( '_price' === $ename ) {
					$args['wpf_price'] = $min . '-' . $max;
				} else {
					$args[ 'wpf_' . sanitize_key( $ename ) ] = $min . '-' . $max;
					$hit                                   = true;
				}
				continue;
			}
			if ( ! isset( $query[ $var ] ) || ! is_scalar( $query[ $var ] ) || '' === (string) $query[ $var ] ) {
				continue;
			}
			$raw = (string) $query[ $var ];
			if ( 'taxonomy' === $entity && 'product_visibility' === $ename ) {
				$min = 0;
				foreach ( preg_split( '/[;,]/', $raw ) as $v ) {
					if ( preg_match( '/^rated-([1-5])$/', trim( $v ), $m ) ) {
						$min = $min ? min( $min, (int) $m[1] ) : (int) $m[1];
					} elseif ( 'featured' === trim( $v ) ) {
						$args['wpf_featured'] = '1';
						$hit                  = true;
					} elseif ( 'outofstock' === trim( $v ) ) {
						$args['wpf_stock'] = 'out';
						$hit               = true;
					}
				}
				if ( $min ) {
					$args['wpf_rating'] = (string) $min;
					$hit                = true;
				}
			} elseif ( 'taxonomy' === $entity && '' !== self::ourKey( $ename ) && taxonomy_exists( $ename ) ) {
				$slugs = self::termSlugs( $raw, $ename );
				if ( $slugs ) {
					$args[ 'wpf_' . self::ourKey( $ename ) ] = implode( ',', $slugs );
					$hit                                     = true;
				}
			} elseif ( 'post_meta' === $entity && '_stock_status' === $ename ) {
				$map = array( 'instock' => 'in', 'outofstock' => 'out', 'onbackorder' => 'backorder' );
				foreach ( preg_split( '/[;,]/', $raw ) as $v ) {
					$v = trim( $v );
					if ( isset( $map[ $v ] ) ) {
						$args['wpf_stock'] = $map[ $v ];
						$hit               = true;
						break; // ours takes one status
					}
				}
			} elseif ( in_array( $entity, array( 'author', 'author_author' ), true ) ) {
				$users = array();
				foreach ( preg_split( '/[;,]/', $raw ) as $v ) {
					$v = sanitize_title( urldecode( trim( $v ) ) );
					if ( '' !== $v ) {
						$users[] = $v;
					}
				}
				if ( $users ) {
					$args['wpf_author'] = implode( ',', $users );
					$hit                = true;
				}
			}
		}
		if ( ! $hit ) {
			return null; // not one of their links, or price only (WooCommerce handles min_price / max_price itself)
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
