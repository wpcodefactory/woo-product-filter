<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Mod_Installer Class
 *
 * Handles the installation, activation, deactivation, and management of modules for the plugin.
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Mod_Installer {

	/**
	 * _current.
	 */
	private static $_current = array();

	/**
	 * extPlugName.
	 */
	private static $extPlugName = '';

	/**
	 * Install new WooBeWoo_PF_Module into plugin.
	 *
	 * @version 3.3.2
	 *
	 * @param string $module new WooBeWoo_PF_Module data (@see classes/tables/modules.php)
	 * @param string $path path to the main plugin file from what module is installed
	 * @return bool true - if install success, else - false
	 */
	public static function install( $module, $path ) {
		$plugin_dir = basename( untrailingslashit( WP_PLUGIN_DIR ) );
		$exPlugDest = explode( $plugin_dir, $path );
		if ( ! empty( $exPlugDest[1] ) ) {
			$module['ex_plug_dir'] = str_replace( WPF_DS, '', $exPlugDest[1] );
		}
		$path = $path . WPF_DS . $module['code'];
		if ( ! empty( $module ) && ! empty( $path ) && is_dir( $path ) ) {
			if ( self::isModule( $path ) ) {
				$filesMoved = false;
				if ( empty( $module['ex_plug_dir'] ) ) {
					$filesMoved = self::moveFiles( $module['code'], $path );
				} else {
					$filesMoved = true; // Those modules doesn't need to move their files
				}
				if ( $filesMoved ) {
					if ( WooBeWoo_PF_Frame::_()->getTable( 'modules' )->exists( $module['code'], 'code' ) ) {
						WooBeWoo_PF_Frame::_()->getTable( 'modules' )->delete( array( 'code' => $module['code'] ) );
					}
					if ( 'license' != $module['code'] ) {
						$module['active'] = WooBeWoo_PF_Frame::_()->getTable( 'modules' )->get( 'active', array( 'code' => 'access' ), '', 'one' ) == 1 ? 1 : 0;
					}
					WooBeWoo_PF_Frame::_()->getTable( 'modules' )->insert( $module );
					self::_runModuleInstall( $module );
					self::_installTables( $module );

					return true;
				} else {
					/* translators: %s: module name */
					WooBeWoo_PF_Errors::push( esc_html( sprintf( __( 'Move files for %s failed', 'woo-product-filter' ), $module['code'] ) ), WooBeWoo_PF_Errors::MOD_INSTALL );
				}
			} else {
				/* translators: %s: module name */
				WooBeWoo_PF_Errors::push( esc_html( sprintf( __( '%s is not plugin module', 'woo-product-filter' ), $module['code'] ) ), WooBeWoo_PF_Errors::MOD_INSTALL );
			}
		}

		return false;
	}

	/**
	 * _runModuleInstall.
	 *
	 * @version 3.3.2
	 */
	protected static function _runModuleInstall( $module, $action = 'install' ) {
		$moduleLocationDir = WPF_MODULES_DIR;
		if ( ! empty( $module['ex_plug_dir'] ) ) {
			$moduleLocationDir = WooBeWoo_PF_Utils::getPluginDir( $module['ex_plug_dir'] );
		}
		if ( is_dir( $moduleLocationDir . $module['code'] ) ) {
			$mod_class_name = WPF_CLASS_PREFIX . ucwords( $module['code']  );
			if ( ! class_exists( $mod_class_name ) ) {
				$mod_class_file     = strtolower( str_replace( '_', '-', $mod_class_name ) ) . '.php';
				$mod_class_location = $moduleLocationDir . $module['code']  . WPF_DS . 'class-' . $mod_class_file;

				if ( file_exists( $mod_class_location ) ) {
					require $mod_class_location;
				}
			}

			$moduleClass = woobewoo_pf_toe_get_class_name( $module['code'] );
			$moduleObj   = new $moduleClass( $module );
			if ( $moduleObj ) {
				$moduleObj->$action();
			}
		}
	}

	/**
	 * Check whether is or no module in given path.
	 *
	 * @param string $path path to the module
	 * @return bool true if it is module, else - false
	 */
	public static function isModule( $path ) {
		return true;
	}

	/**
	 * Move files to plugin modules directory.
	 *
	 * @version 3.3.2
	 *
	 * @param string $code code for module.
	 * @param string $path path from what module will be moved.
	 *
	 * @return bool is success - true, else - false.
	 */
	public static function moveFiles( $code, $path ) {
		if ( ! is_dir( WPF_MODULES_DIR . $code ) ) {
			if ( mkdir( WPF_MODULES_DIR . $code ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
				WooBeWoo_PF_Utils::copyDirectories( $path, WPF_MODULES_DIR . $code );
				return true;
			} else {
				WooBeWoo_PF_Errors::push(
					esc_html(
						sprintf(
							/* translators: %s: modules dir */
							__( 'Cannot create module directory. Try to set permission to %s directory 755 or 777', 'woo-product-filter' ),
							WPF_MODULES_DIR
						)
					),
					WooBeWoo_PF_Errors::MOD_INSTALL
				);
			}
		} else {
			return true;
		}
		return false;
	}

	/**
	 * _getPluginLocations.
	 *
	 * @version 3.3.2
	 */
	private static function _getPluginLocations() {
		$locations = array();
		$plug      = WooBeWoo_PF_Req::getVar( 'plugin' );
		if ( ( empty( $plug ) || is_array( $plug ) ) && ! empty( self::$extPlugName ) ) {
			$plug = self::$extPlugName;
		}

		if ( empty( $plug ) ) {
			$plug = WooBeWoo_PF_Req::getVar( 'checked' );
			if ( isset( $plug[0] ) ) {
				$plug = $plug[0];
			}
		} elseif ( is_array( $plug ) ) {
			if ( isset( $plug[0] ) ) {
				$plug = $plug[0];
			}
		}

		$locations['plugPath']     = empty( $plug ) && function_exists( 'getProPlugFullPathWpf' ) ? plugin_basename( getProPlugFullPathWpf() ) : plugin_basename( trim( $plug ) );
		$locations['plugDir']      = dirname( WP_PLUGIN_DIR . WPF_DS . $locations['plugPath'] );
		$locations['plugMainFile'] = WP_PLUGIN_DIR . WPF_DS . $locations['plugPath'];
		$locations['xmlPath']      = $locations['plugDir'] . WPF_DS . 'install.xml';
		return $locations;
	}

	/**
	 * Try to parse xml file with module data.
	 *
	 * @version 3.3.2
	 *
	 * @param string $xmlPath
	 *
	 * @return array
	 */
	private static function _getModulesFromXml( $xmlPath ) {
		$modDataArr = array();

		if ( function_exists( 'simplexml_load_file' ) ) {
			$xml = WooBeWoo_PF_Utils::getXml( $xmlPath );
			if ( $xml ) {
				if ( isset( $xml->modules ) && isset( $xml->modules->mod ) ) {
					$modules = array();
					$xmlMods = $xml->modules->children();
					foreach ( $xmlMods->mod as $mod ) {
						$modules[] = $mod;
					}
					if ( empty( $modules ) ) {
						WooBeWoo_PF_Errors::push( esc_html__( 'No modules were found in XML file', 'woo-product-filter' ), WooBeWoo_PF_Errors::MOD_INSTALL );
					} else {
						foreach ( $modules as $m ) {
							$modDataArr[] = WooBeWoo_PF_Utils::xmlNodeAttrsToArr( $m );
						}
					}
				} else {
					WooBeWoo_PF_Errors::push( esc_html__( 'Invalid XML file', 'woo-product-filter' ), WooBeWoo_PF_Errors::MOD_INSTALL );
				}
			} else {
				WooBeWoo_PF_Errors::push( esc_html__( 'No XML file were found', 'woo-product-filter' ), WooBeWoo_PF_Errors::MOD_INSTALL );
			}
		} else {
			$modDataArr = maybe_unserialize( WPF_PRO_MODULES );
		}
		return $modDataArr;
	}

	/**
	 * Check whether modules is installed or not, if not and must be activated - install it.
	 *
	 * @version 3.3.2
	 *
	 * @param array  $codes array with modules data to store in database.
	 * @param string $path  path to plugin file where modules is stored (__FILE__ for example).
	 *
	 * @return bool true if check ok, else - false.
	 */
	public static function check( $extPlugName = '' ) {
		if ( WPF_TEST_MODE ) {
			add_action( 'activated_plugin', array( WooBeWoo_PF_Frame::_(), 'savePluginActivationErrors' ) );
		}
		if ( ! empty( $extPlugName ) ) {
			self::$extPlugName = $extPlugName;
		}
		$locations = self::_getPluginLocations();

		$modules = self::_getModulesFromXml( $locations['xmlPath'] );
		foreach ( $modules as $modDataArr ) {
			if ( ! empty( $modDataArr ) ) {
				// If module Exists - just activate it, we can't check this using WooBeWoo_PF_Frame::moduleExists because this will not work for multi-site WP
				if ( WooBeWoo_PF_Frame::_()->getTable( 'modules' )->exists( $modDataArr['code'], 'code' ) ) {
					self::activate( $modDataArr );
					// if not - install it
				} else {
					$m = '';
					if ( ! self::install( $modDataArr, $locations['plugDir'] ) ) {
						/* translators: %s: module name */
						WooBeWoo_PF_Errors::push( esc_html( sprintf( __( 'Install %s failed', 'woo-product-filter' ), $modDataArr['code'] ) ), WooBeWoo_PF_Errors::MOD_INSTALL );
					}
				}
			}
		}
		self::$extPlugName = '';
		if ( WooBeWoo_PF_Errors::haveErrors( WooBeWoo_PF_Errors::MOD_INSTALL ) ) {
			self::displayErrors( false );
			return false;
		}
		update_option( WPF_CODE . '_full_installed', 1 );
		return true;
	}

	/**
	 * Public alias for _getCheckRegPlugs().
	 * We will run this each time plugin start to check modules activation messages.
	 */
	public static function checkActivationMessages() {
	}

	/**
	 * Deactivate module after deactivating external plugin.
	 *
	 * @version 3.3.2
	 */
	public static function deactivate( $exclude = array() ) {
		$locations = self::_getPluginLocations();
		$modules   = self::_getModulesFromXml( $locations['xmlPath'] );
		if ( empty( $exclude ) || ! is_array( $exclude ) ) {
			$exclude = array();
		}

		foreach ( $modules as $modDataArr ) {
			if ( WooBeWoo_PF_Frame::_()->moduleActive( $modDataArr['code'] ) && ! in_array( $modDataArr['code'], $exclude ) ) { // If module is active - then deactivate it
				if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel( 'modules' )->put(
					array(
						'id'     => WooBeWoo_PF_Frame::_()->getModule( $modDataArr['code'] )->getID(),
						'active' => 0,
					)
				)->error ) {
					WooBeWoo_PF_Errors::push( esc_html__( 'Error Deactivation module', 'woo-product-filter' ), WooBeWoo_PF_Errors::MOD_INSTALL );
				}
			}
		}

		if ( WooBeWoo_PF_Errors::haveErrors( WooBeWoo_PF_Errors::MOD_INSTALL ) ) {
			self::displayErrors( false );
			return false;
		}
		return true;
	}

	/**
	 * activate.
	 *
	 * @version 3.3.2
	 */
	public static function activate( $modDataArr ) {
		$locations = self::_getPluginLocations();
		$modules   = self::_getModulesFromXml( $locations['xmlPath'] );
		foreach ( $modules as $modDataArr ) {
			if ( ! WooBeWoo_PF_Frame::_()->moduleActive( $modDataArr['code'] ) ) { // If module is not active - then activate it
				if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel( 'modules' )->put(
					array(
						'code'   => $modDataArr['code'],
						'active' => 1,
					)
				)->error ) {
					WooBeWoo_PF_Errors::push( esc_html__( 'Error Activating module', 'woo-product-filter' ), WooBeWoo_PF_Errors::MOD_INSTALL );
				} else {
					$dbModData = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel( 'modules' )->get( array( 'code' => $modDataArr['code'] ) );
					if ( ! empty( $dbModData ) && ! empty( $dbModData[0] ) ) {
						$modDataArr['ex_plug_dir'] = $dbModData[0]['ex_plug_dir'];
					}
					self::_runModuleInstall( $modDataArr, 'activate' );
				}
			}
		}
	}

	/**
	 * Display all errors for module installer, must be used ONLY if You really need it.
	 *
	 * @version 3.3.2
	 */
	public static function displayErrors( $exit = true ) {
		$errors = WooBeWoo_PF_Errors::get( WooBeWoo_PF_Errors::MOD_INSTALL );
		foreach ( $errors as $e ) {
			echo '<b class="woobewoo-error">' . esc_html( $e ) . '</b><br />';
		}
		if ( $exit ) {
			exit();
		}
	}

	/**
	 * uninstall.
	 *
	 * @version 3.3.2
	 */
	public static function uninstall() {
		$locations = self::_getPluginLocations();
		$modules   = self::_getModulesFromXml( $locations['xmlPath'] );
		foreach ( $modules as $modDataArr ) {
			self::_uninstallTables( $modDataArr );
			WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel( 'modules' )->delete( array( 'code' => $modDataArr['code'] ) );
			WooBeWoo_PF_Utils::deleteDir( WPF_MODULES_DIR . $modDataArr['code'] );
		}

		WooBeWoo_PF_Dispatcher::doAction( 'woobewoo_pf_uninstall' );
	}

	/**
	 * _uninstallTables.
	 *
	 * @version 3.3.2
	 */
	protected static function _uninstallTables( $module ) {
		if ( is_dir( WPF_MODULES_DIR . $module['code'] . WPF_DS . 'tables' ) ) {
			$tableFiles = WooBeWoo_PF_Utils::getFilesList( WPF_MODULES_DIR . $module['code'] . WPF_DS . 'tables' );
			if ( ! empty( $tableNames ) ) {
				foreach ( $tableFiles as $file ) {
					$tableName = str_replace( '.php', '', $file );
					if ( WooBeWoo_PF_Frame::_()->getTable( $tableName ) ) {
						WooBeWoo_PF_Frame::_()->getTable( $tableName )->uninstall();
					}
				}
			}
		}
	}

	/**
	 * _installTables.
	 *
	 * @vertion 3.3.2
	 */
	public static function _installTables( $module, $action = 'install' ) {
		$modDir = empty( $module['ex_plug_dir'] ) ? WPF_MODULES_DIR . $module['code'] . WPF_DS : WooBeWoo_PF_Utils::getPluginDir( $module['ex_plug_dir'] ) . $module['code'] . WPF_DS;
		if ( is_dir( $modDir . 'tables' ) ) {
			$tableFiles = WooBeWoo_PF_Utils::getFilesList( $modDir . 'tables' );
			if ( ! empty( $tableFiles ) ) {
				WooBeWoo_PF_Frame::_()->extractTables( $modDir . 'tables' . WPF_DS );
				foreach ( $tableFiles as $file ) {
					$tableName = str_replace( '.php', '', $file );
					if ( WooBeWoo_PF_Frame::_()->getTable( $tableName ) ) {
						WooBeWoo_PF_Frame::_()->getTable( $tableName )->$action();
					}
				}
			}
		}
	}
}
