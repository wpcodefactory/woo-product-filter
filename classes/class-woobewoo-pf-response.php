<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Response Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Response {
	public $code     = 0;
	public $error    = false;
	public $errors   = array();
	public $messages = array();
	public $html     = '';
	public $data     = array();

	/**
	 * records.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 */
	public $records = array();

	/**
	 * rows.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 */
	public $rows = array();

	/**
	 * total.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 */
	public $total = 0;

	/**
	 * page.
	 *
	 * @version 3.3.0
	 * @since   3.3.0
	 */
	public $page = 1;

	/**
	 * Marker to set data not in internal $data var, but set it as object parameters
	 */
	private $_ignoreShellData = false;

	/**
	 * getReqType.
	 *
	 * @version 3.4.0
	 */
	public function getReqType() {
		return WooBeWoo_PF_Req::getVar( 'reqType' );
	}

	public function isAjax() {
		return $this->getReqType() == 'ajax';
	}

	/**
	 * ajaxExec.
	 *
	 * @version 3.4.0
	 */
	public function ajaxExec( $forceAjax = false ) {
		$isAjax   = $this->isAjax();
		$redirect = WooBeWoo_PF_Req::getVar( 'redirect' );
		if ( count( $this->errors ) > 0 ) {
			$this->error = true;
		}
		if ( $isAjax || $forceAjax ) {
			echo wp_json_encode( $this );
			WooBeWoo_PF_Req::endSession();
			exit();
		}

		return $this;
	}

	public function error() {
		return $this->error;
	}

	public function addError( $error, $key = '' ) {
		if ( empty( $error ) ) {
			return;
		}
		$this->error = true;
		if ( is_array( $error ) ) {
			$this->errors = array_merge( $this->errors, $error );
		} else {
			$m = '';
			if ( empty( $key ) ) {
				$this->errors[] = $error;
			} else {
				$this->errors[ $key ] = $error;
			}
		}
	}

	/**
	 * Alias for WooBeWoo_PF_Response::addError, @see addError method
	 */
	public function pushError( $error, $key = '' ) {
		return $this->addError( $error, $key );
	}

	public function addMessage( $msg ) {
		if ( empty( $msg ) ) {
			return;
		}
		if ( is_array( $msg ) ) {
			$this->messages = array_merge( $this->messages, $msg );
		} else {
			$this->messages[] = $msg;
		}
	}

	public function getMessages() {
		return $this->messages;
	}

	public function setHtml( $html ) {
		$this->html = $html;
	}

	public function addData( $data, $value = null ) {
		if ( empty( $data ) ) {
			return;
		}
		if ( $this->_ignoreShellData ) {
			if ( ! is_array( $data ) ) {
				$data = array( $data => $value );
			}
			foreach ( $data as $key => $val ) {
				$this->{$key} = $val;
			}
		} else {
			$m = '';
			if ( is_array( $data ) ) {
				$this->data = array_merge( $this->data, $data );
			} else {
				$this->data[ $data ] = $value;
			}
		}
	}

	public function getErrors() {
		return $this->errors;
	}

	public function ignoreShellData() {
		$this->_ignoreShellData = true;
	}
}
