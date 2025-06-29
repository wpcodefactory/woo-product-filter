<?php
class UtilsWpf {
	public static function jsonEncode( $arr ) {
		return ( is_array($arr) || is_object($arr) ) ? jsonEncodeUTFnormalWpf($arr) : jsonEncodeUTFnormalWpf(array());
	}
	public static function jsonDecode( $str ) {
		if (is_array($str)) {
			return $str;
		}
		if (is_object($str)) {
			return (array) $str;
		}
		return empty($str) ? array() : json_decode($str, true);
	}
	public static function unserialize( $data ) {
		return unserialize($data);
	}
	public static function serialize( $data ) {
		return serialize($data);
	}
	public static function createDir( $path, $params = array('chmod' => null, 'httpProtect' => false) ) {
		if (@mkdir($path)) {
			if (!is_null($params['chmod'])) {
				@chmod($path, $params['chmod']);
			}
			if (!empty($params['httpProtect'])) {
				self::httpProtectDir($path);
			}
			return true;
		}
		return false;
	}
	public static function httpProtectDir( $path ) {
		$content = 'DENY FROM ALL';
		if (strrpos($path, DS) != strlen($path)) {
			$path .= DS;
		}
		if (file_put_contents($path . '.htaccess', $content)) {
			return true;
		}
		return false;
	}
	/**
	 * Copy all files from one directory ($source) to another ($destination)
	 *
	 * @param string $source path to source directory
	 * @params string $destination path to destination directory
	 */
	public static function copyDirectories( $source, $destination ) {
		if (is_dir($source)) {
			@mkdir($destination);
			$directory = dir($source);
			while ( false !== ( $readdirectory = $directory->read() ) ) {
				if ( ( '.' == $readdirectory ) || ( '..' == $readdirectory ) ) {
					continue;
				}
				$PathDir = $source . '/' . $readdirectory; 
				if (is_dir($PathDir)) {
					self::copyDirectories( $PathDir, $destination . '/' . $readdirectory );
					continue;
				}
				copy( $PathDir, $destination . '/' . $readdirectory );
			}
			$directory->close();
		} else {
			copy( $source, $destination );
		}
	}
	public static function getIP() {
		$res = '';
		if (!isset($_SERVER['HTTP_CLIENT_IP']) || empty($_SERVER['HTTP_CLIENT_IP'])) {
			if (!isset($_SERVER['HTTP_X_REAL_IP']) || empty($_SERVER['HTTP_X_REAL_IP'])) {
				if (!isset($_SERVER['HTTP_X_SUCURI_CLIENTIP']) || empty($_SERVER['HTTP_X_SUCURI_CLIENTIP'])) {
					if (!isset($_SERVER['HTTP_X_FORWARDED_FOR']) || empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
						$res = empty($_SERVER['REMOTE_ADDR']) ? '' : sanitize_text_field($_SERVER['REMOTE_ADDR']);
					} else {
						$res = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
					}
				} else {
					$res = sanitize_text_field($_SERVER['HTTP_X_SUCURI_CLIENTIP']);
				}
			} else {
				$res = sanitize_text_field($_SERVER['HTTP_X_REAL_IP']);
			}
		} else {
			$res = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
		}
		
		return $res;
	}
	
	/**
	 * Parse xml file into simpleXML object
	 *
	 * @param string $path path to xml file
	 * @return mixed object SimpleXMLElement if success, else - false
	 */
	public static function getXml( $path ) {
		if (is_file($path)) {
			return simplexml_load_file($path);
		}
		return false;
	}
	/**
	 * Check if the element exists in array
	 *
	 * @param array $param 
	 */
	public static function xmlAttrToStr( $param, $element ) {
		if (isset($param[$element])) {
			// convert object element to string
			return (string) $param[$element];
		} else {
			return '';
		}
	}
	public static function xmlNodeAttrsToArr( $node ) {
		$arr = array();
		foreach ($node->attributes() as $a => $b) {
			$arr[$a] = self::xmlAttrToStr($node, $a);
		}
		return $arr;
	}
	public static function deleteFile( $str ) {
		return @unlink($str);
	}
	public static function deleteDir( $str ) {
		if (is_file($str)) {
			return self::deleteFile($str);
		} elseif (is_dir($str)) {
			$scan = glob(rtrim($str, '/') . '/*');
			foreach ($scan as $index => $path) {
				self::deleteDir($path);
			}
			return @rmdir($str);
		}
	}
	/**
	 * Retrives list of directories ()
	 */
	public static function getDirList( $path ) {
		$res = array();
		if (is_dir($path)) {
			$files = scandir($path);
			foreach ($files as $f) {
				if ( ( '.' == $f ) || ( '..' == $f ) || ( '.svn' == $f ) ) {
					continue;
				}
				if (!is_dir($path . $f)) {
					continue;
				}
				$res[$f] = array('path' => $path . $f . DS);
			}
		}
		return $res;
	}
	/**
	 * Retrives list of files
	 */
	public static function getFilesList( $path ) {
		$files = array();
		if (is_dir($path)) {
			$dirHandle = opendir($path);
			while ( ( $file = readdir($dirHandle) ) !== false ) {
				if ( ( '.' != $file ) && ( '..' != $file ) && ( '.svn' != $f ) && is_file($path . DS . $file) ) {
					$files[] = $file;
				}
			}
		}
		return $files;
	}
	/**
	 * Check if $var is object or something another in future
	 */
	public static function is( $var, $what = '' ) {
		if (!is_object($var)) {
			return false;
		}
		if (get_class($var) == $what) {
			return true;
		}
		return false;
	}
	/**
	 * Make replacement in $text, where it will be find all keys with prefix ":" and replace it with corresponding value
	 *
	 * @see email_templatesModel::renderContent()
	 * @see checkoutView::getSuccessPage()
	 */
	public static function makeVariablesReplacement( $text, $variables ) {
		if (!empty($text) && !empty($variables) && is_array($variables)) {
			foreach ($variables as $k => $v) {
				$text = str_replace(':' . $k, $v, $text);
			}
			return $text;
		}
		return false;
	}
	/**
	 * Retrive full directory of plugin
	 *
	 * @param string $name - plugin name
	 * @return string full path in file system to plugin directory
	 */
	public static function getPluginDir( $name = '' ) {
		return WP_PLUGIN_DIR . DS . $name . DS;
	}
	public static function getPluginPath( $name = '' ) {
		$path = plugins_url($name) . '/';
		if (substr($path, 0, 4) != 'http') {
			$home = home_url();
			if (is_ssl() && substr($home, 0, 5) != 'https') {
				$home = 'https' . substr($home, 4);
			}
			$path = $home . ( substr($path, 0, 1) == '/' ? '' : '/' ) . $path;
		}
		return $path;
	}
	public static function getExtModDir( $plugName ) {
		return self::getPluginDir($plugName);
	}
	public static function getExtModPath( $plugName ) {
		return self::getPluginPath($plugName);
	}
	public static function getCurrentWPThemePath() {
		return get_template_directory_uri();
	}
	public static function isThisCommercialEdition() {
		foreach (FrameWpf::_()->getModules() as $m) {
			if (is_object($m) && $m->isExternal()) {
				return true;
			}
		}
		return false;
	}
	public static function checkNum( $val, $default = 0 ) {
		if (!empty($val) && is_numeric($val)) {
			return $val;
		}
		return $default;
	}
	public static function checkString( $val, $default = '' ) {
		if (!empty($val) && is_string($val)) {
			return $val;
		}
		return $default;
	}
	/**
	 * Retrives extension of file
	 *
	 * @param string $path - path to a file
	 * @return string - file extension
	 */
	public static function getFileExt( $path ) {
		return strtolower( pathinfo($path, PATHINFO_EXTENSION) );
	}
	public static function getRandStr( $length = 10, $allowedChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890', $params = array() ) {
		$result = '';
		$allowedCharsLen = strlen($allowedChars);
		if (isset($params['only_lowercase']) && $params['only_lowercase']) {
			$allowedChars = strtolower($allowedChars);
		}
		while (strlen($result) < $length) {
			$result .= substr($allowedChars, rand(0, $allowedCharsLen), 1);
		}

		return $result;
	}
	/**
	 * Get current host location
	 *
	 * @return string host string
	 */
	public static function getHost() {
		return empty($_SERVER['HTTP_HOST']) ? '' : sanitize_text_field($_SERVER['HTTP_HOST']);
	}
	/**
	 * Check if device is mobile
	 *
	 * @return bool true if user are watching this site from mobile device
	 */
	public static function isMobile() {
		//importClassWpf('Mobile_Detect', WPF_HELPERS_DIR . 'mobileDetect.php');
		if (!class_exists('Mobile_Detect')) {
			if (file_exists(WPF_HELPERS_DIR . 'mobileDetect.php')) {
				require WPF_HELPERS_DIR . 'mobileDetect.php';
			}
		}
		$mobileDetect = new Mobile_Detect();
		return $mobileDetect->isMobile();
	}
	/**
	 * Check if device is tablet
	 *
	 * @return bool true if user are watching this site from tablet device
	 */
	public static function isTablet() {
		//importClassWpf('Mobile_Detect', WPF_HELPERS_DIR . 'mobileDetect.php');
		if (!class_exists('Mobile_Detect')) {
			if (file_exists(WPF_HELPERS_DIR . 'mobileDetect.php')) {
				require WPF_HELPERS_DIR . 'mobileDetect.php';
			}
		}
		$mobileDetect = new Mobile_Detect();
		return $mobileDetect->isTablet();
	}
	public static function getUploadsDir() {
		$uploadDir = wp_upload_dir();
		return $uploadDir['basedir'];
	}
	public static function getUploadsPath() {
		$uploadDir = wp_upload_dir();
		return $uploadDir['baseurl'];
	}
	public static function arrToCss( $data ) {
		$res = '';
		if (!empty($data)) {
			foreach ($data as $k => $v) {
				$res .= $k . ':' . $v . ';';
			}
		}
		return $res;
	}
	/**
	 * Activate all CSP Plugins
	 * 
	 * @return NULL Check if it's site or multisite and activate.
	 */
	public static function activatePlugin( $networkwide ) {
		global $wpdb;
		if (WPF_TEST_MODE) {
			add_action('activated_plugin', array(FrameWpf::_(), 'savePluginActivationErrors'));
		}
		if (function_exists('is_multisite') && is_multisite() && $networkwide) {
			$blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blog_id as $id) {
				if (switch_to_blog($id)) {
					InstallerWpf::init();
				} 
			}
			restore_current_blog();
			return;
		} else {
			InstallerWpf::init();
		}
	}

	/**
	 * Delete All CSP Plugins
	 * 
	 * @return NULL Check if it's site or multisite and decativate it.
	 */
	public static function deletePlugin() {
		global $wpdb;
		if (function_exists('is_multisite') && is_multisite()) {
			$blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blog_id as $id) {
				if (switch_to_blog($id)) {
					InstallerWpf::delete();
				} 
			}
			restore_current_blog();
			return;
		} else {
			InstallerWpf::delete();
		}
	}
	public static function deactivatePlugin( $networkwide ) {
		global $wpdb;
		if (function_exists('is_multisite') && is_multisite() && $networkwide) {
			$blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			foreach ($blog_id as $id) {
				if (switch_to_blog($id)) {
					InstallerWpf::deactivate();
				} 
			}
			restore_current_blog();
			return;
		} else {
			InstallerWpf::deactivate();
		}
	}
	public static function isWritable( $filename ) {
		return is_writable($filename);
	}
	
	public static function isReadable( $filename ) {
		return is_readable($filename);
	}
	
	public static function fileExists( $filename ) {
		return file_exists($filename);
	}
	public static function isPluginsPage() {
		return ( basename(ReqWpf::getVar('SCRIPT_NAME', 'server')) === 'plugins.php' );
	}
	public static function isSessionStarted() {
		if (version_compare(PHP_VERSION, '5.4.0') >= 0 && function_exists('session_status')) {
			return !( session_status() == PHP_SESSION_NONE );
		} else {
			return !( session_id() == '' );
		}
	}
	public static function generateBgStyle( $data ) {
		$stageBgStyles = array();
		$stageBgStyle = '';
		switch ($data['type']) {
			case 'color':
				$stageBgStyles[] = 'background-color: ' . $data['color'];
				$stageBgStyles[] = 'opacity: ' . $data['opacity'];
				break;
			case 'img':
				$stageBgStyles[] = 'background-image: url(' . $data['img'] . ')';
				switch ($data['img_pos']) {
					case 'center':
						$stageBgStyles[] = 'background-repeat: no-repeat';
						$stageBgStyles[] = 'background-position: center center';
						break;
					case 'tile':
						$stageBgStyles[] = 'background-repeat: repeat';
						break;
					case 'stretch':
						$stageBgStyles[] = 'background-repeat: no-repeat';
						$stageBgStyles[] = '-moz-background-size: 100% 100%';
						$stageBgStyles[] = '-webkit-background-size: 100% 100%';
						$stageBgStyles[] = '-o-background-size: 100% 100%';
						$stageBgStyles[] = 'background-size: 100% 100%';
						break;
				}
				break;
		}
		if (!empty($stageBgStyles)) {
			$stageBgStyle = implode(';', $stageBgStyles);
		}
		return $stageBgStyle;
	}
	/**
	 * Parse worwpfess post/page/custom post type content for images and return it's IDs if there are images
	 *
	 * @param string $content Post/page/custom post type content
	 * @return array List of images IDs from content
	 */
	public static function parseImgIds( $content ) {
		$res = array();
		preg_match_all('/wp-image-(?<ID>\d+)/', $content, $matches);
		if ($matches && isset($matches['ID']) && !empty($matches['ID'])) {
			$res = $matches['ID'];
		}
		return $res;
	}
	/**
	 * Retrive file path in file system from provided URL, it should be in wp-content/uploads
	 *
	 * @param string $url File url path, should be in wp-content/uploads
	 * @return string Path in file system to file
	 */
	public static function getUploadFilePathFromUrl( $url ) {
		$uploadsPath = self::getUploadsPath();
		$uploadsDir = self::getUploadsDir();
		return str_replace($uploadsPath, $uploadsDir, $url);
	}
	/**
	 * Retrive file URL from provided file system path, it should be in wp-content/uploads
	 *
	 * @param string $path File path, should be in wp-content/uploads
	 * @return string URL to file
	 */
	public static function getUploadUrlFromFilePath( $path ) {
		$uploadsPath = self::getUploadsPath();
		$uploadsDir = self::getUploadsDir();
		return str_replace($uploadsDir, $uploadsPath, $path);
	}
	public static function getUserBrowserString() {
		return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : false;
	}
	public static function getBrowser() {
		$u_agent = self::getUserBrowserString();
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version = '';
		$pattern = '';
		
		if ($u_agent) {
			//First get the platform?
			if (preg_match('/linux/i', $u_agent)) {
				$platform = 'linux';
			} elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
				$platform = 'mac';
			} elseif (preg_match('/windows|win32/i', $u_agent)) {
				$platform = 'windows';
			}
			// Next get the name of the useragent yes seperately and for good reason
			if ( ( preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent) ) || ( strpos($u_agent, 'Trident/7.0; rv:11.0') !== false ) ) {
				$bname = 'Internet Explorer';
				$ub = 'MSIE';
			} elseif (preg_match('/Firefox/i', $u_agent)) {
				$bname = 'Mozilla Firefox';
				$ub = 'Firefox';
			} elseif (preg_match('/Chrome/i', $u_agent)) {
				$bname = 'Google Chrome';
				$ub = 'Chrome';
			} elseif (preg_match('/Safari/i', $u_agent)) {
				$bname = 'Apple Safari';
				$ub = 'Safari';
			} elseif (preg_match('/Opera/i', $u_agent)) {
				$bname = 'Opera';
				$ub = 'Opera';
			} elseif (preg_match('/Netscape/i', $u_agent)) {
				$bname = 'Netscape';
				$ub = 'Netscape';
			}

			// finally get the correct version number
			$known = array('Version', $ub, 'other');
			$pattern = '#(?<browser>' . join('|', $known) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';

			// see how many we have
			$i = count($matches['browser']);
			if (1 != $i) {
				//we will have two since we are not using 'other' argument yet
				//see if version is before or after the name
				if ( strripos($u_agent, 'Version') < strripos($u_agent, $ub) ) {
					$version = $matches['version'][0];
				} else {
					$version = $matches['version'][1];
				}
			} else {
				$version = $matches['version'][0];
			}
		}

		// check if we have a number
		if ( ( null == $version ) || ( '' == $version ) ) {
			$version = '?';
		}

		return array(
			'userAgent' => $u_agent,
			'name'      => $bname,
			'version'   => $version,
			'platform'  => $platform,
			'pattern'    => $pattern,
		);
	}
	public static function getBrowsersList() {
		return array('Unknown', 'Internet Explorer', 'Mozilla Firefox', 'Google Chrome', 'Apple Safari', 'Opera', 'Netscape');
	}
	public static function getLangCode2Letter() {
		$langCode = self::getLangCode();
		return strlen($langCode) > 2 ? substr($langCode, 0, 2) : $langCode;
	}
	public static function getLangCode() {
		return get_locale();
	}
	public static function getBrowserLangCode() {
		return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])
			? strtolower(substr(sanitize_text_field($_SERVER['HTTP_ACCEPT_LANGUAGE']), 0, 2))
			: self::getLangCode2Letter();
	}
	public static function getTimeRange() {
		$time = array();
		$hours = range(1, 11);
		array_unshift($hours, 12);
		$k = 0;
		$count = count($hours);
		for ($i = 0; $i < 4 * $count; $i++) {
			$newItem = $hours[ $k ];
			$newItem .= ':' . ( ( $i % 2 ) ? '30' : '00' );
			$newItem .= ( $i < $count * 2 ) ? 'am' : 'pm';
			if ($i % 2) {
				$k++;
			}
			if ($i == $count * 2 - 1) {
				$k = 0;
			}
			$time[] = $newItem;
		}
		return array_combine($time, $time);
	}
	public static function getSearchEnginesList() {
		return array(
			'google.com' => array('label' => 'Google'),
			'yahoo.com' => array('label' => 'Yahoo!'),
			'youdao.com' => array('label' => 'Youdao'),
			'yandex' => array('label' => 'Yandex'),
			'sogou.com' => array('label' => 'Sogou'),
			'qwant.com' => array('label' => 'Qwant'),
			'bing.com' => array('label' => 'Bing'),
			'munax.com' => array('label' => 'Munax'),
		);
	}
	public static function getSocialList() {
		return array(
			'facebook.com' => array('label' => 'Facebook'),
			'pinterest.com' => array('label' => 'Pinterest'),
			'instagram.com' => array('label' => 'Instagram'),
			'yelp.com' => array('label' => 'Yelp'),
			'vk.com' => array('label' => 'VKontakte'),
			'myspace.com' => array('label' => 'Myspace'),
			'linkedin.com' => array('label' => 'LinkedIn'),
			'plus.google.com' => array('label' => 'Google+'),
			'google.com' => array('label' => 'Google'),
		);
	}
	public static function getReferalUrl() {
		// Simple for now
		return ReqWpf::getVar('HTTP_REFERER', 'server');
	}
	public static function getReferalHost() {
		$refUrl = self::getReferalUrl();
		if (!empty($refUrl)) {
			$refer = parse_url( $refUrl );
			if ($refer && isset($refer['host']) && !empty($refer['host'])) {
				return $refer['host'];
			}
		}
		return false;
	}
	public static function getCurrentUserRole() {
		$roles = self::getCurrentUserRoleList();
		if ($roles) {
			$ncaps = count($roles);
			$role = $roles[$ncaps - 1];
			return $role;
		}
		return false;
	}
	public static function getCurrentUserRoleList() {
		global $current_user, $wpdb;
		if ($current_user) {
			$roleKey = $wpdb->prefix . 'capabilities';
			if (isset($current_user->$roleKey) && !empty($current_user->$roleKey)) {
				return array_keys($current_user->$roleKey);
			}
		}
		return false;
	}
	public static function getAllUserRoles() {
		return get_editable_roles();
	}
	public static function getAllUserRolesList() {
		$res = array();
		$roles = self::getAllUserRoles();
		if (!empty($roles)) {
			foreach ($roles as $k => $data) {
				$res[ $k ] = $data['name'];
			}
		}
		return $res;
	}
	public static function rgbToArray( $rgb ) {
		$rgb = array_map('trim', explode(',', trim(str_replace(array('rgb', 'a', '(', ')'), '', $rgb))));
		return $rgb;
	}
	public static function hexToRgb( $hex ) {
		if (strpos($hex, 'rgb') !== false) { // Maybe it's already in rgb format - just return it as array
			return self::rgbToArray($hex);
		}
		$hex = str_replace('#', '', $hex);

		if (strlen($hex) == 3) {
			$r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
			$g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
			$b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
		} else {
			$r = hexdec(substr($hex, 0, 2));
			$g = hexdec(substr($hex, 2, 2));
			$b = hexdec(substr($hex, 4, 2));
		}
		$rgb = array($r, $g, $b);
		return $rgb; // returns an array with the rgb values
	}
	public static function hexToRgbaStr( $hex, $alpha = 1 ) {
		$rgbArr = self::hexToRgb($hex);
		return 'rgba(' . implode(',', $rgbArr) . ',' . $alpha . ')';
	}
	public static function controlNumericValues( $values, $field ) {
		foreach ($values as $k => $val) {
			$values[$k] = ( 'dec' == $field ? (float) $val : (int) $val );
		}
		return $values;
	}
}
