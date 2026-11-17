<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Cli Class
 *
 * WP-CLI commands:
 *   wp wpf filter list|get <id>|create --title= --blocks= |update <id> --set key=value|delete <id>|export <id> --file=|import --file=
 *   wp wpf index rebuild|status
 *   wp wpf cache clear
 *   wp wpf diagnostics
 *   wp wpf links flush
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Cli extends WooBeWoo_PF_Module {

	public function init() {
		parent::init();
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
			require_once __DIR__ . '/class-woobewoo-pf-cli-command.php';
			WP_CLI::add_command( 'wpf', 'WooBeWoo_PF_Cli_Command' );
		}
	}

	/* ------------------------------------------------------------------ shared helpers (also used by importers and abilities) */

	/**
	 * Plain block definitions => the filter "order" array the builder saves.
	 * Block: array( 'type' => category|attribute|tags|brand|price|price_range|stock|onsale|featured|rating|sortby|search|author|custom_field,
	 *                'taxonomy' => 'pa_color' (attribute), 'title' => '', 'display' => checkbox|radio|dropdown|mul_dropdown|slider|list, 'settings' => array() )
	 */
	public static function blocksToOrder( $blocks ) {
		$map   = array(
			'category'     => 'wpfCategory',
			'attribute'    => 'wpfAttribute',
			'price'        => 'wpfPrice',
			'price_range'  => 'wpfPriceRange',
			'tags'         => 'wpfTags',
			'tag'          => 'wpfTags',
			'stock'        => 'wpfInStock',
			'onsale'       => 'wpfOnSale',
			'sale'         => 'wpfOnSale',
			'featured'     => 'wpfFeatured',
			'rating'       => 'wpfRating',
			'brand'        => 'wpfBrand',
			'perfectbrand' => 'wpfPerfectBrand',
			'sortby'       => 'wpfSortBy',
			'sort'         => 'wpfSortBy',
			'search'       => 'wpfSearchText',
			'author'       => 'wpfAuthor',
			'custom_field' => 'wpfCustomField',
		);
		// Blocks registered with WooBeWoo_PF_Blocks ('cli' => word): the word never replaces a built-in one.
		$registry = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::cli_words() : array();
		foreach ( $registry as $word => $rid ) {
			if ( ! isset( $map[ $word ] ) ) {
				$map[ $word ] = $rid;
			}
		}
		$types = array(
			'checkbox'     => 'multi',
			'multi'        => 'multi',
			'radio'        => 'list',
			'list'         => 'list',
			'dropdown'     => 'dropdown',
			'select'       => 'dropdown',
			'mul_dropdown' => 'mul_dropdown',
			'multiselect'  => 'mul_dropdown',
			'slider'       => 'slider',
			'colors'       => 'colors',
			'buttons'      => 'buttons',
			'text'         => 'text',
		);
		$names = array(
			'wpfCategory'  => 'wpf_filter_cat',
			'wpfTags'      => 'product_tag',
			'wpfBrand'     => 'product_brand',
			'wpfPrice'     => 'wpf_min_price,wpf_max_price,tax',
			'wpfPriceRange' => 'wpf_min_price,wpf_max_price,tax',
			'wpfInStock'   => 'pr_stock',
			'wpfOnSale'    => 'pr_onsale',
			'wpfFeatured'  => 'pr_featured',
			'wpfRating'    => 'pr_rating',
			'wpfSortBy'    => 'orderby',
			'wpfAuthor'    => 'pr_author',
			'wpfSearchText' => 'wpf_filter_text',
		);
		$order = array();
		foreach ( (array) $blocks as $b ) {
			if ( is_string( $b ) ) {
				$b = array( 'type' => $b );
			}
			$type = isset( $b['type'] ) ? strtolower( str_replace( '-', '_', (string) $b['type'] ) ) : '';
			if ( ! isset( $map[ $type ] ) ) {
				continue;
			}
			$id       = $map[ $type ];
			$settings = array(
				'f_enable'       => true,
				'f_enable_title' => 'yes_open',
				'f_title'        => isset( $b['title'] ) ? sanitize_text_field( $b['title'] ) : '',
				'f_show_count'   => isset( $b['show_count'] ) ? (string) (int) $b['show_count'] : '1',
			);
			$name = isset( $names[ $id ] ) ? $names[ $id ] : '';
			if ( 'wpfAttribute' === $id ) {
				$tax = isset( $b['taxonomy'] ) ? sanitize_key( $b['taxonomy'] ) : '';
				if ( ! taxonomy_exists( $tax ) && taxonomy_exists( 'pa_' . $tax ) ) {
					$tax = 'pa_' . $tax;
				}
				if ( ! taxonomy_exists( $tax ) || ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
					continue;
				}
				$settings['f_list'] = (string) wc_attribute_taxonomy_id_by_name( $tax );
				$name               = 'wpf_filter_' . str_replace( 'pa_', '', $tax );
				if ( '' === $settings['f_title'] && function_exists( 'wc_attribute_label' ) ) {
					$settings['f_title'] = wc_attribute_label( $tax );
				}
			} elseif ( 'wpfCustomField' === $id ) {
				if ( empty( $b['meta_key'] ) ) {
					continue;
				}
				$settings['f_mlist']    = sanitize_key( $b['meta_key'] );
				$settings['f_meta_key'] = $settings['f_mlist']; // the key the 4.0 engine reads for the field
				$name                = 'custom_field';
			}
			$regDef = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::get( $id ) : null;
			if ( '' === $settings['f_title'] ) {
				$labels = array(
					'wpfCategory' => __( 'Category', 'woo-product-filter' ),
					'wpfTags' => __( 'Tags', 'woo-product-filter' ),
					'wpfBrand' => __( 'Brand', 'woo-product-filter' ),
					'wpfPerfectBrand' => __( 'Brand', 'woo-product-filter' ),
					'wpfPrice' => __( 'Price', 'woo-product-filter' ),
					'wpfPriceRange' => __( 'Price', 'woo-product-filter' ),
					'wpfInStock' => __( 'Stock', 'woo-product-filter' ),
					'wpfOnSale' => __( 'On sale', 'woo-product-filter' ),
					'wpfFeatured' => __( 'Featured', 'woo-product-filter' ),
					'wpfRating' => __( 'Rating', 'woo-product-filter' ),
					'wpfSortBy' => __( 'Sort by', 'woo-product-filter' ),
					'wpfSearchText' => __( 'Search', 'woo-product-filter' ),
					'wpfAuthor' => __( 'Seller', 'woo-product-filter' ),
					'wpfCustomField' => __( 'Field', 'woo-product-filter' ),
				);
				$settings['f_title'] = isset( $labels[ $id ] ) ? $labels[ $id ] : ( $regDef ? $regDef['label'] : $id );
			}
			if ( $regDef && ! empty( $regDef['defaults'] ) ) {
				$settings = array_merge( $settings, $regDef['defaults'] ); // the block's own defaults, as the builder writes them
			}
			if ( ! empty( $b['display'] ) && isset( $types[ strtolower( $b['display'] ) ] ) ) {
				$settings['f_frontend_type'] = $types[ strtolower( $b['display'] ) ];
				$radioList                   = in_array( $id, array( 'wpfCategory', 'wpfBrand', 'wpfPerfectBrand' ), true );
				// 3.x types: on categories and brands "list" is the radio list and "multi" the tick boxes; elsewhere "radio" and "list".
				if ( ! $radioList && in_array( strtolower( $b['display'] ), array( 'radio' ), true ) ) {
					$settings['f_frontend_type'] = 'radio';
				} elseif ( ! $radioList && 'multi' === $settings['f_frontend_type'] ) {
					$settings['f_frontend_type'] = 'list';
				}
				$settings['logic'] = in_array( strtolower( $b['display'] ), array( 'radio', 'list' ), true ) && ( $radioList || 'radio' === strtolower( $b['display'] ) ) ? 'single' : 'or';
			} elseif ( in_array( $id, array( 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAttribute' ), true ) ) {
				// Tick boxes: "multi" on categories and brands, "list" on tags and attributes (3.x types).
				$settings['f_frontend_type'] = in_array( $id, array( 'wpfCategory', 'wpfBrand', 'wpfPerfectBrand' ), true ) ? 'multi' : 'list';
				$settings['logic']           = 'or';
			}
			if ( 'wpfCategory' === $id ) {
				// Keys the classic query path reads without isset().
				$settings['f_filtered_by_selected'] = isset( $settings['f_filtered_by_selected'] ) ? $settings['f_filtered_by_selected'] : '0';
			}
			if ( 'wpfSortBy' === $id && empty( $settings['f_options[]'] ) ) {
				$settings['f_options[]'] = 'default,popularity,price,price-desc,rating,date';
			}
			if ( 'wpfInStock' === $id && empty( $settings['f_options[]'] ) ) {
				$settings['f_options[]'] = 'instock,outofstock,onbackorder';
			}
			if ( ! empty( $b['settings'] ) && is_array( $b['settings'] ) ) {
				foreach ( $b['settings'] as $k => $v ) {
					if ( ( preg_match( '/^f_[a-z0-9_\[\]]+$/', (string) $k ) || in_array( (string) $k, array( 'show_more_rows', 'show_more_top', 'display_type', 'logic' ), true ) ) && ( is_scalar( $v ) || null === $v ) ) {
						$settings[ $k ] = is_bool( $v ) ? ( $v ? '1' : '0' ) : sanitize_text_field( (string) $v );
					}
				}
			}
			if ( isset( $settings['f_query_logic'] ) && 'and' === $settings['f_query_logic'] && ( ! isset( $settings['logic'] ) || 'single' !== $settings['logic'] ) ) {
				$settings['logic'] = 'and'; // "must have all picked values" (the 4.0 engine reads logic first)
			}
			$order[] = array(
				'id'       => $id,
				'name'     => $name,
				'uniqId'   => uniqid( 'wpf_' ),
				'settings' => $settings,
			);
		}
		return $order;
	}

	/**
	 * Create a filter row. $main = main settings (display_on_page etc.). Returns id or WP_Error.
	 */
	public static function createFilter( $title, $blocks, $main = array() ) {
		$order = self::blocksToOrder( $blocks );
		if ( empty( $order ) ) {
			return new WP_Error( 'wpf_no_blocks', __( 'No valid block given.', 'woo-product-filter' ) );
		}
		$settings = array_merge(
			array(
				'display_on_page'            => 'both',
				'enable_ajax'                => '1',
				'auto_update_filter'         => '1',
				'filter_recount'             => '1',
				'show_filter_immediately'    => '1',
				'product_container_selector' => 'ul.products',
			),
			self::sanitizeMain( $main ),
			array( 'filters' => array( 'order' => wp_json_encode( $order ) ) )
		);
		$model = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' );
		$id    = $model->save( array( 'title' => sanitize_text_field( $title ), 'settings' => $settings ) );
		if ( ! $id ) {
			return new WP_Error( 'wpf_save_failed', implode( ' ', (array) $model->getErrors() ) );
		}
		return (int) $id;
	}

	/**
	 * Only scalar keys with plain names are accepted from outside.
	 */
	public static function sanitizeMain( $main ) {
		$out = array();
		foreach ( (array) $main as $k => $v ) {
			if ( ! preg_match( '/^[a-z0-9_]+$/', (string) $k ) || 'filters' === $k ) {
				continue;
			}
			if ( is_bool( $v ) ) {
				$out[ $k ] = $v ? '1' : '0';
			} elseif ( is_scalar( $v ) || null === $v ) {
				$out[ $k ] = sanitize_text_field( (string) $v );
			} elseif ( is_array( $v ) ) {
				$out[ $k ] = array_map( 'sanitize_text_field', array_map( 'strval', $v ) );
			}
		}
		return $out;
	}

	/**
	 * Diagnostics as an array (Help module when present, else the basics).
	 */
	public static function diagnostics() {
		$help = WooBeWoo_PF_Frame::_()->getModule( 'help' );
		if ( $help && method_exists( $help, 'diagnostics' ) ) {
			return $help->diagnostics();
		}
		global $wp_version;
		return array(
			'wordpress'   => $wp_version,
			'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : null,
			'php'         => PHP_VERSION,
			'plugin'      => WPF_VERSION,
			'filters'     => (int) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getCount(),
		);
	}
}
