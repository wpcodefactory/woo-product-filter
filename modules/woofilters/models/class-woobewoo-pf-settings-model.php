<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Settings_Model Class
 *
 * Class contain filters settings api
 * You can use it in any part of your code with construction
 * WooBeWoo_PF_Frame::_()->getModule('woofilters')->getModel('settings');
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Settings_Model extends WooBeWoo_PF_Model {

	/* ------------------------------------------------------------------ 4.0.0: settings as JSON */

	/**
	 * Decode a stored setting_data value. Reads JSON (4.0 format) and falls back to the
	 * serialized 3.x format, so rows written by either version keep working.
	 *
	 * @since 4.0.0
	 *
	 * @param string|array $raw Stored value.
	 * @return array
	 */
	public static function decode_settings( $raw ) {
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$raw = (string) $raw;
		if ( '' === $raw ) {
			return array();
		}
		$first = substr( ltrim( $raw ), 0, 1 );
		if ( '{' === $first || '[' === $first ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		$decoded = maybe_unserialize( $raw );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Encode settings for storage: JSON, unescaped unicode and slashes.
	 * The filters table re-encodes serialized values through this method on write
	 * (transparent migration: read serialized, write JSON).
	 *
	 * @since 4.0.0
	 *
	 * @param array|string $settings Settings array (or an already encoded string).
	 * @return string
	 */
	public static function encode_settings( $settings ) {
		if ( is_string( $settings ) ) {
			$settings = self::decode_settings( $settings );
		}
		$json = wp_json_encode( (array) $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $json ? maybe_serialize( (array) $settings ) : $json;
	}

	/**
	 * Get specific filter block settings.
	 *
	 * @version 4.0.0
	 *
	 * @param int $filterId
	 *
	 * @return array
	 */
	public function getFilterBlockSettings( $filterId ) {
		$settings = array();
		$filter   = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( $filterId );

		if ( ! $filter ) {
			return $settings;
		}

		$settings = self::decode_settings( $filter['setting_data'] );

		return $settings;
	}

	/**
	 * Get filters order in filter block
	 * Except filters order we have some settings specific to individual filters
	 * that we keep in order settings too
	 *
	 * @version 3.4.0
	 *
	 * @param array $filterSettings
	 * @param int   $filterId
	 *
	 * @return array
	 */
	public function getFiltersOrder( $filterBlockSettings = array(), $filterId = 0 ) {
		$order = array();

		if ( ! $filterBlockSettings ) {
			$filterBlockSettings = $this->getFilterBlockSettings( $filterId );
		}

		if ( ! $filterBlockSettings ) {
			return $order;
		}

		if ( ! empty( $filterBlockSettings['settings']['filters']['order'] ) ) {
			$order = WooBeWoo_PF_Utils::jsonDecode( $filterBlockSettings['settings']['filters']['order'] );
		}

		return $order;
	}

	/**
	 * Get settings for individual filter in filter block by filter type
	 * We can have many filters in the filter block with the same type
	 * In such case we return all filters with the same type
	 *
	 * @version 3.4.0
	 *
	 * @param string $filterType
	 * @param array  $order
	 * @param array  $filterBlockSettings
	 * @param int    $filterId
	 *
	 * @return array
	 */
	public function getFilterSettings( $filterType, $order = array(), $filterBlockSettings = array(), $filterId = 0, $blockId = -1 ) {
		$filterSettings = array();

		if ( ! $order ) {
			$order = $this->getFiltersOrder( $filterBlockSettings, $filterId );
		}

		$filterList = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getAllFilters();
		if ( ! array_key_exists( $filterType, $filterList ) ) {
			return $filterSettings;
		}

		foreach ( $order as $index => $filterData ) {
			if ( $filterData['id'] == $filterType ) {
				if ( $blockId < 0 || $index == $blockId ) {
					$filterSettings[] = $filterData;
				}
			}
		}

		return $filterSettings;
	}
}
