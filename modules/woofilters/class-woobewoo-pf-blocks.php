<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Blocks Class
 *
 * Registry of block kinds for the 4.0 storefront engine. A module registers a block once (id, kind, URL key, SQL,
 * counts, markup, builder card and controls) and the query, the engine, the storefront view, the builder schema,
 * the filter types list and WP-CLI read it from here. The built-in blocks keep their own code paths: the registry
 * only serves the `default:` branches of their switch statements, so a filter made of built-in blocks renders and
 * queries exactly as before.
 *
 * Register on the `woobewoo_pf_register_blocks` action (fired once, lazily, after plugins_loaded):
 *
 *   add_action( 'woobewoo_pf_register_blocks', function () {
 *       WooBeWoo_PF_Blocks::register( 'wpfExample', array( ... ) );          // a full block (sql + facets + render)
 *       WooBeWoo_PF_Blocks::register_source( 'wpfMaterial', array( ... ) );  // values and product ids from callbacks
 *   } );
 *
 * The full argument list is documented in docs/BLOCKS.md.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Blocks {

	/** Kinds the engine, view and query handle themselves; a registered block may not reuse them. */
	const BUILTIN_KINDS = array( 'terms', 'author', 'price', 'stock', 'sale', 'featured', 'rating', 'sort', 'q', 'text', 'meta' );

	/** Block ids the engine, view and query handle themselves. */
	const BUILTIN_IDS = array( 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfVendors', 'wpfPrice', 'wpfPriceRange', 'wpfInStock', 'wpfOnSale', 'wpfFeatured', 'wpfRating', 'wpfSortBy', 'wpfSearchText', 'wpfSearchNumber', 'wpfCustomField', 'wpfAttribute' );

	/** @var array id => definition */
	private static $blocks = array();
	/** @var bool */
	private static $booted = false;
	/** @var bool the register action fired */
	private static $fired = false;
	/** @var array source values per block id for the request */
	private static $values = array();

	/* ------------------------------------------------------------------ boot */

	/**
	 * Hooks the registry into the index (meta keys) and the builder (card flags). Safe to call more than once.
	 *
	 * @since 4.0.0
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_filter( 'woobewoo_pf_index_meta_keys', array( __CLASS__, 'index_meta_keys' ), 5 );
		add_filter( 'woobewoo_pf_builder_block_cards', array( __CLASS__, 'builder_cards' ), 5, 2 );
	}

	/**
	 * Fires `woobewoo_pf_register_blocks` once, the first time the registry is read after plugins_loaded.
	 */
	private static function ensure() {
		if ( self::$fired || ! did_action( 'plugins_loaded' ) ) {
			return;
		}
		self::$fired = true;
		do_action( 'woobewoo_pf_register_blocks' );
	}

	/* ------------------------------------------------------------------ registration */

	/**
	 * Register a block kind. Returns false (and does nothing) for a bad id, a built-in id or kind, or a reserved key.
	 *
	 * @since 4.0.0
	 *
	 * @param string $id   Block id, for example 'wpfMaterial' (letters, digits and _; not a built-in id).
	 * @param array  $args See docs/BLOCKS.md: kind, label, slug, unique, content_type, pro, card, displays, controls,
	 *                     defaults, question, finder, single, key, match, parse, param, sql, facets, index, render, chip, cli.
	 * @return bool
	 */
	public static function register( $id, $args ) {
		$id = (string) $id;
		if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{1,60}$/', $id ) || in_array( $id, self::BUILTIN_IDS, true ) || ! is_array( $args ) ) {
			return false;
		}
		$kind = isset( $args['kind'] ) ? strtolower( (string) $args['kind'] ) : '';
		if ( ! preg_match( '/^[a-z][a-z0-9_]{0,40}$/', $kind ) || in_array( $kind, self::BUILTIN_KINDS, true ) ) {
			return false;
		}
		$key = isset( $args['key'] ) ? $args['key'] : $kind;
		if ( ! is_callable( $key ) ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || self::reserved_key( $key ) ) {
				return false;
			}
		}
		$displays = array();
		$known    = class_exists( 'WooBeWoo_PF_Admin_Schema' ) ? array_keys( WooBeWoo_PF_Admin_Schema::displayTypes() ) : array( 'list', 'chips', 'squares', 'swatches', 'dropdown', 'slider', 'toggle', 'range_list' );
		foreach ( (array) ( isset( $args['displays'] ) ? $args['displays'] : array() ) as $d ) {
			$d = sanitize_key( (string) $d );
			if ( in_array( $d, $known, true ) && ! in_array( $d, $displays, true ) ) {
				$displays[] = $d;
			}
		}
		if ( ! $displays ) {
			$displays = array( 'list', 'chips', 'dropdown' );
		}
		$card = isset( $args['card'] ) && is_array( $args['card'] ) ? $args['card'] : array();
		$def  = array(
			'id'           => $id,
			'kind'         => $kind,
			'label'        => isset( $args['label'] ) ? (string) $args['label'] : $id,
			'slug'         => isset( $args['slug'] ) ? sanitize_title( (string) $args['slug'] ) : $kind,
			'unique'       => ! isset( $args['unique'] ) || (bool) $args['unique'],
			'content_type' => isset( $args['content_type'] ) ? sanitize_key( (string) $args['content_type'] ) : 'meta',
			'pro'          => ! empty( $args['pro'] ),
			'card'         => array(
				'icon'  => isset( $card['icon'] ) ? (string) $card['icon'] : '',
				'group' => isset( $card['group'] ) && 'basics' === $card['group'] ? 'basics' : 'product',
				'spec'  => isset( $card['spec'] ) ? (string) $card['spec'] : '',
				'href'  => isset( $card['href'] ) ? (string) $card['href'] : '',
			),
			'displays'     => $displays,
			'controls'     => isset( $args['controls'] ) && is_array( $args['controls'] ) ? array_values( $args['controls'] ) : array(),
			'defaults'     => isset( $args['defaults'] ) && is_array( $args['defaults'] ) ? $args['defaults'] : array(),
			'question'     => isset( $args['question'] ) ? (string) $args['question'] : '',
			'finder'       => ! empty( $args['finder'] ),
			'single'       => ! empty( $args['single'] ),
			'key'          => $key,
			'match'        => isset( $args['match'] ) && is_callable( $args['match'] ) ? $args['match'] : null,
			'parse'        => isset( $args['parse'] ) && is_callable( $args['parse'] ) ? $args['parse'] : null,
			'param'        => isset( $args['param'] ) && is_callable( $args['param'] ) ? $args['param'] : null,
			'sql'          => isset( $args['sql'] ) && is_callable( $args['sql'] ) ? $args['sql'] : null,
			'facets'       => isset( $args['facets'] ) && is_callable( $args['facets'] ) ? $args['facets'] : null,
			'index'        => isset( $args['index'] ) && is_array( $args['index'] ) ? $args['index'] : array(),
			'render'       => isset( $args['render'] ) && is_callable( $args['render'] ) ? $args['render'] : null,
			'chip'         => isset( $args['chip'] ) && is_callable( $args['chip'] ) ? $args['chip'] : null,
			'cli'          => isset( $args['cli'] ) ? sanitize_key( (string) $args['cli'] ) : '',
			'source'       => null,
		);
		self::$blocks[ $id ] = $def;
		return true;
	}

	/**
	 * Register a terms-like block whose values and product ids come from callbacks instead of a taxonomy:
	 *   'values' => function ( $engine ) { return array( 'wool' => array( 'label' => 'Wool', 'count' => null ) ); }
	 *   'ids'    => function ( $values, $engine ) { return array( product ids having one of $values ); }
	 * The registry supplies `sql` (product id IN list), `facets` (a count per value over the narrowed set, cached with
	 * the engine's cache key) and `render` (tick boxes, chips, squares or a dropdown). Every other argument of
	 * register() is accepted too.
	 *
	 * @since 4.0.0
	 * @return bool
	 */
	public static function register_source( $id, $args ) {
		if ( ! is_array( $args ) || empty( $args['values'] ) || ! is_callable( $args['values'] ) || empty( $args['ids'] ) || ! is_callable( $args['ids'] ) ) {
			return false;
		}
		$args['content_type'] = isset( $args['content_type'] ) ? $args['content_type'] : 'meta';
		foreach ( array( 'sql', 'facets', 'render' ) as $k ) {
			unset( $args[ $k ] );
		}
		if ( ! self::register( $id, $args ) ) {
			return false;
		}
		self::$blocks[ $id ]['source'] = array(
			'values' => $args['values'],
			'ids'    => $args['ids'],
		);
		if ( empty( self::$blocks[ $id ]['chip'] ) ) {
			self::$blocks[ $id ]['chip'] = array( __CLASS__, 'source_chip' );
		}
		return true;
	}

	/**
	 * Remove a registered block.
	 *
	 * @since 4.0.0
	 */
	public static function unregister( $id ) {
		unset( self::$blocks[ (string) $id ] );
	}

	/* ------------------------------------------------------------------ lookups */

	/**
	 * The definition of a registered block id, or null.
	 *
	 * @since 4.0.0
	 */
	public static function get( $id ) {
		self::ensure();
		$id = (string) $id;
		return isset( self::$blocks[ $id ] ) ? self::$blocks[ $id ] : null;
	}

	/**
	 * Every registered block: id => definition.
	 *
	 * @since 4.0.0
	 */
	public static function all() {
		self::ensure();
		return self::$blocks;
	}

	/**
	 * The registered kinds (unique, in registration order).
	 *
	 * @since 4.0.0
	 */
	public static function kinds() {
		$kinds = array();
		foreach ( self::all() as $def ) {
			$kinds[ $def['kind'] ] = 1;
		}
		return array_keys( $kinds );
	}

	/**
	 * The blocks of one kind: id => definition.
	 *
	 * @since 4.0.0
	 */
	public static function by_kind( $kind ) {
		$out = array();
		foreach ( self::all() as $id => $def ) {
			if ( $def['kind'] === $kind ) {
				$out[ $id ] = $def;
			}
		}
		return $out;
	}

	/**
	 * The block that owns a URL key (wpf_<key>): a block whose static `key` equals it, or whose `match` callback
	 * accepts it. Null when no registered block claims the key.
	 *
	 * @since 4.0.0
	 */
	public static function by_key( $name ) {
		$name = (string) $name;
		if ( '' === $name ) {
			return null;
		}
		foreach ( self::all() as $def ) {
			if ( is_string( $def['key'] ) && $def['key'] === $name ) {
				return $def;
			}
			if ( $def['match'] && call_user_func( $def['match'], $name, $def ) ) {
				return $def;
			}
		}
		return null;
	}

	/**
	 * Is this block id registered?
	 *
	 * @since 4.0.0
	 */
	public static function is_registered( $id ) {
		return null !== self::get( $id );
	}

	/**
	 * The definition behind a normalized engine block (array with 'block' => builder block), or null.
	 *
	 * @since 4.0.0
	 */
	public static function of( $b ) {
		if ( ! is_array( $b ) || empty( $b['block']['id'] ) ) {
			return null;
		}
		return self::get( $b['block']['id'] );
	}

	/* ------------------------------------------------------------------ query contract */

	/**
	 * The URL key of a block (without wpf_): the static key, or the `key` callback's answer ('' = block inactive).
	 *
	 * @since 4.0.0
	 */
	public static function key_of( $def, $block ) {
		if ( is_callable( $def['key'] ) ) {
			$key = sanitize_key( (string) call_user_func( $def['key'], $block, $def ) );
			return self::reserved_key( $key ) ? '' : $key;
		}
		return (string) $def['key'];
	}

	/**
	 * Store a raw URL value on the query: the block's `parse`, or a comma list into $query->extra[ $key ].
	 *
	 * @since 4.0.0
	 */
	public static function parse( $def, $raw, $query, $key ) {
		if ( $def['parse'] ) {
			call_user_func( $def['parse'], $raw, $query, $key, $def );
			return;
		}
		$query->extra[ $key ] = array_values( array_unique( array_map( 'sanitize_text_field', WooBeWoo_PF_Query::split( $raw ) ) ) );
	}

	/**
	 * A stored value back into its URL form (the reverse of parse): the block's `param`, or the comma list.
	 *
	 * @since 4.0.0
	 */
	public static function param( $def, $value ) {
		if ( $def && $def['param'] ) {
			return (string) call_user_func( $def['param'], $value, $def );
		}
		if ( is_array( $value ) ) {
			$v = array_values( array_unique( array_filter( array_map( 'strval', $value ), 'strlen' ) ) );
			sort( $v );
			return implode( ',', $v );
		}
		return null === $value || false === $value ? '' : (string) $value;
	}

	/**
	 * Is anything stored under the registry values of a query?
	 *
	 * @since 4.0.0
	 */
	public static function extra_set( $extra ) {
		foreach ( (array) $extra as $v ) {
			if ( is_array( $v ) ? (bool) array_filter( array_map( 'strval', $v ), 'strlen' ) : ( null !== $v && false !== $v && '' !== (string) $v ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Static URL keys of every registered block (for WordPress query vars).
	 *
	 * @since 4.0.0
	 */
	public static function query_keys() {
		$keys = array();
		foreach ( self::all() as $def ) {
			if ( is_string( $def['key'] ) && '' !== $def['key'] ) {
				$keys[] = 'wpf_' . $def['key'];
			}
		}
		return $keys;
	}

	/* ------------------------------------------------------------------ engine */

	/**
	 * The selection of a block as SQL pieces: array( joins, tjoins, where ) or null when nothing is picked.
	 *
	 * @since 4.0.0
	 */
	public static function sql( $def, $b, $query, $engine ) {
		if ( $def['source'] ) {
			$sql = self::source_sql( $def, $b, $query, $engine );
		} elseif ( $def['sql'] ) {
			$sql = call_user_func( $def['sql'], $b, $query, $engine, $def );
		} else {
			return null;
		}
		if ( ! is_array( $sql ) ) {
			return null;
		}
		return array(
			'joins'  => isset( $sql['joins'] ) ? array_values( (array) $sql['joins'] ) : array(),
			'tjoins' => isset( $sql['tjoins'] ) ? array_values( (array) $sql['tjoins'] ) : array(),
			'where'  => isset( $sql['where'] ) ? array_values( (array) $sql['where'] ) : array(),
		);
	}

	/**
	 * Counts for one or more blocks of one kind: key => array( kind, counts, ... ). $skipKey is the block whose own
	 * selection is left out of the narrowed set ('' = none).
	 *
	 * @since 4.0.0
	 */
	public static function facets( $def, $blocks, $engine, $skipKey = '' ) {
		if ( $def['source'] ) {
			return self::source_facets( $blocks, $engine, $skipKey );
		}
		if ( ! $def['facets'] ) {
			return array();
		}
		$out = call_user_func( $def['facets'], $blocks, $engine, $skipKey, $def );
		return is_array( $out ) ? $out : array();
	}

	/**
	 * Numeric meta keys the registered blocks need in the index (filter callback, priority 5).
	 *
	 * @since 4.0.0
	 */
	public static function index_meta_keys( $keys ) {
		$keys = (array) $keys;
		foreach ( self::all() as $def ) {
			if ( ! empty( $def['index']['meta_keys'] ) ) {
				foreach ( (array) $def['index']['meta_keys'] as $k ) {
					$k = sanitize_key( (string) $k );
					if ( '' !== $k ) {
						$keys[] = $k;
					}
				}
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * The part of a registered block's settings that may change its SQL or counts (for the engine's blocks signature).
	 *
	 * @since 4.0.0
	 */
	public static function sig_row( $b ) {
		$row = array();
		if ( isset( $b['block']['settings'] ) && is_array( $b['block']['settings'] ) ) {
			foreach ( $b['block']['settings'] as $sk => $sv ) {
				if ( is_scalar( $sv ) ) {
					$row[] = $sk . '=' . $sv;
				}
			}
			sort( $row );
		}
		return $row;
	}

	/* ------------------------------------------------------------------ storefront */

	/**
	 * The inner markup of a block in a display type (the view wraps it). Empty when the block has no renderer.
	 *
	 * @since 4.0.0
	 */
	public static function render( $def, $b, $display, $mobile, $view ) {
		if ( $def['source'] ) {
			return self::source_render( $def, $b, $display, $mobile, $view );
		}
		if ( ! $def['render'] ) {
			return '';
		}
		return (string) call_user_func( $def['render'], $b, $display, $mobile, $view, $def );
	}

	/**
	 * Chip text for one picked value.
	 *
	 * @since 4.0.0
	 */
	public static function chip_label( $def, $value, $b = null ) {
		if ( $def && $def['chip'] ) {
			$label = call_user_func( $def['chip'], $value, $b, $def );
			if ( '' !== (string) $label ) {
				return (string) $label;
			}
		}
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	}

	/**
	 * The finder's default question for a block, '' when none was registered.
	 *
	 * @since 4.0.0
	 */
	public static function question( $def ) {
		return $def ? (string) $def['question'] : '';
	}

	/* ------------------------------------------------------------------ builder */

	/**
	 * Filter type row for WooBeWoo_PF_WooFilters_Model::getAllFilters().
	 *
	 * @since 4.0.0
	 */
	public static function filter_type( $def ) {
		$isPro = class_exists( 'WooBeWoo_PF_Frame' ) && WooBeWoo_PF_Frame::_()->isPro();
		return array(
			'name'         => $def['label'],
			'slug'         => $def['slug'],
			'enabled'      => ! $def['pro'] || $isPro,
			'unique'       => $def['unique'],
			'content_type' => $def['content_type'],
			'registry'     => true,
		);
	}

	/**
	 * "Add a block" cards for WooBeWoo_PF_Admin_Schema::blockCards(): id => array( label, icon ).
	 *
	 * @since 4.0.0
	 */
	public static function cards() {
		$cards = array();
		foreach ( self::all() as $id => $def ) {
			$cards[ $id ] = array(
				'label' => $def['label'],
				'icon'  => $def['card']['icon'],
			);
		}
		return $cards;
	}

	/**
	 * Marks the registry cards in the builder list: "· Pro" (linked to Upgrade) when the block is Pro and Pro is absent,
	 * plus the card's group and spec line (filter callback, priority 5).
	 *
	 * @since 4.0.0
	 */
	public static function builder_cards( $cards, $settings = array() ) {
		$isPro = class_exists( 'WooBeWoo_PF_Frame' ) && WooBeWoo_PF_Frame::_()->isPro();
		foreach ( self::all() as $id => $def ) {
			if ( ! isset( $cards[ $id ] ) || ! is_array( $cards[ $id ] ) ) {
				continue;
			}
			$cards[ $id ]['pro']   = $def['pro'] && ! $isPro;
			$cards[ $id ]['group'] = $def['card']['group'];
			if ( '' !== $def['card']['spec'] ) {
				$cards[ $id ]['spec'] = $def['card']['spec'];
			}
			if ( '' !== $def['card']['href'] ) {
				$cards[ $id ]['href'] = $def['card']['href'];
			}
		}
		return $cards;
	}

	/**
	 * Builder controls of every registered block, each limited to its block id and flagged for the 4.0 engine.
	 *
	 * @since 4.0.0
	 */
	public static function controls() {
		$out = array();
		foreach ( self::all() as $id => $def ) {
			foreach ( $def['controls'] as $i => $c ) {
				if ( ! is_array( $c ) || empty( $c['key'] ) ) {
					continue;
				}
				$c['scope']  = 'block';
				$c['blocks'] = array( $id );
				if ( empty( $c['group'] ) ) {
					$c['group'] = 'basics';
				}
				if ( empty( $c['type'] ) ) {
					$c['type'] = 'text';
				}
				if ( empty( $c['id'] ) ) {
					$c['id'] = $id . '_' . preg_replace( '/[^a-z0-9_]/', '_', strtolower( (string) $c['key'] ) );
				}
				if ( ! isset( $c['engine4Only'] ) ) {
					$c['engine4Only'] = true;
				}
				$out[] = $c;
			}
		}
		return $out;
	}

	/**
	 * Block settings the builder writes when a registered block is added: id => array( key => value ).
	 *
	 * @since 4.0.0
	 */
	public static function defaults() {
		$out = array();
		foreach ( self::all() as $id => $def ) {
			if ( $def['defaults'] ) {
				$out[ $id ] = $def['defaults'];
			}
		}
		return $out;
	}

	/**
	 * Display types of a registered block (the first is the default), or null for an unknown id.
	 *
	 * @since 4.0.0
	 */
	public static function displays( $id ) {
		$def = self::get( $id );
		return $def ? $def['displays'] : null;
	}

	/**
	 * WP-CLI block words: word => block id.
	 *
	 * @since 4.0.0
	 */
	public static function cli_words() {
		$out = array();
		foreach ( self::all() as $id => $def ) {
			if ( '' !== $def['cli'] ) {
				$out[ $def['cli'] ] = $id;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ source blocks */

	/**
	 * Values of a source block for this request: slug => array( label, count|null ).
	 *
	 * @since 4.0.0
	 */
	public static function source_values( $def, $engine = null ) {
		$id = $def['id'];
		if ( isset( self::$values[ $id ] ) ) {
			return self::$values[ $id ];
		}
		$raw = $def['source'] ? call_user_func( $def['source']['values'], $engine, $def ) : array();
		$out = array();
		foreach ( (array) $raw as $slug => $v ) {
			$slug = sanitize_title( is_array( $v ) && isset( $v['value'] ) ? $v['value'] : $slug );
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = array(
				'label' => is_array( $v ) ? ( isset( $v['label'] ) ? (string) $v['label'] : $slug ) : (string) $v,
				'count' => is_array( $v ) && isset( $v['count'] ) && null !== $v['count'] ? (int) $v['count'] : null,
			);
		}
		self::$values[ $id ] = $out;
		return $out;
	}

	/**
	 * Product ids that carry any of the values (the block's `ids` callback), as unique integers.
	 */
	private static function source_ids( $def, $values, $engine ) {
		$ids = call_user_func( $def['source']['ids'], array_values( (array) $values ), $engine, $def );
		return array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );
	}

	private static function source_sql( $def, $b, $query, $engine ) {
		$key    = $b['key'];
		$picked = isset( $query->extra[ $key ] ) ? array_values( array_filter( array_map( 'strval', (array) $query->extra[ $key ] ), 'strlen' ) ) : array();
		if ( ! $picked ) {
			return null;
		}
		$values = self::source_values( $def, $engine );
		$picked = array_values( array_intersect( $picked, array_keys( $values ) ) );
		if ( ! $picked ) {
			return array( 'where' => array( '0 = 1' ) );
		}
		if ( 'and' === $b['logic'] && count( $picked ) > 1 ) {
			$ids = null;
			foreach ( $picked as $v ) {
				$set = self::source_ids( $def, array( $v ), $engine );
				$ids = null === $ids ? $set : array_values( array_intersect( $ids, $set ) );
				if ( ! $ids ) {
					break;
				}
			}
		} else {
			$ids = self::source_ids( $def, $picked, $engine );
		}
		return array( 'where' => array( $ids ? $engine->pid_column() . ' IN (' . implode( ',', $ids ) . ')' : '0 = 1' ) );
	}

	private static function source_facets( $blocks, $engine, $skipKey ) {
		$out = array();
		foreach ( $blocks as $k => $b ) {
			$def    = self::of( $b );
			$values = $def ? self::source_values( $def, $engine ) : array();
			$counts = array();
			$labels = array();
			$set    = $engine->set_ids( $skipKey );
			$empty  = is_array( $set ) && ! $set;
			$ck     = $empty || ! $def ? '' : $engine->cache_key( 'src_' . $def['id'], array( $k, $skipKey ) );
			$cached = '' !== $ck ? WooBeWoo_PF_Engine::cache_get( $ck ) : false;
			if ( is_array( $cached ) ) {
				$counts = $cached;
			} else {
				$flip = is_array( $set ) ? array_flip( $set ) : null;
				foreach ( $values as $slug => $v ) {
					if ( $empty ) {
						$counts[ $slug ] = 0;
					} elseif ( null === $set && null !== $v['count'] ) {
						$counts[ $slug ] = (int) $v['count'];
					} else {
						$ids             = self::source_ids( $def, array( $slug ), $engine );
						$counts[ $slug ] = null === $flip ? count( $ids ) : count( array_intersect_key( array_flip( $ids ), $flip ) );
					}
				}
				if ( '' !== $ck ) {
					WooBeWoo_PF_Engine::cache_set( $ck, $counts );
				}
			}
			foreach ( $values as $slug => $v ) {
				$labels[ $slug ] = $v['label'];
			}
			$out[ $k ] = array(
				'kind'   => $b['kind'],
				'counts' => $counts,
				'labels' => $labels,
			);
		}
		return $out;
	}

	private static function source_render( $def, $b, $display, $mobile, $view ) {
		$values    = self::source_values( $def, $view->engine );
		$facets    = $view->facets;
		$counts    = isset( $facets[ $b['key'] ]['counts'] ) ? $facets[ $b['key'] ]['counts'] : array();
		$picked    = isset( $view->query->extra[ $b['key'] ] ) ? array_map( 'strval', (array) $view->query->extra[ $b['key'] ] ) : array();
		$hideEmpty = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_hide_empty' );
		$showCount = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' );
		$opts      = array();
		foreach ( $values as $slug => $v ) {
			$c  = isset( $counts[ $slug ] ) ? (int) $counts[ $slug ] : 0;
			$on = in_array( $slug, $picked, true );
			if ( $hideEmpty && 0 === $c && ! $on ) {
				continue;
			}
			$opts[] = array(
				'value'   => $slug,
				'label'   => $v['label'],
				'count'   => $c,
				'checked' => $on,
				'depth'   => 0,
			);
		}
		if ( ! in_array( $display, array( 'list', 'chips', 'squares', 'swatches', 'dropdown' ), true ) ) {
			$display = 'list';
		}
		return $view->render_options( $b, $display, 'wpf_' . $b['key'], $opts, $showCount, $mobile );
	}

	/**
	 * Chip text of a source block value: its label.
	 *
	 * @since 4.0.0
	 */
	public static function source_chip( $value, $b, $def ) {
		$values = self::source_values( $def, null );
		$value  = (string) $value;
		return isset( $values[ $value ] ) ? $values[ $value ]['label'] : $value;
	}

	/* ------------------------------------------------------------------ helpers */

	/**
	 * Keys the query reserves for the built-in blocks and its own vars.
	 */
	private static function reserved_key( $key ) {
		if ( in_array( $key, WooBeWoo_PF_Query::RESERVED, true ) || isset( WooBeWoo_PF_Query::FIXED[ $key ] ) || 'author' === $key ) {
			return true;
		}
		return (bool) preg_match( '/^(q\d+|cf_.*|order|count|fbv|skip|fid|nocache|pretty|query|tax|group|dpv|ebv|light|id|default|preview|page_type|selftest|place|lic_for)$/', $key );
	}
}
