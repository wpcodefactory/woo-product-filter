<?php
/**
 * Product Filter by WBW - WoofiltersControllerWpf Class
 *
 * @version 3.1.1
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WoofiltersControllerWpf extends ControllerWpf {

	protected $_code = 'woofilters';

	protected function _prepareTextLikeSearch( $val ) {
		$query = '(title LIKE "%' . $val . '%"';
		if ( is_numeric($val) ) {
			$query .= ' OR id LIKE "%' . (int) $val . '%"';
		}
		$query .= ')';
		return $query;
	}
	public function _prepareListForTbl( $data ) {
		foreach ( $data as $key => $row ) {
			$id        = $row['id'];
			$shortcode = '[' . WPF_SHORTCODE . ' id=' . $id . ']';
			$titleUrl  = '<a href="' . esc_url($this->getModule()->getEditLink( $id )) . '">' . esc_html($row['title']) . ' <i class="fa fa-fw fa-pencil"></i></a> <a data-filter-id="' . $id . '" class="wpfDuplicateFilter" href="" title="' . esc_attr__('Duplicate filter', 'woo-product-filter') . '"><i class="fa fa-fw fa-clone"></i></a>';

			$data[$key]['shortcode'] = $shortcode;
			$data[$key]['title']     = DispatcherWpf::applyFilters('prepareFilterListTitle', $titleUrl, $row);
		}
		return $data;
	}

	public function drawFilterAjax() {
		$res  = new ResponseWpf();
		$data = ReqWpf::get('post');
		if ( isset($data) && $data ) {
			$isPro = FrameWpf::_()->isPro();
			if ( ! empty($data['settings']['js_editor']) ) {
				$data['settings']['js_editor'] = '';
			}
			if ( ! empty($data['settings']['filters']['order']) ) {
				$metaKeys = $this->getDataFilterMetaKeys(stripcslashes($data['settings']['filters']['order']));
				if ( count($metaKeys) > 0 ) {
					FrameWpf::_()->getModule('meta')->calcNeededMetaValues();
				}
			}

			$html = FrameWpf::_()->getModule('woofilters')->getView()->renderHtml($data);

			$html .= '<script type="text/javascript">window.wpfFrontendPage.init();' . ( $isPro ? 'window.wpfFrontendPage.eventsFrontendPro();' : '' ) . '</script>';
			$res->setHtml($html);
		} else {
			$res->pushError($this->getModule('woofilters')->getErrors());
		}

		$res->ajaxExec();
	}

	public function save() {

		if ( is_plugin_active( 'litespeed-cache/litespeed-cache.php' ) ) {
			do_action( 'litespeed_purge_all' );
		}

		if ( is_plugin_active( 'wp-rocket/wp-rocket.php' ) && function_exists( 'rocket_clean_domain' ) ) {
			if ( FrameWpf::_()->getModule( 'options' )->get( 'disable_clean_rocket_cache' ) != 1 ) {
				rocket_clean_domain();
			}
		}

		check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		if ( ! current_user_can('manage_options') ) {
			wp_die();
		}

		$res = new ResponseWpf();
		$id  = $this->getModel('woofilters')->save(ReqWpf::get('post'));
		if ( false != $id ) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
			$res->addData('edit_link', $this->getModule()->getEditLink( $id ));
			$filter   = $this->getModel('woofilters')->getById($id);
			$settings = unserialize($filter['setting_data']);
			$res->addData('filter', $filter);
			$res->addData('filterSettings', $settings);
		} else {
			$res->pushError ($this->getModel('woofilters')->getErrors());
		}
		return $res->ajaxExec();
	}

	public function deleteByID() {
		check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		if ( ! current_user_can('manage_options') ) {
			wp_die();
		}

		$res = new ResponseWpf();

		if ( $this->getModel('woofilters')->delete(ReqWpf::get('post')) != false ) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
		} else {
			$res->pushError($this->getModel('woofilters')->getErrors());
		}
		return $res->ajaxExec();
	}

	public function createTable() {
		check_ajax_referer('wpf-save-nonce', 'wpfNonce');
		if ( ! current_user_can('manage_options') ) {
			wp_die();
		}

		$res = new ResponseWpf();
		$id  = $this->getModel('woofilters')->save(ReqWpf::get('post'));
		if ( false != $id ) {
			$res->addMessage(esc_html__('Done', 'woo-product-filter'));
			$res->addData('edit_link', $this->getModule()->getEditLink( $id ));
			$filter   = $this->getModel('woofilters')->getById($id);
			$settings = unserialize($filter['setting_data']);
			$res->addData('filter', $filter);
			$res->addData('filterSettings', $settings);
		} else {
			$res->pushError ($this->getModel('woofilters')->getErrors());
		}
		return $res->ajaxExec();
	}

	public function filtersFrontend() {
		$res = new ResponseWpf();

		$params = ReqWpf::get('post');

		$filtersDataBackend          = UtilsWpf::jsonDecode(stripslashes($params['filtersDataBackend']));
		$queryvars                   = UtilsWpf::jsonDecode(stripslashes($params['queryvars']));
		$filterSettings              = UtilsWpf::jsonDecode(stripslashes($params['filterSettings']));
		$generalSettings             = UtilsWpf::jsonDecode(stripslashes($params['generalSettings']));
		$woocommerceSettings         = UtilsWpf::jsonDecode(stripslashes($params['woocommerceSettings']));
		$shortcodeAttr               = isset($params['shortcodeAttr']) ? UtilsWpf::jsonDecode(stripslashes($params['shortcodeAttr'])) : array();
		$curUrl                      = $params['currenturl'];
		$queryvars['posts_per_page'] = isset($filterSettings['count_product_shop']) && ! empty($filterSettings['count_product_shop']) ? $filterSettings['count_product_shop'] : $queryvars['posts_per_page'];
		$use_category_filtration     = isset($filterSettings['use_category_filtration']) ? $filterSettings['use_category_filtration'] : 1;
		$onlyStatistics              = isset($params['only_statistics']);
		$onlyFilterRecount           = $onlyStatistics || isset($params['only_recound']);
		$synchroFilterId             = ! empty($params['synchro_filter_id']) ? $params['synchro_filter_id'] : '';

		if ( $onlyStatistics ) {
			$filterSettings['only_have_found'] = true;
			$filterSettings['filter_recount']  = 0;
		}

		foreach ( $filtersDataBackend as $key => $filteringSettings ) {
			if ( 'wpfCategory' == $filteringSettings['id'] ) {
				$filterOrder = array_search($filteringSettings['uniqId'], array_column($generalSettings, 'uniqId'));
				if ( ! empty($filterOrder) ) {
					$logicHierarchical   = $generalSettings[$filterOrder]['settings']['f_multi_logic_hierarchical'];
					$isHierarchical      = $generalSettings[$filterOrder]['settings']['f_show_hierarchical'];
					$type                = $generalSettings[$filterOrder]['settings']['f_frontend_type'];
					$isLogicHierarchical = ( $isHierarchical && in_array( $type, array( 'text', 'multi' ) ) );

					if ( $isLogicHierarchical && 'child' == $logicHierarchical ) {
						$filtersDataBackend[$key]['settings'] = $this->getModule()->exludeParentTems($filtersDataBackend[$key]['settings'], 'product_cat');
					} elseif ( $isLogicHierarchical && 'parent' == $logicHierarchical ) {
						$filtersDataBackend[$key]['settings'] = $this->getModule()->exludeChildTems($filtersDataBackend[$key]['settings'], 'product_cat');
					}
				}
			}
		}

		if ( ! $onlyFilterRecount && class_exists( 'Astra_Theme_Options' ) && class_exists('WP') ) {
			global $wp;
			if ( is_object($wp) ) {
				$wp->main();
			}
		}

		$args = $this->createArgsForFilteringBySettings($filtersDataBackend, $queryvars, $filterSettings, $generalSettings, $woocommerceSettings, $shortcodeAttr);
		if ( isset( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			$queryvars['posts_per_page'] = $args['posts_per_page'];
		}
		$parts    = parse_url($curUrl);
		$urlQuery = array();
		if ( ! empty($parts['query']) ) {
			parse_str($parts['query'], $urlQuery);
			if ( ! empty($urlQuery['s']) ) {
				$args['s'] = $urlQuery['s'];
			}
		}

		if ( isset($urlQuery['product_view']) ) {
			$_GET['product_view']     = $urlQuery['product_view'];
			$_REQUEST['product_view'] = $urlQuery['product_view'];
		}

		$cacheArgs       = $args;
		$categoryHtml    = '';
		$productsHtml    = '';
		$paginationHtml  = '';
		$paginationLeer  = '';
		$resultCountHtml = '';
		$loopStart       = '';
		$jscript         = '';

		if ( ! $onlyFilterRecount ) {

			DispatcherWpf::doAction('beforeFiltersFrontend', $filtersDataBackend);

			$paged = empty($queryvars['paged']) ? 1 : $queryvars['paged'];
			if ( empty($params['runbyload']) && empty($queryvars['pagination']) ) {
				$paged = 1;
			}
			if ( ! empty($queryvars['posts_per_row']) ) {
				$customNums = $queryvars['posts_per_row'];
				global $woocommerce_loop;
				$woocommerce_loop['columns'] = $customNums; // needed for some themes, that check this property first
				add_filter('loop_shop_columns', function ( $num ) use ( $customNums ) {
					return $customNums;
				}, 999);
			}
			$args['paged'] = $paged;

			// other plugin compatibility
			class_exists('WC_pif') && add_filter('post_class', array($this->getModule(), 'WC_pif_product_has_gallery'));
			class_exists('YITH_Request_Quote') && add_filter('woocommerce_loop_add_to_cart_link', array($this->getModule(), 'YITH_hide_add_to_cart_loop'), 10, 2);

			if ( class_exists('Iconic_WSSV_Query') ) {
				$args = $this->getModule()->Iconic_Wssv_Query_Args($args);
			}

			if ( function_exists( 'kute_boutique_woocommerce_setup_loop' ) ) {
				kute_boutique_woocommerce_setup_loop();
			}
		}

		//Prepare params for WooCommerce Shop and Category template variants.
		$shopPageId           = wc_get_page_id('shop');
		$currentPageId        = isset($queryvars['page_id']) ? $queryvars['page_id'] : 0;
		$allProductsFiltering = isset($filterSettings['all_products_filtering']) && $filterSettings['all_products_filtering'];
		$categoryPageId       = isset($queryvars['product_category_id']) && ! $allProductsFiltering ? $queryvars['product_category_id'] : false;

		$calcParentCategory = null;
		$showProducts       = true;

		$is_filter_category_selected = false;
		if ( ! empty($parts['query']) && $use_category_filtration ) {
			if ( ! $categoryPageId && preg_match('/wpf_filter_cat_(\d+)=([^&]+)/', $parts['query'], $matches) ) {
				$categoryPageId = (int) $matches[2];
			}
			if ( $categoryPageId ) {
				$is_filter_category_selected = true;
			}
		}

		if ( ! isset($parts['query']) || '' === $parts['query'] || 'wpf_fbv=1' === $parts['query'] || $is_filter_category_selected ) {
			if ( $shopPageId == $currentPageId && ! $is_filter_category_selected ) {
				$pageDisplay = get_option( 'woocommerce_shop_page_display', '' );
				if ( 'subcategories' == $pageDisplay || 'both' == $pageDisplay ) {
					$calcParentCategory = 0;
					if ( 'subcategories' == $pageDisplay ) {
						$showProducts = false;
					}
				}
			} elseif ( $categoryPageId ) {
				$archiveDisplay = get_term_meta( $categoryPageId, 'display_type', true );
				if ( '' === $archiveDisplay ) {
					$archiveDisplay = get_option( 'woocommerce_category_archive_display', '' );
				}

				$termProductCategory = get_term_by( 'id', $categoryPageId, 'product_cat' );

				if ( $termProductCategory && ( 'subcategories' === $archiveDisplay || 'both' === $archiveDisplay ) ) {
					$calcParentCategory = $termProductCategory->term_id;
					if ( 'subcategories' === $archiveDisplay ) {
						$showProducts = false;
					}
				}
			}
		}
		$recount                  = isset($filterSettings['filter_recount']) && $filterSettings['filter_recount'];
		$removeActions            = isset($filterSettings['remove_actions']) && $filterSettings['remove_actions'];
		$filteringVariations      = isset($filterSettings['filtering_by_variations']) ? $filterSettings['filtering_by_variations'] : false;
		$displayProductVariations = $filteringVariations && isset($filterSettings['display_product_variations']) ? $filterSettings['display_product_variations'] : false;
		$wpfFId                   = ! empty($filterSettings['wpf_fid']) ? $filterSettings['wpf_fid'] : '';

		if ( $removeActions ) {
			remove_all_filters('posts_orderby');
			remove_all_filters('pre_get_posts');
			remove_all_filters('wpv_action_apply_archive_query_settings');
			if ( $onlyFilterRecount ) {
				remove_all_filters('posts_clauses');
			}
		}

		$module     = $this->getModule();
		$taxonomies = $module->getFilterTaxonomies($generalSettings, ! is_null($calcParentCategory), $filterSettings, true, $urlQuery);
		if ( ! $recount ) {
			$taxonomies['count'] = array();
		}
		if ( FrameWpf::_()->proVersionCompare('1.4.8') ) {
			$args = $module->addBeforeFiltersFrontendArgs($args, $filterSettings, $urlQuery);
		} else {
			$args = DispatcherWpf::applyFilters('beforeFilterExistsTerms', $args, $filterSettings, $urlQuery);
		}
		$filterItems = $module->getFilterExistsItems($args, $taxonomies, $calcParentCategory, $categoryPageId, $generalSettings, true , $filterSettings, array(), $urlQuery);
		if ( $onlyStatistics ) {
			$isFound = empty($filterItems['have_posts']) ? 0 : 1;
			DispatcherWpf::doAction('saveStatistics', $isFound);
			return $res->ajaxExec();
		}

		if ( false !== $filterItems ) {
			$openOneByOne                = isset($filterSettings['open_one_by_one']) ? $filterSettings['open_one_by_one'] : 0;
			$displayOnlyChildrenCategory = isset($filterSettings['display_only_children_category']) ? $filterSettings['display_only_children_category'] : 0;

			if ( $openOneByOne && $displayOnlyChildrenCategory ) {
				$optionsHtml = $this->getOptionsHtml($filterItems['exists'], $generalSettings);
			}

			$jscript .= '<script type="text/javascript">wpfShowHideFiltersAtts(' . json_encode($filterItems['exists']) . ', ' . json_encode($filterItems['existsUsers']) . ', "' . $synchroFilterId . '");</script>';
			if ( $recount ) {
				$jscript .= '<script type="text/javascript">wpfChangeFiltersCount(' . json_encode($filterItems['exists']) . ', "' . $synchroFilterId . '");</script>';
			}
		}
		if ( ! empty($wpfFId) ) {
			$jscript .= '<script type="text/javascript">wpfDoActionsAfterLoad(' . $wpfFId . ',' . ( empty($filterItems) || empty($filterItems['have_posts']) ? 0 : 1 ) . ');</script>';
		}

		if ( ! $onlyFilterRecount ) {
			if ( class_exists('qib_settings') ) {
				do_action('template_redirect');
			}
			$categoryIn = isset($filterItems['categories']) ? $filterItems['categories'] : array();
			if ( count($categoryIn) > 0 && $use_category_filtration ) {
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
				$categoryHtml .= ob_get_clean();
			}

			$loopFoundPost = 0;
			if ( $showProducts || empty($categoryHtml) ) {

				//get products
				if ( FrameWpf::_()->proVersionCompare('1.4.8') ) {
					//$loop = new WP_Query($module->addBeforeFiltersFrontendArgs($args, $filterSettings));
					$loop = new WP_Query($args);
				} else {
					$loop = new WP_Query(DispatcherWpf::applyFilters('beforeFiltersFrontendArgs', $args, $filterSettings));
				}
				if ( $displayProductVariations ) {
					DispatcherWpf::doAction('beforeLoopVariations', $filterSettings);
				}
				$loopFoundPost = $loop->found_posts;
				if ( $loop->have_posts() ) {
					ob_start();
					while ( $loop->have_posts() ) :
						$loop->the_post();
						if ( $displayProductVariations ) {
							DispatcherWpf::doAction('beforeDisplayProduct', $args);
						}

						wc_get_template_part('content', 'product');
					endwhile;
					$productsHtml = ob_get_clean();
					if ( empty($productsHtml) ) {
						$productsHtml = __($filterSettings['text_no_products'], 'woo-product-filter');
					}
				} else {
					$productsHtml = __($filterSettings['text_no_products'], 'woo-product-filter');
				}
			}

			ob_start();
			wc_get_template( 'loop/loop-start.php' );

			$loopStart = ob_get_clean();

			//get result count
			ob_start();
			$args = array(
				'total'     => $loopFoundPost,
				'per_page'  => $queryvars['posts_per_page'],
				'current'   => $paged,
				'orderedby' => '',
			);
			wc_get_template( 'loop/result-count.php', $args );
			$resultCountHtml = ob_get_clean();

			//get pagination
			ob_start();
			$base = $queryvars['base'];

			//get query params
			$curUrl = explode( '?', $curUrl );
			$curUrl = isset($curUrl[1]) ? remove_query_arg('product-page', $curUrl[1]) : '';//wpf-page=1';

			$format = isset($queryvars['format']) ? $queryvars['format'] : '';

			//add quary params to base url
			$fullBaseUrl = $base . ( strpos($base, '?') === false ? '?wpf_filtered=1&' : '&' ) . $curUrl;

			// add pagination query to base url
			$paginateType = $queryvars['paginate_type'];
			$paginateBase = $queryvars['paginate_base'];

			if ( 'query' === $paginateType || ( 'shortcode' === $paginateType && strpos($fullBaseUrl, $paginateBase) === false ) ) {
				$fullBaseUrl .= ( strpos($fullBaseUrl, '?') === false ? '?' : '&' ) . $paginateBase . '=%#%';
			}

			$total = ceil( $loopFoundPost / $queryvars['posts_per_page'] ) ;
			//after filtering we always start from 1 page
			$args  = array(
				'base'      => $fullBaseUrl,
				'format'    => $format,
				'add_args'  => false,
				'current'   => $paged, //1,//$queryvars['paged'],
				'total'     => $total,
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'type'      => 'list',
				'end_size'  => 3,
				'mid_size'  => 3,
			);
			$theme = wp_get_theme();
			if ( $theme instanceof WP_Theme ) {

				$themeName = ( '' !== $theme['Parent Theme'] ) ? $theme['Parent Theme'] : $theme['Name'];

				switch ( $themeName ) {
					case 'Impreza':
						$args['before_page_number'] = '<span>';
						$args['after_page_number']  = '</span>';
						unset( $args['type'] );
						$links = paginate_links( $args );
						HtmlWpf::echoEscapedHtml( _navigation_markup( $links, 'pagination', '' ) );
						break;
					case 'Themify Ultra':
						$args['before_page_number'] = '<span>';
						$args['after_page_number']  = '</span>';
						unset( $args['type'] );
						$links = paginate_links( $args );
						HtmlWpf::echoEscapedHtml( '<div class="pagenav tf_clear tf_box tf_textr tf_clearfix">' . $links . '</div>' );
						break;
					default:
						wc_get_template( 'loop/pagination.php', $args );
						break;
				}
			}

			$paginationHtml = ob_get_clean();
			wp_reset_postdata();

			add_filter('primer_wc_pagination_args', function ( $arg ) use ( $fullBaseUrl ) {
				$arg['base'] = $fullBaseUrl;
				return $arg;
			});
			if ( empty($paginationHtml) ) {
				ob_start();
				$args['current'] = 1;
				$args['total']   = 2;
				wc_get_template( 'loop/pagination.php', $args);
				$paginationLeer = ob_get_clean();
				wp_reset_postdata();
				if ( empty($paginationLeer) ) {
					$args['current'] = $paged;
					$args['total']   = $total;
					ob_start();
					global $wp_query;
					$wp_query->max_num_pages = $total;
					wc_get_template( 'loop/pagination.php', $args );
					$paginationHtml = ob_get_clean();
					wp_reset_postdata();
					if ( empty($paginationHtml) ) {
						$args['current'] = 1;
						$args['total']   = 2;
						ob_start();
						$wp_query->max_num_pages = 2;
						wc_get_template( 'loop/pagination.php', $args );
						$paginationLeer = ob_get_clean();
					}
				}
			}
		}

		$prices = array();

		if ( isset( $filterItems['existsPrices'] ) ) {
			$filteredPrices          = $filterItems['existsPrices'];
			$prices['wpf_max_price'] = $filteredPrices->wpfMaxPrice;
			$prices['wpf_min_price'] = $filteredPrices->wpfMinPrice;

			if ( ! empty( $prices['wpf_max_price'] ) ) {
				$jscript .= '<script type="text/javascript">wpfChangePriceFiltersCount(' . json_encode( $prices ) . ');</script>';
			}
		}

		$beforeProductHtml = DispatcherWpf::applyFilters('productLoopStart', '', $generalSettings, $urlQuery);
		if ( '' !== $beforeProductHtml ) {
			$res->addData( 'beforeProductHtml', $beforeProductHtml );
		}
		$res->addData('categoryHtml', $categoryHtml);
		$res->addData('productHtml', $productsHtml);
		$res->addData('paginationHtml', $paginationHtml);
		$res->addData('resultCountHtml', $resultCountHtml);
		$res->addData('loopStartHtml', $loopStart);
		$res->addData('paginationLeerHtml', $paginationLeer);
		$res->addData('prices', $prices);
		$res->addData('jscript', $jscript);
		$res->addData('fid', $wpfFId);

		if ( isset($optionsHtml) ) {
			$res->addData('optionsHtml', $optionsHtml);
		}

		return $res->ajaxExec();
	}

	public function getOptionsHtml( &$exists, $generalSettings ) {
		$optionsHtml = array();

		foreach ( $exists as $key => $value ) {
			if ( substr($key, -13) === '_hierarchical' ) {
				$taxonomy = substr($key, 0, -13);

				$allTermIds = array();

				foreach ( $value as $levelTerms ) {
					$allTermIds = array_merge($allTermIds, array_keys($levelTerms));
				}

				if ( ! empty($allTermIds) ) {
					$terms = get_terms(array(
						'taxonomy'   => $taxonomy,
						'include'    => $allTermIds,
						'hide_empty' => false,
					));

					$termsById = array();
					if ( ! is_wp_error($terms) && ! empty($terms) ) {
						foreach ( $terms as $term ) {
							$termsById[$term->term_id] = $term;
						}
					}

					foreach ( $value as $level => $levelTerms ) {
						if ( ! isset($optionsHtml[$level]) ) {
							$text                = esc_html__( isset( $generalSettings[$level]['settings']['f_dropdown_first_option_text'])
							? $generalSettings[$level]['settings']['f_dropdown_first_option_text']
							: 'Select all', 'woo-product-filter');
							$optionsHtml[$level] = '<option value="" data-slug="">' . $text . '</option>';
						}

						foreach ( $levelTerms as $termId => $count ) {
							if ( isset($termsById[$termId]) ) {
								$term     = $termsById[$termId];
								$termName = esc_attr($term->name);
								$termSlug = esc_attr($term->slug);

								$optionsHtml[$level] .= '<option data-term-name="' . $termName . '" ' .
									'value="' . $termId . '" ' .
									'data-term-slug="' . $termSlug . '" ' .
									'data-count="' . $count . '" ' .
									'data-term-id="' . $termId . '" ' .
									'data-slug="' . $termSlug . '" ' .
									'data-img="" style="">' . $termName . ' </option>';
							}
						}
					}
				}

				unset($exists[$key]);
			}
		}

		return $optionsHtml;
	}

	/**
	 * getTaxonomyTerms.
	 */
	public function getTaxonomyTerms() {

		$res   = new ResponseWpf();
		$slug  = ReqWpf::getVar('slug');
		$terms = array();
		$keys  = array();
		if ( ! is_null($slug) ) {
			$terms = $this->getModule()->getAttributeTerms($slug);
			$keys  = array_keys($terms);
		}
		$res->addData('terms', htmlentities(UtilsWpf::jsonEncode($terms), ENT_COMPAT));
		$res->addData('keys', htmlentities(UtilsWpf::jsonEncode($keys), ENT_COMPAT));
		return $res->ajaxExec();
	}

	/**
	 * Create args for WP_Query.
	 *
	 * @version 3.1.1
	 *
	 * @param array $filtersDataBackend Filters arranged with filtering order with some specific filtering data in it
	 * @param array $queryvars Query filtering variables
	 * @param array $filterSettings Some filter block settings
	 * @param array $generalSettings Filters arranged with filtering order with all filter settings
	 * @param array $woocommerceSettings If we do not have own filtering result we must take it from woocommerce if they are set
	 *
	 * @return array
	 */
	public function createArgsForFilteringBySettings( $filtersDataBackend, $queryvars, $filterSettings, $generalSettings, $woocommerceSettings, $shortcodeAttr ) {
		$queryvars['product_tag']   = isset($queryvars['product_tag']) ? $queryvars['product_tag'] : false;
		$queryvars['product_brand'] = isset($queryvars['product_brand']) ? $queryvars['product_brand'] : false;
		$queryvars['pwb-brand']     = isset($queryvars['pwb-brand']) ? $queryvars['pwb-brand'] : false;
		$queryvars['tax_page']      = isset($queryvars['tax_page']) ? $queryvars['tax_page'] : false;
		$queryvars['vendors']       = isset($queryvars['vendors']) ? $queryvars['vendors'] : false;
		$asDefaultCats              = array();
		$settingIds                 = array_column($filtersDataBackend, 'id');
		$settingCats                = array_keys($settingIds, 'wpfCategory');
		$MultiLogic                 = isset( $filterSettings['f_multi_logic'] ) ? $filterSettings['f_multi_logic'] : 'and';
		if ( ! count($settingCats) ) {
			foreach ( $generalSettings as $generalSingle ) {
				if ( ( 'wpfCategory' == $generalSingle['id'] ) && $generalSingle['settings']['f_filtered_by_selected'] && ! empty($generalSingle['settings']['f_mlist[]']) ) {
					$asDefaultCats = array_merge($asDefaultCats, explode(',', $generalSingle['settings']['f_mlist[]']));
					break;
				}
			}
		}
		$module = $this->getModule();

		$postStatus = ( isset( $filterSettings['display_status_private'] ) && $filterSettings['display_status_private'] )
			? array( 'publish', 'private' )
			: 'publish';

		$args = array(
			'post_type'           => 'product',
			'post_status'         => $postStatus,
			'paged'               => 1,
			'posts_per_page'      => $queryvars['posts_per_page'],
			'ignore_sticky_posts' => true,
			'wpf_query'           => 1,
			'tax_query'           => array('wpf_tax' => 1),
		);
		if ( ! empty($filterSettings['default_query']) ) {
			$args = array_merge($args, $filterSettings['default_query']);
		}

		$args['tax_query'] = $module->addHiddenFilterQuery($args['tax_query']);

		$isAllProductsFiltering = $filterSettings['all_products_filtering'] && $filtersDataBackend;

		if ( ! $isAllProductsFiltering ) {
			if ( ( isset($queryvars['product_category_id']) || $asDefaultCats ) && ! $queryvars['product_tag'] && ! $queryvars['product_brand'] && ! $queryvars['pwb-brand'] ) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'product_cat',
					'field'            => 'id',
					'terms'            => isset($queryvars['product_category_id']) ? $queryvars['product_category_id'] : $asDefaultCats,
					'include_children' => true,
				);
			} elseif ( $queryvars['product_tag'] ) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'product_tag',
					'field'            => 'id',
					'terms'            => $queryvars['product_tag'],
					'include_children' => true,
				);
			} elseif ( $queryvars['product_brand'] ) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'product_brand',
					'field'            => 'id',
					'terms'            => $queryvars['product_brand'],
					'include_children' => true,
				);
			} elseif ( $queryvars['pwb-brand'] ) {
				$args['tax_query'][] = array(
					'taxonomy'         => 'pwb-brand',
					'field'            => 'id',
					'terms'            => $queryvars['pwb-brand'],
					'include_children' => true,
				);
			} elseif ( $queryvars['vendors'] ) {
				if ( is_numeric( $queryvars['vendors'] ) ) {
					$args['author'] = $queryvars['vendors'];
				} else {
					$args['author_name'] = $queryvars['vendors'];
				}
			} elseif ( is_array($queryvars['tax_page']) ) {
				$args['tax_query'][] = array(
					'taxonomy'         => $queryvars['tax_page']['taxonomy'],
					'field'            => 'id',
					'terms'            => $queryvars['tax_page']['term'],
					'include_children' => true,
				);
			}
		}

		if ( $shortcodeAttr ) {
			// first adds parameters from the shortcode
			new WC_Shortcode_Products( $shortcodeAttr );
		}

		// set woocommerce sorting data if we do not have own sorting filtering
		$isWpfSortBy = array_search('wpfSortBy', array_column($filtersDataBackend, 'id'));
		if ( ! $isWpfSortBy && isset($woocommerceSettings['woocommercefSortBy']) ) {
			$filtersDataBackend[] = array(
				'id'       => 'wpfSortBy',
				'settings' => $woocommerceSettings['woocommercefSortBy'],
			);
		}

		$temp = array();
		foreach ( $filtersDataBackend as $setting ) {
			if ( ! empty($setting['settings']) ) {
				$metaQuery = DispatcherWpf::applyFilters('addAjaxCustomMetaQueryPro', array(), $setting, $filterSettings);
				if ( ! empty($metaQuery) ) {
					$args['meta_query'][] = $metaQuery;
					continue;
				}

				switch ( $setting['id'] ) {
					case 'wpfPrice':
						$priceStr = $setting['settings'][0];
						$priceVal = explode(',', $priceStr);

						if ( $priceVal[0] >= 0 && $priceVal[1] >= 0 ) {
							$temp['wpfPrice']['wpf_min_price'] = $priceVal[0];
							$temp['wpfPrice']['wpf_max_price'] = $priceVal[1];
						}
						break;
					case 'wpfPriceRange':
						$priceStr = $setting['settings'][0];
						$priceVal = explode(',', $priceStr);
						if ( count($priceVal) == 2 && ( false !== $priceVal[0] ) ) {
							$temp['wpfPrice']['wpf_min_price'] = ( '' == $priceVal[0] ? null : $priceVal[0] );
							$temp['wpfPrice']['wpf_max_price'] = ( '' == $priceVal[1] ? null : $priceVal[1] );
						}
						break;
					case 'wpfSortBy':
						$sortBy = ( is_array($setting['settings']) ) ? $setting['settings'][0] : $setting['settings'];
						remove_all_filters('posts_clauses');
						switch ( $sortBy ) {
							case 'title':
								$args['orderby'] = 'title';
								$args['order']   = 'ASC';
								break;
							case 'title-desc':
								$args['orderby'] = 'title';
								$args['order']   = 'DESC';
								break;
							case 'rand':
								$args['orderby'] = 'rand';
								break;
							case 'date':
								$args['orderby'] = array(
									'date' => 'DESC',
									'ID'   => 'ASC',
								);
								$args['order']   = 'DESC';
								break;
							case 'date-asc':
								$args['orderby'] = array(
									'date' => 'ASC',
									'ID'   => 'ASC',
								);
								$args['order']   = 'ASC';
								break;
							case 'price':
								$args['orderby']  = 'meta_value_num';
								$args['order']    = 'ASC';
								$args['meta_key'] = '_price';
								break;
							case 'price-desc':
								$args['orderby']  = 'meta_value_num';
								$args['order']    = 'DESC';
								$args['meta_key'] = '_price';
								break;
							case 'popularity':
								$args['orderby']  = 'meta_value_num';
								$args['order']    = 'DESC';
								$args['meta_key'] = 'total_sales';
								break;
							case 'rating':
								$args['meta_key'] = '_wc_average_rating'; // @codingStandardsIgnoreLine
								$args['orderby']  = array(
									'meta_value_num' => 'DESC',
									'ID'             => 'ASC',
								);
								break;
							case 'sku':
								add_filter( 'posts_clauses', array( FrameWpf::_()->getModule( 'woofilters' ), 'addSKUOrder' ) );
								break;
							case 'sku-desc':
								add_filter( 'posts_clauses', array( FrameWpf::_()->getModule( 'woofilters' ), 'addSKUOrderDesc' ) );
								break;
						}
						break;
					case 'wpfCategory':
						$categoryIds           = $setting['settings'];
						$logic                 = isset($setting['logic']) ? $setting['logic'] : 'and';
						$temp['wpfCategory'][] = array(
							'taxonomy'         => 'product_cat',
							'field'            => 'term_id',
							'terms'            => $categoryIds,
							'operator'         => 'not' == $logic ? 'NOT IN' : ( 'or' == $logic || count($categoryIds) <= 1 ? 'IN' : 'AND' ),
							'include_children' => ( isset($setting['children']) && ( '1' == $setting['children'] ) ),
						);
						break;
					case 'wpfPerfectBrand':
						$brandIds            = $setting['settings'];
						$logic               = isset($setting['logic']) ? $setting['logic'] : 'and';
						$args['tax_query'][] = array(
							'taxonomy'         => 'pwb-brand',
							'field'            => 'term_id',
							'terms'            => $brandIds,
							'operator'         => 'not' == $logic ? 'NOT IN' : ( 'or' == $logic || count($brandIds) <= 1 ? 'IN' : 'AND' ),
							'include_children' => ( isset($setting['children']) && ( '1' == $setting['children'] ) ),
						);
						break;
					case 'wpfTags':
						$tagsIdStr = $setting['settings'];
						if ( $tagsIdStr ) {
							$logic             = isset($setting['logic']) ? $setting['logic'] : 'and';
							$temp['wpfTags'][] = array(
								'taxonomy' => 'product_tag',
								'field'    => 'id',
								'terms'    => $tagsIdStr,
								'operator' => 'not' == $logic ? 'NOT IN' : ( 'or' == $logic || count($tagsIdStr) <= 1 ? 'IN' : 'AND' ),
							);
						}
						break;
					case 'wpfAttribute':
						$attrIds        = $setting['settings'];
						$customPrefixes = DispatcherWpf::applyFilters('getCustomPrefixes', array(), false);
						$pos            = strpos($setting['name'], '-');
						if ( ! $pos || ! in_array( substr($setting['name'], 0, $pos + 1), $customPrefixes ) ) {
							$taxonomy = '';
							foreach ( $attrIds as $attr ) {
								$term = get_term( $attr );
								if ( $term && $term->taxonomy ) {
									$taxonomy = $term->taxonomy;
									break;
								}
							}
							if ( '' !== $taxonomy ) {
								$logic                  = isset( $setting['logic'] ) ? $setting['logic'] : 'and';
								$logics                 = $module->getAttrFilterLogic( 'loop' );
								$operator               = array_key_exists( $logic, $logics ) ? $logics[ $logic ] : ( 'not' == $logic ? 'NOT IN' : 'IN' );
								$temp['wpfAttribute'][] = array(
									'taxonomy' => $taxonomy,
									'field'    => 'id',
									'terms'    => $attrIds,
									'operator' => $operator,
								);
							}
						}
						break;
					case 'wpfAuthor':
						if ( isset( $setting['settings'][0] ) && '' !== $setting['settings'][0] ) {
							$args['author__in'] = $setting['settings'];
						}
						break;
					case 'wpfFeatured':
						$enable = $setting['settings'][0];
						if ( '1' === $enable ) {
							$args['tax_query'][] = array(
								'taxonomy' => 'product_visibility',
								'field'    => 'name',
								'terms'    => 'featured',
								'operator' => 'IN',
							);
						}
						break;
					case 'wpfOnSale':
						$enable = $setting['settings'][0];
						if ( '1' === $enable ) {
							$args['post__in'] = array_merge(array(0), wc_get_product_ids_on_sale());
						}
						break;
					case 'wpfInStock':
						$stockstatus = $setting['settings'];
						if ( $stockstatus ) {
							$metaKeyId = $module->getMetaKeyId('_stock_status');
							if ( $metaKeyId ) {
								$stockValues = FrameWpf::_()->getModule( 'meta' )->getModel( 'meta_values' )->getKeyValueIds( $metaKeyId, array(), true );
								if ( ! is_array($stockstatus) ) {
									$stockstatus = array($stockstatus);
								}
								$values = array();
								foreach ( $stockstatus as $ss ) {
									$foundId = array_search($ss, $stockValues);
									if ( false !== $foundId ) {
										$values[] = $foundId;
									}
								}
								$module->addWpfMetaClauses( array(
									'keyId'    => $metaKeyId,
									'isAnd'    => false,
									'values'   => $values,
									'field'    => 'id',
									'urlParam' => $setting['name'],
								) );
							} else {
								$metaQuery = array(
									'key'     => '_stock_status',
									'value'   => $stockstatus,
									'compare' => 'IN',
								);
							}
							$args['meta_query'][] = $metaQuery;
						}
						break;
					case 'wpfRating':
						$ratingRange = $setting['settings'];
						if ( is_array($ratingRange) ) {
							foreach ( $ratingRange as $range ) {
								$range = strpos($range, '-') !== false ? explode('-', $range) : array(intval($range));
								break;
							}
							if ( isset($range[1]) && intval($range[1]) !== 5 ) {
								$range[1] = $range[1] - 0.001;
							}
							if ( $range[0] ) {
								$isBetween = isset($range[1]) && $range[1];
								$metaKeyId = $module->getMetaKeyId('_wc_average_rating');
								if ( $metaKeyId ) {
									$module->addWpfMetaClauses( array(
										'keyId'    => $metaKeyId,
										'isAnd'    => ( $isBetween ? 'BETWEEN' : false ),
										'values'   => $range,
										'field'    => 'int',
										'urlParam' => $setting['name'],
									) );
								} elseif ( $isBetween ) {
										$metaQuery            = array(
											'key'     => '_wc_average_rating',
											'value'   => array( $range[0], $range[1] ),
											'type'    => 'DECIMAL',
											'compare' => 'BETWEEN',
										);
										$args['meta_query'][] = $metaQuery;
								} else {
									$metaQuery            = array(
										'key'     => '_wc_average_rating',
										'value'   => $range[0],
										'type'    => 'DECIMAL',
										'compare' => '=',
									);
									$args['meta_query'][] = $metaQuery;
								}
							}
						}
						break;
					case 'wpfBrand':
						$brandsIdStr = $setting['settings'];
						if ( $brandsIdStr ) {
							$args['tax_query'][] = array(
								'taxonomy' => 'product_brand',
								'field'    => 'id',
								'terms'    => $brandsIdStr,
								'operator' => ( isset($setting['logic']) && ( 'or' == $setting['logic'] ) ? 'IN' : 'AND' ),
							);
						}
						break;
					case 'wpfVendors':
						$vendorIds = $setting['settings'];
						if ( ! empty($vendorIds) ) {
							$args['author__in'] = $vendorIds;
						}
						break;
					case 'wpfSearchNumber':
						if ( ! empty($setting['settings']['value']) ) {
							$args['tax_query'] = DispatcherWpf::applyFilters('addCustomTaxQueryPro', $args['tax_query'], array($setting['name'] => $setting['settings']['value']), 'url');
						}
						break;
				}
			}
		}
		//DispatcherWpf::doAction('addArgsForFilteringBySettings', $filtersDataBackend);

		if ( isset($temp['wpfCategory']) ) {
			$temp['wpfCategory']['relation'] = strtoupper($MultiLogic);
			$args['tax_query'][]             = $temp['wpfCategory'];
		}
		if ( isset($temp['wpfAttribute']) ) {
			$temp['wpfAttribute']['relation'] = strtoupper($MultiLogic);
			$args['tax_query'][]              = $temp['wpfAttribute'];
		}
		if ( isset($temp['wpfTags']) ) {
			$temp['wpfTags']['relation'] = strtoupper($MultiLogic);
			$args['tax_query'][]         = $temp['wpfTags'];
		}
		if ( isset($args['tax_query']) && ! empty($args['tax_query']) ) {
			$args['tax_query']['relation'] = 'AND';
		}
		if ( isset($temp['wpfPrice']) ) {
			$args['meta_query'][] = $module->preparePriceFilter($temp['wpfPrice']['wpf_min_price'], $temp['wpfPrice']['wpf_max_price']);
		}
		$sortByTitle = ! empty( $filterSettings['sort_by_title'] ) ? $filterSettings['sort_by_title'] : false;
		if ( $sortByTitle ) {
			$args = $module->addCustomOrder($args, 'title');
		}
		if ( empty($args['orderby']) ) {
			$WC_Query = new WC_Query();

			$args['orderby'] = 'menu_order title';
			$args['order']   = 'ASC';
			if ( $shortcodeAttr ) {
				if ( isset( $shortcodeAttr['orderby'] ) && '' !== $shortcodeAttr['orderby'] ) {
					$args['orderby'] = ( 'price-desc' === $shortcodeAttr['orderby'] ) ? 'price' : $shortcodeAttr['orderby'];
				}
				if ( isset( $shortcodeAttr['order'] ) && '' !== $shortcodeAttr['order'] ) {
					$args['order'] = ( isset( $shortcodeAttr['orderby'] ) && 'price-desc' === $shortcodeAttr['orderby'] )
						? 'DESC'
						: $shortcodeAttr['order'];
				}
			} else {
				$vars = $WC_Query->get_catalog_ordering_args();
				if ( is_array( $vars ) && ! empty( $vars['orderby'] ) ) {
					$args['orderby'] = $vars['orderby'];
					$args['order']   = empty( $vars['order'] ) ? 'ASC' : $vars['order'];
					if ( ! empty( $vars['meta_key'] ) ) {
						$args['meta_key'] = $vars['meta_key'];
					}
				}
			}
		}
		$args = $module->addWooOptions($args);

		if ( $shortcodeAttr ) {
			if ( ! isset($filterSettings['do_not_use_shortcut']) || ! $filterSettings['do_not_use_shortcut'] ) {
				$args = $this->addShortcodeAttrToArgs($args, $shortcodeAttr, $filterSettings);
			}
		}
		return $args;
	}

	/**
	 * Add shortcode adttributes to wp_query filtering args
	 *
	 * @param array $args
	 * @param array $shortcodeAttr
	 * @param array $filterSettings
	 *
	 * @return void
	 */
	public function addShortcodeAttrToArgs( $args, $shortcodeAttr, $filterSettings ) {
		if ( ! empty( $shortcodeAttr['rows'] && ! empty($shortcodeAttr['columns']) ) ) {
			$shortcodeAttr['limit'] = $shortcodeAttr['columns'] * $shortcodeAttr['rows'];
			$args['posts_per_page'] = intval( $shortcodeAttr['limit'] );
		}

		$args = $this->setCategoriesQueryArgs($args, $shortcodeAttr);
		$args = $this->setTagsQueryArgs($args, $shortcodeAttr);
		$args = $this->setAttributesQueryArgs($args, $shortcodeAttr);

		return $args;
	}

	public function setCategoriesQueryArgs( $args, $shortcodeAttr ) {
		if ( ! empty( $shortcodeAttr['category'] ) ) {
			$categories = array_map( 'sanitize_title', explode( ',', $shortcodeAttr['category'] ) );
			$field      = 'slug';

			if ( is_numeric( $categories[0] ) ) {
				$field      = 'term_id';
				$categories = array_map( 'absint', $categories );
				// Check numeric slugs.
				foreach ( $categories as $cat ) {
					$the_cat = get_term_by( 'slug', $cat, 'product_cat' );
					if ( false !== $the_cat ) {
						$categories[] = $the_cat->term_id;
					}
				}
			}

			$args['tax_query'][] = array(
				'taxonomy'         => 'product_cat',
				'terms'            => $categories,
				'field'            => $field,
				'operator'         => $shortcodeAttr['cat_operator'],

				/*
				 * When cat_operator is AND, the children categories should be excluded,
				 * as only products belonging to all the children categories would be selected.
				 */
				'include_children' => 'AND' === $shortcodeAttr['cat_operator'] ? false : true,
			);
		}

		return $args;
	}

	public function setTagsQueryArgs( $args, $shortcodeAttr ) {
		if ( ! empty( $shortcodeAttr['tag'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_tag',
				'terms'    => array_map( 'sanitize_title', explode( ',', $shortcodeAttr['tag'] ) ),
				'field'    => 'slug',
				'operator' => $shortcodeAttr['tag_operator'],
			);
		}

		return $args;
	}

	public function setAttributesQueryArgs( $args, $shortcodeAttr ) {
		if ( ! empty( $shortcodeAttr['attribute'] ) || ! empty( $shortcodeAttr['terms'] ) ) {
			$taxonomy = strstr( $shortcodeAttr['attribute'], 'pa_' ) ? sanitize_title( $shortcodeAttr['attribute'] ) : 'pa_' . sanitize_title( $shortcodeAttr['attribute'] );
			$terms    = $shortcodeAttr['terms'] ? array_map( 'sanitize_title', explode( ',', $shortcodeAttr['terms'] ) ) : array();
			$field    = 'slug';

			if ( $terms && is_numeric( $terms[0] ) ) {
				$field = 'term_id';
				$terms = array_map( 'absint', $terms );
				// Check numeric slugs.
				foreach ( $terms as $term ) {
					$theTerm = get_term_by( 'slug', $term, $taxonomy );
					if ( false !== $theTerm ) {
						$terms[] = $theTerm->term_id;
					}
				}
			}

			// If no terms were specified get all products that are in the attribute taxonomy.
			if ( ! $terms ) {
				$terms = get_terms(
					array(
						'taxonomy' => $taxonomy,
						'fields'   => 'ids',
					)
				);
				$field = 'term_id';
			}

			// We always need to search based on the slug as well, this is to accommodate numeric slugs.
			$args['tax_query'][] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $terms,
				'field'    => $field,
				'operator' => $shortcodeAttr['terms_operator'],
			);
		}

		return $args;
	}
}
