<?php
abstract class BaseObjectWpf {
	protected $_internalErrors = array();
	protected $_haveErrors = false;
	public function pushError( $error, $key = '' ) {
		if (is_array($error)) {
			$this->_internalErrors = array_merge ($this->_internalErrors, $error);
		} elseif (empty($key)) {
			$this->_internalErrors[] = $error;
		} else {
			$this->_internalErrors[ $key ] = $error;
		}
		$this->_haveErrors = true;
	}
	public function getErrors() {
		return $this->_internalErrors;
	}
	public function haveErrors() {
		return $this->_haveErrors;
	}

	/**
	 * Get settings in specific filter in filter block
	 *
	 * @param array $settings
	 * @param string $name
	 * @param mix $default
	 * @param bool $num
	 * @param array $arr Restriction list of setting value can be.
	 * @param bool $zero
	 *
	 * @return int|string
	 */
	public function getFilterSetting( $settings, $name, $default = '', $num = false, $arr = false, $zero = false, $leer = false ) {

		if ( ! isset( $settings[ $name ] ) ) {
			return $default;
		}

		if ( empty( $settings[ $name ] ) ) {
			return ( $leer && ( '' === $settings[ $name ] ) ? '' : ( $zero && ( '0' === $settings[ $name ] ) ? '0' : $default ) );
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

		return $value;
	}
}
