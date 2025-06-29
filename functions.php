<?php
/**
 * Set first leter in a string as UPPERCASE
 *
 * @param string $str string to modify
 * @return string string with first Uppercase letter
 */
if (!function_exists('strFirstUpWpf')) {
	function strFirstUpWpf( $str ) {
		return strtoupper(substr($str, 0, 1)) . strtolower(substr($str, 1, strlen($str)));
	}
}
/**
 * Deprecated - class must be created
 */
if (!function_exists('dateToTimestampWpf')) {
	function dateToTimestampWpf( $date ) {
		if (empty($a)) {
			return false;
		}
		$a = explode(WPF_DATE_DL, $date);
		return mktime(0, 0, 0, $a[1], $a[0], $a[2]);
	}
}
/**
 * Generate random string name
 *
 * @param int $lenFrom min len
 * @param int $lenTo max len
 * @return string random string with length from $lenFrom to $lenTo
 */
if (!function_exists('getRandNameWpf')) {
	function getRandNameWpf( $lenFrom = 6, $lenTo = 9 ) {
		$res = '';
		$len = mt_rand($lenFrom, $lenTo);
		if ($len) {
			for ($i = 0; $i < $len; $i++) {
				$res .= chr(mt_rand(97, 122)); /*rand symbol from a to z*/
			}
		}
		return $res;
	}
}

if (!function_exists('setDefaultParamsWpf')) {
	function setDefaultParamsWpf( $params, $default ) {
		foreach ($default as $k => $v) {
			$params[$k] = isset($params[$k]) ? $params[$k] : $default[$k];
		}
		return $params;
	}
}
if (!function_exists('importClassWpf')) {
	function importClassWpf( $class, $path = '' ) {
		if (!class_exists($class)) {
			$classFile = lcfirst($class);
			if (strpos(strtolower($classFile), WPF_CODE) !== false) {
				$classFile = preg_replace('/' . WPF_CODE . '/i', '', $classFile);
			}
			$path = WPF_CLASSES_DIR . $classFile . '.php';
			if (file_exists($path)) {
				require WPF_CLASSES_DIR . $classFile . '.php';
				return true;
			}
			//return importWpf($path);
		}
		return false;
	}
}
/**
 * Check if class name exist with prefix or not
 *
 * @param strin $class preferred class name
 * @return string existing class name
 */
if (!function_exists('toeGetClassNameWpf')) {
	function toeGetClassNameWpf( $class ) {
		$className = '';
		if (class_exists($class . strFirstUpWpf(WPF_CODE))) {
			$className = $class . strFirstUpWpf(WPF_CODE);
		} else if (class_exists(WPF_CLASS_PREFIX . $class)) {
			$className = WPF_CLASS_PREFIX . $class;
		} else {
			$className = $class;
		}
		return $className;
	}
}
/**
 * Create object of specified class
 *
 * @param string $class class that you want to create
 * @param array $params array of arguments for class __construct function
 * @return object new object of specified class
 */
if (!function_exists('toeCreateObjWpf')) {
	function toeCreateObjWpf( $class, $params ) {
		$className = toeGetClassNameWpf($class);
		$obj = null;
		if (class_exists('ReflectionClass')) {
			$reflection = new ReflectionClass($className);
			try {
				$obj = $reflection->newInstanceArgs($params);
			} catch (ReflectionException $e) { // If class have no constructor
				$obj = $reflection->newInstanceArgs();
			}
		} else {
			$obj = new $className();
			call_user_func_array(array($obj, '__construct'), $params);
		}
		return $obj;
	}
}
/**
 * Redirect user to specified location. Be advised that it should redirect even if headers alredy sent.
 *
 * @param string $url where page must be redirected
 */
if (!function_exists('redirectWpf')) {
	function redirectWpf( $url ) {
		if (headers_sent()) {
			echo '<script type="text/javascript"> document.location.href = "' . esc_url($url) . '"; </script>';
		} else {
			header('Location: ' . $url);
		}
		exit();
	}
}
if (!function_exists('jsonEncodeUTFnormalWpf')) {
	function jsonEncodeUTFnormalWpf( $value ) {
		if (is_int($value)) {
			return (string) $value;   
		} elseif (is_string($value)) {
			$value = str_replace(array('\\', '/', '"', "\r", "\n", "\b", "\f", "\t"), 
								 array('\\\\', '\/', '\"', '\r', '\n', '\b', '\f', '\t'), $value);
			$convmap = array(0x80, 0xFFFF, 0, 0xFFFF);
			$result = '';
			for ($i = strlen($value) - 1; $i >= 0; $i--) {
				$mb_char = substr($value, $i, 1);
				$result = $mb_char . $result;
			}
			return '"' . $result . '"';                
		} elseif (is_float($value)) {
			return str_replace(',', '.', $value);         
		} elseif (is_null($value)) {
			return 'null';
		} elseif (is_bool($value)) {
			return $value ? 'true' : 'false';
		} elseif (is_array($value)) {
			$with_keys = false;
			$n = count($value);
			for ($i = 0, reset($value); $i < $n; $i++, next($value)) {
				if (key($value) !== $i) {
					$with_keys = true;
					break;
				}
			}
		} elseif (is_object($value)) {
			$with_keys = true;
		} else {
			return '';
		}
		$result = array();
		if ($with_keys) {
			foreach ($value as $key => $v) {
				$result[] = jsonEncodeUTFnormalWpf((string) $key) . ':' . jsonEncodeUTFnormalWpf($v);    
			}
			return '{' . implode(',', $result) . '}';                
		} else {
			foreach ($value as $key => $v) {
				$result[] = jsonEncodeUTFnormalWpf($v);    
			}
			return '[' . implode(',', $result) . ']';
		}
	} 
}
/**
 * Prepares the params values to store into db
 * 
 * @param array $d $_POST array
 * @return array
 */
if (!function_exists('prepareParamsWpf')) {
	function prepareParamsWpf( &$d = array(), &$options = array() ) {
		if (!empty($d['params'])) {
			if (isset($d['params']['options'])) {
				$options = $d['params']['options'];
			}
			if (is_array($d['params'])) {
				$params = UtilsWpf::jsonEncode($d['params']);
				$params = str_replace(array('\n\r', "\n\r", '\n', "\r", '\r', "\r"), '<br />', $params);
				$params = str_replace(array('<br /><br />', '<br /><br /><br />'), '<br />', $params);
				$d['params'] = $params;
			}
		} elseif (isset($d['params'])) {
			$d['params']['attr']['class'] = '';
			$d['params']['attr']['id'] = '';
			$params = UtilsWpf::jsonEncode($d['params']);
			$d['params'] = $params;
		}
		if (empty($options)) {
			$options = array('value' => array('EMPTY'), 'data' => array());
		}
		if (isset($d['code'])) {
			if ('' == $d['code']) {
				$d['code'] = prepareFieldCodeWpf($d['label']) . '_' . rand(0, 9999999);
			}
		}
		return $d;
	}
}
if (!function_exists('prepareFieldCodeWpf')) {
	function prepareFieldCodeWpf( $string ) {   
		$string = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $string);
		$string = preg_replace('/\s+/', ' ', $string);
		$string = preg_replace('/ /', '', $string);

		$code = substr($string, 0, 8);
		$code = strtolower($code);
		if ('' == $code) {
			$code = 'field_' . gmdate('dhis');
		}
		return $code;
	}
}
/**
 * Recursive implode of array
 *
 * @param string $glue imploder
 * @param array $array array to implode
 * @return string imploded array in string
 */
if (!function_exists('recImplodeWpf')) {
	function recImplodeWpf( $glue, $array ) {
		$res = '';
		$i = 0;
		$count = count($array);
		foreach ($array as $el) {
			$str = '';
			if (is_array($el)) {
				$str = recImplodeWpf('', $el);
			} else {
				$str = $el;
			}
			$res .= $str;
			if ($i < ( $count-1 )) {
				$res .= $glue;
			}
			$i++;
		}
		return $res;
	}
}
/**
 * Twig require this function, but it is present not on all servers
 */
if (!function_exists('hash')) {
	function hash( $method, $data ) {
		return md5($data);
	}
}
if (!function_exists('ctype_alpha')) {
	function ctype_alpha( $text ) {
		return (bool) preg_match('/[^\pL]+/', $text);
	}
}

if ( ! function_exists( 'trueRequestWpf' ) ) {
	function trueRequestWpf() {
		$request = true;
		$uri     = ( isset( $_SERVER['REQUEST_URI'] ) && '' !== $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '';

		if ( '' === $uri ) {
			$request = false;
		} else {
			preg_match( '/\.png$|\.jpg$|\.ico$/', $uri, $matches );
			if ( ! empty( $matches ) ) {
				$request = false;
			}
		}

		return $request;
	}
}
add_action('admin_notices', 'woofilterInstallBaseMsg');
if (!function_exists('woofilterInstallBaseMsg')) {
	function woofilterInstallBaseMsg() {
		if ( class_exists('FrameWpf') ) {
			if ( !FrameWpf::_()->proVersionCompare(WPF_PRO_REQUIRES, '>=') ) {
				$plugName = __('Product Filter by WBW', 'woo-product-filter');
				$plugWpUrl = 'https://wordpress.org/plugins/woo-product-filter/';
				echo '<div class="notice error is-dismissible"><p><strong>';
				/* translators: 1: plugin name 2: plugin version */
				echo sprintf(esc_html__('Please install latest PRO version of %1$s plugin (requires at least %2$s). ', 'woo-product-filter'), esc_html($plugName), esc_html(WPF_PRO_REQUIRES));
				/* translators: %s: plugin name */
				echo sprintf(esc_html__('In this way you will have full and upgraded PRO version of %s.', 'woo-product-filter'), esc_html($plugName)) .
					'</strong></p></div>';
			} else if (FrameWpf::_()->getModule('options')->getModel()->get('start_indexing') == 2) {
				$plugName = __('Product Filter by WBW', 'woo-product-filter');
				$plugWpUrl = 'https://wordpress.org/plugins/woo-product-filter/';
				echo '<div class="notice error is-dismissible"><p><strong>';
				/* translators: %s: plugin name */
				echo sprintf(esc_html__('The plugin %s started indexing the product database metadata. If you have a large database, this may take a while, but in the future it will significantly increase your filtering speed.', 'woo-product-filter'), esc_html($plugName)) .
					'</strong></p></div>';
			} else {
				FrameWpf::_()->getModule('overview')->getView()->showRestApiInfo();
			}
		}
	}
}
add_action( 'admin_init', 'woofilterProDeactivate' );
if (!function_exists('woofilterProDeactivate')) {
	function woofilterProDeactivate() {
		if (class_exists('FrameWpf') && function_exists('getProPlugFullPathWpf')) {
			$pathPro = getProPlugFullPathWpf();
			$proPlugin = plugin_basename($pathPro);
			if (is_plugin_active($proPlugin)) {
				$pluginData = get_file_data( $pathPro, array( 'Version' => 'Version' ) );
				$isProActive = FrameWpf::_()->moduleActive('access');
				if ( !version_compare($pluginData['Version'], WPF_PRO_REQUIRES, '>=') ) { 
					//deactivate_plugins($proPlugin);
					if ($isProActive) {
						call_user_func_array(array('ModInstallerWpf', 'deactivate'), array(array('license')));
					}
				} elseif (!$isProActive) {
					call_user_func_array(array('ModInstallerWpf', 'activate'), array(true));
				}
			}
		}
	}
}

if (!function_exists('wpf_translate_string')) {
	function wpf_translate_string( $value, $name = '', $context = 'woo-product-filter' ) {
		if (function_exists('icl_register_string')) {
			return icl_register_string($context, $name, $value);
		}
	}
}
