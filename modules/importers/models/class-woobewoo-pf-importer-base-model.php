<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Base_Model Class
 *
 * Shared pieces of the rival mappers: block records, mapped / unmapped report rows, display
 * type normalisation and the "create our filter as a draft" step (model save only, no raw SQL).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

abstract class WooBeWoo_PF_Importer_Base_Model extends WooBeWoo_PF_Model {

	/** Unique slug of the rival (yith, husky, berocket, filtereverything). */
	abstract public function slug();

	/** Readable name. */
	abstract public function name();

	/** Main plugin file(s) relative to the plugins folder, for the active check. */
	abstract public function pluginFiles();

	/** Whether the rival left data behind (options, tables or posts), active or not. */
	abstract public function installed();

	/** Their filter sets: array of array( 'id', 'title', 'blocks' => int, 'shortcode' => string, 'note' => string ). */
	abstract public function sets();

	/** Map one set: array( 'title', 'blocks' => array, 'main' => array, 'mapped' => array, 'unmapped' => array ). */
	abstract public function map( $setId );

	/**
	 * Where their filter can sit, for the placement swap:
	 * array( 'widgets' => id_bases, 'shortcodes' => tags, 'blocks' => block names, 'products' => product grid shortcode tags they need ).
	 */
	public function signatures() {
		return array();
	}

	/**
	 * Which of their sets a placement shows ($kind widget|shortcode|block, $data = widget instance, shortcode atts or block attrs).
	 * '' when it cannot tell: the placement is then offered with every set.
	 * The switch passes a 4th argument, where the placement sits: array( 'post' => id ) or array( 'sidebar' => id ).
	 * Mappers that need it declare setOfPlacement( $kind, $name, $data, $ctx = array() ) (still compatible with this one).
	 */
	public function setOfPlacement( $kind, $name, $data ) {
		return '';
	}

	/**
	 * Their own automatic shop placement for this set: array( 'label' => '', 'place' => sidebar|above, 'pages' => both|shop ), or null.
	 */
	public function autoPlacement( $setId ) {
		return null;
	}

	/**
	 * Turn their automatic placement off. Returns array( option => old value ) for the undo.
	 */
	public function disableAutoPlacement( $setId ) {
		return array();
	}

	/**
	 * Their filter links (bookmarks, search engines) after the switch: return our query args for this request
	 * (e.g. array( 'wpf_color' => 'red,blue' )) and the path to send it to, as array( 'path' => '', 'args' => array() ),
	 * or null when the request is not one of their filter links. Called only while a switch from this plugin is live.
	 */
	public function legacyUrl( $path, $query ) {
		return null;
	}

	/**
	 * What replaces their product grid block (signatures()['productBlocks']) on a switched page: a parsed block array
	 * or a shortcode string (wrapped in a core/shortcode block). Default: WooCommerce [products] with their grid size.
	 */
	public function blocksReplacement( $blockName, $attrs ) {
		$attrs   = (array) $attrs;
		$columns = ! empty( $attrs['columns'] ) ? (int) $attrs['columns'] : 0;
		$rows    = ! empty( $attrs['rows'] ) ? (int) $attrs['rows'] : 0;
		$limit   = $columns && $rows ? $columns * $rows : (int) apply_filters( 'loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page() );
		$sc      = '[products paginate="true" limit="' . max( 1, $limit ) . '"' . ( $columns ? ' columns="' . $columns . '"' : '' ) . ']';
		return array(
			'blockName'    => 'core/shortcode',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $sc,
			'innerContent' => array( $sc ),
		);
	}

	/**
	 * What replaces their product grid shortcode on a switched page (they stop working once their plugin is off).
	 */
	public function productsReplacement( $tag, $atts ) {
		return '[' . WPF_SHORTCODE_PRODUCTS . ']';
	}

	/**
	 * Apply the owner's choices from the review step: array( 'title' => '', 'blocks' => array( index => array( include, title, display ) ) ).
	 */
	public function applyOverrides( $mapped, $ov ) {
		if ( ! is_array( $ov ) ) {
			return $mapped;
		}
		if ( isset( $ov['title'] ) && '' !== trim( (string) $ov['title'] ) ) {
			$mapped['title'] = sanitize_text_field( (string) $ov['title'] );
		}
		if ( empty( $ov['blocks'] ) || ! is_array( $ov['blocks'] ) ) {
			return $mapped;
		}
		$out = array();
		foreach ( $mapped['blocks'] as $i => $b ) {
			$o = isset( $ov['blocks'][ $i ] ) && is_array( $ov['blocks'][ $i ] ) ? $ov['blocks'][ $i ] : array();
			if ( isset( $o['include'] ) && in_array( $o['include'], array( false, 0, '0', 'false' ), true ) ) {
				continue;
			}
			if ( isset( $o['title'] ) && '' !== trim( (string) $o['title'] ) ) {
				$b['title'] = sanitize_text_field( (string) $o['title'] );
			}
			if ( ! empty( $o['display'] ) && in_array( $o['display'], self::displayChoices( $b ), true ) ) {
				$b['display'] = $o['display'];
			}
			$out[] = $b;
		}
		$mapped['blocks'] = $out;
		return $mapped;
	}

	/**
	 * Display types the owner can pick for a block in the review step (empty: the block has one look).
	 */
	public static function displayChoices( $block ) {
		$type = isset( $block['type'] ) ? $block['type'] : '';
		if ( ! in_array( $type, array( 'category', 'attribute', 'tags', 'brand', 'perfectbrand' ), true ) ) {
			return array();
		}
		return array( 'checkbox', 'radio', 'dropdown', 'mul_dropdown', 'buttons', 'colors' );
	}

	public function active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( (array) $this->pluginFiles() as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detection row for the admin screen.
	 */
	public function detect() {
		$installed = $this->installed() || ( $this->pluginFiles() && $this->active() ); // active with nothing set up yet
		$active    = $installed && $this->active();
		$sets      = $installed ? $this->sets() : array();
		// Two sets with one name (YITH can create its "Default preset" twice on activation): tell them apart by id.
		$names = array_count_values( array_map( 'strtolower', wp_list_pluck( $sets, 'title' ) ) );
		foreach ( $sets as $i => $s ) {
			if ( $names[ strtolower( $s['title'] ) ] > 1 ) {
				$sets[ $i ]['title'] .= ' #' . $s['id'];
			}
		}
		return array(
			'slug'      => $this->slug(),
			'name'      => $this->name(),
			'installed' => $installed,
			'active'    => $active,
			'state'     => $active ? 'active' : ( $installed ? 'inactive' : 'missing' ),
			'sets'      => $sets,
			'count'     => count( $sets ),
		);
	}

	/* ------------------------------------------------------------------ report helpers */

	protected function row( &$list, $setting, $value, $ours = '' ) {
		$list[] = array(
			'setting' => (string) $setting,
			'value'   => is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value ),
			'ours'    => (string) $ours,
		);
	}

	/**
	 * Rival display word => our display type (blocksToOrder understands these).
	 */
	protected function display( $raw, $default = 'checkbox' ) {
		$raw = strtolower( trim( (string) $raw ) );
		$map = array(
			'checkbox'   => 'checkbox',
			'checkboxes' => 'checkbox',
			'multi'      => 'checkbox',
			'grey-check' => 'checkbox',
			'radio'      => 'radio',
			'radiobuttons' => 'radio',
			'select'     => 'dropdown',
			'dropdown'   => 'dropdown',
			'mselect'    => 'mul_dropdown',
			'multiselect' => 'mul_dropdown',
			'label'      => 'buttons',
			'labels'     => 'buttons',
			'buttons'    => 'buttons',
			'text'       => 'text',
			'color'      => 'colors',
			'colors'     => 'colors',
			'image'      => 'colors',
			'slider'     => 'slider',
			'range'      => 'slider',
			'tag_cloud'  => 'buttons',
		);
		return isset( $map[ $raw ] ) ? $map[ $raw ] : $default;
	}

	/**
	 * Display types we can draw (all of them, on the 4.0 engine).
	 */
	protected function freeDisplay( $display, &$unmapped, $setting ) {
		// The 4.0 engine draws chips (buttons) and swatches in Free too.
		return $display;
	}

	/**
	 * Attribute taxonomy => block, or null when the taxonomy does not exist here.
	 */
	protected function taxonomyBlock( $taxonomy, $title, $display, &$unmapped ) {
		$taxonomy = sanitize_key( $taxonomy );
		if ( 'product_cat' === $taxonomy ) {
			return array( 'type' => 'category', 'title' => $title, 'display' => $display );
		}
		if ( 'product_tag' === $taxonomy ) {
			return array( 'type' => 'tags', 'title' => $title, 'display' => $display );
		}
		if ( in_array( $taxonomy, array( 'product_brand', 'pwb-brand', 'berocket_brand' ), true ) ) {
			return array( 'type' => taxonomy_exists( 'product_brand' ) ? 'brand' : 'perfectbrand', 'title' => $title, 'display' => $display );
		}
		if ( 0 === strpos( $taxonomy, 'pa_' ) && taxonomy_exists( $taxonomy ) ) {
			return array( 'type' => 'attribute', 'taxonomy' => $taxonomy, 'title' => $title, 'display' => $display );
		}
		if ( taxonomy_exists( 'pa_' . $taxonomy ) ) {
			return array( 'type' => 'attribute', 'taxonomy' => 'pa_' . $taxonomy, 'title' => $title, 'display' => $display );
		}
		$this->row( $unmapped, 'taxonomy', $taxonomy, taxonomy_exists( $taxonomy ) ? __( 'Custom taxonomies need Pro.', 'woo-product-filter' ) : __( 'This taxonomy does not exist on this site.', 'woo-product-filter' ) );
		return null;
	}

	/**
	 * Create our filter as a draft (display_on_page none) from a map() result. Returns id or WP_Error.
	 */
	public function createDraft( $mapped, $titleSuffix = '', $setId = '' ) {
		$main = isset( $mapped['main'] ) ? (array) $mapped['main'] : array();
		$main['frontend_engine']   = '4'; // imported filters are new filters: always the 4.0 engine
		// The source's page rule (e.g. Shop only, some categories) waits here until the switch makes the filter live.
		$main['wbw_import_pages']  = isset( $main['display_on_page'] ) && 'none' !== $main['display_on_page'] ? (string) $main['display_on_page'] : 'both';
		$main['display_on_page']   = 'none';
		$main['wbw_placement']     = '';
		$main['wbw_imported_from'] = $this->slug();
		$main['wbw_imported_set']  = (string) $setId;
		$title = isset( $mapped['title'] ) && '' !== $mapped['title'] ? $mapped['title'] : $this->name();
		if ( $titleSuffix ) {
			$title .= ' ' . $titleSuffix;
		}
		$title = self::uniqueTitle( $title );
		return WooBeWoo_PF_Cli::createFilter( $title, isset( $mapped['blocks'] ) ? $mapped['blocks'] : array(), $main );
	}

	/**
	 * Our filters as array( id => array( 'title', 'settings' ) ).
	 */
	public static function ourFilters() {
		$out  = array();
		$rows = WooBeWoo_PF_Db::get( 'SELECT `id`, `title`, `setting_data` FROM `@__filters`', 'all' );
		foreach ( (array) $rows as $r ) {
			$s = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( isset( $r['setting_data'] ) ? $r['setting_data'] : '' ) : array();
			$out[ (int) $r['id'] ] = array(
				'title'    => isset( $r['title'] ) ? (string) $r['title'] : '',
				'settings' => isset( $s['settings'] ) && is_array( $s['settings'] ) ? $s['settings'] : array(),
			);
		}
		return $out;
	}

	/**
	 * "Shop sidebar" becomes "Shop sidebar (2)" when one of our filters already has that name.
	 */
	public static function uniqueTitle( $title ) {
		$taken = array_map( 'strtolower', wp_list_pluck( self::ourFilters(), 'title' ) );
		$try   = $title;
		for ( $n = 2; in_array( strtolower( $try ), $taken, true ) && $n < 100; $n++ ) {
			$try = $title . ' (' . $n . ')';
		}
		return $try;
	}

	/**
	 * Our filter already imported from this set, or 0.
	 */
	public function importedAs( $setId ) {
		foreach ( self::ourFilters() as $id => $f ) {
			$s = $f['settings'];
			if ( isset( $s['wbw_imported_from'], $s['wbw_imported_set'] ) && $this->slug() === $s['wbw_imported_from'] && (string) $setId === (string) $s['wbw_imported_set'] ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Our settings array (for the side by side preview draft) from a map() result.
	 */
	public function draftSettings( $mapped ) {
		$order = WooBeWoo_PF_Cli::blocksToOrder( isset( $mapped['blocks'] ) ? $mapped['blocks'] : array() );
		$main  = WooBeWoo_PF_Cli::sanitizeMain( isset( $mapped['main'] ) ? $mapped['main'] : array() );
		return array_merge(
			array(
				'display_on_page'            => 'both',
				'enable_ajax'                => '1',
				'auto_update_filter'         => '1',
				'filter_recount'             => '1',
				'show_filter_immediately'    => '1',
				'product_container_selector' => 'ul.products',
				'wbw_placement'              => 'above',
				'frontend_engine'            => '4',
			),
			$main,
			array( 'filters' => array( 'order' => wp_json_encode( $order ) ) )
		);
	}

	/**
	 * Common main settings from rival wide switches: ajax, counts, mobile, layout.
	 */
	protected function mainFrom( $ajax, $showCount, $mobile, &$mapped, $extra = array() ) {
		$main = array( 'enable_ajax' => $ajax ? '1' : '0', 'auto_update_filter' => $ajax ? '1' : '0' );
		$this->row( $mapped, __( 'Update without reloading (AJAX)', 'woo-product-filter' ), $ajax ? 'yes' : 'no', 'enable_ajax' );
		if ( null !== $showCount ) {
			$this->row( $mapped, __( 'Show product counts', 'woo-product-filter' ), $showCount ? 'yes' : 'no', 'f_show_count on every block' );
		}
		if ( null !== $mobile ) {
			$main['mobile_drawer'] = $mobile ? '1' : '0';
			$this->row( $mapped, __( 'Phone behaviour', 'woo-product-filter' ), $mobile ? 'collapsed / drawer' : 'always open', 'mobile_drawer' );
		}
		return array_merge( $main, $extra );
	}
}
