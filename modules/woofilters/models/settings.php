<?php
/**
 * Class contain silters settings api
 */

/**
 * Class contain filters settings api
 * You can use it in any part of your code with construction
 * FrameWpf::_()->getModule('woofilters')->getModel('settings');
 */

class SettingsModelWpf extends ModelWpf {
	/**
	 * Get specific filter block settings
	 *
	 * @param int $filterId
	 *
	 * @return array
	 */
	public function getFilterBlockSettings( $filterId ) {
		$settings = array();
		$filter = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getById($filterId);

		if (!$filter) {
			return $settings;
		}

		$settings = unserialize($filter['setting_data']);

		return $settings;
	}

	/**
	 * Get filters order in filter block
	 * Except filters order we have some settings specific to individual filters
	 * that we keep in order settings too
	 *
	 * @param array $filterSettings
	 * @param int $filterId
	 *
	 * @return array
	 */
	public function getFiltersOrder( $filterBlockSettings = array(), $filterId = 0 ) {
		$order = array();

		if (!$filterBlockSettings) {
			$filterBlockSettings = $this->getFilterBlockSettings($filterId);
		}

		if (!$filterBlockSettings) {
			return $order;
		}

		if (!empty($filterBlockSettings['settings']['filters']['order'])) {
			$order = UtilsWpf::jsonDecode($filterBlockSettings['settings']['filters']['order']);
		}

		return $order;
	}

	/**
	 * Get settings for individual filter in filter block by filter type
	 * We can have many filters in the filter block with the same type
	 * In such case we return all filters with the same type
	 *
	 * @param string $filterType
	 * @param array $order
	 * @param array $filterBlockSettings
	 * @param int $filterId
	 *
	 * @return array
	 */
	public function getFilterSettings( $filterType, $order = array(), $filterBlockSettings = array(), $filterId = 0, $blockId = -1 ) {
		$filterSettings = array();

		if (!$order) {
			$order =  $this->getFiltersOrder($filterBlockSettings, $filterId);
		}

		$filterList = FrameWpf::_()->getModule('woofilters')->getModel('woofilters')->getAllFilters();
		if (!array_key_exists($filterType, $filterList)) {
			return $filterSettings;
		}

		foreach ($order as $index => $filterData) {
			if ($filterData['id'] == $filterType) {
				if ($blockId < 0 || $index == $blockId) {
					$filterSettings[] = $filterData;
				}
			}
		}

		return $filterSettings;
	}
}
