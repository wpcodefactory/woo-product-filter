<?php
class ReqWpf {
	protected static $_requestData;
	protected static $_requestMethod;
	public static $_requestWithNonce = false;

	public static function init() {
		add_filter('sanitize_text_field', array('ReqWpf', 'sanitizeData'), 999, 2);
	}

	public static function startSession() {

		if (!UtilsWpf::isSessionStarted()) {
			if (version_compare(phpversion(), '5.7.0', '<')) {
				session_start();
			} else {
				session_start(array('read_and_close' => true));
			}
		}
	}

	public static function endSession() {
		if ( UtilsWpf::isSessionStarted() ) {
			session_write_close();
		}
	}

	/**
	 * Function getVar.
	 *
	 * @param string $name key in variables array
	 * @param string $from from where get result = "all", "input", "get"
	 * @param mixed $default default value - will be returned if $name wasn't found
	 * @return mixed value of a variable, if didn't found - $default (NULL by default)
	*/
	public static function getVar( $name, $from = 'all', $default = null ) {
		if (self::$_requestWithNonce) {
			$nonce = empty($_REQUEST['_wpnonce']) ? '' : sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($nonce, 'my-nonce')) {
				echo esc_html__('Security check', 'woo-product-filter');
				exit(); 
			}
		}

		$from = strtolower($from);
		if ('all' == $from) {
			if (isset($_GET[$name])) {
				$from = 'get';
			} elseif (isset($_POST[$name])) {
				$from = 'post';
			}
		}

		switch ($from) {
			case 'get':
				if (isset($_GET[$name])) {
					return sanitize_text_field($_GET[$name]);
				}
				break;
			case 'post':
				if (isset($_POST[$name])) {
					return sanitize_text_field($_POST[$name]);
				}
				break;
			case 'file':
			case 'files':
				if (isset($_FILES[$name])) {
					return sanitize_text_field($_FILES[$name]);
				}
				break;
			case 'session':
				if (isset($_SESSION[$name])) {
					return sanitize_text_field($_SESSION[$name]);
				}
				break;
			case 'server':
				if (isset($_SERVER[$name])) {
					return sanitize_text_field($_SERVER[$name]);
				}
				break;
			case 'cookie':
				if (isset($_COOKIE[$name])) {
					$value = sanitize_text_field($_COOKIE[$name]);
					if (strpos($value, '_JSON:') === 0) {
						$value = explode('_JSON:', $value);
						$value = UtilsWpf::jsonDecode(array_pop($value));
					}
					return $value;
				}
				break;
		}
		return $default;
	}
	
	public static function existGetVar( $begin ) {
		if (isset($_GET) && is_array($_GET)) {
			foreach ($_GET as $k => $v) {
				if (strpos($k, $begin) === 0) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Getting similar parameters when redirecting to set filter values.
	 *
	 * @param string $part part of parameter
	 * @return string
	 */
	public static function getFilterRedirect( $part ) {
		$params = array();
		if (self::$_requestWithNonce) {
			$nonce = empty($_REQUEST['_wpnonce']) ? '' : sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($nonce, 'my-nonce')) {
				echo esc_html__('Security check', 'woo-product-filter');
				exit();
			}
		}
		if ( isset($_GET['redirect']) ) {
			foreach ( $_GET as $key => $value ) {
				if ( strpos ($key, $part) === 0 ) {
					$params[] = sanitize_text_field( $value );
				}
			}
		}

		return implode('|', $params);
	}

	public static function sanitizeData( $filtered, $value ) {
		return is_array($value) ? self::sanitizeArray($value) : $filtered;
	}
	public static function sanitizeArray( $arr ) {
		$newArr = array();
		foreach ($arr as $k => $v) {
			$newArr[$k] = is_array($v) ? self::sanitizeArray($v) : _sanitize_text_fields($v, false);
		}
		return $newArr;
	}
	public static function isEmpty( $name, $from = 'all' ) {
		$val = self::getVar($name, $from);
		return empty($val);
	}
	public static function setVar( $name, $val, $in = 'input', $params = array() ) {
		$in = strtolower($in);
		switch ($in) {
			case 'get':
				$_GET[$name] = $val;
				break;
			case 'post':
				$_POST[$name] = $val;
				break;
			case 'session':
				$_SESSION[$name] = $val;
				break;
			case 'cookie':
				$expire = isset($params['expire']) ? time() + $params['expire'] : 0;
				$path = isset($params['path']) ? $params['path'] : '/';
				if (is_array($val) || is_object($val)) {
					$saveVal = '_JSON:' . UtilsWpf::jsonEncode( $val );
				} else {
					$saveVal = $val;
				}
				setcookie($name, $saveVal, $expire, $path);
				break;
		}
	}
	public static function clearVar( $name, $in = 'input', $params = array() ) {
		if (self::$_requestWithNonce) {
			$nonce = empty($_REQUEST['_wpnonce']) ? '' : sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($nonce, 'my-nonce')) {
				esc_html__('Security check', 'woo-product-filter');
				exit(); 
			}
		}
		$in = strtolower($in);
		switch ($in) {
			case 'get':
				if (isset($_GET[$name])) {
					unset($_GET[$name]);
				}
				break;
			case 'post':
				if (isset($_POST[$name])) {
					unset($_POST[$name]);
				}
				break;
			case 'session':
				if (isset($_SESSION[$name])) {
					unset($_SESSION[$name]);
				}
				break;
			case 'cookie':
				$path = isset($params['path']) ? $params['path'] : '/';
				setcookie($name, '', time() - 3600, $path);
				break;
		}
	}
	public static function get( $what ) {
		if (self::$_requestWithNonce) {
			$nonce = empty($_REQUEST['_wpnonce']) ? '' : sanitize_text_field($_REQUEST['_wpnonce']);
			if (!wp_verify_nonce($nonce, 'my-nonce')) {
				esc_html__('Security check', 'woo-product-filter');
				exit(); 
			}
		}
		$what = strtolower($what);
		switch ($what) {
			case 'get':
				return $_GET;
				break;
			case 'post':
				return $_POST;
				break;
			case 'session':
				return $_SESSION;
				break;
			case 'files':
				return $_FILES;
				break;
		}
		return null;
	}
	public static function getMethod() {
		if (!self::$_requestMethod) {
			self::$_requestMethod = strtoupper( self::getVar('method', 'all', isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '') );
		}
		return self::$_requestMethod;
	}
	public static function getAdminPage() {
		$pagePath = self::getVar('page');
		if (!empty($pagePath) && strpos($pagePath, '/') !== false) {
			$pagePath = explode('/', $pagePath);
			return str_replace('.php', '', $pagePath[count($pagePath) - 1]);
		}
		return false;
	}
	public static function getRequestUri() {
		return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
	}
	public static function getMode() {
		$mod = self::getVar('mod');
		if (!$mod) {
			$mod = self::getVar('page');     //Admin usage
		}
		return $mod;
	}
}
