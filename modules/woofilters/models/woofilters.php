<?php
/**
 * Product Filter by WBW - WoofiltersModelWpf Class
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WoofiltersModelWpf extends ModelWpf {
	public function __construct() {
		$this->_setTbl('filters');
	}

	public function getAllFilters() {
		$filterTypes = array(
			'wpfPrice' => array(
				'name'         => esc_html__('Price', 'woo-product-filter'),
				'slug'         => esc_attr__('price', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
				'group'        => 'wpfPriceRange'
			),
			'wpfPriceRange' => array(
				'name'         => esc_html__('Price range', 'woo-product-filter'),
				'slug'         => esc_attr__('price range', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
				'group'        => 'wpfPrice'
			),
			'wpfSortBy' => array(
				'name' => esc_html__('Sort by', 'woo-product-filter'),
				'slug'         => esc_attr__('sort by', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
			),
			'wpfCategory' => array(
				'name'         => esc_html__('Product categories', 'woo-product-filter'),
				'slug'         => esc_attr__('category', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => false,
				'content_type' => 'taxonomy',
				'filtername'   => 'wpf_filter_cat'
			),
			'wpfTags' => array(
				'name'         => esc_html__('Product tags', 'woo-product-filter'),
				'slug'         => esc_attr__('tag', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => false,
				'content_type' => 'taxonomy',
				'filtername'   => 'product_tag'
			),
			'wpfAttribute' => array(
				'name'         => esc_html__('Attribute', 'woo-product-filter'),
				'slug'         => '',
				'enabled'      => true,
				'unique'       => false,
				'content_type' => 'taxonomy',
			),
			'wpfAuthor' => array(
				'name'         => esc_html__('Author', 'woo-product-filter'),
				'slug'         => esc_attr__('author', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'user',
			),
			'wpfFeatured' => array(
				'name'         => esc_html__('Featured', 'woo-product-filter'),
				'slug'         => esc_attr__('featured', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
			),
			'wpfOnSale' => array(
				'name'         => esc_html__('On sale', 'woo-product-filter'),
				'slug'         => esc_attr__('on sale', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
			),
			'wpfInStock' => array(
				'name'         => esc_html__('Stock status', 'woo-product-filter'),
				'slug'         => esc_attr__('stock status', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
			),
			'wpfRating' => array(
				'name'         => esc_html__('Rating', 'woo-product-filter'),
				'slug'         => esc_attr__('rating', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => true,
				'content_type' => 'meta',
			),
			'wpfSearchText' => array(
				'name'         => esc_html__('Search by Text', 'woo-product-filter'),
				'slug'         => esc_attr__('text', 'woo-product-filter'),
				'enabled'      => false,
				'unique'       => true,
				'content_type' => '',
			),
			'wpfSearchNumber' => array(
				'name'         => esc_html__('Search by Number', 'woo-product-filter'),
				'slug'         => esc_attr__('number', 'woo-product-filter'),
				'enabled'      => false,
				'unique'       => false,
				'content_type' => '',
			),
		);

		/**
		 * Plugin compatibility
		 *
		 * @link https://wordpress.org/plugins/perfect-woocommerce-brands
		 */
		if (taxonomy_exists('pwb-brand')) {
			$filterTypes['wpfPerfectBrand'] = array(
				'name'         => esc_html__('Perfect brands', 'woo-product-filter'),
				'slug'         => esc_attr__('brand', 'woo-product-filter'),
				'enabled'      => true,
				'unique'       => false,
				'content_type' => 'taxonomy',
			);
		}

		/**
		 * Plugin compatibility
		 *
		 * @link https://woocommerce.com/products/brands
		 */
		if (taxonomy_exists('product_brand')) {
			$filterTypes['wpfBrand'] = array(
				'name'         => esc_html__('Product brands', 'woo-product-filter'),
				'slug'         => esc_attr__('brand', 'woo-product-filter'),
				'enabled'      => false,
				'unique'       => true,
				'content_type' => 'taxonomy',
			);
		}

		/**
		 * Plugin compatibility
		 *
		 * @link https://wordpress.org/plugins/wc-vendors/
		 */
		if ( class_exists('WC_Vendors')) {
			$filterTypes['wpfVendors'] = array(
				'name'         => esc_html__('Vendors', 'woo-product-filter'),
				'slug'         => esc_attr__('vendors', 'woo-product-filter'),
				'enabled'      => false,
				'unique'       => true,
				'content_type' => 'user',
			);
		}

		return DispatcherWpf::applyFilters('addFilterTypes', $filterTypes);
	}

	public function getSortByFilterLabels ( $params = [] ) {
		$labels = $this->getFilterLabels('SortBy');

		if ( $params ) {
			$newLabels = [];
			$field = 'f_options[]';

			foreach ( $params as $key=>$value ) {
				if ('wpfSortBy' == $value->id && isset($value->settings) && !empty($value->settings->{$field})) {
					foreach ( explode(',', $value->settings->{$field}) as $_key=>$_value ) {
						if ( isset($labels[$_value]) ) {
							$newLabels[$_value] = $labels[$_value];
						}
					}
					break;
				}
			}

			if ($newLabels) {
				if (count($newLabels) != count($labels) ) {
					$diff = array_diff($labels, $newLabels);
					if ($diff) {
						$newLabels = array_merge($newLabels, $diff);
					}
				}
				return $newLabels;
			}
		}

		return $labels;
	}

	public function getFilterLabels( $filter ) {
		switch ($filter) {
			case 'SortBy':
				$labels = array(
					'default' => esc_html__('Default', 'woo-product-filter'),
					'popularity' => esc_html__('Popularity', 'woo-product-filter'),
					'rating' => esc_html__('Rating', 'woo-product-filter'),
					'date' => esc_html__('Newness', 'woo-product-filter'),
					'date-asc' => esc_html__('Oldest first', 'woo-product-filter'),
					'price' => esc_html__('Price: low to high', 'woo-product-filter'),
					'price-desc' => esc_html__('Price: high to low', 'woo-product-filter'),
					'rand' => esc_html__('Random', 'woo-product-filter'),
					'title' => esc_html__('Name A to Z', 'woo-product-filter'),
					'title-desc' => esc_html__('Name Z to A', 'woo-product-filter'),
					'sku' => esc_html__('SKU ascending', 'woo-product-filter'),
					'sku-desc' => esc_html__('SKU descending', 'woo-product-filter'),
					);
				$custom = apply_filters('woocommerce_catalog_orderby', array());
				if (!empty($custom)) {
					$labels = array_merge($labels, $custom);
				}
				break;
			case 'InStock':
				$labels = array(
					'instock' => esc_html__('In Stock', 'woo-product-filter'),
					'outofstock' => esc_html__('Out of Stock', 'woo-product-filter'),
					'onbackorder' => esc_html__('On Backorder', 'woo-product-filter'),
					);
				break;
			case 'OnSale':
				$labels = array(
					'onsale' => esc_html__('On Sale', 'woo-product-filter')
					);
				break;
			case 'Category':
			case 'PerfectBrand':
			case 'Tags':
			case 'Attribute':
			case 'Author':
				$labels = array(
					'search' => esc_html__('Search ...', 'woo-product-filter')
					);
				break;
			default:
				$labels = array();
				break;
		}
		return $labels;
	}

	public function save( $data = array() ) {

		$id = isset($data['id']) ? $data['id'] : false;

		$title = !empty($data['title']) ? $data['title'] : gmdate('Y-m-d-h-i-s');
		$data['title'] = $title;
		$duplicateId = isset($data['duplicateId']) ? $data['duplicateId'] : false;
		//already created filter
		if ( !empty($id) && !empty($title) ) {
			$data['id'] = (string) $id;
			$statusUpdate = $this->updateById( $data , $id );
			if ($statusUpdate) {
				return $id;
			}
		} else if ( empty($id) && !empty($title) && empty($duplicateId) ) {  //empty filter
			$idInsert = $this->insert( $data );
			if ($idInsert) {
				if (empty($title)) {
					$title = (string) $idInsert;
				}
				$data['id'] = (string) $idInsert;
				$this->updateById( $data , $idInsert );
			}
			return $idInsert;
		} elseif ( empty($id) && !empty($title) && !empty($duplicateId) ) {  //duplicate filter
			$duplicateData                      = $this->getById($duplicateId);
			$settings                           = unserialize($duplicateData['setting_data']);
			$settings['settings']['css_editor'] = stripslashes(base64_decode($settings['settings']['css_editor']));
			$settings['settings']['js_editor']  = stripslashes(base64_decode($settings['settings']['js_editor']));
			$duplicateData['settings']          = $settings['settings'];
			$duplicateData['title']             = isset($title) ? $title : 'untitled';
			$duplicateData['id']                = '';
			$idInsert                           = $this->insert( $duplicateData );
			return $idInsert;
		}
		return false;
	}
	protected function _dataSave( $data, $update = false ) {
		$esettings = isset($data['esettings']) ? UtilsWpf::jsonDecode(stripslashes($data['esettings'])) : array();
		if (!empty($esettings)) {
			$data['settings'] = array_merge($esettings, ( isset($data['settings']) && is_array($data['settings']) ? $data['settings'] : array() ));
		}

		$settings                             = isset($data['settings']) ? $data['settings'] : array();

		$data['settings']['css_editor']       = isset($settings['css_editor']) ? base64_encode($settings['css_editor']) : '';
		$data['settings']['js_editor']        = isset($settings['js_editor']) ? base64_encode($settings['js_editor']) : '';
		$data['settings']['filters']['order'] = isset($settings['filters']) && isset($settings['filters']['order']) ? stripslashes($settings['filters']['order']) : '';
		$notEdit                              = array('css_editor', 'js_editor', 'filters');
		foreach ($data['settings'] as $key => $value) {
			if (!in_array($key, $notEdit) && is_string($value)) {
				$v = str_replace('"', '&quot;', str_replace('\"', '"', $value));
				$data['settings'][$key] = str_replace("'", '&#039;', str_replace("\'", "'", $v));
			}
		}
		$metaKeys = $this->getDataFilterMetaKeys($data['settings']['filters']['order']);
		$data['meta_keys'] = count($metaKeys) > 0 ? implode('|', $metaKeys) : '';

		$settingData          = array('settings' => $data['settings']);
		$data['setting_data'] = addslashes(serialize($settingData));

		$this->translateStrings($data['settings']);

		return $data;
	}

	/**
	 * WPML string translation
	 */
	private function translateStrings( $data_settings ) {
		$filters_arr = json_decode($data_settings['filters']['order'], true);
		// print_r($filters_arr);
		// die;
		// here goes othes filter options strings
		if ( is_array( $filters_arr ) ) {
			foreach ( $filters_arr as $key => $filter ) {
				if ( isset( $filter['settings']['f_dropdown_first_option_text'] ) ) {
					wpf_translate_string( $filter['settings']['f_dropdown_first_option_text'] );
				}
				if ( isset( $filter['settings']['f_title'] ) ) {
					wpf_translate_string( $filter['settings']['f_title'] );
				}
				if ( isset( $filter['settings']['f_description'] ) ) {
					wpf_translate_string( $filter['settings']['f_description'] );
				}
				if ( isset( $filter['settings']['f_custom_title'] ) ) {
					wpf_translate_string( $filter['settings']['f_custom_title'] );
				}
				if ( isset( $filter['settings']['f_search_label'] ) ) {
					wpf_translate_string( $filter['settings']['f_search_label'] );
				}
				if ( isset( $filter['settings']['f_stock_statuses[in]'] ) ) {
					wpf_translate_string( $filter['settings']['f_stock_statuses[in]'] );
				}
				if ( isset( $filter['settings']['f_stock_statuses[out]'] ) ) {
					wpf_translate_string( $filter['settings']['f_stock_statuses[out]'] );
				}
				if ( isset( $filter['settings']['f_stock_statuses[on]'] ) ) {
					wpf_translate_string( $filter['settings']['f_stock_statuses[on]'] );
				}
				if ( isset( $filter['settings']['f_add_text'] ) ) {
					wpf_translate_string( $filter['settings']['f_add_text'] );
				}
				if ( isset( $filter['settings']['f_add_text5'] ) ) {
					wpf_translate_string( $filter['settings']['f_add_text5'] );
				}
				if ( isset( $filter['settings']['f_checkbox_label'] ) ) {
					wpf_translate_string( $filter['settings']['f_checkbox_label'] );
				}
			}
		}
		// here goes other plugin settings strings
		if (!empty($data_settings['filtering_button_word'])) {
			wpf_translate_string($data_settings['filtering_button_word']); // ?
		}
		if (!empty($data_settings['show_clean_button_word'])) {
			wpf_translate_string($data_settings['show_clean_button_word']); // ?
		}
		if (!empty($data_settings['hide_button_hide_text'])) {
			wpf_translate_string($data_settings['hide_button_hide_text']); // ?
		}
		if (!empty($data_settings['hide_button_show_text'])) {
			wpf_translate_string($data_settings['hide_button_show_text']); // ?
		}
		if (!empty($data_settings['text_no_products'])) {
			wpf_translate_string($data_settings['text_no_products']); // ?
		}
		if (!empty($data_settings['enable_overlay_word']) && '1' == $data_settings['enable_overlay_word'] && !empty($data_settings['overlay_word'])) {
			wpf_translate_string($data_settings['overlay_word']); // ?
		}
		if (!empty($data_settings['selected_params_clear']) && '1' == $data_settings['selected_params_clear'] && !empty($data_settings['selected_clean_word'])) {
			wpf_translate_string($data_settings['selected_clean_word']); // ?
		}
	}

	public function getDataFilterMetaKeys( $filters, $save = true ) {
		$filters = UtilsWpf::jsonDecode($filters);
		$metaKeys = array();
		if (class_exists('WooCommerceB2B') && FrameWpf::_()->getModule('options')->getModel()->get('use_wcb2b_prices') == 1) {
			foreach ($filters as $filter) {
				$filterId = $this->getFilterSetting($filter, 'id');
				if ('wpfPrice' == $filterId || 'wpfPriceRange' == $filterId) {
					$metaKeys[] = 'wcb2b_product_group_prices';
					break;
				}
			}
		}

		$metaKeys = DispatcherWpf::applyFilters('addCustomMetaKeys', $metaKeys, $filters);
		foreach ($metaKeys as $k => $key) {
			$metaKeys[$k] = strtolower($key);
		}
		if ($save && count($metaKeys) > 0) {
			$keysModel = FrameWpf::_()->getModule('meta')->getModel('meta_keys');
			if (!$keysModel->addFilterMetaKeys($metaKeys)) {
				$this->pushError($keysModel->getErrors());
			}
		}
		return $metaKeys;
	}

	public function getFiltersMetaKeys( $id = 0, $deep = false ) {
		$keys = array();
		if (!$deep) {
			if (!empty($id)) {
				$this->addWhere(array('id' => $id));
			}
			$data = $this->setSelectFields('meta_keys')->addWhere("meta_keys is null OR meta_keys!=''")->getFromTbl(array('return' => 'col'));

			foreach ($data as $str) {
				if (is_null($str)) {
					$deep = true;
					break;
				}
				$keys = array_merge($keys, explode('|', $str));
			}
		}
		if ($deep) {
			$keys = array();
			if (!empty($id)) {
				$this->addWhere(array('id' => $id));
			}
			$data = $this->setSelectFields('id, setting_data')->getFromTbl();
			foreach ($data as $filter) {
				$settings = unserialize($filter['setting_data']);
				$metaKeys = $settings && !empty($settings['settings']['filters']['order']) ? $this->getDataFilterMetaKeys($settings['settings']['filters']['order'], false) : array();
				$query = "UPDATE `@__filters` SET meta_keys='";
				if (!empty($metaKeys)) {
					$query .= implode('|', $metaKeys);
					$keys = array_merge($keys, $metaKeys);
				}
				if (!DbWpf::query($query . "' WHERE id=" . $filter['id'])) {
					$this->pushError(DbWpf::getError());
					return false;
				}
			}
		}
		return $keys;
	}
}
