<?php
class OptionsModelWpf extends ModelWpf {
	private $_values = array();
	private $_valuesLoaded = false;
	
	public function get( $optKey ) {
		$this->_loadOptValues();
		return isset($this->_values[ $optKey ]) ? $this->_values[ $optKey ]['value'] : false;
	}
	public function getChanged( $optKey ) {
		$this->_loadOptValues();
		return isset($this->_values[ $optKey ]) ? $this->_values[ $optKey ]['changed_on'] : false;
	}
	public function isEmpty( $optKey ) {
		$value = $this->get($optKey);
		return ( false === $value );
	}
	public function save( $optKey, $val, $ignoreDbUpdate = false ) {
		$this->_loadOptValues();
		if (!isset($this->_values[ $optKey ]) || $this->_values[ $optKey ]['value'] != $val) {
			if (isset($this->_values[ $optKey ]) || !isset($this->_values[ $optKey ]['value'])) {
				$this->_values[ $optKey ] = array();
			}
			$this->_values[ $optKey ]['value'] = $val;
			$this->_values[ $optKey ]['changed_on'] = time();
			if (!$ignoreDbUpdate) {
				$this->_updateOptsInDb();
			}
		}
	}
	public function getAll() {
		$this->_loadOptValues();
		return $this->_values;
	}
	/**
	 * Pass throught refferer - to not lose memory for copy of same opts array
	 */
	public function fillInValues( &$options ) {
		$this->_loadOptValues();
		foreach ($options as $cKey => $cData) {
			foreach ($cData['opts'] as $optKey => $optData) {
				$value = 0;
				$changedOn = 0;
				// Retrive value from saved options
				if (isset($this->_values[ $optKey ])) {
					$value = $this->_values[ $optKey ]['value'];
					$changedOn = isset($this->_values[ $optKey ]['changed_on']) ? $this->_values[ $optKey ]['changed_on'] : '';
				} elseif (isset($optData['def'])) {	// If there were no saved data - set it as default
					$value = $optData['def'];
				}
				$options[ $cKey ]['opts'][ $optKey ]['value'] = $value;
				$options[ $cKey ]['opts'][ $optKey ]['changed_on'] = $changedOn;
				if (!isset($this->_values[ $optKey ]['value'])) {
					$this->_values[ $optKey ]['value'] = $value;
				}
			}
		}
	}
	public function saveGroup( $d = array() ) {
		if (isset($d['opt_values']) && is_array($d['opt_values']) && !empty($d['opt_values'])) {
			DispatcherWpf::doAction('beforeSaveOpts', $d);
			foreach ( $d['opt_values'] as $code => $val ) {
				if ( 'indexing_schedule' === $code ) {
					if ( '1' === $val ) {
						if ( ! wp_next_scheduled( 'wpf_calc_meta_indexing_shedule' ) ) {
							wp_schedule_event( time(), 'hourly', 'wpf_calc_meta_indexing_shedule' );
						}
					} else {
						wp_unschedule_hook( 'wpf_calc_meta_indexing_shedule' );
					}
				} else if ( 'optimizing_schedule' === $code ) {
					if ( '1' === $val ) {
						if ( ! wp_next_scheduled( 'wpf_calc_meta_optimizing_shedule' ) ) {
							wp_schedule_event( time(), 'hourly', 'wpf_calc_meta_optimizing_shedule' );
						}
					} else {
						wp_unschedule_hook( 'wpf_calc_meta_optimizing_shedule' );
					}
				}
				$this->save( $code, $val, true );
			}
			$this->_updateOptsInDb();
			return true;
		} else {
			$this->pushError(esc_html__('Empty data to save option', 'woo-product-filter'));
		}
		return false;
	}
	private function _updateOptsInDb() {
		update_option(WPF_CODE . '_opts_data', $this->_values);
	}
	private function _loadOptValues() {
		if (!$this->_valuesLoaded) {
			$this->_values = get_option(WPF_CODE . '_opts_data');
			if (empty($this->_values)) {
				$this->_values = array();
			}
			$this->_valuesLoaded = true;
		}
	}
}
