<?php
/**
 * Product Filter by WBW - HelperWpf Class
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

abstract class HelperWpf {
	protected $_code = '';
	protected $_module = '';
	/**
	 * Construct helper class
	 *
	 * @param string $code 
	 */
	public function __construct( $code ) {
		$this->setCode($code);
	}
	/**
	 * Init function
	 */
	public function init() {
	}
	/**
	 * Set the helper name
	 *
	 * @param string $code 
	 */
	public function setCode( $code ) {
		$this->_code = $code;
	}
	/**
	 * Get the helper name
	 *
	 * @return string 
	 */
	public function getCode() {
		return $this->_code;
	}
}
