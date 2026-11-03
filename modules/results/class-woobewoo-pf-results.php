<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Results Class
 *
 * Four small storefront features of the 4.0 engine. None of them touches a classic (3.x) filter: the hooks below
 * only fire for engine 4 markup, requests and settings.
 *
 *  - "Send shoppers to another page" (settings results_page, results_page_url, results_page_button): a filter on
 *    the home page or a landing page does not filter in place; on apply (or on the first pick when "apply as soon
 *    as shoppers pick" is on) the browser opens the shop, a category or any address with the canonical wpf_* params
 *    (pretty when clean links are on and the target is an archive).
 *  - Products per page block `wpfPerPage` through the block registry: wpf_per_page=24 from the list in
 *    f_per_page_list, honoured by the engine through `woobewoo_pf_per_page` and by the archive loop through
 *    `loop_shop_per_page` (1..200). Never a path segment of a pretty link; never a chip.
 *  - [wpf-found-count] shortcode and the "Show how many products match" switch (show_found_count): "128 products",
 *    updated live from the wpf:results event (settings found_count_text / found_count_text_one, %d = the number).
 *  - Skeleton loader (loading_skeleton): grey placeholder blocks over the product cards and a shimmer on the counts
 *    while a request runs, honouring prefers-reduced-motion.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Results extends WooBeWoo_PF_Module {

	const MIN_PER_PAGE = 1;
	const MAX_PER_PAGE = 200;
	const DEFAULT_LIST = '12,24,48';

	/** @var bool per_page() is being read for the store default: the request value is left out */
	private static $storeDefault = false;
	/** @var bool storefront assets queued */
	private static $assetsDone = false;

	public function init() {
		parent::init();
		add_action( 'woobewoo_pf_register_blocks', array( $this, 'registerBlocks' ) );
		add_filter( 'woobewoo_pf_per_page', array( $this, 'perPage' ), 10, 2 );
		add_filter( 'woobewoo_pf_admin_schema', array( $this, 'adminSchema' ), 10, 2 );
		add_filter( 'woobewoo_pf_frontend4_before_form', array( $this, 'beforeForm' ), 10, 3 );
		add_action( 'woobewoo_pf_frontend4_enqueue', array( $this, 'enqueue' ) );
		add_shortcode( 'wpf-found-count', array( $this, 'foundCountShortcode' ) );
		if ( ! is_admin() ) {
			// After the woofilters module's store-wide "Products per page" (99999): the shopper's pick wins on page load.
			add_filter( 'loop_shop_per_page', array( $this, 'loopShopPerPage' ), 100000 );
		}
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * Defaults of the main settings this module reads (none of them is in settings-schema.php).
	 */
	public static function defaults() {
		return array(
			'results_page'         => '',
			'results_page_url'     => '',
			'results_page_button'  => '',
			'show_found_count'     => '',
			'found_count_text'     => '',
			'found_count_text_one' => '',
			'loading_skeleton'     => '',
		);
	}

	/**
	 * A main setting as a string (the defaults above when unset).
	 */
	public static function setting( $settings, $key ) {
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return $default;
		}
		$v = WooBeWoo_PF_Frontend4::setting( $settings, $key, $default );
		return is_scalar( $v ) ? (string) $v : $default;
	}

	/**
	 * A switch value as a boolean ('', '0', 'no', 'false', 'off' are off).
	 */
	public static function on( $v ) {
		return ! in_array( strtolower( trim( (string) $v ) ), array( '', '0', 'no', 'false', 'off' ), true );
	}

	/* ------------------------------------------------------------------ results page */

	/**
	 * The address picks are sent to, '' when the filter works in place: results_page_url (any address) wins over
	 * results_page ('shop' or 'cat:<id>').
	 */
	public static function results_url( $settings ) {
		$url = trim( self::setting( $settings, 'results_page_url' ) );
		if ( '' !== $url ) {
			return esc_url_raw( $url );
		}
		$page = trim( self::setting( $settings, 'results_page' ) );
		if ( 'shop' === $page && function_exists( 'wc_get_page_permalink' ) ) {
			$link = wc_get_page_permalink( 'shop' );
			return is_string( $link ) ? $link : '';
		}
		if ( preg_match( '/^cat:(\d+)$/', $page, $m ) ) {
			$link = get_term_link( (int) $m[1], 'product_cat' );
			return is_string( $link ) ? $link : '';
		}
		return '';
	}

	/**
	 * May the storefront script build a pretty link for the target? Only for an archive (the shop or a category)
	 * and only while clean links are on; any other address keeps the query string, as a reload of /page/size-42/
	 * would be a 404 there.
	 */
	private static function pretty_target( $settings ) {
		if ( '' !== trim( self::setting( $settings, 'results_page_url' ) ) ) {
			return false;
		}
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		return $links && method_exists( $links, 'cleanLinksOn' ) && $links->cleanLinksOn();
	}

	/* ------------------------------------------------------------------ products per page */

	/**
	 * Registers the Products per page block in the 4.0 block registry.
	 */
	public function registerBlocks() {
		if ( ! class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			return;
		}
		WooBeWoo_PF_Blocks::register(
			'wpfPerPage',
			array(
				'kind'         => 'per_page',
				'label'        => __( 'Products per page', 'woo-product-filter' ),
				'slug'         => 'per_page',
				'unique'       => true,
				'content_type' => '',
				'card'         => array(
					'icon'  => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="19" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="12" width="6" height="5" rx="1.5"/><path d="M12 14.5h13"/></svg>',
					'group' => 'basics',
					'spec'  => __( 'Shoppers pick 12, 24 or 48', 'woo-product-filter' ),
				),
				'displays'     => array( 'list', 'dropdown', 'chips' ),
				'controls'     => array(
					array(
						'key'     => 'f_per_page_list',
						'type'    => 'text',
						'label'   => __( 'Choices, separated by commas', 'woo-product-filter' ),
						'help'    => __( 'Numbers from 1 to 200. The store default applies until shoppers pick one.', 'woo-product-filter' ),
						'default' => self::DEFAULT_LIST,
					),
				),
				'defaults'     => array(
					'f_per_page_list' => self::DEFAULT_LIST,
					'f_title'         => __( 'Products per page', 'woo-product-filter' ),
				),
				'single'       => true,
				'key'          => 'per_page',
				// The value never lands on the query: it is not a filter (no chip, no "filtered" state, no cache key).
				'parse'        => array( __CLASS__, 'parseNothing' ),
				'param'        => array( __CLASS__, 'paramNothing' ),
				'chip'         => array( __CLASS__, 'paramNothing' ),
				'render'       => array( __CLASS__, 'renderPerPage' ),
				'cli'          => 'per_page',
			)
		);
	}

	/**
	 * Registry `parse`: wpf_per_page is read from the request directly (requested_per_page()).
	 */
	public static function parseNothing() {
	}

	/**
	 * Registry `param` and `chip`: nothing to print.
	 */
	public static function paramNothing() {
		return '';
	}

	/**
	 * wpf_per_page from the request (query string or query var), clamped to 1..200; 0 when absent.
	 */
	public static function requested_per_page() {
		$raw = null;
		if ( isset( $_GET['wpf_per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public storefront parameter
			$raw = sanitize_text_field( wp_unslash( $_GET['wpf_per_page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) && isset( $GLOBALS['wp']->query_vars['wpf_per_page'] ) ) {
			$raw = sanitize_text_field( (string) $GLOBALS['wp']->query_vars['wpf_per_page'] );
		}
		if ( null === $raw || '' === $raw || ! is_numeric( $raw ) ) {
			return 0;
		}
		$n = (int) $raw;
		return $n < self::MIN_PER_PAGE ? 0 : min( self::MAX_PER_PAGE, $n );
	}

	/**
	 * Is this an engine 4 request: the engine's JSON endpoint, or a page an engine 4 filter drives?
	 */
	private static function engine4_request() {
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return false;
		}
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the endpoint checks the filter itself
			return WooBeWoo_PF_Frontend4::AJAX_QUERY === $action;
		}
		return (bool) WooBeWoo_PF_Frontend4::active_filter();
	}

	/**
	 * `woobewoo_pf_per_page`: the shopper's wpf_per_page on engine 4 requests.
	 *
	 * @param int                   $n     Products per page the engine computed.
	 * @param WooBeWoo_PF_Query|null $query The query (null when unknown).
	 */
	public function perPage( $n, $query = null ) {
		if ( self::$storeDefault ) {
			return $n;
		}
		$r = self::requested_per_page();
		return $r > 0 && self::engine4_request() ? $r : $n;
	}

	/**
	 * `loop_shop_per_page`: the same pick on the archive page load (WooCommerce pages its own loop there).
	 */
	public function loopShopPerPage( $n ) {
		return $this->perPage( $n );
	}

	/**
	 * The store default (WooCommerce rows x columns, the store-wide option), ignoring the request value.
	 */
	public static function store_per_page() {
		if ( ! class_exists( 'WooBeWoo_PF_Engine' ) ) {
			return (int) get_option( 'posts_per_page', 12 );
		}
		self::$storeDefault = true;
		$n                  = (int) WooBeWoo_PF_Engine::per_page();
		self::$storeDefault = false;
		return $n;
	}

	/**
	 * The choices of a block: f_per_page_list ("12,24,48") as unique integers in 1..200, in the given order.
	 */
	public static function per_page_values( $block ) {
		$raw = class_exists( 'WooBeWoo_PF_Frontend4' ) ? WooBeWoo_PF_Frontend4::block_setting( $block, 'f_per_page_list', self::DEFAULT_LIST ) : self::DEFAULT_LIST;
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $raw ) as $v ) {
			if ( ! is_numeric( $v ) ) {
				continue;
			}
			$n = (int) $v;
			if ( $n >= self::MIN_PER_PAGE && $n <= self::MAX_PER_PAGE && ! in_array( $n, $out, true ) ) {
				$out[] = $n;
			}
		}
		if ( ! $out ) {
			$out = array( 12, 24, 48 );
		}
		return $out;
	}

	/**
	 * "24 per page".
	 */
	public static function per_page_label( $n ) {
		/* translators: %d: number of products on one page */
		return sprintf( __( '%d per page', 'woo-product-filter' ), (int) $n );
	}

	/**
	 * Registry `render`: radios (tick box list or chips) or a dropdown named wpf_per_page. The controls carry
	 * data-wpf4-control, so the storefront script collects the pick and puts it in the request and the address.
	 *
	 * @param array                      $b       Normalized block.
	 * @param string                     $display list | chips | dropdown.
	 * @param bool                       $mobile  Phone variant (disabled until shown).
	 * @param WooBeWoo_PF_Frontend4_View $view    The view (fid()).
	 */
	public static function renderPerPage( $b, $display, $mobile, $view ) {
		$values  = self::per_page_values( $b['block'] );
		$current = self::requested_per_page();
		if ( $current < 1 ) {
			$current = self::store_per_page();
		}
		$name = 'wpf_per_page';
		$dis  = $mobile ? ' disabled' : '';
		if ( 'dropdown' === $display ) {
			$id = $view->fid( $name, $mobile );
			$h  = '<label class="wpf4__sr" for="' . esc_attr( $id ) . '">' . esc_html( $b['title'] ) . '</label>';
			$h .= '<select class="wpf4__select" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $dis . ' data-wpf4-control data-wpf-per-page>';
			foreach ( $values as $v ) {
				$label = self::per_page_label( $v );
				$h    .= '<option value="' . esc_attr( $v ) . '"' . selected( $v, $current, false ) . ' data-label="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</option>';
			}
			return $h . '</select>';
		}
		$chips = 'chips' === $display;
		$h     = '<div class="wpf-perPage__list' . ( $chips ? ' wpf-perPage__list--chips' : '' ) . '" data-wpf-per-page>';
		foreach ( $values as $v ) {
			$id    = $view->fid( $name . '_' . $v, $mobile );
			$on    = $v === $current;
			$label = self::per_page_label( $v );
			$cls   = 'wpf4__opt wpf4__opt--' . ( $chips ? 'chips wpf4__chipOpt' : 'list' ) . ( $on ? ' is-checked' : '' );
			$h    .= '<label class="' . esc_attr( $cls ) . '" for="' . esc_attr( $id ) . '">';
			$h    .= '<input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . checked( $on, true, false ) . $dis . ' data-wpf4-control data-label="' . esc_attr( $label ) . '">';
			$h    .= '<span class="wpf4__optText">' . esc_html( $label ) . '</span></label>';
		}
		return $h . '</div>';
	}

	/* ------------------------------------------------------------------ found count */

	/**
	 * The two text patterns ("%d product" / "%d products"): the owner's, else the translated defaults.
	 */
	public static function found_patterns( $settings ) {
		$one  = trim( self::setting( $settings, 'found_count_text_one' ) );
		$many = trim( self::setting( $settings, 'found_count_text' ) );
		if ( '' === $one && '' === $many ) {
			/* translators: %d: number of matching products */
			$one = _n( '%d product', '%d products', 1, 'woo-product-filter' );
			/* translators: %d: number of matching products */
			$many = _n( '%d product', '%d products', 2, 'woo-product-filter' );
		} elseif ( '' === $one ) {
			$one = $many;
		} elseif ( '' === $many ) {
			$many = $one;
		}
		return array(
			'one'  => $one,
			'many' => $many,
		);
	}

	/**
	 * "128 products" for a count: the owner's pattern, else _n() with the right plural form.
	 */
	public static function found_text( $settings, $n ) {
		$n    = (int) $n;
		$one  = trim( self::setting( $settings, 'found_count_text_one' ) );
		$many = trim( self::setting( $settings, 'found_count_text' ) );
		if ( '' === $one && '' === $many ) {
			/* translators: %d: number of matching products */
			$pattern = _n( '%d product', '%d products', $n, 'woo-product-filter' );
		} else {
			$p       = self::found_patterns( $settings );
			$pattern = 1 === $n ? $p['one'] : $p['many'];
		}
		return str_replace( array( '%1$d', '%1$s', '%d', '%s' ), number_format_i18n( $n ), $pattern );
	}

	/**
	 * The element the script updates on wpf:results.
	 */
	private static function found_html( $settings, $n, $class, $tag = 'span' ) {
		$p = self::found_patterns( $settings );
		return '<' . $tag . ' class="' . esc_attr( $class ) . '" data-wpf-found-count data-one="' . esc_attr( $p['one'] ) . '" data-many="' . esc_attr( $p['many'] ) . '">' . esc_html( self::found_text( $settings, $n ) ) . '</' . $tag . '>';
	}

	/**
	 * Matching products for the engine 4 filter that drives this page.
	 */
	private static function count_for( $filter ) {
		if ( ! class_exists( 'WooBeWoo_PF_Query' ) || ! class_exists( 'WooBeWoo_PF_Engine' ) ) {
			return null;
		}
		$engine = WooBeWoo_PF_Frontend4::make_engine( $filter, WooBeWoo_PF_Query::from_request() );
		return (int) $engine->count();
	}

	/**
	 * [wpf-found-count]: "128 products" for the engine 4 filter shown on this page; nothing on other pages and
	 * with classic filters.
	 */
	public function foundCountShortcode( $atts = array() ) {
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return '';
		}
		$filter = WooBeWoo_PF_Frontend4::active_filter();
		if ( ! $filter || empty( $filter['settings'] ) || ! WooBeWoo_PF_Frontend4::is_engine4( $filter['id'], $filter['settings'] ) ) {
			return '';
		}
		$n = self::count_for( $filter );
		if ( null === $n ) {
			return '';
		}
		$this->enqueue( $filter );
		return self::found_html( $filter['settings'], $n, 'wpf-found' );
	}

	/* ------------------------------------------------------------------ storefront */

	/**
	 * Settings the storefront script needs for one filter (public, no secrets).
	 */
	private function config( $settings ) {
		$url = self::results_url( $settings );
		$btn = trim( self::setting( $settings, 'results_page_button' ) );
		return array(
			'url'      => $url,
			'button'   => '' !== $btn ? $btn : __( 'Show products', 'woo-product-filter' ),
			'pretty'   => '' !== $url && self::pretty_target( $settings ),
			'skeleton' => self::on( self::setting( $settings, 'loading_skeleton' ) ),
			'perPage'  => array( 'def' => self::store_per_page() ),
		);
	}

	/**
	 * `woobewoo_pf_frontend4_before_form`: the script's config for this filter and, with "Show how many products
	 * match" on, the live "128 products" line.
	 */
	public function beforeForm( $html, $filter, $query ) {
		if ( ! is_array( $filter ) || empty( $filter['settings'] ) || ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return $html;
		}
		if ( ! WooBeWoo_PF_Frontend4::is_engine4( $filter['id'], $filter['settings'] ) ) {
			return $html;
		}
		$s  = $filter['settings'];
		$h  = '<span class="wpf-results-cfg" hidden data-wpf-results="' . esc_attr( wp_json_encode( $this->config( $s ) ) ) . '"></span>';
		if ( self::on( self::setting( $s, 'show_found_count' ) ) ) {
			$engine = WooBeWoo_PF_Frontend4::make_engine( $filter, $query instanceof WooBeWoo_PF_Query ? $query : null );
			$h     .= self::found_html( $s, (int) $engine->count(), 'wpf-found wpf4__found', 'p' );
		}
		return $html . $h;
	}

	/**
	 * `woobewoo_pf_frontend4_enqueue` (and the shortcode): the module's storefront script and style, after the
	 * engine's own; nothing is printed when the engine 4 assets are not on the page.
	 */
	public function enqueue( $filter = null ) {
		if ( self::$assetsDone ) {
			return;
		}
		self::$assetsDone = true;
		$path = $this->getModPath();
		wp_enqueue_style( 'woobewoo-pf-results', $path . 'css/frontend.results.css', array( 'woobewoo-pf-frontend-4' ), WPF_VERSION );
		wp_enqueue_script( 'woobewoo-pf-results', $path . 'js/frontend.results.js', array( 'woobewoo-pf-frontend-4' ), WPF_VERSION, true );
	}

	/* ------------------------------------------------------------------ builder */

	/**
	 * Product categories for the results page picker: id => name (children under their parent).
	 */
	private static function category_options() {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 300,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		$byParent = array();
		foreach ( $terms as $t ) {
			$byParent[ (int) $t->parent ][] = $t;
		}
		$out  = array();
		$walk = function ( $parent, $depth ) use ( &$walk, &$out, $byParent ) {
			if ( empty( $byParent[ $parent ] ) || $depth > 5 ) {
				return;
			}
			foreach ( $byParent[ $parent ] as $t ) {
				$out[ (int) $t->term_id ] = str_repeat( '— ', $depth ) . $t->name;
				$walk( (int) $t->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $out;
	}

	/**
	 * `woobewoo_pf_admin_schema`: the builder controls (plain words) and the Advanced rows of this module.
	 */
	public function adminSchema( $schema, $settings = array() ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		$pages = array(
			''     => __( 'No, filter this page', 'woo-product-filter' ),
			'shop' => __( 'The Shop page', 'woo-product-filter' ),
		);
		foreach ( self::category_options() as $id => $label ) {
			/* translators: %s: product category name */
			$pages[ 'cat:' . $id ] = sprintf( __( 'Category: %s', 'woo-product-filter' ), $label );
		}
		$controls = array(
			array(
				'id'          => 'results_page',
				'engine4Only' => true,
				'group'       => 'where',
				'scope'       => 'main',
				'key'         => 'results_page',
				'type'        => 'select',
				'label'       => __( 'Send shoppers to another page', 'woo-product-filter' ),
				'help'        => __( 'For a filter on the home page or a landing page: the picks open that page with the matching products.', 'woo-product-filter' ),
				'options'     => $pages,
				'default'     => '',
			),
			array(
				'id'          => 'results_page_url',
				'engine4Only' => true,
				'group'       => 'where',
				'scope'       => 'main',
				'key'         => 'results_page_url',
				'type'        => 'text',
				'label'       => __( '… or any address', 'woo-product-filter' ),
				'help'        => __( 'A full web address. It wins over the page picked above.', 'woo-product-filter' ),
				'mono'        => true,
			),
			array(
				'id'          => 'results_page_button',
				'engine4Only' => true,
				'group'       => 'where',
				'scope'       => 'main',
				'key'         => 'results_page_button',
				'type'        => 'text',
				'label'       => __( 'The button says then', 'woo-product-filter' ),
				'default'     => __( 'Show products', 'woo-product-filter' ),
			),
			array(
				'id'          => 'show_found_count',
				'engine4Only' => true,
				'group'       => 'basics',
				'scope'       => 'main',
				'key'         => 'show_found_count',
				'type'        => 'switch',
				'label'       => __( 'Show how many products match', 'woo-product-filter' ),
				'help'        => __( '"128 products" at the top of the filter, updated with every pick. The [wpf-found-count] shortcode prints the same anywhere on the page.', 'woo-product-filter' ),
			),
			array(
				'id'          => 'loading_skeleton',
				'engine4Only' => true,
				'group'       => 'look',
				'scope'       => 'main',
				'key'         => 'loading_skeleton',
				'type'        => 'switch',
				'label'       => __( 'Grey placeholder cards while loading', 'woo-product-filter' ),
				'help'        => __( 'The product cards turn into pulsing grey blocks until the new results arrive.', 'woo-product-filter' ),
			),
		);
		$schema['controls'] = array_merge( isset( $schema['controls'] ) && is_array( $schema['controls'] ) ? $schema['controls'] : array(), $controls );
		if ( ! isset( $schema['mainKeys'] ) || ! is_array( $schema['mainKeys'] ) ) {
			$schema['mainKeys'] = array();
		}
		$schema['mainKeys']['found_count_text']     = array(
			'engine4Only' => true,
			'label'       => __( 'The "products match" text says', 'woo-product-filter' ),
			'help'        => __( '%d is the number, for example "%d items found". Empty: "%d products".', 'woo-product-filter' ),
			'type'        => 'text',
			'section'     => 'buttons',
			'default'     => '',
		);
		$schema['mainKeys']['found_count_text_one'] = array(
			'engine4Only' => true,
			'label'       => __( 'The same text for one product', 'woo-product-filter' ),
			'help'        => __( 'Empty: "%d product".', 'woo-product-filter' ),
			'type'        => 'text',
			'section'     => 'buttons',
			'default'     => '',
		);
		return $schema;
	}
}
