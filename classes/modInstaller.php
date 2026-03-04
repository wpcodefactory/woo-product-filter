<?php
class ModInstallerWpf {
	private static $_current = array();
	private static $extPlugName = '';
	/**
	 * Install new ModuleWpf into plugin
	 *
	 * @param string $module new ModuleWpf data (@see classes/tables/modules.php)
	 * @param string $path path to the main plugin file from what module is installed
	 * @return bool true - if install success, else - false
	 */
	public static function install( $module, $path ) {
		$exPlugDest = explode('plugins', $path);
		if (!empty($exPlugDest[1])) {
			$module['ex_plug_dir'] = str_replace(DS, '', $exPlugDest[1]);
		}
		$path = $path . DS . $module['code'];
		if (!empty($module) && !empty($path) && is_dir($path)) {
			if (self::isModule($path)) {
				$filesMoved = false;
				if (empty($module['ex_plug_dir'])) {
					$filesMoved = self::moveFiles($module['code'], $path);
				} else {
					$filesMoved = true;     //Those modules doesn't need to move their files
				}
				if ($filesMoved) {
					if (FrameWpf::_()->getTable('modules')->exists($module['code'], 'code')) {
						FrameWpf::_()->getTable('modules')->delete(array('code' => $module['code']));
					}
					if ('license' != $module['code']) {
						$module['active'] = FrameWpf::_()->getTable('modules')->get('active', array('code' => 'access'), '', 'one' ) == 1 ? 1 : 0;
					}
					FrameWpf::_()->getTable('modules')->insert($module);
					self::_runModuleInstall($module);
					self::_installTables($module);
					return true;
				} else {
					/* translators: %s: module name */
					ErrorsWpf::push(esc_html(sprintf(__('Move files for %s failed'), $module['code'])), ErrorsWpf::MOD_INSTALL);
				}
			} else {
				/* translators: %s: module name */
				ErrorsWpf::push(esc_html(sprintf(__('%s is not plugin module'), $module['code'])), ErrorsWpf::MOD_INSTALL);
			}
		}
		return false;
	}
	protected static function _runModuleInstall( $module, $action = 'install' ) {
		$moduleLocationDir = WPF_MODULES_DIR;
		if (!empty($module['ex_plug_dir'])) {
			$moduleLocationDir = UtilsWpf::getPluginDir( $module['ex_plug_dir'] );
		}
		if (is_dir($moduleLocationDir . $module['code'])) {
			if (!class_exists($module['code'] . strFirstUpWpf(WPF_CODE))) {
				//importClassWpf($module['code'] . strFirstUpWpf(WPF_CODE), $moduleLocationDir . $module['code'] . DS . 'mod.php');
				if (file_exists($moduleLocationDir . $module['code'] . DS . 'mod.php')) {
					require $moduleLocationDir . $module['code'] . DS . 'mod.php';
				}
			}
			$moduleClass = toeGetClassNameWpf($module['code']);
			$moduleObj = new $moduleClass($module);
			if ($moduleObj) {
				$moduleObj->$action();
			}
		}
	}
	/**
	 * Check whether is or no module in given path
	 *
	 * @param string $path path to the module
	 * @return bool true if it is module, else - false
	 */
	public static function isModule( $path ) {
		return true;
	}
	/**
	 * Move files to plugin modules directory
	 *
	 * @param string $code code for module
	 * @param string $path path from what module will be moved
	 * @return bool is success - true, else - false
	 */
	public static function moveFiles( $code, $path ) {
		if (!is_dir(WPF_MODULES_DIR . $code)) {
			if (mkdir(WPF_MODULES_DIR . $code)) {
				UtilsWpf::copyDirectories($path, WPF_MODULES_DIR . $code);
				return true;
			} else {
				ErrorsWpf::push(esc_html__('Cannot create module directory. Try to set permission to ' . WPF_MODULES_DIR . ' directory 755 or 777', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
			}
		} else {
			return true;
		}
		return false;
	}
	private static function _getPluginLocations() {
		$locations = array();
		$plug = ReqWpf::getVar('plugin');
		if ( ( empty( $plug ) || is_array($plug) ) && !empty(self::$extPlugName) ) {
			$plug = self::$extPlugName;
		}
			
		if ( empty( $plug ) ) {
			$plug = ReqWpf::getVar( 'checked' );
			if ( isset( $plug[0] ) ) {
				$plug = $plug[0];
			}
		} else if (is_array($plug)) {
			if ( isset( $plug[0] ) ) {
				$plug = $plug[0];
			}
		}

		$locations['plugPath'] = empty($plug) && function_exists('getProPlugFullPathWpf') ? plugin_basename(getProPlugFullPathWpf()) : plugin_basename( trim( $plug ) );
		$locations['plugDir'] = dirname(WP_PLUGIN_DIR . DS . $locations['plugPath']);
		$locations['plugMainFile'] = WP_PLUGIN_DIR . DS . $locations['plugPath'];
		$locations['xmlPath'] = $locations['plugDir'] . DS . 'install.xml';
		return $locations;
	}

	/**
	 * Try to parse xml file with module data
	 *
	 * @param string $xmlPath
	 *
	 * @return array
	 */
	private static function _getModulesFromXml( $xmlPath ) {
		$modDataArr = array();

		if (function_exists('simplexml_load_file')) {
			$xml = UtilsWpf::getXml($xmlPath);
			if ($xml) {
				if (isset($xml->modules) && isset($xml->modules->mod)) {
					$modules = array();
					$xmlMods = $xml->modules->children();
					foreach ($xmlMods->mod as $mod) {
						$modules[] = $mod;
					}
					if (empty($modules)) {
						ErrorsWpf::push(esc_html__('No modules were found in XML file', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
					} else {
						foreach ($modules as $m) {
							$modDataArr[] = UtilsWpf::xmlNodeAttrsToArr($m);
						}
					}
				} else {
					ErrorsWpf::push(esc_html__('Invalid XML file', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
				}
			} else {
				ErrorsWpf::push(esc_html__('No XML file were found', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
			}
		} else {
			$modDataArr = unserialize(WPF_PRO_MODULES);
		}
		return $modDataArr;
	}
	/**
	 * Check whether modules is installed or not, if not and must be activated - install it
	 *
	 * @param array $codes array with modules data to store in database
	 * @param string $path path to plugin file where modules is stored (__FILE__ for example)
	 * @return bool true if check ok, else - false
	 */
	public static function check( $extPlugName = '' ) {
		if (WPF_TEST_MODE) {
			add_action('activated_plugin', array(FrameWpf::_(), 'savePluginActivationErrors'));
		}
		if (!empty($extPlugName)) {
			self::$extPlugName = $extPlugName;
		}
		$locations = self::_getPluginLocations();

		$modules = self::_getModulesFromXml($locations['xmlPath']);
		foreach ($modules as $modDataArr) {
			if (!empty($modDataArr)) {
				//If module Exists - just activate it, we can't check this using FrameWpf::moduleExists because this will not work for multy-site WP
				if (FrameWpf::_()->getTable('modules')->exists($modDataArr['code'], 'code')) {
					self::activate($modDataArr);
					//  if not - install it
				} else {
					$m = '';
					if (!self::install($modDataArr, $locations['plugDir'])) {
						/* translators: %s: module name */
						ErrorsWpf::push(esc_html(sprintf(__('Install %s failed'), $modDataArr['code'])), ErrorsWpf::MOD_INSTALL);
					}
				}
			}
		}
		self::$extPlugName = '';
		if (ErrorsWpf::haveErrors(ErrorsWpf::MOD_INSTALL)) {
			self::displayErrors(false);
			return false;
		}
		update_option(WPF_CODE . '_full_installed', 1);
		return true;
	}
	/**
	 * Public alias for _getCheckRegPlugs()
	 * We will run this each time plugin start to check modules activation messages
	 */
	public static function checkActivationMessages() {
	}
	/**
	 * Deactivate module after deactivating external plugin
	 */
	public static function deactivate( $exclude = array() ) {
		$locations = self::_getPluginLocations();
		$modules = self::_getModulesFromXml($locations['xmlPath']);
		if (empty($exclude) || !is_array($exclude)) {
			$exclude = array();
		}

		foreach ($modules as $modDataArr) {
			if (FrameWpf::_()->moduleActive($modDataArr['code']) && !in_array($modDataArr['code'], $exclude)) { //If module is active - then deacivate it
				if (FrameWpf::_()->getModule('options')->getModel('modules')->put(array(
					'id' => FrameWpf::_()->getModule($modDataArr['code'])->getID(),
					'active' => 0,
				))->error) {
					ErrorsWpf::push(esc_html__('Error Deactivation module', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
				}
			}
		}

		if (ErrorsWpf::haveErrors(ErrorsWpf::MOD_INSTALL)) {
			self::displayErrors(false);
			return false;
		}
		return true;
	}
	public static function activate( $modDataArr ) {
		$locations = self::_getPluginLocations();
		$modules = self::_getModulesFromXml($locations['xmlPath']);
		foreach ($modules as $modDataArr) {
			if (!FrameWpf::_()->moduleActive($modDataArr['code'])) { //If module is not active - then acivate it
				if (FrameWpf::_()->getModule('options')->getModel('modules')->put(array(
					'code' => $modDataArr['code'],
					'active' => 1,
				))->error) {
					ErrorsWpf::push(esc_html__('Error Activating module', 'woo-product-filter'), ErrorsWpf::MOD_INSTALL);
				} else {
					$dbModData = FrameWpf::_()->getModule('options')->getModel('modules')->get(array('code' => $modDataArr['code']));
					if (!empty($dbModData) && !empty($dbModData[0])) {
						$modDataArr['ex_plug_dir'] = $dbModData[0]['ex_plug_dir'];
					}
					self::_runModuleInstall($modDataArr, 'activate');
				}
			}
		}
	} 
	/**
	 * Display all errors for module installer, must be used ONLY if You realy need it
	 */
	public static function displayErrors( $exit = true ) {
		$errors = ErrorsWpf::get(ErrorsWpf::MOD_INSTALL);
		foreach ($errors as $e) {
			echo '<b class="woobewoo-error">' . esc_html($e) . '</b><br />';
		}
		if ($exit) {
			exit();
		}
	}
	public static function uninstall() {
		$isPro = false;
		$locations = self::_getPluginLocations();
		$modules = self::_getModulesFromXml($locations['xmlPath']);
		foreach ($modules as $modDataArr) {
			self::_uninstallTables($modDataArr);
			FrameWpf::_()->getModule('options')->getModel('modules')->delete(array('code' => $modDataArr['code']));
			UtilsWpf::deleteDir(WPF_MODULES_DIR . $modDataArr['code']);

			if ('license' == $modDataArr['code']) {
				$isPro = true;
			}
		}

		if ($isPro) {
			self::uninstallLicense();
		}
	}
	public static function uninstallLicense() {
		FrameWpf::_()->getModule('options')->getModel()->save('license_save_name', '');
	}
	protected static function _uninstallTables( $module ) {
		if (is_dir(WPF_MODULES_DIR . $module['code'] . DS . 'tables')) {
			$tableFiles = UtilsWpf::getFilesList(WPF_MODULES_DIR . $module['code'] . DS . 'tables');
			if (!empty($tableNames)) {
				foreach ($tableFiles as $file) {
					$tableName = str_replace('.php', '', $file);
					if (FrameWpf::_()->getTable($tableName)) {
						FrameWpf::_()->getTable($tableName)->uninstall();
					}
				}
			}
		}
	}
	public static function _installTables( $module, $action = 'install' ) {
		$modDir = empty($module['ex_plug_dir']) ? WPF_MODULES_DIR . $module['code'] . DS : UtilsWpf::getPluginDir($module['ex_plug_dir']) . $module['code'] . DS; 
		if (is_dir($modDir . 'tables')) {
			$tableFiles = UtilsWpf::getFilesList($modDir . 'tables');
			if (!empty($tableFiles)) {
				FrameWpf::_()->extractTables($modDir . 'tables' . DS);
				foreach ($tableFiles as $file) {
					$tableName = str_replace('.php', '', $file);
					if (FrameWpf::_()->getTable($tableName)) {
						FrameWpf::_()->getTable($tableName)->$action();
					}
				}
			}
		}
	}
}
