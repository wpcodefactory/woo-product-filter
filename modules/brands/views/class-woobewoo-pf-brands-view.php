<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Brands_View Class
 *
 * Renders the WooCommerce Brands (product_brand) block in Free. Extends the woofilters view so
 * the shared helpers (terms with counts, option HTML, header, layout) are reused. Picked up by
 * the block renderer through the `woobewoo_pf_get_pro_view` filter when Pro is not active.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooBeWoo_PF_Woofilters_View' ) ) {
	WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView();
}

class WooBeWoo_PF_Brands_View extends WooBeWoo_PF_Woofilters_View {

	private $cssAdded = false;

	/**
	 * Brand block HTML (same contract as the other generate*FilterHtml methods).
	 */
	public function generateBrandFilterHtml( $filter, $filterSettings, $blockStyle, $key = 1, $viewId = '' ) {
		$settings = $this->getFilterSetting( $filter, 'settings', array() );
		$taxonomy = 'product_brand';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}
		$this->setCurrentSettings( $filterSettings );

		$hiddenBrands         = $this->getFilterSetting( $settings, 'f_hidden_brands', false );
		$includeBrandId       = ( ! empty( $settings['f_mlist[]'] ) ) ? explode( ',', $settings['f_mlist[]'] ) : false;
		$includeBrandChildren = $this->getFilterSetting( $settings, 'f_mlist_with_children', false );
		$excludeIds           = ! empty( $settings['f_exclude_terms'] ) ? $settings['f_exclude_terms'] : false;
		$hideEmpty            = $this->getFilterSetting( $settings, 'f_hide_empty', false );
		$hideEmptyActive      = $hideEmpty && $this->getFilterSetting( $settings, 'f_hide_empty_active', false );
		$showLogos            = $this->getFilterSetting( $settings, 'f_show_logos', false );
		$logoLayout           = $this->getFilterSetting( $settings, 'f_logo_layout', 'list', false, array( 'list', 'swatch' ) );

		if ( ! empty( $includeBrandId ) && $includeBrandChildren ) {
			$includeBrandId = $this->getChildrenOfIncludedCategories( $taxonomy, $includeBrandId );
		}
		$args = array(
			'parent'     => 0,
			'hide_empty' => $hideEmpty,
			'include'    => $includeBrandId,
		);
		$isCustomOrder  = $includeBrandId && ! empty( $settings['f_order_custom'] );
		$order          = ! $isCustomOrder && ! empty( $settings['f_sort_by'] ) ? $settings['f_sort_by'] : 'default';
		$isDefaultOrder = ( 'default' === $order );
		if ( $isCustomOrder || ! $isDefaultOrder ) {
			$args = array_merge(
				$args,
				array(
					'orderby'         => ( $isCustomOrder ? 'include' : 'name' ),
					'order'           => ( $isDefaultOrder ? 'asc' : $order ),
					'sort_as_numbers' => false,
				)
			);
		}

		$showAllBrands = $this->getFilterSetting( $settings, 'f_show_all_brands', false );
		list( $showedTerms, $countsTerms, $showFilter, $allTerms ) = $this->getShowedTerms( $taxonomy, $showAllBrands );

		$productBrand = $this->getTaxonomyHierarchy( $taxonomy, $args );
		if ( ! $productBrand ) {
			return '';
		}

		$type = $this->getFilterSetting( $settings, 'f_frontend_type', 'list', false, array( 'list', 'multi', 'dropdown', 'mul_dropdown' ) );
		$filterName = ( 'multi' === $type ) ? 'product_brand_list' : 'wpf_filter_brand';

		$defSelected   = WooBeWoo_PF_Req::getVar( $filterName );
		$brandSelected = $defSelected;
		if ( $brandSelected ) {
			$brandSelected = preg_split( '/[|,]/', (string) $brandSelected );
			// The engine filters brands by slug; the option list compares ids.
			$ids = array();
			foreach ( $brandSelected as $v ) {
				$t = is_numeric( $v ) ? get_term( (int) $v, $taxonomy ) : get_term_by( 'slug', $v, $taxonomy );
				if ( $t && ! is_wp_error( $t ) ) {
					$ids[] = (int) $t->term_id;
				}
			}
			$brandSelected = $ids;
		} elseif ( $hiddenBrands && $includeBrandId ) {
			$brandSelected    = $includeBrandId;
			$filter['is_ids'] = true;
		}
		if ( empty( $brandSelected ) ) {
			$obj = get_queried_object();
			if ( $obj instanceof WP_Term && $taxonomy === $obj->taxonomy ) {
				$brandSelected = array( (int) $obj->term_id );
			}
		}

		$layout = $this->getFilterLayout( $settings, $filterSettings );
		if ( $defSelected && ! $hideEmptyActive ) {
			$showedTerms = $allTerms;
			$showFilter  = true;
		}

		$logic     = $this->getFilterSetting( $settings, 'f_query_logic', 'or' );
		$notValues = '';
		if ( $hiddenBrands ) {
			$type = 'list';
			if ( ! $includeBrandId && $excludeIds ) {
				$logic     = 'not';
				$notValues = '" data-not-ids="' . esc_attr( $excludeIds );
			}
		}
		$filter['settings']['f_frontend_type'] = $type;
		$filter['settings']['f_show_images']   = false;

		$htmlOpt = $this->generateTaxonomyOptionsHtmlFromPro( $productBrand, $brandSelected, $filter, $excludeIds, '', $layout, $includeBrandId, $showedTerms, $countsTerms );
		if ( $showLogos && in_array( $type, array( 'list', 'multi' ), true ) ) {
			$htmlOpt = $this->addLogos( $htmlOpt, $taxonomy );
		}

		$wrapperStart = '';
		$wrapperEnd   = '';
		if ( 'list' === $type || 'multi' === $type ) {
			$maxHeight = $this->getFilterSetting( $settings, 'f_max_height', 0, true );
			if ( $maxHeight > 0 ) {
				$this->setFilterCss( '#' . self::$blockId . ' .wpfFilterVerScroll {max-height:' . (int) $maxHeight . 'px;}' );
			}
			$logoClass    = $showLogos ? ' wpfBrandLogos wpfBrandLogos--' . $logoLayout : '';
			$wrapperStart = '<ul class="wpfFilterVerScroll' . $layout['class'] . $logoClass . '">';
			$wrapperEnd   = '</ul>';
		} elseif ( 'dropdown' === $type ) {
			$wrapperStart = '<select aria-label="' . esc_attr( $this->getRealDropdownLabel( $settings, __( 'Product brands', 'woo-product-filter' ) ) ) . '">';
			$first        = ! empty( $filter['settings']['f_dropdown_first_option_text'] ) ? $filter['settings']['f_dropdown_first_option_text'] : __( 'Select all', 'woo-product-filter' );
			$htmlOpt      = '<option value="" data-slug="">' . esc_html( $first ) . '</option>' . $htmlOpt;
			$wrapperEnd   = '</select>';
		} elseif ( 'mul_dropdown' === $type ) {
			$htmlOpt = $this->getMultiSelectHtml( $htmlOpt, $settings );
		}

		$noActive    = $defSelected ? '' : 'wpfNotActive';
		$noActive    = $hiddenBrands ? 'wpfHidden' : $noActive;
		$preselected = $hiddenBrands ? ' wpfPreselected' : '';
		$blockStyle  = ( ! $showFilter || ( ! $showAllBrands && self::$isLeerFilter ) ? 'display:none;' : '' ) . $blockStyle;
		if ( ! empty( $blockStyle ) ) {
			$this->setFilterCss( '#' . self::$blockId . ' {' . $blockStyle . '}' );
		}
		$showCount       = $this->getFilterSetting( $settings, 'f_show_count', false ) ? ' wpfShowCount' : '';
		$blockAttributes = isset( $filter['blockAttributes'] ) ? $filter['blockAttributes'] : '';

		$html  = '<div class="wpfFilterWrapper ' . $noActive . $showCount . $preselected . '"' .
			$this->setFilterId() .
			$this->setCommonFitlerDataAttr( $filter, $filterName, $type, 'brand' ) .
			' data-taxonomy="product_brand" data-query-logic="' . esc_attr( $logic ) . $notValues . '"' .
			' data-hide-active="' . ( $hideEmptyActive ? '1' : '0' ) . '"' .
			' data-show-all="' . ( (int) $showAllBrands ) . '"' . $blockAttributes .
			'>';
		$html .= $this->generateFilterHeaderHtml( $filter, $filterSettings, $noActive );
		$html .= $this->generateDescriptionHtml( $filter );
		if ( 'list' === $type && $this->getFilterSetting( $settings, 'f_show_search_input', false ) ) {
			$html .= '<div class="wpfSearchWrapper"><input class="wpfSearchFieldsFilter" type="text" placeholder="' . esc_attr__( 'Search ...', 'woo-product-filter' ) . '"></div>';
		}
		$html .= '<div class="wpfCheckboxHier">' . $wrapperStart . $htmlOpt . $wrapperEnd . '</div>';
		$html .= '</div>'; // end wpfFilterContent (opened by the header).
		$html .= '</div>'; // end wpfFilterWrapper.

		$this->addCss();
		return $html;
	}

	/**
	 * Put the brand logo in front of each option label (term image meta wpf_term_image,
	 * else the WooCommerce term thumbnail).
	 */
	public function addLogos( $html, $taxonomy ) {
		return preg_replace_callback(
			'/(<label class="woobewoo(?:Checkbox|Radio)" data-term-id="(\d+)"[^>]*>)/',
			function ( $m ) {
				$src = WooBeWoo_PF_Brands::logoUrl( (int) $m[2], 'thumbnail' );
				if ( ! $src ) {
					return $m[1] . '<span class="wpfBrandLogo wpfBrandLogo--empty" aria-hidden="true"></span>';
				}
				return $m[1] . '<img class="wpfBrandLogo" src="' . esc_url( $src ) . '" alt="" loading="lazy" width="40" height="40">';
			},
			$html
		);
	}

	private function addCss() {
		if ( $this->cssAdded ) {
			return;
		}
		$this->cssAdded = true;
		$module         = WooBeWoo_PF_Frame::_()->getModule( 'brands' );
		if ( $module ) {
			WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-brands', $module->getModPath() . 'css/frontend.brands.css', array(), WPF_VERSION );
		}
	}
}
