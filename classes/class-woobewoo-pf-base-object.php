<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Base_Object Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

abstract class WooBeWoo_PF_Base_Object {

	/**
	 * _internalErrors.
	 */
	protected $_internalErrors = array();

	/**
	 * _haveErrors.
	 */
	protected $_haveErrors = false;

	/**
	 * pushError.
	 */
	public function pushError( $error, $key = '' ) {
		if ( is_array( $error ) ) {
			$this->_internalErrors = array_merge( $this->_internalErrors, $error );
		} elseif ( empty( $key ) ) {
			$this->_internalErrors[] = $error;
		} else {
			$this->_internalErrors[ $key ] = $error;
		}
		$this->_haveErrors = true;
	}

	/**
	 * getErrors.
	 */
	public function getErrors() {
		return $this->_internalErrors;
	}

	/**
	 * haveErrors.
	 */
	public function haveErrors() {
		return $this->_haveErrors;
	}

	/**
	 * Get settings in specific filter in filter block.
	 *
	 * @version 3.3.2
	 *
	 * @param array  $settings
	 * @param string $name
	 * @param mix    $default
	 * @param bool   $num
	 * @param array  $arr Restriction list of setting value can be.
	 * @param bool   $zero
	 *
	 * @return int|string
	 */
	public function getFilterSetting( $settings, $name, $default = '', $num = false, $arr = false, $zero = false, $leer = false ) {

		if ( ! isset( $settings[ $name ] ) ) {
			return $default;
		}

		if ( empty( $settings[ $name ] ) ) {
			return (
				$leer && ( '' === $settings[ $name ] ) ?
				'' :
				(
					$zero && ( '0' === $settings[ $name ] ) ?
					'0' :
					$default
				)
			);
		}

		$value = $settings[ $name ];

		if ( $num && ! is_numeric( $value ) ) {
			$value = str_replace( ',', '.', $value );

			if ( ! is_numeric( $value ) ) {
				return $default;
			}
		}

		if ( false !== $arr && ! in_array( $value, $arr, true ) ) {
			return $default;
		}

		$translatable_names = array(
			'f_dropdown_first_option_text',
			'f_title',
			'f_description',
			'f_custom_title',
			'f_search_label',
			'f_stock_statuses[in]',
			'f_stock_statuses[out]',
			'f_stock_statuses[on]',
			'f_add_text',
			'f_add_text5',
			'f_checkbox_label',
			'filtering_button_word',
			'show_clean_button_word',
			'hide_button_hide_text',
			'hide_button_show_text',
			'text_no_products',
			'overlay_word',
			'selected_clean_word',
		);
		if ( in_array( $name, $translatable_names, true ) ) {
			$value = woobewoo_pf_translate_string( $value );
		}


		return $value;
	}
}
