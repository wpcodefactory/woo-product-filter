<?php
/**
 * Class to adapt field before display
 * return ONLY htmlParams property
 *
 * @see field
 */
class FieldAdapterWpf {
	const DB = 'DbWpf';
	const HTML = 'HtmlWpf';
	const STR = 'str';
	public static $userfieldDest = array('registration', 'shipping', 'billing');
	public static $countries = array();
	public static $states = array();
	/**
	 * Executes field Adaption process
	 *
	 * @param object type field or value $fieldOrValue if DB adaption - this must be a value of field, elase if html - field object
	 */
	public static function _( $fieldOrValue, $method, $type ) {
		if (method_exists('FieldAdapterWpf', $method)) {
			switch ($type) {
				case self::DB:
					return self::$method($fieldOrValue);
					break;
				case self::HTML:
					self::$method($fieldOrValue);
					break;
				case self::STR:
					return self::$method($fieldOrValue);
					break;
			}
		}
		return $fieldOrValue;
	}
	public static function userFieldDestHtml( $field ) {
		$field->htmlParams['OptionsWpf'] = array();
		if (!is_array($field->value)) {
			if (empty($field->value)) {
				$field->value = array();
			} else {
				$field->value = json_decode($field->value);
			}
		}
		foreach (self::$userfieldDest as $d) {
			$field->htmlParams['OptionsWpf'][] = array(
				'id' => $d,
				'text' => $d,
				'checked' => in_array($d, $field->value),
			);
		}
	}
	public static function userFieldDestToDB( $value ) {
		return UtilsWpf::jsonEncode($value);
	}
	public static function userFieldDestFromDB( $value ) {
		return UtilsWpf::jsonDecode($value);
	}
	
	public static function displayCountry( $cid, $key = 'name' ) {
		if ('name' == $key) {
			$countries = self::getCountries();
			return $countries[$cid];
		} else {
			if (empty(self::$countries)) {
				self::$countries = self::getCachedCountries();
			}
			foreach (self::$countries as $c) {
				if ($c['id'] == $cid) {
					return $c[ $key ];
				}
			}
		}
		return false;
	}
	public static function displayState( $sid, $key = 'name' ) {
		$states = self::getStates();
		return empty($states[$sid]) ? $sid : $states[$sid][$key];
	}
	public static function getCountries( $notSelected = false ) {
		static $options = array();
		if (empty($options[ $notSelected ])) {
			$options[ $notSelected ] = array();
			if (empty(self::$countries)) {
				self::$countries = self::getCachedCountries();
			}
			if ($notSelected) {
				$options[ $notSelected ][0] = is_bool($notSelected) ? esc_html__('Not selected', 'woo-product-filter') : esc_html__($notSelected);
			}
			foreach (self::$countries as $c) {
				$options[ $notSelected ][$c['id']] = $c['name'];
			}
		}
		return $options[ $notSelected ];
	}
	public static function getStates( $notSelected = false ) {
		static $options = array();
		if (empty($options[ $notSelected ])) {
			$options[ $notSelected ] = array();
			if (empty(self::$states)) {
				self::$states = self::getCachedStates();
			}
			if ($notSelected) {
				$notSelectedLabel = is_bool($notSelected) ? 'Not selected' : $notSelected;
				$options[ $notSelected ][0] = array('name' => esc_html__( $notSelectedLabel ), 'country_id' => null);
			}
			foreach (self::$states as $s) {
				$options[ $notSelected ][$s['id']] = $s;
			}
		}
		return $options[ $notSelected ];
	}
	/**
	 * Function to get extra field options 
	 * 
	 * @param object $field
	 * @return string 
	 */
	public static function getExtraFieldOptions( $field_id ) {
		$output = '';
		if (0 == $field_id) {
			return '';
		}
		$options = FrameWpf::_()->getModule('OptionsWpf')->getHelper()->getOptions($field_id);
		if (!empty($options)) {
			foreach ($options as $key=>$value) {
				$output .= '<p>' . $value . '<span class="delete_option" rel="' . $key . '"></span></p>';
			}
		}
		return $output;
	}
	/**
	 * Function to get field params
	 * 
	 * @param object $params 
	 */
	public static function getFieldAttributes( $params ) {
		$output = '';
		if (!empty($params->attr)) {
			foreach ($params->attr as $key=>$value) {
				$output .= esc_html__($key) . ':<br />';
				$output .= HtmlWpf::text('params[attr][' . $key . ']', array('value' => $value)) . '<br />';
			}
		} else {
			$output .= esc_html__('class', 'woo-product-filter') . ':<br />';
			$output .= HtmlWpf::text('params[attr][class]', array('value' => '')) . '<br />';
			$output .= esc_html__('id', 'woo-product-filter') . ':<br />';
			$output .= HtmlWpf::text('params[attr][id]', array('value' => '')) . '<br />';
		}
		return $output;
	}
	/**
	 * Generating the list of categories for product extra fields
	 * 
	 * @param object $field 
	 */
	public static function productFieldCategories( $field ) {
		if (!empty($field->htmlParams['OptionsWpf'])) {
			return;
		}
	}
	public static function intToDB( $val ) {
		return intval($val);
	}
	public static function floatToDB( $val ) {
		return floatval($val);
	}
	/**
	 * Save this in static var - to futher usage
	 *
	 * @return array with countries
	 */
	public static function getCachedCountries( $clearCache = false ) {
		if (empty(self::$countries) || $clearCache) {
			self::$countries = FrameWpf::_()->getTable('countries')->getAll('id, name, iso_code_2, iso_code_3');
		}
		return self::$countries;
	}
	/**
	 * Save this in static var - to futher usage
	 *
	 * @return array with states
	 */
	public static function getCachedStates( $clearCache = false ) {
		if (empty(self::$states) || $clearCache) {
			self::$states = FrameWpf::_()->getTable('states')
				->leftJoin( FrameWpf::_()->getTable('countries'), 'country_id' )
				->getAll('toe_states.id,
					toe_states.name, 
					toe_states.code, 
					toe_states.country_id, 
					toe_cry.name AS c_name,
					toe_cry.iso_code_2 AS c_iso_code_2, 
					toe_cry.iso_code_3 AS c_iso_code_3');
		}
		return self::$states;
	}
}
