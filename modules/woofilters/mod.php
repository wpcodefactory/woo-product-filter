<?php

class WoofiltersWpf extends ModuleWpf {
	public $defaultWCQuery               = null;
	public $mainWCQuery                  = '';
	public $mainWCQueryFiltered          = '';
	public $shortcodeWCQuery             = array();
	public $shortcodeWCQueryFiltered     = array();
	public $shortcodeFilterKey           = 'wpf-filter-';
	public $currentFilterId              = null;
	public $currentFilterWidget          = true;
	public $renderModes                  = array();
	public $preselects                   = array();
	public $preFilters                   = array();
	public $displayMode                  = null;
	private $wcAttributes                = null;
	public static $loadShortcode         = array();
	public static $currentElementorClass = '';
	public $clauses                      = array();
	public $clausesLight                 = array();
	public $hookedClauses                = false;
	public $isLightMode                  = false;
	public $tempFilterTable              = 'wpf_temp_table';
	public $tempVarTable                 = 'wpf_temp_var_table';
	public $tempTables                   = array();
	public $metaKeys                     = null;
	public $originalWCQuery              = null;
	public static $otherShortcodeAttr    = array();
	public $clausesByParam               = array();
	public $fields                       = array();

	public function init() {
		DispatcherWpf::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		add_shortcode( WPF_SHORTCODE, array( $this, 'render' ) );
		add_shortcode( WPF_SHORTCODE_PRODUCTS, array( $this, 'renderProductsList' ) );
		add_shortcode( WPF_SHORTCODE_SELECTED_FILTERS, array( $this, 'renderSelectedFilters' ) );

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'showAdminErrors' ) );
		} elseif ( '1' !== ReqWpf::getVar( 'wpf_skip' ) ) {
			if ( ! class_exists( 'Popup_Maker' ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'addScriptsLisener' ), 999 );
			}
			add_filter( 'yith_wapo_disable_jqueryui', function ( $d ) {
				return true;
			}, 999 );
		} else {
			add_filter( 'woocommerce_redirect_single_search_result', function () {
				return false;
			} );
		}

		FrameWpf::_()->addScript( 'jquery-ui-autocomplete', '', array( 'jquery' ), false, true );

		add_action( 'woocommerce_product_query', array( $this, 'loadProductsFilter' ) );
		
		// for Woocommerce Blocks: Product Collection
		add_filter( 'query_loop_block_query_vars', array( $this, 'addFilterToWoocommerceBlocksAgrs' ), 999, 3 ); 

		add_action( 'woocommerce_shortcode_products_query', array( $this, 'loadShortcodeProductsFilter' ), 999, 3 );
		
		//for Beaver Builder block Posts
		add_filter( 'fl_builder_loop_query_args', function ( $args ) {
			if ( ! empty($args['fl_builder_loop']) && ! empty($args['post_type']) && ( ( is_array($args['post_type']) && in_array('product', $args['post_type']) ) || ( 'product' == $args['post_type'] ) ) ) {
				$args = $this->loadShortcodeProductsFilter( $args );
			}
			return $args;
		});
		add_filter( 'fl_builder_loop_query', function ( $q, $settings ) {
			if ( isset( $settings->data_source ) && 'main_query' == $settings->data_source ) {
				$this->loadProductsFilter( $q );
				if ( '' !== $this->mainWCQueryFiltered ) {
					$q = new WP_Query($this->mainWCQueryFiltered);
				} 
			}
			return $q;
		}, 999, 2);

		// Ultimate Addons for Elementor -> Woo – Products Widget
		add_filter( 'uael_woo_product_query_args', function ( $args, $settings ) {
			$args = $this->loadShortcodeProductsFilter( $args, array('wpf-compatibility' => 1) );
			return $args;
		}, 999, 2 );
		//add_filter('woocommerce_product_query_tax_query', array($this, 'customProductQueryTaxQuery'), 10, 1);

		add_action( 'woocommerce_shortcode_before_products_loop', array( $this, 'addWoocommerceShortcodeQuerySettings' ) );
		add_action( 'woocommerce_shortcode_before_sale_products_loop', array( $this, 'addWoocommerceShortcodeQuerySettings' ) );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active( 'woocommerce-product-feeds/woocommerce-gpf.php' ) ) {
			trait_exists( '\Essential_Addons_Elementor\Template\Content\Product_Grid' ) && add_action( 'pre_get_posts', array(
				$this,
				'loadProductsFilterForProductGrid',
			), 999 );
		}
		if ( ! is_admin() ) {
			if ( ReqWpf::getVar('customize_theme', 'get') != 'sydney' || ReqWpf::getVar('customize_messenger_channel', 'get') != 'preview-0' ) {
				add_filter( 'loop_shop_per_page', array( $this, 'newLoopShopPerPage' ), 99999 );
			}
		}

		class_exists( 'WC_pif' ) && add_filter( 'post_class', array( $this, 'WC_pif_product_has_gallery' ) );
		add_filter( 'yith_woocompare_actions_to_check_frontend', array( $this, 'addAjaxFilterForYithWoocompare' ), 20 );

		add_action( 'wp_loaded', function () {
			// removing action for theme Themify Ultra
			if ( class_exists( 'Tbp_Public' ) ) {
				remove_action( 'pre_get_posts', 'Tbp_Public::set_archive_per_page' );
			}

			if ( is_plugin_active( 'yith-woocommerce-ajax-product-filter-premium/init.php' ) && class_exists( 'YITH_WCAN_Query_Premium' ) ) {
				remove_filter( 'woocommerce_product_query', array( YITH_WCAN_Query_Premium::instance(), 'alter_product_query' ) );
			}
		} );

		add_filter( 'woocommerce_shortcode_products_query_results', array( $this, 'queryResults' ) );
		add_action( 'elementor/widget/before_render_content', array( $this, 'getElementorClass' ) );
		add_action( 'woocommerce_is_filtered', array( $this, 'isFiltered' ) );
		add_action( 'shortcode_atts_products', array( $this, 'shortcodeAttsProducts' ), 999, 3 );
		$this->setFilterClauses();
		
		if ( is_plugin_active( 'divi-bodycommerce/divi-bodyshop-woocommerce.php' ) ) {
			add_filter( 'db_archive_module_args', array( $this, 'replaceArgsIfBuilderUsed' ) );
		}
		if ( is_plugin_active( 'fusion-builder/fusion-builder.php' ) ) {
			if ( isset($_GET['wpf_skip']) ) {
				$_GET['wpf_skip'] = 2;
			}
			add_filter( 'fusion_post_cards_shortcode_query_args', array( $this, 'replaceArgsIfBuilderUsed' ) );
			add_filter( 'fusion_woo_product_grid_query_args', array( $this, 'replaceArgsIfBuilderGridUsed' ) );
		}
		if ( is_plugin_active( 'show-products-by-attributes-variations/addify_show_variation_single_product.php' ) ) {
			remove_all_actions( 'woocommerce_product_query', 100 );
		}

		add_action( 'pre_get_posts', array( $this, 'forceProductFilter' ), 9999 );
		add_filter( 'woocommerce_blocks_product_grid_is_cacheable', function () {
			return false;
		} );

		if ( is_plugin_active('elementor/elementor.php') ) {
			add_action('elementor/frontend/before_render', array($this, 'forceElementorProductFilter'));
			add_filter('elementor/widget/render_content', array($this, 'addElementorParamsToPagenationLinks'));
		}
		add_filter( 'woocommerce_product_object_query_args', array( $this, 'replaceArgsIfBuilderUsed' ) );

		if ( is_plugin_active( 'woolementor/woolementor.php' ) ) {
			add_filter( 'woolementor-product_query_params', array( $this, 'replaceArgsIfBuilderUsed' ) );
		}
		//Integration with Advanced Woo Search
		add_filter( 'aws_search_results_products_ids', array( $this, 'my_aws_search_results_products_ids') );
		add_filter( 'aws_search_page_filters', function ( $filters ) {
			if ( isset($_GET['pr_stock']) ) {
				unset($filters['in_status']);
			}
			return $filters;
		}, 99 );
		if ( isset($_GET['type_aws']) && isset($_GET['aws_filter']) && $this->isFiltered(false) ) {
			ReqWpf::clearVar('type_aws', 'get');
		}

		//Qi Addons For Elementor
		add_filter( 'qi_addons_for_elementor_filter_query_params', array( $this, 'replaceArgsIfBuilderGridUsed') );
		
		//Divi Plus
		add_filter('dipl_woo_products_args', array( $this, 'addFilterAgrsToQuery'));
		
		add_filter( 'pre_do_shortcode_tag', array( $this, 'getOtherShortcodeAttr' ), 10, 3 );
		
		$actions = array('woo_product_pagination', 'woo_product_pagination_product', 'get_woo_products', 'uael_get_products');
		if ( in_array(ReqWpf::getVar('action', 'post'), $actions) && ReqWpf::getVar('with_wpf_filter', 'post') ) {
			parse_str(ReqWpf::getVar('with_wpf_filter', 'post'), $addParams); 
			if ( is_array($addParams) ) {
				foreach ( $addParams as $k => $v ) {
					ReqWpf::setVar($k, urldecode($v), 'get');
				}
			}
		}
		// Theme Elford + Advanced Layout Build (Product Slider + Product Grid)
		add_filter( 'avia_product_slide_query', array($this, 'loadShortcodeProductsFilter'), 20, 1 );
		
		// Theme Bricks + Bricks Builder
		add_filter( 'bricks/posts/query_vars', array($this, 'loadShortcodeProductsFilter'), 20, 1 );

		if ( is_plugin_active( 'jet-woo-builder/jet-woo-builder.php' ) ) {
			add_filter('jet-woo-builder/shortcodes/jet-woo-products/final-query-args', array($this, 'replaceArgsIfJetWooBuilderUsed'));
		}
	}

	public function addFilterToWoocommerceBlocksAgrs( $args, $block, $page ) {
		if ( is_object($block) && $block instanceof WP_Block && ! empty($block->context['query']['isProductCollectionBlock']) ) {
			$args = $this->loadShortcodeProductsFilter( $args );
		}
		return $args;
	}
	
	public function addFilterAgrsToQuery( $args ) {
		$data   = ReqWpf::get( 'post' );
		$params = array();
		if ( is_array($data) && isset($data['action']) && ( 'dipl_get_woo_products' == $data['action'] ) && ! empty($data['query_vars']) ) {
			$params = $data['query_vars'];
		}
		foreach ( $params as $k => $v ) {
			ReqWpf::setVar($k, urldecode($v), 'get');
		}
		$args = $this->loadShortcodeProductsFilter( $args, array('wpf-compatibility' => 1) );
		return $args;
	}

	public function my_aws_search_results_products_ids( $ids ) {
		if ( ! $this->isFiltered(false) ) {
			return $ids;
		}

		$q = new WP_Query( DispatcherWpf::applyFilters( 'beforeFilterExistsTermsWithEmptyArgs', array(
			'post_type'   => 'product',
			'fields'      => 'ids',
			'meta_query'  => array('wpf_not_clauses' => 1),
			'tax_query'   => array(),
			'post__in'    => array_merge( array( 0 ), $ids ),
			'aws_post_in' => array_merge( array( 0 ), $ids ),
		) ) );
		
		$this->loadProductsFilter( $q );
		if ( ! empty($this->mainWCQueryFiltered) ) {
			unset($this->mainWCQueryFiltered['s']);
		}
		unset($this->mainWCQuery['s']);
		$args = ( '' !== $this->mainWCQueryFiltered ? $this->mainWCQueryFiltered : $this->mainWCQuery );
		if ( '' !== $this->mainWCQueryFiltered ) {
			if ( isset($this->mainWCQueryFiltered['fields']) && 'ids' === $this->mainWCQueryFiltered['fields'] ) {
				$args = $this->mainWCQueryFiltered;
			} else {
				$args = array_merge( $this->mainWCQueryFiltered, $this->mainWCQuery);
			}
		} else {
			$args = $this->mainWCQuery;
		}
		
		if ( ! ReqWpf::getVar('wpf_count') ) {
			$args['posts_per_page'] = count($ids);
		}
		
		$filterLoop = new WP_Query( $args );
		$ids        = $filterLoop->posts;
		
		add_filter('wpf_addFilterExistsItemsArgs', function ( $arg ) {
			if ( isset($arg['aws_post_in']) ) {
				$arg['post__in'] = $arg['aws_post_in'];
			}
			return $arg;
		}, 99999999 );
		
		return $ids;
	}

	public function forceElementorProductFilter( $widget ) {
		$paged      = get_query_var( 'paged' );
		$orderby    = get_query_var( 'orderby' );
		$widgetName = $widget->get_name();

		$exclude = array('section', 'column', 'social-icons', 'shortcode', 'heading', 'text-editor', 'icon-list', 'image', 'navigation-menu', 'hfe-cart', 'site-logo', 'icon');
		if ( ! in_array($widgetName, $exclude) && ( '' !== $this->mainWCQueryFiltered || $this->isFiltered(false) ) ) {
			// besa-site-logo: for compatibiliry with Besa Theme
			if ( ( $paged > 0 && 'popularity' != $orderby && 'shop-standard' != $widgetName ) 
				|| ( in_array($widgetName, array('archive-posts', 'besa-site-logo')) && get_query_var( 'wpf_query' ) == 1 ) ) {
				if ( '' !== $this->mainWCQueryFiltered ) {
					$this->mainWCQueryFiltered['paged'] = $paged;
					$this->setNewWPQuery($GLOBALS['wp_query'], $this->mainWCQueryFiltered);
				}
			} elseif ( 'eael-woo-product-gallery' == $widgetName ) {
					trait_exists( '\Essential_Addons_Elementor\Template\Content\Product_Grid' ) && add_action( 'pre_get_posts', array(
						$this,
						'loadProductsFilterForProductGrid',
					), 999 );
			} elseif ( in_array($widgetName, array('aux_advance_recent_product', 'premium-woo-products', 'shop-standard', 'pp-woo-products', 'ucaddon_woocommerce_product_grid')) ) {
				$this->mainWCQueryFiltered = '';
				add_action( 'pre_get_posts', array($this, 'loadProductsFilterForProductGrid'), 999 );
			}
		}
	}

	public function addElementorParamsToPagenationLinks( $widget_content ) {
		$pattern = '/<a\s+[^>]*class=["\'][^"\']*page-numbers[^"\']*["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i';
		
		return preg_replace_callback($pattern, function ( $matches ) {            
			$originalUrl = $matches[1];            
			$urlParts    = parse_url(html_entity_decode($originalUrl));            
		   
			$existingParams = array();
			if ( isset($urlParts['query']) ) {
				parse_str($urlParts['query'], $existingParams);
			}

			$get = ReqWpf::get('get');
			foreach ( $get as $key => $value ) {
				if ( strpos($key, 'e-page-') === 0 || 'product-page' === $key ) {
					unset($get[$key]);
				}
			}

			$newParams = array_merge($existingParams, $get);
			$scheme    = ( isset($urlParts['scheme']) ? $urlParts['scheme'] : '' );
			$baseUrl   = ( empty($scheme) ? '' : $scheme . '://' ) .
				( isset($urlParts['host']) ? $urlParts['host'] : '' ) .
				( isset($urlParts['path']) ? $urlParts['path'] : '' );
			$newUrl    = $baseUrl . '?' . http_build_query($newParams);
			return str_replace($originalUrl, $newUrl, $matches[0]);
		}, $widget_content);
	}   
	
	public function setNewWPQuery( $q, $args ) {
		$q = new WP_Query($args);
	}

	public function forceProductFilter( $query ) {
		$existFilter = false;
		$blocksApi   = false;
		if ( ! empty( $this->renderModes ) ) {
			foreach ( $this->renderModes as $mode ) {
				if ( ! empty($mode) ) {
					$existFilter = true;
					break;
				}
			}
		}
		if ( ! $existFilter ) {
			$uri       = empty($_SERVER['REQUEST_URI']) ? '' : sanitize_text_field($_SERVER['REQUEST_URI']);
			$blocksApi = strpos( $uri, 'wp-json/wc/store/') && strpos( $uri, '/products?');
		}
		if ( ! $existFilter ) {
			if ( ReqWpf::existGetVar('wpf_') ) {
				$existFilter = true;
			}
			if ( ReqWpf::getVar('action', 'post') == 'divi_filter_loadmore_ajax_handler' && ReqWpf::getVar('with_wpf_filter', 'post') ) {
				if ( isset( $query->query_vars['post_type'] ) && 'product' == $query->query_vars['post_type'] ) {
					parse_str(ReqWpf::getVar('with_wpf_filter', 'post'), $addParams); 
					if ( is_array($addParams) ) {
						foreach ( $addParams as $k => $v ) {
							ReqWpf::setVar($k, urldecode($v), 'get');
						}
						$this->loadProductsFilter( $query );
						return $query;
					}
				}
			}
		}

		$forced = false;
		if ( $existFilter || $blocksApi ) {
			if ( isset( $query->query_vars['post_type'] ) && 'product' === $query->query_vars['post_type'] && function_exists( 'debug_backtrace' ) ) {
				$needFiltered  = false;
				$changePerPage = false;
				$backtrace     = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
				if ( is_array( $backtrace ) ) {
					$classes = array(
						'Essential_Addons_Elementor\Elements\Product_Grid',
						'ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query',
						'DynamicContentForElementor\Widgets\DCE_Widget_DynamicPosts_Base',
						'DynamicContentForElementor\Widgets\DynamicPostsBase',
						'DCP_WooProducts',
						'Automattic\WooCommerce\Blocks\StoreApi\Utilities\ProductQuery',
						'Automattic\WooCommerce\StoreApi\Utilities\ProductQuery',
					);
					$classNF = array(
						'Essential_Addons_Elementor\Elements\Product_Grid',
						'ElementorPro\Modules\QueryControl\Classes\Elementor_Post_Query',
						'Automattic\WooCommerce\Blocks\BlockTypes\AbstractBlock',
					);
					$found   = ( ( isset( $backtrace[5]['class'] ) && 'Automattic\WooCommerce\Blocks\BlockTypes\AbstractProductGrid' === $backtrace[5]['class'] ) || ( isset( $backtrace[7]['class'] ) && in_array( $backtrace[7]['class'], $classes, true ) ) )
						? true
						: false;
					
					if ( $found ) {
						if ( isset( $backtrace[7]['class'] ) && in_array( $backtrace[7]['class'], $classNF, true ) ) {
							$needFiltered = true;
						}
					}

					if ( ! $found ) {
						$classList = array_column( $backtrace, 'class' );

						$searchList = array(
							'ElementPack\Modules\Woocommerce\Widgets\Products',
							'ElementPack\Modules\Woocommerce\Widgets\WC_Carousel',
						);
						foreach ( $searchList as $cl ) {
							if ( array_search( $cl, $classList ) > 5 ) {
								$found = true;
								break;
							}
						}
					}
					if ( ! $found ) {
						if ( isset( $backtrace[7]['class'] ) && 'WprAddons\Modules\WooGrid\Widgets\Wpr_Woo_Grid' === $backtrace[7]['class'] && in_array($backtrace[7]['function'], array('render', 'get_max_num_pages')) ) {
							$found        = true;
							$needFiltered = true;
						}
					}
					 
					if ( ! $found ) {
						$theme = wp_get_theme();
						if ( $theme instanceof WP_Theme ) {
							$themeName = ( '' !== $theme['Parent Theme'] ) ? $theme['Parent Theme'] : $theme['Name'];
							if ( 'Flatsome' === $themeName ) {
								$functionList = array_column( $backtrace, 'function' );
								if ( array_search('ux_list_products', $functionList) > 5 ) {
									$found         = true;
									$changePerPage = true;
									$needFiltered  = true;
								}
							}
						}
					}
					if ( ! $found ) {
						$functionList   = array_column( $backtrace, 'function' );
						$functionSearch = array('dnwoo_query_products', 'thegem_extended_products_get_posts');
						foreach ( $functionSearch as $f ) {
							if ( array_search($f, $functionList) > 5 ) {
								$found        = true;
								$needFiltered = true;
								break;
							}
						}
					}
					if ( ! $found ) {
						if ( ! empty($query->query_vars['store']) && $query->is_main_query() && empty($query->query_vars['wpf_query']) && $this->isVendor() ) {
							$found = true;
						}
					}

					if ( $found ) {
						if ( '' !== $this->mainWCQueryFiltered ) {
							if ( $needFiltered || $changePerPage ) {
								$this->mainWCQueryFiltered['paged']          = $query->get('paged');
								$this->mainWCQueryFiltered['posts_per_page'] = $query->get('posts_per_page');
							}
							$query->query_vars = $this->mainWCQueryFiltered;
							if ( $needFiltered ) {
								$query->set('no_found_rows', false);
							}                       
						} elseif ( '' !== $this->mainWCQuery && ! $needFiltered ) {
							if ( $changePerPage ) {
								$this->mainWCQuery['posts_per_page'] = $query->get('posts_per_page');
							}
							$query->query_vars = $this->mainWCQuery;
						} else {
							$this->loadProductsFilter( $query );
						}
						$forced = true;
					}
				}
			}

			if ( ! $forced && $query->is_main_query() && ! empty( $query->query_vars['wpf_query'] ) ) {
				$taxQuery = $query->get( 'tax_query' );
				if ( empty( $taxQuery['wpf_tax'] ) ) {
					$this->loadProductsFilter( $query );
				}
				if ( ReqWpf::getVar('wpf_count', 'get') ) {
					$query->set('posts_per_page', (int) ReqWpf::getVar('wpf_count', 'get'));
				}           
			}
		}
		if ( ! $forced && $query->is_search() && $query->is_main_query() && get_query_var('s', false) && $this->isFiltered(false) ) {
			$taxQuery = $query->get( 'tax_query' );
			if ( empty( $taxQuery['wpf_tax'] ) ) {
				$this->loadProductsFilter( $query );
			}       
		}

		if ( $this->isProductQuery($query->get('post_type')) && $this->isFiltered(false) && empty($query->query_vars['wpf_query']) ) {		
			if ( ! empty($this->mainWCQueryFiltered) ) {
				foreach ( $this->mainWCQueryFiltered as $key => $value ) {
					if ( ! in_array($key, array('paged', 'posts_per_page', 'post_type')) ) {
						$query->set($key, $value);
					}
				}
			
				$query->set('wpf_query', 1);
			} else {
				$this->loadProductsFilter($query);
			}
		}

		return $query;
	}
	
	public function replaceArgsIfBuilderGridUsed( $args ) {

		$paged = empty($args['paged']) ? 0 : $args['paged'];

		if ( isset( $this->mainWCQueryFiltered ) && ! empty( $this->mainWCQueryFiltered ) ) {
			$args = $this->mainWCQueryFiltered;
		} elseif ( isset( $this->mainWCQueryFiltered ) && ! empty( $this->mainWCQueryFiltered ) ) {
			$args = $this->mainWCQueryFiltered;
		}
		$args['paged'] = $paged;

		return $args;
	}

	public function replaceArgsIfBuilderUsed( $args ) {
		// For Woocommerce Lookup table regeneration
		if ( ! empty($args['return']) && ! empty($args['limit']) && ( 'ids' == $args['return'] ) && ( 1 == $args['limit'] ) ) {
			return $args;
		}
		// For TI WooCommerce Merkzettel
		if ( ReqWpf::getVar('wc-ajax') == 'tinvwl' ) {
			return $args;
		}
		// For WooCommerce Mix and Match Products
		if ( ! empty($args['query_id']) && ( 'wc_mnm_query_child_items_by_category' == $args['query_id'] ) ) {
			return $args;
		}
		// Skip filtering if FiboSearch is active with Divi theme to maintain compatibility
		if ( ReqWpf::getVar('dgwt_wcas') == 1 ) {
			return $args;
		}
		
		$paged = empty($args['paged']) ? 0 : $args['paged'];
		$flag  = empty($args['post_cards_query']) ? false : $args['post_cards_query'];
		$ret   = isset($args['return']) ? $args['return'] : false;
		
		if ( $flag && ! empty($args['taxonomy']) ) {
			return $args;
		}
		if ( isset( $this->mainWCQueryFiltered ) && ! empty( $this->mainWCQueryFiltered ) ) {
			$args = $this->mainWCQueryFiltered;
		} elseif ( isset( $this->mainWCQuery ) && ! empty( $this->mainWCQuery ) ) {
			$args = $this->mainWCQuery;
		}
		$args['paged'] = $paged;
		if ( $flag ) {
			$args['post_cards_query'] = $flag;
		}
		if ( false !== $ret ) {
			$args['return'] = $ret;
		}
		return $args;
	}

	public function replaceArgsIfJetWooBuilderUsed( $args ) {
		$tempArgs = array();
		$params   = array(
			'paged',
			'posts_per_page',            
			'nopaging', 
			'jet_smart_filters',
			'jet_use_current_query',
		);

		foreach ( $params as $param ) {
			if ( isset($args[$param]) ) {
				$tempArgs[$param] = $args[$param];
			}
		}

		if ( isset($this->mainWCQueryFiltered) && ! empty($this->mainWCQueryFiltered) ) {
			$args = $this->mainWCQueryFiltered;
		} elseif ( isset($this->mainWCQuery) && ! empty($this->mainWCQuery) ) {
			$args = $this->mainWCQuery;
		}
		
		foreach ( $tempArgs as $key => $value ) {           
			$args[$key] = $tempArgs[$key];           
		}
		return $args;
	}

	public function getTempTable( $table ) {
		return empty( $this->tempTables[ $table ] ) ? false : $this->tempTables[ $table ];
	}

	public function addFilterClauses( $clauses, $isLight = false, $urlParam = false ) {
		if ( empty( $clauses ) ) {
			return;
		}
		$saved = $isLight ? $this->clausesLight : $this->clauses;
		$lastI = 0;
		if ( empty( $saved ) ) {
			$saved = array( 'join' => array(), 'where' => array() );
		} else {
			foreach ( $saved as $key => $arr ) {
				foreach ( $arr as $i => $str ) {
					if ( $i > $lastI ) {
						$lastI = $i;
					}
				}
			}
		}

		if ( ! empty( $clauses['join'] ) ) {

			foreach ( $clauses['join'] as $i => $str ) {
				if ( ! empty( $str ) ) {
					$where = isset( $clauses['where'][ $i ] ) ? $clauses['where'][ $i ] : '';
					$jw    = $str . $where;
					$found = false;
					foreach ( $saved['join'] as $e => $s ) {
						if ( $s . ( isset( $saved['where'][ $e ] ) ? $saved['where'][ $e ] : '' ) == $jw ) {
							$found = true;
							break;
						}
					}
					if ( ! $found ) {
						++$lastI;
						$saved['join'][ $lastI ] = $str;

						if ( $urlParam ) {
							$this->clausesByParam[ $urlParam ]['join'][] = $str;
						}

						if ( ! empty( $where ) ) {
							$saved['where'][ $lastI ] = $where;

							if ( $urlParam ) {
								$this->clausesByParam[ $urlParam ]['where'][] = $where;
							}

							unset( $clauses['where'][ $i ] );
						}
					}
				}
			}
			unset( $clauses['join'] );
		}
		foreach ( $clauses as $key => $arr ) {
			foreach ( $arr as $i => $str ) {
				if ( ! isset( $saved[ $key ] ) ) {
					$saved[ $key ] = array();
				}
				if ( ! in_array( $str, $saved[ $key ] ) ) {
					++$lastI;
					$saved[ $key ][ $lastI ] = $str;
					if ( $urlParam ) {
						$this->clausesByParam[ $urlParam ][ $key ][] = $str;
					}
				}
			}
		}

		if ( $isLight ) {
			$this->clausesLight = $saved;
		} else {
			$this->clauses = $saved;
		}
	}

	public function setFilterClauses() {
		if ( ! $this->hookedClauses ) {
			add_filter( 'posts_clauses_request', array( $this, 'addFilterClausesRequest' ), 10, 2 );
			$this->hookedClauses = true;
		}
	}

	public function addFilterClausesRequest( $clauses, $wp_query ) {
		if ( ( ! empty( $wp_query->query_vars['wpf_query'] ) && $this->validPostType( $wp_query ) ) || ( $wp_query->is_main_query() && isset( $wp_query->query_vars['wc_query'] ) && ! empty( $wp_query->query_vars['wc_query'] ) && 'product_query' === $wp_query->query_vars['wc_query'] ) ) {
			$filterClauses = $this->isLightMode ? $this->clausesLight : $this->clauses;
			global $wpdb;
			foreach ( $filterClauses as $key => $data ) {
				foreach ( $data as $i => $str ) {
					if ( 'limits' === $key && '' === $str ) {
						$clauses[ $key ] = '';
					} elseif ( ! empty( $str ) ) {
						$sql = str_replace( '__#i', '_' . $i, $str );
						if ( false === strpos( $clauses[ $key ], $sql ) ) {
							$clauses[ $key ]   .= " $sql";
							$clauses['groupby'] = "{$wpdb->posts}.ID";
						}
					}
				}
			}
		}

		return $clauses;
	}

	public function validPostType( $wp_query ) {

		if ( ! isset( $wp_query->query_vars['post_type'] ) ) {
			return false;
		}

		if ( ! in_array( 'product', (array) $wp_query->query_vars['post_type'] ) ) {
			return false;
		}

		return true;
	}

	public function getMetaKeyId( $key, $field = 'id' ) {
		$key = strtolower( $key );
		if ( is_null( $this->metaKeys ) ) {
			$this->metaKeys = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_keys' )->getKeysWithCalcControl();
		}

		return isset( $this->metaKeys[ $key ] ) && ( 1 == $this->metaKeys[ $key ]['status'] ) ? $this->metaKeys[ $key ][ $field ] : false;
	}

	public function resetMetaKeys() {
		$this->metaKeys = null;
	}

	public function isFiltered( $filtered ) {
		$ignoreKey = array( 'wpf_count', 'wpf_fbv', 'wpf_dpv', 'wpf_skip', '_' );

		if ( ! $filtered ) {
			$get = ReqWpf::get( 'get' );
			foreach ( $get as $key => $val ) {
				if ( ! in_array( $key, $ignoreKey, true ) && ( 'orderby' === $key || strpos( $key, 'wpf_' ) === 0 || strpos( $key, 'product_tag' ) === 0 || strpos( $key, 'pr_' ) === 0 ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public function newLoopShopPerPage( $count ) {
		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
		if ( isset( $options['count_product_shop'] ) && isset( $options['count_product_shop']['value'] ) && ! empty( $options['count_product_shop']['value'] ) ) {
			$count = $options['count_product_shop']['value'];
		}

		return $count;
	}

	public function addWooOptions( $args ) {
		if ( get_option( 'woocommerce_hide_out_of_stock_items' ) == 'yes' ) {
			$args['meta_query'][] = array(
				'key'     => '_stock_status',
				'value'   => 'outofstock',
				'compare' => '!=',
			);
		}

		$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
		if ( isset( $options['hide_without_price'] ) && '1' === $options['hide_without_price']['value'] ) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array( '', 0 ),
				'compare' => 'NOT IN',
			);
		}

		return $args;
	}

	public function addScriptsLisener() {
		$js = 'if (typeof (window.wpfReadyList) == "undefined") {
			var v = jQuery.fn.jquery;
			if (v && parseInt(v) >= 3 && window.self === window.top) {
				var readyList=[];
				window.originalReadyMethod = jQuery.fn.ready;
				jQuery.fn.ready = function(){
					if(arguments.length && arguments.length > 0 && typeof arguments[0] === "function") {
						readyList.push({"c": this, "a": arguments});
					}
					return window.originalReadyMethod.apply( this, arguments );
				};
				window.wpfReadyList = readyList;
			}}';
		wp_add_inline_script( 'jquery', $js, 'after' );
	}

	public function setCurrentFilter( $id, $isWidget ) {
		$this->currentFilterId     = $id;
		$this->currentFilterWidget = $isWidget;
	}

	public function getPreselectedValue( $val = '' ) {
		if ( empty( $val ) ) {
			return $this->preselects;
		}

		return isset( $this->preselects[ $val ] ) ? $this->preselects[ $val ] : null;
	}

	public function addPreselectedParams( $need = false ) {
		if ( ! is_admin() || $need ) {
			if ( is_null( $this->currentFilterId ) ) {
				global $wp_registered_widgets;
				$filterWidget = 'wpfwoofilterswidget';

				$widgetOpions    = get_option( 'widget_' . $filterWidget );
				$sidebarsWidgets = wp_get_sidebars_widgets();
				$preselects      = array();
				$filters         = array();

				if ( is_array( $sidebarsWidgets ) && ! empty( $widgetOpions ) ) {
					foreach ( $sidebarsWidgets as $sidebar => $widgets ) {
						if ( ( 'wp_inactive_widgets' === $sidebar || 'orphaned_widgets' === substr( $sidebar, 0, 16 ) ) ) {
							continue;
						}
						if ( is_array( $widgets ) ) {
							foreach ( $widgets as $widget ) {
								$ids = explode( '-', $widget );

								// if the filter is added using the Legacy Widget
								if ( count( $ids ) == 2 && $ids[0] == $filterWidget ) {
									if ( isset( $widgetOpions[ $ids[1] ] ) && isset( $widgetOpions[ $ids[1] ]['id'] ) ) {
										$filterId = $widgetOpions[ $ids[1] ]['id'];

										if ( ! isset( $filters[ $filterId ] ) ) {
											$preselects           = array_merge( $preselects, $this->getPreselectedParamsForFilter( $filterId ) );
											$filters[ $filterId ] = 1;
										}
									}
								} elseif ( isset( $wp_registered_widgets[ $widget ] ) ) {
									// trying to find the filter shortcode in the text widget
									$opts    = $wp_registered_widgets[ $widget ];
									$id_base = is_array( $opts['callback'] ) ? $opts['callback'][0]->id_base : $opts['callback'];

									if ( ! $id_base ) {
										continue;
									}

									$instance = get_option( 'widget_' . $id_base );

									if ( ! $instance || ! is_array( $instance ) ) {
										continue;
									}

									foreach ( $instance as $item ) {
										$content = '';

										if ( isset( $item['text'] ) ) {
											$content = $item['text'];
										} elseif ( isset( $item['content'] ) ) {
											$content = $item['content'];
										}

										if ( '' !== $content ) {
											preg_match( '/\[wpf-filters\s+id="?(\d)+"?\]/', $content, $matches );
											if ( isset( $matches[1] ) ) {
												$filterId             = $matches[1];
												$preselects           = array_merge( $preselects, $this->getPreselectedParamsForFilter( $filterId ) );
												$filters[ $filterId ] = 1;
											}
										}
									}
								}
							}
						}
					}
				}
			} else {
				$preselects = $this->getPreselectedParamsForFilter( $this->currentFilterId );
			}

			$this->preselects = array();
			foreach ( $preselects as $value ) {
				if ( ! empty( $value ) ) {
					$paar = explode( '=', $value );
					if ( count( $paar ) == 2 ) {
						$name = $paar[0];
						$var  = $paar[1];
						if ( 'wpf_min_price' == $name || 'wpf_max_price' == $name ) {
							$var = $this->getCurrencyPrice( $var );
						}

						$this->preselects[ $name ] = $var;
					}
				}
			}
		}
	}

	public function getPreselectedParamsForFilter( $filterId ) {
		if ( ! isset( $this->preFilters[ $filterId ] ) ) {
			$preselects = array();
			$filter     = $this->getModel( 'woofilters' )->getById( $filterId );
			if ( $filter ) {
				$settings = unserialize( $filter['setting_data'] );
				$mode     = $this->getRenderMode( $filterId, $settings );
				if ( $mode > 0 ) {
					$preselect = ! empty( $settings['settings']['filters']['preselect'] ) ? $settings['settings']['filters']['preselect'] : '';
					if ( ! empty( $preselect ) ) {
						$preselects = explode( ';', $preselect );
					}
					if ( defined( 'WPF_FREE_REQUIRES' ) && version_compare( '1.4.9', WPF_FREE_REQUIRES, '==' ) ) {
						$preselects = DispatcherWpf::applyFilters( 'addDefaultFilterData', $preselects, $filterId, $settings );
					} else {
						DispatcherWpf::doAction( 'addDefaultFilterData', $filterId, $settings );
					}
				}
			}
			$this->preFilters[ $filterId ] = $preselects;
		}

		return $this->preFilters[ $filterId ];
	}

	public function searchValueQuery( $arrQuery, $key, $value, $delete = false ) {
		if ( ! empty( $arrQuery ) ) {
			foreach ( $arrQuery as $i => $q ) {
				if ( is_array( $q ) && isset( $q[ $key ] ) && $value == $q[ $key ] ) {
					if ( $delete ) {
						unset( $arrQuery[ $i ] );
					} else {
						return $i;
					}
				}
			}
		}

		return $arrQuery;
	}

	public function addCustomFieldsQuery( $data, $mode ) {
		$fields = array();
		if ( count( $data ) == 0 ) {
			return $fields;
		}

		if ( ! empty( $data['pr_onsale'] ) ) {
			$fields['post__in'] = array_merge( array( 0 ), wc_get_product_ids_on_sale() );
			if ( class_exists( '\com\itthinx\woocommerce\search\engine\Query_Control' ) ) {
				\com\itthinx\woocommerce\search\engine\Query_Control::do_pre_get_posts(false);
			}
		}
		if ( ! empty( $data['pr_author'] ) ) {
			$slugs = explode( '|', $data['pr_author'] );

			$userIds = array();
			foreach ( $slugs as $userSlug ) {
				$userObj = get_user_by( 'slug', $userSlug );
				if ( isset( $userObj->ID ) ) {
					$userIds[] = $userObj->ID;
				}
			}

			if ( ! empty( $userIds ) ) {
				$fields['author__in'] = $userIds;
			}
		}
		if ( ! empty( $data['vendors'] ) ) {
			$vendor = ReqWpf::getVar( 'vendors' );
			if ( empty($vendor) ) {
				$vendor = $data['vendors'];
			} 
			//$userObj = get_user_by( 'slug', ReqWpf::getVar( 'vendors' ) );
			$userObj = get_user_by( 'slug', $vendor );
			if ( isset( $userObj->ID ) ) {
				$fields['author'] = $userObj->ID;
			}
		}
		if ( ! empty( $data['wpf_count'] ) ) {
			$fields['posts_per_page'] = $data['wpf_count'];
		}

		$fields = DispatcherWpf::applyFilters( 'addCustomFieldsQueryPro', $fields, $data, $mode );

		return $fields;
	}

	public function addCustomMetaQuery( $metaQuery, $data, $mode ) {
		if ( ! is_array( $metaQuery ) ) {
			$metaQuery = array();
		}

		if ( count( $data ) == 0 ) {
			return $metaQuery;
		}
		$minPrice = ( isset( $data['wpf_min_price'] ) ) ? $data['wpf_min_price'] : null;
		$maxPrice = ( isset( $data['wpf_max_price'] ) ) ? $data['wpf_max_price'] : null;
		$price    = $this->preparePriceFilter( $minPrice, $maxPrice );

		if ( false != $price ) {
			$metaQuery = array_merge( $metaQuery, $price );
			remove_filter( 'posts_clauses', array( WC()->query, 'price_filter_post_clauses' ), 10, 2 );
		}
		if ( ! empty( $data['pr_onsale'] ) && ReqWpf::getVar( 'dgwt_wcas' ) ) {
			$metaQuery[] = array(
				'key'     => '_sale_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'numeric',
			);
		}
		if ( ! empty( $data['pr_stock'] ) ) {
			$slugs = explode( '|', $data['pr_stock'] );
			if ( $slugs ) {
				$metaQuery = $this->searchValueQuery( $metaQuery, 'key', '_stock_status', true );
				$metaKeyId = $this->getMetaKeyId( '_stock_status' );
				if ( $metaKeyId && empty($metaQuery['wpf_not_clauses']) ) {
					$values = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueIds( $metaKeyId, $slugs );
					$this->addWpfMetaClauses( array(
						'keyId'    => $metaKeyId,
						'isAnd'    => false,
						'values'   => $values,
						'field'    => 'id',
						'isLight'  => 'preselect' == $mode,
						'urlParam' => 'pr_stock',
					) );
				} else {
					$metaQuery[] = array(
						'key'     => '_stock_status',
						'value'   => $slugs,
						'compare' => 'IN',
					);
				}
			}
		}
		if ( ! empty( $data['pr_rating'] ) ) {
			$ratingRange = $data['pr_rating'];
			$range       = strpos( $ratingRange, '-' ) !== false ? explode( '-', $ratingRange ) : array( intval( $ratingRange ) );
			if ( isset( $range[1] ) && intval( $range[1] ) !== 5 ) {
				$range[1] = $range[1] - 0.001;
			}
			if ( $range[0] ) {
				$metaQuery = $this->searchValueQuery( $metaQuery, 'key', '_wc_average_rating', true );
				$isBetween = isset( $range[1] ) && $range[1];
				$metaKeyId = $this->getMetaKeyId( '_wc_average_rating' );
				if ( $metaKeyId ) {
					$this->addWpfMetaClauses( array(
						'keyId'    => $metaKeyId,
						'isAnd'    => ( $isBetween ? 'BETWEEN' : false ),
						'values'   => $range,
						'field'    => 'int',
						'isLight'  => 'preselect' == $mode,
						'urlParam' => 'pr_rating',
					) );
				} elseif ( $isBetween ) {
						$metaQuery[] = array(
							'key'     => '_wc_average_rating',
							'value'   => array( $range[0], $range[1] ),
							'type'    => 'DECIMAL',
							'compare' => 'BETWEEN',
						);
				} else {
					$metaQuery[] = array(
						'key'     => '_wc_average_rating',
						'value'   => $range[0],
						'type'    => 'DECIMAL',
						'compare' => '=',
					);
				}
			}
		}
		$metaQuery = DispatcherWpf::applyFilters( 'addCustomMetaQueryPro', $metaQuery, $data, $mode );

		return $metaQuery;
	}

	public function addCustomTaxQuery( $taxQuery, $data, $mode ) {

		if ( ! is_array( $taxQuery ) ) {
			$taxQuery = array();
		}

		$isPreselect = ( 'preselect' == $mode || ReqWpf::getVar('wpf_preselects') == '1' );
		$isSlugs     = ( 'url' == $mode && ! $isPreselect );
		
		// custom tahonomy attr block
		if ( ! empty( $taxQuery ) ) {
			foreach ( $taxQuery as $i => $tax ) {
				if ( is_array( $tax ) && isset( $tax['field'] ) && 'slug' == $tax['field'] ) {
					$name = str_replace( 'pa_', 'wpf_filter_', $tax['taxonomy'] );
					if ( $isPreselect && ReqWpf::getVar( $name ) && strpos($name, 'wpf_filter_') !== false ) {
						unset( $taxQuery[ $i ] );
						continue;
					}
					if ( ! empty( $data[ $name ] ) ) {
						$param = $data[ $name ];
						$slugs = explode( '|', $param );
						if ( count( $slugs ) > 1 ) {
							$taxQuery[ $i ]['terms']    = $slugs;
							$taxQuery[ $i ]['operator'] = 'IN';
						}
					}
				}
			}
		}

		if ( count( $data ) == 0 ) {
			return $taxQuery;
		}

		foreach ( $data as $key => $param ) {
			if ( is_string( $param ) ) {
				$isNot = ( substr( $param, 0, 1 ) === '!' );
				if ( $isNot ) {
					$param = substr( $param, 1 );
				}
				if ( strpos( $key, 'wpf_filter_cat_list' ) !== false ) {
					if ( ! empty( $param ) ) {
						$idsAnd     = explode( ',', $param );
						$idsOr      = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'product_cat',
							'field'            => ( substr( $key, - 1 ) == 's' ? 'slug' : 'term_id' ),
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => false,
						);
					}
				} elseif ( strpos( $key, 'wpf_filter_cat_' ) !== false || ( 'filter_cat' == $key ) ) {
					if ( ! empty( $param ) ) {
						$idsAnd     = explode( ',', $param );
						$idsOr      = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'product_cat',
							'field'            => ( substr( $key, - 1 ) == 's' ? 'slug' : 'term_id' ),
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => true,
						);
					}
				} elseif ( strpos( $key, 'product_tag' ) === 0 ) {
					if ( ! empty( $param ) ) {
						$idsAnd     = explode( ',', $param );
						$idsOr      = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'product_tag',
							'field'            => $isSlugs ? 'slug' : 'id',
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => true,
						);
					}
				} elseif ( ( strpos( $key, 'product_brand' ) === 0 || ( strpos( $key, 'wpf_filter_brand' ) === 0 && ! taxonomy_exists('pa_brand') ) ) && taxonomy_exists('product_brand') && !is_admin() ) {
					if ( ! empty( $param ) ) {
						$idsOr      = explode( ',', $param );
						$idsAnd     = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'product_brand',
							'field'            => $isSlugs ? 'slug' : 'id',
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => true,
						);
					}
				} elseif ( strpos( $key, 'wpf_filter_pwb_list' ) !== false ) {
					if ( ! empty( $param ) ) {
						$idsAnd     = explode( ',', $param );
						$idsOr      = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'pwb-brand',
							'field'            => 'term_id',
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => false,
						);
					}
				} elseif ( strpos( $key, 'wpf_filter_pwb' ) !== false ) {
					if ( ! empty( $param ) ) {
						$idsAnd     = explode( ',', $param );
						$idsOr      = explode( '|', $param );
						$isAnd      = count( $idsAnd ) > count( $idsOr );
						$taxQuery[] = array(
							'taxonomy'         => 'pwb-brand',
							'field'            => 'term_id',
							'terms'            => $isAnd ? $idsAnd : $idsOr,
							'operator'         => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							'include_children' => true,
						);
					}
				} elseif ( strpos( $key, 'pr_filter' ) !== false || strpos( $key, 'pr_wpf_filter' ) !== false ) {
					if ( ! empty( $param ) ) {
						$exeptionalLogic = 'not_in';
						$logic           = $this->getAttrFilterLogic();
						if ( ! empty( $logic['delimetr'][ $exeptionalLogic ] ) ) {
							$key        = str_replace( 'pr_wpf_filter', 'pr_filter', $key );
							$ids        = explode( $logic['delimetr'][ $exeptionalLogic ], $param );
							$taxonomy   = str_replace( 'pr_filter_', 'pa_', $key );
							$taxonomy   = preg_replace( '/_\d{1,}/', '', $taxonomy );
							$taxQuery[] = array(
								'taxonomy' => $taxonomy,
								'field'    => 'slug',
								'terms'    => $ids,
								'operator' => $logic['loop'][ $exeptionalLogic ],
							);
						}
					}
				} elseif ( strpos( $key, 'wpf_filter_' ) === 0 ) {
					if ( ! empty( $param ) ) {
						$idsAnd    = explode( ',', $param );
						$idsOr     = explode( '|', $param );
						$isAnd     = count( $idsAnd ) > count( $idsOr );
						$attrIds   = $isAnd ? $idsAnd : $idsOr;
						$taxExists = false;
						if ( $isSlugs ) {
							$taxonomies = array();

							$taxonomy     = str_replace( 'wpf_filter_', '', $key );
							$taxonomies[] = $taxonomy;
							$taxonomies[] = 'pa_' . $taxonomy;

							$taxonomy     = preg_replace( '/_\d+$/', '', $taxonomy );
							$taxonomies[] = $taxonomy;
							$taxonomies[] = 'pa_' . $taxonomy;

							foreach ( $taxonomies as $taxonomy ) {
								$taxExists = taxonomy_exists( $taxonomy );
								if ( $taxExists ) {
									break;
								}
							}
						} else {
							$taxonomy = '';
							foreach ( $attrIds as $attr ) {
								$term = get_term( $attr );
								if ( $term ) {
									$taxonomy  = $term->taxonomy;
									$taxExists = true;
									break;
								}
							}
						}
						if ( $taxExists ) {
							$taxQuery[] = array(
								'taxonomy' => $taxonomy,
								'field'    => $isSlugs ? 'slug' : 'id',
								'terms'    => $attrIds,
								'operator' => $isNot ? 'NOT IN' : ( $isAnd ? 'AND' : 'IN' ),
							);
						}
					}
				}
			}
		}

		if ( ! empty( $data['pr_featured'] ) ) {
			$taxQuery   = $this->searchValueQuery( $taxQuery, 'taxonomy', 'product_visibility', true );
			$taxQuery[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
			);
		}
		$taxQuery = DispatcherWpf::applyFilters( 'addCustomTaxQueryPro', $taxQuery, $data, $mode );

		return $taxQuery;
	}

	public function restoreOriginalQuery( $widget ) {
		global $wp_query;
		if ( 'WC_Widget_Layered_Nav_Filters' === $widget ) {
			$wp_query->set( 'product_cat', $this->originalWCQuery->query['product_cat'] );
		}
	}
	public function getVendor() {
		if ( class_exists('WC_Vendors') ) {
			$vendor_shop = urldecode( get_query_var( 'vendor_shop' ) );
			return WCV_Vendors::get_vendor_id( $vendor_shop );
		}

		if ( is_plugin_active( 'dokan-lite/dokan.php' ) ) {
			$custom_store_url = dokan_get_option( 'custom_store_url', 'dokan_general', 'store' );
			return get_query_var( $custom_store_url );
		}
	}

	public function loadProductsFilter( $q ) {
		$this->addPreselectedParams();

		if ( ReqWpf::getVar( 'all_products_filtering' ) ) {

			$this->originalWCQuery = $q;
			add_action( 'the_widget', array( $this, 'restoreOriginalQuery' ) );

			$exclude = array( 'paged', 'posts_per_page', 'post_type', 'wc_query', 'orderby', 'order', 'fields' );
			foreach ( $q->query_vars as $queryVarKey => $queryVarValue ) {
				if ( ! in_array( $queryVarKey, $exclude ) ) {
					if ( is_string( $queryVarValue ) ) {
						$q->set( $queryVarKey, '' );
					}
					if ( is_array( $queryVarValue ) ) {
						$q->set( $queryVarKey, array() );
					}
				}
			}
			$hiddenTerm = get_term_by( 'name', 'exclude-from-catalog', 'product_visibility' );
			if ( $hiddenTerm ) {
				$taxQ   = array('relation' => 'AND');
				$taxQ[] = array(
					'taxonomy' => 'product_visibility',
					'field'    => 'term_taxonomy_id',
					'terms'    => array( $hiddenTerm->term_taxonomy_id ),
					'operator' => 'NOT IN',
				);
				$q->set('tax_query', $taxQ);
			}
		} else {
			$search = ReqWpf::getVar( 's' );
			if ( ! is_admin() && ! is_null( $search ) && ! empty( $search ) ) {
				$q->set( 's', $search );
			}
		}
		$this->defaultWCQuery = $q->query_vars;
		
		$metaQuery = $q->get( 'meta_query' );
		$taxQuery  = $q->get( 'tax_query' );
		if ( ! is_array( $taxQuery ) ) {
			$taxQuery = array();
		}
		$taxQuery['wpf_tax'] = 1;

		// set preselects
		$mode       = 'preselect';
		$preselects = $this->getPreselectedValue();
		$fields     = $this->addCustomFieldsQuery( $preselects, $mode );
		$metaQuery  = $this->addCustomMetaQuery( $metaQuery, $preselects, $mode );
		$taxQuery   = $this->addCustomTaxQuery( $taxQuery, $preselects, $mode );

		$q->set( 'meta_query', $metaQuery );
		$q->set( 'tax_query', $this->groupTaxQueryArgs( $taxQuery ) );
		foreach ( $fields as $key => $value ) {
			$q->set( $key, $value );
		}

		// added an additional check, since meta_query can be added by other plugins and, as a result, the request crashed
		if ( empty( $q->get( 'meta_query' ) ) || 'product_query' === $q->get( 'wc_query' ) ) {
			$q->set( 'post_type', 'product' );
			$q->set( 'type', array_merge( array_keys( wc_get_product_types() ) ) );
		}
		$q->set( 'wpf_query', 1 );
		$this->mainWCQuery = $q->query_vars;

		$isMultiLogicOr = false;
		$filters        = FrameWpf::_()->getModule( 'woofilters' )->getModel()->getFromTbl();       

		$isUseCategoryFiltration = false;
		$categoryPageId          = null;
		foreach ( $filters as $key => $filter ) {
			$filtersSettings = unserialize( $filter['setting_data'] );
			$multiLogic      = $this->getFilterSetting( $filtersSettings['settings'], 'f_multi_logic', 'and' );

			if ( 'or' === $multiLogic ) {
				$isMultiLogicOr = true;
				break;
			}
		   
			if ( $this->getFilterSetting($filtersSettings['settings'], 'use_category_filtration') ) {
				$isUseCategoryFiltration = true;
				if ( is_null($categoryPageId) ) {
					$categoryPageId = ReqWpf::getVar( 'wpf_filter_cat_' . $key );  
				}          
			}
		}

		if ( $isMultiLogicOr ) {
			ReqWpf::setVar( 'wpf_light', $q->query_vars, 'session' );
		}
		
		if ( DispatcherWpf::applyFilters('notFilterMainWCQuery', false) ) {
			return;
		}

		$this->fields = array();
		$args         = $this->getQueryVars( $this->mainWCQuery );

		if ( $this->mainWCQuery !== $args ) {

			$q->set( 'meta_query', $args['meta_query'] );
			$q->set( 'tax_query', $args['tax_query'] );
			foreach ( $this->fields as $key => $value ) {
				$q->set( $key, $value );
			}
		}

		if ( ReqWpf::getVar( 'wpf_order' ) ) {
			add_filter( 'posts_clauses', array( $this, 'addClausesTitleOrder' ) );
		}
		
		$orderby = ReqWpf::getVar( 'orderby' );
		if ( is_null($orderby) || empty($orderby) ) {
			if ( isset($args['wpf_default']) && ! empty($args['wpf_default']['pr_sortby']) ) {
				$orderby = $args['wpf_default']['pr_sortby'];
			}
		}
		if ( $orderby && FrameWpf::_()->getModule('options')->get('disable_plugin_sorting') != 1 ) {
			switch ( $orderby ) {
				case 'price':
					add_filter( 'posts_clauses', array( $this, 'addPriceOrder' ), 99999 );
					break;
				case 'price-desc':
					add_filter( 'posts_clauses', array( $this, 'addPriceOrderDesc' ), 99999 );
					break;
				case 'sku':
					add_filter( 'posts_clauses', array( $this, 'addSKUOrder' ), 99999 );
					break;
				case 'sku-desc':
					add_filter( 'posts_clauses', array( $this, 'addSKUOrderDesc' ), 99999 );
					break;
				case 'date-asc':
					add_filter('posts_clauses', array($this, 'addDateOrderAsc'), 99999 );
					break;
				case 'date':
					add_filter('posts_clauses', array( $this, 'addDateOrder' ), 99999 );
					break;
				case 'popularity':
					add_filter( 'posts_clauses', array( $this, 'addPopularityOrder' ), 99999 );
					break;
				case 'title':
					add_filter('posts_clauses', array($this, 'addTitleOrderAsc'), 99999 );
					break;
				case 'title-desc':
					add_filter('posts_clauses', array($this, 'addTitleOrderDesc'), 99999 );
					break;
			}
		}
		if ( FrameWpf::_()->proVersionCompare( '1.4.8' ) ) {
			$filterSettings = array();
			$params         = array();
			if ( ReqWpf::getVar( 'wpf_fbv' ) ) {
				$filterSettings['filtering_by_variations'] = 1;
				$params                                    = ReqWpf::get( 'get' );
			}
			if ( ReqWpf::getVar( 'wpf_ebv' ) ) {
				$filterSettings['exclude_backorder_variations'] = 1;
			}
			if ( ReqWpf::getVar( 'wpf_dpv' ) ) {
				$filterSettings['display_product_variations'] = 1;
			}
			$args = array(
				'tax_query'  => $q->get( 'tax_query' ),
				'meta_query' => $q->get( 'meta_query' ),
				'post__in'   => $q->get( 'post__in' ),
			);
			$args = $this->addBeforeFiltersFrontendArgs( $args, $filterSettings, $params );
			$q->set( 'post__in', $args['post__in'] );
			$q->set( 'tax_query', $args['tax_query'] );
		}

		$q = DispatcherWpf::applyFilters( 'loadProductsFilterPro', $q );
		
		if ( $this->mainWCQuery !== $q->query_vars ) {
			$this->mainWCQueryFiltered = $q->query_vars;
		}
		// removes hooks that could potentially override filter settings
		remove_all_filters( 'wpv_action_apply_archive_query_settings' );

	  
		// allow show subcategories only if nothing is selected        
		if ( $this->isFiltered( false ) ) {
			remove_filter( 'woocommerce_product_loop_start', 'woocommerce_maybe_show_product_subcategories' );
			//compatibility with Product Table for WooCommerce by CodeAstrology (WooproductTable)
			if ( ! empty($q->get('wpt_query_type')) ) {
				$q->set('suppress_filters', 0);
			}
		}

		if ( $isUseCategoryFiltration ) {
			if ( is_null($categoryPageId) && is_product_category() ) {                
				$categoryPageId = get_queried_object_id();
			}
			if ( ! is_null($categoryPageId) && $this->isFiltered( false ) ) {
				if ( $this->needSubcategoriesDisplay($categoryPageId) ) {
					wc_set_loop_prop('is_filtered', false);
				}
				add_filter('woocommerce_product_loop_start', function ( $args ) use ( $categoryPageId ) {
					return $this->maybeShowProductSubcategories($args, $categoryPageId);
				});
			}     
		}
	}
	public function needSubcategoriesDisplay( $categoryPageId ) {
		$displayType = get_term_meta($categoryPageId, 'display_type', true);
		$displayType = '' === $displayType ? get_option('woocommerce_category_archive_display', '') : $displayType;
		if ( in_array($displayType, array('subcategories', 'both'), true) ) {
			$subcategories = woocommerce_get_product_subcategories($categoryPageId);
			return ! empty($subcategories);
		}
		return false;
	}
	public function setSubcategoriesLink( $link ) {
		$curUrl = isset($_SERVER['REQUEST_URI']) ? parse_url( esc_url_raw( $_SERVER['REQUEST_URI'] ) ) : array();
		$catUrl = parse_url($link);
		if ( ! empty($curUrl['query']) ) {
			$link .= ( empty($catUrl['query']) ? '?' : '&' ) . $curUrl['query'];
		}
		return $link;
	}
	public function maybeShowProductSubcategories( $loop_html, $categoryPageId ) {
		$display_type = woocommerce_get_loop_display_mode();
		if ( 'subcategories' === $display_type || 'both' === $display_type ) {
			add_filter('term_link', array( $this, 'setSubcategoriesLink' ), 99 );
			$taxonomies  = $this->getFilterTaxonomies(array(), true);
			$filterItems = $this->getFilterExistsItems($this->mainWCQueryFiltered, $taxonomies, $categoryPageId, $categoryPageId);
			$categoryIn  = isset($filterItems['categories']) ? $filterItems['categories'] : array();
			if ( count($categoryIn) > 0 ) {
				ob_start();
				$catIds = array_keys($categoryIn);
				$cats   = get_terms( array(
					'taxonomy' => 'product_cat',
					'include'  => $catIds,
				) );
				foreach ( $cats as $category ) {
					$cnt             = $categoryIn[$category->term_id];
					$category->count = $cnt;
					wc_get_template('content-product_cat.php', array('category' => $category));
				}
				$loop_html .= ob_get_clean();
			}
			remove_filter('term_link', array( $this, 'setSubcategoriesLink' ), 99 );
			if ( 'subcategories' === $display_type ) {
				wc_set_loop_prop( 'total', 0 );
				global $wp_query;
				if ( $wp_query->is_main_query() ) {
					$wp_query->post_count    = 0;
					$wp_query->max_num_pages = 0;
				}
			}
		}
		return $loop_html;
	}

	public function getQueryVars( $args, $exludeParam = array(), $params = array() ) {
		// set url params
		$mode = 'url';
		if ( empty( $params ) ) {
			$params = ReqWpf::get( 'get' );
		}
		

		if ( ! empty( $exludeParam ) && isset( $params[ $exludeParam ] ) ) {
			unset( $params[ $exludeParam ] );
		}

		if ( count( $params ) === 0 || ( empty($params['isFiltered']) && ! $this->isFiltered(false) ) ) {
			$mode   = 'default';
			$params = DispatcherWpf::applyFilters( 'getDefaultFilterParams', $params );
			if ( count( $params ) > 0 ) {
				$args['wpf_default'] = $params;
			}
		}

		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}

		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}

		if ( count( $params ) > 0 || ! empty($this->preselects) ) {
			$taxQuery           = $this->addCustomTaxQuery( $args['tax_query'], $params, $mode );
			$params             = array_merge( $this->preselects, $params );
			$this->fields       = $this->addCustomFieldsQuery( $params, $mode );
			$metaQuery          = $this->addCustomMetaQuery( $args['meta_query'], $params, $mode );
			$args['meta_query'] = $metaQuery;
			$args['tax_query']  = $this->groupTaxQueryArgs( $taxQuery );
			foreach ( $this->fields as $key => $value ) {
				$args[ $key ] = $value;
			}
			if ( empty( $args['post_type'] ) ) {
				$args['post_type'] = 'product';
			}
		}

		return $args;
	}

	public function addPriceOrder( $args ) {
		global $wpdb;
		if ( function_exists('wcpbc_the_zone') && wcpbc_the_zone() ) {
			if ( strpos($args['join'], ' wpf_price_wcpbc ') === false ) {
				$key           = '_' . wcpbc_the_zone()->get_id() . '_price';
				$args['join'] .= 
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price ON (wpf_price.post_id=' . $wpdb->posts . ".ID AND wpf_price.meta_key='_price')" .
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price_wcpbc ON (wpf_price_wcpbc.post_id=' . $wpdb->posts . ".ID AND wpf_price_wcpbc.meta_key='" . $key . "')";
			}
			$args['orderby'] = ' IFNULL(CAST(wpf_price_wcpbc.meta_value AS DECIMAL(20,3)), CAST(wpf_price.meta_value AS DECIMAL(20,3))) ASC, ' . $wpdb->posts . '.ID ';
		} else {
			$metaKeyId = $this->getMetaKeyId( '_price' );
			if ( $metaKeyId ) {
				$metaDataTable = DbWpf::getTableName( 'meta_data' );
				//$args['join']   .= ' LEFT JOIN ' . $metaDataTable . ' AS wpf_price_order ON (wpf_price_order.product_id=' . $wpdb->posts . '.ID AND wpf_price_order.key_id=' . $metaKeyId . ')';
				//$args['orderby'] = ' wpf_price_order.val_dec ASC, wpf_price_order.product_id ';
				$func            = ( FrameWpf::_()->getModule('options')->get('use_max_price') == 1 ? 'max' : 'min' );
				$args['join']   .= ' LEFT JOIN (SELECT wpf_t.product_id, ' . $func . '(wpf_t.val_dec) as wpf_price FROM ' . $metaDataTable . ' as wpf_t WHERE wpf_t.key_id=' . $metaKeyId . ' GROUP BY wpf_t.product_id) as wpf_price_order ON (wpf_price_order.product_id=' . $wpdb->posts . '.ID)';
				$args['orderby'] = ' wpf_price_order.wpf_price ASC, ' . $wpdb->posts . '.ID ';
			
			} else {
				$args['join']   .= ' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price_order ON (wpf_price_order.post_id=' . $wpdb->posts . ".ID AND wpf_price_order.meta_key='_price')";
				$args['orderby'] = ' CAST(wpf_price_order.meta_value AS DECIMAL(20,3)) ASC, ' . $wpdb->posts . '.ID ';
			}
		}
		remove_filter( 'posts_clauses', array( $this, 'addPriceOrder' ) );

		return $args;
	}

	public function addPriceOrderDesc( $args ) {
		global $wpdb;
		if ( function_exists('wcpbc_the_zone') && wcpbc_the_zone() ) {
			if ( strpos($args['join'], ' wpf_price_wcpbc ') === false ) {
				$key           = '_' . wcpbc_the_zone()->get_id() . '_price';
				$args['join'] .= 
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price ON (wpf_price.post_id=' . $wpdb->posts . ".ID AND wpf_price.meta_key='_price')" .
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price_wcpbc ON (wpf_price_wcpbc.post_id=' . $wpdb->posts . ".ID AND wpf_price_wcpbc.meta_key='" . $key . "')";
			}
			$args['orderby'] = ' IFNULL(CAST(wpf_price_wcpbc.meta_value AS DECIMAL(20,3)), CAST(wpf_price.meta_value AS DECIMAL(20,3))) DESC, ' . $wpdb->posts . '.ID ';
		} else {
			$metaKeyId = $this->getMetaKeyId( '_price' );
			if ( $metaKeyId ) {
				$metaDataTable   = DbWpf::getTableName( 'meta_data' );
				$args['join']   .= ' LEFT JOIN (SELECT wpf_t.product_id, max(wpf_t.val_dec) as wpf_price FROM ' . $metaDataTable . ' as wpf_t WHERE wpf_t.key_id=' . $metaKeyId . ' GROUP BY wpf_t.product_id) as wpf_price_order ON (wpf_price_order.product_id=' . $wpdb->posts . '.ID)';
				$args['orderby'] = ' wpf_price_order.wpf_price DESC, ' . $wpdb->posts . '.ID ';
			} else {
				$args['join']   .= ' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price_order ON (wpf_price_order.post_id=' . $wpdb->posts . ".ID AND wpf_price_order.meta_key='_price')";
				$args['orderby'] = ' CAST(wpf_price_order.meta_value AS DECIMAL(20,3)) DESC, ' . $wpdb->posts . '.ID ';
			}
		}
		remove_filter( 'posts_clauses', array( $this, 'addPriceOrderDesc' ) );

		return $args;
	}
	public function addPopularityOrder( $args ) {
		global $wpdb;
		$args['join']   .= ' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_popularity_order ON (wpf_popularity_order.post_id=' . $wpdb->posts . ".ID AND wpf_popularity_order.meta_key='total_sales')";
		$args['orderby'] = ' CAST(wpf_popularity_order.meta_value AS DECIMAL(20,3)) DESC, ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addPopularityOrder'));
		return $args;
	}
	public function addDateOrderAsc( $args ) {
		global $wpdb;
		$args['orderby'] = $wpdb->posts . '.post_date, ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addDateOrderAsc'));
		return $args;
	}
	public function addDateOrder( $args ) {
		global $wpdb;
		$args['orderby'] = $wpdb->posts . '.post_date DESC, ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addDateOrder'));
		return $args;
	}
	public function addTitleOrderAsc( $args ) {
		global $wpdb;
		$args['orderby'] = $wpdb->posts . '.post_title, ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addTitleOrderAsc'));
		return $args;
	}
	public function addTitleOrderDesc( $args ) {
		global $wpdb;
		$args['orderby'] = $wpdb->posts . '.post_title DESC, ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addTitleOrderDesc'));
		return $args;
	}
	public function addRandOrder( $args ) {
		global $wpdb;
		$args['orderby'] = 'RAND(), ' . $wpdb->posts . '.ID ';
		remove_filter('posts_clauses', array($this, 'addRandOrder'));
		return $args;
	}

	public function addSKUOrder( $args, $order = 'ASC' ) {
		global $wpdb;

		$fields = ", IF (pm.meta_value IS NOT NULL,
	pm.meta_value,
	( SELECT
		pm2.meta_value
	FROM
		{$wpdb->posts} AS p
	LEFT JOIN {$wpdb->postmeta} AS pm2 ON
		pm2.post_id = p.ID
		AND pm2.meta_key = '_sku'
	WHERE
		p.post_type = 'product_variation'
		AND
		p.post_parent = {$wpdb->posts}.ID
	ORDER BY
		pm2.meta_value {$order}
	LIMIT 1)) AS sku";

		$join = " LEFT JOIN {$wpdb->postmeta} AS pm ON pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = '_sku'";

		$args['fields'] .= $fields;
		$args['join']   .= $join;
		$args['orderby'] = " sku {$order}";

		$this->clausesByParam['not_for_temporary_table'] = array( $fields, $join );

		remove_filter( 'posts_clauses', array( $this, 'addSKUOrder' ) );

		return $args;
	}

	public function addSKUOrderDesc( $args ) {
		return $this->addSKUOrder($args, 'DESC');
	}
	public function isProductQuery( $postType ) {
		if ( empty($postType) || is_null($postType) ) {
			return false;
		}
		if ( 'product' == $postType ) {
			return true;
		}
		if ( is_array( $postType ) && in_array( 'product', $postType ) ) {
			return true;
		}
		return false;
	}

	public function loadProductsFilterForProductGrid( $q ) {
		$action = ReqWpf::getVar('action');
		$ignore = array('woocommerce_load_variations', 'woocommerce_do_ajax_product_export', 'phone-orders-for-woocommerce');
				
		if ( $this->isProductQuery($q->get( 'post_type' )) && ( is_null($action) || empty($action) || ! in_array($action, $ignore ) ) ) {
			global $paged;
			remove_filter( 'pre_get_posts', array( $this, 'loadProductsFilterForProductGrid' ), 999 );
			if ( '' !== $this->mainWCQueryFiltered ) {
				$q->query_vars = $this->mainWCQueryFiltered;
			} else {
				$this->loadProductsFilter( $q );
			}
			if ( $paged && $paged > 1 ) {
				$q->set( 'paginate', true );
				$q->set( 'paged', $paged );
			}
		}
	}

	public function loadShortcodeProductsFilter( $args, $attributes = array(), $type = '' ) {
		$hash         = md5( serialize( $args ) . serialize( $attributes ) );
		$isOtherClass = false;

		if ( isset( $attributes['class'] ) && '' !== $attributes['class'] ) {
			$filterKey = $attributes['class'];
		} elseif ( '' !== self::$currentElementorClass ) {
			$filterKey = self::$currentElementorClass;
		} elseif ( isset( self::$otherShortcodeAttr['class'] ) && '' !== self::$otherShortcodeAttr['class'] ) {
			$filterKey    = self::$otherShortcodeAttr['class'];
			$isOtherClass = true;
		} else {
			$filterKey = '-';
		}

		if ( ! key_exists( $hash, self::$loadShortcode ) || 'products' !== $type ) {
			$filterId = null;
			if ( '-' !== $filterKey ) {
				preg_match( '/wpf-filter-(\d+)/', $filterKey, $matches );
				if ( isset( $matches[1] ) ) {
					$filterId  = $matches[1];
					$filterKey = "wpf-filter-{$filterId}";
				} else {
					$filterKey = '-';
				}
			}
			$isClassFilterId = ! is_null( $filterId );
			if ( $isClassFilterId ) {
				$this->setCurrentFilter( $filterId, false );
			}

			$this->addPreselectedParams();
			
			if ( ReqWpf::getVar( 'all_products_filtering' ) && ( ( '-' != $filterKey ) || ! empty($attributes['wpf-compatibility']) ) ) {
				$exclude = array( 'paged', 'posts_per_page', 'post_type', 'wc_query', 'orderby', 'order', 'fields' );
				foreach ( $args as $queryVarKey => $queryVarValue ) {
					if ( ! in_array( $queryVarKey, $exclude ) ) {
						if ( is_string( $queryVarValue ) ) {
							$args[$queryVarKey] = '';
						}
						if ( is_array( $queryVarValue ) ) {
							$args[$queryVarKey] = array();
						}
					}
				}
				$args['tax_query'] = $this->addHiddenFilterQuery(array());
			}

			$metaQuery           = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
			$taxQuery            = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
			$taxQuery['wpf_tax'] = 1;

			// set preselects
			$mode       = 'preselect';
			$preselects = $this->getPreselectedValue();

			if ( ! isset( $preselects['pr_onsale'] ) && isset( $attributes['on_sale'] ) && 'true' === $attributes['on_sale'] ) {
				$preselects['pr_onsale'] = 1;
			}
			
			$fields    = $this->addCustomFieldsQuery( $preselects, $mode );
			$metaQuery = $this->addCustomMetaQuery( $metaQuery, $preselects, $mode );
			$taxQuery  = $this->addCustomTaxQuery( $taxQuery, $preselects, $mode );

			$args['meta_query'] = $metaQuery;
			$args['tax_query']  = $this->groupTaxQueryArgs( $taxQuery );
			foreach ( $fields as $key => $value ) {
				$args[ $key ] = $value;
			}
			if ( empty( $args['post_type'] ) ) {
				$args['post_type'] = 'product';
			}

			$args['wpf_query'] = 1;

			$this->shortcodeWCQuery[ $filterKey ] = $args;

			$params = ReqWpf::get( 'get' );
			if ( ! $isClassFilterId || ( isset( $params['wpf_id'] ) && $filterId === $params['wpf_id'] ) || ( $isOtherClass && ! $this->isFiltered(false) ) ) {
				$args = $this->getQueryVars( $args );

				if ( ReqWpf::getVar( 'wpf_order' ) ) {
					$args['order']   = $this->getWpfOrderParam( ReqWpf::getVar( 'wpf_order' ) );
					$args['orderby'] = 'title';
				}
				$filterSettings = array();
				if ( ReqWpf::getVar( 'wpf_fbv' ) ) {
					$filterSettings['filtering_by_variations'] = 1;
				}
				if ( ReqWpf::getVar( 'wpf_ebv' ) ) {
					$filterSettings['exclude_backorder_variations'] = 1;
				}
				if ( ReqWpf::getVar( 'wpf_dpv' ) ) {
					$filterSettings['display_product_variations'] = 1;
				}
				if ( FrameWpf::_()->proVersionCompare( '1.4.8' ) ) {
					$args = $this->addBeforeFiltersFrontendArgs( $args, $filterSettings, $params );
				} else {
					$args = DispatcherWpf::applyFilters( 'checkBeforeFiltersFrontendArgs', $args, $filterSettings, $params );
				}
				if ( $this->shortcodeWCQuery[ $filterKey ] !== $args ) {
					$this->shortcodeWCQueryFiltered[ $filterKey ] = $args;
				}

				if ( ReqWpf::getVar( 'orderby' ) && FrameWpf::_()->getModule('options')->get('disable_plugin_sorting') != 1 ) {
					$orderby = ReqWpf::getVar( 'orderby' );
					switch ( $orderby ) {
						case 'price':
							add_filter( 'posts_clauses', array( $this, 'addPriceOrder' ), 99999 );
							break;
						case 'price-desc':
							add_filter( 'posts_clauses', array( $this, 'addPriceOrderDesc' ), 99999 );
							break;
						case 'sku':
							add_filter( 'posts_clauses', array( $this, 'addSKUOrder' ), 99999 );
							break;
						case 'sku-desc':
							add_filter( 'posts_clauses', array( $this, 'addSKUOrderDesc' ), 99999 );
							break;
						case 'date-asc':
							add_filter( 'posts_clauses', array( $this, 'addDateOrderAsc' ), 99999 );
							break;
						case 'date':
							add_filter( 'posts_clauses', array( $this, 'addDateOrder' ), 99999 );
							break;
						case 'popularity':
							add_filter( 'posts_clauses', array( $this, 'addPopularityOrder' ), 99999 );
							break;
						case 'title':
							add_filter('posts_clauses', array($this, 'addTitleOrderAsc'), 99999 );
							break;
						case 'title-desc':
							add_filter('posts_clauses', array($this, 'addTitleOrderDesc'), 99999 );
							break;
						case 'rand':
							add_filter('posts_clauses', array($this, 'addRandOrder'), 99999 );
							break;
					}
				}           
			}
			$args                         = DispatcherWpf::applyFilters( 'loadShortcodeProductsFilterPro', $args );
			self::$loadShortcode[ $hash ] = $args;
		} else {
			$args = self::$loadShortcode[ $hash ];
		}

		return $args;
	}

	public function addBeforeFiltersFrontendArgs( $args, $filterSettings = array(), $urlQuery = array() ) {

		$args = DispatcherWpf::applyFilters( 'checkBeforeFiltersFrontendArgs', $args, $filterSettings, $urlQuery );
		if ( ! empty( $args ) ) {
			global $wpdb;
			$args['post_type']             = array( 'product' );
			$settingsFilteringByVariations = ! empty( $filterSettings ) && isset( $filterSettings['filtering_by_variations'] ) ? $filterSettings['filtering_by_variations'] : false;

			if ( $settingsFilteringByVariations && ! isset( $args['variations'] ) ) {
				$join                              = '';
				$where                             = '';
				$having                            = '';
				$whereNot                          = '';
				$i                                 = 0;
				$whAnd                             = ' AND ';
				$modelMetaValues                   = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' );
				$this->clausesByParam['variation'] = array();

				if ( isset( $args['tax_query'] ) && ! empty( $args['tax_query'] ) ) {
					$metaDataTable  = DbWpf::getTableName( 'meta_data' );
					$metaDataValues = DbWpf::getTableName( 'meta_values' );

					foreach ( $args['tax_query'] as $keyTax => &$tax_query ) {
						if ( ! is_array( $tax_query ) ) {
							continue;
						}
						$logic = isset( $tax_query['relation'] ) ? $tax_query['relation'] : 'OR';

						if ( isset( $tax_query['taxonomy'] ) ) {
							$tax_query = array( $tax_query );
						}

						$countTerm = 0;
						$whAnd     = ( 'AND' === $logic ? ' AND ' : ' OR ' );
						foreach ( $tax_query as $k => $tax_item ) {

							if ( ! is_array( $tax_item ) || empty( $tax_item['taxonomy'] ) ) {
								continue;
							}

							++$countTerm;
							$taxonomy = $tax_item['taxonomy'];

							$metaKeyId = $this->getMetaKeyId( 'attribute_' . $taxonomy );

							if ( $metaKeyId ) {
								$isSlug = ( isset( $tax_item['field'] ) && 'slug' === $tax_item['field'] );
								$values = $isSlug ? $tax_item['terms'] : get_terms( array(
									'include'  => $tax_item['terms'],
									'taxonomy' => $taxonomy,
									'fields'   => 'id=>slug',
								) );

								if ( ! empty( $values ) ) {

									$joinTemp[ $taxonomy ]   = array();
									$whereTemp[ $taxonomy ]  = array();
									$havingTemp[ $taxonomy ] = array();

									$isAnd    = isset( $tax_item['operator'] ) && 'AND' === $tax_item['operator'];
									$isNot    = ! $isAnd && isset( $tax_item['operator'] ) && 'NOT IN' === $tax_item['operator'];
									$valueIds = $modelMetaValues->getMetaValueIds( $metaKeyId, $values );
									if ( ! empty( $valueIds ) ) {
										$leerId = $modelMetaValues->getMetaValueId( $metaKeyId, '' );

										++$i;
										if ( $isAnd && count($valueIds) == 1 ) {
											$isAnd = false;
										}

										if ( $isAnd ) {
											$joinTemp[ $taxonomy ][]   = ' LEFT JOIN `' . $metaDataTable . '` md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $metaKeyId . ' AND md' . $i . '.val_id=' . $leerId . ')';
											$havingTemp[ $taxonomy ][] = ( empty( $having ) ? '' : $whAnd ) . ' (count(DISTINCT md' . $i . '.val_id)>0';
											++$i;
											$joinTemp[ $taxonomy ][]   = ' LEFT JOIN `' . $metaDataTable . '` md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $metaKeyId . ' AND md' . $i . '.val_id IN (' . implode( ',', $valueIds ) . '))';
											$havingTemp[ $taxonomy ][] = ' OR count(DISTINCT md' . $i . '.val_id)>=' . count( $valueIds ) . ')';
										} else {
											$valueIds[]               = $leerId;
											$joinTemp[ $taxonomy ][]  = ' LEFT JOIN `' . $metaDataTable . '` md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $metaKeyId . ')';
											$whereTemp[ $taxonomy ][] = ' md' . $i . '.val_id' . ( $isNot ? ' NOT' : '' ) . ' IN (' . implode( ',', $valueIds ) . ')';
										}
									}

									if ( $isNot ) {
										$termIds = $tax_item['terms'];
										if ( $isSlug ) {
											$termIds  = array();
											$allTerms = get_terms( array( 'taxonomy' => $taxonomy, 'fields' => 'id=>slug' ) );
											if ( is_array( $allTerms ) ) {
												foreach ( $allTerms as $id => $slug ) {
													if ( in_array( $slug, $tax_item['terms'] ) ) {
														$termIds[] = $id;
													}
												}
											}
										}
										if ( ! empty( $termIds ) ) {
											$whereNot .= ( empty( $whereNot ) ? '' : $whAnd ) . $wpdb->posts . '.ID NOT IN (SELECT object_id FROM `wp_term_relationships` WHERE term_taxonomy_id IN (' . implode( ',', $termIds ) . '))';
										}
										unset( $tax_query[ $k ] );
									}

									if ( ! empty( $joinTemp[ $taxonomy ] ) ) {
										$sql   = implode( '', $joinTemp[ $taxonomy ] );
										$join .= $sql;
										$this->clausesByParam['variation']['conditions'][ $taxonomy ]['join'][] = $sql;
									}

									if ( ! empty( $whereTemp[ $taxonomy ] ) ) {
										$sql    = implode( '', $whereTemp[ $taxonomy ] ) ;
										$where .= ( empty( $where ) ? '' : ' AND ' ) . $sql;
										$this->clausesByParam['variation']['conditions'][ $taxonomy ]['where'][] = $sql;
									}

									if ( ! empty( $havingTemp[ $taxonomy ] ) ) {
										$sql     = implode( '', $havingTemp[ $taxonomy ] ) ;
										$having .= $sql;
										$this->clausesByParam['variation']['conditions'][ $taxonomy ]['having'][] = $sql;
									}                               
								}
							}
						}
					}
				}

				$clauses = DispatcherWpf::applyFilters( 'addVariationQueryPro', array(
					'join'     => $join,
					'where'    => $where,
					'having'   => $having,
					'whereNot' => $whereNot,
					'i'        => $i,
					'whAnd'    => $whAnd,
				), $urlQuery );
				if ( ! empty( $clauses['join'] ) ) {
					$i         = $clauses['i'];
					$metaKeyId = $this->getMetaKeyId( '_stock_status' );

					if ( $metaKeyId ) {
						++$i;
						$sqlOutofstock = '';
						if ( get_option( 'woocommerce_hide_out_of_stock_items' ) === 'yes' ) {
							$sqlOutofstock       = ' AND md' . $i . '.val_id!=' . $modelMetaValues->getMetaValueId( $metaKeyId, 'outofstock' );
							$backorderVariations = isset( $filterSettings['exclude_backorder_variations'] ) ? $filterSettings['exclude_backorder_variations'] : false;
							if ( $backorderVariations ) {
								$sqlOutofstock .= ' AND md' . $i . '.val_id!=' . $modelMetaValues->getMetaValueId( $metaKeyId, 'onbackorder' );
							}
						} elseif ( ! empty($urlQuery['pr_stock']) ) {
							$valueIds = $modelMetaValues->getMetaValueIds( $metaKeyId, explode('|', $urlQuery['pr_stock']));
							if ( ! empty( $valueIds ) ) {
								$sqlOutofstock = ' AND md' . $i . '.val_id IN (' . implode( ',', $valueIds ) . ')';
							}
						}
						if ( ! empty($sqlOutofstock) ) {
							$clauses['join'] .= ' INNER JOIN `' . $metaDataTable . '` md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $metaKeyId . $sqlOutofstock . ')';
						}
					}

					$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();

					if ( isset( $options['hide_without_price'] ) && '1' === $options['hide_without_price']['value'] ) {
						$metaKeyId = $this->getMetaKeyId( '_price' );
						if ( $metaKeyId ) {
							++$i;
							$clauses['join'] .= ' INNER JOIN `' . $metaDataTable . '` md' . $i . ' ON (md' . $i . '.product_id=p.ID AND md' . $i . '.key_id=' . $metaKeyId . ' AND md' . $i . '.val_dec>0)';
						}
					}

					$displayVariation = isset( $filterSettings['display_product_variations'] ) ? $filterSettings['display_product_variations'] : false;
					$isGroupBy        = $displayVariation || ! empty( $clauses['having'] );

					$sql = 'SELECT ' . ( $isGroupBy ? '' : 'DISTINCT' ) . ' p.post_parent as id' . 
						( $displayVariation ? ', min(p.id) as var_id, count(DISTINCT p.id) as var_cnt' : '' ) .
						' FROM `' . $wpdb->posts . '` AS p';

					$this->clausesByParam['variation']['base_request'][1] = $sql;
					
					if ( $displayVariation && isset($options['display_one_price']) && '1' === $options['display_one_price']['value'] ) {
						$metaKeyId = $this->getMetaKeyId( '_price' );
						if ( $metaKeyId ) {
							++$i;
							$clauses['join'] .= ' LEFT JOIN `' . $metaDataTable . '` md_price ON (md_price.product_id=p.ID AND md_price.key_id=' . $metaKeyId . ')';
							$selectOnePrice   = ', min(md_price.val_dec) as var_price, count(DISTINCT md_price.val_dec) as var_cnt_price';
						} else {
							$selectOnePrice = ', 0 as var_price, 0 as var_cnt_price';
						}
						$sql                                       = str_replace(' FROM ', $selectOnePrice . ' FROM ', $sql); 
						$this->clausesByParam['display_one_price'] = 1;
					}
					
					$query = $sql . $clauses['join'];

					$sql = " WHERE p.post_type='product_variation'";
					$this->clausesByParam['variation']['base_request'][2] = $sql;
					
					$query .= $sql;
					$this->clausesByParam['variation']['base_request'][3] = '';

					if ( ! empty( $clauses['where'] ) ) {
						$query .= ' AND ' . $clauses['where'];
					}
					if ( $isGroupBy ) {
						$query .= ' GROUP BY p.post_parent';
						$this->clausesByParam['variation']['base_request'][3] = ' GROUP BY p.post_parent';
					}
					if ( ! empty( $clauses['having'] ) ) {
						$query .= ' HAVING ' . $clauses['having'];
					}

					$varTable = $this->createTemporaryTable( $this->tempVarTable, $query );

					$this->clausesByParam['variation']['original_request'][ $this->tempVarTable ] = $query;

					if ( ! empty( $varTable ) ) {
						$metaKeyId = $this->getMetaKeyId( '_wpf_product_type' );
						if ( $metaKeyId ) {

							$metaValueId = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueId( $metaKeyId, 'variable' );
							if ( $metaValueId ) {
								$whereNot = empty( $clauses['whereNot'] ) ? '' : ' AND ' . $clauses['whereNot'];
								$clauses  = array(
									'join'  => array( ' LEFT JOIN ' . $varTable . ' as wpf_var_temp ON (wpf_var_temp.id=' . $wpdb->posts . '.ID) LEFT JOIN ' . $metaDataTable . ' as wpf_pr_type__#i ON (wpf_pr_type__#i.product_id=' . $wpdb->posts . '.ID AND wpf_pr_type__#i.key_id=' . $metaKeyId . ')' ),
									'where' => array( ' AND ((wpf_pr_type__#i.val_id!=' . $metaValueId . $whereNot . ') OR wpf_var_temp.id is not null)' ),
								);
								$this->addFilterClauses( $clauses, false );
							}
						}
					}               
				}
			}
		}

		return $args;
	}

	public function getWcAttributeTaxonomies() {
		if ( is_null( $this->wcAttributes ) ) {
			$allAttributes = wc_get_attribute_taxonomies();
			if ( ! empty( $allAttributes ) ) {
				$allAttributes = array_column( $allAttributes, 'attribute_name' );
				$allAttributes = array_map( function ( $attribute ) {
					return 'pa_' . $attribute;
				}, $allAttributes );
			} else {
				$allAttributes = array();
			}
			$this->wcAttributes = $allAttributes;
		}

		return $this->wcAttributes;
	}

	public function getRenderMode( $id, $settings, $isWidget = true ) {
		if ( ! isset( $this->renderModes[ $id ] ) || empty( $this->renderModes[ $id ] ) ) {
			if ( isset( $settings['settings'] ) ) {
				$settings = $settings['settings'];
			}
			$displayOnPageShortcode = $this->getFilterSetting( $settings, 'display_on_page_shortcode', false );
			$displayShop            = ( $displayOnPageShortcode ) ? false : ! $isWidget;
			$displayCategory        = false;
			$displayTag             = false;
			$displayAttribute       = false;
			$displayMobile          = true;
			$displayProduct         = false;
			$displayBrand           = false;

			if ( is_admin() ) {
				$displayShop = true;
			} else {
				$displayOnPage = empty( $settings['display_on_page'] ) ? 'shop' : $settings['display_on_page'];

				if ( 'specific' === $displayOnPage ) {
					$pageList = empty( $settings['display_page_list'] ) ? '' : $settings['display_page_list'];
					if ( is_array( $pageList ) ) {
						$pageList = isset( $pageList[0] ) ? $pageList[0] : '';
					}
					$pages  = explode( ',', $pageList );
					$pageId = $this->getView()->wpfGetPageId();
					if ( in_array( $pageId, $pages ) ) {
						$displayShop     = true;
						$displayCategory = true;
						$displayTag      = true;
					}
				} elseif ( 'custom_cats' === $displayOnPage ) {
					$catList = empty( $settings['display_cat_list'] ) ? '' : $settings['display_cat_list'];
					if ( is_array( $catList ) ) {
						$catList = isset( $catList[0] ) ? $catList[0] : '';
					}

					$cats = explode( ',', $catList );

					$displayChildCat = $this->getFilterSetting( $settings, 'display_child_cat', false );
					if ( $displayChildCat ) {
						$catChild = array();
						foreach ( $cats as $cat ) {
							$catChild = array_merge( $catChild, get_term_children( $cat, 'product_cat' ) );
						}
						$cats = array_merge( $cats, $catChild );
					}

					$parent_id = get_queried_object_id();
					if ( in_array( $parent_id, $cats ) ) {
						$displayCategory = true;
					}
				} elseif ( 'custom_pwb' === $displayOnPage ) {
					$brandList = empty( $settings['display_pwb_list'] ) ? '' : $settings['display_pwb_list'];
					if ( is_array( $brandList ) ) {
						$brandList = isset( $brandList[0] ) ? $brandList[0] : '';
					}

					$brands = explode( ',', $brandList );

					$displayChildBrand = $this->getFilterSetting( $settings, 'display_child_brand', false );
					if ( $displayChildBrand ) {
						$brandChild = array();
						foreach ( $brands as $brand ) {
							$brandChild = array_merge( $brandChild, get_term_children( $brand, 'pwb-brand' ) );
						}
						$brands = array_merge( $brands, $brandChild );
					}

					$parent_id = get_queried_object_id();
					if ( in_array( $parent_id, $brands ) ) {
						$displayBrand = true;
					}
				} elseif ( is_shop() || is_product_category() || is_product_tag() || is_customize_preview() ) {
					if ( 'shop' === $displayOnPage || 'both' === $displayOnPage ) {
						$displayShop = true;
					}
					if ( 'category' === $displayOnPage || 'both' === $displayOnPage ) {
						$displayCategory = true;
					}
					if ( 'tag' === $displayOnPage || 'both' === $displayOnPage ) {
						$displayTag = true;
					}
				} elseif ( is_tax() && ( 'both' === $displayOnPage || 'shop' === $displayOnPage ) ) {
					$displayAttribute = true;
				} elseif ( 'product' === $displayOnPage ) {
					$displayProduct = true;
				} elseif ( 'brand' === $displayOnPage ) {
					$displayBrand = true;
				}

				$displayFor = empty( $settings['display_for'] ) ? '' : $settings['display_for'];

				$mobileBreakpointWidth = $this->getView()->getMobileBreakpointValue( $settings );
				if ( $mobileBreakpointWidth ) {
					$displayFor = 'both';
				}
				if ( 'mobile' === $displayFor ) {
					$displayMobile = UtilsWpf::isMobile();
				} elseif ( 'both' === $displayFor ) {
					$displayMobile = true;
				} elseif ( 'desktop' === $displayFor ) {
					$displayMobile = ! UtilsWpf::isMobile();
				}
			}
			$hideWithoutProducts = ! empty( $settings['hide_without_products'] ) && $settings['hide_without_products'];
			$displayMode         = $this->getDisplayMode();
			$mode                = 0;

			if ( ! $hideWithoutProducts || 'subcategories' != $displayMode || is_search() ) {
				if ( is_product_category() && $displayCategory && $displayMobile ) {
					$mode = 1;
				} elseif ( $this->isVendor() && $displayShop && $displayMobile ) {
					$mode = 7;
				} elseif ( is_shop() && $displayShop && $displayMobile ) {
					$mode = 2;
				} elseif ( is_product_tag() && $displayTag && $displayMobile ) {
					$mode = 3;
				} elseif ( is_tax( 'product_brand' ) && $displayShop && $displayMobile ) {
					$mode = 4;
				} elseif ( is_tax( 'pwb-brand' ) && $displayShop && $displayMobile ) {
					$mode = 5;
				} elseif ( $displayAttribute && $displayMobile ) {
					$mode = 6;
				} elseif ( $displayShop && $displayMobile && ! is_product_category() && ! is_product_tag() ) {
					$mode = 10;
				} elseif ( is_product() && $displayProduct && $displayMobile ) {
					$mode = 8;
				} elseif (
					FrameWpf::_()->isPro() &&
					( is_tax( 'pwb-brand' ) || is_tax( 'product_brand' ) ) &&
					$displayBrand &&
					$displayMobile
				) {
					$mode = 11;
				} elseif ( 'all_pages' === $displayOnPage ) {
					if ( FrameWpf::_()->isPro() ) {
						$mode = 12;
					} elseif ( is_shop() ) {
						$mode = 2; // shop mode if not PRO
					}
				}           
			}
			$this->renderModes[ $id ] = $mode;
		}

		return $this->renderModes[ $id ];
	}

	private function isVendor() {

		if ( $this->isWcVendorsPluginActivated() && WCV_Vendors::is_vendor_page() ) {
			return true;
		}

		if ( is_plugin_active( 'dokan-lite/dokan.php' ) && function_exists( 'dokan_is_store_page' ) ) {
			return dokan_is_store_page();
		}

		return false;
	}

	private function wpf_get_loop_prop( $prop ) {
		return isset( $GLOBALS['woocommerce_loop'], $GLOBALS['woocommerce_loop'][ $prop ] ) ? $GLOBALS['woocommerce_loop'][ $prop ] : '';
	}

	public function getDisplayMode() {
		if ( is_null( $this->displayMode ) ) {
			$mode = '';
			if ( $this->wpf_get_loop_prop( 'is_search' ) || $this->wpf_get_loop_prop( 'is_filtered' ) ) {
				$display_type = 'products';
			} else {
				$parent_id    = 0;
				$display_type = '';
				if ( is_shop() ) {
					$display_type = get_option( 'woocommerce_shop_page_display', '' );
				} elseif ( is_product_category() ) {
					$parent_id    = get_queried_object_id();
					$display_type = get_term_meta( $parent_id, 'display_type', true );
					$display_type = '' === $display_type ? get_option( 'woocommerce_category_archive_display', '' ) : $display_type;
				}

				if ( ( ! is_shop() || 'subcategories' !== $display_type ) && 1 < $this->wpf_get_loop_prop( 'current_page' ) ) {
					$display_type = 'products';
				}
			}

			if ( '' === $display_type || ! in_array( $display_type, array( 'products', 'subcategories', 'both' ), true ) ) {
				$display_type = 'products';
			}

			if ( in_array( $display_type, array( 'subcategories', 'both' ), true ) ) {
				$subcategories = woocommerce_get_product_subcategories( $parent_id );

				if ( empty( $subcategories ) ) {
					$display_type = 'products';
				}
			}
			$this->displayMode = $display_type;
		}

		return $this->displayMode;
	}

	public function addClausesTitleOrder( $args ) {
		global $wpdb;
		$posId = strpos( $args['orderby'], '.product_id' );
		if ( false !== $posId ) {
			$idBegin = strrpos( $args['orderby'], ',', ( strlen( $args['orderby'] ) - $posId ) * ( - 1 ) );
			if ( $idBegin ) {
				$args['orderby'] = substr( $args['orderby'], 0, $idBegin );
			}
		} else {
			$posId = strpos( $args['orderby'], $wpdb->posts . '.ID' );
			if ( false !== $posId ) {
				$idBegin = strrpos( $args['orderby'], ',', ( strlen( $args['orderby'] ) - $posId ) * ( - 1 ) );
				if ( $idBegin ) {
					$args['orderby'] = substr( $args['orderby'], 0, $idBegin );
				}
			}
		}

		$order           = $this->getWpfOrderParam( ReqWpf::getVar( 'wpf_order' ) );
		$orderByTitle    = "$wpdb->posts.post_title $order";
		$args['orderby'] = ( empty( $args['orderby'] ) ? $orderByTitle : $orderByTitle . ', ' . $args['orderby'] );
		remove_filter( 'posts_clauses', array( $this, 'addClausesTitleOrder' ) );

		return $args;
	}

	public function addCustomOrder( $args, $customOrder = 'title' ) {
		if ( empty( $args['orderby'] ) ) {
			$args['orderby'] = $customOrder;
			$args['order']   = 'ASC';
		} elseif ( $args['orderby'] != $customOrder ) {
			if ( is_array( $args['orderby'] ) ) {
				reset( $args['orderby'] );
				$key             = key( $args['orderby'] );
				$args['orderby'] = array( $key => $args['orderby'][ $key ] );
			} else {
				$args['orderby'] = array( $args['orderby'] => empty( $args['order'] ) ? 'ASC' : $args['order'] );
			}
			$args['orderby'][ $customOrder ] = 'ASC';
			$args['order']                   = '';
		}

		return $args;
	}

	private function getWpfOrderParam( $wpfOrder ) {
		$order = 'ASC';
		if ( 'titled' == $wpfOrder ) {
			$order = 'DESC';
		}

		return $order;
	}

	/**
	 * Group together wp_query taxonomies params args with the same taxonomy name
	 *
	 * @param array $taxQuery
	 *
	 * @return array
	 */
	public function groupTaxQueryArgs( $taxQuery ) {
		if ( empty( $taxQuery ) || ! is_array( $taxQuery ) ) {
			return $taxQuery;
		}
		
		//for leer tax_query change OR-relation to AND
		if ( ! empty($taxQuery['relation']) && 'OR' == $taxQuery['relation'] ) {
			$isLeer  = true;
			$exclude = array('relation', 'wpf_tax');
			foreach ( $taxQuery as $k => $v ) {
				if ( ! in_array($k, $exclude) && ! empty($v) ) {
					$isLeer = false;
					break;
				}
			}
			if ( $isLeer ) {
				$taxQuery['relation'] = 'AND';
			}
		}

		$taxGroupedList = array(
			'product_cat',
			'product_tag',
		);

		$attributesTax = array_keys( wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' ) );

		if ( $attributesTax ) {
			$attributesTax = array_map(
				function ( $tax ) {
					return 'pa_' . $tax;
				},
				$attributesTax
			);

			$taxGroupedList = array_merge( $taxGroupedList, $attributesTax );
		}

		$groupedTaxQueryVal = array();
		$taxQueryFormat     = array();
		$uniq               = array();
		foreach ( $taxQuery as $taxQueryIndex => $taxQueryValue ) {
			if ( ! empty( $taxQueryValue['taxonomy'] ) && in_array( $taxQueryValue['taxonomy'], $taxGroupedList ) ) {
				$group = $taxQueryValue['taxonomy'];
				if ( 'product_cat' != $group && 'product_tag' != $group ) {
					$group = 'product_att';
				}
				$groupedTaxQueryVal[ $group ][] = $taxQueryValue;
			} elseif ( ! empty( $taxQueryValue['wpf_group'] ) ) {
				$group = $taxQueryValue['wpf_group'];
				foreach ( $taxQueryValue as $wpfIndex => $wpfValue ) {
					if ( is_int( $wpfIndex ) ) {
						$groupedTaxQueryVal[ $group ][] = $wpfValue;
					}
				}
			} else {
				$json = json_encode( $taxQueryValue );
				if ( ! in_array( $json, $uniq ) ) {
					if ( is_int( $taxQueryIndex ) ) {
						$taxQueryFormat[] = $taxQueryValue;
					} else {
						$taxQueryFormat[ $taxQueryIndex ] = $taxQueryValue;
					}
					$uniq[] = $json;
				}
			}
		}
		if ( $groupedTaxQueryVal ) {
			$logic = ReqWpf::getVar( 'wpf_filter_tax_block_logic' );
			$logic = is_null( $logic ) ? 'AND' : strtoupper( $logic );
			foreach ( $groupedTaxQueryVal as $group => $values ) {
				if ( count( $values ) > 1 ) {
					$uniq = array();
					$vals = array();
					foreach ( $values as $i => $v ) {
						$json = json_encode( $v );
						if ( ! in_array( $json, $uniq ) ) {
							$vals[] = $v;
							$uniq[] = $json;
						}
					}
					$values = $vals;
				}
				$values['wpf_group'] = $group;
				$values['relation']  = $logic;
				$taxQueryFormat[]    = $values;
			}
		}

		return $taxQueryFormat;
	}

	public function addAdminTab( $tabs ) {
		$tabs[ $this->getCode() . '#wpfadd' ] = array(
			'label'      => esc_html__( 'Add New Filter', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-plus-circle',
			'sort_order' => 10,
			'add_bread'  => $this->getCode(),
		);
		$tabs[ $this->getCode() . '_edit' ]   = array(
			'label'      => esc_html__( 'Edit', 'woo-product-filter' ),
			'callback'   => array( $this, 'getEditTabContent' ),
			'sort_order' => 20,
			'child_of'   => $this->getCode(),
			'hidden'     => 1,
			'add_bread'  => $this->getCode(),
		);
		$tabs[ $this->getCode() ]             = array(
			'label'      => esc_html__( 'Show All Filters', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-list',
			'sort_order' => 20, //'is_main' => true,
		);

		return $tabs;
	}

	public function getCurrencyPrice( $raw_price, $dec = false ) {
		if ( function_exists( 'alg_wc_currency_switcher_plugin' ) ) {
			$price = alg_wc_currency_switcher_plugin()->core->change_price_by_currency( $raw_price );
		} elseif ( function_exists( 'wmc_get_price' ) ) {
			$price = wmc_get_price( $raw_price );
		} else {
			
			$price = apply_filters( 'raw_woocommerce_price', $raw_price );

			// some plugin uses a different hook, use it if the standard one did not change the price
			if ( $price === $raw_price && ( is_plugin_active( 'woocommerce-currency-switcher/index.php' ) || is_plugin_active( 'woocommerce-multicurrency/woocommerce-multicurrency.php' ) ) ) {
				$price = apply_filters( 'woocommerce_product_get_regular_price', $raw_price, null );
			}
			if ( $price === $raw_price && class_exists(\Yay_Currency\Helpers\YayCurrencyHelper::class) ) {
				$apply_currency = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
				$price          = \Yay_Currency\Helpers\YayCurrencyHelper::calculate_price_by_currency( $raw_price, false, $apply_currency );
			}
			if ( $price === $raw_price && function_exists( 'wcml_convert_price' ) ) {
				global $woocommerce_wpml;
				if ( ! empty($woocommerce_wpml) && ! empty($woocommerce_wpml->multi_currency) && ! is_null($woocommerce_wpml->multi_currency) ) {
					$price = wcml_convert_price($raw_price);
				}
			}
			if ( $price === $raw_price && class_exists('APBDWMC_general') ) {
				$price = APBDWMC_general::GetModuleInstance()->getCalculatedPrice($raw_price);
			}
		}

		return ( false === $dec ? $price : round( $price, $dec ) );
	}

	public function preparePriceFilter( $minPrice = null, $maxPrice = null, $rate = null ) {
		if ( ! is_null( $minPrice ) ) {
			$minPrice = str_replace( ',', '.', $minPrice );
			if ( ! is_numeric( $minPrice ) ) {
				$minPrice = null;
			}
		}
		if ( ! is_null( $maxPrice ) ) {
			$maxPrice = str_replace( ',', '.', $maxPrice );
			if ( ! is_numeric( $maxPrice ) ) {
				$maxPrice = null;
			}
		}

		if ( is_null( $minPrice ) && is_null( $maxPrice ) ) {
			return false;
		}

		$metaQuery                   = array( 'key' => '_price', 'price_filter' => true, 'type' => 'DECIMAL(20,3)' );
		list( $minPrice, $maxPrice ) = DispatcherWpf::applyFilters( 'priceTax', array(
			$minPrice,
			$maxPrice,
		), 'subtract' );

		if ( is_null( $rate ) ) {
			$rate = $this->getCurrentRate();
		}

		if ( is_null( $minPrice ) ) {
			$metaQuery['compare'] = '<=';
			$metaQuery['value']   = $maxPrice / $rate;
		} elseif ( is_null( $maxPrice ) ) {
			$metaQuery['compare'] = '>=';
			$metaQuery['value']   = $minPrice / $rate;
		} else {
			$metaQuery['compare'] = 'BETWEEN';
			$metaQuery['value']   = array( $minPrice / $rate, $maxPrice / $rate );
		}
		
		if ( function_exists('wcpbc_the_zone') && wcpbc_the_zone() ) {
			global $wpdb;
			$key = '_' . wcpbc_the_zone()->get_id() . '_price';
			
			$value   = $metaQuery['compare'] . 
				( is_array($metaQuery['value']) ? " '" . $metaQuery['value'][0] . "' AND '" . $metaQuery['value'][1] . "'" : "'" . $metaQuery['value'] . "'" );
			$clauses = array(
				'join'  => array(
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price ON (wpf_price.post_id=' . $wpdb->posts . ".ID AND wpf_price.meta_key='_price')",
					' LEFT JOIN ' . $wpdb->postmeta . ' as wpf_price_wcpbc ON (wpf_price_wcpbc.post_id=' . $wpdb->posts . ".ID AND wpf_price_wcpbc.meta_key='" . $key . "')",
				),
				'where' => array(
					' AND ((wpf_price_wcpbc.post_id is NOT NULL AND CAST(wpf_price_wcpbc.meta_value AS DECIMAL(20,3)) ' . $value .
						') OR (wpf_price_wcpbc.post_id is NULL AND CAST(wpf_price.meta_value AS DECIMAL(20,3)) ' . $value . '))',
				),
			);
			$this->addFilterClauses( $clauses, false );
			return array();
		}
		if ( class_exists( 'WC_Measurement_Price_Calculator' ) ) {
			$metaKeyId = $this->getMetaKeyId( '_price' );
			if ( $metaKeyId ) {
				global $wpdb;
				$metaDataTable = DbWpf::getTableName( 'meta_data' );
				$value         = $metaQuery['compare'] . 
					( is_array($metaQuery['value']) ? " '" . $metaQuery['value'][0] . "' AND '" . $metaQuery['value'][1] . "'" : "'" . $metaQuery['value'] . "'" );
			
				$clauses = array(
					'join'  => array(' INNER JOIN ' . $metaDataTable . ' as wpf_price_table ON (wpf_price_table.key_id=' . $metaKeyId . ' AND wpf_price_table.product_id=' . $wpdb->posts . '.ID)'),
					'where' => array(' AND wpf_price_table.val_dec ' . $value),
				);
				$this->addFilterClauses( $clauses, false );
				return array();
			} 
		}
		if ( class_exists('WooCommerceB2B') && FrameWpf::_()->getModule('options')->getModel()->get('use_wcb2b_prices') == 1 ) {
			$user   = wp_get_current_user();
			$userId = $user ? $user->ID : 0;
			if ( $userId ) {
				$groupId = get_user_meta($userId, 'wcb2b_group', true);
				if ( ! empty($groupId) ) {
					$metaKeyId = $this->getMetaKeyId('wcb2b_product_group_prices');
					if ( $metaKeyId ) {
						global $wpdb;
						$value   = $metaQuery['compare'] . 
							( is_array($metaQuery['value']) ? " '" . $metaQuery['value'][0] . "' AND '" . $metaQuery['value'][1] . "'" : "'" . $metaQuery['value'] . "'" );
						$clauses = array(
							'join'  => array(
								' INNER JOIN ' . DbWpf::getTableName( 'meta_data' ) . ' as wpf_mdata ON (wpf_mdata.key_id=' . $metaKeyId . ' AND wpf_mdata.product_id=' . $wpdb->posts . '.ID)',
								' INNER JOIN ' . DbWpf::getTableName( 'meta_values' ) . ' as wpf_vdata ON (wpf_vdata.key_id=' . $metaKeyId . ' AND wpf_vdata.id=wpf_mdata.val_id)',
							),
							'where' => array(' AND ROUND(wpf_vdata.value,2) ' . $value . " AND wpf_vdata.key2='" . $groupId . "'"),
						);
						$this->addFilterClauses( $clauses, false );
						return array();
					}
				}
			} 
		}
		add_filter( 'posts_where', array( $this, 'controlDecimalType' ), 9999, 2 );
		
		return array( 'price_filter' => $metaQuery );
	}

	public function controlDecimalType( $where ) {
		return preg_replace( '/DECIMAL\([\d]*,[\d]*\)\(20,3\)/', 'DECIMAL(20,3)', $where );
	}

	public function getCurrentRate() {
		$price    = 1000;
		$newPrice = $this->getCurrencyPrice( $price );

		return $newPrice / $price;
	}

	public function addHiddenFilterQuery( $query ) {
		$hidden_term = get_term_by( 'name', 'exclude-from-catalog', 'product_visibility' );
		if ( $hidden_term ) {
			$query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( $hidden_term->term_taxonomy_id ),
				'operator' => 'NOT IN',
			);
		}

		return $query;
	}

	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	public function getEditTabContent() {
		$id = ReqWpf::getVar( 'id', 'get' );

		return $this->getView()->getEditTabContent( $id );
	}

	public function getEditLink( $id, $tableTab = '' ) {
		$link  = FrameWpf::_()->getModule( 'options' )->getTabUrl( $this->getCode() . '_edit' );
		$link .= '&id=' . $id;
		if ( ! empty( $tableTab ) ) {
			$link .= '#' . $tableTab;
		}

		return $link;
	}

	public function render( $params ) {
		$p = array(
			'id'   => ( isset($params['id']) ? (int) $params['id'] : 0 ),
			'mode' => ( isset($params['mode']) && 'widget' == $params['mode'] ? 'widget' : '' ),
		);
		return $this->getView()->renderHtml( $p );
	}

	public function renderProductsList( $params ) {
		$params = array();
		return $this->getView()->renderProductsListHtml( $params );
	}

	public function renderSelectedFilters( $params ) {
		$p = array(
			'id' => ( isset($params['id']) ? (int) $params['id'] : 0 ),
		);
		return FrameWpf::_()->isPro() ? $this->getView()->renderSelectedFiltersHtml( $p ) : '';
	}

	public function showAdminErrors() {
		// check WooCommerce is installed and activated
		if ( ! $this->isWooCommercePluginActivated() ) {
			// WooCommerce install url
			$wooCommerceInstallUrl = add_query_arg(
				array(
					's'    => 'WooCommerce',
					'tab'  => 'search',
					'type' => 'term',
				),
				admin_url( 'plugin-install.php' )
			);
			$tableView             = $this->getView();
			$tableView->assign( 'errorMsg',
				$this->translate( 'For work with "' ) . WPF_WP_PLUGIN_NAME . $this->translate( '" plugin, You need to install and activate WooCommerce plugin.' )
			);
			// check current module
			if ( ReqWpf::getVar( 'page' ) == WPF_SHORTCODE || FrameWpf::_()->isWCLicense() ) {
				// show message
				HtmlWpf::echoEscapedHtml( $tableView->getContent( 'showAdminNotice' ) );
			}
		}
	}

	public function isWooCommercePluginActivated() {
		return class_exists( 'WooCommerce' );
	}

	public function WC_pif_product_has_gallery( $classes ) {
		global $product;

		$post_type = get_post_type( get_the_ID() );

		if ( wp_doing_ajax() ) {

			if ( 'product' == $post_type ) {

				if ( is_callable( 'WC_Product::get_gallery_image_ids' ) ) {
					$attachment_ids = $product->get_gallery_image_ids();
				} else {
					$attachment_ids = $product->get_gallery_attachment_ids();
				}

				if ( $attachment_ids ) {
					$classes[] = 'pif-has-gallery';
				}
			}
		}

		return $classes;
	}

	public function YITH_hide_add_to_cart_loop( $link, $product ) {

		if ( wp_doing_ajax() ) {

			if ( get_option( 'ywraq_hide_add_to_cart' ) == 'yes' && class_exists( 'YITH_YWRAQ_Frontend' ) ) {
				return call_user_func_array( array( 'YITH_YWRAQ_Frontend', 'hide_add_to_cart_loop' ), array( $link, $product ) );
			}
		}

		return $link;
	}

	/**
	 * Add plugin compatibility wp_query filtering results args
	 *
	 * @link https://iconicwp.com/products/woocommerce-show-single-variations
	 *
	 * @param array $args query args
	 *
	 * @return array
	 */
	public function Iconic_Wssv_Query_Args( $args ) {
		$args = Iconic_WSSV_Query::add_variations_to_shortcode_query( $args, array() );

		return $args;
	}
	
	public function addChildrenAttributeTerms( $parents ) {
		$aFlat = array();
		foreach ( $parents as $id => $term ) {
			if ( ! empty( $term->children ) ) {
				$aFlatChildren  = $this->addChildrenAttributeTerms ( $term->children );
				$term->children = array();
				$aFlat[$id]     = $term;
				foreach ( $aFlatChildren as $cid => $cterm ) {
					$aFlat[$cid] = $cterm;
				}
			} else {
				$aFlat[$id] = $term;
			}
		}
		return $aFlat;
	}

	public function getAttributeTerms( $slug ) {
		$terms = array();
		if ( empty( $slug ) ) {
			return $terms;
		}
		$args = array( 'hide_empty' => false );
		if ( is_numeric( $slug ) ) {
			$args['taxonomy'] = wc_attribute_taxonomy_name_by_id( (int) $slug );
			$values           = get_terms( $args );
		} else {
			$values = DispatcherWpf::applyFilters( 'getCustomTerms', array(), $slug, $args );
			$values = $this->addChildrenAttributeTerms( $values );
		}

		if ( $values ) {
			foreach ( $values as $value ) {
				if ( ! empty( $value->term_id ) ) {
					$terms[ $value->term_id ] = $value->name;
				}
			}
		}

		return $terms;
	}

	public function getFilterTaxonomies( $settings, $calcCategories = false, $filterSettings = array(), $ajax = false, $urlQuery = array() ) {

		if ( empty( $urlQuery ) ) {
			$urlQuery = ReqWpf::get( 'get' );
		}

		$multiLogic           = $this->getFilterSetting( $filterSettings, 'f_multi_logic', 'and' );
		$taxonomies           = array();
		$forCount             = array();
		$forCountWithChildren = array();
		$other                = array();

		if ( $calcCategories ) {
			$taxonomies[] = 'product_cat';
		}

		$key            = 0;
		$differentLogic = array();
		$difBlocks      = array();

		foreach ( $settings as $filter ) {

			if ( empty( $filter['settings']['f_enable'] ) ) {
				continue;
			}

			$taxonomy = '';
			
			switch ( $filter['id'] ) {
				case 'wpfCategory':
					$taxonomy = 'product_cat';
					break;

				case 'wpfTags':
					$taxonomy = 'product_tag';
					break;

				case 'wpfAttribute':
					if ( ! empty( $filter['settings']['f_list'] ) ) {
						$slug     = $filter['settings']['f_list'];
						$taxonomy = ( is_numeric( $slug ) )
							? wc_attribute_taxonomy_name_by_id( (int) $slug )
							: DispatcherWpf::applyFilters( 'getCustomAttributeName', $slug, $filter );
					}

					$frontendType = $this->getFilterSetting( $filter['settings'], 'f_frontend_type', '' );

					if ( 'slider' === $frontendType ) {
						$showAllSliderAttributes = $this->getFilterSetting( $filter['settings'], 'f_show_all_slider_attributes', false );

						if ( $showAllSliderAttributes ) {
							$other[] = $filter['id'];
						}                   
					}

					break;

				case 'wpfBrand':
					$taxonomy = 'product_brand';
					break;

				case 'wpfPerfectBrand':
					$taxonomy = 'pwb-brand';
					break;

				case 'wpfPrice':
				case 'wpfPriceRange':
					if ( ! $ajax || ( isset( $filterSettings['filter_recount_price'] ) && $filterSettings['filter_recount_price'] ) ) {
						$other[] = $filter['id'];
					}

					break;

				case 'wpfAuthor':
				case 'wpfVendors':
				case 'wpfRating':
					$other[] = $filter['id'];
					break;
				case 'wpfSearchNumber':
					if ( ! empty( $filter['settings']['f_conrol_products'] ) && ! empty( $filter['settings']['f_list'] ) ) {
						$slug     = $filter['settings']['f_list'];
						$taxonomy = ( is_numeric( $slug ) )
							? wc_attribute_taxonomy_name_by_id( (int) $slug )
							: DispatcherWpf::applyFilters( 'getCustomAttributeName', $slug, $filter );
					}
					break;
				default:
					break;

			}

			if ( ! empty( $taxonomy ) ) {
				$typ = $this->getFilterSetting( $filter['settings'], 'f_frontend_type');
				if ( ! in_array($typ, array('dropdown')) ) {
					$settingName = ( 'product_cat' === $taxonomy ) ? 'f_multi_logic' : 'f_query_logic';
					$queryLogic  = $this->getFilterSetting( $filter['settings'], $settingName, 'and' );

					if ( ( 'and' === $multiLogic && 'or' === $queryLogic ) || ( 'or' === $multiLogic && 'and' === $queryLogic ) ) {
						$differentLogic[ $key ] = $taxonomy;
						if ( ! isset($difBlocks[$taxonomy]) ) {
							$difBlocks[$taxonomy] = array();
						}
						$difBlocks[$taxonomy][] = $key;
					}
				}

				$taxonomies[ $key ] = $taxonomy;

				if ( ! empty( $filter['settings']['f_show_count'] ) ) {
					$forCount[] = $taxonomy;

					if ( ! empty( $filter['settings']['f_show_count_parent_with_children'] ) ) {
						$forCountWithChildren[] = $taxonomy;
					}               
				}           
			}

			++$key;

		}

		$getNames = array();
		
		$checkGetNames = $this->getFilterSetting( $filterSettings, 'check_get_names', '0' );
		if ( ! $checkGetNames ) {
			// delete from get_names if more then one block with same taxonomy
			foreach ( $difBlocks as $t => $keys ) {
				if ( count($keys) > 1 ) {
					foreach ( $keys as $i => $k ) {
						unset($differentLogic[$k]);
					}
				}
			}
		}

		if ( $checkGetNames || ! empty( $differentLogic ) ) {
			$getNames = $this->checkGetNames( $taxonomies, $other, $differentLogic, $urlQuery );
		}
		return array(
			'names'               => array_unique( $taxonomies ),
			'count'               => array_unique( $forCount ),
			'count_with_children' => array_unique( $forCountWithChildren ),
			'other_names'         => $other,
			'get_names'           => $getNames,
			'multi_logic'         => $multiLogic,
			'check_get_names'     => $checkGetNames,
			'keep_recount_price'  => $this->getFilterSetting($filterSettings, 'filter_recount_price', '0') && $this->getFilterSetting($filterSettings, 'keep_recount_price', '0'),
			'cat_only_children'   => DispatcherWpf::applyFilters( 'getOneByOneCategoryHierarchy', array(), $urlQuery, $filterSettings ),
		);
	}

	/**
	 * Forms an array with names from the address bar
	 *
	 * @param $taxonomies
	 *
	 * @return array
	 */
	public function checkGetNames( &$taxonomies, &$other, $differentLogic = array(), $urlQuery = array() ) {
		$blocks   = array();
		$getNames = array();

		foreach ( $taxonomies as $index => $taxonomy ) {

			if ( empty( $differentLogic ) || ( isset( $differentLogic[ $index ] ) && $differentLogic[ $index ] === $taxonomy ) ) {

				switch ( $taxonomy ) {
					case 'product_cat':
						$blocks[ $taxonomy ][] = 'wpf_filter_cat.*?_' . $index;
						break;

					case 'product_tag':
						$blocks[ $taxonomy ][] = 'product_tag_' . $index;
						break;

					case 'pwb-brand':
						$blocks[ $taxonomy ][] = 'wpf_filter_pwb.*?_' . $index;
						break;

					default:
						if ( 0 === strpos( $taxonomy, 'flocal-' ) || 0 === strpos( $taxonomy, 'fmeta-' ) || 0 === strpos( $taxonomy, 'acf-' ) ) {
							$blocks[ $taxonomy ][] = $taxonomy;
							$blocks[ $taxonomy ][] = $taxonomy . '_' . $index;
						} else {
							$pattern               = 'wpf_filter_' . preg_replace( '/^pa_/', '', $taxonomy );
							$blocks[ $taxonomy ][] = $pattern;
							$blocks[ $taxonomy ][] = $pattern . '_' . $index;
						}

						break;
				}           
			}       
		}

		foreach ( $other as $index => $taxonomy ) {

			switch ( $taxonomy ) {
				case 'wpfRating':
					$blocks[ $taxonomy ][] = 'pr_rating';
					break;
			}       
		}

		if ( ! empty( $blocks ) ) {

			foreach ( $urlQuery as $param => $value ) {

				foreach ( $blocks as $taxanomy => $patterns ) {

					foreach ( $patterns as $pattern ) {
						preg_match( '/^' . $pattern . '$/', $param, $matches );

						if ( isset( $matches[0] ) ) {
							$getNames[ $taxanomy ] = $param;
							continue 3;
						}                   
					}               
				}           
			}       
		}

		return $getNames;
	}

	public function createTemporaryTable( $table, $sql, $postfix = '' ) {

		if ( '' !== $postfix ) {
			$table .= '_' . str_replace( '-', '_', trim( $postfix ) );
		}

		$resultTable = $table;

		if ( isset( $this->clausesByParam['not_for_temporary_table'] ) ) {

			foreach ( $this->clausesByParam['not_for_temporary_table'] as $sqlPart ) {
				$sql = str_replace( $sqlPart, '', $sql );
			}       
		}

		$sql      = str_replace( 'SQL_CALC_FOUND_ROWS', '', $sql );
		$orderPos = strpos( $sql, 'ORDER' );

		if ( $orderPos ) {
			$sql = substr( $sql, 0, $orderPos );
		} else {
			$limitPos = strpos( $sql, 'LIMIT' );

			if ( $limitPos ) {
				$sql = substr( $sql, 0, $limitPos );
			}       
		}
		$needPrimaryKey = DbWpf::get("SHOW SESSION variables like 'sql_require_primary_key'");
		if ( ! empty($needPrimaryKey) && isset($needPrimaryKey[0]['Value']) && 'ON' == $needPrimaryKey[0]['Value'] ) {
			DbWpf::query('SET SESSION sql_require_primary_key=0');
		}
		if ( ! DbWpf::query( "DROP TEMPORARY TABLE IF EXISTS `{$table}`") ) {
			return false;
		}

		if ( DbWpf::query( "CREATE TEMPORARY TABLE IF NOT EXISTS `{$table}` (index my_pkey (id)) AS {$sql}", true ) === false ) {
			$resultTable = '(' . $sql . ')';
		}

		$this->tempTables[ $table ] = $resultTable;

		return $resultTable;
	}


	public function removeFromArgsForLogicOr( $removeArgs, $args ) {
		$calc = array();

		foreach ( $removeArgs as $taxonomy => $param ) {
			$argsTemp = $args;

			foreach ( $argsTemp['tax_query'] as $index_1 => $tax_1 ) {

				if ( is_array( $tax_1 ) ) {

					if ( isset( $tax_1['taxonomy'] ) ) {

						if ( $tax_1['taxonomy'] === $taxonomy ) {
							unset( $argsTemp['tax_query'][ $index_1 ] );
						}                   
					} elseif ( is_array( $argsTemp['tax_query'][ $index_1 ] ) ) {

						foreach ( $argsTemp['tax_query'][ $index_1 ] as $index_2 => $tax_2 ) {

							if ( isset( $tax_2['taxonomy'] ) && $tax_2['taxonomy'] === $taxonomy ) {
								unset( $argsTemp['tax_query'][ $index_1 ][ $index_2 ] );
							}                       
						}                   
					}               
				}           
			}

			$calc[ $param ]                           = $argsTemp;
			$calc[ $param ]['wpf_get_names_taxonomy'] = $taxonomy;
		}

		return $calc;
	}

	public function addToArgsForLogicAnd( $addArgs, $args, $urlQuery = array() ) {
		$calc = array();

		if ( empty( $urlQuery ) ) {
			$urlQuery = ReqWpf::get( 'get' );
		}

		foreach ( $addArgs as $taxonomy => $param ) {

			if ( isset( $urlQuery[ $param ] ) ) {
				$calc[ $param ]                           = $this->getQueryVars( $args, array(), array( $param => $urlQuery[ $param ] ) );
				$calc[ $param ]['wpf_get_names_taxonomy'] = $taxonomy;
			}       
		}


		return $calc;
	}

	/**
	 * Get filter existing individual filters items
	 *
	 * @param int | null $args wp_query args
	 * @param array $taxonomies
	 * @param int | null $calcCategory
	 * @param int | bool $prodCatId
	 * @param array $generalSettings
	 * @param bool $ajax
	 * @param array $currentSettings
	 *
	 * @return mixed
	 */
	public function getFilterExistsItems( $args, $taxonomies, $calcCategory = null, $prodCatId = false, $generalSettings = array(), $ajax = false, $currentSettings = array(), $settings = array(), $urlQuery = array() ) {

		if ( empty( $taxonomies['names'] ) && empty( $taxonomies['other_names'] ) && empty( $taxonomies['get_names'] ) ) {
			return false;
		}

		$calc         = array();
		$isGetNames   = ! empty( $taxonomies['get_names'] );
		$multiLogicOr = ( 'or' === $taxonomies['multi_logic'] );

		if ( ! empty( $taxonomies['names'] ) || ! empty( $taxonomies['other_names'] ) ) {
			list( $args, $argsFiltered ) = $this->getArgsWCQuery( $args, $currentSettings );
			$calc                        = ( empty( $argsFiltered ) )
				? array( 'full' => $args )
				: array( 'full' => $argsFiltered, 'light' => $args );
		}
		if ( $multiLogicOr ) {

			if ( isset( $calc['light'] ) ) {
				$calc['full'] = $calc['light'];
				unset( $calc['light'] );
			} elseif ( $ajax ) {
				$lightFromSession = ReqWpf::getVar( 'wpf_light', 'session' );

				if ( isset( $lightFromSession ) ) {
					$calc['full'] = $lightFromSession;
				}           
			}

			if ( $isGetNames ) {
				$calc = array_merge( $calc, $this->addToArgsForLogicAnd( $taxonomies['get_names'], $calc['full'], $urlQuery ) );
			}       
		} elseif ( $isGetNames ) {

				$calc = array_merge( $calc, $this->removeFromArgsForLogicOr( $taxonomies['get_names'], $calc['full'] ) );       
		}

		$result    = array( 'exists' => array() );
		$tempTable = $this->tempFilterTable;
		foreach ( $calc as $mode => $args ) {

			if ( isset( $args['wpf_get_names_taxonomy'] ) ) {
				$taxonomy = (array) $args['wpf_get_names_taxonomy'];
			} elseif ( isset( $args['args'] ) ) {
				$taxonomy = array( $args['taxonomy'] );
				$args     = $args['args'];
			} else {
				$taxonomy = $taxonomies['names'];
			}

			$param          = array(
				'ajax'            => $ajax,
				'prodCatId'       => $prodCatId,
				'generalSettings' => $generalSettings,
				'currentSettings' => $currentSettings,
			);
			$args           = $this->addArgs( $args, $param );
			$isCalcCategory = ! is_null( $calcCategory );

			$param = array(
				'isCalcCategory'       => $isCalcCategory,
				'calcCategory'         => $calcCategory,
				'taxonomy'             => $taxonomy,
				'generalSettings'      => $generalSettings,
				'mode'                 => $mode,
				'forCount'             => $taxonomies['count'],
				'forCountWithChildren' => $taxonomies['count_with_children'],
				'withCount'            => ( ! empty( $taxonomies['count'] ) || $isCalcCategory ),
				'isInStockOnly'        => ( get_option( 'woocommerce_hide_out_of_stock_items', 'no' ) === 'yes' ),
				'currentSettings'      => $currentSettings,
				'ajax'                 => $ajax,
				'onlyCategories'       => $taxonomies['cat_only_children'],
			);

			// the search-everything plugin contains an error while adding the arguments
			if ( is_plugin_active( 'search-everything/search-everything.php' ) ) {
				remove_all_filters( 'posts_search' );
			} elseif ( is_plugin_active( 'custom-woocommerce-enhancements/custom-woocommerce-enhancements.php' ) ) {
				if ( isset($args['s']) && '' === $args['s'] ) {                    
					remove_filter( 'posts_search', 'custom_exact_word_search' );
				}
			}
			remove_filter( 'posts_request', 'relevanssi_prevent_default_request' );
			remove_filter( 'the_posts', 'relevanssi_query', 99 );

			$existTerms        = array();
			$calcCategories    = array();
			$this->isLightMode = ( 'light' === $mode ) || ( ! empty( $this->clausesLight ) && ! key_exists( 'light', $calc ) );
			$args['orderby']   = 'ID';
			$args['order']     = 'ASC';

			if ( ! empty( $args['meta_key'] ) && empty( $args['meta_value'] ) && empty( $args['meta_value_num'] ) ) {
				$args['meta_key'] = '';
			}
			$isModeStandart = in_array( $mode, array( 'full', 'light' ), true );
			
			$args = DispatcherWpf::applyFilters( 'addExistFilterArgs', $args );

			if ( ! empty( $this->clauses ) && ( ( ! $multiLogicOr && ! $isModeStandart ) || ( $multiLogicOr && $isModeStandart ) ) ) {
				$filterLoop = $this->getFilterLoopFromMode( $mode, $args );
			} else {
				$filterLoop = new WP_Query( $args );
			}
			$this->isLightMode = false;
			$listTable         = '';
			$havePosts         = $filterLoop->have_posts();
			
			$onlyHaveFound = ! empty($currentSettings['only_have_found']);

			if ( $havePosts && ! $onlyHaveFound ) {
				$createOtherTemporaryTable = false;

				if ( isset( $this->clausesByParam['variation']['base_request'] ) ) {

					$query = '';

					if ( ! $isModeStandart && ! $multiLogicOr && isset( $args['wpf_get_names_taxonomy'] ) ) {
						$query = $this->clausesByParam['variation']['base_request'][1];

						foreach ( $this->clausesByParam['variation']['conditions'] as $currentTax => $conditions ) {

							if ( $currentTax !== $args['wpf_get_names_taxonomy'] ) {

								if ( isset( $conditions['join'] ) ) {
									$query .= implode( '', $conditions['join'] );
								}
							}                       
						}

						$query .= $this->clausesByParam['variation']['base_request'][2];

						$where = '';

						foreach ( $this->clausesByParam['variation']['conditions'] as $currentTax => $conditions ) {

							if ( $currentTax !== $args['wpf_get_names_taxonomy'] ) {

								if ( isset( $conditions['where'] ) ) {
									$where .= ' AND ' . implode( ' AND ', $conditions['where'] );
								}                           
							}                       
						}

						if ( '' !== $where ) {
							$query .= $where;
						}
						$query .= $this->clausesByParam['variation']['base_request'][3];

						$first = true;

						foreach ( $this->clausesByParam['variation']['conditions'] as $currentTax => $conditions ) {

							if ( $currentTax !== $args['wpf_get_names_taxonomy'] ) {

								if ( isset( $conditions['having'] ) ) {

									if ( $first ) {
										$query .= ' HAVING ';
										$first  = false;
									}

									$query .= implode( '', $conditions['having'] );
								}                           
							}                       
						}                   
					}

					if ( $isModeStandart && $multiLogicOr ) {
						$query = implode( '', $this->clausesByParam['variation']['base_request'] );
					}

					if ( '' !== $query ) {
						$baseTable                 = $this->createTemporaryTable( $this->tempVarTable, $query, $mode );
						$filterLoop->request       = str_replace( $this->tempVarTable, $baseTable, $filterLoop->request );
						$listTable                 = $this->createTemporaryTable( $tempTable, $filterLoop->request, $mode );
						$createOtherTemporaryTable = true;
					}
				}

				if ( ! $createOtherTemporaryTable ) {
					$postfix   = ( $isModeStandart ) ? '' : $mode;
					$listTable = $this->createTemporaryTable( $tempTable, $filterLoop->request, $postfix );
				}

				if ( ! empty( $listTable ) ) {
					
					if ( ( isset( $args['product_cat'] ) || ( $ajax && $prodCatId ) ) && $this->getFilterSetting( $currentSettings, 'display_only_descendants_category', false ) ) {
						if ( $ajax && $prodCatId ) {
							$term = get_term_by( 'id', $prodCatId, 'product_cat' );
						} else {
							$term = get_term_by( 'slug', $args['product_cat'], 'product_cat' );
						}
						
						if ( $term ) {
							if ( $this->getFilterSetting($currentSettings, 'display_only_children_category', false) ) {
								$param['only_children_category'] = get_terms(array(
									'taxonomy'   => 'product_cat',
									'parent'     => $term->term_id,
									'hide_empty' => false,
									'fields'     => 'ids', 
								));
							} else {
								$param['only_children_category'] = get_term_children($term->term_id, 'product_cat');
							}
							
							if ( is_array($param['only_children_category']) && ! empty($param['only_children_category']) ) {
								$param['only_children_category'] = UtilsWpf::controlNumericValues($param['only_children_category'], '');
							}
						}                   
					}
					list( $existTerms, $calcCategories ) = $this->getTerms( $listTable, $param );
				}
			}

			switch ( $mode ) {
				case 'full':
					$result['exists']     = $existTerms;
					$result['categories'] = $calcCategories;
					$result['have_posts'] = $havePosts ? 1 : 0;
					break;
				case 'light':
					$result['all'] = $existTerms;
					break;
				default:
					if ( ! empty( $existTerms ) ) {
						$result['exists'] = array_replace( $result['exists'], $existTerms );
					} elseif ( is_array( $taxonomy ) ) {
						$currentTax = current( $taxonomy );

						if ( isset( $result['exists'][ $currentTax ] ) ) {
							$result['exists'][ $currentTax ] = array_fill_keys( array_flip( $result['exists'][ $currentTax ] ), 0 );
						}                   
					}
					break;
			}
			if ( ! $onlyHaveFound ) {
				//if ( ( 'full' === $mode && ! key_exists( 'light', $calc ) ) || 'light' === $mode ) {
				if ( 'full' === $mode || 'light' === $mode ) {
					$param  = array_merge( $param, array(
						'listTable'  => $listTable,
						'havePosts'  => $havePosts,
						'taxonomies' => $taxonomies,
						'calcMode'   => $mode,
						'calcVars'   => $calc,
					) );
					$result = $this->getExistsMore( $args, $param, $result );
				}
			}
		}

		$this->isLightMode = false;

		if ( '1' === ReqWpf::getVar( 'wpf_skip' ) ) {
			$recalculateFilters = $this->getFilterSetting( $settings, 'recalculate_filters', false );
			if ( $recalculateFilters ) {
				$fid                     = ReqWpf::getVar( 'wpf_fid' );
				$jsFound                 = ( ! is_null( $fid ) && ! empty( $fid ) ? 'wpfDoActionsAfterLoad(' . $fid . ',' . ( empty( $result['have_posts'] ) ? 0 : 1 ) . ');' : '' );
				$result['existsTermsJS'] = '<div class="wpfExistsTermsJS" data-fid="' . esc_attr($fid) . '"><script type="text/javascript">' . $jsFound . 'wpfShowHideFiltersAtts(' . wp_json_encode( $result['exists'] ) . ', ' . wp_json_encode( $result['existsUsers'] ) . ');</script><script type="text/javascript">wpfChangeFiltersCount(' . wp_json_encode( $result['exists'] ) . ');</script></div>';
			}
		}
		return $result;
	}

	/**
	 * Returns previously stored arguments in an object
	 *
	 * @param $args
	 *
	 * @return array
	 */
	public function getArgsWCQuery( $args, $currentSettings ) {
		$argsFiltered      = '';
		$postType          = '';
		$doNotUseShortcode = $this->getFilterSetting( $currentSettings, 'do_not_use_shortcut', false );

		if ( $doNotUseShortcode ) {
			if ( empty( $this->mainWCQuery ) ) {
				$q = new WP_Query( DispatcherWpf::applyFilters( 'beforeFilterExistsTermsWithEmptyArgs', array(
					'post_type'  => 'product',
					'meta_query' => array(),
					'tax_query'  => array(),
				) ) );
				$this->loadProductsFilter( $q );
			}
			$args         = $this->mainWCQuery;
			$argsFiltered = $this->mainWCQueryFiltered;

			return array( $args, $argsFiltered );
		}

		if ( is_null( $args ) ) {
			$filterId  = $this->currentFilterId;
			$filterKey = $this->shortcodeFilterKey . $filterId;
			$existSC   = ( count( $this->shortcodeWCQuery ) > 0 );
			if ( ! $doNotUseShortcode && ! isset( $this->shortcodeWCQuery[ $filterKey ] ) ) {
				$filterKey = '-';
			}
			if ( $existSC && isset( $this->shortcodeWCQuery[ $filterKey ] ) ) {
				$args         = $this->shortcodeWCQuery[ $filterKey ];
				$argsFiltered = isset( $this->shortcodeWCQueryFiltered[ $filterKey ] ) ? $this->shortcodeWCQueryFiltered[ $filterKey ] : '';
				$postType     = isset( $args['post_type'] ) ? $args['post_type'] : '';
			}
			if ( 'product' != $postType && ( ! is_array( $postType ) || ! in_array( 'product', $postType ) ) ) {
				$args         = $this->mainWCQuery;
				$argsFiltered = $this->mainWCQueryFiltered;
				$postType     = isset( $args['post_type'] ) ? $args['post_type'] : '';
				if ( 'product' !== $postType && ( ! is_array( $postType ) || ! in_array( 'product', $postType, true ) ) ) {
					if ( $existSC ) {
						$args         = reset( $this->shortcodeWCQuery );
						$argsFiltered = reset( $this->shortcodeWCQueryFiltered );
						$postType     = isset( $args['post_type'] ) ? $args['post_type'] : '';
					}
				}
			}
			if ( 'product' !== $postType && ( ! is_array( $postType ) || ! in_array( 'product', $postType, true ) ) ) {
				$q = new WP_Query( DispatcherWpf::applyFilters( 'beforeFilterExistsTermsWithEmptyArgs', array(
					'post_type'  => 'product',
					'meta_query' => array(),
					'tax_query'  => array(),
				) ) );
				$this->loadProductsFilter( $q );
				$args         = $this->mainWCQuery;
				$argsFiltered = $this->mainWCQueryFiltered;
			}

			if ( $doNotUseShortcode && 'product' !== $postType && ( ! is_array( $postType ) || ! in_array( 'product', $postType, true ) ) ) {
				$filterKey = '-';
				if ( $existSC && isset( $this->shortcodeWCQuery[ $filterKey ] ) ) {
					$args         = $this->shortcodeWCQuery[ $filterKey ];
					$argsFiltered = isset( $this->shortcodeWCQueryFiltered[ $filterKey ] ) ? $this->shortcodeWCQueryFiltered[ $filterKey ] : '';
				}
			}
		}
		if ( ! $this->isFiltered(false) && $this->getFilterSetting( $currentSettings, 'all_products_filtering', false ) && $this->getFilterSetting( $currentSettings, 'form_filter_by_all_products', false ) ) {
			$exclude = array( 'paged', 'posts_per_page', 'post_type', 'wc_query', 'orderby', 'order', 'fields' );
			foreach ( $args as $key => $value ) {
				if ( ! in_array( $key, $exclude ) ) {
					if ( is_string( $value ) ) {
						$args[$key] = '';
					}
					if ( is_array( $value ) ) {
						$args[$key] = array();
					}
				}
			}
		}


		return array( $args, $argsFiltered );
	}

	/**
	 * Adds arguments to $args array
	 *
	 * @param $args
	 * @param $param
	 *
	 * @return array
	 */
	public function addArgs( $args, $param ) {
		if ( isset( $args['taxonomy'] ) ) {
			unset( $args['taxonomy'], $args['term'] );
		}

		if ( is_null( $args ) || empty( $args ) || ! isset( $args['post_type'] ) || ( 'product' !== $args['post_type'] && ( is_array( $args['post_type'] ) && ! in_array( 'product', $args['post_type'], true ) ) ) ) {
			$args = array(
				'post_status'         => 'publish',
				'post_type'           => 'product',
				'ignore_sticky_posts' => true,
				'tax_query'           => array(),
			);
		}

		$addEFC = true;
		if ( isset( $args['tax_query'] ) ) {
			$i    = $this->searchValueQuery( $args['tax_query'], 'taxonomy', 'product_visibility', false );
			$taxQ = ( is_numeric( $i ) && isset( $args['tax_query'][ $i ] ) ? $args['tax_query'][ $i ] : false );

			if ( ! $taxQ && is_array( $args['tax_query'] ) ) {
				foreach ( $args['tax_query'] as $k => $tax ) {
					if ( is_array( $tax ) ) {
						$i = $this->searchValueQuery( $tax, 'taxonomy', 'product_visibility', false );
						if ( is_numeric( $i ) && isset( $tax[ $i ] ) ) {
							$taxQ = $tax[ $i ];
							break;
						}
					}
				}
			}
			if ( $taxQ ) {
				if ( isset( $taxQ['operator'] ) && ( 'NOT IN' == $taxQ['operator'] ) && isset( $taxQ['field'] ) && isset( $taxQ['terms'] ) ) {
					$exludeTerm = get_term_by( 'name', 'exclude-from-catalog', 'product_visibility', ARRAY_A );
					if ( $exludeTerm && isset( $exludeTerm[ $taxQ['field'] ] ) && is_array( $taxQ['terms'] ) && in_array( $exludeTerm[ $taxQ['field'] ], $taxQ['terms'] ) ) {
						$addEFC = false;
					}
				}
			}
		}
		if ( $addEFC ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'exclude-from-catalog',
				'operator' => 'NOT IN',
			);
		}

		if ( $param['prodCatId'] ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $param['prodCatId'],
			);
		}

		/*$args['nopaging']       = true;
		$args['posts_per_page'] = - 1;*/
		$args['nopaging']       = false;
		$args['posts_per_page'] = 1;
		$args['hide_empty']     = 1;
		$args['fields']         = 'ids';

		if ( class_exists( 'Iconic_WSSV_Query' ) ) {
			$args = $this->Iconic_Wssv_Query_Args( $args );
		}
		//Integration with AJAX Search for WooCommerce

		/*
		* Plugin URL: https://wordpress.org/plugins/ajax-search-for-woocommerce/
		* Author: Damian Góra
		*/
		if ( class_exists( 'DGWT_WC_Ajax_Search' ) ) {
			$searchIds = apply_filters( 'dgwt/wcas/search_page/result_post_ids', array() );
			if ( $searchIds && is_array( $searchIds ) ) {
				$postIds = isset( $args['post__in'] ) ? $args['post__in'] : '';
				if ( is_array( $postIds ) && ! empty( $postIds ) ) {
					if ( 1 !== count( $postIds ) || 0 !== $postIds[0] ) {
						$args['post__in'] = array_intersect( $postIds, $searchIds );
					}
				} else {
					$args['post__in'] = $searchIds;
				}
				$args['s'] = '';
			}
		}

		if ( ! empty( $args['post__in'] ) && ( 'product' === $args['post_type'] ) ) {
			$args['post_type'] = array( 'product', 'product_variation' );
		}
		$args = $this->addWooOptions( $args );
		foreach ( $param['generalSettings'] as $filter ) {
			$settings = ( isset( $filter['settings'] ) ) ? $filter['settings'] : array();
			$hiddens  = array( 'f_hidden_brands', 'f_hidden_categories', 'f_hidden_attributes', 'f_hidden_tags' );
			$replace  = false;
			foreach ( $hiddens as $hidden ) {
				if ( $this->getFilterSetting( $settings, $hidden ) ) {
					$replace = true;
				}
			}
			if ( $replace ) {
				foreach ( $args['tax_query'] as &$tax ) {
					if ( isset ( $tax['wpf_group'] ) && $tax['wpf_group'] === $filter['name'] && isset( $tax[0]['terms'] ) ) {
						$tax[0]['terms'] = $settings['f_mlist[]'];
					}
				}
			}
		}
		if ( $this->isVendor() && empty($args['author']) ) {
			$vendor = $this->getVendor();
			if ( ! empty($vendor) ) {
				$userObj = get_user_by( 'slug', $vendor );
				if ( isset( $userObj->ID ) ) {
					$args['author'] = $userObj->ID;
				}
			}
		}
		return DispatcherWpf::applyFilters( 'addFilterExistsItemsArgs', $args );
	}

	/**
	 * Returns items in filter blocks
	 *
	 * @param $filterLoop
	 * @param $param
	 *
	 * @return array
	 */
	public function getTerms( $listTable, $param ) {
		$calcCategories = array();
		$childs         = array();
		$names          = array();
		$colorGroup     = array();
		$addSqls        = array();
		$curSettings    = ( isset($param['currentSettings']) ? $param['currentSettings'] : array() );
		$byVariations   = ( ! empty($curSettings['filtering_by_variations']) && ! empty($curSettings['form_filter_by_variations']) );

		$openOneByOne                = isset($curSettings['open_one_by_one']) ? $curSettings['open_one_by_one'] : 0;
		$displayOnlyChildrenCategory = isset($curSettings['display_only_children_category']) ? $curSettings['display_only_children_category'] : 0;

		if ( $openOneByOne && $displayOnlyChildrenCategory ) {
			$isHierarchicalTaxonomy = array();
		}


		$customPrefixes = DispatcherWpf::applyFilters( 'getCustomPrefixes', array(), false );
		if ( empty( $customPrefixes ) ) {
			$taxonomyList = $param['taxonomy'];
		} else {
			$taxonomyList = array();
			foreach ( $param['taxonomy'] as $i => $tax ) {
				$pos = strpos( $tax, '-' );
				if ( ! $pos || ! in_array( substr( $tax, 0, $pos + 1 ), $customPrefixes, true ) ) {
					$taxonomyList[] = $tax;
				}
			}
		}

		global $wpdb;
		$sql = array();
		
		$stockJoin = '';
		if ( $param['isInStockOnly'] ) {
			$metaKeyId = $this->getMetaKeyId('_stock_status');
			if ( $metaKeyId ) {
				$valueId   = FrameWpf::_()->getModule('meta')->getModel('meta_values')->getMetaValueId($metaKeyId, 'outofstock');
				$stockJoin = ' INNER JOIN @__meta_data pm ON (pm.product_id=wpf_temp.ID AND pm.key_id=' . $metaKeyId . ' AND pm.val_id!=' . $valueId . ')';
			} else {
				$stockJoin = ' INNER JOIN ' . $wpdb->postmeta . " pm ON (pm.post_id=wpf_temp.ID AND pm.meta_key='_stock_status' AND pm.meta_value!='outofstock')";
			}
		}

		if ( ! empty( $taxonomyList ) ) {
			$addSqls['main']['withCount']    = $param['withCount'];
			$addSqls['main']['fields']       = ( $param['withCount'] ? '' : 'DISTINCT ' ) . 'tr.term_taxonomy_id, tt.term_id, tt.taxonomy, tt.parent' . ( $param['withCount'] ? ', COUNT(*) as cnt' : '' );
			$addSqls['main']['taxonomyList'] = implode( "', '", $taxonomyList );
			
			if ( $byVariations ) {
				$attrTaxonomyList = array();
				$case             = '';
				$mainAttrId       = $this->getMetaKeyId('_product_attributes');
				if ( $mainAttrId ) {
					foreach ( $taxonomyList as $tax ) {
						if ( strpos($tax, 'pa_') === 0 ) {
							$metaKeyId = $this->getMetaKeyId('attribute_' . $tax);
							if ( $metaKeyId ) {
								$isForVars = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueId($mainAttrId, '1', array('key2' => 'is_variation', 'key3' => $tax));
								if ( $isForVars ) {
									$attrTaxonomyList[$tax] = $metaKeyId;
									$case                  .= ' WHEN md_attr.key_id=' . $metaKeyId . " THEN '" . $tax . "'";
								}
							}
						}
					}
				}
				if ( ! empty($attrTaxonomyList) ) {
					//$query = 'SELECT wpf_temp.id, p_vars.id as child, md_vals.value, (CASE ' . $case . " ELSE '' END) as taxonomy" .
					$query                   = "SELECT wpf_temp.id, p_vars.id as child, md_vals.value, REPLACE(md_keys.meta_key, 'attribute_', '') as taxonomy" .
						' FROM ' . $listTable . ' as wpf_temp' .
						' INNER JOIN #__posts as p_vars on (p_vars.post_parent=wpf_temp.id)' .
						' INNER JOIN @__meta_data as md_attr ON (md_attr.product_id=p_vars.ID AND md_attr.key_id IN (' . implode(',', $attrTaxonomyList) . '))' .
						' INNER JOIN @__meta_values as md_vals ON (md_vals.id=md_attr.val_id)' .
						' INNER JOIN @__meta_keys as md_keys ON (md_keys.id=md_attr.key_id)' . 
						str_replace('wpf_temp.', 'p_vars.', $stockJoin);
					$listVariationAttributes = $this->createTemporaryTable($this->tempFilterTable . '_var_attrs', $query);

					$typeJoin  = '';
					$metaKeyId = $this->getMetaKeyId('_wpf_product_type');
					if ( $metaKeyId ) {
						$variableMetaId = FrameWpf::_()->getModule('meta')->getModel('meta_values')->getMetaValueId($metaKeyId, 'variable');
						if ( $variableMetaId ) {
							$typeJoin = ' INNER JOIN @__meta_data md_type ON (md_type.product_id=wpf_temp.ID AND md_type.key_id=' . $metaKeyId . ')';
						}
					}
					if ( empty($typeJoin) ) {
						$byVariations = false;
					}
				} else {
					$byVariations = false;
				}
			}
		}

		$taxonomyList = array();
		$colorGroup   = DispatcherWpf::applyFilters( 'getColorGroupForExistTerms', array(), $param );
		if ( ! empty( $colorGroup ) ) {
			foreach ( $param['taxonomy'] as $key => $tax ) {
				if ( key_exists( $tax, $colorGroup ) ) {
					unset( $param['taxonomy'][ $key ] );
					$taxonomyList[] = $tax;
				}
			}
			$addSqls['color']['withCount']    = false;
			$addSqls['color']['fields']       = 'tt.term_id, tt.taxonomy, wpf_temp.ID';
			$addSqls['color']['taxonomyList'] = implode( "', '", $taxonomyList );
		}

		foreach ( $addSqls as $key => $addSql ) {
			$sql[ $key ] = 'SELECT ' . $addSql['fields'] . ' FROM ' . $listTable . ' AS wpf_temp INNER JOIN ' . $wpdb->term_relationships . ' tr ON (tr.object_id=wpf_temp.ID) INNER JOIN ' . $wpdb->term_taxonomy . ' tt ON (tt.term_taxonomy_id=tr.term_taxonomy_id) ';
			/*if ( $addSql['withCount'] && $param['isInStockOnly'] ) {
				$metaKeyId = $this->getMetaKeyId( '_stock_status' );
				if ( $metaKeyId ) {
					$valueId      = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getMetaValueId( $metaKeyId, 'outofstock' );
					$sql[ $key ] .= ' INNER JOIN @__meta_data pm ON (pm.product_id=wpf_temp.ID AND pm.key_id=' . $metaKeyId . ' AND pm.val_id!=' . $valueId . ')';
				} else {
					$sql[ $key ] .= ' INNER JOIN ' . $wpdb->postmeta . " pm ON (pm.post_id=wpf_temp.ID AND pm.meta_key='_stock_status' AND pm.meta_value!='outofstock')";
				}
			}*/
			if ( $addSql['withCount'] && $param['isInStockOnly'] ) {
				$sql[ $key ] .= $stockJoin;
			}
			if ( $byVariations ) {
				$sql[ $key ] .= ' INNER JOIN ' . $wpdb->terms . ' ttt ON (ttt.term_id=tt.term_id) ' . $typeJoin;
			}
				
			$sql[ $key ] .= ' WHERE tt.taxonomy IN (\'' . $addSql['taxonomyList'] . '\')';
			
			if ( $byVariations ) {
				$sql[ $key ] .= ' AND (md_type.val_id!=' . $variableMetaId . 
					' OR tt.taxonomy NOT IN (\'' . implode("','", array_keys($attrTaxonomyList)) . '\')' .
					' OR EXISTS(SELECT 1 FROM ' . $listVariationAttributes . ' as p_childs' . 
					' WHERE p_childs.id=wpf_temp.id and p_childs.taxonomy=tt.taxonomy and p_childs.value=ttt.slug LIMIT 1))';
			}
			
			if ( $addSql['withCount'] ) {
				$sql[ $key ] .= ' GROUP BY tr.term_taxonomy_id';
			}
		}

		if ( FrameWpf::_()->proVersionCompare( WPF_PRO_REQUIRES, '>=' ) ) {
			//$termProducts = DbWpf::get( $sql );
			$termProducts = ! isset( $sql['main'] ) ? array() : DbWpf::get( $sql['main'] );

			if ( false === $termProducts ) {
				$termProducts = array();
			}

			$termProducts = DispatcherWpf::applyFilters( 'addCustomAttributesSql', $termProducts, array(
				'taxonomies'      => $param['taxonomy'],
				'withCount'       => $param['withCount'],
				'listTable'       => $listTable,
				'generalSettings' => $param['generalSettings'],
				'currentSettings' => $param['currentSettings'],
			) );
		} else {
			$sql['main']              = DispatcherWpf::applyFilters( 'addCustomAttributesSql', $sql['main'], array(
				'taxonomies'      => $param['taxonomy'],
				'withCount'       => $param['withCount'],
				'productList'     => '(select id from ' . $listTable . ')',
				'generalSettings' => $param['generalSettings'],
				'currentSettings' => $param['currentSettings'],
			) );
			$wpdb->wpf_prepared_query = $sql['main'];
			$termProducts             = $wpdb->get_results( $wpdb->wpf_prepared_query );
		}

		$existTerms = array();

		foreach ( $termProducts as $term ) {
			$taxonomy       = $term['taxonomy'];
			$isCat          = 'product_cat' === $taxonomy;
			$name           = urldecode( $taxonomy );
			$names[ $name ] = $taxonomy;
			if ( ! isset( $existTerms[ $name ] ) ) {
				$existTerms[ $name ] = array();
			}

			$termId = $term['term_id'];

			if ( $isCat && isset( $param['only_children_category'] ) && ! in_array( (int) $termId, $param['only_children_category'], true ) ) {

				continue;
			}
			
			if ( $isCat && ! empty( $param['onlyCategories'] ) ) {
				$found = true;
				foreach ( $param['onlyCategories'] as $catIds ) {
					if ( ! in_array( (int) $termId, $catIds, true ) ) {
						$found = false;
						break;
					}
				}
				if ( ! $found ) {
					continue;
				}
			}

			$cnt                            = $param['withCount'] ? intval( $term['cnt'] ) : 0;
			$existTerms[ $name ][ $termId ] = $cnt;

			$parent = ( isset( $term['parent'] ) ) ? (int) $term['parent'] : 0;
			if ( $isCat && $param['isCalcCategory'] && $param['calcCategory'] === $parent ) {
				
				$calcCategories[ $termId ] = $cnt;
			}
			
			if ( 0 !== $parent ) {
				if ( isset($isHierarchicalTaxonomy) && ! key_exists($name, $isHierarchicalTaxonomy) ) {
					$isHierarchicalTaxonomy[$name] = $termProducts;
				}
				$children = array( $termId );
				do {
					if ( ! isset( $existTerms[ $name ][ $parent ] ) ) {
						$existTerms[ $name ][ $parent ] = 0;
					}
					if ( isset( $childs[ $parent ] ) ) {
						array_merge( $childs[ $parent ], $children );
					} else {
						$childs[ $parent ] = $children;
					}
					$parentTerm = get_term( $parent, $taxonomy );
					$children[] = $parent;
					if ( $parentTerm && isset( $parentTerm->parent ) ) {
						$parent = $parentTerm->parent;
						if ( $isCat && $param['isCalcCategory'] && $param['calcCategory'] === $parent ) {
							$calcCategories[ $parentTerm->term_id ] = 0;
						}
					} else {
						$parent = 0;
					}
				} while ( 0 !== $parent );
			}
		}

		if ( 'light' !== $param['mode'] && $param['withCount'] ) {
			foreach ( $existTerms as $taxonomy => $terms ) {
				$allCalc          = in_array( $taxonomy, $param['forCount'], true );
				$calcWithChildren = in_array( $taxonomy, $param['forCountWithChildren'], true );
				if ( ! ( $allCalc || ( $param['isCalcCategory'] && 'product_cat' === $taxonomy ) || $calcWithChildren ) ) {
					continue;
				}

				foreach ( $terms as $termId => $cnt ) {
					if ( $calcWithChildren ) {
						$termIds = get_term_children( $termId, $names[ $taxonomy ] );
					} elseif ( isset( $childs[ $termId ] ) && ( $allCalc || isset( $calcCategories[ $termId ] ) ) ) {
							$termIds = $childs[ $termId ];
					} else {
						continue;
					}
					$termIds[] = $termId;

					$sqlTemp                            = "SELECT count(DISTINCT tr.`object_id`) FROM {$listTable} AS wpf_temp 
    					INNER JOIN {$wpdb->term_relationships} AS tr ON (tr.`object_id`=wpf_temp.`ID`) 
					    INNER JOIN {$wpdb->term_taxonomy} AS wtf ON tr.`term_taxonomy_id` = wtf.`term_taxonomy_id`    
						WHERE wtf.`term_id` IN (" . implode( ',', $termIds ) . ')';
					$cnt                                = intval( DbWpf::get( $sqlTemp, 'one' ) );
					$existTerms[ $taxonomy ][ $termId ] = $cnt;
					if ( isset( $calcCategories[ $termId ] ) ) {
						$calcCategories[ $termId ] = $cnt;
					}
				}
			}
		}

		if ( ! empty( $colorGroup ) && isset( $sql['color'] ) ) {
			$termProducts = DbWpf::get( $sql['color'] );
			$existTerms   = DispatcherWpf::applyFilters( 'getExistTermsColor', $existTerms, $colorGroup, $termProducts );
		}

		if ( isset($isHierarchicalTaxonomy) && ! empty($isHierarchicalTaxonomy) ) {
			$existTerms = $this->filterToOnlyChildrenOfSelectedParent($existTerms, $isHierarchicalTaxonomy);
		}

		return array($existTerms, $calcCategories);
	}

	public function filterToOnlyChildrenOfSelectedParent( $existTerms, $isHierarchicalTaxonomy ) {
		$urlQuery = ReqWpf::get('get');
		if ( empty($urlQuery) ) {
			$params = ReqWpf::get('post');
			if ( ! empty($params) && isset($params['currenturl']) ) {
				$curUrl = $params['currenturl'];
				$parts  = wp_parse_url($curUrl);
				if ( ! empty($parts['query']) ) {
					parse_str($parts['query'], $urlQuery);
				}
			}
		}
		
		foreach ( $isHierarchicalTaxonomy as $name => $terms ) {
			$matchingKeys = ( ! empty($urlQuery) ) 
				? preg_grep('/^wpf_filter_' . preg_quote($name, '/') . '_\d+$/', array_keys($urlQuery)) 
				: array(); 

			$termChildren = array();
			$rootTermIds  = array();
		
			foreach ( $terms as $term ) {
				$termId   = (int) $term['term_id'];
				$parentId = (int) $term['parent'];
		
				if ( ! isset($termChildren[$parentId]) ) {
					$termChildren[$parentId] = array();
				}
				$termChildren[$parentId][] = $termId;
		
				if ( 0 === $parentId ) {
					$rootTermIds[] = $termId;
				}
			}

			if ( ! empty($matchingKeys) ) {
				$maxIndex = 0;
				$maxKey   = reset($matchingKeys);
		
				foreach ( $matchingKeys as $key ) {
					preg_match('/^wpf_filter_' . preg_quote($name, '/') . '_(\d+)$/', $key, $matches);
					if ( isset($matches[1]) && (int) $matches[1] > $maxIndex ) {
						$maxIndex = (int) $matches[1];
						$maxKey   = $key;
					}
				}
		
				if ( ! empty($maxKey) ) {
					$allowed = array();
					$term    = get_term_by('slug', $urlQuery[$maxKey], $name);
					if ( $term ) {
						$term_id     = (int) $term->term_id;
						$parent_id   = (int) $term->parent;
						$allowed[]   = $term_id;
						$descendants = $this->getDescendantIds($terms, $term_id);
						$ancestors   = get_ancestors($term_id, $name, 'taxonomy');
		
						$siblings = array();
						foreach ( $terms as $t ) {
							if ( (int) $t['parent'] === $parent_id ) {
								$siblings[] = (int) $t['term_id'];
							}
						}
		
						$allowed = array_merge($allowed, $descendants, $ancestors, $siblings);
						$allowed = array_unique($allowed);
					
						$hierarchicalByLevel = array();                        
					
						foreach ( $terms as $t ) {
							$term_id = (int) $t['term_id'];
							if ( in_array( $term_id, $allowed, true) ) {
								$level    = 0;
								$parentId = (int) $t['parent'];
					
								while ( 0 !== $parentId ) {
									++$level;
							
									$found = false;
									foreach ( $terms as $pt ) {
										if ( (int) $pt['term_id'] === $parentId ) {
											$parentId = (int) $pt['parent'];
											$found    = true;
											break;
										}
									}
									
									if ( ! $found ) {
										break;
									}
								}
								
								if ( ! isset($hierarchicalByLevel[$level]) ) {
									$hierarchicalByLevel[$level] = array();
								}
								$hierarchicalByLevel[$level][$term_id] = $existTerms[$name][$term_id];
							}
						}
						
						ksort($hierarchicalByLevel);
						
						if ( ! empty($hierarchicalByLevel) ) {
							$existTerms[$name . '_hierarchical'] = $hierarchicalByLevel;
						}
		
						if ( ! empty($existTerms[$name]) ) {
							$existTerms[$name] = array_intersect_key($existTerms[$name], array_flip($allowed));
						}
					}
				}
			} elseif ( ! empty($existTerms[$name]) ) {
				$existTerms[$name] = array_intersect_key($existTerms[$name], array_flip($rootTermIds));                    
					
				$maxLevel = 0;
				$cache = array();
				foreach ( $terms as $term ) {
					$level    = 0;
					$parentId = (int) $term['parent'];
					if (isset($cache[$parentId])) {
						continue;
					}
					$firstParent = $parentId;
					while ( 0 !== $parentId ) {
						if (isset($cache[$parentId])) {
							$level += $cache[$parentId];
							break;
						}
						++$level;
						$found = false;
						foreach ( $terms as $pt ) {
							if ( (int) $pt['term_id'] === $parentId ) {
								$parentId = (int) $pt['parent'];
								$found    = true;
								break;
							}
						}
						if ( ! $found ) {
							break;
						}
					}
					$cache[$firstParent] = $level;
					$maxLevel = max($maxLevel, $level);
				}
					
				$hierarchicalByLevel = array();
				for ( $i = 0; $i <= $maxLevel; $i++ ) {
					$hierarchicalByLevel[$i] = array();                        
					if ( 0 === $i ) {
						foreach ( $rootTermIds as $termId ) {
							if ( isset($existTerms[$name][$termId]) ) {
								$hierarchicalByLevel[0][$termId] = $existTerms[$name][$termId];
							}
						}
					}
				}
					
				$existTerms[$name . '_hierarchical'] = $hierarchicalByLevel;
			}
		}
		
		return $existTerms;
	}  

	public function getDescendantIds( array $terms, $parentId ) {
		$descendants = array();
		foreach ( $terms as $term ) {
			if ( (int) $term['parent'] === (int) $parentId ) {
				$term_id       = (int) $term['term_id'];
				$descendants[] = $term_id;
				$descendants   = array_merge($descendants, $this->getDescendantIds($terms, $term_id));
			}
		}
		return $descendants;
	}

	/**
	 * Returns additional data on minimum and maximum prices and users
	 *
	 * @param $args
	 * @param $param
	 *
	 * @return mixed
	 */
	public function getExistsMore( $args, $param, $result ) {
		global $wpdb;
		if ( ! isset($result['existsPrices']) ) {
			$result['existsPrices']              = new stdClass();
			$result['existsPrices']->wpfMinPrice = 1000000000;
			$result['existsPrices']->wpfMaxPrice = 0;
			$result['existsPrices']->decimal     = 0;
			$result['existsPrices']->dataStep    = '1';
			$result['existsUsers']               = array();
		}
		$listTable = $param['listTable'];
		
		$mode = $param['calcMode'];
		$need = ( 'full' === $mode && ! key_exists('light', $param['calcVars']) ) || 'light' === $mode;

		if ( $param['havePosts'] && ! empty ( $param['taxonomies']['other_names'] ) ) {
			foreach ( $param['generalSettings'] as $setting ) {
				if ( ! isset( $setting['id'] ) ) {
					continue;
				}
				if ( in_array( $setting['id'], $param['taxonomies']['other_names'], true ) ) {
					if ( 'wpfPrice' == $setting['id'] ) {
						$keep = $param['taxonomies']['keep_recount_price'];
						if ( ( ! $keep && ! $need ) || ( $keep && 'full' != $mode ) ) {
							continue;
						}
					} elseif ( ! $need ) {
						continue;
					}
					switch ( $setting['id'] ) {
						case 'wpfPrice':
						case 'wpfPriceRange':
							$listTableForPrice = $listTable;
							if ( isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ) {
								$issetArgsPrice = false;
								foreach ( $args['meta_query'] as $key => $row ) {
									if ( isset( $row['price_filter'] ) ) {
										$issetArgsPrice = true;
										unset ( $args['meta_query'][ $key ] );
									}
								}
								if ( $issetArgsPrice ) {
									$filterLoop = new WP_Query( $args );
									if ( $filterLoop->have_posts() ) {
										$listTableForPrice = $this->createTemporaryTable( $this->tempFilterTable . '_price', $filterLoop->request );
									}
								}
							}
							list( $result['existsPrices']->decimal, $result['existsPrices']->dataStep ) = DispatcherWpf::applyFilters( 'getDecimal', array(
								0,
								1,
							), $setting['settings'] );
							if ( 'wpfPriceRange' === $setting['id'] ) {
								$price = $this->getView()->wpfGetFilteredPriceFromProductList( $setting['settings'], $listTableForPrice, false, $result['existsPrices']->decimal );
							} else {
								$price = $this->getView()->wpfGetFilteredPriceFromProductList( $setting['settings'], $listTableForPrice, true, $result['existsPrices']->decimal );
							}

							if ( is_object( $price ) ) {
								$result['existsPrices']->wpfMinPrice = $price->wpfMinPrice;
								$result['existsPrices']->wpfMaxPrice = $price->wpfMaxPrice;
								if ( isset( $price->tax ) ) {
									$result['existsPrices']->tax = $price->tax;
								}
							}
							break;

						case 'wpfAuthor':
						case 'wpfVendors':
							if ( empty( $result['existsUsers'] ) ) {
								$query = 'SELECT DISTINCT ' . $wpdb->users . '.ID' .
										' FROM ' . $listTable . ' AS wpf_temp' .
										' INNER JOIN ' . $wpdb->posts . ' p ON (p.ID=wpf_temp.ID)' .
										' JOIN ' . $wpdb->users . ' ON p.post_author = ' . $wpdb->users . '.ID';

								$result['existsUsers'] = dbWpf::get( $query );
							}
							break;

						case 'wpfAttribute':
							if ( false === $param['ajax'] ) {
								$frontendType = $this->getFilterSetting( $setting['settings'], 'f_frontend_type', '' );

								if ( 'slider' === $frontendType ) {
									$showAllSliderAttributes = $this->getFilterSetting( $setting['settings'], 'f_show_all_slider_attributes', false );

									if ( $showAllSliderAttributes ) {
										$this->clauses = array();
										$name          = $setting['name'];
										$data          = ReqWpf::get( 'get' );
										unset( $data[ $name ] );
										$args['meta_query'] = DispatcherWpf::applyFilters( 'addCustomMetaQueryPro', $args['meta_query'], $data, 'url' );
										$filterLoop         = new WP_Query( $args );

										if ( $filterLoop->have_posts() ) {
											$listTable = $this->createTemporaryTable( $this->tempFilterTable . '_attribute', $filterLoop->request );

											if ( ! empty( $listTable ) ) {
												list( $existsTerms, $calcCategories ) = $this->getTerms( $listTable, $param, $result['exists'] );
												$customPrefixes                       = DispatcherWpf::applyFilters( 'getCustomPrefixes', array(), false );

												foreach ( $customPrefixes as $prefix ) {

													if ( strpos( $name, $prefix ) === 0 ) {
														$name = str_replace( $prefix, '', $name );
													}                                               
												}

												if ( isset( $existsTerms[ $name ] ) ) {
													$result['exists'][ $name ] = $existsTerms[ $name ];
												}                                           
											}                                       
										}                                   
									}
								}
							}
							break;
					}
				}
			}
		}

		return $result;
	}

	public function addAjaxFilterForYithWoocompare( $actions ) {
		return array_merge( $actions, array( 'filtersFrontend' ) );
	}

	public function getAllPages() {
		global $wpdb;
		$allPages = dbWpf::get( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'page' AND post_status IN ('publish','draft') ORDER BY post_title" );
		$pages    = array();
		if ( ! empty( $allPages ) ) {
			foreach ( $allPages as $p ) {
				$pages[ $p['ID'] ] = $p['post_title'];
			}
		}

		return $pages;
	}

	public function isWcVendorsPluginActivated() {
		return class_exists( 'WC_Vendors' );
	}

	/**
	 * Get logic for filtering.
	 *
	 * @return array
	 */
	public function getAttrFilterLogic( $mode = '' ) {
		$logic = array(
			'display'  => array(
				'and' => 'And',
				'or'  => 'Or',
			),
			'loop'     => array(
				'and' => 'AND',
				'or'  => 'IN',
			),
			'delimetr' => array(
				'and' => ',',
				'or'  => '|',
			),
		);

		$logic = DispatcherWpf::applyFilters( 'getAttrFilterLogic', $logic );

		return empty( $mode ) ? $logic : ( isset( $logic[ $mode ] ) ? $logic[ $mode ] : array() );
	}

	public function getFilterTagsList() {
		return array( 0 => 'Default', 1 => 'h1', 2 => 'h2', 3 => 'h3', 4 => 'h4', 5 => 'h5' );
	}

	public function getCategoriesDisplay( $tax = 'product_cat' ) {
		$catArgs = array(
			'taxonomy'   => $tax,
			'orderby'    => 'name',
			'order'      => 'asc',
			'hide_empty' => false,
		);

		$productCategories = get_terms( $catArgs );
		$categoryDisplay   = array();
		$parentCategories  = array();
		if ( is_array($productCategories) ) {
			foreach ( $productCategories as $c ) {
				if ( 0 == $c->parent ) {
					array_push( $parentCategories, $c->term_id );
				}
				$categoryDisplay[ $c->term_id ] = '[' . $c->term_id . '] ' . $c->name;
			}
		}

		return array( $categoryDisplay, $parentCategories );
	}

	public function getTagsDisplay() {
		$tagArgs = array(
			'taxonomy'   => 'product_tag',
			'orderby'    => 'name',
			'order'      => 'asc',
			'hide_empty' => false,
			'parent'     => 0,
		);

		$productTags = get_terms( $tagArgs );
		$tagsDisplay = array();
		if ( is_array( $productTags ) ) {
			foreach ( $productTags as $t ) {
				$tagsDisplay[ $t->term_id ] = $t->name;
			}
		}

		return array( $tagsDisplay );
	}

	public function getAttributesDisplay( $withCustom = true ) {
		$productAttr = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : array();

		if ( $withCustom ) {
			$productAttr = DispatcherWpf::applyFilters( 'addCustomAttributes', $productAttr );
		}

		$attrDisplay = array( 0 => esc_html__( 'Select...', 'woo-product-filter' ) );
		$attrTypes   = array();
		$attrNames   = array();
		foreach ( $productAttr as $attr ) {
			$attrId               = (int) $attr->attribute_id;
			$slug                 = empty( $attrId ) ? $attr->attribute_slug : $attrId;
			$attrDisplay[ $slug ] = $attr->attribute_label;
			$attrTypes[ $slug ]   = isset( $attr->custom_type ) ? $attr->custom_type : '';
			$attrNames[ $slug ]   = isset( $attr->filter_name ) ? $attr->filter_name : 'wpf_filter_' . $attr->attribute_name;
		}

		return array( $attrDisplay, $attrTypes, $attrNames );
	}

	public function getRolesDisplay() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . '/wp-admin/includes/user.php';
		}
		$rolesMain = get_editable_roles();
		$roles     = array();

		foreach ( $rolesMain as $key => $r ) {
			$roles[ $key ] = $r['name'];
		}

		return array( $roles );
	}

	/**
	 * Exlude parent terms from term list
	 *
	 * @param array $termList
	 * @param string $taxonomy
	 *
	 * @return array
	 */
	public function exludeParentTems( $termList, $taxonomy ) {
		foreach ( $termList as $key => $termId ) {
			$parents = get_ancestors( $termId, $taxonomy, 'taxonomy' );

			if ( is_array( $parents ) ) {
				// remove all parent termsId from main parent list
				foreach ( $parents as $parentId ) {
					if ( array_search( $parentId, $termList ) !== false ) {
						$keyParent = array_search( $parentId, $termList );
						unset( $termList[ $keyParent ] );
					}
				}
			}
		}

		return $termList;
	}

	/**
	 * Exlude parent terms from term list
	 *
	 * @param array $termList
	 * @param string $taxonomy
	 *
	 * @return array
	 */
	public function exludeChildTems( $termList, $taxonomy ) {
		foreach ( $termList as $key => $termId ) {
			$children = get_term_children( $termId, $taxonomy );
			if ( is_array( $children ) ) {
				// remove all parent termsId from main parent list
				foreach ( $children as $childId ) {
					if ( array_search( $childId, $termList ) !== false ) {
						$keyParent = array_search( $childId, $termList );
						unset( $termList[ $keyParent ] );
					}
				}
			}
		}

		return $termList;
	}

	/**
	 * Add shortcode attributes to additional html data attributes
	 *
	 * @param array $attributes
	 */
	public function addWoocommerceShortcodeQuerySettings( $attributes ) {
		$shortcodeAttr = htmlentities( UtilsWpf::jsonEncode( $attributes ), ENT_COMPAT );

		echo '<span class="wpfHidden" data-shortcode-attribute="' . esc_html( $shortcodeAttr ) . '"></span>';
	}

	public static function getProductsShortcode( $content ) {
		$shortcode_tags = array(
			'products'      => 'WC_Shortcodes::products',
			'sale_products' => 'WC_Shortcodes::sale_products',
		);
		$original       = $content;
		if ( empty($content) ) {
			$id = get_the_ID();
			if ( $id ) {
				$p = get_post($id);
				if ( $p ) {
					$content = $p->post_content;
				}
			}
		}

		if ( false === strpos( $content, '[' ) ) {
			return $original;
		}

		if ( empty( $shortcode_tags ) || ! is_array( $shortcode_tags ) ) {
			return $original;
		}

		preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );
		$tagnames = array_intersect( array_keys( $shortcode_tags ), $matches[1] );

		if ( empty( $tagnames ) ) {
			// some themes hide woocommerce shortcodes inside their shortcodes,
			// so there is nothing left to do but let them run for execution
			$theme = wp_get_theme();
			if ( $theme instanceof WP_Theme ) {
				$themeName = ( '' !== $theme['Parent Theme'] ) ? $theme['Parent Theme'] : $theme['Name'];
				if ( 'Divi' === $themeName ) {
					//add_filter( 'pre_do_shortcode_tag', array( 'WoofiltersWpf', 'getOtherShortcodeAttr' ), 10, 3 );
					add_filter( 'pre_do_shortcode_tag', function ( $return, $tag, $attr ) {
						if ( 'et_pb_shop' === $tag ) {
							if ( isset( $attr['module_class'] ) && '' !== $attr['module_class'] ) {
								self::$otherShortcodeAttr['class'] = $attr['module_class'];
							} else {
								unset( self::$otherShortcodeAttr['class'] );
							}
						}

						return $return;
					}, 10, 3 );

					preg_match_all( '@(\[et_pb_shop.*?\/et_pb_shop\])@', $content, $diviShortCodes );
					if ( isset( $diviShortCodes[1] ) ) {
						foreach ( $diviShortCodes[1] as $diviShortCode ) {
							do_shortcode( $diviShortCode );
						}
					}
				} elseif ( 'Shoptimizer' === $themeName ) {
					preg_match_all( '@(\[elementor-template.*?\])@', $content, $elementorTemplates );
					if ( isset( $elementorTemplates[1] ) ) {
						foreach ( $elementorTemplates[1] as $elementorTemplate ) {
							do_shortcode( $elementorTemplate);
						}
					}
				}
			}
			
			return $original;
		}

		$pattern = get_shortcode_regex( $tagnames );
		preg_match_all( "/$pattern/", $content, $matches );
		if ( count( $matches ) > 3 ) {
			foreach ( (array) $matches[3] as $key => $m ) {
				if ( 'sale_products' === $matches[2][ $key ] ) {
					$m .= ' on_sale="true"';
				}
				new WC_Shortcode_Products( shortcode_parse_atts( $m ), 'products' );
			}
		}

		return $original;
	}

	public function queryResults( $result ) {
		if ( 0 === $result->total && $this->isFiltered(false) ) {
			$options = FrameWpf::_()->getModule( 'options' )->getModel( 'options' )->getAll();
			if ( isset( $options['not_found_products_message'] ) && '1' === $options['not_found_products_message']['value'] ) {
				echo '<p class="woocommerce-info">' . esc_html__( 'No products were found matching your selection.', 'woocommerce' ) . '</p>';
			}
		}

		return $result;
	}

	public function getElementorClass( $data ) {
		$rawData = $data->get_raw_data();
		if ( isset( $rawData['settings']['_css_classes'] ) && '' !== $rawData['settings']['_css_classes'] ) {
			self::$currentElementorClass = $rawData['settings']['_css_classes'];
		}
	}

	public function shortcodeAttsProducts( $out, $pairs, $atts ) {
		if ( isset( $atts['on_sale'] ) && ! isset( $out['on_sale'] ) ) {
			$out['on_sale'] = $atts['on_sale'];
		}
		$out['cache'] = false;

		return $out;
	}

	public function addWpfMetaClauses( $params ) {
		if ( empty( $params['values'] || $params['keyId'] ) ) {
			return;
		}
		global $wpdb;
		$isLight   = empty( $params['isLight'] ) ? false : $params['isLight'];
		$isAnd     = isset( $params['isAnd'] ) && true === $params['isAnd'];
		$isBetween = isset( $params['isAnd'] ) && 'BETWEEN' === $params['isAnd'];
		$keyId     = $params['keyId'];

		$field  = empty( $params['field'] ) ? 'id' : $params['field'];
		$values = UtilsWpf::controlNumericValues( $params['values'], $field );

		$i       = 0;
		$clauses = array( 'join' => array(), 'where' => array() );
		
		if ( empty($params['searchLogic']) ) {
			foreach ( $values as $val ) {
				++$i;
				$clauses['join'][ $i ] = ' INNER JOIN ' . DbWpf::getTableName( 'meta_data' ) . ' AS wpf_meta__#i ON (wpf_meta__#i.product_id=' . $wpdb->posts . '.ID AND wpf_meta__#i.key_id' . ( is_array( $keyId ) ? ' IN (' . implode( ',', $keyId ) . ')' : '=' . $keyId ) . ')';
				$clauses['where'][$i]  = ' AND wpf_meta__#i.val_' . $field .
											( $isAnd ? '=' . $val : ( $isBetween ? ' BETWEEN ' . ( empty( $values[0] ) ? 0 : $values[0] ) . ' AND ' . ( empty( $values[1] ) ? 0 : $values[1] ) : ' IN (' . implode( ',', $values ) . ')' ) );
				if ( ! $isAnd ) {
					break;
				}
			}
		} else {
			++$i;
			$keyDec = ! empty($params['keyName']) && $this->getMetaKeyId($params['keyName'], 'meta_type') == 1;

			$clauses['join'][ $i ] = ' INNER JOIN ' . DbWpf::getTableName( 'meta_data' ) . ' AS wpf_meta__#i ON (wpf_meta__#i.product_id=' . $wpdb->posts . '.ID AND wpf_meta__#i.key_id' . ( is_array( $keyId ) ? ' IN (' . implode( ',', $keyId ) . ')' : '=' . $keyId ) . ')';
			if ( $keyDec ) {
				$clauses['where'][$i] = ' AND wpf_meta__#i.val_dec' . $params['searchLogic'] . $params['values'][0];
			} else {
				$clauses['join'][ $i ] .= ' INNER JOIN ' . DbWpf::getTableName( 'meta_values' ) . ' AS wpf_meta_values__#i ON (wpf_meta_values__#i.id=wpf_meta__#i.val_id)';
				$clauses['where'][$i]   = ' AND wpf_meta_values__#i.value+0' . $params['searchLogic'] . $params['values'][0];
			}
		}
		$this->addFilterClauses( $clauses, $isLight, $params['urlParam'] );

		return;
	}

	public function getOtherShortcodeAttr( $return, $tag, $attr ) {
		if ( 'et_pb_shop' === $tag ) {
			if ( isset( $attr['module_class'] ) && '' !== $attr['module_class'] ) {
				self::$otherShortcodeAttr['class'] = $attr['module_class'];
			} else {
				unset( self::$otherShortcodeAttr['class'] );
			}
		}

		return $return;
	}

	public function getFilterLoopFromMode( $mode, $args ) {
		$clauses = $this->clauses;

		if ( 'full' === $mode ) {

			foreach ( $this->clausesByParam as $mode => $clausesMode ) {
				if ( 'variation' !== $mode ) {
					foreach ( $clausesMode as $key => $clausesRemove ) {
						if ( isset( $this->clauses[ $key ] ) ) {
							$this->clauses[ $key ] = array_diff( $this->clauses[ $key ], $clausesRemove );
						}
					}
				}
			}       
		} elseif ( isset( $this->clausesByParam[ $mode ] ) ) {

			foreach ( $this->clausesByParam[ $mode ] as $key => $clausesRemove ) {
				if ( isset( $this->clauses[ $key ] ) ) {
					$this->clauses[ $key ] = array_diff( $this->clauses[ $key ], $clausesRemove );
				}
			}
		}

		$filterLoop    = new WP_Query( $args );
		$this->clauses = $clauses;

		return $filterLoop;
	}

	public function getTaxonomyByUrl( $param ) {
		$taxonomy = null;
		$param    = preg_replace( '/(_\d+)$/', '', $param );

		if ( 0 === strpos( $param, 'wpf_filter_cat_' ) ) {
			$taxonomy = 'product_cat';
		} elseif ( 0 === strpos( $param, 'product_tag_' ) ) {
			$taxonomy = 'product_tag';
		} elseif ( 0 === strpos( $param, 'wpf_filter_pwb.' ) ) {
			$taxonomy = 'pwb-brand';
		} elseif ( 0 === strpos( $param, 'fmeta-' ) ) {
			$taxonomy = $param;
		} elseif ( 0 === strpos( $param, 'wpf_filter_' ) ) {
			$taxonomy = preg_replace( '/^wpf_filter_/', 'pa_', $param );
		}

		return $taxonomy;
	}
	public function getDefaultSettings() {
		$defaults = array(
			'force_theme_templates' => '',
		);
		return DispatcherWpf::applyFilters('getDefaultSettings', $defaults);
	}
}
