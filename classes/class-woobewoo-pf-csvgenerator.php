<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Csvgenerator Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Csvgenerator {
	protected $_filename  = '';
	protected $_delimiter = ';';
	protected $_enclosure = "\n";
	protected $_data      = array();
	protected $_escape    = '\\';
	public function __construct( $filename ) {
		$this->_filename = $filename;
	}
	public function addCell( $x, $y, $value ) {
		$this->_data[ $x ][ $y ] = '"' . $value . '"'; // If will not do "" then symbol for example, will broke file
	}
	/**
	 * generate.
	 *
	 * @version 3.4.0
	 */
	public function generate() {
		$strData = '';
		if ( ! empty( $this->_data ) ) {
			$rows = array();
			foreach ( $this->_data as $cells ) {
				$rows[] = implode( $this->_delimiter, $cells );
			}
			$strData = implode( $this->_enclosure, $rows );
		}
		WooBeWoo_PF_File_Generator::_( $this->_filename, $strData, 'csv' )->generate();
	}
	public function getDelimiter() {
		return $this->_delimiter;
	}
	public function getEnclosure() {
		return $this->_enclosure;
	}
	public function getEscape() {
		return $this->_escape;
	}
}
