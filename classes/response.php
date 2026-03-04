<?php
#[\AllowDynamicProperties]
class ResponseWpf {
	public $code = 0;
	public $error = false;
	public $errors = array();
	public $messages = array();
	public $html = '';
	public $data = array();
	/**
	 * Marker to set data not in internal $data var, but set it as object parameters
	 */
	private $_ignoreShellData = false;
	public function getReqType() {
		return ReqWpf::getVar('reqType');
	}
	public function isAjax() {
		return $this->getReqType() == 'ajax';
	}
	public function ajaxExec( $forceAjax = false ) {
		$isAjax = $this->isAjax();
		$redirect = ReqWpf::getVar('redirect');
		if (count($this->errors) > 0) {
			$this->error = true;
		}
		if ( $isAjax || $forceAjax ) {
			HtmlWpf::echoEscapedHtml( jsonEncodeUTFnormalWpf( $this ) );
			ReqWpf::endSession();
			exit();
		}
		return $this;
	}
	public function mainRedirect( $redirectUrl = '' ) {
		$redirectUrl = empty($redirectUrl) ? WPF_SITE_URL : $redirectUrl;
		$redirectData = array();
		if (!empty($this->errors)) {
			$redirectData['wpfErrors'] = $this->errors;
		}
		if (!empty($this->messages)) {
			$redirectData['wpfMsgs'] = $this->messages;
		}
		return redirectWpf( $redirectUrl . ( strpos($redirectUrl, '?') ? '&' : '?' ) . http_build_query($redirectData) );
	}
	public function error() {
		return $this->error;
	}
	public function addError( $error, $key = '' ) {
		if (empty($error)) {
			return;
		}
		$this->error = true;
		if (is_array($error)) {
			$this->errors = array_merge($this->errors, $error);
		} else {
			$m = '';
			if (empty($key)) {
				$this->errors[] = $error;
			} else {
				$this->errors[$key] = $error;
			}
		}
	}
	/**
	 * Alias for ResponseWpf::addError, @see addError method
	 */
	public function pushError( $error, $key = '' ) {
		return $this->addError($error, $key);
	}
	public function addMessage( $msg ) {
		if (empty($msg)) {
			return;
		}
		if (is_array($msg)) {
			$this->messages = array_merge($this->messages, $msg);
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
		if (empty($data)) {
			return;
		}
		if ($this->_ignoreShellData) {
			if (!is_array($data)) {
				$data = array($data => $value);
			}
			foreach ($data as $key => $val) {
				$this->{$key} = $val;
			}
		} else {
			$m = '';
			if (is_array($data)) {
				$this->data = array_merge($this->data, $data);
			} else {
				$this->data[$data] = $value;
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
