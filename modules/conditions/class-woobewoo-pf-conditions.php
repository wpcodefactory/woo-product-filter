<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Conditions Class
 *
 * "Show this block only when..." rules for the 4.0 storefront engine (Free):
 *   - after a pick in another block (dependent blocks),
 *   - only on some pages (categories, brands, the shop page, search results; decided server side),
 *   - hide when fewer than N products match,
 *   - hide when the block has only one choice.
 * The page rule removes the block from the engine, the view and the query (a shared link cannot re-enable it);
 * the other three are evaluated in the browser from every facets response (js/frontend-4/conditions.js).
 * The classic 3.x engine ignores these settings.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Conditions extends WooBeWoo_PF_Module {

	const KEY_PARENT = 'f_cond_parent';
	const KEY_PAGES  = 'f_cond_pages';
	const KEY_MIN    = 'f_cond_min_results';
	const KEY_SINGLE = 'f_cond_single';

	/** @var array|null page context of the request (taxonomy, term_id, search, base), once the engine knows it */
	private static $ctx = null;
	/** @var WooBeWoo_PF_Query[] query objects built in this request; a hidden block's values are removed from them */
	private static $queries = array();

	public function init() {
		parent::init();
		// Builder: the three controls under the block's Advanced group, plus a small script that keeps the list of blocks fresh.
		add_filter( 'woobewoo_pf_admin_schema', array( $this, 'adminSchema' ), 10, 2 );
		add_action( 'woobewoo_pf_builder_assets', array( $this, 'builderAssets' ), 10, 2 );
		// Storefront: the page context and the query of the request, then the page rule on the block list.
		add_filter( 'woobewoo_pf_frontend4_context', array( $this, 'rememberContext' ), 99, 2 );
		add_filter( 'woobewoo_pf_frontend4_engine_context', array( $this, 'rememberEngineContext' ), 99, 3 );
		add_filter( 'woobewoo_pf_query_from_params', array( $this, 'rememberQuery' ), 99, 2 );
		add_filter( 'woobewoo_pf_frontend4_blocks', array( $this, 'pageRule' ), 20, 2 );
		add_filter( 'woobewoo_pf_block_attrs', array( $this, 'blockAttrs' ), 10, 3 );
		add_filter( 'woobewoo_pf_frontend4_config_conditions', array( $this, 'config' ), 10, 2 );
		add_action( 'woobewoo_pf_frontend4_enqueue', array( $this, 'enqueue' ) );
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * The rules of a block (the builder's block array), sanitized.
	 *
	 * @return array parent (uniqId or key, '' = always), pages (tokens shop|search|cat:ID|brand:ID), min (int), single (bool)
	 */
	public static function rules( $block ) {
		$s   = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$out = array(
			'parent' => '',
			'pages'  => array(),
			'min'    => 0,
			'single' => false,
		);
		if ( isset( $s[ self::KEY_PARENT ] ) && is_scalar( $s[ self::KEY_PARENT ] ) ) {
			$out['parent'] = sanitize_key( (string) $s[ self::KEY_PARENT ] );
		}
		if ( isset( $s[ self::KEY_PAGES ] ) ) {
			$raw = is_array( $s[ self::KEY_PAGES ] ) ? $s[ self::KEY_PAGES ] : explode( ',', (string) $s[ self::KEY_PAGES ] );
			foreach ( $raw as $token ) {
				$token = strtolower( trim( (string) $token ) );
				if ( preg_match( '/^(shop|search|cat:\d+|brand:\d+)$/', $token ) ) {
					$out['pages'][] = $token;
				}
			}
			$out['pages'] = array_values( array_unique( $out['pages'] ) );
		}
		if ( isset( $s[ self::KEY_MIN ] ) && is_scalar( $s[ self::KEY_MIN ] ) ) {
			$out['min'] = max( 0, (int) $s[ self::KEY_MIN ] );
		}
		if ( isset( $s[ self::KEY_SINGLE ] ) ) {
			$v             = $s[ self::KEY_SINGLE ];
			$out['single'] = is_string( $v ) ? ! in_array( strtolower( trim( $v ) ), array( '', '0', 'no', 'false', 'off' ), true ) : (bool) $v;
		}
		return $out;
	}

	/**
	 * Does a block carry any rule the storefront script evaluates (parent, min, single)?
	 */
	private static function has_js_rule( $r ) {
		return '' !== $r['parent'] || $r['min'] > 0 || $r['single'];
	}

	/**
	 * The key of the parent block named by a rule (uniqId or key), or '' when it is not among the blocks.
	 */
	private static function parent_key( $parent, $blocks, $self ) {
		if ( '' === $parent ) {
			return '';
		}
		foreach ( $blocks as $k => $b ) {
			if ( $k === $self ) {
				continue;
			}
			if ( $parent === $b['uniqId'] || $parent === $b['key'] ) {
				return $b['key'];
			}
		}
		return '';
	}

	/* ------------------------------------------------------------------ builder */

	/**
	 * The four controls, rendered by the builder under the block's Advanced group (blockKeys rows).
	 */
	public function adminSchema( $schema, $settings ) {
		if ( ! is_array( $schema ) ) {
			return $schema;
		}
		$blocks = array( '' => __( 'Always', 'woo-product-filter' ) );
		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) && class_exists( 'WooBeWoo_PF_Engine' ) && is_array( $settings ) ) {
			foreach ( WooBeWoo_PF_Frontend4::blocks( $settings ) as $b ) {
				$ref = $b['uniqId'] ? $b['uniqId'] : $b['key'];
				if ( '' !== $ref ) {
					$blocks[ $ref ] = $b['title'];
				}
			}
		}
		if ( ! isset( $schema['blockKeys'] ) || ! is_array( $schema['blockKeys'] ) ) {
			$schema['blockKeys'] = array();
		}
		$schema['blockKeys'][ self::KEY_PARENT ] = array(
			'label'       => __( 'Show this block only after a pick in', 'woo-product-filter' ),
			'help'        => __( 'The block stays hidden until shoppers pick something in that block. Clearing the pick hides it again.', 'woo-product-filter' ),
			'type'        => 'select',
			'options'     => $blocks,
			'default'     => '',
			'engine4Only' => true,
		);
		$schema['blockKeys'][ self::KEY_PAGES ]  = array(
			'label'       => __( 'Show only on', 'woo-product-filter' ),
			'help'        => __( 'Nothing ticked: every page. A category includes its sub-categories.', 'woo-product-filter' ),
			'type'        => 'multi',
			'options'     => self::pageOptions(),
			'default'     => '',
			'engine4Only' => true,
		);
		$schema['blockKeys'][ self::KEY_MIN ]    = array(
			'label'       => __( 'Hide when fewer than this many products match', 'woo-product-filter' ),
			'help'        => __( '0 never hides it.', 'woo-product-filter' ),
			'type'        => 'number',
			'default'     => '0',
			'engine4Only' => true,
		);
		$schema['blockKeys'][ self::KEY_SINGLE ] = array(
			'label'       => __( 'Hide when it has only one choice', 'woo-product-filter' ),
			'help'        => __( 'One size left to pick is no choice at all.', 'woo-product-filter' ),
			'type'        => 'switch',
			'default'     => '',
			'engine4Only' => true,
		);
		return $schema;
	}

	/**
	 * Options of "Show only on": the shop page, search results, every product category (as a tree) and every brand.
	 */
	public static function pageOptions() {
		$out = array(
			'shop'   => __( 'The shop page', 'woo-product-filter' ),
			'search' => __( 'Search results', 'woo-product-filter' ),
		);
		$cats = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		if ( ! is_wp_error( $cats ) && $cats ) {
			foreach ( self::tree( $cats ) as $t ) {
				/* translators: %s: category name */
				$out[ 'cat:' . $t->term_id ] = str_repeat( '— ', (int) $t->depth ) . sprintf( __( 'Category: %s', 'woo-product-filter' ), $t->name );
			}
		}
		$brandTax = taxonomy_exists( 'product_brand' ) ? 'product_brand' : ( taxonomy_exists( 'pwb-brand' ) ? 'pwb-brand' : '' );
		if ( $brandTax ) {
			$brands = get_terms(
				array(
					'taxonomy'   => $brandTax,
					'hide_empty' => false,
					'number'     => 500,
				)
			);
			if ( ! is_wp_error( $brands ) && $brands ) {
				foreach ( $brands as $t ) {
					/* translators: %s: brand name */
					$out[ 'brand:' . $t->term_id ] = sprintf( __( 'Brand: %s', 'woo-product-filter' ), $t->name );
				}
			}
		}
		return $out;
	}

	/**
	 * Flatten a term list depth first; sets ->depth. Orphans (parent beyond the limit) come flat at the end.
	 */
	private static function tree( $terms, $parent = 0, $depth = 0 ) {
		$out = array();
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === (int) $parent ) {
				$t->depth = $depth;
				$out[]    = $t;
				$out      = array_merge( $out, self::tree( $terms, $t->term_id, $depth + 1 ) );
			}
		}
		if ( 0 === $depth && count( $out ) < count( $terms ) ) {
			$ids = wp_list_pluck( $out, 'term_id' );
			foreach ( $terms as $t ) {
				if ( ! in_array( $t->term_id, $ids, true ) ) {
					$t->depth = 0;
					$out[]    = $t;
				}
			}
		}
		return $out;
	}

	/**
	 * Builder script: lists the filter's blocks by their titles in the "after a pick in" select, keeps the list fresh
	 * after a save and stops a block from depending on itself.
	 */
	public function builderAssets( $settings, $idIn ) {
		$frame = WooBeWoo_PF_Frame::_();
		$frame->addScript( 'woobewoo-pf-admin-conditions', $this->getModPath() . 'js/admin.conditions.js', array( 'woobewoo-pf-admin-builder' ), false, true );
		$frame->addJSVar(
			'woobewoo-pf-admin-conditions',
			'wpfConditionsData',
			array(
				'keys'      => array( self::KEY_PARENT, self::KEY_PAGES, self::KEY_MIN, self::KEY_SINGLE ),
				'parentKey' => self::KEY_PARENT,
				'always'    => __( 'Always', 'woo-product-filter' ),
			)
		);
	}

	/* ------------------------------------------------------------------ page rule (server side) */

	public function rememberContext( $ctx, $settings ) {
		if ( is_array( $ctx ) ) {
			self::$ctx = $ctx;
		}
		return $ctx;
	}

	public function rememberEngineContext( $ectx, $filter, $ctx ) {
		if ( is_array( $ctx ) ) {
			self::$ctx = $ctx;
		}
		return $ectx;
	}

	public function rememberQuery( $q, $vars ) {
		if ( $q instanceof WooBeWoo_PF_Query ) {
			self::$queries[] = $q;
			if ( count( self::$queries ) > 20 ) {
				array_shift( self::$queries );
			}
		}
		return $q;
	}

	/**
	 * Blocks whose "Show only on" does not match the page are left out: not rendered, no SQL, and their values are
	 * dropped from the request's query so a shared link cannot bring them back.
	 */
	public function pageRule( $blocks, $settings ) {
		if ( null === self::$ctx || ! is_array( $blocks ) ) {
			return $blocks; // no page yet (the builder, WP-CLI): every block stays
		}
		foreach ( $blocks as $k => $b ) {
			$r = self::rules( $b['block'] );
			if ( ! $r['pages'] || self::matches( $r['pages'], self::$ctx ) ) {
				continue;
			}
			unset( $blocks[ $k ] );
			foreach ( self::$queries as $q ) {
				self::forget( $q, $b );
			}
		}
		return $blocks;
	}

	/**
	 * Does the page (taxonomy term, search, the shop page) match one of the tokens?
	 */
	public static function matches( $pages, $ctx ) {
		$tax  = isset( $ctx['taxonomy'] ) ? (string) $ctx['taxonomy'] : '';
		$term = isset( $ctx['term_id'] ) ? (int) $ctx['term_id'] : 0;
		foreach ( $pages as $p ) {
			if ( 'search' === $p ) {
				if ( ! empty( $ctx['search'] ) ) {
					return true;
				}
			} elseif ( 'shop' === $p ) {
				if ( self::is_shop( $ctx ) ) {
					return true;
				}
			} elseif ( 0 === strpos( $p, 'cat:' ) ) {
				$id = (int) substr( $p, 4 );
				if ( 'product_cat' === $tax && $term && ( $id === $term || term_is_ancestor_of( $id, $term, 'product_cat' ) ) ) {
					return true;
				}
			} elseif ( 0 === strpos( $p, 'brand:' ) ) {
				if ( $term && in_array( $tax, array( 'product_brand', 'pwb-brand' ), true ) && (int) substr( $p, 6 ) === $term ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The shop page: is_shop() on the page itself; on the JSON endpoint the base URL the page sent is compared with the
	 * shop page link (both without query string and trailing slash).
	 */
	private static function is_shop( $ctx ) {
		if ( ! empty( $ctx['taxonomy'] ) || ! empty( $ctx['search'] ) ) {
			return false;
		}
		if ( ! wp_doing_ajax() && function_exists( 'is_shop' ) && is_shop() ) {
			return true;
		}
		if ( empty( $ctx['base'] ) || ! function_exists( 'wc_get_page_permalink' ) ) {
			return false;
		}
		$norm = function ( $url ) {
			$url = strtolower( (string) $url );
			$url = preg_replace( '/[?#].*$/', '', $url );
			return untrailingslashit( str_replace( array( 'http://', 'https://' ), '', $url ) );
		};
		$shop = wc_get_page_permalink( 'shop' );
		return $shop && $norm( $shop ) === $norm( $ctx['base'] );
	}

	/**
	 * Remove a block's values from a query object.
	 */
	private static function forget( $q, $b ) {
		$key = $b['key'];
		switch ( $b['kind'] ) {
			case 'terms':
			case 'author':
				unset( $q->terms[ $key ] );
				break;
			case 'price':
				$q->price = null;
				break;
			case 'stock':
				$q->stock = null;
				break;
			case 'sale':
				$q->sale = false;
				break;
			case 'featured':
				$q->featured = false;
				break;
			case 'rating':
				$q->rating = null;
				break;
			case 'sort':
				$q->sort = null;
				break;
			case 'meta':
				unset( $q->meta[ $key ] );
				break;
			case 'text':
				unset( $q->text[ $key ] );
				break;
			case 'q':
				$q->q = '';
				break;
			default:
				unset( $q->extra[ $key ] );
		}
	}

	/**
	 * Is anything picked in a block (from the query)?
	 */
	private static function query_has( $q, $b ) {
		if ( ! $q instanceof WooBeWoo_PF_Query ) {
			return false;
		}
		$key = $b['key'];
		switch ( $b['kind'] ) {
			case 'terms':
			case 'author':
				return ! empty( $q->terms[ $key ] );
			case 'price':
				return is_array( $q->price );
			case 'stock':
				return ! empty( $q->stock );
			case 'sale':
				return (bool) $q->sale;
			case 'featured':
				return (bool) $q->featured;
			case 'rating':
				return ! empty( $q->rating );
			case 'sort':
				return ! empty( $q->sort ) && 'default' !== $q->sort;
			case 'meta':
				return ! empty( $q->meta[ $key ] );
			case 'text':
				return isset( $q->text[ $key ] ) && '' !== (string) $q->text[ $key ];
			case 'q':
				return '' !== (string) $q->q;
		}
		return ! empty( $q->extra[ $key ] );
	}

	/**
	 * How many choices a block offers from the facets (values with products), or null when the block has no countable choices.
	 */
	private static function choices( $b, $facets ) {
		if ( in_array( $b['kind'], array( 'sale', 'featured', 'sort', 'q', 'text' ), true ) ) {
			return null;
		}
		$f = isset( $facets[ $b['key'] ] ) && is_array( $facets[ $b['key'] ] ) ? $facets[ $b['key'] ] : null;
		if ( ! $f ) {
			return null;
		}
		if ( isset( $f['counts'] ) && is_array( $f['counts'] ) ) {
			$n = 0;
			foreach ( $f['counts'] as $c ) {
				if ( (int) $c > 0 ) {
					$n++;
				}
			}
			return $n;
		}
		if ( in_array( $b['kind'], array( 'price', 'meta' ), true ) && isset( $f['min'], $f['max'] ) ) {
			return (float) $f['max'] > (float) $f['min'] ? 2 : 1;
		}
		return null;
	}

	/* ------------------------------------------------------------------ storefront markup */

	/**
	 * data-cond-* attributes on the fieldset; data-cond-start="hidden" when the block starts hidden (the stylesheet
	 * hides it until the script takes over, so a dependent block never flashes before the first evaluation).
	 */
	public function blockAttrs( $attrs, $b, $view ) {
		$r = self::rules( $b['block'] );
		if ( ! self::has_js_rule( $r ) ) {
			return $attrs;
		}
		$blocks = WooBeWoo_PF_Frontend4::blocks( $view->settings );
		$parent = self::parent_key( $r['parent'], $blocks, $b['key'] );
		if ( '' !== $parent ) {
			$attrs['cond-parent'] = $parent;
		}
		if ( $r['min'] > 0 ) {
			$attrs['cond-min'] = (string) $r['min'];
		}
		if ( $r['single'] ) {
			$attrs['cond-single'] = '1';
		}
		if ( self::start_hidden( $b, $blocks, $view, 0 ) ) {
			$attrs['cond-start'] = 'hidden';
		}
		return $attrs;
	}

	/**
	 * Initial state of a block from the request: the parent has no pick (or starts hidden itself), too few products,
	 * or one choice only.
	 */
	private static function start_hidden( $b, $blocks, $view, $depth ) {
		if ( $depth > 5 ) {
			return false;
		}
		$r      = self::rules( $b['block'] );
		$parent = self::parent_key( $r['parent'], $blocks, $b['key'] );
		if ( '' !== $parent && isset( $blocks[ $parent ] ) ) {
			if ( ! self::query_has( $view->query, $blocks[ $parent ] ) || self::start_hidden( $blocks[ $parent ], $blocks, $view, $depth + 1 ) ) {
				return true;
			}
		}
		if ( $r['min'] > 0 && $view->engine instanceof WooBeWoo_PF_Engine && $view->engine->count() < $r['min'] ) {
			return true;
		}
		if ( $r['single'] ) {
			$n = self::choices( $b, is_array( $view->facets ) ? $view->facets : array() );
			if ( null !== $n && $n <= 1 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rules for the script: block key => { parent, min, single }.
	 */
	public function config( $conditions, $view ) {
		$out    = is_array( $conditions ) ? $conditions : array();
		$blocks = WooBeWoo_PF_Frontend4::blocks( $view->settings );
		foreach ( $blocks as $k => $b ) {
			$r = self::rules( $b['block'] );
			if ( ! self::has_js_rule( $r ) ) {
				continue;
			}
			$out[ $k ] = array(
				'parent' => self::parent_key( $r['parent'], $blocks, $k ),
				'min'    => $r['min'],
				'single' => $r['single'],
			);
		}
		return $out;
	}

	public function enqueue( $filter ) {
		$ver = defined( 'WPF_VERSION' ) ? WPF_VERSION : '4.0.0';
		wp_enqueue_style( 'woobewoo-pf-frontend-4-conditions', $this->getModPath() . 'css/frontend-4/conditions.css', array( 'woobewoo-pf-frontend-4' ), $ver );
		wp_enqueue_script( 'woobewoo-pf-frontend-4-conditions', $this->getModPath() . 'js/frontend-4/conditions.js', array( 'woobewoo-pf-frontend-4' ), $ver, true );
	}
}
