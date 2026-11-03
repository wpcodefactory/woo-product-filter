<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Engine_Migration
 *
 * Moves filters from the classic 3.x storefront engine to the 4.0 engine, and back.
 *
 *  - analyze():    what a switch changes for one filter (classic-only settings with a non-default value, mapped or
 *                  dropped; custom CSS / JS to check; page-builder add-on grids on the site).
 *  - switchTo4():  snapshot the row (History + our own copy), write the mapped 4.0 keys, set the engine, log it.
 *  - revert():     put the snapshot back (kept for at least one release after the switch).
 *  - autoSwitch(): on update, switch the filters where nothing changes (no classic-only setting in use, no custom
 *                  code, no add-on grid plugin on the site). Logged; never silent.
 *  - notice():     on the plugin screens, a card per classic filter: Preview on 4.0 / Switch, with the exact list,
 *                  and Switch back on the migrated ones. Dated: the classic engine ends with CLASSIC_END.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Engine_Migration {

	const OPT_LOG   = 'wpf_engine_migration_log';
	const OPT_SNAP  = 'wpf_engine_migration_snap';
	const OPT_AUTO  = 'wpf_engine_autoswitch_done';
	const AJAX      = 'woobewoo_pf_engine_migration';

	/** The classic engine is removed in this version, on or after this date. */
	const CLASSIC_END_VERSION = '4.2';
	const CLASSIC_END_DATE    = '2027-03-31';

	/** Plugins whose product grids the classic engine hooks and the 4.0 engine hooks through grids(); their presence makes a filter placed by shortcode / widget / builder not auto-switchable. */
	public static function addonPlugins() {
		return array(
			'essential-addons-for-elementor-lite/essential_adons_elementor.php' => 'Essential Addons for Elementor',
			'essential-addons-elementor/essential_adons_elementor.php'          => 'Essential Addons for Elementor Pro',
			'header-footer-elementor/header-footer-elementor.php'               => 'Ultimate Addons for Elementor',
			'ultimate-elementor/ultimate-elementor.php'                         => 'Ultimate Addons for Elementor Pro',
			'bdthemes-element-pack/bdthemes-element-pack.php'                   => 'Element Pack Pro',
			'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php'         => 'Element Pack Lite',
			'jet-woo-builder/jet-woo-builder.php'                               => 'JetWooBuilder',
			'divi-plus/divi-plus.php'                                           => 'Divi Plus',
			'dynamic-content-for-elementor/dynamic-content-for-elementor.php'   => 'Dynamic Content for Elementor',
		);
	}

	public static function boot() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybeAutoSwitch' ), 30 );
	}

	/* ------------------------------------------------------------------ rules */

	/**
	 * Classic-only keys with a meaning on 4.0. Everything classicOnly that is not listed here is "drop" (the 4.0
	 * engine has no such switch: it is always on, always off, or the UI does not exist).
	 *
	 * map: the 4.0 key written from the classic value; fn: how the value converts.
	 */
	public static function rules() {
		return array(
			// Behaviour.
			'show_filtering_button'       => array( 'to' => 'auto_update_filter', 'fn' => 'not_button', 'always' => true, 'note' => __( 'Shoppers press a Filter button: 4.0 shows its Apply button', 'woo-product-filter' ) ),
			'filtering_button_word'       => array( 'to' => 'apply_button_word', 'fn' => 'text', 'note' => __( 'The Filter button text becomes the Apply button text', 'woo-product-filter' ) ),
			'checked_items_top'           => array( 'to' => 'checked_items_top', 'fn' => 'bool', 'always' => true, 'note' => __( 'Picked choices move to the top', 'woo-product-filter' ) ),
			'enable_overlay'              => array( 'to' => 'loading_overlay', 'fn' => 'bool', 'always' => true, 'note' => __( 'Dim the products while loading', 'woo-product-filter' ) ),
			'reset_all_filters'           => array( 'to' => null, 'note' => __( '4.0 has one Clear all per filter', 'woo-product-filter' ) ),
			'filter_null_disabled'        => array( 'to' => null, 'note' => __( '4.0 always greys out choices with no products (or hides them per block)', 'woo-product-filter' ) ),
			'filter_recount'              => array( 'to' => null, 'note' => __( '4.0 always updates the counts as shoppers pick', 'woo-product-filter' ) ),
			'filter_recount_price'        => array( 'to' => null, 'note' => __( '4.0 always updates the price range', 'woo-product-filter' ) ),
			'force_theme_templates'       => array( 'to' => null, 'note' => __( '4.0 always renders products through the theme templates', 'woo-product-filter' ) ),
			'count_product_shop'          => array( 'to' => null, 'note' => __( 'Products per page keeps applying store wide', 'woo-product-filter' ) ),
			'columns_product_shop'        => array( 'to' => null, 'note' => __( 'Products per row keeps applying store wide', 'woo-product-filter' ) ),
			'sort_by_title'               => array( 'to' => null, 'note' => __( 'Use a Sort by block with "Name" as the default instead', 'woo-product-filter' ) ),
			'product_list_selector'       => array( 'to' => 'product_container_selector', 'fn' => 'text', 'note' => __( 'Product list selector', 'woo-product-filter' ) ),
			'desctop_mobile_breakpoint_width' => array( 'to' => 'mobile_breakpoint', 'fn' => 'int', 'note' => __( 'Phone breakpoint', 'woo-product-filter' ) ),
			'filter_width'                => array( 'to' => 'side_width', 'fn' => 'int', 'note' => __( 'Filter width becomes the sidebar width', 'woo-product-filter' ) ),
			// Pro classic keys.
			'display_selected_params'     => array( 'to' => 'show_chips', 'fn' => 'bool', 'always' => true, 'note' => __( 'Picked values as chips', 'woo-product-filter' ) ),
			'selected_params_clear'       => array( 'to' => null, 'note' => __( '4.0 has the Clear all button instead of a Clear chip', 'woo-product-filter' ) ),
			'selected_clean_word'         => array( 'to' => 'show_clean_button_word', 'fn' => 'text', 'note' => __( 'The Clear text', 'woo-product-filter' ) ),
			'floating_mode'               => array( 'to' => 'mobile_drawer', 'fn' => 'bool', 'always' => true, 'note' => __( 'Floating filter becomes the drawer', 'woo-product-filter' ) ),
			'display_hide_button_mobile'  => array( 'to' => 'mobile_drawer', 'fn' => 'bool', 'always' => true, 'note' => __( 'Hide behind a button on phones becomes the drawer', 'woo-product-filter' ) ),
			'display_hide_button'         => array( 'to' => 'mobile_drawer_always', 'fn' => 'bool', 'always' => true, 'note' => __( 'Hide behind a button on desktop becomes the drawer on every screen', 'woo-product-filter' ) ),
			'hide_button_show_text'       => array( 'to' => 'mobile_drawer_text', 'fn' => 'text', 'note' => __( 'The button text', 'woo-product-filter' ) ),
			'display_product_variations'  => array( 'to' => 'variations_as_products', 'fn' => 'bool', 'always' => true, 'note' => __( 'Matching variations in the results', 'woo-product-filter' ) ),
			'scroll_after_filtration'     => array( 'to' => null, 'note' => __( '4.0 scrolls to the products when they are out of view', 'woo-product-filter' ) ),
			'display_view_more'           => array( 'to' => null, 'note' => __( '4.0 folds long lists behind "Show more" per block (Show this many rows)', 'woo-product-filter' ) ),
			'ajax_leave_products'         => array( 'to' => null, 'note' => __( '4.0 keeps the old products on screen while loading', 'woo-product-filter' ) ),
			'use_category_slug'           => array( 'to' => null, 'note' => __( '4.0 always writes words in the address', 'woo-product-filter' ) ),
			// Block level.
			'f_show_hierarchical'         => array( 'to' => null, 'block' => true, 'note' => __( '4.0 shows categories as a tree when the taxonomy is hierarchical', 'woo-product-filter' ) ),
			'f_dropdown_search'           => array( 'to' => 'f_show_search_input', 'fn' => 'bool', 'always' => true, 'block' => true, 'note' => __( 'Dropdown search becomes the search box above the list', 'woo-product-filter' ) ),
			'f_hide_empty_active'         => array( 'to' => 'f_hide_empty', 'fn' => 'bool', 'always' => true, 'block' => true, 'note' => __( 'Hide empty choices', 'woo-product-filter' ) ),
			'f_skin_type'                 => array( 'to' => null, 'block' => true, 'note' => __( '4.0 has one slider look, coloured by the accent', 'woo-product-filter' ) ),
			'f_colors_singleselect'       => array( 'to' => 'f_single_select', 'fn' => 'bool', 'always' => true, 'block' => true, 'note' => __( 'One swatch at a time', 'woo-product-filter' ) ),
			'f_text_singleselection'      => array( 'to' => 'f_single_select', 'fn' => 'bool', 'always' => true, 'block' => true, 'note' => __( 'One value at a time', 'woo-product-filter' ) ),
			'f_buttons_multiselect'       => array( 'to' => 'f_single_select', 'fn' => 'not_bool', 'always' => true, 'block' => true, 'note' => __( 'Buttons: one at a time unless multi select was on', 'woo-product-filter' ) ),
			'f_max_show_more'             => array( 'to' => null, 'block' => true, 'note' => __( '4.0 folds by rows ("Show this many rows")', 'woo-product-filter' ) ),
		);
	}

	private static function convert( $fn, $value ) {
		$v = is_array( $value ) ? '' : (string) $value;
		switch ( $fn ) {
			case 'bool':
				return in_array( strtolower( $v ), array( '1', 'on', 'true', 'yes' ), true ) ? '1' : '0';
			case 'not_bool':
				return in_array( strtolower( $v ), array( '1', 'on', 'true', 'yes' ), true ) ? '0' : '1';
			case 'not_button':
				// show_filtering_button: '1' / 'yes' = shoppers press Filter -> auto update off.
				return in_array( strtolower( $v ), array( '1', 'on', 'true', 'yes' ), true ) ? '0' : '1';
			case 'int':
				return (string) (int) $v;
			default:
				return sanitize_text_field( $v );
		}
	}

	/* ------------------------------------------------------------------ analysis */

	/**
	 * Classic-only controls (main and block) with their defaults, from the builder schema.
	 */
	private static function classicIndex( $settings ) {
		static $idx = null;
		if ( null !== $idx ) {
			return $idx;
		}
		$idx = array( 'main' => array(), 'block' => array() );
		if ( ! class_exists( 'WooBeWoo_PF_Admin_Schema' ) ) {
			require_once WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'admin-schema.php'; // only the builder loads it otherwise
		}
		try {
			$schema = WooBeWoo_PF_Admin_Schema::build( $settings );
		} catch ( Throwable $e ) {
			$idx = null; // unknown: analyze() then refuses to auto-switch
			return array( 'main' => array(), 'block' => array(), 'unknown' => true );
		}
		$idx['defaults'] = isset( $schema['defaults'] ) && is_array( $schema['defaults'] ) ? $schema['defaults'] : array();
		// 3.x saved every setting with its default as the value: the untouched 3.4.7 filter tells which values those are.
		$classic = include WPF_MODULES_DIR . 'woofilters' . WPF_DS . 'classic-defaults.php';
		if ( is_array( $classic ) ) {
			$idx['defaults'] = array_merge( $idx['defaults'], $classic );
		}
		foreach ( (array) $schema['controls'] as $c ) {
			if ( ! empty( $c['classicOnly'] ) && ! empty( $c['key'] ) ) {
				$idx[ 'block' === $c['scope'] ? 'block' : 'main' ][ $c['key'] ] = $c;
			}
		}
		foreach ( (array) $schema['mainKeys'] as $key => $def ) {
			if ( ! empty( $def['classicOnly'] ) && ! isset( $idx['main'][ $key ] ) ) {
				$idx['main'][ $key ] = array_merge( $def, array( 'key' => $key ) );
			}
		}
		foreach ( (array) $schema['blockKeys'] as $key => $def ) {
			if ( ! empty( $def['classicOnly'] ) && ! isset( $idx['block'][ $key ] ) ) {
				$idx['block'][ $key ] = array_merge( $def, array( 'key' => $key ) );
			}
		}
		return $idx;
	}

	/**
	 * Is this stored value something the owner set? 3.x saved every setting with its default as the value, so a
	 * value equal to the 3.x default (the engine defaults, Pro's included) or to the control default is not "in use".
	 */
	private static function inUse( $def, $value, $classicDefault = null ) {
		if ( is_array( $value ) ) {
			return count( array_filter( $value, 'strlen' ) ) > 0;
		}
		$v    = trim( (string) $value );
		$type = isset( $def['type'] ) ? $def['type'] : 'text';
		if ( 'hidden' === $type ) {
			return false;
		}
		if ( null !== $classicDefault && ! is_array( $classicDefault ) && strcasecmp( trim( (string) $classicDefault ), $v ) === 0 ) {
			return false;
		}
		if ( in_array( $v, array( '', '0', 'no', 'off' ), true ) && 'text' !== $type ) {
			return false;
		}
		$default = isset( $def['default'] ) ? (string) $def['default'] : '';
		if ( 'switch' === $type ) {
			$on = isset( $def['on'] ) ? (string) $def['on'] : '1';
			return $v === $on && $default !== $on;
		}
		if ( '' === $v ) {
			return false;
		}
		if ( '' === $default && ( 'select' === $type || 'pictures' === $type ) && ! empty( $def['options'] ) && is_array( $def['options'] ) ) {
			$keys    = array_keys( $def['options'] );
			$default = (string) reset( $keys );
		}
		return $v !== $default;
	}

	/**
	 * What switching one filter to 4.0 changes.
	 *
	 * @return array id, title, engine, mapped[], dropped[], css, js, addons[], placement, auto_ok, reasons[]
	 */
	public static function analyze( $filter ) {
		$s      = isset( $filter['settings']['settings'] ) ? $filter['settings']['settings'] : array();
		$rules  = self::rules();
		$idx    = self::classicIndex( $filter['settings'] );
		$out    = array(
			'id'        => (int) $filter['id'],
			'title'     => (string) $filter['title'],
			'engine'    => class_exists( 'WooBeWoo_PF_Frontend4' ) ? WooBeWoo_PF_Frontend4::engine_of( $filter['id'], $filter['settings'] ) : 'legacy',
			'mapped'    => array(),
			'dropped'   => array(),
			'css'       => ! empty( $s['css_editor'] ),
			'js'        => ! empty( $s['js_editor'] ),
			'addons'    => array(),
			'placement' => isset( $s['wbw_placement'] ) ? (string) $s['wbw_placement'] : '',
			'reasons'   => array(),
		);
		foreach ( $idx['main'] as $key => $def ) {
			if ( ! array_key_exists( $key, $s ) ) {
				continue;
			}
			$used  = self::inUse( $def, $s[ $key ], isset( $idx['defaults'][ $key ] ) ? $idx['defaults'][ $key ] : null );
			$label = isset( $def['label'] ) ? $def['label'] : $key;
			if ( isset( $rules[ $key ] ) && ! empty( $rules[ $key ]['to'] ) && ( $used || ! empty( $rules[ $key ]['always'] ) ) ) {
				// Lossless: the 4.0 key takes the classic value (also when it is the 3.x default, so the behaviour stays).
				$r = $rules[ $key ];
				$out['mapped'][] = array( 'key' => $key, 'label' => $label, 'to' => $r['to'], 'value' => self::convert( isset( $r['fn'] ) ? $r['fn'] : 'text', $s[ $key ] ), 'note' => $r['note'], 'changed' => $used );
				continue;
			}
			if ( ! $used ) {
				continue;
			}
			if ( isset( $rules[ $key ] ) ) {
				$r = $rules[ $key ];
				if ( ! empty( $r['to'] ) ) {
					$out['mapped'][] = array( 'key' => $key, 'label' => $label, 'to' => $r['to'], 'value' => self::convert( isset( $r['fn'] ) ? $r['fn'] : 'text', $s[ $key ] ), 'note' => $r['note'], 'changed' => true );
				} else {
					$out['dropped'][] = array( 'key' => $key, 'label' => $label, 'note' => $r['note'] );
				}
			} else {
				$out['dropped'][] = array( 'key' => $key, 'label' => $label, 'note' => __( 'Not in the 4.0 engine', 'woo-product-filter' ) );
			}
		}
		// Block level.
		$blocks = isset( $s['filters'] ) && is_array( $s['filters'] ) ? $s['filters'] : array();
		foreach ( $blocks as $bkey => $b ) {
			if ( ! is_array( $b ) || empty( $b['settings'] ) || ! is_array( $b['settings'] ) ) {
				continue;
			}
			$bt = isset( $b['settings']['f_title'] ) && '' !== $b['settings']['f_title'] ? $b['settings']['f_title'] : ( isset( $b['settings']['f_name'] ) ? $b['settings']['f_name'] : (string) $bkey );
			foreach ( $idx['block'] as $key => $def ) {
				$root = preg_replace( '/\[.*$/', '', $key );
				if ( ! array_key_exists( $root, $b['settings'] ) ) {
					continue;
				}
				if ( ! empty( $def['blocks'] ) && is_array( $def['blocks'] ) && ! empty( $b['settings']['f_name'] ) && ! in_array( $b['settings']['f_name'], $def['blocks'], true ) ) {
					continue;
				}
				$used  = self::inUse( $def, $b['settings'][ $root ], isset( $idx['defaults'][ $root ] ) ? $idx['defaults'][ $root ] : null );
				$label = ( isset( $def['label'] ) ? $def['label'] : $root ) . ' (' . $bt . ')';
				if ( isset( $rules[ $root ] ) && ! empty( $rules[ $root ]['to'] ) && ( $used || ! empty( $rules[ $root ]['always'] ) ) ) {
					$r = $rules[ $root ];
					$out['mapped'][] = array( 'key' => $root, 'block' => (string) $bkey, 'label' => $label, 'to' => $r['to'], 'value' => self::convert( isset( $r['fn'] ) ? $r['fn'] : 'text', $b['settings'][ $root ] ), 'note' => $r['note'], 'changed' => $used );
					continue;
				}
				if ( ! $used ) {
					continue;
				}
				if ( isset( $rules[ $root ] ) ) {
					$r = $rules[ $root ];
					if ( ! empty( $r['to'] ) ) {
						$out['mapped'][] = array( 'key' => $root, 'block' => (string) $bkey, 'label' => $label, 'to' => $r['to'], 'value' => self::convert( isset( $r['fn'] ) ? $r['fn'] : 'text', $b['settings'][ $root ] ), 'note' => $r['note'] );
					} else {
						$out['dropped'][] = array( 'key' => $root, 'block' => (string) $bkey, 'label' => $label, 'note' => $r['note'] );
					}
				} else {
					$out['dropped'][] = array( 'key' => $root, 'block' => (string) $bkey, 'label' => $label, 'note' => __( 'Not in the 4.0 engine', 'woo-product-filter' ) );
				}
			}
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( self::addonPlugins() as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$out['addons'][] = $name;
			}
		}
		$out['auto_ok'] = true;
		if ( ! empty( $idx['unknown'] ) ) {
			$out['auto_ok']   = false;
			$out['reasons'][] = __( 'the settings could not be analysed', 'woo-product-filter' );
		}
		$changedMaps = array_filter( $out['mapped'], function ( $m ) { return ! empty( $m['changed'] ); } );
		if ( $out['dropped'] || $changedMaps ) {
			$out['auto_ok']   = false;
			$out['reasons'][] = __( 'uses settings only the classic engine reads', 'woo-product-filter' );
		}
		if ( $out['css'] || $out['js'] ) {
			$out['auto_ok']   = false;
			$out['reasons'][] = __( 'has custom CSS or JavaScript', 'woo-product-filter' );
		}
		// A filter placed by shortcode, widget or builder on a site with an add-on grid plugin may sit next to one of
		// its grids: the owner checks it. Filters on the shop / category pages (sidebar, above) are not affected.
		if ( $out['addons'] && ! in_array( $out['placement'], array( 'sidebar', 'above', 'drawer' ), true ) ) {
			$out['auto_ok']   = false;
			$out['reasons'][] = sprintf( __( 'a page-builder grid plugin is active (%s)', 'woo-product-filter' ), implode( ', ', $out['addons'] ) );
		}
		return apply_filters( 'woobewoo_pf_engine_migration_analysis', $out, $filter );
	}

	/* ------------------------------------------------------------------ switching */

	private static function row( $id ) {
		return WooBeWoo_PF_Db::get( 'SELECT id, title, setting_data FROM `@__filters` WHERE id = %d', 'row', ARRAY_A, array( (int) $id ) );
	}

	private static function filterOf( $id ) {
		$row = self::row( $id );
		if ( ! $row ) {
			return null;
		}
		$s = function_exists( 'woobewoo_pf_decode_settings' ) ? woobewoo_pf_decode_settings( $row['setting_data'] ) : maybe_unserialize( $row['setting_data'] );
		if ( ! is_array( $s ) ) {
			$s = array();
		}
		if ( ! isset( $s['settings'] ) || ! is_array( $s['settings'] ) ) {
			$s['settings'] = array();
		}
		return array( 'id' => (int) $row['id'], 'title' => (string) $row['title'], 'settings' => $s, 'raw' => $row['setting_data'] );
	}

	private static function writeSettings( $id, $settings ) {
		// Stored the way the builder stores it (serialized): the admin list and 3.x code paths read that; JSON rows are
		// read by the storefront only.
		$encoded = maybe_serialize( $settings );
		$ok      = WooBeWoo_PF_Db::query( 'UPDATE `@__filters` SET setting_data = %s WHERE id = %d', false, array( $encoded, (int) $id ) );
		if ( $ok ) {
			if ( class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
				WooBeWoo_PF_Frontend4::flush_filters();
			}
			do_action( 'woobewoo_pf_filter_restored', (int) $id );
			$module = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
			if ( $module && method_exists( $module->getModel( 'woofilters' ), 'getFiltersMetaKeys' ) ) {
				$module->getModel( 'woofilters' )->getFiltersMetaKeys( (int) $id, true );
			}
		}
		return (bool) $ok;
	}

	private static function versions() {
		$module = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		return $module && method_exists( $module, 'getModel' ) ? $module->getModel( 'versions' ) : null;
	}

	/**
	 * Apply the analysis to a settings array: mapped keys written, engine set, CSS flag set. Returns the new settings.
	 */
	public static function migratedSettings( $filter, $analysis = null ) {
		$analysis = $analysis ? $analysis : self::analyze( $filter );
		$settings = $filter['settings'];
		foreach ( $analysis['mapped'] as $m ) {
			if ( ! empty( $m['block'] ) ) {
				if ( isset( $settings['settings']['filters'][ $m['block'] ]['settings'] ) ) {
					$settings['settings']['filters'][ $m['block'] ]['settings'][ $m['to'] ] = $m['value'];
				}
			} else {
				$settings['settings'][ $m['to'] ] = $m['value'];
			}
		}
		$settings['settings']['frontend_engine']  = '4';
		$settings['settings']['legacy_css_check'] = ( $analysis['css'] || $analysis['js'] ) ? '1' : '0';
		return $settings;
	}

	/**
	 * Switch one filter to the 4.0 engine. $mode: 'auto' | 'manual'.
	 */
	public static function switchTo4( $id, $mode = 'manual' ) {
		$filter = self::filterOf( $id );
		if ( ! $filter ) {
			return new WP_Error( 'wpf_no_filter', __( 'Filter not found.', 'woo-product-filter' ) );
		}
		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) && WooBeWoo_PF_Frontend4::is_engine4( $filter['id'], $filter['settings'] ) ) {
			return new WP_Error( 'wpf_already', __( 'This filter already runs the 4.0 engine.', 'woo-product-filter' ) );
		}
		$analysis = self::analyze( $filter );
		$versions = self::versions();
		if ( $versions ) {
			$versions->snapshot( $filter['id'] );
		}
		$snap                 = get_option( self::OPT_SNAP, array() );
		$snap[ $filter['id'] ] = array(
			'title'   => $filter['title'],
			'raw'     => $filter['raw'],
			'when'    => current_time( 'mysql', true ),
			'version' => WPF_VERSION,
			'mode'    => $mode,
		);
		update_option( self::OPT_SNAP, $snap, false );
		$new = self::migratedSettings( $filter, $analysis );
		if ( ! self::writeSettings( $filter['id'], $new ) ) {
			return new WP_Error( 'wpf_write', __( 'The filter could not be saved.', 'woo-product-filter' ) );
		}
		// Verify what landed: the same blocks and at least as many settings as before, or put the row back at once.
		$check = self::filterOf( $filter['id'] );
		$was   = isset( $filter['settings']['settings'] ) ? $filter['settings']['settings'] : array();
		$now   = $check && isset( $check['settings']['settings'] ) ? $check['settings']['settings'] : array();
		$wasB  = isset( $was['filters'] ) ? wp_json_encode( $was['filters'] ) : '';
		$nowB  = isset( $now['filters'] ) ? wp_json_encode( $now['filters'] ) : '';
		if ( ! $check || count( $now ) < count( $was ) || $wasB !== $nowB || '4' !== (string) ( isset( $now['frontend_engine'] ) ? $now['frontend_engine'] : '' ) ) {
			WooBeWoo_PF_Db::query( 'UPDATE `@__filters` SET setting_data = %s WHERE id = %d', false, array( (string) $filter['raw'], (int) $filter['id'] ) );
			WooBeWoo_PF_Frontend4::flush_filters();
			unset( $snap[ $filter['id'] ] );
			update_option( self::OPT_SNAP, $snap, false );
			$why = sprintf( 'settings %d -> %d, blocks %s, engine %s', count( $was ), count( $now ), $wasB === $nowB ? 'same' : 'changed', isset( $now['frontend_engine'] ) ? $now['frontend_engine'] : 'none' );
			update_option( 'wpf_engine_migration_last_error', array( 'id' => (int) $filter['id'], 'when' => current_time( 'mysql', true ), 'why' => $why, 'raw_len' => strlen( (string) $filter['raw'] ), 'json_len' => strlen( (string) ( function_exists( 'woobewoo_pf_encode_settings' ) ? woobewoo_pf_encode_settings( $new ) : '' ) ) ), false );
			return new WP_Error( 'wpf_verify', sprintf( __( 'The switch was undone: the saved filter did not read back correctly (%s).', 'woo-product-filter' ), $why ) );
		}
		if ( $versions ) {
			$versions->snapshot( $filter['id'] );
		}
		$seen = array_map( 'intval', (array) get_option( 'wpf_filters_engine4_seen', array() ) );
		if ( ! in_array( (int) $filter['id'], $seen, true ) ) {
			$seen[] = (int) $filter['id'];
			update_option( 'wpf_filters_engine4_seen', $seen, false );
		}
		self::log( $filter['id'], $filter['title'], $mode, $analysis );
		do_action( 'woobewoo_pf_engine_switched', (int) $filter['id'], '4', $mode, $analysis );
		return $analysis;
	}

	/**
	 * Put the pre-switch row back.
	 */
	public static function revert( $id ) {
		$snap = get_option( self::OPT_SNAP, array() );
		if ( empty( $snap[ (int) $id ] ) ) {
			return new WP_Error( 'wpf_no_snap', __( 'No copy of the classic version of this filter is kept.', 'woo-product-filter' ) );
		}
		$versions = self::versions();
		if ( $versions ) {
			$versions->snapshot( (int) $id );
		}
		$ok = WooBeWoo_PF_Db::query( 'UPDATE `@__filters` SET setting_data = %s WHERE id = %d', false, array( (string) $snap[ (int) $id ]['raw'], (int) $id ) );
		if ( ! $ok ) {
			return new WP_Error( 'wpf_write', __( 'The filter could not be saved.', 'woo-product-filter' ) );
		}
		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			WooBeWoo_PF_Frontend4::flush_filters();
		}
		do_action( 'woobewoo_pf_filter_restored', (int) $id );
		$seen = array_map( 'intval', (array) get_option( 'wpf_filters_engine4_seen', array() ) );
		update_option( 'wpf_filters_engine4_seen', array_values( array_diff( $seen, array( (int) $id ) ) ), false );
		if ( $versions ) {
			$versions->snapshot( (int) $id );
		}
		unset( $snap[ (int) $id ] );
		update_option( self::OPT_SNAP, $snap, false );
		$row = self::row( $id );
		self::log( (int) $id, $row ? $row['title'] : '', 'revert', null );
		do_action( 'woobewoo_pf_engine_switched', (int) $id, 'legacy', 'revert', null );
		return true;
	}

	private static function log( $id, $title, $mode, $analysis ) {
		$log   = get_option( self::OPT_LOG, array() );
		$log[] = array(
			'id'      => (int) $id,
			'title'   => (string) $title,
			'when'    => current_time( 'mysql', true ),
			'mode'    => $mode,
			'version' => WPF_VERSION,
			'mapped'  => $analysis ? wp_list_pluck( $analysis['mapped'], 'label' ) : array(),
			'dropped' => $analysis ? wp_list_pluck( $analysis['dropped'], 'label' ) : array(),
			'css'     => $analysis ? (bool) ( $analysis['css'] || $analysis['js'] ) : false,
			'user'    => get_current_user_id(),
		);
		update_option( self::OPT_LOG, array_slice( $log, -200 ), false );
	}

	/**
	 * On update: switch every classic filter where nothing changes. Once per plugin version.
	 */
	public static function maybeAutoSwitch() {
		if ( ! current_user_can( 'manage_woocommerce' ) || (string) get_option( self::OPT_AUTO ) === WPF_VERSION ) {
			return;
		}
		update_option( self::OPT_AUTO, WPF_VERSION, true );
		self::autoSwitch();
	}

	public static function autoSwitch() {
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) || ! apply_filters( 'woobewoo_pf_engine_autoswitch', true ) ) {
			return array();
		}
		$done = array();
		foreach ( WooBeWoo_PF_Frontend4::filters() as $f ) {
			if ( WooBeWoo_PF_Frontend4::is_engine4( $f['id'], $f['settings'] ) ) {
				continue;
			}
			$a = self::analyze( $f );
			if ( ! $a['auto_ok'] ) {
				continue;
			}
			$r = self::switchTo4( $f['id'], 'auto' );
			if ( ! is_wp_error( $r ) ) {
				$done[] = $f['id'];
			}
		}
		if ( $done ) {
			set_transient( 'wpf_engine_autoswitch_notice', $done, WEEK_IN_SECONDS );
		}
		return $done;
	}

	/**
	 * Storefront URL that shows the filter as it would be on 4.0, without saving (wizard draft preview).
	 */
	public static function previewUrl( $id ) {
		$filter = self::filterOf( $id );
		if ( ! $filter ) {
			return '';
		}
		$setup = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		if ( ! $setup || ! method_exists( $setup->getModel(), 'saveDraftSettings' ) ) {
			return '';
		}
		$new     = self::migratedSettings( $filter );
		$draftId = $setup->getModel()->saveDraftSettings( $filter['title'], $new );
		if ( ! $draftId ) {
			return '';
		}
		$base = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		return add_query_arg( array( 'wpf_preview' => $draftId, 'wpf_preview_of' => (int) $id ), $base );
	}

	/* ------------------------------------------------------------------ admin */

	public static function classicFilters() {
		$out = array();
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			return $out;
		}
		foreach ( WooBeWoo_PF_Frontend4::filters() as $f ) {
			if ( ! WooBeWoo_PF_Frontend4::is_engine4( $f['id'], $f['settings'] ) ) {
				$out[] = $f;
			}
		}
		return $out;
	}

	public static function switchedFilters() {
		$snap = get_option( self::OPT_SNAP, array() );
		$out  = array();
		foreach ( (array) $snap as $id => $s ) {
			$f = class_exists( 'WooBeWoo_PF_Frontend4' ) ? WooBeWoo_PF_Frontend4::filter( (int) $id ) : null;
			if ( $f && WooBeWoo_PF_Frontend4::is_engine4( $f['id'], $f['settings'] ) ) {
				$out[] = array_merge( $f, array( 'snap' => $s ) );
			}
		}
		return $out;
	}

	public static function endText() {
		/* translators: 1: plugin version, 2: date */
		return sprintf( __( 'The classic engine is removed in Product Filter %1$s (%2$s). Until then every filter still on it keeps working as it does today.', 'woo-product-filter' ), self::CLASSIC_END_VERSION, date_i18n( get_option( 'date_format' ), strtotime( self::CLASSIC_END_DATE ) ) );
	}

	public static function notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'wpf-' ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $tab && ! in_array( $tab, array( 'woofilters', 'overview', 'setup' ), true ) ) {
			return;
		}
		$classic  = self::classicFilters();
		$switched = self::switchedFilters();
		$auto     = get_transient( 'wpf_engine_autoswitch_notice' );
		if ( ! $classic && ! $switched && ! $auto ) {
			return;
		}
		$nonce = wp_create_nonce( self::AJAX );
		echo '<div class="notice notice-info wpf-engine-notice" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( $nonce ) . '">';
		if ( $auto ) {
			echo '<p><strong>' . esc_html( sprintf( _n( '%d filter now runs the 4.0 engine.', '%d filters now run the 4.0 engine.', count( (array) $auto ), 'woo-product-filter' ), count( (array) $auto ) ) ) . '</strong> ' . esc_html__( 'Nothing changed in how they look or filter: they used no classic-only setting and no custom code. Each one has a "Switch back" link below for one release.', 'woo-product-filter' ) . '</p>';
			delete_transient( 'wpf_engine_autoswitch_notice' );
		}
		if ( $classic ) {
			echo '<p><strong>' . esc_html( sprintf( _n( '%d filter still runs the classic engine.', '%d filters still run the classic engine.', count( $classic ), 'woo-product-filter' ), count( $classic ) ) ) . '</strong> ' . esc_html( self::endText() ) . '</p>';
			echo '<ul class="wpf-engine-list">';
			foreach ( $classic as $f ) {
				$a = self::analyze( $f );
				echo '<li data-id="' . (int) $f['id'] . '"><strong>' . esc_html( $f['title'] ) . '</strong> ';
				$items = array();
				foreach ( $a['mapped'] as $m ) {
					$items[] = sprintf( '%s → %s', $m['label'], $m['note'] );
				}
				foreach ( $a['dropped'] as $d ) {
					$items[] = sprintf( __( '%1$s: dropped (%2$s)', 'woo-product-filter' ), $d['label'], $d['note'] );
				}
				if ( $a['css'] || $a['js'] ) {
					$items[] = __( 'Custom CSS / JavaScript was written for the classic markup: check it on 4.0 (the filter is flagged in the builder).', 'woo-product-filter' );
				}
				if ( $a['addons'] && ! in_array( $a['placement'], array( 'sidebar', 'above', 'drawer' ), true ) ) {
					$items[] = sprintf( __( 'Check the page-builder grids it filters (%s): 4.0 filters them through its own hooks.', 'woo-product-filter' ), implode( ', ', $a['addons'] ) );
				}
				if ( $items ) {
					echo '<span class="wpf-engine-what">' . esc_html__( 'What changes:', 'woo-product-filter' ) . '</span><ul>';
					foreach ( $items as $it ) {
						echo '<li>' . esc_html( $it ) . '</li>';
					}
					echo '</ul>';
				} else {
					echo '<span class="wpf-engine-what">' . esc_html__( 'Nothing changes: it uses no classic-only setting.', 'woo-product-filter' ) . '</span> ';
				}
				echo '<button type="button" class="button button-small" data-act="preview">' . esc_html__( 'Preview on 4.0', 'woo-product-filter' ) . '</button> ';
				echo '<button type="button" class="button button-small button-primary" data-act="switch">' . esc_html__( 'Switch to 4.0', 'woo-product-filter' ) . '</button>';
				echo '</li>';
			}
			echo '</ul>';
		}
		if ( $switched ) {
			echo '<p>' . esc_html__( 'Switched to 4.0, classic copy kept:', 'woo-product-filter' ) . ' ';
			$links = array();
			foreach ( $switched as $f ) {
				$links[] = '<span data-id="' . (int) $f['id'] . '">' . esc_html( $f['title'] ) . ' <button type="button" class="button-link" data-act="revert">' . esc_html__( 'Switch back', 'woo-product-filter' ) . '</button></span>';
			}
			echo wp_kses_post( implode( ' · ', $links ) ) . '</p>';
		}
		echo '</div>';
		?>
		<style>.wpf-engine-notice .wpf-engine-list{margin:0 0 8px 1em}.wpf-engine-notice .wpf-engine-list>li{margin-bottom:8px}.wpf-engine-notice .wpf-engine-list ul{list-style:disc;margin:2px 0 4px 1.5em}.wpf-engine-what{color:#50575e}</style>
		<script>
		(function(){
			var box=document.querySelector('.wpf-engine-notice');if(!box){return;}
			box.addEventListener('click',function(e){
				var b=e.target.closest('button[data-act]');if(!b){return;}
				var id=b.closest('[data-id]').getAttribute('data-id'),act=b.getAttribute('data-act');
				if(act==='switch'&&!window.confirm(<?php echo wp_json_encode( __( 'Switch this filter to the 4.0 engine? A copy of the classic version is kept and "Switch back" restores it.', 'woo-product-filter' ) ); ?>)){return;}
				b.disabled=true;
				var fd=new FormData();fd.append('action',<?php echo wp_json_encode( self::AJAX ); ?>);fd.append('_wpnonce',box.getAttribute('data-nonce'));fd.append('op',act);fd.append('id',id);
				fetch(box.getAttribute('data-ajax'),{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(r){
					if(!r||!r.success){alert((r&&r.data&&r.data.message)||'Error');b.disabled=false;return;}
					if(act==='preview'){window.open(r.data.url,'_blank');b.disabled=false;return;}
					window.location.reload();
				}).catch(function(){b.disabled=false;});
			});
		})();
		</script>
		<?php
	}

	public static function ajax() {
		check_ajax_referer( self::AJAX );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'woo-product-filter' ) ), 403 );
		}
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		switch ( $op ) {
			case 'analyze':
				$f = self::filterOf( $id );
				if ( ! $f ) {
					wp_send_json_error( array( 'message' => __( 'Filter not found.', 'woo-product-filter' ) ), 404 );
				}
				wp_send_json_success( self::analyze( $f ) );
				break;
			case 'preview':
				$url = self::previewUrl( $id );
				if ( ! $url ) {
					wp_send_json_error( array( 'message' => __( 'Preview is not available (the setup module is off).', 'woo-product-filter' ) ) );
				}
				wp_send_json_success( array( 'url' => $url ) );
				break;
			case 'switch':
				$r = self::switchTo4( $id, 'manual' );
				if ( is_wp_error( $r ) ) {
					wp_send_json_error( array( 'message' => $r->get_error_message() ) );
				}
				wp_send_json_success( $r );
				break;
			case 'state':
				// Diagnostics for support: options the migration keeps, and the row head of one filter.
				$row = $id ? self::row( $id ) : null;
				wp_send_json_success(
					array(
						'version'   => WPF_VERSION,
						'auto_done' => get_option( self::OPT_AUTO ),
						'snap_ids'  => array_keys( (array) get_option( self::OPT_SNAP, array() ) ),
						'snap_info' => array_map( function ( $x ) { return array( 'when' => $x['when'], 'mode' => $x['mode'], 'raw_len' => strlen( (string) $x['raw'] ), 'raw_head' => substr( (string) $x['raw'], 0, 160 ) ); }, (array) get_option( self::OPT_SNAP, array() ) ),
						'log'       => array_slice( (array) get_option( self::OPT_LOG, array() ), -12 ),
						'last_err'  => get_option( 'wpf_engine_migration_last_error' ),
						'before_4'  => get_option( 'wpf_filters_before_4' ),
						'seen_4'    => get_option( 'wpf_filters_engine4_seen' ),
						'row'       => $row ? array( 'len' => strlen( (string) $row['setting_data'] ), 'head' => substr( (string) $row['setting_data'], 0, 400 ), 'engine' => WooBeWoo_PF_Frontend4::engine_of( (int) $id, woobewoo_pf_decode_settings( $row['setting_data'] ) ), 'keys' => count( (array) ( woobewoo_pf_decode_settings( $row['setting_data'] )['settings'] ?? array() ) ) ) : null,
					)
				);
				break;
			case 'revert':
				$r = self::revert( $id );
				if ( is_wp_error( $r ) ) {
					wp_send_json_error( array( 'message' => $r->get_error_message() ) );
				}
				wp_send_json_success( array( 'ok' => true ) );
				break;
			default:
				wp_send_json_error( array( 'message' => 'Unknown op' ), 400 );
		}
	}
}
