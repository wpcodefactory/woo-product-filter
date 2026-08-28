<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_File_Generator Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_File_Generator {
	protected static $_instances = array();
	protected $_filename         = '';
	protected $_data             = '';
	protected $_type             = '';
	public function __construct( $filename, $data, $type ) {
		$this->_filename = $filename;
		$this->_data     = $data;
		$this->_type     = strtolower( $type );
	}
	/**
	 * getInstance.
	 *
	 * @version 3.4.0
	 */
	public static function getInstance( $filename, $data, $type ) {
		$name = md5( $filename . $data . $type );
		if ( ! isset( self::$_instances[ $name ] ) ) {
			self::$_instances[ $name ] = new WooBeWoo_PF_File_Generator( $filename, $data, $type );
		}
		return self::$_instances[ $name ];
	}
	public static function _( $filename, $data, $type ) {
		return self::getInstance( $filename, $data, $type );
	}

	/**
	 * generate.
	 *
	 * @version 3.4.0
	 */
	public function generate() {
		switch ( $this->_type ) {
			case 'txt':
				$this->_getTxtHeader();
				break;
			case 'csv':
				$this->_getCsvHeader();
				break;
			default:
				$this->_getDefaultHeader();
				break;
		}
		WooBeWoo_PF_Html::echoEscapedHtml( $this->_data );
		exit();
	}
	protected function _getTxtHeader() {
		header( 'Content-Disposition: attachment; filename="' . $this->_filename . '.txt"' );
		header( 'Content-type: text/plain' );
	}
	protected function _getCsvHeader() {
		header( 'Content-Disposition: attachment; filename="' . $this->_filename . '.csv"' );
		header( 'Content-type: application/csv' );
	}
	protected function _getDefaultHeader() {
		header( 'Content-Disposition: attachment; filename="' . $this->_filename . '"' );
		header( 'Content-type: ' . $this->_type );
	}
}
