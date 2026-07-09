<?php
/**
 * Product Filter by WBW - ReqWpf Class
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class ReqWpf {

	protected static $_requestData;

	protected static $_requestMethod;

	public static $_requestWithNonce = false;

	/**
	 * verifyRequestNonce.
	 *
	 * @version 3.1.9
	 * @since   3.1.9
	 *
	 * @return void
	 */
	protected static function verifyRequestNonce() {
		$nonce = empty($_REQUEST['wpfNonce']) ? '' : sanitize_text_field(wp_unslash($_REQUEST['wpfNonce']));
		if (empty($nonce) && !empty($_REQUEST['_wpnonce'])) {
			$nonce = sanitize_text_field(wp_unslash($_REQUEST['_wpnonce']));
		}
		if (!wp_verify_nonce($nonce, 'wpf-save-nonce')) {
			echo esc_html__('Security check', 'woo-product-filter');
			exit();
		}
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
	 * @version 3.1.9
	 *
	 * @param string $name key in variables array
	 * @param string $from from where get result = "all", "input", "get"
	 * @param mixed $default default value - will be returned if $name wasn't found
	 *
	 * @return mixed value of a variable, if didn't found - $default (NULL by default)
	*/
	public static function getVar( $name, $from = 'all', $default = null ) {
		if (self::$_requestWithNonce) {
			self::verifyRequestNonce();
		}

		$from = strtolower($from);
		if ('all' == $from) {
			if (isset($_GET[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$from = 'get';
			} elseif (isset($_POST[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$from = 'post';
			}
		}

		switch ($from) {
			case 'get':
				if (isset($_GET[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return self::sanitizeValue(wp_unslash($_GET[$name])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
				break;
			case 'post':
				if (isset($_POST[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					return self::sanitizeValue(wp_unslash($_POST[$name])); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
				break;
			case 'file':
			case 'files':
				if (isset($_FILES[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					return self::sanitizeValue($_FILES[$name]); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
				break;
			case 'session':
				if (isset($_SESSION[$name])) {
					return self::sanitizeValue($_SESSION[$name]); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
				break;
			case 'server':
				if (isset($_SERVER[$name])) {
					return self::sanitizeValue(wp_unslash($_SERVER[$name])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				}
				break;
			case 'cookie':
				if (isset($_COOKIE[$name])) {
					$value = self::sanitizeValue(wp_unslash($_COOKIE[$name])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
		if (isset($_GET) && is_array($_GET)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ($_GET as $k => $v) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	 * @version 3.1.9
	 *
	 * @param string $part part of parameter
	 *
	 * @return string
	 */
	public static function getFilterRedirect( $part ) {
		$params = array();
		if (self::$_requestWithNonce) {
			self::verifyRequestNonce();
		}
		if ( isset($_GET['redirect']) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( strpos ($key, $part) === 0 ) {
					$params[] = self::sanitizeValue( $value );
				}
			}
		}

		return implode('|', $params);
	}

	/**
	 * sanitizeValue.
	 *
	 * Sanitizes a value without altering global sanitize_text_field behavior.
	 * Handles arrays recursively via sanitizeArray().
	 *
	 * @version 3.1.9
	 * @since   3.1.9
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	private static function sanitizeValue( $value ) {
		return is_array( $value ) ? self::sanitizeArray( $value ) : sanitize_text_field( $value );
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

	/**
	 * clearVar.
	 *
	 * @version 3.1.9
	 */
	public static function clearVar( $name, $in = 'input', $params = array() ) {
		if (self::$_requestWithNonce) {
			self::verifyRequestNonce();
		}
		$in = strtolower($in);
		switch ($in) {
			case 'get':
				if (isset($_GET[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					unset($_GET[$name]);
				}
				break;
			case 'post':
				if (isset($_POST[$name])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
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

	/**
	 * get.
	 *
	 * @version 3.1.9
	 */
	public static function get( $what ) {
		if (self::$_requestWithNonce) {
			self::verifyRequestNonce();
		}
		$what = strtolower($what);
		switch ($what) {
			case 'get':
				return self::sanitizeArray(wp_unslash($_GET)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			case 'post':
				return self::sanitizeArray(wp_unslash($_POST)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
			case 'session':
				return isset($_SESSION) ? self::sanitizeArray($_SESSION) : array();
			case 'files':
				return self::sanitizeFiles($_FILES); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return null;
	}

	/**
	 * sanitizeFiles.
	 *
	 * Sanitizes a $_FILES array: file names and MIME types are cleaned;
	 * tmp_name is server-generated and left as-is.
	 *
	 * @version 3.1.9
	 * @since   3.1.9
	 *
	 * @param array $files Raw $_FILES array.
	 *
	 * @return array
	 */
	private static function sanitizeFiles( $files ) {
		$clean = array();
		foreach ( $files as $key => $file ) {
			if ( is_array($file) && isset($file['name']) ) {
				$clean[$key] = array(
					'name'     => sanitize_file_name( $file['name'] ),
					'type'     => sanitize_mime_type( $file['type'] ),
					'tmp_name' => $file['tmp_name'],
					'size'     => absint( $file['size'] ),
					'error'    => absint( $file['error'] ),
				);
			} else {
				$clean[$key] = $file;
			}
		}
		return $clean;
	}

	/**
	 * getMethod.
	 *
	 * @version 3.1.8
	 */
	public static function getMethod() {
		if (!self::$_requestMethod) {
			self::$_requestMethod = strtoupper(
				self::getVar(
					'method',
					'all',
					(
						isset($_SERVER['REQUEST_METHOD'])
						? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
						: ''
					)
				)
			);
		}
		return self::$_requestMethod;
	}

	/**
	 * getAdminPage.
	 */
	public static function getAdminPage() {
		$pagePath = self::getVar('page');
		if (!empty($pagePath) && strpos($pagePath, '/') !== false) {
			$pagePath = explode('/', $pagePath);
			return str_replace('.php', '', $pagePath[count($pagePath) - 1]);
		}
		return false;
	}

	/**
	 * getMethod.
	 *
	 * @version 3.1.8
	 */
	public static function getRequestUri() {
		return (
			isset($_SERVER['REQUEST_URI'])
			? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))
			: ''
		);
	}

	/**
	 * getMode.
	 */
	public static function getMode() {
		$mod = self::getVar('mod');
		if (!$mod) {
			$mod = self::getVar('page'); // Admin usage
		}
		return $mod;
	}
}
